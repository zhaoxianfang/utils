<?php

/**
 * AES加密解密类 - 最终优化版
 * 支持多种加密模式、填充方式和密钥长度
 * 基于PHP 8.2+ openssl扩展实现
 * 修复了文件完整性验证、密钥管理等关键问题
 *
 * @package Crypto
 * @author Security Team
 * @version 6.0.1
 * @license MIT
 */
class AESCrypto
{
    /**
     * @var string 加密密钥
     */
    private string $key;

    /**
     * @var string 加密方法
     */
    private string $method;

    /**
     * @var string 初始化向量
     */
    private string $iv;

    /**
     * @var array 支持的加密方法列表
     */
    private const SUPPORTED_METHODS = [
        // CBC模式
        'aes-128-cbc', 'aes-192-cbc', 'aes-256-cbc',
        // ECB模式
        'aes-128-ecb', 'aes-192-ecb', 'aes-256-ecb',
        // CFB模式
        'aes-128-cfb', 'aes-192-cfb', 'aes-256-cfb',
        'aes-128-cfb1', 'aes-192-cfb1', 'aes-256-cfb1',
        'aes-128-cfb8', 'aes-192-cfb8', 'aes-256-cfb8',
        // OFB模式
        'aes-128-ofb', 'aes-192-ofb', 'aes-256-ofb',
        // CTR模式
        'aes-128-ctr', 'aes-192-ctr', 'aes-256-ctr',
        // GCM模式
        'aes-128-gcm', 'aes-192-gcm', 'aes-256-gcm',
        // XTS模式
        'aes-128-xts', 'aes-256-xts'
    ];

    /**
     * @var int 默认分块大小（64KB）
     */
    private const DEFAULT_CHUNK_SIZE = 65536;

    /**
     * @var int 默认PBKDF2迭代次数
     */
    private const DEFAULT_PBKDF2_ITERATIONS = 100000;

    /**
     * @var string 默认哈希算法
     */
    private const DEFAULT_HASH_ALGORITHM = 'sha256';

    /**
     * @var string 文件格式版本
     */
    private const FILE_FORMAT_VERSION = 'AESv3';

    /**
     * @var array 加密上下文
     */
    private array $encryptionContext = [];

    /**
     * @var bool 调试模式
     */
    private bool $debugMode = false;

    /**
     * 构造函数
     */
    public function __construct(string $key, string $method = 'aes-256-cbc', ?string $iv = null, bool $debugMode = false)
    {
        $this->debugMode = $debugMode;
        $this->validateMethod($method);
        $this->validateKey($key, $method);

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

        $this->logDebug("AES加密器初始化完成 - 方法: {$method}, 密钥长度: " . strlen($key));
    }

    /**
     * 记录调试信息
     */
    private function logDebug(string $message): void
    {
        if ($this->debugMode) {
            echo "[AES DEBUG] " . $message . "\n";
        }
    }

    /**
     * 验证加密方法是否支持
     */
    private function validateMethod(string $method): void
    {
        if (!in_array($method, self::SUPPORTED_METHODS, true)) {
            throw new InvalidArgumentException(
                "不支持的加密方法: {$method}。支持的方法: " . implode(', ', self::SUPPORTED_METHODS)
            );
        }
    }

    /**
     * 验证密钥长度
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

        $prefix = substr($method, 0, 7);
        $requiredLength = $requiredLengths[$method] ?? $requiredLengths[$prefix] ?? null;

        if ($requiredLength && strlen($key) !== $requiredLength) {
            throw new InvalidArgumentException(
                "密钥长度必须为 {$requiredLength} 字节，当前长度: " . strlen($key)
            );
        }
    }

    /**
     * 验证IV长度
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
     */
    private function requiresIV(): bool
    {
        return !str_contains($this->method, 'ecb');
    }

    /**
     * 检查是否为认证加密模式（GCM）
     */
    private function isAuthenticatedMode(): bool
    {
        return str_ends_with($this->method, '-gcm');
    }

    /**
     * 生成初始化向量
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

        $iv = random_bytes($ivLength);
        $this->logDebug("生成IV: " . bin2hex($iv) . " (长度: {$ivLength})");
        return $iv;
    }

    /**
     * 初始化加密上下文
     */
    private function initializeContext(): void
    {
        $this->encryptionContext = [
            'method' => $this->method,
            'key' => $this->key,
            'iv' => $this->iv,
            'requires_iv' => $this->requiresIV(),
            'tag' => '',
            'tag_length' => 16,
            'authenticated_mode' => $this->isAuthenticatedMode()
        ];
    }

    /**
     * 加密数据
     */
    public function encrypt(string $data, int $options = OPENSSL_RAW_DATA): string
    {
        if (empty($data)) {
            throw new InvalidArgumentException('加密数据不能为空');
        }

        $this->logDebug("开始加密数据，长度: " . strlen($data));

        // 处理认证加密模式
        if ($this->isAuthenticatedMode()) {
            $result = $this->encryptAuthenticated($data, $options);
            $this->logDebug("GCM模式加密完成");
            return $result;
        }

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
        if ($this->requiresIV() && !empty($this->iv)) {
            $result = base64_encode($this->iv . $encrypted);
            $this->logDebug("CBC模式加密完成，包含IV");
            return $result;
        }

        $result = base64_encode($encrypted);
        $this->logDebug("ECB模式加密完成");
        return $result;
    }

    /**
     * 认证加密模式加密（GCM）
     */
    private function encryptAuthenticated(string $data, int $options): string
    {
        $tag = '';
        $tagLength = 16;
        $additionalData = '';

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
     * 解密数据
     */
    public function decrypt(string $encryptedData, int $options = OPENSSL_RAW_DATA): string
    {
        if (empty($encryptedData)) {
            throw new InvalidArgumentException('解密数据不能为空');
        }

        $this->logDebug("开始解密数据");

        $data = base64_decode($encryptedData);
        if ($data === false) {
            throw new RuntimeException('Base64解码失败');
        }

        // 处理认证加密模式
        if ($this->isAuthenticatedMode()) {
            $result = $this->decryptAuthenticated($data, $options);
            $this->logDebug("GCM模式解密完成");
            return $result;
        }

        // 提取IV和加密数据
        if ($this->requiresIV() && !empty($this->iv)) {
            $ivLength = openssl_cipher_iv_length($this->method);
            if ($ivLength === false) {
                throw new RuntimeException('无法获取IV长度');
            }

            $iv = substr($data, 0, $ivLength);
            $encrypted = substr($data, $ivLength);
            $this->logDebug("提取IV: " . bin2hex($iv));
        } else {
            $iv = $this->iv;
            $encrypted = $data;
        }

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
     * 认证加密模式解密（GCM）
     */
    private function decryptAuthenticated(string $data, int $options): string
    {
        $ivLength = openssl_cipher_iv_length($this->method);
        if ($ivLength === false) {
            throw new RuntimeException('无法获取IV长度');
        }

        $tagLength = 16;
        $iv = substr($data, 0, $ivLength);
        $tag = substr($data, $ivLength, $tagLength);
        $encrypted = substr($data, $ivLength + $tagLength);
        $additionalData = '';

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
     * 加密文件 - 优化版：改进文件格式兼容性
     */
    public function encryptFile(string $inputFile, string $outputFile, int $chunkSize = self::DEFAULT_CHUNK_SIZE): bool
    {
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
            // 获取文件大小
            $fileSize = filesize($inputFile);
            if ($fileSize === false) {
                throw new RuntimeException("无法获取文件大小: {$inputFile}");
            }

            $this->logDebug("文件大小: {$fileSize} 字节");

            // 写入增强的文件头
            $header = $this->createEnhancedFileHeader($fileSize);
            if (fwrite($outputHandle, $header) === false) {
                throw new RuntimeException('文件头写入失败');
            }

            // 读取整个文件内容进行加密
            $fileContent = file_get_contents($inputFile);
            if ($fileContent === false) {
                throw new RuntimeException('无法读取文件内容');
            }

            // 加密文件内容
            $encryptedContent = $this->encrypt($fileContent, OPENSSL_RAW_DATA);
            $encryptedBinary = base64_decode($encryptedContent);

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

            // 写入增强的文件尾
            $footer = $this->createEnhancedFileFooter($fileContent, $fileSize);
            if (fwrite($outputHandle, $footer) === false) {
                throw new RuntimeException('文件尾写入失败');
            }

            $this->logDebug("文件加密完成，输出文件: {$outputFile}");
            return true;
        } finally {
            fclose($inputHandle);
            fclose($outputHandle);
        }
    }

    /**
     * 解密文件 - 优化版：改进文件格式解析
     */
    public function decryptFile(string $inputFile, string $outputFile, int $chunkSize = self::DEFAULT_CHUNK_SIZE): bool
    {
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
            // 读取并验证增强的文件头
            $headerInfo = $this->readEnhancedFileHeader($inputHandle);
            $expectedOriginalSize = $headerInfo['original_size'];

            $this->logDebug("文件头验证成功，期望大小: {$expectedOriginalSize}");

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
            $decryptedContent = $this->decrypt($encryptedBase64, OPENSSL_RAW_DATA);

            // 验证解密数据大小
            $decryptedSize = strlen($decryptedContent);
            if ($decryptedSize !== $expectedOriginalSize) {
                throw new RuntimeException("文件大小不匹配: 期望 {$expectedOriginalSize}, 实际 {$decryptedSize}");
            }

            // 写入解密后的内容
            $written = fwrite($outputHandle, $decryptedContent);
            if ($written === false || $written !== $decryptedSize) {
                throw new RuntimeException('解密数据写入失败');
            }

            // 读取并验证文件尾
            $footer = fread($inputHandle, 64);
            if (strlen($footer) !== 64) {
                throw new RuntimeException('文件尾读取失败');
            }

            if (!$this->validateEnhancedFileFooter($footer, $decryptedContent, $expectedOriginalSize)) {
                throw new RuntimeException('文件完整性验证失败');
            }

            $this->logDebug("文件解密完成，完整性验证成功");
            return true;
        } finally {
            fclose($inputHandle);
            fclose($outputHandle);
        }
    }

    /**
     * 创建增强的文件头
     */
    private function createEnhancedFileHeader(int $fileSize): string
    {
        $header = self::FILE_FORMAT_VERSION;
        $header .= pack('C', strlen($this->method));
        $header .= $this->method;

        if ($this->requiresIV()) {
            $header .= $this->iv;
        }

        $header .= pack('N', time()); // 时间戳
        $header .= pack('N', $fileSize); // 原始文件大小
        $header .= random_bytes(16); // 随机填充
        $header .= pack('N', crc32($header)); // 头部校验

        $this->logDebug("创建文件头，大小: " . strlen($header) . " 字节");
        return $header;
    }

    /**
     * 读取增强的文件头
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
        $padding = fread($handle, 16);
        $headerChecksum = unpack('N', fread($handle, 4))[1];

        // 验证头部完整性
        $headerData = $version . pack('C', $methodLength) . $method . $iv .
            pack('N', $timestamp) . pack('N', $originalSize) . $padding;

        if (crc32($headerData) !== $headerChecksum) {
            throw new RuntimeException('文件头完整性验证失败');
        }

        return [
            'version' => $version,
            'method' => $method,
            'iv' => $iv,
            'timestamp' => $timestamp,
            'original_size' => $originalSize,
            'padding' => $padding
        ];
    }

    /**
     * 创建增强的文件尾
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
    }

    /**
     * 验证并调整分块大小
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

        return $chunkSize;
    }

    /**
     * 生成随机密钥
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

        $key = random_bytes($length);
        return $key;
    }

    /**
     * 从密码生成密钥（使用PBKDF2）
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

        $keyLengths = [
            'aes-128' => 16,
            'aes-192' => 24,
            'aes-256' => 32
        ];

        $prefix = substr($method, 0, 7);
        $length = $keyLengths[$prefix] ?? 32;

        $key = hash_pbkdf2('sha256', $password, $salt, $iterations, $length, true);
        return $key;
    }

    /**
     * 获取支持的加密方法列表
     */
    public static function getSupportedMethods(): array
    {
        return self::SUPPORTED_METHODS;
    }

    /**
     * 获取当前使用的加密方法
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * 获取初始化向量
     */
    public function getIV(): string
    {
        return $this->iv;
    }

    /**
     * 计算数据的HMAC签名
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
     */
    public function verifyHMAC(string $data, string $hmac, string $algorithm = self::DEFAULT_HASH_ALGORITHM): bool
    {
        $expectedHmac = $this->calculateHMAC($data, $algorithm);
        return hash_equals($expectedHmac, $hmac);
    }

    /**
     * 加密并签名数据
     */
    public function encryptAndSign(string $data, string $hmacAlgorithm = self::DEFAULT_HASH_ALGORITHM): array
    {
        $encrypted = $this->encrypt($data);
        $signature = $this->calculateHMAC($data, $hmacAlgorithm);

        return [
            'encrypted_data' => $encrypted,
            'signature' => $signature,
            'hmac_algorithm' => $hmacAlgorithm,
            'timestamp' => time()
        ];
    }

    /**
     * 解密并验证签名
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
     */
    public function getCipherInfo(): array
    {
        return [
            'method' => $this->method,
            'key_length' => strlen($this->key) * 8,
            'iv_length' => strlen($this->iv),
            'requires_iv' => $this->requiresIV(),
            'authenticated_mode' => $this->isAuthenticatedMode(),
            'supported_methods' => self::SUPPORTED_METHODS
        ];
    }

    /**
     * 新功能：加密字符串到文件
     */
    public function encryptStringToFile(string $data, string $outputFile): bool
    {
        if (empty($data)) {
            throw new InvalidArgumentException('加密数据不能为空');
        }

        $this->logDebug("加密字符串到文件，数据长度: " . strlen($data));

        // 直接加密并写入文件
        $encrypted = $this->encrypt($data, OPENSSL_RAW_DATA);
        $encryptedBinary = base64_decode($encrypted);

        if ($encryptedBinary === false) {
            throw new RuntimeException('加密数据Base64解码失败');
        }

        $fileSize = strlen($data);
        $header = $this->createEnhancedFileHeader($fileSize);
        $dataLength = pack('N', strlen($encryptedBinary));
        $footer = $this->createEnhancedFileFooter($data, $fileSize);

        $fileContent = $header . $dataLength . $encryptedBinary . $footer;

        $result = file_put_contents($outputFile, $fileContent);
        if ($result === false) {
            throw new RuntimeException('无法写入输出文件');
        }

        $this->logDebug("字符串加密到文件完成: {$outputFile}");
        return true;
    }

    /**
     * 新功能：从文件解密字符串
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

        $headerSize += 4 + 4 + 16 + 4; // 时间戳 + 原始大小 + 填充 + 校验和

        $lengthData = substr($fileContent, $headerSize, 4);
        $dataLength = unpack('N', $lengthData)[1];

        $encryptedData = substr($fileContent, $headerSize + 4, $dataLength);
        $encryptedBase64 = base64_encode($encryptedData);

        $decrypted = $this->decrypt($encryptedBase64, OPENSSL_RAW_DATA);

        $this->logDebug("文件解密到字符串完成，长度: " . strlen($decrypted));
        return $decrypted;
    }

    /**
     * 新功能：批量加密文件
     */
    public function encryptFiles(array $files, string $outputDir): array
    {
        $results = [];

        // 确保输出目录存在
        if (!is_dir($outputDir)) {
            if (!mkdir($outputDir, 0755, true)) {
                throw new RuntimeException("无法创建输出目录: {$outputDir}");
            }
        }

        $this->logDebug("开始批量加密 " . count($files) . " 个文件");

        foreach ($files as $inputFile) {
            try {
                if (!file_exists($inputFile)) {
                    $results[$inputFile] = ['success' => false, 'error' => '文件不存在'];
                    continue;
                }

                $outputFile = $outputDir . '/' . basename($inputFile) . '.encrypted';

                $success = $this->encryptFile($inputFile, $outputFile);
                $results[$inputFile] = [
                    'success' => $success,
                    'output_file' => $outputFile
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

        $successCount = count(array_filter($results, function($r) { return $r['success']; }));
        $this->logDebug("批量加密完成: {$successCount}/" . count($files) . " 成功");
        return $results;
    }

    /**
     * 新功能：批量解密文件
     */
    public function decryptFiles(array $files, string $outputDir): array
    {
        $results = [];

        // 确保输出目录存在
        if (!is_dir($outputDir)) {
            if (!mkdir($outputDir, 0755, true)) {
                throw new RuntimeException("无法创建输出目录: {$outputDir}");
            }
        }

        $this->logDebug("开始批量解密 " . count($files) . " 个文件");

        foreach ($files as $inputFile) {
            try {
                if (!file_exists($inputFile)) {
                    $results[$inputFile] = ['success' => false, 'error' => '文件不存在'];
                    continue;
                }

                $outputFile = $outputDir . '/' . basename($inputFile, '.encrypted');

                $success = $this->decryptFile($inputFile, $outputFile);
                $results[$inputFile] = [
                    'success' => $success,
                    'output_file' => $outputFile
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

        $successCount = count(array_filter($results, function($r) { return $r['success']; }));
        $this->logDebug("批量解密完成: {$successCount}/" . count($files) . " 成功");
        return $results;
    }

    /**
     * 新功能：获取文件加密信息
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

            return [
                'encrypted' => true,
                'version' => $version,
                'method' => $method,
                'format' => 'AES Encrypted File'
            ];
        } finally {
            fclose($handle);
        }
    }

    /**
     * 新功能：更改加密密钥
     */
    public function changeKey(string $newKey): void
    {
        $this->validateKey($newKey, $this->method);
        $this->key = $newKey;
        $this->initializeContext();
        $this->logDebug("加密密钥已更改");
    }

    /**
     * 新功能：更改加密方法
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
     * 新功能：启用/禁用调试模式
     */
    public function setDebugMode(bool $enabled): void
    {
        $this->debugMode = $enabled;
        $this->logDebug("调试模式 " . ($enabled ? '启用' : '禁用'));
    }

    /**
     * 新功能：性能测试
     */
    public function testPerformance(int $dataSize = 1024, int $iterations = 100): array
    {
        $testData = random_bytes($dataSize);

        $startTime = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $encrypted = $this->encrypt($testData);
            $this->decrypt($encrypted);
        }
        $totalTime = microtime(true) - $startTime;

        $throughput = ($dataSize * $iterations * 2) / $totalTime / 1024 / 1024; // MB/s

        return [
            'method' => $this->method,
            'data_size' => $dataSize,
            'iterations' => $iterations,
            'total_time' => round($totalTime, 4) . '秒',
            'throughput' => round($throughput, 2) . 'MB/秒',
            'operations_per_second' => round($iterations / $totalTime, 2)
        ];
    }
}
