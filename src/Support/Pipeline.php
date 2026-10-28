<?php

/**
 * ============================================================================
 * 数据流水线处理类
 * ============================================================================
 *
 * 支持链式调用、异常处理、条件执行、中间件模式的数据处理流水线。
 *
 * @package     zxf\Utils\Support
 * @author      zxf\Utils
 * @version     1.0.0
 * @license     MIT
 * @link        https://github.com/zhaoxianfang/utils
 *
 * @example
 * $result = Pipeline::make($data)
 *     ->through(
 *         fn ($data) => array_map('trim', $data),
 *         fn ($data) => array_filter($data),
 *     )
 *     ->then(fn ($data) => array_values($data));
 */

declare(strict_types=1);

namespace zxf\Utils\Support;

use Closure;
use RuntimeException;
use Throwable;

/**
 * 数据流水线处理类
 *
 * 支持链式调用、异常处理、条件执行、中间件模式
 *
 * @example
 * $result = Pipeline::make($data)
 *     ->through(
 *         fn ($data) => array_map('trim', $data),
 *         fn ($data) => array_filter($data),
 *         fn ($data) => array_unique($data),
 *     )
 *     ->then(fn ($data) => array_values($data));
 */
final class Pipeline
{
    /** @var mixed 初始数据 */
    private mixed $data;

    /** @var mixed 最近一次执行后的数据（供 peek() 使用） */
    private mixed $current;

    /** @var Closure[] 管道列表 */
    private array $pipes = [];

    private ?Closure $exceptionHandler = null;

    private bool $haltOnException = true;

    /**
     * 私有构造函数，初始化流水线数据与状态
     *
     * @param mixed $data 初始数据
     */
    private function __construct(mixed $data)
    {
        $this->data = $data;
        $this->current = $data;
    }

    /**
     * 创建流水线实例
     *
     * 初始数据同时作为 current 的初值，因此未执行时 peek() 返回该数据。
     *
     * @param mixed $data 初始数据
     * @return static
     */
    public static function make(mixed $data): self
    {
        return new self($data);
    }

    /**
     * 添加处理管道（可传入多个）
     *
     * 管道追加到现有列表尾部并按注册顺序依次执行，重复调用会累加而非覆盖。
     *
     * @param Closure ...$pipes 每个管道接收当前数据，返回处理后的数据
     * @return self 当前实例，便于链式调用
     */
    public function through(Closure ...$pipes): self
    {
        $this->pipes = array_merge($this->pipes, $pipes);

        return $this;
    }

    /**
     * 条件执行管道
     *
     * 条件为真时把 $pipe 追加到管道列表，为假且提供了 $else 时追加 $else；
     * 判定发生在注册阶段，之后修改条件不会影响已追加的管道。
     *
     * @param bool $condition 是否追加 $pipe
     * @param Closure $pipe 条件成立时使用的管道
     * @param Closure|null $else 条件不成立时使用的管道，为 null 则不追加
     * @return self 当前实例，便于链式调用
     */
    public function when(bool $condition, Closure $pipe, ?Closure $else = null): self
    {
        if ($condition) {
            $this->pipes[] = $pipe;
        } elseif ($else !== null) {
            $this->pipes[] = $else;
        }

        return $this;
    }

    /**
     * 添加不影响数据的副作用管道（回调的返回值被忽略）
     *
     * 常用于日志、计数等旁路操作，回调入参为当前数据，数据原样传给下一个管道。
     *
     * @param Closure $callback 副作用回调，签名 `function (mixed $data): void`
     * @return self 当前实例，便于链式调用
     */
    public function tap(Closure $callback): self
    {
        $this->pipes[] = static function (mixed $data) use ($callback): mixed {
            $callback($data);

            return $data;
        };

        return $this;
    }

    /**
     * 设置异常处理方式
     *
     * 一旦提供了处理器，管道抛出的异常会交给它处理（处理器返回的值
     * 作为该管道的输出），流水线继续执行；同时会把 haltOnException 置为 false。
     *
     * @param Closure $handler 异常处理器，签名 `function (Throwable $e, mixed $data): mixed`
     * @return self 当前实例，便于链式调用
     */
    public function catch(Closure $handler): self
    {
        $this->exceptionHandler = $handler;
        $this->haltOnException = false;

        return $this;
    }

    /**
     * 是否在异常时终止执行
     *
     * 仅在没有设置 catch() 处理器时生效：
     * true（默认）时包装为 RuntimeException 抛出；false 时跳过该管道并保留原数据。
     *
     * @param bool $halt 是否在异常时终止，默认 true
     * @return self 当前实例，便于链式调用
     */
    public function haltOnException(bool $halt = true): self
    {
        $this->haltOnException = $halt;

        return $this;
    }

    /**
     * 执行流水线并返回最终结果
     *
     * 可以重复调用：每次都会从**初始数据**重新流过全部管道。
     *
     * @param Closure|null $destination 可选的终点回调，其异常同样受 catch() 保护
     * @return mixed 终点数据；无终点回调时为最后一个管道的输出
     *
     * @throws RuntimeException 未设置处理器且 haltOnException 为 true 时，包装管道异常后抛出
     */
    public function then(?Closure $destination = null): mixed
    {
        $data = $this->data;

        foreach ($this->pipes as $pipe) {
            $data = $this->runPipe($pipe, $data);
        }

        if ($destination !== null) {
            $data = $this->runPipe($destination, $data);
        }

        return $this->current = $data;
    }

    /**
     * 执行单个管道并处理异常
     *
     * 正常返回管道输出；异常时优先交给 catch() 处理器，其次按 haltOnException
     * 决定包装抛出还是跳过（返回进入管道前的数据）。
     *
     * @param Closure $pipe 待执行的管道
     * @param mixed $data 进入该管道的数据
     * @return mixed 管道输出、处理器返回值或原始数据
     *
     * @throws RuntimeException 无处理器且 haltOnException 为 true 时抛出
     */
    private function runPipe(Closure $pipe, mixed $data): mixed
    {
        try {
            return $pipe($data);
        } catch (Throwable $e) {
            if ($this->exceptionHandler !== null) {
                return ($this->exceptionHandler)($e, $data);
            }

            if ($this->haltOnException) {
                throw new RuntimeException('Pipeline execution failed: ' . $e->getMessage(), 0, $e);
            }

            // 不中断且无处理器：跳过该管道，保留进入时的数据
            return $data;
        }
    }

    /**
     * 获取最近一次执行后的数据（未执行过则返回初始数据）
     *
     * @return mixed 最近一次 then() 的结果，未执行时为构造时传入的初始数据
     */
    public function peek(): mixed
    {
        return $this->current;
    }

    /**
     * 获取初始数据
     *
     * 即 make() 传入的原始数据，不随 then() 执行而改变。
     *
     * @return mixed 构造流水线时传入的初始数据
     */
    public function initial(): mixed
    {
        return $this->data;
    }

    /**
     * 重置所有管道（保留数据）
     *
     * 清空管道列表，但保留初始数据与异常处理配置，便于复用同一实例重新编排。
     *
     * @return self 当前实例，便于链式调用
     */
    public function reset(): self
    {
        $this->pipes = [];

        return $this;
    }
}
