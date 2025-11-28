<?php

namespace zxf\Utils\Crypto;

use Exception;
use InvalidArgumentException;
use RuntimeException;

/**
 * AES加密解密类
 * 支持多种加密模式、填充方式、密钥长度和认证加密
 * 基于PHP 8.2+ openssl扩展实现，包含完整的错误处理和调试功能
 *
 * 主要功能：
 * - 支持所有主流AES加密模式（CBC, ECB, CFB, OFB, CTR, GCM, XTS）
 * - 支持文件加密解密和批量操作
 * - 支持HMAC签名和验证
 * - 支持PBKDF2密钥派生
 * - 完整的调试日志和性能监控
 * - 增强的文件格式和完整性验证
 *
 * @package Crypto
 * @author Security Team
 * @version 7.0.0
 * @license MIT
 * @created 2026-01-01
 * @updated 2026-01-15
 */
class AES
{
    /**
     * @var string 加密密钥（二进制格式）
     * @access private
     */
    private string $key;

    /**
     * @var string 加密方法（openssl支持的算法字符串）
     * @access private
     */
    private string $method;

    /**
     * @var string 初始化向量（二进制格式）
     * @access private
     */
    private string $iv;

    /**
     * @var array 支持的加密方法列表
     * @access private
     */
    private const SUPPORTED_METHODS = [
        // CBC模式 - 最常用，需要IV，推荐用于文件加密
        'aes-128-cbc', 'aes-192-cbc', 'aes-256-cbc',

        // ECB模式 - 不推荐，仅用于兼容性，不需要IV
        'aes-128-ecb', 'aes-192-ecb', 'aes-256-ecb',

        // CFB模式 - 流加密模式，支持不同反馈位数
        'aes-128-cfb', 'aes-192-cfb', 'aes-256-cfb',
        'aes-128-cfb1', 'aes-192-cfb1', 'aes-256-cfb1',
        'aes-128-cfb8', 'aes-192-cfb8', 'aes-256-cfb8',

        // OFB模式 - 流加密模式
        'aes-128-ofb', 'aes-192-ofb', 'aes-256-ofb',

        // CTR模式 - 流加密模式，推荐用于高性能场景
        'aes-128-ctr', 'aes-192-ctr', 'aes-256-ctr',

        // GCM模式 - 认证加密，推荐用于网络传输
        'aes-128-gcm', 'aes-192-gcm', 'aes-256-gcm',

        // XTS模式 - 磁盘加密专用
        'aes-128-xts', 'aes-256-xts'
    ];

    /**
     * @var array 支持的填充方式
     * @access private
     */
    private const SUPPORTED_PADDINGS = [
        OPENSSL_RAW_DATA => '无填充',
        OPENSSL_ZERO_PADDING => '零填充',
        OPENSSL_PKCS1_PADDING => 'PKCS#1填充', // 仅RSA
    ];

    /**
     * @var int 默认分块大小（64KB）- 文件加密时使用
     * @access private
     */
    private const DEFAULT_CHUNK_SIZE = 65536;

    /**
     * @var int 默认PBKDF2迭代次数 - 密码派生密钥时使用
     * @access private
     */
    private const DEFAULT_PBKDF2_ITERATIONS = 100000;

    /**
     * @var string 默认哈希算法
     * @access private
     */
    private const DEFAULT_HASH_ALGORITHM = 'sha256';

    /**
     * @var string 文件格式版本
     * @access private
     */
    private const FILE_FORMAT_VERSION = 'AESv4';

    /**
     * @var array 加密上下文信息
     * @access private
     */
    private array $encryptionContext = [];

    /**
     * @var bool 调试模式开关
     * @access private
     */
    private bool $debugMode = false;

    /**
     * @var array 性能统计信息
     * @access private
     */
    private array $performanceStats = [];

    /**
     * @var int 内存使用限制（字节）
     * @access private
     */
    private const MEMORY_LIMIT = 134217728; // 128MB

    /**
     * 构造函数 - 初始化AES加密器
     *
     * @param string $key 加密密钥（二进制格式）
     * @param string $method 加密方法（可选值：见SUPPORTED_METHODS）
     * @param string|null $iv 初始化向量（null时自动生成）
     * @param bool $debugMode 调试模式开关（默认false）
     *
     * @throws InvalidArgumentException 当参数验证失败时抛出
     * @throws RuntimeException 当加密器初始化失败时抛出
     *
     * 使用示例：
     * $aes = new AES($key, 'aes-256-cbc', null, true);
     */
    public function __construct(
        string $key,
        string $method = 'aes-256-cbc',
        ?string $iv = null,
        bool $debugMode = false
    ) {
        // 设置调试模式
        $this->debugMode = $debugMode;
        $this->logDebug("开始初始化AES加密器");

        // 验证加密方法
        $this->validateMethod($method);

        // 验证密钥长度
        $this->validateKey($key, $method);

        // 设置密钥和方法
        $this->key = $key;
        $this->method = $method;

        // 生成或验证IV
        if ($iv === null) {
            $this->iv = $this->generateIV();
            $this->logDebug("生成新的IV: " . bin2hex($this->iv));
        } else {
            $this->iv = $iv;
            if ($this->requiresIV() && !$this->validateIV($this->iv)) {
                throw new InvalidArgumentException('初始化向量(IV)长度无效');
            }
            $this->logDebug("使用提供的IV: " . bin2hex($this->iv));
        }

        // 初始化加密上下文
        $this->initializeContext();

        // 初始化性能统计
        $this->initializePerformanceStats();

        $this->logDebug("AES加密器初始化完成 - 方法: {$method}, 密钥长度: " . strlen($key) . "字节");
    }

    /**
     * 记录调试信息
     *
     * @param string $message 调试消息
     * @return void
     * @access private
     */
    private function logDebug(string $message): void
    {
        if ($this->debugMode) {
            $timestamp = date('Y-m-d H:i:s.v');
            $memory = round(memory_get_usage(true) / 1024 / 1024, 2);
            echo "[AES DEBUG][{$timestamp}][{$memory}MB] " . $message . "\n";
        }
    }

    /**
     * 验证加密方法是否支持
     *
     * @param string $method 加密方法名称
     * @return void
     * @throws InvalidArgumentException 当方法不支持时抛出
     * @access private
     */
    private function validateMethod(string $method): void
    {
        if (!in_array($method, self::SUPPORTED_METHODS, true)) {
            $supported = implode(', ', self::SUPPORTED_METHODS);
            throw new InvalidArgumentException(
                "不支持的加密方法: {$method}。支持的方法: {$supported}"
            );
        }

        // 检查OpenSSL是否支持该算法
        if (!in_array($method, openssl_get_cipher_methods())) {
            throw new InvalidArgumentException(
                "当前OpenSSL环境不支持加密方法: {$method}"
            );
        }
    }

    /**
     * 验证密钥长度是否符合要求
     *
     * @param string $key 加密密钥
     * @param string $method 加密方法
     * @return void
     * @throws InvalidArgumentException 当密钥长度无效时抛出
     * @access private
     */
    private function validateKey(string $key, string $method): void
    {
        $requiredLengths = [
            'aes-128' => 16,
            'aes-192' => 24,
            'aes-256' => 32,
            'aes-128-xts' => 32,
            'aes-256-xts' => 64
        ];

        // 查找对应的密钥长度要求
        $prefix = substr($method, 0, 7);
        $requiredLength = $requiredLengths[$method] ?? $requiredLengths[$prefix] ?? null;

        if ($requiredLength && strlen($key) !== $requiredLength) {
            throw new InvalidArgumentException(
                "密钥长度必须为 {$requiredLength} 字节，当前长度: " . strlen($key)
            );
        }

        // 检查密钥是否为有效的二进制数据
        if (!ctype_print($key) && strlen($key) > 0) {
            $this->logDebug("密钥包含非打印字符，可能是二进制格式");
        }
    }

    /**
     * 验证IV长度是否符合要求
     *
     * @param string $iv 初始化向量
     * @return bool 验证结果
     * @access private
     */
    private function validateIV(string $iv): bool
    {
        if (!$this->requiresIV()) {
            return true;
        }

        $ivLength = openssl_cipher_iv_length($this->method);
        return $ivLength !== false && strlen($iv) === $ivLength;
    }

    /**
     * 检查加密模式是否需要IV
     *
     * @return bool 是否需要IV
     * @access private
     */
    public function requiresIV(): bool
    {
        return !str_contains($this->method, 'ecb');
    }

    /**
     * 检查是否为认证加密模式（GCM）
     *
     * @return bool 是否为认证加密模式
     * @access private
     */
    public function isAuthenticatedMode(): bool
    {
        return str_ends_with($this->method, '-gcm');
    }

    /**
     * 检查是否为XTS模式（磁盘加密）
     *
     * @return bool 是否为XTS模式
     * @access private
     */
    private function isXTSMode(): bool
    {
        return str_ends_with($this->method, '-xts');
    }

    /**
     * 生成安全的初始化向量
     *
     * @return string 生成的IV
     * @throws RuntimeException 当IV生成失败时抛出
     * @access private
     */
    private function generateIV(): string
    {
        if (!$this->requiresIV()) {
            return '';
        }

        $ivLength = openssl_cipher_iv_length($this->method);
        if ($ivLength === false || $ivLength <= 0) {
            throw new RuntimeException('无法获取IV长度或IV长度无效');
        }

        try {
            $iv = random_bytes($ivLength);
            $this->logDebug("生成IV: " . bin2hex($iv) . " (长度: {$ivLength})");
            return $iv;
        } catch (Exception $e) {
            throw new RuntimeException('IV生成失败: ' . $e->getMessage());
        }
    }

    /**
     * 初始化加密上下文信息
     *
     * @return void
     * @access private
     */
    private function initializeContext(): void
    {
        $this->encryptionContext = [
            'method' => $this->method,
            'key_length' => strlen($this->key) * 8,
            'iv_length' => strlen($this->iv),
            'requires_iv' => $this->requiresIV(),
            'authenticated_mode' => $this->isAuthenticatedMode(),
            'xts_mode' => $this->isXTSMode(),
            'block_size' => 16, // AES块大小固定为16字节
            'initialized_at' => microtime(true),
            'operations_count' => 0
        ];
    }

    /**
     * 初始化性能统计
     *
     * @return void
     * @access private
     */
    private function initializePerformanceStats(): void
    {
        $this->performanceStats = [
            'encryption_operations' => 0,
            'decryption_operations' => 0,
            'total_data_processed' => 0,
            'start_time' => microtime(true),
            'last_operation_time' => null,
            'peak_memory_usage' => 0
        ];
    }

    /**
     * 更新性能统计信息
     *
     * @param string $operation 操作类型（encrypt/decrypt）
     * @param int $dataSize 处理的数据大小
     * @return void
     * @access private
     */
    private function updatePerformanceStats(string $operation, int $dataSize): void
    {
        $this->performanceStats[$operation . '_operations']++;
        $this->performanceStats['total_data_processed'] += $dataSize;
        $this->performanceStats['last_operation_time'] = microtime(true);

        $currentMemory = memory_get_usage(true);
        if ($currentMemory > $this->performanceStats['peak_memory_usage']) {
            $this->performanceStats['peak_memory_usage'] = $currentMemory;
        }

        $this->encryptionContext['operations_count']++;
    }

    /**
     * 检查内存使用情况
     *
     * @param int $additionalMemory 预估需要的内存
     * @return bool 是否安全
     * @access private
     */
    private function checkMemorySafety(int $additionalMemory = 0): bool
    {
        $currentUsage = memory_get_usage(true);
        $estimatedUsage = $currentUsage + $additionalMemory;

        if ($estimatedUsage > self::MEMORY_LIMIT) {
            $this->logDebug("内存使用预警: {$estimatedUsage}/" . self::MEMORY_LIMIT);
            return false;
        }

        return true;
    }

    /**
     * 加密数据 - 主加密方法
     *
     * @param string $data 要加密的原始数据
     * @param int $options 加密选项（可选值：OPENSSL_RAW_DATA, OPENSSL_ZERO_PADDING）
     * @param string|null $additionalData 附加数据（仅GCM模式有效）
     * @param int $tagLength 认证标签长度（仅GCM模式有效，默认16）
     * @return string 加密后的数据（Base64编码）
     *
     * @throws InvalidArgumentException 当数据为空时抛出
     * @throws RuntimeException 当加密失败时抛出
     *
     * 使用示例：
     * $encrypted = $aes->encrypt('敏感数据', OPENSSL_RAW_DATA, '附加信息', 16);
     */
    public function encrypt(
        string $data,
        int $options = OPENSSL_RAW_DATA,
        ?string $additionalData = null,
        int $tagLength = 16
    ): string {
        if (empty($data)) {
            throw new InvalidArgumentException('加密数据不能为空');
        }

        $this->logDebug("开始加密数据，长度: " . strlen($data));
        $this->updatePerformanceStats('encryption', strlen($data));

        // 检查内存安全
        if (!$this->checkMemorySafety(strlen($data) * 3)) {
            $this->logDebug("内存预警，尝试分块处理");
            return $this->encryptChunked($data, $options, $additionalData, $tagLength);
        }

        // 处理认证加密模式
        if ($this->isAuthenticatedMode()) {
            $result = $this->encryptAuthenticated($data, $options, $additionalData, $tagLength);
            $this->logDebug("GCM模式加密完成");
            return $result;
        }

        // 处理XTS模式
        if ($this->isXTSMode()) {
            $result = $this->encryptXTS($data, $options);
            $this->logDebug("XTS模式加密完成");
            return $result;
        }

        // 标准加密模式
        $encrypted = openssl_encrypt(
            $data,
            $this->method,
            $this->key,
            $options,
            $this->iv
        );

        if ($encrypted === false) {
            $error = openssl_error_string();
            throw new RuntimeException('加密失败: ' . ($error ?: '未知错误'));
        }

        // 对于需要IV的模式，将IV和加密数据一起返回
        $result = $this->packEncryptedData($encrypted, $options);
        $this->logDebug("加密完成，输出长度: " . strlen($result));

        return $result;
    }

    /**
     * 分块加密大数据
     *
     * @param string $data 原始数据
     * @param int $options 加密选项
     * @param string|null $additionalData 附加数据
     * @param int $tagLength 标签长度
     * @return string 加密结果
     * @access private
     */
    private function encryptChunked(
        string $data,
        int $options,
        ?string $additionalData = null,
        int $tagLength = 16
    ): string {
        $chunkSize = 1024 * 1024; // 1MB chunks
        $chunks = str_split($data, $chunkSize);
        $encryptedChunks = [];

        $this->logDebug("分块加密，块大小: {$chunkSize}, 块数: " . count($chunks));

        foreach ($chunks as $index => $chunk) {
            $this->logDebug("加密块 {$index}/" . count($chunks));

            if ($this->isAuthenticatedMode()) {
                $encryptedChunks[] = $this->encryptAuthenticated($chunk, $options, $additionalData, $tagLength);
            } else {
                $encrypted = openssl_encrypt($chunk, $this->method, $this->key, $options, $this->iv);
                if ($encrypted === false) {
                    throw new RuntimeException("分块加密失败: " . openssl_error_string());
                }
                $encryptedChunks[] = base64_encode($encrypted);
            }
        }

        $result = implode('|', $encryptedChunks);
        $this->logDebug("分块加密完成");
        return $result;
    }

    /**
     * 认证加密模式加密（GCM）
     *
     * @param string $data 原始数据
     * @param int $options 加密选项
     * @param string|null $additionalData 附加数据
     * @param int $tagLength 认证标签长度
     * @return string 加密结果
     * @access private
     */
    private function encryptAuthenticated(
        string $data,
        int $options,
        ?string $additionalData = null,
        int $tagLength = 16
    ): string {
        $tag = '';
        $additionalData = $additionalData ?? '';

        $encrypted = openssl_encrypt(
            $data,
            $this->method,
            $this->key,
            $options,
            $this->iv,
            $tag,
            $additionalData,
            $tagLength
        );

        if ($encrypted === false) {
            $error = openssl_error_string();
            throw new RuntimeException($this->method . ' 加密失败: ' . ($error ?: '未知错误'));
        }

        $this->logDebug("GCM加密完成，标签长度: " . strlen($tag));
        return base64_encode($this->iv . $tag . $encrypted);
    }

    /**
     * XTS模式加密
     *
     * @param string $data 原始数据
     * @param int $options 加密选项
     * @return string 加密结果
     * @access private
     */
    private function encryptXTS(string $data, int $options): string
    {
        // XTS模式需要特殊处理，这里使用标准加密
        $encrypted = openssl_encrypt(
            $data,
            $this->method,
            $this->key,
            $options,
            $this->iv
        );

        if ($encrypted === false) {
            $error = openssl_error_string();
            throw new RuntimeException('XTS加密失败: ' . ($error ?: '未知错误'));
        }

        return base64_encode($this->iv . $encrypted);
    }

    /**
     * 打包加密数据
     *
     * @param string $encrypted 加密数据
     * @param int $options 加密选项
     * @return string 打包后的数据
     * @access private
     */
    private function packEncryptedData(string $encrypted, int $options): string
    {
        if ($this->requiresIV() && !empty($this->iv)) {
            $result = base64_encode($this->iv . $encrypted);
            $this->logDebug("包含IV的加密数据打包完成");
            return $result;
        }

        $result = base64_encode($encrypted);
        $this->logDebug("不包含IV的加密数据打包完成");
        return $result;
    }

    /**
     * 解密数据 - 主解密方法
     *
     * @param string $encryptedData 加密数据（Base64编码）
     * @param int $options 解密选项（可选值：OPENSSL_RAW_DATA, OPENSSL_ZERO_PADDING）
     * @param string|null $additionalData 附加数据（仅GCM模式有效）
     * @return string 解密后的原始数据
     *
     * @throws InvalidArgumentException 当数据为空时抛出
     * @throws RuntimeException 当解密失败时抛出
     *
     * 使用示例：
     * $decrypted = $aes->decrypt($encryptedData, OPENSSL_RAW_DATA, '附加信息');
     */
    public function decrypt(
        string $encryptedData,
        int $options = OPENSSL_RAW_DATA,
        ?string $additionalData = null
    ): string {
        if (empty($encryptedData)) {
            throw new InvalidArgumentException('解密数据不能为空');
        }

        $this->logDebug("开始解密数据");
        $this->updatePerformanceStats('decryption', strlen($encryptedData));

        // 检查是否为分块加密数据
        if (strpos($encryptedData, '|') !== false && !$this->isAuthenticatedMode()) {
            $this->logDebug("检测到分块加密数据");
            return $this->decryptChunked($encryptedData, $options, $additionalData);
        }

        $data = base64_decode($encryptedData);
        if ($data === false) {
            throw new RuntimeException('Base64解码失败');
        }

        // 处理认证加密模式
        if ($this->isAuthenticatedMode()) {
            $result = $this->decryptAuthenticated($data, $options, $additionalData);
            $this->logDebug("GCM模式解密完成");
            return $result;
        }

        // 处理XTS模式
        if ($this->isXTSMode()) {
            $result = $this->decryptXTS($data, $options);
            $this->logDebug("XTS模式解密完成");
            return $result;
        }

        // 提取IV和加密数据
        list($iv, $encrypted) = $this->extractIVAndData($data);

        $decrypted = openssl_decrypt(
            $encrypted,
            $this->method,
            $this->key,
            $options,
            $iv
        );

        if ($decrypted === false) {
            $error = openssl_error_string();
            throw new RuntimeException('解密失败: ' . ($error ?: '未知错误'));
        }

        $this->logDebug("解密完成，数据长度: " . strlen($decrypted));
        return $decrypted;
    }

    /**
     * 分块解密数据
     *
     * @param string $encryptedData 加密数据
     * @param int $options 解密选项
     * @param string|null $additionalData 附加数据
     * @return string 解密结果
     * @access private
     */
    private function decryptChunked(
        string $encryptedData,
        int $options,
        ?string $additionalData = null
    ): string {
        $chunks = explode('|', $encryptedData);
        $decryptedChunks = [];

        $this->logDebug("分块解密，块数: " . count($chunks));

        foreach ($chunks as $index => $chunk) {
            $this->logDebug("解密块 {$index}/" . count($chunks));

            if ($this->isAuthenticatedMode()) {
                $data = base64_decode($chunk);
                $decryptedChunks[] = $this->decryptAuthenticated($data, $options, $additionalData);
            } else {
                $decrypted = $this->decrypt($chunk, $options, $additionalData);
                $decryptedChunks[] = $decrypted;
            }
        }

        $result = implode('', $decryptedChunks);
        $this->logDebug("分块解密完成");
        return $result;
    }

    /**
     * 认证加密模式解密（GCM）
     *
     * @param string $data 加密数据
     * @param int $options 解密选项
     * @param string|null $additionalData 附加数据
     * @return string 解密结果
     * @access private
     */
    private function decryptAuthenticated(
        string $data,
        int $options,
        ?string $additionalData = null
    ): string {
        $ivLength = openssl_cipher_iv_length($this->method);
        if ($ivLength === false) {
            throw new RuntimeException('无法获取IV长度');
        }

        $tagLength = 16;
        $iv = substr($data, 0, $ivLength);
        $tag = substr($data, $ivLength, $tagLength);
        $encrypted = substr($data, $ivLength + $tagLength);
        $additionalData = $additionalData ?? '';

        $this->logDebug("GCM解密 - IV: " . bin2hex($iv) . ", 标签: " . bin2hex($tag));

        $decrypted = openssl_decrypt(
            $encrypted,
            $this->method,
            $this->key,
            $options,
            $iv,
            $tag,
            $additionalData
        );

        if ($decrypted === false) {
            $error = openssl_error_string();
            throw new RuntimeException($this->method . ' 解密失败: ' . ($error ?: '未知错误'));
        }

        return $decrypted;
    }

    /**
     * XTS模式解密
     *
     * @param string $data 加密数据
     * @param int $options 解密选项
     * @return string 解密结果
     * @access private
     */
    private function decryptXTS(string $data, int $options): string
    {
        $ivLength = openssl_cipher_iv_length($this->method);
        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);

        $decrypted = openssl_decrypt(
            $encrypted,
            $this->method,
            $this->key,
            $options,
            $iv
        );

        if ($decrypted === false) {
            $error = openssl_error_string();
            throw new RuntimeException('XTS解密失败: ' . ($error ?: '未知错误'));
        }

        return $decrypted;
    }

    /**
     * 提取IV和加密数据
     *
     * @param string $data 原始数据
     * @return array [IV, 加密数据]
     * @access private
     */
    private function extractIVAndData(string $data): array
    {
        if ($this->requiresIV() && !empty($this->iv)) {
            $ivLength = openssl_cipher_iv_length($this->method);
            if ($ivLength === false) {
                throw new RuntimeException('无法获取IV长度');
            }

            $iv = substr($data, 0, $ivLength);
            $encrypted = substr($data, $ivLength);
            $this->logDebug("提取IV: " . bin2hex($iv));
            return [$iv, $encrypted];
        }

        return [$this->iv, $data];
    }

    /**
     * 加密文件 - 企业级文件加密
     *
     * @param string $inputFile 输入文件路径
     * @param string $outputFile 输出文件路径
     * @param int $chunkSize 分块大小（字节，默认64KB）
     * @param bool $enableCompression 是否启用压缩（默认true）
     * @param bool $enableIntegrityCheck 是否启用完整性检查（默认true）
     * @return bool 加密是否成功
     *
     * @throws InvalidArgumentException 当文件操作参数无效时抛出
     * @throws RuntimeException 当文件操作失败时抛出
     *
     * 使用示例：
     * $success = $aes->encryptFile('input.txt', 'output.enc', 65536, true, true);
     */
    public function encryptFile(
        string $inputFile,
        string $outputFile,
        int $chunkSize = self::DEFAULT_CHUNK_SIZE,
        bool $enableCompression = true,
        bool $enableIntegrityCheck = true
    ): bool {
        $this->validateFileOperation($inputFile, $outputFile);
        $chunkSize = $this->validateAndAdjustChunkSize($chunkSize);

        $this->logDebug("开始加密文件: {$inputFile} -> {$outputFile}");

        $inputHandle = fopen($inputFile, 'rb');
        if ($inputHandle === false) {
            throw new RuntimeException("无法打开输入文件: {$inputFile}");
        }

        $outputHandle = fopen($outputFile, 'wb');
        if ($outputHandle === false) {
            fclose($inputHandle);
            throw new RuntimeException("无法创建输出文件: {$outputFile}");
        }

        try {
            // 获取文件信息
            $fileInfo = $this->getFileInfo($inputFile);
            $this->logDebug("文件信息 - 大小: {$fileInfo['size']}字节, 修改时间: {$fileInfo['mtime']}");

            // 写入增强的文件头
            $header = $this->createEnhancedFileHeader($fileInfo, $enableCompression, $enableIntegrityCheck);
            if (fwrite($outputHandle, $header) === false) {
                throw new RuntimeException('文件头写入失败');
            }

            // 处理文件内容
            if ($enableCompression) {
                $this->logDebug("启用压缩模式");
                $success = $this->encryptFileWithCompression($inputHandle, $outputHandle, $chunkSize);
            } else {
                $success = $this->encryptFileDirect($inputHandle, $outputHandle, $chunkSize);
            }

            if (!$success) {
                throw new RuntimeException('文件内容加密失败');
            }

            // 写入完整性校验信息
            if ($enableIntegrityCheck) {
                $this->writeIntegrityData($inputFile, $outputHandle);
            }

            $this->logDebug("文件加密完成，输出文件: {$outputFile}");
            return true;
        } finally {
            fclose($inputHandle);
            fclose($outputHandle);
        }
    }

    /**
     * 直接加密文件（无压缩）
     *
     * @param resource $inputHandle 输入文件句柄
     * @param resource $outputHandle 输出文件句柄
     * @param int $chunkSize 分块大小
     * @return bool 是否成功
     * @access private
     */
    private function encryptFileDirect($inputHandle, $outputHandle, int $chunkSize): bool
    {
        $this->logDebug("开始直接加密文件内容");

        while (!feof($inputHandle)) {
            $chunk = fread($inputHandle, $chunkSize);
            if ($chunk === false) {
                throw new RuntimeException('文件读取失败');
            }

            if (!empty($chunk)) {
                $encryptedChunk = $this->encrypt($chunk, OPENSSL_RAW_DATA);
                $encryptedBinary = base64_decode($encryptedChunk);

                if ($encryptedBinary === false) {
                    throw new RuntimeException('加密数据Base64解码失败');
                }

                // 写入块长度和内容
                $chunkLength = pack('N', strlen($encryptedBinary));
                if (fwrite($outputHandle, $chunkLength) === false) {
                    throw new RuntimeException('块长度写入失败');
                }

                if (fwrite($outputHandle, $encryptedBinary) === false) {
                    throw new RuntimeException('加密数据写入失败');
                }
            }
        }

        $this->logDebug("直接加密完成");
        return true;
    }

    /**
     * 压缩后加密文件
     *
     * @param resource $inputHandle 输入文件句柄
     * @param resource $outputHandle 输出文件句柄
     * @param int $chunkSize 分块大小
     * @return bool 是否成功
     * @access private
     */
    private function encryptFileWithCompression($inputHandle, $outputHandle, int $chunkSize): bool
    {
        $this->logDebug("开始压缩加密文件内容");

        $fileContent = '';
        while (!feof($inputHandle)) {
            $chunk = fread($inputHandle, $chunkSize);
            if ($chunk === false) {
                throw new RuntimeException('文件读取失败');
            }
            $fileContent .= $chunk;
        }

        // 压缩数据
        $compressed = gzcompress($fileContent, 9);
        if ($compressed === false) {
            throw new RuntimeException('文件压缩失败');
        }

        $this->logDebug("压缩完成 - 原始大小: " . strlen($fileContent) . ", 压缩后: " . strlen($compressed));

        // 加密压缩数据
        $encrypted = $this->encrypt($compressed, OPENSSL_RAW_DATA);
        $encryptedBinary = base64_decode($encrypted);

        if ($encryptedBinary === false) {
            throw new RuntimeException('加密数据Base64解码失败');
        }

        // 写入加密数据长度和内容
        $dataLength = pack('N', strlen($encryptedBinary));
        if (fwrite($outputHandle, $dataLength) === false) {
            throw new RuntimeException('数据长度写入失败');
        }

        $written = fwrite($outputHandle, $encryptedBinary);
        if ($written === false || $written !== strlen($encryptedBinary)) {
            throw new RuntimeException('加密数据写入失败');
        }

        $this->logDebug("压缩加密完成");
        return true;
    }

    /**
     * 解密文件 - 企业级文件解密
     *
     * @param string $inputFile 输入文件路径
     * @param string $outputFile 输出文件路径
     * @param int $chunkSize 分块大小（字节，默认64KB）
     * @return bool 解密是否成功
     *
     * @throws InvalidArgumentException 当文件操作参数无效时抛出
     * @throws RuntimeException 当文件操作失败时抛出
     *
     * 使用示例：
     * $success = $aes->decryptFile('input.enc', 'output.txt', 65536);
     */
    public function decryptFile(
        string $inputFile,
        string $outputFile,
        int $chunkSize = self::DEFAULT_CHUNK_SIZE
    ): bool {
        $this->validateFileOperation($inputFile, $outputFile);
        $chunkSize = $this->validateAndAdjustChunkSize($chunkSize);

        $this->logDebug("开始解密文件: {$inputFile} -> {$outputFile}");

        $inputHandle = fopen($inputFile, 'rb');
        if ($inputHandle === false) {
            throw new RuntimeException("无法打开输入文件: {$inputFile}");
        }

        $outputHandle = fopen($outputFile, 'wb');
        if ($outputHandle === false) {
            fclose($inputHandle);
            throw new RuntimeException("无法创建输出文件: {$outputFile}");
        }

        try {
            // 读取并验证文件头
            $headerInfo = $this->readEnhancedFileHeader($inputHandle);
            $this->logDebug("文件头验证成功");

            // 根据文件头信息处理内容
            if ($headerInfo['compressed']) {
                $success = $this->decryptFileWithDecompression($inputHandle, $outputHandle, $headerInfo);
            } else {
                $success = $this->decryptFileDirect($inputHandle, $outputHandle, $chunkSize, $headerInfo);
            }

            if (!$success) {
                throw new RuntimeException('文件内容解密失败');
            }

            // 验证完整性
            if ($headerInfo['integrity_check']) {
                $this->verifyFileIntegrity($inputFile, $outputFile, $inputHandle);
            }

            $this->logDebug("文件解密完成，完整性验证成功");
            return true;
        } finally {
            fclose($inputHandle);
            fclose($outputHandle);
        }
    }

    /**
     * 直接解密文件（无压缩）
     *
     * @param resource $inputHandle 输入文件句柄
     * @param resource $outputHandle 输出文件句柄
     * @param int $chunkSize 分块大小
     * @param array $headerInfo 头部信息
     * @return bool 是否成功
     * @access private
     */
    private function decryptFileDirect($inputHandle, $outputHandle, int $chunkSize, array $headerInfo): bool
    {
        $this->logDebug("开始直接解密文件内容");

        while (!feof($inputHandle)) {
            // 读取块长度
            $lengthData = fread($inputHandle, 4);
            if (strlen($lengthData) !== 4) {
                if (feof($inputHandle)) break;
                throw new RuntimeException('块长度读取失败');
            }

            $chunkLength = unpack('N', $lengthData)[1];
            if ($chunkLength === 0) {
                break;
            }

            // 读取加密块
            $encryptedChunk = '';
            $bytesRead = 0;
            while ($bytesRead < $chunkLength) {
                $chunk = fread($inputHandle, min(8192, $chunkLength - $bytesRead));
                if ($chunk === false) {
                    throw new RuntimeException('加密数据读取失败');
                }
                $encryptedChunk .= $chunk;
                $bytesRead += strlen($chunk);
            }

            // 解密块
            $encryptedBase64 = base64_encode($encryptedChunk);
            $decryptedChunk = $this->decrypt($encryptedBase64, OPENSSL_RAW_DATA);

            // 写入解密数据
            if (fwrite($outputHandle, $decryptedChunk) === false) {
                throw new RuntimeException('解密数据写入失败');
            }
        }

        $this->logDebug("直接解密完成");
        return true;
    }

    /**
     * 解压解密文件
     *
     * @param resource $inputHandle 输入文件句柄
     * @param resource $outputHandle 输出文件句柄
     * @param array $headerInfo 头部信息
     * @return bool 是否成功
     * @access private
     */
    private function decryptFileWithDecompression($inputHandle, $outputHandle, array $headerInfo): bool
    {
        $this->logDebug("开始解压解密文件内容");

        // 读取加密数据长度
        $lengthData = fread($inputHandle, 4);
        if (strlen($lengthData) !== 4) {
            throw new RuntimeException('数据长度读取失败');
        }

        $dataLength = unpack('N', $lengthData)[1];
        $this->logDebug("加密数据长度: {$dataLength} 字节");

        // 读取加密数据
        $encryptedData = '';
        $bytesRead = 0;
        while ($bytesRead < $dataLength) {
            $chunk = fread($inputHandle, min(8192, $dataLength - $bytesRead));
            if ($chunk === false) {
                throw new RuntimeException('加密数据读取失败');
            }
            $encryptedData .= $chunk;
            $bytesRead += strlen($chunk);
        }

        if (strlen($encryptedData) !== $dataLength) {
            throw new RuntimeException('加密数据读取不完整');
        }

        // 解密数据
        $encryptedBase64 = base64_encode($encryptedData);
        $decryptedCompressed = $this->decrypt($encryptedBase64, OPENSSL_RAW_DATA);

        // 解压数据
        $decryptedContent = gzuncompress($decryptedCompressed);
        if ($decryptedContent === false) {
            throw new RuntimeException('数据解压失败');
        }

        $this->logDebug("解压完成 - 压缩大小: " . strlen($decryptedCompressed) . ", 解压后: " . strlen($decryptedContent));

        // 验证解密数据大小
        $decryptedSize = strlen($decryptedContent);
        $expectedSize = $headerInfo['original_size'];
        if ($decryptedSize !== $expectedSize) {
            throw new RuntimeException("文件大小不匹配: 期望 {$expectedSize}, 实际 {$decryptedSize}");
        }

        // 写入解密后的内容
        $written = fwrite($outputHandle, $decryptedContent);
        if ($written === false || $written !== $decryptedSize) {
            throw new RuntimeException('解密数据写入失败');
        }

        $this->logDebug("解压解密完成");
        return true;
    }

    /**
     * 创建增强的文件头
     *
     * @param array $fileInfo 文件信息
     * @param bool $compressed 是否压缩
     * @param bool $integrityCheck 是否完整性检查
     * @return string 文件头数据
     * @access private
     */
    private function createEnhancedFileHeader(array $fileInfo, bool $compressed, bool $integrityCheck): string
    {
        $header = self::FILE_FORMAT_VERSION;
        $header .= pack('C', strlen($this->method));
        $header .= $this->method;

        if ($this->requiresIV()) {
            $header .= $this->iv;
        }

        $header .= pack('N', time()); // 时间戳
        $header .= pack('N', $fileInfo['size']); // 原始文件大小
        $header .= pack('C', $compressed ? 1 : 0); // 压缩标志
        $header .= pack('C', $integrityCheck ? 1 : 0); // 完整性检查标志
        $header .= pack('N', $fileInfo['crc32']); // 文件CRC32校验
        $header .= random_bytes(8); // 随机填充
        $header .= pack('N', crc32($header)); // 头部校验

        $this->logDebug("创建文件头，大小: " . strlen($header) . " 字节");
        return $header;
    }

    /**
     * 读取增强的文件头
     *
     * @param resource $handle 文件句柄
     * @return array 头部信息
     * @access private
     */
    private function readEnhancedFileHeader($handle): array
    {
        // 读取版本
        $version = fread($handle, 5);
        if ($version !== self::FILE_FORMAT_VERSION) {
            throw new RuntimeException('不支持的文件格式版本: ' . $version);
        }

        // 读取方法
        $methodLength = unpack('C', fread($handle, 1))[1];
        $method = fread($handle, $methodLength);

        // 读取IV
        $iv = '';
        if ($this->requiresIV()) {
            $ivLength = openssl_cipher_iv_length($method);
            if ($ivLength === false) {
                throw new RuntimeException('无法获取IV长度');
            }
            $iv = fread($handle, $ivLength);
        }

        $timestamp = unpack('N', fread($handle, 4))[1];
        $originalSize = unpack('N', fread($handle, 4))[1];
        $compressed = unpack('C', fread($handle, 1))[1] === 1;
        $integrityCheck = unpack('C', fread($handle, 1))[1] === 1;
        $fileCrc32 = unpack('N', fread($handle, 4))[1];
        $padding = fread($handle, 8);
        $headerChecksum = unpack('N', fread($handle, 4))[1];

        // 验证头部完整性
        $headerData = $version . pack('C', $methodLength) . $method . $iv .
            pack('N', $timestamp) . pack('N', $originalSize) .
            pack('C', $compressed ? 1 : 0) . pack('C', $integrityCheck ? 1 : 0) .
            pack('N', $fileCrc32) . $padding;

        if (crc32($headerData) !== $headerChecksum) {
            throw new RuntimeException('文件头完整性验证失败');
        }

        return [
            'version' => $version,
            'method' => $method,
            'iv' => $iv,
            'timestamp' => $timestamp,
            'original_size' => $originalSize,
            'compressed' => $compressed,
            'integrity_check' => $integrityCheck,
            'file_crc32' => $fileCrc32,
            'padding' => $padding
        ];
    }

    /**
     * 写入完整性数据
     *
     * @param string $inputFile 输入文件
     * @param resource $outputHandle 输出句柄
     * @return void
     * @access private
     */
    private function writeIntegrityData(string $inputFile, $outputHandle): void
    {
        $fileContent = file_get_contents($inputFile);
        if ($fileContent === false) {
            throw new RuntimeException('无法读取文件内容进行完整性验证');
        }

        $footer = $this->createEnhancedFileFooter($fileContent, strlen($fileContent));
        if (fwrite($outputHandle, $footer) === false) {
            throw new RuntimeException('完整性数据写入失败');
        }

        $this->logDebug("完整性数据写入完成");
    }

    /**
     * 验证文件完整性
     *
     * @param string $inputFile 输入文件
     * @param string $outputFile 输出文件
     * @param resource $inputHandle 输入句柄
     * @return void
     * @access private
     */
    private function verifyFileIntegrity(string $inputFile, string $outputFile, $inputHandle): void
    {
        // 读取文件尾
        $footer = fread($inputHandle, 64);
        if (strlen($footer) !== 64) {
            throw new RuntimeException('完整性数据读取失败');
        }

        $decryptedContent = file_get_contents($outputFile);
        if ($decryptedContent === false) {
            throw new RuntimeException('无法读取解密文件进行完整性验证');
        }

        if (!$this->validateEnhancedFileFooter($footer, $decryptedContent, strlen($decryptedContent))) {
            throw new RuntimeException('文件完整性验证失败');
        }

        $this->logDebug("文件完整性验证成功");
    }

    /**
     * 获取文件信息
     *
     * @param string $filePath 文件路径
     * @return array 文件信息
     * @access private
     */
    private function getFileInfo(string $filePath): array
    {
        $stat = stat($filePath);
        if ($stat === false) {
            throw new RuntimeException("无法获取文件状态: {$filePath}");
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new RuntimeException("无法读取文件内容: {$filePath}");
        }

        return [
            'size' => $stat['size'],
            'mtime' => date('Y-m-d H:i:s', $stat['mtime']),
            'crc32' => crc32($content),
            'sha256' => hash('sha256', $content)
        ];
    }

    /**
     * 创建增强的文件尾
     *
     * @param string $originalData 原始数据
     * @param int $processedBytes 处理字节数
     * @return string 文件尾数据
     * @access private
     */
    private function createEnhancedFileFooter(string $originalData, int $processedBytes): string
    {
        $contentHash = hash('sha256', $originalData, true);
        $sizeHash = hash('sha256', pack('N', $processedBytes) . $this->key, true);
        $footer = $contentHash . $sizeHash;

        $this->logDebug("创建文件尾，内容哈希: " . bin2hex($contentHash));
        return $footer;
    }

    /**
     * 验证增强的文件尾
     *
     * @param string $footer 文件尾数据
     * @param string $decryptedData 解密数据
     * @param int $expectedSize 期望大小
     * @return bool 验证结果
     * @access private
     */
    private function validateEnhancedFileFooter(string $footer, string $decryptedData, int $expectedSize): bool
    {
        if (strlen($footer) !== 64) {
            $this->logDebug("文件尾长度无效: " . strlen($footer));
            return false;
        }

        $expectedContentHash = hash('sha256', $decryptedData, true);
        $expectedSizeHash = hash('sha256', pack('N', $expectedSize) . $this->key, true);
        $expectedFooter = $expectedContentHash . $expectedSizeHash;

        $contentValid = hash_equals(substr($expectedFooter, 0, 32), substr($footer, 0, 32));
        $sizeValid = hash_equals(substr($expectedFooter, 32, 32), substr($footer, 32, 32));

        $this->logDebug("文件尾验证 - 内容: " . ($contentValid ? '有效' : '无效') .
            ", 大小: " . ($sizeValid ? '有效' : '无效'));

        return $contentValid && $sizeValid;
    }

    /**
     * 验证文件操作参数
     *
     * @param string $inputFile 输入文件
     * @param string $outputFile 输出文件
     * @return void
     * @throws InvalidArgumentException
     * @access private
     */
    private function validateFileOperation(string $inputFile, string $outputFile): void
    {
        if (!file_exists($inputFile)) {
            throw new InvalidArgumentException("输入文件不存在: {$inputFile}");
        }

        if (!is_readable($inputFile)) {
            throw new InvalidArgumentException("输入文件不可读: {$inputFile}");
        }

        if (!is_file($inputFile)) {
            throw new InvalidArgumentException("输入路径不是文件: {$inputFile}");
        }

        $outputDir = dirname($outputFile);
        if (!is_dir($outputDir)) {
            if (!mkdir($outputDir, 0755, true)) {
                throw new InvalidArgumentException("无法创建输出目录: {$outputDir}");
            }
        }

        if (!is_writable($outputDir)) {
            throw new InvalidArgumentException("输出目录不可写: {$outputDir}");
        }

        // 检查文件大小
        $fileSize = filesize($inputFile);
        if ($fileSize === false) {
            throw new InvalidArgumentException("无法获取文件大小: {$inputFile}");
        }

        if ($fileSize === 0) {
            throw new InvalidArgumentException("输入文件为空: {$inputFile}");
        }

        // 检查磁盘空间
        $freeSpace = disk_free_space($outputDir);
        if ($freeSpace !== false && $freeSpace < $fileSize * 3) {
            throw new InvalidArgumentException("输出目录磁盘空间不足");
        }
    }

    /**
     * 验证并调整分块大小
     *
     * @param int $chunkSize 分块大小
     * @return int 调整后的分块大小
     * @access private
     */
    private function validateAndAdjustChunkSize(int $chunkSize): int
    {
        if ($chunkSize <= 0) {
            throw new InvalidArgumentException('分块大小必须大于0');
        }

        // 确保分块大小是16的倍数（AES块大小）
        if ($chunkSize % 16 !== 0) {
            $chunkSize = (int)(ceil($chunkSize / 16) * 16);
            $this->logDebug("调整分块大小为16的倍数: {$chunkSize}");
        }

        // 限制最大分块大小
        $maxChunkSize = 1024 * 1024 * 10; // 10MB
        if ($chunkSize > $maxChunkSize) {
            $chunkSize = $maxChunkSize;
            $this->logDebug("限制分块大小为: {$chunkSize}");
        }

        return $chunkSize;
    }

    /**
     * 生成随机密钥
     *
     * @param string $method 加密方法（用于确定密钥长度）
     * @return string 生成的随机密钥
     *
     * @throws RuntimeException 当密钥生成失败时抛出
     *
     * 使用示例：
     * $key = AES::generateKey('aes-256-cbc');
     */
    public static function generateKey(string $method = 'aes-256-cbc'): string
    {
        $keyLengths = [
            'aes-128' => 16,
            'aes-192' => 24,
            'aes-256' => 32,
            'aes-128-xts' => 32,
            'aes-256-xts' => 64
        ];

        $prefix = substr($method, 0, 7);
        $length = $keyLengths[$method] ?? $keyLengths[$prefix] ?? 32;

        try {
            return random_bytes($length);
        } catch (Exception $e) {
            throw new RuntimeException('随机密钥生成失败: ' . $e->getMessage());
        }
    }

    /**
     * 从密码生成密钥（使用PBKDF2）
     *
     * @param string $password 密码
     * @param string $salt 盐值
     * @param string $method 加密方法
     * @param int $iterations 迭代次数
     * @return string 派生的密钥
     *
     * @throws InvalidArgumentException 当参数无效时抛出
     *
     * 使用示例：
     * $key = AES::generateKeyFromPassword('mypassword', 'somesalt', 'aes-256-cbc', 100000);
     */
    public static function generateKeyFromPassword(
        string $password,
        string $salt,
        string $method = 'aes-256-cbc',
        int $iterations = self::DEFAULT_PBKDF2_ITERATIONS
    ): string {
        if ($iterations <= 0) {
            throw new InvalidArgumentException('迭代次数必须大于0');
        }

        if (empty($salt)) {
            throw new InvalidArgumentException('盐值不能为空');
        }

        if (strlen($salt) < 8) {
            throw new InvalidArgumentException('盐值长度至少8字节');
        }

        $keyLengths = [
            'aes-128' => 16,
            'aes-192' => 24,
            'aes-256' => 32
        ];

        $prefix = substr($method, 0, 7);
        $length = $keyLengths[$prefix] ?? 32;

        return hash_pbkdf2('sha256', $password, $salt, $iterations, $length, true);
    }

    /**
     * 获取支持的加密方法列表
     *
     * @return array 支持的加密方法
     *
     * 使用示例：
     * $methods = AES::getSupportedMethods();
     */
    public static function getSupportedMethods(): array
    {
        return self::SUPPORTED_METHODS;
    }

    /**
     * 获取支持的填充方式列表
     *
     * @return array 支持的填充方式
     */
    public static function getSupportedPaddings(): array
    {
        return self::SUPPORTED_PADDINGS;
    }

    /**
     * 获取当前使用的加密方法
     *
     * @return string 加密方法
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * 获取初始化向量
     *
     * @return string 初始化向量
     */
    public function getIV(): string
    {
        return $this->iv;
    }

    /**
     * 获取加密密钥
     *
     * @return string 加密密钥
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * 计算数据的HMAC签名
     *
     * @param string $data 要签名的数据
     * @param string $algorithm 哈希算法（默认sha256）
     * @return string HMAC签名（Base64编码）
     *
     * @throws InvalidArgumentException 当算法不支持时抛出
     *
     * 使用示例：
     * $hmac = $aes->calculateHMAC('数据内容', 'sha256');
     */
    public function calculateHMAC(string $data, string $algorithm = self::DEFAULT_HASH_ALGORITHM): string
    {
        $supportedAlgorithms = hash_hmac_algos();
        if (!in_array($algorithm, $supportedAlgorithms, true)) {
            throw new InvalidArgumentException("不支持的HMAC算法: {$algorithm}");
        }

        $hmac = hash_hmac($algorithm, $data, $this->key, true);
        return base64_encode($hmac);
    }

    /**
     * 验证HMAC签名
     *
     * @param string $data 原始数据
     * @param string $hmac HMAC签名
     * @param string $algorithm 哈希算法
     * @return bool 验证结果
     *
     * 使用示例：
     * $valid = $aes->verifyHMAC('数据内容', $hmacSignature, 'sha256');
     */
    public function verifyHMAC(string $data, string $hmac, string $algorithm = self::DEFAULT_HASH_ALGORITHM): bool
    {
        $expectedHmac = $this->calculateHMAC($data, $algorithm);
        return hash_equals($expectedHmac, $hmac);
    }

    /**
     * 加密并签名数据
     *
     * @param string $data 要加密的数据
     * @param string $hmacAlgorithm HMAC算法
     * @return array 包含加密数据和签名的数组
     *
     * 使用示例：
     * $package = $aes->encryptAndSign('敏感数据', 'sha256');
     */
    public function encryptAndSign(string $data, string $hmacAlgorithm = self::DEFAULT_HASH_ALGORITHM): array
    {
        $encrypted = $this->encrypt($data);
        $signature = $this->calculateHMAC($data, $hmacAlgorithm);

        return [
            'encrypted_data' => $encrypted,
            'signature' => $signature,
            'hmac_algorithm' => $hmacAlgorithm,
            'timestamp' => time(),
            'method' => $this->method,
            'data_size' => strlen($data)
        ];
    }

    /**
     * 解密并验证签名
     *
     * @param array $package 加密数据包
     * @return string 解密后的数据
     *
     * @throws InvalidArgumentException 当数据包无效时抛出
     * @throws RuntimeException 当签名验证失败时抛出
     *
     * 使用示例：
     * $decrypted = $aes->decryptAndVerify($encryptedPackage);
     */
    public function decryptAndVerify(array $package): string
    {
        $encryptedData = $package['encrypted_data'] ?? '';
        $signature = $package['signature'] ?? '';
        $algorithm = $package['hmac_algorithm'] ?? self::DEFAULT_HASH_ALGORITHM;

        if (empty($encryptedData) || empty($signature)) {
            throw new InvalidArgumentException('无效的加密数据包');
        }

        $decrypted = $this->decrypt($encryptedData);

        if (!$this->verifyHMAC($decrypted, $signature, $algorithm)) {
            throw new RuntimeException('HMAC签名验证失败');
        }

        return $decrypted;
    }

    /**
     * 获取加密信息摘要
     *
     * @return array 加密信息
     *
     * 使用示例：
     * $info = $aes->getCipherInfo();
     */
    public function getCipherInfo(): array
    {
        return [
            'method' => $this->method,
            'key_length' => strlen($this->key) * 8,
            'iv_length' => strlen($this->iv),
            'requires_iv' => $this->requiresIV(),
            'authenticated_mode' => $this->isAuthenticatedMode(),
            'xts_mode' => $this->isXTSMode(),
            'supported_methods' => self::SUPPORTED_METHODS,
            'operations_count' => $this->encryptionContext['operations_count'],
            'initialized_at' => $this->encryptionContext['initialized_at']
        ];
    }

    /**
     * 获取性能统计信息
     *
     * @return array 性能统计
     */
    public function getPerformanceStats(): array
    {
        $currentTime = microtime(true);
        $runningTime = $currentTime - $this->performanceStats['start_time'];

        return [
            'encryption_operations' => $this->performanceStats['encryption_operations'],
            'decryption_operations' => $this->performanceStats['decryption_operations'],
            'total_operations' => $this->performanceStats['encryption_operations'] + $this->performanceStats['decryption_operations'],
            'total_data_processed' => $this->performanceStats['total_data_processed'],
            'running_time_seconds' => round($runningTime, 2),
            'operations_per_second' => $runningTime > 0 ?
                round(($this->performanceStats['encryption_operations'] + $this->performanceStats['decryption_operations']) / $runningTime, 2) : 0,
            'data_throughput_mb_s' => $runningTime > 0 ?
                round($this->performanceStats['total_data_processed'] / $runningTime / 1024 / 1024, 2) : 0,
            'peak_memory_usage_mb' => round($this->performanceStats['peak_memory_usage'] / 1024 / 1024, 2),
            'last_operation_time' => $this->performanceStats['last_operation_time']
        ];
    }

    /**
     * 重置性能统计
     *
     * @return void
     */
    public function resetPerformanceStats(): void
    {
        $this->initializePerformanceStats();
        $this->logDebug("性能统计已重置");
    }

    /**
     * 加密字符串到文件
     *
     * @param string $data 要加密的字符串数据
     * @param string $outputFile 输出文件路径
     * @param bool $enableCompression 是否启用压缩
     * @return bool 操作是否成功
     *
     * @throws InvalidArgumentException 当参数无效时抛出
     * @throws RuntimeException 当操作失败时抛出
     *
     * 使用示例：
     * $success = $aes->encryptStringToFile('敏感字符串数据', 'output.enc', true);
     */
    public function encryptStringToFile(
        string $data,
        string $outputFile,
        bool $enableCompression = true
    ): bool {
        if (empty($data)) {
            throw new InvalidArgumentException('加密数据不能为空');
        }

        $this->logDebug("加密字符串到文件，数据长度: " . strlen($data));

        // 准备输出目录
        $outputDir = dirname($outputFile);
        if (!is_dir($outputDir)) {
            if (!mkdir($outputDir, 0755, true)) {
                throw new RuntimeException("无法创建输出目录: {$outputDir}");
            }
        }

        // 处理数据
        $processedData = $data;
        if ($enableCompression) {
            $processedData = gzcompress($data, 9);
            if ($processedData === false) {
                throw new RuntimeException('数据压缩失败');
            }
            $this->logDebug("数据压缩: " . strlen($data) . " -> " . strlen($processedData) . " 字节");
        }

        // 加密数据
        $encrypted = $this->encrypt($processedData, OPENSSL_RAW_DATA);
        $encryptedBinary = base64_decode($encrypted);

        if ($encryptedBinary === false) {
            throw new RuntimeException('加密数据Base64解码失败');
        }

        // 创建文件内容
        $fileSize = strlen($data);
        $fileInfo = [
            'size' => $fileSize,
            'mtime' => time(),
            'crc32' => crc32($data),
            'sha256' => hash('sha256', $data)
        ];

        $header = $this->createEnhancedFileHeader($fileInfo, $enableCompression, true);
        $dataLength = pack('N', strlen($encryptedBinary));
        $footer = $this->createEnhancedFileFooter($data, $fileSize);

        $fileContent = $header . $dataLength . $encryptedBinary . $footer;

        // 写入文件
        $result = file_put_contents($outputFile, $fileContent);
        if ($result === false) {
            throw new RuntimeException('无法写入输出文件');
        }

        $this->logDebug("字符串加密到文件完成: {$outputFile}, 文件大小: {$result} 字节");
        return true;
    }

    /**
     * 从文件解密字符串
     *
     * @param string $inputFile 输入文件路径
     * @return string 解密后的字符串
     *
     * @throws InvalidArgumentException 当文件不存在时抛出
     * @throws RuntimeException 当解密失败时抛出
     *
     * 使用示例：
     * $decryptedString = $aes->decryptFileToString('encrypted.enc');
     */
    public function decryptFileToString(string $inputFile): string
    {
        if (!file_exists($inputFile)) {
            throw new InvalidArgumentException("输入文件不存在: {$inputFile}");
        }

        $this->logDebug("从文件解密字符串: {$inputFile}");

        $fileContent = file_get_contents($inputFile);
        if ($fileContent === false) {
            throw new RuntimeException('无法读取加密文件');
        }

        // 解析文件格式
        $version = substr($fileContent, 0, 5);
        if ($version !== self::FILE_FORMAT_VERSION) {
            throw new RuntimeException('不支持的文件格式版本: ' . $version);
        }

        $methodLength = unpack('C', $fileContent[5])[1];
        $headerSize = 5 + 1 + $methodLength;

        if ($this->requiresIV()) {
            $ivLength = openssl_cipher_iv_length($this->method);
            $headerSize += $ivLength;
        }

        $headerSize += 4 + 4 + 1 + 1 + 4 + 8 + 4; // 时间戳 + 原始大小 + 压缩标志 + 完整性标志 + CRC32 + 填充 + 校验和

        $lengthData = substr($fileContent, $headerSize, 4);
        $dataLength = unpack('N', $lengthData)[1];

        $encryptedData = substr($fileContent, $headerSize + 4, $dataLength);
        $encryptedBase64 = base64_encode($encryptedData);

        $decrypted = $this->decrypt($encryptedBase64, OPENSSL_RAW_DATA);

        // 检查是否需要解压缩
        $compressedFlag = unpack('C', $fileContent[5 + 1 + $methodLength + ($this->requiresIV() ? $ivLength : 0) + 4 + 4])[1];
        if ($compressedFlag === 1) {
            $decrypted = gzuncompress($decrypted);
            if ($decrypted === false) {
                throw new RuntimeException('数据解压失败');
            }
        }

        $this->logDebug("文件解密到字符串完成，长度: " . strlen($decrypted));
        return $decrypted;
    }

    /**
     * 批量加密文件
     *
     * @param array $files 文件路径数组
     * @param string $outputDir 输出目录
     * @param int $chunkSize 分块大小
     * @param bool $enableCompression 是否启用压缩
     * @param callable|null $progressCallback 进度回调函数
     * @return array 加密结果
     *
     * 使用示例：
     * $results = $aes->encryptFiles(['file1.txt', 'file2.txt'], '/output', 65536, true, function($file, $progress) {
     *     echo "处理 {$file}: {$progress}%\n";
     * });
     */
    public function encryptFiles(
        array $files,
        string $outputDir,
        int $chunkSize = self::DEFAULT_CHUNK_SIZE,
        bool $enableCompression = true,
        ?callable $progressCallback = null
    ): array {
        $results = [];

        // 确保输出目录存在
        if (!is_dir($outputDir)) {
            if (!mkdir($outputDir, 0755, true)) {
                throw new RuntimeException("无法创建输出目录: {$outputDir}");
            }
        }

        $totalFiles = count($files);
        $this->logDebug("开始批量加密 {$totalFiles} 个文件");

        foreach ($files as $index => $inputFile) {
            try {
                if (!file_exists($inputFile)) {
                    $results[$inputFile] = ['success' => false, 'error' => '文件不存在'];
                    continue;
                }

                $outputFile = $outputDir . '/' . basename($inputFile) . '.encrypted';

                // 调用进度回调
                if ($progressCallback !== null) {
                    $progress = round(($index / $totalFiles) * 100, 2);
                    $progressCallback($inputFile, $progress);
                }

                $success = $this->encryptFile($inputFile, $outputFile, $chunkSize, $enableCompression);
                $results[$inputFile] = [
                    'success' => $success,
                    'output_file' => $outputFile,
                    'input_size' => filesize($inputFile),
                    'output_size' => $success ? filesize($outputFile) : 0
                ];

                $this->logDebug("文件 {$inputFile} 加密 " . ($success ? '成功' : '失败'));
            } catch (Exception $e) {
                $results[$inputFile] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
                $this->logDebug("文件 {$inputFile} 加密失败: " . $e->getMessage());
            }
        }

        // 最终进度回调
        if ($progressCallback !== null) {
            $progressCallback('完成', 100);
        }

        $successCount = count(array_filter($results, function($r) { return $r['success']; }));
        $this->logDebug("批量加密完成: {$successCount}/{$totalFiles} 成功");
        return $results;
    }

    /**
     * 批量解密文件
     *
     * @param array $files 文件路径数组
     * @param string $outputDir 输出目录
     * @param int $chunkSize 分块大小
     * @param callable|null $progressCallback 进度回调函数
     * @return array 解密结果
     *
     * 使用示例：
     * $results = $aes->decryptFiles(['file1.encrypted', 'file2.encrypted'], '/output', 65536, function($file, $progress) {
     *     echo "处理 {$file}: {$progress}%\n";
     * });
     */
    public function decryptFiles(
        array $files,
        string $outputDir,
        int $chunkSize = self::DEFAULT_CHUNK_SIZE,
        ?callable $progressCallback = null
    ): array {
        $results = [];

        // 确保输出目录存在
        if (!is_dir($outputDir)) {
            if (!mkdir($outputDir, 0755, true)) {
                throw new RuntimeException("无法创建输出目录: {$outputDir}");
            }
        }

        $totalFiles = count($files);
        $this->logDebug("开始批量解密 {$totalFiles} 个文件");

        foreach ($files as $index => $inputFile) {
            try {
                if (!file_exists($inputFile)) {
                    $results[$inputFile] = ['success' => false, 'error' => '文件不存在'];
                    continue;
                }

                $outputFile = $outputDir . '/' . basename($inputFile, '.encrypted');

                // 调用进度回调
                if ($progressCallback !== null) {
                    $progress = round(($index / $totalFiles) * 100, 2);
                    $progressCallback($inputFile, $progress);
                }

                $success = $this->decryptFile($inputFile, $outputFile, $chunkSize);
                $results[$inputFile] = [
                    'success' => $success,
                    'output_file' => $outputFile,
                    'input_size' => filesize($inputFile),
                    'output_size' => $success ? filesize($outputFile) : 0
                ];

                $this->logDebug("文件 {$inputFile} 解密 " . ($success ? '成功' : '失败'));
            } catch (Exception $e) {
                $results[$inputFile] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
                $this->logDebug("文件 {$inputFile} 解密失败: " . $e->getMessage());
            }
        }

        // 最终进度回调
        if ($progressCallback !== null) {
            $progressCallback('完成', 100);
        }

        $successCount = count(array_filter($results, function($r) { return $r['success']; }));
        $this->logDebug("批量解密完成: {$successCount}/{$totalFiles} 成功");
        return $results;
    }

    /**
     * 获取文件加密信息
     *
     * @param string $filePath 文件路径
     * @return array 文件加密信息
     *
     * @throws InvalidArgumentException 当文件不存在时抛出
     *
     * 使用示例：
     * $info = AES::getFileEncryptionInfo('encrypted.enc');
     */
    public static function getFileEncryptionInfo(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("文件不存在: {$filePath}");
        }

        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new RuntimeException("无法打开文件: {$filePath}");
        }

        try {
            $version = fread($handle, 5);
            if ($version !== self::FILE_FORMAT_VERSION) {
                return ['encrypted' => false];
            }

            $methodLength = unpack('C', fread($handle, 1))[1];
            $method = fread($handle, $methodLength);

            // 读取更多信息
            $iv = '';
            $ivLength = openssl_cipher_iv_length($method);
            if ($ivLength !== false && $ivLength > 0) {
                $iv = fread($handle, $ivLength);
            }

            $timestamp = unpack('N', fread($handle, 4))[1];
            $originalSize = unpack('N', fread($handle, 4))[1];
            $compressed = unpack('C', fread($handle, 1))[1] === 1;
            $integrityCheck = unpack('C', fread($handle, 1))[1] === 1;

            return [
                'encrypted' => true,
                'version' => $version,
                'method' => $method,
                'iv' => bin2hex($iv),
                'timestamp' => date('Y-m-d H:i:s', $timestamp),
                'original_size' => $originalSize,
                'compressed' => $compressed,
                'integrity_check' => $integrityCheck,
                'format' => 'AES Encrypted File'
            ];
        } finally {
            fclose($handle);
        }
    }

    /**
     * 更改加密密钥
     *
     * @param string $newKey 新密钥
     * @return void
     *
     * @throws InvalidArgumentException 当新密钥无效时抛出
     *
     * 使用示例：
     * $aes->changeKey($newKey);
     */
    public function changeKey(string $newKey): void
    {
        $this->validateKey($newKey, $this->method);
        $this->key = $newKey;
        $this->initializeContext();
        $this->logDebug("加密密钥已更改");
    }

    /**
     * 更改加密方法
     *
     * @param string $newMethod 新加密方法
     * @param string|null $newIV 新初始化向量
     * @return void
     *
     * @throws InvalidArgumentException 当新方法无效时抛出
     *
     * 使用示例：
     * $aes->changeMethod('aes-256-gcm', $newIV);
     */
    public function changeMethod(string $newMethod, ?string $newIV = null): void
    {
        $this->validateMethod($newMethod);

        // 根据新方法生成合适长度的密钥
        $newKey = self::generateKey($newMethod);

        $this->method = $newMethod;
        $this->key = $newKey;

        if ($newIV === null) {
            $this->iv = $this->generateIV();
        } else {
            $this->iv = $newIV;
            if ($this->requiresIV() && !$this->validateIV($this->iv)) {
                throw new InvalidArgumentException('初始化向量(IV)长度无效');
            }
        }

        $this->initializeContext();
        $this->logDebug("加密方法已更改为: {$newMethod}");
    }

    /**
     * 启用/禁用调试模式
     *
     * @param bool $enabled 是否启用调试模式
     * @return void
     *
     * 使用示例：
     * $aes->setDebugMode(true);
     */
    public function setDebugMode(bool $enabled): void
    {
        $this->debugMode = $enabled;
        $this->logDebug("调试模式 " . ($enabled ? '启用' : '禁用'));
    }

    /**
     * 性能测试
     *
     * @param int $dataSize 测试数据大小（字节）
     * @param int $iterations 迭代次数
     * @return array 性能测试结果
     *
     * 使用示例：
     * $performance = $aes->testPerformance(1024, 1000);
     */
    public function testPerformance(int $dataSize = 1024, int $iterations = 100): array
    {
        $testData = random_bytes($dataSize);

        $startTime = microtime(true);
        $memoryBefore = memory_get_usage(true);

        for ($i = 0; $i < $iterations; $i++) {
            $encrypted = $this->encrypt($testData);
            $this->decrypt($encrypted);
        }

        $totalTime = microtime(true) - $startTime;
        $memoryAfter = memory_get_usage(true);
        $memoryUsed = $memoryAfter - $memoryBefore;

        $throughput = ($dataSize * $iterations * 2) / $totalTime / 1024 / 1024; // MB/s

        return [
            'method' => $this->method,
            'data_size' => $dataSize,
            'iterations' => $iterations,
            'total_time' => round($totalTime, 4) . '秒',
            'throughput' => round($throughput, 2) . 'MB/秒',
            'operations_per_second' => round($iterations / $totalTime, 2),
            'memory_used' => round($memoryUsed / 1024 / 1024, 2) . 'MB',
            'average_operation_time' => round(($totalTime / $iterations) * 1000, 2) . '毫秒'
        ];
    }

    /**
     * 安全擦除敏感数据
     *
     * @return void
     *
     * 使用示例：
     * $aes->secureWipe();
     */
    public function secureWipe(): void
    {
        // 安全擦除密钥
        if (isset($this->key)) {
            $length = strlen($this->key);
            for ($i = 0; $i < $length; $i++) {
                $this->key[$i] = "\0";
            }
            unset($this->key);
        }

        // 安全擦除IV
        if (isset($this->iv)) {
            $length = strlen($this->iv);
            for ($i = 0; $i < $length; $i++) {
                $this->iv[$i] = "\0";
            }
            unset($this->iv);
        }

        // 清空上下文
        $this->encryptionContext = [];
        $this->performanceStats = [];

        $this->logDebug("敏感数据已安全擦除");
    }

    /**
     * 导出加密配置
     *
     * @return array 加密配置
     *
     * 使用示例：
     * $config = $aes->exportConfig();
     */
    public function exportConfig(): array
    {
        return [
            'method' => $this->method,
            'key' => base64_encode($this->key),
            'iv' => base64_encode($this->iv),
            'key_length' => strlen($this->key) * 8,
            'iv_length' => strlen($this->iv),
            'requires_iv' => $this->requiresIV(),
            'authenticated_mode' => $this->isAuthenticatedMode()
        ];
    }

    /**
     * 从配置导入
     *
     * @param array $config 加密配置
     * @return static 新的AES实例
     *
     * @throws InvalidArgumentException 当配置无效时抛出
     *
     * 使用示例：
     * $newAes = AES::fromConfig($config);
     */
    public static function fromConfig(array $config): self
    {
        $required = ['method', 'key', 'iv'];
        foreach ($required as $field) {
            if (!isset($config[$field])) {
                throw new InvalidArgumentException("配置缺少必要字段: {$field}");
            }
        }

        $key = base64_decode($config['key']);
        $iv = base64_decode($config['iv']);

        if ($key === false || $iv === false) {
            throw new InvalidArgumentException('配置中的密钥或IV Base64解码失败');
        }

        return new self($key, $config['method'], $iv);
    }

    /**
     * 析构函数 - 安全清理
     */
    public function __destruct()
    {
        // 在析构时安全擦除敏感数据
        if ($this->key !== null) {
            $this->secureWipe();
        }
    }
}
