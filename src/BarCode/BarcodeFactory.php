<?php

declare(strict_types=1);

namespace zxf\Utils\BarCode;

use zxf\Utils\BarCode\Contracts\BarcodeGeneratorInterface;
use zxf\Utils\BarCode\DTO\BarcodeConfig;
use zxf\Utils\BarCode\Generator\EAN13Generator;
use zxf\Utils\BarCode\Generator\EAN8Generator;
use zxf\Utils\BarCode\Generator\UPCAGenerator;
use zxf\Utils\BarCode\Generator\Code128Generator;
use zxf\Utils\BarCode\Generator\Code39Generator;
use zxf\Utils\BarCode\Generator\ITF14Generator;
use zxf\Utils\BarCode\Generator\ISSNGenerator;
use zxf\Utils\BarCode\Exceptions\InvalidDataException;

/**
 * 条形码工厂类
 * 
 * 用于创建各种条形码生成器实例
 * 提供统一的创建接口
 */
class BarcodeFactory
{
    /**
     * 支持的条码类型
     * 
     * @var array<string, string>
     */
    protected static array $generators = [
        'ean13' => EAN13Generator::class,
        'ean-13' => EAN13Generator::class,
        'ean8' => EAN8Generator::class,
        'ean-8' => EAN8Generator::class,
        'upca' => UPCAGenerator::class,
        'upc-a' => UPCAGenerator::class,
        'code128' => Code128Generator::class,
        'code-128' => Code128Generator::class,
        'code39' => Code39Generator::class,
        'code-39' => Code39Generator::class,
        'itf14' => ITF14Generator::class,
        'itf-14' => ITF14Generator::class,
        'issn' => ISSNGenerator::class,
    ];

    /**
     * 创建条码生成器
     * 
     * @param string             $type   条码类型
     * @param BarcodeConfig|null $config 配置对象
     * @return BarcodeGeneratorInterface 返回条码生成器实例
     * @throws InvalidDataException 当条码类型不支持时抛出
     */
    public static function create(string $type, ?BarcodeConfig $config = null): BarcodeGeneratorInterface
    {
        $type = strtolower($type);
        
        if (!isset(self::$generators[$type])) {
            throw new InvalidDataException(
                "不支持的条码类型: {$type}。支持的类型: " . implode(', ', array_keys(self::$generators))
            );
        }

        $className = self::$generators[$type];
        return new $className($config);
    }

    /**
     * 获取支持的条码类型列表
     * 
     * @return array<string> 支持的条码类型数组
     */
    public static function getSupportedTypes(): array
    {
        return array_keys(self::$generators);
    }

    /**
     * 检查是否支持指定条码类型
     * 
     * @param string $type 条码类型
     * @return bool 支持返回true
     */
    public static function isSupported(string $type): bool
    {
        return isset(self::$generators[strtolower($type)]);
    }

    /**
     * 注册自定义条码生成器
     * 
     * @param string $type      条码类型标识
     * @param string $className 生成器类名（必须实现BarcodeGeneratorInterface）
     */
    public static function register(string $type, string $className): void
    {
        if (!in_array(BarcodeGeneratorInterface::class, class_implements($className), true)) {
            throw new InvalidDataException("类 {$className} 必须实现 BarcodeGeneratorInterface 接口");
        }
        
        self::$generators[strtolower($type)] = $className;
    }
}
