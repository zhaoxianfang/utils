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

        // 处理校验位
        if (strlen($data) === 12) {
            $checkData = substr($data, 0, 11);
            $providedCheck = $data[11];
            $calculatedCheck = $this->calculateChecksum($checkData);
            if ($providedCheck !== $calculatedCheck) {
                throw new InvalidDataException(
                    "校验位错误: 提供的校验位是 '{$providedCheck}'，计算得到的校验位是 '{$calculatedCheck}'"
                );
            }
            $this->rawData = $data;
        } else {
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

    protected function calculateLongBarPositions(): void
    {
        $this->longBarPositions = [];
        
        // UPC-A 结构（共113模块）：
        // 静区9 + 起始符3 + 左侧42 + 分隔符5 + 右侧42 + 终止符3 + 静区9
        
        // 起始保护符位置（在左侧静区之后，9模块处开始）
        // 起始保护符编码：101，位置9和11
        $startPos = $this->quietZoneModules;
        $this->longBarPositions[] = $startPos;      // 第1条（位置9）
        $this->longBarPositions[] = $startPos + 2;  // 第3条（位置11）
        
        // 中间分隔符位置（在左侧6位之后：9+3+42=54）
        // 中间分隔符编码：01010，位置55和57（第2条和第4条）
        $middlePos = $this->quietZoneModules + 3 + 42;
        $this->longBarPositions[] = $middlePos + 1;  // 第2条（位置55）
        $this->longBarPositions[] = $middlePos + 3;  // 第4条（位置57）
        
        // 终止保护符位置（在右侧6位之后：54+5+42=101）
        // 终止保护符编码：101，位置101和103
        $endPos = $this->quietZoneModules + 3 + 42 + 5 + 42;
        $this->longBarPositions[] = $endPos;        // 第1条（位置101）
        $this->longBarPositions[] = $endPos + 2;    // 第3条（位置103）
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
