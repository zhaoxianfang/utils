<?php

/**
 * ============================================================================
 * 速率限制器
 * ============================================================================
 *
 * 简单的内存速率限制器，适用于单进程内的速率控制，如 API 限流、爬虫控制等。
 * 基于滑动窗口算法实现。
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

use RuntimeException;

/**
 * 简单的内存速率限制器
 *
 * 适用于单进程内的速率控制，如 API 限流、爬虫控制等
 *
 * @example
 * $limiter = RateLimiter::make('api', 60, 60); // 60秒内最多60次
 * if ($limiter->attempt()) {
 *     // 允许执行
 * } else {
 *     // 触发限流
 * }
 */
final class RateLimiter
{
    private static array $store = [];

    private function __construct(
        private string $key,
        private int $maxAttempts,
        private int $decaySeconds
    ) {
    }

    /**
     * 创建限流器实例
     */
    public static function make(string $key, int $maxAttempts, int $decaySeconds = 60): self
    {
        return new self($key, $maxAttempts, $decaySeconds);
    }

    /**
     * 尝试执行，成功返回 true
     */
    public function attempt(): bool
    {
        $this->clean();

        if ($this->tooManyAttempts()) {
            return false;
        }

        $this->hit();
        return true;
    }

    /**
     * 是否超过限制
     */
    public function tooManyAttempts(): bool
    {
        $this->clean();
        return count(self::$store[$this->key] ?? []) >= $this->maxAttempts;
    }

    /**
     * 剩余可尝试次数
     */
    public function remaining(): int
    {
        $this->clean();
        return max(0, $this->maxAttempts - count(self::$store[$this->key] ?? []));
    }

    /**
     * 距离重置的秒数
     */
    public function availableIn(): int
    {
        $timestamps = self::$store[$this->key] ?? [];
        if (empty($timestamps)) {
            return 0;
        }

        $oldest = min($timestamps);
        return max(0, (int) ($oldest + $this->decaySeconds - microtime(true)));
    }

    /**
     * 记录一次尝试
     */
    public function hit(): self
    {
        self::$store[$this->key][] = microtime(true);
        return $this;
    }

    /**
     * 清空当前 key 的记录
     */
    public function clear(): self
    {
        unset(self::$store[$this->key]);
        return $this;
    }

    /**
     * 重置所有限流记录
     */
    public static function resetAll(): void
    {
        self::$store = [];
    }

    /**
     * 清理过期的记录
     */
    private function clean(): void
    {
        if (! isset(self::$store[$this->key])) {
            return;
        }

        $cutoff = microtime(true) - $this->decaySeconds;
        self::$store[$this->key] = array_values(array_filter(
            self::$store[$this->key],
            fn (float $timestamp) => $timestamp > $cutoff
        ));
    }

    /**
     * 等待直到允许执行（阻塞）
     *
     * @throws RuntimeException 如果超时
     */
    public function block(int $timeout = 0): bool
    {
        $start = microtime(true);

        while (! $this->attempt()) {
            if ($timeout > 0 && (microtime(true) - $start) >= $timeout) {
                throw new RuntimeException('Rate limiter block timeout exceeded');
            }

            usleep(100000); // 100ms
        }

        return true;
    }
}
