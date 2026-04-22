<?php

declare(strict_types=1);

namespace zxf\Utils\Crypto;

use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

/**
 * JSON Web Token (JWT) 类
 * 支持HS/RS/ES系列算法，符合RFC 7519标准
 *
 * @package Crypto
 * @version 1.0.0
 * @license MIT
 */
class JWT
{
    /**
     * @var array 支持的算法
     */
    private const SUPPORTED_ALGORITHMS = [
        'HS256' => ['type' => 'hmac',   'openssl' => 'SHA256'],
        'HS384' => ['type' => 'hmac',   'openssl' => 'SHA384'],
        'HS512' => ['type' => 'hmac',   'openssl' => 'SHA512'],
        'RS256' => ['type' => 'rsa',    'openssl' => 'SHA256'],
        'RS384' => ['type' => 'rsa',    'openssl' => 'SHA384'],
        'RS512' => ['type' => 'rsa',    'openssl' => 'SHA512'],
        'ES256' => ['type' => 'ecdsa',  'openssl' => 'SHA256'],
        'ES384' => ['type' => 'ecdsa',  'openssl' => 'SHA384'],
        'ES512' => ['type' => 'ecdsa',  'openssl' => 'SHA512'],
    ];

    /**
     * @var array 已解码的header和payload缓存
     */
    private array $cache = [];

    /**
     * 编码JWT
     *
     * @param array  $payload   JWT载荷数据
     * @param string $secret    密钥（HMAC）或私钥（RSA/ECDSA）
     * @param string $algorithm 算法，默认HS256
     * @param array  $headers   自定义头部
     * @return string 编码后的JWT字符串
     */
    public static function encode(array $payload, string $secret, string $algorithm = 'HS256', array $headers = []): string
    {
        self::validateAlgorithm($algorithm);

        $header = array_merge([
            'alg' => $algorithm,
            'typ' => 'JWT',
        ], $headers);

        $headerJson  = self::jsonEncode($header);
        $payloadJson = self::jsonEncode($payload);

        $headerB64  = Base64::urlSafeEncode($headerJson);
        $payloadB64 = Base64::urlSafeEncode($payloadJson);

        $signingInput = "{$headerB64}.{$payloadB64}";
        $signature    = self::sign($signingInput, $secret, $algorithm);
        $signatureB64 = Base64::urlSafeEncode($signature);

        return "{$signingInput}.{$signatureB64}";
    }

    /**
     * 解码JWT
     *
     * @param string $jwt    JWT字符串
     * @param string $secret 密钥或公钥
     * @param string $algorithm 期望算法（验证用），空则不验证算法
     * @return array 解码后的payload
     */
    public static function decode(string $jwt, string $secret, string $algorithm = 'HS256'): array
    {
        $parts = self::split($jwt);
        $signingInput = "{$parts['header']}.{$parts['payload']}";

        self::validateAlgorithm($algorithm);

        if (!self::verify($signingInput, $parts['signature'], $secret, $algorithm)) {
            throw new RuntimeException('JWT签名验证失败');
        }

        $payload = self::jsonDecode(Base64::urlSafeDecode($parts['payload']));

        // 验证过期时间
        if (isset($payload['exp']) && time() > $payload['exp']) {
            throw new RuntimeException('JWT已过期');
        }

        // 验证生效时间
        if (isset($payload['nbf']) && time() < $payload['nbf']) {
            throw new RuntimeException('JWT尚未生效');
        }

        return $payload;
    }

    /**
     * 解码但不验证签名（仅用于读取）
     *
     * @param string $jwt JWT字符串
     * @return array 包含header和payload的数组
     */
    public static function decodeUnsafe(string $jwt): array
    {
        $parts = self::split($jwt);
        return [
            'header'  => self::jsonDecode(Base64::urlSafeDecode($parts['header'])),
            'payload' => self::jsonDecode(Base64::urlSafeDecode($parts['payload'])),
        ];
    }

    /**
     * 验证JWT签名（不解码）
     *
     * @param string $jwt    JWT字符串
     * @param string $secret 密钥或公钥
     * @param string $algorithm 算法
     * @return bool 验证结果
     */
    public static function verifySignature(string $jwt, string $secret, string $algorithm = 'HS256'): bool
    {
        try {
            $parts = self::split($jwt);
            $signingInput = "{$parts['header']}.{$parts['payload']}";
            return self::verify($signingInput, $parts['signature'], $secret, $algorithm);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 刷新JWT（验证后重新编码，更新过期时间）
     *
     * @param string $jwt       原JWT
     * @param string $secret    密钥
     * @param int    $ttl       新过期时间（秒），默认3600
     * @param string $algorithm 算法
     * @return string 新的JWT
     */
    public static function refresh(string $jwt, string $secret, int $ttl = 3600, string $algorithm = 'HS256'): string
    {
        $payload = self::decode($jwt, $secret, $algorithm);
        unset($payload['iat'], $payload['exp'], $payload['nbf']);

        $payload['iat'] = time();
        $payload['exp'] = time() + $ttl;

        return self::encode($payload, $secret, $algorithm);
    }

    /**
     * 创建标准JWT载荷
     *
     * @param array  $claims 自定义声明
     * @param string $issuer     签发者
     * @param string $audience   接收者
     * @param string $subject    主题
     * @param int    $ttl        过期时间（秒），默认3600
     * @return array 标准载荷
     */
    public static function buildPayload(array $claims = [], string $issuer = '', string $audience = '', string $subject = '', int $ttl = 3600): array
    {
        $now = new DateTimeImmutable();
        $payload = [
            'iat' => $now->getTimestamp(),
            'exp' => $now->modify("+{$ttl} seconds")->getTimestamp(),
        ];

        if ($issuer !== '')   $payload['iss'] = $issuer;
        if ($audience !== '') $payload['aud'] = $audience;
        if ($subject !== '')  $payload['sub'] = $subject;

        return array_merge($payload, $claims);
    }

    /**
     * 签名数据
     */
    private static function sign(string $data, string $secret, string $algorithm): string
    {
        $algInfo = self::SUPPORTED_ALGORITHMS[$algorithm];

        return match ($algInfo['type']) {
            'hmac'  => hash_hmac($algInfo['openssl'], $data, $secret, true),
            'rsa'   => self::rsaSign($data, $secret, $algInfo['openssl']),
            'ecdsa' => self::ecdsaSign($data, $secret, $algInfo['openssl']),
            default => throw new RuntimeException("不支持的算法类型: {$algInfo['type']}"),
        };
    }

    /**
     * 验证签名
     */
    private static function verify(string $data, string $signature, string $secret, string $algorithm): bool
    {
        $algInfo = self::SUPPORTED_ALGORITHMS[$algorithm];

        return match ($algInfo['type']) {
            'hmac'  => hash_equals(hash_hmac($algInfo['openssl'], $data, $secret, true), $signature),
            'rsa'   => self::rsaVerify($data, $signature, $secret, $algInfo['openssl']),
            'ecdsa' => self::ecdsaVerify($data, $signature, $secret, $algInfo['openssl']),
            default => false,
        };
    }

    private static function rsaSign(string $data, string $privateKeyPem, string $algorithm): string
    {
        $privateKey = openssl_pkey_get_private($privateKeyPem);
        if ($privateKey === false) {
            throw new RuntimeException('无效的RSA私钥');
        }
        $signature = '';
        if (!openssl_sign($data, $signature, $privateKey, $algorithm)) {
            throw new RuntimeException('RSA签名失败: ' . openssl_error_string());
        }
        return $signature;
    }

    private static function rsaVerify(string $data, string $signature, string $publicKeyPem, string $algorithm): bool
    {
        $publicKey = openssl_pkey_get_public($publicKeyPem);
        if ($publicKey === false) {
            throw new RuntimeException('无效的RSA公钥');
        }
        $result = openssl_verify($data, $signature, $publicKey, $algorithm);
        if ($result === -1) {
            throw new RuntimeException('RSA验证失败: ' . openssl_error_string());
        }
        return $result === 1;
    }

    private static function ecdsaSign(string $data, string $privateKeyPem, string $algorithm): string
    {
        $privateKey = openssl_pkey_get_private($privateKeyPem);
        if ($privateKey === false) {
            throw new RuntimeException('无效的ECC私钥');
        }
        $signature = '';
        if (!openssl_sign($data, $signature, $privateKey, $algorithm)) {
            throw new RuntimeException('ECDSA签名失败: ' . openssl_error_string());
        }
        return $signature;
    }

    private static function ecdsaVerify(string $data, string $signature, string $publicKeyPem, string $algorithm): bool
    {
        $publicKey = openssl_pkey_get_public($publicKeyPem);
        if ($publicKey === false) {
            throw new RuntimeException('无效的ECC公钥');
        }
        $result = openssl_verify($data, $signature, $publicKey, $algorithm);
        if ($result === -1) {
            throw new RuntimeException('ECDSA验证失败: ' . openssl_error_string());
        }
        return $result === 1;
    }

    /**
     * 拆分JWT字符串
     */
    private static function split(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new InvalidArgumentException('无效的JWT格式');
        }
        return [
            'header'    => $parts[0],
            'payload'   => $parts[1],
            'signature' => Base64::urlSafeDecode($parts[2], true) ?: '',
        ];
    }

    private static function validateAlgorithm(string $algorithm): void
    {
        if (!isset(self::SUPPORTED_ALGORITHMS[$algorithm])) {
            throw new InvalidArgumentException("不支持的JWT算法: {$algorithm}");
        }
    }

    private static function jsonEncode(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('JSON编码失败');
        }
        return $json;
    }

    private static function jsonDecode(string $json): array
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new RuntimeException('JSON解码失败');
        }
        return $data;
    }
}
