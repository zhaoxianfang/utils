<?php

/**
 * ============================================================================
 * 性能基准测试工具
 * ============================================================================
 *
 * 提供单次测量、多次平均、多方案对比等性能测试功能。
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
 * 性能基准测试工具
 *
 * @example
 * $result = Benchmark::measure(fn () => heavyComputation());
 * echo $result->time();     // 执行时间（毫秒）
 * echo $result->memory();   // 内存峰值（MB）
 */
final readonly class BenchmarkResult
{
    public function __construct(
        private float $timeMs,
        private float $memoryMb,
        private mixed $value,
        private int $peakMemoryBytes
    ) {
    }

    public function time(): float
    {
        return $this->timeMs;
    }

    public function memory(): float
    {
        return $this->memoryMb;
    }

    public function peakMemoryBytes(): int
    {
        return $this->peakMemoryBytes;
    }

    public function value(): mixed
    {
        return $this->value;
    }

    public function toArray(): array
    {
        return [
            'time_ms' => $this->timeMs,
            'memory_mb' => $this->memoryMb,
            'peak_memory_bytes' => $this->peakMemoryBytes,
            'value' => $this->value,
        ];
    }
}

final class Benchmark
{
    /**
     * 测量单次执行
     */
    public static function measure(Closure $callback): BenchmarkResult
    {
        $startMemory = memory_get_usage(true);
        $startTime = hrtime(true);

        $value = $callback();

        $endTime = hrtime(true);
        $peakMemory = memory_get_peak_usage(true);

        $timeMs = ($endTime - $startTime) / 1e6;
        $memoryMb = ($peakMemory - $startMemory) / 1024 / 1024;

        return new BenchmarkResult($timeMs, $memoryMb, $value, $peakMemory);
    }

    /**
     * 多次执行取平均
     */
    public static function average(Closure $callback, int $times = 10): array
    {
        $times = max(1, $times);
        $results = [];
        $totalTime = 0;
        $totalMemory = 0;

        for ($i = 0; $i < $times; $i++) {
            $result = self::measure($callback);
            $results[] = $result;
            $totalTime += $result->time();
            $totalMemory += $result->memory();
        }

        return [
            'times' => $times,
            'avg_time_ms' => round($totalTime / $times, 4),
            'avg_memory_mb' => round($totalMemory / $times, 4),
            'min_time_ms' => round(min(array_map(fn (BenchmarkResult $r) => $r->time(), $results)), 4),
            'max_time_ms' => round(max(array_map(fn (BenchmarkResult $r) => $r->time(), $results)), 4),
            'results' => array_map(fn (BenchmarkResult $r) => $r->toArray(), $results),
        ];
    }

    /**
     * 比较多个回调的性能
     *
     * @param array<string, Closure> $callbacks
     */
    public static function compare(array $callbacks, int $times = 10): array
    {
        $results = [];

        foreach ($callbacks as $name => $callback) {
            $results[$name] = self::average($callback, $times);
        }

        // 排序：按平均时间升序
        uasort($results, fn (array $a, array $b) => $a['avg_time_ms'] <=> $b['avg_time_ms']);

        return $results;
    }
}
