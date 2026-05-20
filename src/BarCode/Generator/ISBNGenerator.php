<?php

declare(strict_types=1);

namespace zxf\Utils\BarCode\Generator;

use zxf\Utils\BarCode\Exceptions\InvalidDataException;

/**
 * ISBN 条形码生成器
 *
 * ISBN（国际标准书号）条码基于 EAN-13 标准，使用 978/979 前缀。
 * 本生成器支持 ISBN-10 和 ISBN-13，自动校验位计算与格式转换。
 */
class ISBNGenerator extends BaseGenerator
{
    protected const TYPE = 'ISBN';

    /** @var string ISBN-13 默认前缀 */
    protected const ISBN13_PREFIX = '978';

    /** @var string 当前处理的 ISBN-13 数据（13位数字） */
    protected string $isbn13Data = '';

    /** @var string|null 原始输入的 ISBN-10（如果有） */
    protected ?string $isbn10Data = null;

    /**
     * 生成 ISBN 条码
     *
     * 支持的输入格式：
     * - ISBN-13：13位数字（自动验证/计算校验位）或12位数字（自动补校验位）
     * - ISBN-10：10位字符（最后一位可以是X）或9位数字（自动补校验位）
     * - 带连字符的 ISBN（自动去除连字符和空格）
     *
     * @param string $data 要编码的 ISBN 数据
     * @return array<int> 条空模式数组（EAN-13 格式）
     * @throws InvalidDataException 数据格式错误时抛出
     */
    public function generate(string $data): array
    {
        $data = $this->sanitizeData($data);
        $cleaned = $this->cleanInput($data);

        if ($this->isIsbn10Format($cleaned)) {
            $this->isbn10Data = $this->normalizeIsbn10($cleaned);
            $this->isbn13Data = $this->convertIsbn10To13($this->isbn10Data);
        } elseif ($this->isIsbn13Format($cleaned)) {
            $this->isbn13Data = $this->normalizeIsbn13($cleaned);
            $this->isbn10Data = null;
        } else {
            throw new InvalidDataException('无效的 ISBN 格式。请输入 9-10 位 ISBN-10 或 12-13 位 ISBN-13。');
        }

        $this->rawData = $this->isbn13Data;

        // ISBN 条码本质上是 EAN-13，使用内部 EAN-13 引擎生成条空模式
        $ean13 = new EAN13Generator();
        $this->barcodeArray = $ean13->generate($this->isbn13Data);
        $this->longBarPositions = $ean13->getLongBarPositions();

        return $this->barcodeArray;
    }

    /**
     * 清理输入：去除连字符、空格和其他分隔符
     */
    protected function cleanInput(string $data): string
    {
        return strtoupper(preg_replace('/[^0-9X]/', '', $data) ?? '');
    }

    /**
     * 判断是否为 ISBN-10 格式（9位数字+可选校验位）
     */
    protected function isIsbn10Format(string $cleaned): bool
    {
        if (strlen($cleaned) === 10) {
            return ctype_digit(substr($cleaned, 0, 9)) && (ctype_digit($cleaned[9]) || $cleaned[9] === 'X');
        }
        if (strlen($cleaned) === 9) {
            return ctype_digit($cleaned);
        }
        return false;
    }

    /**
     * 判断是否为 ISBN-13 格式（12位数字+可选校验位）
     */
    protected function isIsbn13Format(string $cleaned): bool
    {
        if (strlen($cleaned) === 13 || strlen($cleaned) === 12) {
            return ctype_digit($cleaned);
        }
        return false;
    }

    /**
     * 规范化 ISBN-10：9位数据自动计算并补全校验位
     */
    protected function normalizeIsbn10(string $cleaned): string
    {
        if (strlen($cleaned) === 9) {
            return $cleaned . $this->calculateIsbn10Checksum($cleaned);
        }

        // 验证已有校验位
        $base = substr($cleaned, 0, 9);
        $expected = $this->calculateIsbn10Checksum($base);
        if ($cleaned[9] !== $expected) {
            throw new InvalidDataException("ISBN-10 校验位错误。期望: {$expected}，实际: {$cleaned[9]}");
        }

        return $cleaned;
    }

    /**
     * 规范化 ISBN-13：12位数据自动计算并补全校验位
     */
    protected function normalizeIsbn13(string $cleaned): string
    {
        if (strlen($cleaned) === 12) {
            return $cleaned . $this->calculateIsbn13Checksum($cleaned);
        }

        // 验证已有校验位
        $base = substr($cleaned, 0, 12);
        $expected = $this->calculateIsbn13Checksum($base);
        if ($cleaned[12] !== $expected) {
            throw new InvalidDataException("ISBN-13 校验位错误。期望: {$expected}，实际: {$cleaned[12]}");
        }

        return $cleaned;
    }

    /**
     * 计算 ISBN-10 校验位（模 11）
     *
     * @param string $data 前 9 位数字
     * @return string 校验位（0-9 或 X）
     */
    public function calculateIsbn10Checksum(string $data): string
    {
        $data = $this->cleanInput($data);
        if (strlen($data) < 9 || !ctype_digit(substr($data, 0, 9))) {
            throw new InvalidDataException('ISBN-10 校验位计算需要至少 9 位纯数字');
        }

        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $data[$i] * (10 - $i);
        }

        $remainder = $sum % 11;
        $checksum = 11 - $remainder;

        if ($checksum === 11) {
            return '0';
        }
        if ($checksum === 10) {
            return 'X';
        }

        return (string) $checksum;
    }

    /**
     * 计算 ISBN-13 校验位（EAN-13 模 10）
     *
     * @param string $data 前 12 位数字
     * @return string 校验位数字
     */
    public function calculateIsbn13Checksum(string $data): string
    {
        $data = $this->cleanInput($data);
        if (strlen($data) < 12 || !ctype_digit(substr($data, 0, 12))) {
            throw new InvalidDataException('ISBN-13 校验位计算需要至少 12 位纯数字');
        }

        $base = substr($data, 0, 12);
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $digit = (int) $base[$i];
            $sum += $digit * (($i % 2 === 0) ? 1 : 3);
        }

        $checksum = (10 - ($sum % 10)) % 10;
        return (string) $checksum;
    }

    /**
     * 将 ISBN-10 转换为 ISBN-13
     *
     * @param string $isbn10 完整的 10 位 ISBN（含校验位）
     * @return string 13 位 ISBN-13（含校验位）
     */
    public function convertIsbn10To13(string $isbn10): string
    {
        $isbn10 = $this->cleanInput($isbn10);
        if (strlen($isbn10) !== 10) {
            throw new InvalidDataException('ISBN-10 转 ISBN-13 需要完整的 10 位 ISBN');
        }

        $base9 = substr($isbn10, 0, 9);
        $isbn12 = self::ISBN13_PREFIX . $base9;

        return $isbn12 . $this->calculateIsbn13Checksum($isbn12);
    }

    /**
     * 将 ISBN-13 转换为 ISBN-10（仅 978 前缀支持）
     *
     * @param string $isbn13 完整的 13 位 ISBN-13（含校验位）
     * @return string 10 位 ISBN-10（含校验位）
     */
    public function convertIsbn13To10(string $isbn13): string
    {
        $isbn13 = $this->cleanInput($isbn13);
        if (strlen($isbn13) !== 13) {
            throw new InvalidDataException('ISBN-13 转 ISBN-10 需要完整的 13 位 ISBN');
        }

        if (!str_starts_with($isbn13, '978')) {
            throw new InvalidDataException('只有 978 前缀的 ISBN-13 可以转换为 ISBN-10');
        }

        $base9 = substr($isbn13, 3, 9);
        return $base9 . $this->calculateIsbn10Checksum($base9);
    }

    /**
     * 格式化 ISBN（带连字符）
     *
     * 注意：ISBN 的连字符分组规则因出版国家/地区而异。
     * 本方法提供通用的默认格式化，适用于大多数情况。
     *
     * @param string $isbn 要格式化的 ISBN（10 或 13 位）
     * @return string 格式化后的 ISBN
     */
    public function formatIsbn(string $isbn): string
    {
        $cleaned = $this->cleanInput($isbn);

        if (strlen($cleaned) === 10) {
            // ISBN-10: X-XXX-XXXXX-X 或 XX-XXX-XXXX-X 等
            return $this->formatIsbn10Heuristic($cleaned);
        }

        if (strlen($cleaned) === 13) {
            // ISBN-13: 978/979-X-XXX-XXXXX-X
            $prefix = substr($cleaned, 0, 3);
            $group = $cleaned[3];
            $publisher = substr($cleaned, 4, 3);
            $title = substr($cleaned, 7, 5);
            $check = $cleaned[12];
            return "{$prefix}-{$group}-{$publisher}-{$title}-{$check}";
        }

        return $cleaned;
    }

    /**
     * ISBN-10 启发式格式化
     */
    protected function formatIsbn10Heuristic(string $isbn10): string
    {
        $group = $isbn10[0];
        $publisher = substr($isbn10, 1, 3);
        $title = substr($isbn10, 4, 5);
        $check = $isbn10[9];
        return "{$group}-{$publisher}-{$title}-{$check}";
    }

    public function validate(string $data): bool
    {
        $cleaned = $this->cleanInput($data);
        return $this->isIsbn10Format($cleaned) || $this->isIsbn13Format($cleaned);
    }

    public function calculateChecksum(string $data): string
    {
        $cleaned = $this->cleanInput($data);

        if ($this->isIsbn10Format($cleaned)) {
            $base = substr($cleaned, 0, 9);
            return $this->calculateIsbn10Checksum($base);
        }

        if ($this->isIsbn13Format($cleaned)) {
            $base = substr($cleaned, 0, 12);
            return $this->calculateIsbn13Checksum($base);
        }

        throw new InvalidDataException('无法为无效的 ISBN 格式计算校验位');
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    /**
     * 获取完整数据（总是返回 ISBN-13，因为条码编码使用的是 ISBN-13）
     */
    public function getFullData(): string
    {
        return $this->isbn13Data;
    }

    /**
     * 获取 ISBN-13 格式数据
     */
    public function getIsbn13(): string
    {
        return $this->isbn13Data;
    }

    /**
     * 获取 ISBN-10 格式数据（如果原始输入是 ISBN-10）
     */
    public function getIsbn10(): ?string
    {
        return $this->isbn10Data;
    }

    /**
     * 获取 ISBN 条码的数字布局信息
     */
    public function getDigitLayout(): array
    {
        return [
            'type' => 'isbn',
            'isbn13' => $this->isbn13Data,
            'isbn10' => $this->isbn10Data,
            'prefix' => substr($this->isbn13Data, 0, 3),
            'group' => substr($this->isbn13Data, 3, 1),
            'publisher' => substr($this->isbn13Data, 4, 3),
            'title' => substr($this->isbn13Data, 7, 5),
            'check' => $this->isbn13Data[12] ?? '',
        ];
    }
}
