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
     * 对多个值进行一致性哈希（用于负载均衡等场景）
     */
    public static function consistent(string $key, array $nodes): string
    {
        if ($nodes === []) {
            throw new InvalidArgumentException('节点列表不能为空');
        }

        $hashes = [];
        foreach ($nodes as $node) {
            $hashes[$node] = hexdec(substr(self::md5($key . (string) $node), 0, 8));
        }
        asort($hashes);
        return array_key_first($hashes);
    }
}
