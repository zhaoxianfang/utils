<?php

declare(strict_types=1);

namespace zxf\Utils\Tests;

use Closure;
use Throwable;

/**
 * 极简断言库
 *
 * 测试套件刻意不依赖 PHPUnit：本包定位是零依赖工具库，
 * 保持测试可在 `composer install` 之后立即运行（无需额外下载测试框架）。
 */
final class Assert
{
    /**
     * 断言两个值全等
     */
    public static function same(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            self::fail($message, sprintf(
                '期望 %s，实际 %s',
                self::export($expected),
                self::export($actual)
            ));
        }
    }

    /**
     * 断言值为真
     */
    public static function true(mixed $value, string $message = ''): void
    {
        if ($value !== true) {
            self::fail($message, '期望 true，实际 ' . self::export($value));
        }
    }

    /**
     * 断言值为假
     */
    public static function false(mixed $value, string $message = ''): void
    {
        if ($value !== false) {
            self::fail($message, '期望 false，实际 ' . self::export($value));
        }
    }

    /**
     * 断言值为 null
     */
    public static function null(mixed $value, string $message = ''): void
    {
        if ($value !== null) {
            self::fail($message, '期望 null，实际 ' . self::export($value));
        }
    }

    /**
     * 断言两个浮点值近似相等
     */
    public static function equals(float $expected, float $actual, float $delta = 0.0001, string $message = ''): void
    {
        if (abs($expected - $actual) > $delta) {
            self::fail($message, sprintf('期望 %s，实际 %s（容差 %s）', $expected, $actual, $delta));
        }
    }

    /**
     * 断言回调抛出指定类型的异常
     *
     * @param class-string<Throwable> $exceptionClass
     * @return Throwable 捕获到的异常，便于调用方进一步断言其 message
     */
    public static function throws(string $exceptionClass, Closure $callback, string $message = ''): Throwable
    {
        try {
            $callback();
        } catch (Throwable $e) {
            if (! $e instanceof $exceptionClass) {
                self::fail($message, sprintf(
                    '期望抛出 %s，实际抛出 %s: %s',
                    $exceptionClass,
                    get_class($e),
                    $e->getMessage()
                ));
            }

            return $e;
        }

        self::fail($message, '期望抛出 ' . $exceptionClass . '，但没有抛出任何异常');
    }

    /**
     * 断言回调不抛出异常
     *
     * @template T
     * @param Closure(): T $callback
     * @return T
     */
    public static function doesNotThrow(Closure $callback, string $message = ''): mixed
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            self::fail($message, sprintf('不应抛出异常，实际抛出 %s: %s', get_class($e), $e->getMessage()));
        }
    }

    /**
     * 断言数组包含指定元素
     */
    public static function contains(mixed $needle, array $haystack, string $message = ''): void
    {
        if (! in_array($needle, $haystack, true)) {
            self::fail($message, sprintf('数组 %s 中未找到 %s', self::export($haystack), self::export($needle)));
        }
    }

    /**
     * 断言数组长度
     */
    public static function count(int $expected, array|\Countable $actual, string $message = ''): void
    {
        $real = count($actual);

        if ($real !== $expected) {
            self::fail($message, sprintf('期望 %d 个元素，实际 %d 个', $expected, $real));
        }
    }

    /**
     * 断言字符串包含子串
     */
    public static function stringContains(string $needle, string $haystack, string $message = ''): void
    {
        if (! str_contains($haystack, $needle)) {
            self::fail($message, sprintf('字符串 %s 中未找到 %s', self::export($haystack), self::export($needle)));
        }
    }

    /**
     * 断言字符串「不」包含子串（用于 XSS 等安全断言）
     */
    public static function stringNotContains(string $needle, string $haystack, string $message = ''): void
    {
        if (str_contains($haystack, $needle)) {
            self::fail($message, sprintf('字符串 %s 中不应包含 %s', self::export($haystack), self::export($needle)));
        }
    }

    /**
     * 断言字符串匹配正则
     */
    public static function matches(string $pattern, string $subject, string $message = ''): void
    {
        if (preg_match($pattern, $subject) !== 1) {
            self::fail($message, sprintf('%s 不匹配正则 %s', self::export($subject), $pattern));
        }
    }

    /**
     * 直接标记失败
     */
    public static function fail(string $message, string $detail = ''): never
    {
        throw new AssertionFailedException($message !== '' ? $message . ' — ' . $detail : $detail);
    }

    /**
     * 值的可读表示
     */
    private static function export(mixed $value): string
    {
        return match (true) {
            is_null($value)   => 'null',
            is_bool($value)   => $value ? 'true' : 'false',
            is_string($value) => '"' . $value . '"',
            is_array($value)  => 'array(' . count($value) . ')',
            is_object($value) => get_class($value),
            default           => (string) $value,
        };
    }
}
