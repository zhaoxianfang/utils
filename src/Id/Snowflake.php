<?php

declare(strict_types=1);

namespace zxf\Utils\Id;

use InvalidArgumentException;
use RuntimeException;

/**
 * 雪花算法分布式ID生成器
 * 基于 Twitter Snowflake 算法，支持自定义数据中心和工作节点
 * 生成64位长整型唯一ID，趋势递增，适合高并发分布式场景
 *
 * ID 结构（共64位）：
 * - 1位符号位（始终为0，保证正数）
 * - 41位时间戳（毫秒级，约可用69年）
 * - 5位数据中心ID（0-31）
 * - 5位工作节点ID（0-31）
 * - 12位序列号（每毫秒每节点最多4096个ID）
 *
 * @package Id
 * @version 1.0.0
 * @license MIT
 */
class Snowflake
{
    /** 起始时间戳（2024-01-01 00:00:00 UTC），用于缩短时间戳占用位数 */
    private const EPOCH = 1704067200000;

    /** 数据中心ID所占位数 */
    private const DATA_CENTER_BITS = 5;

    /** 工作节点ID所占位数 */
    private const WORKER_BITS = 5;

    /** 序列号所占位数 */
    private const SEQUENCE_BITS = 12;

    /** 最大数据中心ID（2^5 - 1 = 31） */
    private const MAX_DATA_CENTER_ID = (1 << self::DATA_CENTER_BITS) - 1;

    /** 最大工作节点ID（2^5 - 1 = 31） */
    private const MAX_WORKER_ID = (1 << self::WORKER_BITS) - 1;

    /** 最大序列号（2^12 - 1 = 4095） */
    private const MAX_SEQUENCE = (1 << self::SEQUENCE_BITS) - 1;

    /** 工作节点ID在64位ID中的左移位数 */
    private const WORKER_SHIFT = self::SEQUENCE_BITS;

    /** 数据中心ID在64位ID中的左移位数 */
    private const DATA_CENTER_SHIFT = self::SEQUENCE_BITS + self::WORKER_BITS;

    /** 时间戳在64位ID中的左移位数 */
    private const TIMESTAMP_SHIFT = self::SEQUENCE_BITS + self::WORKER_BITS + self::DATA_CENTER_BITS;

    /** @var int 数据中心ID（0-31） */
    private int $dataCenterId;

    /** @var int 工作节点ID（0-31） */
    private int $workerId;

    /** @var int 当前毫秒内的序列号（0-4095） */
    private int $sequence = 0;

    /** @var int 上次生成ID的时间戳（毫秒） */
    private int $lastTimestamp = -1;

    /**
     * 构造函数
     *
     * @param int $dataCenterId 数据中心ID，范围为 0-31，默认为 0
     * @param int $workerId     工作节点ID，范围为 0-31，默认为 0
     * @throws InvalidArgumentException 当ID超出有效范围时抛出
     */
    public function __construct(int $dataCenterId = 0, int $workerId = 0)
    {
        if ($dataCenterId < 0 || $dataCenterId > self::MAX_DATA_CENTER_ID) {
            throw new InvalidArgumentException("数据中心ID必须在 0-" . self::MAX_DATA_CENTER_ID . " 之间");
        }
        if ($workerId < 0 || $workerId > self::MAX_WORKER_ID) {
            throw new InvalidArgumentException("工作节点ID必须在 0-" . self::MAX_WORKER_ID . " 之间");
        }
        $this->dataCenterId = $dataCenterId;
        $this->workerId = $workerId;
    }

    /**
     * 生成下一个唯一ID
     *
     * 在同一毫秒内，通过递增序列号保证唯一性；
     * 若序列号溢出，则等待至下一毫秒再继续生成。
     *
     * @return int 64位长整型唯一ID
     * @throws RuntimeException 当检测到系统时钟回拨时抛出，防止生成重复ID
     */
    public function nextId(): int
    {
        $timestamp = $this->currentTimeMillis();

        if ($timestamp < $this->lastTimestamp) {
            throw new RuntimeException("时钟回拨，拒绝生成ID");
        }

        if ($timestamp === $this->lastTimestamp) {
            $this->sequence = ($this->sequence + 1) & self::MAX_SEQUENCE;
            if ($this->sequence === 0) {
                $timestamp = $this->tilNextMillis($this->lastTimestamp);
            }
        } else {
            $this->sequence = 0;
        }

        $this->lastTimestamp = $timestamp;

        return (($timestamp - self::EPOCH) << self::TIMESTAMP_SHIFT)
            | ($this->dataCenterId << self::DATA_CENTER_SHIFT)
            | ($this->workerId << self::WORKER_SHIFT)
            | $this->sequence;
    }

    /**
     * 批量生成唯一ID
     *
     * @param int $count 生成数量，范围为 1-10000
     * @return int[] 唯一ID数组
     * @throws InvalidArgumentException 当数量超出范围时抛出
     */
    public function nextIds(int $count): array
    {
        if ($count < 1 || $count > 10000) {
            throw new InvalidArgumentException('批量生成数量必须在 1-10000 之间');
        }
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $ids[] = $this->nextId();
        }
        return $ids;
    }

    /**
     * 解析雪花ID，提取各部分元数据信息
     *
     * @param int $id 雪花算法生成的64位ID
     * @return array{id:int,timestamp:int,datetime:string,data_center:int,worker:int,sequence:int} ID解析结果数组
     */
    public static function parse(int $id): array
    {
        $timestamp = (($id >> self::TIMESTAMP_SHIFT) + self::EPOCH) / 1000;
        return [
            'id'            => $id,
            'timestamp'     => (int) $timestamp,
            'datetime'      => date('Y-m-d H:i:s.v', (int) $timestamp),
            'data_center'   => ($id >> self::DATA_CENTER_SHIFT) & self::MAX_DATA_CENTER_ID,
            'worker'        => ($id >> self::WORKER_SHIFT) & self::MAX_WORKER_ID,
            'sequence'      => $id & self::MAX_SEQUENCE,
        ];
    }

    /**
     * 生成字符串格式的唯一ID（36进制，更短易读）
     *
     * @return string 36进制字符串ID
     */
    public function nextIdString(): string
    {
        return base_convert((string) $this->nextId(), 10, 36);
    }

    /**
     * 获取当前系统时间的毫秒时间戳
     *
     * @return int 毫秒时间戳
     */
    private function currentTimeMillis(): int
    {
        return (int) (microtime(true) * 1000);
    }

    /**
     * 自旋等待，直到获取到大于 lastTimestamp 的下一个毫秒时间戳
     *
     * 当同一毫秒内序列号溢出时调用，避免ID冲突。
     *
     * @param int $lastTimestamp 上次生成ID的时间戳
     * @return int 下一个毫秒时间戳
     */
    private function tilNextMillis(int $lastTimestamp): int
    {
        $timestamp = $this->currentTimeMillis();
        while ($timestamp <= $lastTimestamp) {
            $timestamp = $this->currentTimeMillis();
        }
        return $timestamp;
    }
}
