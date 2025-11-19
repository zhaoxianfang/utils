<?php

namespace zxf\Util\Encrypt;

use InvalidArgumentException;
use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * RSA 非对称加密解密类
 * 使用 RSA 公钥密码系统进行加密、解密、数字签名和验证操作
 * 完全兼容 PHP 8.2+，提供工业级的非对称加密解决方案
 */
class RSA
{
    /**
     * @var OpenSSLAsymmetricKey|resource|false 私钥资源句柄
     */
    private $privateKey;

    /**
     * @var OpenSSLAsymmetricKey|resource|false 公钥资源句柄
     */
    private $publicKey;

    /**
     * @var int 加密填充方式
     */
    private int $padding;

    /**
     * 支持的密钥长度枚举
     */
    private const KEY_SIZES = [
        512 => '已不安全，仅用于测试和学习',
        1024 => '已不安全，不推荐用于生产环境',
        2048 => '当前最低安全标准，广泛使用',
        3072 => '推荐安全级别，中长期安全',
        4096 => '高安全要求，性能开销较大',
        8192 => '极高安全要求，性能开销很大'
    ];

    /**
     * 支持的填充方式枚举
     */
    private const PADDING_SCHEMES = [
        'PKCS1_PADDING' => [
            'value' => OPENSSL_PKCS1_PADDING,
            'description' => 'PKCS#1 v1.5填充方案',
            'security' => '中等',
            'compatibility' => '高',
            'recommended' => false
        ],
        'OAEP_PADDING' => [
            'value' => OPENSSL_PKCS1_OAEP_PADDING,
            'description' => 'OAEP填充方案（最优非对称加密填充）',
            'security' => '高',
            'compatibility' => '中等',
            'recommended' => true
        ],
    ];

    /**
     * 支持的哈希算法枚举
     */
    private const HASH_ALGORITHMS = [
        'sha1' => [
            'description' => 'SHA-1哈希算法',
            'output_bits' => 160,
            'security' => '已破解，不安全',
            'recommended' => false
        ],
        'sha224' => [
            'description' => 'SHA-224哈希算法',
            'output_bits' => 224,
            'security' => '安全性一般',
            'recommended' => false
        ],
        'sha256' => [
            'description' => 'SHA-256哈希算法',
            'output_bits' => 256,
            'security' => '安全，广泛使用',
            'recommended' => true
        ],
        'sha384' => [
            'description' => 'SHA-384哈希算法',
            'output_bits' => 384,
            'security' => '高安全性',
            'recommended' => true
        ],
        'sha512' => [
            'description' => 'SHA-512哈希算法',
            'output_bits' => 512,
            'security' => '最高安全性',
            'recommended' => true
        ],
    ];

    /**
     * 构造函数
     */
    public function __construct(int $padding = OPENSSL_PKCS1_OAEP_PADDING)
    {
        // 验证填充方式是否在支持的方案中
        $validPaddingValues = array_column(self::PADDING_SCHEMES, 'value');
        if (!in_array($padding, $validPaddingValues, true)) {
            throw new InvalidArgumentException(
                '不支持的RSA填充方式。支持的填充方式: ' .
                implode(', ', array_keys(self::PADDING_SCHEMES))
            );
        }

        $this->privateKey = false;
        $this->publicKey = false;
        $this->padding = $padding;
    }

    /**
     * 从文件加载RSA密钥对
     */
    public function loadFromFile(
        ?string $privateKeyPath = null,
        ?string $publicKeyPath = null,
        string  $passphrase = ''
    ): void {
        // 加载私钥文件
        if ($privateKeyPath !== null) {
            if (!file_exists($privateKeyPath)) {
                throw new RuntimeException('RSA私钥文件不存在: ' . $privateKeyPath);
            }

            if (!is_readable($privateKeyPath)) {
                throw new RuntimeException('RSA私钥文件不可读: ' . $privateKeyPath);
            }

            $privateKeyContent = file_get_contents($privateKeyPath);
            if ($privateKeyContent === false) {
                throw new RuntimeException('无法读取RSA私钥文件: ' . $privateKeyPath);
            }

            $this->loadPrivateKeyFromString($privateKeyContent, $passphrase);
        }

        // 加载公钥文件
        if ($publicKeyPath !== null) {
            if (!file_exists($publicKeyPath)) {
                throw new RuntimeException('RSA公钥文件不存在: ' . $publicKeyPath);
            }

            if (!is_readable($publicKeyPath)) {
                throw new RuntimeException('RSA公钥文件不可读: ' . $publicKeyPath);
            }

            $publicKeyContent = file_get_contents($publicKeyPath);
            if ($publicKeyContent === false) {
                throw new RuntimeException('无法读取RSA公钥文件: ' . $publicKeyPath);
            }

            $this->loadPublicKeyFromString($publicKeyContent);
        }
    }

    /**
     * 从字符串加载RSA密钥对
     */
    public function loadFromString(
        ?string $privateKeyString = null,
        ?string $publicKeyString = null,
        string  $passphrase = ''
    ): void {
        // 从字符串加载私钥
        if ($privateKeyString !== null) {
            $this->loadPrivateKeyFromString($privateKeyString, $passphrase);
        }

        // 从字符串加载公钥
        if ($publicKeyString !== null) {
            $this->loadPublicKeyFromString($publicKeyString);
        }
    }

    /**
     * 从字符串加载私钥
     */
    private function loadPrivateKeyFromString(string $privateKeyString, string $passphrase = ''): void
    {
        $privateKeyString = trim($privateKeyString);
        $this->privateKey = openssl_pkey_get_private($privateKeyString, $passphrase);

        if ($this->privateKey === false) {
            throw new RuntimeException('RSA私钥字符串加载失败: ' . $this->getOpenSSLError());
        }

        $details = openssl_pkey_get_details($this->privateKey);
        if ($details === false || $details['type'] !== OPENSSL_KEYTYPE_RSA) {
            throw new RuntimeException('提供的字符串不是有效的RSA私钥');
        }
    }

    /**
     * 从字符串加载公钥
     */
    private function loadPublicKeyFromString(string $publicKeyString): void
    {
        $publicKeyString = trim($publicKeyString);
        $this->publicKey = openssl_pkey_get_public($publicKeyString);

        if ($this->publicKey === false) {
            throw new RuntimeException('RSA公钥字符串加载失败: ' . $this->getOpenSSLError());
        }

        $details = openssl_pkey_get_details($this->publicKey);
        if ($details === false || $details['type'] !== OPENSSL_KEYTYPE_RSA) {
            throw new RuntimeException('提供的字符串不是有效的RSA公钥');
        }
    }

    /**
     * 生成RSA密钥对
     */
    public static function generateKeyPair(
        int   $keySize = 2048,
        array $additionalConfig = []
    ): array {
        // 验证密钥长度
        if (!array_key_exists($keySize, self::KEY_SIZES)) {
            throw new InvalidArgumentException(
                '不支持的RSA密钥长度: ' . $keySize .
                '。支持的密钥长度: ' . implode(', ', array_keys(self::KEY_SIZES))
            );
        }

        // 准备配置参数
        $config = array_merge([
            "digest_alg" => "sha256",
            "private_key_bits" => $keySize,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        ], $additionalConfig);

        // 生成密钥对
        $keyPair = openssl_pkey_new($config);
        if ($keyPair === false) {
            throw new RuntimeException('RSA密钥对生成失败: ' . self::getStaticOpenSSLError());
        }

        // 导出私钥
        $privateKey = '';
        $exportSuccess = openssl_pkey_export($keyPair, $privateKey, null, $config);
        if (!$exportSuccess) {
            throw new RuntimeException('RSA私钥导出失败: ' . self::getStaticOpenSSLError());
        }

        // 获取公钥
        $publicKeyDetails = openssl_pkey_get_details($keyPair);
        if ($publicKeyDetails === false) {
            throw new RuntimeException('无法获取RSA公钥详情: ' . self::getStaticOpenSSLError());
        }

        $publicKey = $publicKeyDetails['key'];
        $actualKeySize = $publicKeyDetails['bits'];
        $fingerprint = self::calculateKeyFingerprint($publicKey);

        // 在 PHP 8.0+ 中，OpenSSL 资源会自动垃圾回收，不需要手动释放
        // 只需取消引用即可
        unset($keyPair);

        return [
            'private_key' => $privateKey,
            'public_key' => $publicKey,
            'key_size' => $actualKeySize,
            'key_type' => OPENSSL_KEYTYPE_RSA,
            'fingerprint' => $fingerprint,
            'security_level' => self::KEY_SIZES[$keySize]
        ];
    }

    /**
     * 计算公钥指纹
     */
    private static function calculateKeyFingerprint(string $publicKey): string
    {
        $publicKeyClean = preg_replace('/-----(BEGIN|END) PUBLIC KEY-----/', '', $publicKey);
        $publicKeyClean = preg_replace('/\s+/', '', $publicKeyClean);
        $publicKeyBinary = base64_decode($publicKeyClean);

        if ($publicKeyBinary === false) {
            return 'invalid-key';
        }

        return hash('sha256', $publicKeyBinary);
    }

    /**
     * 使用公钥加密数据
     */
    public function encrypt(string $data): string
    {
        if ($this->publicKey === false) {
            throw new RuntimeException('公钥未加载，无法执行加密操作。请先使用loadFromFile()或loadFromString()方法加载公钥。');
        }

        $maxDataLength = $this->getMaxEncryptionLength();
        if (strlen($data) > $maxDataLength) {
            throw new InvalidArgumentException(
                '加密数据长度超过RSA限制。最大允许 ' . $maxDataLength .
                ' 字节，实际 ' . strlen($data) . ' 字节。建议使用混合加密方案。'
            );
        }

        $encrypted = '';
        $success = openssl_public_encrypt(
            $data,
            $encrypted,
            $this->publicKey,
            $this->padding
        );

        if (!$success) {
            throw new RuntimeException('RSA加密失败: ' . $this->getOpenSSLError());
        }

        return base64_encode($encrypted);
    }

    /**
     * 计算最大加密长度
     */
    private function getMaxEncryptionLength(): int
    {
        if ($this->publicKey === false) {
            return 0;
        }

        $keyDetails = openssl_pkey_get_details($this->publicKey);
        if ($keyDetails === false) {
            return 0;
        }

        $keySize = $keyDetails['bits'];
        $keyBytes = (int)($keySize / 8);

        if ($this->padding === OPENSSL_PKCS1_OAEP_PADDING) {
            return $keyBytes - (2 * 32) - 2;
        } else {
            return $keyBytes - 11;
        }
    }

    /**
     * 使用私钥解密数据
     */
    public function decrypt(string $encryptedData): string
    {
        if ($this->privateKey === false) {
            throw new RuntimeException('私钥未加载，无法执行解密操作。请先使用loadFromFile()或loadFromString()方法加载私钥。');
        }

        $encryptedRaw = base64_decode($encryptedData, true);
        if ($encryptedRaw === false) {
            throw new InvalidArgumentException('Base64解码失败：加密数据格式不正确');
        }

        $keyDetails = openssl_pkey_get_details($this->privateKey);
        if ($keyDetails !== false && strlen($encryptedRaw) !== (int)($keyDetails['bits'] / 8)) {
            throw new InvalidArgumentException('加密数据长度与密钥长度不匹配');
        }

        $decrypted = '';
        $success = openssl_private_decrypt(
            $encryptedRaw,
            $decrypted,
            $this->privateKey,
            $this->padding
        );

        if (!$success) {
            throw new RuntimeException('RSA解密失败: ' . $this->getOpenSSLError());
        }

        return $decrypted;
    }

    /**
     * 使用私钥对数据进行数字签名
     */
    public function sign(string $data, string $algorithm = 'sha256'): string
    {
        if ($this->privateKey === false) {
            throw new RuntimeException('私钥未加载，无法执行签名操作。请先使用loadFromFile()或loadFromString()方法加载私钥。');
        }

        if (!array_key_exists($algorithm, self::HASH_ALGORITHMS)) {
            throw new InvalidArgumentException(
                '不支持的签名算法: ' . $algorithm .
                '。支持的算法: ' . implode(', ', array_keys(self::HASH_ALGORITHMS)) .
                '。推荐使用sha256、sha384或sha512。'
            );
        }

        $algorithmInfo = self::HASH_ALGORITHMS[$algorithm];
        if (!$algorithmInfo['recommended']) {
            trigger_error(
                '使用不推荐的哈希算法进行签名: ' . $algorithm .
                '。建议使用更安全的算法如sha256、sha384或sha512。',
                E_USER_WARNING
            );
        }

        $signature = '';
        $success = openssl_sign(
            $data,
            $signature,
            $this->privateKey,
            $algorithm
        );

        if (!$success) {
            throw new RuntimeException('数字签名失败: ' . $this->getOpenSSLError());
        }

        return base64_encode($signature);
    }

    /**
     * 使用公钥验证数字签名
     */
    public function verify(string $data, string $signature, string $algorithm = 'sha256'): bool
    {
        if ($this->publicKey === false) {
            throw new RuntimeException('公钥未加载，无法执行签名验证。请先使用loadFromFile()或loadFromString()方法加载公钥。');
        }

        if (!array_key_exists($algorithm, self::HASH_ALGORITHMS)) {
            throw new InvalidArgumentException(
                '不支持的验证算法: ' . $algorithm .
                '。支持的算法: ' . implode(', ', array_keys(self::HASH_ALGORITHMS))
            );
        }

        $signatureRaw = base64_decode($signature, true);
        if ($signatureRaw === false) {
            throw new InvalidArgumentException('Base64解码失败：签名格式不正确。请确保签名是有效的Base64编码。');
        }

        $result = openssl_verify(
            $data,
            $signatureRaw,
            $this->publicKey,
            $algorithm
        );

        if ($result === -1) {
            throw new RuntimeException('签名验证过程出错: ' . $this->getOpenSSLError());
        }

        return $result === 1;
    }

    /**
     * 获取当前加载的密钥详细信息
     */
    public function getKeyDetails(): ?array
    {
        $key = $this->publicKey !== false ? $this->publicKey : $this->privateKey;

        if ($key === false) {
            return null;
        }

        $details = openssl_pkey_get_details($key);
        if ($details === false) {
            return null;
        }

        $details['has_private'] = $this->privateKey !== false;
        $details['has_public'] = $this->publicKey !== false;
        $details['padding_scheme'] = $this->getPaddingSchemeName();

        return $details;
    }

    /**
     * 获取填充方案名称
     */
    private function getPaddingSchemeName(): string
    {
        foreach (self::PADDING_SCHEMES as $name => $scheme) {
            if ($scheme['value'] === $this->padding) {
                return $name;
            }
        }
        return 'UNKNOWN';
    }

    /**
     * 检查是否已加载私钥
     */
    public function hasPrivateKey(): bool
    {
        return $this->privateKey !== false;
    }

    /**
     * 检查是否已加载公钥
     */
    public function hasPublicKey(): bool
    {
        return $this->publicKey !== false;
    }

    /**
     * 获取当前使用的填充方式信息
     */
    public function getPaddingInfo(): array
    {
        foreach (self::PADDING_SCHEMES as $name => $scheme) {
            if ($scheme['value'] === $this->padding) {
                return array_merge(['name' => $name], $scheme);
            }
        }

        return [
            'name' => 'UNKNOWN',
            'value' => $this->padding,
            'description' => '未知填充方案',
            'security' => '未知',
            'compatibility' => '未知',
            'recommended' => false
        ];
    }

    /**
     * 获取所有支持的哈希算法信息
     */
    public static function getHashAlgorithmsInfo(): array
    {
        return self::HASH_ALGORITHMS;
    }

    /**
     * 获取所有支持的密钥长度信息
     */
    public static function getKeySizesInfo(): array
    {
        return self::KEY_SIZES;
    }

    /**
     * 获取 OpenSSL 错误信息
     */
    private function getOpenSSLError(): string
    {
        $errorMessages = [];
        while ($errorMessage = openssl_error_string()) {
            $errorMessages[] = $errorMessage;
        }
        return $errorMessages ? implode('; ', $errorMessages) : '未知OpenSSL错误';
    }

    /**
     * 获取 OpenSSL 错误信息（静态方法）
     */
    private static function getStaticOpenSSLError(): string
    {
        $errorMessages = [];
        while ($errorMessage = openssl_error_string()) {
            $errorMessages[] = $errorMessage;
        }
        return $errorMessages ? implode('; ', $errorMessages) : '未知OpenSSL错误';
    }

    /**
     * 析构函数
     * 在 PHP 8.0+ 中，OpenSSL 资源会自动垃圾回收
     * 只需要取消引用即可
     */
    public function __destruct()
    {
        // 在 PHP 8.0+ 中，OpenSSLAsymmetricKey 对象会自动垃圾回收
        // 在 PHP 7.x 中，资源也会在对象销毁时自动释放
        // 不需要手动调用 openssl_pkey_free

        $this->privateKey = false;
        $this->publicKey = false;
    }
}
