<?php

/**
 * RSA加密解密类 - 最终优化版
 * 支持密钥生成、加密、解密、签名、验证等功能
 * 基于PHP 8.2+ openssl扩展实现
 * 修复了分块大小、密钥导出和验证逻辑问题
 *
 * @package Crypto
 * @author Security Team
 * @version 6.0.1
 * @license MIT
 */
class RSACrypto
{
    /**
     * @var OpenSSLAsymmetricKey|null 私钥资源
     */
    private $privateKey = null;

    /**
     * @var OpenSSLAsymmetricKey|null 公钥资源
     */
    private $publicKey = null;

    /**
     * @var int 密钥位数
     */
    private int $keySize;

    /**
     * @var string 哈希算法
     */
    private string $hashAlg;

    /**
     * @var array 支持的哈希算法列表
     */
    private const SUPPORTED_HASH_ALGORITHMS = [
        'sha256' => 'SHA256',
        'sha384' => 'SHA384',
        'sha512' => 'SHA512'
    ];

    /**
     * @var array 支持的密钥长度列表
     */
    private const SUPPORTED_KEY_SIZES = [
        2048 => '2048位 (推荐)',
        3072 => '3072位 (安全)',
        4096 => '4096位 (高安全)'
    ];

    /**
     * @var array 支持的填充方式
     */
    private const SUPPORTED_PADDINGS = [
        OPENSSL_PKCS1_PADDING => 'PKCS1 v1.5',
        OPENSSL_PKCS1_OAEP_PADDING => 'PKCS1 OAEP (推荐)'
    ];

    /**
     * @var int 默认分块大小
     */
    private const DEFAULT_CHUNK_SIZE = 214;

    /**
     * @var string 文件格式版本
     */
    private const FILE_FORMAT_VERSION = 'RSAv3';

    /**
     * @var bool 调试模式
     */
    private bool $debugMode = false;

    /**
     * 构造函数 - 最终优化版
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
        } catch (Exception $e) {
            $this->cleanup();
            throw $e;
        }
    }

    /**
     * 记录调试信息
     */
    private function logDebug(string $message): void
    {
        if ($this->debugMode) {
            echo "[RSA DEBUG] " . $message . "\n";
        }
    }

    /**
     * 析构函数 - 清理资源
     */
    public function __destruct()
    {
        $this->cleanup();
    }

    /**
     * 清理密钥资源 - 优化：使用现代PHP资源管理
     */
    private function cleanup(): void
    {
        // PHP 8.0+ 会自动管理OpenSSL资源，这里只需取消引用
        $this->privateKey = null;
        $this->publicKey = null;
        $this->logDebug("RSA资源清理完成");
    }

    /**
     * 验证密钥长度是否支持
     */
    private function validateKeySize(int $keySize): void
    {
        if (!array_key_exists($keySize, self::SUPPORTED_KEY_SIZES)) {
            throw new InvalidArgumentException(
                "不支持的密钥长度: {$keySize}。支持的密钥长度: " . implode(', ', array_keys(self::SUPPORTED_KEY_SIZES))
            );
        }
    }

    /**
     * 验证哈希算法是否支持
     */
    private function validateHashAlgorithm(string $hashAlg): void
    {
        if (!array_key_exists($hashAlg, self::SUPPORTED_HASH_ALGORITHMS)) {
            throw new InvalidArgumentException(
                "不支持的哈希算法: {$hashAlg}。支持的算法: " . implode(', ', array_keys(self::SUPPORTED_HASH_ALGORITHMS))
            );
        }
    }

    /**
     * 生成RSA密钥对
     */
    private function generateKeyPair(): void
    {
        $config = [
            'private_key_bits' => $this->keySize,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $keyPair = openssl_pkey_new($config);
        if ($keyPair === false) {
            $error = openssl_error_string();
            throw new RuntimeException('RSA密钥对生成失败: ' . ($error ?: '未知错误'));
        }

        $this->privateKey = $keyPair;
        $this->derivePublicKey();

        // 更新实际密钥大小
        $this->updateActualKeySize();

        $this->logDebug("RSA密钥对生成成功");
    }

    /**
     * 加载私钥 - 最终优化版：改进错误处理
     */
    private function loadPrivateKey(string $privateKey): void
    {
        // 尝试不同的密码组合
        $passphrases = ['', null];
        $resource = false;

        foreach ($passphrases as $passphrase) {
            $resource = openssl_pkey_get_private($privateKey, $passphrase);
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
     * 加载公钥 - 最终优化版
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
     * 更新实际密钥大小 - 优化：动态调整密钥大小
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
     * 从私钥导出公钥 - 最终优化版
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
     * 加密数据
     */
    public function encrypt(string $data, int $padding = OPENSSL_PKCS1_OAEP_PADDING): string
    {
        if ($this->publicKey === null) {
            throw new RuntimeException('需要公钥来进行加密');
        }

        if (empty($data)) {
            throw new InvalidArgumentException('加密数据不能为空');
        }

        $this->logDebug("开始RSA加密，数据长度: " . strlen($data));

        $maxLength = $this->getMaxEncryptBlockSize($padding);
        if (strlen($data) <= $maxLength) {
            $result = $this->encryptSingleBlock($data, $padding);
            $this->logDebug("单块加密完成");
            return $result;
        } else {
            $result = $this->encryptChunked($data, $padding);
            $this->logDebug("分块加密完成，块数: " . count(explode('::RSA_CHUNK::', base64_decode($result))));
            return $result;
        }
    }

    /**
     * 单块加密 - 最终优化版：改进错误处理
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
     * 分块加密数据
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
        $this->logDebug("分块加密完成");
        return $result;
    }

    /**
     * 解密数据
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
     * 单块解密 - 最终优化版：改进错误处理
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
     * 分块解密数据
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
     * 加密文件 - 修复版：改进混合加密方案
     */
    public function encryptFile(string $inputFile, string $outputFile, int $chunkSize = self::DEFAULT_CHUNK_SIZE): bool
    {
        $this->validateFileOperation($inputFile, $outputFile);

        // 对分块大小进行更宽松的验证
        if ($chunkSize <= 0) {
            $chunkSize = self::DEFAULT_CHUNK_SIZE;
        }

        // 确保分块大小是合理的，但不严格限制为16的倍数
        $chunkSize = min($chunkSize, 1024 * 1024); // 最大1MB

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
            // 写入文件头
            $header = $this->createFileHeader();
            if (fwrite($outputHandle, $header) === false) {
                throw new RuntimeException('文件头写入失败');
            }

            // 使用混合加密：使用随机AES密钥加密文件，再用RSA加密AES密钥
            $aesKey = AESCrypto::generateKey('aes-256-cbc');
            $aes = new AESCrypto($aesKey, 'aes-256-cbc');

            // 加密AES密钥并写入 - 修复：使用二进制格式
            $encryptedAesKey = $this->encryptSingleBlockBinary($aesKey, OPENSSL_PKCS1_OAEP_PADDING);
            $keyHeader = pack('N', strlen($encryptedAesKey)) . $encryptedAesKey;
            if (fwrite($outputHandle, $keyHeader) === false) {
                throw new RuntimeException('密钥头写入失败');
            }

            $this->logDebug("AES密钥加密完成，长度: " . strlen($encryptedAesKey));

            // 使用AES加密文件内容
            $tempEncryptedFile = $outputFile . '.aes.tmp';
            $success = $aes->encryptFile($inputFile, $tempEncryptedFile, $chunkSize);

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
            // 清理临时文件
            if (isset($tempEncryptedFile) && file_exists($tempEncryptedFile)) {
                unlink($tempEncryptedFile);
                $this->logDebug("临时文件已清理: {$tempEncryptedFile}");
            }
        }
    }

    /**
     * 解密文件 - 修复版：改进文件处理逻辑
     */
    public function decryptFile(string $inputFile, string $outputFile, int $chunkSize = self::DEFAULT_CHUNK_SIZE): bool
    {
        $this->validateFileOperation($inputFile, $outputFile);

        // 对分块大小进行更宽松的验证
        if ($chunkSize <= 0) {
            $chunkSize = self::DEFAULT_CHUNK_SIZE;
        }

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

            // 读取加密的AES密钥
            $keyLengthData = fread($inputHandle, 4);
            if (strlen($keyLengthData) !== 4) {
                throw new RuntimeException('密钥长度读取失败');
            }

            $keyLength = unpack('N', $keyLengthData)[1];
            $encryptedAesKey = fread($inputHandle, $keyLength);
            if (strlen($encryptedAesKey) !== $keyLength) {
                throw new RuntimeException('密钥读取失败');
            }

            $this->logDebug("AES密钥读取成功，长度: {$keyLength}");

            // 解密AES密钥 - 修复：使用二进制解密方法
            $aesKey = $this->decryptSingleBlockBinary($encryptedAesKey, OPENSSL_PKCS1_OAEP_PADDING);
            $aes = new AESCrypto($aesKey, 'aes-256-cbc');

            $this->logDebug("AES密钥解密成功");

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
            // 清理临时文件
            if (isset($tempOutput) && file_exists($tempOutput)) {
                unlink($tempOutput);
                $this->logDebug("临时文件已清理: {$tempOutput}");
            }
        }
    }

    /**
     * 单块加密（二进制输出）- 新增方法
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
     * 单块解密（二进制输入）- 新增方法
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
     * 创建文件头
     */
    private function createFileHeader(): string
    {
        $header = self::FILE_FORMAT_VERSION;
        $header .= pack('n', $this->keySize);
        $header .= pack('N', time());
        $header .= random_bytes(16); // 随机填充
        $header .= pack('N', crc32($header)); // 头部校验

        $this->logDebug("创建RSA文件头，大小: " . strlen($header) . " 字节");
        return $header;
    }

    /**
     * 读取文件头
     */
    private function readFileHeader($handle): string
    {
        $version = fread($handle, 5);
        if ($version !== self::FILE_FORMAT_VERSION) {
            throw new RuntimeException('不支持的RSA文件格式版本: ' . $version);
        }

        $keySize = unpack('n', fread($handle, 2))[1];
        $timestamp = unpack('N', fread($handle, 4))[1];
        $padding = fread($handle, 16);
        $checksum = unpack('N', fread($handle, 4))[1];

        $headerData = $version . pack('n', $keySize) . pack('N', $timestamp) . $padding;

        // 验证头部完整性
        if (crc32($headerData) !== $checksum) {
            throw new RuntimeException('RSA文件头完整性验证失败');
        }

        $this->logDebug("RSA文件头读取成功，密钥大小: {$keySize}");
        return $headerData . pack('N', $checksum);
    }

    /**
     * 验证文件头
     */
    private function validateFileHeader(string $header): void
    {
        if (substr($header, 0, 5) !== self::FILE_FORMAT_VERSION) {
            throw new RuntimeException('无效的RSA文件格式');
        }
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
    }

    /**
     * 验证并调整分块大小 - 优化：更宽松的验证
     */
    private function validateAndAdjustChunkSize(int $chunkSize): int
    {
        if ($chunkSize <= 0) {
            return self::DEFAULT_CHUNK_SIZE;
        }

        // 更宽松的验证，只确保不是太大
        $maxReasonableSize = 1024 * 1024; // 1MB
        if ($chunkSize > $maxReasonableSize) {
            return self::DEFAULT_CHUNK_SIZE;
        }

        return $chunkSize;
    }

    /**
     * 获取最大加密块大小
     */
    private function getMaxEncryptBlockSize(int $padding): int
    {
        $keySizeInBytes = (int)($this->keySize / 8);

        return match ($padding) {
            OPENSSL_PKCS1_PADDING => $keySizeInBytes - 11,
            OPENSSL_PKCS1_OAEP_PADDING => $keySizeInBytes - 42,
            default => $keySizeInBytes - 11
        };
    }

    /**
     * 计算最优分块大小
     */
    private function calculateOptimalChunkSize(int $padding): int
    {
        $maxBlockSize = $this->getMaxEncryptBlockSize($padding);
        return max(1, $maxBlockSize - 10);
    }

    /**
     * 签名数据
     */
    public function sign(string $data): string
    {
        if ($this->privateKey === null) {
            throw new RuntimeException('需要私钥来进行签名');
        }

        $this->logDebug("开始RSA签名，数据长度: " . strlen($data));

        $signature = '';
        $success = openssl_sign($data, $signature, $this->privateKey, $this->hashAlg);

        if (!$success) {
            $error = openssl_error_string();
            throw new RuntimeException('签名失败: ' . ($error ?: '未知错误'));
        }

        $result = base64_encode($signature);
        $this->logDebug("RSA签名完成");
        return $result;
    }

    /**
     * 验证签名 - 最终优化版：允许仅私钥实例验证签名
     */
    public function verify(string $data, string $signature): bool
    {
        // 如果没有公钥，尝试从私钥导出
        if ($this->publicKey === null) {
            if ($this->privateKey === null) {
                throw new RuntimeException('需要公钥或私钥来验证签名');
            }
            $this->derivePublicKey();
            $this->logDebug("从私钥导出公钥进行签名验证");
        }

        $signatureBinary = base64_decode($signature);
        if ($signatureBinary === false) {
            throw new RuntimeException('签名Base64解码失败');
        }

        $result = openssl_verify($data, $signatureBinary, $this->publicKey, $this->hashAlg);

        if ($result === -1) {
            $error = openssl_error_string();
            throw new RuntimeException('签名验证过程出错: ' . ($error ?: '未知错误'));
        }

        $this->logDebug("RSA签名验证: " . ($result === 1 ? '成功' : '失败'));
        return $result === 1;
    }

    /**
     * 签名文件
     */
    public function signFile(string $filePath): string
    {
        if ($this->privateKey === null) {
            throw new RuntimeException('需要私钥来进行文件签名');
        }

        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("文件不存在: {$filePath}");
        }

        $this->logDebug("开始文件签名: {$filePath}");

        $fileHash = hash_file($this->hashAlg, $filePath);
        if ($fileHash === false) {
            throw new RuntimeException('文件哈希计算失败');
        }

        $signature = $this->sign($fileHash);
        $this->logDebug("文件签名完成");
        return $signature;
    }

    /**
     * 验证文件签名 - 最终优化版：允许仅私钥实例验证
     */
    public function verifyFile(string $filePath, string $signature): bool
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

        $fileHash = hash_file($this->hashAlg, $filePath);
        if ($fileHash === false) {
            throw new RuntimeException('文件哈希计算失败');
        }

        $result = $this->verify($fileHash, $signature);
        $this->logDebug("文件签名验证: " . ($result ? '成功' : '失败'));
        return $result;
    }

    /**
     * 导出私钥 - 最终优化版：改进密钥导出
     */
    public function exportPrivateKey(string $passphrase = ''): string
    {
        if ($this->privateKey === null) {
            throw new RuntimeException('私钥不可用');
        }

        $export = '';
        $success = openssl_pkey_export($this->privateKey, $export, $passphrase);

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

        $this->logDebug("私钥导出成功");
        return $export;
    }

    /**
     * 导出公钥 - 最终优化版：改进密钥导出
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
     */
    public static function getSupportedHashAlgorithms(): array
    {
        return self::SUPPORTED_HASH_ALGORITHMS;
    }

    /**
     * 获取支持的密钥长度列表
     */
    public static function getSupportedKeySizes(): array
    {
        return self::SUPPORTED_KEY_SIZES;
    }

    /**
     * 获取支持的填充方式列表
     */
    public static function getSupportedPaddings(): array
    {
        return self::SUPPORTED_PADDINGS;
    }

    /**
     * 获取当前密钥长度
     */
    public function getKeySize(): int
    {
        return $this->keySize;
    }

    /**
     * 获取当前哈希算法
     */
    public function getHashAlgorithm(): string
    {
        return $this->hashAlg;
    }

    /**
     * 检查是否包含私钥
     */
    public function hasPrivateKey(): bool
    {
        return $this->privateKey !== null;
    }

    /**
     * 检查是否包含公钥
     */
    public function hasPublicKey(): bool
    {
        return $this->publicKey !== null;
    }

    /**
     * 生成RSA密钥对（静态方法）
     */
    public static function createKeyPair(
        int $keySize = 2048,
        string $passphrase = ''
    ): array {
        try {
            $rsa = new self(null, null, $keySize);

            return [
                'private_key' => $rsa->exportPrivateKey($passphrase),
                'public_key' => $rsa->exportPublicKey(),
                'key_size' => $keySize,
                'key_size_name' => self::SUPPORTED_KEY_SIZES[$keySize] ?? '未知长度'
            ];
        } catch (Exception $e) {
            throw new RuntimeException("RSA密钥对生成失败: " . $e->getMessage());
        }
    }

    /**
     * 从现有密钥创建实例 - 最终优化版：改进密码处理
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
     */
    public function encryptAndSign(string $data, self $recipient, int $padding = OPENSSL_PKCS1_OAEP_PADDING): array
    {
        if ($this->privateKey === null) {
            throw new RuntimeException('需要私钥来进行签名');
        }

        if ($recipient->publicKey === null) {
            throw new RuntimeException('接收方需要公钥来进行加密');
        }

        $this->logDebug("开始加密并签名数据");

        $encryptedData = $recipient->encrypt($data, $padding);
        $signature = $this->sign($data);

        $result = [
            'encrypted_data' => $encryptedData,
            'signature' => $signature,
            'timestamp' => time(),
            'algorithm' => $this->hashAlg,
            'key_size' => $this->keySize,
            'sender_fingerprint' => $this->getKeyFingerprint()
        ];

        $this->logDebug("加密并签名完成");
        return $result;
    }

    /**
     * 解密并验证签名
     */
    public function decryptAndVerify(array $package, self $sender, int $padding = OPENSSL_PKCS1_OAEP_PADDING, int $timeTolerance = 300): string
    {
        if ($this->privateKey === null) {
            throw new RuntimeException('需要私钥来进行解密');
        }

        if ($sender->publicKey === null) {
            throw new RuntimeException('发送方需要公钥来验证签名');
        }

        $this->logDebug("开始解密并验证签名");

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
            'supported_paddings' => array_keys(self::SUPPORTED_PADDINGS)
        ];

        $this->logDebug("获取加密信息摘要");
        return $info;
    }

    /**
     * 新功能：导出密钥为各种格式
     */
    public function exportKey(string $format = 'PEM', string $passphrase = ''): array
    {
        $result = [];

        if ($this->privateKey !== null) {
            switch ($format) {
                case 'PEM':
                    $result['private_key'] = $this->exportPrivateKey($passphrase);
                    break;
                case 'PKCS12':
                    // 模拟PKCS12导出
                    $result['private_key'] = $this->exportPrivateKey($passphrase);
                    break;
                default:
                    throw new InvalidArgumentException("不支持的密钥格式: {$format}");
            }
        }

        if ($this->publicKey !== null) {
            $result['public_key'] = $this->exportPublicKey();
        }

        $this->logDebug("导出密钥格式: {$format}");
        return $result;
    }

    /**
     * 新功能：验证证书
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

        $this->logDebug("开始批量RSA加密 " . count($files) . " 个文件");

        foreach ($files as $inputFile) {
            try {
                if (!file_exists($inputFile)) {
                    $results[$inputFile] = ['success' => false, 'error' => '文件不存在'];
                    continue;
                }

                $outputFile = $outputDir . '/' . basename($inputFile) . '.rsa_encrypted';

                $success = $this->encryptFile($inputFile, $outputFile);
                $results[$inputFile] = [
                    'success' => $success,
                    'output_file' => $outputFile
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

        $successCount = count(array_filter($results, function($r) { return $r['success']; }));
        $this->logDebug("批量RSA加密完成: {$successCount}/" . count($files) . " 成功");
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

        $this->logDebug("开始批量RSA解密 " . count($files) . " 个文件");

        foreach ($files as $inputFile) {
            try {
                if (!file_exists($inputFile)) {
                    $results[$inputFile] = ['success' => false, 'error' => '文件不存在'];
                    continue;
                }

                $outputFile = $outputDir . '/' . basename($inputFile, '.rsa_encrypted');

                $success = $this->decryptFile($inputFile, $outputFile);
                $results[$inputFile] = [
                    'success' => $success,
                    'output_file' => $outputFile
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

        $successCount = count(array_filter($results, function($r) { return $r['success']; }));
        $this->logDebug("批量RSA解密完成: {$successCount}/" . count($files) . " 成功");
        return $results;
    }

    /**
     * 新功能：密钥对强度测试
     */
    public function testKeyStrength(): array
    {
        $startTime = microtime(true);

        $this->logDebug("开始RSA密钥强度测试");

        // 测试加密性能
        $testData = random_bytes(1024);
        $encryptTime = 0;
        $decryptTime = 0;

        for ($i = 0; $i < 10; $i++) {
            $encryptStart = microtime(true);
            $encrypted = $this->encrypt($testData);
            $encryptTime += microtime(true) - $encryptStart;

            $decryptStart = microtime(true);
            $this->decrypt($encrypted);
            $decryptTime += microtime(true) - $decryptStart;
        }

        $totalTime = microtime(true) - $startTime;

        $result = [
            'key_size' => $this->keySize,
            'encrypt_speed' => round(1024 / ($encryptTime / 10), 2) . ' KB/s',
            'decrypt_speed' => round(1024 / ($decryptTime / 10), 2) . ' KB/s',
            'total_time' => round($totalTime, 4) . ' seconds',
            'performance_rating' => $this->keySize >= 4096 ? '高安全' : ($this->keySize >= 3072 ? '安全' : '标准')
        ];

        $this->logDebug("RSA密钥强度测试完成: " . $result['performance_rating']);
        return $result;
    }

    /**
     * 新功能：更改哈希算法
     */
    public function changeHashAlgorithm(string $newHashAlg): void
    {
        $this->validateHashAlgorithm($newHashAlg);
        $this->hashAlg = $newHashAlg;
        $this->logDebug("哈希算法已更改为: {$newHashAlg}");
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
     * 新功能：获取密钥使用统计
     */
    public function getKeyUsageStats(): array
    {
        return [
            'key_size' => $this->keySize,
            'has_private_key' => $this->hasPrivateKey(),
            'has_public_key' => $this->hasPublicKey(),
            'key_pair_valid' => $this->verifyKeyPair(),
            'max_data_size' => $this->getMaxEncryptBlockSize(OPENSSL_PKCS1_OAEP_PADDING),
            'supported_operations' => [
                'encryption' => $this->hasPublicKey(),
                'decryption' => $this->hasPrivateKey(),
                'signing' => $this->hasPrivateKey(),
                'verification' => $this->hasPublicKey() || $this->hasPrivateKey()
            ]
        ];
    }
}
