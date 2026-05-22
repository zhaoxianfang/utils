<?php

declare(strict_types=1);

namespace zxf\Utils\Cache;

use Closure;

/**
 * 内存缓存管理器
 *
 * 提供带过期时间（TTL）和标签分组的高速内存缓存，适用于单请求内的数据缓存、
 * 配置缓存、计算结果缓存等场景。支持原子递增、条件缓存和批量清理。
 *
 * 特性：
 * - TTL 自动过期
 * - 标签批量清理
 * - 原子递增/递减
 * - 条件缓存（remember）
 * - 命中率统计
 *
 * @package Cache
 * @version 1.0.0
 * @license MIT
 */
class MemoryCache
{
    /** @var array<string, array{value: mixed, expires: int, tags: string[]}> */
    private array $storage = [];

    /** @var array<string, int> */
    private array $stats = ['hits' => 0, 'misses' => 0];

    /**
     * 写入缓存
     *
     * @param string        $key    缓存键
     * @param mixed         $value  缓存值
     * @param int|null      $ttl    过期时间（秒），null 表示永不过期
     * @param string[]      $tags   标签列表，用于批量清理
     */
    public function set(string $key, mixed $value, ?int $ttl = null, array $tags = []): void
    {
        $this->storage[$key] = [
            'value'   => $value,
            'expires' => $ttl !== null ? time() + $ttl : 0,
            'tags'    => $tags,
        ];
    }

    /**
     * 读取缓存
     *
     * @param string $key     缓存键
     * @param mixed  $default 默认值
     * @return mixed 缓存值或默认值
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->has($key)) {
            $this->stats['misses']++;
            return $default;
        }

        $this->stats['hits']++;
        return $this->storage[$key]['value'];
    }

    /**
     * 条件缓存：若键不存在则执行回调并将结果缓存
     *
     * @param string   $key    缓存键
     * @param Closure  $callback 回调函数，返回值将被缓存
     * @param int|null $ttl    过期时间（秒）
     * @param string[] $tags   标签列表
     * @return mixed 缓存值或回调返回值
     */
    public function remember(string $key, Closure $callback, ?int $ttl = null, array $tags = []): mixed
    {
        if ($this->has($key)) {
            return $this->get($key);
        }

        $value = $callback();
        $this->set($key, $value, $ttl, $tags);
        return $value;
    }

    /**
     * 判断缓存是否存在且未过期
     *
     * @param string $key 缓存键
     * @return bool
     */
    public function has(string $key): bool
    {
        if (!isset($this->storage[$key])) {
            return false;
        }

        $item = $this->storage[$key];
        if ($item['expires'] !== 0 && $item['expires'] < time()) {
            unset($this->storage[$key]);
            return false;
        }

        return true;
    }

    /**
     * 删除指定缓存
     *
     * @param string $key 缓存键
     */
    public function delete(string $key): void
    {
        unset($this->storage[$key]);
    }

    /**
     * 原子递增
     *
     * @param string $key   缓存键
     * @param int    $step  增量
     * @return int 递增后的值
     */
    public function increment(string $key, int $step = 1): int
    {
        $current = $this->get($key, 0);
        $value = (is_int($current) ? $current : 0) + $step;
        $this->set($key, $value);
        return $value;
    }

    /**
     * 原子递减
     *
     * @param string $key   缓存键
     * @param int    $step  减量
     * @return int 递减后的值
     */
    public function decrement(string $key, int $step = 1): int
    {
        return $this->increment($key, -$step);
    }

    /**
     * 按标签批量删除缓存
     *
     * @param string|string[] $tags 标签或标签数组
     */
    public function flushByTags(string|array $tags): void
    {
        $tagsToMatch = (array) $tags;
        foreach ($this->storage as $key => $item) {
            foreach ($tagsToMatch as $tag) {
                if (in_array($tag, $item['tags'], true)) {
                    unset($this->storage[$key]);
                    break;
                }
            }
        }
    }

    /**
     * 清空所有缓存
     */
    public function flush(): void
    {
        $this->storage = [];
    }

    /**
     * 获取缓存命中率统计
     *
     * @return array{hits: int, misses: int, rate: float}
     */
    public function stats(): array
    {
        $total = $this->stats['hits'] + $this->stats['misses'];
        return [
            'hits'   => $this->stats['hits'],
            'misses' => $this->stats['misses'],
            'rate'   => $total > 0 ? round($this->stats['hits'] / $total, 4) : 0.0,
        ];
    }

    /**
     * 获取所有缓存键（不过滤过期项）
     *
     * @return string[]
     */
    public function keys(): array
    {
        return array_keys($this->storage);
    }
}
