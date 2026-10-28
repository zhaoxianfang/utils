<?php

declare(strict_types=1);

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
        // 统一把 RFC 7517 标准名（P-256/P-384/P-521）归一化为 OpenSSL 内部名，内部一律使用后者
        $this->curveName = self::normalizeCurveName($curveName);
        $this->validateCurve($this->curveName);

        $this->logDebug("ECC加密器初始化 - 曲线: {$this->curveName}");

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
     * 记录一条 ECC 调试日志
     *
     * 仅在调试模式开启时输出，否则静默丢弃。日志附带毫秒级时间戳与当前进程
     * 内存占用（MB），便于排查密钥加载与签名流程；不参与任何业务判断。
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
     * 加载 PEM/DER 格式公钥
     *
     * 交由 OpenSSL 解析公钥字符串并缓存句柄，随后调用 validateKeyCurve()
     * 校验（或在兼容范围内自动修正）曲线名称。解析失败时抛出异常。
     *
     * @param string $publicKey 公钥字符串，支持 PEM 及 OpenSSL 可识别的格式
     * @return void
     * @throws RuntimeException 公钥无法加载或曲线校验失败时抛出
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
     * 从私钥导出一致的公钥句柄
     *
     * 通过 openssl_pkey_get_details() 取私钥对应的 PEM 公钥并重新解析，
     * 使 publicKey 与 privateKey 严格配对。私钥缺失或详情不含 key 字段时
     * 抛出异常。构造流程中加载私钥后会自动调用。
     *
     * @return void
     * @throws RuntimeException 私钥不可用、密钥详情获取失败或公钥解析失败时抛出
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
     * 注意：PHP 的 openssl_sign 无法注入确定性 nonce k（RFC 6979），
     * $deterministic 参数保留仅为 API 兼容；实际无论是否传 true，
     * 均由 OpenSSL 内部 CSPRNG 生成 k（安全性不受影响，但签名不保证可复现）。
     * 需要真正确定性签名时应使用专门的密码学库（如 mdanter/ecc）。
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
            if ($deterministic) {
                // PHP openssl 绑定不支持 k 注入，无法真正实现 RFC 6979 确定性签名，
                // 此处回退到加密安全随机 ECDSA（与 deterministic=false 行为一致）
                $this->logDebug("deterministic=true 已请求，但 PHP OpenSSL 不支持注入确定性 k，回退为安全随机 ECDSA 签名");
            }

            $signature = '';
            $success = openssl_sign($data, $signature, $this->privateKey, $algorithm);
            if (!$success) {
                $error = openssl_error_string();
                throw new RuntimeException('ECDSA签名失败: ' . ($error ?: '未知错误'));
            }

            $this->logDebug("ECC签名成功 - 算法: {$algorithm}");
            return $signature;

        } catch (Exception $e) {
            $this->logDebug("ECC签名失败: " . $e->getMessage());
            throw new RuntimeException('ECC签名失败: ' . $e->getMessage());
        }
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
            // 归一化后实例内部存 OpenSSL 名，元数据查找与返回的 curve_name 均一致
            $curveName = self::normalizeCurveName($curveName);
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
     * 将签名、验签、密钥交换次数与累计数据量清零，并以当前时刻作为新的
     * 统计起点；重置后 getPerformanceStats() 的耗时与吞吐指标重新累计。
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

        // RFC 7517：crv 使用标准名（P-256/P-384/P-521），secp256k1 本身即标准名
        $crv = self::standardCrvName($this->curveName);
        $coordSize = self::ecCoordSizeByCurve($this->curveName);

        // openssl 详情中的 x/y/d 可能省略前导零字节，必须补齐到曲线坐标长度
        $pad = static fn (?string $v): string => $v === null || $v === '' ? '' : str_pad($v, $coordSize, "\x00", STR_PAD_LEFT);

        $jwk = [
            'kty' => 'EC',
            'crv' => $crv,
            'x' => Base64::urlSafeEncode($pad($ecDetails['x'] ?? null)),
            'y' => Base64::urlSafeEncode($pad($ecDetails['y'] ?? null)),
        ];

        if ($includePrivate && $this->privateKey !== null && isset($ecDetails['d'])) {
            $jwk['d'] = Base64::urlSafeEncode($pad($ecDetails['d']));
        }

        $this->logDebug("导出JWK格式" . ($includePrivate ? ' (包含私钥)' : ' (仅公钥)'));
        return $jwk;
    }

    /**
     * 从JWK导入密钥
     *
     * @param array $jwk JWK格式的密钥（crv 兼容标准名 P-256/P-384/P-521/secp256k1 与 OpenSSL 名）
     * @param string $passphrase 密码（可选）
     * @return self ECC实例
     *
     * @throws InvalidArgumentException 当JWK格式无效时抛出
     *
     * 使用示例：
     * $ecc = ECC::createFromJWK($jwkData);
     */
    public static function createFromJWK(array $jwk, string $passphrase = ''): self
    {
        if (!isset($jwk['kty']) || $jwk['kty'] !== 'EC') {
            throw new InvalidArgumentException('无效的JWK格式：必须是EC类型');
        }

        if (!isset($jwk['crv']) || !isset($jwk['x']) || !isset($jwk['y'])) {
            throw new InvalidArgumentException('无效的JWK格式：缺少必要参数');
        }

        $curveName = self::normalizeCurveName((string) $jwk['crv']);

        // 构造公钥 PEM（真实还原 x/y 坐标，不再生成随机密钥）
        $publicKeyPem = self::buildPublicKeyFromJWK($jwk);

        if (isset($jwk['d'])) {
            if (!is_string($jwk['d']) || $jwk['d'] === '') {
                throw new InvalidArgumentException('无效的JWK格式：d 必须是字符串');
            }
            $privateKeyPem = self::buildPrivateKeyFromJWK($jwk, $curveName);
            // 传入私钥 PEM，由构造器自动派生/校验公钥
            return self::constructFromPem($privateKeyPem, $publicKeyPem, $curveName);
        }

        return self::constructFromPem(null, $publicKeyPem, $curveName);
    }

    /**
     * 构造 ECC 实例，并把 OpenSSL 层加载失败统一转换为 InvalidArgumentException
     * （JWK 数据无效 = 调用方输入错误，不属于运行时内部故障）
     *
     * @param string|null $privPem 私钥 PEM，仅公钥场景传 null
     * @param string|null $pubPem 公钥 PEM，传 null 时由构造器从私钥派生
     * @param string $curveName 已归一化的 OpenSSL 曲线名
     * @return self 新建的 ECC 实例
     * @throws InvalidArgumentException 密钥数据无法被 OpenSSL 加载时抛出
     * @access private
     */
    private static function constructFromPem(?string $privPem, ?string $pubPem, string $curveName): self
    {
        try {
            return new self($privPem, $pubPem, $curveName);
        } catch (RuntimeException $e) {
            throw new InvalidArgumentException('无效的JWK密钥数据: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 将 JWK 曲线名与 OpenSSL 别名归一化为 OpenSSL 内部曲线名
     *
     * 支持 P-256/P-384/P-521（RFC 7517 的 crv 命名）与 secp256r1 等别名；
     * 无法识别的名称原样返回，交由 validateCurve() 抛出明确的错误信息。
     *
     * @param string $curve 待归一化的曲线名
     * @return string OpenSSL 内部曲线名；未识别时返回原值
     * @access private
     */
    private static function normalizeCurveName(string $curve): string
    {
        return match ($curve) {
            'P-256', 'prime256v1', 'secp256r1' => 'prime256v1',
            'P-384', 'secp384r1' => 'secp384r1',
            'P-521', 'secp521r1' => 'secp521r1',
            'secp256k1' => 'secp256k1',
            default => $curve, // 交由 validateCurve 抛出明确错误
        };
    }

    /**
     * 将 OpenSSL 内部曲线名转换为 RFC 7517 的 crv 标准名
     *
     * 例如 prime256v1 → P-256；secp256k1 本身即为标准名，其余未识别名称
     * 原样返回，便于直接写入 JWK。
     *
     * @param string $curve OpenSSL 内部曲线名
     * @return string RFC 7517 的 crv 名称；未识别时返回原值
     * @access private
     */
    private static function standardCrvName(string $curve): string
    {
        return match ($curve) {
            'prime256v1' => 'P-256',
            'secp384r1' => 'P-384',
            'secp521r1' => 'P-521',
            default => $curve, // secp256k1 自身即标准名
        };
    }

    /**
     * 返回指定曲线的单个坐标字节长度
     *
     * 用于 JWK 的 x/y/d 定长补零：P-256 与 secp256k1 为 32，P-384 为 48，
     * P-521 为 66；未识别曲线按 32 处理（后续 DER 构造多半会失败）。
     *
     * @param string $curve OpenSSL 内部曲线名
     * @return int 坐标字节数
     * @access private
     */
    private static function ecCoordSizeByCurve(string $curve): int
    {
        return match ($curve) {
            'prime256v1', 'secp256k1' => 32,
            'secp384r1' => 48,
            'secp521r1' => 66,
            default => 32,
        };
    }

    /**
     * 返回曲线的 OID 点分字符串
     *
     * 覆盖 secp256r1(prime256v1)、secp384r1、secp521r1 与 secp256k1，
     * 供 DER 算法标识使用；不支持的曲线抛出异常而非回退默认值。
     *
     * @param string $curve OpenSSL 内部曲线名
     * @return string 点分形式的 OID，如 1.2.840.10045.3.1.7
     * @throws InvalidArgumentException 曲线不受支持时抛出
     * @access private
     */
    private static function curveOid(string $curve): string
    {
        return match ($curve) {
            'prime256v1' => '1.2.840.10045.3.1.7',
            'secp384r1' => '1.3.132.0.34',
            'secp521r1' => '1.3.132.0.35',
            'secp256k1' => '1.3.132.0.10',
            default => throw new InvalidArgumentException("不支持的曲线: {$curve}"),
        };
    }

    /**
     * DER 长度编码（definite form）
     *
     * 小于 128 时输出单字节；否则首字节为 0x80|后续字节数，后接大端长度。
     * 负数无合法 DER 表示，调用方须保证入参非负。
     *
     * @param int $length 内容字节数，须为非负整数
     * @return string DER 长度字段字节串
     * @access private
     */
    private static function derLength(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }
        $bytes = '';
        while ($length > 0) {
            $bytes = chr($length & 0xff) . $bytes;
            $length >>= 8;
        }
        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    /**
     * 将内容封装为 DER SEQUENCE
     *
     * @param string $content 已编码的子项字节串
     * @return string 0x30 ‖ 长度 ‖ 内容的 DER 字节串
     * @access private
     */
    private static function derSequence(string $content): string
    {
        return "\x30" . self::derLength(strlen($content)) . $content;
    }

    /**
     * 将内容封装为 DER OCTET STRING
     *
     * @param string $content 原始字节串
     * @return string 0x04 ‖ 长度 ‖ 内容的 DER 字节串
     * @access private
     */
    private static function derOctetString(string $content): string
    {
        return "\x04" . self::derLength(strlen($content)) . $content;
    }

    /**
     * 将无符号大端整数编码为 DER INTEGER
     *
     * 先去除前导零；若首字节最高位为 1，则补一个 0x00，避免被解析成负数。
     * 空输入按数值 0 编码。
     *
     * @param string $bytes 无符号大端字节串（如 JWK 的 d 值解码结果）
     * @return string 0x02 ‖ 长度 ‖ 整数的 DER 字节串
     * @access private
     */
    private static function derInteger(string $bytes): string
    {
        $bytes = ltrim($bytes, "\x00");
        if ($bytes === '') {
            $bytes = "\x00";
        }
        if ((ord($bytes[0]) & 0x80) !== 0) {
            $bytes = "\x00" . $bytes;
        }
        return "\x02" . self::derLength(strlen($bytes)) . $bytes;
    }

    /**
     * 将未压缩椭圆曲线点编码为 DER BIT STRING
     *
     * DER BIT STRING 需在内容前加一个字节表示未使用位数，此处固定为 0x00。
     *
     * @param string $bytes 未压缩点字节串（0x04 ‖ x ‖ y）
     * @return string 0x03 ‖ 长度 ‖ 0x00 ‖ 点的 DER 字节串
     * @access private
     */
    private static function derBitString(string $bytes): string
    {
        return "\x03" . self::derLength(strlen($bytes) + 1) . "\x00" . $bytes;
    }

    /**
     * 构造 context-specific 显式标签 [n] EXPLICIT
     *
     * 标签字节为 0xa0|n，仅支持 0-31 的单字节标签号，用于 SEC1 私钥中
     * 包裹曲线 OID 与公钥点。
     *
     * @param int $tag 标签号（0-31）
     * @param string $content 已编码的子项字节串
     * @return string 0xa0|tag ‖ 长度 ‖ 内容的 DER 字节串
     * @access private
     */
    private static function derContextExplicit(int $tag, string $content): string
    {
        return chr(0xa0 | $tag) . self::derLength(strlen($content)) . $content;
    }

    /**
     * 将点分 OID 编码为 DER OBJECT IDENTIFIER
     *
     * 前两段合并为 40*a+b 的首字节，其余各段按 base-128 变长编码（高位续行）。
     * 不足两段或含非数字字符时抛出异常。
     *
     * @param string $oid 点分形式的 OID，如 1.2.840.10045.2.1
     * @return string 0x06 ‖ 长度 ‖ OID 内容的 DER 字节串
     * @throws InvalidArgumentException OID 格式非法时抛出
     * @access private
     */
    private static function derOid(string $oid): string
    {
        $parts = explode('.', $oid);
        if (count($parts) < 2 || !ctype_digit(implode('', $parts))) {
            throw new InvalidArgumentException("无效的OID: {$oid}");
        }
        $first = (int) array_shift($parts) * 40 + (int) array_shift($parts);
        $bytes = chr($first);
        foreach ($parts as $part) {
            $value = (int) $part;
            $stack = [$value & 0x7f];
            $value >>= 7;
            while ($value > 0) {
                array_unshift($stack, ($value & 0x7f) | 0x80);
                $value >>= 7;
            }
            foreach ($stack as $b) {
                $bytes .= chr($b);
            }
        }
        return "\x06" . self::derLength(strlen($bytes)) . $bytes;
    }

    /**
     * 将 DER 字节串封装为 PEM 文本
     *
     * 按 64 字符折行的 Base64 输出，并在首尾加上指定标签的 BEGIN/END 行，
     * 可直接交给 OpenSSL 解析。
     *
     * @param string $der DER 编码的二进制内容
     * @param string $label PEM 标签，如 PUBLIC KEY、EC PRIVATE KEY
     * @return string 带换行的 PEM 文本
     * @access private
     */
    private static function pemEncode(string $der, string $label): string
    {
        return "-----BEGIN {$label}-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END {$label}-----\n";
    }

    /**
     * 解码 JWK 的 base64url 坐标并补齐到曲线坐标长度
     *
     * 先做 base64url 解码，长度超过坐标长度视为非法；不足时在左侧补零，
     * 保证 DER 中的定长大端整数格式。解码失败或结果为空时抛出异常。
     *
     * @param string $value JWK 中的 base64url 编码坐标（x/y/d）
     * @param int $coordSize 目标坐标字节数，可由 ecCoordSizeByCurve() 取得
     * @return string 定长大端坐标字节串
     * @throws InvalidArgumentException 解码失败、为空或超长时抛出
     * @access private
     */
    private static function decodeJwkCoord(string $value, int $coordSize): string
    {
        $bin = Base64::urlSafeDecode($value, true);
        if ($bin === false || $bin === '') {
            throw new InvalidArgumentException('无效的JWK坐标（base64url 解码失败）');
        }
        if (strlen($bin) > $coordSize) {
            throw new InvalidArgumentException('无效的JWK坐标长度');
        }
        return str_pad($bin, $coordSize, "\x00", STR_PAD_LEFT);
    }

    /**
     * 从 JWK 构建公钥 PEM（SPKI 格式）
     *
     * @param array $jwk JWK数据
     * @return string 公钥 PEM
     * @access private
     */
    private static function buildPublicKeyFromJWK(array $jwk): string
    {
        $curveName = self::normalizeCurveName((string) $jwk['crv']);
        $coordSize = self::ecCoordSizeByCurve($curveName);
        $x = self::decodeJwkCoord((string) $jwk['x'], $coordSize);
        $y = self::decodeJwkCoord((string) $jwk['y'], $coordSize);

        $algId = self::derSequence(
            self::derOid('1.2.840.10045.2.1') .   // id-ecPublicKey
            self::derOid(self::curveOid($curveName))
        );
        $subjectPublicKey = self::derBitString("\x04" . $x . $y);
        $spki = self::derSequence($algId . $subjectPublicKey);

        return self::pemEncode($spki, 'PUBLIC KEY');
    }

    /**
     * 从 JWK 构建私钥 PEM（SEC1 EC PRIVATE KEY，含 [0] 曲线 OID 与 [1] 公钥点）
     *
     * @param array $jwk JWK数据（须含 x/y/d）
     * @param string $curveName 已归一化的曲线名
     * @return string 私钥 PEM
     * @access private
     */
    private static function buildPrivateKeyFromJWK(array $jwk, string $curveName): string
    {
        $coordSize = self::ecCoordSizeByCurve($curveName);
        if (!isset($jwk['x'], $jwk['y'])) {
            throw new InvalidArgumentException('无效的JWK格式：包含私钥 d 时必须同时提供 x/y');
        }
        $d = self::decodeJwkCoord((string) $jwk['d'], $coordSize);
        $x = self::decodeJwkCoord((string) $jwk['x'], $coordSize);
        $y = self::decodeJwkCoord((string) $jwk['y'], $coordSize);

        $sec1 = self::derSequence(
            self::derInteger("\x01") .                                                    // version = 1
            self::derOctetString($d) .                                                    // privateKey = d
            self::derContextExplicit(0, self::derOid(self::curveOid($curveName))) .       // [0] 曲线 OID
            self::derContextExplicit(1, self::derBitString("\x04" . $x . $y))             // [1] 公钥点
        );
        return self::pemEncode($sec1, 'EC PRIVATE KEY');
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
     * 获取曲线的性能评级
     *
     * 按曲线在签名/验签速度上的大致表现返回中文评级：P-256 与 secp256k1
     * 为「高性能」，P-384 为「平衡」，P-521 为「高安全」；未识别曲线返回
     * 「未知」。评级仅用于展示，不参与安全判定。
     *
     * @return string 性能评级文案
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

        // 变更曲线意味着必须生成新曲线上的密钥对；先保存当前密钥用于失败回滚
        $oldCurve = $this->curveName;
        $oldPrivatePem = $this->privateKey !== null ? $this->exportPrivateKey() : null;
        $oldPublicPem = $this->publicKey !== null ? $this->exportPublicKey() : null;

        try {
            $this->curveName = $newCurveName;
            $this->generateKeyPair();
            $this->logDebug("曲线更改成功: {$oldCurve} -> {$newCurveName}");
        } catch (Exception $e) {
            // 回滚：恢复旧曲线名并重新载入旧密钥，
            // 否则实例会停留在"新曲线名 + 旧曲线密钥（或半生成状态）"的损坏状态
            $this->curveName = $oldCurve;
            $this->privateKey = null;
            $this->publicKey = null;
            if ($oldPrivatePem !== null) {
                $restored = openssl_pkey_get_private($oldPrivatePem);
                if ($restored !== false) {
                    $this->privateKey = $restored;
                    try {
                        $this->derivePublicKey();
                    } catch (Exception $ignored) {
                        // 公钥还原失败不影响私钥可用性
                    }
                }
            } elseif ($oldPublicPem !== null) {
                $restored = openssl_pkey_get_public($oldPublicPem);
                if ($restored !== false) {
                    $this->publicKey = $restored;
                }
            }
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
     * 获取当前曲线符合的标准与生态列表
     *
     * NIST 系列曲线返回 NIST / FIPS 186-4 / TLS 1.2/1.3，secp256k1 返回
     * SECG / Bitcoin / Ethereum；未识别曲线返回 ['未知']。
     *
     * @return array<int,string> 标准或生态名称列表，至少含一个元素
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
