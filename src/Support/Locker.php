<?php

declare(strict_types=1);

namespace zxf\Utils\Support;

use RuntimeException;

/**
 * 文件锁互斥工具
 *
 * 基于 flock() 实现进程级互斥锁，支持阻塞/非阻塞模式、
 * 超时等待和自动释放。适用于防止并发执行的定时任务、
 * 缓存重建、资源竞争等场景。
 *
 * @package Support
 * @version 1.0.0
 * @license MIT
 */
class Locker
{
    /** @var resource|null 锁文件句柄 */
    private $handle = null;

    /** @var string 锁文件路径 */
    private string $file;

    /** @var bool 当前是否持有锁 */
    private bool $locked = false;

    /**
     * @param string $name 锁名称（将自动转换为文件路径）
     */
    public function __construct(string $name)
    {
        $dir = sys_get_temp_dir() . '/zxf-locker';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $this->file = $dir . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $name) . '.lock';
    }

    /**
     * 尝试获取锁（非阻塞）
     *
     * @return bool 获取成功返回 true
     */
    public function tryLock(): bool
    {
        $this->open();
        $this->locked = flock($this->handle, LOCK_EX | LOCK_NB);
        return $this->locked;
    }

    /**
     * 获取锁（阻塞，直到成功）
     *
     * @return static
     */
    public function lock(): static
    {
        $this->open();
        flock($this->handle, LOCK_EX);
        $this->locked = true;
        return $this;
    }

    /**
     * 带超时限制的锁获取
     *
     * @param float $seconds 最大等待时间（秒）
     * @return bool 获取成功返回 true，超时返回 false
     */
    public function lockWithTimeout(float $seconds): bool
    {
        $start = microtime(true);
        while (!$this->tryLock()) {
            if ((microtime(true) - $start) >= $seconds) {
                return false;
            }
            usleep(50000); // 50ms
        }
        return true;
    }

    /**
     * 释放锁
     */
    public function unlock(): void
    {
        if ($this->locked && $this->handle !== null) {
            flock($this->handle, LOCK_UN);
            $this->locked = false;
        }
    }

    /**
     * 是否持有锁
     *
     * @return bool
     */
    public function isLocked(): bool
    {
        return $this->locked;
    }

    /**
     * 同步执行回调（自动获取和释放锁）
     *
     * @template T
     * @param callable(): T $callback 回调函数
     * @return T
     */
    public function synchronized(callable $callback): mixed
    {
        $this->lock();
        try {
            return $callback();
        } finally {
            $this->unlock();
        }
    }

    /**
     * 尝试同步执行（非阻塞）
     *
     * @template T
     * @param callable(): T $callback 回调函数
     * @param T             $default  获取锁失败时的返回值
     * @return T
     */
    public function trySynchronized(callable $callback, mixed $default = null): mixed
    {
        if (!$this->tryLock()) {
            return $default;
        }
        try {
            return $callback();
        } finally {
            $this->unlock();
        }
    }

    private function open(): void
    {
        if ($this->handle === null) {
            $this->handle = fopen($this->file, 'c+');
            if ($this->handle === false) {
                throw new RuntimeException("无法创建锁文件: {$this->file}");
            }
        }
    }

    public function __destruct()
    {
        $this->unlock();
        if ($this->handle !== null) {
            fclose($this->handle);
        }
    }
}
