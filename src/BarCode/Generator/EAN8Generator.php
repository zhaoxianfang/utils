<?php

declare(strict_types=1);

namespace zxf\Utils\BarCode\Generator;

use zxf\Utils\BarCode\Exceptions\InvalidDataException;

/**
 * EAN-8 条码生成器（修复版）
 * 
 * EAN-8 是 EAN-13 的短版格式
 *
 * 注意：本生成器输出【模块级】条空数组（1 模块 = 1 个单位），与 EAN-13 一致，
 * 最终像素宽度 = 模块数 × renderer 的 moduleSize。历史上曾把每个模块预展开成 2px
 * （MODULE_WIDTH=2），导致 moduleSize 语义翻倍、同一 moduleSize 下比 EAN-13 宽一倍，
 * 已修复为模块级输出。
 */
class EAN8Generator extends BaseGenerator
{
    protected const TYPE = 'EAN-8';
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

    protected int $quietZoneModules = 7;

    /**
     * 生成 EAN-8 条码的条空模式数组
     *
     * 支持 7 位（自动补算校验位）或 8 位（含校验位，默认校验其正确性）数字输入；
     * 输出模块级条空数组（正数=条、负数=空，绝对值为模块数），供 renderer 按 moduleSize 放大。
     *
     * @param string $data 要编码的 EAN-8 数据（7 或 8 位纯数字）
     * @return array<int> 条空模式数组（1 模块 = 1 个单位）
     * @throws InvalidDataException 数据非 7/8 位纯数字，或 8 位输入校验位错误时抛出
     */
    public function generate(string $data): array
    {
        $data = $this->sanitizeData($data);
        
        if (!$this->validate($data)) {
            throw new InvalidDataException('EAN-8 数据必须是7或8位纯数字');
        }

        if (strlen($data) === 8) {
            // 输入已含校验位：默认验证正确性，校验位错误时抛异常
            $this->assertCompleteChecksum($data, 'EAN-8');
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

        // 左侧静区（模块级）
        $binary .= str_repeat('0', $this->quietZoneModules);

        // 起始保护符
        $startGuardOffset = strlen($binary);
        $binary .= self::START_GUARD;

        // 左侧4位（A模式）
        $leftBinary = '';
        for ($i = 0; $i < 4; $i++) {
            $digit = (int) $leftData[$i];
            $leftBinary .= $this->encodingA[$digit];
        }
        $binary .= $leftBinary;

        // 中间分隔符
        $middleGuardOffset = strlen($binary);
        $binary .= self::MIDDLE_GUARD;

        // 右侧4位（C模式）
        $rightBinary = '';
        for ($i = 0; $i < 4; $i++) {
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
     *
     * 依次根据起始符、中间分隔符、终止符的模块偏移定位其条的位置，再把
     * 模块偏移换算成条空数组下标，最后排序后写入 longBarPositions。
     *
     * @param string $binary 完整的二进制模块串（含静区）
     * @param int $startGuardOffset 起始符在二进制串中的模块偏移
     * @param int $middleGuardOffset 中间分隔符在二进制串中的模块偏移
     * @param int $endGuardOffset 终止符在二进制串中的模块偏移
     * @return void
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

    /**
     * 校验 EAN-8 数据是否合法
     *
     * 合法要求：去除非法字符后为 7 位或 8 位，且全部为数字。
     * 注意：本方法仅检查格式长度与数字性，不校验 8 位输入的校验位正确性。
     *
     * @param string $data 待校验的 EAN-8 数据
     * @return bool 合法返回 true，否则返回 false
     */
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

    /**
     * 计算 EAN-8 校验位
     *
     * 采用 EAN 模 10 算法（从右向左奇数位乘 3、偶数位乘 1 求和后取 10 补数）。
     * 需传入前 7 位纯数字，自动补算第 8 位校验位。
     *
     * @param string $data 前 7 位纯数字 EAN-8 数据
     * @return string 校验位数字（0-9）
     * @throws InvalidDataException 数据非 7 位纯数字时抛出
     */
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
     * 获取条码类型标识
     *
     * @return string 固定返回 'EAN-8'
     */
    public function getType(): string
    {
        return self::TYPE;
    }

    /**
     * 获取完整的编码数据（含校验位）
     *
     * @return string 13 位以内的 EAN-8 完整数据（8 位数字）
     */
    public function getFullData(): string
    {
        return $this->rawData;
    }

    /**
     * 获取 EAN-8 数字分段布局信息
     *
     * @return array{type:string,leftDigits:string,rightDigits:string} 左 4 位与右 4 位数字布局
     */
    public function getDigitLayout(): array
    {
        return [
            'type' => 'ean8',
            'leftDigits' => substr($this->rawData, 0, 4),
            'rightDigits' => substr($this->rawData, 4, 4),
        ];
    }
}
