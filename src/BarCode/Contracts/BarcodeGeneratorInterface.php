<?php

declare(strict_types=1);

namespace zxf\Utils\BarCode\Contracts;

/**
 * 条形码生成器接口
 * 
 * 定义所有条形码生成器必须实现的方法
 * 所有一维条形码生成器都需要实现此接口以保证统一性
 */
interface BarcodeGeneratorInterface
{
    /**
     * 生成条形码数据
     * 
     * @param string $data 要编码的数据
     * @return array 返回条形码条空模式数组，每个元素表示一个条或空的宽度单位数
     * @throws \InvalidArgumentException 当数据格式不正确时抛出
     */
    public function generate(string $data): array;

    /**
     * 验证数据是否适用于当前条码类型
     * 
     * @param string $data 要验证的数据
     * @return bool 数据有效返回true，否则返回false
     */
    public function validate(string $data): bool;

    /**
     * 获取条码类型名称
     * 
     * @return string 返回条码类型的标准名称
     */
    public function getType(): string;

    /**
     * 计算校验位
     * 
     * @param string $data 要计算校验位的数据
     * @return string 返回计算得到的校验位（单个字符）
     */
    public function calculateChecksum(string $data): string;

    /**
     * 设置配置对象
     * 
     * @param \zxf\Utils\BarCode\DTO\BarcodeConfig $config 配置对象
     * @return self 支持链式调用
     */
    public function setConfig(\zxf\Utils\BarCode\DTO\BarcodeConfig $config): self;

    /**
     * 获取长竖线（保护符）位置索引
     * 
     * @return array<int> 长竖线索引数组
     */
    public function getLongBarPositions(): array;

    /**
     * 获取完整数据（含校验位等附加信息）
     * 
     * @return string 完整数据
     */
    public function getFullData(): string;

    /**
     * 设置是否跳过校验位验证
     * 
     * 对于不需要校验位的条码类型，默认实现为空操作。
     * 
     * @param bool $skip 是否跳过
     * @return self 支持链式调用
     */
    public function setSkipChecksumValidation(bool $skip): self;
}
