<?php

namespace zxf\Utils\QrCode\Color;

use Exception;

/**
 * 颜色类
 * 用于设置二维码的前景色和背景色
 */
class Color
{
    /** @var int 红色分量 (0-255) */
    private int $red;

    /** @var int 绿色分量 (0-255) */
    private int $green;

    /** @var int 蓝色分量 (0-255) */
    private int $blue;

    /** @var int|null 透明度分量 (0-127, 0为完全不透明, 127为完全透明) */
    private ?int $alpha;

    /**
     * 构造函数
     *
     * @param int $red 红色分量 (0-255)
     * @param int $green 绿色分量 (0-255)
     * @param int $blue 蓝色分量 (0-255)
     * @param int|null $alpha 透明度 (0-127, null表示完全不透明)
     * @throws Exception 如果颜色值超出范围
     */
    public function __construct(int $red, int $green, int $blue, ?int $alpha = null)
    {
        if ($red < 0 || $red > 255) {
            throw new Exception('红色分量必须在0-255之间');
        }
        if ($green < 0 || $green > 255) {
            throw new Exception('绿色分量必须在0-255之间');
        }
        if ($blue < 0 || $blue > 255) {
            throw new Exception('蓝色分量必须在0-255之间');
        }
        if ($alpha !== null && ($alpha < 0 || $alpha > 127)) {
            throw new Exception('透明度必须在0-127之间');
        }

        $this->red = $red;
        $this->green = $green;
        $this->blue = $blue;
        $this->alpha = $alpha;
    }

    /**
     * 创建白色（完全不透明）
     *
     * @return self
     */
    public static function white(): self
    {
        return new self(255, 255, 255);
    }

    /**
     * 创建黑色（完全不透明）
     *
     * @return self
     */
    public static function black(): self
    {
        return new self(0, 0, 0);
    }

    /**
     * 创建红色（完全不透明）
     *
     * @return self
     */
    public static function red(): self
    {
        return new self(255, 0, 0);
    }

    /**
     * 创建绿色（完全不透明）
     *
     * @return self
     */
    public static function green(): self
    {
        return new self(0, 255, 0);
    }

    /**
     * 创建蓝色（完全不透明）
     *
     * @return self
     */
    public static function blue(): self
    {
        return new self(0, 0, 255);
    }

    /**
     * 从十六进制颜色值创建
     * 支持格式: #RGB, #RGBA, #RRGGBB, #RRGGBBAA
     *
     * @param string $hex 十六进制颜色值
     * @return self
     * @throws Exception 如果格式无效
     */
    public static function fromHex(string $hex): self
    {
        $hex = ltrim($hex, '#');

        // 验证是否为有效的十六进制字符串
        if (!ctype_xdigit($hex)) {
            throw new Exception('无效的十六进制颜色值: ' . $hex);
        }

        if (strlen($hex) === 3) {
            # RGB格式，每个字符重复一次
            $r = @hexdec(str_repeat($hex[0], 2));
            $g = @hexdec(str_repeat($hex[1], 2));
            $b = @hexdec(str_repeat($hex[2], 2));
            return new self($r, $g, $b);
        }

        if (strlen($hex) === 4) {
            # RGBA格式
            $r = @hexdec(str_repeat($hex[0], 2));
            $g = @hexdec(str_repeat($hex[1], 2));
            $b = @hexdec(str_repeat($hex[2], 2));
            $a = @hexdec(str_repeat($hex[3], 2));
            # 转换为0-127范围
            $alpha = 127 - (int)($a * 127 / 255);
            return new self($r, $g, $b, $alpha);
        }

        if (strlen($hex) === 6) {
            # RRGGBB格式
            $r = @hexdec(substr($hex, 0, 2));
            $g = @hexdec(substr($hex, 2, 2));
            $b = @hexdec(substr($hex, 4, 2));
            return new self($r, $g, $b);
        }

        if (strlen($hex) === 8) {
            # RRGGBBAA格式
            $r = @hexdec(substr($hex, 0, 2));
            $g = @hexdec(substr($hex, 2, 2));
            $b = @hexdec(substr($hex, 4, 2));
            $a = @hexdec(substr($hex, 6, 2));
            $alpha = 127 - (int)($a * 127 / 255);
            return new self($r, $g, $b, $alpha);
        }

        throw new Exception('无效的十六进制颜色值: ' . $hex);
    }

    /**
     * 获取红色分量
     *
     * @return int
     */
    public function getRed(): int
    {
        return $this->red;
    }

    /**
     * 获取绿色分量
     *
     * @return int
     */
    public function getGreen(): int
    {
        return $this->green;
    }

    /**
     * 获取蓝色分量
     *
     * @return int
     */
    public function getBlue(): int
    {
        return $this->blue;
    }

    /**
     * 获取透明度分量
     *
     * @return int|null
     */
    public function getAlpha(): ?int
    {
        return $this->alpha;
    }

    /**
     * 转换为GD库颜色索引
     *
     * @param resource $image GD图像资源
     * @return int
     */
    public function toGdColor($image): int
    {
        if ($this->alpha === null) {
            return imagecolorallocate($image, $this->red, $this->green, $this->blue);
        }
        return imagecolorallocatealpha($image, $this->red, $this->green, $this->blue, $this->alpha);
    }

    /**
     * 转换为十六进制颜色字符串（不含#）
     *
     * @return string
     */
    public function toHex(): string
    {
        $hex = sprintf('%02x%02x%02x', $this->red, $this->green, $this->blue);
        if ($this->alpha !== null) {
            $a = 255 - (int)($this->alpha * 255 / 127);
            $hex .= sprintf('%02x', $a);
        }
        return $hex;
    }

    /**
     * 字符串表示
     *
     * @return string
     */
    public function __toString(): string
    {
        return '#' . $this->toHex();
    }

    /**
     * 克隆颜色对象
     *
     * @return self
     */
    public function clone(): self
    {
        return new self($this->red, $this->green, $this->blue, $this->alpha);
    }
}
