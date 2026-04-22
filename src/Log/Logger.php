<?php

declare(strict_types=1);

namespace zxf\Utils\Log;

use InvalidArgumentException;
use RuntimeException;

/**
 * 简易日志类
 * 支持按日期和日志级别分文件存储、8级日志级别控制、上下文 JSON 附加、控制台同步输出和自动日志清理
 *
 * 日志级别（从低到高，RFC 5424 标准）：
 * DEBUG < INFO < NOTICE < WARNING < ERROR < CRITICAL < ALERT < EMERGENCY
 *
 * @package Log
 * @version 1.0.0
 * @license MIT
 */
class Logger
{
    /** 调试级别，用于开发调试信息 */
    public const DEBUG     = 100;
    /** 信息级别，用于常规业务信息 */
    public const INFO      = 200;
    /** 通知级别，用于正常但重要的事件 */
    public const NOTICE    = 250;
    /** 警告级别，用于非错误异常但需要注意的情况 */
    public const WARNING   = 300;
    /** 错误级别，用于运行时错误 */
    public const ERROR     = 400;
    /** 严重级别，用于关键组件故障 */
    public const CRITICAL  = 500;
    /** 警报级别，需要立即处理的情况 */
    public const ALERT     = 550;
    /** 紧急级别，系统不可用 */
    public const EMERGENCY = 600;

    /** @var array<int,string> 日志级别编号到名称的映射 */
    private const LEVEL_NAMES = [
        self::DEBUG     => 'DEBUG',
        self::INFO      => 'INFO',
        self::NOTICE    => 'NOTICE',
        self::WARNING   => 'WARNING',
        self::ERROR     => 'ERROR',
        self::CRITICAL  => 'CRITICAL',
        self::ALERT     => 'ALERT',
        self::EMERGENCY => 'EMERGENCY',
    ];

    /** @var string 日志文件存储目录 */
    private string $logDir;

    /** @var int 当前最低记录级别（低于此级别的日志将被忽略） */
    private int $minLevel;

    /** @var string 日志文件名中的日期格式 */
    private string $dateFormat = 'Y-m-d';

    /** @var string 日志内容中的时间戳格式 */
    private string $timeFormat = 'Y-m-d H:i:s.v';

    /** @var bool 是否同时输出到控制台（PHP CLI 模式下有效） */
    private bool $echoOutput = false;

    /** @var resource|null 当前打开的文件句柄（用于批量写入优化，减少频繁开关文件） */
    private $fileHandle = null;

    /** @var string 当前打开的文件路径 */
    private string $currentFile = '';

    /**
     * 构造函数
     *
     * @param string $logDir   日志文件存储目录
     * @param int    $minLevel 最低记录级别，默认 DEBUG（记录所有级别）
     * @throws RuntimeException 当日志目录无法创建时抛出
     */
    public function __construct(string $logDir = __DIR__ . '/../../runtime/logs', int $minLevel = self::DEBUG)
    {
        $this->logDir = rtrim($logDir, '/\\');
        $this->minLevel = $minLevel;

        if (!is_dir($this->logDir)) {
            if (!mkdir($this->logDir, 0755, true)) {
                throw new RuntimeException('无法创建日志目录: ' . $this->logDir);
            }
        }
    }

    /**
     * 记录 DEBUG 级别日志（开发调试信息）
     *
     * @param string $message 日志消息
     * @param array  $context 上下文数据（将被 JSON 编码附加到日志行）
     * @return void
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log(self::DEBUG, $message, $context);
    }

    /**
     * 记录 INFO 级别日志（常规业务信息）
     *
     * @param string $message 日志消息
     * @param array  $context 上下文数据
     * @return void
     */
    public function info(string $message, array $context = []): void
    {
        $this->log(self::INFO, $message, $context);
    }

    /**
     * 记录 NOTICE 级别日志（正常但重要的事件）
     *
     * @param string $message 日志消息
     * @param array  $context 上下文数据
     * @return void
     */
    public function notice(string $message, array $context = []): void
    {
        $this->log(self::NOTICE, $message, $context);
    }

    /**
     * 记录 WARNING 级别日志（警告信息）
     *
     * @param string $message 日志消息
     * @param array  $context 上下文数据
     * @return void
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log(self::WARNING, $message, $context);
    }

    /**
     * 记录 ERROR 级别日志（错误信息）
     *
     * @param string $message 日志消息
     * @param array  $context 上下文数据
     * @return void
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(self::ERROR, $message, $context);
    }

    /**
     * 记录 CRITICAL 级别日志（严重故障）
     *
     * @param string $message 日志消息
     * @param array  $context 上下文数据
     * @return void
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log(self::CRITICAL, $message, $context);
    }

    /**
     * 记录 ALERT 级别日志（需要立即处理的警报）
     *
     * @param string $message 日志消息
     * @param array  $context 上下文数据
     * @return void
     */
    public function alert(string $message, array $context = []): void
    {
        $this->log(self::ALERT, $message, $context);
    }

    /**
     * 记录 EMERGENCY 级别日志（系统不可用）
     *
     * @param string $message 日志消息
     * @param array  $context 上下文数据
     * @return void
     */
    public function emergency(string $message, array $context = []): void
    {
        $this->log(self::EMERGENCY, $message, $context);
    }

    /**
     * 通用日志记录方法
     *
     * 日志格式：[时间] [级别] 消息 {"context_key":"context_value"}
     * 按级别和日期分文件存储，如 2024-01-15_ERROR.log
     *
     * @param int    $level   日志级别（使用类常量 DEBUG ~ EMERGENCY）
     * @param string $message 日志消息
     * @param array  $context 上下文数据数组
     * @return void
     */
    public function log(int $level, string $message, array $context = []): void
    {
        if ($level < $this->minLevel) {
            return;
        }

        $levelName = self::LEVEL_NAMES[$level] ?? 'UNKNOWN';
        $time = date($this->timeFormat);
        $contextStr = empty($context) ? '' : ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $line = "[{$time}] [{$levelName}] {$message}{$contextStr}" . PHP_EOL;

        // 按级别分文件存储
        $file = $this->getLogFile($levelName);
        $this->writeToFile($file, $line);

        if ($this->echoOutput) {
            echo $line;
        }
    }

    /**
     * 设置最低记录级别（低于此级别的日志将被忽略）
     *
     * @param int $level 日志级别常量
     * @return self 支持链式调用
     */
    public function setMinLevel(int $level): self
    {
        $this->minLevel = $level;
        return $this;
    }

    /**
     * 设置是否同时输出日志到控制台
     *
     * @param bool $echo true 开启控制台输出，false 关闭
     * @return self 支持链式调用
     */
    public function setEchoOutput(bool $echo): self
    {
        $this->echoOutput = $echo;
        return $this;
    }

    /**
     * 设置日志文件名中的日期格式（用于日志轮转）
     *
     * @param string $format PHP date() 格式字符串，默认 'Y-m-d'
     * @return self 支持链式调用
     */
    public function setDateFormat(string $format): self
    {
        $this->dateFormat = $format;
        return $this;
    }

    /**
     * 设置日志内容中的时间戳格式
     *
     * @param string $format PHP date() 格式字符串，默认 'Y-m-d H:i:s.v'
     * @return self 支持链式调用
     */
    public function setTimeFormat(string $format): self
    {
        $this->timeFormat = $format;
        return $this;
    }

    /**
     * 清理指定天数之前的过期日志文件
     *
     * @param int $days 保留天数，默认 30 天
     * @return int 实际删除的文件数量
     */
    public function clean(int $days = 30): int
    {
        $deleted = 0;
        $now = time();
        foreach (glob($this->logDir . '/*.log') as $file) {
            if (is_file($file) && ($now - filemtime($file)) > $days * 86400) {
                unlink($file);
                $deleted++;
            }
        }
        return $deleted;
    }

    /**
     * 析构函数：关闭打开的文件句柄，防止资源泄漏
     *
     * @return void
     */
    public function __destruct()
    {
        if ($this->fileHandle !== null) {
            fclose($this->fileHandle);
        }
    }

    /**
     * 根据级别名称和日期生成日志文件路径
     *
     * @param string $levelName 日志级别名称
     * @return string 日志文件完整路径
     */
    private function getLogFile(string $levelName): string
    {
        $date = date($this->dateFormat);
        return $this->logDir . DIRECTORY_SEPARATOR . "{$date}_{$levelName}.log";
    }

    /**
     * 将内容写入日志文件（自动管理文件句柄复用）
     *
     * @param string $file    目标文件路径
     * @param string $content 要写入的日志内容
     * @return void
     */
    private function writeToFile(string $file, string $content): void
    {
        // 如果文件变了，关闭旧句柄
        if ($this->currentFile !== $file && $this->fileHandle !== null) {
            fclose($this->fileHandle);
            $this->fileHandle = null;
        }

        if ($this->fileHandle === null) {
            $dir = dirname($file);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $this->fileHandle = fopen($file, 'ab');
            $this->currentFile = $file;
        }

        if ($this->fileHandle !== false) {
            fwrite($this->fileHandle, $content);
        }
    }
}
