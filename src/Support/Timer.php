<?php

declare(strict_types=1);

namespace zxf\Utils\Support;

use Closure;
use RuntimeException;

/**
 * 计时器与超时控制工具
 *
 * 提供代码块执行计时、超时检测、间隔等待等功能。
 * 基于 hrtime() 实现纳秒级精度，适用于性能分析和超时控制场景。
 *
 * @package Support
 * @version 1.0.0
 * @license MIT
 */
class Timer
{
    /** @var float|int 计时开始时间（纳秒） */
    private float|int $start;

    /** @var float|int|null 超时阈值（纳秒） */
    private float|int|null $timeoutNs = null;

    /**
     * 创建并立即开始计时的新实例
     */
    public static function start(): self
    {
        return new self();
    }

    /**
     * 创建带超时限制的计时器
     *
     * @param float $seconds 超时时间（秒）
     */
    public static function withTimeout(float $seconds): self
    {
        $timer = new self();
        $timer->timeoutNs = (int) ($seconds * 1e9);
        return $timer;
    }

    public function __construct()
    {
        $this->start = hrtime(true);
    }

    /**
     * 重置计时器
     */
    public function reset(): self
    {
        $this->start = hrtime(true);
        return $this;
    }

    /**
     * 获取已流逝的秒数
     *
     * @param int $precision 小数精度（默认 6）
     * @return float
     */
    public function elapsed(int $precision = 6): float
    {
        $elapsed = (hrtime(true) - $this->start) / 1e9;
        return round($elapsed, $precision);
    }

    /**
     * 获取已流逝的毫秒数
     *
     * @return float
     */
    public function elapsedMs(): float
    {
        return (hrtime(true) - $this->start) / 1e6;
    }

    /**
     * 检查是否已超时
     *
     * @return bool
     */
    public function isTimedOut(): bool
    {
        if ($this->timeoutNs === null) {
            return false;
        }
        return (hrtime(true) - $this->start) >= $this->timeoutNs;
    }

    /**
     * 若已超时则抛出异常
     *
     * @throws RuntimeException
     */
    public function throwIfTimedOut(string $message = '操作超时'): void
    {
        if ($this->isTimedOut()) {
            throw new RuntimeException($message);
        }
    }

    /**
     * 测量闭包的执行时间
     *
     * @template T
     * @param Closure(): T $callback 待测量的闭包
     * @return array{result: T, seconds: float, ms: float}
     */
    public static function measure(Closure $callback): array
    {
        $start = hrtime(true);
        $result = $callback();
        $ns = hrtime(true) - $start;

        return [
            'result'  => $result,
            'seconds' => round($ns / 1e9, 6),
            'ms'      => round($ns / 1e6, 3),
        ];
    }

    /**
     * 高精度睡眠（秒）
     *
     * 使用 time_nanosleep 实现亚秒级精度睡眠。
     *
     * @param float $seconds 睡眠时间（支持小数）
     */
    public static function sleep(float $seconds): void
    {
        $s = (int) $seconds;
        $ns = (int) (($seconds - $s) * 1e9);
        time_nanosleep($s, $ns);
    }
}
