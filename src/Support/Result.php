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
 * @version     1.1.0
 * @license     MIT
 * @link        https://github.com/zhaoxianfang/utils
 *
 * @template T
 */

declare(strict_types=1);

namespace zxf\Utils\Support;

use JsonSerializable;
use RuntimeException;
use Throwable;

/**
 * Result 类型封装
 *
 * 用于显式处理成功/失败结果，避免使用异常控制流程。
 * 一个 Result 要么是 Ok（持有成功值），要么是 Err（持有 Throwable），二者必居其一。
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
 *
 * @example 链式转换
 * $name = Result::ok($user)
 *     ->map(fn (User $u) => $u->profile)
 *     ->map(fn (Profile $p) => $p->name)
 *     ->unwrapOr('匿名');
 */
final readonly class Result implements JsonSerializable
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
     *
     * @param string|Throwable $error 错误信息或异常实例
     * @return self<never>
     */
    public static function err(string|Throwable $error): self
    {
        $exception = $error instanceof Throwable ? $error : new RuntimeException($error);

        return new self(false, null, $exception);
    }

    /**
     * 执行回调并自动捕获异常，成功返回 Ok，抛异常返回 Err
     *
     * @template R
     * @param callable(): R $callback
     * @return self<R>
     */
    public static function attempt(callable $callback): self
    {
        try {
            return self::ok($callback());
        } catch (Throwable $e) {
            return self::err($e);
        }
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
     * 是否成功且满足给定条件
     *
     * @param callable(T): bool $predicate
     */
    public function isOkAnd(callable $predicate): bool
    {
        return $this->ok && (bool) $predicate($this->value);
    }

    /**
     * 是否失败且满足给定条件
     *
     * @param callable(Throwable): bool $predicate
     */
    public function isErrAnd(callable $predicate): bool
    {
        return ! $this->ok && $this->err !== null && (bool) $predicate($this->err);
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
     * 获取失败异常
     *
     * @throws RuntimeException 如果结果是成功状态
     */
    public function unwrapErr(): Throwable
    {
        if ($this->ok) {
            throw new RuntimeException('Called unwrapErr() on an Ok value');
        }

        // Err 必定由 err()/attempt()/内部构造产生，err 恒不为 null；
        // 此处回退仅为满足静态分析的 null 安全性。
        return $this->err ?? new RuntimeException('Unknown error');
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
        if ($this->ok) {
            return $this->value;
        }

        return $callback($this->err ?? new RuntimeException('Unknown error'));
    }

    /**
     * 获取错误信息（失败时不为 null）
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
     * 映射成功值（失败则原样传递错误）
     *
     * @param callable(T): mixed $callback
     * @return self<mixed>
     */
    public function map(callable $callback): self
    {
        return $this->ok ? self::ok($callback($this->value)) : $this->propagateErr();
    }

    /**
     * 映射成功值，失败时返回给定默认值
     *
     * @param callable(T): mixed $callback
     */
    public function mapOr(mixed $default, callable $callback): mixed
    {
        return $this->ok ? $callback($this->value) : $default;
    }

    /**
     * 映射错误值（成功则原样返回）
     *
     * 回调可返回 Throwable 或字符串，其它标量会被转为字符串，
     * 避免回调返回类型不符时直接抛 TypeError。
     */
    public function mapErr(callable $callback): self
    {
        if ($this->ok) {
            return $this;
        }

        $mapped = $callback($this->err ?? new RuntimeException('Unknown error'));

        return $mapped instanceof Throwable
            ? self::err($mapped)
            : self::err(is_scalar($mapped) ? (string) $mapped : 'Unknown error');
    }

    /**
     * 链式调用：成功时把值交给返回 Result 的回调，失败则短路
     *
     * @param callable(T): self $callback
     * @return self<mixed>
     */
    public function andThen(callable $callback): self
    {
        return $this->ok ? $callback($this->value) : $this;
    }

    /**
     * 失败时的兜底：返回另一个 Result
     *
     * @param callable(Throwable): self $callback
     * @return self<mixed>
     */
    public function orElse(callable $callback): self
    {
        if ($this->ok) {
            return $this;
        }

        return $callback($this->err ?? new RuntimeException('Unknown error'));
    }

    /**
     * 获取成功值，失败时为 null
     */
    public function value(): mixed
    {
        return $this->ok ? $this->value : null;
    }

    /**
     * 将结果转换为数组
     *
     * @return array{ok: bool, value?: mixed, error?: string|null}
     */
    public function toArray(): array
    {
        return $this->ok
            ? ['ok' => true, 'value' => $this->value]
            : ['ok' => false, 'error' => $this->error()];
    }

    /**
     * 实现 JsonSerializable，便于直接 json_encode
     *
     * @return array{ok: bool, value?: mixed, error?: string|null}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * 把失败原样传递出去（成功时不会调用）
     *
     * 单独抽出的原因：构造函数签名要求 ?Throwable，
     * 而公开 API 只接受 string|Throwable，直接复用 self::err($this->err)
     * 在 err 为 null 时会触发 TypeError。
     */
    private function propagateErr(): self
    {
        return new self(false, null, $this->err ?? new RuntimeException('Unknown error'));
    }
}
