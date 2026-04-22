<?php

declare(strict_types=1);

namespace zxf\Utils\Convert;

use InvalidArgumentException;

/**
 * 颜色转换工具类
 * 支持 HEX、RGB、RGBA、HSL、HSV、CMYK 等常见颜色空间格式互转
 * 并提供渐变色生成、亮度/对比色计算等实用功能
 *
 * @package Convert
 * @version 1.0.0
 * @license MIT
 */
class Color
{
    /**
     * 将 HEX 颜色字符串转换为 RGB 数组
     *
     * 支持 3位简写（#RGB）、6位标准（#RRGGBB）和 8位带透明通道（#RRGGBBAA）
     *
     * @param string $hex HEX 颜色字符串，可带或不带 "#" 前缀
     * @return array{r:int,g:int,b:int,a:float} RGBA 数组，r/g/b 范围为 0-255，a 范围为 0.0-1.0
     * @throws InvalidArgumentException 当 HEX 格式无效时抛出
     */
    public static function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        $len = strlen($hex);
        if ($len === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        } elseif ($len !== 6 && $len !== 8) {
            throw new InvalidArgumentException('无效的HEX颜色格式');
        }
        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2)),
            'a' => $len === 8 ? round(hexdec(substr($hex, 6, 2)) / 255, 2) : 1.0,
        ];
    }

    /**
     * 将 RGB 值转换为 HEX 颜色字符串
     *
     * @param int    $r         红色分量（0-255）
     * @param int    $g         绿色分量（0-255）
     * @param int    $b         蓝色分量（0-255）
     * @param float  $a         透明通道（0.0-1.0），默认为 1.0（不透明）
     * @param bool   $withAlpha 是否在 HEX 中包含 Alpha 通道，默认为 false
     * @return string HEX 颜色字符串，如 "#FF5733" 或 "#FF5733CC"
     * @throws InvalidArgumentException 当 RGB 值超出 0-255 范围时抛出
     */
    public static function rgbToHex(int $r, int $g, int $b, float $a = 1.0, bool $withAlpha = false): string
    {
        foreach ([$r, $g, $b] as $v) {
            if ($v < 0 || $v > 255) {
                throw new InvalidArgumentException('RGB值必须在 0-255 之间');
            }
        }
        $hex = sprintf('%02X%02X%02X', $r, $g, $b);
        if ($withAlpha && $a < 1.0) {
            $hex .= sprintf('%02X', (int) round($a * 255));
        }
        return '#' . $hex;
    }

    /**
     * 将 RGB 值转换为 HSL 数组
     *
     * HSL 即色相（Hue）、饱和度（Saturation）、亮度（Lightness）
     *
     * @param int $r 红色分量（0-255）
     * @param int $g 绿色分量（0-255）
     * @param int $b 蓝色分量（0-255）
     * @return array{h:float,s:float,l:float} HSL 数组，h 范围为 0-360，s/l 范围为 0-100
     */
    public static function rgbToHsl(int $r, int $g, int $b): array
    {
        $r /= 255; $g /= 255; $b /= 255;
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;

        if ($max === $min) {
            $h = $s = 0;
        } else {
            $d = $max - $min;
            $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
            $h = match ($max) {
                $r => ($g - $b) / $d + ($g < $b ? 6 : 0),
                $g => ($b - $r) / $d + 2,
                default => ($r - $g) / $d + 4,
            };
            $h /= 6;
        }

        return ['h' => round($h * 360, 2), 's' => round($s * 100, 2), 'l' => round($l * 100, 2)];
    }

    /**
     * 将 HSL 值转换为 RGB 数组
     *
     * @param float $h 色相（0-360）
     * @param float $s 饱和度（0-100）
     * @param float $l 亮度（0-100）
     * @return array{r:int,g:int,b:int} RGB 数组，各分量范围为 0-255
     */
    public static function hslToRgb(float $h, float $s, float $l): array
    {
        $h /= 360; $s /= 100; $l /= 100;
        if ($s === 0.0) {
            $r = $g = $b = $l;
        } else {
            $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
            $p = 2 * $l - $q;
            $r = self::hueToRgb($p, $q, $h + 1 / 3);
            $g = self::hueToRgb($p, $q, $h);
            $b = self::hueToRgb($p, $q, $h - 1 / 3);
        }
        return ['r' => (int) round($r * 255), 'g' => (int) round($g * 255), 'b' => (int) round($b * 255)];
    }

    /**
     * 将 RGB 值转换为 HSV 数组
     *
     * HSV 即色相（Hue）、饱和度（Saturation）、明度（Value/Brightness）
     *
     * @param int $r 红色分量（0-255）
     * @param int $g 绿色分量（0-255）
     * @param int $b 蓝色分量（0-255）
     * @return array{h:float,s:float,v:float} HSV 数组，h 范围为 0-360，s/v 范围为 0-100
     */
    public static function rgbToHsv(int $r, int $g, int $b): array
    {
        $r /= 255; $g /= 255; $b /= 255;
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $v = $max;
        $d = $max - $min;
        $s = $max === 0 ? 0 : $d / $max;

        if ($max === $min) {
            $h = 0;
        } else {
            $h = match ($max) {
                $r => ($g - $b) / $d + ($g < $b ? 6 : 0),
                $g => ($b - $r) / $d + 2,
                default => ($r - $g) / $d + 4,
            };
            $h /= 6;
        }

        return ['h' => round($h * 360, 2), 's' => round($s * 100, 2), 'v' => round($v * 100, 2)];
    }

    /**
     * 将 HSV 值转换为 RGB 数组
     *
     * @param float $h 色相（0-360）
     * @param float $s 饱和度（0-100）
     * @param float $v 明度（0-100）
     * @return array{r:int,g:int,b:int} RGB 数组，各分量范围为 0-255
     */
    public static function hsvToRgb(float $h, float $s, float $v): array
    {
        $h /= 360; $s /= 100; $v /= 100;
        $i = (int) floor($h * 6);
        $f = $h * 6 - $i;
        $p = $v * (1 - $s);
        $q = $v * (1 - $f * $s);
        $t = $v * (1 - (1 - $f) * $s);

        [$r, $g, $b] = match ($i % 6) {
            0 => [$v, $t, $p],
            1 => [$q, $v, $p],
            2 => [$p, $v, $t],
            3 => [$p, $q, $v],
            4 => [$t, $p, $v],
            default => [$v, $p, $q],
        };

        return ['r' => (int) round($r * 255), 'g' => (int) round($g * 255), 'b' => (int) round($b * 255)];
    }

    /**
     * 将 RGB 值转换为 CMYK 数组
     *
     * CMYK 即青色（Cyan）、品红（Magenta）、黄色（Yellow）、黑色（Key/Black）
     *
     * @param int $r 红色分量（0-255）
     * @param int $g 绿色分量（0-255）
     * @param int $b 蓝色分量（0-255）
     * @return array{c:int,m:int,y:int,k:int} CMYK 数组，各分量范围为 0-100
     */
    public static function rgbToCmyk(int $r, int $g, int $b): array
    {
        $r /= 255; $g /= 255; $b /= 255;
        $k = 1 - max($r, $g, $b);
        if ($k === 1.0) {
            return ['c' => 0, 'm' => 0, 'y' => 0, 'k' => 100];
        }
        $c = (1 - $r - $k) / (1 - $k);
        $m = (1 - $g - $k) / (1 - $k);
        $y = (1 - $b - $k) / (1 - $k);
        return [
            'c' => (int) round($c * 100),
            'm' => (int) round($m * 100),
            'y' => (int) round($y * 100),
            'k' => (int) round($k * 100),
        ];
    }

    /**
     * 生成随机 HEX 颜色
     *
     * @return string 随机 HEX 颜色字符串，如 "#3F7A2B"
     */
    public static function random(): string
    {
        return sprintf('#%06X', random_int(0, 0xFFFFFF));
    }

    /**
     * 在两个颜色之间生成渐变色数组
     *
     * @param string $from   起始颜色（HEX 格式）
     * @param string $to     结束颜色（HEX 格式）
     * @param int    $steps  渐变色步数（至少为2），即生成的颜色数量
     * @return string[] HEX 颜色字符串数组
     * @throws InvalidArgumentException 当步数小于2时抛出
     */
    public static function gradient(string $from, string $to, int $steps = 10): array
    {
        if ($steps < 2) {
            throw new InvalidArgumentException('步数至少为2');
        }
        $start = self::hexToRgb($from);
        $end = self::hexToRgb($to);
        $colors = [];

        for ($i = 0; $i < $steps; $i++) {
            $ratio = $i / ($steps - 1);
            $r = (int) round($start['r'] + ($end['r'] - $start['r']) * $ratio);
            $g = (int) round($start['g'] + ($end['g'] - $start['g']) * $ratio);
            $b = (int) round($start['b'] + ($end['b'] - $start['b']) * $ratio);
            $colors[] = self::rgbToHex($r, $g, $b);
        }

        return $colors;
    }

    /**
     * 计算颜色的相对亮度（基于 W3C 标准）
     *
     * 用于评估颜色的明暗程度，返回值范围为 0.0（纯黑）~ 1.0（纯白）
     *
     * @param int $r 红色分量（0-255）
     * @param int $g 绿色分量（0-255）
     * @param int $b 蓝色分量（0-255）
     * @return float 相对亮度值
     */
    public static function luminance(int $r, int $g, int $b): float
    {
        return (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
    }

    /**
     * 判断颜色是否为深色（亮度低于 0.5）
     *
     * 常用于决定在该背景色上使用白色文字还是黑色文字
     *
     * @param int $r 红色分量（0-255）
     * @param int $g 绿色分量（0-255）
     * @param int $b 蓝色分量（0-255）
     * @return bool 若为深色则返回 true
     */
    public static function isDark(int $r, int $g, int $b): bool
    {
        return self::luminance($r, $g, $b) < 0.5;
    }

    /**
     * 获取适合在指定背景色上显示的高对比度文字颜色（黑或白）
     *
     * @param int $r 红色分量（0-255）
     * @param int $g 绿色分量（0-255）
     * @param int $b 蓝色分量（0-255）
     * @return string 对比色 HEX 字符串，深色背景返回 "#FFFFFF"，浅色背景返回 "#000000"
     */
    public static function contrastColor(int $r, int $g, int $b): string
    {
        return self::isDark($r, $g, $b) ? '#FFFFFF' : '#000000';
    }

    /**
     * HSL/HSV 转换过程中的辅助函数：根据色相分段计算 RGB 分量
     *
     * @param float $p 中间值 p
     * @param float $q 中间值 q
     * @param float $t 色相偏移量 t
     * @return float 计算后的 RGB 分量（0.0-1.0）
     */
    private static function hueToRgb(float $p, float $q, float $t): float
    {
        if ($t < 0) $t += 1;
        if ($t > 1) $t -= 1;
        if ($t < 1 / 6) return $p + ($q - $p) * 6 * $t;
        if ($t < 1 / 2) return $q;
        if ($t < 2 / 3) return $p + ($q - $p) * (2 / 3 - $t) * 6;
        return $p;
    }
}
