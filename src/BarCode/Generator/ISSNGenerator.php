<?php

declare(strict_types=1);

namespace zxf\Utils\BarCode\Generator;

use zxf\Utils\BarCode\Exceptions\InvalidDataException;

/**
 * ISSN 条码生成器
 * 
 * ISSN（国际标准期刊编号）条码基于EAN-13编码
 * 特征：
 * - 8位纯数字（7位数据+1位校验）
 * - 期刊专用，EAN-8变体
 * - 起始/终止长竖线
 * - 数字分「2+6」段
 * 
 * ISSN编码规则：
 * - 前缀977（转换为EAN-13）
 * - 7位ISSN数字
 * - 2位补充码（通常表示期号）
 * - 1位校验位
 */
class ISSNGenerator extends BaseGenerator
{
    /**
     * ISSN 条码类型标识
     * 
     * @var string
     */
    protected const TYPE = 'ISSN';

    /**
     * ISSN前缀（转换为EAN-13时使用）
     * 
     * @var string
     */
    protected const PREFIX = '977';

    /**
     * EAN-13生成器实例
     * 
     * @var EAN13Generator
     */
    protected EAN13Generator $ean13Generator;

    /**
     * 补充码（2位，通常表示期号）
     * 
     * @var string
     */
    protected string $addonCode = '00';

    /**
     * 构造函数
     */
    public function __construct(?\zxf\Utils\BarCode\DTO\BarcodeConfig $config = null)
    {
        parent::__construct($config);
        $this->ean13Generator = new EAN13Generator($config);
    }

    /**
     * 生成 ISSN 条码
     * 
     * ISSN转换为EAN-13格式：977 + ISSN(7位) + 补充码(2位) + 校验位
     * 
     * @param string $data 要编码的数据（7或8位ISSN数字，可含补充码）
     * @return array<int> 返回条形码条空模式数组
     */
    public function generate(string $data): array
    {
        $data = $this->sanitizeData($data);
        
        // 解析数据和补充码
        $parts = explode(' ', $data);
        $issnData = $parts[0];
        $issnData = str_replace(['-', ' '], '', $issnData);
        
        if (isset($parts[1])) {
            $this->addonCode = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
        }
        
        // 验证ISSN格式
        if (!$this->validateISSN($issnData)) {
            throw new InvalidDataException('ISSN 数据格式无效，应为7或8位数字');
        }

        // 转换ISSN为EAN-13格式
        $ean13Data = $this->convertISSNToEAN13($issnData);
        
        // 使用EAN-13生成器生成条码
        $this->barcodeArray = $this->ean13Generator->generate($ean13Data);
        $this->rawData = $this->ean13Generator->getFullData();
        $this->longBarPositions = $this->ean13Generator->getLongBarPositions();

        return $this->barcodeArray;
    }

    /**
     * 验证数据是否符合 ISSN 格式
     * 
     * @param string $data 要验证的数据
     * @return bool 数据有效返回true
     */
    public function validate(string $data): bool
    {
        $parts = explode(' ', $this->sanitizeData($data));
        $issnData = str_replace(['-', ' '], '', $parts[0]);
        
        return $this->validateISSN($issnData);
    }

    /**
     * 验证ISSN格式
     * 
     * @param string $issn ISSN数据
     * @return bool 有效返回true
     */
    protected function validateISSN(string $issn): bool
    {
        // 必须是7或8位
        if (strlen($issn) !== 7 && strlen($issn) !== 8) {
            return false;
        }

        // 必须是纯数字
        if (!$this->isNumeric($issn)) {
            return false;
        }

        return true;
    }

    /**
     * 计算 ISSN 校验位（模11算法）
     * 
     * 算法：
     * 1. 从右向左，第1位×8，第2位×7，...，第7位×2
     * 2. 求和
     * 3. 校验位 = 11 - (和 mod 11)，若为10则为X
     * 
     * @param string $data 前7位数据
     * @return string 返回校验位（数字或X）
     */
    public function calculateChecksum(string $data): string
    {
        $data = $this->sanitizeData($data);
        $data = str_replace(['-', ' '], '', $data);
        
        if (strlen($data) !== 7 || !$this->isNumeric($data)) {
            throw new InvalidDataException('计算校验位需要7位纯数字');
        }

        $sum = 0;
        $weights = [8, 7, 6, 5, 4, 3, 2];
        
        for ($i = 0; $i < 7; $i++) {
            $sum += (int) $data[$i] * $weights[$i];
        }

        $remainder = $sum % 11;
        $checksum = 11 - $remainder;
        
        if ($checksum === 11) {
            return '0';
        } elseif ($checksum === 10) {
            return 'X';
        }
        
        return (string) $checksum;
    }

    /**
     * 获取条码类型名称
     * 
     * @return string 返回'ISSN'
     */
    public function getType(): string
    {
        return self::TYPE;
    }

    /**
     * 获取完整数据（EAN-13格式）
     * 
     * @return string 返回13位完整数据
     */
    public function getFullData(): string
    {
        return $this->rawData;
    }

    /**
     * 转换 ISSN 为 EAN-13 格式
     * 
     * @param string $issn 7或8位ISSN
     * @return string 返回13位EAN-13格式
     */
    protected function convertISSNToEAN13(string $issn): string
    {
        $issn = str_replace(['-', ' '], '', $issn);
        
        // 取前7位
        $issn7 = substr($issn, 0, 7);
        
        // 构建EAN-13基础：977 + ISSN7 + 补充码2位
        $ean13Base = self::PREFIX . $issn7 . $this->addonCode;
        
        // 计算EAN-13校验位
        $ean13 = new EAN13Generator();
        $checksum = $ean13->calculateChecksum($ean13Base);
        
        return $ean13Base . $checksum;
    }

    /**
     * 设置补充码
     * 
     * @param string $code 2位补充码（期号）
     * @return self 支持链式调用
     */
    public function setAddonCode(string $code): self
    {
        $this->addonCode = str_pad($code, 2, '0', STR_PAD_LEFT);
        return $this;
    }

    /**
     * 格式化 ISSN 显示
     * 
     * @param string $issn 原始ISSN
     * @return string 格式化后的ISSN（如1234-567X）
     */
    public function formatISSN(string $issn): string
    {
        $issn = str_replace(['-', ' '], '', $this->sanitizeData($issn));
        
        if (strlen($issn) === 8) {
            return substr($issn, 0, 4) . '-' . substr($issn, 4, 4);
        }
        
        return $issn;
    }

    /**
     * 获取数字显示配置
     * 
     * @return array<string, mixed> 数字位置配置
     */
    public function getDigitLayout(): array
    {
        return [
            'type' => 'issn',
            'prefix' => self::PREFIX,
            'issn' => substr($this->rawData, 3, 7),
            'addon' => $this->addonCode,
            'checkDigit' => substr($this->rawData, 12, 1),
        ];
    }
}
