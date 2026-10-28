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
 *
 * @example
 * // 计算 SHA-256 哈希
 * $hash = Hash::sha256('hello world');
 *
 * // 计算带密钥的 HMAC（用于接口签名校验、防篡改）
 * $sign = Hash::hmac('message', 'secret-key', 'sha256');
 *
 * // 安全验证 HMAC（使用 hash_equals 防时序攻击）
 * if (Hash::verifyHmac('message', 'secret-key', $sign)) {
 *     // 签名有效
 * }
 */

declare(strict_types=1);

namespace zxf\Utils\Security;

use InvalidArgumentException;
use RuntimeException;

/**
 * 通用哈希工具类
 *
 * 提供 MD5、SHA 系列、HMAC、文件哈希等操作。MD5 仅适用于非安全校验；
 * 签名校验请使用 Hash::verifyHmac()（内部用 hash_equals 防时序攻击）。
 *
 * @package zxf\Utils\Security
 */
final class Hash
{
    /**
     * 计算字符串的 MD5 哈希（32位小写十六进制）
     *
     * 注意：MD5 已被证实存在严重碰撞漏洞，不具备密码学安全性，
     * 仅适用于非安全的校验/指纹场景，切勿用于密码存储或签名。
     *
     * @param string $data 待哈希的原始字符串
     * @param bool   $raw  为 true 时返回原始二进制（16 字节），否则返回 32 位十六进制
     * @return string 哈希结果（十六进制或原始二进制，取决于 $raw）
     */
    public static function md5(string $data, bool $raw = false): string
    {
        return md5($data, $raw);
    }

    /**
     * 计算字符串的 SHA-1 哈希（40位小写十六进制）
     *
     * 注意：SHA-1 同样已被攻破（可构造碰撞），不建议用于安全签名，
     * 仅可用于兼容性或完整性校验场景。
     *
     * @param string $data 待哈希的原始字符串
     * @param bool   $raw  为 true 时返回原始二进制（20 字节），否则返回 40 位十六进制
     * @return string 哈希结果（十六进制或原始二进制，取决于 $raw）
     */
    public static function sha1(string $data, bool $raw = false): string
    {
        return sha1($data, $raw);
    }

    /**
     * 计算字符串的 SHA-256 哈希（64位小写十六进制）
     *
     * SHA-256 属于 SHA-2 家族，目前仍被广泛认为是安全的单向哈希算法，
     * 适用于数据指纹、令牌生成等场景。
     *
     * @param string $data 待哈希的原始字符串
     * @param bool   $raw  为 true 时返回原始二进制（32 字节），否则返回 64 位十六进制
     * @return string 哈希结果（十六进制或原始二进制，取决于 $raw）
     */
    public static function sha256(string $data, bool $raw = false): string
    {
        return hash('sha256', $data, $raw);
    }

    /**
     * 计算字符串的 SHA-512 哈希（128位小写十六进制）
     *
     * SHA-512 输出更长，抗碰撞性更强，适用于对安全性要求更高的场景。
     *
     * @param string $data 待哈希的原始字符串
     * @param bool   $raw  为 true 时返回原始二进制（64 字节），否则返回 128 位十六进制
     * @return string 哈希结果（十六进制或原始二进制，取决于 $raw）
     */
    public static function sha512(string $data, bool $raw = false): string
    {
        return hash('sha512', $data, $raw);
    }

    /**
     * 使用指定算法计算哈希
     *
     * @param string $data 待哈希的原始字符串
     * @param string $algo 算法名称，如 'md5', 'sha256', 'sha3-256' 等
     * @param bool   $raw  为 true 时返回原始二进制，否则返回十六进制
     * @return string 哈希结果（十六进制或原始二进制，取决于 $raw）
     * @throws InvalidArgumentException 当算法不被 hash_algos() 支持时抛出
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
     * HMAC 将密钥与消息混合后再哈希，可在共享密钥的前提下验证数据完整性
     * 与真实性，常用于接口签名、消息防篡改等场景。
     *
     * @param string $data 要哈希的数据
     * @param string $key  密钥（应足够随机且保密）
     * @param string $algo 算法名称
     * @param bool   $raw  为 true 时返回原始二进制，否则返回十六进制
     * @return string HMAC 哈希结果（十六进制或原始二进制，取决于 $raw）
     * @throws InvalidArgumentException 当算法不被 hash_hmac_algos() 支持时抛出
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
     *
     * 用相同参数重新计算 HMAC，并以 hash_equals() 做定长比较，
     * 比较耗时与内容无关，可防时序攻击。算法必须在 hash_hmac_algos() 支持列表中。
     *
     * @param string $data     原始数据
     * @param string $key      与生成时相同的密钥
     * @param string $expected 待校验的 HMAC 字符串（十六进制形式）
     * @param string $algo     算法名称，需与生成时一致，默认 sha256
     * @return bool 签名匹配返回 true，否则返回 false
     *
     * @throws InvalidArgumentException 算法不受 hash_hmac_algos() 支持时抛出
     */
    public static function verifyHmac(string $data, string $key, string $expected, string $algo = 'sha256'): bool
    {
        return hash_equals($expected, self::hmac($data, $key, $algo));
    }

    /**
     * 计算文件的哈希值
     *
     * 采用流式读取（hash_file），大文件也不会一次性载入内存；
     * 先校验文件存在，再校验计算结果，任一失败都会抛出异常。
     *
     * @param string $path 文件路径
     * @param string $algo 算法名称，如 'sha256'、'md5'
     * @param bool   $raw  为 true 时返回原始二进制，否则返回十六进制
     * @return string 文件内容的哈希值（十六进制或原始二进制，取决于 $raw）
     *
     * @throws RuntimeException 文件不存在或无法读取/计算哈希时抛出
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
     *
     * 同 file()，但把密钥混入哈希，可用于校验文件在传输过程中是否被篡改；
     * 同样以流式方式读取，适用于大文件。
     *
     * @param string $path 文件路径
     * @param string $key  密钥（应足够随机且保密）
     * @param string $algo 算法名称，需被 hash_hmac_algos() 支持
     * @param bool   $raw  为 true 时返回原始二进制，否则返回十六进制
     * @return string 文件的 HMAC 值（十六进制或原始二进制，取决于 $raw）
     *
     * @throws RuntimeException 文件不存在或无法计算 HMAC 时抛出
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
     *
     * 结果统一格式化为 8 位小写十六进制（左侧补零），因此正负结果都能得到
     * 固定长度字符串。CRC32 不具抗碰撞能力，只适合做传输/存储的快速完整性检查。
     *
     * @param string $data 待计算的数据
     * @return string 8 位小写十六进制校验值，如 "cbf43926"
     */
    public static function crc32(string $data): string
    {
        return sprintf('%08x', crc32($data));
    }

    /**
     * 生成时间变化的一次性密码（TOTP 风格，简化版）
     *
     * 以「当前时间 / 时间步长」为计数器，用 HMAC-SHA1 计算后做动态截断
     * （RFC 4226 的 offset 取值法），再对 10^$digits 取模并左侧补零。
     * 同一时间窗内结果恒定，跨窗口后自动变化；未提供密钥派生与容错窗口，
     * 与标准 TOTP 实现不保证互通。
     *
     * @param string $secret 共享密钥
     * @param int $timeStep 时间步长（秒），必须大于 0
     * @param int $digits 输出位数，1~10 之间较为合理
     * @return string 指定位数的一次性密码，如 "012345"
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
     *
     * 直接返回 hash_algos() 的结果，可用于校验 make() 的 $algo 是否可用。
     *
     * @return array<int, string> 算法名称列表，如 ['md5', 'sha256', ...]
     */
    public static function algorithms(): array
    {
        return hash_algos();
    }

    /**
     * 获取系统支持的所有 HMAC 算法列表
     *
     * 直接返回 hash_hmac_algos() 的结果；该列表是 hash_algos() 的子集，
     * 可用于校验 hmac()/verifyHmac() 的 $algo 是否可用。
     *
     * @return array<int, string> HMAC 算法名称列表
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
