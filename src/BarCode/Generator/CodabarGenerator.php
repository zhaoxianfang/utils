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
 */
class CodabarGenerator extends BaseGenerator
{
    protected const TYPE = 'Codabar';
    protected const DATA_CHARSET = '0123456789-$:/.+';
    protected const START_STOP_CHARSET = 'ABCDabcd';
    protected const NARROW_WIDTH = 2;
    protected const WIDE_WIDTH = 4;
    protected const INTER_CHAR_GAP = 2;
    protected const QUIET_ZONE = 20;

    /**
     * ANSI/AIM BC3-1995 标准 Codabar 编码表
     * 每个字符由 7 个元素组成（4 条 + 3 空），其中 2 个宽元素、5 个窄元素
     * 数组格式：[条宽, 空宽, 条宽, 空宽, 条宽, 空宽, 条宽]，1=窄(1模块), 2=宽(2模块)
     */
    protected array $encoding = [
        '0' => [1,1,1,1,1,2,2], '1' => [1,1,1,2,1,2,1], '2' => [1,1,2,1,1,2,1],
        '3' => [2,2,1,1,1,1,1], '4' => [1,1,2,1,2,1,1], '5' => [2,1,1,1,1,2,1],
        '6' => [1,2,1,1,1,1,2], '7' => [1,2,1,2,1,1,1], '8' => [1,2,2,1,1,1,1],
        '9' => [2,1,1,2,1,1,1], '-' => [1,1,1,2,2,1,1], '$' => [1,1,2,2,1,1,1],
        ':' => [2,1,1,1,2,1,2], '/' => [2,1,2,1,1,1,2], '.' => [2,1,2,1,2,1,1],
        '+' => [1,1,2,1,2,1,2], 'A' => [1,1,2,2,1,2,1], 'B' => [1,2,1,2,1,1,2],
        'C' => [1,1,1,2,1,2,2], 'D' => [1,1,1,2,2,2,1],
    ];

    protected string $startChar = 'A';
    protected string $stopChar = 'B';

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

        $this->barcodeArray[] = -(self::QUIET_ZONE / self::NARROW_WIDTH);
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
        $this->barcodeArray[] = -(self::QUIET_ZONE / self::NARROW_WIDTH);

        return $this->barcodeArray;
    }

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

    public function calculateChecksum(string $data): string
    {
        return '';
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    public function getFullData(): string
    {
        return $this->startChar . $this->rawData . $this->stopChar;
    }
}
