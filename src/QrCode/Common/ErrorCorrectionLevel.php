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

    /**
     * 构造函数：保存该纠错级别对应的比特值
     *
     * @param int $bits 纠错级别比特值（0=M、1=L、2=H、3=Q）
     */
    protected function __construct(private readonly int $bits)
    {
    }

    /**
     * 通过比特值获取错误纠正级别
     *
     * 按 QR 规范中格式信息的两比特编码映射：0→M、1→L、2→H、3→Q。
     * 只接受 0~3，其余取值一律视为非法。
     *
     * @param int $bits 纠错级别比特值（0=M、1=L、2=H、3=Q）
     * @return self 对应的纠错级别单例
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
     *
     * 该值会写入二维码的格式信息区，供解码方识别容错等级。
     *
     * @return int 纠错级别比特值（0=M、1=L、2=H、3=Q）
     */
    public function getBits() : int
    {
        return $this->bits;
    }

    /**
     * 返回low级别（L的别名）
     *
     * 与 L() 完全等价，约可容忍 7% 的码字损坏，仅作为可读性更好的写法保留。
     *
     * @return self L 级别的纠错级别实例
     */
    public static function low(): self
    {
        return self::L();
    }

    /**
     * 返回medium级别（M的别名）
     *
     * 与 M() 完全等价，约可容忍 15% 的码字损坏，仅作为可读性更好的写法保留。
     *
     * @return self M 级别的纠错级别实例
     */
    public static function medium(): self
    {
        return self::M();
    }

    /**
     * 返回quartile级别（Q的别名）
     *
     * 与 Q() 完全等价，约可容忍 25% 的码字损坏，仅作为可读性更好的写法保留。
     *
     * @return self Q 级别的纠错级别实例
     */
    public static function quartile(): self
    {
        return self::Q();
    }

    /**
     * 返回high级别（H的别名）
     *
     * 与 H() 完全等价，约可容忍 30% 的码字损坏，容错能力最强但可容纳的
     * 数据量最少，仅作为可读性更好的写法保留。
     *
     * @return self H 级别的纠错级别实例
     */
    public static function high(): self
    {
        return self::H();
    }

    /**
     * 从值获取纠错级别（兼容旧版本）
     *
     * @param int $value 纠错级别值 (0=M, 1=L, 2=H, 3=Q)
     * @return self
     * @throws InvalidArgumentException 如果值无效
     */
    public static function fromValue(int $value): self
    {
        return match($value) {
            0 => self::M(),  // 0 = M (Medium)
            1 => self::L(),  // 1 = L (Low)
            2 => self::H(),  // 2 = H (High)
            3 => self::Q(),  // 3 = Q (Quartile)
            default => throw new InvalidArgumentException('无效的错误纠正级别值: ' . $value . '。有效值为: 0(M), 1(L), 2(H), 3(Q)')
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
