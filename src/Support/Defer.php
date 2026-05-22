<?php

/**
 * ============================================================================
 * 延迟执行工具类
 * ============================================================================
 *
 * 实现类似 Go 语言 defer 的延迟执行机制。
 * 注册的回调会在对象销毁时按 LIFO（后进先出）顺序执行。
 *
 * @package     zxf\Utils\Support
 * @author      zxf\Utils
 * @version     1.0.0
 * @license     MIT
 * @link        https://github.com/zhaoxianfang/utils
 *
 * @example
 * function processFile(string $path): void {
 *     $file = fopen($path, 'r');
 *     $defer = Defer::make();
 *     $defer->add(fn () => fclose($file)); // 确保文件一定会被关闭
 *
 *     // 执行业务逻辑...
 *     $content = fread($file, 1024);
 * }
 */

declare(strict_types=1);

namespace zxf\Utils\Support;

use Closure;
use Throwable;

final class Defer
{
    /** @var Closure[] */
    private array $callbacks = [];

    /** @var Throwable|null */
    private ?Throwable $caught = null;

    private bool $executed = false;

    public static function make(): self
    {
        return new self();
    }

    /**
     * 注册延迟执行的回调
     */
    public function add(Closure $callback): self
    {
        $this->callbacks[] = $callback;
        return $this;
    }

    /**
     * 包装一个可调用对象，在完成后自动执行延迟回调
     *
     * @template T
     * @param Closure(): T $callback
     * @return T
     */
    public function call(Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            $this->caught = $e;
            throw $e;
        } finally {
            $this->execute();
        }
    }

    /**
     * 手动触发所有延迟回调
     */
    public function execute(): void
    {
        if ($this->executed) {
            return;
        }

        $this->executed = true;

        // 后进先出
        while ($callback = array_pop($this->callbacks)) {
            try {
                $callback($this->caught);
            } catch (Throwable) {
                // 延迟回调中的异常不应阻止其他回调执行
            }
        }
    }

    /**
     * 获取捕获的异常（如果有）
     */
    public function caught(): ?Throwable
    {
        return $this->caught;
    }

    /**
     * 对象销毁时自动执行
     */
    public function __destruct()
    {
        $this->execute();
    }
}
