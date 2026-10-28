<?php

declare(strict_types=1);

namespace zxf\Utils\Text;

/**
 * 文本差异比较工具
 *
 * 基于最长公共子序列（LCS）算法实现行级和字符级文本差异比较，
 * 支持生成统一差异格式（Unified Diff）和 HTML 高亮显示。
 *
 * @package zxf\Utils\Text
 * @version 1.0.0
 * @license MIT
 *
 * @example
 * use zxf\Utils\Text\Diff;
 * $diff = Diff::compare("line1\nline2", "line1\nline3"); // 行级差异
 * echo Diff::toUnified("a\nb", "a\nc");  // 生成 Unified Diff 文本
 * echo Diff::toHtml("a\nb", "a\nc");     // 生成 HTML 高亮
 */
class Diff
{
    /** @var string 删除标记 */
    public const REMOVED = 'removed';

    /** @var string 新增标记 */
    public const ADDED = 'added';

    /** @var string 未变更标记 */
    public const UNCHANGED = 'unchanged';

    /**
     * 比较两段文本（按行）
     *
     * @param string $oldText 旧文本
     * @param string $newText 新文本
     * @return array<int, array{type: string, line: string}> 差异结果数组
     */
    public static function compare(string $oldText, string $newText): array
    {
        $oldLines = $oldText === '' ? [] : explode("\n", $oldText);
        $newLines = $newText === '' ? [] : explode("\n", $newText);
        $lcs = self::lcs($oldLines, $newLines);

        $result = [];
        $i = $j = 0;
        foreach ($lcs as $line) {
            while ($i < count($oldLines) && $oldLines[$i] !== $line) {
                $result[] = ['type' => self::REMOVED, 'line' => $oldLines[$i]];
                $i++;
            }
            while ($j < count($newLines) && $newLines[$j] !== $line) {
                $result[] = ['type' => self::ADDED, 'line' => $newLines[$j]];
                $j++;
            }
            $result[] = ['type' => self::UNCHANGED, 'line' => $line];
            $i++;
            $j++;
        }
        while ($i < count($oldLines)) {
            $result[] = ['type' => self::REMOVED, 'line' => $oldLines[$i]];
            $i++;
        }
        while ($j < count($newLines)) {
            $result[] = ['type' => self::ADDED, 'line' => $newLines[$j]];
            $j++;
        }

        return $result;
    }

    /**
     * 生成统一差异格式（Unified Diff）
     *
     * @param string $oldText   旧文本
     * @param string $newText   新文本
     * @param string $oldFile   旧文件名（用于头部）
     * @param string $newFile   新文件名（用于头部）
     * @param int    $context   上下文行数（默认 3）
     * @return string 统一差异格式的字符串
     */
    public static function toUnified(string $oldText, string $newText, string $oldFile = 'a', string $newFile = 'b', int $context = 3): string
    {
        $diff = self::compare($oldText, $newText);
        if ($diff === []) {
            return '';
        }

        $output = "--- {$oldFile}\n+++ {$newFile}\n";
        $hunk = [];
        $oldLine = $newLine = 1;

        foreach ($diff as $i => $item) {
            if ($item['type'] === self::UNCHANGED) {
                if ($hunk !== []) {
                    $hunk[] = ' ' . $item['line'];
                    if (count($hunk) > $context * 2 + 1) {
                        $output .= self::formatHunk($hunk, $oldLine, $newLine, $context);
                        $hunk = [];
                    }
                }
                $oldLine++;
                $newLine++;
            } else {
                if ($hunk === []) {
                    // 回溯添加上下文
                    $start = max(0, $i - $context);
                    for ($k = $start; $k < $i; $k++) {
                        $hunk[] = ' ' . $diff[$k]['line'];
                    }
                }
                $hunk[] = ($item['type'] === self::REMOVED ? '-' : '+') . $item['line'];
                if ($item['type'] === self::REMOVED) {
                    $oldLine++;
                } else {
                    $newLine++;
                }
            }
        }

        if ($hunk !== []) {
            $output .= self::formatHunk($hunk, $oldLine, $newLine, $context);
        }

        return $output;
    }

    /**
     * 生成 HTML 差异高亮
     *
     * @param string $oldText 旧文本
     * @param string $newText 新文本
     * @return string HTML 字符串
     */
    public static function toHtml(string $oldText, string $newText): string
    {
        $diff = self::compare($oldText, $newText);
        $html = '<div class="diff">';
        foreach ($diff as $item) {
            $class = match ($item['type']) {
                self::REMOVED   => 'diff-removed',
                self::ADDED     => 'diff-added',
                default         => 'diff-unchanged',
            };
            $prefix = match ($item['type']) {
                self::REMOVED   => '-',
                self::ADDED     => '+',
                default         => ' ',
            };
            $escaped = htmlspecialchars($prefix . $item['line'], ENT_QUOTES, 'UTF-8');
            $html .= "<div class=\"{$class}\">{$escaped}</div>";
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * 计算最长公共子序列（LCS）
     *
     * @template T
     * @param array<T> $a 序列 A
     * @param array<T> $b 序列 B
     * @return array<T> 最长公共子序列
     */
    private static function lcs(array $a, array $b): array
    {
        $m = count($a);
        $n = count($b);
        $dp = array_fill(0, $m + 1, array_fill(0, $n + 1, 0));

        for ($i = 1; $i <= $m; $i++) {
            for ($j = 1; $j <= $n; $j++) {
                $dp[$i][$j] = $a[$i - 1] === $b[$j - 1]
                    ? $dp[$i - 1][$j - 1] + 1
                    : max($dp[$i - 1][$j], $dp[$i][$j - 1]);
            }
        }

        // 回溯
        $result = [];
        $i = $m;
        $j = $n;
        while ($i > 0 && $j > 0) {
            if ($a[$i - 1] === $b[$j - 1]) {
                array_unshift($result, $a[$i - 1]);
                $i--;
                $j--;
            } elseif ($dp[$i - 1][$j] > $dp[$i][$j - 1]) {
                $i--;
            } else {
                $j--;
            }
        }

        return $result;
    }

    /**
     * 格式化差异块头部
     *
     * 生成统一 diff 的块头（`@@ -旧起始,旧行数 +新起始,新行数 @@`）并拼接块内容：
     * 以空格/`-` 开头的行计入旧行数，以空格/`+` 开头的行计入新行数，
     * 起始行号按「当前行号 - 对应行数」回推且不小于 1。
     *
     * @param array $hunk 差异块的行列表，每行带 ` `、`-`、`+` 前缀
     * @param int $oldLine 差异块结束处的旧文件行号
     * @param int $newLine 差异块结束处的新文件行号
     * @param int $context 上下文行数（当前实现未参与计算，见实现说明）
     * @return string 格式化后的差异块文本（含结尾换行）
     */
    private static function formatHunk(array $hunk, int $oldLine, int $newLine, int $context): string
    {
        $oldCount = count(array_filter($hunk, static fn (string $l): bool => str_starts_with($l, ' ') || str_starts_with($l, '-')));
        $newCount = count(array_filter($hunk, static fn (string $l): bool => str_starts_with($l, ' ') || str_starts_with($l, '+')));
        $oldStart = max(1, $oldLine - $oldCount);
        $newStart = max(1, $newLine - $newCount);
        return "@@ -{$oldStart},{$oldCount} +{$newStart},{$newCount} @@\n" . implode("\n", $hunk) . "\n";
    }
}
