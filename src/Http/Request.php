<?php

declare(strict_types=1);

namespace zxf\Utils\Http;

use zxf\Utils\Xml\XML2Array;

/**
 * HTTP 请求类
 */
class Request
{
    protected static ?self $instance = null;

    private ?string $contentType = '';

    private array $overridden = [];

    private array $get = [];

    private array $post = [];

    private array $input = [];

    private static ?string $rawInput = null;

    private array $files = [];

    private array $sessions = [];

    private array $cookies = [];

    private array $env = [];

    private array $servers = [];

    private array $headers = [];

    private ?string $protocol = null;

    private ?string $method = null;

    private ?string $query = null;

    private ?string $userAgent = null;

    private ?int $port = null;

    private array $resolvers = [];

    private array $proxies = [];

    /**
     * 构造时立即从当前请求的超全局变量中采集数据
     *
     * 会读取 $_SERVER、$_GET、$_POST、$_FILES、$_COOKIE、$_SESSION、$_ENV 等，
     * 因此必须在请求生命周期内使用；外部请优先通过 instance() 获取共享实例。
     */
    public function __construct()
    {
        $this->init();
    }

    /**
     * 获取请求单例
     *
     * 首次调用时创建实例并采集请求数据，之后复用同一实例（不会重新读取超全局变量）；
     * 传入 $refresh 为 true 时丢弃旧实例并重新采集，适用于常驻进程（如 Swoole）
     * 或同一进程内模拟多次请求的场景。
     *
     * @param  bool  $refresh  是否强制重建实例
     * @return static 请求实例
     */
    public static function instance(bool $refresh = false): static
    {
        if (self::$instance === null || $refresh) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    /**
     * 获取参数前初始化请求信息
     *
     * @return $this
     */
    private function init(): static
    {
        // 一、基础的、逻辑较简单的放在最前面优先获取
        // 获取请求方法（GET、POST等）
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        // 获取请求的内容类型
        $this->contentType = strtolower(! empty($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : (! empty($_SERVER['HTTP_CONTENT_TYPE']) ? $_SERVER['HTTP_CONTENT_TYPE'] : 'text/html'));
        // 获取GET参数
        $this->get = ! empty($_GET) ? $_GET : [];
        // 获取POST参数（PHP 5.6+ 已移除 $GLOBALS['HTTP_RAW_POST_DATA']，直接使用 $_POST）
        $this->post = $_POST ?? [];
        // 获取查询字符串参数（URL中"?"后的部分）
        $this->query = $_SERVER['QUERY_STRING'] ?? '';
        // 获取用户代理（User-Agent）信息
        $this->userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        // 获取文件参数
        $this->files = $_FILES ?? [];

        // 二、获取其他内容
        // 获取重写的参数
        $this->overridden = $_SERVER['REDIRECT_STATUS'] ?? [];
        // 获取输入参数（可能是GET或POST 等）
        $this->input = $this->getRawInput(false) ?? [];

        // 获取Session参数
        $this->sessions = $_SESSION ?? [];
        // 获取Cookie参数
        $this->cookies = $_COOKIE ?? [];
        // 获取环境变量参数
        $this->env = $_ENV ?? [];
        // 获取服务器参数
        $this->servers = $_SERVER ?? [];
        // 获取协议类型（HTTP或HTTPS）
        $this->protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : ($this->servers['SERVER_PROTOCOL'] ?? 'http');

        // 获取请求的端口号信息（如果是HTTP默认端口80，HTTPS默认端口443）
        $this->port = (int) ($_SERVER['SERVER_PORT'] ?? ($_SERVER['X_FORWARDED_PORT'] ?? 80));

        // 获取请求头信息
        $this->headers = $this->buildHeadersFromServer();

        return $this;
    }

    /**
     * 从超全局变量重建请求头（键统一为大写）
     *
     * 优先使用 SAPI 提供的 `apache_request_headers()` / `getallheaders()`
     * （它们能原样保留 Content-Type、Content-Length 等头）；
     * 两者都不可用时（CLI、部分 CGI）从 `$_SERVER` 的 `HTTP_*` 变量重建。
     *
     * 注意：早期实现用 `headers_list()` 作为回退是**错误**的 ——
     * `headers_list()` 返回的是**待发送的响应头**，不是请求头，
     * 在 FPM/CLI 下会导致所有请求头丢失。
     *
     * @return array<string, string>
     */
    private function buildHeadersFromServer(): array
    {
        if (function_exists('apache_request_headers')) {
            $headers = @apache_request_headers();
            if (is_array($headers)) {
                return array_change_key_case($headers, CASE_UPPER);
            }
        }

        if (function_exists('getallheaders')) {
            $headers = @getallheaders();
            if (is_array($headers)) {
                return array_change_key_case($headers, CASE_UPPER);
            }
        }

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[str_replace('_', '-', substr($key, 5))] = (string) $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $headers[str_replace('_', '-', $key)] = (string) $value;
            }
        }

        return $headers;
    }

    /**
     * 检查一个字符串是否为 key1=value1&key2=value2 格式的字符串
     *
     * 判据是「parse_str 能解析出非空结果」且「原串含有 =」。这只是格式
     * 上的粗略判断，不保证每个片段都带 =（如 a=1&b 也会返回 true）。
     *
     * @param mixed $str 待判断的值，非字符串或空值直接视为否
     * @return bool 形似 URL 编码字符串返回 true，否则返回 false
     */
    private function isUrlEncodedString(mixed $str): bool
    {
        if (empty($str) || ! is_string($str)) {
            return false;
        }
        parse_str($str, $result);

        // 如果解析后数组不为空且原始字符串包含=号，则可能是正确格式
        return ! empty($result) && str_contains($str, '=');
    }

    /**
     * 获取请求体内容，可返回原始字符串或解析后的数组
     *
     * 原始内容由 getRequestRawContent() 读取并缓存到静态属性，重复调用不会重复读流。
     * $returnOriginal 为 true 时直接返回原始字符串（可能为 null）；为 false 时依次按
     * urlencoded、JSON、XML 解析，并合并 $_GET 与 $_POST（无 POST 时合并 $_FILES），
     * 原始内容为空且 GET/POST/FILES 均无数据时返回空数组。
     * 注意：构造阶段已把解析结果缓存到 $this->input，缓存非空时会直接返回缓存，
     * 此时 $getDataType 不生效。
     *
     * @param  bool  $returnOriginal  true 返回原始内容，false 返回解析后的数据
     * @param  bool  $getDataType  为 true 时返回 ['data' => 数据, 'type' => raw|json|xml]
     * @return array|string|null 原始字符串、解析结果数组，或含 data/type 的数组
     */
    public function getRawInput(bool $returnOriginal = true, bool $getDataType = false): array|string|null
    {
        $rawInput = $this->getRequestRawContent();

        // 如果是原始数据，直接返回
        if ($returnOriginal) {
            return $rawInput;
        }
        if ($this->input !== []) {
            return $this->input;
        }

        // 如果未获取到原始数据，返回 null
        if (($rawInput === null || $rawInput === '') && $this->get === [] && $this->post === [] && $this->files === []) {
            return [];
        }

        $data = [];
        // 默认返回的解析结果
        $type = 'raw';

        if ($this->isUrlEncodedString($rawInput)) {
            // 判断 $rawInput 是否为
            parse_str($rawInput, $form);
            $data = is_array($form) ? $form : [];
        }

        // 判断数据类型并进行解析
        if (is_json($rawInput)) {
            $type = 'json';
            $data = is_array($rawInput) ? $rawInput : json_decode($rawInput, true);
        } elseif (is_xml($rawInput)) {
            $type = 'xml';
            $data = XML2Array::toArray($rawInput);
        }

        // 处理 get 数据
        if (! empty($this->get) && ! empty($data)) {
            $data = array_merge($data, $this->get);
        }

        // 处理 POST 数据， multipart/form-data 数据不包含在 $rawInput 中，需要单独处理
        if ((! empty($this->post) || ! empty($this->files)) && ! empty($data)) {
            $data = ! empty($this->post) ? array_merge($data, $this->post) : array_merge($data, $this->files);
        }

        // 如果需要返回数据类型
        if ($getDataType) {
            return [
                'data' => $data,
                'type' => $type,
            ];
        }

        return (array) $data;
    }

    /**
     * 获取PHP原始请求内容
     *      提示：如果是混合形式的请求(例如是 $_GET 请求 但是又包含了xml 等请求体)的只返回请求体数据，不返回$_GET 数据
     *
     * 优先使用静态缓存；缓存为资源句柄时先 rewind 再读取，保证可重复调用。
     * 未命中缓存时按框架探测顺序取 body，失败则退回 php://input，再退回
     * POST/GET 的 http_build_query 结果，最后整体为空时返回 null。
     *
     * @return mixed 原始请求体字符串；无任何可用内容时为 null
     */
    private function getRequestRawContent(): mixed
    {
        if (! empty(self::$rawInput)) {
            if (\is_resource(self::$rawInput)) {
                rewind(self::$rawInput);
                $rawInput = stream_get_contents(self::$rawInput);
            } else {
                $rawInput = self::$rawInput;
            }

            return $rawInput;
        }

        // 检测常见PHP框架并获取rawInput
        $rawInput = match (true) {
            // Laravel/Lumen
            class_exists(\Illuminate\Http\Request::class) => (
                // Laravel 封装方式尝试（优先使用一次）
                app()->bound('request')
                    ? app('request')->getContent()
                    : \Illuminate\Http\Request::capture()->getContent()
            ),
            // Symfony
            class_exists(\Symfony\Component\HttpFoundation\Request::class) => \Symfony\Component\HttpFoundation\Request::createFromGlobals()->getContent(),
            // CodeIgniter 4
            class_exists(\CodeIgniter\HTTP\IncomingRequest::class) => \CodeIgniter\Config\Services::request()->getBody(),
            // Slim 3/4
            class_exists(\Slim\Psr7\Request::class) => (string) \Slim\Factory\AppFactory::determineRequestMethod()->getBody(),
            // Yii2
            class_exists(\yii\web\Request::class) => \Yii::$app->request->getRawBody(),
            // ThinkPHP 6+
            class_exists(\think\Request::class) => \think\facade\Request::instance()->getContent(),
            // CakePHP
            class_exists(\Cake\Http\ServerRequest::class) => (string) \Cake\Http\ServerRequestFactory::fromGlobals()->getBody(),
            // Phalcon
            class_exists(\Phalcon\Http\Request::class) => \Phalcon\Di\FactoryDefault::getDefault()->get('request')->getRawBody(),
            // Zend Framework / Laminas
            class_exists(\Laminas\Diactoros\ServerRequestFactory::class) => (string) \Laminas\Diactoros\ServerRequestFactory::fromGlobals()->getBody(),
            // FuelPHP
            class_exists(\Fuel\Core\Request::class) => \Fuel\Core\Request::active()->getBody(),
            // FlightPHP
            class_exists(\flight\Engine::class) => \flight\core\Dispatcher::getInstance()->request()->getBody(),
            // 默认处理
            default => $GLOBALS['HTTP_RAW_POST_DATA'] ?? null
        };
        if (empty($rawInput)) {
            // 尝试获取原始数据 优先使用
            $rawInput = file_get_contents('php://input');
            if (empty($rawInput)) {
                if (! empty($this->post)) {
                    $rawInput = http_build_query($this->post);
                } else {
                    // 最后尝试通过输入流
                    $input = fopen('php://input', 'r');
                    $rawInput = stream_get_contents($input);
                    fclose($input);
                    $rawInput = ($rawInput !== false) ? $rawInput : null;
                }
            }
        }
        if (empty($rawInput) && ! empty($this->get)) {
            $rawInput = http_build_query($this->get);
        }

        self::$rawInput = $rawInput;

        return $rawInput;
    }

    /**
     * 获取请求类型
     *
     * 返回构造阶段由请求头推断出的 MIME 类型（如 application/json、
     * application/x-www-form-urlencoded）。请求头缺失时返回 null。
     *
     * @return string|null 请求 Content-Type；无法识别时为 null
     */
    public function getContentType(): ?string
    {
        return $this->contentType ?? null;
    }

    /**
     * 以数组形式返回解析后的输入数据
     *
     * 等价于 (array) $this->input()，即 getRawInput(false) 的解析结果（已合并
     * GET/POST 数据）；需要原始请求体字符串时应使用 getRawInput()。
     *
     * @return array 解析结果数组，无任何输入时为空数组
     */
    public function body(): array
    {
        return (array) $this->input();
    }

    /**
     * 判断请求是否被服务器内部重定向过
     *
     * 依据 $_SERVER['REDIRECT_STATUS'] 是否设置（Apache 的 ErrorDocument、rewrite
     * 内部跳转会写入该值），并非检测 PUT/DELETE 之类的方法重写。
     * 属性缺省为 []，判断方式是"与 [] 不相等"：只要该键存在，即使值为空字符串
     * 也会返回 true。
     *
     * @return bool REDIRECT_STATUS 存在时返回 true
     */
    public function overridden(): bool
    {
        return $this->overridden !== [];
    }

    /**
     * 读取内部数组中的指定键，支持默认值
     *
     * 各 get()/post()/files() 等访问器共用的取值实现：$key 为 null 时返回整个
     * 数组，否则返回对应键，键不存在（或值为 null）时返回 $default。
     *
     * @param  array  $data  数据源
     * @param  int|string|null  $key  键名；null 表示返回全部
     * @param  mixed  $default  键不存在时的默认值
     * @return mixed 取到的值或 $default
     */
    private function parseData(array $data, int|string|null $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $data;
        }

        return $data[$key] ?? $default;
    }

    /**
     * 获取 $_GET 数组中项目的值
     *
     * 不传 $key 时返回整个 $_GET 数组；仅读取构造时快照，运行期新增的 $_GET
     * 数据不会反映到结果中。
     *
     * <code>
     *   $request = Request::instance();
     *
     *   // 从 $_GET 数组中获取 username 变量。
     *   $username = $request->get('username');
     *
     *   // 如果请求的项未定义，则返回默认值。
     *   $username = $request->get('username', 'Fred Nurk');
     *
     *   // 返回 $_GET 数组中的所有数据。
     *   $input = $request->get();
     * </code>
     *
     * @param  string|null  $key  数组的键值
     * @param  mixed  $default  默认值
     * @return mixed 键不存在时返回 $default（默认为 NULL）
     */
    public function get(?string $key = null, mixed $default = null): mixed
    {
        return $this->parseData($this->get, $key, $default);
    }

    /**
     * 获取 $_POST 数组中项目的值
     *
     * 仅包含 PHP 已解析的表单体（application/x-www-form-urlencoded、multipart/form-data）；
     * application/json、text/xml 等请求体不在 $_POST 中，需要用 getRawInput() 读取。
     *
     * @param  string|null  $key  数组的键值
     * @param  mixed  $default  默认值
     * @return mixed 键不存在时返回 $default（默认为 NULL）
     */
    public function post(?string $key = null, mixed $default = null): mixed
    {
        return $this->parseData($this->post, $key, $default);
    }

    /**
     * 向 $_POST 数据中追加键值
     *
     * 传字符串键时写入单个键值（已存在则覆盖）；传二维数组时按数组 keys 批量
     * 合并，使用 "+" 运算，因此已存在的同名键会保留原值、不被新值覆盖。
     * 注意只有多维数组才走批量分支，一维数组会落入单键分支（数组作键会报错）。
     * $keys 为空时直接返回，不产生任何修改。
     *
     * @param  string|array  $keys  键名；批量追加时传二维数组
     * @param  mixed  $value  键对应的值，仅在 $keys 为字符串时生效
     * @return $this
     */
    public function addPost(string|array $keys = '', mixed $value = null): static
    {
        if (empty($keys)) {
            return $this;
        }
        if (is_array($keys) && count($keys) != count($keys, 1)) {
            // 是数组却为二维数组
            $this->post = $keys + $this->post;
        } else {
            $this->post[$keys] = $value;
        }

        return $this;
    }

    /**
     * 向 $_GET 数据中追加键值
     *
     * 行为与 addPost() 一致：字符串键写入单值，二维数组按 "+" 批量合并（已有键
     * 保留原值）；仅修改本实例的属性，不会改写超全局变量 $_GET。
     *
     * @param  array|string  $keys  键名；批量追加时传二维数组
     * @param  mixed|null  $value  键对应的值，仅在 $keys 为字符串时生效
     * @return $this
     */
    public function addGet(array|string $keys = '', mixed $value = null): static
    {
        if (empty($keys)) {
            return $this;
        }
        if (is_array($keys) && count($keys) != count($keys, 1)) {
            // 是数组却为二维数组
            $this->get = $keys + $this->get;
        } else {
            $this->get[$keys] = $value;
        }

        return $this;
    }

    /**
     * 获取 PUT 请求提交的项的值
     *
     * 仅当请求方法为 PUT 时从 $_POST 数据中取值（PHP 不会自动填充 PUT 的 body，
     * 需要业务侧自行解析后通过 addPost() 注入）；其他请求方法一律返回 $default。
     *
     * @param  string|null  $key  数组的键值
     * @param  mixed  $default  默认值
     * @return mixed 取到的值；方法不匹配或键不存在时返回 $default
     */
    public function put(?string $key = null, mixed $default = null): mixed
    {
        return $this->method() === 'PUT' ? $this->post($key, $default) : $default;
    }

    /**
     * 获取 DELETE 请求提交的项的值
     *
     * 仅当请求方法为 DELETE 时从 $_POST 数据中取值（DELETE 的 body 同样不会被
     * PHP 自动解析）；其他请求方法一律返回 $default。
     *
     * @param  string|null  $key  数组的键值
     * @param  mixed  $default  默认值
     * @return mixed 取到的值；方法不匹配或键不存在时返回 $default
     */
    public function delete(?string $key = null, mixed $default = null): mixed
    {
        return $this->method() === 'DELETE' ? $this->post($key, $default) : $default;
    }

    /**
     * 获取 $_FILES 数组中项目的值
     *
     * 单个上传文件的结构与 PHP 原生一致（name/type/tmp_name/error/size）；
     * 文件信息主要保存在 $_FILES 中，需用本方法读取（input() 只有在没有 POST
     * 数据时才会把 files 合并进来）。
     *
     * @param  string|null  $key  数组的键值
     * @param  mixed  $default  默认值
     * @return mixed 键不存在时返回 $default
     */
    public function files(?string $key = null, mixed $default = null)
    {
        return $this->parseData($this->files, $key, $default);
    }

    /**
     * 获取 $_SESSION 数组中项目的值
     *
     * 取自构造时读取的 $_SESSION 快照；若未调用 session_start()，结果为默认值。
     *
     * @param  string|null  $key  数组的键值
     * @param  mixed  $default  默认值
     * @return mixed 键不存在时返回 $default
     */
    public function session(?string $key = null, mixed $default = null)
    {
        return $this->parseData($this->sessions, $key, $default);
    }

    /**
     * 获取 $_COOKIE 数组中项目的值
     *
     * 读取的是解析后的 Cookie 键值，不含原始 Cookie 头文本；原始头可通过
     * headers('COOKIE') 获取。
     *
     * @param  string|null  $key  数组的键值
     * @param  mixed  $default  默认值
     * @return mixed 键不存在时返回 $default
     */
    public function cookie(?string $key = null, mixed $default = null)
    {
        return $this->parseData($this->cookies, $key, $default);
    }

    /**
     * 获取 $_ENV 数组中项目的值
     *
     * 注意 $_ENV 是否填充取决于 php.ini 的 variables_order 是否包含 "E"，
     * 未开启时通常只能读到默认值（读环境变量更可靠的方式是 getenv()）。
     *
     * @param  string|null  $key  数组的键值
     * @param  mixed  $default  默认值
     * @return mixed 键不存在时返回 $default
     */
    public function env(?string $key = null, mixed $default = null)
    {
        return $this->parseData($this->env, $key, $default);
    }

    /**
     * 获取 $_SERVER 数组中项的值
     *
     * 常用于读取 REQUEST_URI、REMOTE_ADDR、REQUEST_TIME 等；键名大小写敏感，
     * 需与 $_SERVER 中的实际键一致（如 HTTPS 而非 https）。
     *
     * @param  string|null  $key  数组的键值
     * @param  mixed  $default  默认值
     * @return mixed 键不存在时返回 $default
     */
    public function server(?string $key = null, mixed $default = null): mixed
    {
        return $this->parseData($this->servers, $key, $default);
    }

    /**
     * 获取请求头
     *
     * 键名统一转为大写（如 CONTENT-TYPE、USER-AGENT、AUTHORIZATION）；
     * 优先使用 apache_request_headers()/getallheaders()，不可用时从 $_SERVER 重建。
     *
     * @param  string|null  $key  头名称（大写）
     * @param  mixed  $default  头不存在时的默认值
     * @return mixed 不传 $key 时返回全部请求头数组，否则返回指定头或 $default
     */
    public function headers(?string $key = null, mixed $default = null)
    {
        return $this->parseData($this->headers, $key, $default);
    }

    /**
     * 获取请求输入数据（请求体解析结果）中项目的值
     *
     * 数据来源为 getRawInput(false) 的解析结果，已合并 $_GET、$_POST 等，
     * 因此 JSON/XML 请求体也能取到；通常不含 $_FILES（仅在没有 POST 数据时合并）。
     *
     * @param  string|null  $key  数组的键值
     * @param  mixed  $default  默认值
     * @return mixed 键不存在时返回 $default
     */
    public function input(?string $key = null, mixed $default = null): mixed
    {
        return $this->parseData($this->input, $key, $default);
    }

    /**
     * 聚合全部来源的输入数据后取值
     *
     * 按 input + post + get + files 的顺序用 "+" 合并，同名键以靠前者为准
     * （input 优先，files 最低）；不传 $key 时返回合并后的完整数组。
     *
     * @param  string|null  $key  数组的键值
     * @param  mixed  $default  默认值
     * @return mixed 键不存在时返回 $default
     */
    public function all(?string $key = null, mixed $default = null)
    {
        return $this->parseData((array) $this->input + (array) $this->post + (array) $this->get + (array) $this->files, $key, $default);
    }

    /**
     * 从输入数据中截取指定键的子集
     *
     * 以 input() 的解析结果为数据源，仅保留 $keys 中列出的键（键不存在时被忽略），
     * 返回顺序与数据源一致；需要按来源（GET/POST/FILES）区分时请使用对应访问器。
     *
     * <code>
     *   $request = Request::instance();
     *
     *   // 仅从输入数据中获取 email。
     *   $email = $request->only('email');
     *
     *   // 仅从输入数据中获取 username 和 email。
     *   $input = $request->only(['username', 'email']);
     * </code>
     *
     * @param  array|string  $keys  要保留的键，支持单个字符串或键数组
     * @return array 仅包含已存在键的关联数组，可能为空数组
     */
    public function only(array|string $keys): array
    {
        return array_intersect_key(
            (array) $this->input(), array_flip((array) $keys)
        );
    }

    /**
     * 从输入数据中排除指定键
     *
     * 与 only() 互补，同样以 input() 的解析结果为数据源；$keys 中不存在的键
     * 不影响结果，剩余键保持原有顺序。
     *
     * <code>
     *   $request = Request::instance();
     *
     *   // 获取除 username 之外的所有输入数据。
     *   $input = $request->except('username');
     *
     *   // 获取除 username 和 email 之外的所有输入数据。
     *   $input = $request->except(['username', 'email']);
     * </code>
     *
     * @param  array|string  $keys  要排除的键，支持单个字符串或键数组
     * @return array 排除后的关联数组
     */
    public function except(array|string $keys): array
    {
        return array_diff_key(
            (array) $this->input(), array_flip((array) $keys)
        );
    }

    /**
     * 判断输入数据中是否存在指定键且值非空
     *
     * 逐个用 input($key) 取值：值为 null，或转成字符串并去掉空白后为空字符串即视为
     * 不存在（因此 false 与空字符串等价）；"0"、0 等非空值算存在。
     * 传数组时要求**全部**键都存在才返回 true，任一不满足立即返回 false。
     *
     * <code>
     *   $request = Request::instance();
     *
     *   // 请求信息中是否存在 id
     *   if ($request->has('id')) { echo 'The `id` exists.'; }
     *
     *   // 请求信息中 id 和 name 是否都存在？
     *   if ($request->has(['id', 'name'])) { // do stuff }
     * </code>
     *
     * @param  array|string  $keys  单个键或键数组
     * @return bool 全部键存在且非空返回 true，否则 false
     */
    public function has(array|string $keys)
    {
        foreach ((array) $keys as $key) {
            $value = $this->input($key);
            // 使用严格比较，避免 "0" 等合法值被误判为空；仅当值为 null 或去除空白后为空字符串才视为“不存在”
            if ($value === null || trim((string) $value) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * 获取请求的协议。例如HTTP/1.1
     *
     * 默认为HTTP/1.1。
     *
     * @return string
     */
    public function protocol()
    {
        return $this->protocol;
    }

    /**
     * 获取请求 scheme 。即http或https。
     *
     * 如果使用TRUE调用该方法，则将返回带有 :// 前缀的scheme
     *
     * @param  bool  $decorated  是否添加 :// 前缀.
     * @return string
     */
    public function scheme(bool $decorated = false)
    {
        $scheme = $this->secure() ? 'https' : 'http';

        return $decorated ? "$scheme://" : $scheme;
    }

    /**
     * 判断请求是否通过 HTTPS 访问
     *
     * 优先检查 $_SERVER['HTTPS'] 是否为 ON/on；否则仅在处于受信代理之后时，
     * 才参考 SSL_HTTPS 与 X_FORWARDED_PROTO 头，避免客户端直接伪造协议头。
     *
     * @return bool 通过 HTTPS 访问返回 true
     */
    public function secure()
    {
        if (in_array($this->server('HTTPS'), ['ON', 'on'])) {
            return true;
        }

        if (! $this->entrusted()) {
            return false;
        }

        return in_array($this->server('SSL_HTTPS'), ['ON', 'on']) || in_array($this->server('X_FORWARDED_PROTO'), ['HTTPS', 'https']);
    }

    /**
     * 获取请求方法。例如GET、POST。
     *
     * 可以重写此方法以支持非浏览器请求方法。例如PUT、DELETE。
     *
     * @return string
     */
    public function method()
    {
        return $this->method;
    }

    /**
     * 检查请求方法是否安全。即GET或HEAD。
     *
     * @return bool
     */
    public function safe()
    {
        return in_array($this->method(), ['GET', 'HEAD']);
    }

    /**
     * 获取当前请求的时间
     *
     *
     * @param  string  $format  返回时间格式 默认 'Y-m-d H:i:s'
     * @return string
     */
    public function time(string $format = ''): string
    {
        $format = (! empty($format) && is_string($format)) ? $format : 'Y-m-d H:i:s';

        return date($format, (int) $this->server('REQUEST_TIME'));
    }

    /**
     * 检查请求是否为AJAX请求。
     *
     * 依据请求头 X-Requested-With 是否等于 XMLHTTPREQUEST（忽略大小写）。
     *
     * @return bool 是 AJAX 请求返回 true，否则返回 false
     */
    public function isAjax(): bool
    {
        return isset($this->servers['HTTP_X_REQUESTED_WITH']) && strtoupper($this->servers['HTTP_X_REQUESTED_WITH']) == 'XMLHTTPREQUEST';
    }

    /**
     * 判断当前是否为 Pjax 请求
     *
     * 依据请求头 X-PJAX 是否存在且非空。$pjax 为 true 时把该头的原始值作为结果
     * 返回（常用于同时下发版本标识），为 false 时只返回布尔结果。
     *
     * @param  bool  $pjax  是否返回 X-PJAX 头的原始值
     * @return bool|string 非 Pjax 请求为 false；$pjax 为 true 时为头原始值
     */
    public function isPjax(bool $pjax = false): bool|string
    {
        $val = $this->server('HTTP_X_PJAX');

        if (empty($val)) {
            return false;
        }

        // $pjax 为 true 时返回原始头值（字符串），否则仅返回布尔判定
        return $pjax ? (string) $val : true;
    }

    /**
     * 判断当前请求是否为 POST
     *
     * @return bool REQUEST_METHOD 为 POST 时返回 true
     */
    public function isPost(): bool
    {
        return $this->method() == 'POST';
    }

    /**
     * 判断当前请求是否为 GET
     *
     * @return bool REQUEST_METHOD 为 GET（或未提供时默认值）返回 true
     */
    public function isGet(): bool
    {
        return $this->method() == 'GET';
    }

    /**
     * 获取来源页 URL（Referer）
     *
     * 读取 `HTTP_REFERER` 请求头（PHP 注入时即为该拼写），缺失时返回 $default。
     *
     * @param  mixed  $default  请求头缺失时返回的默认值
     * @return array|string|null 来源页 URL；缺失时返回 $default
     */
    public function referrer($default = null): array|string|null
    {
        return $this->server('HTTP_REFERER', $default);
    }

    /**
     * 重写默认URI解析器列表。
     *
     * 解析器数组的元素是$_SERVER数组中的键，具有可选的“modifier”函数来调整返回值。
     *
     * @param  array  $resolvers  URI解析器的优先级排序列表。
     * @return array
     */
    public function resolvers(array $resolvers = [])
    {
        if ($resolvers || empty($this->resolvers)) {
            $this->resolvers = $resolvers + [
                'PATH_INFO',
                'REQUEST_URI' => function ($uri) {
                    return parse_url($uri, PHP_URL_PATH);
                },
                'PHP_SELF',
                'REDIRECT_URL',
            ];
        }

        return $this->resolvers;
    }

    /**
     * 获取请求的URL. e.g. http://a.com/bar?q=foo
     *
     * 由协议、主机、端口、路径与查询串依次拼接而成，端口为默认值时省略。
     *
     * @return string 完整请求 URL
     */
    public function fullUrl(): string
    {
        return $this->scheme(true).$this->host().$this->port(true).$this->uri().$this->query(true);
    }

    /**
     * 获取请求的URL. e.g. http://a.com/bar?q=foo
     *
     * @return string
     */
    public function url()
    {
        return $this->scheme(true).$this->host().$this->port(true).$this->uri().$this->query(true);
    }

    /**
     * 获取请求的URI. e.g. /blog/item/10
     *
     * 排除查询字符串。
     *
     * @return string
     */
    public function uri()
    {
        return parse_url($this->server('REQUEST_URI'), PHP_URL_PATH);
    }

    /**
     * 获取请求查询字符串. e.g. q=search&foo=bar
     *
     * 默认情况下，问号被排除在外。要包含问号，请使用TRUE调用该方法。
     *
     * @param  bool  $decorated  添加 ? 前缀.
     * @return string
     */
    public function query(bool $decorated = false)
    {
        return $decorated ? "?$this->query" : $this->query;
    }

    /**
     * 获取请求的URI段。
     *
     * @param  array  $default  默认值
     * @return array
     */
    public function segments(array $default = [])
    {
        // uri() 可能为空（例如 CLI 环境）：此时回退到 $default。
        // $default 是数组，必须先拼成字符串，否则 trim(array, '/') 会抛 TypeError。
        $uri = $this->uri();

        if ($uri === null || $uri === '') {
            $uri = implode('/', $default);
        }

        $uri = trim($uri, '/');

        return $uri === '' ? [] : explode('/', $uri);
    }

    /**
     * 获取请求的特定URI段。
     *
     * 使用负索引以相反顺序检索段。
     *
     * @param  int  $index  A one-based segment index. 基于一的段索引。
     * @param  string|null  $default  默认值
     * @return string
     */
    public function segment(int $index, ?string $default = null)
    {
        $segments = $this->segments();

        if ($index < 0) {
            $index *= -1;
            $segments = array_reverse($segments);
        }

        return $this->parseData($segments, $index - 1, $default);
    }

    /**
     * 从HTTP接受标头中获取有序的值数组。
     *
     * @param  string  $terms  HTTP接受标头。
     * @param  string  $regex  用于解析标头的正则表达式。
     * @return array
     */
    protected function parse(string $terms, string $regex)
    {
        $result = [];

        foreach (array_reverse(explode(',', $terms)) as $part) {
            // 防御性裁剪：HTTP 请求头由客户端完全控制，限制单个片段长度，
            // 避免超长/恶意输入让正则引擎产生大量回溯而耗尽 CPU（ReDoS 加固）。
            if (strlen($part) > 512) {
                $part = substr($part, 0, 512);
            }
            if (preg_match("/{$regex}/", $part, $m)) {
                $quality = $m['quality'] ?? 1;
                $result[$m['term']] = $quality;
            }
        }

        arsort($result);

        return array_keys($result);
    }

    /**
     * 获取客户端首选的语言。
     *
     * 默认值 'en'.
     *
     * @param  string  $default  默认值
     * @return string
     */
    public function language(?string $default = null): mixed
    {
        return $this->parseData($this->languages(), 0, $default);
    }

    /**
     * 获取客户端首选语言的有序数组。
     *
     * @return array
     */
    public function languages()
    {
        return $this->parse(
            $this->server('HTTP_ACCEPT_LANGUAGE', 'en'),
            '(?P<term>[\w\-]+)(?:;q=(?P<quality>[0-9]+\.[0-9]+))?'
        );
    }

    /**
     * 获取客户端首选的媒体类型的有序数组。
     *
     * @return array
     */
    public function accepts()
    {
        return $this->parse(
            $this->server('HTTP_ACCEPT', 'text/html'),
            '(?P<term>[\w\-\+\/\*]+)(?:;q=(?P<quality>[0-9]+\.[0-9]+))?'
        );
    }

    /**
     * 获取客户端首选的媒体类型。
     *
     * 默认 'utf-8'.
     *
     * @param  string|null  $default  默认值
     * @return string
     */
    public function charset(?string $default = null): mixed
    {
        return $this->parseData($this->charsets(), 0, $default);
    }

    /**
     * 获取客户端首选的字符集的有序数组。
     *
     * @return array
     */
    public function charsets()
    {
        return $this->parse(
            $this->server('HTTP_ACCEPT_CHARSET', 'utf-8'), '(?P<term>[\w\-\*]+)(?:;q=(?P<quality>[0-9]+\.[0-9]+))?'
        );
    }

    /**
     * 获取用户代理. e.g. Mozilla/5.0 (Macintosh; ...)
     *
     * @param  string|null  $default  默认值
     * @return string
     */
    public function userAgent(?string $default = null)
    {
        return $this->userAgent ?? $default;
    }

    /**
     * 设置一个或多个受信任的代理服务器。
     *
     * 默认情况下，所有代理服务器都是受信任的。当请求客户端IP地址时，使用此方法只信任有限的一组代理服务器。
     *
     * 此方法不是累积的。
     *
     * @param  mixed  $proxies  受信任代理的IP地址或受信任代理阵列。
     * @return $this
     */
    public function setProxies($proxies)
    {
        $this->proxies = (array) $proxies;

        return $this;
    }

    /**
     * 检查当前请求是否来自“受信任的代理服务器”。
     *
     * 仅当显式通过 setProxies() 配置了受信代理列表，且当前直接连接的 REMOTE_ADDR
     * 命中其中一项时，才认为转发头（X-Forwarded-For / Client-IP 等）可信。
     *
     * 默认（未配置 proxies）返回 false，即不信任任何转发头，
     * 以防止客户端伪造 X-Forwarded-For / Client-IP 进行 IP 欺骗。
     *
     * @return bool
     */
    public function entrusted()
    {
        return ! empty($this->proxies)
            && isset($this->servers['REMOTE_ADDR'])
            && in_array($this->servers['REMOTE_ADDR'], $this->proxies, true);
    }

    /**
     * 解析web服务器的名称。
     *
     * 解析顺序是请求的“host”标头，然后是“server name”指令，然后是服务器IP地址。
     * 端口号（如果存在）将被剥离。
     *
     * @param  string|null  $default  默认值
     * @return string
     */
    public function host(?string $default = null)
    {
        $keys = ['HTTP_HOST', 'SERVER_NAME', 'SERVER_ADDR'];

        if (
            $this->entrusted() &&
            $host = $this->server('X_FORWARDED_HOST')
        ) {
            $host = explode(',', $host);
            $host = trim($host[count($host) - 1]);
        } else {
            foreach ($keys as $key) {
                if (isset($this->servers[$key])) {
                    $host = $this->servers[$key];
                    break;
                }
            }
        }

        return isset($host) ? preg_replace('/:\d+$/', '', $host) : $default;
    }

    /**
     * 获取当前包含协议的域名
     *
     * 结果形如 https://example.com，不含端口与路径。
     *
     * @return string 协议加主机名
     */
    public function domain(): string
    {
        return $this->scheme().'://'.$this->host();
    }

    /**
     * 获取客户端 IP 地址
     *
     * 仅当通过 setProxies() 配置了受信代理、且直连的 REMOTE_ADDR 命中其中一项时，
     * 才采信 X-Forwarded-For / X-Forwarded / Forwarded-For / Forwarded 头（多个地址
     * 取逗号分隔后的最后一个），$trusted 为 true 时才会额外采信 Client-IP 头；
     * 未经受信代理时这些头一律忽略，以防客户端伪造。
     * 候选项需能通过 FILTER_VALIDATE_IP 且不属于私有/保留网段，全部不合法时回退到
     * REMOTE_ADDR，仍然没有则返回 0.0.0.0。
     *
     * @param  bool  $trusted  是否采信 Client-IP 头（仍要求处于受信代理之后）
     * @return string 客户端 IP 地址
     */
    public function ip($trusted = true)
    {
        $keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED'];

        $ips = [];

        // 仅当确实处于受信代理之后，才接受客户端可控的 Client-IP 头，避免 IP 伪造
        if ($trusted && $this->entrusted() && isset($this->servers['HTTP_CLIENT_IP'])) {
            $ips[] = $this->servers['HTTP_CLIENT_IP'];
        }

        foreach ($keys as $key) {
            if (isset($this->servers[$key])) {
                if ($this->entrusted()) {
                    $parts = explode(',', $this->servers[$key]);
                    $ips[] = trim($parts[count($parts) - 1]);
                }
            }
        }

        foreach ($ips as $ip) {
            if (
                filter_var($ip, FILTER_VALIDATE_IP,
                    FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 |
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
            ) {
                return $ip;
            }
        }

        return $this->server('REMOTE_ADDR', '0.0.0.0');
    }

    /**
     * 检测是否使用手机访问
     *
     * 依次判断 HTTP_VIA 是否含 wap、HTTP_ACCEPT 是否声明 WAP、是否带
     * X-WAP-PROFILE/HTTP_PROFILE，最后用 User-Agent 正则匹配常见移动端标识。
     * 任一条命中即视为移动端，不做 UA 真伪校验。
     *
     * @return bool 判定为移动端返回 true，否则返回 false
     */
    public function isMobile(): bool
    {
        if ($this->server('HTTP_VIA') && stristr($this->server('HTTP_VIA'), 'wap')) {
            return true;
        } elseif ($this->server('HTTP_ACCEPT') && strpos(strtoupper($this->server('HTTP_ACCEPT')), 'VND.WAP.WML')) {
            return true;
        } elseif ($this->server('HTTP_X_WAP_PROFILE') || $this->server('HTTP_PROFILE')) {
            return true;
        } elseif ($this->server('HTTP_USER_AGENT') && preg_match('/(Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|blackberry|configuration\/cldc|hp |hp-|htc |htc_|htc-|iemobile|kindle|midp|mmp|motorola|mobile|nokia|opera mini|opera |Googlebot-Mobile|YahooSeeker\/M1A1-R2D2|android|iphone|ipod|mobi|palm|palmos|pocket|portalmmm|ppc;|smartphone|sonyericsson|sqh|spv|symbian|treo|up.browser|up.link|vodafone|windows ce|xda |xda_)/i', $this->server('HTTP_USER_AGENT'))) {
            return true;
        }

        return false;
    }

    /**
     * 获取请求的端口号. e.g. 80
     *
     * 如果使用TRUE调用该方法，则端口号（如果为80或443）将被省略，否则将以冒号作为前缀。
     * 如果未定义SERVER_port，则默认为端口80。
     *
     * @param  bool  $decorated  前缀为：
     * @return string
     */
    public function port(bool $decorated = false): string|int
    {
        $port = $this->port ?? (int) ($_SERVER['SERVER_PORT'] ?? ($_SERVER['X_FORWARDED_PORT'] ?? 80));

        return $decorated ? (in_array((int) $port, [80, 443], true) ? '' : ":{$port}") : (int) $port;
    }
}
