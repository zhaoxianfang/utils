<?php

declare(strict_types=1);

namespace zxf\Utils\BarCode\Generator;

use zxf\Utils\BarCode\Exceptions\InvalidDataException;

/**
 * EAN-13 条码生成器（GS1标准完整实现版）
 * 
 * EAN-13（欧洲商品编号）是13位纯数字条形码
 * 严格遵循GS1标准编码规范
 * 
 * 【编码结构】（共117个窄模块）：
 * - 左侧静区：11个窄模块（空白）
 * - 起始保护符：3模块（101）- 2条1空，形成长竖线特征
 * - 左侧6位数据：42模块（7模块×6位，根据第一位选择A/B模式）
 * - 中间分隔符：5模块（01010）- 形成长竖线特征
 * - 右侧6位数据：42模块（7模块×6位，全部C模式）
 * - 终止保护符：3模块（101）- 形成长竖线特征
 * - 右侧静区：11个窄模块（空白）
 * 
 * 【奇偶模式表】（根据第一位数字选择左侧6位的A/B模式）：
 * 第一位数字通过左侧6位的奇偶模式隐含表示，不直接编码
 * 例如第一位是9时，模式为BBBAAA（第1,2,3位B模式，4,5,6位A模式）
 * 
 * 【长竖线位置】（共6条长竖线）：
 * - 起始保护符：第2条和第4条（从静区后数）
 * - 中间分隔符：第2条和第4条
 * - 终止保护符：第2条和第4条
 * 
 * 【数字显示位置】（标准EAN-13布局）：
 * - 第1位数字：最左侧，静区左边外侧
 * - 第2-7位数字：起始保护符和中间分隔符之间
 * - 第8-13位数字：中间分隔符和终止保护符之间
 */
class EAN13Generator extends BaseGenerator
{
    /** @var string 条码类型标识 */
    protected const TYPE = 'EAN-13';
    
    /** @var string 起始保护符编码（3模块：条-空-条） */
    protected const START_GUARD = '101';
    
    /** @var string 中间分隔符编码（5模块：空-条-空-条-空） */
    protected const MIDDLE_GUARD = '01010';
    
    /** @var string 终止保护符编码（3模块：条-空-条） */
    protected const END_GUARD = '101';

    /**
     * 左侧数字奇偶性模式表
     * 根据第一位数字确定左侧6位使用A模式还是B模式
     * 第一位数字不直接编码，而是通过奇偶模式隐含表示
     * 
     * A模式 = 奇校验（以空开始，7个模块）
     * B模式 = 偶校验（以条开始，7个模块）
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
     * 用于左侧数据，以空开始，7个模块
     * 编码规则：0=空，1=条
     * 
     * @var array<string>
     */
    protected array $encodingA = [
        '0001101',  // 0: 空空空条条空条
        '0011001',  // 1: 空空条条空空条
        '0010011',  // 2: 空空条空条条条
        '0111101',  // 3: 空条条条条空条
        '0100011',  // 4: 空条空空条条条
        '0110001',  // 5: 空条条空空条
        '0101111',  // 6: 空条空条条条条
        '0111011',  // 7: 空条条条条条条
        '0110111',  // 8: 空条条空条条条
        '0001011',  // 9: 空空空条空条条
    ];

    /**
     * B模式编码表（偶校验）
     * 用于左侧数据，以条开始，7个模块
     * 是A模式的反码（0变1，1变0）
     * 
     * @var array<string>
     */
    protected array $encodingB = [
        '0100111',  // 0: 条空空条条条条
        '0110011',  // 1: 条条条空条条条
        '0011011',  // 2: 空条条条空条条
        '0100001',  // 3: 条空空空空条
        '0011101',  // 4: 空条条条空条
        '0111001',  // 5: 条条条空空条
        '0000101',  // 6: 空空空空条空条
        '0010001',  // 7: 空条空空空条
        '0001001',  // 8: 空空条空空条
        '0010111',  // 9: 空条空条条条条
    ];

    /**
     * C模式编码表（偶校验）
     * 用于右侧数据，是A模式的反码，7个模块
     * 右侧数据全部使用C模式
     * 
     * @var array<string>
     */
    protected array $encodingC = [
        '1110010',  // 0
        '1100110',  // 1
        '1101100',  // 2
        '1000010',  // 3
        '1011100',  // 4
        '1001110',  // 5
        '1010000',  // 6
        '1000100',  // 7
        '1001000',  // 8
        '1110100',  // 9
    ];

    /** @var int 静区模块数（GS1标准要求至少11个模块） */
    protected int $quietZoneModules = 11;
    
    /** @var bool 是否跳过校验位验证 */
    protected bool $skipChecksumValidation = false;
    
    /** @var string 当前处理的完整数据（含校验位） */
    protected string $currentData = '';

    /**
     * 生成 EAN-13 条码
     * 
     * 【生成流程】：
     * 1. 验证数据格式（12或13位纯数字）
     * 2. 处理校验位（12位则自动计算，13位则验证）
     * 3. 确定第一位数字的奇偶模式
     * 4. 编码左侧6位（根据奇偶模式选择A/B模式）
     * 5. 编码右侧6位（全部C模式）
     * 6. 添加保护符和静区
     * 7. 转换为条空模式数组
     * 
     * @param string $data 要编码的数据（12或13位数字）
     * @return array<int> 条空模式数组（正数=条，负数=空）
     * @throws InvalidDataException 数据格式错误时抛出
     */
    public function generate(string $data): array
    {
        $data = $this->sanitizeData($data);
        
        if (!$this->validate($data)) {
            throw new InvalidDataException('EAN-13 数据必须是12或13位纯数字，当前数据: ' . $data);
        }

        // 处理校验位
        if (strlen($data) === 13) {
            if (!$this->skipChecksumValidation) {
                $checkData = substr($data, 0, 12);
                $providedCheck = $data[12];
                $calculatedCheck = $this->calculateChecksum($checkData);
                if ($providedCheck !== $calculatedCheck) {
                    throw new InvalidDataException(
                        "校验位错误: 提供的校验位是 '{$providedCheck}'，计算得到的校验位是 '{$calculatedCheck}'"
                    );
                }
            }
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
        $firstDigit = (int) $this->rawData[0];  // 第一位数字（0-9）
        $leftData = substr($this->rawData, 1, 6);   // 左侧6位（第2-7位）
        $rightData = substr($this->rawData, 7, 6);  // 右侧6位（第8-13位）

        // 构建二进制编码
        $binaryString = '';
        
        // 步骤1：左侧静区（空）
        $binaryString .= str_repeat('0', $this->quietZoneModules);
        
        // 步骤2：起始保护符（101）- 2条1空
        $binaryString .= self::START_GUARD;
        
        // 步骤3：左侧6位编码（42模块 = 7×6）
        // 根据第一位数字选择奇偶模式
        $parity = $this->parityPattern[$firstDigit];
        for ($i = 0; $i < 6; $i++) {
            $digit = (int) $leftData[$i];
            $mode = $parity[$i];
            // 根据模式选择A或B编码
            $binaryString .= $mode === 'A' ? $this->encodingA[$digit] : $this->encodingB[$digit];
        }
        
        // 步骤4：中间分隔符（01010）- 2条2空交错
        $binaryString .= self::MIDDLE_GUARD;
        
        // 步骤5：右侧6位编码（42模块 = 7×6）
        // 右侧全部使用C模式
        for ($i = 0; $i < 6; $i++) {
            $digit = (int) $rightData[$i];
            $binaryString .= $this->encodingC[$digit];
        }
        
        // 步骤6：终止保护符（101）- 2条1空
        $binaryString .= self::END_GUARD;
        
        // 步骤7：右侧静区（空）
        $binaryString .= str_repeat('0', $this->quietZoneModules);

        // 转换为条/空模式（连续相同模块合并）
        $this->barcodeArray = $this->binaryToBars($binaryString);

        // 计算长竖线位置（用于渲染时长竖线效果）
        $this->calculateLongBarPositions();

        return $this->barcodeArray;
    }

    /**
     * 计算长竖线位置
     * 
     * 【EAN-13长竖线位置】（从静区开始计数，0-based）：
     * 保护符和分隔符中的条（1）形成长竖线
     * 
     * - 起始保护符（101）：位置11, 13（第1条和第3条）
     * - 中间分隔符（01010）：位置56, 58（第2条和第4条）
     * - 终止保护符（101）：位置101, 103（第1条和第3条）
     * 
     * 注：这些位置是相对于静区开始计算的模块位置
     */
    protected function calculateLongBarPositions(): void
    {
        $this->longBarPositions = [];
        
        // EAN-13 结构（共117模块）：
        // 静区11 + 起始符3 + 左侧42 + 分隔符5 + 右侧42 + 终止符3 + 静区11
        
        // 起始保护符位置（在左侧静区之后，11模块处开始）
        // 起始保护符编码：101，条在位置11和13
        $startPos = $this->quietZoneModules;
        $this->longBarPositions[] = $startPos;      // 第1条（位置11）
        $this->longBarPositions[] = $startPos + 2;  // 第3条（位置13）
        
        // 中间分隔符位置（在左侧6位之后：11+3+42=56）
        // 中间分隔符编码：01010，条在位置57和59（相对于分隔符起始偏移1和3）
        $middlePos = $this->quietZoneModules + 3 + 42;
        $this->longBarPositions[] = $middlePos + 1;  // 第2条（位置57）
        $this->longBarPositions[] = $middlePos + 3;  // 第4条（位置59）
        
        // 终止保护符位置（在右侧6位之后：56+5+42=103）
        // 终止保护符编码：101，条在位置103和105
        $endPos = $this->quietZoneModules + 3 + 42 + 5 + 42;
        $this->longBarPositions[] = $endPos;        // 第1条（位置103）
        $this->longBarPositions[] = $endPos + 2;    // 第3条（位置105）
    }

    /**
     * 验证数据格式
     * 
     * 【验证规则】：
     * 1. 长度必须是12或13位
     * 2. 必须全部是数字字符（0-9）
     * 
     * @param string $data 要验证的数据
     * @return bool 数据有效返回true
     */
    public function validate(string $data): bool
    {
        $data = $this->sanitizeData($data);
        
        // 长度检查
        if (strlen($data) !== 12 && strlen($data) !== 13) {
            return false;
        }

        // 纯数字检查
        if (!$this->isNumeric($data)) {
            return false;
        }

        return true;
    }

    /**
     * 计算EAN-13校验位（GS1标准算法）
     * 
     * 【校验位计算算法】（MOD 10）：
     * 1. 从右向左数，奇数位（第1,3,5,7,9,11位）乘以3
     * 2. 从右向左数，偶数位（第2,4,6,8,10,12位）乘以1
     * 3. 将所有乘积求和
     * 4. 校验位 = (10 - (和 mod 10)) mod 10
     * 
     * 示例：计算 978020137962 的校验位
     * 位置：9 7 8 0 2 0 1 3 7 9 6 2
     *       12 11 10 9 8 7 6 5 4 3 2 1（从右数的位置）
     * 权重：1 3 1 3 1 3 1 3 1 3 1 3（奇数位×3，偶数位×1）
     * 计算：9×1 + 7×3 + 8×1 + 0×3 + 2×1 + 0×3 + 1×1 + 3×3 + 7×1 + 9×3 + 6×1 + 2×3
     *      = 9 + 21 + 8 + 0 + 2 + 0 + 1 + 9 + 7 + 27 + 6 + 6 = 96
     * 校验位：(10 - (96 mod 10)) mod 10 = (10 - 6) mod 10 = 4
     * 完整条码：9780201379624
     * 
     * @param string $data 前12位数据
     * @return string 校验位数字（0-9）
     */
    public function calculateChecksum(string $data): string
    {
        $data = $this->sanitizeData($data);
        
        // 确保至少有12位数字
        if (strlen($data) < 12 || !$this->isNumeric(substr($data, 0, 12))) {
            throw new InvalidDataException('计算校验位需要至少12位纯数字');
        }

        // 取前12位
        $baseData = substr($data, 0, 12);
        
        $sum = 0;
        $length = strlen($baseData);
        
        // 从右向左计算（位置1-12）
        for ($i = 0; $i < $length; $i++) {
            // 从右边数第i+1位（位置i+1）
            $digit = (int) $baseData[$length - 1 - $i];
            // 奇数位（从右数，位置1,3,5,7,9,11）乘以3，偶数位乘以1
            $position = $i + 1;
            $multiplier = ($position % 2 === 1) ? 3 : 1;
            $sum += $digit * $multiplier;
        }

        // 计算校验位
        $checksum = (10 - ($sum % 10)) % 10;
        return (string) $checksum;
    }

    /**
     * 设置是否跳过校验位验证
     * 
     * 当传入13位数据且已知校验位正确时，可跳过验证以提高性能
     * 或当需要生成非标准条码时使用
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
     * 设置静区大小
     * 
     * 【静区说明】：
     * 静区是条码左右两侧的空白区域，用于确保扫描器正确识别条码边界
     * GS1标准要求EAN-13静区至少11个窄模块宽度
     * 
     * @param int $modules 静区模块数（至少11，推荐11）
     * @return self 支持链式调用
     */
    public function setQuietZone(int $modules): self
    {
        $this->quietZoneModules = max(11, $modules);
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
     * 【EAN-13数字布局】：
     * 用于渲染器根据条码结构显示数字
     * 
     * @return array<string, mixed> 数字位置配置
     *         - type: 条码类型（ean13）
     *         - firstDigit: 第1位数字（最左侧）
     *         - leftDigits: 第2-7位数字（左侧6位）
     *         - rightDigits: 第8-13位数字（右侧6位）
     *         - fullData: 完整13位数据
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
