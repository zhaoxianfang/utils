<?php

/**
 * ============================================================================
 * CSV 读写工具类
 * ============================================================================
 *
 * 提供 CSV 文件的读取、写入、转换、流式处理等功能。
 * 支持大文件处理、自定义分隔符、编码转换和标题行映射。
 *
 * @package     zxf\Utils\Files
 * @author      zxf\Utils
 * @version     1.0.0
 * @license     MIT
 * @link        https://github.com/zhaoxianfang/utils
 * @since       1.0.0
 */

declare(strict_types=1);

namespace zxf\Utils\Files;

use InvalidArgumentException;
use RuntimeException;
use Generator;

final class CSV
{
    private string $delimiter = ',';

    private string $enclosure = '"';

    private string $escape = '\\';

    private string $inputEncoding = 'UTF-8';

    private string $outputEncoding = 'UTF-8';

    private bool $hasHeader = true;

    public static function make(): self
    {
        return new self();
    }

    public function delimiter(string $delimiter): self
    {
        $this->delimiter = $delimiter;
        return $this;
    }

    public function enclosure(string $enclosure): self
    {
        $this->enclosure = $enclosure;
        return $this;
    }

    public function escape(string $escape): self
    {
        $this->escape = $escape;
        return $this;
    }

    public function fromEncoding(string $encoding): self
    {
        $this->inputEncoding = $encoding;
        return $this;
    }

    public function toEncoding(string $encoding): self
    {
        $this->outputEncoding = $encoding;
        return $this;
    }

    public function hasHeader(bool $hasHeader = true): self
    {
        $this->hasHeader = $hasHeader;
        return $this;
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function read(string $path): Generator
    {
        if (! is_file($path)) {
            throw new RuntimeException("CSV 文件不存在: {$path}");
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("无法打开 CSV 文件: {$path}");
        }

        try {
            $headers = null;
            $rowNum  = 0;

            while (($row = fgetcsv($handle, 0, $this->delimiter, $this->enclosure, $this->escape)) !== false) {
                $rowNum++;
                if ($row === [null]) {
                    continue;
                }
                $row = $this->convertEncoding($row, $this->inputEncoding, $this->outputEncoding);
                if ($this->hasHeader && $headers === null) {
                    $headers = array_map(static fn ($h) => trim((string) $h), $row);
                    continue;
                }
                if ($headers !== null) {
                    yield $rowNum => array_combine($headers, $row) ?: array_combine(
                        array_slice($headers, 0, count($row)),
                        $row
                    );
                } else {
                    yield $rowNum => $row;
                }
            }
        } finally {
            fclose($handle);
        }
    }

    public function readAll(string $path): array
    {
        return iterator_to_array($this->read($path));
    }

    /**
     * @param array<int, array<string, mixed>> $data
     */
    public function write(string $path, array $data): void
    {
        $dir = dirname($path);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true)) {
            throw new RuntimeException("无法创建目录: {$dir}");
        }

        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException("无法创建 CSV 文件: {$path}");
        }

        try {
            if ($this->hasHeader && $data !== []) {
                $headers = array_keys($data[array_key_first($data)]);
                fputcsv($handle, $headers, $this->delimiter, $this->enclosure, $this->escape);
            }

            foreach ($data as $row) {
                $row = $this->convertEncoding($row, $this->outputEncoding, $this->inputEncoding);
                fputcsv($handle, $row, $this->delimiter, $this->enclosure, $this->escape);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * 将数组追加到 CSV 文件末尾
     *
     * @param array<int, array<string, mixed>> $data
     */
    public function append(string $path, array $data): void
    {
        $dir = dirname($path);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true)) {
            throw new RuntimeException("无法创建目录: {$dir}");
        }

        $handle = fopen($path, 'ab');
        if ($handle === false) {
            throw new RuntimeException("无法打开 CSV 文件: {$path}");
        }

        try {
            foreach ($data as $row) {
                $row = $this->convertEncoding($row, $this->outputEncoding, $this->inputEncoding);
                fputcsv($handle, $row, $this->delimiter, $this->enclosure, $this->escape);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * 读取 CSV 并转为 JSON 字符串
     */
    public function toJson(string $path, int $flags = 0): string
    {
        return \zxf\Utils\Convert\Json::encode($this->readAll($path), $flags);
    }

    /**
     * 将 JSON 字符串写入 CSV 文件
     */
    public function fromJson(string $path, string $json): void
    {
        $data = \zxf\Utils\Convert\Json::decode($json);
        if (! is_array($data)) {
            throw new InvalidArgumentException('JSON 数据必须是数组');
        }
        $this->write($path, $data);
    }

    /**
     * 编码转换
     */
    private function convertEncoding(array $row, string $from, string $to): array
    {
        if ($from === $to || ! extension_loaded('mbstring')) {
            return $row;
        }
        return array_map(
            static fn ($cell) => is_string($cell) ? mb_convert_encoding($cell, $to, $from) : $cell,
            $row
        );
    }
}
