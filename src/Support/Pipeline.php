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

    private function __construct(mixed $data)
    {
        $this->data = $data;
        $this->current = $data;
    }

    /**
     * 创建流水线实例
     */
    public static function make(mixed $data): self
    {
        return new self($data);
    }

    /**
     * 添加处理管道（可传入多个）
     *
     * @param Closure ...$pipes 每个管道接收当前数据，返回处理后的数据
     */
    public function through(Closure ...$pipes): self
    {
        $this->pipes = array_merge($this->pipes, $pipes);

        return $this;
    }

    /**
     * 条件执行管道
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
     * 作为该管道的输出），流水线继续执行。
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
     */
    public function peek(): mixed
    {
        return $this->current;
    }

    /**
     * 获取初始数据
     */
    public function initial(): mixed
    {
        return $this->data;
    }

    /**
     * 重置所有管道（保留数据）
     */
    public function reset(): self
    {
        $this->pipes = [];

        return $this;
    }
}
