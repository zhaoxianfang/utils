<?php
declare(strict_types = 1);

namespace zxf\Utils\QrCode\Common;

use zxf\Utils\QrCode\Exception\InvalidArgumentException;
use SplFixedArray;

/**
 * A simple, fast array of bits.
 */
final class BitArray
{
    /**
     * Bits represented as an array of integers.
     *
     * @var SplFixedArray<int>
     */
    private SplFixedArray $bits;

    /**
     * Creates a new bit array with a given size.
     */
    public function __construct(private int $size = 0)
    {
        $this->bits = SplFixedArray::fromArray(array_fill(0, ($this->size + 31) >> 3, 0));
    }

    /**
     * 获取位数组的长度，以 bit 为单位。
     *
     * 该长度由构造函数给定，之后仅随 appendBit()/appendBits() 的追加而增长，
     * 不包含为凑满 32 位字而预留的冗余位。
     *
     * @return int 位数组中有效 bit 的数量
     */
    public function getSize() : int
    {
        return $this->size;
    }

    /**
     * 获取位数组占用的字节数。
     *
     * 将 bit 长度按 8 位一组向上取整，因此末尾不足一字节的残余位也会
     * 计入一个完整字节。
     *
     * @return int 字节数，等于 (getSize() + 7) >> 3
     */
    public function getSizeInBytes() : int
    {
        return ($this->size + 7) >> 3;
    }

    /**
     * 确保内部存储至少能容纳指定数量的 bit。
     *
     * 内部按 32 位整数分段存储，仅当所需位数超过现有容量时才扩容，
     * 否则保持原状，因此可以安全地重复调用。
     *
     * @param int $size 期望能够容纳的最小 bit 数量
     */
    public function ensureCapacity(int $size) : void
    {
        if ($size > count($this->bits) << 5) {
            $this->bits->setSize(($size + 31) >> 5);
        }
    }

    /**
     * 读取指定下标处的 bit 值。
     *
     * 下标按 32 位分段定位到内部整数，再取其中对应的位；下标越界时
     * 由底层 SplFixedArray 抛出运行时异常。
     *
     * @param int $i bit 下标，从 0 开始计数
     * @return bool 该位为 1 时返回 true，为 0 时返回 false
     */
    public function get(int $i) : bool
    {
        return 0 !== ($this->bits[$i >> 5] & (1 << ($i & 0x1f)));
    }

    /**
     * 将指定下标处的 bit 置为 1，其余位保持不变。
     *
     * 通过按位或把目标位写入内部整数分段；本方法不会改变 getSize() 的值。
     *
     * @param int $i 要置位的 bit 下标，从 0 开始计数
     */
    public function set(int $i) : void
    {
        $this->bits[$i >> 5] = $this->bits[$i >> 5] | 1 << ($i & 0x1f);
    }

    /**
     * 翻转指定下标处的 bit（0 变 1，1 变 0）。
     *
     * 使用异或运算定位并取反目标位，不影响其他位。
     *
     * @param int $i 要翻转的 bit 下标，从 0 开始计数
     */
    public function flip(int $i) : void
    {
        $this->bits[$i >> 5] ^= 1 << ($i & 0x1f);
    }

    /**
     * 从指定位置起向后查找下一个值为 1 的 bit。
     *
     * 查找范围包含 $from 本身；先掩掉当前字中 $from 之前的位，再逐字扫描，
     * 若一直扫描到末尾仍未找到，则返回 getSize()。
     *
     * @param int $from 查找起始下标（含），从 0 开始计数
     * @return int 下一个置位 bit 的下标；不存在时返回位数组长度
     */
    public function getNextSet(int $from) : int
    {
        if ($from >= $this->size) {
            return $this->size;
        }

        $bitsOffset = $from >> 5;
        $currentBits = $this->bits[$bitsOffset];
        $bitsLength = count($this->bits);
        $currentBits &= ~((1 << ($from & 0x1f)) - 1);

        while (0 === $currentBits) {
            if (++$bitsOffset === $bitsLength) {
                return $this->size;
            }

            $currentBits = $this->bits[$bitsOffset];
        }

        $result = ($bitsOffset << 5) + BitUtils::numberOfTrailingZeros($currentBits);
        return min($result, $this->size);
    }

    /**
     * 从指定位置起向后查找下一个值为 0 的 bit。
     *
     * 查找范围包含 $from 本身；实现上对每个内部整数取反后再定位最低位的 1，
     * 等价于定位原值中最低位的 0，若一直扫描到末尾仍未找到则返回 getSize()。
     *
     * @param int $from 查找起始下标（含），从 0 开始计数
     * @return int 下一个未置位 bit 的下标；不存在时返回位数组长度
     */
    public function getNextUnset(int $from) : int
    {
        if ($from >= $this->size) {
            return $this->size;
        }

        $bitsOffset = $from >> 5;
        $currentBits = ~$this->bits[$bitsOffset];
        $bitsLength = count($this->bits);
        $currentBits &= ~((1 << ($from & 0x1f)) - 1);

        while (0 === $currentBits) {
            if (++$bitsOffset === $bitsLength) {
                return $this->size;
            }

            $currentBits = ~$this->bits[$bitsOffset];
        }

        $result = ($bitsOffset << 5) + BitUtils::numberOfTrailingZeros($currentBits);
        return min($result, $this->size);
    }

    /**
     * 以整字为单位批量写入 bit。
     *
     * 直接把 32 位整数写入 $i 所在的内部整数分段（而非按位修改），
     * 因此会整体覆盖该分段上原有的 32 个位。
     *
     * @param int $i 写入位置对应的 bit 下标，内部按 $i >> 5 定位到分段
     * @param int $newBits 要写入的 32 位值，按位解释为目标分段的位图
     */
    public function setBulk(int $i, int $newBits) : void
    {
        $this->bits[$i >> 5] = $newBits;
    }

    /**
     * 将 [start, end) 区间内的所有 bit 置为 1。
     *
     * 区间左闭右开，$end 处的位不会被修改；逐字段生成掩码后与内部整数做按位或，
     * 因此不会影响区间外的位。当 $end 等于 $start 时直接返回。
     *
     * @param int $start 起始下标（含）
     * @param int $end 结束下标（不含）
     * @throws InvalidArgumentException 当 $end 小于 $start 时抛出
     */
    public function setRange(int $start, int $end) : void
    {
        if ($end < $start) {
            throw new InvalidArgumentException('End must be greater or equal to start');
        }

        if ($end === $start) {
            return;
        }

        --$end;

        $firstInt = $start >> 5;
        $lastInt = $end >> 5;

        for ($i = $firstInt; $i <= $lastInt; ++$i) {
            $firstBit = $i > $firstInt ? 0 : $start & 0x1f;
            $lastBit = $i < $lastInt ? 31 : $end & 0x1f;

            if (0 === $firstBit && 31 === $lastBit) {
                // 32 位全置 1：PHP 的 int 为 64 位，不能用 -1（会污染 32~63 位）
                $mask = 0xFFFFFFFF;
            } else {
                $mask = 0;

                // 闭区间：$lastBit 这一位也必须置 1，否则单 bit 区间（$start = $end - 1）不会置位
                for ($j = $firstBit; $j <= $lastBit; ++$j) {
                    $mask |= 1 << $j;
                }
            }

            $this->bits[$i] = $this->bits[$i] | $mask;
        }
    }

    /**
     * Clears the bit array, unsetting every bit.
     */
    public function clear() : void
    {
        $bitsLength = count($this->bits);

        for ($i = 0; $i < $bitsLength; ++$i) {
            $this->bits[$i] = 0;
        }
    }

    /**
     * 判断 [start, end) 区间内的 bit 是否全部等于期望值。
     *
     * 区间左闭右开，$end 处不计入；逐字段生成掩码后与内部整数比较，
     * 一旦发现某字段不符合期望即提前返回 false，全部符合才返回 true。
     * 当 $end 等于 $start（空区间）时视为成立，直接返回 true。
     *
     * @param int $start 起始下标（含）
     * @param int $end 结束下标（不含）
     * @param bool $value 期望的位值：true 表示期望全为 1，false 表示期望全为 0
     * @return bool 区间内所有位都等于 $value 时返回 true，否则返回 false
     * @throws InvalidArgumentException 当 $end 小于 $start 时抛出
     */
    public function isRange(int $start, int $end, bool $value) : bool
    {
        if ($end < $start) {
            throw new InvalidArgumentException('End must be greater or equal to start');
        }

        if ($end === $start) {
            return true;
        }

        --$end;

        $firstInt = $start >> 5;
        $lastInt = $end >> 5;

        for ($i = $firstInt; $i <= $lastInt; ++$i) {
            $firstBit = $i > $firstInt ? 0 : $start & 0x1f;
            $lastBit = $i < $lastInt ? 31 : $end & 0x1f;

            if (0 === $firstBit && 31 === $lastBit) {
                // 32 位全置 1，与 setRange() 保持一致（含第 31 位）
                $mask = 0xFFFFFFFF;
            } else {
                $mask = 0;

                for ($j = $firstBit; $j <= $lastBit; ++$j) {
                    $mask |= 1 << $j;
                }
            }

            if (($this->bits[$i] & $mask) !== ($value ? $mask : 0)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 在位数组末尾追加一个 bit。
     *
     * 先按需扩容，再在 size 位置写入该位（true 置 1，false 保持 0），
     * 最后把长度加一，因此末尾追加的 0 同样会占据一个有效位置。
     *
     * @param bool $bit 要追加的位值，true 表示 1，false 表示 0
     */
    public function appendBit(bool $bit) : void
    {
        $this->ensureCapacity($this->size + 1);

        if ($bit) {
            $this->bits[$this->size >> 5] = $this->bits[$this->size >> 5] | (1 << ($this->size & 0x1f));
        }

        ++$this->size;
    }

    /**
     * 追加一个整数的低 $numBits 位（最多 32 位）。
     *
     * 从最高有效位开始逐位取出并追加，因此写入顺序为高位在前；
     * 若 $numBits 为 0 则不追加任何内容。
     *
     * @param int $value 待追加的数值，仅其低 $numBits 位有效
     * @param int $numBits 追加的位数，取值 0~32
     * @throws InvalidArgumentException 当 $numBits 不在 0~32 之间时抛出
     */
    public function appendBits(int $value, int $numBits) : void
    {
        if ($numBits < 0 || $numBits > 32) {
            throw new InvalidArgumentException('Num bits must be between 0 and 32');
        }

        $this->ensureCapacity($this->size + $numBits);

        for ($numBitsLeft = $numBits; $numBitsLeft > 0; $numBitsLeft--) {
            $this->appendBit((($value >> ($numBitsLeft - 1)) & 0x01) === 1);
        }
    }

    /**
     * 将另一个位数组完整追加到当前位数组末尾。
     *
     * 按对方的下标顺序逐个复制有效位，并预先一次性扩容以避免反复分配；
     * 追加后当前数组长度增加对方的 getSize()。
     *
     * @param self $other 提供追加内容的源位数组
     */
    public function appendBitArray(self $other) : void
    {
        $otherSize = $other->getSize();
        $this->ensureCapacity($this->size + $other->getSize());

        for ($i = 0; $i < $otherSize; ++$i) {
            $this->appendBit($other->get($i));
        }
    }

    /**
     * 与另一个位数组逐位做异或运算，结果写回当前位数组。
     *
     * 以内部整数分段为单位执行异或，因此要求两个位数组的内部长度一致；
     * 运算结果就地写回当前位数组，$other 本身保持不变。
     *
     * @param self $other 参与异或运算的另一个位数组
     * @throws InvalidArgumentException 当两个位数组的内部长度不一致时抛出
     */
    public function xorBits(self $other) : void
    {
        $bitsLength = count($this->bits);
        $otherBits  = $other->getBitArray();

        if ($bitsLength !== count($otherBits)) {
            throw new InvalidArgumentException('Sizes don\'t match');
        }

        for ($i = 0; $i < $bitsLength; ++$i) {
            $this->bits[$i] = $this->bits[$i] ^ $otherBits[$i];
        }
    }

    /**
     * 从指定位偏移开始，把连续的 bit 按 8 位一组打包成字节数组。
     *
     * 每组内高位在前（大端序），共读取 $numBytes 组；读取过程中偏移量
     * 会持续向后推进，不做越界检查。
     *
     * @param int $bitOffset 起始 bit 下标，从 0 开始计数
     * @param int $numBytes 需要转换的字节数
     * @return SplFixedArray<int> 长度为 $numBytes 的字节数组，元素为 0~255 的整数
     */
    public function toBytes(int $bitOffset, int $numBytes) : SplFixedArray
    {
        $bytes = new SplFixedArray($numBytes);

        for ($i = 0; $i < $numBytes; ++$i) {
            $byte = 0;

            for ($j = 0; $j < 8; ++$j) {
                if ($this->get($bitOffset)) {
                    $byte |= 1 << (7 - $j);
                }

                ++$bitOffset;
            }

            $bytes[$i] = $byte;
        }

        return $bytes;
    }

    /**
     * Gets the internal bit array.
     *
     * @return SplFixedArray<int>
     */
    public function getBitArray() : SplFixedArray
    {
        return $this->bits;
    }

    /**
     * Reverses the array.
     */
    public function reverse() : void
    {
        $newBits = new SplFixedArray(count($this->bits));

        for ($i = 0; $i < $this->size; ++$i) {
            if ($this->get($this->size - $i - 1)) {
                $newBits[$i >> 5] = $newBits[$i >> 5] | (1 << ($i & 0x1f));
            }
        }

        $this->bits = $newBits;
    }

    /**
     * Returns a string representation of the bit array.
     */
    public function __toString() : string
    {
        $result = '';

        for ($i = 0; $i < $this->size; ++$i) {
            if (0 === ($i & 0x07)) {
                $result .= ' ';
            }

            $result .= $this->get($i) ? 'X' : '.';
        }

        return $result;
    }
}
