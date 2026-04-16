<?php

declare(strict_types=1);

namespace zxf\Utils\BarCode\Generator;

use zxf\Utils\BarCode\Exceptions\InvalidDataException;

/**
 * EAN-8 条码生成器（修复版）
 * 
 * EAN-8 是 EAN-13 的短版格式
 */
class EAN8Generator extends BaseGenerator
{
    protected const TYPE = 'EAN-8';
    protected const START_GUARD = '101';
    protected const MIDDLE_GUARD = '01010';
    protected const END_GUARD = '101';
    protected const MODULE_WIDTH = 2;

    protected array $encodingA = [
        '0001101', '0011001', '0010011', '0111101', '0100011',
        '0110001', '0101111', '0111011', '0110111', '0001011'
    ];

    protected array $encodingC = [
        '1110010', '1100110', '1101100', '1000010', '1011100',
        '1001110', '1010000', '1000100', '1001000', '1110100'
    ];

    protected int $quietZoneModules = 7;

    public function generate(string $data): array
    {
        $data = $this->sanitizeData($data);
        
        if (!$this->validate($data)) {
            throw new InvalidDataException('EAN-8 数据必须是7或8位纯数字');
        }

        if (strlen($data) === 8) {
            $this->rawData = $data;
        } else {
            $checksum = $this->calculateChecksum($data);
            $this->rawData = $data . $checksum;
        }

        $this->barcodeArray = [];
        $this->longBarPositions = [];

        $leftData = substr($this->rawData, 0, 4);
        $rightData = substr($this->rawData, 4, 4);

        $binary = '';

        // 左侧静区
        $binary .= str_repeat('0', $this->quietZoneModules * self::MODULE_WIDTH);

        // 起始保护符
        $startGuardOffset = strlen($binary);
        $binary .= $this->expandPattern(self::START_GUARD);

        // 左侧4位（A模式）
        $leftBinary = '';
        for ($i = 0; $i < 4; $i++) {
            $digit = (int) $leftData[$i];
            $leftBinary .= $this->expandPattern($this->encodingA[$digit]);
        }
        $binary .= $leftBinary;

        // 中间分隔符
        $middleGuardOffset = strlen($binary);
        $binary .= $this->expandPattern(self::MIDDLE_GUARD);

        // 右侧4位（C模式）
        $rightBinary = '';
        for ($i = 0; $i < 4; $i++) {
            $digit = (int) $rightData[$i];
            $rightBinary .= $this->expandPattern($this->encodingC[$digit]);
        }
        $binary .= $rightBinary;

        // 终止保护符
        $endGuardOffset = strlen($binary);
        $binary .= $this->expandPattern(self::END_GUARD);

        // 右侧静区
        $binary .= str_repeat('0', $this->quietZoneModules * self::MODULE_WIDTH);

        $this->barcodeArray = $this->binaryToBars($binary);
        $this->calculateLongBarPositionsFromBinary($binary, $startGuardOffset, $middleGuardOffset, $endGuardOffset);

        return $this->barcodeArray;
    }

    /**
     * 基于二进制偏移量精确计算长竖线位置
     */
    protected function calculateLongBarPositionsFromBinary(
        string $binary,
        int $startGuardOffset,
        int $middleGuardOffset,
        int $endGuardOffset
    ): void {
        $this->longBarPositions = [];

        // 起始符 '101' -> 条在偏移 +0 和 +2
        $this->longBarPositions[] = $this->binaryOffsetToBarIndex($binary, $startGuardOffset);
        $this->longBarPositions[] = $this->binaryOffsetToBarIndex($binary, $startGuardOffset + 2 * self::MODULE_WIDTH);

        // 中间分隔符 '01010' -> 条在偏移 +1 和 +3
        $this->longBarPositions[] = $this->binaryOffsetToBarIndex($binary, $middleGuardOffset + 1 * self::MODULE_WIDTH);
        $this->longBarPositions[] = $this->binaryOffsetToBarIndex($binary, $middleGuardOffset + 3 * self::MODULE_WIDTH);

        // 终止符 '101' -> 条在偏移 +0 和 +2
        $this->longBarPositions[] = $this->binaryOffsetToBarIndex($binary, $endGuardOffset);
        $this->longBarPositions[] = $this->binaryOffsetToBarIndex($binary, $endGuardOffset + 2 * self::MODULE_WIDTH);

        sort($this->longBarPositions);
    }

    public function validate(string $data): bool
    {
        $data = $this->sanitizeData($data);
        
        if (strlen($data) !== 7 && strlen($data) !== 8) {
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
        
        if (strlen($data) !== 7 || !$this->isNumeric($data)) {
            throw new InvalidDataException('计算校验位需要7位纯数字');
        }

        // EAN-8权重：从右向左，奇数位(1,3,5,7)乘3，偶数位(2,4,6)乘1
        $sum = 0;
        $length = strlen($data);
        
        for ($i = 0; $i < $length; $i++) {
            // 从右边数第i+1位（位置i+1）
            $digit = (int) $data[$length - 1 - $i];
            // 奇数位（从右数，位置1,3,5,7）乘以3，偶数位乘以1
            $position = $i + 1;
            $multiplier = ($position % 2 === 1) ? 3 : 1;
            $sum += $digit * $multiplier;
        }

        $checksum = (10 - ($sum % 10)) % 10;
        return (string) $checksum;
    }

    /**
     * 将模式按 MODULE_WIDTH 展开（保持像素尺寸兼容）
     */
    protected function expandPattern(string $pattern): string
    {
        $expanded = '';
        for ($i = 0; $i < strlen($pattern); $i++) {
            $expanded .= str_repeat($pattern[$i], self::MODULE_WIDTH);
        }
        return $expanded;
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
            'type' => 'ean8',
            'leftDigits' => substr($this->rawData, 0, 4),
            'rightDigits' => substr($this->rawData, 4, 4),
        ];
    }
}
