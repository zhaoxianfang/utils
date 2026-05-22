<?php

declare(strict_types=1);

namespace zxf\Utils\Files;

/**
 * 跨平台路径处理工具类
 *
 * 提供路径拼接、规范化、相对路径计算、扩展名操作等常用功能，
 * 统一处理 Windows 反斜杠与 Unix 正斜杠的差异。
 *
 * @package Files
 * @version 1.0.0
 * @license MIT
 */
class Path
{
    /**
     * 智能拼接多个路径片段
     *
     * 自动处理多余的目录分隔符，保留协议前缀（如 file://、phar://）。
     *
     * @param string ...$paths 路径片段
     * @return string 拼接后的路径
     */
    public static function join(string ...$paths): string
    {
        if ($paths === []) {
            return '';
        }

        $first = array_shift($paths);
        $prefix = '';

        // 保留 Windows 盘符或协议前缀
        if (str_contains($first, '://')) {
            [$prefix, $first] = explode('://', $first, 2);
            $prefix .= '://';
        } elseif (str_starts_with($first, '\\')) {
            $prefix = '\\\\';
            $first = ltrim($first, '\\');
        } elseif (str_starts_with($first, '/')) {
            $prefix = '/';
            $first = ltrim($first, '/');
        }

        $segments = array_filter(
            array_merge([$first], $paths),
            static fn (string $p): bool => $p !== ''
        );

        $joined = implode('/', array_map(static fn (string $p): string => str_replace(['\\', '/'], '/', trim($p, '\\/')), $segments));

        return $prefix . $joined;
    }

    /**
     * 规范化路径，移除冗余的 . 和 ..
     *
     * @param string $path 原始路径
     * @return string 规范化后的路径
     */
    public static function normalize(string $path): string
    {
        $prefix = '';
        if (str_contains($path, '://')) {
            [$prefix, $path] = explode('://', $path, 2);
            $prefix .= '://';
        }

        $isAbsolute = str_starts_with($path, '/') || (strlen($path) > 1 && $path[1] === ':');
        $parts = explode('/', str_replace('\\', '/', $path));
        $stack = [];

        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                if ($stack !== [] && end($stack) !== '..') {
                    array_pop($stack);
                } elseif (!$isAbsolute) {
                    $stack[] = '..';
                }
            } else {
                $stack[] = $part;
            }
        }

        $result = implode('/', $stack);
        if ($isAbsolute && !str_starts_with($result, '/') && !str_contains($result, ':')) {
            $result = '/' . $result;
        }

        return $prefix . $result;
    }

    /**
     * 计算从基础路径到目标路径的相对路径
     *
     * @param string $from 基础路径（目录）
     * @param string $to   目标路径
     * @return string 相对路径
     */
    public static function relative(string $from, string $to): string
    {
        $from = rtrim(str_replace('\\', '/', self::normalize($from)), '/');
        $to   = str_replace('\\', '/', self::normalize($to));

        $fromParts = explode('/', $from);
        $toParts   = explode('/', $to);

        // 找到共同前缀长度
        $common = 0;
        $minLen = min(count($fromParts), count($toParts));
        for ($i = 0; $i < $minLen; $i++) {
            if ($fromParts[$i] === $toParts[$i]) {
                $common++;
            } else {
                break;
            }
        }

        $upSteps = count($fromParts) - $common;
        $downParts = array_slice($toParts, $common);

        $result = array_merge(array_fill(0, $upSteps, '..'), $downParts);
        return implode('/', $result) ?: '.';
    }

    /**
     * 获取路径中的文件名（含扩展名）
     *
     * @param string $path 文件路径
     * @return string 文件名
     */
    public static function basename(string $path): string
    {
        return basename(str_replace('\\', '/', $path));
    }

    /**
     * 获取路径中的目录部分
     *
     * @param string $path 文件路径
     * @return string 目录路径
     */
    public static function dirname(string $path): string
    {
        return dirname(str_replace('\\', '/', $path));
    }

    /**
     * 获取或替换文件扩展名
     *
     * @param string      $path     文件路径
     * @param string|null $newExt   新扩展名（不含点），null 表示仅获取
     * @return string 扩展名（获取模式）或新路径（替换模式）
     */
    public static function extension(string $path, ?string $newExt = null): string
    {
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        if ($newExt === null) {
            return $ext;
        }
        $base = pathinfo($path, PATHINFO_FILENAME);
        $dir  = dirname($path);
        return self::join($dir, $base . ($newExt !== '' ? '.' . $newExt : ''));
    }

    /**
     * 判断路径是否为绝对路径
     *
     * @param string $path 路径
     * @return bool
     */
    public static function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || str_starts_with($path, '\\') || (strlen($path) > 1 && ctype_alpha($path[0]) && $path[1] === ':');
    }

    /**
     * 确保路径以指定分隔符结尾（适用于目录路径）
     *
     * @param string $path 路径
     * @param string $sep  分隔符，默认为 DIRECTORY_SEPARATOR
     * @return string
     */
    public static function trailing(string $path, string $sep = DIRECTORY_SEPARATOR): string
    {
        return rtrim($path, '\\/') . $sep;
    }
}
