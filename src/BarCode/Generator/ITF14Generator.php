<?php

declare(strict_types=1);

namespace zxf\Utils\BarCode\Generator;

use zxf\Utils\BarCode\Exceptions\InvalidDataException;

/**
 * ITF-14 条码生成器（GS1标准完整实现版）
 * 
 * ITF-14（Interleaved 2 of 5）是14位纯数字条码，主要用于物流包装箱标识
 * 严格遵循GS1标准编码规范
 * 
 * 【编码特征】：
 * - 14位纯数字（包含1位校验位）
 * - 交叉25码结构：条和空交错编码，每2个数字一组
 * - 每个数字用5个元素表示（2宽3窄或3宽2窄）
 * - 无长竖线特征（不同于EAN-13）
 * - 高密度，耐磨损设计
 * 
 * 【数据结构】（14位）：
 * - 包装指示符（1位）：0=零售商品，1-8=包装箱等级，9=变量度量商品
 * - UPC/EAN系统字符（1位）：通常为0
 * - 厂商代码（5-7位）
 * - 产品代码（3-5位）
 * - 校验位（1位）：MOD 10算法
 * 
 * 【编码结构】（模块数取决于窄模块宽度）：
 * - 左侧静区：10个窄条宽度（推荐20mm）
 * - 起始符：4个窄元素（窄条-窄空-窄条-窄空）
 * - 数据（14位数字，7组2位数字）：
 *   * 每组2位交错编码，每个数字5个元素（2宽3窄）
 *   * 第一个数字编码为条，第二个数字编码为空
 * - 终止符：3个元素（宽条-窄空-窄条）
 * - 右侧静区：10个窄条宽度（推荐20mm）
 * 
 * 【窄/宽比例】：推荐1:2.0到1:3.0之间
 */
class ITF14Generator extends BaseGenerator
{
    /** @var string 条码类型标识 */
    protected const TYPE = 'ITF-14';
    
    /** @var string 起始符编码（4窄：条-空-条-空） */
    protected const START = '1010';
    
    /** @var string 终止符编码（宽条-窄空-窄条） */
    protected const STOP = '1101';

    /**
     * ITF-14 编码表（5 个元素/数字，Interleaved 2 of 5 权威表，每数字恰 2 宽）
     *
     * 【编码规则】：
     * - n = 窄元素，w = 宽元素；5 元素中恰 2 个宽元素（C(5,2)=10 恰好映射 0-9）
     * - 与 jsBarcode / zbar / ZXing 三家实现逐位一致（均经本地实测交叉验证）
     * - 历史缺陷：旧表在 5/6/8/9 上宽位错位，真扫码器按标准表把内容确定性翻转
     *   （5↔8、6↔9，如 15400141288763 被 zbar/ZXing 读成 18400141255793）
     *
     * 交错编码方式：
     * - 第 1 个数字编码为 5 个条，第 2 个数字编码为 5 个空
     * - 两者交错：条1-空1-条2-空2-条3-空3-条4-空4-条5-空5
     *
     * @var array<string, string>
     */
    protected array $encoding = [
        '0' => 'nnwwn', // 窄窄宽宽窄 (jsBarcode 00110)
        '1' => 'wnnnw', // 宽窄窄窄宽 (10001)
        '2' => 'nwnnw', // 窄宽窄窄宽 (01001)
        '3' => 'wwnnn', // 宽宽窄窄窄 (11000)
        '4' => 'nnwnw', // 窄窄宽窄宽 (00101)
        '5' => 'wnwnn', // 宽窄宽窄窄 (10100)
        '6' => 'nwwnn', // 窄宽宽窄窄 (01100)
        '7' => 'nnnww', // 窄窄窄宽宽 (00011)
        '8' => 'wnnwn', // 宽窄窄宽窄 (10010)
        '9' => 'nwnwn', // 窄宽窄宽窄 (01010)
    ];

    /** @var float 宽条与窄条的比例（GS1 推荐 2.25-3.0） */
    protected float $wideRatio = 2.5;
    
    /**
     * @var int 窄元素宽度（模块级：1 模块 = renderer 的 moduleSize 像素）
     * 默认 1 模块；宽元素 = round(窄 × wideRatio) 模块。
     * 历史实现把窄元素当像素(默认2px)后由 renderer 再按 moduleSize 放大，
     * 导致 moduleSize 语义翻倍，已统一为模块级。
     */
    protected int $narrowWidth = 1;
    
    /** @var int 静区模块数（ITF-14要求至少10个窄模块） */
    protected int $quietZoneModules = 10;

    /** @var string 当前处理的完整数据（含校验位） */
    protected string $currentData = '';

    /**
     * 生成 ITF-14 条码
     * 
     * 【生成流程】：
     * 1. 验证数据格式（13或14位纯数字）
     * 2. 处理校验位（13位则自动计算，14位则直接使用）
     * 3. 确保数据长度为偶数（ITF要求，不足则在前面补0）
     * 4. 添加左侧静区
     * 5. 添加起始符
     * 6. 交错编码数据（每2位一组，第1位编码为条，第2位编码为空）
     * 7. 添加终止符
     * 8. 添加右侧静区
     * 9. 转换为条空模式数组
     * 
     * @param string $data 要编码的数据（13或14位数字）
     * @return array<int> 条空模式数组
     * @throws InvalidDataException 数据格式错误时抛出
     */
    public function generate(string $data): array
    {
        $data = $this->sanitizeData($data);
        
        if (!$this->validate($data)) {
            throw new InvalidDataException('ITF-14 数据必须是13或14位纯数字，当前数据: ' . $data);
        }

        // 处理校验位
        if (strlen($data) === 14) {
            // 输入已含校验位：默认验证正确性（skipChecksum(true) 可跳过）。
            // 校验位错误意味着内容本身非法（扫码器会拒读），必须报错而不是静默放行
            $this->assertCompleteChecksum($data, 'ITF-14');
            $this->rawData = $data;
            $this->currentData = $data;
        } else {
            // 13位数据，自动计算校验位
            $checksum = $this->calculateChecksum($data);
            $this->rawData = $data . $checksum;
            $this->currentData = $this->rawData;
        }

        // ITF-14必须是偶数位，确保数据长度为偶数
        $encodedData = $this->rawData;
        if (strlen($encodedData) % 2 !== 0) {
            $encodedData = '0' . $encodedData;
        }

        // 构建二进制编码
        $binary = '';
        
        // 步骤1：左侧静区（空）
        $quietWidth = $this->quietZoneModules * $this->narrowWidth;
        $binary .= str_repeat('0', $quietWidth);
        
        // 步骤2：起始符（4窄：条-空-条-空）
        // 每个窄元素用narrowWidth个模块表示
        $narrowPattern = str_repeat('1', $this->narrowWidth); // 窄条
        $narrowSpace = str_repeat('0', $this->narrowWidth);   // 窄空
        $binary .= $narrowPattern . $narrowSpace . $narrowPattern . $narrowSpace;

        // 步骤3：交错编码数据
        // 每2个数字一组，第1个编码为条，第2个编码为空
        for ($i = 0; $i < strlen($encodedData); $i += 2) {
            $digit1 = $encodedData[$i];      // 第一个数字（编码为条序列）
            $digit2 = $encodedData[$i + 1];  // 第二个数字（编码为空序列）

            $pattern1 = $this->encoding[$digit1]; // 条模式（n/w）
            $pattern2 = $this->encoding[$digit2]; // 空模式（n/w）

            // 交错编码：条-空-条-空-条-空-条-空-条-空（共10个元素）
            for ($j = 0; $j < 5; $j++) {
                // digit1编码为条
                $isWide1 = ($pattern1[$j] === 'w');
                $width1 = $isWide1 ? $this->wideModules() : $this->narrowWidth;
                $binary .= str_repeat('1', $width1);

                // digit2编码为空
                $isWide2 = ($pattern2[$j] === 'w');
                $width2 = $isWide2 ? $this->wideModules() : $this->narrowWidth;
                $binary .= str_repeat('0', $width2);
            }
        }

        // 步骤4：终止符（宽条-窄空-窄条）
        $wideWidth = $this->wideModules();
        $binary .= str_repeat('1', $wideWidth);  // 宽条
        $binary .= str_repeat('0', $this->narrowWidth); // 窄空
        $binary .= str_repeat('1', $this->narrowWidth); // 窄条

        // 步骤5：右侧静区（空）
        $binary .= str_repeat('0', $quietWidth);

        // 转换为条/空模式
        $this->barcodeArray = $this->binaryToBars($binary);

        return $this->barcodeArray;
    }

    /**
     * 验证数据格式
     * 
     * 【验证规则】：
     * 1. 长度必须是13或14位
     * 2. 必须全部是数字字符（0-9）
     * 
     * @param string $data 要验证的数据
     * @return bool 数据有效返回true
     */
    public function validate(string $data): bool
    {
        $data = $this->sanitizeData($data);
        
        // 长度检查
        if (strlen($data) !== 13 && strlen($data) !== 14) {
            return false;
        }

        // 纯数字检查
        if (!$this->isNumeric($data)) {
            return false;
        }

        return true;
    }

    /**
     * 计算ITF-14校验位（GS1标准MOD 10算法）
     * 
     * 【校验位计算算法】（MOD 10）：
     * 1. 从右向左，第1,3,5,7,9,11,13位（奇数位）乘以3
     * 2. 从右向左，第2,4,6,8,10,12位（偶数位）乘以1
     * 3. 将所有乘积求和
     * 4. 校验位 = (10 - (和 mod 10)) mod 10
     * 
     * 注意：ITF-14校验位算法与EAN-13相同
     * 
     * @param string $data 前13位数据
     * @return string 校验位数字（0-9）
     */
    public function calculateChecksum(string $data): string
    {
        $data = $this->sanitizeData($data);
        
        // 确保有13位数字
        if (strlen($data) !== 13 || !$this->isNumeric($data)) {
            throw new InvalidDataException('ITF-14计算校验位需要13位纯数字');
        }

        $sum = 0;
        $length = strlen($data);
        
        // 从右向左计算（位置1-13）
        for ($i = 0; $i < $length; $i++) {
            // 从右边数第i+1位（位置i+1）
            $digit = (int) $data[$length - 1 - $i];
            // 奇数位（从右数，位置1,3,5,7,9,11,13）乘以3，偶数位乘以1
            $position = $i + 1;
            $weight = ($position % 2 === 1) ? 3 : 1;
            $sum += $digit * $weight;
        }

        // 计算校验位
        $checksum = (10 - ($sum % 10)) % 10;
        return (string) $checksum;
    }

    /**
     * 计算宽元素模块数：round(窄模块数 × 宽窄比)，保证宽:窄 落在 GS1 允许区间内
     *
     * 结果至少比窄元素宽 1 个模块，避免取整后宽窄相同；而实际比值仍受
     * wideRatio 的 2.0-3.0 约束。
     *
     * @return int 宽元素占用的模块数
     */
    protected function wideModules(): int
    {
        return max($this->narrowWidth + 1, (int) round($this->narrowWidth * $this->wideRatio));
    }

    /**
     * 设置宽条与窄条的比例
     * 
     * 【比例说明】：
     * - GS1标准要求：2.25 ≤ 宽/窄比例 ≤ 3.0
     * - 默认 2.5；由于元素宽度按模块取整，实际宽:窄可能为 2:1 或 3:1
     * 
     * @param float $ratio 宽条与窄条的比例
     * @return self 支持链式调用
     */
    public function setWideRatio(float $ratio): self
    {
        $this->wideRatio = max(2.0, min(3.0, $ratio));
        return $this;
    }

    /**
     * 设置窄元素宽度（模块级，1 模块 = renderer 的 moduleSize 像素）
     * 
     * 【宽度说明】：
     * - 默认 1 模块；如需更粗的窄条可传 2（宽元素随之取整放大，宽窄比保持）
     * 
     * @param int $width 窄元素模块数
     * @return self 支持链式调用
     */
    public function setNarrowWidth(int $width): self
    {
        $this->narrowWidth = max(1, $width);
        return $this;
    }

    /**
     * 设置静区大小
     * 
     * 【静区说明】：
     * ITF-14标准要求静区至少10个窄条宽度
     * 实际应用中推荐10个窄条宽度（约20mm）
     * 
     * @param int $modules 静区模块数（至少10）
     * @return self 支持链式调用
     */
    public function setQuietZone(int $modules): self
    {
        $this->quietZoneModules = max(10, $modules);
        return $this;
    }

    /**
     * 获取条码类型
     *
     * 返回固定的类型标识，渲染器据此决定是否绘制 Bearer Bar 与文字布局。
     * 
     * @return string 返回 'ITF-14'
     */
    public function getType(): string
    {
        return self::TYPE;
    }

    /**
     * 获取完整数据（含校验位）
     * 
     * @return string 14位完整数据
     */
    public function getFullData(): string
    {
        return $this->currentData;
    }
}
