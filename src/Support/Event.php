<?php

declare(strict_types=1);

namespace zxf\Utils\Support;

use Closure;

/**
 * 轻量级事件分发器
 *
 * 提供订阅-发布模式的事件管理机制，支持通配符监听、
 * 一次性监听器和监听器优先级排序。
 *
 * @package Support
 * @version 1.0.0
 * @license MIT
 */
class Event
{
    /** @var array<string, array<int, Closure[]>> */
    private array $listeners = [];

    /** @var array<string, Closure[]> */
    private array $wildcards = [];

    /**
     * 注册事件监听器
     *
     * @param string   $event    事件名称，支持 * 通配符（如 user.*）
     * @param Closure  $callback 回调函数，接收事件载荷
     * @param int      $priority 优先级（数值越大越先执行）
     * @return static
     */
    public function listen(string $event, Closure $callback, int $priority = 0): static
    {
        if (str_contains($event, '*')) {
            $this->wildcards[$event][] = $callback;
        } else {
            $this->listeners[$event][$priority][] = $callback;
            krsort($this->listeners[$event]);
        }
        return $this;
    }

    /**
     * 注册一次性监听器（触发后自动移除）
     *
     * @param string  $event    事件名称
     * @param Closure $callback 回调函数
     * @param int     $priority 优先级
     * @return static
     */
    public function once(string $event, Closure $callback, int $priority = 0): static
    {
        $wrapper = function (...$args) use ($event, $callback, &$wrapper): mixed {
            $this->off($event, $wrapper);
            return $callback(...$args);
        };
        return $this->listen($event, $wrapper, $priority);
    }

    /**
     * 触发事件
     *
     * @param string $event  事件名称
     * @param mixed  ...$args 传递给监听器的参数
     * @return array 所有监听器的返回值数组
     */
    public function emit(string $event, mixed ...$args): array
    {
        $results = [];

        // 精确匹配
        if (isset($this->listeners[$event])) {
            foreach ($this->listeners[$event] as $group) {
                foreach ($group as $callback) {
                    $results[] = $callback(...$args);
                }
            }
        }

        // 通配符匹配
        foreach ($this->wildcards as $pattern => $callbacks) {
            if (self::matchWildcard($pattern, $event)) {
                foreach ($callbacks as $callback) {
                    $results[] = $callback(...$args);
                }
            }
        }

        return $results;
    }

    /**
     * 触发事件，若任一监听器返回 false 则停止传播
     *
     * @param string $event  事件名称
     * @param mixed  ...$args 传递给监听器的参数
     * @return mixed 最后一个返回值或 false
     */
    public function emitUntilFalse(string $event, mixed ...$args): mixed
    {
        if (isset($this->listeners[$event])) {
            foreach ($this->listeners[$event] as $group) {
                foreach ($group as $callback) {
                    $result = $callback(...$args);
                    if ($result === false) {
                        return false;
                    }
                }
            }
        }

        foreach ($this->wildcards as $pattern => $callbacks) {
            if (self::matchWildcard($pattern, $event)) {
                foreach ($callbacks as $callback) {
                    $result = $callback(...$args);
                    if ($result === false) {
                        return false;
                    }
                }
            }
        }

        return null;
    }

    /**
     * 移除指定事件的监听器
     *
     * @param string       $event    事件名称
     * @param Closure|null $callback 要移除的特定回调，null 表示移除全部
     * @return static
     */
    public function off(string $event, ?Closure $callback = null): static
    {
        if ($callback === null) {
            unset($this->listeners[$event]);
            return $this;
        }

        if (isset($this->listeners[$event])) {
            foreach ($this->listeners[$event] as $priority => $group) {
                $this->listeners[$event][$priority] = array_values(
                    array_filter($group, static fn (Closure $cb): bool => $cb !== $callback)
                );
            }
        }

        return $this;
    }

    /**
     * 判断事件是否有监听器
     *
     * @param string $event 事件名称
     * @return bool
     */
    public function hasListeners(string $event): bool
    {
        if (isset($this->listeners[$event]) && $this->listeners[$event] !== []) {
            return true;
        }
        foreach ($this->wildcards as $pattern => $callbacks) {
            if ($callbacks !== [] && self::matchWildcard($pattern, $event)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 清空所有监听器
     */
    public function clear(): void
    {
        $this->listeners = [];
        $this->wildcards = [];
    }

    /**
     * 通配符匹配
     */
    private static function matchWildcard(string $pattern, string $subject): bool
    {
        $regex = '/^' . str_replace(['*', '\\?'], ['.*', '.'], preg_quote($pattern, '/')) . '$/';
        return (bool) preg_match($regex, $subject);
    }
}
