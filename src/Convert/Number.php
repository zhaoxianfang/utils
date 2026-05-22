<?php

/**
 * ============================================================================
 * 数字格式化与转换工具类
 * ============================================================================
 *
 * 提供数字的多种格式化输出、中文大写金额转换、进制转换等功能。
 *
 * @package     zxf\Utils\Convert
 * @author      zxf\Utils
 * @version     1.0.0
 * @license     MIT
 * @link        https://github.com/zhaoxianfang/utils
 *
 * @example
 * Number::currency(1234567.89);           // "1,234,567.89"
 * Number::chinese(1234.56);               // "壹仟贰佰叁拾肆元伍角陆分"
 * Number::abbreviate(1234567);            // "1.2M"
 * Number::ordinal(21);                    // "21st"
 */

declare(strict_types=1);

namespace zxf\Utils\Convert;

use InvalidArgumentException;

final class Number
{
    private const CHINESE_NUMBERS = ['零', '壹', '贰', '叁', '肆', '伍', '陆', '柒', '捌', '玖'];

    private const CHINESE_UNITS = ['', '拾', '佰', '仟'];

    private const CHINESE_BIG_UNITS = ['', '万', '亿', '万亿'];

    /**
     * 格式化为货币格式（千分位）
     */
    public static function currency(float|int $number, int $decimals = 2, string $decimalPoint = '.', string $thousandsSeparator = ','): string
    {
        return number_format((float) $number, $decimals, $decimalPoint, $thousandsSeparator);
    }

    /**
     * 转换为中文大写金额
     */
    public static function chinese(float|int $number): string
    {
        if ($number < 0) {
            return '负' . self::chinese(abs($number));
        }

        $amount = round((float) $number, 2);
        $integerPart = (int) $amount;
        $decimalPart = (int) round(($amount - $integerPart) * 100);

        $result = $integerPart > 0 ? self::intToChinese($integerPart) . '元' : '零元';

        if ($decimalPart === 0) {
            $result .= '整';
        } else {
            $jiao = (int) ($decimalPart / 10);
            $fen = $decimalPart % 10;

            if ($integerPart > 0 && $jiao === 0) {
                $result .= '零';
            }
            if ($jiao > 0) {
                $result .= self::CHINESE_NUMBERS[$jiao] . '角';
            }
            if ($fen > 0) {
                $result .= self::CHINESE_NUMBERS[$fen] . '分';
            }
        }

        return $result;
    }

    /**
     * 缩写大数字（K/M/B/T）
     */
    public static function abbreviate(float|int $number, int $precision = 1): string
    {
        $abs = abs((float) $number);
        $suffix = '';
        $divisor = 1;

        if ($abs >= 1_000_000_000_000) {
            $suffix = 'T';
            $divisor = 1_000_000_000_000;
        } elseif ($abs >= 1_000_000_000) {
            $suffix = 'B';
            $divisor = 1_000_000_000;
        } elseif ($abs >= 1_000_000) {
            $suffix = 'M';
            $divisor = 1_000_000;
        } elseif ($abs >= 1_000) {
            $suffix = 'K';
            $divisor = 1_000;
        }

        $value = round($abs / $divisor, $precision);

        // 去除末尾的 .0
        if ($precision > 0 && $value == (int) $value) {
            $value = (int) $value;
        }

        return ($number < 0 ? '-' : '') . $value . $suffix;
    }

    /**
     * 获取序数词（1st, 2nd, 3rd, 4th...）
     */
    public static function ordinal(int $number): string
    {
        $abs = abs($number);
        $lastTwo = $abs % 100;
        $lastOne = $abs % 10;

        $suffix = match (true) {
            $lastTwo >= 11 && $lastTwo <= 13 => 'th',
            $lastOne === 1 => 'st',
            $lastOne === 2 => 'nd',
            $lastOne === 3 => 'rd',
            default => 'th',
        };

        return $number . $suffix;
    }

    /**
     * 转换为百分比
     */
    public static function percentage(float|int $number, int $decimals = 0): string
    {
        return number_format((float) $number * 100, $decimals) . '%';
    }

    /**
     * 将字节数转换为人类可读格式
     */
    public static function fileSize(float|int $bytes, int $precision = 2): string
    {
        return DataSize::format((int) $bytes, $precision);
    }

    /**
     * 限制数值在指定范围内
     */
    public static function clamp(float|int $number, float|int $min, float|int $max): float|int
    {
        return max($min, min($max, $number));
    }

    /**
     * 判断是否为偶数
     */
    public static function even(float|int $number): bool
    {
        return ((int) $number) % 2 === 0;
    }

    /**
     * 判断是否为奇数
     */
    public static function odd(float|int $number): bool
    {
        return ! self::even($number);
    }

    /**
     * 四舍五入到最近的指定倍数
     */
    public static function roundTo(float|int $number, float|int $multiple = 1): float|int
    {
        if ($multiple === 0) {
            throw new InvalidArgumentException('Multiple cannot be zero');
        }
        return round((float) $number / (float) $multiple) * (float) $multiple;
    }

    /**
     * 将整数转为中文数字
     */
    private static function intToChinese(int $number): string
    {
        if ($number === 0) {
            return '零';
        }

        $result = '';
        $groupIndex = 0;

        while ($number > 0) {
            $group = $number % 10000;
            $number = (int) ($number / 10000);

            if ($group > 0) {
                $groupStr = self::groupToChinese($group);
                $result = $groupStr . self::CHINESE_BIG_UNITS[$groupIndex] . $result;
            } elseif ($result !== '' && ! str_starts_with($result, '零')) {
                $result = '零' . $result;
            }

            $groupIndex++;
        }

        return rtrim($result, '零');
    }

    private static function groupToChinese(int $number): string
    {
        $result = '';
        $hasZero = false;

        for ($i = 0; $i < 4 && $number > 0; $i++) {
            $digit = $number % 10;
            $number = (int) ($number / 10);

            if ($digit === 0) {
                if (! $hasZero && $result !== '') {
                    $result = '零' . $result;
                    $hasZero = true;
                }
            } else {
                $result = self::CHINESE_NUMBERS[$digit] . self::CHINESE_UNITS[$i] . $result;
                $hasZero = false;
            }
        }

        return $result;
    }
}
