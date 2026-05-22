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

use Closure;

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
     */
    public function __call(string $method, array $args): static
    {
        if (property_exists($this, $method)) {
            $this->{$method} = $args[0] ?? null;
            return $this;
        }

        throw new \BadMethodCallException(sprintf('Method %s::%s() does not exist.', static::class, $method));
    }

    /**
     * 使用数组批量设置属性
     */
    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
        return $this;
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * 创建实例并填充数据
     */
    public static function make(array $attributes = []): static
    {
        return (new static())->fill($attributes);
    }

    /**
     * 条件执行
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
     * 条件设置属性
     */
    public function whenFilled(string $property, mixed $value): static
    {
        if ($value !== null && $value !== '' && $value !== []) {
            if (property_exists($this, $property)) {
                $this->{$property} = $value;
            }
        }
        return $this;
    }
}
