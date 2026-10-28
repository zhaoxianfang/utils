<?php
declare(strict_types = 1);

namespace zxf\Utils\QrCode\Common;

use zxf\Utils\QrCode\Exception\InvalidArgumentException;
use SplFixedArray;

/**
 * Bit matrix.
 *
 * Represents a 2D matrix of bits. In function arguments below, and throughout
 * the common module, x is the column position, and y is the row position. The
 * ordering is always x, y. The origin is at the top-left.
 */
class BitMatrix
{
    /**
     * Width of the bit matrix.
     */
    private int $width;

    /**
     * Height of the bit matrix.
     */
    private ?int $height;

    /**
     * Size in bits of each individual row.
     */
    private int $rowSize;

    /**
     * Bits representation.
     *
     * @var SplFixedArray<int>
     */
    private SplFixedArray $bits;

    /**
     * @throws InvalidArgumentException if a dimension is smaller than zero
     */
    public function __construct(int $width, ?int $height = null)
    {
        if (null === $height) {
            $height = $width;
        }

        if ($width < 1 || $height < 1) {
            throw new InvalidArgumentException('Both dimensions must be greater than zero');
        }

        $this->width = $width;
        $this->height = $height;
        $this->rowSize = ($width + 31) >> 5;
        $this->bits = SplFixedArray::fromArray(array_fill(0, $this->rowSize * $height, 0));
    }

    /**
     * 读取指定坐标处的位，true 表示该像素为黑（深色模块）。
     *
     * 按 (y * rowSize + x / 32) 定位到内部 32 位分段，再右移取最低位；
     * 方法不做边界检查，坐标必须落在矩阵范围内。
     *
     * @param int $x 横坐标（列），从 0 开始
     * @param int $y 纵坐标（行），从 0 开始
     * @return bool 该位置被置位时返回 true，否则返回 false
     */
    public function get(int $x, int $y) : bool
    {
        $offset = $y * $this->rowSize + ($x >> 5);
        return 0 !== (BitUtils::unsignedRightShift($this->bits[$offset], ($x & 0x1f)) & 1);
    }

    /**
     * 将指定坐标处的位设为 true（即标记为黑点）。
     *
     * 通过按位或在对应 32 位分段上置位，其他位保持不变；坐标越界时
     * 会由底层 SplFixedArray 抛出运行时异常。
     *
     * @param int $x 横坐标（列），从 0 开始
     * @param int $y 纵坐标（行），从 0 开始
     */
    public function set(int $x, int $y) : void
    {
        $offset = $y * $this->rowSize + ($x >> 5);
        $this->bits[$offset] = $this->bits[$offset] | (1 << ($x & 0x1f));
    }

    /**
     * 翻转指定坐标处的位（黑变白、白变黑）。
     *
     * 用于绘制掩码图形时按掩码条件取反像素，使用异或语义，仅影响该坐标。
     *
     * @param int $x 横坐标（列），从 0 开始
     * @param int $y 纵坐标（行），从 0 开始
     */
    public function flip(int $x, int $y) : void
    {
        $offset = $y * $this->rowSize + ($x >> 5);
        $this->bits[$offset] = $this->bits[$offset] ^ (1 << ($x & 0x1f));
    }

    /**
     * Clears all bits (set to false).
     */
    public function clear() : void
    {
        $max = count($this->bits);

        for ($i = 0; $i < $max; ++$i) {
            $this->bits[$i] = 0;
        }
    }

    /**
     * 将一块矩形区域内的所有位设为 true（填充为黑）。
     *
     * 区域以左上角 ($left, $top) 加宽高描述，覆盖 [left, left+width) ×
     * [top, top+height)；写入前会依次校验坐标非负、宽高至少为 1 且区域
     * 完全落在矩阵内，任一条件不满足即抛出异常。
     *
     * @param int $left 区域左边界横坐标，需大于等于 0
     * @param int $top 区域上边界纵坐标，需大于等于 0
     * @param int $width 区域宽度，至少为 1
     * @param int $height 区域高度，至少为 1
     * @throws InvalidArgumentException 当 left/top 为负、宽高小于 1 或区域超出矩阵边界时抛出
     */
    public function setRegion(int $left, int $top, int $width, int $height) : void
    {
        if ($top < 0 || $left < 0) {
            throw new InvalidArgumentException('Left and top must be non-negative');
        }

        if ($height < 1 || $width < 1) {
            throw new InvalidArgumentException('Width and height must be at least 1');
        }

        $right = $left + $width;
        $bottom = $top + $height;

        if ($bottom > $this->height || $right > $this->width) {
            throw new InvalidArgumentException('The region must fit inside the matrix');
        }

        for ($y = $top; $y < $bottom; ++$y) {
            $offset = $y * $this->rowSize;

            for ($x = $left; $x < $right; ++$x) {
                $index = $offset + ($x >> 5);
                $this->bits[$index] = $this->bits[$index] | (1 << ($x & 0x1f));
            }
        }
    }

    /**
     * 以 BitArray 形式快速取出矩阵的一整行。
     *
     * 传入的 $row 可复用：仅当其位长度不足矩阵宽度时才新建实例，
     * 否则直接覆盖其内容并按整字批量写入。返回值直接暴露内部行数据，
     * 修改它不会影响矩阵本身。
     *
     * @param int $y 行号，从 0 开始
     * @param BitArray|null $row 可选的复用缓冲区，长度不足时会被替换为新实例
     * @return BitArray 该行的位数据，长度不小于矩阵宽度
     */
    public function getRow(int $y, ?BitArray $row = null) : BitArray
    {
        if (null === $row || $row->getSize() < $this->width) {
            $row = new BitArray($this->width);
        }

        $offset = $y * $this->rowSize;

        for ($x = 0; $x < $this->rowSize; ++$x) {
            $row->setBulk($x << 5, $this->bits[$offset + $x]);
        }

        return $row;
    }

    /**
     * 用 BitArray 中的数据整行覆盖矩阵的第 $y 行。
     *
     * 按内部 32 位分段拷贝，拷贝长度由矩阵的 rowSize 决定，
     * 因此 $row 的容量需要与矩阵宽度匹配，否则结果可能被截断。
     *
     * @param int $y 行号，从 0 开始
     * @param BitArray $row 提供该行位数据的源数组
     */
    public function setRow(int $y, BitArray $row) : void
    {
        $bits = $row->getBitArray();

        for ($i = 0; $i < $this->rowSize; ++$i) {
            $this->bits[$y * $this->rowSize + $i] = $bits[$i];
        }
    }

    /**
     * This is useful in detecting the enclosing rectangle of a 'pure' barcode.
     *
     * @return int[]|null
     */
    public function getEnclosingRectangle() : ?array
    {
        $left = $this->width;
        $top = $this->height;
        $right = -1;
        $bottom = -1;

        for ($y = 0; $y < $this->height; ++$y) {
            for ($x32 = 0; $x32 < $this->rowSize; ++$x32) {
                $bits = $this->bits[$y * $this->rowSize + $x32];

                if (0 !== $bits) {
                    if ($y < $top) {
                        $top = $y;
                    }

                    if ($y > $bottom) {
                        $bottom = $y;
                    }

                    if ($x32 * 32 < $left) {
                        $bit = 0;

                        while (($bits << (31 - $bit)) === 0) {
                            $bit++;
                        }

                        if (($x32 * 32 + $bit) < $left) {
                            $left = $x32 * 32 + $bit;
                        }
                    }
                }

                if ($x32 * 32 + 31 > $right) {
                    $bit = 31;

                    while (0 === BitUtils::unsignedRightShift($bits, $bit)) {
                        --$bit;
                    }

                    if (($x32 * 32 + $bit) > $right) {
                        $right = $x32 * 32 + $bit;
                    }
                }
            }
        }

        $width = $right - $left;
        $height = $bottom - $top;

        if ($width < 0 || $height < 0) {
            return null;
        }

        return [$left, $top, $width, $height];
    }

    /**
     * Gets the most top left set bit.
     *
     * This is useful in detecting a corner of a 'pure' barcode.
     *
     * @return int[]|null
     */
    public function getTopLeftOnBit() : ?array
    {
        $bitsOffset = 0;

        while ($bitsOffset < count($this->bits) && 0 === $this->bits[$bitsOffset]) {
            ++$bitsOffset;
        }

        if (count($this->bits) === $bitsOffset) {
            return null;
        }

        $x = intdiv($bitsOffset, $this->rowSize);
        $y = ($bitsOffset % $this->rowSize) << 5;

        $bits = $this->bits[$bitsOffset];
        $bit = 0;

        while (0 === ($bits << (31 - $bit))) {
            ++$bit;
        }

        $x += $bit;

        return [$x, $y];
    }

    /**
     * Gets the most bottom right set bit.
     *
     * This is useful in detecting a corner of a 'pure' barcode.
     *
     * @return int[]|null
     */
    public function getBottomRightOnBit() : ?array
    {
        $bitsOffset = count($this->bits) - 1;

        while ($bitsOffset >= 0 && 0 === $this->bits[$bitsOffset]) {
            --$bitsOffset;
        }

        if ($bitsOffset < 0) {
            return null;
        }

        $x = intdiv($bitsOffset, $this->rowSize);
        $y = ($bitsOffset % $this->rowSize) << 5;

        $bits = $this->bits[$bitsOffset];
        $bit  = 0;

        while (0 === BitUtils::unsignedRightShift($bits, $bit)) {
            --$bit;
        }

        $x += $bit;

        return [$x, $y];
    }

    /**
     * 获取矩阵的宽度（列数）。
     *
     * 返回构造时确定的横向尺寸，不随后续 set/flip 等操作变化。
     *
     * @return int 宽度，单位为像素（模块）数
     */
    public function getWidth() : int
    {
        return $this->width;
    }

    /**
     * 获取矩阵的高度（行数）。
     *
     * 返回构造时确定的纵向尺寸，不随后续 set/flip 等操作变化。
     *
     * @return int 高度，单位为像素（模块）数
     */
    public function getHeight() : int
    {
        return $this->height;
    }
}
