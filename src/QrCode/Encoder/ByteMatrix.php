<?php
declare(strict_types = 1);

namespace zxf\Utils\QrCode\Encoder;

use SplFixedArray;
use Traversable;

/**
 * Byte matrix.
 */
final class ByteMatrix
{
    /**
     * Bytes in the matrix, represented as array.
     *
     * @var SplFixedArray<SplFixedArray<int>>
     */
    private SplFixedArray $bytes;

    /**
     * 构造一个指定宽高的字节矩阵，并初始化全部单元为 0
     *
     * @param int $width  矩阵列数
     * @param int $height 矩阵行数
     */
    public function __construct(private readonly int $width, private readonly int $height)
    {
        $this->bytes = new SplFixedArray($height);

        for ($y = 0; $y < $height; ++$y) {
            $this->bytes[$y] = SplFixedArray::fromArray(array_fill(0, $width, 0));
        }
    }

    /**
     * Gets the width of the matrix.
     *
     * 宽度在构造时确定且只读，不随 set()/clear() 改变。
     *
     * @return int 矩阵列数
     */
    public function getWidth() : int
    {
        return $this->width;
    }

    /**
     * Gets the height of the matrix.
     *
     * 高度在构造时确定且只读，不随 set()/clear() 改变。
     *
     * @return int 矩阵行数
     */
    public function getHeight() : int
    {
        return $this->height;
    }

    /**
     * Gets the internal representation of the matrix.
     *
     * @return SplFixedArray<SplFixedArray<int>>
     */
    public function getArray() : SplFixedArray
    {
        return $this->bytes;
    }

    /**
     * 逐行逐列地遍历矩阵中的全部字节值（生成器实现）
     *
     * 按行优先顺序（先 y 后 x）产出元素，遍历的是调用时刻的矩阵内容；
     * 由于是生成器，未消费完的迭代器不会执行剩余扫描，适合大矩阵的流式读取。
     *
     * @return Traversable<int> 依次产出每个单元的整数值
     */
    public function getBytes() : Traversable
    {
        foreach ($this->bytes as $row) {
            foreach ($row as $byte) {
                yield $byte;
            }
        }
    }

    /**
     * 读取指定坐标处的字节值
     *
     * 注意参数顺序为 (x, y)，内部按 bytes[y][x] 访问，不做边界检查。
     *
     * @param int $x 列索引，从 0 开始
     * @param int $y 行索引，从 0 开始
     * @return int 该单元保存的整数值（通常为 -1、0 或 1）
     */
    public function get(int $x, int $y) : int
    {
        return $this->bytes[$y][$x];
    }

    /**
     * 写入指定坐标处的字节值
     *
     * 注意参数顺序为 (x, y)，内部写入 bytes[y][x]，会直接覆盖原值；
     * 不做范围校验，越界时由 SplFixedArray 抛出运行时异常。
     *
     * @param int $x 列索引，从 0 开始
     * @param int $y 行索引，从 0 开始
     * @param int $value 要写入的整数值
     */
    public function set(int $x, int $y, int $value) : void
    {
        $this->bytes[$y][$x] = $value;
    }

    /**
     * 用指定值填满整个矩阵
     *
     * 逐个单元赋值，尺寸保持不变；编码前常用 -1 清空以标记“未使用”。
     *
     * @param int $value 要填充到每个单元的整数值
     */
    public function clear(int $value) : void
    {
        for ($y = 0; $y < $this->height; ++$y) {
            for ($x = 0; $x < $this->width; ++$x) {
                $this->bytes[$y][$x] = $value;
            }
        }
    }

    /**
     * 深拷贝矩阵，保证内部每一行 SplFixedArray 都被独立克隆
     */
    public function __clone()
    {
        $this->bytes = clone $this->bytes;

        foreach ($this->bytes as $index => $row) {
            $this->bytes[$index] = clone $row;
        }
    }

    /**
     * Returns a string representation of the matrix.
     */
    public function __toString() : string
    {
        $result = '';

        for ($y = 0; $y < $this->height; $y++) {
            for ($x = 0; $x < $this->width; $x++) {
                switch ($this->bytes[$y][$x]) {
                    case 0:
                        $result .= ' 0';
                        break;

                    case 1:
                        $result .= ' 1';
                        break;

                    default:
                        $result .= '  ';
                        break;
                }
            }

            $result .= "\n";
        }

        return $result;
    }
}
