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

/**
 * CSV 读写工具类
 *
 * 支持大文件流式读取、自定义分隔符/包围符、编码转换、标题行映射，
 * 并在写入时默认防止 CSV 公式注入。
 *
 * @package zxf\Utils\Files
 */
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

    /**
     * 创建 CSV 工具实例（工厂方法）
     *
     * @return static 新的 CSV 实例
     */
    public static function make(): self
    {
        return new self();
    }

    /**
     * 设置字段分隔符（如逗号、分号、制表符）
     *
     * @param string $delimiter 分隔符字符
     * @return $this 支持链式调用
     */
    public function delimiter(string $delimiter): self
    {
        $this->delimiter = $delimiter;
        return $this;
    }

    /**
     * 设置字段包围符（包裹含特殊字符字段的字符，通常为双引号）
     *
     * @param string $enclosure 包围符字符
     * @return $this 支持链式调用
     */
    public function enclosure(string $enclosure): self
    {
        $this->enclosure = $enclosure;
        return $this;
    }

    /**
     * 设置转义符（用于转义分隔符、包围符或转义符本身）
     *
     * @param string $escape 转义符字符
     * @return $this 支持链式调用
     */
    public function escape(string $escape): self
    {
        $this->escape = $escape;
        return $this;
    }

    /**
     * 设置读取时的源文件编码（输入编码）
     *
     * @param string $encoding 源编码名称，如 UTF-8、GBK
     * @return $this 支持链式调用
     */
    public function fromEncoding(string $encoding): self
    {
        $this->inputEncoding = $encoding;
        return $this;
    }

    /**
     * 设置写入时的目标文件编码（输出编码）
     *
     * @param string $encoding 目标编码名称，如 UTF-8、GBK
     * @return $this 支持链式调用
     */
    public function toEncoding(string $encoding): self
    {
        $this->outputEncoding = $encoding;
        return $this;
    }

    /**
     * 设置 CSV 是否包含标题行
     *
     * @param bool $hasHeader true=首行作为表头（默认），false=纯数据行
     * @return $this 支持链式调用
     */
    public function hasHeader(bool $hasHeader = true): self
    {
        $this->hasHeader = $hasHeader;
        return $this;
    }

    /**
     * 设置是否防止 CSV 公式注入（默认开启）。
     *
     * 开启后写入的字符串单元格若以 = + - @ 或制表符、回车开头，会在前面加单引号，
     * 使表格软件按纯文本处理；纯数值（如 "-5"）不受影响。
     *
     * @param bool $enable true=开启防护（推荐），false=原样写入
     * @return $this 支持链式调用
     */
    public function preventFormulaInjection(bool $enable = true): self
    {
        $this->preventFormulaInjection = $enable;
        return $this;
    }

    /**
     * 流式读取 CSV 文件，按行 yield 返回（支持大文件，内存友好）
     *
     * 首行默认作为表头，后续行组合为关联数组；无表头时返回索引数组。
     * 自动剥离 UTF-8 BOM，列数不一致时以空值补齐或丢弃多余列。
     *
     * @param string $path CSV 文件路径
     * @return Generator<int, array<string, mixed>> 逐行数据
     * @throws RuntimeException 文件不存在或无法打开时抛出
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

    /**
     * 读取整个 CSV 文件并转为数组
     *
     * 内部调用 read() 将所有行一次性收集为数组返回，便于整体处理小文件。
     *
     * @param string $path CSV 文件路径
     * @return array<int, array<string, mixed>|array<int, mixed>> 全部数据行
     * @throws RuntimeException 文件不存在或无法打开时抛出
     */
    public function readAll(string $path): array
    {
        return iterator_to_array($this->read($path));
    }

    /**
     * 将数据写入 CSV 文件（覆盖已有内容）
     *
     * 目录不存在时递归创建（并发场景下会二次确认目录是否已存在）。
     * hasHeader 为 true 且数据非空时，取第一条记录的键名作为表头写入；
     * 数据行会先做编码转换再经公式注入防护后写出。文件以二进制模式打开，
     * 逐行写入并及时关闭句柄，适用于中大型数据集。
     *
     * @param string $path 目标 CSV 文件路径
     * @param array<int, array<string, mixed>> $data 数据行数组，每行以「列名 => 值」表示
     * @return void
     *
     * @throws RuntimeException 目录无法创建或文件无法打开时抛出
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
                // 表头同样是会被表格软件解析的单元格，需一并做公式注入防护
                $headers = $this->sanitizeRow($headers);
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
     * 以追加模式打开文件（不存在则创建），只写入数据行，**不会**补写表头；
     * 每行同样会做编码转换与公式注入防护。目录不存在时递归创建。
     *
     * @param string $path 目标 CSV 文件路径
     * @param array<int, array<string, mixed>> $data 待追加的数据行
     * @return void
     *
     * @throws RuntimeException 目录无法创建或文件无法打开时抛出
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
     *
     * 先用 readAll() 载入全部行（含表头映射），再交给 Json::encode() 编码；
     * 中文默认不被转义为 \uXXXX。
     *
     * @param string $path  CSV 文件路径
     * @param int    $flags 额外的 json_encode 选项位，如 JSON_PRETTY_PRINT
     * @return string 编码后的 JSON 字符串
     *
     * @throws RuntimeException 文件不存在或无法打开时抛出
     * @throws \JsonException 数据无法编码为 JSON 时抛出
     */
    public function toJson(string $path, int $flags = 0): string
    {
        return \zxf\Utils\Convert\Json::encode($this->readAll($path), $flags);
    }

    /**
     * 将 JSON 字符串写入 CSV 文件
     *
     * 先按关联数组解码 JSON，再复用 write() 落盘，因此表头、编码转换与
     * 公式注入防护等行为与 write() 完全一致；解码结果不是数组时直接拒绝。
     *
     * @param string $path 目标 CSV 文件路径
     * @param string $json JSON 字符串，根节点必须是数组
     * @return void
     *
     * @throws InvalidArgumentException JSON 解码结果不是数组时抛出
     * @throws \JsonException JSON 语法非法时抛出
     * @throws RuntimeException 目录无法创建或文件无法写入时抛出
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
     *
     * 逐单元格把字符串从 $from 转为 $to；当两者相同或未加载 mbstring 扩展时
     * 直接原样返回（不做任何降级转换），非字符串单元格（如 null、数字）保持不变。
     * 读取时按「inputEncoding → outputEncoding」调用，写入时按相反方向调用，
     * 两次转换互为逆操作，因此以 default 配置读写不会改变数据。
     *
     * @param array<int|string, mixed> $row  待转换的一行数据
     * @param string                   $from 源编码，如 'GBK'
     * @param string                   $to   目标编码，如 'UTF-8'
     * @return array<int|string, mixed> 转换后的一行数据，键名保持不变
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
