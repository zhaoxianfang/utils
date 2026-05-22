<?php

/**
 * ============================================================================
 * Result 类型封装
 * ============================================================================
 *
 * 用于显式处理成功/失败结果，避免使用异常控制流程。
 * 灵感来自 Rust 的 Result<T, E> 类型。
 *
 * @package     zxf\Utils\Support
 * @author      zxf\Utils
 * @version     1.0.0
 * @license     MIT
 * @link        https://github.com/zhaoxianfang/utils
 *
 * @template T
 */

declare(strict_types=1);

namespace zxf\Utils\Support;

use RuntimeException;
use Throwable;

/**
 * Result 类型封装
 *
 * 用于显式处理成功/失败结果，避免使用异常控制流程
 *
 * @template T
 *
 * @example
 * $result = someOperation();
 * if ($result->isOk()) {
 *     echo $result->unwrap();
 * } else {
 *     echo $result->error();
 * }
 */
final readonly class Result
{
    private function __construct(
        private bool $ok,
        private mixed $value,
        private ?Throwable $err = null
    ) {
    }

    /**
     * 创建成功结果
     *
     * @param T $value
     * @return self<T>
     */
    public static function ok(mixed $value): self
    {
        return new self(true, $value);
    }

    /**
     * 创建失败结果
     */
    public static function err(string|Throwable $error): self
    {
        $exception = $error instanceof Throwable ? $error : new RuntimeException($error);
        return new self(false, null, $exception);
    }

    /**
     * 是否成功
     */
    public function isOk(): bool
    {
        return $this->ok;
    }

    /**
     * 是否失败
     */
    public function isErr(): bool
    {
        return ! $this->ok;
    }

    /**
     * 获取成功值
     *
     * @return T
     * @throws RuntimeException 如果结果是失败状态
     */
    public function unwrap(): mixed
    {
        if (! $this->ok) {
            throw new RuntimeException('Called unwrap() on an Err value: ' . $this->error());
        }
        return $this->value;
    }

    /**
     * 获取成功值，失败时返回默认值
     *
     * @param T $default
     * @return T
     */
    public function unwrapOr(mixed $default): mixed
    {
        return $this->ok ? $this->value : $default;
    }

    /**
     * 获取成功值，失败时通过回调生成默认值
     *
     * @param callable(Throwable): T $callback
     * @return T
     */
    public function unwrapOrElse(callable $callback): mixed
    {
        return $this->ok ? $this->value : $callback($this->err);
    }

    /**
     * 获取错误信息
     */
    public function error(): ?string
    {
        return $this->err?->getMessage();
    }

    /**
     * 获取异常对象
     */
    public function exception(): ?Throwable
    {
        return $this->err;
    }

    /**
     * 映射成功值
     *
     * @param callable(T): mixed $callback
     * @return self<mixed>
     */
    public function map(callable $callback): self
    {
        return $this->ok ? self::ok($callback($this->value)) : self::err($this->err);
    }

    /**
     * 映射错误值
     */
    public function mapErr(callable $callback): self
    {
        return $this->ok ? $this : self::err($callback($this->err));
    }

    /**
     * 链式调用
     *
     * @param callable(T): self $callback
     * @return self<mixed>
     */
    public function andThen(callable $callback): self
    {
        return $this->ok ? $callback($this->value) : $this;
    }

    /**
     * 获取成功值，允许失败时返回 null
     */
    public function value(): mixed
    {
        return $this->ok ? $this->value : null;
    }

    /**
     * 将结果转换为数组
     */
    public function toArray(): array
    {
        return $this->ok
            ? ['ok' => true, 'value' => $this->value]
            : ['ok' => false, 'error' => $this->error()];
    }
}
