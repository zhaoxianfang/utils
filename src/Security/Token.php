<?php

declare(strict_types=1);

namespace zxf\Utils\Security;

use InvalidArgumentException;
use RuntimeException;

/**
 * 安全 Token 生成与验证工具
 *
 * 提供基于 HMAC-SHA256 的签名 Token（类似简化版 JWT），支持过期时间、
 * 自定义载荷和防篡改验证。无需外部依赖，纯 PHP 实现。
 *
 * 特性：
 * - HMAC-SHA256 签名防篡改
 * - 内置过期时间（TTL）
 * - URL-Safe Base64 编码
 * - 支持自定义 Secret Key 轮换
 *
 * @package Security
 * @version 1.0.0
 * @license MIT
 */
class Token
{
    /** @var string 当前签名密钥 */
    private string $secret;

    /** @var string 签名算法 */
    private const ALGORITHM = 'sha256';

    /** @var string Token 各部分的分隔符 */
    private const SEPARATOR = '.';

    /**
     * @param string $secret 签名密钥（建议 32 字节以上）
     */
    public function __construct(string $secret)
    {
        if (strlen($secret) < 16) {
            throw new InvalidArgumentException('密钥长度应不少于 16 字节');
        }
        $this->secret = $secret;
    }

    /**
     * 生成签名 Token
     *
     * @param array  $payload 自定义载荷数据
     * @param int    $ttl     过期时间（秒），0 表示永不过期
     * @return string 签名后的 Token 字符串
     */
    public function encode(array $payload, int $ttl = 3600): string
    {
        $header = json_encode(['alg' => self::ALGORITHM, 'typ' => 'zxf']);
        $body = [
            'iss' => time(),
            'exp' => $ttl > 0 ? time() + $ttl : 0,
            'pld' => $payload,
        ];
        $encodedBody = json_encode($body);

        $b64Header  = self::base64UrlEncode($header);
        $b64Payload = self::base64UrlEncode($encodedBody);
        $signature  = self::sign($b64Header . self::SEPARATOR . $b64Payload);

        return $b64Header . self::SEPARATOR . $b64Payload . self::SEPARATOR . $signature;
    }

    /**
     * 验证并解码 Token
     *
     * @param string $token Token 字符串
     * @return array 解码后的载荷数据
     * @throws RuntimeException Token 无效或过期时抛出
     */
    public function decode(string $token): array
    {
        $parts = explode(self::SEPARATOR, $token);
        if (count($parts) !== 3) {
            throw new RuntimeException('Token 格式错误');
        }

        [$b64Header, $b64Payload, $signature] = $parts;

        // 验证签名
        $expected = self::sign($b64Header . self::SEPARATOR . $b64Payload);
        if (!hash_equals($expected, $signature)) {
            throw new RuntimeException('Token 签名无效');
        }

        $body = json_decode(self::base64UrlDecode($b64Payload), true);
        if (!is_array($body)) {
            throw new RuntimeException('Token 载荷解析失败');
        }

        // 检查过期时间
        if (isset($body['exp']) && is_int($body['exp']) && $body['exp'] > 0 && $body['exp'] < time()) {
            throw new RuntimeException('Token 已过期');
        }

        return $body['pld'] ?? [];
    }

    /**
     * 仅验证 Token 是否有效，不返回载荷
     *
     * @param string $token Token 字符串
     * @return bool
     */
    public function verify(string $token): bool
    {
        try {
            $this->decode($token);
            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * 生成一个简单的随机 Token 字符串（无载荷，仅用于 CSRF / API Key）
     *
     * @param int $length 长度（默认 32）
     * @return string 十六进制字符串
     */
    public static function random(int $length = 32): string
    {
        return bin2hex(random_bytes((int) ceil($length / 2)));
    }

    /**
     * 使用当前密钥创建签名
     *
     * 以 HMAC-SHA256 计算原始二进制摘要，再做 URL-Safe Base64 编码，
     * 结果可直接拼进以 "." 分隔的 Token 中。密钥取构造时传入的 secret。
     *
     * @param string $data 待签名的原文（Token 中为「header.payload」两段拼接）
     * @return string URL-Safe Base64 编码后的签名串
     */
    private function sign(string $data): string
    {
        return self::base64UrlEncode(hash_hmac(self::ALGORITHM, $data, $this->secret, true));
    }

    /**
     * URL-Safe Base64 编码
     *
     * 把标准 Base64 中的加号换成减号、斜杠换成下划线，并去掉结尾的等号填充，
     * 使结果可直接用于 URL 与文件名。
     *
     * @param string $data 原始字符串
     * @return string 不含 "+"、"/"、"=" 的 Base64 字符串
     */
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * URL-Safe Base64 解码
     *
     * 先把 URL-Safe 字符还原为标准 Base64 字符，再按需补齐等号填充，
     * 使用严格模式解码；非法字符或长度异常时抛异常而非静默返回空串。
     *
     * @param string $data URL-Safe Base64 字符串（通常不带等号填充）
     * @return string 解码后的原始字符串
     *
     * @throws RuntimeException 内容不是合法 Base64 时抛出
     */
    private static function base64UrlDecode(string $data): string
    {
        $pad = 4 - (strlen($data) % 4);
        if ($pad !== 4) {
            $data .= str_repeat('=', $pad);
        }
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        if ($decoded === false) {
            throw new RuntimeException('Base64 解码失败');
        }
        return $decoded;
    }
}
