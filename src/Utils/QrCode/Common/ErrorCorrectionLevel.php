<?php
declare(strict_types = 1);

namespace zxf\Utils\QrCode\Common;

use zxf\Utils\QrCode\Enum\AbstractEnum;
use zxf\Utils\QrCode\Exception\InvalidArgumentException;
use zxf\Utils\QrCode\Exception\OutOfBoundsException;

/**
 * 枚举类，表示四个错误纠正级别
 *
 * @method static self L() ~7% 纠错 ，别名：low()
 * @method static self M() ~15% 纠错，别名：medium()
 * @method static self Q() ~25% 纠错，别名：quartile()
 * @method static self H() ~30% 纠错，别名：high()
 */
final class ErrorCorrectionLevel extends AbstractEnum
{
    protected const L = [0x01];
    protected const M = [0x00];
    protected const Q = [0x03];
    protected const H = [0x02];

    protected function __construct(private readonly int $bits)
    {
    }

    /**
     * 通过比特值获取错误纠正级别
     *
     * @throws OutOfBoundsException 如果比特值无效
     */
    public static function forBits(int $bits) : self
    {
        return match ($bits) {
            0 => self::M(),
            1 => self::L(),
            2 => self::H(),
            3 => self::Q(),
            default => throw new OutOfBoundsException('Invalid number of bits'),
        };
    }

    /**
     * 返回用于编码此错误纠正级别的两个比特
     */
    public function getBits() : int
    {
        return $this->bits;
    }

    /**
     * 返回low级别（L的别名）
     */
    public static function low(): self
    {
        return self::L();
    }

    /**
     * 返回medium级别（M的别名）
     */
    public static function medium(): self
    {
        return self::M();
    }

    /**
     * 返回quartile级别（Q的别名）
     */
    public static function quartile(): self
    {
        return self::Q();
    }

    /**
     * 返回high级别（H的别名）
     */
    public static function high(): self
    {
        return self::H();
    }

    /**
     * 从值获取纠错级别（兼容旧版本）
     *
     * @param int $value 纠错级别值
     * @return self
     */
    public static function fromValue(int $value): self
    {
        return match($value) {
            0 => self::L(),
            1 => self::M(),
            2 => self::Q(),
            3 => self::H(),
            default => throw new InvalidArgumentException('无效的错误纠正级别值: ' . $value)
        };
    }

    /**
     * 获取纠错级别名称
     *
     * @return string
     */
    public function getName(): string
    {
        return match ($this->bits) {
            0 => 'M',
            1 => 'L',
            2 => 'H',
            3 => 'Q',
            default => 'M'
        };
    }
}
