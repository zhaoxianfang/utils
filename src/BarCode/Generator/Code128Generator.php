<?php

declare(strict_types=1);

namespace zxf\Utils\BarCode\Generator;

use zxf\Utils\BarCode\Exceptions\InvalidDataException;

/**
 * Code 128 条码生成器（标准实现）
 * 
 * Code 128 是高密度线性条码，支持所有128个ASCII字符
 * 
 * 【编码规则】：
 * - 每个字符由11个模块组成（条空交替）
 * - 3个条和3个空，每个1-4模块宽
 * - 总宽度恒为11模块
 * - 使用起始符、校验符、终止符
 */
class Code128Generator extends BaseGenerator
{
    protected const TYPE = 'Code 128';
    protected const START_A = 103;
    protected const START_B = 104;
    protected const START_C = 105;
    protected const STOP = 106;
    
    /** @var int 模块宽度 */
    protected const MODULE_WIDTH = 2;
    
    /** @var int 静区宽度（10模块） */
    protected const QUIET_ZONE = 20;

    /**
     * Code 128 编码表（每个值对应11个模块的条空模式）
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

    protected array $encodedValues = [];

    /**
     * 生成 Code 128 条码
     * 
     * @param string $data 要编码的数据
     * @return array<int> 条空模式数组
     */
    public function generate(string $data): array
    {
        $data = $this->sanitizeData($data);
        
        if (!$this->validate($data)) {
            throw new InvalidDataException('Code 128 数据包含无效字符');
        }

        if (strlen($data) === 0) {
            throw new InvalidDataException('Code 128 数据不能为空');
        }

        $this->rawData = $data;
        $this->barcodeArray = [];
        $this->longBarPositions = [];
        $this->encodedValues = [];

        // 使用字符集B（支持大小写字母和数字）
        $this->encodedValues[] = self::START_B;
        
        // 编码数据
        for ($i = 0; $i < strlen($data); $i++) {
            $char = $data[$i];
            $value = $this->charToValue($char);
            $this->encodedValues[] = $value;
        }
        
        // 计算校验值
        $checksum = $this->calculateCode128Checksum();
        $this->encodedValues[] = $checksum;
        
        // 添加停止符
        $this->encodedValues[] = self::STOP;

        // 转换为条/空模式
        $this->barcodeArray = $this->convertToBars();

        return $this->barcodeArray;
    }

    /**
     * 验证数据是否符合 Code 128 格式
     * 
     * @param string $data 要验证的数据
     * @return bool 数据有效返回true
     */
    public function validate(string $data): bool
    {
        if (strlen($data) === 0) {
            return false;
        }

        // Code 128 支持所有标准 ASCII (0-127)
        for ($i = 0; $i < strlen($data); $i++) {
            if (ord($data[$i]) > 127) {
                return false;
            }
        }

        return true;
    }

    /**
     * 字符转换为编码值（字符集B）
     * 
     * @param string $char 字符
     * @return int 编码值
     */
    protected function charToValue(string $char): int
    {
        $ord = ord($char);
        
        // 空格 (32) -> 0
        if ($ord === 32) {
            return 0;
        }
        
        // ! (33) 到 _ (95) -> 1-63
        if ($ord >= 33 && $ord <= 95) {
            return $ord - 32;
        }
        
        // ` (96) 到 ~ (126) -> 64-94
        if ($ord >= 96 && $ord <= 126) {
            return $ord - 32;
        }
        
        // DEL (127) -> 95
        if ($ord === 127) {
            return 95;
        }
        
        return 0;
    }

    /**
     * 计算 Code 128 校验值
     * 
     * @return int 校验值
     */
    protected function calculateCode128Checksum(): int
    {
        if (empty($this->encodedValues)) {
            return 0;
        }
        
        $sum = $this->encodedValues[0]; // 起始符
        
        for ($i = 1; $i < count($this->encodedValues); $i++) {
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
        $bars = [];
        
        // 左侧静区 (统一使用模块宽度)
        $bars[] = -(self::QUIET_ZONE / self::MODULE_WIDTH) * self::MODULE_WIDTH;
        
        foreach ($this->encodedValues as $value) {
            $pattern = $this->encoding[$value];
            
            // 解析11位编码
            for ($i = 0; $i < strlen($pattern); $i++) {
                $isBar = ($pattern[$i] === '1');
                $width = self::MODULE_WIDTH;
                
                if ($isBar) {
                    $bars[] = $width;
                } else {
                    $bars[] = -$width;
                }
            }
        }
        
        // 右侧静区 (统一使用模块宽度)
        $bars[] = -(self::QUIET_ZONE / self::MODULE_WIDTH) * self::MODULE_WIDTH;
        
        return $bars;
    }

    /**
     * 计算 Code 128 校验位
     * 
     * @return string 返回校验值
     */
    public function calculateChecksum(string $data = ''): string
    {
        return (string) $this->calculateCode128Checksum();
    }

    /**
     * 获取条码类型名称
     * 
     * @return string 返回'Code 128'
     */
    public function getType(): string
    {
        return self::TYPE;
    }
}
