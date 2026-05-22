<?php

/**
 * ============================================================================
 * 枚举工具类
 * ============================================================================
 *
 * 提供枚举常用的辅助方法，兼容 PHP 8.1+ 的 Enum 特性。
 * 支持 UnitEnum 和 BackedEnum 的统一操作。
 *
 * @package     zxf\Utils\Support
 * @author      zxf\Utils
 * @version     1.0.0
 * @license     MIT
 * @link        https://github.com/zhaoxianfang/utils
 * @since       1.0.0
 */

declare(strict_types=1);

namespace zxf\Utils\Support;

use BackedEnum;
use ReflectionEnum;
use UnitEnum;
use ValueError;

/**
 * 枚举工具类
 *
 * 提供枚举常用的辅助方法
 */
final class EnumHelper
{
    /**
     * 获取枚举所有名称
     *
     * @param class-string<UnitEnum> $enumClass
     * @return array<string>
     */
    public static function names(string $enumClass): array
    {
        return array_map(fn (UnitEnum $case) => $case->name, $enumClass::cases());
    }

    /**
     * 获取 BackedEnum 所有值
     *
     * @param class-string<BackedEnum> $enumClass
     * @return array<int|string>
     */
    public static function values(string $enumClass): array
    {
        return array_map(fn (BackedEnum $case) => $case->value, $enumClass::cases());
    }

    /**
     * 获取枚举为 name => value 数组
     *
     * @param class-string<BackedEnum> $enumClass
     * @return array<string, int|string>
     */
    public static function toArray(string $enumClass): array
    {
        $result = [];
        foreach ($enumClass::cases() as $case) {
            $result[$case->name] = $case->value;
        }
        return $result;
    }

    /**
     * 获取枚举为 value => name 数组
     *
     * @param class-string<BackedEnum> $enumClass
     * @return array<int|string, string>
     */
    public static function flip(string $enumClass): array
    {
        $result = [];
        foreach ($enumClass::cases() as $case) {
            $result[$case->value] = $case->name;
        }
        return $result;
    }

    /**
     * 根据值获取枚举实例，失败返回 null
     *
     * @template T of BackedEnum
     * @param class-string<T> $enumClass
     * @return T|null
     */
    public static function tryFrom(string $enumClass, int|string $value): ?BackedEnum
    {
        try {
            return $enumClass::from($value);
        } catch (ValueError) {
            return null;
        }
    }

    /**
     * 根据名称获取枚举实例，失败返回 null
     *
     * @template T of UnitEnum
     * @param class-string<T> $enumClass
     * @return T|null
     */
    public static function tryFromName(string $enumClass, string $name): ?UnitEnum
    {
        foreach ($enumClass::cases() as $case) {
            if ($case->name === $name) {
                return $case;
            }
        }
        return null;
    }

    /**
     * 检查值是否在枚举中
     *
     * @param class-string<BackedEnum> $enumClass
     */
    public static function hasValue(string $enumClass, int|string $value): bool
    {
        return self::tryFrom($enumClass, $value) !== null;
    }

    /**
     * 检查名称是否在枚举中
     *
     * @param class-string<UnitEnum> $enumClass
     */
    public static function hasName(string $enumClass, string $name): bool
    {
        return self::tryFromName($enumClass, $name) !== null;
    }

    /**
     * 随机获取一个枚举实例
     *
     * @template T of UnitEnum
     * @param class-string<T> $enumClass
     * @return T
     */
    public static function random(string $enumClass): UnitEnum
    {
        $cases = $enumClass::cases();
        return $cases[array_rand($cases)];
    }

    /**
     * 获取枚举的详细描述信息（需枚举方法支持）
     *
     * @param class-string<BackedEnum> $enumClass
     * @return array<int, array{name: string, value: int|string, label: string}>
     */
    public static function options(string $enumClass): array
    {
        $result = [];
        foreach ($enumClass::cases() as $case) {
            $item = [
                'name' => $case->name,
                'value' => $case->value,
            ];

            // 尝试调用 label() 或 description() 方法
            $item['label'] = match (true) {
                method_exists($case, 'label') => $case->label(),
                method_exists($case, 'description') => $case->description(),
                default => $case->name,
            };

            $result[] = $item;
        }
        return $result;
    }

    /**
     * 获取枚举的注释（DocBlock）
     *
     * @param class-string<UnitEnum> $enumClass
     */
    public static function descriptions(string $enumClass): array
    {
        $reflection = new ReflectionEnum($enumClass);
        $result = [];

        foreach ($reflection->getCases() as $case) {
            $docComment = $case->getDocComment();
            $result[$case->getName()] = $docComment
                ? trim(preg_replace('/\s*\/?[\*\/]?/', '', $docComment))
                : '';
        }

        return $result;
    }
}
