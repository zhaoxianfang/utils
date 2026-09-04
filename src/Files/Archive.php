<?php

declare(strict_types=1);

namespace zxf\Utils\Files;

use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

/**
 * ZIP 归档压缩解压工具
 *
 * 封装 PHP ZipArchive 扩展，提供链式 API 进行压缩包创建、文件添加、
 * 解压、密码保护和注释管理等操作。
 *
 * @package Files
 * @version 1.0.0
 * @license MIT
 */
class Archive
{
    private ?ZipArchive $zip = null;

    private string $file = '';

    /**
     * 创建或打开一个 ZIP 文件
     *
     * @param string $file   ZIP 文件路径
     * @param bool   $create 不存在时是否创建（默认 true）
     * @return static
     */
    public static function open(string $file, bool $create = true): static
    {
        $instance = new static();
        $instance->zip = new ZipArchive();
        $instance->file = $file;

        $flags = $create ? ZipArchive::CREATE | ZipArchive::OVERWRITE : 0;
        if ($instance->zip->open($file, $flags) !== true) {
            throw new RuntimeException("无法打开 ZIP 文件: {$file}");
        }

        return $instance;
    }

    /**
     * 添加单个文件到压缩包
     *
     * @param string      $path     源文件路径
     * @param string|null $localName 压缩包内的路径名（默认使用文件名）
     * @return static
     */
    public function addFile(string $path, ?string $localName = null): static
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException("文件不存在: {$path}");
        }
        $name = $localName ?? basename($path);
        $this->zip->addFile($path, $name);
        return $this;
    }

    /**
     * 添加字符串内容作为压缩包内的文件
     *
     * @param string $localName 压缩包内的路径名
     * @param string $content   文件内容
     * @return static
     */
    public function addString(string $localName, string $content): static
    {
        $this->zip->addFromString($localName, $content);
        return $this;
    }

    /**
     * 递归添加整个目录到压缩包
     *
     * @param string $directory 源目录路径
     * @param string $prefix    压缩包内的前缀路径
     * @return static
     */
    public function addDirectory(string $directory, string $prefix = ''): static
    {
        if (!is_dir($directory)) {
            throw new InvalidArgumentException("目录不存在: {$directory}");
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $baseLen = strlen(rtrim($directory, '/\\'));
        foreach ($iterator as $file) {
            $relative = substr($file->getPathname(), $baseLen);
            $relative = ltrim(str_replace(['\\', '/'], '/', $relative), '/');
            $localName = $prefix ? rtrim($prefix, '/') . '/' . $relative : $relative;

            if ($file->isDir()) {
                $this->zip->addEmptyDir($localName);
            } else {
                $this->zip->addFile($file->getPathname(), $localName);
            }
        }

        return $this;
    }

    /**
     * 设置压缩包密码（加密）
     *
     * @param string $password 密码
     * @return static
     */
    public function setPassword(string $password): static
    {
        $this->zip->setPassword($password);
        return $this;
    }

    /**
     * 设置压缩包注释
     *
     * @param string $comment 注释内容
     * @return static
     */
    public function setComment(string $comment): static
    {
        $this->zip->setArchiveComment($comment);
        return $this;
    }

    /**
     * 关闭并保存压缩包
     */
    public function close(): void
    {
        if ($this->zip !== null) {
            $this->zip->close();
            $this->zip = null;
        }
    }

    /**
     * 解压整个压缩包到指定目录
     *
     * @param string      $destination 目标目录
     * @param string|null $password    解压密码（如有）
     * @return array 解压的文件列表
     */
    public function extract(string $destination, ?string $password = null): array
    {
        if ($password !== null) {
            // 注意：PHP ZipArchive 的 setPassword() 仅对 ZipCrypto/WinZip AES 加密有效，
            // 且 extractTo() 会按条目自动解密；若密码错误解压出的内容将是损坏的。
            // 这里仅设置密码，真正的校验在解压失败时由调用方自行判断。
            $this->zip->setPassword($password);
        }

        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }
        $baseDir = realpath($destination);
        if ($baseDir === false) {
            throw new RuntimeException('无法解析目标目录: ' . $destination);
        }
        $baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        $files = [];
        for ($i = 0; $i < $this->zip->numFiles; $i++) {
            // getNameIndex() 在读取失败时返回 false，直接参与字符串运算会触发 TypeError
            $name = $this->zip->getNameIndex($i);
            if ($name === false) {
                throw new RuntimeException("无法读取压缩包内第 {$i} 个条目的名称");
            }

            // 防御 Zip Slip（路径穿越）攻击：逐个校验条目名，
            // 通过“手动解析相对路径、禁止逃出根目录”的方式确保解压目标始终落在 $baseDir 内。
            // 注意：真正落盘的是 extractTo()，它用的是压缩包里的原始条目名，
            // 因此这里凡是无法确定为安全相对路径的条目一律拒绝，而不是“跳过校验”。
            $resolved = [];
            // 同时按 "/" 与 "\" 切分：Windows 下反斜杠同样是目录分隔符，
            // 只按 "/" 切分会让 "..\..\evil.exe" 绕过校验。
            foreach (preg_split('#[\\\\/]+#', $name) as $part) {
                if ($part === '' || $part === '.') {
                    continue;
                }
                if ($part === '..') {
                    // 越界的 ".."（如 "../../evil.txt"）直接拒绝，而不是在根目录处钳制
                    if ($resolved === []) {
                        throw new RuntimeException('压缩包内含非法路径，拒绝解压以防路径穿越: ' . $name);
                    }
                    array_pop($resolved);
                    continue;
                }
                $resolved[] = $part;
            }

            // 绝对路径条目（"/etc/passwd"、"C:\windows\x.exe"）与解析后为空的条目
            //（".."、"foo/.."）同样视为非法
            if ($resolved === []
                || str_starts_with($name, '/') || str_starts_with($name, '\\')
                || preg_match('#^[A-Za-z]:#', $name) === 1) {
                throw new RuntimeException('压缩包内含非法路径，拒绝解压以防路径穿越: ' . $name);
            }

            $target = $baseDir . implode(DIRECTORY_SEPARATOR, $resolved);
            if (! str_starts_with($target, $baseDir)) {
                throw new RuntimeException('压缩包内含非法路径，拒绝解压以防路径穿越: ' . $name);
            }
            $files[] = $name;
        }

        $this->zip->extractTo($destination);
        return $files;
    }

    /**
     * 获取压缩包内的文件列表
     *
     * @return string[]
     */
    public function list(): array
    {
        $files = [];
        for ($i = 0; $i < $this->zip->numFiles; $i++) {
            // getNameIndex() 失败时返回 false，跳过以保证返回值是 string[]
            $name = $this->zip->getNameIndex($i);
            if ($name !== false) {
                $files[] = $name;
            }
        }
        return $files;
    }

    /**
     * 读取压缩包内指定文件的内容
     *
     * @param string $localName 压缩包内的文件名
     * @return string|false
     */
    public function read(string $localName): string|false
    {
        return $this->zip->getFromName($localName);
    }

    /**
     * 快捷压缩文件或目录
     *
     * @param string       $zipFile     输出 ZIP 文件路径
     * @param string|array $source      源文件或目录（字符串或数组）
     * @param string|null  $password    加密密码
     * @return string 生成的 ZIP 文件路径
     */
    public static function compress(string $zipFile, string|array $source, ?string $password = null): string
    {
        $archive = self::open($zipFile);
        if ($password !== null) {
            $archive->setPassword($password);
        }

        foreach ((array) $source as $path) {
            if (is_dir($path)) {
                $archive->addDirectory($path);
            } elseif (is_file($path)) {
                $archive->addFile($path);
            }
        }

        $archive->close();
        return $zipFile;
    }

    /**
     * 快捷解压文件
     *
     * @param string      $zipFile     ZIP 文件路径
     * @param string      $destination 目标目录
     * @param string|null $password    解压密码
     * @return array 解压的文件列表
     */
    public static function decompress(string $zipFile, string $destination, ?string $password = null): array
    {
        $archive = self::open($zipFile, false);
        $files = $archive->extract($destination, $password);
        $archive->close();
        return $files;
    }

    public function __destruct()
    {
        $this->close();
    }
}
