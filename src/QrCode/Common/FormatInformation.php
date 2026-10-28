<?php
declare(strict_types=1);

/**
 * BaconQrCode
 *
 * @link      http://github.com/Bacon/BaconQrCode For the canonical source repository
 * @copyright 2013 Ben 'DASPRiD' Scholzen
 * @license   http://opensource.org/licenses/BSD-2-Clause Simplified BSD License
 */

namespace zxf\Utils\QrCode\Common;

/**
 * Encapsulates a QR Code's format information, including the data mask used and error correction level.
 */
class FormatInformation
{
    /**
     * Mask for format information.
     */
    private const FORMAT_INFO_MASK_QR = 0x5412;

    /**
     * Lookup table for decoding format information.
     *
     * See ISO 18004:2006, Annex C, Table C.1
     */
    private const FORMAT_INFO_DECODE_LOOKUP = [
        [0x5412, 0x00],
        [0x5125, 0x01],
        [0x5e7c, 0x02],
        [0x5b4b, 0x03],
        [0x45f9, 0x04],
        [0x40ce, 0x05],
        [0x4f97, 0x06],
        [0x4aa0, 0x07],
        [0x77c4, 0x08],
        [0x72f3, 0x09],
        [0x7daa, 0x0a],
        [0x789d, 0x0b],
        [0x662f, 0x0c],
        [0x6318, 0x0d],
        [0x6c41, 0x0e],
        [0x6976, 0x0f],
        [0x1689, 0x10],
        [0x13be, 0x11],
        [0x1ce7, 0x12],
        [0x19d0, 0x13],
        [0x0762, 0x14],
        [0x0255, 0x15],
        [0x0d0c, 0x16],
        [0x083b, 0x17],
        [0x355f, 0x18],
        [0x3068, 0x19],
        [0x3f31, 0x1a],
        [0x3a06, 0x1b],
        [0x24b4, 0x1c],
        [0x2183, 0x1d],
        [0x2eda, 0x1e],
        [0x2bed, 0x1f],
    ];

    /**
     * Offset i holds the number of 1 bits in the binary representation of i.
     *
     * @var int[]
     */
    private const BITS_SET_IN_HALF_BYTE = [0, 1, 1, 2, 1, 2, 2, 3, 1, 2, 2, 3, 2, 3, 3, 4];

    /**
     * Error correction level.
     */
    private ErrorCorrectionLevel $ecLevel;

    private int $dataMask;

    /**
     * 由格式信息整数解析出错误纠正级别与数据掩码
     *
     * @param int $formatInfo 编码后的格式信息整数
     */
    protected function __construct(int $formatInfo)
    {
        $this->ecLevel = ErrorCorrectionLevel::forBits(($formatInfo >> 3) & 0x3);
        $this->dataMask = $formatInfo & 0x7;
    }

    /**
     * 统计两个整数在二进制表示下有多少位不同（汉明距离）。
     *
     * 先异或得到差异位，再用“半字节置位数查表”把 32 位拆成 8 段累加，
     * 避免逐位循环；结果用于在解码格式信息时判断与候选值的接近程度。
     *
     * @param int $a 参与比较的第一个整数
     * @param int $b 参与比较的第二个整数
     * @return int 两者不同的二进制位数（0~32）
     */
    public static function numBitsDiffering(int $a, int $b) : int
    {
        $a ^= $b;

        return (
            self::BITS_SET_IN_HALF_BYTE[$a & 0xf]
            + self::BITS_SET_IN_HALF_BYTE[(BitUtils::unsignedRightShift($a, 4) & 0xf)]
            + self::BITS_SET_IN_HALF_BYTE[(BitUtils::unsignedRightShift($a, 8) & 0xf)]
            + self::BITS_SET_IN_HALF_BYTE[(BitUtils::unsignedRightShift($a, 12) & 0xf)]
            + self::BITS_SET_IN_HALF_BYTE[(BitUtils::unsignedRightShift($a, 16) & 0xf)]
            + self::BITS_SET_IN_HALF_BYTE[(BitUtils::unsignedRightShift($a, 20) & 0xf)]
            + self::BITS_SET_IN_HALF_BYTE[(BitUtils::unsignedRightShift($a, 24) & 0xf)]
            + self::BITS_SET_IN_HALF_BYTE[(BitUtils::unsignedRightShift($a, 28) & 0xf)]
        );
    }

    /**
     * 解码格式信息（含容错重试）
     *
     * 先用传入的两份原始格式信息尝试解码；若失败，考虑到部分生成器没有
     * 对格式位做掩码，再与 FORMAT_INFO_MASK_QR 异或后重试一次。
     *
     * @param int $maskedFormatInfo1 第一份（通常来自左上角）掩码后的格式信息
     * @param int $maskedFormatInfo2 第二份（通常来自右下角/右上角）掩码后的格式信息，可与第一份相同
     * @return self|null 解析出的格式信息；两种方式均无法匹配时返回 null
     */
    public static function decodeFormatInformation(int $maskedFormatInfo1, int $maskedFormatInfo2) : ?self
    {
        $formatInfo = self::doDecodeFormatInformation($maskedFormatInfo1, $maskedFormatInfo2);

        if (null !== $formatInfo) {
            return $formatInfo;
        }

        // Should return null, but, some QR codes apparently do not mask this info. Try again by actually masking the
        // pattern first.
        return self::doDecodeFormatInformation(
            $maskedFormatInfo1 ^ self::FORMAT_INFO_MASK_QR,
            $maskedFormatInfo2 ^ self::FORMAT_INFO_MASK_QR
        );
    }

    /**
     * 在查表中寻找与输入最接近的格式信息（内部实现）
     *
     * 先找完全相同的候选并直接返回；否则遍历查表记录最小汉明距离，
     * 两份输入都会参与比较。由于 32 个合法码字的最小距离为 7，
     * 只有差异位不超过 3 时才认定匹配成功。
     *
     * @param int $maskedFormatInfo1 第一份待解码的格式信息
     * @param int $maskedFormatInfo2 第二份待解码的格式信息，可与第一份相同
     * @return self|null 匹配成功返回格式信息，差异过大时返回 null
     */
    private static function doDecodeFormatInformation(int $maskedFormatInfo1, int $maskedFormatInfo2) : ?self
    {
        $bestDifference = PHP_INT_MAX;
        $bestFormatInfo = 0;

        foreach (self::FORMAT_INFO_DECODE_LOOKUP as $decodeInfo) {
            $targetInfo = $decodeInfo[0];

            if ($targetInfo === $maskedFormatInfo1 || $targetInfo === $maskedFormatInfo2) {
                // Found an exact match
                return new self($decodeInfo[1]);
            }

            $bitsDifference = self::numBitsDiffering($maskedFormatInfo1, $targetInfo);

            if ($bitsDifference < $bestDifference) {
                $bestFormatInfo = $decodeInfo[1];
                $bestDifference = $bitsDifference;
            }

            if ($maskedFormatInfo1 !== $maskedFormatInfo2) {
                // Also try the other option
                $bitsDifference = self::numBitsDiffering($maskedFormatInfo2, $targetInfo);

                if ($bitsDifference < $bestDifference) {
                    $bestFormatInfo = $decodeInfo[1];
                    $bestDifference = $bitsDifference;
                }
            }
        }

        // Hamming distance of the 32 masked codes is 7, by construction, so <= 3 bits differing means we found a match.
        if ($bestDifference <= 3) {
            return new self($bestFormatInfo);
        }

        return null;
    }

    /**
     * 返回该格式信息中携带的错误纠正级别
     *
     * 级别在构造时已从格式信息的高两位解析得到，此处直接返回缓存实例。
     *
     * @return ErrorCorrectionLevel 错误纠正级别（L/M/Q/H 之一）
     */
    public function getErrorCorrectionLevel() : ErrorCorrectionLevel
    {
        return $this->ecLevel;
    }

    /**
     * 返回该格式信息中携带的数据掩码编号
     *
     * 掩码编号在构造时取自格式信息的低三位，用于在解码时还原被异或
     * 处理的模块。
     *
     * @return int 数据掩码编号，取值 0~7
     */
    public function getDataMask() : int
    {
        return $this->dataMask;
    }

    /**
     * 计算该格式信息的哈希值
     *
     * 把纠错级别比特左移 3 位后与数据掩码做按位或，还原为原始的 5 位
     * 格式信息编码，可作为数组键或用于相等性快速比较。
     *
     * @return int 由纠错级别与掩码组合成的 5 位整数
     */
    public function hashCode() : int
    {
        return ($this->ecLevel->getBits() << 3) | $this->dataMask;
    }

    /**
     * 判断当前实例与另一个格式信息是否等价。
     *
     * 比较纠错级别实例与数据掩码编号是否同时相同；由于纠错级别是枚举
     * 单例，级别部分可直接用全等判断。
     *
     * @param self $other 待比较的另一个格式信息实例
     * @return bool 纠错级别与数据掩码都相同时返回 true，否则返回 false
     */
    public function equals(self $other) : bool
    {
        return (
            $this->ecLevel === $other->ecLevel
            && $this->dataMask === $other->dataMask
        );
    }
}
