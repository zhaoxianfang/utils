<?php

declare(strict_types=1);

namespace zxf\Utils\BarCode\Generator;

use zxf\Utils\BarCode\Exceptions\InvalidDataException;

/**
 * Code 128 条码生成器（支持自动字符集 A/B/C 切换）
 *
 * Code 128 是高密度线性条码，支持所有 128 个 ASCII 字符。
 * 本实现支持：
 * - 字符集 A：ASCII 0-31 控制字符 + 32-95 可打印字符
 * - 字符集 B：ASCII 32-127 可打印字符
 * - 字符集 C：数字对 00-99（高密度数字编码）
 *
 * 编码规则：
 * - 每个字符由 11 个模块组成（条空交替）
 * - 3 个条和 3 个空，每个 1-4 模块宽
 * - 总宽度恒为 11 模块
 * - 使用起始符、校验符、终止符
 */
class Code128Generator extends BaseGenerator
{
    protected const TYPE = 'Code 128';

    // 起始符
    protected const START_A = 103;
    protected const START_B = 104;
    protected const START_C = 105;

    // 切换符
    protected const CODE_A = 101;
    protected const CODE_B = 100;
    protected const CODE_C = 99;
    protected const SHIFT = 98;

    // 停止符
    protected const STOP = 106;

    /** @var int 模块宽度 */
    protected const MODULE_WIDTH = 2;

    /** @var int 静区宽度（10 模块） */
    protected const QUIET_ZONE = 20;

    /**
     * Code 128 编码表（每个值对应 11 个模块的条空模式）
     * 1=条，0=空
     *
     * @var array<string>
     */
    protected array $encoding = [
        '11011001100', '11001101100', '11001100110', '10010011000', '10010001100',
        '10001001100', '10011001000', '10011000100', '10001100100', '11001001000',
        '11001000100', '11000100100', '10110011100', '10011011100', '10011001110',
        '10111001100', '10011101100', '10011100110', '11001110010', '11001011100',
        '11001001110', '11011100100', '11001110100', '11101101110', '11101001100',
        '11100101100', '11100100110', '11101100100', '11100110100', '11100110010',
        '11011011000', '11011000110', '11000110110', '10100011000', '10001011000',
        '10001000110', '10110001000', '10001101000', '10001100010', '11010001000',
        '11000101000', '11000100010', '10110111000', '10110001110', '10001101110',
        '10111011000', '10111000110', '10001110110', '11101110110', '11010001110',
        '11000101110', '11011101000', '11011100010', '11011101110', '11101011000',
        '11101000110', '11100010110', '11101101000', '11101100010', '11100011010',
        '11101111010', '11001000010', '11110001010', '10100110000', '10100001100',
        '10010110000', '10010000110', '10000101100', '10000100110', '10110010000',
        '10110000100', '10011010000', '10011000010', '10000110100', '10000110010',
        '11000010010', '11001010000', '11110111010', '11000010100', '10001111010',
        '10100111100', '10010111100', '10010011110', '10111100100', '10011110100',
        '10011110010', '11110100100', '11110010100', '11110010010', '11011011110',
        '11011110110', '11110110110', '10101111000', '10100011110', '10001011110',
        '10111101000', '10111100010', '11110101000', '11110100010', '10111011110',
        '10111101110', '11101011110', '11110101110', '11010000100', '11010010000',
        '11010011100', '1100011101011'
    ];

    /** @var array<int> 编码后的值序列（含起始/切换/校验/停止符） */
    protected array $encodedValues = [];

    /**
     * 生成 Code 128 条码
     *
     * @param string $data 要编码的数据（支持 ASCII 0-127）
     * @return array<int> 条空模式数组
     * @throws InvalidDataException
     */
    public function generate(string $data): array
    {
        $data = $this->sanitizeData($data);

        if (strlen($data) === 0) {
            throw new InvalidDataException('Code 128 数据不能为空');
        }

        if (!$this->validate($data)) {
            throw new InvalidDataException('Code 128 数据包含无效字符（仅支持 ASCII 0-127）');
        }

        $this->rawData = $data;
        $this->barcodeArray = [];
        $this->longBarPositions = [];
        $this->encodedValues = [];

        // 自动选择最佳字符集并编码
        $this->autoEncode($data);

        // 计算校验值并追加停止符
        $checksum = $this->calculateCode128Checksum();
        $this->encodedValues[] = $checksum;
        $this->encodedValues[] = self::STOP;

        // 转换为条/空模式
        $this->barcodeArray = $this->convertToBars();

        return $this->barcodeArray;
    }

    /**
     * 验证数据是否符合 Code 128 格式
     *
     * @param string $data 要验证的数据
     * @return bool 数据有效返回 true
     */
    public function validate(string $data): bool
    {
        if (strlen($data) === 0) {
            return false;
        }

        for ($i = 0; $i < strlen($data); $i++) {
            $ord = ord($data[$i]);
            if ($ord < 0 || $ord > 127) {
                return false;
            }
        }

        return true;
    }

    /**
     * 自动字符集切换编码
     *
     * 策略：
     * 1. 根据前几个字符特性选择起始字符集（C 优先用于数字，A 用于控制字符，否则 B）
     * 2. 在 C 中时，每次尝试编码两位数字；无法继续时切换回 A/B
     * 3. 在 A/B 时，遇到 >=4 位连续数字则切换到 C
     * 4. 控制字符强制使用 A，反引号/DEL 等强制使用 B
     *
     * @param string $data 原始数据
     */
    protected function autoEncode(string $data): void
    {
        $len = strlen($data);
        $i = 0;

        $currentSet = $this->chooseStartSet($data);
        $this->encodedValues[] = match ($currentSet) {
            'A' => self::START_A,
            'C' => self::START_C,
            default => self::START_B,
        };

        while ($i < $len) {
            if ($currentSet === 'C') {
                // C 集：必须以两位数字为单位
                if ($i + 1 < $len && ctype_digit($data[$i]) && ctype_digit($data[$i + 1])) {
                    $this->encodedValues[] = (int) substr($data, $i, 2);
                    $i += 2;
                    continue;
                }

                // 无法继续留在 C，需要切换
                $newSet = $this->chooseFallbackSetForChar(ord($data[$i]));
                $this->appendSetSwitch($currentSet, $newSet);
                $currentSet = $newSet;
                continue;
            }

            // A/B 集：先检查是否有足够长的数字序列可以切 C
            $numericRun = 0;
            for ($j = $i; $j < $len && ctype_digit($data[$j]); $j++) {
                $numericRun++;
            }

            if ($numericRun >= 4) {
                $this->appendSetSwitch($currentSet, 'C');
                $currentSet = 'C';
                continue;
            }

            $ord = ord($data[$i]);

            if ($this->canEncodeInSet($ord, $currentSet)) {
                $this->encodedValues[] = $this->charToValue($data[$i], $currentSet);
                $i++;
                continue;
            }

            // 当前集无法编码，切换到合适集合并重试该字符
            $newSet = $this->chooseFallbackSetForChar($ord);
            $this->appendSetSwitch($currentSet, $newSet);
            $currentSet = $newSet;
        }
    }

    /**
     * 选择起始字符集
     */
    protected function chooseStartSet(string $data): string
    {
        $len = strlen($data);

        // 如果以控制字符开头，使用 A
        $firstOrd = ord($data[0]);
        if ($firstOrd >= 0 && $firstOrd <= 31) {
            return 'A';
        }

        // 如果前 4 个字符都是数字，优先使用 C
        $numericCount = 0;
        for ($i = 0; $i < $len && $i < 4; $i++) {
            if (ctype_digit($data[$i])) {
                $numericCount++;
            } else {
                break;
            }
        }
        if ($numericCount >= 4 && $len >= 2) {
            return 'C';
        }

        return 'B';
    }

    /**
     * 检查字符是否能在指定字符集中编码
     */
    protected function canEncodeInSet(int $ord, string $set): bool
    {
        return match ($set) {
            'A' => $ord >= 0 && $ord <= 95,
            'B' => $ord >= 32 && $ord <= 127,
            default => false,
        };
    }

    /**
     * 为无法在当前集编码的字符选择合适的字符集
     */
    protected function chooseFallbackSetForChar(int $ord): string
    {
        // 控制字符只能用 A
        if ($ord >= 0 && $ord <= 31) {
            return 'A';
        }

        // ` (96) ~ DEL (127) 只能用 B
        if ($ord >= 96 && $ord <= 127) {
            return 'B';
        }

        // 32-95 在 A 和 B 中都可用，默认回到 B（可读性更好）
        return 'B';
    }

    /**
     * 追加字符集切换符
     */
    protected function appendSetSwitch(string &$currentSet, string $newSet): void
    {
        if ($currentSet === $newSet) {
            return;
        }

        $this->encodedValues[] = match ($newSet) {
            'A' => self::CODE_A,
            'B' => self::CODE_B,
            'C' => self::CODE_C,
            default => throw new \RuntimeException('不支持的字符集切换: ' . $newSet),
        };

        $currentSet = $newSet;
    }

    /**
     * 字符转换为编码值
     *
     * @param string $char 字符
     * @param string $charset 字符集（A/B/C）
     * @return int 编码值
     */
    protected function charToValue(string $char, string $charset = 'B'): int
    {
        if ($charset === 'C') {
            // C 集直接返回两位数字值（0-99）
            return (int) $char;
        }

        $ord = ord($char);

        if ($charset === 'A') {
            // 控制字符 0-31 -> 64-95
            if ($ord >= 0 && $ord <= 31) {
                return $ord + 64;
            }
            // 可打印字符 32-95 -> 0-63
            if ($ord >= 32 && $ord <= 95) {
                return $ord - 32;
            }
        }

        // B 集（默认）
        // 32-95 -> 0-63
        if ($ord >= 32 && $ord <= 95) {
            return $ord - 32;
        }
        // 96-127 -> 64-95
        if ($ord >= 96 && $ord <= 127) {
            return $ord - 32;
        }

        return 0;
    }

    /**
     * 计算 Code 128 校验值
     *
     * 基于原始编码值（不含校验位和停止符）计算标准校验和
     *
     * @return int 校验值（0-102）
     */
    protected function calculateCode128Checksum(): int
    {
        if (empty($this->encodedValues)) {
            return 0;
        }

        $sum = $this->encodedValues[0]; // 起始符权重为 1
        $count = count($this->encodedValues);

        for ($i = 1; $i < $count; $i++) {
            $sum += $this->encodedValues[$i] * $i;
        }

        return $sum % 103;
    }

    /**
     * 将编码值转换为条/空模式
     *
     * @return array<int> 条/空模式数组
     */
    protected function convertToBars(): array
    {
        $binary = '';

        // 左侧静区
        $binary .= str_repeat('0', self::QUIET_ZONE);

        foreach ($this->encodedValues as $value) {
            $binary .= $this->encoding[$value];
        }

        // 右侧静区
        $binary .= str_repeat('0', self::QUIET_ZONE);

        return $this->binaryToBars($binary);
    }

    /**
     * 计算 Code 128 校验位
     *
     * @param string $data 留空时使用已编码的值序列
     * @return string 返回校验值
     */
    public function calculateChecksum(string $data = ''): string
    {
        if ($data !== '' && $this->validate($data)) {
            // 临时编码以计算校验值
            $originalValues = $this->encodedValues;
            $this->encodedValues = [];
            $this->autoEncode($data);
            $checksum = $this->calculateCode128Checksum();
            $this->encodedValues = $originalValues;
            return (string) $checksum;
        }

        return (string) $this->calculateCode128Checksum();
    }

    /**
     * 获取完整数据
     *
     * @return string 返回原始编码数据
     */
    public function getFullData(): string
    {
        return $this->rawData;
    }

    /**
     * 获取条码类型名称
     *
     * @return string 返回 'Code 128'
     */
    public function getType(): string
    {
        return self::TYPE;
    }
}
