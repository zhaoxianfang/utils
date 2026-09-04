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
        if (! $condition) {
            return;
        }

        if ($exception instanceof \Throwable) {
            throw $exception;
        }

        // 字符串形式必须是已存在且可抛出的类，否则给出明确错误而不是模糊的 "Class not found"
        if (! class_exists($exception) || ! is_subclass_of($exception, \Throwable::class)) {
            throw new \InvalidArgumentException(sprintf(
                '异常类 "%s" 不存在或未实现 Throwable 接口',
                $exception
            ));
        }

        throw new $exception($message);
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
     * 创建流式字符串实例，用于链式调用 StringHelper 中的方法
     *
     * 每次调用都以当前字符串作为首个参数；返回字符串的方法会继续返回
     * 可链式的实例，返回其它类型（bool/int/array）的方法则直接返回结果。
     *
     *  u_str('foo-bar')->camel();              // 'fooBar'
     *  u_str('Hello World')->slug()->upper();  // 'HELLO-WORLD'（upper 由 Str 转发）
     *  u_str('abc')->contains('b');            // true
     *  (string) u_str('foo-bar')->camel();     // 'fooBar'
     *  u_str('13812345678')->maskMobile();     // '138****5678'
     *
     * @param string $value 初始字符串，默认空串
     */
    function u_str(string $value = ''): \zxf\Utils\Str\Str
    {
        return new \zxf\Utils\Str\Str($value);
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

        // class_parents() 对不存在的类返回 false，直接参与运算会触发 TypeError
        if (! class_exists($class) && ! interface_exists($class)) {
            return [];
        }

        $results = [];
        $parents = class_parents($class) ?: [];

        foreach (array_reverse($parents) + [$class => $class] as $c) {
            // 子函数返回 list，此处必须用 array_merge 累积（用 += 会按下标覆盖导致漏项）
            $results = array_merge($results, u_trait_uses_recursive($c));
        }

        return array_values(array_unique($results));
    }
}

if (! function_exists('u_trait_uses_recursive')) {
    /**
     * 递归获取 trait 使用的所有 trait
     *
     * @param string                  $trait   trait 或类名
     * @param array<string, true>     $visited 已访问集合（内部递归使用，防止环与重复遍历）
     * @return list<string>
     */
    function u_trait_uses_recursive(string $trait, array $visited = []): array
    {
        if (isset($visited[$trait])) {
            return [];
        }
        $visited[$trait] = true;

        $traits = class_uses($trait) ?: []; // 键与值均为 trait 名称

        foreach ($traits as $name) {
            // 传递 visited 集合，避免深 trait 链被指数级重复遍历。
            // 子结果为 list，需逐个按名写入，保证键与值始终是 trait 名称。
            foreach (u_trait_uses_recursive($name, $visited) as $sub) {
                $traits[$sub] = $sub;
            }
        }

        return array_values($traits);
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

        // 键本身就是完整下标且目标直接命中时，优先整体匹配，
        // 这样含点号的键（如 "user.name" 作为字面下标）也能被正确读取。
        if (is_array($target) && array_key_exists($key, $target)) {
            return $target[$key];
        }

        $key = is_int($key) ? $key : (string) $key;

        foreach (explode('.', $key) as $segment) {
            if (is_array($target) && array_key_exists($segment, $target)) {
                $target = $target[$segment];
            } elseif ($target instanceof \ArrayAccess && $target->offsetExists($segment)) {
                $target = $target[$segment];
            } elseif (is_object($target) && property_exists($target, $segment)) {
                // 用 property_exists 而非 isset：属性值为 null 时同样应视为命中
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
        if ($key === '') {
            return $target;
        }

        $segments = explode('.', $key);
        $segment = array_shift($segments);

        // 最后一段：写入最终值
        if ($segments === []) {
            if (is_object($target)) {
                if ($overwrite || ! property_exists($target, $segment)) {
                    $target->{$segment} = $value;
                }

                return $target;
            }

            if (! is_array($target)) {
                $target = [];
            }

            if ($overwrite || ! array_key_exists($segment, $target)) {
                $target[$segment] = $value;
            }

            return $target;
        }

        // 中间段：确保存在可继续下钻的容器
        if (is_object($target)) {
            if (! isset($target->{$segment}) || ! (is_array($target->{$segment}) || is_object($target->{$segment}))) {
                $target->{$segment} = [];
            }
            $next = &$target->{$segment};
        } else {
            if (! is_array($target)) {
                $target = [];
            }
            if (! array_key_exists($segment, $target) || ! is_array($target[$segment])) {
                $target[$segment] = [];
            }
            $next = &$target[$segment];
        }

        u_data_set($next, implode('.', $segments), $value, $overwrite);

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
