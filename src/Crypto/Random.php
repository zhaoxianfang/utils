<?php

declare(strict_types=1);

namespace zxf\Utils\Crypto;

use InvalidArgumentException;
use RuntimeException;

/**
 * 密码学安全随机数生成器
 *
 * 基于 PHP 原生 random_bytes() / random_int() 提供密码学安全的随机字节、整数、
 * 字符串、UUID（v4 / v7）、Token、OTP 等生成能力。所有随机源均为 CSPRNG
 * （密码学安全伪随机数生成器），绝对禁止用其替代普通随机数用途。
 *
 * @package zxf\Utils\Crypto
 * @version 1.0.0
 * @license  MIT
 *
 * @example
 * // 生成 32 字节（256 位熵）的 URL 安全令牌，可用于会话标识 / API Key
 * // 注意：以下仅为占位示例，真实密钥请勿硬编码到代码中
 * $token = \zxf\Utils\Crypto\Random::token(32);
 */
class Random
{
    /**
     * @var string 默认字符集
     */
    private const DEFAULT_CHARSET = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    /**
     * @var string 十六进制字符集
     */
    private const HEX_CHARSET = '0123456789abcdef';

    /**
     * @var string URL安全字符集
     */
    private const URL_SAFE_CHARSET = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-_';

    /**
     * 生成密码学安全随机字节
     *
     * @param int $length 字节长度
     * @return string 随机字节串
     * @throws InvalidArgumentException 当 $length 小于 1 时抛出
     */
    public static function bytes(int $length): string
    {
        if ($length < 1) {
            throw new InvalidArgumentException('长度必须大于0');
        }
        return random_bytes($length);
    }

    /**
     * 生成密码学安全随机整数
     *
     * @param int $min 最小值
     * @param int $max 最大值
     * @return int 随机整数
     */
    public static function int(int $min, int $max): int
    {
        return random_int($min, $max);
    }

    /**
     * 生成指定长度的随机字符串
     *
     * 逐字符从字符集中使用 random_int（CSPRNG）取值，字符集按字节处理，
     * 因此多字节字符会被拆开。长度小于 1 或字符集为空时抛出异常。
     *
     * @param int    $length  长度（字符数，按字节计）
     * @param string $charset 自定义字符集，默认字母数字
     * @return string 随机字符串
     * @throws InvalidArgumentException 当 $length 小于 1 或 $charset 为空时抛出
     */
    public static function string(int $length, string $charset = self::DEFAULT_CHARSET): string
    {
        if ($length < 1) {
            throw new InvalidArgumentException('长度必须大于0');
        }
        if ($charset === '') {
            throw new InvalidArgumentException('字符集不能为空');
        }

        $charLen = strlen($charset);
        $result = '';

        for ($i = 0; $i < $length; $i++) {
            $result .= $charset[random_int(0, $charLen - 1)];
        }

        return $result;
    }

    /**
     * 生成十六进制随机字符串
     *
     * @param int $length 字节数（结果长度为字节数*2）
     * @return string 十六进制字符串
     */
    public static function hex(int $length): string
    {
        return bin2hex(self::bytes($length));
    }

    /**
     * 生成URL安全随机字符串
     *
     * @param int $length 长度
     * @return string URL安全字符串
     */
    public static function urlSafe(int $length): string
    {
        return self::string($length, self::URL_SAFE_CHARSET);
    }

    /**
     * 生成UUID v4（RFC 4122）
     *
     * @return string UUID字符串
     */
    public static function uuid(): string
    {
        $data = self::bytes(16);

        // 设置版本号（第7个字节的高4位为0100）
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // 设置变体位（第9个字节的高2位为10）
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * 生成UUID v7（基于时间戳，排序友好）
     *
     * @return string UUID字符串
     */
    public static function uuid7(): string
    {
        $timestamp = (int) (microtime(true) * 1000);
        $timestampBytes = pack('J', $timestamp); // 64-bit big endian

        $randomBytes = self::bytes(10);

        // 版本号 0111 (v7)
        $randomBytes[0] = chr((ord($randomBytes[0]) & 0x0f) | 0x70);
        // 变体位 10
        $randomBytes[2] = chr((ord($randomBytes[2]) & 0x3f) | 0x80);

        $data = substr($timestampBytes, 2, 6) . $randomBytes;

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * 生成随机Token
     *
     * @param int $length 长度（字节），默认32
     * @return string Base64编码的Token
     */
    public static function token(int $length = 32): string
    {
        return Base64::urlSafeEncode(self::bytes($length));
    }

    /**
     * 生成一次性密码（OTP）
     *
     * @param int    $length  长度，默认6
     * @param bool   $numeric 是否纯数字
     * @return string OTP字符串
     * @throws InvalidArgumentException 当 $length 小于 1 或纯数字长度超过 18 时抛出
     */
    public static function otp(int $length = 6, bool $numeric = true): string
    {
        if ($length < 1) {
            throw new InvalidArgumentException('长度必须大于0');
        }
        if ($numeric) {
            // 纯数字分支基于 random_int 生成，10^length 不得超过 PHP_INT_MAX（18 位十进制内安全）
            if ($length > 18) {
                throw new InvalidArgumentException('纯数字 OTP 长度不能超过 18 位');
            }
            $min = (int) pow(10, $length - 1);
            $max = (int) pow(10, $length) - 1;
            return (string) random_int($min, $max);
        }
        return self::string($length);
    }

    /**
     * 生成区间内的随机浮点数
     *
     * 以 PHP_INT_MAX 为精度基准做整数随机后线性映射到 [$min, $max]，结果
     * 落在左闭右闭区间，精度受双精度浮点限制。调用方应保证 $min <= $max，
     * 否则返回值的区间语义将反转。
     *
     * @param float $min 最小值（含）
     * @param float $max 最大值（含）
     * @return float 随机浮点数
     */
    public static function float(float $min = 0.0, float $max = 1.0): float
    {
        $random = random_int(0, PHP_INT_MAX) / PHP_INT_MAX;
        return $min + $random * ($max - $min);
    }

    /**
     * 从数组中随机选择一个元素
     *
     * @param array $array 输入数组
     * @return mixed 随机元素
     * @throws InvalidArgumentException 当 $array 为空时抛出
     */
    public static function choice(array $array): mixed
    {
        if (empty($array)) {
            throw new InvalidArgumentException('数组不能为空');
        }
        $keys = array_keys($array);
        return $array[$keys[random_int(0, count($keys) - 1)]];
    }

    /**
     * 随机打乱数组（Fisher-Yates算法）
     *
     * @param array $array 输入数组
     * @return array 打乱后的数组
     */
    public static function shuffle(array $array): array
    {
        $count = count($array);
        if ($count <= 1) {
            return $array;
        }

        $keys = array_keys($array);
        for ($i = $count - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$keys[$i], $keys[$j]] = [$keys[$j], $keys[$i]];
        }

        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $array[$key];
        }
        return $result;
    }

    /**
     * 生成随机布尔值
     *
     * 以 random_int(0, 1) 取值，true/false 各占约 50%，随机源为系统 CSPRNG。
     *
     * @return bool 随机布尔值
     */
    public static function bool(): bool
    {
        return random_int(0, 1) === 1;
    }

    /**
     * 生成指定区间内的随机日期时间
     *
     * 两端均以 strtotime 可解析的相对/绝对时间描述传入，取值后在 [$start, $end]
     * 时间戳区间内随机，再按 $format 格式化输出。任一描述无法解析时抛出异常；
     * 若 $start 晚于 $end，random_int 会抛出 ValueError。
     *
     * @param string $start  开始时间描述，默认 '-1 year'
     * @param string $end    结束时间描述，默认 'now'
     * @param string $format 输出格式，遵循 date() 语法，默认 'Y-m-d H:i:s'
     * @return string 格式化的日期时间
     * @throws InvalidArgumentException 当时间范围无法解析时抛出
     */
    public static function dateTime(string $start = '-1 year', string $end = 'now', string $format = 'Y-m-d H:i:s'): string
    {
        $startTime = strtotime($start);
        $endTime = strtotime($end);

        if ($startTime === false || $endTime === false) {
            throw new InvalidArgumentException('无效的时间范围');
        }

        // 起止顺序颠倒时交换，避免 random_int() 因 min > max 抛 ValueError
        if ($startTime > $endTime) {
            [$startTime, $endTime] = [$endTime, $startTime];
        }

        $timestamp = random_int($startTime, $endTime);
        return date($format, $timestamp);
    }

    /**
     * 生成符合特定模式的随机字符串
     *
     * @param string $pattern 模式，如 'XXX-9999-XXX'（X为字母，9为数字）
     * @return string 生成的字符串
     */
    public static function pattern(string $pattern): string
    {
        $result = '';
        $upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lower = 'abcdefghijklmnopqrstuvwxyz';

        for ($i = 0, $len = strlen($pattern); $i < $len; $i++) {
            $char = $pattern[$i];
            $result .= match ($char) {
                'X' => $upper[random_int(0, 25)],
                'x' => $lower[random_int(0, 25)],
                '9' => (string) random_int(0, 9),
                'A' => self::DEFAULT_CHARSET[random_int(0, 61)],
                'H' => self::HEX_CHARSET[random_int(0, 15)],
                default => $char,
            };
        }

        return $result;
    }

    /**
     * 生成加密安全的nonce
     *
     * @param int $length 长度，默认16
     * @return string nonce
     */
    public static function nonce(int $length = 16): string
    {
        return Base64::urlSafeEncode(self::bytes($length));
    }

    /**
     * 生成CSRF Token
     *
     * @return string CSRF Token
     */
    public static function csrfToken(): string
    {
        return self::hex(32);
    }

    /**
     * 生成API Key
     *
     * @param string $prefix 前缀，如 'sk_', 'pk_'
     * @param int    $length 随机部分长度，默认32
     * @return string API Key
     */
    public static function apiKey(string $prefix = '', int $length = 32): string
    {
        $key = self::string($length, self::DEFAULT_CHARSET . self::SPECIAL_CHARS);
        return $prefix . $key;
    }

    private const SPECIAL_CHARS = '_-';
}
