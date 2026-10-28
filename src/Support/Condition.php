<?php

/**
 * ============================================================================
 * 条件执行构建器
 * ============================================================================
 *
 * 替代复杂的 if/else 嵌套，提供声明式的条件执行。
 * 支持链式调用和类型安全的条件判断。
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

use Closure;

/**
 * 条件执行构建器
 *
 * 替代复杂的 if/else 嵌套，提供声明式的条件执行
 *
 * @example
 * Condition::make($value)
 *     ->when(fn ($v) => $v > 100, fn () => 'high')
 *     ->when(fn ($v) => $v > 50, fn () => 'medium')
 *     ->default(fn () => 'low')
 *     ->evaluate();
 */
final class Condition
{
    private mixed $value;

    private array $branches = [];

    private ?Closure $default = null;

    private bool $matchFirst = true;

    /**
     * 私有构造函数，创建条件构建器实例
     *
     * @param mixed $value 待判断的源值，供后续 when/equals/in 等条件引用
     */
    private function __construct(mixed $value)
    {
        $this->value = $value;
    }

    /**
     * 创建条件构建器实例
     *
     * @param mixed $value 待判断的源值
     * @return static
     */
    public static function make(mixed $value): self
    {
        return new self($value);
    }

    /**
     * 添加条件分支
     *
     * 按注册顺序登记一条「条件 + 回调」；条件回调接收源值并返回 bool，
     * 命中时以源值为参数执行回调。是否短路由 matchFirst() 控制。
     *
     * @param Closure $condition 条件回调，签名 `function (mixed $value): bool`
     * @param Closure $callback 命中后的处理回调，签名 `function (mixed $value): mixed`
     * @return self 当前实例，便于链式调用
     */
    public function when(Closure $condition, Closure $callback): self
    {
        $this->branches[] = ['condition' => $condition, 'callback' => $callback];
        return $this;
    }

    /**
     * 添加否定条件分支
     *
     * 条件结果为 false 时执行回调，等价于 when() 取反，注册顺序与 when() 一致。
     *
     * @param Closure $condition 条件回调，签名 `function (mixed $value): bool`
     * @param Closure $callback 条件不成立时的处理回调
     * @return self 当前实例，便于链式调用
     */
    public function unless(Closure $condition, Closure $callback): self
    {
        return $this->when(fn ($value) => ! $condition($value), $callback);
    }

    /**
     * 设置默认回调
     *
     * 所有分支均未命中时执行，以源值作为入参；后设置会覆盖先前设置。
     * 未设置默认回调且无分支命中时 evaluate() 返回 null。
     *
     * @param Closure $callback 默认回调，签名 `function (mixed $value): mixed`
     * @return self 当前实例，便于链式调用
     */
    public function default(Closure $callback): self
    {
        $this->default = $callback;
        return $this;
    }

    /**
     * 是否只匹配第一个满足的条件（默认 true）
     *
     * 为 true 时命中首个分支立即返回；为 false 时收集所有命中分支的结果并以数组返回。
     *
     * @param bool $match 是否只取第一个命中结果，默认 true
     * @return self 当前实例，便于链式调用
     */
    public function matchFirst(bool $match = true): self
    {
        $this->matchFirst = $match;
        return $this;
    }

    /**
     * 执行条件判断并返回结果
     *
     * 依次求值各分支条件：matchFirst 为 true 时返回首个命中分支的回调结果；
     * 为 false 时返回全部命中结果的数组；均未命中时执行默认回调，无默认回调则返回 null。
     *
     * @return mixed 命中分支的结果、命中结果数组或默认回调的返回值
     */
    public function evaluate(): mixed
    {
        $results = [];

        foreach ($this->branches as $branch) {
            if ($branch['condition']($this->value)) {
                $result = $branch['callback']($this->value);

                if ($this->matchFirst) {
                    return $result;
                }

                $results[] = $result;
            }
        }

        if (! empty($results)) {
            return $this->matchFirst ? $results[0] : $results;
        }

        return $this->default !== null ? ($this->default)($this->value) : null;
    }

    /**
     * 匹配值等于指定值时执行
     *
     * 使用全等（===）比较，因此类型不同不会命中。
     *
     * @param mixed $compare 期望相等的目标值
     * @param Closure $callback 命中后的处理回调
     * @return self 当前实例，便于链式调用
     */
    public function equals(mixed $compare, Closure $callback): self
    {
        return $this->when(fn ($value) => $value === $compare, $callback);
    }

    /**
     * 匹配值在数组中时执行
     *
     * 使用 in_array() 严格模式比较，避免类型隐式转换导致的误命中。
     *
     * @param array $values 候选值集合
     * @param Closure $callback 命中后的处理回调
     * @return self 当前实例，便于链式调用
     */
    public function in(array $values, Closure $callback): self
    {
        return $this->when(fn ($value) => in_array($value, $values, true), $callback);
    }

    /**
     * 匹配值类型时执行
     *
     * 与 gettype() 的返回值比较（如 'integer'、'string'、'array'），需注意是类型名而非类名。
     *
     * @param string $type 期望的类型名（gettype() 返回值）
     * @param Closure $callback 命中后的处理回调
     * @return self 当前实例，便于链式调用
     */
    public function type(string $type, Closure $callback): self
    {
        return $this->when(fn ($value) => gettype($value) === $type, $callback);
    }

    /**
     * 匹配值 instanceof 时执行
     *
     * 值需为对象且是 $class 或其子类的实例才会命中；非对象值不会命中。
     *
     * @param string $class 目标类名或接口名（含命名空间）
     * @param Closure $callback 命中后的处理回调
     * @return self 当前实例，便于链式调用
     */
    public function instance(string $class, Closure $callback): self
    {
        return $this->when(fn ($value) => $value instanceof $class, $callback);
    }

    /**
     * 快捷执行
     *
     * 按 $cases 的「键 => 回调」批量注册 equals 分支后立即求值；
     * 非可调用值会被跳过，$default 存在时作为兜底分支。
     *
     * @param mixed $value 待判断的源值
     * @param array $cases 键为期望值、值为命中回调的映射
     * @param Closure|null $default 全部未命中时的默认回调
     * @return mixed 命中分支的结果或默认回调返回值
     */
    public static function match(mixed $value, array $cases, ?Closure $default = null): mixed
    {
        $condition = self::make($value);

        foreach ($cases as $caseValue => $callback) {
            if (is_callable($callback)) {
                $condition->equals($caseValue, $callback);
            }
        }

        if ($default !== null) {
            $condition->default($default);
        }

        return $condition->evaluate();
    }
}
