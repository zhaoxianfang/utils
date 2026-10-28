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

/**
 * 数字格式化与转换工具类
 *
 * 提供数字在多场景下的格式化与转换能力：货币千分位、中文大写金额、
 * 缩写（K/M/B/T）、序数词、百分比、字节大小、进制与步长取整、奇偶判断等。
 * 所有方法均为静态调用，不维护实例状态。
 */
final class Number
{
    private const CHINESE_NUMBERS = ['零', '壹', '贰', '叁', '肆', '伍', '陆', '柒', '捌', '玖'];

    private const CHINESE_UNITS = ['', '拾', '佰', '仟'];

    private const CHINESE_BIG_UNITS = ['', '万', '亿', '万亿', '亿亿', '万亿亿', '亿亿亿'];

    /**
     * 格式化为货币格式（千分位）
     *
     * 直接委托 number_format()：按 $decimals 四舍五入，整数部分每三位插入
     * $thousandsSeparator。注意本方法只做数字排版，不附加货币符号。
     *
     * @param float|int $number             待格式化的数值
     * @param int       $decimals           保留小数位数，0 表示取整
     * @param string    $decimalPoint       小数点字符，如 "."、"、"
     * @param string    $thousandsSeparator 千分位分隔符，传空串则不分组
     * @return string 格式化后的数字字符串
     */
    public static function currency(float|int $number, int $decimals = 2, string $decimalPoint = '.', string $thousandsSeparator = ','): string
    {
        return number_format((float) $number, $decimals, $decimalPoint, $thousandsSeparator);
    }

    /**
     * 转换为中文大写金额
     *
     * 先四舍五入到 2 位小数，整数部分按 4 位分组转为中文大写，小数部分拆成
     * 角、分两级输出；无小数时补「整」，整数位存在而角位为 0 时会补「零」
     * （如 1.05 → "壹元零伍分"）。负数在最前面加「负」。
     *
     * @param float|int $number 金额数值，建议不要超过 int 可表示范围
     * @return string 中文大写金额，如 "壹仟贰佰叁拾肆元伍角陆分"
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
     *
     * 按 1000 进制（非 1024）逐级选取 K/M/B/T 后缀，绝对值小于 1000 时不加后缀；
     * 结果四舍五入到 $precision 位小数，若小数部分为 0 则省略（输出 "1.2M" 而非
     * "1.20M"）。负数保留负号并作用于绝对值。
     *
     * @param float|int $number    待缩写的数值
     * @param int       $precision 保留小数位数
     * @return string 带单位的缩写字符串，如 "1.2M"、"-3.5K"、"999"
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
     *
     * 按英文规则判定后缀：11~13 固定为 th（eleventh/thirteenth），
     * 其余看个位数 1/2/3 分别取 st/nd/rd，其他为 th。
     * 负数先取绝对值判定后缀，再拼回原始数值（如 -2 → "-2nd"）。
     *
     * @param int $number 整数数值
     * @return string 序数词字符串，如 "21st"、"112th"、"-2nd"
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
     *
     * 先把数值乘以 100，再用 number_format() 处理小数位与千分位（固定英文
     * 逗号分组、点号小数点），最后拼上 "%"。因此 0.1234 会得到 "12%"（默认不保留小数），
     * 而 1234.5 会得到 "123,450%"。
     *
     * @param float|int $number   原始比例值（0.5 表示 50%）
     * @param int       $decimals 保留小数位数
     * @return string 带百分号的字符串，如 "12.34%"
     */
    public static function percentage(float|int $number, int $decimals = 0): string
    {
        return number_format((float) $number * 100, $decimals) . '%';
    }

    /**
     * 将字节数转换为人类可读格式
     *
     * 委托 DataSize::format() 处理：按 1024 进制自动升级到 B/KB/MB/GB/TB/PB
     * 中最合适的单位；为字节单位时不保留小数。$bytes 会被截断为整数。
     *
     * @param float|int $bytes     字节数，小数部分直接舍去
     * @param int       $precision 非字节单位保留的小数位数
     * @return string 可读字符串，如 "1.5 KB"、"512 B"
     */
    public static function fileSize(float|int $bytes, int $precision = 2): string
    {
        return DataSize::format((int) $bytes, $precision);
    }

    /**
     * 限制数值在指定范围内
     *
     * 小于 $min 返回 $min，大于 $max 返回 $max，区间内原样返回；
     * 不做类型转换，返回值的具体类型取自被选中的那个参数。
     * 若 $min > $max，结果恒为 $max。
     *
     * @param float|int $number 待限制的数值
     * @param float|int $min    下限
     * @param float|int $max    上限
     * @return float|int 落在 [$min, $max] 区间内的值
     */
    public static function clamp(float|int $number, float|int $min, float|int $max): float|int
    {
        return max($min, min($max, $number));
    }

    /**
     * 判断是否为偶数
     *
     * 先强制转为 int（小数部分被截断，如 2.9 视为 2），再判断除以 2 的余数；
     * 负数同样适用（-2 为偶数）。
     *
     * @param float|int $number 待判断的数值
     * @return bool 偶数返回 true，奇数返回 false
     */
    public static function even(float|int $number): bool
    {
        return ((int) $number) % 2 === 0;
    }

    /**
     * 判断是否为奇数
     *
     * 等价于对 even() 取反，因此入参同样会先截断为整数，负数亦可正确判断
     * （-3 为奇数）。
     *
     * @param float|int $number 待判断的数值
     * @return bool 奇数返回 true，偶数返回 false
     */
    public static function odd(float|int $number): bool
    {
        return ! self::even($number);
    }

    /**
     * 四舍五入到最近的指定倍数
     *
     * 等价于 round($number / $multiple) * $multiple，例如 7 按 5 取整得 5、
     * 8 按 5 取整得 10。$multiple 允许为负数（结果符号随倍数翻转）。
     * 计算过程使用浮点，返回类型实际总是 float。
     *
     * @param float|int $number   待取整的数值
     * @param float|int $multiple 目标倍数，不可为 0（含 0.0）
     * @return float|int 取整后的结果（运行时为 float）
     *
     * @throws InvalidArgumentException 倍数为 0 时抛出
     */
    public static function roundTo(float|int $number, float|int $multiple = 1): float|int
    {
        // 必须先把 multiple 转成 float 再与 0.0 比较：
        // strict_types 下 `0.0 === 0` 恒为 false，若只判断 `$multiple === 0`，
        // 调用 roundTo(5, 0.0) 会绕过校验并触发 DivisionByZeroError。
        $divisor = (float) $multiple;

        if ($divisor === 0.0) {
            throw new InvalidArgumentException('Multiple cannot be zero');
        }

        return round((float) $number / $divisor) * $divisor;
    }

    /**
     * 将整数转为中文数字
     *
     * 从低位起每 4 位切分为一组，逐组转中文并拼上分组单位（万/亿/万亿…）；
     * 中间出现整组为 0 时补一个「零」占位，末尾多余的「零」会被裁掉。
     * 仅处理非负整数，0 返回「零」；符号需由调用方（如 chinese()）处理。
     *
     * @param int $number 非负整数
     * @return string 中文大写数字，如 12345 → "壹万贰仟叁佰肆拾伍"
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
                // 使用 bigUnit() 获取分组单位，避免超大数值时数组越界（Undefined array key）
                $result = $groupStr . self::bigUnit($groupIndex) . $result;
            } elseif ($result !== '' && ! str_starts_with($result, '零')) {
                $result = '零' . $result;
            }

            $groupIndex++;
        }

        return rtrim($result, '零');
    }

    /**
     * 根据分组下标返回中文大数单位
     *
     * 中文每 4 位为一个分组：万(10^4)、亿(10^8)、万亿(10^12)、亿亿(10^16)……
     * 超过内建数组范围时，以“亿亿”为基、逐层叠加“万”（每加一个“万”即 ×10^4），
     * 保证任意大整数都不会触发数组越界，且数值含义正确。
     *
     * @param int $groupIndex 分组下标，0 表示个位组（10^0），每加 1 代表 ×10^4
     * @return string 对应的中文单位，下标 0 返回空串
     */
    private static function bigUnit(int $groupIndex): string
    {
        if ($groupIndex < count(self::CHINESE_BIG_UNITS)) {
            return self::CHINESE_BIG_UNITS[$groupIndex];
        }
        return '亿亿' . str_repeat('万', $groupIndex - 4);
    }

    /**
     * 将 4 位以内的整数分组转换为中文数字
     *
     * 处理个/拾/佰/仟 四个量级，并在零值处正确补「零」；
     * 作为 intToChinese() 的底层递归单元。
     *
     * @param int $number 0~9999 的整数
     * @return string 对应的中文数字字符串
     */
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
