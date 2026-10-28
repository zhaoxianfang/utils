<?php
declare(strict_types = 1);

namespace zxf\Utils\QrCode\Common;

/**
 * Encapsulates the parameters for one error-correction block in one symbol version.
 *
 * This includes the number of data codewords, and the number of times a block with these parameters is used
 * consecutively in the QR code version's format.
 */
final class EcBlock
{
    /**
     * 构造一个错误纠正块描述
     *
     * @param int $count        该参数块在版本中连续出现的次数
     * @param int $dataCodewords 每个块包含的数据码字数
     */
    public function __construct(private readonly int $count, private readonly int $dataCodewords)
    {
    }

    /**
     * Returns how many times the block is used.
     *
     * 同一版本中参数相同的块会连续出现多次，该值即这类块的重复次数。
     *
     * @return int 该参数块在版本中连续出现的次数
     */
    public function getCount() : int
    {
        return $this->count;
    }

    /**
     * Returns the number of data codewords.
     *
     * 仅统计该块中的有效数据码字，不包含随后附加的错误纠正码字。
     *
     * @return int 每个块包含的数据码字数
     */
    public function getDataCodewords() : int
    {
        return $this->dataCodewords;
    }
}
