<?php

declare(strict_types=1);

namespace zxf\Utils\BarCode\Generator;

use zxf\Utils\BarCode\Contracts\BarcodeGeneratorInterface;
use zxf\Utils\BarCode\DTO\BarcodeConfig;
use zxf\Utils\BarCode\Exceptions\InvalidDataException;

/**
 * 条形码生成器抽象基类
 * 
 * 提供条形码生成器的公共功能和工具方法
 * 所有具体条形码生成器都应继承此类
 */
abstract class BaseGenerator implements BarcodeGeneratorInterface
{
    /**
     * 配置对象
     * 
     * @var BarcodeConfig
     */
    protected BarcodeConfig $config;

    /**
     * 当前处理的原始数据
     * 
     * @var string
     */
    protected string $rawData = '';

    /**
     * 条/空模式数组
     * 每个元素表示一个条或空，正数为条，负数为空
     * 
     * @var array<int>
     */
    protected array $barcodeArray = [];

    /**
     * 长竖线位置索引
     * 记录哪些位置应该是长竖线
     * 
     * @var array<int>
     */
    protected array $longBarPositions = [];

    /**
     * 数字显示位置信息
     * 记录每个数字应该显示的位置
     * 
     * @var array<int, string>
     */
    protected array $digitPositions = [];

    /**
     * 构造函数
     * 
     * @param BarcodeConfig|null $config 配置对象
     */
    public function __construct(?BarcodeConfig $config = null)
    {
        $this->config = $config ?? new BarcodeConfig();
    }

    /**
     * 设置配置
     * 
     * @param BarcodeConfig $config 配置对象
     * @return self 支持链式调用
     */
    public function setConfig(BarcodeConfig $config): self
    {
        $this->config = $config;
        return $this;
    }

    /**
     * 获取配置
     * 
     * @return BarcodeConfig 配置对象
     */
    public function getConfig(): BarcodeConfig
    {
        return $this->config;
    }

    /**
     * 获取长竖线位置
     * 
     * @return array<int> 长竖线索引数组
     */
    public function getLongBarPositions(): array
    {
        return $this->longBarPositions;
    }

    /**
     * 获取数字显示位置
     * 
     * @return array<int, string> 数字位置数组
     */
    public function getDigitPositions(): array
    {
        return $this->digitPositions;
    }

    /**
     * 获取条码总宽度（模块数）
     * 
     * @return int 总宽度（模块数）
     */
    public function getTotalWidth(): int
    {
        $width = 0;
        foreach ($this->barcodeArray as $element) {
            $width += abs($element);
        }
        return $width;
    }

    /**
     * 清理数据，移除非必要字符
     * 
     * @param string $data 原始数据
     * @return string 清理后的数据
     */
    protected function sanitizeData(string $data): string
    {
        return trim($data);
    }

    /**
     * 将二进制字符串转换为条/空模式
     * 
     * @param string $binary 二进制字符串（1表示条，0表示空）
     * @return array<int> 条/空模式数组
     */
    protected function binaryToBars(string $binary): array
    {
        $result = [];
        $current = $binary[0] ?? '1';
        $count = 0;

        for ($i = 0; $i < strlen($binary); $i++) {
            if ($binary[$i] === $current) {
                $count++;
            } else {
                $result[] = $current === '1' ? $count : -$count;
                $current = $binary[$i];
                $count = 1;
            }
        }

        if ($count > 0) {
            $result[] = $current === '1' ? $count : -$count;
        }

        return $result;
    }

    /**
     * 将条/空模式转换为二进制字符串
     * 
     * @param array<int> $bars 条/空模式数组
     * @return string 二进制字符串
     */
    protected function barsToBinary(array $bars): string
    {
        $result = '';
        foreach ($bars as $bar) {
            $isBar = $bar > 0;
            $width = abs($bar);
            $result .= str_repeat($isBar ? '1' : '0', $width);
        }
        return $result;
    }

    /**
     * 在条码数据中插入静区（空白区域）
     * 
     * @param array<int> $bars 原始条/空模式
     * @return array<int> 添加静区后的模式
     */
    protected function addQuietZone(array $bars): array
    {
        if (!$this->config->showQuietZone) {
            return $bars;
        }

        $quietZone = [$this->config->quietZoneWidth * -1];
        return array_merge($quietZone, $bars, $quietZone);
    }

    /**
     * 十六进制颜色转换为RGB数组
     * 
     * @param string $hex 十六进制颜色值
     * @return array<int> RGB数组 [R, G, B]
     */
    protected function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * 生成纯数字的校验位（模10算法）
     * 
     * 【标准MOD 10算法】：
     * 奇数位（从右数，位置1,3,5...）乘以3，偶数位乘以1
     * 
     * @param string $data 纯数字数据（不包含校验位）
     * @return string 返回校验位数字
     */
    protected function calculateMod10Checksum(string $data): string
    {
        $sum = 0;
        $length = strlen($data);
        
        for ($i = 0; $i < $length; $i++) {
            $digit = (int) $data[$length - 1 - $i];
            // 从右数的位置（1-based）
            $position = $i + 1;
            // 奇数位（位置1,3,5...）乘以3，偶数位乘以1
            $multiplier = ($position % 2 === 1) ? 3 : 1;
            $sum += $digit * $multiplier;
        }

        $checksum = (10 - ($sum % 10)) % 10;
        return (string) $checksum;
    }

    /**
     * 检查字符串是否为纯数字
     * 
     * @param string $str 要检查的字符串
     * @return bool 是纯数字返回true
     */
    protected function isNumeric(string $str): bool
    {
        return ctype_digit($str);
    }

    /**
     * 检查字符串是否只包含指定字符集
     * 
     * @param string $str     要检查的字符串
     * @param string $charset 允许的字符集
     * @return bool 符合返回true
     */
    protected function containsOnly(string $str, string $charset): bool
    {
        return strspn($str, $charset) === strlen($str);
    }
}
