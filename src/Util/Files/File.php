<?php

declare(strict_types=1);

namespace zxf\Util;

use FilesystemIterator;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use RecursiveCallbackFilterIterator;
use RuntimeException;
use InvalidArgumentException;
use SplFileInfo;
use Generator;
use JsonException;
use ZipArchive;

/**
 * 高级文件和目录操作封装类
 *
 * 功能特性：
 * 1. 完整的文件和目录CRUD操作
 * 2. 大文件内存优化处理
 * 3. 高级搜索和过滤功能
 * 4. 文件监控和备份恢复
 * 5. 批量操作和性能优化
 * 6. 安全删除和权限管理
 * 7. 压缩和解压缩支持
 * 8. 磁盘空间监控
 * 9. 文件上传和下载功能
 * 10. 流式文件操作
 *
 * @package zxf\Util
 * @version 3.0
 * @author AI Assistant
 */
class Files
{
    /**
     * 文件操作模式常量
     */
    public const MODE_READ = 'r';
    public const MODE_WRITE = 'w';
    public const MODE_APPEND = 'a';
    public const MODE_READ_WRITE = 'r+';
    public const MODE_CREATE_WRITE = 'x';
    public const MODE_BINARY_READ = 'rb';
    public const MODE_BINARY_WRITE = 'wb';

    /**
     * 文件类型常量
     */
    public const TYPE_FILE = 'file';
    public const TYPE_DIRECTORY = 'directory';
    public const TYPE_LINK = 'link';

    /**
     * 文件大小单位常量
     */
    private const SIZE_UNITS = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];

    /**
     * 上传文件错误码常量
     */
    private const UPLOAD_ERRORS = [
        UPLOAD_ERR_OK => '文件上传成功',
        UPLOAD_ERR_INI_SIZE => '上传的文件大小超过了 php.ini 中 upload_max_filesize 选项限制的值',
        UPLOAD_ERR_FORM_SIZE => '上传文件的大小超过了 HTML 表单中 MAX_FILE_SIZE 选项指定的值',
        UPLOAD_ERR_PARTIAL => '文件只有部分被上传',
        UPLOAD_ERR_NO_FILE => '没有文件被上传',
        UPLOAD_ERR_NO_TMP_DIR => '找不到临时文件夹',
        UPLOAD_ERR_CANT_WRITE => '文件写入失败',
        UPLOAD_ERR_EXTENSION => 'PHP 扩展程序停止了文件上传'
    ];

    /**
     * 创建目录 - 支持递归创建和权限设置
     *
     * @param string $path 目录路径
     * @param int $permissions 目录权限，默认0755
     * @param bool $recursive 是否递归创建，默认true
     * @return bool 创建成功返回true
     * @throws RuntimeException 当目录创建失败时抛出异常
     */
    public static function createDirectory(string $path, int $permissions = 0755, bool $recursive = true): bool
    {
        // 规范化路径
        $path = self::normalizePath($path);

        // 如果目录已存在，只更新权限
        if (is_dir($path)) {
            return chmod($path, $permissions);
        }

        // 尝试创建目录
        if (!mkdir($path, $permissions, $recursive) && !is_dir($path)) {
            throw new RuntimeException(sprintf('目录创建失败: "%s"，请检查路径和权限', $path));
        }

        return true;
    }

    /**
     * 创建嵌套目录结构 - 一次性创建多级嵌套目录
     *
     * @param string $basePath 基础路径
     * @param array $directories 目录名称数组
     * @param int $permissions 目录权限，默认0755
     * @return bool 创建成功返回true
     */
    public static function createNestedDirectories(string $basePath, array $directories, int $permissions = 0755): bool
    {
        $currentPath = rtrim(self::normalizePath($basePath), DIRECTORY_SEPARATOR);

        foreach ($directories as $directory) {
            $currentPath .= DIRECTORY_SEPARATOR . trim($directory, DIRECTORY_SEPARATOR);
            self::createDirectory($currentPath, $permissions);
        }

        return true;
    }

    /**
     * 创建文件并写入内容 - 支持多种数据类型
     *
     * @param string $filePath 文件路径
     * @param mixed $content 文件内容，支持字符串、数组、对象等
     * @param int $flags 文件操作标志，默认0
     * @return bool 创建成功返回true
     */
    public static function createFile(string $filePath, mixed $content = '', int $flags = 0): bool
    {
        $filePath = self::normalizePath($filePath);
        $directory = dirname($filePath);

        // 确保目录存在
        if (!is_dir($directory)) {
            self::createDirectory($directory);
        }

        // 处理数组和对象数据
        if (is_array($content) || is_object($content)) {
            $content = serialize($content);
            $flags |= LOCK_EX; // 序列化数据时自动加锁
        }

        // 写入文件内容
        $result = file_put_contents($filePath, (string)$content, $flags);

        if ($result === false) {
            throw new RuntimeException(sprintf('文件创建失败: "%s"', $filePath));
        }

        return true;
    }

    /**
     * 创建空文件 - 仅创建文件不写入内容
     *
     * @param string $filePath 文件路径
     * @return bool 创建成功返回true
     */
    public static function createEmptyFile(string $filePath): bool
    {
        return self::createFile($filePath, '');
    }

    /**
     * 读取文件内容 - 支持指定偏移量和长度
     *
     * @param string $filePath 文件路径
     * @param int|null $offset 读取偏移量
     * @param int|null $length 读取长度
     * @return string 文件内容
     * @throws RuntimeException 当文件不存在或读取失败时抛出异常
     */
    public static function readFile(string $filePath, ?int $offset = null, ?int $length = null): string
    {
        $filePath = self::normalizePath($filePath);

        // 检查文件是否存在和可读
        if (!is_file($filePath)) {
            throw new RuntimeException(sprintf('文件不存在: "%s"', $filePath));
        }

        if (!is_readable($filePath)) {
            throw new RuntimeException(sprintf('文件不可读: "%s"', $filePath));
        }

        // 根据参数选择读取方式
        $content = $length !== null
            ? file_get_contents($filePath, false, null, $offset ?? 0, $length)
            : file_get_contents($filePath, false, null, $offset ?? 0);

        if ($content === false) {
            throw new RuntimeException(sprintf('文件读取失败: "%s"', $filePath));
        }

        return $content;
    }

    /**
     * 使用生成器读取大文件 - 内存友好的大文件读取方式
     *
     * @param string $filePath 文件路径
     * @param int $chunkSize 每次读取的块大小，默认8KB
     * @return Generator 返回生成器，逐块产生文件内容
     */
    public static function readFileAsGenerator(string $filePath, int $chunkSize = 8192): Generator
    {
        $filePath = self::normalizePath($filePath);

        if (!is_file($filePath)) {
            throw new RuntimeException(sprintf('文件不存在: "%s"', $filePath));
        }

        $handle = fopen($filePath, self::MODE_BINARY_READ);
        if (!$handle) {
            throw new RuntimeException(sprintf('文件打开失败: "%s"', $filePath));
        }

        try {
            while (!feof($handle)) {
                $chunk = fread($handle, $chunkSize);
                if ($chunk !== false) {
                    yield $chunk;
                }
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * 逐行读取文件 - 适用于日志文件等文本文件
     *
     * @param string $filePath 文件路径
     * @return Generator 返回生成器，逐行产生文件内容
     */
    public static function readFileLineByLine(string $filePath): Generator
    {
        $filePath = self::normalizePath($filePath);

        if (!is_file($filePath)) {
            throw new RuntimeException(sprintf('文件不存在: "%s"', $filePath));
        }

        $handle = fopen($filePath, self::MODE_READ);
        if (!$handle) {
            throw new RuntimeException(sprintf('文件打开失败: "%s"', $filePath));
        }

        try {
            while (($line = fgets($handle)) !== false) {
                yield rtrim($line, "\r\n");
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * 读取文件流 - 返回文件资源句柄
     *
     * @param string $filePath 文件路径
     * @param string $mode 打开模式
     * @return resource 文件资源句柄
     */
    public static function readFileStream(string $filePath, string $mode = self::MODE_BINARY_READ)
    {
        $filePath = self::normalizePath($filePath);

        if (!is_file($filePath)) {
            throw new RuntimeException(sprintf('文件不存在: "%s"', $filePath));
        }

        $handle = fopen($filePath, $mode);
        if (!$handle) {
            throw new RuntimeException(sprintf('文件流打开失败: "%s"', $filePath));
        }

        return $handle;
    }

    /**
     * 保存文件流 - 将流内容保存到文件
     *
     * @param resource $stream 文件流资源
     * @param string $filePath 保存路径
     * @param bool $autoClose 是否自动关闭流
     * @return bool 保存成功返回true
     */
    public static function saveFileStream($stream, string $filePath, bool $autoClose = true): bool
    {
        if (!is_resource($stream)) {
            throw new RuntimeException('参数必须是一个有效的文件流资源');
        }

        $filePath = self::normalizePath($filePath);
        $directory = dirname($filePath);

        if (!is_dir($directory)) {
            self::createDirectory($directory);
        }

        $handle = fopen($filePath, self::MODE_BINARY_WRITE);
        if (!$handle) {
            throw new RuntimeException(sprintf('目标文件打开失败: "%s"', $filePath));
        }

        try {
            rewind($stream);
            while (!feof($stream)) {
                $chunk = fread($stream, 8192);
                if ($chunk !== false) {
                    fwrite($handle, $chunk);
                }
            }

            if ($autoClose) {
                fclose($stream);
            }

            return true;
        } finally {
            fclose($handle);
        }
    }

    /**
     * 读取JSON文件 - 自动解析JSON内容
     *
     * @param string $filePath JSON文件路径
     * @param bool $associative 是否返回关联数组，默认true
     * @return array 解析后的JSON数据
     * @throws RuntimeException 当JSON解析失败时抛出异常
     */
    public static function readJsonFile(string $filePath, bool $associative = true): array
    {
        $content = self::readFile($filePath);

        try {
            return json_decode($content, $associative, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException(sprintf('JSON解析失败: "%s" - 错误信息: %s', $filePath, $e->getMessage()));
        }
    }

    /**
     * 读取序列化文件 - 自动反序列化PHP数据
     *
     * @param string $filePath 序列化文件路径
     * @return mixed 反序列化后的数据
     */
    public static function readSerializedFile(string $filePath): mixed
    {
        $content = self::readFile($filePath);
        $data = unserialize($content, ['allowed_classes' => true]);

        if ($data === false && $content !== serialize(false)) {
            throw new RuntimeException(sprintf('反序列化失败: "%s"', $filePath));
        }

        return $data;
    }

    /**
     * 写入JSON文件 - 自动编码数据为JSON格式
     *
     * @param string $filePath 文件路径
     * @param mixed $data 要写入的数据
     * @param int $flags JSON编码选项
     * @return bool 写入成功返回true
     */
    public static function writeJsonFile(string $filePath, mixed $data, int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE): bool
    {
        try {
            $content = json_encode($data, $flags | JSON_THROW_ON_ERROR);
            return self::createFile($filePath, $content, LOCK_EX);
        } catch (JsonException $e) {
            throw new RuntimeException(sprintf('JSON编码失败: %s', $e->getMessage()));
        }
    }

    /**
     * 文件上传处理 - 安全处理文件上传
     *
     * @param array $uploadFile $_FILES数组中的文件项
     * @param string $targetDirectory 目标目录
     * @param string|null $newFilename 新文件名，不指定则使用原文件名
     * @param array $allowedTypes 允许的文件类型MIME数组
     * @param int $maxSize 最大文件大小（字节），0表示不限制
     * @return array 上传结果信息
     */
    public static function uploadFile(array $uploadFile, string $targetDirectory, ?string $newFilename = null, array $allowedTypes = [], int $maxSize = 0): array
    {
        // 检查上传错误
        if ($uploadFile['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException(
                self::UPLOAD_ERRORS[$uploadFile['error']] ?? sprintf('未知上传错误: %d', $uploadFile['error'])
            );
        }

        // 检查文件是否通过HTTP POST上传
        if (!is_uploaded_file($uploadFile['tmp_name'])) {
            throw new RuntimeException('文件不是通过HTTP POST上传的');
        }

        // 检查文件大小
        if ($maxSize > 0 && $uploadFile['size'] > $maxSize) {
            throw new RuntimeException(sprintf('文件大小超过限制: %s > %s',
                self::formatSize($uploadFile['size']),
                self::formatSize($maxSize)
            ));
        }

        // 检查文件类型
        if (!empty($allowedTypes)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $uploadFile['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedTypes, true)) {
                throw new RuntimeException(sprintf('不允许的文件类型: %s', $mimeType));
            }
        }

        // 创建目标目录
        self::createDirectory($targetDirectory);

        // 生成目标文件名
        $filename = $newFilename ?? $uploadFile['name'];
        $targetPath = self::normalizePath($targetDirectory . DIRECTORY_SEPARATOR . $filename);

        // 移动上传文件
        if (!move_uploaded_file($uploadFile['tmp_name'], $targetPath)) {
            throw new RuntimeException(sprintf('文件移动失败: "%s"', $targetPath));
        }

        return [
            'success' => true,
            'path' => $targetPath,
            'filename' => $filename,
            'size' => $uploadFile['size'],
            'original_name' => $uploadFile['name'],
            'mime_type' => $uploadFile['type'] ?? 'unknown'
        ];
    }

    /**
     * 批量文件上传处理
     *
     * @param array $uploadFiles $_FILES数组
     * @param string $targetDirectory 目标目录
     * @param array $allowedTypes 允许的文件类型
     * @param int $maxSize 最大文件大小
     * @return array 上传结果数组
     */
    public static function uploadMultipleFiles(array $uploadFiles, string $targetDirectory, array $allowedTypes = [], int $maxSize = 0): array
    {
        $results = [];

        // 标准化文件数组结构
        $files = self::normalizeFilesArray($uploadFiles);

        foreach ($files as $file) {
            try {
                $result = self::uploadFile($file, $targetDirectory, null, $allowedTypes, $maxSize);
                $result['original_index'] = $file['index'] ?? null;
                $results[] = $result;
            } catch (RuntimeException $e) {
                $results[] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'original_name' => $file['name'],
                    'original_index' => $file['index'] ?? null
                ];
            }
        }

        return $results;
    }

    /**
     * 文件下载 - 发送文件到浏览器下载
     *
     * @param string $filePath 文件路径
     * @param string|null $downloadName 下载显示的文件名
     * @param bool $deleteAfterDownload 下载后是否删除文件
     * @return void
     */
    public static function downloadFile(string $filePath, ?string $downloadName = null, bool $deleteAfterDownload = false): void
    {
        $filePath = self::normalizePath($filePath);

        if (!is_file($filePath)) {
            throw new RuntimeException(sprintf('下载文件不存在: "%s"', $filePath));
        }

        if (!is_readable($filePath)) {
            throw new RuntimeException(sprintf('文件不可读: "%s"', $filePath));
        }

        // 设置HTTP头
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . ($downloadName ?? basename($filePath)) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));

        // 清空输出缓冲区
        if (ob_get_length()) {
            ob_clean();
        }
        flush();

        // 读取文件并输出
        readfile($filePath);

        // 下载后删除文件
        if ($deleteAfterDownload) {
            unlink($filePath);
        }

        exit;
    }

    /**
     * 流式下载大文件 - 支持断点续传
     *
     * @param string $filePath 文件路径
     * @param string|null $downloadName 下载显示的文件名
     * @param int $chunkSize 分块大小
     * @return void
     */
    public static function streamDownload(string $filePath, ?string $downloadName = null, int $chunkSize = 8192): void
    {
        $filePath = self::normalizePath($filePath);

        if (!is_file($filePath)) {
            throw new RuntimeException(sprintf('下载文件不存在: "%s"', $filePath));
        }

        $fileSize = filesize($filePath);
        $downloadName = $downloadName ?? basename($filePath);

        // 处理断点续传
        $range = null;
        if (isset($_SERVER['HTTP_RANGE'])) {
            preg_match('/bytes=(\d+)-(\d+)?/', $_SERVER['HTTP_RANGE'], $matches);
            $start = (int)($matches[1] ?? 0);
            $end = isset($matches[2]) ? (int)$matches[2] : $fileSize - 1;
            $range = [$start, $end];
        }

        // 设置HTTP头
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Accept-Ranges: bytes');

        if ($range) {
            header('HTTP/1.1 206 Partial Content');
            header('Content-Range: bytes ' . $range[0] . '-' . $range[1] . '/' . $fileSize);
            header('Content-Length: ' . ($range[1] - $range[0] + 1));
        } else {
            header('Content-Length: ' . $fileSize);
        }

        // 清空输出缓冲区
        if (ob_get_length()) {
            ob_clean();
        }
        flush();

        // 流式输出文件内容
        $handle = fopen($filePath, 'rb');
        if ($range) {
            fseek($handle, $range[0]);
            $length = $range[1] - $range[0] + 1;
        } else {
            $length = $fileSize;
        }

        $bytesSent = 0;
        while (!feof($handle) && $bytesSent < $length) {
            $chunk = fread($handle, min($chunkSize, $length - $bytesSent));
            echo $chunk;
            $bytesSent += strlen($chunk);
            flush();
        }

        fclose($handle);
        exit;
    }

    /**
     * 文件内容搜索和替换 - 支持批量替换操作
     *
     * @param string $filePath 文件路径
     * @param array|string $search 要搜索的内容
     * @param array|string $replace 替换内容
     * @param bool $caseSensitive 是否区分大小写，默认true
     * @return bool 替换成功返回true，无变化返回false
     */
    public static function searchAndReplace(string $filePath, array|string $search, array|string $replace, bool $caseSensitive = true): bool
    {
        $content = self::readFile($filePath);

        // 根据大小写设置选择替换函数
        if ($caseSensitive) {
            $newContent = str_replace($search, $replace, $content);
        } else {
            $newContent = str_ireplace($search, $replace, $content);
        }

        // 检查内容是否有变化
        if ($content === $newContent) {
            return false;
        }

        return self::createFile($filePath, $newContent, LOCK_EX);
    }

    /**
     * 在文件中搜索文本 - 返回匹配的行号和内容
     *
     * @param string $filePath 文件路径
     * @param string $search 搜索文本
     * @param bool $caseSensitive 是否区分大小写
     * @return array 匹配结果数组，键为行号，值为行内容
     */
    public static function searchInFile(string $filePath, string $search, bool $caseSensitive = true): array
    {
        $matches = [];
        $lineNumber = 0;

        foreach (self::readFileLineByLine($filePath) as $line) {
            $lineNumber++;
            $subject = $caseSensitive ? $line : strtolower($line);
            $searchText = $caseSensitive ? $search : strtolower($search);

            if (str_contains($subject, $searchText)) {
                $matches[$lineNumber] = $line;
            }
        }

        return $matches;
    }

    /**
     * 文件差异比较 - 比较两个文件的差异
     *
     * @param string $file1 第一个文件路径
     * @param string $file2 第二个文件路径
     * @return array 比较结果数组
     */
    public static function compareFiles(string $file1, string $file2): array
    {
        if (!is_file($file1) || !is_file($file2)) {
            throw new RuntimeException('要比较的两个文件都必须存在');
        }

        $content1 = self::readFile($file1);
        $content2 = self::readFile($file2);

        return [
            'identical' => $content1 === $content2,
            'size_difference' => strlen($content1) - strlen($content2),
            'size_absolute_difference' => abs(strlen($content1) - strlen($content2)),
            'md5_match' => md5($content1) === md5($content2),
            'sha1_match' => sha1($content1) === sha1($content2),
            'file1_size' => strlen($content1),
            'file2_size' => strlen($content2),
            'similarity' => similar_text($content1, $content2, $percent) ? $percent : 0
        ];
    }

    /**
     * 复制文件 - 支持覆盖控制和目录自动创建
     *
     * @param string $source 源文件路径
     * @param string $destination 目标文件路径
     * @param bool $overwrite 是否覆盖已存在文件
     * @return bool 复制成功返回true
     */
    public static function copyFile(string $source, string $destination, bool $overwrite = true): bool
    {
        $source = self::normalizePath($source);
        $destination = self::normalizePath($destination);

        if (!is_file($source)) {
            throw new RuntimeException(sprintf('源文件不存在: "%s"', $source));
        }

        if (is_file($destination) && !$overwrite) {
            throw new RuntimeException(sprintf('目标文件已存在: "%s"', $destination));
        }

        $destinationDir = dirname($destination);
        if (!is_dir($destinationDir)) {
            self::createDirectory($destinationDir);
        }

        if (!copy($source, $destination)) {
            throw new RuntimeException(sprintf('文件复制失败: 从 "%s" 到 "%s"', $source, $destination));
        }

        return true;
    }

    /**
     * 复制目录 - 递归复制整个目录结构
     *
     * @param string $source 源目录路径
     * @param string $destination 目标目录路径
     * @param bool $overwrite 是否覆盖已存在文件
     * @param callable|null $filter 文件过滤回调函数
     * @return bool 复制成功返回true
     */
    public static function copyDirectory(string $source, string $destination, bool $overwrite = true, ?callable $filter = null): bool
    {
        $source = self::normalizePath($source);
        $destination = self::normalizePath($destination);

        if (!is_dir($source)) {
            throw new RuntimeException(sprintf('源目录不存在: "%s"', $source));
        }

        if (!is_dir($destination)) {
            self::createDirectory($destination);
        }

        $iterator = self::createFilteredIterator($source, $filter);

        foreach ($iterator as $item) {
            $targetPath = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();

            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    self::createDirectory($targetPath);
                }
            } else {
                if ($overwrite || !is_file($targetPath)) {
                    self::copyFile($item->getPathname(), $targetPath, $overwrite);
                }
            }
        }

        return true;
    }

    /**
     * 移动文件或目录 - 支持覆盖控制
     *
     * @param string $source 源路径
     * @param string $destination 目标路径
     * @param bool $overwrite 是否覆盖已存在路径
     * @return bool 移动成功返回true
     */
    public static function move(string $source, string $destination, bool $overwrite = true): bool
    {
        $source = self::normalizePath($source);
        $destination = self::normalizePath($destination);

        if (!file_exists($source)) {
            throw new RuntimeException(sprintf('源路径不存在: "%s"', $source));
        }

        if (file_exists($destination) && !$overwrite) {
            throw new RuntimeException(sprintf('目标路径已存在: "%s"', $destination));
        }

        $destinationDir = dirname($destination);
        if (!is_dir($destinationDir)) {
            self::createDirectory($destinationDir);
        }

        // 如果目标存在且允许覆盖，先删除目标
        if (file_exists($destination) && $overwrite) {
            self::delete($destination);
        }

        if (!rename($source, $destination)) {
            throw new RuntimeException(sprintf('移动失败: 从 "%s" 到 "%s"', $source, $destination));
        }

        return true;
    }

    /**
     * 删除文件或目录 - 统一删除方法
     *
     * @param string $path 要删除的路径
     * @return bool 删除成功返回true
     */
    public static function delete(string $path): bool
    {
        $path = self::normalizePath($path);

        if (!file_exists($path)) {
            return true;
        }

        if (is_file($path) || is_link($path)) {
            if (!unlink($path)) {
                throw new RuntimeException(sprintf('文件删除失败: "%s"', $path));
            }
            return true;
        }

        if (is_dir($path)) {
            return self::deleteDirectory($path);
        }

        throw new RuntimeException(sprintf('无法识别的路径类型: "%s"', $path));
    }

    /**
     * 安全删除目录 - 防止误删大目录
     *
     * @param string $directory 要删除的目录
     * @param int $maxSize 最大安全删除大小（字节），默认100MB
     * @return bool 删除成功返回true
     */
    public static function safeDeleteDirectory(string $directory, int $maxSize = 104857600): bool
    {
        $directory = self::normalizePath($directory);

        if (!is_dir($directory)) {
            return true;
        }

        $size = self::getDirectorySize($directory);
        if ($size > $maxSize) {
            throw new RuntimeException(sprintf(
                '目录大小 (%s) 超过安全限制 (%s)，拒绝删除。如确认要删除，请使用 deleteDirectory 方法',
                self::formatSize($size),
                self::formatSize($maxSize)
            ));
        }

        return self::deleteDirectory($directory);
    }

    /**
     * 递归删除目录 - 内部实现方法
     *
     * @param string $directory 要删除的目录
     * @return bool 删除成功返回true
     */
    private static function deleteDirectory(string $directory): bool
    {
        if (!is_dir($directory)) {
            return true;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                if (!rmdir($item->getPathname())) {
                    throw new RuntimeException(sprintf('目录删除失败: "%s"', $item->getPathname()));
                }
            } else {
                if (!unlink($item->getPathname())) {
                    throw new RuntimeException(sprintf('文件删除失败: "%s"', $item->getPathname()));
                }
            }
        }

        if (!rmdir($directory)) {
            throw new RuntimeException(sprintf('根目录删除失败: "%s"', $directory));
        }

        return true;
    }

    /**
     * 清空目录内容但保留目录本身
     *
     * @param string $directory 要清空的目录
     * @return bool 清空成功返回true
     */
    public static function emptyDirectory(string $directory): bool
    {
        $directory = self::normalizePath($directory);

        if (!is_dir($directory)) {
            throw new RuntimeException(sprintf('目录不存在: "%s"', $directory));
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                if (!rmdir($item->getPathname())) {
                    throw new RuntimeException(sprintf('子目录删除失败: "%s"', $item->getPathname()));
                }
            } else {
                if (!unlink($item->getPathname())) {
                    throw new RuntimeException(sprintf('文件删除失败: "%s"', $item->getPathname()));
                }
            }
        }

        return true;
    }

    /**
     * 获取目录统计信息 - 详细的目录分析
     *
     * @param string $directory 要分析的目录
     * @return array 统计信息数组
     */
    public static function getDirectoryStats(string $directory): array
    {
        $directory = self::normalizePath($directory);

        if (!is_dir($directory)) {
            throw new RuntimeException(sprintf('目录不存在: "%s"', $directory));
        }

        $fileCount = 0;
        $dirCount = 0;
        $totalSize = 0;
        $extensions = [];
        $largestFile = ['size' => 0, 'path' => ''];
        $oldestFile = ['time' => PHP_INT_MAX, 'path' => ''];
        $newestFile = ['time' => 0, 'path' => ''];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $fileCount++;
                $size = $item->getSize();
                $totalSize += $size;

                // 记录最大文件
                if ($size > $largestFile['size']) {
                    $largestFile = ['size' => $size, 'path' => $item->getPathname()];
                }

                // 记录最旧文件
                $mtime = $item->getMTime();
                if ($mtime < $oldestFile['time']) {
                    $oldestFile = ['time' => $mtime, 'path' => $item->getPathname()];
                }

                // 记录最新文件
                if ($mtime > $newestFile['time']) {
                    $newestFile = ['time' => $mtime, 'path' => $item->getPathname()];
                }

                // 统计扩展名
                $extension = strtolower($item->getExtension());
                if ($extension) {
                    $extensions[$extension] = ($extensions[$extension] ?? 0) + 1;
                }
            } elseif ($item->isDir()) {
                $dirCount++;
            }
        }

        arsort($extensions);

        return [
            'files' => $fileCount,
            'directories' => $dirCount,
            'total_items' => $fileCount + $dirCount,
            'total_size' => $totalSize,
            'total_size_formatted' => self::formatSize($totalSize),
            'extensions' => $extensions,
            'largest_file' => $largestFile,
            'oldest_file' => $oldestFile,
            'newest_file' => $newestFile,
            'last_modified' => self::getModifiedTime($directory)
        ];
    }

    /**
     * 高级文件搜索 - 支持多种搜索条件
     *
     * @param string $directory 搜索目录
     * @param callable|null $filter 自定义过滤函数
     * @param bool $recursive 是否递归搜索
     * @return array 匹配的文件路径数组
     */
    public static function findFiles(string $directory, ?callable $filter = null, bool $recursive = true): array
    {
        $directory = self::normalizePath($directory);

        if (!is_dir($directory)) {
            throw new RuntimeException(sprintf('目录不存在: "%s"', $directory));
        }

        $files = [];
        $iterator = $recursive
            ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS))
            : new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS);

        foreach ($iterator as $item) {
            if ($item->isFile() && (!$filter || $filter($item))) {
                $files[] = $item->getPathname();
            }
        }

        return $files;
    }

    /**
     * 按通配符模式搜索文件
     *
     * @param string $directory 搜索目录
     * @param string $pattern 通配符模式
     * @param bool $recursive 是否递归搜索
     * @return array 匹配的文件路径数组
     */
    public static function findFilesByPattern(string $directory, string $pattern, bool $recursive = true): array
    {
        $filter = fn(SplFileInfo $file) => fnmatch($pattern, $file->getFilename());
        return self::findFiles($directory, $filter, $recursive);
    }

    /**
     * 按扩展名搜索文件
     *
     * @param string $directory 搜索目录
     * @param array|string $extensions 扩展名或扩展名数组
     * @param bool $recursive 是否递归搜索
     * @return array 匹配的文件路径数组
     */
    public static function findFilesByExtension(string $directory, array|string $extensions, bool $recursive = true): array
    {
        if (is_string($extensions)) {
            $extensions = [$extensions];
        }

        $extensions = array_map('strtolower', $extensions);

        $filter = fn(SplFileInfo $file) => in_array(
            strtolower($file->getExtension()),
            $extensions,
            true
        );

        return self::findFiles($directory, $filter, $recursive);
    }

    /**
     * 按文件大小搜索文件
     *
     * @param string $directory 搜索目录
     * @param int|null $minSize 最小文件大小（字节）
     * @param int|null $maxSize 最大文件大小（字节）
     * @param bool $recursive 是否递归搜索
     * @return array 匹配的文件路径数组
     */
    public static function findFilesBySize(string $directory, ?int $minSize = null, ?int $maxSize = null, bool $recursive = true): array
    {
        $filter = fn(SplFileInfo $file) => (
            ($minSize === null || $file->getSize() >= $minSize) &&
            ($maxSize === null || $file->getSize() <= $maxSize)
        );

        return self::findFiles($directory, $filter, $recursive);
    }

    /**
     * 按修改时间搜索文件
     *
     * @param string $directory 搜索目录
     * @param int|null $startTime 开始时间戳
     * @param int|null $endTime 结束时间戳
     * @param bool $recursive 是否递归搜索
     * @return array 匹配的文件路径数组
     */
    public static function findFilesByModifiedTime(string $directory, ?int $startTime = null, ?int $endTime = null, bool $recursive = true): array
    {
        $filter = fn(SplFileInfo $file) => (
            ($startTime === null || $file->getMTime() >= $startTime) &&
            ($endTime === null || $file->getMTime() <= $endTime)
        );

        return self::findFiles($directory, $filter, $recursive);
    }

    /**
     * 创建文件索引 - 生成目录的文件索引
     *
     * @param string $directory 要索引的目录
     * @param bool $includeStats 是否包含详细统计信息
     * @return array 文件索引数组
     */
    public static function createFileIndex(string $directory, bool $includeStats = false): array
    {
        $index = [];
        $files = self::findFiles($directory, null, true);

        foreach ($files as $file) {
            $fileInfo = [
                'path' => $file,
                'filename' => basename($file),
                'size' => filesize($file),
                'modified' => filemtime($file),
                'extension' => pathinfo($file, PATHINFO_EXTENSION)
            ];

            if ($includeStats) {
                $fileInfo['md5'] = md5_file($file);
                $fileInfo['sha1'] = sha1_file($file);
                $fileInfo['permissions'] = substr(sprintf('%o', fileperms($file)), -4);
                $fileInfo['mime_type'] = mime_content_type($file);
            }

            $index[] = $fileInfo;
        }

        return $index;
    }

    /**
     * 查找重复文件 - 基于文件内容哈希
     *
     * @param string $directory 搜索目录
     * @param bool $recursive 是否递归搜索
     * @return array 重复文件分组数组
     */
    public static function findDuplicateFiles(string $directory, bool $recursive = true): array
    {
        $files = self::findFiles($directory, null, $recursive);
        $hashes = [];
        $duplicates = [];

        foreach ($files as $file) {
            $hash = md5_file($file);

            if (!isset($hashes[$hash])) {
                $hashes[$hash] = [];
            }

            $hashes[$hash][] = $file;
        }

        foreach ($hashes as $hash => $fileList) {
            if (count($fileList) > 1) {
                $duplicates[$hash] = [
                    'files' => $fileList,
                    'count' => count($fileList),
                    'size' => filesize($fileList[0])
                ];
            }
        }

        return $duplicates;
    }

    /**
     * 批量重命名文件 - 支持自定义重命名逻辑
     *
     * @param string $directory 目录路径
     * @param callable $renameCallback 重命名回调函数
     * @param bool $recursive 是否递归处理
     * @return array 重命名结果数组
     */
    public static function batchRename(string $directory, callable $renameCallback, bool $recursive = false): array
    {
        $files = self::findFiles($directory, null, $recursive);
        $results = [];

        foreach ($files as $file) {
            $newName = $renameCallback(basename($file), $file);
            $newPath = dirname($file) . DIRECTORY_SEPARATOR . $newName;

            if ($file !== $newPath) {
                try {
                    if (self::move($file, $newPath)) {
                        $results[] = [
                            'old' => $file,
                            'new' => $newPath,
                            'success' => true
                        ];
                    } else {
                        $results[] = [
                            'old' => $file,
                            'new' => $newPath,
                            'success' => false,
                            'error' => '重命名操作失败'
                        ];
                    }
                } catch (RuntimeException $e) {
                    $results[] = [
                        'old' => $file,
                        'new' => $newPath,
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * 更改文件扩展名
     *
     * @param string $filePath 文件路径
     * @param string $newExtension 新的扩展名
     * @return bool 更改成功返回true
     */
    public static function changeExtension(string $filePath, string $newExtension): bool
    {
        if (!is_file($filePath)) {
            throw new RuntimeException(sprintf('文件不存在: "%s"', $filePath));
        }

        $newPath = pathinfo($filePath, PATHINFO_DIRNAME) . DIRECTORY_SEPARATOR .
            pathinfo($filePath, PATHINFO_FILENAME) . '.' . ltrim($newExtension, '.');

        return self::move($filePath, $newPath);
    }

    /**
     * 创建过滤迭代器 - 内部工具方法
     */
    private static function createFilteredIterator(string $directory, ?callable $filter = null): RecursiveIteratorIterator
    {
        $dirIterator = new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS);

        if ($filter) {
            $dirIterator = new RecursiveCallbackFilterIterator($dirIterator, $filter);
        }

        return new RecursiveIteratorIterator($dirIterator, RecursiveIteratorIterator::SELF_FIRST);
    }

    /**
     * 获取文件详细信息 - 完整的文件元数据
     *
     * @param string $filePath 文件路径
     * @return array 文件详细信息数组
     */
    public static function getFileInfo(string $filePath): array
    {
        $filePath = self::normalizePath($filePath);

        if (!is_file($filePath)) {
            throw new RuntimeException(sprintf('文件不存在: "%s"', $filePath));
        }

        $stat = stat($filePath);
        $pathInfo = pathinfo($filePath);

        return [
            'path' => $filePath,
            'filename' => $pathInfo['filename'] ?? '',
            'extension' => $pathInfo['extension'] ?? '',
            'dirname' => $pathInfo['dirname'] ?? '',
            'size' => $stat['size'],
            'size_formatted' => self::formatSize($stat['size']),
            'modified' => $stat['mtime'],
            'accessed' => $stat['atime'],
            'created' => $stat['ctime'],
            'permissions' => substr(sprintf('%o', fileperms($filePath)), -4),
            'inode' => $stat['ino'],
            'device' => $stat['dev'],
            'type' => filetype($filePath),
            'mime_type' => mime_content_type($filePath),
            'md5' => md5_file($filePath),
            'sha1' => sha1_file($filePath),
            'readable' => is_readable($filePath),
            'writable' => is_writable($filePath),
            'executable' => is_executable($filePath)
        ];
    }

    /**
     * 监控目录变化 - 实时监控文件系统变化
     *
     * @param string $directory 要监控的目录
     * @param int $interval 检查间隔（秒）
     * @return Generator 返回变化信息的生成器
     */
    public static function monitorDirectory(string $directory, int $interval = 5): Generator
    {
        $directory = self::normalizePath($directory);

        if (!is_dir($directory)) {
            throw new RuntimeException(sprintf('目录不存在: "%s"', $directory));
        }

        $previousState = self::createFileIndex($directory, true);

        while (true) {
            sleep($interval);

            $currentState = self::createFileIndex($directory, true);
            $changes = [];

            // 检查新增和修改的文件
            foreach ($currentState as $currentFile) {
                $found = false;
                foreach ($previousState as $previousFile) {
                    if ($currentFile['path'] === $previousFile['path']) {
                        $found = true;

                        // 检查文件是否被修改
                        if ($currentFile['modified'] !== $previousFile['modified'] ||
                            $currentFile['size'] !== $previousFile['size'] ||
                            $currentFile['md5'] !== $previousFile['md5']) {
                            $changes[] = [
                                'type' => 'modified',
                                'file' => $currentFile['path'],
                                'previous' => $previousFile,
                                'current' => $currentFile,
                                'timestamp' => time()
                            ];
                        }
                        break;
                    }
                }

                // 新增文件
                if (!$found) {
                    $changes[] = [
                        'type' => 'created',
                        'file' => $currentFile['path'],
                        'info' => $currentFile,
                        'timestamp' => time()
                    ];
                }
            }

            // 检查删除的文件
            foreach ($previousState as $previousFile) {
                $found = false;
                foreach ($currentState as $currentFile) {
                    if ($currentFile['path'] === $previousFile['path']) {
                        $found = true;
                        break;
                    }
                }

                // 文件被删除
                if (!$found) {
                    $changes[] = [
                        'type' => 'deleted',
                        'file' => $previousFile['path'],
                        'info' => $previousFile,
                        'timestamp' => time()
                    ];
                }
            }

            // 如果有变化，返回变化信息
            if (!empty($changes)) {
                yield $changes;
            }

            $previousState = $currentState;
        }
    }

    /**
     * 创建目录树结构 - 生成目录的树形结构
     *
     * @param string $directory 目录路径
     * @param int $maxDepth 最大深度
     * @return array 目录树数组
     */
    public static function createDirectoryTree(string $directory, int $maxDepth = 5): array
    {
        $directory = self::normalizePath($directory);

        if (!is_dir($directory)) {
            throw new RuntimeException(sprintf('目录不存在: "%s"', $directory));
        }

        return self::buildTree($directory, $maxDepth);
    }

    /**
     * 递归构建目录树 - 修复和优化的内部工具方法
     */
    private static function buildTree(string $directory, int $maxDepth, int $currentDepth = 0): array
    {
        // 检查深度限制
        if ($currentDepth >= $maxDepth) {
            return [];
        }

        $tree = [];

        try {
            // 创建目录迭代器
            $iterator = new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS);

            foreach ($iterator as $item) {
                // 构建节点信息
                $node = [
                    'name' => $item->getFilename(),
                    'path' => $item->getPathname(),
                    'type' => $item->isDir() ? 'directory' : 'file',
                    'size' => $item->isFile() ? $item->getSize() : 0,
                    'size_formatted' => $item->isFile() ? self::formatSize($item->getSize()) : '0 B',
                    'modified' => $item->getMTime(),
                    'permissions' => substr(sprintf('%o', $item->getPerms()), -4)
                ];

                // 如果是目录，递归构建子树
                if ($item->isDir()) {
                    try {
                        $node['children'] = self::buildTree($item->getPathname(), $maxDepth, $currentDepth + 1);
                        // 统计文件数量
                        $node['file_count'] = count(self::findFiles($item->getPathname(),
                            fn($f) => $f->isFile(), true));
                        $node['dir_count'] = count(self::findFiles($item->getPathname(),
                            fn($f) => $f->isDir(), true));
                    } catch (RuntimeException $e) {
                        // 如果无法访问子目录，记录错误但继续处理其他项目
                        $node['children'] = [];
                        $node['error'] = $e->getMessage();
                    }
                }

                $tree[] = $node;
            }

            // 按类型和名称排序：目录在前，文件在后
            usort($tree, function($a, $b) {
                if ($a['type'] !== $b['type']) {
                    return $a['type'] === 'directory' ? -1 : 1;
                }
                return strnatcasecmp($a['name'], $b['name']);
            });

        } catch (RuntimeException $e) {
            // 如果无法读取目录，返回空数组
            return [];
        }

        return $tree;
    }

    /**
     * 备份文件或目录 - 创建带时间戳的备份
     *
     * @param string $source 要备份的路径
     * @param string $backupDir 备份目录
     * @param string|null $backupName 备份名称
     * @return string 备份路径
     */
    public static function backup(string $source, string $backupDir, string $backupName = null): string
    {
        $source = self::normalizePath($source);
        $backupDir = self::normalizePath($backupDir);

        if (!file_exists($source)) {
            throw new RuntimeException(sprintf('源路径不存在: "%s"', $source));
        }

        self::createDirectory($backupDir);

        $timestamp = date('Y-m-d-His');
        $sourceName = basename($source);
        $backupName = $backupName ?? $sourceName . '_' . $timestamp;
        $backupPath = $backupDir . DIRECTORY_SEPARATOR . $backupName;

        if (is_file($source)) {
            self::copyFile($source, $backupPath);
        } elseif (is_dir($source)) {
            self::copyDirectory($source, $backupPath);
        }

        return $backupPath;
    }

    /**
     * 从备份恢复 - 恢复备份的文件或目录
     *
     * @param string $backupPath 备份路径
     * @param string $targetPath 目标路径
     * @param bool $overwrite 是否覆盖已存在文件
     * @return bool 恢复成功返回true
     */
    public static function restore(string $backupPath, string $targetPath, bool $overwrite = true): bool
    {
        $backupPath = self::normalizePath($backupPath);
        $targetPath = self::normalizePath($targetPath);

        if (!file_exists($backupPath)) {
            throw new RuntimeException(sprintf('备份文件不存在: "%s"', $backupPath));
        }

        if (file_exists($targetPath) && !$overwrite) {
            throw new RuntimeException(sprintf('目标路径已存在: "%s"', $targetPath));
        }

        if (is_file($backupPath)) {
            return self::copyFile($backupPath, $targetPath, $overwrite);
        } elseif (is_dir($backupPath)) {
            return self::copyDirectory($backupPath, $targetPath, $overwrite);
        }

        return false;
    }

    /**
     * 压缩目录 - 创建ZIP压缩包
     *
     * @param string $directory 要压缩的目录
     * @param string $zipPath ZIP文件路径
     * @return bool 压缩成功返回true
     */
    public static function compressDirectory(string $directory, string $zipPath): bool
    {
        $directory = self::normalizePath($directory);
        $zipPath = self::normalizePath($zipPath);

        if (!is_dir($directory)) {
            throw new RuntimeException(sprintf('目录不存在: "%s"', $directory));
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException(sprintf('ZIP文件创建失败: "%s"', $zipPath));
        }

        $files = self::findFiles($directory, null, true);

        foreach ($files as $file) {
            $relativePath = substr($file, strlen($directory) + 1);
            $zip->addFile($file, $relativePath);
        }

        if (!$zip->close()) {
            throw new RuntimeException(sprintf('ZIP文件关闭失败: "%s"', $zipPath));
        }

        return true;
    }

    /**
     * 解压缩文件 - 解压ZIP文件
     *
     * @param string $zipPath ZIP文件路径
     * @param string $extractTo 解压目标目录
     * @return bool 解压成功返回true
     */
    public static function extractZip(string $zipPath, string $extractTo): bool
    {
        $zipPath = self::normalizePath($zipPath);
        $extractTo = self::normalizePath($extractTo);

        if (!is_file($zipPath)) {
            throw new RuntimeException(sprintf('ZIP文件不存在: "%s"', $zipPath));
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException(sprintf('ZIP文件打开失败: "%s"', $zipPath));
        }

        self::createDirectory($extractTo);

        if (!$zip->extractTo($extractTo)) {
            $zip->close();
            throw new RuntimeException(sprintf('ZIP文件解压失败: "%s"', $zipPath));
        }

        $zip->close();
        return true;
    }

    /**
     * 获取磁盘空间信息 - 查看磁盘使用情况
     *
     * @param string $path 磁盘路径
     * @return array 磁盘空间信息数组
     */
    public static function getDiskSpace(string $path = '/'): array
    {
        $total = disk_total_space($path);
        $free = disk_free_space($path);

        if ($total === false || $free === false) {
            throw new RuntimeException(sprintf('无法获取磁盘空间信息: "%s"', $path));
        }

        $used = $total - $free;

        return [
            'total' => $total,
            'free' => $free,
            'used' => $used,
            'usage_percentage' => $total > 0 ? round(($used / $total) * 100, 2) : 0,
            'total_formatted' => self::formatSize((int)$total),
            'free_formatted' => self::formatSize((int)$free),
            'used_formatted' => self::formatSize((int)$used)
        ];
    }

    /**
     * 获取文件大小
     *
     * @param string $filePath 文件路径
     * @return int 文件大小（字节）
     */
    public static function getFileSize(string $filePath): int
    {
        $filePath = self::normalizePath($filePath);

        if (!is_file($filePath)) {
            throw new RuntimeException(sprintf('文件不存在: "%s"', $filePath));
        }

        $size = filesize($filePath);

        if ($size === false) {
            throw new RuntimeException(sprintf('无法获取文件大小: "%s"', $filePath));
        }

        return $size;
    }

    /**
     * 获取目录大小 - 递归计算目录总大小
     *
     * @param string $directory 目录路径
     * @return int 目录总大小（字节）
     */
    public static function getDirectorySize(string $directory): int
    {
        $directory = self::normalizePath($directory);

        if (!is_dir($directory)) {
            throw new RuntimeException(sprintf('目录不存在: "%s"', $directory));
        }

        $size = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $size += $item->getSize();
            }
        }

        return $size;
    }

    /**
     * 获取文件修改时间
     *
     * @param string $path 文件或目录路径
     * @return int 修改时间戳
     */
    public static function getModifiedTime(string $path): int
    {
        $path = self::normalizePath($path);

        if (!file_exists($path)) {
            throw new RuntimeException(sprintf('路径不存在: "%s"', $path));
        }

        $mtime = filemtime($path);

        if ($mtime === false) {
            throw new RuntimeException(sprintf('无法获取修改时间: "%s"', $path));
        }

        return $mtime;
    }

    /**
     * 格式化文件大小 - 将字节转换为人类可读格式
     *
     * @param int $bytes 字节数
     * @param int $precision 小数精度
     * @return string 格式化后的文件大小
     */
    public static function formatSize(int $bytes, int $precision = 2): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count(self::SIZE_UNITS) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . self::SIZE_UNITS[$pow];
    }

    /**
     * 安全写入文件 - 先写入临时文件再重命名，避免写入过程中断
     *
     * @param string $filePath 文件路径
     * @param string $content 文件内容
     * @return bool 写入成功返回true
     */
    public static function safeWrite(string $filePath, string $content): bool
    {
        $filePath = self::normalizePath($filePath);
        $tempFile = $filePath . '.tmp';

        if (file_put_contents($tempFile, $content, LOCK_EX) === false) {
            throw new RuntimeException(sprintf('临时文件写入失败: "%s"', $tempFile));
        }

        if (!rename($tempFile, $filePath)) {
            unlink($tempFile);
            throw new RuntimeException(sprintf('文件重命名失败: "%s"', $filePath));
        }

        return true;
    }

    /**
     * 检查文件或目录是否存在
     *
     * @param string $path 路径
     * @return bool 存在返回true
     */
    public static function exists(string $path): bool
    {
        $path = self::normalizePath($path);
        return file_exists($path);
    }

    /**
     * 检查是否为文件
     *
     * @param string $path 路径
     * @return bool 是文件返回true
     */
    public static function isFile(string $path): bool
    {
        $path = self::normalizePath($path);
        return is_file($path);
    }

    /**
     * 检查是否为目录
     *
     * @param string $path 路径
     * @return bool 是目录返回true
     */
    public static function isDirectory(string $path): bool
    {
        $path = self::normalizePath($path);
        return is_dir($path);
    }

    /**
     * 检查文件是否可读
     *
     * @param string $path 路径
     * @return bool 可读返回true
     */
    public static function isReadable(string $path): bool
    {
        $path = self::normalizePath($path);
        return is_readable($path);
    }

    /**
     * 检查文件是否可写
     *
     * @param string $path 路径
     * @return bool 可写返回true
     */
    public static function isWritable(string $path): bool
    {
        $path = self::normalizePath($path);
        return is_writable($path);
    }

    /**
     * 获取文件扩展名
     *
     * @param string $filePath 文件路径
     * @return string 文件扩展名
     */
    public static function getExtension(string $filePath): string
    {
        return pathinfo($filePath, PATHINFO_EXTENSION);
    }

    /**
     * 获取文件名（不含扩展名）
     *
     * @param string $filePath 文件路径
     * @return string 文件名
     */
    public static function getFilename(string $filePath): string
    {
        return pathinfo($filePath, PATHINFO_FILENAME);
    }

    /**
     * 获取文件 basename
     *
     * @param string $filePath 文件路径
     * @return string 文件basename
     */
    public static function getBasename(string $filePath): string
    {
        return pathinfo($filePath, PATHINFO_BASENAME);
    }

    /**
     * 获取目录名
     *
     * @param string $filePath 文件路径
     * @return string 目录名
     */
    public static function getDirname(string $filePath): string
    {
        return pathinfo($filePath, PATHINFO_DIRNAME);
    }

    /**
     * 规范化路径 - 统一路径分隔符和解析相对路径
     *
     * @param string $path 原始路径
     * @return string 规范化后的路径
     */
    private static function normalizePath(string $path): string
    {
        // 替换路径分隔符
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        // 解析相对路径
        $parts = [];
        foreach (explode(DIRECTORY_SEPARATOR, $path) as $part) {
            if ($part === '..') {
                array_pop($parts);
            } elseif ($part !== '' && $part !== '.') {
                $parts[] = $part;
            }
        }

        return implode(DIRECTORY_SEPARATOR, $parts);
    }

    /**
     * 标准化文件数组 - 处理多文件上传的数组结构
     *
     * @param array $files $_FILES数组
     * @return array 标准化后的文件数组
     */
    private static function normalizeFilesArray(array $files): array
    {
        $normalized = [];

        foreach ($files as $key => $file) {
            if (is_array($file['name'])) {
                // 多文件上传的情况
                foreach ($file['name'] as $index => $name) {
                    $normalized[] = [
                        'name' => $name,
                        'type' => $file['type'][$index],
                        'tmp_name' => $file['tmp_name'][$index],
                        'error' => $file['error'][$index],
                        'size' => $file['size'][$index],
                        'index' => $index
                    ];
                }
            } else {
                // 单文件上传的情况
                $file['index'] = 0;
                $normalized[] = $file;
            }
        }

        return $normalized;
    }

    /**
     * 保存上传的文件 - 简化版上传方法
     *
     * @param array $uploadedFile 上传的文件数组
     * @param string $destination 目标路径
     * @return array 保存成功返回true
     */
    public static function saveUploadedFile(array $uploadedFile, string $destination): array
    {
        return self::uploadFile($uploadedFile, dirname($destination), basename($destination));
    }

    /**
     * 获取文件哈希值 - 支持多种哈希算法
     *
     * @param string $filePath 文件路径
     * @param string $algorithm 哈希算法，默认md5
     * @return string 文件哈希值
     */
    public static function getFileHash(string $filePath, string $algorithm = 'md5'): string
    {
        $filePath = self::normalizePath($filePath);

        if (!is_file($filePath)) {
            throw new RuntimeException(sprintf('文件不存在: "%s"', $filePath));
        }

        $hash = hash_file($algorithm, $filePath);

        if ($hash === false) {
            throw new RuntimeException(sprintf('无法计算文件哈希: "%s"', $filePath));
        }

        return $hash;
    }

    /**
     * 创建符号链接
     *
     * @param string $target 目标路径
     * @param string $link 链接路径
     * @return bool 创建成功返回true
     */
    public static function createSymlink(string $target, string $link): bool
    {
        $target = self::normalizePath($target);
        $link = self::normalizePath($link);

        if (file_exists($link)) {
            throw new RuntimeException(sprintf('链接已存在: "%s"', $link));
        }

        return symlink($target, $link);
    }

    /**
     * 创建硬链接
     *
     * @param string $target 目标文件
     * @param string $link 链接文件
     * @return bool 创建成功返回true
     */
    public static function createHardlink(string $target, string $link): bool
    {
        $target = self::normalizePath($target);
        $link = self::normalizePath($link);

        if (!is_file($target)) {
            throw new RuntimeException(sprintf('目标文件不存在: "%s"', $target));
        }

        if (file_exists($link)) {
            throw new RuntimeException(sprintf('链接已存在: "%s"', $link));
        }

        return link($target, $link);
    }

    /**
     * 获取文件行数 - 统计文件总行数
     *
     * @param string $filePath 文件路径
     * @return int 文件行数
     */
    public static function getLineCount(string $filePath): int
    {
        $filePath = self::normalizePath($filePath);

        if (!is_file($filePath)) {
            throw new RuntimeException(sprintf('文件不存在: "%s"', $filePath));
        }

        $lineCount = 0;
        $handle = fopen($filePath, 'r');

        if (!$handle) {
            throw new RuntimeException(sprintf('无法打开文件: "%s"', $filePath));
        }

        while (!feof($handle)) {
            fgets($handle);
            $lineCount++;
        }

        fclose($handle);
        return $lineCount;
    }

    /**
     * 获取文件编码 - 检测文本文件编码
     *
     * @param string $filePath 文件路径
     * @return string 文件编码
     */
    public static function getFileEncoding(string $filePath): string
    {
        $filePath = self::normalizePath($filePath);

        if (!is_file($filePath)) {
            throw new RuntimeException(sprintf('文件不存在: "%s"', $filePath));
        }

        $content = self::readFile($filePath, 0, 4096); // 读取前4KB用于检测编码

        if (function_exists('mb_detect_encoding')) {
            $encoding = mb_detect_encoding($content, ['UTF-8', 'GBK', 'GB2312', 'BIG5', 'ASCII'], true);
            return $encoding ?: 'unknown';
        }

        return 'unknown';
    }

    /**
     * 转换文件编码
     *
     * @param string $filePath 文件路径
     * @param string $fromEncoding 原编码
     * @param string $toEncoding 目标编码
     * @return bool 转换成功返回true
     */
    public static function convertFileEncoding(string $filePath, string $fromEncoding, string $toEncoding = 'UTF-8'): bool
    {
        $filePath = self::normalizePath($filePath);

        if (!is_file($filePath)) {
            throw new RuntimeException(sprintf('文件不存在: "%s"', $filePath));
        }

        if (!function_exists('mb_convert_encoding')) {
            throw new RuntimeException('需要启用 mbstring 扩展');
        }

        $content = self::readFile($filePath);
        $convertedContent = mb_convert_encoding($content, $toEncoding, $fromEncoding);

        return self::createFile($filePath, $convertedContent, LOCK_EX);
    }
}
