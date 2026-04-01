<?php

declare(strict_types=1);

namespace zxf\Utils\BarCode\Generator;

use zxf\Utils\BarCode\Exceptions\InvalidDataException;

/**
 * ISBN 条码生成器（GS1标准完整实现版）
 * 
 * ISBN（国际标准书号）条码基于EAN-13编码
 * 严格遵循GS1标准编码规范
 * 
 * 【关键概念】：
 * ISBN-13就是前缀为978或979的EAN-13条码
 * 两者在条码编码上完全相同，只是ISBN有特定的前缀要求
 * 
 * 【ISBN-13格式】（13位）：
 * - 前缀（3位）：978或979
 * - 组号（1位）：语言/地区代码（中国为7）
 * - 出版社号（3-4位）
 * - 书序号（5-4位）
 * - 校验位（1位）：MOD 10算法
 * 
 * 【ISBN-10格式】（已淘汰，10位）：
 * - 组号（1位）
 * - 出版社号（2-7位）
 * - 书序号（6-1位）
 * - 校验位（1位）：可能是0-9或X
 * 
 * 【编码特征】：
 * - 基于EAN-13，13位纯数字
 * - 3组长竖线（起始/分隔/终止保护符）
 * - 数字分「1+6+6」段显示
 *   * 第1位：最左侧，静区左边外侧
 *   * 第2-7位：起始保护符和中间分隔符之间
 *   * 第8-13位：中间分隔符和终止保护符之间
 * - 黑条白空结构
 * 
 * 【中国图书ISBN格式】：
 * 978-7-XXX-XXXXX-X
 * - 978：ISBN前缀
 * - 7：中国组号
 * - XXX：出版社号
 * - XXXXX：书序号
 * - X：校验位
 */
class ISBNGenerator extends BaseGenerator
{
    /** @var string 条码类型标识 */
    protected const TYPE = 'ISBN';
    
    /** @var array<string> ISBN支持的前缀 */
    protected array $prefixes = ['978', '979'];
    
    /** @var EAN13Generator EAN-13生成器实例 */
    protected EAN13Generator $ean13Generator;
    
    /** @var string 格式化后的ISBN（带分隔符，用于显示） */
    protected string $formattedISBN = '';
    
    /** @var bool 是否跳过校验位验证 */
    protected bool $skipChecksumValidation = false;
    
    /** @var string 当前处理的完整数据（含校验位） */
    protected string $currentData = '';

    public function __construct(?\zxf\Utils\BarCode\DTO\BarcodeConfig $config = null)
    {
        parent::__construct($config);
        $this->ean13Generator = new EAN13Generator($config);
    }

    /**
     * 生成 ISBN 条码
     * 
     * 【关键理解】：
     * ISBN-13本质上就是EAN-13条码，前缀为978或979
     * 因此直接使用EAN-13生成器生成，确保扫描结果一致
     * 
     * 【生成流程】：
     * 1. 清理数据，移除非数字字符
     * 2. 格式化数据用于显示
     * 3. 处理ISBN-10到ISBN-13的转换
     * 4. 验证ISBN-13格式（前缀978/979，13位数字）
     * 5. 使用EAN-13生成器生成条码
     * 
     * @param string $data 要编码的数据（ISBN-10或ISBN-13格式）
     * @return array<int> 条空模式数组
     * @throws InvalidDataException 数据格式错误时抛出
     */
    public function generate(string $data): array
    {
        $originalData = $data;
        $data = $this->sanitizeData($data);
        
        // 保存格式化版本（用于显示）
        $this->formattedISBN = $this->formatISBNForDisplay($originalData);
        
        // 移除所有分隔符，获取纯数字
        $cleanData = str_replace(['-', ' ', '.'], '', $data);
        
        // 处理ISBN-10转ISBN-13
        if ($this->isISBN10($cleanData)) {
            $cleanData = $this->convertISBN10To13($cleanData);
        }
        
        // 验证ISBN-13格式
        if (!$this->validateISBN13($cleanData)) {
            throw new InvalidDataException(
                'ISBN 数据格式无效，应为978/979开头的13位数字，当前数据: ' . $cleanData
            );
        }

        // 【关键】使用EAN-13生成器生成条码
        // ISBN就是EAN-13的一种应用，前缀978或979
        $this->ean13Generator->setSkipChecksumValidation($this->skipChecksumValidation);
        $this->barcodeArray = $this->ean13Generator->generate($cleanData);
        
        // 获取完整数据（确保包含正确的校验位）
        $this->rawData = $this->ean13Generator->getFullData();
        $this->currentData = $this->rawData;
        
        return $this->barcodeArray;
    }

    /**
     * 判断是否为ISBN-10格式
     * 
     * 【ISBN-10特征】：
     * - 长度为10位
     * - 前9位为数字
     * - 第10位为数字或X（X代表10）
     * 
     * @param string $data 要判断的数据
     * @return bool 是ISBN-10返回true
     */
    protected function isISBN10(string $data): bool
    {
        // ISBN-10长度为10，前9位数字，第10位数字或X
        if (strlen($data) === 10) {
            $prefix = substr($data, 0, 9);
            $check = strtoupper($data[9]);
            return ctype_digit($prefix) && (ctype_digit($check) || $check === 'X');
        }
        return false;
    }

    /**
     * 验证ISBN-13格式
     * 
     * 【验证规则】：
     * 1. 长度必须为13位
     * 2. 必须全部是数字
     * 3. 前缀必须是978或979
     * 
     * @param string $data 要验证的数据
     * @return bool 格式正确返回true
     */
    protected function validateISBN13(string $data): bool
    {
        // 长度检查
        if (strlen($data) !== 13) {
            return false;
        }

        // 纯数字检查
        if (!ctype_digit($data)) {
            return false;
        }

        // 前缀检查
        $prefix = substr($data, 0, 3);
        if (!in_array($prefix, $this->prefixes, true)) {
            return false;
        }

        return true;
    }

    /**
     * 验证数据
     * 
     * 支持ISBN-10或ISBN-13格式
     * 
     * @param string $data 要验证的数据
     * @return bool 数据有效返回true
     */
    public function validate(string $data): bool
    {
        $data = str_replace(['-', ' ', '.'], '', $this->sanitizeData($data));
        
        // 支持ISBN-10或ISBN-13
        if ($this->isISBN10($data)) {
            return true;
        }
        
        return $this->validateISBN13($data);
    }

    /**
     * 计算ISBN校验位（GS1标准MOD 10算法）
     * 
     * 【算法说明】：
     * ISBN-13使用EAN-13的MOD 10算法
     * 与EAN-13校验位计算方法完全相同
     * 
     * 【标准算法】：
     * 从右向左数，奇数位（第1,3,5,7,9,11位）乘以3，偶数位乘以1
     * 
     * @param string $data 要计算的数据（前12位）
     * @return string 校验位数字（0-9）
     */
    public function calculateChecksum(string $data): string
    {
        $data = str_replace(['-', ' ', '.'], '', $this->sanitizeData($data));
        
        // 如果是ISBN-10，先转为ISBN-13前12位再计算
        if ($this->isISBN10($data)) {
            $data = '978' . substr($data, 0, 9);
        }
        
        // 取前12位计算
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

        $checksum = (10 - ($sum % 10)) % 10;
        return (string) $checksum;
    }

    /**
     * 转换ISBN-10到ISBN-13
     * 
     * 【转换规则】：
     * 1. 取ISBN-10的前9位（去掉校验位）
     * 2. 前缀添加978
     * 3. 使用EAN-13算法计算新的校验位
     * 4. 组合成13位ISBN-13
     * 
     * 示例：
     * ISBN-10: 0-306-40615-2
     * 转换：978 + 030640615 + 新校验位
     * 计算：978030640615的校验位
     * 结果：9780306406157
     * 
     * @param string $isbn10 ISBN-10格式
     * @return string ISBN-13格式
     * @throws InvalidDataException 无效的ISBN-10格式
     */
    public function convertISBN10To13(string $isbn10): string
    {
        $isbn10 = str_replace(['-', ' ', '.'], '', $this->sanitizeData($isbn10));
        
        if (!$this->isISBN10($isbn10)) {
            throw new InvalidDataException('无效的ISBN-10格式: ' . $isbn10);
        }

        // ISBN-10转ISBN-13：前缀978 + 前9位 + 新校验位
        $isbn13Base = '978' . substr($isbn10, 0, 9);
        $checksum = $this->calculateChecksum($isbn13Base);
        
        return $isbn13Base . $checksum;
    }

    /**
     * 格式化ISBN用于显示
     * 
     * 【格式化规则】：
     * 中国图书：978-7-XXX-XXXXX-X
     * 通用格式：978-X-XXXX-XXXX-X
     * 
     * @param string $isbn 原始ISBN
     * @return string 格式化后的ISBN
     */
    protected function formatISBNForDisplay(string $isbn): string
    {
        $clean = str_replace(['-', ' ', '.'], '', $this->sanitizeData($isbn));
        
        if (strlen($clean) === 13) {
            $prefix = substr($clean, 0, 3);
            $group = substr($clean, 3, 1);
            
            // 中国图书格式：978-7-XXX-XXXXX-X
            if ($prefix === '978' && $group === '7') {
                return substr($clean, 0, 3) . '-' . 
                       substr($clean, 3, 1) . '-' . 
                       substr($clean, 4, 3) . '-' . 
                       substr($clean, 7, 5) . '-' . 
                       substr($clean, 12, 1);
            }
            
            // 通用格式：978-X-XXXX-XXXX-X
            return substr($clean, 0, 3) . '-' . 
                   substr($clean, 3, 1) . '-' . 
                   substr($clean, 4, 4) . '-' . 
                   substr($clean, 8, 4) . '-' . 
                   substr($clean, 12, 1);
        }
        
        return $clean;
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
     * @return string 返回 'ISBN'
     */
    public function getType(): string
    {
        return self::TYPE;
    }

    /**
     * 获取完整数据（含校验位）
     * 
     * @return string 13位完整ISBN
     */
    public function getFullData(): string
    {
        return $this->currentData;
    }

    /**
     * 获取格式化的ISBN
     * 
     * @return string 带分隔符的ISBN，如 "978-7-111-12345-3"
     */
    public function getFormattedISBN(): string
    {
        return $this->formattedISBN;
    }

    /**
     * 获取数字布局信息
     * 
     * 【ISBN数字布局】：
     * 用于渲染器根据条码结构显示数字
     * 
     * @return array<string, mixed> 数字位置配置
     *         - type: 条码类型（isbn）
     *         - prefix: 前缀（978/979）
     *         - groupCode: 组号（语言/地区代码）
     *         - publisherCode: 出版社号
     *         - titleCode: 书序号
     *         - checkDigit: 校验位
     *         - formatted: 格式化后的ISBN
     *         - fullData: 完整13位数据
     */
    public function getDigitLayout(): array
    {
        return [
            'type' => 'isbn',
            'prefix' => substr($this->currentData, 0, 3),
            'groupCode' => substr($this->currentData, 3, 1),
            'publisherCode' => substr($this->currentData, 4, 3),
            'titleCode' => substr($this->currentData, 7, 5),
            'checkDigit' => substr($this->currentData, 12, 1),
            'formatted' => $this->formattedISBN,
            'fullData' => $this->currentData,
        ];
    }
}
