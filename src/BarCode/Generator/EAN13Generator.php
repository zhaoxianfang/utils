<?php

declare(strict_types=1);

namespace zxf\Utils\BarCode\Generator;

use zxf\Utils\BarCode\Exceptions\InvalidDataException;

/**
 * EAN-13 商品条码生成器
 *
 * EAN-13 是国际通用的商品条码，共 13 位数字：首位决定左侧 6 位的奇偶（A/B）编码组合，
 * 中间由守护码分隔，右侧 6 位统一使用 C 编码，最后一位为校验位。
 *
 * 本生成器同时接受 12 位（自动补算校验位）与 13 位（默认校验校验位是否正确）输入。
 *
 * @package zxf\Utils\BarCode\Generator
 * @version 1.0.0
 * @license MIT
 *
 * @example
 * ```php
 * $generator = new EAN13Generator();
 * $bars = $generator->generate('690102808929');  // 12 位：自动补校验位
 * $generator->getFullData();                     // '6901028089296'（13 位含校验位）
 * ```
 */
class EAN13Generator extends BaseGenerator
{
    protected const TYPE = 'EAN-13';
    protected const START_GUARD = '101';
    protected const MIDDLE_GUARD = '01010';
    protected const END_GUARD = '101';

    /**
     * 首位数字 → 左侧 6 位所使用的编码集组合（A/B 奇偶模式）。
     *
     * 这是 EAN-13 的核心：首位数字本身不单独编码，而是由这 10 种奇偶组合隐含表达。
     */
    protected array $parityPattern = [
        ['A','A','A','A','A','A'],['A','A','B','A','B','B'],
        ['A','A','B','B','A','B'],['A','A','B','B','B','A'],
        ['A','B','A','A','B','B'],['A','B','B','A','A','B'],
        ['A','B','B','B','A','A'],['A','B','A','B','A','B'],
        ['A','B','A','B','B','A'],['A','B','B','A','B','A'],
    ];

    /** A 编码集（左侧奇编码），索引即数字 0-9 */
    protected array $encodingA = [
        '0001101','0011001','0010011','0111101','0100011',
        '0110001','0101111','0111011','0110111','0001011',
    ];

    /** B 编码集（左侧偶编码），索引即数字 0-9 */
    protected array $encodingB = [
        '0100111','0110011','0011011','0100001','0011101',
        '0111001','0000101','0010001','0001001','0010111',
    ];

    /** C 编码集（右侧统一使用），索引即数字 0-9 */
    protected array $encodingC = [
        '1110010','1100110','1101100','1000010','1011100',
        '1001110','1010000','1000100','1001000','1110100',
    ];

    /** 最近一次生成的完整 13 位数据（含校验位） */
    protected string $currentData = '';

    /**
     * 生成 EAN-13 条码的条空序列。
     *
     * 处理流程：
     * 1. 清洗并校验输入（12 位或 13 位纯数字）；
     * 2. 12 位时自动计算并补上校验位；13 位时默认校验校验位正确性
     *    （可用 skipChecksum(true) 跳过，用于生成「故意错误校验位」的测试码）；
     * 3. 按「起始守护码 + 左侧 6 位（按奇偶模式选 A/B）+ 中间守护码
     *    + 右侧 6 位（C）+ 结束守护码」拼成二进位串，再转为条空数组；
     * 4. 记录守护长条位置，供渲染器加长显示。
     *
     * @param  string  $data  12 位或 13 位纯数字
     * @return array 条空序列：正整数为「条」宽度，负整数为「空」宽度（单位：模块）
     *
     * @throws InvalidDataException 位数不符、含非数字，或 13 位输入校验位错误时抛出
     *
     * @example
     * ```php
     * $bars = (new EAN13Generator())->generate('690102808929');
     * ```
     */
    public function generate(string $data): array
    {
        $data = $this->sanitizeData($data);
        if (!$this->validate($data)) {
            throw new InvalidDataException('EAN-13 数据必须是12或13位纯数字，当前数据: ' . $data);
        }

        if (strlen($data) === 13) {
            // 输入已含校验位：默认验证正确性（skipChecksum(true) 可跳过），
            // 校验位错误时抛异常，避免生成被校验型扫码器拒绝的坏码
            $this->assertCompleteChecksum($data, 'EAN-13');
            $this->rawData = $data;
            $this->currentData = $data;
        } else {
            $checksum = $this->calculateChecksum($data);
            $this->rawData = $data . $checksum;
            $this->currentData = $this->rawData;
        }

        $this->barcodeArray = [];
        $this->longBarPositions = [];

        $firstDigit = (int) $this->rawData[0];
        $leftData = substr($this->rawData, 1, 6);
        $rightData = substr($this->rawData, 7, 6);

        $seq = self::START_GUARD;
        $parity = $this->parityPattern[$firstDigit];
        for ($i = 0; $i < 6; $i++) {
            $digit = (int) $leftData[$i];
            $seq .= $parity[$i] === 'A' ? $this->encodingA[$digit] : $this->encodingB[$digit];
        }
        $seq .= self::MIDDLE_GUARD;
        for ($i = 0; $i < 6; $i++) {
            $digit = (int) $rightData[$i];
            $seq .= $this->encodingC[$digit];
        }
        $seq .= self::END_GUARD;

        $this->barcodeArray = $this->binaryToBars($seq);

        $this->longBarPositions = [
            $this->binaryOffsetToBarIndex($seq, 0),
            $this->binaryOffsetToBarIndex($seq, 2),
            $this->binaryOffsetToBarIndex($seq, 46),
            $this->binaryOffsetToBarIndex($seq, 48),
            $this->binaryOffsetToBarIndex($seq, 92),
            $this->binaryOffsetToBarIndex($seq, 94),
        ];
        sort($this->longBarPositions);

        return $this->barcodeArray;
    }

    /**
     * 校验数据是否符合 EAN-13 要求。
     *
     * @param  string  $data  待校验数据（内部先做清洗）
     * @return bool 长度为 12 或 13 且均为数字时返回 true
     *
     * @example
     * ```php
     * (new EAN13Generator())->validate('690102808929');  // true（12 位）
     * (new EAN13Generator())->validate('6901028089296'); // true（13 位）
     * (new EAN13Generator())->validate('69010280');      // false（位数不符）
     * ```
     */
    public function validate(string $data): bool
    {
        $data = $this->sanitizeData($data);
        if (strlen($data) !== 12 && strlen($data) !== 13) {
            return false;
        }
        return $this->isNumeric($data);
    }

    /**
     * 计算 EAN-13 校验位（第 13 位）。
     *
     * 算法：从右往左，奇数位（第 1、3、5...位，从右数）乘 3、偶数位乘 1，求和后
     * 取 (10 - sum % 10) % 10 即为校验位。
     *
     * @param  string  $data  至少 12 位纯数字；超过 12 位时只取前 12 位计算
     * @return string 一位数字字符串（0-9）
     *
     * @throws InvalidDataException 不足 12 位或前 12 位含非数字时抛出
     *
     * @example
     * ```php
     * (new EAN13Generator())->calculateChecksum('690102808929'); // '6'
     * ```
     */
    public function calculateChecksum(string $data): string
    {
        $data = $this->sanitizeData($data);
        if (strlen($data) < 12 || !$this->isNumeric(substr($data, 0, 12))) {
            throw new InvalidDataException('计算校验位需要至少12位纯数字');
        }
        $baseData = substr($data, 0, 12);
        $sum = 0;
        $length = strlen($baseData);
        for ($i = 0; $i < $length; $i++) {
            $digit = (int) $baseData[$length - 1 - $i];
            $position = $i + 1;
            $multiplier = ($position % 2 === 1) ? 3 : 1;
            $sum += $digit * $multiplier;
        }
        $checksum = (10 - ($sum % 10)) % 10;
        return (string) $checksum;
    }

    /**
     * 获取条码类型标识。
     *
     * @return string 固定为 'EAN-13'
     */
    public function getType(): string
    {
        return self::TYPE;
    }

    /**
     * 获取最近一次生成的完整数据（13 位，含校验位）。
     *
     * @return string 完整的 13 位数据；未生成过时为空字符串
     *
     * @example
     * ```php
     * $g = new EAN13Generator();
     * $g->generate('690102808929');
     * $g->getFullData(); // '6901028089296'
     * ```
     */
    public function getFullData(): string
    {
        return $this->currentData;
    }

    /**
     * 获取数字的分区布局，便于渲染器分段绘制（左侧常与首位分开排版）。
     *
     * @return array 结构：[
     *     'type'        => 'ean13',
     *     'firstDigit'  => 首位数字,
     *     'leftDigits'  => 左侧 6 位,
     *     'rightDigits' => 右侧 6 位,
     *     'fullData'    => 完整 13 位,
     * ]
     *
     * @example
     * ```php
     * (new EAN13Generator())->generate('690102808929');
     * // getDigitLayout()['firstDigit']  === '6'
     * // getDigitLayout()['leftDigits']  === '901028'
     * // getDigitLayout()['rightDigits'] === '089296'
     * ```
     */
    public function getDigitLayout(): array
    {
        return [
            'type' => 'ean13',
            'firstDigit' => $this->currentData[0] ?? '',
            'leftDigits' => substr($this->currentData, 1, 6),
            'rightDigits' => substr($this->currentData, 7, 6),
            'fullData' => $this->currentData,
        ];
    }
}
