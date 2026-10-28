<?php
declare(strict_types = 1);

namespace zxf\Utils\QrCode\Encoder;

use zxf\Utils\QrCode\Common\ErrorCorrectionLevel;
use zxf\Utils\QrCode\Common\Mode;
use zxf\Utils\QrCode\Common\Version;

/**
 * QR code.
 */
final class QrCode
{
    /**
     * Number of possible mask patterns.
     */
    public const NUM_MASK_PATTERNS = 8;

    /**
     * Mask pattern of the QR code.
     */
    private int $maskPattern = -1;

    /**
     * Matrix of the QR code.
     */
    private ByteMatrix $matrix;

    /**
     * 构造一个已编码完成的二维码对象
     *
     * @param Mode                 $mode              编码模式
     * @param ErrorCorrectionLevel $errorCorrectionLevel 错误纠正级别
     * @param Version              $version           版本信息
     * @param int                  $maskPattern       掩码模式（0-7）
     * @param ByteMatrix           $matrix            已生成的模块矩阵
     */
    public function __construct(
        private readonly Mode                 $mode,
        private readonly ErrorCorrectionLevel $errorCorrectionLevel,
        private readonly Version              $version,
        int                                   $maskPattern,
        ByteMatrix                            $matrix
    ) {
        $this->maskPattern = $maskPattern;
        $this->matrix = $matrix;
    }

    /**
     * Gets the mode.
     *
     * 返回编码时实际选定的模式，可据此判断内容是按数字、字母数字、
     * 字节还是汉字方式压缩的。
     *
     * @return Mode 本次编码使用的模式
     */
    public function getMode() : Mode
    {
        return $this->mode;
    }

    /**
     * Gets the EC level.
     *
     * 返回编码时采用的错误纠正级别（L/M/Q/H），级别越高容错越强但容量越小。
     *
     * @return ErrorCorrectionLevel 本次编码使用的纠错级别
     */
    public function getErrorCorrectionLevel() : ErrorCorrectionLevel
    {
        return $this->errorCorrectionLevel;
    }

    /**
     * Gets the version.
     *
     * 返回最终确定的版本（1~40），可能是根据容量自动选择的结果，
     * 也可能是调用方强制指定的版本。
     *
     * @return Version 本次编码使用的版本
     */
    public function getVersion() : Version
    {
        return $this->version;
    }

    /**
     * Gets the mask pattern.
     *
     * 返回在 8 种候选掩码中罚分最低者的编号，解码时据此还原被取反的模块。
     *
     * @return int 掩码编号，取值 0~7
     */
    public function getMaskPattern() : int
    {
        return $this->maskPattern;
    }

    /**
     * 获取二维码的模块矩阵
     *
     * @return ByteMatrix 当前二维码的字节矩阵
     */
    public function getMatrix(): ByteMatrix
    {
        return $this->matrix;
    }

    /**
     * Validates whether a mask pattern is valid.
     *
     * @param int $maskPattern 要验证的掩码模式 (0-7)
     * @return bool
     */
    public static function isValidMaskPattern(int $maskPattern) : bool
    {
        return $maskPattern >= 0 && $maskPattern < self::NUM_MASK_PATTERNS;
    }

    /**
     * Returns a string representation of the QR code.
     */
    public function __toString() : string
    {
        $result = "<<\n"
                . ' mode: ' . $this->mode . "\n"
                . ' ecLevel: ' . $this->errorCorrectionLevel . "\n"
                . ' version: ' . $this->version . "\n"
                . ' maskPattern: ' . $this->maskPattern . "\n";

        if ($this->matrix === null) {
            $result .= " matrix: null\n";
        } else {
            $result .= " matrix:\n";
            $result .= $this->matrix;
        }

        $result .= ">>\n";

        return $result;
    }
}
