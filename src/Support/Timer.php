<?php

declare(strict_types=1);

namespace zxf\Utils\Support;

use Closure;
use InvalidArgumentException;
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
     *
     * 等价于 new self()，内部使用 hrtime(true) 记录纳秒级起点，不带超时阈值。
     *
     * @return self 已开始计时、未设置超时的计时器
     */
    public static function start(): self
    {
        return new self();
    }

    /**
     * 创建带超时限制的计时器
     *
     * 超时阈值按纳秒保存（超出 int 范围时夹取到 PHP_INT_MAX），计时从创建时开始。
     *
     * @param float $seconds 超时时间（秒，必须 >= 0）
     * @return self 已开始计时并设置超时阈值的计时器
     *
     * @throws InvalidArgumentException 超时时间为负数或 NaN 时抛出
     */
    public static function withTimeout(float $seconds): self
    {
        if ($seconds < 0 || is_nan($seconds)) {
            throw new InvalidArgumentException('超时时间不能为负数或非法值');
        }

        // 纳秒值可能超出 int 范围（约 292 年），此处夹取到 PHP_INT_MAX
        $nanoseconds = $seconds * 1e9;
        $timer = new self();
        $timer->timeoutNs = $nanoseconds >= (float) PHP_INT_MAX
            ? PHP_INT_MAX
            : (int) $nanoseconds;

        return $timer;
    }

    /**
     * 构造函数，创建计时器并立即开始计时
     */
    public function __construct()
    {
        $this->start = hrtime(true);
    }

    /**
     * 重置计时器
     *
     * 把起点改为当前时刻，已设置的超时阈值保持不变（即从重置时刻重新计算超时）。
     *
     * @return self 当前实例，便于链式调用
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
     * 未设置超时阈值（如 start() 创建）时恒为 false；否则按「已流逝时间 >= 阈值」判断。
     *
     * @return bool 已超过阈值返回 true，未超时或未设置阈值返回 false
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
     * 结果取决于 isTimedOut()：未设置阈值时不会抛异常。
     *
     * @param string $message 超时时抛出的异常消息，默认「操作超时」
     * @return void 无返回值，未超时直接返回
     *
     * @throws RuntimeException 已超时时抛出
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
     * 注意：如果睡眠被信号中断，time_nanosleep() 会返回剩余的纳秒数数组，
     * 此时必须继续补足剩余时间，否则睡眠时间会远短于预期。
     *
     * @param float $seconds 睡眠时间（支持小数，必须 >= 0）
     *
     * @throws InvalidArgumentException 睡眠时间为负数时抛出
     * @throws RuntimeException 睡眠被信号中断且无法完成时抛出
     */
    public static function sleep(float $seconds): void
    {
        if ($seconds < 0 || is_nan($seconds) || is_infinite($seconds)) {
            throw new InvalidArgumentException('睡眠时间不能为负数或非法值');
        }

        if ($seconds === 0.0) {
            return;
        }

        $remainingSeconds = (int) $seconds;
        $remainingNanos = (int) round(($seconds - $remainingSeconds) * 1e9);

        // 进位修正：浮点误差可能让 nanos 恰好等于 1e9
        if ($remainingNanos >= 1000000000) {
            $remainingSeconds++;
            $remainingNanos -= 1000000000;
        }

        $retries = 0;

        while (true) {
            $result = time_nanosleep($remainingSeconds, $remainingNanos);

            if ($result === true) {
                return;
            }

            if (! is_array($result)) {
                throw new RuntimeException('高精度睡眠失败');
            }

            // 被信号中断，继续补足剩余时间
            $remainingSeconds = (int) ($result['seconds'] ?? 0);
            $remainingNanos = (int) ($result['nanoseconds'] ?? 0);

            if ($remainingSeconds <= 0 && $remainingNanos <= 0) {
                return;
            }

            if (++$retries > 1000) {
                throw new RuntimeException('高精度睡眠被反复中断，无法完成');
            }
        }
    }

    /**
     * 创建并立即开始的高精度睡眠别名（语义更清晰）
     *
     * @param float $seconds 睡眠时间（秒）
     */
    public static function usleep(float $seconds): void
    {
        self::sleep($seconds);
    }
}
