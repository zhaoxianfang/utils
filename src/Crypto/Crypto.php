<?php

declare(strict_types=1);

namespace zxf\Utils\Crypto;

/**
 * 统一加密门面类（Crypto Facade）
 * 提供静态快捷方法，统一访问所有加密功能，无需手动实例化具体加密类
 *
 * @package Crypto
 * @version 1.0.0
 * @license MIT
 */
class Crypto
{
    // ========== AES 对称加密快捷方法 ==========

    /**
     * 使用 AES 算法加密数据
     *
     * @param string $data   待加密的明文数据
     * @param string $key    加密密钥（长度需与加密方法匹配）
     * @param string $method 加密方法，如 aes-256-gcm（默认）、aes-128-cbc 等
     * @return string 加密后的密文（通常包含 IV 和认证标签）
     * @throws \RuntimeException 当加密失败时抛出
     * @see AES::encrypt()
     */
    public static function aesEncrypt(string $data, string $key, string $method = 'aes-256-gcm'): string
    {
        $aes = new AES($key, $method);
        return $aes->encrypt($data);
    }

    /**
     * 使用 AES 算法解密数据
     *
     * @param string $data   待解密的密文
     * @param string $key    解密密钥
     * @param string $method 加密方法，必须与加密时一致
     * @return string 解密后的明文
     * @throws \RuntimeException 当解密失败或数据被篡改时抛出
     * @see AES::decrypt()
     */
    public static function aesDecrypt(string $data, string $key, string $method = 'aes-256-gcm'): string
    {
        $aes = new AES($key, $method);
        return $aes->decrypt($data);
    }

    /**
     * 为指定 AES 方法生成随机密钥
     *
     * @param string $method 加密方法，默认为 aes-256-gcm
     * @return string 生成的随机密钥（二进制字符串）
     * @see AES::generateKey()
     */
    public static function aesGenerateKey(string $method = 'aes-256-gcm'): string
    {
        return AES::generateKey($method);
    }

    // ========== RSA 非对称加密快捷方法 ==========

    /**
     * 生成 RSA 密钥对
     *
     * @param int $keySize 密钥位数，默认为 2048（推荐），最低 1024
     * @return array{private:string,public:string} 包含私钥和公钥的数组
     * @throws \RuntimeException 当密钥生成失败时抛出
     * @see RSA::createKeyPair()
     */
    public static function rsaCreateKeyPair(int $keySize = 2048): array
    {
        return RSA::createKeyPair($keySize);
    }

    /**
     * 使用 RSA 公钥加密数据
     *
     * @param string $publicKeyPem 公钥 PEM 格式字符串
     * @param string $data         待加密的明文数据（长度受密钥大小限制）
     * @param int    $padding      填充模式，默认为 OPENSSL_PKCS1_OAEP_PADDING
     * @return string 加密后的密文
     * @throws \RuntimeException 当加密失败时抛出
     * @see RSA::encrypt()
     */
    public static function rsaEncrypt(string $data, string $publicKeyPem, int $padding = OPENSSL_PKCS1_OAEP_PADDING): string
    {
        $rsa = RSA::createFromPublicKey($publicKeyPem);
        return $rsa->encrypt($data, $padding);
    }

    /**
     * 使用 RSA 私钥解密数据
     *
     * @param string $privateKeyPem 私钥 PEM 格式字符串
     * @param string $data          待解密的密文
     * @param int    $padding       填充模式，必须与加密时一致
     * @return string 解密后的明文
     * @throws \RuntimeException 当解密失败时抛出
     * @see RSA::decrypt()
     */
    public static function rsaDecrypt(string $data, string $privateKeyPem, int $padding = OPENSSL_PKCS1_OAEP_PADDING): string
    {
        $rsa = RSA::createFromKey($privateKeyPem);
        return $rsa->decrypt($data, $padding);
    }

    /**
     * 使用 RSA 私钥对数据进行数字签名
     *
     * @param string $privateKeyPem 私钥 PEM 格式字符串
     * @param string $data          待签名的数据
     * @param string $algorithm     哈希算法，如 sha256（默认）、sha512 等
     * @return string 签名结果（二进制字符串）
     * @throws \RuntimeException 当签名失败时抛出
     * @see RSA::sign()
     */
    public static function rsaSign(string $data, string $privateKeyPem, string $algorithm = 'sha256'): string
    {
        $rsa = RSA::createFromKey($privateKeyPem);
        return $rsa->sign($data, $algorithm);
    }

    /**
     * 使用 RSA 公钥验证数字签名
     *
     * @param string $publicKeyPem 公钥 PEM 格式字符串
     * @param string $data         原始数据
     * @param string $signature    签名结果（二进制字符串）
     * @param string $algorithm    哈希算法，必须与签名时一致
     * @return bool 签名有效返回 true，无效返回 false
     * @see RSA::verify()
     */
    public static function rsaVerify(string $data, string $signature, string $publicKeyPem, string $algorithm = 'sha256'): bool
    {
        $rsa = RSA::createFromPublicKey($publicKeyPem);
        return $rsa->verify($data, $signature, $algorithm);
    }

    // ========== ECC 椭圆曲线快捷方法 ==========

    /**
     * 生成 ECC 密钥对
     *
     * @param string $curve 椭圆曲线名称，如 prime256v1（默认，即 P-256）、secp384r1、secp521r1 等
     * @return array{private:string,public:string} 包含私钥和公钥的数组
     * @throws \RuntimeException 当密钥生成失败时抛出
     * @see ECC::createKeyPair()
     */
    public static function eccCreateKeyPair(string $curve = 'prime256v1'): array
    {
        return ECC::createKeyPair($curve);
    }

    /**
     * 使用 ECC 私钥对数据进行数字签名
     *
     * @param string $privateKeyPem 私钥 PEM 格式字符串
     * @param string $data          待签名的数据
     * @param string $algorithm     哈希算法，如 sha256（默认）
     * @return string 签名结果（DER 格式二进制字符串）
     * @throws \RuntimeException 当签名失败时抛出
     * @see ECC::sign()
     */
    public static function eccSign(string $data, string $privateKeyPem, string $algorithm = 'sha256'): string
    {
        $ecc = ECC::createFromKey($privateKeyPem);
        return $ecc->sign($data, $algorithm);
    }

    /**
     * 使用 ECC 公钥验证数字签名
     *
     * @param string $publicKeyPem 公钥 PEM 格式字符串
     * @param string $data         原始数据
     * @param string $signature    签名结果
     * @param string $algorithm    哈希算法，必须与签名时一致
     * @return bool 签名有效返回 true，无效返回 false
     * @see ECC::verify()
     */
    public static function eccVerify(string $data, string $signature, string $publicKeyPem, string $algorithm = 'sha256'): bool
    {
        $ecc = ECC::createFromPublicKey($publicKeyPem);
        return $ecc->verify($data, $signature, $algorithm);
    }

    /**
     * 使用 ECC 密钥对计算共享密钥（ECDH 密钥协商）
     *
     * @param string $privateKeyPem 己方私钥
     * @param string $publicKeyPem  对方公钥
     * @param int    $length        期望的共享密钥长度（字节），默认为 32
     * @return string 协商出的共享密钥（二进制字符串）
     * @throws \RuntimeException 当计算失败时抛出
     * @see ECC::computeSharedSecret()
     */
    public static function eccSharedSecret(string $privateKeyPem, string $publicKeyPem, int $length = 32): string
    {
        $ecc = ECC::createFromKey($privateKeyPem);
        return $ecc->computeSharedSecret($publicKeyPem, $length);
    }

    // ========== Hash 哈希快捷方法 ==========

    /**
     * 计算字符串的哈希值
     *
     * @param string $data      待哈希的数据
     * @param string $algorithm 哈希算法，如 sha256（默认）、sha512、md5 等
     * @return string 十六进制格式的哈希值
     * @see Hash::calculate()
     */
    public static function hash(string $data, string $algorithm = 'sha256'): string
    {
        return Hash::calculate($data, $algorithm);
    }

    /**
     * 计算文件的哈希值（流式读取，适合大文件）
     *
     * @param string $filePath  文件路径
     * @param string $algorithm 哈希算法，默认为 sha256
     * @return string 十六进制格式的哈希值
     * @throws \RuntimeException 当文件读取失败时抛出
     * @see Hash::file()
     */
    public static function hashFile(string $filePath, string $algorithm = 'sha256'): string
    {
        return Hash::file($filePath, $algorithm);
    }

    /**
     * 计算 HMAC（基于哈希的消息认证码）
     *
     * @param string $data      待认证的数据
     * @param string $key       密钥
     * @param string $algorithm 哈希算法，默认为 sha256
     * @return string 十六进制格式的 HMAC 值
     * @see Hash::hmac()
     */
    public static function hmac(string $data, string $key, string $algorithm = 'sha256'): string
    {
        return Hash::hmac($data, $key, $algorithm);
    }

    /**
     * 使用 PBKDF2 算法派生密钥
     *
     * 适合从密码生成加密密钥，安全性依赖于迭代次数
     *
     * @param string $password    原始密码
     * @param string $salt        盐值（建议使用密码学安全随机值）
     * @param int    $iterations  迭代次数，默认为 100000（最低推荐值）
     * @param int    $length      输出密钥长度（字节），默认为 32
     * @return string 派生出的密钥（二进制字符串）
     * @see Hash::pbkdf2()
     */
    public static function pbkdf2(string $password, string $salt, int $iterations = 100000, int $length = 32): string
    {
        return Hash::pbkdf2($password, $salt, $iterations, $length);
    }

    // ========== Base64 快捷方法 ==========

    /**
     * 对数据进行标准 Base64 编码
     *
     * @param string $data 原始二进制数据
     * @return string Base64 编码字符串
     * @see Base64::encode()
     */
    public static function base64Encode(string $data): string
    {
        return Base64::encode($data);
    }

    /**
     * 对标准 Base64 字符串进行解码
     *
     * @param string $data Base64 编码字符串
     * @return string|false 解码后的原始数据，失败返回 false
     * @see Base64::decode()
     */
    public static function base64Decode(string $data): string|false
    {
        return Base64::decode($data);
    }

    /**
     * 对数据进行 URL 安全 Base64 编码（RFC 4648）
     *
     * 将 "+" 替换为 "-", "/" 替换为 "_"，并去除填充符 "="
     *
     * @param string $data 原始二进制数据
     * @return string URL 安全 Base64 字符串
     * @see Base64::urlSafeEncode()
     */
    public static function base64UrlEncode(string $data): string
    {
        return Base64::urlSafeEncode($data);
    }

    /**
     * 对 URL 安全 Base64 字符串进行解码
     *
     * @param string $data URL 安全 Base64 字符串
     * @return string|false 解码后的原始数据，失败返回 false
     * @see Base64::urlSafeDecode()
     */
    public static function base64UrlDecode(string $data): string|false
    {
        return Base64::urlSafeDecode($data);
    }

    // ========== Password 密码处理快捷方法 ==========

    /**
     * 对密码进行哈希处理
     *
     * @param string $password 原始明文密码
     * @param string $algo     哈希算法，支持 bcrypt（默认）、argon2id、argon2i
     * @return string 密码哈希字符串（包含算法和参数信息）
     * @see Password::bcrypt() Password::argon2id() Password::argon2i()
     */
    public static function passwordHash(string $password, string $algo = 'bcrypt'): string
    {
        return match ($algo) {
            'bcrypt'   => Password::bcrypt($password),
            'argon2id' => Password::argon2id($password),
            'argon2i'  => Password::argon2i($password),
            default    => Password::bcrypt($password),
        };
    }

    /**
     * 验证密码是否与哈希匹配
     *
     * @param string $password 原始明文密码
     * @param string $hash     密码哈希字符串
     * @return bool 密码正确返回 true，错误返回 false
     * @see Password::verify()
     */
    public static function passwordVerify(string $password, string $hash): bool
    {
        return Password::verify($password, $hash);
    }

    // ========== JWT 快捷方法 ==========

    /**
     * 编码生成 JWT（JSON Web Token）
     *
     * @param array  $payload   载荷数据（如 exp 过期时间、sub 主题等）
     * @param string $secret    密钥（对称算法）或私钥 PEM（非对称算法）
     * @param string $algorithm 签名算法，如 HS256（默认）、RS256、ES256 等
     * @return string JWT 字符串（Header.Payload.Signature）
     * @throws \RuntimeException 当编码失败时抛出
     * @see JWT::encode()
     */
    public static function jwtEncode(array $payload, string $secret, string $algorithm = 'HS256'): string
    {
        return JWT::encode($payload, $secret, $algorithm);
    }

    /**
     * 解码并验证 JWT
     *
     * @param string $jwt       JWT 字符串
     * @param string $secret    密钥（对称算法）或公钥 PEM（非对称算法）
     * @param string $algorithm 签名算法，必须与编码时一致
     * @return array 解码后的载荷数据
     * @throws \RuntimeException 当签名无效或令牌过期时抛出
     * @see JWT::decode()
     */
    public static function jwtDecode(string $jwt, string $secret, string $algorithm = 'HS256'): array
    {
        return JWT::decode($jwt, $secret, $algorithm);
    }

    // ========== Random 随机数快捷方法 ==========

    /**
     * 生成密码学安全的随机字节串
     *
     * @param int $length 字节长度
     * @return string 随机二进制字符串
     * @throws \Exception 当随机源不可用时抛出
     * @see Random::bytes()
     */
    public static function randomBytes(int $length): string
    {
        return Random::bytes($length);
    }

    /**
     * 生成密码学安全的随机字母数字字符串
     *
     * @param int $length 字符串长度
     * @return string 随机字符串（A-Z, a-z, 0-9）
     * @throws \Exception 当随机源不可用时抛出
     * @see Random::string()
     */
    public static function randomString(int $length): string
    {
        return Random::string($length);
    }

    /**
     * 生成 UUID v4（完全随机版本）
     *
     * @return string 标准 UUID 字符串，如 "550e8400-e29b-41d4-a716-446655440000"
     * @see Random::uuid()
     */
    public static function uuid(): string
    {
        return Random::uuid();
    }

    /**
     * 生成高熵随机令牌（URL 安全 Base64 格式）
     *
     * 适合作为 API Key、会话令牌、重置密码令牌等场景
     *
     * @param int $length 令牌字节长度，默认为 32（256位熵）
     * @return string URL 安全 Base64 令牌字符串
     * @throws \Exception 当随机源不可用时抛出
     * @see Random::token()
     */
    public static function token(int $length = 32): string
    {
        return Random::token($length);
    }
}
