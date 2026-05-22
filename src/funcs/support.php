<?php

declare(strict_types=1);

use zxf\Utils\Support\Pipeline;
use zxf\Utils\Support\Result;
use zxf\Utils\Support\Retry;
use zxf\Utils\Support\Benchmark;
use zxf\Utils\Support\Once;
use zxf\Utils\Support\RateLimiter;

if (! function_exists('u_pipeline')) {
    /**
     * 创建数据流水线
     */
    function u_pipeline(mixed $data): Pipeline
    {
        return Pipeline::make($data);
    }
}

if (! function_exists('u_ok')) {
    /**
     * 创建成功的 Result
     */
    function u_ok(mixed $value): Result
    {
        return Result::ok($value);
    }
}

if (! function_exists('u_err')) {
    /**
     * 创建失败的 Result
     */
    function u_err(string|\Throwable $error): Result
    {
        return Result::err($error);
    }
}

if (! function_exists('u_retry')) {
    /**
     * 重试执行回调
     *
     * @param callable $callback
     * @param int $times
     * @param int $sleep 毫秒
     */
    function u_retry(callable $callback, int $times = 3, int $sleep = 100): mixed
    {
        return Retry::make($callback)
            ->times($times)
            ->sleep($sleep)
            ->run();
    }
}

if (! function_exists('u_benchmark')) {
    /**
     * 性能测试
     */
    function u_benchmark(callable $callback): \zxf\Utils\Support\BenchmarkResult
    {
        return Benchmark::measure($callback);
    }
}

if (! function_exists('u_once')) {
    /**
     * 确保回调只执行一次
     */
    function u_once(callable $callback): Once
    {
        return Once::make($callback);
    }
}

if (! function_exists('u_tap')) {
    /**
     * 对值执行回调后返回原值
     */
    function u_tap(mixed $value, callable $callback): mixed
    {
        $callback($value);
        return $value;
    }
}

if (! function_exists('u_with')) {
    /**
     * 对值执行回调并返回回调结果
     */
    function u_with(mixed $value, callable $callback): mixed
    {
        return $callback($value);
    }
}

if (! function_exists('u_value')) {
    /**
     * 返回值或执行回调
     */
    function u_value(mixed $value, mixed ...$args): mixed
    {
        return $value instanceof Closure ? $value(...$args) : $value;
    }
}

if (! function_exists('u_throw_if')) {
    /**
     * 条件抛出异常
     */
    function u_throw_if(bool $condition, string|\Throwable $exception, string $message = ''): void
    {
        if ($condition) {
            throw $exception instanceof \Throwable ? $exception : new $exception($message);
        }
    }
}

if (! function_exists('u_throw_unless')) {
    /**
     * 条件不成立时抛出异常
     */
    function u_throw_unless(bool $condition, string|\Throwable $exception, string $message = ''): void
    {
        u_throw_if(! $condition, $exception, $message);
    }
}

if (! function_exists('u_blank')) {
    /**
     * 判断值是否为空（null, '', [], '0', 0, false）
     */
    function u_blank(mixed $value): bool
    {
        return match (true) {
            is_null($value) => true,
            is_string($value) => trim($value) === '',
            is_numeric($value) => false,
            default => empty($value),
        };
    }
}

if (! function_exists('u_filled')) {
    /**
     * 判断值是否非空
     */
    function u_filled(mixed $value): bool
    {
        return ! u_blank($value);
    }
}

if (! function_exists('u_str')) {
    /**
     * 快速创建 StringHelper 实例
     */
    function u_str(?string $value = null): \zxf\Utils\Str\StringHelper|string
    {
        if ($value === null) {
            return new \zxf\Utils\Str\StringHelper();
        }

        return $value;
    }
}

if (! function_exists('u_limiter')) {
    /**
     * 创建速率限制器
     */
    function u_limiter(string $key, int $maxAttempts, int $decaySeconds = 60): RateLimiter
    {
        return RateLimiter::make($key, $maxAttempts, $decaySeconds);
    }
}

if (! function_exists('u_when')) {
    /**
     * 条件执行
     */
    function u_when(mixed $value, callable $callback, ?callable $default = null): mixed
    {
        if ($value) {
            return $callback($value);
        }

        return $default ? $default($value) : $value;
    }
}

if (! function_exists('u_unless')) {
    /**
     * 条件不成立时执行
     */
    function u_unless(mixed $value, callable $callback, ?callable $default = null): mixed
    {
        if (! $value) {
            return $callback($value);
        }

        return $default ? $default($value) : $value;
    }
}

if (! function_exists('u_class_basename')) {
    /**
     * 获取类名（不含命名空间）
     */
    function u_class_basename(string|object $class): string
    {
        $class = is_object($class) ? get_class($class) : $class;
        return basename(str_replace('\\', '/', $class));
    }
}

if (! function_exists('u_class_uses_recursive')) {
    /**
     * 递归获取类使用的所有 trait
     */
    function u_class_uses_recursive(string|object $class): array
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        $results = [];
        foreach (array_reverse(class_parents($class)) + [$class => $class] as $c) {
            $results += u_trait_uses_recursive($c);
        }

        return array_unique($results);
    }
}

if (! function_exists('u_trait_uses_recursive')) {
    /**
     * 递归获取 trait 使用的所有 trait
     */
    function u_trait_uses_recursive(string $trait): array
    {
        $traits = class_uses($trait) ?: [];
        foreach ($traits as $t) {
            $traits += u_trait_uses_recursive($t);
        }
        return $traits;
    }
}

if (! function_exists('u_data_get')) {
    /**
     * 使用点号获取数组或对象中的值
     */
    function u_data_get(mixed $target, int|string|null $key, mixed $default = null): mixed
    {
        if (is_null($key)) {
            return $target;
        }

        $key = is_int($key) ? $key : (string) $key;

        foreach (explode('.', $key) as $segment) {
            if (is_array($target) && array_key_exists($segment, $target)) {
                $target = $target[$segment];
            } elseif ($target instanceof \ArrayAccess && $target->offsetExists($segment)) {
                $target = $target[$segment];
            } elseif (is_object($target) && isset($target->{$segment})) {
                $target = $target->{$segment};
            } else {
                return u_value($default);
            }
        }

        return $target;
    }
}

if (! function_exists('u_data_set')) {
    /**
     * 使用点号设置数组或对象中的值
     */
    function u_data_set(mixed &$target, string $key, mixed $value, bool $overwrite = true): mixed
    {
        $segments = explode('.', $key);
        $segment = array_shift($segments);

        if (count($segments) === 0) {
            if ($overwrite || ! isset($target[$segment])) {
                $target[$segment] = $value;
            }
            return $target;
        }

        if (! isset($target[$segment]) || ! is_array($target[$segment])) {
            $target[$segment] = [];
        }

        u_data_set($target[$segment], implode('.', $segments), $value, $overwrite);

        return $target;
    }
}

if (! function_exists('u_data_fill')) {
    /**
     * 使用点号填充数组或对象中的值（仅当键不存在时）
     */
    function u_data_fill(mixed &$target, string $key, mixed $value): mixed
    {
        return u_data_set($target, $key, $value, false);
    }
}

if (! function_exists('u_head')) {
    /**
     * 获取数组的第一个元素
     */
    function u_head(array $array): mixed
    {
        return reset($array);
    }
}

if (! function_exists('u_last')) {
    /**
     * 获取数组的最后一个元素
     */
    function u_last(array $array): mixed
    {
        return end($array);
    }
}

if (! function_exists('u_collect')) {
    /**
     * 创建 Collection 实例
     */
    function u_collect(mixed $value = []): \zxf\Utils\Data\Collection
    {
        return new \zxf\Utils\Data\Collection($value);
    }
}

if (! function_exists('u_env')) {
    /**
     * 获取环境变量值
     */
    function u_env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false) {
            return u_value($default);
        }

        return match (strtolower((string) $value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'empty', '(empty)' => '',
            'null', '(null)' => null,
            default => $value,
        };
    }
}

if (! function_exists('u_parse_size')) {
    /**
     * 解析数据大小字符串为字节数
     */
    function u_parse_size(string $size): int
    {
        return \zxf\Utils\Convert\DataSize::parse($size)->toBytes();
    }
}

if (! function_exists('u_format_size')) {
    /**
     * 格式化字节数为人类可读格式
     */
    function u_format_size(int $bytes, int $precision = 2): string
    {
        return \zxf\Utils\Convert\DataSize::format($bytes, $precision);
    }
}
