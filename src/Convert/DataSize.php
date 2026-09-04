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
    private const UNITS = [
        'B' => 1,
        'KB' => 1024,
        'MB' => 1048576,
        'GB' => 1073741824,
        'TB' => 1099511627776,
        'PB' => 1125899906842624,
    ];

    private function __construct(private int $bytes)
    {
    }

    /**
     * 从字节数创建
     */
    public static function bytes(int|float $bytes): self
    {
        return new self((int) round($bytes));
    }

    /**
     * 从字符串解析（如 "1.5GB", "1024KB"）
     *
     * @param string $size 数据大小字符串，如 "2.5GB"、"1024 kb"、"512"
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

    public function toBytes(): int
    {
        return $this->bytes;
    }

    public function toKb(int $precision = 2): float
    {
        return round($this->bytes / self::UNITS['KB'], $precision);
    }

    public function toMb(int $precision = 2): float
    {
        return round($this->bytes / self::UNITS['MB'], $precision);
    }

    public function toGb(int $precision = 2): float
    {
        return round($this->bytes / self::UNITS['GB'], $precision);
    }

    public function toTb(int $precision = 2): float
    {
        return round($this->bytes / self::UNITS['TB'], $precision);
    }

    public function toPb(int $precision = 2): float
    {
        return round($this->bytes / self::UNITS['PB'], $precision);
    }

    /**
     * 自动选择最合适的单位格式化
     *
     * 以 1024 为进制逐级放大，字节级（B）不保留小数。
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
     */
    public static function format(int $bytes, int $precision = 2): string
    {
        return self::bytes($bytes)->autoFormat($precision);
    }

    public function add(self $other): self
    {
        return new self($this->bytes + $other->bytes);
    }

    /**
     * 相减（结果不会为负，小于 0 时按 0 处理）
     */
    public function subtract(self $other): self
    {
        return new self(max(0, $this->bytes - $other->bytes));
    }

    public function equals(self $other): bool
    {
        return $this->bytes === $other->bytes;
    }

    public function greaterThan(self $other): bool
    {
        return $this->bytes > $other->bytes;
    }

    public function lessThan(self $other): bool
    {
        return $this->bytes < $other->bytes;
    }
}
