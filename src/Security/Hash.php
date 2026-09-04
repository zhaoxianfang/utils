<?php

/**
 * ============================================================================
 * 通用哈希工具类
 * ============================================================================
 *
 * 提供 MD5、SHA 系列、HMAC、文件哈希等常用哈希操作。
 * 基于 PHP 8.2+ 的 hash 扩展，支持多种算法。
 *
 * @package     zxf\Utils\Security
 * @author      zxf\Utils
 * @version     1.0.0
 * @license     MIT
 * @link        https://github.com/zhaoxianfang/utils
 * @since       1.0.0
 */

declare(strict_types=1);

namespace zxf\Utils\Security;

use InvalidArgumentException;
use RuntimeException;

final class Hash
{
    /**
     * 计算字符串的 MD5 哈希（32位小写十六进制）
     */
    public static function md5(string $data, bool $raw = false): string
    {
        return md5($data, $raw);
    }

    /**
     * 计算字符串的 SHA-1 哈希（40位小写十六进制）
     */
    public static function sha1(string $data, bool $raw = false): string
    {
        return sha1($data, $raw);
    }

    /**
     * 计算字符串的 SHA-256 哈希（64位小写十六进制）
     */
    public static function sha256(string $data, bool $raw = false): string
    {
        return hash('sha256', $data, $raw);
    }

    /**
     * 计算字符串的 SHA-512 哈希（128位小写十六进制）
     */
    public static function sha512(string $data, bool $raw = false): string
    {
        return hash('sha512', $data, $raw);
    }

    /**
     * 使用指定算法计算哈希
     *
     * @param string $algo 算法名称，如 'md5', 'sha256', 'sha3-256' 等
     */
    public static function make(string $data, string $algo = 'sha256', bool $raw = false): string
    {
        if (! in_array($algo, hash_algos(), true)) {
            throw new InvalidArgumentException("不支持的哈希算法: {$algo}");
        }
        return hash($algo, $data, $raw);
    }

    /**
     * 计算 HMAC 哈希
     *
     * @param string $data 要哈希的数据
     * @param string $key 密钥
     * @param string $algo 算法名称
     */
    public static function hmac(string $data, string $key, string $algo = 'sha256', bool $raw = false): string
    {
        if (! in_array($algo, hash_hmac_algos(), true)) {
            throw new InvalidArgumentException("不支持的 HMAC 算法: {$algo}");
        }
        return hash_hmac($algo, $data, $key, $raw);
    }

    /**
     * 验证 HMAC 哈希
     */
    public static function verifyHmac(string $data, string $key, string $expected, string $algo = 'sha256'): bool
    {
        return hash_equals($expected, self::hmac($data, $key, $algo));
    }

    /**
     * 计算文件的哈希值
     */
    public static function file(string $path, string $algo = 'sha256', bool $raw = false): string
    {
        if (! is_file($path)) {
            throw new RuntimeException("文件不存在: {$path}");
        }
        $hash = hash_file($algo, $path, $raw);
        if ($hash === false) {
            throw new RuntimeException("无法计算文件哈希: {$path}");
        }
        return $hash;
    }

    /**
     * 计算 HMAC 文件哈希
     */
    public static function fileHmac(string $path, string $key, string $algo = 'sha256', bool $raw = false): string
    {
        if (! is_file($path)) {
            throw new RuntimeException("文件不存在: {$path}");
        }
        $hash = hash_hmac_file($algo, $path, $key, $raw);
        if ($hash === false) {
            throw new RuntimeException("无法计算 HMAC 文件哈希: {$path}");
        }
        return $hash;
    }

    /**
     * 计算字符串的 CRC32（用于快速校验）
     */
    public static function crc32(string $data): string
    {
        return sprintf('%08x', crc32($data));
    }

    /**
     * 生成时间变化的一次性密码（TOTP 风格，简化版）
     *
     * @param string $secret 共享密钥
     * @param int $timeStep 时间步长（秒）
     * @param int $digits 输出位数
     */
    public static function timeHash(string $secret, int $timeStep = 30, int $digits = 6): string
    {
        $time = floor(time() / $timeStep);
        $hash = hash_hmac('sha1', pack('N*', 0) . pack('N*', $time), $secret, true);
        $offset = ord($hash[-1]) & 0x0F;
        $code = ((ord($hash[$offset]) & 0x7F) << 24 |
                (ord($hash[$offset + 1]) & 0xFF) << 16 |
                (ord($hash[$offset + 2]) & 0xFF) << 8 |
                (ord($hash[$offset + 3]) & 0xFF)) % (10 ** $digits);
        return str_pad((string) $code, $digits, '0', STR_PAD_LEFT);
    }

    /**
     * 获取系统支持的所有哈希算法列表
     */
    public static function algorithms(): array
    {
        return hash_algos();
    }

    /**
     * 获取系统支持的所有 HMAC 算法列表
     */
    public static function hmacAlgorithms(): array
    {
        return hash_hmac_algos();
    }

    /**
     * 一致性哈希：为给定键在节点列表中选出唯一节点（用于负载均衡、分片路由等场景）
     *
     * 采用 HRW（Rendezvous / Highest Random Weight）算法：对每个节点计算
     * weight = hash(key + node)，取权重**最大**的节点。该算法具备一致性哈希的核心性质：
     * 新增或移除节点时，只会重新映射原本落在该节点上的键，不会引起全量重分布；
     * 且无需构造虚拟节点环，实现简单、分布均匀。
     *
     * 权重计算取 md5 前 8 位十六进制（32 位无符号整数）。
     * 实测 crc32 的权重分布极不均匀（3 节点下呈 50%/25%/25%），
     * 故不使用；hexdec() 在 32 位 PHP 上会返回 float，
     * 但 double 的 53 位精度足以精确表示 32 位整数，数值排序依然正确。
     *
     * @param string        $key   需要路由的键
     * @param array<string|int> $nodes 候选节点列表（字符串或整数）
     * @return string|int 选中的节点（保持传入时的原始类型）
     *
     * @throws InvalidArgumentException 节点列表为空或包含非法类型时抛出
     */
    public static function consistent(string $key, array $nodes): string|int
    {
        if ($nodes === []) {
            throw new InvalidArgumentException('节点列表不能为空');
        }

        $weights = [];
        $nodeMap = [];

        foreach ($nodes as $node) {
            if (! is_string($node) && ! is_int($node)) {
                throw new InvalidArgumentException(
                    '节点必须是字符串或整数，' . get_debug_type($node) . ' 不受支持'
                );
            }

            $nodeKey = (string) $node;
            if (isset($nodeMap[$nodeKey])) {
                continue; // 节点去重
            }

            $nodeMap[$nodeKey] = $node;
            // 使用 \0 分隔，避免 ("ab","c") 与 ("a","bc") 拼接出相同的键
            $weights[$nodeKey] = hexdec(substr(md5($key . "\0" . $nodeKey), 0, 8));
        }

        arsort($weights, SORT_NUMERIC);

        $winner = array_key_first($weights);

        return $nodeMap[$winner];
    }
}
