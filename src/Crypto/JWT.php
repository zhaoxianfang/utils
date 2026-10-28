<?php

declare(strict_types=1);

namespace zxf\Utils\Crypto;

use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

/**
 * JSON Web Token (JWT) 类
 *
 * 提供 JWT 的签发与验证，支持 HS（HMAC）、RS（RSA）、ES（ECDSA）共九种算法，
 * 符合 RFC 7519 标准。默认对算法声明做严格校验，可防御「算法混淆攻击
 * （alg confusion）」与 alg=none 未签名令牌；HMAC 比较使用 hash_equals 防止
 * 时序侧信道；ECDSA 签名按 RFC 7518 在 raw R||S 与 OpenSSL 的 DER 之间转换。
 *
 * @package zxf\Utils\Crypto
 * @version 1.0.0
 * @license MIT
 *
 * @example
 * // HS256 对称签名（请勿硬编码真实密钥，以下用占位符 'your-secret-key'）
 * $payload = \zxf\Utils\Crypto\JWT::buildPayload(['uid' => 123], 'my-iss', '', 'user-1', 3600);
 * $token   = \zxf\Utils\Crypto\JWT::encode($payload, 'your-secret-key', 'HS256');
 * $data    = \zxf\Utils\Crypto\JWT::decode($token, 'your-secret-key', 'HS256');
 *
 * // RS256 非对称签名（使用私钥签名、公钥验签）
 * $token = \zxf\Utils\Crypto\JWT::encode($payload, $privateKeyPem, 'RS256');
 * $data  = \zxf\Utils\Crypto\JWT::decode($token, $publicKeyPem, 'RS256');
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
     * 编码（签发）JWT
     *
     * 将 header 与 payload 分别做 URL 安全 Base64 编码，拼接后按算法计算签名，
     * 最终返回 "header.payload.signature" 三段式字符串。
     *
     * @param array  $payload   JWT 载荷数据（如用户标识、权限等自定义声明）
     * @param string $secret    密钥（HMAC 算法）或私钥 PEM（RSA/ECDSA 算法）
     * @param string $algorithm 签名算法，默认 HS256（可选 HS、RS、ES 系列）
     * @param array  $headers   自定义头部（会与 alg/typ 合并，勿覆盖 alg）
     * @return string 编码后的 JWT 字符串
     * @throws InvalidArgumentException 当算法不被支持时抛出
     * @throws RuntimeException 当签名失败（如密钥无效）时抛出
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
     * 解码并验证 JWT
     *
     * 先拆分三段、校验头部 alg 是否经白名单校验且调用方期望一致（防算法混淆攻击），
     * 再验签；随后校验 exp（过期）、nbf（未生效）等时间声明。仅当全部通过后返回
     * 解码后的 payload。
     *
     * @param string $jwt       JWT 字符串
     * @param string $secret    密钥（HMAC）或公钥 PEM（RSA/ECDSA）
     * @param string $algorithm 期望算法（验证用）；为空时退而采用令牌声明的算法（仍需白名单校验）
     * @return array 解码后的 payload（关联数组）
     * @throws InvalidArgumentException 当令牌格式错误或头部算法缺失时抛出
     * @throws RuntimeException 当算法不符、签名无效、已过期或尚未生效时抛出
     */
    public static function decode(string $jwt, string $secret, string $algorithm = 'HS256'): array
    {
        $parts = self::split($jwt);
        $signingInput = "{$parts['header']}.{$parts['payload']}";

        // 解析头部，校验算法，防止“算法混淆攻击（alg confusion）”：
        // 必须以调用方期望（或令牌自身声明、且经过白名单校验）的算法为准，
        // 严禁接受 alg=none 等未签名令牌。
        $header = self::jsonDecode(Base64::urlSafeDecode($parts['header']));
        $headerAlg = (string) ($header['alg'] ?? '');
        if ($headerAlg === '' || ! isset(self::SUPPORTED_ALGORITHMS[$headerAlg])) {
            throw new RuntimeException('JWT 头部算法不被支持');
        }
        // 当调用方未显式指定算法（空）时，退而使用令牌声明的算法，但仍需经白名单校验；
        // 否则要求令牌声明算法与期望算法严格一致（使用 hash_equals 防时序侧信道）。
        $expectedAlg = $algorithm === '' ? $headerAlg : $algorithm;
        if (! hash_equals($headerAlg, $expectedAlg)) {
            throw new RuntimeException('JWT 算法与期望不符（疑似算法混淆攻击）');
        }
        $algorithm = $expectedAlg;

        self::validateAlgorithm($algorithm);

        if (!self::verify($signingInput, $parts['signature'], $secret, $algorithm)) {
            throw new RuntimeException('JWT签名验证失败');
        }

        $payload = self::jsonDecode(Base64::urlSafeDecode($parts['payload']));

        // 验证过期时间（载荷来自 JSON，exp/nbf 可能是字符串/浮点，统一 (int) 归一化后再比较）
        if (isset($payload['exp']) && is_numeric($payload['exp']) && time() > (int) $payload['exp']) {
            throw new RuntimeException('JWT已过期');
        }
        // 非数字 exp 视为缺失处理；nbf 同理
        if (isset($payload['exp']) && ! is_numeric($payload['exp'])) {
            throw new RuntimeException('JWT的exp声明必须是数值');
        }

        // 验证生效时间
        if (isset($payload['nbf']) && is_numeric($payload['nbf']) && time() < (int) $payload['nbf']) {
            throw new RuntimeException('JWT尚未生效');
        }
        if (isset($payload['nbf']) && ! is_numeric($payload['nbf'])) {
            throw new RuntimeException('JWT的nbf声明必须是数值');
        }

        return $payload;
    }

    /**
     * 解码但不验证签名（仅用于读取/调试）
     *
     * 警告：本方法完全跳过签名验证，仅解析内容，绝不应用于任何信任场景。
     * 任何涉及身份/权限的判断都必须使用 decode() 进行验签。
     *
     * @param string $jwt JWT 字符串
     * @return array{header:array,payload:array} 包含 header 和 payload 的数组
     * @throws InvalidArgumentException 当令牌格式不合法（非三段式）时抛出
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
        } catch (\Exception $e) {
            // 注意：此处必须使用全限定名 \Exception。
            // 本文件位于 zxf\Utils\Crypto 命名空间，裸写 Exception 会解析为
            // zxf\Utils\Crypto\Exception（不存在），导致 catch 匹配不到、异常外泄。
            return false;
        }
    }

    /**
     * 刷新 JWT（验证后重新编码，更新过期时间）
     *
     * 先调用 decode() 完成验签与时间校验，剔除旧的 iat/exp/nbf 后重新写入当前签发
     * 时间与新的过期时间，再编码返回。若原令牌无效则直接抛出异常。
     *
     * @param string $jwt       原 JWT 字符串
     * @param string $secret    密钥（HMAC）或公钥 PEM（RSA/ECDSA）
     * @param int    $ttl       新过期时间（秒），默认 3600
     * @param string $algorithm 算法，默认 HS256
     * @return string 新的 JWT 字符串
     * @throws InvalidArgumentException 当令牌格式错误时抛出
     * @throws RuntimeException 当验签失败或令牌已过期/未生效时抛出
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
     * 根据算法类型对数据签名（HMAC / RSA / ECDSA 分发）
     *
     * @param string $data      待签名数据（signing input，即 header.payload）
     * @param string $secret    密钥（HMAC）或私钥 PEM（RSA/ECDSA）
     * @param string $algorithm 已校验的算法名称
     * @return string 二进制签名
     * @throws RuntimeException 当算法类型不支持或底层签名失败时抛出
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
     * 根据算法类型验证签名（HMAC 时序安全比较 / RSA / ECDSA 分发）
     *
     * @param string $data      原始数据（signing input）
     * @param string $signature 待验证的二进制签名
     * @param string $secret    密钥（HMAC）或公钥 PEM（RSA/ECDSA）
     * @param string $algorithm 已校验的算法名称
     * @return bool 签名有效返回 true，无效返回 false
     * @throws RuntimeException 当算法类型不支持或底层验证出错时抛出
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

    /**
     * 使用 RSA 私钥对数据签名（PKCS#1 v1.5）
     *
     * @param string $data          待签名数据（通常是 JWT 的 signing input）
     * @param string $privateKeyPem 私钥 PEM 字符串
     * @param string $algorithm     OpenSSL 哈希算法名（如 SHA256）
     * @return string 二进制签名
     * @throws RuntimeException 当私钥无效或签名失败时抛出
     */
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

    /**
     * 使用 RSA 公钥验证签名（PKCS#1 v1.5）
     *
     * @param string $data          原始数据
     * @param string $signature     待验证的二进制签名
     * @param string $publicKeyPem  公钥 PEM 字符串
     * @param string $algorithm     OpenSSL 哈希算法名
     * @return bool 签名有效返回 true，无效返回 false
     * @throws RuntimeException 当公钥无效或底层验证出错（result=-1）时抛出
     */
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

    /**
     * 使用 ECDSA 私钥对数据签名
     *
     * openssl_sign 返回 DER 编码签名，需按 RFC 7518 转换为定长 raw R||S 后再返回。
     *
     * @param string $data          待签名数据
     * @param string $privateKeyPem 私钥 PEM 字符串
     * @param string $algorithm     OpenSSL 哈希算法名
     * @return string 转换后的 raw R||S 二进制签名
     * @throws RuntimeException 当私钥无效、签名失败或 DER 转换失败时抛出
     */
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
        // RFC 7518：ES256/ES384/ES512 的签名必须是定长 raw R||S（各 coordSize 字节），
        // 而 openssl_sign 返回 DER 编码（SEQUENCE { INTEGER r, INTEGER s }），
        // 必须转换，否则与任何标准 JWT 实现（firebase/php-jwt、jose、libraries.io 等）互不兼容。
        return self::derToRawSignature($signature, self::ecKeyCoordSize($privateKey));
    }

    /**
     * 使用 ECDSA 公钥验证签名
     *
     * 入参签名为 JWT 标准的 raw R||S，需先转回 OpenSSL 所需的 DER 编码再验签。
     *
     * @param string $data          原始数据
     * @param string $signature     raw R||S 二进制签名
     * @param string $publicKeyPem  公钥 PEM 字符串
     * @param string $algorithm     OpenSSL 哈希算法名
     * @return bool 签名有效返回 true，无效返回 false
     * @throws RuntimeException 当公钥无效、DER 转换失败或底层验证出错时抛出
     */
    private static function ecdsaVerify(string $data, string $signature, string $publicKeyPem, string $algorithm): bool
    {
        $publicKey = openssl_pkey_get_public($publicKeyPem);
        if ($publicKey === false) {
            throw new RuntimeException('无效的ECC公钥');
        }
        $result = openssl_verify($data, self::rawToDerSignature($signature, self::ecKeyCoordSize($publicKey)), $publicKey, $algorithm);
        if ($result === -1) {
            throw new RuntimeException('ECDSA验证失败: ' . openssl_error_string());
        }
        return $result === 1;
    }

    /**
     * 探测 EC 密钥坐标字节长度（P-256=32 / P-384=48 / P-521=66）
     *
     * 用于 ECDSA 签名在 raw R||S 与 DER 之间转换时确定各分量定长，避免长度歧义。
     *
     * @param \OpenSSLAsymmetricKey $key OpenSSL 私钥或公钥资源
     * @return int 单个坐标（R 或 S）的字节长度
     * @throws RuntimeException 当无法获取密钥位数时抛出
     */
    private static function ecKeyCoordSize($key): int
    {
        $details = openssl_pkey_get_details($key);
        $bits = (int) ($details['bits'] ?? 0);
        if ($bits < 1) {
            throw new RuntimeException('无法获取ECC密钥位数');
        }
        return (int) ceil($bits / 8);
    }

    /**
     * 将长度值 DER 编码（ASN.1 长度字段：短型或长型）
     *
     * @param int $length 待编码的长度（非负整数）
     * @return string DER 长度字节序列
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
     * 将无符号整数二进制编码为 DER INTEGER（必要时补 0x00 保证为正数）
     *
     * DER INTEGER 以补码解释首字节最高位，故当最高位为 1 时需前置 0x00 表示正数，
     * 否则会被误判为负数导致签名解析失败。
     *
     * @param string $value 大端字节序的无符号整数（R 或 S 分量）
     * @return string DER INTEGER TLV 字节序列
     */
    private static function derInteger(string $value): string
    {
        $value = ltrim($value, "\x00");
        if ($value === '') {
            $value = "\x00";
        }
        if ((ord($value[0]) & 0x80) !== 0) {
            $value = "\x00" . $value;
        }
        return "\x02" . self::derLength(strlen($value)) . $value;
    }

    /**
     * 标准 raw R||S → OpenSSL 所需的 DER ECDSA 签名
     *
     * @param string $raw        JWT 标准定长签名（R、S 各 coordSize 字节）
     * @param int    $coordSize 单个坐标字节长度
     * @return string DER 编码（SEQUENCE { INTEGER r, INTEGER s }）签名
     * @throws RuntimeException 当签名长度非法时抛出
     */
    private static function rawToDerSignature(string $raw, int $coordSize): string
    {
        if (strlen($raw) !== $coordSize * 2) {
            throw new RuntimeException("无效的ECDSA签名长度: 期望 " . ($coordSize * 2) . " 字节");
        }
        $content = self::derInteger(substr($raw, 0, $coordSize)) . self::derInteger(substr($raw, $coordSize));
        return "\x30" . self::derLength(strlen($content)) . $content;
    }

    /**
     * OpenSSL DER ECDSA 签名 → 标准 raw R||S
     *
     * 解析 DER 的 SEQUENCE 与两个 INTEGER，去掉前导零后将各分量补齐到 coordSize
     * 字节，拼接为定长 R||S（符合 RFC 7518 对 ES* 算法签名格式的要求）。
     *
     * @param string $der       DER 编码签名
     * @param int    $coordSize 单个坐标字节长度
     * @return string 定长 raw R||S 签名
     * @throws RuntimeException 当 DER 结构非法或分量长度超限时抛出
     */
    private static function derToRawSignature(string $der, int $coordSize): string
    {
        $derLen = strlen($der);
        if ($derLen < 8 || $der[0] !== "\x30") {
            throw new RuntimeException('无效的DER ECDSA签名');
        }
        $pos = 1;
        $len = ord($der[$pos]);
        $pos++;
        if ($len & 0x80) {
            $numBytes = $len & 0x7f;
            if ($numBytes === 0 || $pos + $numBytes > $derLen) {
                throw new RuntimeException('无效的DER ECDSA签名');
            }
            $len = 0;
            for ($i = 0; $i < $numBytes; $i++) {
                $len = ($len << 8) | ord($der[$pos]);
                $pos++;
            }
        }
        $end = min($pos + $len, $derLen);

        $coords = [];
        for ($i = 0; $i < 2; $i++) {
            if ($pos + 2 > $end || $der[$pos] !== "\x02") {
                throw new RuntimeException('无效的DER ECDSA签名');
            }
            $pos++;
            $clen = ord($der[$pos]);
            $pos++;
            if ($clen & 0x80) {
                $numBytes = $clen & 0x7f;
                if ($numBytes === 0 || $pos + $numBytes > $end) {
                    throw new RuntimeException('无效的DER ECDSA签名');
                }
                $clen = 0;
                for ($j = 0; $j < $numBytes; $j++) {
                    $clen = ($clen << 8) | ord($der[$pos]);
                    $pos++;
                }
            }
            if ($pos + $clen > $end) {
                throw new RuntimeException('无效的DER ECDSA签名');
            }
            $coord = ltrim(substr($der, $pos, $clen), "\x00");
            $pos += $clen;
            if (strlen($coord) > $coordSize) {
                throw new RuntimeException('ECDSA 签名分量长度非法');
            }
            $coords[] = str_pad($coord, $coordSize, "\x00", STR_PAD_LEFT);
        }
        return $coords[0] . $coords[1];
    }

    /**
     * 将 JWT 字符串拆分为 header、payload、signature 三段
     *
     * 校验必须为两段点号分隔的三段式；signature 段在 Base64URL 解码失败时
     * 降级为空字符串（交由后续验签/解码统一报错），避免提前抛异常。
     *
     * @param string $jwt JWT 字符串
     * @return array{header:string,payload:string,signature:string} 三段内容
     * @throws InvalidArgumentException 当格式不是标准三段式时抛出
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

    /**
     * 校验 JWT 算法是否在支持列表中，否则抛出异常
     *
     * @param string $algorithm 算法名称（如 HS256 / RS384 / ES512）
     * @return void
     * @throws InvalidArgumentException 当算法不被支持时抛出
     */
    private static function validateAlgorithm(string $algorithm): void
    {
        if (!isset(self::SUPPORTED_ALGORITHMS[$algorithm])) {
            throw new InvalidArgumentException("不支持的JWT算法: {$algorithm}");
        }
    }

    /**
     * 将数组编码为 JSON 字符串（保留非 ASCII 与斜杠，避免令牌被破坏）
     *
     * @param array $data 待编码的关联数组（header 或 payload）
     * @return string JSON 字符串
     * @throws RuntimeException 当编码失败（含不可序列化值）时抛出
     */
    private static function jsonEncode(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('JSON编码失败');
        }
        return $json;
    }

    /**
     * 将 JSON 字符串解码为关联数组
     *
     * @param string $json JSON 字符串
     * @return array 解码后的关联数组
     * @throws RuntimeException 当 JSON 无效或解码结果非数组时抛出
     */
    private static function jsonDecode(string $json): array
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new RuntimeException('JSON解码失败');
        }
        return $data;
    }
}
