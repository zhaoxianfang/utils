<?php

declare(strict_types=1);

namespace zxf\Utils\Sundry;

use InvalidArgumentException;

/**
 * 命令行打印输出内容
 *
 * 用法：
 * $output = new CliOutput();
 *
 * $output->info("info");
 * $output->error("error");
 * $output->warning("warning");
 * $output->blink("闪烁文字"); // 闪烁文字
 *
 * 自定义颜色样式
 * $output->printLine("红色文字", CliOutput::ANSI_RED); // 红色文字
 * $output->printLine("绿色加粗文字", CliOutput::ANSI_GREEN, CliOutput::ANSI_BOLD); // 绿色加粗文字
 * $output->printLine("蓝色闪烁文字", CliOutput::ANSI_BLUE, CliOutput::ANSI_BLINK); // 蓝色闪烁文字
 * $output->printLine("黄色反色文字", CliOutput::ANSI_YELLOW, CliOutput::ANSI_REVERSE); // 黄色反色文字
 * $output->printLine("青色下划线文字", CliOutput::ANSI_CYAN, CliOutput::ANSI_UNDERLINE); // 青色下划线文字
 * $output->printLine("带背景色的文字", CliOutput::ANSI_BG_RED, CliOutput::ANSI_BOLD); // 带背景色的文字
 *
 * 打印进度条
 * $total = 100;
 * for ($i = 0; $i <= $total; $i++) {
 *   usleep(100000); // 模拟耗时操作
 *   $output->printProgress($i, $total);
 * }
 *
 * 打印表格
 * $data = [
 *   ['姓名', '年龄', '性别'],
 *   ['张三11', 25, '男'],
 *   ['李四', 30, '女'],
 *   ['王五', 28, '男'],
 * ];
 * $output->printTable($data);
 */
class CliOutput
{
    const ANSI_RESET = "\033[0m";

    const ANSI_BLACK = "\033[30m";

    const ANSI_RED = "\033[31m";

    const ANSI_GREEN = "\033[32m";

    const ANSI_YELLOW = "\033[33m";

    const ANSI_BLUE = "\033[34m";

    const ANSI_MAGENTA = "\033[35m";

    const ANSI_CYAN = "\033[36m";

    const ANSI_WHITE = "\033[37m";

    const ANSI_BG_BLACK = "\033[40m";

    const ANSI_BG_RED = "\033[41m";

    const ANSI_BG_GREEN = "\033[42m";

    const ANSI_BG_YELLOW = "\033[43m";

    const ANSI_BG_BLUE = "\033[44m";

    const ANSI_BG_MAGENTA = "\033[45m";

    const ANSI_BG_CYAN = "\033[46m";

    const ANSI_BG_WHITE = "\033[47m";

    const ANSI_BOLD = "\033[1m";

    const ANSI_DIM = "\033[2m";

    const ANSI_ITALIC = "\033[3m";

    const ANSI_UNDERLINE = "\033[4m";

    const ANSI_BLINK = "\033[5m";

    const ANSI_REVERSE = "\033[7m";

    const ANSI_HIDDEN = "\033[8m";

    /**
     * 构造命令行输出器，非 CLI 环境下直接抛出异常
     *
     * @throws \RuntimeException 当前不是命令行环境时抛出
     */
    public function __construct()
    {
        // 检查环境
        if (PHP_SAPI != 'cli') {
            // 作为库被引用时不能用 exit()：那会直接终止宿主进程且调用方无法捕获，
            // 这里改为抛异常，由调用方自行决定如何处理。
            throw new \RuntimeException('Please run under command line.');
        }
    }

    /**
     * 打印带有颜色和样式的文本。
     *
     * @param  string  $text  要打印的文本。
     * @param  string  $color  颜色代码。
     * @param  string  $style  样式代码，默认为重置样式。
     * @param  bool  $needWrap  是否需要换行
     */
    public function printColoredText(string $text, string $color, string $style = self::ANSI_RESET, bool $needWrap = false): void
    {
        echo $style.$color.$text.self::ANSI_RESET;
        if ($needWrap) {
            echo "\n";
        }
    }

    /**
     * 打印一行带有颜色和样式的文本，并自动换行。
     *
     * @param  string  $text  要打印的文本。
     * @param  string  $color  颜色代码。
     * @param  string  $style  样式代码，默认为重置样式。
     */
    public function printLine(string $text, string $color, string $style = self::ANSI_RESET): void
    {
        $this->printColoredText($text, $color, $style, true);
    }

    /**
     * 打印一个进度条，显示完成的百分比。
     *
     * @param  int  $current  当前进度。
     * @param  int  $total  总数。
     * @param  int  $barLength  进度条长度，默认为 20 个占位符号。
     * @param  string  $color  颜色代码，默认为绿色。
     * @param  string  $style  样式代码，默认为重置样式。
     *
     * @throws InvalidArgumentException 当 $total 或 $barLength 不是正数时抛出
     */
    public function printProgress(int $current, int $total, int $barLength = 20, string $color = self::ANSI_GREEN, string $style = self::ANSI_RESET): void
    {
        // $total 为 0 会触发除零错误（PHP 8 为 DivisionByZeroError），必须显式拦截
        if ($total <= 0) {
            throw new InvalidArgumentException("总数必须大于 0，当前值: {$total}");
        }
        if ($barLength <= 0) {
            throw new InvalidArgumentException("进度条长度必须大于 0，当前值: {$barLength}");
        }

        $current = max(0, min($current, $total));

        $progress = (int) round(($current / $total) * 100);
        // str_repeat() 的第二个参数必须是 int：floor()/ceil() 返回 float，
        // 在 strict_types 下直接传入会 TypeError
        $filledLength = (int) floor($barLength * $current / $total);
        $filledLength = max(0, min($filledLength, $barLength));
        $bar = str_repeat('▓', $filledLength).str_repeat('░', $barLength - $filledLength);

        $this->printColoredText("[$bar] $progress%", $color, $style);
        echo " ($current/$total)\r";
        if ($current === $total) {
            echo "\n"; // 当进度达到 100% 时换行
        }
    }

    /**
     * 输出提示信息
     *
     * 以「指定颜色 + 加粗」样式打印一行文本并换行，默认绿色，用于常规成功/提示类输出。
     *
     * @param  string  $test  要输出的提示文本
     * @param  string  $color  文本颜色对应的 ANSI 颜色代码，默认绿色（ANSI_GREEN）
     * @return void 无返回值，直接写入标准输出
     */
    public function info(string $test, $color = self::ANSI_GREEN)
    {
        $this->printLine($test, $color, self::ANSI_BOLD); // $color色加粗文字,默认绿色
    }

    /**
     * 输出错误信息
     *
     * 以红色加粗样式打印一行文本并换行，用于错误提示；仅影响终端显示，不中断程序执行。
     *
     * @param  string  $test  要输出的错误文本
     * @return void 无返回值，直接写入标准输出
     */
    public function error(string $test)
    {
        $this->printLine($test, self::ANSI_RED, self::ANSI_BOLD); // 红色加粗文字
    }

    /**
     * 输出警告信息
     *
     * 以黄色加粗样式打印一行文本并换行，用于风险/异常提醒；仅影响终端显示，不中断程序执行。
     *
     * @param  string  $test  要输出的警告文本
     * @return void 无返回值，直接写入标准输出
     */
    public function warning(string $test)
    {
        $this->printLine($test, self::ANSI_YELLOW, self::ANSI_BOLD); // 黄色加粗文字
    }

    /**
     * 输出闪烁文本
     *
     * 以 ANSI 闪烁样式打印一行文本并换行；终端不支持闪烁控制符时退化为普通彩色文本。
     *
     * @param  string  $test  要输出的文本
     * @param  string  $color  文本颜色对应的 ANSI 颜色代码，默认黄色（ANSI_YELLOW）
     * @return void 无返回值，直接写入标准输出
     */
    public function blink(string $test, string $color = self::ANSI_YELLOW): void
    {
        $this->printLine($test, $color, self::ANSI_BLINK); // $color色闪烁文字
    }

    /**
     * 打印一个简单的表格。
     *
     * @param  array  $data  二维数组，表示表格的数据。
     */
    public function printTable(array $data): void
    {
        $topBorder = '+';
        $separator = '+';
        $horizontal = '-';
        $vertical = '|';

        // 计算列宽
        $columnWidths = [];
        foreach ($data as $row) {
            foreach ($row as $colIndex => $cell) {
                if (! isset($columnWidths[$colIndex])) {
                    $columnWidths[$colIndex] = 0;
                }
                // 单元格可能是 int/float 等标量，必须先转字符串，
                // 否则 mb_strlen()/preg_replace() 在 strict_types 下直接 TypeError
                $cellWidth = self::displayWidth($cell);
                $columnWidths[$colIndex] = max($columnWidths[$colIndex], $cellWidth);
            }
        }

        // 每行实际宽度 = 左侧竖线(1) + 每列(内容宽度 + 左右空格 + 竖线 = width + 3)
        // 旧公式 (N+1)*3 会让边框比内容行多出 2 个字符，导致上下边框不对齐。
        $totalWidth = array_sum($columnWidths) + count($columnWidths) * 3 + 1;

        echo $topBorder.str_repeat($horizontal, max(0, $totalWidth - 2)).$topBorder.PHP_EOL;

        foreach ($data as $row) {
            $output = $vertical;
            foreach ($row as $colIndex => $cell) {
                $cellWidth = self::displayWidth($cell);
                $padding = max(0, $columnWidths[$colIndex] - $cellWidth);
                // str_repeat() 需要 int：floor()/ceil() 返回 float，必须显式转换
                $leftPadding = (int) floor($padding / 2);
                $rightPadding = (int) ceil($padding / 2);

                $paddedCell = str_repeat(' ', $leftPadding).(string) $cell.str_repeat(' ', $rightPadding);
                $output .= ' '.$paddedCell.' '.$vertical;
            }
            echo $output.PHP_EOL;
            echo $separator.str_repeat($horizontal, max(0, $totalWidth - 2)).$separator.PHP_EOL;
        }
    }

    /**
     * 计算单元格的显示宽度（中文等全角字符按 2 个英文字符宽度计）
     *
     * 宽度 = UTF-8 字符数 + 中文汉字个数，非字符串标量会先转为字符串再计算。
     *
     * @param  mixed  $cell  单元格内容
     * @return int 终端显示列宽
     */
    private static function displayWidth(mixed $cell): int
    {
        $text = (string) $cell;
        // 非中文字符按 1 个显示宽度计，中文字符按 2 个计：
        // 即「字符数 + 中文字符个数」（旧实现只统计了非中文部分的长度，
        // 导致纯中文单元格的宽度被少算一半，列对不齐）。
        return mb_strlen($text, 'UTF-8') + (int) preg_match_all('/[\x{4e00}-\x{9fff}]/u', $text);
    }
}
