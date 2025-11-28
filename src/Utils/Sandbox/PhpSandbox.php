<?php

declare(strict_types=1);

namespace zxf\Utils\Sandbox;

/**
 * 企业级PHP沙箱运行环境
 *
 * 一个安全、高性能、可靠的PHP代码沙箱执行环境，支持复杂代码逻辑执行，
 * 同时提供多层次安全防护和完整的资源管理。
 *
 * @see https://weisifang.com/docs/doc/6_210 PHP Sandbox 文档
 * @package PhpSandbox
 * @version 1.0.0
 * @license MIT
 */
final class PhpSandbox
{
    private array $allowedFunctions = [];
    private array $disabledFunctions = [];
    private array $disabledClasses = [];
    private array $disabledKeywords = [];
    private string $tempDir;
    private int $maxExecutionTime;
    private int $memoryLimit;
    private int $maxCodeLength;
    private array $originalSettings = [];
    private bool $isExecuting = false;
    private ?string $currentTempFile = null;
    private array $executionHistory = [];
    private int $maxHistorySize = 100;
    private int $executionCount = 0;
    private string $uniquePrefix;
    private float $startTime;
    private int $startMemory;
    private static int $instanceCounter = 0;

    // 默认配置常量
    private const DEFAULT_MEMORY_LIMIT = 256;
    private const DEFAULT_EXECUTION_TIME = 10;
    private const DEFAULT_MAX_CODE_LENGTH = 50000;

    // 安全配置常量
    private const DISABLED_FUNCTIONS = [
        'fopen', 'fwrite', 'file_put_contents', 'file_get_contents', 'file',
        'unlink', 'rmdir', 'mkdir', 'rename', 'copy', 'move_uploaded_file',
        'chmod', 'chown', 'chgrp', 'link', 'symlink', 'realpath', 'readlink',
        'readfile', 'tmpfile', 'tempnam', 'glob', 'scandir', 'disk_free_space',
        'disk_total_space', 'fileatime', 'filectime', 'filegroup', 'fileinode',
        'filemtime', 'fileowner', 'fileperms', 'filesize', 'filetype',
        'is_dir', 'is_executable', 'is_file', 'is_link', 'is_readable', 'is_writable',
        'is_writeable', 'lchgrp', 'lchown', 'parse_ini_file', 'parse_ini_string',
        'pathinfo', 'basename', 'dirname', 'stat', 'lstat', 'clearstatcache',

        'exec', 'system', 'passthru', 'shell_exec', 'proc_open', 'proc_close',
        'proc_get_status', 'proc_nice', 'proc_terminate', 'popen', 'pclose',
        'pcntl_exec', 'pcntl_fork', 'pcntl_waitpid', 'pcntl_wexitstatus',
        'pcntl_wifexited', 'pcntl_wifsignaled', 'pcntl_wifstopped',
        'pcntl_wstopsig', 'pcntl_wtermsig', 'pcntl_alarm', 'pcntl_signal',
        'pcntl_signal_dispatch', 'pcntl_get_last_error', 'pcntl_strerror',
        'pcntl_sigprocmask', 'pcntl_sigwaitinfo', 'pcntl_sigtimedwait',
        'pcntl_async_signals',

        'fsockopen', 'pfsockopen', 'stream_socket_client', 'stream_socket_server',
        'stream_socket_accept', 'stream_socket_get_name', 'stream_socket_pair',
        'curl_init', 'curl_exec', 'curl_multi_exec', 'socket_create', 'socket_listen',
        'socket_accept', 'socket_bind', 'socket_connect', 'socket_read', 'socket_write',
        'socket_send', 'socket_recv', 'http_build_query', 'get_headers',
        'stream_context_create', 'stream_set_timeout', 'stream_set_blocking',

        'eval', 'assert', 'create_function', 'preg_replace', 'mb_ereg_replace',
        'mb_eregi_replace', 'include', 'include_once', 'require', 'require_once',
        'forward_static_call', 'forward_static_call_array', 'call_user_func',
        'call_user_func_array', 'register_shutdown_function', 'register_tick_function',

        'dl', 'ini_set', 'ini_alter', 'ini_restore', 'putenv', 'getenv',
        'apache_setenv', 'set_include_path', 'restore_include_path',
        'set_time_limit', 'ignore_user_abort', 'header', 'header_remove',
        'http_response_code', 'setcookie', 'setrawcookie', 'session_start',
        'session_destroy', 'session_id', 'session_name', 'session_set_cookie_params',
        'session_set_save_handler', 'session_write_close', 'session_regenerate_id',

        'phpinfo', 'phpversion', 'php_sapi_name', 'php_uname', 'get_loaded_extensions',
        'get_defined_constants', 'get_defined_functions', 'get_defined_vars',
        'get_cfg_var', 'get_current_user', 'getlastmod', 'getmygid', 'getmyinode',
        'getmypid', 'getmyuid', 'getrusage', 'get_browser', 'highlight_file',
        'show_source', 'debug_backtrace', 'debug_print_backtrace',

        'mysql_connect', 'mysqli_connect', 'pg_connect', 'sqlite_open',
        'oci_connect', 'odbc_connect', 'dbase_open', 'dba_open', 'dba_close',

        'mail', 'mb_send_mail',

        'virtual', 'leak', 'listen', 'chroot', 'apache_child_terminate', 'posix_kill',
        'posix_mkfifo', 'posix_setpgid', 'posix_setsid', 'posix_setuid',
        'posix_setgid', 'posix_uname', 'posix_times', 'posix_ctermid',
        'posix_getcwd', 'posix_getegid', 'posix_geteuid', 'posix_getgid',
        'posix_getgrgid', 'posix_getgrnam', 'posix_getgroups', 'posix_getlogin',
        'posix_getpgid', 'posix_getpgrp', 'posix_getpid', 'posix_getppid',
        'posix_getpwnam', 'posix_getpwuid', 'posix_getrlimit', 'posix_getsid',
        'posix_getuid', 'posix_isatty', 'posix_setegid', 'posix_seteuid',
        'posix_setgid', 'posix_setuid', 'posix_ttyname', 'posix_access',
        'posix_mknod', 'posix_strerror', 'posix_times',
    ];

    private const DISABLED_CLASSES = [
        'DirectoryIterator', 'FilesystemIterator', 'GlobIterator', 'RecursiveDirectoryIterator',
        'SplFileObject', 'SplFileInfo', 'SplTempFileObject', 'ReflectionFunction',
        'ReflectionMethod', 'ReflectionClass', 'ReflectionProperty', 'ReflectionParameter',
        'ReflectionExtension', 'ReflectionZendExtension', 'ZipArchive', 'PDO', 'mysqli',
        'SQLite3', 'Memcached', 'Redis', 'MongoClient', 'MongoDB\Client', 'SoapClient',
        'CURLFile', 'DOMDocument', 'XMLReader', 'XMLWriter', 'SimpleXMLElement',
        'XSLTProcessor', 'COM', 'DotNet', 'Imagick', 'Gmagick', 'finfo', 'Phar',
        'PharData', 'PharFileInfo', 'SNMP', 'LDAP\Connection', 'EnchantBroker',
        'HaruDoc', 'HaruPage', 'HaruFont', 'HaruEncoder', 'TokyoTyrant',
        'TokyoTyrantTable', 'Yar_Client', 'Yar_Server', 'GearmanClient',
        'GearmanWorker', 'Memcache', 'SolrClient', 'SolrQuery', 'SphinxClient',
    ];

    private const ALLOWED_FUNCTIONS = [
        'strlen', 'substr', 'strpos', 'strrpos', 'stripos', 'strripos',
        'str_replace', 'str_ireplace', 'trim', 'ltrim', 'rtrim', 'chop',
        'strtolower', 'strtoupper', 'ucfirst', 'ucwords', 'lcfirst',
        'explode', 'implode', 'join', 'str_split', 'chunk_split',
        'wordwrap', 'htmlspecialchars', 'htmlentities', 'html_entity_decode',
        'strip_tags', 'nl2br', 'md5', 'sha1', 'crc32', 'base64_encode',
        'base64_decode', 'urlencode', 'urldecode', 'rawurlencode', 'rawurldecode',
        'json_encode', 'json_decode', 'serialize', 'unserialize',
        'str_pad', 'str_repeat', 'str_shuffle', 'str_word_count', 'strcasecmp',
        'strcmp', 'strcoll', 'strcspn', 'stristr', 'strnatcmp', 'strnatcasecmp',
        'strncasecmp', 'strncmp', 'strpbrk', 'strspn', 'strstr', 'strtok',
        'strtr', 'substr_compare', 'substr_count', 'substr_replace',
        'quotemeta', 'addcslashes', 'addslashes', 'stripcslashes', 'stripslashes',
        'chr', 'ord', 'parse_str', 'str_getcsv', 'str_rot13', 'strval',
        'number_format', 'money_format', 'sprintf', 'vsprintf', 'printf',
        'vprintf', 'sscanf',

        'count', 'sizeof', 'array_merge', 'array_merge_recursive',
        'array_keys', 'array_values', 'array_key_exists', 'key_exists',
        'in_array', 'array_search', 'array_map', 'array_filter', 'array_reduce',
        'array_walk', 'array_walk_recursive', 'array_slice', 'array_splice',
        'array_chunk', 'array_combine', 'array_fill', 'array_fill_keys',
        'array_pad', 'array_pop', 'array_push', 'array_shift', 'array_unshift',
        'array_reverse', 'array_flip', 'array_unique', 'array_sum',
        'array_product', 'array_rand', 'shuffle', 'range', 'array_change_key_case',
        'array_column', 'array_count_values', 'array_diff', 'array_diff_assoc',
        'array_diff_key', 'array_diff_uassoc', 'array_diff_ukey',
        'array_intersect', 'array_intersect_assoc', 'array_intersect_key',
        'array_intersect_uassoc', 'array_intersect_ukey', 'array_udiff',
        'array_udiff_assoc', 'array_udiff_uassoc', 'array_uintersect',
        'array_uintersect_assoc', 'array_uintersect_uassoc', 'array_multisort',
        'arsort', 'asort', 'krsort', 'ksort', 'natcasesort', 'natsort',
        'rsort', 'sort', 'uasort', 'uksort', 'usort', 'compact', 'extract',
        'list', 'each', 'current', 'next', 'prev', 'reset', 'end', 'key',

        'abs', 'ceil', 'floor', 'round', 'max', 'min', 'rand', 'mt_rand',
        'sqrt', 'pow', 'exp', 'log', 'log10', 'sin', 'cos', 'tan', 'asin',
        'acos', 'atan', 'atan2', 'pi', 'bindec', 'decbin', 'dechex', 'hexdec',
        'decoct', 'octdec', 'base_convert', 'number_format', 'hypot',
        'deg2rad', 'rad2deg', 'fmod', 'intdiv', 'is_finite', 'is_infinite',
        'is_nan', 'lcg_value', 'mt_srand', 'srand', 'getrandmax', 'mt_getrandmax',
        'random_int', 'random_bytes', 'min', 'max',

        'intval', 'floatval', 'doubleval', 'strval', 'boolval', 'settype',
        'gettype', 'is_array', 'is_string', 'is_int', 'is_integer', 'is_long',
        'is_float', 'is_double', 'is_bool', 'is_null', 'is_numeric', 'is_scalar',
        'is_callable', 'is_object', 'is_resource', 'var_dump', 'print_r',
        'var_export', 'is_countable', 'is_iterable', 'is_a', 'is_subclass_of',
        'get_class', 'get_parent_class', 'get_called_class', 'get_object_vars',
        'get_class_methods', 'get_class_vars', 'method_exists', 'property_exists',
        'class_exists', 'interface_exists', 'trait_exists', 'enum_exists',

        'date', 'time', 'microtime', 'strtotime', 'date_default_timezone_set',
        'gmdate', 'gmmktime', 'localtime', 'getdate', 'checkdate', 'date_create',
        'date_format', 'date_diff', 'date_add', 'date_sub', 'date_timestamp_get',
        'date_timestamp_set', 'timezone_open', 'timezone_name_get', 'date_parse',
        'date_parse_from_format', 'date_sun_info', 'date_sunrise', 'date_sunset',
        'idate', 'mktime', 'strftime', 'gmstrftime', 'timezone_identifiers_list',
        'timezone_location_get', 'timezone_name_from_abbr', 'timezone_offset_get',
        'timezone_transitions_get', 'timezone_version_get',

        'version_compare', 'constant', 'define', 'defined', 'sleep', 'usleep',
        'uniqid', 'getrandmax', 'mt_getrandmax', 'srand', 'mt_srand',
        'pack', 'unpack', 'crc32', 'crypt', 'hash', 'hash_algos', 'hash_file',
        'hash_hmac', 'hash_hmac_file', 'hash_init', 'hash_update', 'hash_final',
        'password_hash', 'password_verify', 'password_needs_rehash', 'password_get_info',
        'bin2hex', 'hex2bin', 'bin2hex', 'hex2bin', 'quoted_printable_encode',
        'quoted_printable_decode', 'convert_uuencode', 'convert_uudecode',
        'metaphone', 'soundex', 'levenshtein', 'similar_text', 'localeconv',
        'nl_langinfo',
    ];

    /**
     * 构造函数
     */
    public function __construct(array $config = [])
    {
        // 使用更安全的唯一前缀生成方式，确保纯小写字母
        self::$instanceCounter++;
        $this->uniquePrefix = $this->generateUniquePrefix();
        $this->initializeConfiguration($config);
        $this->initializeSecuritySettings();
        $this->initializeTempDirectory();
        $this->registerShutdownFunction();
        $this->preCheckEnvironment();
        $this->executionHistory = [];
    }

    /**
     * 生成唯一前缀 - 纯小写字母
     */
    private function generateUniquePrefix(): string
    {
        // 使用实例计数器、时间戳和随机字符串生成唯一前缀
        $timestamp = (int)(microtime(true) * 1000000);
        $randomStr = $this->generateRandomString(12);
        $instanceId = self::$instanceCounter;

        // 组合并确保纯小写字母
        $base = "sandbox_{$instanceId}_{$timestamp}_{$randomStr}";
        return preg_replace('/[^a-z0-9_]/', '', strtolower($base)) . '_';
    }

    /**
     * 生成随机字符串 - 纯小写字母
     */
    private function generateRandomString(int $length): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $result = '';
        $max = strlen($characters) - 1;

        for ($i = 0; $i < $length; $i++) {
            $result .= $characters[random_int(0, $max)];
        }

        return $result;
    }

    /**
     * 初始化配置
     */
    private function initializeConfiguration(array $config): void
    {
        $this->memoryLimit = max(1, $config['memory_limit'] ?? self::DEFAULT_MEMORY_LIMIT);
        $this->maxExecutionTime = max(1, $config['max_execution_time'] ?? self::DEFAULT_EXECUTION_TIME);
        $this->maxCodeLength = max(100, $config['max_code_length'] ?? self::DEFAULT_MAX_CODE_LENGTH);

        $tempDir = $config['temp_dir'] ?? sys_get_temp_dir() . '/php_sandbox_' . $this->generateRandomString(16);
        $this->tempDir = $this->sanitizePath($tempDir);
    }

    /**
     * 初始化安全设置
     */
    private function initializeSecuritySettings(): void
    {
        $this->disabledFunctions = self::DISABLED_FUNCTIONS;
        $this->disabledClasses = self::DISABLED_CLASSES;
        $this->allowedFunctions = self::ALLOWED_FUNCTIONS;

        $this->disabledKeywords = [
            'eval', 'exec', 'system', 'shell_exec', 'passthru', 'proc_open',
            'popen', 'pcntl_exec', 'include', 'require', 'fopen', 'file_put_contents',
            'chmod', 'chown', '`', '$_GET', '$_POST', '$_REQUEST', '$_COOKIE',
            '$_SERVER', '$_FILES', '$_ENV', '$GLOBALS', 'php://', 'phar://',
            'zip://', 'data://', 'expect://', 'ssh2://', 'rar://', 'ogg://',
            'http://', 'https://', 'ftp://', 'ftps://', 'exit', 'die', 'phpinfo',
            'putenv', 'ini_set', 'ini_alter', 'dl', 'header', 'setcookie',
            'session_start', 'session_id', 'mysql_connect', 'mysqli_connect',
            'pg_connect', 'sqlite_open', 'curl_init', 'fsockopen',
        ];
    }

    /**
     * 初始化临时目录
     */
    private function initializeTempDirectory(): void
    {
        if (!is_dir($this->tempDir)) {
            if (!@mkdir($this->tempDir, 0700, true) && !is_dir($this->tempDir)) {
                throw new \RuntimeException("无法创建临时目录: {$this->tempDir}");
            }
        }

        @chmod($this->tempDir, 0700);

        // 创建安全保护文件
        $htaccessContent = "Order deny,allow\nDeny from all";
        @file_put_contents($this->tempDir . '/.htaccess', $htaccessContent);
    }

    /**
     * 清理路径
     */
    private function sanitizePath(string $path): string
    {
        $path = str_replace(['../', './', '~', '//'], '', $path);

        $sysTemp = sys_get_temp_dir();
        if (strpos($path, $sysTemp) !== 0) {
            $path = $sysTemp . '/' . basename($path);
        }

        return rtrim($path, '/\\');
    }

    /**
     * 预检查环境
     */
    private function preCheckEnvironment(): void
    {
        $requiredExtensions = ['tokenizer'];
        foreach ($requiredExtensions as $ext) {
            if (!extension_loaded($ext)) {
                throw new \RuntimeException("需要 {$ext} 扩展");
            }
        }

        if (!is_writable($this->tempDir)) {
            throw new \RuntimeException("临时目录不可写: {$this->tempDir}");
        }

        $systemMemoryLimit = $this->parseMemorySize(ini_get('memory_limit'));
        if ($systemMemoryLimit > 0 && $this->memoryLimit * 1024 * 1024 > $systemMemoryLimit) {
            throw new \RuntimeException("请求的内存限制超过系统限制");
        }
    }

    /**
     * 解析内存大小
     */
    private function parseMemorySize(string $size): int
    {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
        $size = preg_replace('/[^0-9\.]/', '', $size);

        // Ensure $size is a valid numeric string; default to 0 if not.
        $size = is_string($size) ? $size : '0';

        if ($unit && is_string($unit)) {
            return (int)round((float)$size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        }

        return (int)round((float)$size);
    }

    /**
     * 注册关闭处理函数
     */
    private function registerShutdownFunction(): void
    {
        register_shutdown_function(function() {
            $this->cleanup();
        });
    }

    /**
     * 执行PHP代码
     */
    public function execute($code, ?string $identifier = null)
    {
        // 批量执行
        if (is_array($code)) {
            return $this->executeBatch($code);
        }

        // 单个执行
        if ($this->isExecuting) {
            throw new \LogicException("沙箱正在执行中，不能同时执行多个代码片段");
        }

        $this->isExecuting = true;
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage(true);
        $this->executionCount++;

        $result = new PhpSandboxResult();
        $identifier = $identifier ?? 'exec_' . $this->executionCount;

        try {
            $this->saveEnvironment();

            // 预处理代码：安全移除PHP标签
            $processedCode = $this->preprocessCode($code);
            $this->performSecurityChecks($processedCode);

            $this->setupExecutionEnvironment();
            $this->currentTempFile = $this->createTempFile($processedCode);
            $output = $this->executeTempFile();

            $result->setSuccess(true)
                ->setOutput($output)
                ->setIdentifier($identifier);

        } catch (\Throwable $e) {
            $result->setError($this->formatException($e))
                ->setErrorType(get_class($e))
                ->setIdentifier($identifier);
        } finally {
            $this->cleanupExecution();

            $result->setExecutionTime(round(microtime(true) - $this->startTime, 4))
                ->setMemoryUsed(memory_get_peak_usage(true) - $this->startMemory)
                ->setPeakMemory(memory_get_peak_usage(true))
                ->setTimestamp(time());

            $this->recordExecution($result);
        }

        return $result;
    }

    /**
     * 批量执行代码
     */
    private function executeBatch(array $codes): array
    {
        $results = [];

        foreach ($codes as $key => $code) {
            try {
                $results[$key] = $this->execute($code, is_string($key) ? $key : null);
            } catch (\Throwable $e) {
                $errorResult = new PhpSandboxResult();
                $errorResult->setError($e->getMessage())
                    ->setErrorType(get_class($e))
                    ->setIdentifier(is_string($key) ? $key : 'batch_error');
                $results[$key] = $errorResult;
            }

            if (gc_enabled()) {
                gc_collect_cycles();
            }

            usleep(1000);
        }

        return $results;
    }

    /**
     * 预处理代码：安全移除PHP标签
     */
    private function preprocessCode(string $code): string
    {
        // 安全移除PHP开始标签
        $code = preg_replace('/^\s*<\?php\s*/i', '', $code);
        // 安全移除PHP结束标签
        $code = preg_replace('/\s*\?>\s*$/', '', $code);

        // 清理空白字符
        $code = trim($code);

        return $code;
    }

    /**
     * 保存环境设置
     */
    private function saveEnvironment(): void
    {
        $this->originalSettings = [
            'error_reporting' => error_reporting(),
            'display_errors' => ini_get('display_errors'),
            'log_errors' => ini_get('log_errors'),
            'max_execution_time' => ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit'),
            'disable_functions' => ini_get('disable_functions'),
        ];
    }

    /**
     * 执行安全检查
     */
    private function performSecurityChecks(string $code): void
    {
        if (strlen($code) > $this->maxCodeLength) {
            throw new SecurityException("代码长度超过限制: {$this->maxCodeLength} 字符");
        }

        $this->tokenBasedSecurityCheck($code);
        $this->patternBasedSecurityCheck($code);
        $this->complexityCheck($code);
    }

    /**
     * 基于token的安全检查
     */
    private function tokenBasedSecurityCheck(string $code): void
    {
        // 添加安全的PHP标签进行token分析
        $tokens = @token_get_all("<?php\n" . $code);

        $inFunctionCall = false;
        $currentFunction = '';
        $bracketDepth = 0;

        foreach ($tokens as $token) {
            if (is_array($token)) {
                [$id, $text, $line] = $token;

                if ($id === T_STRING) {
                    if ($inFunctionCall) {
                        $currentFunction = strtolower($text);

                        if (in_array($currentFunction, $this->disabledFunctions, true)) {
                            throw new SecurityException("检测到禁用函数: {$currentFunction} (行: {$line})");
                        }

                        if (in_array($text, $this->disabledClasses, true)) {
                            throw new SecurityException("检测到禁用类: {$text} (行: {$line})");
                        }

                        $inFunctionCall = false;
                    }

                    if (in_array(strtolower($text), $this->disabledKeywords, true)) {
                        throw new SecurityException("检测到禁用关键字: {$text} (行: {$line})");
                    }
                } elseif ($id === T_EVAL) {
                    throw new SecurityException("检测到eval关键字 (行: {$line})");
                }
            } else {
                $tokenStr = $token;
                if ($tokenStr === '(') {
                    $inFunctionCall = true;
                } elseif ($tokenStr === ')' || $tokenStr === ';') {
                    $inFunctionCall = false;
                } elseif ($tokenStr === '{') {
                    $bracketDepth++;
                } elseif ($tokenStr === '}') {
                    $bracketDepth--;
                }
            }
        }

        if ($bracketDepth > 50) {
            throw new SecurityException("代码嵌套过深，可能存在无限递归");
        }
    }

    /**
     * 基于模式的安全检查
     */
    private function patternBasedSecurityCheck(string $code): void
    {
        $cleanCode = $this->cleanCodeForAnalysis($code);

        $dangerousPatterns = [
            '/\beval\s*\(\s*\$/' => '动态eval调用',
            '/`.*`/s' => '反引号执行',
            '/\$_(GET|POST|REQUEST|COOKIE|SERVER|FILES|ENV)\b/' => '超全局变量访问',
            '/php:\/\/(filter|input|glob|expect)/i' => '危险流包装器',
            '/phar:\/\//i' => 'PHAR流访问',
            '/\bexit\s*\(/i' => 'exit调用',
            '/\bdie\s*\(/i' => 'die调用',
            '/\bini_set\s*\(/i' => 'ini_set调用',
            '/\bputenv\s*\(/i' => 'putenv调用',
            '/\bdl\s*\(/i' => 'dl调用',
            '/\$GLOBALS\s*\[/i' => 'GLOBALS数组访问',
            '/\b(__halt_compiler)\s*\(/i' => '编译器停止',
        ];

        foreach ($dangerousPatterns as $pattern => $description) {
            if (preg_match($pattern, $cleanCode)) {
                throw new SecurityException("检测到危险模式: {$description}");
            }
        }
    }

    /**
     * 复杂度检查
     */
    private function complexityCheck(string $code): void
    {
        $functionCount = substr_count($code, 'function ');
        if ($functionCount > 20) {
            throw new SecurityException("函数定义过多: {$functionCount}");
        }

        $loopCount = substr_count($code, 'for(') + substr_count($code, 'while(') +
            substr_count($code, 'foreach(');
        if ($loopCount > 10) {
            throw new SecurityException("循环结构过多: {$loopCount}");
        }
    }

    /**
     * 清理代码用于分析
     */
    private function cleanCodeForAnalysis(string $code): string
    {
        $code = preg_replace('/\/\/.*$/m', '', $code);
        $code = preg_replace('/\/\*.*?\*\//s', '', $code);
        $code = preg_replace('/\'(?:[^\'\\\\]|\\\\.)*\'/s', "''", $code);
        $code = preg_replace('/\"(?:[^\"\\\\]|\\\\.)*\"/s', '""', $code);
        $code = preg_replace('/<<<\s*[\'"]?(\w+)[\'"]?.*?\1;/s', '"";', $code);

        return $code;
    }

    /**
     * 设置执行环境
     */
    private function setupExecutionEnvironment(): void
    {
        error_reporting(E_ALL);
        @set_time_limit($this->maxExecutionTime);

        set_error_handler([$this, 'handleError']);
        register_shutdown_function([$this, 'handleShutdown']);

        if (function_exists('ini_set') && !in_array('ini_set', $this->disabledFunctions, true)) {
            @ini_set('display_errors', '0');
            @ini_set('log_errors', '0');
        }
    }

    /**
     * 创建临时文件
     */
    private function createTempFile(string $code): string
    {
        $filename = $this->tempDir . '/sandbox_' . $this->generateRandomString(16) . '.php';
        $wrappedCode = $this->wrapCode($code);

        if (file_put_contents($filename, $wrappedCode, LOCK_EX) === false) {
            throw new \RuntimeException("无法创建临时文件: {$filename}");
        }

        @chmod($filename, 0600);
        return $filename;
    }

    /**
     * 包装代码 - 修复函数重复声明问题
     */
    private function wrapCode(string $code): string
    {
        $memoryCheckFunc = $this->uniquePrefix . 'check_memory_usage';
        $timeCheckFunc = $this->uniquePrefix . 'check_execution_time';

        // 使用字符串格式化避免浮点数问题
        $memoryLimit = $this->memoryLimit;
        $maxExecutionTime = $this->maxExecutionTime;

        $wrappedCode = "<?php\n" .
            "// PHP沙箱执行环境\n" .
            "error_reporting(E_ALL);\n" .
            "set_time_limit({$maxExecutionTime});\n";

        // 检查函数是否已定义，避免重复声明
        $wrappedCode .= "\nif (!function_exists('{$memoryCheckFunc}')) {\n" .
            "    function {$memoryCheckFunc}() {\n" .
            "        static \$last_check = 0;\n" .
            "        \$current_time = microtime(true);\n" .
            "        if (\$current_time - \$last_check > 0.1) {\n" .
            "            \$memory_usage = memory_get_usage(true);\n" .
            "            \$memory_limit = {$memoryLimit} * 1024 * 1024;\n" .
            "            if (\$memory_usage > \$memory_limit * 0.9) {\n" .
            "                throw new RuntimeException('内存使用超过限制');\n" .
            "            }\n" .
            "            \$last_check = \$current_time;\n" .
            "        }\n" .
            "    }\n" .
            "}\n";

        $wrappedCode .= "\nif (!function_exists('{$timeCheckFunc}')) {\n" .
            "    function {$timeCheckFunc}() {\n" .
            "        static \$start_time = null;\n" .
            "        if (\$start_time === null) {\n" .
            "            \$start_time = microtime(true);\n" .
            "        }\n" .
            "        \$current_time = microtime(true);\n" .
            "        if (\$current_time - \$start_time > {$maxExecutionTime} * 0.9) {\n" .
            "            throw new RuntimeException('执行时间接近限制');\n" .
            "        }\n" .
            "    }\n" .
            "}\n";

        $wrappedCode .= "\n// 用户代码开始\n" .
            "try {\n" .
            "    if (function_exists('register_tick_function')) {\n" .
            "        register_tick_function('{$memoryCheckFunc}');\n" .
            "        register_tick_function('{$timeCheckFunc}');\n" .
            "    }\n" .
            "    if (function_exists('register_tick_function')) {\n" .
            "        declare(ticks=100) {\n" .
            $this->indentCode($code) . "\n" .
            "        }\n" .
            "    } else {\n" .
            "        // 如果不支持ticks，直接执行代码\n" .
            $this->indentCode($code) . "\n" .
            "    }\n" .
            "} catch (Throwable \$e) {\n" .
            "    echo '沙箱捕获异常: ' . get_class(\$e) . ': ' . \$e->getMessage() . \"\\n\";\n" .
            "    throw \$e;\n" .
            "} finally {\n" .
            "    if (function_exists('unregister_tick_function')) {\n" .
            "        @unregister_tick_function('{$memoryCheckFunc}');\n" .
            "        @unregister_tick_function('{$timeCheckFunc}');\n" .
            "    }\n" .
            "}\n" .
            "// 用户代码结束\n";

        return $wrappedCode;
    }

    /**
     * 缩进代码
     */
    private function indentCode(string $code): string
    {
        $lines = explode("\n", $code);
        $indented = [];

        foreach ($lines as $line) {
            $indented[] = '        ' . $line;
        }

        return implode("\n", $indented);
    }

    /**
     * 执行临时文件
     */
    private function executeTempFile(): string
    {
        ob_start();

        try {
            include $this->currentTempFile;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string)ob_get_clean();
    }

    /**
     * 错误处理
     */
    public function handleError(int $level, string $message, string $file = '', int $line = 0): bool
    {
        if ($level === E_STRICT || $level === E_DEPRECATED || $level === E_USER_DEPRECATED) {
            return true;
        }

        if (error_reporting() & $level) {
            throw new \ErrorException($message, 0, $level, $file, $line);
        }

        return true;
    }

    /**
     * 关闭处理
     */
    public function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            throw new \ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            );
        }
    }

    /**
     * 记录执行历史
     */
    private function recordExecution(PhpSandboxResult $result): void
    {
        $this->executionHistory[] = [
            'timestamp' => $result->getTimestamp(),
            'identifier' => $result->getIdentifier(),
            'success' => $result->isSuccess(),
            'execution_time' => $result->getExecutionTime(),
            'memory_used' => $result->getMemoryUsed(),
        ];

        if (count($this->executionHistory) > $this->maxHistorySize) {
            array_shift($this->executionHistory);
        }
    }

    /**
     * 清理执行环境
     */
    private function cleanupExecution(): void
    {
        $this->isExecuting = false;

        if ($this->currentTempFile && file_exists($this->currentTempFile)) {
            @unlink($this->currentTempFile);
            $this->currentTempFile = null;
        }

        $this->restoreEnvironment();

        if (gc_enabled()) {
            gc_collect_cycles();
        }
    }

    /**
     * 恢复环境设置
     */
    private function restoreEnvironment(): void
    {
        if (empty($this->originalSettings)) {
            return;
        }

        restore_error_handler();

        error_reporting($this->originalSettings['error_reporting']);

        if (function_exists('ini_set') && !in_array('ini_set', $this->disabledFunctions, true)) {
            @ini_set('display_errors', $this->originalSettings['display_errors']);
            @ini_set('log_errors', $this->originalSettings['log_errors']);
        }

        @set_time_limit((int)$this->originalSettings['max_execution_time']);
    }

    /**
     * 格式化异常信息
     */
    private function formatException(\Throwable $e): string
    {
        $type = get_class($e);
        $message = $e->getMessage();

        return "{$type}: {$message}";
    }

    /**
     * 完全清理资源
     */
    public function cleanup(): void
    {
        $this->cleanupExecution();

        if (is_dir($this->tempDir)) {
            $files = @glob($this->tempDir . '/*');
            if ($files) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            }

            $remainingFiles = @glob($this->tempDir . '/*');
            if (empty($remainingFiles)) {
                @rmdir($this->tempDir);
            }
        }
    }

    /**
     * 获取执行统计
     */
    public function getStatistics(): array
    {
        $totalExecutions = count($this->executionHistory);
        $successfulExecutions = count(array_filter($this->executionHistory, fn($item) => $item['success']));
        $totalExecutionTime = array_sum(array_column($this->executionHistory, 'execution_time'));
        $averageExecutionTime = $totalExecutions > 0 ? $totalExecutionTime / $totalExecutions : 0;

        return [
            'total_executions' => $totalExecutions,
            'successful_executions' => $successfulExecutions,
            'failed_executions' => $totalExecutions - $successfulExecutions,
            'success_rate' => $totalExecutions > 0 ? ($successfulExecutions / $totalExecutions) * 100 : 0,
            'total_execution_time' => $totalExecutionTime,
            'average_execution_time' => $averageExecutionTime,
            'current_memory_usage' => memory_get_usage(true),
            'peak_memory_usage' => memory_get_peak_usage(true),
        ];
    }

    /**
     * 获取执行历史
     */
    public function getExecutionHistory(int $limit = 10): array
    {
        return array_slice($this->executionHistory, -$limit);
    }

    /**
     * 设置配置
     */
    public function setConfig(array $config): self
    {
        if (isset($config['memory_limit'])) {
            $this->memoryLimit = max(1, (int)$config['memory_limit']);
        }
        if (isset($config['max_execution_time'])) {
            $this->maxExecutionTime = max(1, (int)$config['max_execution_time']);
        }
        if (isset($config['max_code_length'])) {
            $this->maxCodeLength = max(100, (int)$config['max_code_length']);
        }
        if (isset($config['max_history_size'])) {
            $this->maxHistorySize = max(10, (int)$config['max_history_size']);
        }

        return $this;
    }

    /**
     * 获取临时目录
     */
    public function getTempDir(): string
    {
        return $this->tempDir;
    }

    /**
     * 添加允许的函数
     */
    public function addAllowedFunction(string $function): self
    {
        if (!in_array($function, $this->allowedFunctions, true)) {
            $this->allowedFunctions[] = $function;
        }
        return $this;
    }

    /**
     * 添加禁用函数
     */
    public function addDisabledFunction(string $function): self
    {
        if (!in_array($function, $this->disabledFunctions, true)) {
            $this->disabledFunctions[] = $function;
        }
        return $this;
    }

    /**
     * 获取当前配置
     */
    public function getConfig(): array
    {
        return [
            'memory_limit' => $this->memoryLimit,
            'max_execution_time' => $this->maxExecutionTime,
            'max_code_length' => $this->maxCodeLength,
            'temp_dir' => $this->tempDir,
            'max_history_size' => $this->maxHistorySize,
        ];
    }

    /**
     * 重置执行历史
     */
    public function resetHistory(): self
    {
        $this->executionHistory = [];
        $this->executionCount = 0;
        return $this;
    }

    /**
     * 获取唯一前缀（用于测试）
     */
    public function getUniquePrefix(): string
    {
        return $this->uniquePrefix;
    }

    /**
     * 析构函数
     */
    public function __destruct()
    {
        $this->cleanup();
    }
}
