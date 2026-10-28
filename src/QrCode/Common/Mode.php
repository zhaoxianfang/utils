<?php
declare(strict_types = 1);

namespace zxf\Utils\QrCode\Common;

use zxf\Utils\QrCode\Enum\AbstractEnum;

/**
 * 枚举类，表示数据可以被编码为比特的各种模式
 *
 * @method static self TERMINATOR()
 * @method static self NUMERIC()
 * @method static self ALPHANUMERIC()
 * @method static self STRUCTURED_APPEND()
 * @method static self BYTE()
 * @method static self ECI()
 * @method static self KANJI()
 * @method static self FNC1_FIRST_POSITION()
 * @method static self FNC1_SECOND_POSITION()
 * @method static self HANZI()
 */
final class Mode extends AbstractEnum
{
    protected const TERMINATOR = [[0, 0, 0], 0x00];
    protected const NUMERIC = [[10, 12, 14], 0x01];
    protected const ALPHANUMERIC = [[9, 11, 13], 0x02];
    protected const STRUCTURED_APPEND = [[0, 0, 0], 0x03];
    protected const BYTE = [[8, 16, 16], 0x04];
    protected const ECI = [[0, 0, 0], 0x07];
    protected const KANJI = [[8, 10, 12], 0x08];
    protected const FNC1_FIRST_POSITION = [[0, 0, 0], 0x05];
    protected const FNC1_SECOND_POSITION = [[0, 0, 0], 0x09];
    protected const HANZI = [[8, 10, 12], 0x0d];

    /**
     * @param int[] $characterCountBitsForVersions
     */
    protected function __construct(
        private readonly array $characterCountBitsForVersions,
        private readonly int   $bits
    ) {
    }

    /**
     * 返回在特定二维码版本中使用的比特数
     *
     * 字符计数指示符的位宽随版本区间变化：1~9 取第 0 位、
     * 10~26 取第 1 位、27~40 取第 2 位；若某模式这三个值相同则无差别。
     *
     * @param Version $version 目标二维码版本对象
     * @return int 该模式的字符计数指示符所占的比特数
     */
    public function getCharacterCountBits(Version $version) : int
    {
        $number = $version->getVersionNumber();

        return match (true) {
            $number <= 9 => $this->characterCountBitsForVersions[0],
            $number <= 26 => $this->characterCountBitsForVersions[1],
            default => $this->characterCountBitsForVersions[2],
        };
    }

    /**
     * 返回用于编码此模式的四个比特
     *
     * 该值是 QR 规范中模式指示符的编码（如 NUMERIC 为 0x01、BYTE 为 0x04），
     * 会作为每段数据的起始标记写入比特流。
     *
     * @return int 模式指示符的比特值
     */
    public function getBits() : int
    {
        return $this->bits;
    }
}
