<?php

declare(strict_types=1);

namespace zxf\Utils\BarCode\Generator;

use zxf\Utils\BarCode\Exceptions\InvalidDataException;

/**
 * Code 39 条码生成器（标准 AIM USS 实现）
 * 
 * Code 39 是工业标准条码，支持数字、大写字母和部分特殊符号
 * 
 * 【编码规则】（严格遵循AIM USS标准）：
 * - 每个字符由9个元素组成（5条+4空）
 * - 其中3个宽元素，6个窄元素
 * - 窄条/空 = 1模块
 * - 宽条/空 = 3模块（标准比例 1:3）
 * - 字符间用窄空分隔（1模块）
 * - 起始和终止符都是'*'
 */
class Code39Generator extends BaseGenerator
{
    /** @var string 条码类型标识 */
    protected const TYPE = 'Code 39';
    
    /** @var string Code 39支持的字符集 */
    protected const CHARSET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ-. $/+%';

    /** @var int 窄元素宽度（模块级：1 模块 = renderer 的 moduleSize 像素） */
    protected const NARROW_WIDTH = 1;
    
    /** @var int 宽元素宽度（宽:窄 = 3:1，AIM USS 规范） */
    protected const WIDE_WIDTH = 3;
    
    /** @var int 字符间分隔空宽度（= 1 个窄宽，1 模块） */
    protected const INTER_CHAR_GAP = 1;
    
    /** @var int 每侧静区宽度（10 个窄宽，标准要求 >=10x） */
    protected const QUIET_ZONE = 10;

    /**
     * Code 39标准编码表（AIM USS规范）
     * 每个字符由9个元素表示：n=窄条(1模块)，w=宽条(3模块)
     * 格式：条-空-条-空-条-空-条-空-条
     * 每个字符必须有3个宽元素和6个窄元素
     * 
     * @var array<string, string>
     */
    protected array $encoding = [
        '0' => 'nnnwwnwnn', '1' => 'wnnwnnnnw', '2' => 'nnwwnnnnw',
        '3' => 'wnwwnnnnn', '4' => 'nnnwwnnnw', '5' => 'wnnwwnnnn',
        '6' => 'nnwwwnnnn', '7' => 'nnnwnnwnw', '8' => 'wnnwnnwnn',
        '9' => 'nnwwnnwnn', 'A' => 'wnnnnwnnw', 'B' => 'nnwnnwnnw',
        'C' => 'wnwnnwnnn', 'D' => 'nnnnwwnnw', 'E' => 'wnnnwwnnn',
        'F' => 'nnwnwwnnn', 'G' => 'nnnnnwwnw', 'H' => 'wnnnnwwnn',
        'I' => 'nnwnnwwnn', 'J' => 'nnnnwwwnn', 'K' => 'wnnnnnnww',
        'L' => 'nnwnnnnww', 'M' => 'wnwnnnnwn', 'N' => 'nnnnwnnww',
        'O' => 'wnnnwnnwn', 'P' => 'nnwnwnnwn', 'Q' => 'nnnnnnwww',
        'R' => 'wnnnnnwwn', 'S' => 'nnwnnnwwn', 'T' => 'nnnnwnwwn',
        'U' => 'wwnnnnnnw', 'V' => 'nwwnnnnnw', 'W' => 'wwwnnnnnn',
        'X' => 'nwnnwnnnw', 'Y' => 'wwnnwnnnn', 'Z' => 'nwwnwnnnn',
        '-' => 'nwnnnnwnw', '.' => 'wwnnnnwnn', ' ' => 'nwwnnnwnn',
        '$' => 'nwnwnwnnn', '/' => 'nwnwnnnwn', '+' => 'nwnnnwnwn',
        '%' => 'nnnwnwnwn', '*' => 'nwnnwnwnn',
    ];

    /**
     * 生成 Code 39 条码
     * 
     * @param string $data 要编码的数据
     * @return array<int> 条空模式数组
     * @throws InvalidDataException 数据格式错误时抛出
     */
    public function generate(string $data): array
    {
        $data = $this->sanitizeData($data);
        
        // 统一大写：Code 39 规范字符集仅含大写字母，小写输入自动转大写
        $this->rawData = strtoupper($data);

        if (strlen($this->rawData) === 0) {
            throw new InvalidDataException('Code 39 数据不能为空');
        }

        // 验证数据
        if (!$this->validate($this->rawData)) {
            throw new InvalidDataException('Code 39 数据包含无效字符，仅支持数字、大写字母和 - . $ / + % 空格（小写字母已自动转大写）');
        }
        $this->barcodeArray = [];
        $this->longBarPositions = [];

        // 左侧静区（10 模块，渲染时按 moduleSize 放大）
        $this->barcodeArray[] = -self::QUIET_ZONE;

        // 起始符 '*'（其后同样需要 1 模块字符间隔）
        $this->encodeCharacter('*');
        $this->barcodeArray[] = -self::INTER_CHAR_GAP;

        // 数据字符
        for ($i = 0; $i < strlen($this->rawData); $i++) {
            $char = $this->rawData[$i];
            $this->encodeCharacter($char);

            // 字符间分隔（1 模块窄空；最后一个数据字符后的间隔由终止星前的分隔接管）
            if ($i < strlen($this->rawData) - 1) {
                $this->barcodeArray[] = -self::INTER_CHAR_GAP;
            }
        }

        // 终止符前分隔
        $this->barcodeArray[] = -self::INTER_CHAR_GAP;

        // 终止符 '*'
        $this->encodeCharacter('*');

        // 右侧静区（10 模块）
        $this->barcodeArray[] = -self::QUIET_ZONE;

        return $this->barcodeArray;
    }

    /**
     * 编码单个字符
     * 
     * @param string $char 要编码的字符
     */
    protected function encodeCharacter(string $char): void
    {
        if (!isset($this->encoding[$char])) {
            return;
        }

        $pattern = $this->encoding[$char];
        
        // 解析9位编码（5条4空）
        // 位置：0=条, 1=空, 2=条, 3=空, 4=条, 5=空, 6=条, 7=空, 8=条
        for ($i = 0; $i < 9; $i++) {
            $isWide = ($pattern[$i] === 'w');
            $width = $isWide ? self::WIDE_WIDTH : self::NARROW_WIDTH;
            $isBar = ($i % 2 === 0);
            
            if ($isBar) {
                $this->barcodeArray[] = $width;
            } else {
                $this->barcodeArray[] = -$width;
            }
        }
    }

    /**
     * 验证数据格式
     * 
     * @param string $data 要验证的数据
     * @return bool 数据有效返回true
     */
    public function validate(string $data): bool
    {
        if (strlen($data) === 0) {
            return false;
        }

        return $this->containsOnly($data, self::CHARSET);
    }

    /**
     * 计算Code 39校验位（模43校验）
     * 
     * @param string $data 要计算的数据
     * @return string 校验字符
     */
    public function calculateChecksum(string $data): string
    {
        $data = strtoupper($this->sanitizeData($data));
        $sum = 0;
        
        $charsetArray = str_split(self::CHARSET);
        
        for ($i = 0; $i < strlen($data); $i++) {
            $pos = array_search($data[$i], $charsetArray);
            if ($pos !== false) {
                $sum += $pos;
            }
        }
        
        $checksumIndex = $sum % 43;
        return $charsetArray[$checksumIndex];
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    /**
     * 获取完整数据（不包含起始/终止符）
     * 
     * @return string 原始数据
     */
    public function getFullData(): string
    {
        return $this->rawData;
    }

    /**
     * 获取带分隔符的完整数据（用于显示）
     * 
     * @return string 带*的完整数据
     */
    public function getDataWithDelimiters(): string
    {
        return '*' . $this->rawData . '*';
    }
}
