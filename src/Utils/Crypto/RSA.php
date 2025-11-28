<?php

namespace zxf\Utils\Crypto;

use Exception;
use InvalidArgumentException;
use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * RSA加密解密类
 * 支持密钥生成、加密、解密、签名、验证和文件操作
 * 基于PHP 8.2+ openssl扩展实现，包含完整的错误处理和调试功能
 *
 * 主要功能：
 * - 支持多种密钥长度（2048, 3072, 4096位）
 * - 支持PKCS1 v1.5和OAEP填充方式
 * - 支持文件加密解密和批量操作
 * - 支持数字签名和验证
 * - 支持证书操作和CSR生成
 * - 完整的调试日志和性能监控
 * - 混合加密方案（RSA+AES）用于大文件
 *
 * @package Crypto
 * @author Security Team
 * @version 1.0.0
 * @license MIT
 * @created 2026-01-01
 * @updated 2026-01-15
 */
class RSA
{
    /**
     * @var OpenSSLAsymmetricKey|null 私钥资源
     * @access private
     */
    private $privateKey = null;

    /**
     * @var OpenSSLAsymmetricKey|null 公钥资源
     * @access private
     */
    private $publicKey = null;

    /**
     * @var int 密钥位数（2048, 3072, 4096）
     * @access private
     */
    private int $keySize;

    /**
     * @var string 哈希算法（sha256, sha384, sha512）
     * @access private
     */
    private string $hashAlg;

    /**
     * @var array 支持的哈希算法列表
     * @access private
     */
    private const SUPPORTED_HASH_ALGORITHMS = [
        'sha256' => 'SHA256 (推荐)',
        'sha384' => 'SHA384 (安全)',
        'sha512' => 'SHA512 (高安全)',
        'sha3-256' => 'SHA3-256 (前沿)',
        'sha3-384' => 'SHA3-384 (前沿)',
        'sha3-512' => 'SHA3-512 (前沿)'
    ];

    /**
     * @var array 支持的密钥长度列表
     * @access private
     */
    private const SUPPORTED_KEY_SIZES = [
        2048 => '2048位 (推荐)',
        3072 => '3072位 (安全)',
        4096 => '4096位 (高安全)',
        8192 => '8192位 (极高安全)'
    ];

    /**
     * @var array 支持的填充方式
     * @access private
     */
    private const SUPPORTED_PADDINGS = [
        OPENSSL_PKCS1_PADDING => 'PKCS1 v1.5 (兼容性)',
        OPENSSL_PKCS1_OAEP_PADDING => 'PKCS1 OAEP (推荐)',
        OPENSSL_NO_PADDING => '无填充 (特殊用途)'
    ];

    /**
     * @var int 默认分块大小（根据密钥长度动态计算）
     * @access private
     */
    private const DEFAULT_CHUNK_SIZE = 214;

    /**
     * @var string 文件格式版本
     * @access private
     */
    private const FILE_FORMAT_VERSION = 'RSAv4';

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
     * @var array 加密上下文信息
     * @access private
     */
    private array $encryptionContext = [];

    /**
     * @var int 内存使用限制（字节）
     * @access private
     */
    private const MEMORY_LIMIT = 134217728; // 128MB

    /**
     * 构造函数 - 初始化RSA加密器
     *
     * @param string|null $privateKey 私钥字符串（PEM格式）
     * @param string|null $publicKey 公钥字符串（PEM格式）
     * @param int $keySize 密钥长度（可选值：2048, 3072, 4096）
     * @param string $hashAlg 哈希算法（可选值：sha256, sha384, sha512）
     * @param bool $debugMode 调试模式开关（默认false）
     *
     * @throws InvalidArgumentException 当参数验证失败时抛出
     * @throws RuntimeException|Exception 当加密器初始化失败时抛出
     *
     * 使用示例：
     * $rsa = new RSA($privateKey, $publicKey, 2048, 'sha256', true);
     * $rsa = new RSA(null, $publicKey, 2048, 'sha256'); // 仅公钥模式
     * $rsa = new RSA($privateKey, null, 2048, 'sha256'); // 仅私钥模式
     */
    public function __construct(
        ?string $privateKey = null,
        ?string $publicKey = null,
        int $keySize = 2048,
        string $hashAlg = 'sha256',
        bool $debugMode = false
    ) {
        $this->debugMode = $debugMode;
        $this->validateKeySize($keySize);
        $this->validateHashAlgorithm($hashAlg);

        $this->keySize = $keySize;
        $this->hashAlg = $hashAlg;

        $this->logDebug("RSA加密器初始化 - 密钥长度: {$keySize}, 哈希算法: {$hashAlg}");

        try {
            if ($privateKey === null && $publicKey === null) {
                $this->generateKeyPair();
                $this->logDebug("生成新的RSA密钥对");
            } elseif ($privateKey !== null) {
                $this->loadPrivateKey($privateKey);
                if ($publicKey !== null) {
                    $this->loadPublicKey($publicKey);
                } else {
                    $this->derivePublicKey();
                }
                $this->logDebug("从私钥加载RSA密钥");
            } elseif ($publicKey !== null) {
                $this->loadPublicKey($publicKey);
                $this->logDebug("从公钥加载RSA密钥");
            } else {
                throw new InvalidArgumentException('必须提供私钥或公钥');
            }

            // 初始化上下文和性能统计
            $this->initializeContext();
            $this->initializePerformanceStats();

        } catch (Exception $e) {
            $this->cleanup();
            throw $e;
        }
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
            echo "[RSA DEBUG][{$timestamp}][{$memory}MB] " . $message . "\n";
        }
    }

    /**
     * 析构函数 - 安全清理资源
     */
    public function __destruct()
    {
        $this->cleanup();
    }

    /**
     * 清理密钥资源 - 安全擦除敏感数据
     *
     * @return void
     * @access private
     */
    private function cleanup(): void
    {
        // PHP 8.0+ 会自动管理OpenSSL资源，这里只需取消引用
        $this->privateKey = null;
        $this->publicKey = null;

        // 清空上下文和统计信息
        $this->encryptionContext = [];
        $this->performanceStats = [];

        $this->logDebug("RSA资源安全清理完成");
    }

    /**
     * 验证密钥长度是否支持
     *
     * @param int $keySize 密钥长度
     * @return void
     * @throws InvalidArgumentException 当密钥长度不支持时抛出
     * @access private
     */
    private function validateKeySize(int $keySize): void
    {
        if (!array_key_exists($keySize, self::SUPPORTED_KEY_SIZES)) {
            $supported = implode(', ', array_keys(self::SUPPORTED_KEY_SIZES));
            throw new InvalidArgumentException(
                "不支持的密钥长度: {$keySize}。支持的密钥长度: {$supported}"
            );
        }

        // 检查密钥长度是否达到安全标准
        if ($keySize < 2048) {
            throw new InvalidArgumentException(
                "密钥长度必须至少为2048位以确保安全，当前: {$keySize}"
            );
        }
    }

    /**
     * 验证哈希算法是否支持
     *
     * @param string $hashAlg 哈希算法名称
     * @return void
     * @throws InvalidArgumentException 当算法不支持时抛出
     * @access private
     */
    private function validateHashAlgorithm(string $hashAlg): void
    {
        if (!array_key_exists($hashAlg, self::SUPPORTED_HASH_ALGORITHMS)) {
            $supported = implode(', ', array_keys(self::SUPPORTED_HASH_ALGORITHMS));
            throw new InvalidArgumentException(
                "不支持的哈希算法: {$hashAlg}。支持的算法: {$supported}"
            );
        }

        // 检查系统是否支持该哈希算法
        if (!in_array($hashAlg, hash_algos())) {
            throw new InvalidArgumentException(
                "当前系统不支持哈希算法: {$hashAlg}"
            );
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
        $keyDetails = $this->getKeyDetails();

        $this->encryptionContext = [
            'key_size' => $this->keySize,
            'hash_algorithm' => $this->hashAlg,
            'has_private_key' => $this->hasPrivateKey(),
            'has_public_key' => $this->hasPublicKey(),
            'key_type' => $keyDetails['type'] ?? OPENSSL_KEYTYPE_RSA,
            'bits' => $keyDetails['bits'] ?? $this->keySize,
            'max_encrypt_size' => $this->getMaxEncryptBlockSize(OPENSSL_PKCS1_OAEP_PADDING),
            'initialized_at' => microtime(true),
            'operations_count' => 0
        ];
    }

    /**
     * 初始化性能统计信息
     *
     * @return void
     * @access private
     */
    private function initializePerformanceStats(): void
    {
        $this->performanceStats = [
            'encryption_operations' => 0,
            'decryption_operations' => 0,
            'signing_operations' => 0,
            'verification_operations' => 0,
            'total_data_processed' => 0,
            'start_time' => microtime(true),
            'last_operation_time' => null,
            'peak_memory_usage' => 0
        ];
    }

    /**
     * 更新性能统计信息
     *
     * @param string $operation 操作类型
     * @param int $dataSize 数据大小
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
     * 生成RSA密钥对
     *
     * @return void
     * @throws RuntimeException 当密钥生成失败时抛出
     * @access private
     */
    private function generateKeyPair(): void
    {
        $config = [
            'private_key_bits' => $this->keySize,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'digest_alg' => $this->hashAlg,
        ];

        // 根据密钥长度调整配置
        if ($this->keySize >= 4096) {
            $config['config'] = openssl_get_cert_locations();
        }

        $keyPair = openssl_pkey_new($config);
        if ($keyPair === false) {
            $error = openssl_error_string();
            throw new RuntimeException('RSA密钥对生成失败: ' . ($error ?: '未知错误'));
        }

        $this->privateKey = $keyPair;
        $this->derivePublicKey();

        // 更新实际密钥大小
        $this->updateActualKeySize();

        $this->logDebug("RSA密钥对生成成功 - 实际长度: {$this->keySize}位");
    }

    /**
     * 加载私钥 - 支持密码保护的私钥
     *
     * @param string $privateKey 私钥字符串
     * @param string $passphrase 私钥密码（可选）
     * @return void
     * @throws RuntimeException 当私钥加载失败时抛出
     * @access private
     */
    private function loadPrivateKey(string $privateKey, string $passphrase = ''): void
    {
        // 尝试不同的密码组合
        $passphrases = [$passphrase, '', null];
        $resource = false;

        foreach ($passphrases as $pass) {
            $resource = openssl_pkey_get_private($privateKey, $pass);
            if ($resource !== false) {
                break;
            }
        }

        if ($resource === false) {
            // 尝试解析PEM格式
            if (strpos($privateKey, 'ENCRYPTED') !== false) {
                throw new RuntimeException('私钥需要密码，请使用createFromKey方法并提供密码');
            }
            $error = openssl_error_string();
            throw new RuntimeException('私钥加载失败: ' . ($error ?: '未知错误'));
        }

        $this->privateKey = $resource;
        $this->updateActualKeySize();
        $this->logDebug("私钥加载成功");
    }

    /**
     * 加载公钥
     *
     * @param string $publicKey 公钥字符串
     * @return void
     * @throws RuntimeException 当公钥加载失败时抛出
     * @access private
     */
    private function loadPublicKey(string $publicKey): void
    {
        $this->publicKey = openssl_pkey_get_public($publicKey);
        if ($this->publicKey === false) {
            $error = openssl_error_string();
            throw new RuntimeException('公钥加载失败: ' . ($error ?: '未知错误'));
        }

        $this->updateActualKeySize();
        $this->logDebug("公钥加载成功");
    }

    /**
     * 更新实际密钥大小 - 动态调整密钥大小
     *
     * @return void
     * @access private
     */
    private function updateActualKeySize(): void
    {
        $key = $this->privateKey ?? $this->publicKey;
        if ($key === null) {
            return;
        }

        $details = openssl_pkey_get_details($key);
        if ($details === false || !isset($details['bits'])) {
            throw new RuntimeException('无法获取密钥大小信息');
        }

        $loadedKeySize = $details['bits'];

        // 允许密钥大小有一定差异，但记录警告
        if (abs($loadedKeySize - $this->keySize) > 512) {
            // 如果差异太大，更新为实际大小
            $oldSize = $this->keySize;
            $this->keySize = $loadedKeySize;
            $this->logDebug("密钥大小调整: {$oldSize} -> {$loadedKeySize}");
        }
    }

    /**
     * 从私钥导出公钥
     *
     * @return void
     * @throws RuntimeException 当公钥导出失败时抛出
     * @access private
     */
    private function derivePublicKey(): void
    {
        if ($this->privateKey === null) {
            throw new RuntimeException('私钥不可用，无法导出公钥');
        }

        $details = openssl_pkey_get_details($this->privateKey);
        if ($details === false) {
            $error = openssl_error_string();
            throw new RuntimeException('获取密钥详情失败: ' . ($error ?: '未知错误'));
        }

        if (!isset($details['key'])) {
            throw new RuntimeException('无法从私钥导出公钥：密钥格式无效');
        }

        $publicKeyPem = $details['key'];
        $this->publicKey = openssl_pkey_get_public($publicKeyPem);
        if ($this->publicKey === false) {
            $error = openssl_error_string();
            throw new RuntimeException('公钥导出失败: ' . ($error ?: '未知错误'));
        }

        $this->logDebug("从私钥成功导出公钥");
    }

    /**
     * 加密数据 - 主加密方法
     *
     * @param string $data 要加密的原始数据
     * @param int $padding 填充方式（可选值：OPENSSL_PKCS1_PADDING, OPENSSL_PKCS1_OAEP_PADDING）
     * @param bool $useChunking 是否启用分块加密（大数据时自动启用）
     * @return string 加密后的数据（Base64编码）
     *
     * @throws InvalidArgumentException 当数据为空时抛出
     * @throws RuntimeException 当加密失败时抛出
     *
     * 使用示例：
     * $encrypted = $rsa->encrypt('敏感数据', OPENSSL_PKCS1_OAEP_PADDING, true);
     */
    public function encrypt(
        string $data,
        int $padding = OPENSSL_PKCS1_OAEP_PADDING,
        bool $useChunking = true
    ): string {
        if ($this->publicKey === null) {
            throw new RuntimeException('需要公钥来进行加密');
        }

        if (empty($data)) {
            throw new InvalidArgumentException('加密数据不能为空');
        }

        $this->logDebug("开始RSA加密，数据长度: " . strlen($data));
        $this->updatePerformanceStats('encryption', strlen($data));

        // 验证填充方式
        $this->validatePadding($padding);

        $maxLength = $this->getMaxEncryptBlockSize($padding);

        // 检查是否需要分块加密
        if ($useChunking && strlen($data) > $maxLength) {
            $result = $this->encryptChunked($data, $padding);
            $this->logDebug("分块加密完成");
            return $result;
        } else {
            $result = $this->encryptSingleBlock($data, $padding);
            $this->logDebug("单块加密完成");
            return $result;
        }
    }

    /**
     * 验证填充方式是否支持
     *
     * @param int $padding 填充方式
     * @return void
     * @throws InvalidArgumentException 当填充方式不支持时抛出
     * @access private
     */
    private function validatePadding(int $padding): void
    {
        if (!array_key_exists($padding, self::SUPPORTED_PADDINGS)) {
            $supported = implode(', ', array_keys(self::SUPPORTED_PADDINGS));
            throw new InvalidArgumentException(
                "不支持的填充方式: {$padding}。支持的填充方式: {$supported}"
            );
        }
    }

    /**
     * 单块加密
     *
     * @param string $data 原始数据
     * @param int $padding 填充方式
     * @return string 加密结果
     * @throws RuntimeException 当加密失败时抛出
     * @access private
     */
    private function encryptSingleBlock(string $data, int $padding): string
    {
        $encrypted = '';
        $success = openssl_public_encrypt($data, $encrypted, $this->publicKey, $padding);

        if (!$success) {
            $error = openssl_error_string();
            throw new RuntimeException('加密失败: ' . ($error ?: '未知错误'));
        }

        return base64_encode($encrypted);
    }

    /**
     * 单块加密（二进制输出）
     *
     * @param string $data 原始数据
     * @param int $padding 填充方式
     * @return string 加密结果（二进制）
     * @access private
     */
    private function encryptSingleBlockBinary(string $data, int $padding): string
    {
        $encrypted = '';
        $success = openssl_public_encrypt($data, $encrypted, $this->publicKey, $padding);

        if (!$success) {
            $error = openssl_error_string();
            throw new RuntimeException('加密失败: ' . ($error ?: '未知错误'));
        }

        return $encrypted;
    }

    /**
     * 分块加密数据
     *
     * @param string $data 原始数据
     * @param int $padding 填充方式
     * @return string 加密结果
     * @throws RuntimeException 当加密失败时抛出
     * @access private
     */
    private function encryptChunked(string $data, int $padding): string
    {
        $chunkSize = $this->calculateOptimalChunkSize($padding);
        $chunks = str_split($data, $chunkSize);
        $encryptedChunks = [];

        $this->logDebug("分块加密，块大小: {$chunkSize}, 总块数: " . count($chunks));

        foreach ($chunks as $index => $chunk) {
            $encrypted = '';
            $success = openssl_public_encrypt($chunk, $encrypted, $this->publicKey, $padding);

            if (!$success) {
                $error = openssl_error_string();
                throw new RuntimeException("分块加密失败 (块 {$index}): " . ($error ?: '未知错误'));
            }

            $encryptedChunks[] = base64_encode($encrypted);
        }

        $result = base64_encode(implode('::RSA_CHUNK::', $encryptedChunks));
        $this->logDebug("分块加密完成，总块数: " . count($chunks));
        return $result;
    }

    /**
     * 解密数据 - 主解密方法
     *
     * @param string $encryptedData 加密数据（Base64编码）
     * @param int $padding 填充方式（可选值：OPENSSL_PKCS1_PADDING, OPENSSL_PKCS1_OAEP_PADDING）
     * @return string 解密后的原始数据
     *
     * @throws InvalidArgumentException 当数据为空时抛出
     * @throws RuntimeException 当解密失败时抛出
     *
     * 使用示例：
     * $decrypted = $rsa->decrypt($encryptedData, OPENSSL_PKCS1_OAEP_PADDING);
     */
    public function decrypt(string $encryptedData, int $padding = OPENSSL_PKCS1_OAEP_PADDING): string
    {
        if ($this->privateKey === null) {
            throw new RuntimeException('需要私钥来进行解密');
        }

        if (empty($encryptedData)) {
            throw new InvalidArgumentException('解密数据不能为空');
        }

        $this->logDebug("开始RSA解密");
        $this->updatePerformanceStats('decryption', strlen($encryptedData));

        // 验证填充方式
        $this->validatePadding($padding);

        $data = base64_decode($encryptedData);
        if ($data === false) {
            throw new RuntimeException('Base64解码失败');
        }

        if (strpos($data, '::RSA_CHUNK::') !== false) {
            $result = $this->decryptChunked($data, $padding);
            $this->logDebug("分块解密完成");
            return $result;
        }

        $result = $this->decryptSingleBlock($data, $padding);
        $this->logDebug("单块解密完成");
        return $result;
    }

    /**
     * 单块解密
     *
     * @param string $data 加密数据
     * @param int $padding 填充方式
     * @return string 解密结果
     * @throws RuntimeException 当解密失败时抛出
     * @access private
     */
    private function decryptSingleBlock(string $data, int $padding): string
    {
        $decrypted = '';
        $success = openssl_private_decrypt($data, $decrypted, $this->privateKey, $padding);

        if (!$success) {
            $error = openssl_error_string();
            throw new RuntimeException('解密失败: ' . ($error ?: '未知错误'));
        }

        return $decrypted;
    }

    /**
     * 单块解密（二进制输入）
     *
     * @param string $data 加密数据（二进制）
     * @param int $padding 填充方式
     * @return string 解密结果
     * @access private
     */
    private function decryptSingleBlockBinary(string $data, int $padding): string
    {
        $decrypted = '';
        $success = openssl_private_decrypt($data, $decrypted, $this->privateKey, $padding);

        if (!$success) {
            $error = openssl_error_string();
            throw new RuntimeException('解密失败: ' . ($error ?: '未知错误'));
        }

        return $decrypted;
    }

    /**
     * 分块解密数据
     *
     * @param string $data 加密数据
     * @param int $padding 填充方式
     * @return string 解密结果
     * @throws RuntimeException 当解密失败时抛出
     * @access private
     */
    private function decryptChunked(string $data, int $padding): string
    {
        $chunks = explode('::RSA_CHUNK::', $data);
        $decryptedChunks = [];

        $this->logDebug("分块解密，块数: " . count($chunks));

        foreach ($chunks as $index => $chunk) {
            $chunkData = base64_decode($chunk);
            if ($chunkData === false) {
                throw new RuntimeException("分块Base64解码失败 (块 {$index})");
            }

            $decrypted = '';
            $success = openssl_private_decrypt($chunkData, $decrypted, $this->privateKey, $padding);

            if (!$success) {
                $error = openssl_error_string();
                throw new RuntimeException("分块解密失败 (块 {$index}): " . ($error ?: '未知错误'));
            }

            $decryptedChunks[] = $decrypted;
        }

        $result = implode('', $decryptedChunks);
        $this->logDebug("分块解密完成，总长度: " . strlen($result));
        return $result;
    }

    /**
     * 获取最大加密块大小
     *
     * @param int $padding 填充方式
     * @return int 最大块大小（字节）
     * @access private
     */
    private function getMaxEncryptBlockSize(int $padding): int
    {
        $keySizeInBytes = (int)($this->keySize / 8);

        return match ($padding) {
            OPENSSL_PKCS1_PADDING => $keySizeInBytes - 11,
            OPENSSL_PKCS1_OAEP_PADDING => $keySizeInBytes - 42,
            OPENSSL_NO_PADDING => $keySizeInBytes,
            default => $keySizeInBytes - 11
        };
    }

    /**
     * 计算最优分块大小
     *
     * @param int $padding 填充方式
     * @return int 最优分块大小
     * @access private
     */
    private function calculateOptimalChunkSize(int $padding): int
    {
        $maxBlockSize = $this->getMaxEncryptBlockSize($padding);
        return max(1, $maxBlockSize - 10); // 留出一些余量
    }

    /**
     * 加密文件 - 使用混合加密方案（RSA+AES）
     *
     * @param string $inputFile 输入文件路径
     * @param string $outputFile 输出文件路径
     * @param int $chunkSize 分块大小（字节）
     * @param bool $enableCompression 是否启用压缩
     * @param bool $enableIntegrityCheck 是否启用完整性检查
     * @return bool 加密是否成功
     *
     * @throws InvalidArgumentException 当文件操作参数无效时抛出
     * @throws RuntimeException 当文件操作失败时抛出
     *
     * 使用示例：
     * $success = $rsa->encryptFile('input.txt', 'output.enc', 65536, true, true);
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

        $this->logDebug("开始RSA文件加密: {$inputFile} -> {$outputFile}");

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
            $this->logDebug("文件信息 - 大小: {$fileInfo['size']}字节");

            // 写入增强的文件头
            $header = $this->createFileHeader($fileInfo, $enableCompression, $enableIntegrityCheck);
            if (fwrite($outputHandle, $header) === false) {
                throw new RuntimeException('文件头写入失败');
            }

            // 使用混合加密：使用随机AES密钥加密文件，再用RSA加密AES密钥
            $aesKey = $this->generateAESKey();
            $aesIV = $this->generateAESIV();

            $this->logDebug("生成AES密钥和IV");

            // 加密AES密钥并写入
            $encryptedAesKey = $this->encryptSingleBlockBinary($aesKey, OPENSSL_PKCS1_OAEP_PADDING);
            $encryptedAesIV = $this->encryptSingleBlockBinary($aesIV, OPENSSL_PKCS1_OAEP_PADDING);

            $keyHeader = pack('N', strlen($encryptedAesKey)) . $encryptedAesKey .
                pack('N', strlen($encryptedAesIV)) . $encryptedAesIV;

            if (fwrite($outputHandle, $keyHeader) === false) {
                throw new RuntimeException('密钥头写入失败');
            }

            $this->logDebug("AES密钥和IV加密完成");

            // 使用AES加密文件内容
            $aes = new AES($aesKey, 'aes-256-cbc', $aesIV);
            $tempEncryptedFile = $outputFile . '.aes.tmp';

            $success = $aes->encryptFile($inputFile, $tempEncryptedFile, $chunkSize, $enableCompression, $enableIntegrityCheck);

            if (!$success) {
                throw new RuntimeException('AES文件加密失败');
            }

            // 读取加密后的内容并写入
            $encryptedContent = file_get_contents($tempEncryptedFile);
            if ($encryptedContent === false) {
                throw new RuntimeException('无法读取临时加密文件');
            }

            $written = fwrite($outputHandle, $encryptedContent);
            if ($written === false || $written !== strlen($encryptedContent)) {
                throw new RuntimeException('加密内容写入失败');
            }

            $this->logDebug("RSA文件加密完成: {$outputFile}");
            return true;
        } finally {
            fclose($inputHandle);
            fclose($outputHandle);
            // 安全清理临时文件
            if (isset($tempEncryptedFile) && file_exists($tempEncryptedFile)) {
                $this->secureDelete($tempEncryptedFile);
                $this->logDebug("临时文件已安全删除: {$tempEncryptedFile}");
            }
        }
    }

    /**
     * 生成AES密钥
     *
     * @return string AES密钥
     * @access private
     */
    private function generateAESKey(): string
    {
        return random_bytes(32); // AES-256
    }

    /**
     * 生成AES IV
     *
     * @return string AES IV
     * @access private
     */
    private function generateAESIV(): string
    {
        return random_bytes(16); // AES块大小
    }

    /**
     * 安全删除文件
     *
     * @param string $filePath 文件路径
     * @return bool 是否成功
     * @access private
     */
    private function secureDelete(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            return true;
        }

        // 尝试多次覆盖文件内容
        $fileSize = filesize($filePath);

        if ($fileSize > 0) {
            $handle = fopen($filePath, 'r+');
            if ($handle !== false) {
                // 使用随机数据覆盖3次
                for ($i = 0; $i < 3; $i++) {
                    fseek($handle, 0);
                    fwrite($handle, random_bytes($fileSize));
                    fflush($handle);
                }
                fclose($handle);
            }
        }

        // 删除文件
        return unlink($filePath);
    }

    /**
     * 解密文件 - 使用混合加密方案
     *
     * @param string $inputFile 输入文件路径
     * @param string $outputFile 输出文件路径
     * @param int $chunkSize 分块大小（字节）
     * @return bool 解密是否成功
     *
     * @throws InvalidArgumentException 当文件操作参数无效时抛出
     * @throws RuntimeException 当文件操作失败时抛出
     *
     * 使用示例：
     * $success = $rsa->decryptFile('input.enc', 'output.txt', 65536);
     */
    public function decryptFile(
        string $inputFile,
        string $outputFile,
        int $chunkSize = self::DEFAULT_CHUNK_SIZE
    ): bool {
        $this->validateFileOperation($inputFile, $outputFile);
        $chunkSize = $this->validateAndAdjustChunkSize($chunkSize);

        $this->logDebug("开始RSA文件解密: {$inputFile} -> {$outputFile}");

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
            // 读取文件头
            $header = $this->readFileHeader($inputHandle);
            $this->validateFileHeader($header);

            $this->logDebug("RSA文件头验证成功");

            // 读取加密的AES密钥和IV
            $keyLengthData = fread($inputHandle, 4);
            if (strlen($keyLengthData) !== 4) {
                throw new RuntimeException('密钥长度读取失败');
            }

            $keyLength = unpack('N', $keyLengthData)[1];
            $encryptedAesKey = fread($inputHandle, $keyLength);
            if (strlen($encryptedAesKey) !== $keyLength) {
                throw new RuntimeException('AES密钥读取失败');
            }

            $ivLengthData = fread($inputHandle, 4);
            if (strlen($ivLengthData) !== 4) {
                throw new RuntimeException('IV长度读取失败');
            }

            $ivLength = unpack('N', $ivLengthData)[1];
            $encryptedAesIV = fread($inputHandle, $ivLength);
            if (strlen($encryptedAesIV) !== $ivLength) {
                throw new RuntimeException('AES IV读取失败');
            }

            $this->logDebug("AES密钥和IV读取成功");

            // 解密AES密钥和IV
            $aesKey = $this->decryptSingleBlockBinary($encryptedAesKey, OPENSSL_PKCS1_OAEP_PADDING);
            $aesIV = $this->decryptSingleBlockBinary($encryptedAesIV, OPENSSL_PKCS1_OAEP_PADDING);

            $aes = new AES($aesKey, 'aes-256-cbc', $aesIV);

            $this->logDebug("AES密钥和IV解密成功");

            // 使用AES解密剩余内容
            $tempOutput = $outputFile . '.tmp';
            $remainingContent = '';
            while (!feof($inputHandle)) {
                $chunk = fread($inputHandle, 8192);
                if ($chunk !== false) {
                    $remainingContent .= $chunk;
                }
            }

            if (file_put_contents($tempOutput, $remainingContent) === false) {
                throw new RuntimeException('无法写入临时文件');
            }

            $success = $aes->decryptFile($tempOutput, $outputFile, $chunkSize);

            $this->logDebug("RSA文件解密完成: " . ($success ? '成功' : '失败'));
            return $success;
        } finally {
            fclose($inputHandle);
            fclose($outputHandle);
            // 安全清理临时文件
            if (isset($tempOutput) && file_exists($tempOutput)) {
                $this->secureDelete($tempOutput);
                $this->logDebug("临时文件已安全删除: {$tempOutput}");
            }
        }
    }

    /**
     * 创建文件头
     *
     * @param array $fileInfo 文件信息
     * @param bool $compressed 是否压缩
     * @param bool $integrityCheck 是否完整性检查
     * @return string 文件头数据
     * @access private
     */
    private function createFileHeader(array $fileInfo, bool $compressed, bool $integrityCheck): string
    {
        $header = self::FILE_FORMAT_VERSION;
        $header .= pack('n', $this->keySize);
        $header .= pack('C', strlen($this->hashAlg));
        $header .= $this->hashAlg;
        $header .= pack('N', time());
        $header .= pack('N', $fileInfo['size']);
        $header .= pack('C', $compressed ? 1 : 0);
        $header .= pack('C', $integrityCheck ? 1 : 0);
        $header .= random_bytes(12); // 随机填充
        $header .= pack('N', crc32($header)); // 头部校验

        $this->logDebug("创建RSA文件头，大小: " . strlen($header) . " 字节");
        return $header;
    }

    /**
     * 读取文件头
     *
     * @param resource $handle 文件句柄
     * @return array 头部信息
     * @access private
     */
    private function readFileHeader($handle): array
    {
        $version = fread($handle, 5);
        if ($version !== self::FILE_FORMAT_VERSION) {
            throw new RuntimeException('不支持的RSA文件格式版本: ' . $version);
        }

        $keySize = unpack('n', fread($handle, 2))[1];
        $hashAlgLength = unpack('C', fread($handle, 1))[1];
        $hashAlg = fread($handle, $hashAlgLength);
        $timestamp = unpack('N', fread($handle, 4))[1];
        $originalSize = unpack('N', fread($handle, 4))[1];
        $compressed = unpack('C', fread($handle, 1))[1] === 1;
        $integrityCheck = unpack('C', fread($handle, 1))[1] === 1;
        $padding = fread($handle, 12);
        $checksum = unpack('N', fread($handle, 4))[1];

        $headerData = $version . pack('n', $keySize) . pack('C', $hashAlgLength) . $hashAlg .
            pack('N', $timestamp) . pack('N', $originalSize) .
            pack('C', $compressed ? 1 : 0) . pack('C', $integrityCheck ? 1 : 0) . $padding;

        // 验证头部完整性
        if (crc32($headerData) !== $checksum) {
            throw new RuntimeException('RSA文件头完整性验证失败');
        }

        $this->logDebug("RSA文件头读取成功，密钥大小: {$keySize}, 哈希算法: {$hashAlg}");
        return [
            'version' => $version,
            'key_size' => $keySize,
            'hash_algorithm' => $hashAlg,
            'timestamp' => $timestamp,
            'original_size' => $originalSize,
            'compressed' => $compressed,
            'integrity_check' => $integrityCheck,
            'padding' => $padding
        ];
    }

    /**
     * 验证文件头
     *
     * @param array $header 头部信息
     * @return void
     * @access private
     */
    private function validateFileHeader(array $header): void
    {
        if ($header['version'] !== self::FILE_FORMAT_VERSION) {
            throw new RuntimeException('无效的RSA文件格式');
        }

        if ($header['key_size'] !== $this->keySize) {
            $this->logDebug("文件头密钥大小不匹配: {$header['key_size']} != {$this->keySize}");
        }
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
            return self::DEFAULT_CHUNK_SIZE;
        }

        // 更宽松的验证，只确保不是太大
        $maxReasonableSize = 1024 * 1024 * 10; // 10MB
        if ($chunkSize > $maxReasonableSize) {
            return self::DEFAULT_CHUNK_SIZE;
        }

        return $chunkSize;
    }

    /**
     * 签名数据
     *
     * @param string $data 要签名的数据
     * @param string|null $signatureAlg 签名算法（默认使用构造函数设置的算法）
     * @return string 数字签名（Base64编码）
     *
     * @throws RuntimeException 当签名失败时抛出
     *
     * 使用示例：
     * $signature = $rsa->sign('要签名的数据');
     * $signature = $rsa->sign('数据', 'sha512'); // 使用特定算法
     */
    public function sign(string $data, ?string $signatureAlg = null): string
    {
        if ($this->privateKey === null) {
            throw new RuntimeException('需要私钥来进行签名');
        }

        $algorithm = $signatureAlg ?? $this->hashAlg;
        $this->validateHashAlgorithm($algorithm);

        $this->logDebug("开始RSA签名，算法: {$algorithm}, 数据长度: " . strlen($data));
        $this->updatePerformanceStats('signing', strlen($data));

        $signature = '';
        $success = openssl_sign($data, $signature, $this->privateKey, $algorithm);

        if (!$success) {
            $error = openssl_error_string();
            throw new RuntimeException('签名失败: ' . ($error ?: '未知错误'));
        }

        $result = base64_encode($signature);
        $this->logDebug("RSA签名完成");
        return $result;
    }

    /**
     * 验证签名
     *
     * @param string $data 原始数据
     * @param string $signature 数字签名（Base64编码）
     * @param string|null $signatureAlg 签名算法（默认使用构造函数设置的算法）
     * @return bool 验证结果
     *
     * @throws RuntimeException 当验证过程出错时抛出
     *
     * 使用示例：
     * $valid = $rsa->verify('原始数据', $signature);
     * $valid = $rsa->verify('数据', $signature, 'sha512');
     */
    public function verify(string $data, string $signature, ?string $signatureAlg = null): bool
    {
        // 如果没有公钥，尝试从私钥导出
        if ($this->publicKey === null) {
            if ($this->privateKey === null) {
                throw new RuntimeException('需要公钥或私钥来验证签名');
            }
            $this->derivePublicKey();
            $this->logDebug("从私钥导出公钥进行签名验证");
        }

        $algorithm = $signatureAlg ?? $this->hashAlg;
        $this->validateHashAlgorithm($algorithm);

        $signatureBinary = base64_decode($signature);
        if ($signatureBinary === false) {
            throw new RuntimeException('签名Base64解码失败');
        }

        $this->updatePerformanceStats('verification', strlen($data));

        $result = openssl_verify($data, $signatureBinary, $this->publicKey, $algorithm);

        if ($result === -1) {
            $error = openssl_error_string();
            throw new RuntimeException('签名验证过程出错: ' . ($error ?: '未知错误'));
        }

        $this->logDebug("RSA签名验证: " . ($result === 1 ? '成功' : '失败'));
        return $result === 1;
    }

    /**
     * 签名文件
     *
     * @param string $filePath 文件路径
     * @param string|null $signatureAlg 签名算法
     * @return string 文件签名
     *
     * @throws InvalidArgumentException 当文件不存在时抛出
     * @throws RuntimeException 当签名失败时抛出
     *
     * 使用示例：
     * $signature = $rsa->signFile('document.pdf');
     * $signature = $rsa->signFile('file.txt', 'sha384');
     */
    public function signFile(string $filePath, ?string $signatureAlg = null): string
    {
        if ($this->privateKey === null) {
            throw new RuntimeException('需要私钥来进行文件签名');
        }

        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("文件不存在: {$filePath}");
        }

        $this->logDebug("开始文件签名: {$filePath}");

        $fileHash = hash_file($signatureAlg ?? $this->hashAlg, $filePath);
        if ($fileHash === false) {
            throw new RuntimeException('文件哈希计算失败');
        }

        $signature = $this->sign($fileHash, $signatureAlg);
        $this->logDebug("文件签名完成");
        return $signature;
    }

    /**
     * 验证文件签名
     *
     * @param string $filePath 文件路径
     * @param string $signature 数字签名
     * @param string|null $signatureAlg 签名算法
     * @return bool 验证结果
     *
     * @throws InvalidArgumentException 当文件不存在时抛出
     * @throws RuntimeException 当验证失败时抛出
     *
     * 使用示例：
     * $valid = $rsa->verifyFile('document.pdf', $signature);
     * $valid = $rsa->verifyFile('file.txt', $signature, 'sha384');
     */
    public function verifyFile(string $filePath, string $signature, ?string $signatureAlg = null): bool
    {
        // 如果没有公钥，尝试从私钥导出
        if ($this->publicKey === null) {
            if ($this->privateKey === null) {
                throw new RuntimeException('需要公钥或私钥来验证文件签名');
            }
            $this->derivePublicKey();
        }

        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("文件不存在: {$filePath}");
        }

        $this->logDebug("开始验证文件签名: {$filePath}");

        $fileHash = hash_file($signatureAlg ?? $this->hashAlg, $filePath);
        if ($fileHash === false) {
            throw new RuntimeException('文件哈希计算失败');
        }

        $result = $this->verify($fileHash, $signature, $signatureAlg);
        $this->logDebug("文件签名验证: " . ($result ? '成功' : '失败'));
        return $result;
    }

    /**
     * 导出私钥
     *
     * @param string $passphrase 私钥密码（可选）
     * @param string $cipher 加密算法（默认AES-256-CBC）
     * @return string 私钥字符串（PEM格式）
     *
     * @throws RuntimeException 当私钥导出失败时抛出
     *
     * 使用示例：
     * $privateKey = $rsa->exportPrivateKey();
     * $privateKey = $rsa->exportPrivateKey('mypassword', 'AES-256-CBC');
     */
    public function exportPrivateKey(string $passphrase = '', string $cipher = 'AES-256-CBC'): string
    {
        if ($this->privateKey === null) {
            throw new RuntimeException('私钥不可用');
        }

        $export = '';
        $success = openssl_pkey_export($this->privateKey, $export, $passphrase, [
            'encrypt_key' => !empty($passphrase),
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'encrypt_key_cipher' => $cipher
        ]);

        if (!$success) {
            $error = openssl_error_string();
            // 尝试不同的导出方式
            $details = openssl_pkey_get_details($this->privateKey);
            if ($details !== false && isset($details['key'])) {
                $this->logDebug("使用备用方式导出私钥");
                return $details['key'];
            }
            throw new RuntimeException('私钥导出失败: ' . ($error ?: '未知错误'));
        }

        $this->logDebug("私钥导出成功" . (!empty($passphrase) ? " (已加密)" : ""));
        return $export;
    }

    /**
     * 导出公钥
     *
     * @return string 公钥字符串（PEM格式）
     *
     * @throws RuntimeException 当公钥导出失败时抛出
     *
     * 使用示例：
     * $publicKey = $rsa->exportPublicKey();
     */
    public function exportPublicKey(): string
    {
        if ($this->publicKey === null) {
            // 如果没有公钥但有私钥，尝试导出
            if ($this->privateKey !== null) {
                $this->derivePublicKey();
                $this->logDebug("从私钥导出公钥");
            } else {
                throw new RuntimeException('公钥不可用');
            }
        }

        $details = openssl_pkey_get_details($this->publicKey);
        if ($details === false) {
            $error = openssl_error_string();
            throw new RuntimeException('获取公钥详情失败: ' . ($error ?: '未知错误'));
        }

        if (!isset($details['key'])) {
            throw new RuntimeException('公钥格式无效');
        }

        $this->logDebug("公钥导出成功");
        return $details['key'];
    }

    /**
     * 获取密钥详情
     *
     * @return array 密钥详细信息
     *
     * @throws RuntimeException 当获取密钥详情失败时抛出
     *
     * 使用示例：
     * $details = $rsa->getKeyDetails();
     */
    public function getKeyDetails(): array
    {
        $key = $this->privateKey ?? $this->publicKey;
        if ($key === null) {
            throw new RuntimeException('密钥不可用');
        }

        $details = openssl_pkey_get_details($key);
        if ($details === false) {
            $error = openssl_error_string();
            throw new RuntimeException('获取密钥详情失败: ' . ($error ?: '未知错误'));
        }

        $this->logDebug("获取密钥详情成功");
        return $details;
    }

    /**
     * 验证密钥对是否匹配
     *
     * @return bool 验证结果
     *
     * 使用示例：
     * $valid = $rsa->verifyKeyPair();
     */
    public function verifyKeyPair(): bool
    {
        if ($this->privateKey === null || $this->publicKey === null) {
            $this->logDebug("密钥对验证失败: 缺少私钥或公钥");
            return false;
        }

        try {
            $testData = random_bytes(32);
            $signature = $this->sign($testData);
            $result = $this->verify($testData, $signature);

            $this->logDebug("密钥对验证: " . ($result ? '成功' : '失败'));
            return $result;
        } catch (Exception $e) {
            $this->logDebug("密钥对验证失败: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取支持的哈希算法列表
     *
     * @return array 支持的哈希算法
     *
     * 使用示例：
     * $algorithms = RSA::getSupportedHashAlgorithms();
     */
    public static function getSupportedHashAlgorithms(): array
    {
        return self::SUPPORTED_HASH_ALGORITHMS;
    }

    /**
     * 获取支持的密钥长度列表
     *
     * @return array 支持的密钥长度
     *
     * 使用示例：
     * $keySizes = RSA::getSupportedKeySizes();
     */
    public static function getSupportedKeySizes(): array
    {
        return self::SUPPORTED_KEY_SIZES;
    }

    /**
     * 获取支持的填充方式列表
     *
     * @return array 支持的填充方式
     *
     * 使用示例：
     * $paddings = RSA::getSupportedPaddings();
     */
    public static function getSupportedPaddings(): array
    {
        return self::SUPPORTED_PADDINGS;
    }

    /**
     * 获取当前密钥长度
     *
     * @return int 密钥长度
     */
    public function getKeySize(): int
    {
        return $this->keySize;
    }

    /**
     * 获取当前哈希算法
     *
     * @return string 哈希算法
     */
    public function getHashAlgorithm(): string
    {
        return $this->hashAlg;
    }

    /**
     * 检查是否包含私钥
     *
     * @return bool 是否包含私钥
     */
    public function hasPrivateKey(): bool
    {
        return $this->privateKey !== null;
    }

    /**
     * 检查是否包含公钥
     *
     * @return bool 是否包含公钥
     */
    public function hasPublicKey(): bool
    {
        return $this->publicKey !== null;
    }

    /**
     * 生成RSA密钥对（静态方法）
     *
     * @param int $keySize 密钥长度
     * @param string $passphrase 私钥密码（可选）
     * @param string $hashAlg 哈希算法
     * @return array 生成的密钥对
     *
     * @throws RuntimeException 当密钥生成失败时抛出
     *
     * 使用示例：
     * $keyPair = RSA::createKeyPair(2048, 'mypassword', 'sha256');
     */
    public static function createKeyPair(
        int $keySize = 2048,
        string $passphrase = '',
        string $hashAlg = 'sha256'
    ): array {
        try {
            $rsa = new self(null, null, $keySize, $hashAlg);

            return [
                'private_key' => $rsa->exportPrivateKey($passphrase),
                'public_key' => $rsa->exportPublicKey(),
                'key_size' => $keySize,
                'key_size_name' => self::SUPPORTED_KEY_SIZES[$keySize] ?? '未知长度',
                'hash_algorithm' => $hashAlg,
                'timestamp' => time()
            ];
        } catch (Exception $e) {
            throw new RuntimeException("RSA密钥对生成失败: " . $e->getMessage());
        }
    }

    /**
     * 从现有密钥创建实例
     *
     * @param string $privateKey 私钥字符串
     * @param string|null $publicKey 公钥字符串（可选）
     * @param string $hashAlg 哈希算法
     * @param string $passphrase 私钥密码（可选）
     * @return self RSA实例
     *
     * @throws RuntimeException 当密钥加载失败时抛出
     *
     * 使用示例：
     * $rsa = RSA::createFromKey($privateKey, $publicKey, 'sha256', 'mypassword');
     */
    public static function createFromKey(
        string $privateKey,
        ?string $publicKey = null,
        string $hashAlg = 'sha256',
        string $passphrase = ''
    ): self {
        $resource = openssl_pkey_get_private($privateKey, $passphrase);
        if ($resource === false) {
            // 尝试不带密码
            $resource = openssl_pkey_get_private($privateKey);
            if ($resource === false) {
                $error = openssl_error_string();
                throw new RuntimeException('无效的私钥格式或密码错误: ' . ($error ?: '未知错误'));
            }
        }

        $details = openssl_pkey_get_details($resource);
        if ($details === false || !isset($details['bits'])) {
            throw new RuntimeException('无法从私钥中提取密钥信息');
        }

        return new self($privateKey, $publicKey, $details['bits'], $hashAlg);
    }

    /**
     * 从公钥创建实例
     *
     * @param string $publicKey 公钥字符串
     * @param int $keySize 密钥长度
     * @param string $hashAlg 哈希算法
     * @return self RSA实例
     *
     * 使用示例：
     * $rsa = RSA::createFromPublicKey($publicKey, 2048, 'sha256');
     */
    public static function createFromPublicKey(
        string $publicKey,
        int $keySize = 2048,
        string $hashAlg = 'sha256'
    ): self {
        return new self(null, $publicKey, $keySize, $hashAlg);
    }

    /**
     * 加密并签名数据
     *
     * @param string $data 要加密的数据
     * @param self $recipient 接收方RSA实例
     * @param int $padding 填充方式
     * @param string|null $signatureAlg 签名算法
     * @return array 包含加密数据和签名的数据包
     *
     * @throws RuntimeException 当操作失败时抛出
     *
     * 使用示例：
     * $package = $sender->encryptAndSign('敏感数据', $recipient, OPENSSL_PKCS1_OAEP_PADDING, 'sha256');
     */
    public function encryptAndSign(
        string $data,
        self $recipient,
        int $padding = OPENSSL_PKCS1_OAEP_PADDING,
        ?string $signatureAlg = null
    ): array {
        if ($this->privateKey === null) {
            throw new RuntimeException('需要私钥来进行签名');
        }

        if ($recipient->publicKey === null) {
            throw new RuntimeException('接收方需要公钥来进行加密');
        }

        $this->logDebug("开始加密并签名数据");

        $encryptedData = $recipient->encrypt($data, $padding);
        $signature = $this->sign($data, $signatureAlg);

        $result = [
            'encrypted_data' => $encryptedData,
            'signature' => $signature,
            'timestamp' => time(),
            'algorithm' => $signatureAlg ?? $this->hashAlg,
            'key_size' => $this->keySize,
            'sender_fingerprint' => $this->getKeyFingerprint(),
            'padding' => $padding
        ];

        $this->logDebug("加密并签名完成");
        return $result;
    }

    /**
     * 解密并验证签名
     *
     * @param array $package 加密数据包
     * @param self $sender 发送方RSA实例
     * @param int $padding 填充方式
     * @param int $timeTolerance 时间容差（秒）
     * @return string 解密后的数据
     *
     * @throws InvalidArgumentException 当数据包无效时抛出
     * @throws RuntimeException 当验证失败时抛出
     *
     * 使用示例：
     * $decrypted = $recipient->decryptAndVerify($package, $sender, OPENSSL_PKCS1_OAEP_PADDING, 300);
     */
    public function decryptAndVerify(
        array $package,
        self $sender,
        int $padding = OPENSSL_PKCS1_OAEP_PADDING,
        int $timeTolerance = 300
    ): string {
        if ($this->privateKey === null) {
            throw new RuntimeException('需要私钥来进行解密');
        }

        if ($sender->publicKey === null) {
            throw new RuntimeException('发送方需要公钥来验证签名');
        }

        $this->logDebug("开始解密并验证签名");

        // 检查时间戳
        if (isset($package['timestamp'])) {
            $currentTime = time();
            if (abs($currentTime - $package['timestamp']) > $timeTolerance) {
                throw new RuntimeException('数据包已过期');
            }
        }

        $encryptedData = $package['encrypted_data'] ?? '';
        if (empty($encryptedData)) {
            throw new InvalidArgumentException('加密数据为空');
        }

        $decryptedData = $this->decrypt($encryptedData, $padding);

        $signature = $package['signature'] ?? '';
        if (empty($signature)) {
            throw new InvalidArgumentException('签名为空');
        }

        if (!$sender->verify($decryptedData, $signature)) {
            throw new RuntimeException('签名验证失败');
        }

        $this->logDebug("解密并验证签名成功");
        return $decryptedData;
    }

    /**
     * 生成密钥指纹
     *
     * @param string $algorithm 哈希算法
     * @return string 密钥指纹
     *
     * @throws RuntimeException 当指纹生成失败时抛出
     *
     * 使用示例：
     * $fingerprint = $rsa->getKeyFingerprint('sha256');
     */
    public function getKeyFingerprint(string $algorithm = 'sha256'): string
    {
        $publicKey = $this->exportPublicKey();
        $keyWithoutHeaders = preg_replace('/-----(BEGIN|END) PUBLIC KEY-----/', '', $publicKey);
        $keyWithoutHeaders = preg_replace('/\s+/', '', $keyWithoutHeaders);

        $binaryKey = base64_decode($keyWithoutHeaders);
        if ($binaryKey === false) {
            throw new RuntimeException('公钥解码失败');
        }

        $fingerprint = hash($algorithm, $binaryKey, true);

        $result = bin2hex($fingerprint);
        $this->logDebug("生成密钥指纹: {$result}");
        return $result;
    }

    /**
     * 获取加密信息摘要
     *
     * @return array 加密信息
     *
     * 使用示例：
     * $info = $rsa->getCipherInfo();
     */
    public function getCipherInfo(): array
    {
        $info = [
            'key_size' => $this->keySize,
            'hash_algorithm' => $this->hashAlg,
            'has_private_key' => $this->hasPrivateKey(),
            'has_public_key' => $this->hasPublicKey(),
            'key_pair_valid' => $this->verifyKeyPair(),
            'max_encrypt_size' => $this->getMaxEncryptBlockSize(OPENSSL_PKCS1_OAEP_PADDING),
            'supported_key_sizes' => array_keys(self::SUPPORTED_KEY_SIZES),
            'supported_algorithms' => array_keys(self::SUPPORTED_HASH_ALGORITHMS),
            'supported_paddings' => array_keys(self::SUPPORTED_PADDINGS),
            'operations_count' => $this->encryptionContext['operations_count'],
            'initialized_at' => $this->encryptionContext['initialized_at']
        ];

        $this->logDebug("获取加密信息摘要");
        return $info;
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
            'signing_operations' => $this->performanceStats['signing_operations'],
            'verification_operations' => $this->performanceStats['verification_operations'],
            'total_operations' => $this->performanceStats['encryption_operations'] +
                $this->performanceStats['decryption_operations'] +
                $this->performanceStats['signing_operations'] +
                $this->performanceStats['verification_operations'],
            'total_data_processed' => $this->performanceStats['total_data_processed'],
            'running_time_seconds' => round($runningTime, 2),
            'operations_per_second' => $runningTime > 0 ?
                round(($this->performanceStats['encryption_operations'] +
                        $this->performanceStats['decryption_operations'] +
                        $this->performanceStats['signing_operations'] +
                        $this->performanceStats['verification_operations']) / $runningTime, 2) : 0,
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
     * 导出密钥为各种格式
     *
     * @param string $format 密钥格式（PEM, PKCS12, JWK）
     * @param string $passphrase 密码（可选）
     * @return array 导出的密钥
     *
     * @throws InvalidArgumentException 当格式不支持时抛出
     *
     * 使用示例：
     * $keys = $rsa->exportKey('PEM', 'mypassword');
     * $keys = $rsa->exportKey('JWK');
     */
    public function exportKey(string $format = 'PEM', string $passphrase = ''): array
    {
        $result = [];

        switch ($format) {
            case 'PEM':
                if ($this->privateKey !== null) {
                    $result['private_key'] = $this->exportPrivateKey($passphrase);
                }
                if ($this->publicKey !== null) {
                    $result['public_key'] = $this->exportPublicKey();
                }
                break;

            case 'PKCS12':
                if ($this->privateKey !== null) {
                    // 模拟PKCS12导出 - 实际应使用openssl_pkcs12_export
                    $result['private_key'] = $this->exportPrivateKey($passphrase);
                    $result['certificate'] = $this->generateSelfSignedCertificate();
                }
                break;

            case 'JWK':
                $result = $this->exportAsJWK();
                break;

            default:
                throw new InvalidArgumentException("不支持的密钥格式: {$format}");
        }

        $this->logDebug("导出密钥格式: {$format}");
        return $result;
    }

    /**
     * 导出为JWK格式
     *
     * @return array JWK格式的密钥
     */
    public function exportAsJWK(): array
    {
        $details = $this->getKeyDetails();
        $rsaDetails = $details['rsa'];

        $jwk = [
            'kty' => 'RSA',
            'n' => rtrim(strtr(base64_encode($rsaDetails['n']), '+/', '-_'), '='),
            'e' => rtrim(strtr(base64_encode($rsaDetails['e']), '+/', '-_'), '='),
            'alg' => 'RS' . substr($this->hashAlg, -3),
            'key_ops' => []
        ];

        if ($this->hasPublicKey()) {
            $jwk['key_ops'][] = 'verify';
        }

        if ($this->hasPrivateKey()) {
            $jwk['key_ops'][] = 'sign';

            // 添加私钥参数（如果可用）
            if (isset($rsaDetails['d'])) {
                $jwk['d'] = rtrim(strtr(base64_encode($rsaDetails['d']), '+/', '-_'), '=');
            }
            if (isset($rsaDetails['p'])) {
                $jwk['p'] = rtrim(strtr(base64_encode($rsaDetails['p']), '+/', '-_'), '=');
            }
            if (isset($rsaDetails['q'])) {
                $jwk['q'] = rtrim(strtr(base64_encode($rsaDetails['q']), '+/', '-_'), '=');
            }
        }

        return $jwk;
    }

    /**
     * 生成自签名证书
     *
     * @param array $dn 主题信息
     * @param int $days 有效期（天）
     * @return string 证书字符串
     */
    private function generateSelfSignedCertificate(array $dn = [], int $days = 365): string
    {
        $defaultDN = [
            "countryName" => "CN",
            "stateOrProvinceName" => "State",
            "localityName" => "City",
            "organizationName" => "Organization",
            "organizationalUnitName" => "Unit",
            "commonName" => "Common Name",
            "emailAddress" => "email@example.com"
        ];

        $dn = array_merge($defaultDN, $dn);

        $cert = openssl_csr_new($dn, $this->privateKey, ['digest_alg' => $this->hashAlg]);
        $cert = openssl_csr_sign($cert, null, $this->privateKey, $days, ['digest_alg' => $this->hashAlg]);

        openssl_x509_export($cert, $certOut);
        return $certOut;
    }

    /**
     * 验证证书
     *
     * @param string $certificate 证书字符串
     * @return bool 验证结果
     *
     * 使用示例：
     * $valid = $rsa->verifyCertificate($certificate);
     */
    public function verifyCertificate(string $certificate): bool
    {
        $cert = openssl_x509_read($certificate);
        if ($cert === false) {
            $this->logDebug("证书验证失败: 无法读取证书");
            return false;
        }

        $publicKey = openssl_pkey_get_public($cert);
        if ($publicKey === false) {
            $this->logDebug("证书验证失败: 无法获取公钥");
            return false;
        }

        $result = openssl_x509_verify($cert, $publicKey) === 1;
        $this->logDebug("证书验证: " . ($result ? '成功' : '失败'));
        return $result;
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
     * $results = $rsa->encryptFiles(['file1.txt', 'file2.txt'], '/output', 65536, true, function($file, $progress) {
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
        $this->logDebug("开始批量RSA加密 {$totalFiles} 个文件");

        foreach ($files as $index => $inputFile) {
            try {
                if (!file_exists($inputFile)) {
                    $results[$inputFile] = ['success' => false, 'error' => '文件不存在'];
                    continue;
                }

                $outputFile = $outputDir . '/' . basename($inputFile) . '.rsa_encrypted';

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

                $this->logDebug("文件 {$inputFile} RSA加密 " . ($success ? '成功' : '失败'));
            } catch (Exception $e) {
                $results[$inputFile] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
                $this->logDebug("文件 {$inputFile} RSA加密失败: " . $e->getMessage());
            }
        }

        // 最终进度回调
        if ($progressCallback !== null) {
            $progressCallback('完成', 100);
        }

        $successCount = count(array_filter($results, function($r) { return $r['success']; }));
        $this->logDebug("批量RSA加密完成: {$successCount}/{$totalFiles} 成功");
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
     * $results = $rsa->decryptFiles(['file1.encrypted', 'file2.encrypted'], '/output', 65536, function($file, $progress) {
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
        $this->logDebug("开始批量RSA解密 {$totalFiles} 个文件");

        foreach ($files as $index => $inputFile) {
            try {
                if (!file_exists($inputFile)) {
                    $results[$inputFile] = ['success' => false, 'error' => '文件不存在'];
                    continue;
                }

                $outputFile = $outputDir . '/' . basename($inputFile, '.rsa_encrypted');

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

                $this->logDebug("文件 {$inputFile} RSA解密 " . ($success ? '成功' : '失败'));
            } catch (Exception $e) {
                $results[$inputFile] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
                $this->logDebug("文件 {$inputFile} RSA解密失败: " . $e->getMessage());
            }
        }

        // 最终进度回调
        if ($progressCallback !== null) {
            $progressCallback('完成', 100);
        }

        $successCount = count(array_filter($results, function($r) { return $r['success']; }));
        $this->logDebug("批量RSA解密完成: {$successCount}/{$totalFiles} 成功");
        return $results;
    }

    /**
     * 密钥对强度测试
     *
     * @param int $iterations 测试迭代次数
     * @return array 强度测试结果
     *
     * 使用示例：
     * $strength = $rsa->testKeyStrength(100);
     */
    public function testKeyStrength(int $iterations = 50): array
    {
        $startTime = microtime(true);

        $this->logDebug("开始RSA密钥强度测试");

        // 测试加密性能
        $testData = random_bytes(1024);
        $encryptTime = 0;
        $decryptTime = 0;
        $signTime = 0;
        $verifyTime = 0;

        for ($i = 0; $i < $iterations; $i++) {
            // 测试加密解密
            $encryptStart = microtime(true);
            $encrypted = $this->encrypt($testData);
            $encryptTime += microtime(true) - $encryptStart;

            $decryptStart = microtime(true);
            $this->decrypt($encrypted);
            $decryptTime += microtime(true) - $decryptStart;

            // 测试签名验证
            $signStart = microtime(true);
            $signature = $this->sign($testData);
            $signTime += microtime(true) - $signStart;

            $verifyStart = microtime(true);
            $this->verify($testData, $signature);
            $verifyTime += microtime(true) - $verifyStart;
        }

        $totalTime = microtime(true) - $startTime;

        $result = [
            'key_size' => $this->keySize,
            'iterations' => $iterations,
            'encrypt_speed' => round(1024 / ($encryptTime / $iterations), 2) . ' KB/s',
            'decrypt_speed' => round(1024 / ($decryptTime / $iterations), 2) . ' KB/s',
            'sign_speed' => round($iterations / $signTime, 2) . ' 签名/秒',
            'verify_speed' => round($iterations / $verifyTime, 2) . ' 验证/秒',
            'total_time' => round($totalTime, 4) . ' seconds',
            'performance_rating' => $this->getPerformanceRating()
        ];

        $this->logDebug("RSA密钥强度测试完成: " . $result['performance_rating']);
        return $result;
    }

    /**
     * 获取性能评级
     *
     * @return string 性能评级
     * @access private
     */
    private function getPerformanceRating(): string
    {
        $ratings = [
            2048 => '高性能',
            3072 => '平衡',
            4096 => '高安全',
            8192 => '极高安全'
        ];

        return $ratings[$this->keySize] ?? '未知';
    }

    /**
     * 更改哈希算法
     *
     * @param string $newHashAlg 新哈希算法
     * @return void
     *
     * @throws InvalidArgumentException 当算法不支持时抛出
     *
     * 使用示例：
     * $rsa->changeHashAlgorithm('sha512');
     */
    public function changeHashAlgorithm(string $newHashAlg): void
    {
        $this->validateHashAlgorithm($newHashAlg);
        $this->hashAlg = $newHashAlg;
        $this->initializeContext();
        $this->logDebug("哈希算法已更改为: {$newHashAlg}");
    }

    /**
     * 启用/禁用调试模式
     *
     * @param bool $enabled 是否启用调试模式
     * @return void
     *
     * 使用示例：
     * $rsa->setDebugMode(true);
     */
    public function setDebugMode(bool $enabled): void
    {
        $this->debugMode = $enabled;
        $this->logDebug("调试模式 " . ($enabled ? '启用' : '禁用'));
    }

    /**
     * 获取密钥使用统计
     *
     * @return array 密钥使用统计
     *
     * 使用示例：
     * $stats = $rsa->getKeyUsageStats();
     */
    public function getKeyUsageStats(): array
    {
        return [
            'key_size' => $this->keySize,
            'hash_algorithm' => $this->hashAlg,
            'has_private_key' => $this->hasPrivateKey(),
            'has_public_key' => $this->hasPublicKey(),
            'key_pair_valid' => $this->verifyKeyPair(),
            'max_data_size' => $this->getMaxEncryptBlockSize(OPENSSL_PKCS1_OAEP_PADDING),
            'supported_operations' => [
                'encryption' => $this->hasPublicKey(),
                'decryption' => $this->hasPrivateKey(),
                'signing' => $this->hasPrivateKey(),
                'verification' => $this->hasPublicKey() || $this->hasPrivateKey(),
                'key_export' => true,
                'certificate_operations' => $this->hasPrivateKey()
            ],
            'performance_stats' => $this->getPerformanceStats()
        ];
    }

    /**
     * 安全擦除敏感数据
     *
     * @return void
     *
     * 使用示例：
     * $rsa->secureWipe();
     */
    public function secureWipe(): void
    {
        $this->cleanup();
        $this->logDebug("RSA敏感数据已安全擦除");
    }

    /**
     * 导出加密配置
     *
     * @return array 加密配置
     *
     * 使用示例：
     * $config = $rsa->exportConfig();
     */
    public function exportConfig(): array
    {
        return [
            'key_size' => $this->keySize,
            'hash_algorithm' => $this->hashAlg,
            'has_private_key' => $this->hasPrivateKey(),
            'has_public_key' => $this->hasPublicKey(),
            'public_key' => $this->hasPublicKey() ? base64_encode($this->exportPublicKey()) : null,
            'key_fingerprint' => $this->getKeyFingerprint(),
            'key_pair_valid' => $this->verifyKeyPair()
        ];
    }

    /**
     * 从配置导入
     *
     * @param array $config 加密配置
     * @return static 新的RSA实例
     *
     * @throws InvalidArgumentException 当配置无效时抛出
     *
     * 使用示例：
     * $newRsa = RSA::fromConfig($config);
     */
    public static function fromConfig(array $config): self
    {
        $required = ['key_size', 'hash_algorithm'];
        foreach ($required as $field) {
            if (!isset($config[$field])) {
                throw new InvalidArgumentException("配置缺少必要字段: {$field}");
            }
        }

        $publicKey = null;
        if (isset($config['public_key'])) {
            $publicKey = base64_decode($config['public_key']);
            if ($publicKey === false) {
                throw new InvalidArgumentException('配置中的公钥Base64解码失败');
            }
        }

        return new self(null, $publicKey, $config['key_size'], $config['hash_algorithm']);
    }
}
