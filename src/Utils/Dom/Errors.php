<?php

declare(strict_types=1);

namespace zxf\Utils\Dom;

use Throwable;

/**
 * 错误处理类
 * 
 * 提供统一的错误处理和报告机制
 * 支持错误日志记录和自定义错误处理器
 * 
 * 特性：
 * - PHP 8.2+ 类型系统
 * - 只读属性访问
 * - 可配置的错误处理策略
 * 
 * @package zxf\Utils\Dom
 */
class Errors
{
    /**
     * 是否启用错误日志
     */
    private static bool $loggingEnabled = true;

    /**
     * 日志文件路径
     */
    private static ?string $logFile = null;

    /**
     * 自定义错误处理器
     * 
     * @var callable|null
     */
    private static $errorHandler = null;

    /**
     * 是否启用错误日志
     * 
     * @return bool
     */
    public static function isLoggingEnabled(): bool
    {
        return self::$loggingEnabled;
    }

    /**
     * 启用或禁用错误日志
     * 
     * @param  bool  $enabled  是否启用
     * @return void
     */
    public static function setLoggingEnabled(bool $enabled): void
    {
        self::$loggingEnabled = $enabled;
    }

    /**
     * 获取日志文件路径
     * 
     * @return string|null
     */
    public static function getLogFile(): ?string
    {
        return self::$logFile;
    }

    /**
     * 设置日志文件路径
     * 
     * @param  string|null  $path  日志文件路径
     * @return void
     */
    public static function setLogFile(?string $path): void
    {
        self::$logFile = $path;
    }

    /**
     * 设置自定义错误处理器
     * 
     * @param  callable|null  $handler  错误处理器
     * @return void
     */
    public static function setErrorHandler(?callable $handler): void
    {
        self::$errorHandler = $handler;
    }

    /**
     * 处理错误
     * 
     * @param  Throwable  $error  异常或错误对象
     * @param  array<string, mixed>  $context  上下文信息
     * @return void
     */
    public static function handle(Throwable $error, array $context = []): void
    {
        // 调用自定义错误处理器
        if (self::$errorHandler !== null) {
            call_user_func(self::$errorHandler, $error, $context);
        }

        // 记录错误日志
        if (self::$loggingEnabled) {
            self::logError($error, $context);
        }
    }

    /**
     * 记录错误日志
     * 
     * @param  Throwable  $error  异常或错误对象
     * @param  array<string, mixed>  $context  上下文信息
     * @return void
     */
    private static function logError(Throwable $error, array $context = []): void
    {
        $message = sprintf(
            "[%s] %s: %s\n",
            date('Y-m-d H:i:s'),
            get_class($error),
            $error->getMessage()
        );

        if (! empty($context)) {
            $message .= "上下文: " . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        }

        $message .= sprintf(
            "文件: %s:%d\n堆栈:\n%s\n\n",
            $error->getFile(),
            $error->getLine(),
            $error->getTraceAsString()
        );

        // 输出到日志文件
        if (self::$logFile !== null) {
            file_put_contents(self::$logFile, $message, FILE_APPEND);
        }

        // 输出到错误日志（PHP 错误日志）
        error_log($message);
    }

    /**
     * 静默处理错误（不抛出异常）
     * 
     * @param  callable  $callback  要执行的回调
     * @param  mixed  $default  出错时的默认返回值
     * @return mixed
     */
    public static function silence(callable $callback, mixed $default = null): mixed
    {
        try {
            return $callback();
        } catch (Throwable $error) {
            self::handle($error);
            return $default;
        }
    }
}
