<?php

/**
 * ============================================================================
 * 一次性执行封装
 * ============================================================================
 *
 * 确保回调只执行一次，后续调用直接返回缓存结果。
 *
 * @package     zxf\Utils\Support
 * @author      zxf\Utils
 * @version     1.0.0
 * @license     MIT
 * @link        https://github.com/zhaoxianfang/utils
 */

declare(strict_types=1);

namespace zxf\Utils\Support;

use Closure;

/**
 * 确保回调只执行一次的封装
 *
 * @example
 * $once = Once::make(fn () => expensiveOperation());
 * $result1 = $once(); // 执行并缓存
 * $result2 = $once(); // 直接返回缓存结果
 */
final class Once
{
    private bool $executed = false;

    private mixed $value;

    private function __construct(private Closure $callback)
    {
    }

    public static function make(Closure $callback): self
    {
        return new self($callback);
    }

    public function __invoke(): mixed
    {
        if (! $this->executed) {
            $this->value = ($this->callback)();
            $this->executed = true;
        }

        return $this->value;
    }

    /**
     * 重置，允许再次执行
     */
    public function reset(): void
    {
        $this->executed = false;
        $this->value = null;
    }

    /**
     * 是否已执行
     */
    public function executed(): bool
    {
        return $this->executed;
    }
}
