<?php

namespace zxf\Utils\QrCode;

use Exception;

/**
 * 二维码错误纠正级别枚举类
 * 定义了四种标准的二维码错误纠正级别
 */
final class ErrorCorrectionLevel
{
    /** @var int L级别 - 约7%的错误纠正能力 */
    public const L = 0x01;

    /** @var int M级别 - 约15%的错误纠正能力 */
    public const M = 0x00;

    /** @var int Q级别 - 约25%的错误纠正能力 */
    public const Q = 0x03;

    /** @var int H级别 - 约30%的错误纠正能力 */
    public const H = 0x02;

    /** @var array 错误纠正级别名称映射 */
    private static array $names = [
        self::L => 'L',
        self::M => 'M',
        self::Q => 'Q',
        self::H => 'H',
    ];

    /** @var int 当前错误纠正级别值 */
    private int $value;

    /**
     * 私有构造函数，防止直接实例化
     *
     * @param int $value 错误纠正级别值
     * @throws Exception 如果值无效
     */
    private function __construct(int $value)
    {
        if (!isset(self::$names[$value])) {
            throw new Exception('无效的错误纠正级别: ' . $value);
        }
        $this->value = $value;
    }

    /**
     * 获取L级别（最低错误纠正，约7%）
     * 适用于数据量较大但允许一定错误的场景
     *
     * @return self
     */
    public static function low(): self
    {
        return new self(self::L);
    }

    /**
     * 获取M级别（中等错误纠正，约15%）
     * 最常用的级别，平衡了容错和数据量
     *
     * @return self
     */
    public static function medium(): self
    {
        return new self(self::M);
    }

    /**
     * 获取Q级别（较高错误纠正，约25%）
     * 适用于需要较高容错率的场景
     *
     * @return self
     */
    public static function quartile(): self
    {
        return new self(self::Q);
    }

    /**
     * 获取H级别（最高错误纠正，约30%）
     * 适用于对容错率要求极高的场景
     *
     * @return self
     */
    public static function high(): self
    {
        return new self(self::H);
    }

    /**
     * 根据值创建错误纠正级别对象
     *
     * @param int $value 错误纠正级别值
     * @return self
     * @throws Exception
     */
    public static function fromValue(int $value): self
    {
        return new self($value);
    }

    /**
     * 获取当前级别的数值
     *
     * @return int
     */
    public function getValue(): int
    {
        return $this->value;
    }

    /**
     * 获取当前级别的名称（L/M/Q/H）
     *
     * @return string
     */
    public function getName(): string
    {
        return self::$names[$this->value];
    }

    /**
     * 获取当前级别的位数（用于编码类型信息）
     *
     * @return int
     */
    public function getBits(): int
    {
        return $this->value;
    }

    /**
     * 字符串表示
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->getName();
    }
}
