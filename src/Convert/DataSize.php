<?php

/**
 * ============================================================================
 * 数据大小转换工具
 * ============================================================================
 *
 * 提供数据大小单位之间的精确转换（B, KB, MB, GB, TB, PB）。
 * 支持从字符串解析（如 '2.5GB'）以及自动格式化输出。
 *
 * @package     zxf\Utils\Convert
 * @author      zxf\Utils
 * @version     1.0.0
 * @license     MIT
 * @link        https://github.com/zhaoxianfang/utils
 * @since       1.0.0
 */

declare(strict_types=1);

namespace zxf\Utils\Convert;

use InvalidArgumentException;

/**
 * 数据大小转换工具
 *
 * @example
 * echo DataSize::bytes(1536)->toKb(); // 1.5
 * echo DataSize::parse('2.5GB')->toBytes(); // 2684354560
 */
final class DataSize
{
    /**
     * 各单位对应的字节数。
     *
     * 采用二进制进制（1024），与 autoFormat() 使用的层级保持一致。
     */
    private const UNITS = [
        'B' => 1,
        'KB' => 1024,
        'MB' => 1048576,
        'GB' => 1073741824,
        'TB' => 1099511627776,
        'PB' => 1125899906842624,
    ];

    /**
     * 私有构造器：对象不可变，请通过 bytes() 或 parse() 创建实例。
     *
     * @param int $bytes 以字节为单位的原始大小
     */
    private function __construct(private int $bytes)
    {
    }

    /**
     * 从字节数创建
     *
     * @param  int|float  $bytes  字节数（浮点数会四舍五入为整数）
     * @return self
     *
     * @example
     * ```php
     * DataSize::bytes(1536)->toKb(); // 1.5
     * ```
     */
    public static function bytes(int|float $bytes): self
    {
        return new self((int) round($bytes));
    }

    /**
     * 从字符串解析（如 "1.5GB", "1024KB"）
     *
     * 解析规则：先 trim，再用正则匹配「数字 + 可选单位」，数字允许千分位逗号
     * 与前导小数（如 ".5MB"）；单位缺省为 B，大小写不敏感且只接受
     * B/KB/MB/GB/TB/PB 这六种。去掉逗号后按 1024 进制换算成字节并四舍五入取整。
     *
     * @param string $size 数据大小字符串，如 "2.5GB"、"1024 kb"、"512"
     * @return self 以字节为单位的新实例
     *
     * @throws InvalidArgumentException 格式非法、单位不支持或数值不合法时抛出
     */
    public static function parse(string $size): self
    {
        $size = trim($size);

        // 仅允许 UNITS 中实际支持的单位（B/KB/MB/GB/TB/PB），
        // 避免正则接受 EB 等未定义单位导致 “Unknown unit” 异常（与 autoFormat 支持的层级保持一致）
        if (! preg_match('/^(\d[\d,]*(?:\.\d+)?|\.\d+)\s*([KMGTP]?B)?$/i', $size, $matches)) {
            throw new InvalidArgumentException('Invalid data size format: ' . $size);
        }

        // 去掉千分位分隔符后校验为合法数字（正则已保证格式，这里防御 "1.2.3" 之类的异常输入）
        $number = str_replace(',', '', $matches[1]);
        if (! is_numeric($number)) {
            throw new InvalidArgumentException('Invalid data size number: ' . $matches[1]);
        }

        $value = (float) $number;
        $unit = strtoupper($matches[2] ?? 'B');

        if (! isset(self::UNITS[$unit])) {
            throw new InvalidArgumentException('Unknown unit: ' . $unit);
        }

        if ($value < 0 || is_nan($value) || is_infinite($value)) {
            throw new InvalidArgumentException('Data size must be a finite non-negative number: ' . $size);
        }

        return new self((int) round($value * self::UNITS[$unit]));
    }

    /**
     * 转换为字节数。
     *
     * @return int 字节数（内部即以字节存储，此为无损读取）
     *
     * @example
     * ```php
     * DataSize::parse('2.5GB')->toBytes(); // 2684354560
     * ```
     */
    public function toBytes(): int
    {
        return $this->bytes;
    }

    /**
     * 转换为 KB。
     *
     * 按 1024 字节 = 1 KB 的二进制进制换算，并对结果四舍五入到 $precision 位小数；
     * 不足 1 KB 时会返回小数（如 512 字节 → 0.5）。
     *
     * @param  int    $precision  保留小数位数
     * @return float 以 KB 为单位、已四舍五入的数值
     */
    public function toKb(int $precision = 2): float
    {
        return round($this->bytes / self::UNITS['KB'], $precision);
    }

    /**
     * 转换为 MB。
     *
     * 按 1048576 字节 = 1 MB 的二进制进制换算，并对结果四舍五入到 $precision 位小数；
     * 不足 1 MB 时返回小数（如 512 KB → 0.5）。
     *
     * @param  int    $precision  保留小数位数
     * @return float 以 MB 为单位、已四舍五入的数值
     */
    public function toMb(int $precision = 2): float
    {
        return round($this->bytes / self::UNITS['MB'], $precision);
    }

    /**
     * 转换为 GB。
     *
     * 按 1073741824 字节 = 1 GB 的二进制进制换算，并对结果四舍五入到 $precision 位小数；
     * 数值小于 1 GB 时返回小数，不会做单位降级。
     *
     * @param  int    $precision  保留小数位数
     * @return float 以 GB 为单位、已四舍五入的数值
     */
    public function toGb(int $precision = 2): float
    {
        return round($this->bytes / self::UNITS['GB'], $precision);
    }

    /**
     * 转换为 TB。
     *
     * 按 1099511627776 字节 = 1 TB 的二进制进制换算，并对结果四舍五入到 $precision 位小数；
     * 数值小于 1 TB 时返回小数，不会做单位降级。
     *
     * @param  int    $precision  保留小数位数
     * @return float 以 TB 为单位、已四舍五入的数值
     */
    public function toTb(int $precision = 2): float
    {
        return round($this->bytes / self::UNITS['TB'], $precision);
    }

    /**
     * 转换为 PB。
     *
     * 按 1125899906842624 字节 = 1 PB 的二进制进制换算，并对结果四舍五入到 $precision
     * 位小数；PB 是支持的最大单位，超过该量级也不会再升级单位。
     *
     * @param  int    $precision  保留小数位数
     * @return float 以 PB 为单位、已四舍五入的数值
     */
    public function toPb(int $precision = 2): float
    {
        return round($this->bytes / self::UNITS['PB'], $precision);
    }

    /**
     * 自动选择最合适的单位格式化
     *
     * 以 1024 为进制逐级放大，字节级（B）不保留小数。
     *
     * @param  int     $precision  非字节单位保留的小数位数
     * @return string 形如 "512 B"、"1.5 KB"、"2.5 GB" 的可读字符串
     *
     * @example
     * ```php
     * DataSize::bytes(512)->autoFormat();      // "512 B"
     * DataSize::bytes(1536)->autoFormat();     // "1.5 KB"
     * DataSize::parse('2.5GB')->autoFormat();  // "2.5 GB"
     * ```
     */
    public function autoFormat(int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $lastIndex = count($units) - 1;

        $value = (float) $this->bytes;
        $unitIndex = 0;

        while ($value >= 1024 && $unitIndex < $lastIndex) {
            $value /= 1024;
            $unitIndex++;
        }

        // 字节是整数单位，输出小数没有意义（如 "512.00 B"）
        $number = $unitIndex === 0
            ? (string) (int) round($value)
            : (string) round($value, $precision);

        return $number . ' ' . $units[$unitIndex];
    }

    /**
     * 格式化字节数（兼容常见显示）
     *
     * 等价于 DataSize::bytes($bytes)->autoFormat($precision)，用于已有字节数时的快捷调用。
     *
     * @param  int     $bytes      字节数
     * @param  int     $precision  非字节单位保留的小数位数
     * @return string 可读字符串
     *
     * @example
     * ```php
     * DataSize::format(1536); // "1.5 KB"
     * ```
     */
    public static function format(int $bytes, int $precision = 2): string
    {
        return self::bytes($bytes)->autoFormat($precision);
    }

    /**
     * 与另一个 DataSize 相加。
     *
     * 对象不可变：返回新实例，不修改当前对象。
     *
     * @param  self  $other  另一个数据大小
     * @return self 相加结果的新实例
     *
     * @example
     * ```php
     * DataSize::parse('1MB')->add(DataSize::parse('512KB'))->autoFormat(); // "1.5 MB"
     * ```
     */
    public function add(self $other): self
    {
        return new self($this->bytes + $other->bytes);
    }

    /**
     * 相减（结果不会为负，小于 0 时按 0 处理）
     *
     * @param  self  $other  要减去的数据大小
     * @return self 相减结果的新实例（最小为 0 字节）
     */
    public function subtract(self $other): self
    {
        return new self(max(0, $this->bytes - $other->bytes));
    }

    /**
     * 判断是否与另一个 DataSize 相等（字节数完全相同）。
     *
     * @param  self  $other  比较对象
     * @return bool
     *
     * @example
     * ```php
     * DataSize::parse('1024KB')->equals(DataSize::parse('1MB')); // true
     * ```
     */
    public function equals(self $other): bool
    {
        return $this->bytes === $other->bytes;
    }

    /**
     * 判断是否大于另一个 DataSize。
     *
     * @param  self  $other  比较对象
     * @return bool
     *
     * @example
     * ```php
     * DataSize::parse('2MB')->greaterThan(DataSize::parse('1MB')); // true
     * ```
     */
    public function greaterThan(self $other): bool
    {
        return $this->bytes > $other->bytes;
    }

    /**
     * 判断是否小于另一个 DataSize。
     *
     * @param  self  $other  比较对象
     * @return bool
     *
     * @example
     * ```php
     * DataSize::parse('512KB')->lessThan(DataSize::parse('1MB')); // true
     * ```
     */
    public function lessThan(self $other): bool
    {
        return $this->bytes < $other->bytes;
    }
}
