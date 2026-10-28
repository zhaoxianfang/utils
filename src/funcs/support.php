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
     * 创建一条「数据流水线」（Pipeline）
     *
     * 初始数据会依次流经通过 through() / when() / tap() 注册的处理器，
     * 每个处理器的返回值作为下一个处理器的输入；最终调用 then() 执行并返回结果。
     * 注意：then() 是**终止方法**，它一次性跑完所有管道并返回最终数据（mixed），
     * 因此不能对 then() 的返回值继续链式追加处理器——要追加处理器请用 through()。
     *
     * @param  mixed  $data  进入流水线的初始数据（任意类型）
     * @return Pipeline 流水线实例，可继续调用 through()/when()/tap()/then()
     *
     * @example 依次执行 +1 与 ×2，最终得到 4
     * ```php
     * $result = u_pipeline(1)
     *     ->through(fn ($v) => $v + 1, fn ($v) => $v * 2)
     *     ->then(); // 4
     * ```
     */
    function u_pipeline(mixed $data): Pipeline
    {
        return Pipeline::make($data);
    }
}

if (! function_exists('u_ok')) {
    /**
     * 创建一个「成功」状态的 Result
     *
     * Result 用于显式承载成功/失败两种结果，避免用异常控制正常流程。
     * 用 unwrap() 取成功值（失败时抛异常），用 value() 取成功值（失败时返回 null）。
     *
     * @param  mixed  $value  成功时携带的业务数据
     * @return Result 成功状态的结果对象
     *
     * @example
     * ```php
     * $res = u_ok(['id' => 1]);
     * $res->isOk();    // true
     * $res->unwrap();  // ['id' => 1]
     * ```
     */
    function u_ok(mixed $value): Result
    {
        return Result::ok($value);
    }
}

if (! function_exists('u_err')) {
    /**
     * 创建一个「失败」状态的 Result
     *
     * 失败信息可传字符串（会包装成 RuntimeException）或一个 Throwable 实例；
     * 用 error() 取错误消息，用 exception() 取原始异常对象。
     *
     * @param  string|\Throwable  $error  错误消息，或异常/错误对象
     * @return Result 失败状态的结果对象
     *
     * @example
     * ```php
     * $res = u_err('参数错误');
     * $res->isErr();  // true
     * $res->error();  // '参数错误'
     * ```
     */
    function u_err(string|\Throwable $error): Result
    {
        return Result::err($error);
    }
}

if (! function_exists('u_retry')) {
    /**
     * 重试执行回调，直到成功或用尽尝试次数
     *
     * 当回调抛出异常时按次数与间隔自动重试；全部失败后抛出最后一次的异常。
     * 回调会收到「当前第几次尝试」（从 1 开始）作为参数，便于实现退避策略。
     *
     * @param  callable  $callback  待执行的回调，签名 `function (int $attempt): mixed`
     * @param  int       $times     最大尝试次数（含首次），默认 3
     * @param  int       $sleep     每次重试前的等待时间（毫秒），默认 100
     * @return mixed  回调成功时的返回值
     * @throws \Throwable 所有尝试均失败时抛出最后一次的异常
     *
     * @example
     * ```php
     * $data = u_retry(fn (int $attempt) => httpGet($url), times: 3, sleep: 500);
     * ```
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
     * 对回调做一次性能测量（耗时 + 内存）
     *
     * 返回的 BenchmarkResult 提供 time()（毫秒）、memory()（MB）、
     * value()（被测回调的返回值）等方法。
     *
     * @param  callable  $callback  被测回调，可返回任意值
     * @return \zxf\Utils\Support\BenchmarkResult 测量结果对象
     *
     * @example
     * ```php
     * $r = u_benchmark(fn () => array_sum(range(1, 100000)));
     * echo $r->time();   // 执行耗时（毫秒）
     * echo $r->memory(); // 内存增量（MB）
     * ```
     */
    function u_benchmark(callable $callback): \zxf\Utils\Support\BenchmarkResult
    {
        return Benchmark::measure($callback);
    }
}

if (! function_exists('u_once')) {
    /**
     * 包装一个「只执行一次」的回调
     *
     * 返回 Once 实例；首次调用该实例时真正执行回调并缓存结果，
     * 之后的调用直接返回缓存值。注意：非跨进程/线程安全，
     * 并发场景如需全局唯一需配合 Locker 等互斥机制。
     *
     * @param  callable  $callback  只应执行一次的回调
     * @return Once 一次性执行封装
     *
     * @example
     * ```php
     * $once = u_once(fn () => expensiveOperation());
     * $once(); // 首次：真正执行
     * $once(); // 之后：直接返回缓存结果
     * ```
     */
    function u_once(callable $callback): Once
    {
        return Once::make($callback);
    }
}

if (! function_exists('u_tap')) {
    /**
     * 对值执行副作用回调后，原样返回该值
     *
     * 回调的返回值会被忽略，常用于在链式流程中插入日志、调试或埋点。
     *
     * @param  mixed     $value     原值
     * @param  callable  $callback  接收原值的回调，其返回值被忽略
     * @return mixed  原值（保持不变）
     *
     * @example
     * ```php
     * $user = u_tap($user, fn ($u) => logger('查看用户: ' . $u->id));
     * // $user 仍是原来的对象
     * ```
     */
    function u_tap(mixed $value, callable $callback): mixed
    {
        $callback($value);
        return $value;
    }
}

if (! function_exists('u_with')) {
    /**
     * 把值交给回调处理，并返回回调的返回值
     *
     * 与 u_tap() 的区别：u_tap() 返回原值，u_with() 返回回调结果。
     *
     * @param  mixed     $value     传入回调的值
     * @param  callable  $callback  接收该值的回调
     * @return mixed  回调的返回值
     *
     * @example
     * ```php
     * u_with('hello', fn ($s) => strlen($s)); // 5
     * ```
     */
    function u_with(mixed $value, callable $callback): mixed
    {
        return $callback($value);
    }
}

if (! function_exists('u_value')) {
    /**
     * 返回值本身；若值是闭包则调用它并返回调用结果
     *
     * 常用于「默认值可能是固定值、也可能是惰性求值的闭包」的场景：
     * 传闭包时才会求值，避免不必要的计算。
     *
     * @param  mixed  $value  任意值；若为 Closure 则被调用
     * @param  mixed  ...$args  传给闭包的参数（仅当 $value 是闭包时生效）
     * @return mixed  原值，或闭包的调用结果
     *
     * @example
     * ```php
     * u_value(42);                            // 42（直接返回）
     * u_value(fn ($a, $b) => $a + $b, 1, 2); // 3（调用闭包）
     * ```
     */
    function u_value(mixed $value, mixed ...$args): mixed
    {
        return $value instanceof Closure ? $value(...$args) : $value;
    }
}

if (! function_exists('u_throw_if')) {
    /**
     * 条件为真时抛出异常
     *
     * $exception 可以是异常实例，也可以是「异常类名」；传类名时会用 $message 实例化。
     * 若传入的类名不存在或未实现 Throwable，则抛出 InvalidArgumentException，
     * 以便尽早暴露拼写错误，而不是得到模糊的 "Class not found"。
     *
     * @param  bool               $condition  为 true 时抛异常
     * @param  string|\Throwable  $exception  异常类名（须实现 Throwable）或异常实例
     * @param  string             $message    当 $exception 为类名时使用的异常消息
     * @return void
     * @throws \Throwable  条件为真时抛出；类名非法时抛出 InvalidArgumentException
     *
     * @example
     * ```php
     * u_throw_if($age < 18, \InvalidArgumentException::class, '未成年');
     * ```
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
     * 条件为假时抛出异常（u_throw_if 的反向判断）
     *
     * 常用于「断言某条件必须成立」：不成立则抛异常。参数含义同 u_throw_if()。
     *
     * @param  bool               $condition  为 false 时抛异常
     * @param  string|\Throwable  $exception  异常类名（须实现 Throwable）或异常实例
     * @param  string             $message    当 $exception 为类名时使用的异常消息
     * @return void
     * @throws \Throwable  条件为假时抛出；类名非法时抛出 InvalidArgumentException
     *
     * @example
     * ```php
     * u_throw_unless($user !== null, \RuntimeException::class, '用户未登录');
     * // 等价于：if ($user === null) throw new RuntimeException('用户未登录');
     * ```
     */
    function u_throw_unless(bool $condition, string|\Throwable $exception, string $message = ''): void
    {
        u_throw_if(! $condition, $exception, $message);
    }
}

if (! function_exists('u_blank')) {
    /**
     * 判断值是否为「空」
     *
     * 判空规则（并非 PHP 的 empty 全部语义）：
     *  - null、false、空数组 []、空对象等 empty 语义为真的值 → 视为空；
     *  - 字符串：去除首尾空白后为空串才算空（纯空白串为「空」）；
     *  - 数值（含整数 0、浮点 0.0、字符串 '0'）→ 一律**不视为空**。
     * 因此 0 与 '0' 均返回 false（非空），这一点与 Laravel blank() 的数值处理不同，需留意。
     *
     * @param  mixed  $value  待判断的值
     * @return bool  为空返回 true，否则 false
     *
     * @example
     * ```php
     * u_blank(null);  // true
     * u_blank('  ');  // true
     * u_blank([]);    // true
     * u_blank(false); // true
     * u_blank(0);     // false（数值不视为空）
     * u_blank('0');   // false（数值字符串不视为空）
     * ```
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
     * 判断值是否「非空」（u_blank 的取反）
     *
     * @param  mixed  $value  待判断的值
     * @return bool  非空返回 true，否则 false
     *
     * @example
     * ```php
     * u_filled('abc'); // true
     * u_filled(0);     // true（数值视为非空）
     * u_filled('  ');  // false
     * ```
     */
    function u_filled(mixed $value): bool
    {
        return ! u_blank($value);
    }
}

if (! function_exists('u_str')) {
    /**
     * 创建流式字符串对象（Str），把 StringHelper 的方法变成可链式调用
     *
     * 每次方法调用都以当前字符串作为首个参数转发给 StringHelper 的同名静态方法：
     *  - 若方法返回 string，则继续包装为新的 Str，可继续链式调用；
     *  - 若返回其它类型（bool/int/array 等），则直接返回该结果，链式终止。
     * 用 (string) 强制转换或用 value() 可取回普通字符串。
     *
     * @param  string  $value  初始字符串，默认空串
     * @return \zxf\Utils\Str\Str 字符串包装器实例
     *
     * @example
     * ```php
     * (string) u_str('foo-bar')->camel();      // 'fooBar'
     * (string) u_str('Hello World')->slug();   // 'hello-world'
     * u_str('abc')->contains('b');             // true（布尔结果，链式终止）
     * (string) u_str('13812345678')->maskMobile(); // '138****5678'
     * ```
     */
    function u_str(string $value = ''): \zxf\Utils\Str\Str
    {
        return new \zxf\Utils\Str\Str($value);
    }
}

if (! function_exists('u_limiter')) {
    /**
     * 创建速率限制器（单进程内存滑动窗口）
     *
     * 用同一个 $key 区分不同限流场景（如按用户、按接口）。注意其为进程内存实现，
     * 多进程（如 FPM 多 worker）之间不共享计数，分布式限流需改用 Redis 等外部存储。
     *
     * @param  string  $key          限流键名（区分不同场景，如 'api:user:1'）
     * @param  int     $maxAttempts  窗口内允许的最大次数
     * @param  int     $decaySeconds 滑动窗口长度（秒），默认 60
     * @return RateLimiter 限流器实例
     *
     * @example
     * ```php
     * $limiter = u_limiter('api:user:1', 60, 60); // 60 秒内最多 60 次
     * if (! $limiter->attempt()) {
     *     // 已触发限流
     * }
     * ```
     */
    function u_limiter(string $key, int $maxAttempts, int $decaySeconds = 60): RateLimiter
    {
        return RateLimiter::make($key, $maxAttempts, $decaySeconds);
    }
}

if (! function_exists('u_when')) {
    /**
     * 值为「真」时执行回调，否则返回默认值或原值
     *
     * 回调会收到原 $value 作为参数；未提供 $default 时返回原 $value。
     *
     * @param  mixed          $value     判断依据（真值判断）
     * @param  callable       $callback  值为真时执行，接收 $value
     * @param  callable|null  $default   值为假时执行，接收 $value；为 null 时返回原值
     * @return mixed  回调结果或原值
     *
     * @example
     * ```php
     * u_when($isVip, fn ($flag) => 80, fn ($flag) => 100);
     * // $isVip 为 true 时得 80，否则得 100
     * ```
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
     * 值为「假」时执行回调，否则返回默认值或原值（u_when 的反向判断）
     *
     * @param  mixed          $value     判断依据（真值判断）
     * @param  callable       $callback  值为假时执行，接收 $value
     * @param  callable|null  $default   值为真时执行，接收 $value；为 null 时返回原值
     * @return mixed  回调结果或原值
     *
     * @example
     * ```php
     * u_unless($isVip, fn ($flag) => 100, fn ($flag) => 80);
     * // $isVip 为 false 时得 100，否则得 80
     * ```
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
     *
     * 传入类名字符串或对象实例均可，内部按命名空间分隔符拆分后取最后一段。
     *
     * @param  string|object  $class  类名（含或不含命名空间）或对象实例
     * @return string 不含命名空间的短类名
     *
     * @example
     * ```php
     * u_class_basename(new \DateTime());                       // 'DateTime'
     * u_class_basename(\zxf\Utils\Support\Pipeline::class);    // 'Pipeline'
     * ```
     */
    function u_class_basename(string|object $class): string
    {
        $class = is_object($class) ? get_class($class) : $class;
        return basename(str_replace('\\', '/', $class));
    }
}

if (! function_exists('u_class_uses_recursive')) {
    /**
     * 递归收集类（含其父类）使用的全部 trait
     *
     * 会沿着继承链向上遍历，并把各层 use 的 trait（以及 trait 内部再 use 的 trait）
     * 去重后汇总。类 / 接口不存在时返回空数组。
     *
     * @param  string|object  $class  类名或对象实例
     * @return list<string> 去重后的 trait 全名列表
     *
     * @example
     * ```php
     * // 某模型及其父类分别 use 了不同的 trait，可一次性取全
     * u_class_uses_recursive(\App\Models\User::class);
     * // 例如：['Illuminate\Database\Eloquent\Factories\HasFactory', 'SoftDeletes', ...]
     * ```
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
     * 递归收集一个 trait 内部使用的所有 trait
     *
     * 主要供 u_class_uses_recursive() 内部调用；通过 $visited 集合避免
     * trait 之间互相 use 造成的环与重复遍历。
     *
     * @param  string               $trait    trait 或类名
     * @param  array<string, true>  $visited  已访问集合（内部递归使用，防止环与重复遍历）
     * @return list<string> 去重后的 trait 全名列表（含 $trait 自身直接 use 的 trait）
     *
     * @example
     * ```php
     * u_trait_uses_recursive(\App\Traits\HasUuid::class);
     * // 例如：['App\Traits\GeneratesUuid']
     * ```
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
     * 用「点号路径」读取数组 / ArrayAccess / 对象中的值
     *
     * 支持逐级下钻（如 'user.profile.name'）；若最外层本身就存在同名完整键
     * （键名里含点号），会优先整体命中。路径中任一层不存在时返回 $default
     * （若 $default 是闭包则惰性求值）。$key 为 null 时直接返回 $target。
     *
     * @param  mixed                $target   目标（数组 / ArrayAccess / 对象）
     * @param  int|string|null      $key      点号路径；null 表示直接返回目标
     * @param  mixed                $default  未命中时返回的默认值（可为闭包）
     * @return mixed  命中的值或默认值
     *
     * @example
     * ```php
     * u_data_get(['user' => ['name' => 'Tom']], 'user.name'); // 'Tom'
     * u_data_get(['a' => 1], 'a.b', 'D');                    // 'D'
     * ```
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
     * 用「点号路径」设置数组 / 对象中的值（按引用就地修改）
     *
     * 中间层不存在时会自动创建为数组；$overwrite 为 false 时仅在目标键
     * 不存在时写入（已存在则保留原值）。$target 以引用传入，函数同时返回它，
     * 便于链式或赋值使用。
     *
     * @param  mixed   &$target    目标（数组 / 对象），会被就地修改
     * @param  string  $key        点号路径，如 'user.name'
     * @param  mixed   $value      要写入的值
     * @param  bool    $overwrite  是否覆盖已存在的键（false 表示仅填充缺失键）
     * @return mixed  修改后的 $target
     *
     * @example
     * ```php
     * $data = [];
     * u_data_set($data, 'user.name', 'Tom');
     * // $data === ['user' => ['name' => 'Tom']]
     * ```
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
     * 用「点号路径」填充值，仅在目标键不存在时才写入
     *
     * 等价于 u_data_set(..., $overwrite = false)，适合给配置补默认值而不覆盖已有项。
     *
     * @param  mixed   &$target  目标（数组 / 对象），会被就地修改
     * @param  string  $key      点号路径
     * @param  mixed   $value    缺失时要写入的默认值
     * @return mixed  修改后的 $target
     *
     * @example
     * ```php
     * $cfg = ['user' => ['name' => 'Tom']];
     * u_data_fill($cfg, 'user.name', 'Jerry'); // 已存在，保持 'Tom'
     * u_data_fill($cfg, 'user.age', 18);       // 不存在，写入 18
     * ```
     */
    function u_data_fill(mixed &$target, string $key, mixed $value): mixed
    {
        return u_data_set($target, $key, $value, false);
    }
}

if (! function_exists('u_head')) {
    /**
     * 获取数组的第一个元素
     *
     * 注意：空数组返回 false（底层 reset() 的行为），而非 null。
     *
     * @param  array  $array  目标数组
     * @return mixed  首个元素；数组为空时返回 false
     *
     * @example
     * ```php
     * u_head([3, 1, 2]); // 3
     * u_head([]);        // false
     * ```
     */
    function u_head(array $array): mixed
    {
        return reset($array);
    }
}

if (! function_exists('u_last')) {
    /**
     * 获取数组的最后一个元素
     *
     * 注意：空数组返回 false（底层 end() 的行为），而非 null。
     *
     * @param  array  $array  目标数组
     * @return mixed 最后一个元素；数组为空时返回 false
     *
     * @example
     * ```php
     * u_last([3, 1, 2]); // 2
     * u_last([]);        // false
     * ```
     */
    function u_last(array $array): mixed
    {
        return end($array);
    }
}

if (! function_exists('u_collect')) {
    /**
     * 创建 Collection 集合实例
     *
     * @param  mixed  $value  初始数据，默认空数组
     * @return \zxf\Utils\Data\Collection 集合实例
     *
     * @example
     * ```php
     * u_collect([1, 2, 3])->count(); // 3
     * ```
     */
    function u_collect(mixed $value = []): \zxf\Utils\Data\Collection
    {
        return new \zxf\Utils\Data\Collection($value);
    }
}

if (! function_exists('u_env')) {
    /**
     * 读取环境变量并做常用类型转换
     *
     * 依次从 $_ENV、$_SERVER、getenv() 中查找；并对特殊字面量做转换：
     * 'true' / '(true)' → true，'false' / '(false)' → false，
     * 'empty' / '(empty)' → ''，'null' / '(null)' → null，其余原样返回（字符串）。
     * 未设置时返回 $default（若 $default 为闭包则惰性求值）。
     *
     * @param  string  $key      环境变量名
     * @param  mixed   $default  未设置时的默认值（可为闭包）
     * @return mixed  转换后的值或默认值
     *
     * @example
     * ```php
     * // 假设 APP_DEBUG=true
     * u_env('APP_DEBUG');       // true
     * u_env('APP_NAME', 'zxf'); // 未设置时返回 'zxf'
     * ```
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
     * 解析「数据大小字符串」为字节数
     *
     * 单位必须带 B 后缀：B / KB / MB / GB / TB / PB（大小写不敏感），
     * 例如 '2MB'、'512KB'。注意 '2M' 这类省略 B 的写法会抛 InvalidArgumentException。
     *
     * @param  string  $size  数据大小字符串，如 '2MB'、'512KB'
     * @return int 对应的字节数（1024 进制）
     * @throws \InvalidArgumentException 格式非法或单位不支持时抛出
     *
     * @example
     * ```php
     * u_parse_size('2MB');   // 2097152
     * u_parse_size('512KB'); // 524288
     * ```
     */
    function u_parse_size(string $size): int
    {
        return \zxf\Utils\Convert\DataSize::parse($size)->toBytes();
    }
}

if (! function_exists('u_format_size')) {
    /**
     * 把字节数格式化为人类可读的数据大小
     *
     * 自动选择合适的单位（B/KB/MB/GB…）并保留指定小数位。
     *
     * @param  int  $bytes      字节数
     * @param  int  $precision  保留的小数位数，默认 2
     * @return string 格式化后的字符串，如 '1.5 KB'
     *
     * @example
     * ```php
     * u_format_size(1536);     // '1.5 KB'
     * u_format_size(2097152);  // '2 MB'
     * ```
     */
    function u_format_size(int $bytes, int $precision = 2): string
    {
        return \zxf\Utils\Convert\DataSize::format($bytes, $precision);
    }
}
