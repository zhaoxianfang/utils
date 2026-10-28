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
use zxf\Utils\BarCode\Generator\CodabarGenerator;
use zxf\Utils\BarCode\Generator\ISBNGenerator;
use zxf\Utils\BarCode\Exceptions\BarcodeException;
use zxf\Utils\BarCode\Exceptions\InvalidDataException;

/**
 * 条形码工厂类
 *
 * 用于创建各种条形码生成器实例，提供统一的创建接口，并维护类型别名到规范名的映射。
 *
 * @package zxf\Utils\BarCode
 * @version 1.0.0
 * @license MIT
 *
 * @example
 * ```php
 * use zxf\Utils\BarCode\BarcodeFactory;
 *
 * // 创建 EAN-13 生成器（别名 ean-13 亦可）
 * $generator = BarcodeFactory::create('ean13');
 * $bars = $generator->generate('690123456789');
 * ```
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
        'codabar' => CodabarGenerator::class,
        'nw7' => CodabarGenerator::class,
        'isbn' => ISBNGenerator::class,
        'isbn-10' => ISBNGenerator::class,
        'isbn-13' => ISBNGenerator::class,
    ];

    /**
     * 别名 → 规范名映射（别名与规范名并存注册，但对外元数据/列表只暴露规范名）
     *
     * @var array<string, string>
     */
    protected static array $canonicalTypes = [
        'ean13' => 'ean13', 'ean-13' => 'ean13',
        'ean8' => 'ean8', 'ean-8' => 'ean8',
        'upca' => 'upca', 'upc-a' => 'upca',
        'code128' => 'code128', 'code-128' => 'code128',
        'code39' => 'code39', 'code-39' => 'code39',
        'itf14' => 'itf14', 'itf-14' => 'itf14',
        'issn' => 'issn',
        'codabar' => 'codabar', 'nw7' => 'codabar',
        'isbn' => 'isbn', 'isbn-10' => 'isbn', 'isbn-13' => 'isbn',
    ];

    /**
     * 创建条码生成器
     *
     * 类型名统一转为小写后查表实例化对应生成器，并注入配置对象；
     * 未传配置时由生成器自行使用默认配置。别名（如 ean-13、isbn-10）
     * 与规范名均可识别。
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
                "不支持的条码类型: {$type}。支持的类型: " . implode(', ', self::getSupportedTypes()),
                BarcodeException::ERROR_UNSUPPORTED_TYPE
            );
        }

        $className = self::$generators[$type];
        return new $className($config);
    }

    /**
     * 获取支持的条码类型列表（去重后的规范名：ean13/ean8/upca/code128/code39/itf14/issn/codabar/isbn）
     * 
     * @return array<string> 支持的条码类型数组
     */
    public static function getSupportedTypes(): array
    {
        return array_values(array_unique(self::$canonicalTypes));
    }

    /**
     * 将类型名（含别名，如 ean-13/upc-a/nw7/isbn-10）归一化为规范名
     *
     * @param string $type 原始类型名
     * @return string 规范类型名；未知类型返回原样（小写）便于上层 fallback
     */
    public static function canonicalType(string $type): string
    {
        $type = strtolower(trim($type));
        return self::$canonicalTypes[$type] ?? $type;
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
        
        $type = strtolower($type);
        self::$generators[$type] = $className;
        // 自定义类型无内置别名，将自身作为规范名纳入列表/归一化
        if (!isset(self::$canonicalTypes[$type])) {
            self::$canonicalTypes[$type] = $type;
        }
    }
}
