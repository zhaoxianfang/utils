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

    /**
     * 是否在写入时防止 CSV 公式注入（默认开启）。
     * 当单元格内容以 = + - @ 开头时，Excel 等会将其当作公式执行，
     * 通过前置单引号将其转为纯文本，避免恶意数据在表格软件中执行。
     */
    private bool $preventFormulaInjection = true;

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
     * 设置是否防止 CSV 公式注入（默认开启）。
     *
     * @param bool $enable true=开启防护（推荐），false=原样写入
     */
    public function preventFormulaInjection(bool $enable = true): self
    {
        $this->preventFormulaInjection = $enable;
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
                    // 首行作为表头：同时剥离 UTF-8 BOM，否则第一列的键会带上 \u{FEFF}
                    $headers = array_map(static fn ($h) => trim((string) $h), $row);
                    if ($headers !== []) {
                        $headers[array_key_first($headers)] = ltrim(
                            (string) $headers[array_key_first($headers)],
                            "\u{FEFF}"
                        );
                    }
                    continue;
                }
                if ($headers !== null) {
                    // 绝不能 yield false：列数与表头不一致时 array_combine() 返回 false，
                    // 旧实现两次调用都失败后会 yield false，调用方 foreach 直接 TypeError。
                    yield $rowNum => self::combineRow($headers, $row);
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
        // 末尾再判一次 is_dir()：并发下目录可能已被其他进程创建，
        // 此时 mkdir() 返回 false 并不代表失败。
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
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
                fputcsv($handle, $this->sanitizeRow($row), $this->delimiter, $this->enclosure, $this->escape);
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
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException("无法创建目录: {$dir}");
        }

        $handle = fopen($path, 'ab');
        if ($handle === false) {
            throw new RuntimeException("无法打开 CSV 文件: {$path}");
        }

        try {
            foreach ($data as $row) {
                $row = $this->convertEncoding($row, $this->outputEncoding, $this->inputEncoding);
                fputcsv($handle, $this->sanitizeRow($row), $this->delimiter, $this->enclosure, $this->escape);
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
     * 将一行数据按表头组合成关联数组（列数不一致时用空值补齐 / 丢弃多余列）
     *
     * @param array<int, string>       $headers 表头
     * @param array<int, mixed>        $row     数据行
     * @return array<string, mixed>
     */
    private static function combineRow(array $headers, array $row): array
    {
        $count = count($headers);
        // 列数不足：用空字符串补齐，保证键完整
        $padded = array_slice(array_values($row), 0, $count);
        while (count($padded) < $count) {
            $padded[] = '';
        }

        return array_combine($headers, $padded) ?: [];
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

    /**
     * 防止 CSV 公式注入。
     *
     * 当单元格内容为字符串且以 = + - @ 等字符开头时，Excel / WPS 等表格软件
     * 会将其当作公式执行（如 `=cmd|...` 造成命令执行或信息泄露）。
     * 通过前置单引号，表格软件会将其按纯文本处理，从而消除风险。
     *
     * @param array<int|string, mixed> $row
     * @return array<int|string, mixed>
     */
    private function sanitizeRow(array $row): array
    {
        if (! $this->preventFormulaInjection) {
            return $row;
        }
        foreach ($row as &$cell) {
            // 必须排除合法数值（如 "-5"、"1.5"），否则负数会被写成 "'-5" 造成数据污染；
            // 危险首字符集合按 OWASP 建议补齐制表符与回车。
            if (is_string($cell) && $cell !== '' && ! is_numeric($cell) && str_contains("=+-@\t\r", $cell[0])) {
                $cell = "'" . $cell;
            }
        }
        return $row;
    }
}
