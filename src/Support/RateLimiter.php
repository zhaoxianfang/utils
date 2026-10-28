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

    /**
     * 私有构造函数，初始化限流器配置
     *
     * @param string $key          限流键名，用于区分不同的限流场景
     * @param int    $maxAttempts 时间窗口内允许的最大尝试次数
     * @param int    $decaySeconds 滑动窗口长度（秒）
     */
    private function __construct(
        private string $key,
        private int $maxAttempts,
        private int $decaySeconds
    ) {
    }

    /**
     * 创建速率限制器实例
     *
     * @param string $key          限流键名，用于区分不同的限流场景
     * @param int    $maxAttempts 时间窗口内允许的最大尝试次数
     * @param int    $decaySeconds 滑动窗口长度（秒），默认 60
     * @return static
     */
    public static function make(string $key, int $maxAttempts, int $decaySeconds = 60): self
    {
        return new self($key, $maxAttempts, $decaySeconds);
    }

    /**
     * 尝试执行，成功返回 true
     *
     * 先清理过期记录，未超限则计一次尝试并返回 true；已超限直接返回 false（不计数）。
     *
     * @return bool 本次尝试被允许返回 true，被限流返回 false
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
     *
     * 先清理窗口外的记录，再判断窗口内次数是否已达到上限（等于上限即视为超限）。
     *
     * @return bool 已达上限返回 true，否则返回 false
     */
    public function tooManyAttempts(): bool
    {
        $this->clean();
        return count(self::$store[$this->key] ?? []) >= $this->maxAttempts;
    }

    /**
     * 剩余可尝试次数
     *
     * 先清理过期记录，再用上限减去当前窗口内次数，最小为 0。
     *
     * @return int 本窗口内还可尝试的次数（0 表示已限流）
     */
    public function remaining(): int
    {
        $this->clean();
        return max(0, $this->maxAttempts - count(self::$store[$this->key] ?? []));
    }

    /**
     * 距离重置的秒数
     *
     * 以窗口内最早的一次记录为基准，计算其滑出窗口还需多少秒；
     * 无记录时返回 0。注意此处不清理过期记录。
     *
     * @return int 距离可再次尝试的剩余秒数（向下取整），已可尝试时为 0
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
     *
     * 直接向当前 key 追加一条当前时间戳，不做过期清理与上限校验。
     *
     * @return self 当前实例，便于链式调用
     */
    public function hit(): self
    {
        self::$store[$this->key][] = microtime(true);
        return $this;
    }

    /**
     * 清空当前 key 的记录
     *
     * 仅移除本实例 key 对应的数据，其他 key 的记录不受影响。
     *
     * @return self 当前实例，便于链式调用
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
     *
     * 丢弃时间戳早于「当前时间 - 窗口长度」的条目，并重建为连续索引；
     * 当前 key 无数据时直接返回。
     *
     * @return void 无返回值
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
     * 每 100ms 重试一次 attempt()，成功即返回 true；
     * $timeout 为 0 时无限等待，超时则抛异常。
     *
     * @param int $timeout 最长等待秒数，0 表示不限制
     * @return bool 获取到执行资格返回 true
     *
     * @throws RuntimeException 超过等待时限仍未获得执行资格时抛出
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
