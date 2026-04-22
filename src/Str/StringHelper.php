<?php

declare(strict_types=1);

namespace zxf\Utils\Str;

use InvalidArgumentException;

/**
 * 字符串处理工具类
 * 提供命名风格转换、内容判断、掩码脱敏、相似度计算等常用字符串操作
 *
 * @package Str
 * @version 1.0.0
 * @license MIT
 */
class StringHelper
{
    /**
     * 限制字符串长度，超出部分用指定后缀截断
     *
     * @param string $string  原始字符串
     * @param int    $limit   最大允许长度（字符数）
     * @param string $end     截断后追加的后缀，默认为 "..."
     * @return string 处理后的字符串
     */
    public static function limit(string $string, int $limit = 100, string $end = '...'): string
    {
        if (mb_strlen($string) <= $limit) return $string;
        return mb_substr($string, 0, $limit) . $end;
    }

    /**
     * 将字符串转换为驼峰命名（camelCase），首字母小写
     *
     * 示例：foo-bar → fooBar, foo_bar → fooBar
     *
     * @param string $value 原始字符串
     * @return string 驼峰命名字符串
     */
    public static function camel(string $value): string
    {
        return lcfirst(self::studly($value));
    }

    /**
     * 将字符串转换为 Studly 命名（StudlyCase），每个单词首字母大写且无分隔符
     *
     * 示例：foo-bar → FooBar, foo_bar → FooBar
     *
     * @param string $value 原始字符串
     * @return string Studly 命名字符串
     */
    public static function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }

    /**
     * 将字符串转换为蛇形命名（snake_case）
     *
     * 示例：FooBar → foo_bar, fooBar → foo_bar
     *
     * @param string $value     原始字符串
     * @param string $delimiter 单词分隔符，默认为 "_"
     * @return string 蛇形命名字符串
     */
    public static function snake(string $value, string $delimiter = '_'): string
    {
        if (!ctype_lower($value)) {
            $value = preg_replace('/\s+/u', '', ucwords($value));
            $value = mb_strtolower(preg_replace('/(.)(?=[A-Z])/u', '$1' . $delimiter, $value));
        }
        return $value;
    }

    /**
     * 将字符串转换为短横线命名（kebab-case）
     *
     * 示例：FooBar → foo-bar
     *
     * @param string $value 原始字符串
     * @return string 短横线命名字符串
     */
    public static function kebab(string $value): string
    {
        return self::snake($value, '-');
    }

    /**
     * 判断字符串是否以指定前缀开头（支持多个前缀，满足其一即返回 true）
     *
     * @param string          $haystack 待检查的字符串
     * @param string|array    $needles  前缀或前缀数组
     * @return bool 若字符串以任一指定前缀开头则返回 true
     */
    public static function startsWith(string $haystack, string|array $needles): bool
    {
        foreach ((array) $needles as $n) {
            if ($n !== '' && str_starts_with($haystack, $n)) return true;
        }
        return false;
    }

    /**
     * 判断字符串是否以指定后缀结尾（支持多个后缀，满足其一即返回 true）
     *
     * @param string          $haystack 待检查的字符串
     * @param string|array    $needles  后缀或后缀数组
     * @return bool 若字符串以任一指定后缀结尾则返回 true
     */
    public static function endsWith(string $haystack, string|array $needles): bool
    {
        foreach ((array) $needles as $n) {
            if ($n !== '' && str_ends_with($haystack, $n)) return true;
        }
        return false;
    }

    /**
     * 判断字符串是否包含指定子串（支持多个子串，满足其一即返回 true）
     *
     * @param string          $haystack 待检查的字符串
     * @param string|array    $needles  子串或子串数组
     * @return bool 若字符串包含任一指定子串则返回 true
     */
    public static function contains(string $haystack, string|array $needles): bool
    {
        foreach ((array) $needles as $n) {
            if ($n !== '' && str_contains($haystack, $n)) return true;
        }
        return false;
    }

    /**
     * 移除字符串开头的指定前缀（支持多个前缀，移除第一个匹配的）
     *
     * @param string          $string 原始字符串
     * @param string|array    $prefix 前缀或前缀数组
     * @return string 移除前缀后的字符串，若无匹配前缀则原样返回
     */
    public static function removePrefix(string $string, string|array $prefix): string
    {
        foreach ((array) $prefix as $p) {
            if (str_starts_with($string, $p)) return substr($string, strlen($p));
        }
        return $string;
    }

    /**
     * 移除字符串结尾的指定后缀（支持多个后缀，移除第一个匹配的）
     *
     * @param string          $string 原始字符串
     * @param string|array    $suffix 后缀或后缀数组
     * @return string 移除后缀后的字符串，若无匹配后缀则原样返回
     */
    public static function removeSuffix(string $string, string|array $suffix): string
    {
        foreach ((array) $suffix as $s) {
            if (str_ends_with($string, $s)) return substr($string, 0, -strlen($s));
        }
        return $string;
    }

    /**
     * 压缩字符串中的连续空白字符为单个空格，并去除首尾空白
     *
     * @param string $string 原始字符串
     * @return string 压缩后的字符串
     */
    public static function squish(string $string): string
    {
        return preg_replace('/\s+/u', ' ', trim($string)) ?? $string;
    }

    /**
     * 从指定字符集中生成随机字符串
     *
     * @param int    $length   生成字符串的长度
     * @param string $charset  字符集，默认为大小写字母加数字
     * @return string 随机字符串
     * @throws InvalidArgumentException 当字符集为空时抛出
     */
    public static function random(int $length = 16, string $charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'): string
    {
        $charLen = strlen($charset);
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $charset[random_int(0, $charLen - 1)];
        }
        return $result;
    }

    /**
     * 计算两个字符串的相似度（基于 Levenshtein 编辑距离）
     *
     * 返回值范围：0.0（完全不同）~ 1.0（完全相同）
     *
     * @param string $a 第一个字符串
     * @param string $b 第二个字符串
     * @return float 相似度比例
     */
    public static function similarity(string $a, string $b): float
    {
        $maxLen = max(mb_strlen($a), mb_strlen($b));
        if ($maxLen === 0) return 1.0;
        return 1.0 - (levenshtein($a, $b) / $maxLen);
    }

    /**
     * 将字符串转换为 URL 友好的 slug 格式
     *
     * 移除非字母数字字符，连续分隔符合并为单个，并转为小写
     *
     * @param string $title      原始字符串
     * @param string $separator  单词分隔符，默认为 "-"
     * @return string slug 字符串
     */
    public static function slug(string $title, string $separator = '-'): string
    {
        $title = preg_replace('![' . preg_quote($separator) . '\s]+!u', $separator, $title);
        $title = preg_replace('![^\pL\pN]+!u', $separator, $title);
        return mb_strtolower(trim($title, $separator));
    }

    /**
     * 对字符串进行掩码脱敏处理
     *
     * 从指定起始位置开始，用掩码字符替换指定长度的内容
     *
     * @param string $string 原始字符串
     * @param string $mask   掩码字符，默认为 "*"
     * @param int    $start  起始位置（从0开始）
     * @param int    $length 替换长度
     * @return string 脱敏后的字符串
     */
    public static function mask(string $string, string $mask = '*', int $start = 3, int $length = 4): string
    {
        $strLen = mb_strlen($string);
        if ($strLen <= $start) return $string;
        $actual = min($length, $strLen - $start);
        return mb_substr($string, 0, $start) . str_repeat($mask, $actual) . mb_substr($string, $start + $actual);
    }

    /**
     * 对手机号码进行脱敏（保留前3位和后4位，中间用 * 替代）
     *
     * @param string $mobile 手机号码
     * @return string 脱敏后的手机号码，如 138****8888
     */
    public static function maskMobile(string $mobile): string
    {
        return self::mask($mobile, '*', 3, 4);
    }

    /**
     * 对邮箱地址进行脱敏（保留前2位和域名部分，其余用 * 替代）
     *
     * @param string $email 邮箱地址
     * @return string 脱敏后的邮箱，如 ab****@example.com
     */
    public static function maskEmail(string $email): string
    {
        if (!str_contains($email, '@')) return $email;
        [$local, $domain] = explode('@', $email, 2);
        $len = strlen($local);
        if ($len <= 2) return $email;
        return substr($local, 0, 2) . str_repeat('*', $len - 2) . '@' . $domain;
    }

    /**
     * 按指定宽度对字符串进行自动换行
     *
     * @param string $string 原始字符串
     * @param int    $width  每行最大宽度（字节数）
     * @param string $break  换行符，默认为 "\n"
     * @param bool   $cut    是否强制在宽度处截断长单词
     * @return string 换行后的字符串
     */
    public static function wordWrap(string $string, int $width = 75, string $break = "\n", bool $cut = false): string
    {
        return wordwrap($string, $width, $break, $cut);
    }

    /**
     * 使用指定字符对字符串进行填充，使其达到目标长度
     *
     * @param string $string 原始字符串
     * @param int    $length 目标长度
     * @param string $pad    填充字符，默认为空格
     * @param int    $type   填充方向：STR_PAD_RIGHT（右侧，默认）、STR_PAD_LEFT（左侧）、STR_PAD_BOTH（两侧）
     * @return string 填充后的字符串
     */
    public static function pad(string $string, int $length, string $pad = ' ', int $type = STR_PAD_RIGHT): string
    {
        return str_pad($string, $length, $pad, $type);
    }

    /**
     * 反转字符串（支持多字节字符，如中文）
     *
     * @param string $string 原始字符串
     * @return string 反转后的字符串
     */
    public static function reverse(string $string): string
    {
        return implode('', array_reverse(mb_str_split($string)));
    }

    /**
     * 将字符串中的非 ASCII 字符转为其最接近的 ASCII 等价字符（音译）
     *
     * @param string $string 原始字符串
     * @return string ASCII 字符串，无法转换的字符将被移除
     */
    public static function ascii(string $string): string
    {
        return iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $string) ?: '';
    }

    /**
     * 提取两个标记之间的子串（不包含标记本身）
     *
     * @param string $string 原始字符串
     * @param string $from   起始标记
     * @param string $to     结束标记
     * @return string 提取到的子串，若未找到则返回空字符串
     */
    public static function between(string $string, string $from, string $to): string
    {
        $start = strpos($string, $from);
        if ($start === false) return '';
        $start += strlen($from);
        $end = strpos($string, $to, $start);
        if ($end === false) return '';
        return substr($string, $start, $end - $start);
    }

    /**
     * 获取子串在字符串中首次出现位置之后的所有内容
     *
     * @param string $string 原始字符串
     * @param string $search 查找的子串
     * @return string 子串之后的部分，若未找到则返回空字符串
     */
    public static function after(string $string, string $search): string
    {
        $pos = strpos($string, $search);
        return $pos === false ? '' : substr($string, $pos + strlen($search));
    }

    /**
     * 获取子串在字符串中首次出现位置之前的所有内容
     *
     * @param string $string 原始字符串
     * @param string $search 查找的子串
     * @return string 子串之前的部分，若未找到则返回空字符串
     */
    public static function before(string $string, string $search): string
    {
        $pos = strpos($string, $search);
        return $pos === false ? '' : substr($string, 0, $pos);
    }
}
