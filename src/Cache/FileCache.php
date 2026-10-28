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
     * 键名先经 PSR-16 规则校验，非法键直接抛异常；随后按 md5 分片定位文件，
     * 目录不存在时自动创建。$ttl 为负数时回落到构造时设置的默认 TTL，
     * 为 0 表示永不过期。写入采用「临时文件 + rename」的原子替换，
     * 序列化失败（如 JSON 模式下存在非法 UTF-8）返回 false 且不落盘。
     *
     * @param string $key   缓存键名，长度不超过 64 且不得包含 {}()/\@: 等保留字符
     * @param mixed  $value 缓存值（任意可序列化类型）
     * @param int    $ttl   过期时间（秒），0 为永不过期，负数使用默认 TTL
     * @return bool 写入成功返回 true；目录不可写或序列化失败返回 false
     * @throws RuntimeException 缓存键非法时抛出
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
     * 文件不存在、内容为空或载荷结构损坏时返回 $default；
     * 若载荷已过期，会顺带删除该缓存文件再返回 $default。
     *
     * @param string $key     缓存键名
     * @param mixed  $default 缓存不存在、已过期或损坏时的默认值
     * @return mixed 命中时返回原始缓存值（可能为 null），否则返回 $default
     * @throws RuntimeException 缓存键非法时抛出
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
     * 只有文件真实存在时才尝试 unlink；文件本就不存在视为删除成功（幂等），
     * 因此重复调用不会报错。
     *
     * @param string $key 缓存键名
     * @return bool 删除成功（或本就不存在）返回 true，unlink 失败返回 false
     * @throws RuntimeException 缓存键非法时抛出
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
     * 遍历写入，单个键失败不会中断后续键的处理；整型键会被强制转为字符串，
     * 避免 strict_types 下触发 TypeError。返回值只反映「是否全部成功」，
     * 不做整体回滚，因此可能出现部分键已写入的情况。
     *
     * @param array<int|string, mixed> $items 键值对数组，如 ['key1' => 'value1', 2 => 'value2']
     * @param int                      $ttl   过期时间（秒），0 永不过期，-1 使用默认 TTL
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
     * 逐个复用 get() 读取，未命中或已过期的键在结果中填入 $default；
     * 整型键会被转为字符串后再查询，但结果数组仍以原始键名索引。
     *
     * @param array<int|string> $keys    缓存键名数组
     * @param mixed             $default 缓存不存在或已过期时使用的默认值
     * @return array<int|string, mixed> 以键名为索引的结果数组，顺序与 $keys 一致
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
     * 逐个调用 delete()，删除不存在的键也算成功，因此重复批量删除是幂等的；
     * 单个键失败不会中断后续键的删除。
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
     *
     * 返回构造时传入的目录路径（已去掉结尾的斜杠与反斜杠），
     * 不校验该目录当前是否存在或可写。
     *
     * @return string 缓存文件存储目录的绝对路径或相对路径
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
     *
     * 使用 'c+' 模式打开文件并加 LOCK_EX 独占锁，在锁内完成「读 → 过期判定
     * → 加步长 → 截断重写」的完整流程，保证并发计数不丢失。
     * 已过期的旧值按 0 起算，写回时统一使用默认 TTL。
     * 目录创建失败、文件无法打开、加锁失败或序列化失败时返回 0（即计数未生效）。
     *
     * @param string $key  缓存键名，调用方需保证已通过合法性校验
     * @param int    $step 增减步长，正数递增、负数递减
     * @return int 增减后的数值，失败时返回 0
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
     *
     * 临时文件名为「目标文件 + .tmp.<pid>」，写入后 rename 覆盖目标；
     * 若 rename 失败（如 Windows 下目标被占用）会先清理临时文件，
     * 再退回直接覆盖写入。
     *
     * @param string $file    目标文件完整路径
     * @param string $content 待写入的序列化载荷
     * @return bool 写入成功返回 true，写临时文件与回退写入均失败时返回 false
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
     * 序列化缓存载荷
     *
     * 按构造参数选择 PHP 序列化或 JSON 编码；JSON 模式启用
     * JSON_UNESCAPED_UNICODE 与 JSON_PRESERVE_ZERO_FRACTION，
     * 遇到非 UTF-8 字符串或递归引用等编码失败情形返回 null，
     * 由调用方中止写入，避免把空内容落盘导致缓存静默丢失。
     *
     * @param array<string, mixed> $data 含 expires 与 value 的缓存载荷
     * @return string|null 序列化后的字符串，编码失败返回 null
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
     * 反序列化缓存载荷
     *
     * 序列化模式下限定 allowed_classes=false，杜绝反序列化出任意对象引发 POP 链攻击；
     * JSON 模式按关联数组解析。解析结果不是数组（含内容损坏、类型不符）时返回 null，
     * 由调用方按「未命中」处理。
     *
     * @param string $content 从缓存文件读到的原始内容
     * @return array<string, mixed>|null 解析成功返回载荷数组，失败返回 null
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
     *
     * 目录已存在时仅做可写判断；不存在则递归创建（权限 0755）。
     * 创建后仍不可写时返回 false，由调用方决定是否中止写入。
     *
     * @param string $dir 目录路径
     * @return bool 目录存在且可写返回 true，存在但不可写返回 false
     * @throws RuntimeException 目录无法创建时抛出
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
     * 依次检查三项：键不能为空串、长度不得超过 64 个字符、
     * 不得包含 {}()/\@: 等保留字符。任一不满足即抛出异常。
     *
     * @param string $key 待校验的缓存键名
     * @return void
     * @throws RuntimeException 键为空、超长或含保留字符时抛出
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
     *
     * 首次调用时惰性创建一个 stdClass 并缓存在静态属性上，后续调用返回同一引用。
     * 由于对象比较默认按引用，has() 可借此区分「值为 null」与「键不存在」。
     *
     * @return object 进程内唯一的哨兵对象
     */
    private static function missSentinel(): object
    {
        return self::$missSentinel ??= new \stdClass();
    }
}
