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

    private function __construct(mixed $value)
    {
        $this->value = $value;
    }

    public static function make(mixed $value): self
    {
        return new self($value);
    }

    /**
     * 添加条件分支
     */
    public function when(Closure $condition, Closure $callback): self
    {
        $this->branches[] = ['condition' => $condition, 'callback' => $callback];
        return $this;
    }

    /**
     * 添加否定条件分支
     */
    public function unless(Closure $condition, Closure $callback): self
    {
        return $this->when(fn ($value) => ! $condition($value), $callback);
    }

    /**
     * 设置默认回调
     */
    public function default(Closure $callback): self
    {
        $this->default = $callback;
        return $this;
    }

    /**
     * 是否只匹配第一个满足的条件（默认 true）
     */
    public function matchFirst(bool $match = true): self
    {
        $this->matchFirst = $match;
        return $this;
    }

    /**
     * 执行条件判断并返回结果
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
     */
    public function equals(mixed $compare, Closure $callback): self
    {
        return $this->when(fn ($value) => $value === $compare, $callback);
    }

    /**
     * 匹配值在数组中时执行
     */
    public function in(array $values, Closure $callback): self
    {
        return $this->when(fn ($value) => in_array($value, $values, true), $callback);
    }

    /**
     * 匹配值类型时执行
     */
    public function type(string $type, Closure $callback): self
    {
        return $this->when(fn ($value) => gettype($value) === $type, $callback);
    }

    /**
     * 匹配值 instanceof 时执行
     */
    public function instance(string $class, Closure $callback): self
    {
        return $this->when(fn ($value) => $value instanceof $class, $callback);
    }

    /**
     * 快捷执行
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
