<?php

/**
 * ============================================================================
 * 重试机制封装
 * ============================================================================
 *
 * 支持指数退避、自定义条件重试、抖动策略的通用重试工具。
 *
 * @package     zxf\Utils\Support
 * @author      zxf\Utils
 * @version     1.0.0
 * @license     MIT
 * @link        https://github.com/zhaoxianfang/utils
 *
 * @example
 * $result = Retry::make(fn () => fetchData())
 *     ->times(3)
 *     ->sleep(100)
 *     ->backoff(2)
 *     ->run();
 */

declare(strict_types=1);

namespace zxf\Utils\Support;

use Closure;
use RuntimeException;
use Throwable;

/**
 * 重试机制封装
 *
 * 支持指数退避、自定义条件重试、抖动策略
 *
 * @example
 * $result = Retry::make(fn () => fetchData())
 *     ->times(3)
 *     ->sleep(100)
 *     ->backoff(2)
 *     ->run();
 */
final class Retry
{
    private Closure $callback;

    private int $maxAttempts = 3;

    private int $sleepMilliseconds = 100;

    private float $backoffMultiplier = 1.0;

    private bool $useJitter = false;

    private ?Closure $retryIf = null;

    private ?Closure $onFailure = null;

    private function __construct(Closure $callback)
    {
        $this->callback = $callback;
    }

    /**
     * 创建重试实例
     */
    public static function make(Closure $callback): self
    {
        return new self($callback);
    }

    /**
     * 设置最大重试次数
     */
    public function times(int $maxAttempts): self
    {
        $this->maxAttempts = max(1, $maxAttempts);
        return $this;
    }

    /**
     * 设置基础等待时间（毫秒）
     */
    public function sleep(int $milliseconds): self
    {
        $this->sleepMilliseconds = max(0, $milliseconds);
        return $this;
    }

    /**
     * 设置指数退避倍数
     */
    public function backoff(float $multiplier): self
    {
        $this->backoffMultiplier = max(1.0, $multiplier);
        return $this;
    }

    /**
     * 启用随机抖动，避免惊群效应
     */
    public function jitter(bool $enable = true): self
    {
        $this->useJitter = $enable;
        return $this;
    }

    /**
     * 自定义重试条件
     */
    public function when(Closure $callback): self
    {
        $this->retryIf = $callback;
        return $this;
    }

    /**
     * 失败时的回调
     */
    public function onFailure(Closure $callback): self
    {
        $this->onFailure = $callback;
        return $this;
    }

    /**
     * 执行重试逻辑
     */
    public function run(): mixed
    {
        $attempts = 0;
        $lastException = null;

        while ($attempts < $this->maxAttempts) {
            $attempts++;

            try {
                $result = ($this->callback)($attempts);

                if ($this->retryIf !== null && ($this->retryIf)($result, $attempts)) {
                    if ($attempts < $this->maxAttempts) {
                        // 注意：此处必须调用真正执行等待的 doSleep()，
                        // 而非 sleep()（sleep 只是设置基础等待毫秒数的 setter），
                        // 否则 retryIf 命中时不会退避，且若回调始终触发 retryIf 会立即死循环。
                        $this->doSleep($attempts);
                    }
                    continue;
                }

                return $result;
            } catch (Throwable $e) {
                $lastException = $e;

                if ($this->onFailure !== null) {
                    ($this->onFailure)($e, $attempts);
                }

                if ($attempts < $this->maxAttempts) {
                    $this->doSleep($attempts);
                }
            }
        }

        throw $lastException ?? new RuntimeException(
            sprintf('重试 %d 次后仍然失败', $this->maxAttempts)
        );
    }

    /**
     * 计算并执行等待
     */
    private function doSleep(int $attempt): void
    {
        $delay = $this->sleepMilliseconds * ($this->backoffMultiplier ** ($attempt - 1));

        if ($this->useJitter) {
            // 抖动到基础延迟的 50% ~ 100%，避免多实例同时重试造成惊群
            $delay *= 0.5 + random_int(0, 1000) / 2000;
        }

        $delay = max(0.0, $delay);

        if ($delay <= 0) {
            return;
        }

        // 必须显式取整：usleep() 是内部函数，即使传入整数值的 float
        // （如 100.0）在 strict_types 下也会抛 TypeError。
        // 这一点与用户态 int 参数不同，极易踩坑。
        usleep((int) round($delay * 1000));
    }
}
