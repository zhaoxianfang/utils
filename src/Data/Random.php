<?php

declare(strict_types=1);

namespace zxf\Utils\Data;

use InvalidArgumentException;
use Random\RandomException;
use RuntimeException;

/**
 * 随机数据生成器
 *
 * 提供密码、颜色、UUID v4、假数据（姓名、邮箱、IP）等常用随机数据的生成。
 * 所有随机操作均基于 PHP 8.2+ 的 cryptographically secure 随机函数。
 *
 * @package Data
 * @version 1.0.0
 * @license MIT
 */
class Random
{
    /** @var string[] 常用中文姓氏 */
    private const SURNAMES = ['赵', '钱', '孙', '李', '周', '吴', '郑', '王', '冯', '陈', '褚', '卫', '蒋', '沈', '韩', '杨'];

    /** @var string[] 常用中文名字字符 */
    private const NAME_CHARS = ['伟', '芳', '娜', '敏', '静', '丽', '强', '磊', '军', '洋', '勇', '艳', '杰', '娟', '涛', '明'];

    /** @var string[] 颜色名称 */
    private const COLOR_NAMES = ['red', 'green', 'blue', 'yellow', 'orange', 'purple', 'pink', 'cyan', 'magenta', 'lime'];

    /**
     * 生成指定强度的随机密码
     *
     * @param int $length 密码长度（默认 16）
     * @param bool $upper 包含大写字母
     * @param bool $lower 包含小写字母
     * @param bool $numbers 包含数字
     * @param bool $symbols 包含特殊符号
     * @return string 生成的密码
     * @throws RandomException
     */
    public static function password(int $length = 16, bool $upper = true, bool $lower = true, bool $numbers = true, bool $symbols = true): string
    {
        if ($length < 1) {
            throw new InvalidArgumentException('密码长度必须大于 0');
        }

        $sets = [];
        if ($upper)   $sets[] = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        if ($lower)   $sets[] = 'abcdefghijklmnopqrstuvwxyz';
        if ($numbers) $sets[] = '0123456789';
        if ($symbols) $sets[] = '!@#$%^&*()-_=+[]{}|;:,.<>?';

        if ($sets === []) {
            throw new InvalidArgumentException('至少选择一种字符类型');
        }

        $all = implode('', $sets);
        $password = '';

        // 确保每种类型的字符至少出现一次
        foreach ($sets as $set) {
            $password .= $set[random_int(0, strlen($set) - 1)];
        }

        while (strlen($password) < $length) {
            $password .= $all[random_int(0, strlen($all) - 1)];
        }

        return str_shuffle($password);
    }

    /**
     * 生成标准 UUID v4（随机 UUID）
     *
     * @param bool $withDashes 是否包含连字符（默认 true）
     * @return string UUID 字符串
     */
    public static function uuid(bool $withDashes = true): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // version 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variant

        $uuid = bin2hex($data);
        if (!$withDashes) {
            return $uuid;
        }

        return sprintf(
            '%08s-%04s-%04s-%04s-%12s',
            substr($uuid, 0, 8),
            substr($uuid, 8, 4),
            substr($uuid, 12, 4),
            substr($uuid, 16, 4),
            substr($uuid, 20, 12)
        );
    }

    /**
     * 生成随机 HEX 颜色代码
     *
     * @param bool $withHash 是否包含 # 前缀（默认 true）
     * @return string 如 #3f7a2c
     */
    public static function hexColor(bool $withHash = true): string
    {
        $hex = bin2hex(random_bytes(3));
        return $withHash ? '#' . $hex : $hex;
    }

    /**
     * 生成 RGB 颜色数组
     *
     * @return array{r: int, g: int, b: int}
     */
    public static function rgbColor(): array
    {
        try {
            return [
                'r' => random_int(0, 255),
                'g' => random_int(0, 255),
                'b' => random_int(0, 255),
            ];
        } catch (RandomException $e) {

        }
    }

    /**
     * 生成随机中文姓名（2-3 个字）
     *
     * @return string 如 "李明"
     */
    public static function chineseName(): string
    {
        $surname = self::pick(self::SURNAMES);
        $name = self::pick(self::NAME_CHARS);
        if (random_int(0, 1) === 1) {
            $name .= self::pick(self::NAME_CHARS);
        }
        return $surname . $name;
    }

    /**
     * 生成随机邮箱地址
     *
     * @return string 如 user123@example.com
     */
    public static function email(): string
    {
        $user = self::string(random_int(5, 10), 'abcdefghijklmnopqrstuvwxyz');
        $domains = ['example.com', 'test.org', 'demo.net', 'mail.io', 'app.dev'];
        return $user . '@' . self::pick($domains);
    }

    /**
     * 生成随机 IPv4 地址
     *
     * @param bool $private 是否允许私有地址（默认 true）
     * @return string 如 192.168.1.1
     */
    public static function ipv4(bool $private = true): string
    {
        if (!$private) {
            // 生成公网 IP（避开私有网段）
            $ranges = [
                [1, 9], [11, 126], [128, 171], [173, 191], [193, 223],
            ];
            $range = self::pick($ranges);
            $a = random_int($range[0], $range[1]);
        } else {
            $a = random_int(1, 223);
        }

        return implode('.', [$a, random_int(0, 255), random_int(0, 255), random_int(1, 254)]);
    }

    /**
     * 生成随机 MAC 地址
     *
     * @param string $separator 分隔符（默认 :）
     * @return string 如 00:1a:2b:3c:4d:5e
     */
    public static function mac(string $separator = ':'): string
    {
        $bytes = [];
        for ($i = 0; $i < 6; $i++) {
            $bytes[] = str_pad(dechex(random_int(0, 255)), 2, '0', STR_PAD_LEFT);
        }
        return implode($separator, $bytes);
    }

    /**
     * 生成随机日期时间字符串
     *
     * @param string $format 日期格式（默认 Y-m-d H:i:s）
     * @param string $start  起始日期
     * @param string $end    结束日期
     * @return string
     */
    public static function dateTime(string $format = 'Y-m-d H:i:s', string $start = '-1 year', string $end = 'now'): string
    {
        $startTs = strtotime($start);
        $endTs   = strtotime($end);
        if ($startTs === false || $endTs === false) {
            throw new InvalidArgumentException('无效的日期范围');
        }
        $ts = random_int($startTs, $endTs);
        return date($format, $ts);
    }

    /**
     * 从数组中随机抽取一个元素
     *
     * @template T
     * @param array<T> $array 输入数组
     * @return T
     */
    public static function pick(array $array): mixed
    {
        if ($array === []) {
            throw new InvalidArgumentException('数组不能为空');
        }
        return $array[array_rand($array)];
    }

    /**
     * 从数组中随机抽取多个不重复元素
     *
     * @template T
     * @param array<T> $array  输入数组
     * @param int      $count  抽取数量
     * @return array<T>
     */
    public static function pickMany(array $array, int $count): array
    {
        if ($count < 1 || $count > count($array)) {
            throw new InvalidArgumentException('抽取数量无效');
        }
        $keys = array_rand($array, $count);
        if ($count === 1) {
            $keys = [$keys];
        }
        return array_map(static fn (int $k): mixed => $array[$k], $keys);
    }

    /**
     * 生成指定长度和字符集的随机字符串
     *
     * @param int    $length  长度
     * @param string $charset 字符集
     * @return string
     */
    public static function string(int $length = 16, string $charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'): string
    {
        if ($length < 1 || $charset === '') {
            throw new InvalidArgumentException('参数无效');
        }
        $result = '';
        $max = strlen($charset) - 1;
        for ($i = 0; $i < $length; $i++) {
            $result .= $charset[random_int(0, $max)];
        }
        return $result;
    }
}
