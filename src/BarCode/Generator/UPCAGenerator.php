<?php

declare(strict_types=1);

namespace zxf\Utils\BarCode\Generator;

use zxf\Utils\BarCode\Exceptions\InvalidDataException;

/**
 * UPC-A 条码生成器（修复版）
 * 
 * UPC-A（通用产品代码）是北美地区广泛使用的零售商品条码
 * 12位纯数字，结构与EAN-13类似
 *
 * 注意：本生成器输出【模块级】条空数组（1 模块 = 1 个单位），与 EAN-13 一致，
 * 最终像素宽度 = 模块数 × renderer 的 moduleSize。历史上曾把每个模块预展开成 2px
 * （MODULE_WIDTH=2），导致 moduleSize 语义翻倍、同一 moduleSize 下比 EAN-13 宽一倍，
 * 已修复为模块级输出。
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

        if (strlen($data) === 12) {
            // 输入已含校验位：默认验证正确性，校验位错误时抛异常
            $this->assertCompleteChecksum($data, 'UPC-A');
            $this->rawData = $data;
        } else {
            $checksum = $this->calculateChecksum($data);
            $this->rawData = $data . $checksum;
        }

        $this->barcodeArray = [];
        $this->longBarPositions = [];

        $leftData = substr($this->rawData, 0, 6);
        $rightData = substr($this->rawData, 6, 6);

        $binary = '';

        // 左侧静区（模块级）
        $binary .= str_repeat('0', $this->quietZoneModules);

        // 起始保护符
        $startGuardOffset = strlen($binary);
        $binary .= self::START_GUARD;

        // 左侧6位（A模式）
        $leftBinary = '';
        for ($i = 0; $i < 6; $i++) {
            $digit = (int) $leftData[$i];
            $leftBinary .= $this->encodingA[$digit];
        }
        $binary .= $leftBinary;

        // 中间分隔符
        $middleGuardOffset = strlen($binary);
        $binary .= self::MIDDLE_GUARD;

        // 右侧6位（C模式）
        $rightBinary = '';
        for ($i = 0; $i < 6; $i++) {
            $digit = (int) $rightData[$i];
            $rightBinary .= $this->encodingC[$digit];
        }
        $binary .= $rightBinary;

        // 终止保护符
        $endGuardOffset = strlen($binary);
        $binary .= self::END_GUARD;

        // 右侧静区（模块级）
        $binary .= str_repeat('0', $this->quietZoneModules);

        $this->barcodeArray = $this->binaryToBars($binary);
        $this->calculateLongBarPositionsFromBinary($binary, $startGuardOffset, $middleGuardOffset, $endGuardOffset);

        return $this->barcodeArray;
    }

    /**
     * 基于二进制偏移量精确计算长竖线位置（模块级偏移）
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
        $this->longBarPositions[] = $this->binaryOffsetToBarIndex($binary, $startGuardOffset + 2);

        // 中间分隔符 '01010' -> 条在偏移 +1 和 +3
        $this->longBarPositions[] = $this->binaryOffsetToBarIndex($binary, $middleGuardOffset + 1);
        $this->longBarPositions[] = $this->binaryOffsetToBarIndex($binary, $middleGuardOffset + 3);

        // 终止符 '101' -> 条在偏移 +0 和 +2
        $this->longBarPositions[] = $this->binaryOffsetToBarIndex($binary, $endGuardOffset);
        $this->longBarPositions[] = $this->binaryOffsetToBarIndex($binary, $endGuardOffset + 2);

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
