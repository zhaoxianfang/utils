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
     * 直接覆盖同名键的旧值、过期时间与标签。$ttl 为 null 时表示永不过期，
     * 为 0 时按「当前时间 + 0」处理，即立即过期；标签用于 flushByTags() 批量清理，
     * 不参与读取判断。写入不会记录命中统计。
     *
     * @param string   $key   缓存键
     * @param mixed    $value 缓存值，可为任意类型（含 null）
     * @param int|null $ttl   过期时间（秒），null 表示永不过期
     * @param string[] $tags  标签列表，用于按标签批量清理
     * @return void
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
     * 键不存在或已过期时累加 misses 统计并返回 $default；命中则累加 hits 统计
     * 并返回原始值（缓存值为 null 时同样返回 null，不会回落到 $default）。
     * 每次读取都会顺带触发过期检查，被判定过期的条目会被立即清除。
     *
     * @param string $key     缓存键
     * @param mixed  $default 键不存在或已过期时返回的默认值
     * @return mixed 命中时返回缓存值，否则返回 $default
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
     * 直接 unset 对应条目，键不存在时静默返回（幂等），不抛异常也不影响统计。
     *
     * @param string $key 缓存键
     * @return void
     */
    public function delete(string $key): void
    {
        unset($this->storage[$key]);
    }

    /**
     * 原子递增
     *
     * 读取当前值（不存在或已过期按 0 计），非整数值统一归零后再加 $step 并写回；
     * 写回时保留条目原有的 TTL 与标签（TTL 按剩余时间续期），不会清空元信息。
     *
     * @param string $key  缓存键
     * @param int    $step 增量，可为负数
     * @return int 递增后的值
     */
    public function increment(string $key, int $step = 1): int
    {
        $existing = $this->storage[$key] ?? null;
        $expired = $existing !== null
            && $existing['expires'] !== 0
            && $existing['expires'] < time();

        if ($existing === null || $expired) {
            $this->set($key, $step);

            return $step;
        }

        $current = is_int($existing['value']) ? $existing['value'] : 0;
        $value = $current + $step;

        $ttl = $existing['expires'] === 0 ? null : max(1, $existing['expires'] - time());
        $this->set($key, $value, $ttl, $existing['tags']);

        return $value;
    }

    /**
     * 原子递减
     *
     * 等价于 increment($key, -$step)，其余行为（类型归一、TTL 与标签保留）
     * 与 increment() 完全一致。
     *
     * @param string $key  缓存键
     * @param int    $step 减量，传负数则实际为递增
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
     *
     * 直接丢弃整个存储数组，包含过期与未过期条目；标签随之清空，
     * 但命中/未命中统计（stats）会保留，便于跨清理周期观察命中率。
     *
     * @return void
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
