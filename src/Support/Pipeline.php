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
    private mixed $data;

    private array $pipes = [];

    private ?Closure $exceptionHandler = null;

    private bool $haltOnException = true;

    private function __construct(mixed $data)
    {
        $this->data = $data;
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
     * 设置异常处理方式
     */
    public function catch(Closure $handler): self
    {
        $this->exceptionHandler = $handler;
        $this->haltOnException = false;
        return $this;
    }

    /**
     * 是否在异常时终止执行
     */
    public function haltOnException(bool $halt = true): self
    {
        $this->haltOnException = $halt;
        return $this;
    }

    /**
     * 执行流水线并返回最终结果
     */
    public function then(?Closure $destination = null): mixed
    {
        $data = $this->data;

        foreach ($this->pipes as $pipe) {
            try {
                $data = $pipe($data);
            } catch (Throwable $e) {
                if ($this->exceptionHandler !== null) {
                    $data = ($this->exceptionHandler)($e, $data);
                } elseif ($this->haltOnException) {
                    throw new RuntimeException('Pipeline execution failed: ' . $e->getMessage(), 0, $e);
                }
            }
        }

        return $destination !== null ? $destination($data) : $data;
    }

    /**
     * 获取当前数据（不执行后续管道）
     */
    public function peek(): mixed
    {
        return $this->data;
    }

    /**
     * 重置所有管道
     */
    public function reset(): self
    {
        $this->pipes = [];
        return $this;
    }
}
