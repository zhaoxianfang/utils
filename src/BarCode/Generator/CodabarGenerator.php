<?php

declare(strict_types=1);

namespace zxf\Utils\BarCode\Generator;

use zxf\Utils\BarCode\Exceptions\InvalidDataException;

/**
 * Codabar（NW-7）条码生成器
 *
 * Codabar 是一种离散型自校验条码，广泛应用于图书馆、血库、照片冲印等行业。
 *
 * 【编码规则】（遵循 ANSI/AIM BC3-1995 标准）：
 * - 每个字符由7个元素组成：4条 + 3空
 * - 其中2个宽元素和5个窄元素
 * - 字符间有间隔（1个窄空）
 * - 起始/终止符必须是 A, B, C, D 之一
 *
 * @package zxf\Utils\BarCode\Generator
 * @version 1.0.0
 * @license MIT
 *
 * @example
 * ```php
 * $generator = new CodabarGenerator();
 * $bars = $generator->generate('A12345B');   // 起止符 A/B 会被剥离并单独编码
 * $generator->getFullData();                 // 'A12345B'
 * ```
 */
class CodabarGenerator extends BaseGenerator
{
    protected const TYPE = 'Codabar';
    protected const DATA_CHARSET = '0123456789-$:/.+';
    protected const START_STOP_CHARSET = 'ABCDabcd';

    /** @var int 窄元素宽度（模块级：1 模块 = renderer 的 moduleSize 像素） */
    protected const NARROW_WIDTH = 1;

    /** @var int 宽元素宽度（宽:窄 = 2:1） */
    protected const WIDE_WIDTH = 2;

    /** @var int 字符间分隔空宽度（= 1 个窄宽，1 模块） */
    protected const INTER_CHAR_GAP = 1;

    /** @var int 每侧静区宽度（10 模块；renderer 还会叠加静区白边） */
    protected const QUIET_ZONE = 10;

    /**
     * ANSI/AIM BC3-1995 标准 Codabar 编码表（经 ZXing / jsBarcode 表核验）
     * 每个字符由 7 个元素组成（4 条 + 3 空），元素从条开始、条-空交替；
     * 1=窄(1模块), 2=宽(2模块)。起止 A-D 为 3 宽；数据字符中 : / . 为 3 宽、+ 为 5 宽。
     */
    protected array $encoding = [
        '0' => [1,1,1,1,1,2,2], '1' => [1,1,1,1,2,2,1], '2' => [1,1,1,2,1,1,2],
        '3' => [2,2,1,1,1,1,1], '4' => [1,1,2,1,1,2,1], '5' => [2,1,1,1,1,2,1],
        '6' => [1,2,1,1,1,1,2], '7' => [1,2,1,1,2,1,1], '8' => [1,2,2,1,1,1,1],
        '9' => [2,1,1,2,1,1,1], '-' => [1,1,1,2,2,1,1], '$' => [1,1,2,2,1,1,1],
        ':' => [2,1,1,1,2,1,2], '/' => [2,1,2,1,1,1,2], '.' => [2,1,2,1,2,1,1],
        '+' => [1,1,2,2,2,2,2], 'A' => [1,1,2,2,1,2,1], 'B' => [1,2,1,2,1,1,2],
        'C' => [1,1,1,2,1,2,2], 'D' => [1,1,1,2,2,2,1],
    ];

    protected string $startChar = 'A';
    protected string $stopChar = 'B';

    /**
     * 生成 Codabar 条码的条空序列。
     *
     * 处理流程：
     * 1. 清洗输入（sanitizeData）；
     * 2. 若首尾是合法起止符（A/B/C/D，大小写均可），剥离开来单独编码；
     * 3. 校验数据字符合法性后，按「起始符 → 字符（间隔分隔）→ 终止符」拼接，
     *    两侧各加静区。
     *
     * @param  string  $data  待编码数据，可带或不带起止符，如 'A12345B' 或 '12345'
     * @return array 条空序列：正整数表示「条」宽度，负整数表示「空」宽度（单位：模块）
     *
     * @throws InvalidDataException 数据含非法字符，或剥离起止符后数据为空时抛出
     *
     * @example
     * ```php
     * $bars = (new CodabarGenerator())->generate('A12345B');
     * // [-10, 1,1,-1, ...]  首个 -10 为左侧静区
     * ```
     */
    public function generate(string $data): array
    {
        $data = $this->sanitizeData($data);

        $firstChar = strtoupper($data[0] ?? '');
        $lastChar = strtoupper($data[-1] ?? '');

        if (str_contains(self::START_STOP_CHARSET, $firstChar) &&
            str_contains(self::START_STOP_CHARSET, $lastChar) && strlen($data) >= 2) {
            $this->startChar = strtoupper($firstChar);
            $this->stopChar = strtoupper($lastChar);
            $data = substr($data, 1, -1);
        }

        if (!$this->validate($data)) {
            throw new InvalidDataException('Codabar 数据包含无效字符，仅支持数字和 - $ : / . +');
        }

        if (strlen($data) === 0) {
            throw new InvalidDataException('Codabar 数据不能为空');
        }

        $this->rawData = $data;
        $this->barcodeArray = [];
        $this->longBarPositions = [];

        $this->barcodeArray[] = -self::QUIET_ZONE;
        $this->encodeCharacter($this->startChar);
        $this->barcodeArray[] = -(self::INTER_CHAR_GAP);

        for ($i = 0; $i < strlen($this->rawData); $i++) {
            $this->encodeCharacter($this->rawData[$i]);
            if ($i < strlen($this->rawData) - 1) {
                $this->barcodeArray[] = -(self::INTER_CHAR_GAP);
            }
        }

        $this->barcodeArray[] = -(self::INTER_CHAR_GAP);
        $this->encodeCharacter($this->stopChar);
        $this->barcodeArray[] = -self::QUIET_ZONE;

        return $this->barcodeArray;
    }

    /**
     * 将单个字符编码为条空序列，追加到 barcodeArray。
     *
     * 编码表每个字符 7 个元素，索引偶数位为「条」、奇数位为「空」；
     * 值 2 表示宽元素（2 模块），其余为窄元素（1 模块）。
     * 未知字符会被静默跳过（调用方已通过 validate() 保证合法性）。
     *
     * @param  string  $char  单个字符（自动转大写）
     * @return void
     */
    protected function encodeCharacter(string $char): void
    {
        $char = strtoupper($char);
        if (!isset($this->encoding[$char])) {
            return;
        }

        $pattern = $this->encoding[$char];
        for ($i = 0; $i < 7; $i++) {
            // 1=窄(1模块), 2=宽(2模块)；位置偶数为条，奇数为空
            $width = ($pattern[$i] === 2) ? self::WIDE_WIDTH : self::NARROW_WIDTH;
            $isBar = ($i % 2 === 0);
            $this->barcodeArray[] = $isBar ? $width : -$width;
        }
    }

    /**
     * 校验数据是否可编码为 Codabar。
     *
     * 会自动剥离首尾的合法起止符后再校验数据部分，
     * 因此 'A12345B' 与 '12345' 都视为合法。
     *
     * @param  string  $data  待校验数据
     * @return bool 合法返回 true；空数据或含非法字符返回 false
     *
     * @example
     * ```php
     * (new CodabarGenerator())->validate('A12345B'); // true
     * (new CodabarGenerator())->validate('12X45');   // false（X 非法）
     * ```
     */
    public function validate(string $data): bool
    {
        if (strlen($data) === 0) {
            return false;
        }

        $firstChar = strtoupper($data[0] ?? '');
        $lastChar = strtoupper($data[-1] ?? '');
        if (str_contains(self::START_STOP_CHARSET, $firstChar) &&
            str_contains(self::START_STOP_CHARSET, $lastChar) && strlen($data) >= 2) {
            $data = substr($data, 1, -1);
        }

        if (strlen($data) === 0) {
            return false;
        }

        return $this->containsOnly($data, self::DATA_CHARSET);
    }

    /**
     * 计算校验位。
     *
     * Codabar 为自校验码（字符本身含校验信息），无需额外校验位，故恒返回空字符串。
     * 实现此方法仅为满足生成器接口约定。
     *
     * @param  string  $data  数据（未使用）
     * @return string 恒为空字符串
     */
    public function calculateChecksum(string $data): string
    {
        return '';
    }

    /**
     * 获取条码类型标识。
     *
     * @return string 固定为 'Codabar'
     */
    public function getType(): string
    {
        return self::TYPE;
    }

    /**
     * 获取含起止符的完整数据。
     *
     * @return string 形如 'A12345B'（起始符 + 数据 + 终止符）
     *
     * @example
     * ```php
     * $g = new CodabarGenerator();
     * $g->generate('12345');
     * $g->getFullData(); // 'A12345B'（使用默认起止符 A/B）
     * ```
     */
    public function getFullData(): string
    {
        return $this->startChar . $this->rawData . $this->stopChar;
    }
}
