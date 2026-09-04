<?php

/**
 * ============================================================================
 * 一次性执行封装
 * ============================================================================
 *
 * 确保回调只执行一次，后续调用直接返回缓存结果。
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
 * 确保回调只执行一次的封装
 *
 * @example
 * $once = Once::make(fn () => expensiveOperation());
 * $result1 = $once(); // 执行并缓存
 * $result2 = $once(); // 直接返回缓存结果
 */
final class Once
{
    private bool $executed = false;

    private mixed $value = null;

    private function __construct(private Closure $callback)
    {
    }

    public static function make(Closure $callback): self
    {
        return new self($callback);
    }

    /**
     * 执行回调（仅首次真正执行），返回缓存的结果
     *
     * 注意：本实现**不具备跨进程/跨线程安全性**。在并发请求（多进程 FPM）
     * 或协程环境下，多个执行流可能同时进入首次调用；需要全局唯一时请配合
     * `zxf\Utils\Support\Locker` 等互斥机制使用。
     */
    public function __invoke(): mixed
    {
        if (! $this->executed) {
            $this->value = ($this->callback)();
            $this->executed = true;
        }

        return $this->value;
    }

    /**
     * 获取缓存值；尚未执行则返回 null（不会触发执行）
     */
    public function value(): mixed
    {
        return $this->value;
    }

    /**
     * 重置，允许再次执行
     */
    public function reset(): void
    {
        $this->executed = false;
        $this->value = null;
    }

    /**
     * 是否已执行
     */
    public function executed(): bool
    {
        return $this->executed;
    }
}
