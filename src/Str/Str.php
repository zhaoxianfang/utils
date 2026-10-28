<?php

declare(strict_types=1);

namespace zxf\Utils\Str;

use BadMethodCallException;
use Stringable;

/**
 * 流式字符串包装器
 *
 * 把 StringHelper 的静态方法包装成可链式调用的对象接口，
 * 每次调用都以对象持有的字符串作为首个参数，返回值按下列规则处理：
 *  - 返回 string → 包装成新的 Str 实例，可继续链式调用
 *  - 返回其它类型（bool/int/float/array 等）→ 直接返回原值，终止链式调用
 *
 *  u_str('foo-bar')->camel()->upper();          // 链式转换
 *  u_str('hello world')->slug();                // 'hello-world'
 *  u_str('abc')->contains('b');                 // true（终止链式）
 *  (string) u_str('foo-bar')->camel();          // 'fooBar'
 *
 * @package     zxf\Utils\Str
 * @author      zxf\Utils
 * @version     1.0.0
 * @license     MIT
 * @link        https://github.com/zhaoxianfang/utils
 *
 * @see StringHelper 底层静态方法集合
 * @see u_str()      便捷构造函数
 */
final class Str implements Stringable
{
    /** @var string 当前持有的字符串 */
    private string $value;

    /**
     * @param string $value 初始字符串
     */
    public function __construct(string $value = '')
    {
        $this->value = $value;
    }

    /**
     * 获取当前持有的字符串
     *
     * @return string 当前持有的字符串
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * 字符串化（对象当作字符串使用时返回所持有的字符串）
     *
     * @return string 当前持有的字符串
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * 判断两个字符串是否相等
     *
     * @param string $other 待比较的字符串
     * @return bool 完全相同（含大小写）时返回 true
     */
    public function is(string $other): bool
    {
        return $this->value === $other;
    }

    /**
     * 当前字符串是否为空（不含任何字符，空白字符视为非空）
     *
     * @return bool 为空字符串时返回 true
     */
    public function isEmpty(): bool
    {
        return $this->value === '';
    }

    /**
     * 获取字符串长度（字符数，多字节安全）
     *
     * @return int 字符数
     */
    public function length(): int
    {
        return mb_strlen($this->value);
    }

    /**
     * 转换为字符数组
     *
     * 按「字符」而非字节拆分（依赖 mb_str_split），因此中文等多字节字符会
     * 各自成为一个元素；空字符串返回空数组，结果下标从 0 连续递增。
     *
     * @return list<string> 字符数组，空串时为 []
     */
    public function chars(): array
    {
        return $this->value === '' ? [] : mb_str_split($this->value);
    }

    /**
     * 把方法调用转发到 StringHelper 的同名静态方法
     *
     * @param array<int, mixed> $arguments
     *
     * @return mixed 字符串结果包装为新的 Str 实例（可继续链式调用），其它结果原样返回
     *
     * @throws BadMethodCallException 底层不存在对应静态方法，或首个参数不是字符串时抛出
     */
    public function __call(string $method, array $arguments): mixed
    {
        if (! method_exists(StringHelper::class, $method)) {
            throw new BadMethodCallException(sprintf(
                '方法 %s::%s() 不存在（可选方法：%s）',
                static::class,
                $method,
                implode(', ', get_class_methods(StringHelper::class))
            ));
        }

        $result = StringHelper::{$method}($this->value, ...$arguments);

        // 字符串结果继续包装，便于链式调用；其它结果直接返回
        return is_string($result) ? new self($result) : $result;
    }

    /**
     * 静态转发：Str::camel('foo-bar') 等价于 u_str('foo-bar')->camel()
     *
     * @param array<int, mixed> $arguments
     *
     * @return mixed 字符串结果包装为新的 Str 实例（可继续链式调用），其它结果原样返回
     *
     * @throws BadMethodCallException 首个参数不是字符串时抛出
     */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        if ($arguments === [] || ! is_string($arguments[0])) {
            throw new BadMethodCallException(sprintf(
                '静态调用 %s::%s() 的首个参数必须是字符串',
                static::class,
                $method
            ));
        }

        $value = array_shift($arguments);

        return (new self($value))->{$method}(...$arguments);
    }
}
