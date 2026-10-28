<?php
declare(strict_types=1);

namespace zxf\Utils\Sundry;

use Exception;
use InvalidArgumentException;

/**
 * 命令行参数解析工具类
 *
 * #!/usr/bin/env php
 * <?php
 *
 * //实例化
 * $cmd = zxf\Utils\Sundry\Command::instance();
 *
 * // 获取所有参数值
 * $all = $cmd->all();
 *
 * // 解析选项 port
 * $cmd->option('port', function ($val) {
 *     // $val port选项传入的值
 *     echo 'Option port handler=>'.$val;
 * });
 *
 * // 解析参数 test
 * $cmd->args('test', function ($bool){
 *     // $bool 是否解析到 test true|false
 *     if($bool){
 *         // 传入了 test
 *     }else{
 *         //未传入 test
 *     }
 * });
 *
 * // 获取所有Opts的值
 * $opts = $cmd->getOptVal();
 *
 * // 获取 port 的值 ，没有则返回null
 * $port = $cmd->getOptVal('port');
 *
 * // 获取所有Args的值
 * $args = $cmd->getArgVal();
 *
 * // 获取 是否传入 test 的 ，返回true|false
 * $test = $cmd->getArgVal('test');
 *
 * 调用 demo:  php 你的脚本文件 --port 3307 -c 100 -hlocal test -g
 * 传入参数说明：
 *    --opts参数名称 加 空格 加 opts参数值 例如：--port 3307 表示 port 的值为 3307       ; 返回到 opts 中
 *    -opts参数名称 加 空格 加 opts参数值 例如：-c 100 表示 c 的值为 100                 ; 返回到 opts 中
 *    -opts参数简称「单字母」 不加空格 接opts参数值 例如：-hlocal 表示 h 的值为 local      ; 返回到 opts 中
 *    -opts参数简称「单字母」 例如：-g 表示 传入了参数 g                                 ; 返回到 opts 中
 *    参数名称 例如：test 表示 传入了参数 test                                         ; 返回到 args 中
 *
 *
 * 扩展方法
 * $cmd->info('打印一个info');
 * $name = $cmd->input('请输入您的名字');
 */
class Command
{
    // store options
    private static array $optsArr = [];

    // store args
    private static array $argsArr = [];

    // 是否解析过
    private static bool $isParse = false;

    /**
     * @var static|null 单例实例
     */
    protected static ?object $instance = null;

    protected ?\zxf\Utils\Sundry\CliInput $cliInput = null;

    protected ?\zxf\Utils\Sundry\CliOutput $cliOutput = null;

    /**
     * 构造命令行参数解析器，初始化 CliInput/CliOutput 代理并解析一次参数
     *
     * @throws \RuntimeException 当前不是命令行环境时退出
     */
    public function __construct()
    {
        // 检查环境
        if (PHP_SAPI != 'cli') {
            exit('Please run under command line.');
        }
        // 注意：字符串形式的类名不会做命名空间解析，class_exists('CliInput') 查的是全局类，
        // 恒为 false 会让 $this->cliInput/$this->cliOutput 一直为 null，
        // 导致 __call() 代理的 input()/info() 等方法全部抛 “method not exists”。
        if (class_exists(CliInput::class)) {
            $this->cliInput = new CliInput;
        }
        if (class_exists(CliOutput::class)) {
            $this->cliOutput = new CliOutput;
        }

        if (! self::$isParse) {
            self::parseArgs();
        }
    }

    /**
     * 获取单例实例
     *
     * 首次调用时通过 `new static` 创建实例（构造函数会顺带解析一次命令行参数），
     * 之后调用直接返回同一实例，保证全局 opts/args 只解析一次。
     *
     * @return object|static 当前类的唯一实例
     */
    public static function instance()
    {
        if (! isset(self::$instance) || is_null(self::$instance)) {
            self::$instance = new static;
        }

        return self::$instance;
    }

    /**
     * 获取选项值
     *
     * 不传参数时返回全部已解析的选项数组；传入选项名时返回该选项的值，
     * 选项存在但未带值（如 `-g`）时值为 true，选项不存在时返回 null。
     *
     * @param  string|null  $opt  选项名（不含 `-` / `--` 前缀），为 null 时取全部选项
     * @return array|string|bool|null 全部选项数组 / 指定选项的值（无值选项为 true）/ 不存在时 null
     */
    public function getOptVal(?string $opt = null): array|string|bool|null
    {
        if (is_null($opt)) {
            return self::$optsArr;
        } else {
            if (isset(self::$optsArr[$opt])) {
                return self::$optsArr[$opt];
            }
        }

        return null;
    }

    /**
     * 获取命令行参数值
     *
     * 不传参数时返回全部位置参数（args）数组；传入参数名时只做「是否出现」判断：
     * 出现过返回 true，未出现返回 false（不会返回该参数对应的值）。
     *
     * @param  string|null  $arg  位置参数名称，为 null 时取全部位置参数
     * @return bool|array|string|null 传 null 时返回全部参数数组（无参数则为空数组）；传名称时返回 true/false
     */
    public function getArgVal(?string $arg = null): bool|array|string|null
    {
        if (is_null($arg)) {
            return self::$argsArr;
        } elseif (! empty(self::$argsArr) && in_array($arg, self::$argsArr)) {
            return true;
        }

        return false;
    }

    /**
     * 注册选项对应的回调处理函数, $callback 应该有一个参数, 用于接收选项值
     *
     * @param  string  $opt  解析的opts参数名称
     * @param  callable  $callback  回调函数
     * @return void 解析到值返回解析值，否则返回 null
     *
     * @throws InvalidArgumentException
     */
    public function option($opt, callable $callback)
    {
        // check
        if (! is_callable($callback)) {
            throw new InvalidArgumentException(sprintf('Not a valid callback <%s>.', $callback));
        }
        if (isset(self::$optsArr[$opt])) {
            call_user_func($callback, self::$optsArr[$opt]);
        } else {
            call_user_func($callback, null);
        }
    }

    /**
     * 注册参数对应的回调处理函数, $callback 应该有一个参数, 用于接收参数值
     *
     * @param  string  $arg  解析的arg参数名称
     * @param  callable  $callback  回调函数
     * @return void 解析到参数返回true，否则返回 false
     *
     * @throws InvalidArgumentException
     */
    public function args($arg, callable $callback)
    {
        // check
        if (! is_callable($callback)) {
            throw new InvalidArgumentException(sprintf('Not a valid callback <%s>.', $callback));
        }
        if (! empty(self::$argsArr) && in_array($arg, self::$argsArr)) {
            call_user_func($callback, true);
        } else {
            call_user_func($callback, false);
        }
    }

    /**
     * 获取所有 opts 和 args 的值
     *
     * @return array{opts: array<string, mixed>, args: array<int, string>} 键 opts 为选项映射，键 args 为位置参数列表
     */
    public function all(): array
    {
        return ['opts' => self::$optsArr, 'args' => self::$argsArr];
    }

    /**
     * 是否是 -s 形式的短选项
     *
     * 形如 `-c`：单个字母或数字，返回选项名（去掉 `-` 前缀）；不匹配返回 false。
     *
     * @param  string  $opt  待判断的原始命令行片段
     * @return string|bool 匹配时返回短选项名，否则返回 false
     */
    private static function isShortOptions(string $opt): bool|string
    {
        if (preg_match('/^\-([a-zA-Z0-9])$/', $opt, $matchs)) {
            return $matchs[1];
        }

        return false;
    }

    /**
     * 是否是 -svalue 形式的短选项
     *
     * 形如 `-hlocal`：单字母选项名后紧跟非空白字符组成的值，返回 `[选项名, 选项值]`；不匹配返回 false。
     *
     * @param  string  $opt  待判断的原始命令行片段
     * @return array|bool 匹配时返回 [选项名, 选项值]，否则返回 false
     */
    private static function isShortOptionsWithValue(string $opt): bool|array
    {
        if (preg_match('/^\-([a-zA-Z0-9])(\S+)$/', $opt, $matchs)) {
            return [$matchs[1], $matchs[2]];
        }

        return false;
    }

    /**
     * 是否是 --longopts 形式的长选项
     *
     * 形如 `--port`：两个及以上字母/数字/`-`/`_`，返回选项名（去掉 `--` 前缀）；不匹配返回 false。
     *
     * @param  string  $opt  待判断的原始命令行片段
     * @return string|bool 匹配时返回长选项名，否则返回 false
     */
    private static function isLongOptions(string $opt): bool|string
    {
        if (preg_match('/^\-\-([a-zA-Z0-9\-_]{2,})$/', $opt, $matchs)) {
            return $matchs[1];
        }

        return false;
    }

    /**
     * 是否是 --longopts=value 形式的长选项
     *
     * @param  string  $opt
     * @return array|bool 返回长选项名及选项值
     */
    private static function isLongOptionsWithValue($opt): bool|array
    {
        if (preg_match('/^\-\-([a-zA-Z0-9\-_]{2,})(?:\=(.*?))$/', $opt, $matchs)) {
            return [$matchs[1], $matchs[2]];
        }

        return false;
    }

    /**
     * 是否是命令行位置参数
     *
     * 判断依据：不以 `-` 开头即视为位置参数（因此负数、以 `-` 开头的值不会被识别为参数）。
     *
     * @param  string  $value  待判断的原始命令行片段
     * @return bool 不以 `-` 开头返回 true，否则返回 false
     */
    private static function isArg(string $value): bool
    {
        return ! preg_match('/^\-/', $value);
    }

    /**
     * 解析命令行参数
     *
     * 从全局 `$argv` 第 2 项开始逐项扫描，按顺序识别四种形式：
     * `--name value`、`-n value`（有后继非 `-` 开头项时取值，否则记为 true）、
     * `-nvalue`、`--name=value`；其余不以 `-` 开头的片段作为位置参数追加。
     * 解析结果写入静态 $optsArr/$argsArr，并用 $isParse 保证只解析一次。
     *
     * @return void 无返回值，解析结果保存在静态属性中
     */
    private static function parseArgs(): void
    {
        global $argv;
        if (! self::$isParse) {
            $index = 1;
            $length = count($argv);
            while ($index < $length) {
                // current value
                $curVal = $argv[$index];
                if (($key = self::isShortOptions($curVal)) || ($key = self::isLongOptions($curVal))) {
                    $index++;
                    if (isset($argv[$index]) && self::isArg($argv[$index])) {
                        self::$optsArr[$key] = $argv[$index];
                    } else {
                        self::$optsArr[$key] = true;
                        $index--;
                    }
                } else {
                    if (
                        ($key = self::isShortOptionsWithValue($curVal))
                        || ($key = self::isLongOptionsWithValue($curVal))
                    ) {
                        self::$optsArr[$key[0]] = $key[1];
                    } else {
                        if (self::isArg($curVal)) {
                            self::$argsArr[] = $curVal;
                        }
                    }
                }
                $index++;
            }
            self::$isParse = true;
        }
    }

    /**
     * 调用本类中不存在的方法，尝试去调用 cliInput 或者 cliOutput 的方法
     *
     *
     * @return mixed
     *
     * @throws Exception
     */
    public function __call($methodName, $arguments)
    {
        if (! empty($this->cliInput) && method_exists($this->cliInput, $methodName)) {
            return call_user_func_array([$this->cliInput, $methodName], $arguments);
        }
        if (! empty($this->cliOutput) && method_exists($this->cliOutput, $methodName)) {
            return call_user_func_array([$this->cliOutput, $methodName], $arguments);
        }
        throw new Exception("method $methodName not exists");
    }
}
