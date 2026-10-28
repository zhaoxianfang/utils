<?php

/**
 * ============================================================================
 * 流式接口构建器
 * ============================================================================
 *
 * 用于快速构建具有链式 setter 的类，支持动态属性设置和批量填充。
 *
 * @package     zxf\Utils\Support
 * @author      zxf\Utils
 * @version     1.0.0
 * @license     MIT
 * @link        https://github.com/zhaoxianfang/utils
 */

declare(strict_types=1);

namespace zxf\Utils\Support;

use BadMethodCallException;
use Closure;
use InvalidArgumentException;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;

/**
 * 流式接口构建器
 *
 * 用于快速构建具有链式 setter 的类
 *
 * @example
 * class QueryBuilder extends Fluent {
 *     protected string $table = '';
 *     protected array $where = [];
 * }
 *
 * $query = (new QueryBuilder())->table('users')->where(['id' => 1]);
 */
abstract class Fluent
{
    /**
     * 动态设置属性并返回 $this
     *
     * 只作用于 **public** 属性：私有/受保护属性不对外暴露，
     * 否则 `__call` 会变成绕过封装写入内部状态的后门。
     * 写入前还会按属性声明的类型做校验，避免赋 null 给非 nullable
     * 属性时抛出难以定位的 TypeError。
     *
     * @param array<int, mixed> $args
     *
     * @throws BadMethodCallException 属性不存在或不可写时抛出
     * @throws InvalidArgumentException 值的类型与属性声明不符时抛出
     */
    public function __call(string $method, array $args): static
    {
        $property = $this->resolveWritableProperty($method);

        $value = $args[0] ?? null;
        $this->assertPropertyType($property, $value);

        $this->{$method} = $value;

        return $this;
    }

    /**
     * 使用数组批量设置属性
     *
     * 仅写入存在的 public 可写属性，未知键与私有/只读属性被忽略（便于直接传入请求参数），
     * 每个值都会按属性声明的类型校验，任一值不合法即抛出异常。
     *
     * @param array<string, mixed> $attributes 属性名 => 属性值
     * @return static 当前实例，便于链式调用
     *
     * @throws InvalidArgumentException 值的类型与属性声明不符时抛出
     */
    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            $property = $this->resolveWritableProperty((string) $key, silent: true);

            if ($property === null) {
                continue;
            }

            $this->assertPropertyType($property, $value);
            $this->{$key} = $value;
        }

        return $this;
    }

    /**
     * 转换为数组
     *
     * 等价于 get_object_vars($this) 在类内部调用的效果：返回当前对象
     * 全部已定义属性（含 private/protected），不会包含未定义属性。
     *
     * @return array<string, mixed> 属性名 => 当前值
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * 创建实例并填充数据
     *
     * 使用 new static 实例化子类，再交由 fill() 批量赋值，常用于快捷构造。
     *
     * @param array<string, mixed> $attributes 属性名 => 属性值
     * @return static 填充完成的新实例
     *
     * @throws InvalidArgumentException 值的类型与属性声明不符时抛出
     */
    public static function make(array $attributes = []): static
    {
        return (new static())->fill($attributes);
    }

    /**
     * 条件执行
     *
     * 条件为真时以当前实例为参数执行 $callback，为假且有 $default 时执行之；
     * 两个回调的返回值都会被忽略，方法始终返回当前实例。
     *
     * @param bool $condition 判定条件
     * @param Closure $callback 条件成立时执行的回调，签名 `function (static $self): void`
     * @param Closure|null $default 条件不成立时的回调，为 null 时不执行
     * @return static 当前实例，便于链式调用
     */
    public function when(bool $condition, Closure $callback, ?Closure $default = null): static
    {
        if ($condition) {
            $callback($this);
        } elseif ($default !== null) {
            $default($this);
        }
        return $this;
    }

    /**
     * 条件设置属性（值为 null、空字符串或空数组时跳过）
     *
     * 值非空时才解析并写入属性，写入前做类型校验；注意 0 与 false 不会被跳过。
     *
     * @param string $property 属性名
     * @param mixed $value 待写入的值
     * @return static 当前实例，便于链式调用
     *
     * @throws BadMethodCallException 属性不存在或不可写时抛出
     * @throws InvalidArgumentException 值的类型与属性声明不符时抛出
     */
    public function whenFilled(string $property, mixed $value): static
    {
        if ($value === null || $value === '' || $value === []) {
            return $this;
        }

        $reflection = $this->resolveWritableProperty($property);
        $this->assertPropertyType($reflection, $value);

        $this->{$property} = $value;

        return $this;
    }

    /**
     * 解析可写的 public 属性
     *
     * 只认可 public 且非 readonly 的属性；属性不存在或不可写时按 $silent 决定返回 null 还是抛出。
     *
     * @param string $name 属性名
     * @param bool $silent true 时属性不存在/不可写返回 null，false 时抛异常
     * @return ReflectionProperty|null 可写属性反射，$silent 模式下的失败场景返回 null
     *
     * @throws BadMethodCallException silent 为 false 且属性不可写时抛出
     */
    private function resolveWritableProperty(string $name, bool $silent = false): ?ReflectionProperty
    {
        if (! property_exists($this, $name)) {
            if ($silent) {
                return null;
            }

            throw new BadMethodCallException(sprintf(
                '属性 %s::$%s 不存在',
                static::class,
                $name
            ));
        }

        $property = new ReflectionProperty($this, $name);

        if (! $property->isPublic() || $property->isReadOnly()) {
            if ($silent) {
                return null;
            }

            throw new BadMethodCallException(sprintf(
                '属性 %s::$%s 不是可写的 public 属性，无法通过流式方法设置',
                static::class,
                $name
            ));
        }

        return $property;
    }

    /**
     * 校验值是否符合属性声明的类型
     *
     * 无类型声明时不做约束；null 仅在 nullable 时通过；命名类型按内置类型或
     * instanceof 判断；交叉类型需全部满足，联合类型命中任一即可。
     *
     * @param ReflectionProperty $property 目标属性反射
     * @param mixed $value 待校验的值
     * @return void 无返回值，校验不通过时抛异常
     *
     * @throws InvalidArgumentException 类型不符时抛出
     */
    private function assertPropertyType(ReflectionProperty $property, mixed $value): void
    {
        $type = $property->getType();

        if ($type === null) {
            return; // 无类型声明，不做约束
        }

        if ($value === null) {
            if (! $type->allowsNull()) {
                throw new InvalidArgumentException(sprintf(
                    '属性 %s::$%s 不接受 null',
                    static::class,
                    $property->getName()
                ));
            }

            return;
        }

        if ($type instanceof ReflectionNamedType) {
            $name = $type->getName();

            $matched = match ($name) {
                'mixed'             => true,
                'string'            => is_string($value),
                'int'               => is_int($value),
                'float'             => is_float($value) || is_int($value),
                'bool'              => is_bool($value),
                'array'             => is_array($value),
                'object'            => is_object($value),
                'callable', 'Closure' => is_callable($value),
                'iterable'          => is_iterable($value),
                default             => $value instanceof $name,
            };

            if (! $matched) {
                throw new InvalidArgumentException(sprintf(
                    '属性 %s::$%s 期望类型 %s，实际传入 %s',
                    static::class,
                    $property->getName(),
                    $name,
                    get_debug_type($value)
                ));
            }

            return;
        }

        // 联合/交叉类型：借助反射逐个尝试
        $matchType = function (string $name) use ($value): bool {
            return match ($name) {
                'mixed'     => true,
                'string'    => is_string($value),
                'int'       => is_int($value),
                'float'     => is_float($value) || is_int($value),
                'bool'      => is_bool($value),
                'array'     => is_array($value),
                'object'    => is_object($value),
                'iterable'  => is_iterable($value),
                default     => $value instanceof $name,
            };
        };

        if ($type instanceof ReflectionIntersectionType) {
            // 交叉类型（A&B）：必须全部满足
            foreach ($type->getTypes() as $named) {
                if (! $named instanceof ReflectionNamedType || ! $matchType($named->getName())) {
                    throw new InvalidArgumentException(sprintf(
                        '属性 %s::$%s 不接受类型 %s（期望交叉类型 %s）',
                        static::class,
                        $property->getName(),
                        get_debug_type($value),
                        (string) $type
                    ));
                }
            }

            return;
        }

        // 联合类型：命中任一即通过
        foreach ($type->getTypes() as $named) {
            if (! $named instanceof ReflectionNamedType) {
                continue;
            }
            if ($matchType($named->getName())) {
                return;
            }
        }

        throw new InvalidArgumentException(sprintf(
            '属性 %s::$%s 不接受类型 %s（期望 %s）',
            static::class,
            $property->getName(),
            get_debug_type($value),
            (string) $type
        ));
    }
}
