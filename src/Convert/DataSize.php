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
     */
    public static function parse(string $size): self
    {
        $size = trim($size);
        if (! preg_match('/^([\d.]+)\s*([KMGTPE]?B)$/i', $size, $matches)) {
            throw new \InvalidArgumentException('Invalid data size format: ' . $size);
        }

        $value = (float) $matches[1];
        $unit = strtoupper($matches[2]);

        if (! isset(self::UNITS[$unit])) {
            throw new \InvalidArgumentException('Unknown unit: ' . $unit);
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

    /**
     * 自动选择最合适的单位格式化
     */
    public function autoFormat(int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        $value = $this->bytes;
        $unitIndex = 0;

        while ($value >= 1024 && $unitIndex < count($units) - 1) {
            $value /= 1024;
            $unitIndex++;
        }

        return round($value, $precision) . ' ' . $units[$unitIndex];
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
