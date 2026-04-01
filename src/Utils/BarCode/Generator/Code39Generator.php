<?php

declare(strict_types=1);

namespace zxf\Utils\BarCode\Generator;

use zxf\Utils\BarCode\Exceptions\InvalidDataException;

/**
 * Code 39 条码生成器（修复版）
 * 
 * Code 39 是工业标准条码，支持数字、大写字母和部分特殊符号
 * 特征：
 * - 可变长度
 * - 支持数字+大写字母+特殊符号
 * - 条空等宽（窄条/空=1单位，宽条/空=2-3单位）
 * - 自校验（每个字符包含校验信息）
 * - 工业/医疗专用
 * 
 * 编码规则：
 * - 每个字符由9个元素组成（5条+4空）
 * - 其中3个宽元素，6个窄元素
 * - 字符间用窄空分隔
 * - 起始和终止符都是'*'
 */
class Code39Generator extends BaseGenerator
{
    /** @var string 条码类型标识 */
    protected const TYPE = 'Code 39';
    
    /** @var string Code 39支持的字符集 */
    protected const CHARSET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ-. $/+%';

    /**
     * Code 39编码表
     * 每个字符由9个元素表示：n=窄条(1单位)，w=宽条(2-3单位)
     * 格式：条空条空... 共9个元素
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

    /** @var float 宽条与窄条的比例 */
    protected float $wideRatio = 2.5;
    
    /** @var int 字符间分隔空宽度（窄空单位数） */
    protected int $interCharGap = 1;
    
    /** @var bool 是否在数据中包含起始/终止符 */
    protected bool $includeDelimiters = false;

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
        
        // 验证数据
        if (!$this->validate($data)) {
            throw new InvalidDataException('Code 39 数据包含无效字符，仅支持数字、大写字母和 - . $ / + % 空格');
        }

        if (strlen($data) === 0) {
            throw new InvalidDataException('Code 39 数据不能为空');
        }

        $this->rawData = strtoupper($data);
        $this->barcodeArray = [];
        $this->longBarPositions = [];

        // 构建编码（添加起始和终止符*）
        $encodedData = '*' . $this->rawData . '*';
        
        $binary = '';
        for ($i = 0; $i < strlen($encodedData); $i++) {
            $char = $encodedData[$i];
            $pattern = $this->encoding[$char];
            
            // 将模式转换为二进制
            for ($j = 0; $j < strlen($pattern); $j++) {
                $isWide = ($pattern[$j] === 'w');
                $width = $isWide ? (int)($this->wideRatio) : 1;
                $isBar = ($j % 2 === 0);
                
                $binary .= str_repeat($isBar ? '1' : '0', $width);
            }
            
            // 字符间分隔（最后一个字符后不加）
            if ($i < strlen($encodedData) - 1) {
                $binary .= str_repeat('0', $this->interCharGap);
            }
        }

        $this->barcodeArray = $this->binaryToBars($binary);

        return $this->barcodeArray;
    }

    /**
     * 验证数据格式
     * 
     * 【验证规则】：
     * - Code 39只支持大写字母、数字和部分特殊符号
     * - 不支持小写字母
     * - 数据不能为空
     * 
     * @param string $data 要验证的数据
     * @return bool 数据有效返回true
     */
    public function validate(string $data): bool
    {
        if (strlen($data) === 0) {
            return false;
        }

        // Code 39只支持大写字母，小写字母无效
        // 不进行大小写转换，直接验证原始数据
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

    /**
     * 设置宽条比例
     * 
     * @param float $ratio 宽条与窄条的比例
     * @return self 支持链式调用
     */
    public function setWideRatio(float $ratio): self
    {
        $this->wideRatio = $ratio;
        return $this;
    }

    /**
     * 设置字符间分隔宽度
     * 
     * @param int $width 窄空单位数
     * @return self 支持链式调用
     */
    public function setInterCharGap(int $width): self
    {
        $this->interCharGap = $width;
        return $this;
    }
}
