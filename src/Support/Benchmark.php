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
 * 性能基准测试结果容器
 *
 * 以只读形式保存单次测量的耗时、内存与返回值，便于后续取值或导出。
 *
 * @example
 * $result = Benchmark::measure(fn () => heavyComputation());
 * echo $result->time();     // 执行时间（毫秒）
 * echo $result->memory();   // 内存峰值（MB）
 */
final readonly class BenchmarkResult
{
    /**
     * 构造测量结果
     *
     * @param float $timeMs 执行耗时（毫秒）
     * @param float $memoryMb 相对内存增量（MB）
     * @param mixed $value 被测回调的返回值
     * @param int $peakMemoryBytes 内存峰值（字节）
     */
    public function __construct(
        private float $timeMs,
        private float $memoryMb,
        private mixed $value,
        private int $peakMemoryBytes
    ) {
    }

    /**
     * 获取执行耗时（毫秒）
     *
     * @return float 本次测量的耗时，单位毫秒
     */
    public function time(): float
    {
        return $this->timeMs;
    }

    /**
     * 获取相对内存增量（MB）
     *
     * @return float 本次测量的内存增量，单位 MB
     */
    public function memory(): float
    {
        return $this->memoryMb;
    }

    /**
     * 获取内存峰值（字节）
     *
     * @return int 本次测量过程中的内存峰值，单位字节
     */
    public function peakMemoryBytes(): int
    {
        return $this->peakMemoryBytes;
    }

    /**
     * 获取被测回调的返回值
     *
     * @return mixed 被测回调 return 的值
     */
    public function value(): mixed
    {
        return $this->value;
    }

    /**
     * 将测量结果导出为关联数组
     *
     * @return array{time_ms: float, memory_mb: float, peak_memory_bytes: int, value: mixed} 结果映射
     */
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

/**
 * 性能基准测试工具
 *
 * 提供单次测量、多次平均、多方案对比等性能测试能力。
 *
 * @package     zxf\Utils\Support
 * @author      zxf\Utils
 * @version     1.0.0
 * @license     MIT
 * @link        https://github.com/zhaoxianfang/utils
 *
 * @example
 * // 单次测量
 * $r = Benchmark::measure(fn () => usleep(1000));
 * echo $r->time(); // 毫秒
 *
 * // 多方案对比（各执行 100 次取平均，按耗时升序）
 * $report = Benchmark::compare([
 *     'foreach' => fn () => /* ... *\/,
 *     'array_map' => fn () => /* ... *\/,
 * ], 100);
 */
final class Benchmark
{
    /**
     * 测量单次执行的耗时与内存
     *
     * 使用 hrtime 取得高精度时间戳（纳秒），内存以回调前后
     * 实际分配量（memory_get_usage(true)）之差估算。
     *
     * @param Closure $callback 待测回调
     * @return BenchmarkResult 包含耗时、内存与返回值的测量结果
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
     * 多次执行回调并汇总平均/最值等统计指标
     *
     * @param Closure $callback 待测回调
     * @param int $times 执行次数，若传入小于等于 0 会被修正为 1
     * @return array{times: int, avg_time_ms: float, avg_memory_mb: float, min_time_ms: float, max_time_ms: float, results: array} 统计结果
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
     * 比较多个命名回调的性能并排序
     *
     * 每个回调都会先经 average() 取统计值，最后按平均耗时升序排列，
     * 便于直观看出哪个方案更快。
     *
     * @param array<string, Closure> $callbacks 以名称为键、回调为值的方案集合
     * @param int $times 每个方案的执行次数
     * @return array<string, array> 按平均耗时升序的各方案统计结果
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
