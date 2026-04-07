<?php

declare(strict_types=1);

namespace zxf\Utils\BarCode\Generator;

use zxf\Utils\BarCode\Exceptions\InvalidDataException;

/**
 * EAN-13 条码生成器（严格GS1标准实现）
 * 
 * EAN-13（欧洲商品编号）是13位纯数字条形码
 * 严格遵循GS1标准编码规范
 * 
 * 【编码结构】（共95个模块，不含静区）：
 * - 左侧静区：11个模块（空白）
 * - 起始保护符：3模块（101）
 * - 左侧6位数据：42模块（7模块×6位）
 * - 中间分隔符：5模块（01010）
 * - 右侧6位数据：42模块（7模块×6位）
 * - 终止保护符：3模块（101）
 * - 右侧静区：11个模块（空白）
 */
class EAN13Generator extends BaseGenerator
{
    protected const TYPE = 'EAN-13';
    
    /** @var string 起始保护符编码 */
    protected const START_GUARD = '101';
    
    /** @var string 中间分隔符编码 */
    protected const MIDDLE_GUARD = '01010';
    
    /** @var string 终止保护符编码 */
    protected const END_GUARD = '101';

    /** @var int 模块宽度 */
    protected const MODULE_WIDTH = 2;
    
    /** @var int 静区模块数 */
    protected const QUIET_ZONE_MODULES = 11;

    /**
     * 左侧数字奇偶性模式表
     * 根据第一位数字确定左侧6位使用A模式还是B模式
     * 
     * @var array<array<string>>
     */
    protected array $parityPattern = [
        ['A', 'A', 'A', 'A', 'A', 'A'],  // 0
        ['A', 'A', 'B', 'A', 'B', 'B'],  // 1
        ['A', 'A', 'B', 'B', 'A', 'B'],  // 2
        ['A', 'A', 'B', 'B', 'B', 'A'],  // 3
        ['A', 'B', 'A', 'A', 'B', 'B'],  // 4
        ['A', 'B', 'B', 'A', 'A', 'B'],  // 5
        ['A', 'B', 'B', 'B', 'A', 'A'],  // 6
        ['A', 'B', 'A', 'B', 'A', 'B'],  // 7
        ['A', 'B', 'A', 'B', 'B', 'A'],  // 8
        ['A', 'B', 'B', 'A', 'B', 'A'],  // 9
    ];

    /**
     * A模式编码表（奇校验）
     * 用于左侧数据
     * 
     * @var array<string>
     */
    protected array $encodingA = [
        '0001101', '0011001', '0010011', '0111101', '0100011',
        '0110001', '0101111', '0111011', '0110111', '0001011',
    ];

    /**
     * B模式编码表（偶校验）
     * 用于左侧数据
     * 
     * @var array<string>
     */
    protected array $encodingB = [
        '0100111', '0110011', '0011011', '0100001', '0011101',
        '0111001', '0000101', '0010001', '0001001', '0010111',
    ];

    /**
     * C模式编码表（偶校验）
     * 用于右侧数据
     * 
     * @var array<string>
     */
    protected array $encodingC = [
        '1110010', '1100110', '1101100', '1000010', '1011100',
        '1001110', '1010000', '1000100', '1001000', '1110100',
    ];

    /** @var bool 是否跳过校验位验证 */
    protected bool $skipChecksumValidation = false;
    
    /** @var string 当前处理的完整数据（含校验位） */
    protected string $currentData = '';

    /**
     * 生成 EAN-13 条码
     * 
     * 【重要】EAN-13包括ISBN(978/979前缀)都遵循相同的编码规范
     * 扫描器识别出来的是完整的13位数字，包括前缀和校验位
     * 
     * 【修改】移除校验位验证，保证条码内容与传入内容完全一致
     * 
     * @param string $data 要编码的数据（12或13位数字）
     * @return array<int> 条空模式数组
     * @throws InvalidDataException 数据格式错误时抛出
     */
    public function generate(string $data): array
    {
        $data = $this->sanitizeData($data);
        
        if (!$this->validate($data)) {
            throw new InvalidDataException('EAN-13 数据必须是12或13位纯数字，当前数据: ' . $data);
        }

        // 处理校验位 - 移除校验位验证，保证条码内容与传入内容完全一致
        if (strlen($data) === 13) {
            // 直接使用传入的13位数据，不再验证校验位
            $this->rawData = $data;
            $this->currentData = $data;
        } else {
            // 12位数据，自动计算校验位
            $checksum = $this->calculateChecksum($data);
            $this->rawData = $data . $checksum;
            $this->currentData = $this->rawData;
        }

        $this->barcodeArray = [];
        $this->longBarPositions = [];

        // 解析数据
        $firstDigit = (int) $this->rawData[0];
        $leftData = substr($this->rawData, 1, 6);
        $rightData = substr($this->rawData, 7, 6);

        // 构建条空模式
        $this->barcodeArray = [];
        
        // 左侧静区
        $this->addQuietZoneToArray();
        
        // 起始保护符
        $this->encodePattern(self::START_GUARD);
        
        // 左侧6位编码
        $parity = $this->parityPattern[$firstDigit];
        for ($i = 0; $i < 6; $i++) {
            $digit = (int) $leftData[$i];
            $mode = $parity[$i];
            $pattern = $mode === 'A' ? $this->encodingA[$digit] : $this->encodingB[$digit];
            $this->encodePattern($pattern);
        }
        
        // 中间分隔符
        $this->encodePattern(self::MIDDLE_GUARD);
        
        // 右侧6位编码
        for ($i = 0; $i < 6; $i++) {
            $digit = (int) $rightData[$i];
            $this->encodePattern($this->encodingC[$digit]);
        }
        
        // 终止保护符
        $this->encodePattern(self::END_GUARD);
        
        // 右侧静区
        $this->addQuietZoneToArray();

        // 计算长竖线位置
        $this->calculateLongBarPositions();

        return $this->barcodeArray;
    }

    /**
     * 添加静区到条码数组
     */
    protected function addQuietZoneToArray(): void
    {
        $this->barcodeArray[] = -self::QUIET_ZONE_MODULES * self::MODULE_WIDTH;
    }

    /**
     * 编码模式字符串
     * 
     * @param string $pattern 二进制模式字符串（1=条，0=空）
     */
    protected function encodePattern(string $pattern): void
    {
        for ($i = 0; $i < strlen($pattern); $i++) {
            $isBar = ($pattern[$i] === '1');
            $width = self::MODULE_WIDTH;
            
            if ($isBar) {
                $this->barcodeArray[] = $width;
            } else {
                $this->barcodeArray[] = -$width;
            }
        }
    }

    /**
     * 计算长竖线位置
     */
    protected function calculateLongBarPositions(): void
    {
        $this->longBarPositions = [];

        // 找到所有条的位置
        $barIndices = [];
        foreach ($this->barcodeArray as $i => $element) {
            if ($element > 0) {
                $barIndices[] = $i;
            }
        }

        // EAN-13 长竖线分布：
        // 起始符(2条) + 左侧数据条 + 分隔符(2条) + 右侧数据条 + 终止符(2条)
        $totalBars = count($barIndices);
        if ($totalBars >= 6) {
            // 前2条（起始符）
            $this->longBarPositions[] = $barIndices[0];
            $this->longBarPositions[] = $barIndices[1];

            // 后2条（终止符）
            $this->longBarPositions[] = $barIndices[$totalBars - 2];
            $this->longBarPositions[] = $barIndices[$totalBars - 1];

            // 中间2条（分隔符）
            $middleIdx = (int)($totalBars / 2);
            $this->longBarPositions[] = $barIndices[$middleIdx - 1];
            $this->longBarPositions[] = $barIndices[$middleIdx];
        }

        sort($this->longBarPositions);
    }

    /**
     * 验证数据格式
     * 
     * @param string $data 要验证的数据
     * @return bool 数据有效返回true
     */
    public function validate(string $data): bool
    {
        $data = $this->sanitizeData($data);
        
        if (strlen($data) !== 12 && strlen($data) !== 13) {
            return false;
        }

        if (!$this->isNumeric($data)) {
            return false;
        }

        return true;
    }

    /**
     * 计算EAN-13校验位
     * 
     * @param string $data 前12位数据
     * @return string 校验位数字
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
     * 设置是否跳过校验位验证
     * 
     * @param bool $skip 是否跳过验证
     * @return self 支持链式调用
     */
    public function setSkipChecksumValidation(bool $skip): self
    {
        $this->skipChecksumValidation = $skip;
        return $this;
    }

    /**
     * 获取条码类型
     * 
     * @return string 返回 'EAN-13'
     */
    public function getType(): string
    {
        return self::TYPE;
    }

    /**
     * 获取完整数据（含校验位）
     * 
     * @return string 13位完整数据
     */
    public function getFullData(): string
    {
        return $this->currentData;
    }

    /**
     * 获取数字布局信息
     * 
     * @return array<string, mixed> 数字位置配置
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
