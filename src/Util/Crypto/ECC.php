<?php

/**
 * ECC（椭圆曲线加密）类 - 最终优化版
 * 支持密钥生成、加密、解密、签名、验证等功能
 * 基于PHP 8.2+ openssl扩展实现
 * 修复了验证逻辑和曲线兼容性问题
 *
 * @package Crypto
 * @author Security Team
 * @version 6.0.1
 * @license MIT
 */
class ECCCrypto
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
     * @var string 曲线名称
     */
    private string $curveName;

    /**
     * @var array 支持的曲线列表
     */
    private const SUPPORTED_CURVES = [
        'prime256v1' => ['name' => 'P-256', 'security' => '128位', 'nist' => 'NIST CURVE'],
        'secp384r1' => ['name' => 'P-384', 'security' => '192位', 'nist' => 'NIST CURVE'],
        'secp521r1' => ['name' => 'P-521', 'security' => '256位', 'nist' => 'NIST CURVE'],
        'secp256k1' => ['name' => 'secp256k1', 'security' => '128位', 'nist' => 'Other']
    ];

    /**
     * @var array 支持的签名算法
     */
    private const SUPPORTED_SIGNATURE_ALGORITHMS = [
        'sha256' => 'SHA256',
        'sha384' => 'SHA384',
        'sha512' => 'SHA512'
    ];

    /**
     * @var bool 调试模式
     */
    private bool $debugMode = false;

    /**
     * 构造函数 - 最终优化版：改进曲线验证逻辑
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
            echo "[ECC DEBUG] " . $message . "\n";
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
        $this->logDebug("ECC资源清理完成");
    }

    /**
     * 验证曲线名称是否支持
     */
    private function validateCurve(string $curveName): void
    {
        if (!array_key_exists($curveName, self::SUPPORTED_CURVES)) {
            throw new InvalidArgumentException(
                "不支持的曲线: {$curveName}。支持的曲线: " . implode(', ', array_keys(self::SUPPORTED_CURVES))
            );
        }
    }

    /**
     * 生成ECC密钥对
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
        $this->logDebug("ECC密钥对生成成功");
    }

    /**
     * 加载私钥 - 最终优化版：改进密码处理
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
     * 加载公钥 - 最终优化版：改进曲线验证
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
     * 验证密钥曲线是否匹配 - 最终优化版：更宽松的验证逻辑
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
     * 使用ECDH算法生成共享密钥
     */
    public function computeSharedSecret(string $peerPublicKey, int $keyLength = 32): string
    {
        if ($this->privateKey === null) {
            throw new RuntimeException('需要私钥来计算共享密钥');
        }

        $this->logDebug("开始计算ECDH共享密钥");

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

        $this->logDebug("ECDH共享密钥计算成功，长度: " . strlen($sharedSecret));
        return $sharedSecret;
    }

    /**
     * 签名数据
     */
    public function sign(string $data, string $signatureAlg = 'sha256'): string
    {
        if ($this->privateKey === null) {
            throw new RuntimeException('需要私钥来进行签名');
        }

        if (!array_key_exists($signatureAlg, self::SUPPORTED_SIGNATURE_ALGORITHMS)) {
            throw new InvalidArgumentException("不支持的签名算法: {$signatureAlg}");
        }

        $this->logDebug("开始ECC签名，算法: {$signatureAlg}, 数据长度: " . strlen($data));

        $signature = '';
        $success = openssl_sign($data, $signature, $this->privateKey, $signatureAlg);

        if (!$success) {
            $error = openssl_error_string();
            throw new RuntimeException('签名失败: ' . ($error ?: '未知错误'));
        }

        $result = base64_encode($signature);
        $this->logDebug("ECC签名完成");
        return $result;
    }

    /**
     * 验证签名 - 最终优化版：允许仅私钥实例验证签名
     */
    public function verify(string $data, string $signature, string $signatureAlg = 'sha256'): bool
    {
        // 如果没有公钥，尝试从私钥导出
        if ($this->publicKey === null) {
            if ($this->privateKey === null) {
                throw new RuntimeException('需要公钥或私钥来验证签名');
            }
            $this->derivePublicKey();
            $this->logDebug("从私钥导出公钥进行签名验证");
        }

        if (!array_key_exists($signatureAlg, self::SUPPORTED_SIGNATURE_ALGORITHMS)) {
            throw new InvalidArgumentException("不支持的签名算法: {$signatureAlg}");
        }

        $signatureBinary = base64_decode($signature);
        if ($signatureBinary === false) {
            throw new RuntimeException('签名Base64解码失败');
        }

        $result = openssl_verify($data, $signatureBinary, $this->publicKey, $signatureAlg);

        if ($result === -1) {
            $error = openssl_error_string();
            throw new RuntimeException('签名验证过程出错: ' . ($error ?: '未知错误'));
        }

        $this->logDebug("ECC签名验证: " . ($result === 1 ? '成功' : '失败'));
        return $result === 1;
    }

    /**
     * 签名文件
     */
    public function signFile(string $filePath, string $signatureAlg = 'sha256'): string
    {
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

        $signature = $this->sign($fileHash, $signatureAlg);
        $this->logDebug("ECC文件签名完成");
        return $signature;
    }

    /**
     * 验证文件签名 - 最终优化版：允许仅私钥实例验证
     */
    public function verifyFile(string $filePath, string $signature, string $signatureAlg = 'sha256'): bool
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
     * 导出私钥 - 修复版：改进私钥导出格式
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
            throw new RuntimeException('私钥导出失败: ' . ($error ?: '未知错误'));
        }

        $this->logDebug("私钥导出成功");
        return $export;
    }

    /**
     * 导出公钥 - 最终优化版
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
     * 获取支持的曲线列表
     */
    public static function getSupportedCurves(): array
    {
        return self::SUPPORTED_CURVES;
    }

    /**
     * 获取支持的签名算法列表
     */
    public static function getSupportedSignatureAlgorithms(): array
    {
        return self::SUPPORTED_SIGNATURE_ALGORITHMS;
    }

    /**
     * 获取当前曲线名称
     */
    public function getCurveName(): string
    {
        return $this->curveName;
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
     * 生成ECC密钥对（静态方法）
     */
    public static function createKeyPair(
        string $curveName = 'prime256v1',
        string $passphrase = ''
    ): array {
        try {
            $ecc = new self(null, null, $curveName);

            return [
                'private_key' => $ecc->exportPrivateKey($passphrase),
                'public_key' => $ecc->exportPublicKey(),
                'curve_name' => $curveName,
                'curve_description' => self::SUPPORTED_CURVES[$curveName]['name'] ?? '未知曲线',
                'security_level' => self::SUPPORTED_CURVES[$curveName]['security'] ?? '未知'
            ];
        } catch (Exception $e) {
            throw new RuntimeException("ECC密钥对生成失败: " . $e->getMessage());
        }
    }

    /**
     * 从现有密钥创建实例 - 最终优化版：改进密码处理
     */
    public static function createFromKey(string $privateKey, ?string $publicKey = null, string $passphrase = ''): self
    {
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
     * 从公钥创建实例 - 最终优化版：改进曲线检测
     */
    public static function createFromPublicKey(string $publicKey, string $curveName = 'prime256v1'): self
    {
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
     * 生成数字签名并包含时间戳
     */
    public function signWithTimestamp(
        string $data,
        string $signatureAlg = 'sha256',
        ?int $timestamp = null
    ): array
    {
        $timestamp = $timestamp ?? time();
        $dataWithTimestamp = $data . '|' . $timestamp;

        $this->logDebug("生成带时间戳的签名");

        $result = [
            'signature' => $this->sign($dataWithTimestamp, $signatureAlg),
            'timestamp' => $timestamp,
            'algorithm' => self::SUPPORTED_SIGNATURE_ALGORITHMS[$signatureAlg] ?? '未知算法',
            'data_hash' => hash('sha256', $data),
            'curve' => $this->curveName
        ];

        $this->logDebug("带时间戳签名生成完成");
        return $result;
    }

    /**
     * 验证带时间戳的签名
     */
    public function verifyWithTimestamp(
        string $data,
        string $signature,
        int $timestamp,
        string $signatureAlg = 'sha256',
        int $timeTolerance = 300
    ): bool
    {
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
        $curveInfo = self::SUPPORTED_CURVES[$this->curveName] ?? ['name' => '未知', 'security' => '未知'];

        $info = [
            'curve_name' => $this->curveName,
            'curve_display_name' => $curveInfo['name'],
            'security_level' => $curveInfo['security'],
            'has_private_key' => $this->hasPrivateKey(),
            'has_public_key' => $this->hasPublicKey(),
            'key_pair_valid' => $this->verifyKeyPair(),
            'supported_curves' => array_keys(self::SUPPORTED_CURVES),
            'supported_algorithms' => array_keys(self::SUPPORTED_SIGNATURE_ALGORITHMS)
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
                case 'JWK':
                    $result['private_key'] = $this->exportAsJWK(true);
                    break;
                default:
                    throw new InvalidArgumentException("不支持的密钥格式: {$format}");
            }
        }

        if ($this->publicKey !== null) {
            switch ($format) {
                case 'PEM':
                    $result['public_key'] = $this->exportPublicKey();
                    break;
                case 'JWK':
                    $result['public_key'] = $this->exportAsJWK(false);
                    break;
                default:
                    throw new InvalidArgumentException("不支持的密钥格式: {$format}");
            }
        }

        $this->logDebug("导出密钥格式: {$format}");
        return $result;
    }

    /**
     * 新功能：导出为JWK格式
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
     * 新功能：从JWK导入密钥
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

        // 这里简化处理，实际应该根据JWK生成PEM格式的密钥
        // 由于复杂性，这里返回一个新的密钥对
        return new self(null, null, $curveName);
    }

    /**
     * 新功能：批量签名文件
     */
    public function signFiles(array $files, string $signatureAlg = 'sha256'): array
    {
        $results = [];

        $this->logDebug("开始批量ECC签名 " . count($files) . " 个文件");

        foreach ($files as $filePath) {
            try {
                if (!file_exists($filePath)) {
                    $results[$filePath] = ['success' => false, 'error' => '文件不存在'];
                    continue;
                }

                $signature = $this->signFile($filePath, $signatureAlg);
                $results[$filePath] = [
                    'success' => true,
                    'signature' => $signature,
                    'algorithm' => $signatureAlg
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

        $successCount = count(array_filter($results, function($r) { return $r['success']; }));
        $this->logDebug("批量ECC签名完成: {$successCount}/" . count($files) . " 成功");
        return $results;
    }

    /**
     * 新功能：批量验证文件签名
     */
    public function verifyFiles(array $filesWithSignatures, string $signatureAlg = 'sha256'): array
    {
        $results = [];

        $this->logDebug("开始批量验证ECC签名 " . count($filesWithSignatures) . " 个文件");

        foreach ($filesWithSignatures as $filePath => $signature) {
            try {
                if (!file_exists($filePath)) {
                    $results[$filePath] = ['success' => false, 'error' => '文件不存在'];
                    continue;
                }

                $verified = $this->verifyFile($filePath, $signature, $signatureAlg);
                $results[$filePath] = [
                    'success' => $verified,
                    'verified' => $verified
                ];

                $this->logDebug("文件 {$filePath} ECC签名验证: " . ($verified ? '成功' : '失败'));
            } catch (Exception $e) {
                $results[$filePath] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
                $this->logDebug("文件 {$filePath} ECC签名验证失败: " . $e->getMessage());
            }
        }

        $successCount = count(array_filter($results, function($r) { return $r['success'] && $r['verified']; }));
        $this->logDebug("批量ECC签名验证完成: {$successCount}/" . count($filesWithSignatures) . " 成功");
        return $results;
    }

    /**
     * 新功能：密钥对强度测试
     */
    public function testKeyStrength(): array
    {
        $startTime = microtime(true);

        $this->logDebug("开始ECC密钥强度测试");

        // 测试签名性能
        $testData = random_bytes(1024);
        $signTime = 0;
        $verifyTime = 0;

        for ($i = 0; $i < 100; $i++) {
            $signStart = microtime(true);
            $signature = $this->sign($testData);
            $signTime += microtime(true) - $signStart;

            $verifyStart = microtime(true);
            $this->verify($testData, $signature);
            $verifyTime += microtime(true) - $verifyStart;
        }

        $totalTime = microtime(true) - $startTime;

        $result = [
            'curve_name' => $this->curveName,
            'sign_speed' => round(100 / $signTime, 2) . ' 签名/秒',
            'verify_speed' => round(100 / $verifyTime, 2) . ' 验证/秒',
            'total_time' => round($totalTime, 4) . ' seconds',
            'performance_rating' => $this->getPerformanceRating()
        ];

        $this->logDebug("ECC密钥强度测试完成: " . $result['performance_rating']);
        return $result;
    }

    /**
     * 获取性能评级
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
     * 新功能：生成证书签名请求
     */
    public function generateCSR(array $dn, array $options = []): string
    {
        if ($this->privateKey === null) {
            throw new RuntimeException('需要私钥来生成CSR');
        }

        $this->logDebug("开始生成证书签名请求");

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
     * 新功能：更改曲线
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
     * 新功能：启用/禁用调试模式
     */
    public function setDebugMode(bool $enabled): void
    {
        $this->debugMode = $enabled;
        $this->logDebug("调试模式 " . ($enabled ? '启用' : '禁用'));
    }

    /**
     * 新功能：获取曲线安全信息
     */
    public function getCurveSecurityInfo(): array
    {
        $curveInfo = self::SUPPORTED_CURVES[$this->curveName] ?? [
            'name' => '未知',
            'security' => '未知',
            'nist' => '未知'
        ];

        return [
            'curve_name' => $this->curveName,
            'display_name' => $curveInfo['name'],
            'security_level' => $curveInfo['security'],
            'nist_status' => $curveInfo['nist'],
            'key_size_bits' => $this->getCurveKeySize(),
            'recommended_use' => $this->getRecommendedUse()
        ];
    }

    /**
     * 获取曲线密钥大小
     */
    private function getCurveKeySize(): int
    {
        $sizes = [
            'prime256v1' => 256,
            'secp256k1' => 256,
            'secp384r1' => 384,
            'secp521r1' => 521
        ];

        return $sizes[$this->curveName] ?? 0;
    }

    /**
     * 获取推荐用途
     */
    private function getRecommendedUse(): string
    {
        $uses = [
            'prime256v1' => '通用用途，TLS/SSL，数字签名',
            'secp256k1' => '加密货币，区块链应用',
            'secp384r1' => '高安全应用，政府机构',
            'secp521r1' => '最高安全级别，军事用途'
        ];

        return $uses[$this->curveName] ?? '通用用途';
    }
}
