<?php

declare(strict_types=1);

namespace zxf\Utils\Cache;

use RuntimeException;

/**
 * 简易文件缓存类
 *
 * 基于 JSON 或 PHP 序列化的文件存储缓存，支持 TTL 过期、原子写入、数值自增减、
 * 批量读写和目录分片。
 *
 * 设计要点：
 * - 键名按 PSR-16 规范校验（保留字符与超长键直接拒绝），键一律经 md5 分片落盘，
 *   因此天然免疫路径穿越。
 * - 写入采用「临时文件 + rename」的原子替换，避免读写进程读到半截内容。
 * - increment()/decrement() 使用独占文件锁，保证并发下的读改写原子性。
 * - 批量方法内部统一把键转为字符串，整型键（数组常见）不会触发 TypeError。
 *
 * @package Cache
 * @version 1.1.0
 * @license MIT
 */
class FileCache
{
    /** 缓存文件扩展名 */
    private const EXTENSION = '.cache';

    /** 临时文件后缀（配合 rename 实现原子写入） */
    private const TMP_SUFFIX = '.tmp';

    /** PSR-16 保留字符 */
    private const RESERVED_CHARACTERS = '{}()/\\@:';

    /** PSR-16 建议的最大键长度 */
    private const MAX_KEY_LENGTH = 64;

    /** @var string 缓存文件存储目录 */
    private string $cacheDir;

    /** @var int 默认 TTL（秒），0 表示永不过期 */
    private int $defaultTtl;

    /** @var bool 是否使用 PHP 序列化（true 支持对象存储，false 使用 JSON 更通用） */
    private bool $useSerialize;

    /**
     * 构造函数
     *
     * @param string $cacheDir     缓存目录路径
     * @param int    $defaultTtl   默认过期时间（秒），0 表示永不过期
     * @param bool   $useSerialize 是否使用 PHP 序列化（支持对象），默认 true；false 则使用 JSON
     *
     * @throws RuntimeException 当缓存目录无法创建或不可写时抛出
     */
    public function __construct(
        string $cacheDir = __DIR__ . '/../../runtime/cache',
        int $defaultTtl = 3600,
        bool $useSerialize = true
    ) {
        $this->cacheDir = rtrim($cacheDir, '/\\');
        $this->defaultTtl = max(0, $defaultTtl);
        $this->useSerialize = $useSerialize;

        $this->ensureDirectory($this->cacheDir);
    }

    /**
     * 写入缓存
     *
     * @param string $key   缓存键名
     * @param mixed  $value 缓存值（任意可序列化类型）
     * @param int    $ttl   过期时间（秒），0 为永不过期，负数使用默认 TTL
     * @return bool 写入成功返回 true
     */
    public function set(string $key, mixed $value, int $ttl = -1): bool
    {
        $this->assertValidKey($key);

        $file = $this->getFilePath($key);
        $dir = dirname($file);
        if (! $this->ensureDirectory($dir)) {
            return false;
        }

        $ttl = $ttl < 0 ? $this->defaultTtl : max(0, $ttl);
        $data = [
            'expires' => $ttl === 0 ? 0 : time() + $ttl,
            'value'   => $value,
        ];

        $content = $this->encode($data);
        if ($content === null) {
            return false;
        }

        return $this->atomicWrite($file, $content);
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
        $this->assertValidKey($key);

        $file = $this->getFilePath($key);
        if (! is_file($file)) {
            return $default;
        }

        $content = @file_get_contents($file);
        if ($content === false || $content === '') {
            return $default;
        }

        $data = $this->decode($content);
        if (! is_array($data) || ! array_key_exists('expires', $data) || ! array_key_exists('value', $data)) {
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
     * 使用静态哨兵对象区分「缓存值为 null」与「缓存不存在」，
     * 避免 has() 对已缓存 null 的键返回 false。
     *
     * @param string $key 缓存键名
     * @return bool 存在且有效返回 true
     */
    public function has(string $key): bool
    {
        $this->assertValidKey($key);

        $sentinel = self::missSentinel();

        return $this->get($key, $sentinel) !== $sentinel;
    }

    /**
     * 删除缓存
     *
     * @param string $key 缓存键名
     * @return bool 删除成功（或本就不存在）返回 true
     */
    public function delete(string $key): bool
    {
        $this->assertValidKey($key);

        $file = $this->getFilePath($key);
        if (! file_exists($file)) {
            return true;
        }

        return @unlink($file);
    }

    /**
     * 递增数值缓存（键不存在时视为 0）
     *
     * 整个读改写过程持有独占文件锁，可安全用于并发计数场景。
     *
     * @param string $key  缓存键名
     * @param int    $step 步长，默认 1
     * @return int 递增后的数值
     */
    public function increment(string $key, int $step = 1): int
    {
        $this->assertValidKey($key);

        return $this->delta($key, $step);
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
        $this->assertValidKey($key);

        return $this->delta($key, -$step);
    }

    /**
     * 批量写入缓存
     *
     * @param array<int|string, mixed> $items 键值对数组，如 ['key1' => 'value1', 2 => 'value2']
     * @param int                      $ttl   过期时间（秒），-1 使用默认 TTL
     * @return bool 全部写入成功返回 true，任一失败返回 false
     */
    public function setMultiple(array $items, int $ttl = -1): bool
    {
        $success = true;
        foreach ($items as $key => $value) {
            // 数组键为 int 时，strict_types 下直接传给 set(string) 会抛 TypeError
            if (! $this->set((string) $key, $value, $ttl)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * 批量读取缓存
     *
     * @param array<int|string> $keys    缓存键名数组
     * @param mixed             $default 默认值
     * @return array<int|string, mixed> 以键名为索引的结果数组
     */
    public function getMultiple(array $keys, mixed $default = null): array
    {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->get((string) $key, $default);
        }

        return $results;
    }

    /**
     * 批量删除缓存
     *
     * @param array<int|string> $keys 缓存键名数组
     * @return bool 全部删除成功返回 true，任一失败返回 false
     */
    public function deleteMultiple(array $keys): bool
    {
        $success = true;
        foreach ($keys as $key) {
            if (! $this->delete((string) $key)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * 清空所有缓存（删除缓存目录下的所有缓存文件，保留目录本身）
     *
     * @return bool 清空成功返回 true
     */
    public function clear(): bool
    {
        return $this->deleteDirectory($this->cacheDir, keepRoot: true);
    }

    /**
     * 获取缓存目录
     */
    public function getCacheDir(): string
    {
        return $this->cacheDir;
    }

    /**
     * 根据缓存键计算文件存储路径
     *
     * 使用 MD5 前 2 位作为子目录，避免单目录文件过多；
     * 由于最终文件名来自 md5，任何键都不会产生路径穿越。
     *
     * @param string $key 缓存键名
     * @return string 缓存文件完整路径
     */
    private function getFilePath(string $key): string
    {
        $hash = md5($key);
        $subdir = substr($hash, 0, 2);

        return $this->cacheDir . DIRECTORY_SEPARATOR . $subdir . DIRECTORY_SEPARATOR . $hash . self::EXTENSION;
    }

    /**
     * 以独占锁执行数值增减
     */
    private function delta(string $key, int $step): int
    {
        $file = $this->getFilePath($key);
        if (! $this->ensureDirectory(dirname($file))) {
            return 0;
        }

        $handle = @fopen($file, 'c+');
        if ($handle === false) {
            return 0;
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                return 0;
            }

            $raw = stream_get_contents($handle);
            $value = 0;

            if ($raw !== false && $raw !== '') {
                $data = $this->decode($raw);
                if (is_array($data) && array_key_exists('value', $data)) {
                    $expired = $data['expires'] !== 0 && $data['expires'] < time();
                    $value = $expired ? 0 : (int) $data['value'];
                }
            }

            $value += $step;

            $content = $this->encode([
                'expires' => $this->defaultTtl === 0 ? 0 : time() + $this->defaultTtl,
                'value'   => $value,
            ]);

            if ($content === null) {
                return 0;
            }

            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, $content);
            fflush($handle);

            return $value;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * 原子写入：先写临时文件再 rename，避免并发读取到不完整内容
     */
    private function atomicWrite(string $file, string $content): bool
    {
        $tmp = $file . self::TMP_SUFFIX . '.' . getmypid();

        if (@file_put_contents($tmp, $content, LOCK_EX) === false) {
            return false;
        }

        if (! @rename($tmp, $file)) {
            @unlink($tmp);

            // 部分平台（如 Windows 上目标被占用）rename 可能失败，回退为直接写
            return @file_put_contents($file, $content, LOCK_EX) !== false;
        }

        return true;
    }

    /**
     * 序列化缓存载荷，失败返回 null
     */
    private function encode(array $data): ?string
    {
        if ($this->useSerialize) {
            return serialize($data);
        }

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

        // json_encode 失败（如存在非 UTF-8 字符串 / 递归引用）时返回 false，
        // 若不加判断会把空内容写入文件，造成缓存静默丢失。
        return $json === false ? null : $json;
    }

    /**
     * 反序列化缓存载荷，失败返回 null
     */
    private function decode(string $content): ?array
    {
        if ($this->useSerialize) {
            // 禁止反序列化出任意类，防止 POP 链攻击
            $data = @unserialize($content, ['allowed_classes' => false]);

            return is_array($data) ? $data : null;
        }

        $data = json_decode($content, true);

        return is_array($data) ? $data : null;
    }

    /**
     * 确保目录存在且可写
     */
    private function ensureDirectory(string $dir): bool
    {
        if (is_dir($dir)) {
            return is_writable($dir);
        }

        if (! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException('无法创建缓存目录: ' . $dir);
        }

        return is_writable($dir);
    }

    /**
     * 校验缓存键合法性（遵循 PSR-16 约定）
     *
     * @throws RuntimeException 键非法时抛出
     */
    private function assertValidKey(string $key): void
    {
        if ($key === '') {
            throw new RuntimeException('缓存键不能为空');
        }

        if (strlen($key) > self::MAX_KEY_LENGTH) {
            throw new RuntimeException(sprintf(
                '缓存键长度不能超过 %d 个字符，当前为 %d',
                self::MAX_KEY_LENGTH,
                strlen($key)
            ));
        }

        if (strpbrk($key, self::RESERVED_CHARACTERS) !== false) {
            throw new RuntimeException(sprintf(
                '缓存键 "%s" 包含保留字符 %s',
                $key,
                self::RESERVED_CHARACTERS
            ));
        }
    }

    /**
     * 递归删除目录及其内容
     *
     * @param string $dir      目录路径
     * @param bool   $keepRoot 是否保留根目录本身
     * @return bool 删除成功返回 true
     */
    private function deleteDirectory(string $dir, bool $keepRoot = false): bool
    {
        if (! is_dir($dir)) {
            return true;
        }

        $entries = @scandir($dir);
        if ($entries === false) {
            return false;
        }

        $success = true;

        foreach (array_diff($entries, ['.', '..']) as $entry) {
            $path = $dir . DIRECTORY_SEPARATOR . $entry;

            if (is_dir($path) && ! is_link($path)) {
                $success = $this->deleteDirectory($path) && $success;
            } elseif (! @unlink($path)) {
                $success = false;
            }
        }

        if ($keepRoot) {
            return $success;
        }

        return @rmdir($dir) && $success;
    }

    /** @var object|null has() 的未命中哨兵，用于区分「缓存值为 null」与「缓存不存在」 */
    private static ?object $missSentinel = null;

    /**
     * 获取未命中哨兵实例（每个进程共享同一个对象引用）
     */
    private static function missSentinel(): object
    {
        return self::$missSentinel ??= new \stdClass();
    }
}
