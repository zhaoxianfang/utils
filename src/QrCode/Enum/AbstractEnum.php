<?php
declare(strict_types = 1);

namespace zxf\Utils\QrCode\Enum;

use zxf\Utils\QrCode\Exception\CloneNotSupportedException;
use zxf\Utils\QrCode\Exception\IllegalArgumentException;
use zxf\Utils\QrCode\Exception\MismatchException;
use zxf\Utils\QrCode\Exception\SerializeNotSupportedException;
use zxf\Utils\QrCode\Exception\UnserializeNotSupportedException;
use ReflectionClass;

/**
 * 抽象枚举基类
 *
 * 提供枚举功能的基础类，支持定义枚举常量和静态方法访问
 * 从 DASPRiD\Enum\AbstractEnum 复制并重构
 */
abstract class AbstractEnum
{
    /**
     * @var string 枚举名称
     */
    private string $name;

    /**
     * @var int 枚举序号
     */
    private int $ordinal;

    /**
     * @var array<string, array<string, static>> 枚举值缓存
     */
    private static array $values = [];

    /**
     * @var array<string, bool> 所有值是否已加载
     */
    private static array $allValuesLoaded = [];

    /**
     * @var array<string, array> 常量缓存
     */
    private static array $constants = [];

    /**
     * 构造函数设为私有以避免任意创建枚举实例
     *
     * 当为参数化枚举创建自己的构造函数时，确保声明为 protected，
     * 这样静态方法能够构造它。避免声明为 public，因为这会允许创建非单例枚举实例
     */
    private function __construct()
    {
    }

    /**
     * 魔术静态调用方法，转发所有调用到 valueOf()
     *
     * @param string $name 方法名
     * @param array $arguments 参数
     * @return static
     */
    final public static function __callStatic(string $name, array $arguments) : self
    {
        return static::valueOf($name);
    }

    /**
     * 返回具有指定名称的枚举
     *
     * 名称必须精确匹配用于在此类型中声明枚举的标识符（不允许多余的空白字符）
     *
     * @param string $name 枚举名称
     * @return static
     * @throws IllegalArgumentException 如果枚举没有指定名称的常量
     */
    final public static function valueOf(string $name) : self
    {
        if (isset(self::$values[static::class][$name])) {
            return self::$values[static::class][$name];
        }

        $constants = self::constants();

        if (array_key_exists($name, $constants)) {
            return self::createValue($name, $constants[$name][0], $constants[$name][1]);
        }

        throw new IllegalArgumentException(sprintf('No enum constant %s::%s', static::class, $name));
    }

    /**
     * 创建枚举值
     *
     * @param string $name 名称
     * @param int $ordinal 序号
     * @param array $arguments 构造参数
     * @return static
     */
    private static function createValue(string $name, int $ordinal, array $arguments) : self
    {
        $instance = new static(...$arguments);
        $instance->name = $name;
        $instance->ordinal = $ordinal;
        self::$values[static::class][$name] = $instance;
        return $instance;
    }

    /**
     * 获取此枚举定义的所有可能类型
     *
     * @return static[]
     */
    final public static function values() : array
    {
        if (isset(self::$allValuesLoaded[static::class])) {
            return self::$values[static::class];
        }

        if (! isset(self::$values[static::class])) {
            self::$values[static::class] = [];
        }

        foreach (self::constants() as $name => $constant) {
            if (array_key_exists($name, self::$values[static::class])) {
                continue;
            }

            static::createValue($name, $constant[0], $constant[1]);
        }

        uasort(self::$values[static::class], function (self $a, self $b) {
            return $a->ordinal() <=> $b->ordinal();
        });

        self::$allValuesLoaded[static::class] = true;
        return self::$values[static::class];
    }

    /**
     * 获取类常量
     *
     * @return array
     */
    private static function constants() : array
    {
        if (isset(self::$constants[static::class])) {
            return self::$constants[static::class];
        }

        self::$constants[static::class] = [];
        $reflectionClass = new ReflectionClass(static::class);
        $ordinal = -1;

        foreach ($reflectionClass->getReflectionConstants() as $reflectionConstant) {
            if (! $reflectionConstant->isProtected()) {
                continue;
            }

            $value = $reflectionConstant->getValue();

            self::$constants[static::class][$reflectionConstant->name] = [
                ++$ordinal,
                is_array($value) ? $value : []
            ];
        }

        return self::$constants[static::class];
    }

    /**
     * 返回此枚举常量的名称，与其枚举声明中声明的完全一致
     *
     * 大多数程序员应该优先使用 __toString() 方法，因为 toString 方法可能返回更友好的名称。
     * 此方法主要用于需要获取精确名称的特殊情况，这些名称不会随版本发布而变化
     *
     * @return string
     */
    final public function name() : string
    {
        return $this->name;
    }

    /**
     * 返回此枚举常量的序号（在其枚举声明中的位置，初始常量被分配为序号零）
     *
     * 大多数程序员不会使用此方法。它是为复杂的基于枚举的数据结构设计的
     *
     * @return int
     */
    final public function ordinal() : int
    {
        return $this->ordinal;
    }

    /**
     * 将此枚举与指定对象进行比较以确定顺序
     *
     * 返回负整数、零或正整数，分别表示此对象小于、等于或大于指定对象
     *
     * 枚举只能与相同类型的其他枚举比较。此方法实现的自然顺序是常量声明的顺序
     *
     * @param self $other 另一个枚举
     * @return int 比较结果
     * @throws MismatchException 如果传递的枚举不是相同类型
     */
    final public function compareTo(self $other) : int
    {
        if (! $other instanceof static) {
            throw new MismatchException(sprintf(
                'The passed enum %s is not of the same type as %s',
                get_class($other),
                static::class
            ));
        }

        return $this->ordinal - $other->ordinal;
    }

    /**
     * 禁止克隆枚举
     *
     * @throws CloneNotSupportedException
     */
    final public function __clone()
    {
        throw new CloneNotSupportedException();
    }

    /**
     * 禁止序列化枚举
     *
     * @throws SerializeNotSupportedException
     */
    final public function __sleep() : array
    {
        throw new SerializeNotSupportedException();
    }

    /**
     * 禁止序列化枚举
     *
     * @throws SerializeNotSupportedException
     */
    final public function __serialize() : array
    {
        throw new SerializeNotSupportedException();
    }

    /**
     * 禁止反序列化枚举
     *
     * @throws UnserializeNotSupportedException
     */
    final public function __wakeup() : void
    {
        throw new UnserializeNotSupportedException();
    }

    /**
     * 禁止反序列化枚举
     *
     * @param mixed $arg 参数
     * @throws UnserializeNotSupportedException
     */
    final public function __unserialize($arg) : void
    {
        throw new UnserializeNotSupportedException();
    }

    /**
     * 将枚举转换为字符串表示
     *
     * 您可以重写此方法以提供更友好的版本
     *
     * @return string
     */
    public function __toString() : string
    {
        return $this->name;
    }
}
