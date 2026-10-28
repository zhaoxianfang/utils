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

    /**
     * 私有构造函数，封装待重试的回调
     *
     * @param Closure $callback 待重试的回调，其参数为当前尝试次数
     */
    private function __construct(Closure $callback)
    {
        $this->callback = $callback;
    }

    /**
     * 创建重试实例
     *
     * 默认参数为：最多 3 次、基础等待 100ms、退避倍数 1.0、不启用抖动；
     * 可通过 times()/sleep()/backoff()/jitter() 链式覆盖。
     *
     * @param Closure $callback 待重试的回调，签名 `function (int $attempt): mixed`
     * @return static
     */
    public static function make(Closure $callback): self
    {
        return new self($callback);
    }

    /**
     * 设置最大重试次数
     *
     * 实际是「最大尝试次数」（含首次调用），传入小于 1 的值会被修正为 1。
     *
     * @param int $maxAttempts 最大尝试次数
     * @return self 当前实例，便于链式调用
     */
    public function times(int $maxAttempts): self
    {
        $this->maxAttempts = max(1, $maxAttempts);
        return $this;
    }

    /**
     * 设置基础等待时间（毫秒）
     *
     * 作为退避计算的基数（第 n 次等待 = 基数 × 倍数^(n-1)），负数会被修正为 0。
     *
     * @param int $milliseconds 基础等待毫秒数
     * @return self 当前实例，便于链式调用
     */
    public function sleep(int $milliseconds): self
    {
        $this->sleepMilliseconds = max(0, $milliseconds);
        return $this;
    }

    /**
     * 设置指数退避倍数
     *
     * 第 n 次重试的等待时间为「基础等待 × 倍数^(n-1)」；小于 1.0 会被修正为 1.0（即固定间隔）。
     *
     * @param float $multiplier 退避倍数
     * @return self 当前实例，便于链式调用
     */
    public function backoff(float $multiplier): self
    {
        $this->backoffMultiplier = max(1.0, $multiplier);
        return $this;
    }

    /**
     * 启用随机抖动，避免惊群效应
     *
     * 开启后实际等待时间按 50%~100% 的随机比例缩放计算出的退避时长。
     *
     * @param bool $enable 是否启用抖动，默认 true
     * @return self 当前实例，便于链式调用
     */
    public function jitter(bool $enable = true): self
    {
        $this->useJitter = $enable;
        return $this;
    }

    /**
     * 自定义重试条件
     *
     * 回调接收「回调返回值 + 当前尝试次数」，返回 true 表示需要继续重试（即使未抛异常）；
     * 未设置时仅在抛异常的情况下重试。
     *
     * @param Closure $callback 重试判定回调，签名 `function (mixed $result, int $attempt): bool`
     * @return self 当前实例，便于链式调用
     */
    public function when(Closure $callback): self
    {
        $this->retryIf = $callback;
        return $this;
    }

    /**
     * 失败时的回调
     *
     * 每次捕获到异常都会调用（含最后一次失败），回调抛出的异常不会被捕获。
     *
     * @param Closure $callback 失败回调，签名 `function (Throwable $e, int $attempt): void`
     * @return self 当前实例，便于链式调用
     */
    public function onFailure(Closure $callback): self
    {
        $this->onFailure = $callback;
        return $this;
    }

    /**
     * 执行重试逻辑
     *
     * 循环最多 maxAttempts 次：回调正常返回且未命中 when() 条件时立即返回结果；
     * 命中 when() 条件或抛出异常则在还有剩余次数时退避等待后重试。
     * 次数耗尽后抛出最后一次捕获的异常。
     *
     * @return mixed 首次成功（或未命中重试条件）时的回调返回值
     *
     * @throws Throwable 全部尝试失败时抛出最后一次捕获的异常
     * @throws RuntimeException 全部尝试因 when() 条件触发而失败时抛出
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
     *
     * 等待时长 = 基础等待 × 倍数^(尝试次数-1)，启用抖动时再乘以 50%~100% 的随机系数；
     * 结果向下取整为微秒后调用 usleep()，时长不大于 0 时直接返回。
     *
     * @param int $attempt 当前尝试次数（从 1 开始，用于计算指数退避）
     * @return void 无返回值
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
