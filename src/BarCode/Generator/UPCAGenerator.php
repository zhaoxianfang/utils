<?php

declare(strict_types=1);

namespace zxf\Utils\BarCode\Generator;

use zxf\Utils\BarCode\Exceptions\InvalidDataException;

/**
 * UPC-A 条码生成器（修复版）
 * 
 * UPC-A（通用产品代码）是北美地区广泛使用的零售商品条码
 * 12位纯数字，结构与EAN-13类似
 */
class UPCAGenerator extends BaseGenerator
{
    protected const TYPE = 'UPC-A';
    protected const START_GUARD = '101';
    protected const MIDDLE_GUARD = '01010';
    protected const END_GUARD = '101';

    protected array $encodingA = [
        '0001101', '0011001', '0010011', '0111101', '0100011',
        '0110001', '0101111', '0111011', '0110111', '0001011'
    ];

    protected array $encodingC = [
        '1110010', '1100110', '1101100', '1000010', '1011100',
        '1001110', '1010000', '1000100', '1001000', '1110100'
    ];

    protected int $quietZoneModules = 9;

    public function generate(string $data): array
    {
        $data = $this->sanitizeData($data);
        
        if (!$this->validate($data)) {
            throw new InvalidDataException('UPC-A 数据必须是11或12位纯数字');
        }

        // 处理校验位 - 移除校验位验证，保证条码内容与传入内容完全一致
        if (strlen($data) === 12) {
            // 直接使用传入的12位数据，不再验证校验位
            $this->rawData = $data;
        } else {
            // 11位数据，自动计算校验位
            $checksum = $this->calculateChecksum($data);
            $this->rawData = $data . $checksum;
        }

        $this->barcodeArray = [];
        $this->longBarPositions = [];

        $leftData = substr($this->rawData, 0, 6);
        $rightData = substr($this->rawData, 6, 6);

        // 构建编码
        $binaryString = '';
        
        // 左侧静区
        $binaryString .= str_repeat('0', $this->quietZoneModules);
        
        // 起始保护符
        $binaryString .= self::START_GUARD;
        
        // 左侧6位（A模式）
        for ($i = 0; $i < 6; $i++) {
            $digit = (int) $leftData[$i];
            $binaryString .= $this->encodingA[$digit];
        }
        
        // 中间分隔符
        $binaryString .= self::MIDDLE_GUARD;
        
        // 右侧6位（C模式）
        for ($i = 0; $i < 6; $i++) {
            $digit = (int) $rightData[$i];
            $binaryString .= $this->encodingC[$digit];
        }
        
        // 终止保护符
        $binaryString .= self::END_GUARD;
        
        // 右侧静区
        $binaryString .= str_repeat('0', $this->quietZoneModules);

        $this->barcodeArray = $this->binaryToBars($binaryString);
        $this->calculateLongBarPositions();

        return $this->barcodeArray;
    }

    /**
     * 计算长竖线位置（基于条空模式数组索引）
     *
     * UPC-A 结构：静区9 + 起始符3 + 左侧42 + 分隔符5 + 右侧42 + 终止符3 + 静区9
     * 长竖线在：起始符(2条)、中间分隔符(2条)、终止符(2条)
     */
    protected function calculateLongBarPositions(): void
    {
        $this->longBarPositions = [];

        // 分析条空模式数组，找到所有条（正数元素）
        $barIndices = [];
        foreach ($this->barcodeArray as $i => $element) {
            if ($element > 0) {
                $barIndices[] = $i;
            }
        }

        // UPC-A 有3组保护符，每组2条长竖线，共6条
        // 条在数组中的大致分布：起始符(2条) + 数据中的条 + 分隔符(2条) + 数据中的条 + 终止符(2条)
        // 简单策略：取前2条、中间2条、后2条作为长竖线
        $totalBars = count($barIndices);
        if ($totalBars >= 6) {
            // 前2条（起始符）
            $this->longBarPositions[] = $barIndices[0];
            $this->longBarPositions[] = $barIndices[1];

            // 后2条（终止符）
            $this->longBarPositions[] = $barIndices[$totalBars - 2];
            $this->longBarPositions[] = $barIndices[$totalBars - 1];

            // 中间2条（分隔符）- 大致在中间位置
            $middleIdx = (int)($totalBars / 2);
            $this->longBarPositions[] = $barIndices[$middleIdx - 1];
            $this->longBarPositions[] = $barIndices[$middleIdx];
        }

        sort($this->longBarPositions);
    }

    public function validate(string $data): bool
    {
        $data = $this->sanitizeData($data);
        
        if (strlen($data) !== 11 && strlen($data) !== 12) {
            return false;
        }

        if (!$this->isNumeric($data)) {
            return false;
        }

        return true;
    }

    public function calculateChecksum(string $data): string
    {
        $data = $this->sanitizeData($data);
        
        if (strlen($data) !== 11 || !$this->isNumeric($data)) {
            throw new InvalidDataException('计算校验位需要11位纯数字');
        }

        return $this->calculateMod10Checksum($data);
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    public function getFullData(): string
    {
        return $this->rawData;
    }

    public function getDigitLayout(): array
    {
        return [
            'type' => 'upca',
            'numberSystem' => $this->rawData[0] ?? '',
            'manufacturerCode' => substr($this->rawData, 1, 5),
            'productCode' => substr($this->rawData, 6, 5),
            'checkDigit' => $this->rawData[11] ?? '',
        ];
    }
}
