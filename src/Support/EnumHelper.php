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
use InvalidArgumentException;
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
        self::assertEnum($enumClass);

        return array_map(fn (UnitEnum $case) => $case->name, $enumClass::cases());
    }

    /**
     * 获取枚举所有值
     *
     * 纯枚举（UnitEnum，未实现 BackedEnum）没有 ->value，
     * 此时返回各 case 的 name 作为其标识，而不是抛出 TypeError。
     *
     * @param class-string<UnitEnum> $enumClass
     * @return array<int|string>
     */
    public static function values(string $enumClass): array
    {
        self::assertEnum($enumClass);

        return array_map(
            fn (UnitEnum $case) => $case instanceof BackedEnum ? $case->value : $case->name,
            $enumClass::cases()
        );
    }

    /**
     * 获取枚举为 name => value 数组
     *
     * 纯枚举的 value 回退为 name（见 values()）。
     *
     * @param class-string<UnitEnum> $enumClass
     * @return array<string, int|string>
     */
    public static function toArray(string $enumClass): array
    {
        self::assertEnum($enumClass);

        $result = [];
        foreach ($enumClass::cases() as $case) {
            $result[$case->name] = $case instanceof BackedEnum ? $case->value : $case->name;
        }
        return $result;
    }

    /**
     * 获取枚举为 value => name 数组
     *
     * 纯枚举的 value 回退为 name（见 values()）。
     *
     * @param class-string<UnitEnum> $enumClass
     * @return array<int|string, string>
     */
    public static function flip(string $enumClass): array
    {
        self::assertEnum($enumClass);

        $result = [];
        foreach ($enumClass::cases() as $case) {
            $result[$case instanceof BackedEnum ? $case->value : $case->name] = $case->name;
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
        if (! self::isEnum($enumClass) || ! is_subclass_of($enumClass, BackedEnum::class)) {
            // 纯枚举没有 from() 方法，直接调用会抛 Error 而非可预期的 ValueError
            return null;
        }

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
        if (! self::isEnum($enumClass)) {
            return null;
        }

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
        self::assertEnum($enumClass);

        $cases = $enumClass::cases();

        if ($cases === []) {
            // array_rand() 对空数组会抛 ValueError，这里给出更明确的提示
            throw new InvalidArgumentException("枚举 {$enumClass} 没有定义任何 case");
        }

        return $cases[array_rand($cases)];
    }

    /**
     * 获取枚举的详细描述信息（需枚举方法支持）
     *
     * 纯枚举的 value 回退为 name（见 values()）。
     *
     * @param class-string<UnitEnum> $enumClass
     * @return array<int, array{name: string, value: int|string, label: string}>
     */
    public static function options(string $enumClass): array
    {
        self::assertEnum($enumClass);

        $result = [];
        foreach ($enumClass::cases() as $case) {
            $item = [
                'name'  => $case->name,
                'value' => $case instanceof BackedEnum ? $case->value : $case->name,
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
        self::assertEnum($enumClass);

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

    /**
     * 判断给定的类名是否为枚举
     */
    public static function isEnum(string $class): bool
    {
        return enum_exists($class);
    }

    /**
     * 校验类名确为枚举，否则抛出明确异常
     *
     * 直接对非枚举类调用 `$class::cases()` 只会得到
     * "Call to undefined method ...::cases()" 这类无法定位问题的 Error，
     * 因此在所有入口统一先做校验。
     *
     * @param string $enumClass 待校验的类名
     *
     * @throws InvalidArgumentException 不是已定义的枚举时抛出
     */
    private static function assertEnum(string $enumClass): void
    {
        if (! enum_exists($enumClass)) {
            throw new InvalidArgumentException("类 {$enumClass} 不是已定义的枚举");
        }
    }
}
