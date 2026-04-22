<?php

declare(strict_types=1);

namespace zxf\Utils\Convert;

use InvalidArgumentException;

/**
 * 单位转换工具类
 * 支持文件大小、温度、长度、重量、时间、角度等常见单位转换
 *
 * @package Convert
 * @version 1.0.0
 * @license MIT
 */
class Unit
{
    /** @var string[] 文件大小单位列表（从 B 到 EB） */
    private const SIZE_UNITS = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB'];

    /**
     * 将字节数转换为人类可读的格式
     *
     * 示例：1536 → "1.5 KB"
     *
     * @param int|float $bytes     字节数，必须大于等于0
     * @param int       $precision 小数精度，默认为2
     * @return string 人类可读的文件大小字符串
     * @throws InvalidArgumentException 当字节数为负数时抛出
     */
    public static function bytesToHuman(int|float $bytes, int $precision = 2): string
    {
        if ($bytes < 0) {
            throw new InvalidArgumentException('字节数不能为负数');
        }
        if ($bytes === 0) return '0 B';
        $exp = (int) floor(log($bytes, 1024));
        $exp = min($exp, count(self::SIZE_UNITS) - 1);
        $value = $bytes / pow(1024, $exp);
        return round($value, $precision) . ' ' . self::SIZE_UNITS[$exp];
    }

    /**
     * 将人类可读的文件大小字符串转换为字节数
     *
     * 示例："1.5 KB" → 1536, "2MB" → 2097152
     *
     * @param string $size 文件大小字符串，如 "10 MB", "1.5G"
     * @return int 对应的字节数
     * @throws InvalidArgumentException 当格式无效或单位不支持时抛出
     */
    public static function humanToBytes(string $size): int
    {
        $size = trim($size);
        if (!preg_match('/^(\d+(?:\.\d+)?)\s*([KMGTPE]?B?)$/i', $size, $matches)) {
            throw new InvalidArgumentException('无效的文件大小格式');
        }
        $value = (float) $matches[1];
        $unit = strtoupper($matches[2]);
        $map = ['B' => 0, 'K' => 1, 'KB' => 1, 'M' => 2, 'MB' => 2, 'G' => 3, 'GB' => 3, 'T' => 4, 'TB' => 4, 'P' => 5, 'PB' => 5, 'E' => 6, 'EB' => 6];
        if (!isset($map[$unit])) {
            throw new InvalidArgumentException('不支持的单位: ' . $unit);
        }
        return (int) ($value * pow(1024, $map[$unit]));
    }

    // ========== 温度转换 ==========

    /**
     * 摄氏度转华氏度
     *
     * 公式：°F = °C × 9/5 + 32
     *
     * @param float $c 摄氏度
     * @return float 华氏度
     */
    public static function celsiusToFahrenheit(float $c): float
    {
        return $c * 9 / 5 + 32;
    }

    /**
     * 华氏度转摄氏度
     *
     * 公式：°C = (°F - 32) × 5/9
     *
     * @param float $f 华氏度
     * @return float 摄氏度
     */
    public static function fahrenheitToCelsius(float $f): float
    {
        return ($f - 32) * 5 / 9;
    }

    /**
     * 摄氏度转开尔文
     *
     * 公式：K = °C + 273.15
     *
     * @param float $c 摄氏度
     * @return float 开尔文
     */
    public static function celsiusToKelvin(float $c): float
    {
        return $c + 273.15;
    }

    /**
     * 开尔文转摄氏度
     *
     * 公式：°C = K - 273.15
     *
     * @param float $k 开尔文
     * @return float 摄氏度
     */
    public static function kelvinToCelsius(float $k): float
    {
        return $k - 273.15;
    }

    // ========== 长度转换 ==========

    /** @var array<string,float> 长度单位到米的换算比例 */
    private const LENGTH_RATIOS = [
        'nm'  => 1e-9,
        'um'  => 1e-6,
        'mm'  => 1e-3,
        'cm'  => 1e-2,
        'm'   => 1,
        'km'  => 1e3,
        'in'  => 0.0254,
        'ft'  => 0.3048,
        'yd'  => 0.9144,
        'mi'  => 1609.344,
        'nmi' => 1852,
    ];

    /**
     * 在任意两个长度单位之间进行转换
     *
     * 支持的单位：nm(纳米), um(微米), mm(毫米), cm(厘米), m(米), km(千米),
     *            in(英寸), ft(英尺), yd(码), mi(英里), nmi(海里)
     *
     * @param float  $value 数值
     * @param string $from  源单位
     * @param string $to    目标单位
     * @return float 转换后的数值
     * @throws InvalidArgumentException 当单位不支持时抛出
     */
    public static function convertLength(float $value, string $from, string $to): float
    {
        if (!isset(self::LENGTH_RATIOS[$from]) || !isset(self::LENGTH_RATIOS[$to])) {
            throw new InvalidArgumentException('不支持的长度单位');
        }
        return $value * self::LENGTH_RATIOS[$from] / self::LENGTH_RATIOS[$to];
    }

    // ========== 重量转换 ==========

    /** @var array<string,float> 重量单位到千克的换算比例 */
    private const WEIGHT_RATIOS = [
        'mg' => 1e-6,
        'g'  => 1e-3,
        'kg' => 1,
        't'  => 1e3,
        'oz' => 0.0283495,
        'lb' => 0.453592,
    ];

    /**
     * 在任意两个重量单位之间进行转换
     *
     * 支持的单位：mg(毫克), g(克), kg(千克), t(吨), oz(盎司), lb(磅)
     *
     * @param float  $value 数值
     * @param string $from  源单位
     * @param string $to    目标单位
     * @return float 转换后的数值
     * @throws InvalidArgumentException 当单位不支持时抛出
     */
    public static function convertWeight(float $value, string $from, string $to): float
    {
        if (!isset(self::WEIGHT_RATIOS[$from]) || !isset(self::WEIGHT_RATIOS[$to])) {
            throw new InvalidArgumentException('不支持的重量单位');
        }
        return $value * self::WEIGHT_RATIOS[$from] / self::WEIGHT_RATIOS[$to];
    }

    // ========== 时间转换 ==========

    /** @var array<string,float> 时间单位到秒的换算比例 */
    private const TIME_RATIOS = [
        'ms'  => 0.001,
        's'   => 1,
        'min' => 60,
        'h'   => 3600,
        'd'   => 86400,
        'w'   => 604800,
        'mo'  => 2592000,
        'y'   => 31536000,
    ];

    /**
     * 在任意两个时间单位之间进行转换
     *
     * 支持的单位：ms(毫秒), s(秒), min(分钟), h(小时), d(天), w(周), mo(月,按30天), y(年,按365天)
     *
     * @param float  $value 数值
     * @param string $from  源单位
     * @param string $to    目标单位
     * @return float 转换后的数值
     * @throws InvalidArgumentException 当单位不支持时抛出
     */
    public static function convertTime(float $value, string $from, string $to): float
    {
        if (!isset(self::TIME_RATIOS[$from]) || !isset(self::TIME_RATIOS[$to])) {
            throw new InvalidArgumentException('不支持的时间单位');
        }
        return $value * self::TIME_RATIOS[$from] / self::TIME_RATIOS[$to];
    }

    /**
     * 将秒数转换为人类可读的中文时间描述
     *
     * 示例：3661 → "1小时1分钟1秒"
     *
     * @param int $seconds 秒数
     * @return string 中文时间描述
     */
    public static function secondsToHuman(int $seconds): string
    {
        if ($seconds < 0) $seconds = 0;
        $units = [
            '年'   => 31536000,
            '天'   => 86400,
            '小时' => 3600,
            '分钟' => 60,
            '秒'   => 1,
        ];
        $parts = [];
        foreach ($units as $name => $unit) {
            if ($seconds >= $unit) {
                $count = (int) floor($seconds / $unit);
                $parts[] = $count . $name;
                $seconds %= $unit;
            }
        }
        return empty($parts) ? '0秒' : implode('', $parts);
    }

    // ========== 角度转换 ==========

    /**
     * 角度转弧度
     *
     * 公式：rad = deg × π / 180
     *
     * @param float $deg 角度值
     * @return float 弧度值
     */
    public static function degreesToRadians(float $deg): float
    {
        return $deg * M_PI / 180;
    }

    /**
     * 弧度转角度
     *
     * 公式：deg = rad × 180 / π
     *
     * @param float $rad 弧度值
     * @return float 角度值
     */
    public static function radiansToDegrees(float $rad): float
    {
        return $rad * 180 / M_PI;
    }

    // ========== 数字进制转换 ==========

    /**
     * 在任意进制之间转换数字字符串
     *
     * 支持的进制范围：2 ~ 36
     *
     * @param string $number    待转换的数字字符串
     * @param int    $fromBase  源进制（2-36）
     * @param int    $toBase    目标进制（2-36）
     * @return string 转换后的数字字符串
     * @throws InvalidArgumentException 当进制超出支持范围时抛出
     */
    public static function baseConvert(string $number, int $fromBase, int $toBase): string
    {
        if ($fromBase < 2 || $fromBase > 36 || $toBase < 2 || $toBase > 36) {
            throw new InvalidArgumentException('进制必须在 2-36 之间');
        }
        $decimal = base_convert($number, $fromBase, 10);
        return base_convert($decimal, 10, $toBase);
    }

    // ========== 百分比计算 ==========

    /**
     * 计算某个值相对于总数的百分比
     *
     * @param float $value     部分值
     * @param float $total     总值
     * @param int   $precision 小数精度，默认为2
     * @return float 百分比数值
     * @throws InvalidArgumentException 当总数为0时抛出
     */
    public static function percentage(float $value, float $total, int $precision = 2): float
    {
        if ($total == 0) {
            throw new InvalidArgumentException('总数不能为0');
        }
        return round(($value / $total) * 100, $precision);
    }
}
