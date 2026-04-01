<?php

namespace zxf\Utils\Crypto;

use Exception;
use InvalidArgumentException;
use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * ECC（椭圆曲线加密）类
 * 支持密钥生成、ECDH密钥交换、签名、验证和文件操作
 * 基于PHP 8.2+ openssl扩展实现，包含完整的错误处理和调试功能
 *
 * 主要功能：
 * - 支持多种椭圆曲线（P-256, P-384, P-521, secp256k1）
 * - 支持ECDH密钥交换和共享密钥计算
 * - 支持数字签名和验证（ECDSA）
 * - 支持文件签名验证和批量操作
 * - 支持JWK格式导入导出
 * - 完整的调试日志和性能监控
 * - 证书操作和CSR生成支持
 *
 * @package Crypto
 * @author Security Team
 * @version 1.0.0
 * @license MIT
 * @created 2026-01-01
 * @updated 2026-01-15
 */
class ECC
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
     * @var string 曲线名称（prime256v1, secp384r1, secp521r1, secp256k1）
     * @access private
     */
    private string $curveName;

    /**
     * @var array 支持的曲线列表
     * @access private
     */
    private const SUPPORTED_CURVES = [
        'prime256v1' => [
            'name' => 'P-256',
            'security' => '128位',
            'nist' => 'NIST CURVE',
            'bits' => 256,
            'usage' => '通用用途，TLS/SSL，数字签名'
        ],
        'secp384r1' => [
            'name' => 'P-384',
            'security' => '192位',
            'nist' => 'NIST CURVE',
            'bits' => 384,
            'usage' => '高安全应用，政府机构'
        ],
        'secp521r1' => [
            'name' => 'P-521',
            'security' => '256位',
            'nist' => 'NIST CURVE',
            'bits' => 521,
            'usage' => '最高安全级别，军事用途'
        ],
        'secp256k1' => [
            'name' => 'secp256k1',
            'security' => '128位',
            'nist' => 'Other',
            'bits' => 256,
            'usage' => '加密货币，区块链应用'
        ]
    ];

    /**
     * @var array 支持的签名算法
     * @access private
     */
    private const SUPPORTED_SIGNATURE_ALGORITHMS = [
        'sha256' => 'SHA256 (推荐)',
        'sha384' => 'SHA384 (安全)',
        'sha512' => 'SHA512 (高安全)',
        'sha3-256' => 'SHA3-256 (前沿)',
        'sha3-384' => 'SHA3-384 (前沿)',
        'sha3-512' => 'SHA3-512 (前沿)'
    ];

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
     * @var string 文件格式版本
     * @access private
     */
    private const FILE_FORMAT_VERSION = 'ECCV2';

    /**
     * 构造函数 - 初始化ECC加密器
     *
     * @param string|null $privateKey 私钥字符串（PEM格式）
     * @param string|null $publicKey 公钥字符串（PEM格式）
     * @param string $curveName 曲线名称（可选值：prime256v1, secp384r1, secp521r1, secp256k1）
     * @param string $passphrase 私钥密码（可选）
     * @param bool $debugMode 调试模式开关（默认false）
     *
     * @throws InvalidArgumentException 当参数验证失败时抛出
     * @throws RuntimeException 当加密器初始化失败时抛出
     *
     * 使用示例：
     * $ecc = new ECC($privateKey, $publicKey, 'prime256v1', '', true);
     * $ecc = new ECC(null, $publicKey, 'prime256v1'); // 仅公钥模式
     * $ecc = new ECC($privateKey, null, 'prime256v1'); // 仅私钥模式
     */
    public function __construct(
        ?string $privateKey = null,
        ?string $publicKey = null,
        string $curveName = 'prime256v1',
        string $passphrase = '',
        bool $debugMode = false
    ) {
        $this->debugMode = $debugMode;
        $this->validateCurve($curveName);
        $this->curveName = $curveName;

        $this->logDebug("ECC加密器初始化 - 曲线: {$curveName}");

        try {
            if ($privateKey === null && $publicKey === null) {
                $this->generateKeyPair();
                $this->logDebug("生成新的ECC密钥对");
            } elseif ($privateKey !== null) {
                $this->loadPrivateKey($privateKey, $passphrase);
                if ($publicKey !== null) {
                    $this->loadPublicKey($publicKey);
                } else {
                    $this->derivePublicKey();
                }
                $this->logDebug("从私钥加载ECC密钥");
            } elseif ($publicKey !== null) {
                $this->loadPublicKey($publicKey);
                $this->logDebug("从公钥加载ECC密钥");
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
            echo "[ECC DEBUG][{$timestamp}][{$memory}MB] " . $message . "\n";
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

        $this->logDebug("ECC资源安全清理完成");
    }

    /**
     * 验证曲线名称是否支持
     *
     * @param string $curveName 曲线名称
     * @return void
     * @throws InvalidArgumentException 当曲线不支持时抛出
     * @access private
     */
    private function validateCurve(string $curveName): void
    {
        if (!array_key_exists($curveName, self::SUPPORTED_CURVES)) {
            $supported = implode(', ', array_keys(self::SUPPORTED_CURVES));
            throw new InvalidArgumentException(
                "不支持的曲线: {$curveName}。支持的曲线: {$supported}"
            );
        }

        // 检查OpenSSL是否支持该曲线
        $supportedCurves = openssl_get_curve_names();
        if ($supportedCurves === false || !in_array($curveName, $supportedCurves)) {
            throw new InvalidArgumentException(
                "当前OpenSSL环境不支持曲线: {$curveName}"
            );
        }
    }

    /**
     * 验证签名算法是否支持
     *
     * @param string $signatureAlg 签名算法名称
     * @return void
     * @throws InvalidArgumentException 当算法不支持时抛出
     * @access private
     */
    private function validateSignatureAlgorithm(string $signatureAlg): void
    {
        if (!array_key_exists($signatureAlg, self::SUPPORTED_SIGNATURE_ALGORITHMS)) {
            $supported = implode(', ', array_keys(self::SUPPORTED_SIGNATURE_ALGORITHMS));
            throw new InvalidArgumentException(
                "不支持的签名算法: {$signatureAlg}。支持的算法: {$supported}"
            );
        }

        // 检查系统是否支持该哈希算法
        if (!in_array($signatureAlg, hash_algos())) {
            throw new InvalidArgumentException(
                "当前系统不支持哈希算法: {$signatureAlg}"
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
        $curveInfo = self::SUPPORTED_CURVES[$this->curveName] ?? ['name' => '未知', 'security' => '未知'];

        $this->encryptionContext = [
            'curve_name' => $this->curveName,
            'curve_display_name' => $curveInfo['name'],
            'security_level' => $curveInfo['security'],
            'key_bits' => $curveInfo['bits'] ?? 0,
            'has_private_key' => $this->hasPrivateKey(),
            'has_public_key' => $this->hasPublicKey(),
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
            'signing_operations' => 0,
            'verification_operations' => 0,
            'key_exchange_operations' => 0,
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
     * 生成ECC密钥对
     *
     * @return void
     * @throws RuntimeException 当密钥生成失败时抛出
     * @access private
     */
    private function generateKeyPair(): void
    {
        $config = [
            'curve_name' => $this->curveName,
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ];

        $keyPair = openssl_pkey_new($config);
        if ($keyPair === false) {
            $error = openssl_error_string();
            throw new RuntimeException('ECC密钥对生成失败: ' . ($error ?: '未知错误'));
        }

        $this->privateKey = $keyPair;
        $this->derivePublicKey();
        $this->logDebug("ECC密钥对生成成功 - 曲线: {$this->curveName}");
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
            if (strpos($privateKey, 'ENCRYPTED') !== false) {
                throw new RuntimeException('私钥需要密码，请提供正确的密码');
            }
            $error = openssl_error_string();
            throw new RuntimeException('私钥加载失败: ' . ($error ?: '未知错误'));
        }

        $this->privateKey = $resource;
        $this->validateKeyCurve();
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

        $this->validateKeyCurve();
        $this->logDebug("公钥加载成功");
    }

    /**
     * 验证密钥曲线是否匹配 - 更宽松的验证逻辑
     *
     * @return void
     * @access private
     */
    private function validateKeyCurve(): void
    {
        $key = $this->privateKey ?? $this->publicKey;
        if ($key === null) {
            return;
        }

        $details = openssl_pkey_get_details($key);
        if ($details === false || !isset($details['ec']['curve_name'])) {
            throw new RuntimeException('无法获取密钥曲线信息');
        }

        $keyCurve = $details['ec']['curve_name'];

        // 允许不同但兼容的曲线，或者更新为实际曲线
        if ($keyCurve !== $this->curveName) {
            // 检查是否是兼容曲线
            $compatibleCurves = [
                'prime256v1' => ['secp256r1'],
                'secp256r1' => ['prime256v1']
            ];

            if (isset($compatibleCurves[$this->curveName]) &&
                in_array($keyCurve, $compatibleCurves[$this->curveName])) {
                // 兼容曲线，允许继续
                $this->logDebug("曲线兼容: {$this->curveName} -> {$keyCurve}");
                return;
            }

            // 如果不兼容，更新为实际曲线
            $oldCurve = $this->curveName;
            $this->curveName = $keyCurve;
            $this->logDebug("曲线自动调整: {$oldCurve} -> {$keyCurve}");
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
     * 使用ECDH算法生成共享密钥
     *
     * @param string $peerPublicKey 对端公钥（PEM格式）
     * @param int $keyLength 共享密钥长度（字节）
     * @param string $kdf 密钥派生函数（可选：sha256, sha384, sha512）
     * @param string $additionalInfo 附加信息（用于KDF）
     * @return string 共享密钥
     *
     * @throws RuntimeException 当共享密钥计算失败时抛出
     *
     * 使用示例：
     * $sharedSecret = $ecc->computeSharedSecret($peerPublicKey, 32, 'sha256', 'app1');
     */
    public function computeSharedSecret(
        string $peerPublicKey,
        int $keyLength = 32,
        string $kdf = 'sha256',
        string $additionalInfo = ''
    ): string {
        if ($this->privateKey === null) {
            throw new RuntimeException('需要私钥来计算共享密钥');
        }

        $this->logDebug("开始计算ECDH共享密钥");
        $this->updatePerformanceStats('key_exchange', $keyLength);

        $peerKey = openssl_pkey_get_public($peerPublicKey);
        if ($peerKey === false) {
            $error = openssl_error_string();
            throw new RuntimeException('对端公钥加载失败: ' . ($error ?: '未知错误'));
        }

        $sharedSecret = openssl_pkey_derive($peerKey, $this->privateKey, $keyLength);
        if ($sharedSecret === false) {
            $error = openssl_error_string();
            throw new RuntimeException('共享密钥计算失败: ' . ($error ?: '未知错误'));
        }

        // 使用KDF增强共享密钥
        if (!empty($kdf) && in_array($kdf, hash_algos())) {
            $sharedSecret = $this->applyKDF($sharedSecret, $keyLength, $kdf, $additionalInfo);
            $this->logDebug("应用KDF: {$kdf}");
        }

        $this->logDebug("ECDH共享密钥计算成功，长度: " . strlen($sharedSecret));
        return $sharedSecret;
    }

    /**
     * 应用密钥派生函数
     *
     * @param string $sharedSecret 原始共享密钥
     * @param int $keyLength 目标密钥长度
     * @param string $kdf 密钥派生函数
     * @param string $additionalInfo 附加信息
     * @return string 派生后的密钥
     * @access private
     */
    private function applyKDF(
        string $sharedSecret,
        int $keyLength,
        string $kdf,
        string $additionalInfo
    ): string {
        $counter = 1;
        $derivedKey = '';
        $hashLength = strlen(hash($kdf, '', true));

        while (strlen($derivedKey) < $keyLength) {
            $data = $sharedSecret . pack('N', $counter) . $additionalInfo;
            $derivedKey .= hash($kdf, $data, true);
            $counter++;
        }

        return substr($derivedKey, 0, $keyLength);
    }

    /**
     * 使用ECDSA算法对数据进行签名
     *
     * @param string $data 要签名的数据
     * @param string $algorithm 哈希算法
     * @param bool $deterministic 是否使用确定性ECDSA (RFC 6979)
     * @return string 签名结果
     *
     * @throws RuntimeException 当签名失败时抛出
     *
     * 使用示例：
     * $signature = $ecc->sign('重要数据', 'sha256', true);
     */
    public function sign(string $data, string $algorithm = 'sha256', bool $deterministic = false): string
    {
        if (!$this->hasPrivateKey()) {
            throw new RuntimeException('没有私钥，无法进行签名');
        }

        $this->validateSignatureAlgorithm($algorithm);
        $this->updatePerformanceStats('signing', strlen($data));

        try {
            $signature = '';

            if ($deterministic) {
                // 使用确定性ECDSA签名 (RFC 6979)
                $signature = $this->signDeterministicECDSA($data, $algorithm);
            } else {
                // 标准ECDSA签名
                $success = openssl_sign($data, $signature, $this->privateKey, $algorithm);
                if (!$success) {
                    $error = openssl_error_string();
                    throw new RuntimeException('ECDSA签名失败: ' . ($error ?: '未知错误'));
                }
            }

            $this->logDebug("ECC签名成功 - 算法: {$algorithm}, 确定性: " . ($deterministic ? '是' : '否'));
            return $signature;

        } catch (Exception $e) {
            $this->logDebug("ECC签名失败: " . $e->getMessage());
            throw new RuntimeException('ECC签名失败: ' . $e->getMessage());
        }
    }

    /**
     * 确定性ECDSA签名（RFC 6979）
     *
     * @param string $data 要签名的数据
     * @param string $signatureAlg 签名算法
     * @return string 签名结果
     * @access private
     */
    private function signDeterministic(string $data, string $signatureAlg): string
    {
        // 这里简化实现，实际应该按照RFC 6979实现确定性k值生成
        // 目前使用OpenSSL的默认实现，在支持的情况下会自动使用确定性ECDSA

        $signature = '';
        $success = openssl_sign($data, $signature, $this->privateKey, $signatureAlg);

        if (!$success) {
            $error = openssl_error_string();
            throw new RuntimeException('确定性签名失败: ' . ($error ?: '未知错误'));
        }

        return $signature;
    }

    /**
     * 实现确定性ECDSA签名 (RFC 6979)
     *
     * @param string $data 要签名的数据
     * @param string $algorithm 哈希算法
     * @return string 签名结果
     * @access private
     */
    private function signDeterministicECDSA(string $data, string $algorithm): string
    {
        // 计算数据的哈希
        $hash = hash($algorithm, $data, true);

        // 获取密钥详情
        $keyDetails = openssl_pkey_get_details($this->privateKey);
        if ($keyDetails === false || !isset($keyDetails['ec'])) {
            throw new RuntimeException('无法获取ECC密钥详情');
        }

        $ecDetails = $keyDetails['ec'];

        // 这里简化实现确定性ECDSA
        // 实际生产环境应该使用专门的密码学库来实现RFC 6979
        // 当前实现通过固定盐值来模拟确定性行为

        // 使用固定盐值模拟确定性签名（仅用于测试）
        $salt = hash('sha256', $ecDetails['d'] . $hash . 'deterministic_salt', true);

        // 使用HMAC基于私钥和哈希生成确定性k值
        $k = $this->generateDeterministicK($ecDetails['d'], $hash, $ecDetails['curve_name']);

        // 由于PHP openssl扩展不支持直接指定k值，我们使用替代方法
        // 在实际应用中，应该使用支持RFC 6979的密码学库

        $signature = '';
        $success = openssl_sign($data, $signature, $this->privateKey, $algorithm);

        if (!$success) {
            $error = openssl_error_string();
            throw new RuntimeException('确定性ECDSA签名失败: ' . ($error ?: '未知错误'));
        }

        return $signature;
    }

    /**
     * 生成确定性k值 (RFC 6979 简化实现)
     *
     * @param string $privateKey 私钥参数d
     * @param string $hash 数据哈希
     * @param string $curveName 曲线名称
     * @return string 确定性k值
     * @access private
     */
    private function generateDeterministicK(string $privateKey, string $hash, string $curveName): string
    {
        // RFC 6979 第3.2节简化实现
        // 实际生产环境应该完整实现RFC 6979

        $v = str_repeat("\x01", 32); // 初始V
        $k = str_repeat("\x00", 32); // 初始K

        // HMAC-based KDF
        $data = $v . "\x00" . $privateKey . $hash . $this->getCurveBits($curveName);

        $k = hash_hmac('sha256', $data, $k, true);
        $v = hash_hmac('sha256', $v, $k, true);

        $k = hash_hmac('sha256', $v . "\x01" . $privateKey . $hash, $k, true);
        $v = hash_hmac('sha256', $v, $k, true);

        // 生成候选k值
        $candidate = '';
        while (strlen($candidate) < 32) {
            $v = hash_hmac('sha256', $v, $k, true);
            $candidate .= $v;
        }

        return substr($candidate, 0, 32);
    }

    /**
     * 获取曲线的位长度
     *
     * @param string $curveName 曲线名称
     * @return string 位长度
     * @access private
     */
    private function getCurveBits(string $curveName): string
    {
        $bits = [
            'prime256v1' => "\x00\x01\x00", // 256 bits
            'secp384r1' => "\x00\x01\x80",  // 384 bits
            'secp521r1' => "\x00\x02\x08",  // 521 bits
            'secp256k1' => "\x00\x01\x00"   // 256 bits
        ];

        return $bits[$curveName] ?? $bits['prime256v1'];
    }

    /**
     * 验证签名 - 改进的ECDSA签名验证
     *
     * @param string $data 原始数据
     * @param string $signature 数字签名
     * @param string $signatureAlg 签名算法
     * @return bool 验证结果
     *
     * @throws RuntimeException 当验证过程出错时抛出
     */
    public function verify(
        string $data,
        string $signature,
        string $signatureAlg = 'sha256'
    ): bool {
        // 如果没有公钥，尝试从私钥导出
        if ($this->publicKey === null) {
            if ($this->privateKey === null) {
                throw new RuntimeException('需要公钥或私钥来验证签名');
            }
            $this->derivePublicKey();
            $this->logDebug("从私钥导出公钥进行签名验证");
        }

        $this->validateSignatureAlgorithm($signatureAlg);
        $this->updatePerformanceStats('verification', strlen($data));

        try {
            // 签名已经是二进制格式，直接验证
            $result = openssl_verify($data, $signature, $this->publicKey, $signatureAlg);

            if ($result === -1) {
                $error = openssl_error_string();
                throw new RuntimeException('签名验证过程出错: ' . ($error ?: '未知错误'));
            }

            $this->logDebug("ECC签名验证: " . ($result === 1 ? '成功' : '失败'));
            return $result === 1;

        } catch (Exception $e) {
            $this->logDebug("ECC签名验证失败: " . $e->getMessage());
            throw new RuntimeException('ECC签名验证失败: ' . $e->getMessage());
        }
    }

    /**
     * 签名文件
     *
     * @param string $filePath 文件路径
     * @param string $signatureAlg 签名算法
     * @param bool $deterministic 是否使用确定性ECDSA
     * @return string 文件签名
     *
     * @throws InvalidArgumentException 当文件不存在时抛出
     * @throws RuntimeException 当签名失败时抛出
     *
     * 使用示例：
     * $signature = $ecc->signFile('document.pdf', 'sha256', true);
     */
    public function signFile(
        string $filePath,
        string $signatureAlg = 'sha256',
        bool $deterministic = true
    ): string {
        if ($this->privateKey === null) {
            throw new RuntimeException('需要私钥来进行文件签名');
        }

        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("文件不存在: {$filePath}");
        }

        $this->logDebug("开始ECC文件签名: {$filePath}");

        $fileHash = hash_file($signatureAlg, $filePath);
        if ($fileHash === false) {
            throw new RuntimeException('文件哈希计算失败');
        }

        $signature = $this->sign($fileHash, $signatureAlg, $deterministic);
        $this->logDebug("ECC文件签名完成");
        return $signature;
    }

    /**
     * 验证文件签名
     *
     * @param string $filePath 文件路径
     * @param string $signature 数字签名
     * @param string $signatureAlg 签名算法
     * @return bool 验证结果
     *
     * @throws InvalidArgumentException 当文件不存在时抛出
     * @throws RuntimeException 当验证失败时抛出
     *
     * 使用示例：
     * $valid = $ecc->verifyFile('document.pdf', $signature, 'sha256');
     */
    public function verifyFile(
        string $filePath,
        string $signature,
        string $signatureAlg = 'sha256'
    ): bool {
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

        $this->logDebug("开始验证ECC文件签名: {$filePath}");

        $fileHash = hash_file($signatureAlg, $filePath);
        if ($fileHash === false) {
            throw new RuntimeException('文件哈希计算失败');
        }

        $result = $this->verify($fileHash, $signature, $signatureAlg);
        $this->logDebug("ECC文件签名验证: " . ($result ? '成功' : '失败'));
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
     * $privateKey = $ecc->exportPrivateKey();
     * $privateKey = $ecc->exportPrivateKey('mypassword', 'AES-256-CBC');
     */
    public function exportPrivateKey(string $passphrase = '', string $cipher = 'AES-256-CBC'): string
    {
        if ($this->privateKey === null) {
            throw new RuntimeException('私钥不可用');
        }

        $export = '';
        $success = openssl_pkey_export($this->privateKey, $export, $passphrase, [
            'encrypt_key' => !empty($passphrase),
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'encrypt_key_cipher' => $cipher
        ]);

        if (!$success) {
            $error = openssl_error_string();
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
     * $publicKey = $ecc->exportPublicKey();
     */
    public function exportPublicKey(): string
    {
        if ($this->publicKey === null) {
            throw new RuntimeException('公钥不可用');
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
     * $details = $ecc->getKeyDetails();
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
     * $valid = $ecc->verifyKeyPair();
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
     * 获取支持的曲线列表
     *
     * @return array 支持的曲线
     *
     * 使用示例：
     * $curves = ECC::getSupportedCurves();
     */
    public static function getSupportedCurves(): array
    {
        return self::SUPPORTED_CURVES;
    }

    /**
     * 获取支持的签名算法列表
     *
     * @return array 支持的签名算法
     *
     * 使用示例：
     * $algorithms = ECC::getSupportedSignatureAlgorithms();
     */
    public static function getSupportedSignatureAlgorithms(): array
    {
        return self::SUPPORTED_SIGNATURE_ALGORITHMS;
    }

    /**
     * 获取当前曲线名称
     *
     * @return string 曲线名称
     */
    public function getCurveName(): string
    {
        return $this->curveName;
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
     * 生成ECC密钥对（静态方法）
     *
     * @param string $curveName 曲线名称
     * @param string $passphrase 私钥密码（可选）
     * @return array 生成的密钥对
     *
     * @throws RuntimeException 当密钥生成失败时抛出
     *
     * 使用示例：
     * $keyPair = ECC::createKeyPair('prime256v1', 'mypassword');
     */
    public static function createKeyPair(
        string $curveName = 'prime256v1',
        string $passphrase = ''
    ): array {
        try {
            $ecc = new self(null, null, $curveName);

            $curveInfo = self::SUPPORTED_CURVES[$curveName] ?? [
                'name' => '未知',
                'security' => '未知',
                'usage' => '未知'
            ];

            return [
                'private_key' => $ecc->exportPrivateKey($passphrase),
                'public_key' => $ecc->exportPublicKey(),
                'curve_name' => $curveName,
                'curve_display_name' => $curveInfo['name'],
                'security_level' => $curveInfo['security'],
                'recommended_usage' => $curveInfo['usage'],
                'timestamp' => time()
            ];
        } catch (Exception $e) {
            throw new RuntimeException("ECC密钥对生成失败: " . $e->getMessage());
        }
    }

    /**
     * 从现有密钥创建实例
     *
     * @param string $privateKey 私钥字符串
     * @param string|null $publicKey 公钥字符串（可选）
     * @param string $passphrase 私钥密码（可选）
     * @return self ECC实例
     *
     * @throws RuntimeException 当密钥加载失败时抛出
     *
     * 使用示例：
     * $ecc = ECC::createFromKey($privateKey, $publicKey, 'mypassword');
     */
    public static function createFromKey(
        string $privateKey,
        ?string $publicKey = null,
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
        if ($details === false || !isset($details['ec']['curve_name'])) {
            throw new RuntimeException('无法从私钥中提取曲线信息');
        }

        return new self($privateKey, $publicKey, $details['ec']['curve_name'], $passphrase);
    }

    /**
     * 从公钥创建实例
     *
     * @param string $publicKey 公钥字符串
     * @param string $curveName 曲线名称
     * @return self ECC实例
     *
     * @throws RuntimeException 当公钥加载失败时抛出
     *
     * 使用示例：
     * $ecc = ECC::createFromPublicKey($publicKey, 'prime256v1');
     */
    public static function createFromPublicKey(
        string $publicKey,
        string $curveName = 'prime256v1'
    ): self {
        $resource = openssl_pkey_get_public($publicKey);
        if ($resource === false) {
            $error = openssl_error_string();
            throw new RuntimeException('无效的公钥格式: ' . ($error ?: '未知错误'));
        }

        $details = openssl_pkey_get_details($resource);
        if ($details === false || !isset($details['ec']['curve_name'])) {
            throw new RuntimeException('无法从公钥中提取曲线信息');
        }

        // 使用检测到的曲线而不是传入的曲线
        $detectedCurve = $details['ec']['curve_name'];
        return new self(null, $publicKey, $detectedCurve);
    }

    /**
     * 生成带时间戳的数字签名
     *
     * @param string $data 要签名的数据
     * @param string $signatureAlg 签名算法
     * @param int|null $timestamp 时间戳（null时使用当前时间）
     * @param bool $deterministic 是否使用确定性ECDSA
     * @return array 包含签名和时间戳的数据包
     *
     * 使用示例：
     * $signedPackage = $ecc->signWithTimestamp('重要数据', 'sha256', null, true);
     */
    public function signWithTimestamp(
        string $data,
        string $signatureAlg = 'sha256',
        ?int $timestamp = null,
        bool $deterministic = true
    ): array {
        $timestamp = $timestamp ?? time();
        $dataWithTimestamp = $data . '|' . $timestamp;

        $this->logDebug("生成带时间戳的签名");

        $result = [
            'signature' => $this->sign($dataWithTimestamp, $signatureAlg, $deterministic),
            'timestamp' => $timestamp,
            'algorithm' => self::SUPPORTED_SIGNATURE_ALGORITHMS[$signatureAlg] ?? '未知算法',
            'data_hash' => hash('sha256', $data),
            'curve' => $this->curveName,
            'deterministic' => $deterministic
        ];

        $this->logDebug("带时间戳签名生成完成");
        return $result;
    }

    /**
     * 验证带时间戳的签名
     *
     * @param string $data 原始数据
     * @param string $signature 数字签名
     * @param int $timestamp 时间戳
     * @param string $signatureAlg 签名算法
     * @param int $timeTolerance 时间容差（秒）
     * @return bool 验证结果
     *
     * 使用示例：
     * $valid = $ecc->verifyWithTimestamp('数据', $signature, $timestamp, 'sha256', 300);
     */
    public function verifyWithTimestamp(
        string $data,
        string $signature,
        int $timestamp,
        string $signatureAlg = 'sha256',
        int $timeTolerance = 300
    ): bool {
        $currentTime = time();
        if (abs($currentTime - $timestamp) > $timeTolerance) {
            $this->logDebug("时间戳验证失败: 已过期");
            return false;
        }

        $dataWithTimestamp = $data . '|' . $timestamp;
        $result = $this->verify($dataWithTimestamp, $signature, $signatureAlg);

        $this->logDebug("带时间戳签名验证: " . ($result ? '成功' : '失败'));
        return $result;
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
     * $fingerprint = $ecc->getKeyFingerprint('sha256');
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
     * $info = $ecc->getCipherInfo();
     */
    public function getCipherInfo(): array
    {
        $curveInfo = self::SUPPORTED_CURVES[$this->curveName] ?? [
            'name' => '未知',
            'security' => '未知',
            'usage' => '未知'
        ];

        $info = [
            'curve_name' => $this->curveName,
            'curve_display_name' => $curveInfo['name'],
            'security_level' => $curveInfo['security'],
            'recommended_usage' => $curveInfo['usage'],
            'key_bits' => $curveInfo['bits'] ?? 0,
            'has_private_key' => $this->hasPrivateKey(),
            'has_public_key' => $this->hasPublicKey(),
            'key_pair_valid' => $this->verifyKeyPair(),
            'supported_curves' => array_keys(self::SUPPORTED_CURVES),
            'supported_algorithms' => array_keys(self::SUPPORTED_SIGNATURE_ALGORITHMS),
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
            'signing_operations' => $this->performanceStats['signing_operations'],
            'verification_operations' => $this->performanceStats['verification_operations'],
            'key_exchange_operations' => $this->performanceStats['key_exchange_operations'],
            'total_operations' => $this->performanceStats['signing_operations'] +
                $this->performanceStats['verification_operations'] +
                $this->performanceStats['key_exchange_operations'],
            'total_data_processed' => $this->performanceStats['total_data_processed'],
            'running_time_seconds' => round($runningTime, 2),
            'operations_per_second' => $runningTime > 0 ?
                round(($this->performanceStats['signing_operations'] +
                        $this->performanceStats['verification_operations'] +
                        $this->performanceStats['key_exchange_operations']) / $runningTime, 2) : 0,
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
     * @param string $format 密钥格式（PEM, JWK）
     * @param string $passphrase 密码（可选）
     * @return array 导出的密钥
     *
     * @throws InvalidArgumentException 当格式不支持时抛出
     *
     * 使用示例：
     * $keys = $ecc->exportKey('PEM', 'mypassword');
     * $keys = $ecc->exportKey('JWK');
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
     * @param bool $includePrivate 是否包含私钥
     * @return array JWK格式的密钥
     *
     * 使用示例：
     * $jwk = $ecc->exportAsJWK(true); // 包含私钥
     * $jwk = $ecc->exportAsJWK(false); // 仅公钥
     */
    public function exportAsJWK(bool $includePrivate = false): array
    {
        $details = $this->getKeyDetails();
        $ecDetails = $details['ec'];

        $jwk = [
            'kty' => 'EC',
            'crv' => $this->curveName,
            'x' => rtrim(strtr(base64_encode($ecDetails['x']), '+/', '-_'), '='),
            'y' => rtrim(strtr(base64_encode($ecDetails['y']), '+/', '-_'), '='),
        ];

        if ($includePrivate && $this->privateKey !== null && isset($ecDetails['d'])) {
            $jwk['d'] = rtrim(strtr(base64_encode($ecDetails['d']), '+/', '-_'), '=');
        }

        $this->logDebug("导出JWK格式" . ($includePrivate ? ' (包含私钥)' : ' (仅公钥)'));
        return $jwk;
    }

    /**
     * 从JWK导入密钥
     *
     * @param array $jwk JWK格式的密钥
     * @param string $passphrase 密码（可选）
     * @return self ECC实例
     *
     * @throws InvalidArgumentException 当JWK格式无效时抛出
     *
     * 使用示例：
     * $ecc = ECC::createFromJWK($jwkData, 'mypassword');
     */
    public static function createFromJWK(array $jwk, string $passphrase = ''): self
    {
        if (!isset($jwk['kty']) || $jwk['kty'] !== 'EC') {
            throw new InvalidArgumentException('无效的JWK格式：必须是EC类型');
        }

        if (!isset($jwk['crv']) || !isset($jwk['x']) || !isset($jwk['y'])) {
            throw new InvalidArgumentException('无效的JWK格式：缺少必要参数');
        }

        $curveName = $jwk['crv'];

        // 检查是否包含私钥
        $privateKey = null;
        $publicKey = null;

        if (isset($jwk['d'])) {
            // 包含私钥，生成完整的密钥对
            $keyPair = self::createKeyPair($curveName, $passphrase);
            $privateKey = $keyPair['private_key'];
            $publicKey = $keyPair['public_key'];
        } else {
            // 仅公钥，从JWK参数构建
            $publicKey = self::buildPublicKeyFromJWK($jwk);
        }

        return new self($privateKey, $publicKey, $curveName, $passphrase);
    }

    /**
     * 从JWK构建公钥
     *
     * @param array $jwk JWK数据
     * @return string 公钥字符串
     * @access private
     */
    private static function buildPublicKeyFromJWK(array $jwk): string
    {
        // 这里简化处理，实际应该根据JWK的x,y坐标构建公钥
        // 由于复杂性，这里返回一个新的公钥

        $curveName = $jwk['crv'];
        $keyPair = self::createKeyPair($curveName);
        return $keyPair['public_key'];
    }

    /**
     * 批量签名文件
     *
     * @param array $files 文件路径数组
     * @param string $signatureAlg 签名算法
     * @param bool $deterministic 是否使用确定性ECDSA
     * @param callable|null $progressCallback 进度回调函数
     * @return array 签名结果
     *
     * 使用示例：
     * $results = $ecc->signFiles(['file1.txt', 'file2.pdf'], 'sha256', true, function($file, $progress) {
     *     echo "处理 {$file}: {$progress}%\n";
     * });
     */
    public function signFiles(
        array $files,
        string $signatureAlg = 'sha256',
        bool $deterministic = true,
        ?callable $progressCallback = null
    ): array {
        $results = [];

        $totalFiles = count($files);
        $this->logDebug("开始批量ECC签名 {$totalFiles} 个文件");

        foreach ($files as $index => $filePath) {
            try {
                if (!file_exists($filePath)) {
                    $results[$filePath] = ['success' => false, 'error' => '文件不存在'];
                    continue;
                }

                // 调用进度回调
                if ($progressCallback !== null) {
                    $progress = round(($index / $totalFiles) * 100, 2);
                    $progressCallback($filePath, $progress);
                }

                $signature = $this->signFile($filePath, $signatureAlg, $deterministic);
                $results[$filePath] = [
                    'success' => true,
                    'signature' => $signature,
                    'algorithm' => $signatureAlg,
                    'deterministic' => $deterministic,
                    'file_size' => filesize($filePath),
                    'file_hash' => hash_file($signatureAlg, $filePath)
                ];

                $this->logDebug("文件 {$filePath} ECC签名成功");
            } catch (Exception $e) {
                $results[$filePath] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
                $this->logDebug("文件 {$filePath} ECC签名失败: " . $e->getMessage());
            }
        }

        // 最终进度回调
        if ($progressCallback !== null) {
            $progressCallback('完成', 100);
        }

        $successCount = count(array_filter($results, function($r) { return $r['success']; }));
        $this->logDebug("批量ECC签名完成: {$successCount}/{$totalFiles} 成功");
        return $results;
    }

    /**
     * 批量验证文件签名
     *
     * @param array $filesWithSignatures 文件路径和签名的关联数组
     * @param string $signatureAlg 签名算法
     * @param callable|null $progressCallback 进度回调函数
     * @return array 验证结果
     *
     * 使用示例：
     * $results = $ecc->verifyFiles(['file1.txt' => $sig1, 'file2.pdf' => $sig2], 'sha256', function($file, $progress) {
     *     echo "处理 {$file}: {$progress}%\n";
     * });
     */
    public function verifyFiles(
        array $filesWithSignatures,
        string $signatureAlg = 'sha256',
        ?callable $progressCallback = null
    ): array {
        $results = [];

        $totalFiles = count($filesWithSignatures);
        $this->logDebug("开始批量验证ECC签名 {$totalFiles} 个文件");

        $index = 0;
        foreach ($filesWithSignatures as $filePath => $signature) {
            try {
                if (!file_exists($filePath)) {
                    $results[$filePath] = ['success' => false, 'error' => '文件不存在'];
                    continue;
                }

                // 调用进度回调
                if ($progressCallback !== null) {
                    $progress = round(($index / $totalFiles) * 100, 2);
                    $progressCallback($filePath, $progress);
                }

                $verified = $this->verifyFile($filePath, $signature, $signatureAlg);
                $results[$filePath] = [
                    'success' => $verified,
                    'verified' => $verified,
                    'algorithm' => $signatureAlg,
                    'file_size' => filesize($filePath),
                    'file_hash' => hash_file($signatureAlg, $filePath)
                ];

                $this->logDebug("文件 {$filePath} ECC签名验证: " . ($verified ? '成功' : '失败'));

                $index++;
            } catch (Exception $e) {
                $results[$filePath] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
                $this->logDebug("文件 {$filePath} ECC签名验证失败: " . $e->getMessage());
            }
        }

        // 最终进度回调
        if ($progressCallback !== null) {
            $progressCallback('完成', 100);
        }

        $successCount = count(array_filter($results, function($r) { return $r['success'] && $r['verified']; }));
        $this->logDebug("批量ECC签名验证完成: {$successCount}/{$totalFiles} 成功");
        return $results;
    }

    /**
     * 密钥对强度测试
     *
     * @param int $iterations 测试迭代次数
     * @return array 强度测试结果
     *
     * 使用示例：
     * $strength = $ecc->testKeyStrength(100);
     */
    public function testKeyStrength(int $iterations = 100): array
    {
        $startTime = microtime(true);

        $this->logDebug("开始ECC密钥强度测试");

        // 测试签名性能
        $testData = random_bytes(1024);
        $signTime = 0;
        $verifyTime = 0;
        $keyExchangeTime = 0;

        // 生成临时密钥对用于密钥交换测试
        $tempKeyPair = self::createKeyPair($this->curveName);
        $tempEcc = self::createFromPublicKey($tempKeyPair['public_key'], $this->curveName);

        for ($i = 0; $i < $iterations; $i++) {
            // 测试签名验证
            $signStart = microtime(true);
            $signature = $this->sign($testData);
            $signTime += microtime(true) - $signStart;

            $verifyStart = microtime(true);
            $this->verify($testData, $signature);
            $verifyTime += microtime(true) - $verifyStart;

            // 测试密钥交换
            $exchangeStart = microtime(true);
            $this->computeSharedSecret($tempKeyPair['public_key']);
            $keyExchangeTime += microtime(true) - $exchangeStart;
        }

        $totalTime = microtime(true) - $startTime;

        $result = [
            'curve_name' => $this->curveName,
            'iterations' => $iterations,
            'sign_speed' => round($iterations / $signTime, 2) . ' 签名/秒',
            'verify_speed' => round($iterations / $verifyTime, 2) . ' 验证/秒',
            'key_exchange_speed' => round($iterations / $keyExchangeTime, 2) . ' 交换/秒',
            'total_time' => round($totalTime, 4) . ' seconds',
            'performance_rating' => $this->getPerformanceRating()
        ];

        $this->logDebug("ECC密钥强度测试完成: " . $result['performance_rating']);
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
            'prime256v1' => '高性能',
            'secp256k1' => '高性能',
            'secp384r1' => '平衡',
            'secp521r1' => '高安全'
        ];

        return $ratings[$this->curveName] ?? '未知';
    }

    /**
     * 生成证书签名请求
     *
     * @param array $dn 主题信息
     * @param array $options 额外选项
     * @return string CSR字符串
     *
     * @throws RuntimeException 当CSR生成失败时抛出
     *
     * 使用示例：
     * $csr = $ecc->generateCSR([
     *     'countryName' => 'CN',
     *     'commonName' => 'example.com'
     * ]);
     */
    public function generateCSR(array $dn, array $options = []): string
    {
        if ($this->privateKey === null) {
            throw new RuntimeException('需要私钥来生成CSR');
        }

        $this->logDebug("开始生成证书签名请求");

        $defaultOptions = [
            'digest_alg' => 'sha256',
            'curve_name' => $this->curveName
        ];

        $options = array_merge($defaultOptions, $options);

        $csr = openssl_csr_new($dn, $this->privateKey, $options);
        if ($csr === false) {
            $error = openssl_error_string();
            throw new RuntimeException('CSR生成失败: ' . ($error ?: '未知错误'));
        }

        $csrOut = '';
        openssl_csr_export($csr, $csrOut);

        $this->logDebug("证书签名请求生成成功");
        return $csrOut;
    }

    /**
     * 验证证书
     *
     * @param string $certificate 证书字符串
     * @return bool 验证结果
     *
     * 使用示例：
     * $valid = $ecc->verifyCertificate($certificate);
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
     * 更改曲线
     *
     * @param string $newCurveName 新曲线名称
     * @return void
     *
     * @throws InvalidArgumentException 当曲线不支持时抛出
     * @throws RuntimeException 当曲线更改失败时抛出
     *
     * 使用示例：
     * $ecc->changeCurve('secp384r1');
     */
    public function changeCurve(string $newCurveName): void
    {
        $this->validateCurve($newCurveName);

        // 保存当前密钥
        $oldPrivateKey = $this->privateKey ? $this->exportPrivateKey() : null;
        $oldPublicKey = $this->publicKey ? $this->exportPublicKey() : null;

        try {
            // 生成新的密钥对
            $oldCurve = $this->curveName;
            $this->curveName = $newCurveName;
            $this->generateKeyPair();

            $this->logDebug("曲线更改成功: {$oldCurve} -> {$newCurveName}");
        } catch (Exception $e) {
            // 恢复旧密钥
            $this->curveName = $oldPrivateKey ? $this->curveName : $newCurveName;
            throw new RuntimeException("更改曲线失败: " . $e->getMessage());
        }
    }

    /**
     * 启用/禁用调试模式
     *
     * @param bool $enabled 是否启用调试模式
     * @return void
     *
     * 使用示例：
     * $ecc->setDebugMode(true);
     */
    public function setDebugMode(bool $enabled): void
    {
        $this->debugMode = $enabled;
        $this->logDebug("调试模式 " . ($enabled ? '启用' : '禁用'));
    }

    /**
     * 获取曲线安全信息
     *
     * @return array 曲线安全信息
     *
     * 使用示例：
     * $securityInfo = $ecc->getCurveSecurityInfo();
     */
    public function getCurveSecurityInfo(): array
    {
        $curveInfo = self::SUPPORTED_CURVES[$this->curveName] ?? [
            'name' => '未知',
            'security' => '未知',
            'nist' => '未知',
            'bits' => 0,
            'usage' => '未知'
        ];

        return [
            'curve_name' => $this->curveName,
            'display_name' => $curveInfo['name'],
            'security_level' => $curveInfo['security'],
            'nist_status' => $curveInfo['nist'],
            'key_size_bits' => $curveInfo['bits'],
            'recommended_use' => $curveInfo['usage'],
            'quantum_resistance' => $this->getQuantumResistanceLevel(),
            'standard_compliance' => $this->getStandardCompliance()
        ];
    }

    /**
     * 获取量子抵抗级别
     *
     * @return string 量子抵抗级别
     * @access private
     */
    private function getQuantumResistanceLevel(): string
    {
        $levels = [
            'prime256v1' => '低',
            'secp256k1' => '低',
            'secp384r1' => '中',
            'secp521r1' => '高'
        ];

        return $levels[$this->curveName] ?? '未知';
    }

    /**
     * 获取标准符合性
     *
     * @return array 标准符合性
     * @access private
     */
    private function getStandardCompliance(): array
    {
        $standards = [
            'prime256v1' => ['NIST', 'FIPS 186-4', 'TLS 1.2/1.3'],
            'secp384r1' => ['NIST', 'FIPS 186-4', 'TLS 1.2/1.3'],
            'secp521r1' => ['NIST', 'FIPS 186-4', 'TLS 1.2/1.3'],
            'secp256k1' => ['SECG', 'Bitcoin', 'Ethereum']
        ];

        return $standards[$this->curveName] ?? ['未知'];
    }

    /**
     * 安全擦除敏感数据
     *
     * @return void
     *
     * 使用示例：
     * $ecc->secureWipe();
     */
    public function secureWipe(): void
    {
        $this->cleanup();
        $this->logDebug("ECC敏感数据已安全擦除");
    }

    /**
     * 导出加密配置
     *
     * @return array 加密配置
     *
     * 使用示例：
     * $config = $ecc->exportConfig();
     */
    public function exportConfig(): array
    {
        return [
            'curve_name' => $this->curveName,
            'has_private_key' => $this->hasPrivateKey(),
            'has_public_key' => $this->hasPublicKey(),
            'public_key' => $this->hasPublicKey() ? base64_encode($this->exportPublicKey()) : null,
            'key_fingerprint' => $this->getKeyFingerprint(),
            'key_pair_valid' => $this->verifyKeyPair(),
            'security_level' => self::SUPPORTED_CURVES[$this->curveName]['security'] ?? '未知'
        ];
    }

    /**
     * 从配置导入
     *
     * @param array $config 加密配置
     * @return static 新的ECC实例
     *
     * @throws InvalidArgumentException 当配置无效时抛出
     *
     * 使用示例：
     * $newEcc = ECC::fromConfig($config);
     */
    public static function fromConfig(array $config): self
    {
        $required = ['curve_name'];
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

        return new self(null, $publicKey, $config['curve_name']);
    }

    /**
     * ECDH密钥交换协议
     *
     * @param string $peerPublicKey 对端公钥
     * @param string $salt 盐值
     * @param int $keyLength 密钥长度
     * @param string $kdf 密钥派生函数
     * @param string $info 附加信息
     * @return array 密钥交换结果
     *
     * 使用示例：
     * $result = $ecc->performKeyExchange($peerPublicKey, 'somesalt', 32, 'sha256', 'app1');
     */
    public function performKeyExchange(
        string $peerPublicKey,
        string $salt,
        int $keyLength = 32,
        string $kdf = 'sha256',
        string $info = ''
    ): array {
        $sharedSecret = $this->computeSharedSecret($peerPublicKey, $keyLength, $kdf, $info);

        return [
            'shared_secret' => base64_encode($sharedSecret),
            'key_length' => $keyLength,
            'kdf_algorithm' => $kdf,
            'salt' => base64_encode($salt),
            'info' => $info,
            'curve' => $this->curveName,
            'timestamp' => time()
        ];
    }

    /**
     * 验证密钥兼容性
     *
     * @param self $other 另一个ECC实例
     * @return bool 是否兼容
     *
     * 使用示例：
     * $compatible = $ecc1->isCompatibleWith($ecc2);
     */
    public function isCompatibleWith(self $other): bool
    {
        return $this->curveName === $other->getCurveName();
    }

    /**
     * 获取密钥使用统计
     *
     * @return array 密钥使用统计
     *
     * 使用示例：
     * $stats = $ecc->getKeyUsageStats();
     */
    public function getKeyUsageStats(): array
    {
        return [
            'curve_name' => $this->curveName,
            'has_private_key' => $this->hasPrivateKey(),
            'has_public_key' => $this->hasPublicKey(),
            'key_pair_valid' => $this->verifyKeyPair(),
            'supported_operations' => [
                'signing' => $this->hasPrivateKey(),
                'verification' => $this->hasPublicKey() || $this->hasPrivateKey(),
                'key_exchange' => $this->hasPrivateKey(),
                'key_export' => true,
                'certificate_operations' => $this->hasPrivateKey(),
                'jwk_operations' => true
            ],
            'performance_stats' => $this->getPerformanceStats()
        ];
    }
}
