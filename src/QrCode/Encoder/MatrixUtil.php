<?php
declare(strict_types = 1);

namespace zxf\Utils\QrCode\Encoder;

use zxf\Utils\QrCode\Common\BitArray;
use zxf\Utils\QrCode\Common\ErrorCorrectionLevel;
use zxf\Utils\QrCode\Common\Version;
use zxf\Utils\QrCode\Exception\RuntimeException;
use zxf\Utils\QrCode\Exception\WriterException;

/**
 * Matrix utility.
 */
final class MatrixUtil
{
    /**
     * Position detection pattern.
     */
    private const POSITION_DETECTION_PATTERN = [
        [1, 1, 1, 1, 1, 1, 1],
        [1, 0, 0, 0, 0, 0, 1],
        [1, 0, 1, 1, 1, 0, 1],
        [1, 0, 1, 1, 1, 0, 1],
        [1, 0, 1, 1, 1, 0, 1],
        [1, 0, 0, 0, 0, 0, 1],
        [1, 1, 1, 1, 1, 1, 1],
    ];

    /**
     * Position adjustment pattern.
     */
    private const POSITION_ADJUSTMENT_PATTERN = [
        [1, 1, 1, 1, 1],
        [1, 0, 0, 0, 1],
        [1, 0, 1, 0, 1],
        [1, 0, 0, 0, 1],
        [1, 1, 1, 1, 1],
    ];

    /**
     * Coordinates for position adjustment patterns for each version.
     */
    private const POSITION_ADJUSTMENT_PATTERN_COORDINATE_TABLE = [
        [null, null, null, null, null, null, null], // Version 1
        [   6,   18, null, null, null, null, null], // Version 2
        [   6,   22, null, null, null, null, null], // Version 3
        [   6,   26, null, null, null, null, null], // Version 4
        [   6,   30, null, null, null, null, null], // Version 5
        [   6,   34, null, null, null, null, null], // Version 6
        [   6,   22,   38, null, null, null, null], // Version 7
        [   6,   24,   42, null, null, null, null], // Version 8
        [   6,   26,   46, null, null, null, null], // Version 9
        [   6,   28,   50, null, null, null, null], // Version 10
        [   6,   30,   54, null, null, null, null], // Version 11
        [   6,   32,   58, null, null, null, null], // Version 12
        [   6,   34,   62, null, null, null, null], // Version 13
        [   6,   26,   46,   66, null, null, null], // Version 14
        [   6,   26,   48,   70, null, null, null], // Version 15
        [   6,   26,   50,   74, null, null, null], // Version 16
        [   6,   30,   54,   78, null, null, null], // Version 17
        [   6,   30,   56,   82, null, null, null], // Version 18
        [   6,   30,   58,   86, null, null, null], // Version 19
        [   6,   34,   62,   90, null, null, null], // Version 20
        [   6,   28,   50,   72,   94, null, null], // Version 21
        [   6,   26,   50,   74,   98, null, null], // Version 22
        [   6,   30,   54,   78,  102, null, null], // Version 23
        [   6,   28,   54,   80,  106, null, null], // Version 24
        [   6,   32,   58,   84,  110, null, null], // Version 25
        [   6,   30,   58,   86,  114, null, null], // Version 26
        [   6,   34,   62,   90,  118, null, null], // Version 27
        [   6,   26,   50,   74,   98,  122, null], // Version 28
        [   6,   30,   54,   78,  102,  126, null], // Version 29
        [   6,   26,   52,   78,  104,  130, null], // Version 30
        [   6,   30,   56,   82,  108,  134, null], // Version 31
        [   6,   34,   60,   86,  112,  138, null], // Version 32
        [   6,   30,   58,   86,  114,  142, null], // Version 33
        [   6,   34,   62,   90,  118,  146, null], // Version 34
        [   6,   30,   54,   78,  102,  126,  150], // Version 35
        [   6,   24,   50,   76,  102,  128,  154], // Version 36
        [   6,   28,   54,   80,  106,  132,  158], // Version 37
        [   6,   32,   58,   84,  110,  136,  162], // Version 38
        [   6,   26,   54,   82,  110,  138,  166], // Version 39
        [   6,   30,   58,   86,  114,  142,  170], // Version 40
    ];

    /**
     * Type information coordinates.
     */
    private const TYPE_INFO_COORDINATES = [
        [8, 0],
        [8, 1],
        [8, 2],
        [8, 3],
        [8, 4],
        [8, 5],
        [8, 7],
        [8, 8],
        [7, 8],
        [5, 8],
        [4, 8],
        [3, 8],
        [2, 8],
        [1, 8],
        [0, 8],
    ];

    /**
     * Version information polynomial.
     */
    private const VERSION_INFO_POLY = 0x1f25;

    /**
     * Type information polynomial.
     */
    private const TYPE_INFO_POLY = 0x537;

    /**
     * Type information mask pattern.
     */
    private const TYPE_INFO_MASK_PATTERN = 0x5412;

    /**
     * Clears a given matrix.
     *
     * 把所有单元重置为 -1，作为“尚未写入”的标记，后续嵌入功能图案时可
     * 借此判断某个位置是否已被占用。
     *
     * @param ByteMatrix $matrix 待清空的矩阵
     */
    public static function clearMatrix(ByteMatrix $matrix) : void
    {
        $matrix->clear(-1);
    }

    /**
     * Builds a complete matrix.
     *
     * 按「清空 → 嵌入基础图案 → 嵌入格式信息 → 嵌入版本信息（≥7 时）→
     * 嵌入数据比特」的顺序一次性生成完整矩阵，结果写入传入的 $matrix。
     *
     * @param BitArray $dataBits 已完成纠错交织的最终比特流
     * @param ErrorCorrectionLevel $level 纠错级别，用于生成格式信息
     * @param Version $version 二维码版本，决定尺寸与图案布局
     * @param int $maskPattern 数据掩码编号（0~7），-1 表示不做掩码（仅调试用）
     * @param ByteMatrix $matrix 输出矩阵，内容会被整体覆盖
     */
    public static function buildMatrix(
        BitArray $dataBits,
        ErrorCorrectionLevel $level,
        Version $version,
        int $maskPattern,
        ByteMatrix $matrix
    ) : void {
        self::clearMatrix($matrix);
        self::embedBasicPatterns($version, $matrix);
        self::embedTypeInfo($level, $maskPattern, $matrix);
        self::maybeEmbedVersionInfo($version, $matrix);
        self::embedDataBits($dataBits, $maskPattern, $matrix);
    }

    /**
     * Removes the position detection patterns from a matrix.
     *
     * This can be useful if you need to render those patterns separately.
     *
     * 将左上、右上、左下三个 7×7 定位图案区域全部置 0，其余模块不受影响；
     * 矩阵尺寸按宽度取用，因此仅适用于正方形矩阵。
     *
     * @param ByteMatrix $matrix 待处理的矩阵，会被就地修改
     */
    public static function removePositionDetectionPatterns(ByteMatrix $matrix) : void
    {
        $pdpWidth = count(self::POSITION_DETECTION_PATTERN[0]);

        self::removePositionDetectionPattern(0, 0, $matrix);
        self::removePositionDetectionPattern($matrix->getWidth() - $pdpWidth, 0, $matrix);
        self::removePositionDetectionPattern(0, $matrix->getWidth() - $pdpWidth, $matrix);
    }

    /**
     * Embeds type information into a matrix.
     *
     * 生成 15 位格式信息后，按位分别写入左上角与右上/左下两处冗余位置，
     * 两处内容相同，便于解码时容错。
     *
     * @param ErrorCorrectionLevel $level 纠错级别
     * @param int $maskPattern 数据掩码编号（0~7）
     * @param ByteMatrix $matrix 目标矩阵，会被就地写入
     */
    private static function embedTypeInfo(ErrorCorrectionLevel $level, int $maskPattern, ByteMatrix $matrix) : void
    {
        $typeInfoBits = new BitArray();
        self::makeTypeInfoBits($level, $maskPattern, $typeInfoBits);

        $typeInfoBitsSize = $typeInfoBits->getSize();

        for ($i = 0; $i < $typeInfoBitsSize; ++$i) {
            $bit = $typeInfoBits->get($typeInfoBitsSize - 1 - $i);

            $x1 = self::TYPE_INFO_COORDINATES[$i][0];
            $y1 = self::TYPE_INFO_COORDINATES[$i][1];

            $matrix->set($x1, $y1, (int) $bit);

            if ($i < 8) {
                $x2 = $matrix->getWidth() - $i - 1;
                $y2 = 8;
            } else {
                $x2 = 8;
                $y2 = $matrix->getHeight() - 7 + ($i - 8);
            }

            $matrix->set($x2, $y2, (int) $bit);
        }
    }

    /**
     * Generates type information bits and appends them to a bit array.
     *
     * 由 2 位纠错级别与 3 位掩码编号拼成 5 位值，追加 10 位 BCH 校验码后
     * 与固定掩码 0x5412 异或，最终得到 15 位格式信息。
     *
     * @param ErrorCorrectionLevel $level 纠错级别
     * @param int $maskPattern 数据掩码编号（0~7）
     * @param BitArray $bits 目标比特流，会被追加 15 位并就地异或
     * @throws RuntimeException if bit array resulted in invalid size
     */
    private static function makeTypeInfoBits(ErrorCorrectionLevel $level, int $maskPattern, BitArray $bits) : void
    {
        $typeInfo = ($level->getBits() << 3) | $maskPattern;
        $bits->appendBits($typeInfo, 5);

        $bchCode = self::calculateBchCode($typeInfo, self::TYPE_INFO_POLY);
        $bits->appendBits($bchCode, 10);

        $maskBits = new BitArray();
        $maskBits->appendBits(self::TYPE_INFO_MASK_PATTERN, 15);
        $bits->xorBits($maskBits);

        if (15 !== $bits->getSize()) {
            throw new RuntimeException('Bit array resulted in invalid size: ' . $bits->getSize());
        }
    }

    /**
     * Embeds version information if required.
     *
     * 仅版本 7 及以上需要写入版本信息（18 位）；低于 7 时直接返回。
     * 同一份数据会分别写入右上区域与左下区域，形成两处冗余。
     *
     * @param Version $version 当前版本
     * @param ByteMatrix $matrix 目标矩阵，会被就地写入
     */
    private static function maybeEmbedVersionInfo(Version $version, ByteMatrix $matrix) : void
    {
        if ($version->getVersionNumber() < 7) {
            return;
        }

        $versionInfoBits = new BitArray();
        self::makeVersionInfoBits($version, $versionInfoBits);

        $bitIndex = 6 * 3 - 1;

        for ($i = 0; $i < 6; ++$i) {
            for ($j = 0; $j < 3; ++$j) {
                $bit = $versionInfoBits->get($bitIndex);
                --$bitIndex;

                $matrix->set($i, $matrix->getHeight() - 11 + $j, (int) $bit);
                $matrix->set($matrix->getHeight() - 11 + $j, $i, (int) $bit);
            }
        }
    }

    /**
     * Generates version information bits and appends them to a bit array.
     *
     * 追加 6 位版本号及其 12 位 BCH 校验码，共 18 位；此处不做过掩码处理。
     *
     * @param Version $version 当前版本（需为 7 及以上）
     * @param BitArray $bits 目标比特流，会被追加 18 位
     * @throws RuntimeException if bit array resulted in invalid size
     */
    private static function makeVersionInfoBits(Version $version, BitArray $bits) : void
    {
        $bits->appendBits($version->getVersionNumber(), 6);

        $bchCode = self::calculateBchCode($version->getVersionNumber(), self::VERSION_INFO_POLY);
        $bits->appendBits($bchCode, 12);

        if (18 !== $bits->getSize()) {
            throw new RuntimeException('Bit array resulted in invalid size: ' . $bits->getSize());
        }
    }

    /**
     * Calculates the BCH code for a value and a polynomial.
     *
     * 先把值左移到与生成多项式最高位对齐，再反复用多项式做异或消去高位，
     * 余数即为校验码；$poly 的位数决定校验码长度。
     *
     * @param int $value 需要计算校验码的原始值（如格式信息或版本号）
     * @param int $poly 生成多项式（如 0x537 用于格式信息、0x1f25 用于版本信息）
     * @return int 计算得到的校验码余数
     */
    private static function calculateBchCode(int $value, int $poly) : int
    {
        $msbSetInPoly = self::findMsbSet($poly);
        $value <<= $msbSetInPoly - 1;

        while (self::findMsbSet($value) >= $msbSetInPoly) {
            $value ^= $poly << (self::findMsbSet($value) - $msbSetInPoly);
        }

        return $value;
    }

    /**
     * Finds and MSB set.
     *
     * 通过不断右移统计有效位数，得到最高置位的位置（1 表示最高位在 bit0），
     * 传入 0 时返回 0。
     *
     * @param int $value 待测量的非负整数
     * @return int 最高置位的位置，也等于该值的二进制有效位数
     */
    private static function findMsbSet(int $value) : int
    {
        $numDigits = 0;

        while (0 !== $value) {
            $value >>= 1;
            ++$numDigits;
        }

        return $numDigits;
    }

    /**
     * Embeds basic patterns into a matrix.
     *
     * 依次嵌入定位图案与分隔符、左下角固定黑点、按版本决定的对齐图案，
     * 以及横竖定时图案；须在写入格式信息与数据比特之前调用。
     *
     * @param Version $version 当前版本，决定是否需要对齐图案
     * @param ByteMatrix $matrix 目标矩阵，会被就地写入
     */
    private static function embedBasicPatterns(Version $version, ByteMatrix $matrix) : void
    {
        self::embedPositionDetectionPatternsAndSeparators($matrix);
        self::embedDarkDotAtLeftBottomCorner($matrix);
        self::maybeEmbedPositionAdjustmentPatterns($version, $matrix);
        self::embedTimingPatterns($matrix);
    }

    /**
     * Embeds position detection patterns and separators into a byte matrix.
     *
     * 在三个角嵌入 7×7 定位图案，随后补齐它们内侧的水平与垂直分隔线
     * （共 9 处）；分隔线写入前会校验目标格为空，避免覆盖已有图案。
     *
     * @param ByteMatrix $matrix 目标矩阵，会被就地写入
     */
    private static function embedPositionDetectionPatternsAndSeparators(ByteMatrix $matrix) : void
    {
        $pdpWidth = count(self::POSITION_DETECTION_PATTERN[0]);

        self::embedPositionDetectionPattern(0, 0, $matrix);
        self::embedPositionDetectionPattern($matrix->getWidth() - $pdpWidth, 0, $matrix);
        self::embedPositionDetectionPattern(0, $matrix->getWidth() - $pdpWidth, $matrix);

        $hspWidth = 8;

        self::embedHorizontalSeparationPattern(0, $hspWidth - 1, $matrix);
        self::embedHorizontalSeparationPattern($matrix->getWidth() - $hspWidth, $hspWidth - 1, $matrix);
        self::embedHorizontalSeparationPattern(0, $matrix->getWidth() - $hspWidth, $matrix);

        $vspSize = 7;

        self::embedVerticalSeparationPattern($vspSize, 0, $matrix);
        self::embedVerticalSeparationPattern($matrix->getHeight() - $vspSize - 1, 0, $matrix);
        self::embedVerticalSeparationPattern($vspSize, $matrix->getHeight() - $vspSize, $matrix);
    }

    /**
     * Embeds a single position detection pattern into a byte matrix.
     *
     * 从 ($xStart, $yStart) 开始铺一个 7×7 的定位图案，直接覆盖目标区域
     * 原有内容，不做占位校验。
     *
     * @param int $xStart 图案左上角列坐标
     * @param int $yStart 图案左上角行坐标
     * @param ByteMatrix $matrix 目标矩阵，会被就地写入
     */
    private static function embedPositionDetectionPattern(int $xStart, int $yStart, ByteMatrix $matrix) : void
    {
        for ($y = 0; $y < 7; ++$y) {
            for ($x = 0; $x < 7; ++$x) {
                $matrix->set($xStart + $x, $yStart + $y, self::POSITION_DETECTION_PATTERN[$y][$x]);
            }
        }
    }

    /**
     * 将单个定位探测图案区域清零（用于单独移除一个定位图案）
     *
     * @param int        $xStart 起始列坐标
     * @param int        $yStart 起始行坐标
     * @param ByteMatrix $matrix 待处理的矩阵
     */
    private static function removePositionDetectionPattern(int $xStart, int $yStart, ByteMatrix $matrix) : void
    {
        for ($y = 0; $y < 7; ++$y) {
            for ($x = 0; $x < 7; ++$x) {
                $matrix->set($xStart + $x, $yStart + $y, 0);
            }
        }
    }

    /**
     * Embeds a single horizontal separation pattern.
     *
     * 在给定起点向右铺 8 个浅色（0）模块，构成定位图案下方的水平分隔线；
     * 任一格已被占用（非 -1）即说明布局冲突并抛出异常。
     *
     * @param int $xStart 分隔线起始列坐标
     * @param int $yStart 分隔线所在行坐标
     * @param ByteMatrix $matrix 目标矩阵，会被就地写入
     * @throws RuntimeException if a byte was already set
     */
    private static function embedHorizontalSeparationPattern(int $xStart, int $yStart, ByteMatrix $matrix) : void
    {
        for ($x = 0; $x < 8; $x++) {
            if (-1 !== $matrix->get($xStart + $x, $yStart)) {
                throw new RuntimeException('Byte already set');
            }

            $matrix->set($xStart + $x, $yStart, 0);
        }
    }

    /**
     * Embeds a single vertical separation pattern.
     *
     * 在给定起点向下铺 7 个浅色（0）模块，构成定位图案右侧的垂直分隔线；
     * 任一格已被占用（非 -1）即说明布局冲突并抛出异常。
     *
     * @param int $xStart 分隔线所在列坐标
     * @param int $yStart 分隔线起始行坐标
     * @param ByteMatrix $matrix 目标矩阵，会被就地写入
     * @throws RuntimeException if a byte was already set
     */
    private static function embedVerticalSeparationPattern(int $xStart, int $yStart, ByteMatrix $matrix) : void
    {
        for ($y = 0; $y < 7; $y++) {
            if (-1 !== $matrix->get($xStart, $yStart + $y)) {
                throw new RuntimeException('Byte already set');
            }

            $matrix->set($xStart, $yStart + $y, 0);
        }
    }

    /**
     * Embeds a dot at the left bottom corner.
     *
     * 在坐标 (8, height-8) 写入固定黑点；写入前若该格已是 0 则说明与
     * 已有图案冲突，抛出异常。
     *
     * @param ByteMatrix $matrix 目标矩阵，会被就地写入
     * @throws RuntimeException if a byte was already set to 0
     */
    private static function embedDarkDotAtLeftBottomCorner(ByteMatrix $matrix) : void
    {
        if (0 === $matrix->get(8, $matrix->getHeight() - 8)) {
            throw new RuntimeException('Byte already set to 0');
        }

        $matrix->set(8, $matrix->getHeight() - 8, 1);
    }

    /**
     * Embeds position adjustment patterns if required.
     *
     * 版本 1 没有对齐图案，直接返回；其余版本按坐标表两两组合出候选中心，
     * 跳过空坐标，并仅在该中心仍为空（-1）时以 (x-2, y-2) 为左上角绘制，
     * 从而避开已存在的定位图案。
     *
     * @param Version $version 当前版本
     * @param ByteMatrix $matrix 目标矩阵，会被就地写入
     */
    private static function maybeEmbedPositionAdjustmentPatterns(Version $version, ByteMatrix $matrix) : void
    {
        if ($version->getVersionNumber() < 2) {
            return;
        }

        $index = $version->getVersionNumber() - 1;

        $coordinates = self::POSITION_ADJUSTMENT_PATTERN_COORDINATE_TABLE[$index];
        $numCoordinates = count($coordinates);

        for ($i = 0; $i < $numCoordinates; ++$i) {
            for ($j = 0; $j < $numCoordinates; ++$j) {
                $y = $coordinates[$i];
                $x = $coordinates[$j];

                if (null === $x || null === $y) {
                    continue;
                }

                if (-1 === $matrix->get($x, $y)) {
                    self::embedPositionAdjustmentPattern($x - 2, $y - 2, $matrix);
                }
            }
        }
    }

    /**
     * Embeds a single position adjustment pattern.
     *
     * 从 ($xStart, $yStart) 开始铺一个 5×5 的对齐图案，会覆盖目标区域
     * 原有内容。
     *
     * @param int $xStart 图案左上角列坐标
     * @param int $yStart 图案左上角行坐标
     * @param ByteMatrix $matrix 目标矩阵，会被就地写入
     */
    private static function embedPositionAdjustmentPattern(int $xStart, int $yStart, ByteMatrix $matrix) : void
    {
        for ($y = 0; $y < 5; $y++) {
            for ($x = 0; $x < 5; $x++) {
                $matrix->set($xStart + $x, $yStart + $y, self::POSITION_ADJUSTMENT_PATTERN[$y][$x]);
            }
        }
    }

    /**
     * Embeds timing patterns into a matrix.
     *
     * 在第 6 行与第 6 列、避开两端定位图案的区域（8 ~ width-9）交替写入
     * 深浅模块；仅填充仍为 -1 的空格，已有图案不会被覆盖。
     *
     * @param ByteMatrix $matrix 目标矩阵，会被就地写入
     */
    private static function embedTimingPatterns(ByteMatrix $matrix) : void
    {
        $matrixWidth = $matrix->getWidth();

        for ($i = 8; $i < $matrixWidth - 8; ++$i) {
            $bit = ($i + 1) % 2;

            if (-1 === $matrix->get($i, 6)) {
                $matrix->set($i, 6, $bit);
            }

            if (-1 === $matrix->get(6, $i)) {
                $matrix->set(6, $i, $bit);
            }
        }
    }

    /**
     * Embeds "dataBits" using "getMaskPattern".
     *
     * For debugging purposes, it skips masking process if "getMaskPattern" is -1. See 8.7 of JISX0510:2004 (p.38) for
     * how to embed data bits.
     *
     * 从右下角开始，以两列为一组上下交替地蛇形填充，跳过第 6 列定时图案
     * 与所有已占用的功能图案；比特用尽后剩余空格补 0。若最终消费的比特数
     * 与比特流长度不一致，说明数据量不匹配并抛出异常。
     *
     * @param BitArray $dataBits 已完成纠错交织的比特流
     * @param int $maskPattern 掩码编号（0~7），-1 表示不做掩码（仅调试用）
     * @param ByteMatrix $matrix 目标矩阵，空单元会被逐格填充
     * @throws WriterException if not all bits could be consumed
     */
    private static function embedDataBits(BitArray $dataBits, int $maskPattern, ByteMatrix $matrix) : void
    {
        $bitIndex = 0;
        $direction = -1;

        // Start from the right bottom cell.
        $x = $matrix->getWidth() - 1;
        $y = $matrix->getHeight() - 1;

        while ($x > 0) {
            // Skip vertical timing pattern.
            if (6 === $x) {
                --$x;
            }

            while ($y >= 0 && $y < $matrix->getHeight()) {
                for ($i = 0; $i < 2; $i++) {
                    $xx = $x - $i;

                    // Skip the cell if it's not empty.
                    if (-1 !== $matrix->get($xx, $y)) {
                        continue;
                    }

                    if ($bitIndex < $dataBits->getSize()) {
                        $bit = $dataBits->get($bitIndex);
                        ++$bitIndex;
                    } else {
                        // Padding bit. If there is no bit left, we'll fill the
                        // left cells with 0, as described in 8.4.9 of
                        // JISX0510:2004 (p. 24).
                        $bit = false;
                    }

                    // Skip masking if maskPattern is -1.
                    if (-1 !== $maskPattern && MaskUtil::getDataMaskBit($maskPattern, $xx, $y)) {
                        $bit = ! $bit;
                    }

                    $matrix->set($xx, $y, (int) $bit);
                }

                $y += $direction;
            }

            $direction  = -$direction;
            $y += $direction;
            $x -= 2;
        }

        // All bits should be consumed
        if ($dataBits->getSize() !== $bitIndex) {
            throw new WriterException('Not all bits consumed (' . $bitIndex . ' out of ' . $dataBits->getSize() .')');
        }
    }
}
