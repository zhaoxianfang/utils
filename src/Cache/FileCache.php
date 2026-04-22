<?php

declare(strict_types=1);

namespace zxf\Utils\Cache;

use InvalidArgumentException;
use RuntimeException;

/**
 * 简易文件缓存类
 * 基于 JSON 或 PHP 序列化的文件存储缓存，支持 TTL 过期、数值自增减、批量读写和目录分片
 *
 * @package Cache
 * @version 1.0.0
 * @license MIT
 */
class FileCache
{
    /** @var string 缓存文件存储目录 */
    private string $cacheDir;

    /** @var string 缓存文件扩展名 */
    private string $extension = '.cache';

    /** @var int 默认 TTL（秒），0 表示永不过期 */
    private int $defaultTtl = 3600;

    /** @var bool 是否使用 PHP 序列化（true 支持对象存储，false 使用 JSON 更通用） */
    private bool $useSerialize = true;

    /**
     * 构造函数
     *
     * @param string $cacheDir     缓存目录路径
     * @param int    $defaultTtl   默认过期时间（秒），0 表示永不过期
     * @param bool   $useSerialize 是否使用 PHP 序列化（支持对象），默认 true；false 则使用 JSON
     * @throws RuntimeException 当缓存目录无法创建时抛出
     */
    public function __construct(string $cacheDir = __DIR__ . '/../../runtime/cache', int $defaultTtl = 3600, bool $useSerialize = true)
    {
        $this->cacheDir = rtrim($cacheDir, '/\\');
        $this->defaultTtl = $defaultTtl;
        $this->useSerialize = $useSerialize;

        if (!is_dir($this->cacheDir)) {
            if (!mkdir($this->cacheDir, 0755, true)) {
                throw new RuntimeException('无法创建缓存目录: ' . $this->cacheDir);
            }
        }
    }

    /**
     * 写入缓存
     *
     * @param string $key   缓存键名
     * @param mixed  $value 缓存值（任意可序列化类型）
     * @param int    $ttl   过期时间（秒），0 为永不过期，-1 使用默认 TTL
     * @return bool 写入成功返回 true
     */
    public function set(string $key, mixed $value, int $ttl = -1): bool
    {
        $file = $this->getFilePath($key);
        $dir = dirname($file);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            return false;
        }

        $ttl = $ttl < 0 ? $this->defaultTtl : $ttl;
        $expires = $ttl === 0 ? 0 : time() + $ttl;
        $data = [
            'expires' => $expires,
            'value'   => $value,
        ];

        $content = $this->useSerialize ? serialize($data) : json_encode($data, JSON_UNESCAPED_UNICODE);
        return file_put_contents($file, $content, LOCK_EX) !== false;
    }

    /**
     * 读取缓存
     *
     * @param string $key     缓存键名
     * @param mixed  $default 缓存不存在或已过期时的默认值
     * @return mixed 缓存值或默认值
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->getFilePath($key);
        if (!file_exists($file)) {
            return $default;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return $default;
        }

        $data = $this->useSerialize ? @unserialize($content) : json_decode($content, true);
        if (!is_array($data) || !array_key_exists('expires', $data) || !array_key_exists('value', $data)) {
            return $default;
        }

        // 检查过期
        if ($data['expires'] !== 0 && $data['expires'] < time()) {
            $this->delete($key);
            return $default;
        }

        return $data['value'];
    }

    /**
     * 判断缓存是否存在且未过期
     *
     * @param string $key 缓存键名
     * @return bool 存在且有效返回 true
     */
    public function has(string $key): bool
    {
        return $this->get($key, $this) !== $this;
    }

    /**
     * 删除缓存
     *
     * @param string $key 缓存键名
     * @return bool 删除成功返回 true
     */
    public function delete(string $key): bool
    {
        $file = $this->getFilePath($key);
        if (file_exists($file)) {
            return unlink($file);
        }
        return true;
    }

    /**
     * 递增数值缓存（键不存在时视为 0）
     *
     * @param string $key  缓存键名
     * @param int    $step 步长，默认 1
     * @return int 递增后的数值
     */
    public function increment(string $key, int $step = 1): int
    {
        $value = (int) $this->get($key, 0);
        $value += $step;
        $this->set($key, $value);
        return $value;
    }

    /**
     * 递减数值缓存（键不存在时视为 0）
     *
     * @param string $key  缓存键名
     * @param int    $step 步长，默认 1
     * @return int 递减后的数值
     */
    public function decrement(string $key, int $step = 1): int
    {
        return $this->increment($key, -$step);
    }

    /**
     * 批量写入缓存
     *
     * @param array $items 键值对数组，如 ['key1' => 'value1', 'key2' => 'value2']
     * @param int   $ttl   过期时间（秒），-1 使用默认 TTL
     * @return bool 全部写入成功返回 true，任一失败返回 false
     */
    public function setMultiple(array $items, int $ttl = -1): bool
    {
        $success = true;
        foreach ($items as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * 批量读取缓存
     *
     * @param array  $keys    缓存键名数组
     * @param mixed  $default 默认值
     * @return array 以键名为索引的结果数组
     */
    public function getMultiple(array $keys, mixed $default = null): array
    {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
        }
        return $results;
    }

    /**
     * 批量删除缓存
     *
     * @param array $keys 缓存键名数组
     * @return bool 全部删除成功返回 true，任一失败返回 false
     */
    public function deleteMultiple(array $keys): bool
    {
        $success = true;
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * 清空所有缓存（删除缓存目录及其所有内容）
     *
     * @return bool 清空成功返回 true
     */
    public function clear(): bool
    {
        return $this->deleteDirectory($this->cacheDir);
    }

    /**
     * 根据缓存键计算文件存储路径
     *
     * 使用 MD5 前 2 位作为子目录，避免单目录文件过多
     *
     * @param string $key 缓存键名
     * @return string 缓存文件完整路径
     */
    private function getFilePath(string $key): string
    {
        $hash = md5($key);
        // 按前2位分目录，避免单目录文件过多
        $subdir = substr($hash, 0, 2);
        return $this->cacheDir . DIRECTORY_SEPARATOR . $subdir . DIRECTORY_SEPARATOR . $hash . $this->extension;
    }

    /**
     * 递归删除目录及其内容
     *
     * @param string $dir 目录路径
     * @return bool 删除成功返回 true
     */
    private function deleteDirectory(string $dir): bool
    {
        if (!is_dir($dir)) return true;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        return rmdir($dir);
    }
}
