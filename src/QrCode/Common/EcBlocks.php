<?php
declare(strict_types = 1);

namespace zxf\Utils\QrCode\Common;

/**
 * Encapsulates a set of error-correction blocks in one symbol version.
 *
 * Most versions will use blocks of differing sizes within one version, so, this encapsulates the parameters for each
 * set of blocks. It also holds the number of error-correction codewords per block since it will be the same across all
 * blocks within one version.
 */
final class EcBlocks
{
    /**
     * List of EC blocks.
     *
     * @var EcBlock[]
     */
    private array $ecBlocks;

    /**
     * 构造一组错误纠正块集合
     *
     * @param int      $ecCodewordsPerBlock 每个块统一的错误纠正码字数
     * @param EcBlock ...$ecBlocks          组成该版本的若干错误纠正块
     */
    public function __construct(private readonly int $ecCodewordsPerBlock, EcBlock ...$ecBlocks)
    {
        $this->ecBlocks = $ecBlocks;
    }

    /**
     * Returns the number of EC codewords per block.
     *
     * 同一版本内所有块的纠错码字数相同，因此该值可统一用于计算总量。
     *
     * @return int 每个块包含的错误纠正码字数
     */
    public function getEcCodewordsPerBlock() : int
    {
        return $this->ecCodewordsPerBlock;
    }

    /**
     * Returns the total number of EC block appearances.
     *
     * 累加各组块的重复次数，得到该版本实际的纠错块总数。
     *
     * @return int 该版本中纠错块的总个数
     */
    public function getNumBlocks() : int
    {
        $total = 0;

        foreach ($this->ecBlocks as $ecBlock) {
            $total += $ecBlock->getCount();
        }

        return $total;
    }

    /**
     * Returns the total count of EC codewords.
     *
     * 由每块纠错码字数乘以块总数得到，即该版本用于纠错的码字总量。
     *
     * @return int 该版本全部纠错码字的总数
     */
    public function getTotalEcCodewords() : int
    {
        return $this->ecCodewordsPerBlock * $this->getNumBlocks();
    }

    /**
     * Returns the EC blocks included in this collection.
     *
     * @return EcBlock[]
     */
    public function getEcBlocks() : array
    {
        return $this->ecBlocks;
    }
}
