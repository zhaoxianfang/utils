<?php

declare(strict_types=1);

namespace zxf\Utils\Crypto;

use InvalidArgumentException;
use RuntimeException;

/**
 * 哈希工具类
 * 提供多种哈希算法、HMAC、文件哈希和密码哈希功能
 * 基于PHP 8.2+ 原生hash扩展实现，支持时序安全比较
 *
 * 主要功能：
 * - 支持多种哈希算法（MD5, SHA家族, SHA3, BLAKE2等）
 * - 大文件流式哈希处理
 * - HMAC计算与验证
 * - 密码哈希封装（bcrypt, argon2）
 * - 时序安全比较
 *
 * @package Crypto
 * @author Security Team
 * @version 1.0.0
 * @license MIT
 * @created 2026-04-23
 */
class Hash
{
    /**
     * @var array 支持的哈希算法列表
     */
    private const SUPPORTED_ALGORITHMS = [
        'md5'         => ['bits' => 128,  'secure' => false, 'description' => 'MD5 (不推荐用于安全场景)'],
        'sha1'        => ['bits' => 160,  'secure' => false, 'description' => 'SHA1 (不推荐用于安全场景)'],
        'sha224'      => ['bits' => 224,  'secure' => true,  'description' => 'SHA-224'],
        'sha256'      => ['bits' => 256,  'secure' => true,  'description' => 'SHA-256 (推荐)'],
        'sha384'      => ['bits' => 384,  'secure' => true,  'description' => 'SHA-384'],
        'sha512'      => ['bits' => 512,  'secure' => true,  'description' => 'SHA-512'],
        'sha3-224'    => ['bits' => 224,  'secure' => true,  'description' => 'SHA3-224'],
        'sha3-256'    => ['bits' => 256,  'secure' => true,  'description' => 'SHA3-256 (前沿)'],
        'sha3-384'    => ['bits' => 384,  'secure' => true,  'description' => 'SHA3-384'],
        'sha3-512'    => ['bits' => 512,  'secure' => true,  'description' => 'SHA3-512'],
        'ripemd160'   => ['bits' => 160,  'secure' => true,  'description' => 'RIPEMD-160'],
        'blake2b'     => ['bits' => 512,  'secure' => true,  'description' => 'BLAKE2b'],
        'blake2s'     => ['bits' => 256,  'secure' => true,  'description' => 'BLAKE2s'],
        'crc32'       => ['bits' => 32,   'secure' => false, 'description' => 'CRC32 (校验用)'],
        'crc32b'      => ['bits' => 32,   'secure' => false, 'description' => 'CRC32b (校验用)'],
    ];

    /**
     * @var array 支持的HMAC算法列表
     */
    private const SUPPORTED_HMAC_ALGORITHMS = [
        'sha256', 'sha384', 'sha512', 'sha3-256', 'sha3-384', 'sha3-512', 'sha1', 'md5'
    ];

    /**
     * @var int 默认文件读取块大小（1MB）
     */
    private const DEFAULT_CHUNK_SIZE = 1048576;

    /**
     * @var bool 调试模式开关
     */
    private bool $debugMode = false;

    /**
     * @var array 性能统计
     */
    private array $performanceStats = [];

    /**
     * 构造函数
     *
     * @param bool $debugMode 是否启用调试模式
     */
    public function __construct(bool $debugMode = false)
    {
        $this->debugMode = $debugMode;
    }

    /**
     * 计算字符串哈希值
     *
     * @param string $data   要哈希的数据
     * @param string $algorithm 哈希算法，默认sha256
     * @param bool   $binary 是否返回二进制结果，默认false返回十六进制字符串
     * @return string 哈希结果
     *
     * @throws InvalidArgumentException 当算法不支持时抛出
     */
    public static function calculate(string $data, string $algorithm = 'sha256', bool $binary = false): string
    {
        self::validateAlgorithm($algorithm);

        return hash($algorithm, $data, $binary);
    }

    /**
     * 计算文件哈希值（支持大文件流式处理）
     *
     * @param string $filePath  文件路径
     * @param string $algorithm 哈希算法，默认sha256
     * @param bool   $binary    是否返回二进制结果
     * @param int    $chunkSize 读取块大小，默认1MB
     * @return string 哈希结果
     *
     * @throws InvalidArgumentException 当文件不存在或算法不支持时抛出
     * @throws RuntimeException 当读取文件失败时抛出
     */
    public static function file(string $filePath, string $algorithm = 'sha256', bool $binary = false, int $chunkSize = self::DEFAULT_CHUNK_SIZE): string
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("文件不存在: {$filePath}");
        }

        if (!is_readable($filePath)) {
            throw new RuntimeException("文件无法读取: {$filePath}");
        }

        self::validateAlgorithm($algorithm);

        $context = hash_init($algorithm);
        if ($context === false) {
            throw new RuntimeException("初始化哈希上下文失败: {$algorithm}");
        }

        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new RuntimeException("无法打开文件: {$filePath}");
        }

        try {
            while (!feof($handle)) {
                $chunk = fread($handle, $chunkSize);
                if ($chunk === false) {
                    throw new RuntimeException("读取文件失败: {$filePath}");
                }
                hash_update($context, $chunk);
            }
        } finally {
            fclose($handle);
        }

        return hash_final($context, $binary);
    }

    /**
     * 计算多个文件的哈希值
     *
     * @param array  $filePaths 文件路径数组
     * @param string $algorithm 哈希算法，默认sha256
     * @return array 以文件路径为键的哈希结果数组
     */
    public static function files(array $filePaths, string $algorithm = 'sha256'): array
    {
        $results = [];
        foreach ($filePaths as $filePath) {
            try {
                $results[$filePath] = [
                    'hash'    => self::file($filePath, $algorithm),
                    'success' => true,
                    'error'   => null,
                ];
            } catch (Exception $e) {
                $results[$filePath] = [
                    'hash'    => null,
                    'success' => false,
                    'error'   => $e->getMessage(),
                ];
            }
        }
        return $results;
    }

    /**
     * 计算HMAC值
     *
     * @param string $data      要计算HMAC的数据
     * @param string $key       HMAC密钥
     * @param string $algorithm 哈希算法，默认sha256
     * @param bool   $binary    是否返回二进制结果
     * @return string HMAC结果
     *
     * @throws InvalidArgumentException 当算法不支持时抛出
     */
    public static function hmac(string $data, string $key, string $algorithm = 'sha256', bool $binary = false): string
    {
        self::validateHmacAlgorithm($algorithm);

        if (empty($key)) {
            throw new InvalidArgumentException('HMAC密钥不能为空');
        }

        return hash_hmac($algorithm, $data, $key, $binary);
    }

    /**
     * 验证HMAC值（时序安全比较）
     *
     * @param string $data      原始数据
     * @param string $key       HMAC密钥
     * @param string $hmac      要验证的HMAC值
     * @param string $algorithm 哈希算法，默认sha256
     * @return bool 验证结果
     */
    public static function verifyHmac(string $data, string $key, string $hmac, string $algorithm = 'sha256'): bool
    {
        $calculated = self::hmac($data, $key, $algorithm);
        return self::timingSafeCompare($calculated, $hmac);
    }

    /**
     * 计算文件HMAC值（支持大文件）
     *
     * @param string $filePath  文件路径
     * @param string $key       HMAC密钥
     * @param string $algorithm 哈希算法，默认sha256
     * @param bool   $binary    是否返回二进制结果
     * @param int    $chunkSize 读取块大小
     * @return string HMAC结果
     */
    public static function hmacFile(string $filePath, string $key, string $algorithm = 'sha256', bool $binary = false, int $chunkSize = self::DEFAULT_CHUNK_SIZE): string
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("文件不存在: {$filePath}");
        }

        self::validateHmacAlgorithm($algorithm);

        if (empty($key)) {
            throw new InvalidArgumentException('HMAC密钥不能为空');
        }

        $context = hash_init($algorithm, HASH_HMAC, $key);
        if ($context === false) {
            throw new RuntimeException("初始化HMAC上下文失败");
        }

        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new RuntimeException("无法打开文件: {$filePath}");
        }

        try {
            while (!feof($handle)) {
                $chunk = fread($handle, $chunkSize);
                if ($chunk === false) {
                    throw new RuntimeException("读取文件失败: {$filePath}");
                }
                hash_update($context, $chunk);
            }
        } finally {
            fclose($handle);
        }

        return hash_final($context, $binary);
    }

    /**
     * 密码哈希（使用PHP原生password_hash，推荐用于用户密码）
     *
     * @param string $password 明文密码
     * @param string $algo     算法，默认PASSWORD_DEFAULT（当前为 bcrypt）
     * @param array  $options  算法选项（如cost）
     * @return string 密码哈希值
     *
     * @throws RuntimeException 当哈希失败时抛出
     */
    public static function password(string $password, string|int|null $algo = PASSWORD_DEFAULT, array $options = []): string
    {
        if (empty($password)) {
            throw new InvalidArgumentException('密码不能为空');
        }

        $hash = password_hash($password, $algo ?? PASSWORD_DEFAULT, $options);
        if ($hash === false) {
            throw new RuntimeException('密码哈希生成失败');
        }

        return $hash;
    }

    /**
     * 验证密码
     *
     * @param string $password 明文密码
     * @param string $hash     密码哈希值
     * @return bool 验证结果
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * 检查密码哈希是否需要重新计算（如cost升级）
     *
     * @param string $hash    密码哈希值
     * @param string|int|null $algo   目标算法，默认PASSWORD_DEFAULT
     * @param array  $options 算法选项
     * @return bool 是否需要重新哈希
     */
    public static function passwordNeedsRehash(string $hash, string|int|null $algo = PASSWORD_DEFAULT, array $options = []): bool
    {
        return password_needs_rehash($hash, $algo ?? PASSWORD_DEFAULT, $options);
    }

    /**
     * PBKDF2密钥派生
     *
     * @param string $password   密码
     * @param string $salt       盐值
     * @param int    $iterations 迭代次数，默认100000
     * @param int    $length     输出密钥长度（字节），默认32
     * @param string $algorithm  哈希算法，默认sha256
     * @return string 派生密钥（二进制）
     */
    public static function pbkdf2(string $password, string $salt, int $iterations = 100000, int $length = 32, string $algorithm = 'sha256'): string
    {
        self::validateAlgorithm($algorithm);

        if ($iterations < 1000) {
            throw new InvalidArgumentException('迭代次数至少为1000');
        }

        if ($length < 1) {
            throw new InvalidArgumentException('密钥长度必须大于0');
        }

        $key = hash_pbkdf2($algorithm, $password, $salt, $iterations, $length, true);
        if ($key === false) {
            throw new RuntimeException('PBKDF2密钥派生失败');
        }

        return $key;
    }

    /**
     * HKDF密钥派生（RFC 5869）
     *
     * @param string $ikm   输入密钥材料
     * @param string $salt  盐值（可选）
     * @param string $info  上下文信息（可选）
     * @param int    $length 输出长度（字节），默认32
     * @param string $algorithm 哈希算法，默认sha256
     * @return string 派生密钥
     */
    public static function hkdf(string $ikm, string $salt = '', string $info = '', int $length = 32, string $algorithm = 'sha256'): string
    {
        self::validateAlgorithm($algorithm);

        if ($length < 1 || $length > 255 * (strlen(hash($algorithm, '', true)))) {
            throw new InvalidArgumentException('HKDF输出长度超出范围');
        }

        // Extract
        $prk = hash_hmac($algorithm, $ikm, $salt, true);

        // Expand
        $okm = '';
        $previousBlock = '';
        $n = ceil($length / strlen($prk));

        for ($i = 1; $i <= $n; $i++) {
            $previousBlock = hash_hmac($algorithm, $previousBlock . $info . chr($i), $prk, true);
            $okm .= $previousBlock;
        }

        return substr($okm, 0, $length);
    }

    /**
     * 时序安全字符串比较
     *
     * @param string $a 字符串A
     * @param string $b 字符串B
     * @return bool 比较结果
     */
    public static function timingSafeCompare(string $a, string $b): bool
    {
        return hash_equals($a, $b);
    }

    /**
     * 生成盐值
     *
     * @param int $length 盐值长度（字节），默认16
     * @return string 随机盐值（二进制）
     */
    public static function generateSalt(int $length = 16): string
    {
        if ($length < 8) {
            throw new InvalidArgumentException('盐值长度至少为8字节');
        }

        return random_bytes($length);
    }

    /**
     * 获取支持的算法列表
     *
     * @param bool $secureOnly 仅返回安全算法
     * @return array 算法信息数组
     */
    public static function getSupportedAlgorithms(bool $secureOnly = false): array
    {
        if ($secureOnly) {
            return array_filter(self::SUPPORTED_ALGORITHMS, fn(array $info): bool => $info['secure']);
        }

        return self::SUPPORTED_ALGORITHMS;
    }

    /**
     * 获取算法信息
     *
     * @param string $algorithm 算法名称
     * @return array|null 算法信息，不存在时返回null
     */
    public static function getAlgorithmInfo(string $algorithm): ?array
    {
        return self::SUPPORTED_ALGORITHMS[$algorithm] ?? null;
    }

    /**
     * 检查算法是否支持
     *
     * @param string $algorithm 算法名称
     * @return bool 是否支持
     */
    public static function isAlgorithmSupported(string $algorithm): bool
    {
        return isset(self::SUPPORTED_ALGORITHMS[$algorithm]) && in_array($algorithm, hash_algos(), true);
    }

    /**
     * 计算数据的多重哈希（哈希链）
     *
     * @param string $data       要哈希的数据
     * @param array  $algorithms 算法列表，按顺序应用
     * @param bool   $binary     最终是否返回二进制
     * @return string 多重哈希结果
     */
    public static function multiHash(string $data, array $algorithms, bool $binary = false): string
    {
        if (empty($algorithms)) {
            throw new InvalidArgumentException('算法列表不能为空');
        }

        $result = $data;
        $lastIndex = count($algorithms) - 1;

        foreach ($algorithms as $index => $algorithm) {
            self::validateAlgorithm($algorithm);
            $isLast = ($index === $lastIndex);
            $result = hash($algorithm, $result, $isLast && $binary);
        }

        return $result;
    }

    /**
     * 生成校验和文件内容（类似shasum格式）
     *
     * @param array  $filePaths 文件路径数组
     * @param string $algorithm 哈希算法，默认sha256
     * @return string 校验和文本内容
     */
    public static function generateChecksumFile(array $filePaths, string $algorithm = 'sha256'): string
    {
        $lines = [];
        foreach ($filePaths as $filePath) {
            try {
                $hash = self::file($filePath, $algorithm);
                $filename = basename($filePath);
                $lines[] = "{$hash}  {$filename}";
            } catch (Exception $e) {
                $lines[] = "# ERROR: {$filePath} - " . $e->getMessage();
            }
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * 验证校验和文件
     *
     * @param string $checksumFile 校验和文件路径
     * @param string $baseDir      文件所在基础目录
     * @param string $algorithm    哈希算法，默认sha256
     * @return array 验证结果
     */
    public static function verifyChecksumFile(string $checksumFile, string $baseDir = '', string $algorithm = 'sha256'): array
    {
        if (!file_exists($checksumFile)) {
            throw new InvalidArgumentException("校验和文件不存在: {$checksumFile}");
        }

        $results = [];
        $lines = file($checksumFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            if (str_starts_with($line, '#')) {
                continue;
            }

            $parts = preg_split('/\s+/', trim($line), 2);
            if (count($parts) !== 2) {
                continue;
            }

            [$expectedHash, $filename] = $parts;
            $filePath = $baseDir ? rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR . $filename : $filename;

            try {
                $actualHash = self::file($filePath, $algorithm);
                $results[$filename] = [
                    'valid'   => self::timingSafeCompare($expectedHash, $actualHash),
                    'expected' => $expectedHash,
                    'actual'   => $actualHash,
                    'error'    => null,
                ];
            } catch (Exception $e) {
                $results[$filename] = [
                    'valid'    => false,
                    'expected' => $expectedHash,
                    'actual'   => null,
                    'error'    => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * 验证算法有效性
     *
     * @param string $algorithm 算法名称
     * @throws InvalidArgumentException 当算法不支持时抛出
     */
    private static function validateAlgorithm(string $algorithm): void
    {
        if (!in_array($algorithm, hash_algos(), true)) {
            throw new InvalidArgumentException("不支持的哈希算法: {$algorithm}");
        }
    }

    /**
     * 验证HMAC算法有效性
     *
     * @param string $algorithm 算法名称
     * @throws InvalidArgumentException 当算法不支持时抛出
     */
    private static function validateHmacAlgorithm(string $algorithm): void
    {
        if (!in_array($algorithm, self::SUPPORTED_HMAC_ALGORITHMS, true)) {
            throw new InvalidArgumentException("不支持的HMAC算法: {$algorithm}");
        }

        self::validateAlgorithm($algorithm);
    }
}
