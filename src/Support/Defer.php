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
use RuntimeException;
use Throwable;

/**
 * 延迟执行工具类
 *
 * 实现类似 Go 语言 defer 的延迟执行机制：注册的回调在对象销毁或显式
 * execute() 时按后进先出（LIFO）顺序执行，常用于资源释放、锁释放、
 * 计时收尾等需要确保「无论成功失败都执行」的场景。
 */
final class Defer
{
    /** @var Closure[] */
    private array $callbacks = [];

    /** @var Throwable|null */
    private ?Throwable $caught = null;

    private bool $executed = false;

    /**
     * 创建延迟执行实例
     *
     * @return static
     */
    public static function make(): self
    {
        return new self();
    }

    /**
     * 注册延迟执行的回调
     *
     * 回调会在 execute()（或对象销毁）时按后进先出顺序执行，
     * 并收到一个 `Throwable|null` 参数（即 call() 中捕获到的异常，没有则为 null）。
     *
     * @param Closure(Throwable|null): mixed $callback
     * @return self 当前实例，便于链式注册
     *
     * @throws RuntimeException 延迟回调已执行完毕后再注册时抛出
     */
    public function add(Closure $callback): self
    {
        // 已执行完毕后再注册会永远得不到执行，属于明显的用法错误，
        // 直接报错好过静默丢弃。
        if ($this->executed) {
            throw new RuntimeException('延迟回调已执行完毕，无法再注册新的回调');
        }

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

        // 后进先出；用 !== null 判断而非真值判断，避免把闭包误判为假值
        while (($callback = array_pop($this->callbacks)) !== null) {
            try {
                $callback($this->caught);
            } catch (Throwable) {
                // 延迟回调中的异常不应阻止其他回调执行
            }
        }
    }

    /**
     * 获取捕获的异常（如果有）
     *
     * 仅在 call() 执行期间发生异常时被赋值，可提供给延迟回调判断是否走了异常分支。
     *
     * @return Throwable|null 捕获到的异常，未发生异常时返回 null
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
