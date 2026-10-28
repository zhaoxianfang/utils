<?php

declare(strict_types=1);

// +---------------------------------------------------------------------
// | Curl
// +---------------------------------------------------------------------
// | Author     | ZhaoXianFang <1748331509@qq.com>
// +---------------------------------------------------------------------
// | 版权       | http://www.weisifang.com
// +---------------------------------------------------------------------
// | Date       | 2019-07-30
// +---------------------------------------------------------------------

namespace zxf\Utils\Http;

use Closure;
use CURLFile;
use CurlHandle;
use Exception;
use zxf\Utils\Data\Collection;

/**
 * HTTP 请求客户端（基于 cURL 封装）
 *
 * 支持 GET/POST、表单与文件上传、下载与断点续传、Cookie 管理、自定义请求头、
 * 请求前/后回调以及调试输出；默认开启 SSL 证书校验、超时与跳转次数限制。
 * 通过 responseObject 控制返回 Curl 实例还是直接返回响应结果。
 *
 * @package zxf\Utils\Http
 */
class Curl
{
    private ?\CurlHandle $ch = null; // curl 句柄

    private $debugStream = null; // 调试输出流资源（CURLOPT_STDERR），需显式关闭以防句柄泄漏

    private array|string|null $httpParams = null;

    // cookie文件路径地址
    private string $cookieFile = '';

    private string $cookieJar = '';

    // 发起请求后是否响应Curl对象; true:返回Curl对象,false:直接返回请求结果
    private bool $responseObject = false;

    // 请求前回调函数
    private ?Closure $beforeFunc = null;

    // 请求后回调函数
    private ?Closure $afterFunc = null;

    // 响应内容
    private array $respObjData = [];

    private array $defaultParams = [
        CURLOPT_RETURNTRANSFER => true, // 返回响应内容，而不是直接输出
        CURLOPT_HEADER => true, // 返回头部信息
        CURLOPT_SSL_VERIFYPEER => true, // 默认启用 SSL 证书验证（安全）
        CURLOPT_SSL_VERIFYHOST => 2, // 验证证书中的主机名
        CURLOPT_TIMEOUT => 10, // 设置超时限制防止死循环
        CURLOPT_FOLLOWLOCATION => true, // 启用时会将服务器返回的 "Location:" 递归处理
        CURLOPT_AUTOREFERER => true, // 自动设置 Referer
        CURLOPT_MAXREDIRS => 5, // 最大跳转次数
        CURLOPT_CONNECTTIMEOUT => 10, // 设置连接等待时间
    ];

    // 请求头信息,会自动处理成 key => value 的形式，避免设置重复
    private array $headers = [
        // 'Content-type' => "application/x-www-form-urlencoded;charset='utf-8'",
        // 'Content-type' => "application/json;charset='utf-8'",
    ];

    /**
     * @var static|null 单例实例
     */
    protected static ?object $instance = null;

    /**
     * @param  array  $config  给curl进行默认配置
     *                         eg: [
     *                         CURLOPT_TIMEOUT => 10,
     *                         CURLOPT_RETURNTRANSFER=>true,
     *                         CURLOPT_HTTPHEADER => [
     *                         'Accept: application/json',
     *                         'Authorization: Bearer YOUR_API_TOKEN'
     *                         ],
     *                         CURLOPT_COOKIEJAR => 'cookie.txt',
     *                         CURLOPT_COOKIEFILE => 'cookie.txt',
     *                         ...
     *                         ]
     * @param  bool  $responseObject  发起请求后是否响应对象; true:返回Curl对象,false:直接返回请求结果
     *                                true:返回Curl对象, 后面可以再次调用
     *                                ->getStatusCode()、->getBody()、->isSuccessful()、->getHeader()、->hasRedirect()、->getError()、->getResp()等方法
     *                                false:直接返回请求结果，直接返回html原始网页、json数组、Collection集合等
     *
     * @throws Exception
     */
    public function __construct(array $config = [], bool $responseObject = false)
    {
        if (! function_exists('curl_init')) {
            throw new Exception('不支持CURL功能.');
        }
        $this->responseObject = $responseObject;
        $this->ch = null;

        $this->defaultParams[CURLINFO_HEADER_OUT] = true;
        if ($config) {
            $this->defaultParams = array_merge($this->defaultParams, $config);
        }

        $this->initCurl();
    }

    /**
     * 初始化 cURL 句柄并应用默认选项
     *
     * 仅在句柄为空时执行 curl_init() 并写入 defaultParams，避免重复初始化覆盖
     * 已设置的选项；每次调用都会重新应用 Cookie 读取/保存路径（若已通过
     * setCookieFile() 配置）并清空上一次的响应数据。
     *
     * @return void
     */
    private function initCurl()
    {
        if (empty($this->ch)) {
            $this->ch = curl_init();
            curl_setopt_array($this->ch, $this->defaultParams);
        }
        // 使用cookie文件
        ! empty($this->cookieJar) && curl_setopt($this->ch, CURLOPT_COOKIEJAR, $this->cookieJar);
        ! empty($this->cookieFile) && curl_setopt($this->ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        $this->respObjData = [];
    }

    /**
     * 获取单例实例
     *
     * 仅在首次调用时按 $options、$responseObject 创建实例，后续调用直接返回同一
     * 对象（此时两个参数被忽略）；需要不同配置时请直接 new static(...)。
     *
     * @param  array  $options  首次创建时生效的 cURL 默认配置
     * @param  bool  $responseObject  首次创建时生效，为 true 时请求返回当前对象
     * @return static 共享实例
     *
     * @throws Exception
     */
    public static function instance(array $options = [], bool $responseObject = false): static
    {
        if (self::$instance === null) {
            self::$instance = new static($options, $responseObject);
        }

        return self::$instance;
    }

    /**
     * 设置请求头
     *
     * 支持两种写法并统一归一化为 key => value：关联数组，或索引数组中形如
     * "Key: value" 的字符串。归一化后按 $isAppend 决定合并还是覆盖，再拼成
     * cURL 需要的 "Key: value" 列表写入 CURLOPT_HTTPHEADER（立即生效）。
     *
     * @param  array  $header  请求头，如 ['Accept' => 'application/json'] 或 ['Accept: application/json']
     * @param  bool  $isAppend  为 false 时丢弃已有请求头只保留本次设置，为 true 时合并（同键覆盖）
     * @param  bool  $setLength  为 true 时按 $httpParams 计算并写入 Content-Length；需先调用 setParams()
     *
     * @example $http->setHeader(['Content-type' => 'application/json;charset="utf-8"', 'Accept' => 'application/json'])
     *          $http->setHeader(['Content-type: application/json;charset="utf-8"', 'Accept: application/json'], false)
     *
     * @return $this
     *
     * @throws Exception
     */
    public function setHeader(array $header, bool $isAppend = true, bool $setLength = false)
    {
        $this->initCurl();

        $headData = [];
        foreach ($header as $key => $head) {
            if (is_numeric($key) && is_string($head) && str_contains($head, ':')) {
                [$key, $head] = explode(':', $head, 2);
                $headData[trim($key)] = trim($head);
            } elseif (is_string($key) && is_string($head)) {
                $headData[trim($key)] = trim($head);
            }
        }

        $this->headers = (empty($this->headers) || ! $isAppend) ? $headData : array_merge($this->headers, $headData);

        if ($setLength) {
            // 设置 Length
            $length = empty($this->httpParams) ? 0 : (is_string($this->httpParams) ? strlen($this->httpParams) : strlen(json_encode($this->httpParams)));
            $this->headers['Content-Length'] = $length;
        }
        // 把 $this->headers 处理成 ['key: value'] 的形式给curl使用
        $curlHeader = array_map(fn ($k, $v) => "$k: $v", array_keys($this->headers), $this->headers);

        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $curlHeader);
        curl_setopt($this->ch, CURLINFO_HEADER_OUT, true);

        return $this;
    }

    /**
     * 设置 SSL 证书校验策略
     *
     * 生产环境强烈建议保持校验开启（默认开启）。设置会同时写入 defaultParams
     * （句柄重建时仍然生效）并立即应用到当前句柄；$verify 为 false 时会强制
     * 把 CURLOPT_SSL_VERIFYHOST 置 0，与 cURL 的要求保持一致。
     *
     * @param  bool  $verify  是否校验服务端证书
     * @param  int  $verifyHost  校验级别：0=不校验, 1=证书存在即可(不安全), 2=证书与主机名匹配(推荐)
     * @return self
     */
    public function verifySsl(bool $verify = true, int $verifyHost = 2): self
    {
        $this->defaultParams[CURLOPT_SSL_VERIFYPEER] = $verify;
        $this->defaultParams[CURLOPT_SSL_VERIFYHOST] = $verify ? $verifyHost : 0;

        if ($this->ch instanceof \CurlHandle) {
            curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, $verify);
            curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, $verify ? $verifyHost : 0);
        }

        return $this;
    }

    /**
     * 清空句柄上的请求头选项
     *
     * 仅把 CURLOPT_HTTPHEADER 置为空数组，不会清空内部保存的 $this->headers；
     * 之后再调用 setHeader() 且 $isAppend 为 true 时，旧请求头会被重新合并下发。
     *
     * @return $this
     *
     * @throws Exception
     */
    public function cleanHeader()
    {
        $this->initCurl();
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, []);

        return $this;
    }

    /**
     * 设置 Cookie 文件的读取/写入路径
     *
     * 非空路径会先通过 create_dir_or_filepath() 创建目录与文件；$cookieFile 用于
     * 发送时读取 Cookie（CURLOPT_COOKIEFILE），$cookieJarFile 用于保存响应中的
     * Cookie（CURLOPT_COOKIEJAR）。仅记录到属性，需在下一次 initCurl()（即下次请求）
     * 时才会应用到句柄。
     *
     * @param  string  $cookieFile  Cookie 读取文件路径，空字符串表示不读取
     * @param  string  $cookieJarFile  Cookie 保存文件路径，空字符串表示不保存
     * @return $this
     */
    public function setCookieFile(string $cookieFile = '', string $cookieJarFile = '')
    {
        $cookieFile && create_dir_or_filepath($cookieFile);
        $cookieJarFile && create_dir_or_filepath($cookieJarFile);
        $this->cookieFile = $cookieFile;
        $this->cookieJar = $cookieJarFile;

        return $this;
    }

    /**
     * 以字符串形式设置请求携带的 Cookie
     *
     * 直接写入 CURLOPT_COOKIE，格式为 "name1=value1; name2=value2"；
     * 与 setCookieFile() 配置的 Cookie 文件可以并存，两者内容会一并发送；
     * 连续调用会覆盖上一次设置，只保留最后一次的字符串。
     *
     * @param  string  $cookieString  Cookie 字符串，如 "sid=abc; token=xyz"
     * @return $this
     */
    public function setCookieString(string $cookieString)
    {
        curl_setopt($this->ch, CURLOPT_COOKIE, $cookieString);

        return $this;
    }

    /**
     * 设置请求超时时间（秒）
     *
     * 对应 CURLOPT_TIMEOUT，含连接与传输总耗时。传入小于等于 0 的值时会被
     * 静默修正为 5 秒，避免设置成 0 导致无限等待。
     *
     * @param  int  $time  超时秒数，非正数按 5 处理
     * @return $this
     *
     * @throws Exception
     */
    public function setTimeout(int $time = 3)
    {
        $this->initCurl();
        // 不能小于等于0
        if ($time <= 0) {
            $time = 5;
        }
        // 只需要设置一个秒的数量就可以
        curl_setopt($this->ch, CURLOPT_TIMEOUT, $time);

        return $this;
    }

    /**
     * 设置 HTTP 代理地址
     *
     * 对应 CURLOPT_PROXY，可写 "127.0.0.1:8080" 或 "socks5://127.0.0.1:1080"；
     * 传入空字符串时不设置，保留原有代理配置。
     *
     * @param  string  $proxy  代理地址
     * @return $this
     *
     * @throws Exception
     */
    public function setProxy(string $proxy)
    {
        $this->initCurl();
        if ($proxy) {
            curl_setopt($this->ch, CURLOPT_PROXY, $proxy);
        }

        return $this;
    }

    /**
     * 设置代理端口
     *
     * 对应 CURLOPT_PROXYPORT；若代理地址中已带端口（setProxy('host:port')），
     * 一般无需再调用本方法。
     *
     * @param  int  $port  代理端口
     * @return $this
     *
     * @throws Exception
     */
    public function setProxyPort(int $port)
    {
        $this->initCurl();
        curl_setopt($this->ch, CURLOPT_PROXYPORT, $port);

        return $this;
    }

    /**
     * 设置 Referer 来源页
     *
     * 对应 CURLOPT_REFERER；传入空字符串时不设置（不会清空已有值）。
     * 注意 defaultParams 中已开启 CURLOPT_AUTOREFERER，跟随跳转时会自动改写 Referer。
     *
     * @param  string  $referer  来源页面地址
     * @return $this
     *
     * @throws Exception
     */
    public function setReferer(string $referer = '')
    {
        $this->initCurl();
        if (! empty($referer)) {
            curl_setopt($this->ch, CURLOPT_REFERER, $referer);
        }

        return $this;
    }

    /**
     * 设置 User-Agent
     *
     * 对应 CURLOPT_USERAGENT；传入空字符串时不设置。
     * 注意：get()/post()/put()/delete()/patch() 在发起请求时会用 randUserAgent()
     * 覆盖此处设置的值，需要固定 UA 时应改用 setHeader(['User-Agent' => ...])。
     *
     * @param  string  $agent  用户代理字符串
     * @return $this
     *
     * @throws Exception
     */
    public function setUserAgent(string $agent = '')
    {
        $this->initCurl();
        if ($agent) {
            // 模拟用户使用的浏览器
            curl_setopt($this->ch, CURLOPT_USERAGENT, $agent);
        }

        return $this;
    }

    /**
     * 开启文件调试
     *
     * 打开 CURLOPT_VERBOSE 并把调试输出重定向到指定文件，文件（及其目录）
     * 不存在时自动创建。写入流句柄被保存，供 closeDebug() 与析构函数关闭，
     * 避免句柄泄漏；重复调用会用新模式重新打开同一文件。
     *
     * @param  string  $debugFile  调试输出文件路径
     * @return $this
     */
    public function debug(string $debugFile)
    {
        create_dir_or_filepath($debugFile);
        // 启用调试输出
        curl_setopt($this->ch, CURLOPT_VERBOSE, true);
        // 将调试信息写入文件（保存句柄以便后续关闭，避免句柄泄漏）
        $this->debugStream = fopen($debugFile, 'w+');
        curl_setopt($this->ch, CURLOPT_STDERR, $this->debugStream);

        return $this;
    }

    /**
     * 关闭调试
     *
     * 关闭 CURLOPT_VERBOSE，恢复 CURLOPT_RETURNTRANSFER（使响应以返回值形式
     * 返回而非直接输出），并关闭已打开的调试流。未开启调试时只做幂等处理。
     *
     * @return $this
     */
    public function closeDebug()
    {
        // 关闭调试模式
        curl_setopt($this->ch, CURLOPT_VERBOSE, false);

        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);

        if ($this->debugStream !== null) {
            fclose($this->debugStream);
            $this->debugStream = null;
        }

        return $this;
    }

    /**
     * 析构时释放 curl 句柄与调试流，避免资源泄漏
     */
    public function __destruct()
    {
        if ($this->debugStream !== null) {
            fclose($this->debugStream);
            $this->debugStream = null;
        }
        if ($this->ch instanceof \CurlHandle) {
            // PHP 8.0 起 curl_close() 已无实际作用、8.5 起被弃用，直接断开引用交由 GC 回收
            $this->ch = null;
        }
    }

    /**
     * 切换请求后的返回形式
     *
     * 为 true 时 get()/post() 等方法返回 $this，可继续调用 getStatusCode()、
     * getBody()、getHeader()、getResp()、isSuccessful() 等读取响应；为 false
     * （默认）时直接返回解析后的响应结果。
     *
     * @param  bool  $flag  是否返回对象
     * @return $this
     */
    public function respObj(bool $flag = true)
    {
        $this->responseObject = $flag;

        return $this;
    }

    /**
     * 复制当前 cURL 句柄
     *
     * 返回一份选项相同的独立句柄，可用于并发请求；复制出的句柄由调用方负责
     * 释放（PHP 8.0 起 curl_close() 已无实际作用，断开引用交由 GC 回收即可）。
     * 当前句柄未初始化时会先执行 initCurl()。
     *
     * @return CurlHandle|false 复制失败时返回 false
     *
     * @throws Exception
     */
    public function copyCurl()
    {
        $this->initCurl();

        return curl_copy_handle($this->ch);
    }

    /**
     * 重置句柄上已设置的全部选项
     *
     * 调用 curl_reset() 后，构造函数与 setHeader()/setTimeout() 等设置过的原生
     * cURL 选项（含 defaultParams 中的默认项）都会被清除；本类属性（请求头、
     * Cookie 路径、请求参数）不受影响。重置后继续请求需重新设置相关选项。
     *
     * @return $this
     */
    public function reset()
    {
        $this->initCurl();
        curl_reset($this->ch);

        return $this;
    }

    /**
     * 设置 curl_exec 返回的内容是否包含响应头
     *
     * 对应 CURLOPT_HEADER；开启后 curl_exec 的结果会在响应体前拼接响应头，
     * parserResponse() 会按 CURLINFO_HEADER_SIZE 将其切分，因此 getBody()
     * 拿到的仍是纯响应体。
     *
     * @param  mixed  $show  1/true 显示响应头，0/false 不显示
     * @return $this
     *
     * @throws Exception
     */
    public function showResponseHeader($show)
    {
        $this->initCurl();
        curl_setopt($this->ch, CURLOPT_HEADER, $show);

        return $this;
    }

    /**
     * 设置本次请求的参数（GET 查询串或 POST 请求体）
     *
     * 按 $data_type 转换后暂存到 $httpParams，由 get()/post()/put() 等方法在发起
     * 请求时读取；重复调用会整体覆盖上一次的参数。
     *   - json：json_encode 成 JSON 字符串，需配合 Content-type: application/json；
     *   - array：经 obj2Arr() 把对象转为数组后暂存（实际交给 cURL 处理）；
     *   - string（默认）：http_build_query 生成 a=1&b=2，或按 $excludeZhCN 走不编码值的实现。
     *
     * @param  array  $params  请求参数，支持一维/多维数组
     * @param  string  $data_type  参数类型：array|json|string
     * @param  bool  $excludeZhCN  仅 $data_type=string 时生效；true 时值不做 URL 编码（键仍编码），便于直接阅读中文
     * @return $this
     *
     * @example $http->setParams(['abc' => '123'], 'string');
     *
     * @throws Exception
     */
    public function setParams(array $params, string $data_type = 'string', bool $excludeZhCN = false)
    {
        $this->initCurl();
        // 支持json数据数据提交
        if ($data_type == 'json') {
            $params = json_encode($params);
        } elseif ($data_type == 'array') {
            $params = obj2Arr($params);
        } else {
            $params = $excludeZhCN ? $this->custom_http_build_query($params) : http_build_query($params, '', '&');
        }
        $this->httpParams = $params;

        return $this;
    }

    /**
     * 生成不对值做 URL 编码的查询串
     *
     * 用于替代 http_build_query 的中文转义行为：本实现输出 name=张三&age=25，
     * 而 http_build_query 会得到 name=%E5%BC%A0%E4%B8%89&age=25。
     * 嵌套数组会递归展开为 parent[key]=value；键默认编码、值默认原样保留。
     *
     * @param  array  $data  待处理的参数
     * @param  string  $prefix  当前层级的键前缀，递归时内部使用
     * @param  string  $arg_separator  参数之间的分隔符
     * @param  bool  $encode_keys  是否对键做 rawurlencode
     * @param  bool  $encode_values  是否对值做 rawurlencode
     * @param  string  $encoding  键编码时按此字符集做编码转换
     * @return string 拼接后的查询串
     */
    private function custom_http_build_query(array $data, string $prefix = '', string $arg_separator = '&', bool $encode_keys = true, bool $encode_values = false, string $encoding = 'UTF-8'): bool|string
    {
        $query_parts = [];

        foreach ($data as $key => $value) {
            // 处理键
            $encoded_key = $encode_keys ? rawurlencode(mb_convert_encoding((string) $key, $encoding, 'auto')) : (string) $key;

            // 如果有前缀，添加前缀
            if ($prefix !== '') {
                $encoded_key = $prefix.'['.$encoded_key.']';
            }

            // 处理值
            if (is_array($value)) {
                // 递归处理嵌套数组
                $nested_query = $this->custom_http_build_query($value, $encoded_key, $arg_separator, $encode_keys, $encode_values, $encoding);
                if ($nested_query !== false) {
                    $query_parts[] = $nested_query;
                }
            } else {
                // 对值进行编码或保持原样
                if ($encode_values) {
                    $encoded_value = rawurlencode(mb_convert_encoding((string) $value, $encoding, 'auto'));
                } else {
                    $encoded_value = (string) $value;
                }
                $query_parts[] = $encoded_key.'='.$encoded_value;
            }
        }

        return implode($arg_separator, $query_parts);
    }

    /**
     * 设置证书路径
     *
     * 将 CA 证书文件（PEM 格式）写入 CURLOPT_CAINFO，用于校验 HTTPS 服务端
     * 证书；调用前会先初始化 cURL 句柄。文件是否存在不在本方法校验范围内，
     * 由 cURL 在请求时自行报错。
     *
     * @param  string  $file  CA 证书文件路径
     * @return Curl
     *
     * @throws Exception
     */
    public function setCaPath(string $file)
    {
        $this->initCurl();
        curl_setopt($this->ch, CURLOPT_CAINFO, $file);

        return $this;
    }

    /**
     * 获取cURL版本数组
     *
     * @return array|false
     */
    public function version()
    {
        return curl_version();
    }

    /**
     * 通过闭包注入原生 cURL 操作
     *
     * 闭包签名约定为 function (Curl $http, ?CurlHandle $ch) {}，可直接用
     * curl_setopt() 设置本类未封装的原生选项（如 CURLOPT_SSLCERTTYPE）。
     * 本方法不会调用 initCurl()，请求结束或 reset() 之后句柄为 null，此时闭包
     * 收到的第二个参数是 null；需要有效句柄时请先调用任意 setter（如 setHeader）。
     *
     * @param  Closure  $func  接收 (当前实例, curl 句柄) 的回调
     * @return $this
     *
     * @example $http->inject(function ($http, $ch) {
     *              curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
     *          });
     */
    public function inject(Closure $func)
    {
        $func($this, $this->ch);

        return $this;
    }

    /**
     * 在请求之前调用闭包函数
     *
     * 仅登记回调，实际在每次发送请求前执行，可用于统一追加签名头等操作。
     * 重复调用会覆盖上一个回调；回调接收 (当前实例, curl 句柄) 两个参数。
     *
     * @param  Closure  $func  请求前执行的回调
     * @return $this
     *
     * @example $http->before(function($http, $ch){
     *          // ...
     *          })
     */
    public function before(Closure $func)
    {
        $this->beforeFunc = $func;

        return $this;
    }

    /**
     * 在请求之后调用闭包函数
     *
     * 仅登记回调，实际在每次请求响应解析完成后执行，可用于记录日志或统一
     * 改写响应。重复调用会覆盖上一个回调。回调约定接收 (当前实例, curl 句柄,
     * 响应 body) 三个参数，此时句柄尚未关闭。
     *
     * @param  Closure  $func  请求后执行的回调
     * @return $this
     *
     * @example $http->after(function($http, $ch, $response){
     *          // ...
     *          })
     */
    public function after(Closure $func)
    {
        $this->afterFunc = $func;

        return $this;
    }

    /**
     * 模拟 GET 请求
     *
     * 会把 setParams() 设置的参数拼接到 URL 尾部（URL 已含 ? 时用 & 连接，否则用
     * ? 连接），随后强制启用压缩解码、随机设置浏览器 User-Agent 并发送请求。
     *
     * @param  string  $url  请求地址
     * @param  string  $data_type  返回数据类型：json(数组)|string(json 字符串)|collection(集合)
     * @return mixed 默认返回解析后的响应体；$responseObject 为 true 时返回当前实例；
     *               传输层出错时直接返回错误数组（见 getError()）
     *
     * Examples:
     * ```
     * $http->get('http://api.example.com/?a=123&b=456', 'json');
     * ```
     *
     * @throws Exception
     */
    public function get(string $url, string $data_type = 'json')
    {
        $this->initCurl();
        // 设置get参数
        if (! empty($this->httpParams)) {
            // 根据 URL 是否已含查询字符串，选择使用 '?' 或 '&' 作为连接符，避免拼接出错误格式
            $glue = str_contains($url, '?') ? '&' : '?';
            if (is_array($this->httpParams)) {
                $paramString = $glue . http_build_query($this->httpParams);
            } else {
                $paramString = $glue . $this->httpParams;
            }
            $url .= $paramString;
        }
        curl_setopt($this->ch, CURLOPT_ENCODING, '');
        curl_setopt($this->ch, CURLINFO_HEADER_OUT, true);

        // 设置请求方式为 GET
        curl_setopt($this->ch, CURLOPT_HTTPGET, true);
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        curl_setopt($this->ch, CURLOPT_USERAGENT, $this->randUserAgent());

        return $this->run($data_type);
    }

    /**
     * 模拟 POST 请求
     *
     * 以 CURLOPT_POST 发送；请求体取自 setParams() 设置的 $httpParams，未设置则
     * 发送空 body。文件上传请改用 upload()，它会把文件包成 CURLFile 后再调用本方法。
     *
     * @param  string  $url  请求地址
     * @param  string  $data_type  返回数据类型：json(数组)|string(json 字符串)|collection(集合)
     * @return mixed 默认返回解析后的响应体；$responseObject 为 true 时返回当前实例；
     *               传输层出错时直接返回错误数组（见 getError()）
     *
     * Examples:
     * ```
     * $http->setParams(['a' => 123], 'json')->post('http://api.example.com/', 'json');
     * ```
     *
     * @throws Exception
     */
    public function post(string $url, string $data_type = 'json')
    {
        $this->initCurl();

        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_POST, true);
        curl_setopt($this->ch, CURLOPT_USERAGENT, $this->randUserAgent());

        // 设置post body
        if (! empty($this->httpParams)) {
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $this->httpParams);
        }

        return $this->run($data_type);
    }

    /**
     * 发送 PUT 请求
     *
     * 请求体取自此前 setParams() 设置的 $httpParams（未设置则不发送 body）；
     * 每次请求都会把 User-Agent 替换为随机的主流浏览器 UA。
     * 注意 $url 未做类型声明，内部直接交给 CURLOPT_URL。
     *
     * @param  string  $url  请求地址
     * @param  string  $data_type  返回数据类型：json|string|collection，语义与 get() 一致
     * @return mixed 默认返回解析后的响应体；$responseObject 为 true 时返回当前对象
     */
    public function put($url, $data_type = 'json')
    {
        $this->initCurl();
        curl_setopt($this->ch, CURLOPT_URL, $url); // 设置请求的URL
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'PUT'); // 设置请求方式
        curl_setopt($this->ch, CURLOPT_USERAGENT, $this->randUserAgent());
        // 设置post body
        if (! empty($this->httpParams)) {
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $this->httpParams);
        }

        return $this->run($data_type);
    }

    /**
     * 发送 DELETE 请求
     *
     * 通过 CURLOPT_CUSTOMREQUEST 指定 DELETE 方法；若此前 setParams() 设置过参数，
     * 会作为请求体发送（部分服务端会忽略 DELETE 的 body）。
     *
     * @param  string  $url  请求地址
     * @param  string  $data_type  返回数据类型：json|string|collection，语义与 get() 一致
     * @return mixed 默认返回解析后的响应体；$responseObject 为 true 时返回当前对象
     */
    public function delete($url, $data_type = 'json')
    {
        $this->initCurl();
        // $data = json_encode($data);
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($this->ch, CURLOPT_USERAGENT, $this->randUserAgent());
        // curl_setopt($this->ch, CURLOPT_POSTFIELDS, $data);
        if (! empty($this->httpParams)) {
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $this->httpParams);
        }

        return $this->run($data_type);
    }

    /**
     * 发送 PATCH 请求
     *
     * 通过 CURLOPT_CUSTOMREQUEST 指定 PATCH 方法；若此前 setParams() 设置过参数，
     * 会作为请求体发送。常用于资源的局部更新。
     *
     * @param  string  $url  请求地址
     * @param  string  $data_type  返回数据类型：json|string|collection，语义与 get() 一致
     * @return mixed 默认返回解析后的响应体；$responseObject 为 true 时返回当前对象
     */
    public function patch($url, $data_type = 'json')
    {
        $this->initCurl();
        // $data = json_encode($data);
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($this->ch, CURLOPT_USERAGENT, $this->randUserAgent());
        // curl_setopt($this->ch, CURLOPT_POSTFIELDS, $data); //20170611修改接口，用/id的方式传递，直接写在url中了
        if (! empty($this->httpParams)) {
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $this->httpParams);
        }

        return $this->run($data_type);
    }

    /**
     * 上传文件
     *
     * 先把文件包装成 CURLFile（PHP < 5.5 回退为 @路径 写法），与 $params 合并后
     * 交给 setParams()，再以 POST 方式提交到 $url；返回结果类型与 post() 一致。
     *
     * @param  string  $url  上传接口地址
     * @param  string  $filePath  本地待上传文件的路径，必须存在且可读
     * @param  string  $name  表单文件字段名，如 file；为空时默认 media
     * @param  array  $params  随文件一起提交的附加字段，如 description
     * @return mixed 解析后的响应体，或 $responseObject 为 true 时的当前实例
     *
     * @throws Exception 文件不存在或不可读时抛出
     */
    public function upload(string $url = '', string $filePath = '', string $name = '', array $params = [])
    {
        if (! file_exists($filePath) || ! is_readable($filePath)) {
            throw new Exception(sprintf('文件不存在或者不可读: "%s"', $filePath));
        }
        // return "@{$filename};filename={$postname};type={$mimetype}";

        // 上传字段名称；eg: file
        $name = ! empty($name) ? $name : 'media';
        if (class_exists('\CURLFile')) {
            $data = [$name => new CURLFile(realpath($filePath))];
        } else {
            $data = [$name => '@'.realpath($filePath)]; // <=5.5
        }

        $reqData = ! empty($params) ? array_merge($data, $params) : $data;

        $this->setParams($reqData);

        return $this->post($url);
    }

    /**
     * 下载远程文件到本地
     *
     * 取消 PHP 执行时间限制，把响应体直接写入 CURLOPT_FILE 指向的文件流，
     * 因此响应内容不会保留在内存里。目标文件以 w+ 打开，同名文件会被覆盖，
     * 且不自动创建多级目录。文件句柄在请求结束后（含异常）必定关闭。
     *
     * 返回值描述本次下载结果：success 需同时满足「无传输错误 + 2xx 状态码 +
     * 落盘字节数大于 0」，可直接据此判断下载是否成功。
     *
     * @param  string  $url  远程文件地址
     * @param  string  $filePath  本地保存路径，其所在目录需已存在且可写
     * @return array{success:bool,file:string,size:int,status:int,error:mixed}
     *               success 是否成功；file 落盘路径；size 实际字节数；
     *               status HTTP 状态码（0 表示未取得）；error 传输层错误，无错误为 null
     *
     * @throws Exception 目标文件无法以写方式打开时抛出 RuntimeException
     */
    public function download(string $url = '', string $filePath = ''): array
    {
        return $this->downloadToFile($url, $filePath, null);
    }

    /**
     * 断点续传下载远程文件
     *
     * 通过 CURLOPT_RANGE 设置 "{$range}-" 请求从指定字节偏移开始的内容，
     * 服务端须支持 Range 请求（返回 206）才能真正续传。目标文件仍以 w+ 打开，
     * 会从头覆盖写入，所以续传时传入的 $filePath 应是需要写入的分片文件而非
     * 已下载的完整文件；如需追加，请自行先改好写入方式。
     *
     * @param  string  $url  远程文件地址
     * @param  string  $filePath  本地保存路径
     * @param  int  $range  起始字节偏移，0 表示从头下载
     * @return array{success:bool,file:string,size:int,status:int,error:mixed}
     *               结构同 download()；续传成功时 status 通常为 206
     *
     * @throws Exception 目标文件无法以写方式打开时抛出 RuntimeException
     */
    public function downloadByRange(string $url = '', string $filePath = '', int $range = 0): array
    {
        return $this->downloadToFile($url, $filePath, $range);
    }

    /**
     * 下载实现：把响应体写入文件并汇总结果
     *
     * @param  string  $url  远程文件地址
     * @param  string  $filePath  本地保存路径
     * @param  int|null  $range  起始字节偏移，null 表示不设置 Range 头
     * @return array{success:bool,file:string,size:int,status:int,error:mixed} 下载结果
     *
     * @throws Exception 目标文件无法以写方式打开时抛出 RuntimeException
     */
    protected function downloadToFile(string $url, string $filePath, ?int $range): array
    {
        set_time_limit(0);
        $this->initCurl();

        if ($range !== null) {
            curl_setopt($this->ch, CURLOPT_RANGE, $range . '-');
        }
        curl_setopt($this->ch, CURLOPT_URL, $url);
        // 默认参数里 CURLOPT_HEADER 为 true：与 CURLOPT_FILE 组合会把响应头一起写进
        // 目标文件，导致下载内容被污染，这里必须关闭
        curl_setopt($this->ch, CURLOPT_HEADER, false);

        $fp = fopen($filePath, 'w+');
        if ($fp === false) {
            throw new \RuntimeException("无法打开文件用于写入: {$filePath}");
        }
        curl_setopt($this->ch, CURLOPT_FILE, $fp);
        try {
            $this->run();
        } finally {
            fclose($fp);
        }

        // 文件刚写完，清掉 stat 缓存再取真实大小
        clearstatcache(true, $filePath);
        $size = is_file($filePath) ? (int) filesize($filePath) : 0;
        $status = (int) ($this->getStatusCode() ?? 0);
        $error = $this->isError() ? $this->getError() : null;

        return [
            'success' => $error === null && $status >= 200 && $status < 300 && $size > 0,
            'file' => $filePath,
            'size' => $size,
            'status' => $status,
            'error' => $error,
        ];
    }

    /**
     * 判断远程资源是否存在
     *
     * 使用独立的 cURL 句柄发送 HEAD 请求（CURLOPT_NOBODY），仅当 HTTP 状态码
     * 恰为 200 时返回 true；3xx 跳转、4xx/5xx 与网络错误均返回 false。
     * 该方法不复用当前句柄，因此 setHeader()/setProxy()/setTimeout() 等设置对其无效，
     * 但会强制开启 SSL 证书与主机名校验（自签名证书站点会被判为不存在）。
     *
     * @param  string  $url  待检测的远程地址
     * @return bool 存在返回 true，否则返回 false
     */
    public function exists(string $url = '')
    {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_NOBODY, true);          // 不取回数据
        // 默认启用 SSL 证书与主机名验证（与常规请求保持一致），避免中间人攻击（MITM）。
        // 若目标使用自签名证书，请通过 setCaPath() 指定 CA 或显式调用 verifySsl(false)。
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        $found = false; // 如果请求没有发送失败
        if (curl_exec($curl) !== false) {
            if (curl_getinfo($curl, CURLINFO_HTTP_CODE) == 200) {// 再检查http响应码是否为200
                $found = true;
            }
        }
        // PHP 8.0 起 curl_close() 无实际作用（8.5 弃用），断开引用即可
        unset($curl);

        return $found;
    }

    /**
     * 执行请求并返回结果（供 get()/post() 等公开方法内部调用）
     *
     * 流程：触发 before 回调（参数为 $this 与 curl 句柄）→ parserResponse() 发请求
     * 并解析响应 → 触发 after 回调（参数为 $this、句柄、响应 body）→ 关闭句柄并置空。
     * 返回值的优先级为：$responseObject 为 true 时返回当前实例；存在传输层错误时返回
     * 错误数组；响应体是字符串（HTML/纯文本）时原样返回；否则再按 $data_type 转换。
     *
     * @param  string  $data_type  返回数据类型：json(数组，默认)|string(JSON 字符串)|collection(集合)
     * @return mixed 当前实例、错误数组、原始字符串、JSON 字符串、Collection 实例或数组
     */
    protected function run(string $data_type = 'json')
    {
        // 请求前处理,回调参数($this 对象，$this->ch 句柄)
        (($this->beforeFunc) instanceof Closure) && is_callable($this->beforeFunc) && ($this->beforeFunc)($this, $this->ch);

        // 解析返回的数据
        $this->parserResponse();

        // 请求后处理,回调参数($this 对象，响应的body)
        (($this->afterFunc) instanceof Closure) && is_callable($this->afterFunc) && ($this->afterFunc)($this, $this->ch, $this->respObjData['body']);

        // 断开句柄引用（curl_close 自 PHP 8.0 起无作用，8.5 起弃用）
        $this->ch = null; // 重置

        if ($this->responseObject) {
            // 返回curl对象，以便调用其他方法
            return $this;
        }

        $body = $this->respObjData['body'];

        if ($this->isError()) {
            return $this->getError();
        }

        // 响应内容为无法解析的文本，直接返回响应内容
        if (is_string($body)) {
            return $body;
        }

        if ($data_type == 'string') {
            return json_array_to_string($body);
        }
        // 集合
        if ($data_type == 'collection') {
            return new Collection($body);
        }

        // 默认返回json 数组
        return $body;
    }

    /**
     * 执行请求并解析响应，结果写入 $respObjData
     *
     * 调用 curl_exec() 发起请求，采集 curl_getinfo 的状态码、耗时、服务器 IP、
     * 重定向、SSL 校验结果等信息，并按 $header_size 拆分响应头与响应体。
     * 响应体按以下顺序归一化：JSONP（callback(...)）剥壳 → 非 JSON 的 key=value
     * 串用 parse_str 解析 → XML 转数组 → JSON 解码，最后经 objToArr() 递归转数组。
     * 请求失败不抛异常，错误码与描述记录在 $respObjData['error']（no/info）。
     *
     * @return static 返回当前实例，便于链式调用
     */
    private function parserResponse(): static
    {
        $content = curl_exec($this->ch);

        // 设置了 CURLOPT_FILE（download/downloadByRange/putFileFromUrlContent）时
        // curl_exec 返回 bool 而非响应字符串；不归一化会在严格类型下让 substr() 抛 TypeError
        $content = is_string($content) ? $content : '';

        $http_code = curl_getinfo($this->ch, CURLINFO_HTTP_CODE); // 获取 HTTP 状态码
        $effective_url = curl_getinfo($this->ch, CURLINFO_EFFECTIVE_URL); // 最终请求的 URL（跟随重定向后的 URL）
        $content_type = (string) (curl_getinfo($this->ch, CURLINFO_CONTENT_TYPE) ?? ''); // 响应的 Content-Type（CURLOPT_FILE 模式下可能为 null）
        $total_time = curl_getinfo($this->ch, CURLINFO_TOTAL_TIME); // 总请求时间（秒）
        $name_lookup_time = curl_getinfo($this->ch, CURLINFO_NAMELOOKUP_TIME); // DNS 解析时间（秒）
        $connect_time = curl_getinfo($this->ch, CURLINFO_CONNECT_TIME); // 连接服务器所用时间（秒）
        $pre_transfer_time = curl_getinfo($this->ch, CURLINFO_PRETRANSFER_TIME); // 从连接到数据传输前所用时间（秒）
        $start_transfer_time = curl_getinfo($this->ch, CURLINFO_STARTTRANSFER_TIME); // 从请求开始到第一个字节传输时间（秒）
        $redirect_count = curl_getinfo($this->ch, CURLINFO_REDIRECT_COUNT); // 重定向次数
        $redirect_time = curl_getinfo($this->ch, CURLINFO_REDIRECT_TIME); // 重定向消耗的总时间（秒）
        $download_size = curl_getinfo($this->ch, CURLINFO_SIZE_DOWNLOAD); // 下载内容大小（字节）
        $size_upload = curl_getinfo($this->ch, CURLINFO_SIZE_UPLOAD); // 上传内容大小（字节）
        $speed_download = curl_getinfo($this->ch, CURLINFO_SPEED_DOWNLOAD); // 平均下载速度（字节/秒）
        $speed_upload = curl_getinfo($this->ch, CURLINFO_SPEED_UPLOAD); // 平均上传速度（字节/秒）
        $header_size = curl_getinfo($this->ch, CURLINFO_HEADER_SIZE); // 响应头部大小（字节）
        $request_size = curl_getinfo($this->ch, CURLINFO_REQUEST_SIZE); // 请求头部大小（字节）
        $ssl_verify_result = curl_getinfo($this->ch, CURLINFO_SSL_VERIFYRESULT); // SSL 证书验证结果
        $primary_ip = curl_getinfo($this->ch, CURLINFO_PRIMARY_IP); // 服务器ip
        $primary_port = curl_getinfo($this->ch, CURLINFO_PRIMARY_PORT); // 服务器端口
        $local_ip = curl_getinfo($this->ch, CURLINFO_LOCAL_IP); // 本地ip
        $local_port = curl_getinfo($this->ch, CURLINFO_LOCAL_PORT); // 本地端口
        $http_version = curl_getinfo($this->ch, CURLINFO_HTTP_VERSION); // HTTP 版本
        $request_header = curl_getinfo($this->ch, CURLINFO_HEADER_OUT); // 请求头部
        $redirect_url = curl_getinfo($this->ch, CURLINFO_REDIRECT_URL); // 重定向 URL
        $error = curl_errno($this->ch) ? [
            'no' => curl_errno($this->ch),
            'info' => curl_error($this->ch),
        ] : []; // 获取请求是否发生错误

        // 提取响应头和主体内容
        $headerStr = substr($content, 0, $header_size);  // 响应头
        $body = substr($content, $header_size);  // 响应主体

        // 提取响应头
        $headers = [];
        $lines = explode("\r\n", $headerStr);
        foreach ($lines as $line) {
            if (str_contains($line, ': ')) {
                [$key, $value] = explode(': ', $line, 2);
                $headers[$key] = $value;
            }
        }
        $headers['content_type'] = $content_type; // 响应的 Content-Type
        $headers['header_size'] = $header_size; // 响应头部大小（字节）
        $headers['http_code'] = $http_code; // HTTP 响应状态码
        $headers['request_size'] = $request_size; // 请求头部大小（字节）

        // 服务器信息
        $servers = [
            'is_redirect' => $redirect_count > 0, // 请求是否发生跳转（重定向）
            'total_time' => $total_time, // 总请求时间（秒）
            'primary_ip' => $primary_ip, // 服务器ip
            'primary_port' => $primary_port, // 服务器端口
            'local_ip' => $local_ip, // 本地ip
            'local_port' => $local_port, // 本地端口
            'http_version' => $http_version, // HTTP 版本
            'request_header' => $request_header, // 请求头部
            'redirect_url' => $redirect_url, // 重定向 URL
            'ssl_verify_result' => $ssl_verify_result, // SSL 证书验证结果
            'redirect_count' => $redirect_count, // 重定向次数
            'redirect_time' => $redirect_time, // 重定向消耗的总时间（秒）
            'download_size' => $download_size, // 下载内容大小（字节）
            'size_upload' => $size_upload, // 上传内容大小（字节）
            'speed_download' => $speed_download, // 平均下载速度（字节/秒）
            'speed_upload' => $speed_upload, // 平均上传速度（字节/秒）
            'effective_url' => $effective_url, // 最终请求的 URL（跟随重定向后的 URL）
            'name_lookup_time' => $name_lookup_time, // DNS 解析时间（秒）
            'connect_time' => $connect_time, // 连接服务器所用时间（秒）
            'pre_transfer_time' => $pre_transfer_time, // 从连接到数据传输前所用时间（秒）
            'start_transfer_time' => $start_transfer_time, // 从请求开始到第一个字节传输时间（秒）
        ];

        $this->respObjData = [
            'success' => $http_code >= 200 && $http_code < 300, // 请求是否成功
            'http_code' => $http_code, // HTTP 响应状态码
            'servers' => $servers, // 服务器信息
            'headers' => $headers, // 响应头
            'body' => $body, // 响应内容
            'error' => $error, // 获取请求是否发生错误
        ];

        // JSONP 格式的json字符串处理, 如:callback({"key":"value"})
        // 采用子串截取而非贪婪正则，避免 JSON 内部包含 '}' 时提取错误，
        // 同时避免无匹配时访问未定义数组下标（Warning: Undefined array key）。
        if (str_starts_with($body, 'callback(')) {
            $inner = substr($body, strlen('callback('));
            if (str_ends_with($inner, ';')) {
                $inner = substr($inner, 0, -1);
            }
            if (str_ends_with($inner, ')')) {
                $inner = substr($inner, 0, -1);
            }
            $body = trim($inner);
        } else {
            if (! $this->isJson($body)) {
                // 判断 等号出现次数
                $countEqStr = substr_count($body, '=', 0);
                if ($countEqStr > 0) {
                    parse_str($body, $body);
                }
            }
        }

        // 根据 `Content-Type` 解析内容
        if (stripos($content_type, 'text/xml') !== false || stripos($content_type, 'application/xml') !== false) {
            // 解析 XML
            $xmlObject = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);
            // 将对象转换为 JSON，再转换为数组
            $body = json_decode(json_encode($xmlObject), true);
        } else {
            // 其他类型
            // json(application/json)、html(text/html)、文本(text/plain) 和其他不需要处理的格式
            if ($this->isJson($body)) {
                // json字符串处理
                $body = is_array($body) ? $body : json_decode($body, true);
            }
        }
        $body = $this->objToArr($body);
        $this->respObjData['body'] = $body;

        return $this;
    }

    /**
     * 判断请求是否成功
     *
     * 判定依据为 HTTP 状态码是否落在 2xx 区间（含 204 等空响应），与响应内容
     * 无关；连接失败时状态码为 0，同样视为失败。未发起请求时该键不存在，返回 null。
     *
     * @return bool|null 2xx 返回 true，其余返回 false；无响应数据时为 null
     */
    public function isSuccessful(): mixed
    {
        return $this->respObjData['success'];
    }

    /**
     * 判断本次请求是否存在传输层错误
     *
     * 对应 curl_errno() 是否非 0（如域名解析失败、连接超时、SSL 握手失败），
     * 4xx/5xx 这类服务端正常返回的状态码不计入错误。
     *
     * @return bool 有错误返回 true
     */
    public function isError(): bool
    {
        return ! empty($this->respObjData['error']);
    }

    /**
     * 获取响应 HTTP 状态码
     *
     * @return int 如 200、404；未发起请求时该键不存在，取值为 null
     */
    public function getStatusCode()
    {
        return $this->respObjData['http_code'];
    }

    /**
     * 获取响应体
     *
     * 内容已在 parserResponse() 中按 Content-Type 归一化：JSON/XML 转为数组，
     * 纯文本（HTML、文本响应）保持字符串，因此返回类型不固定。
     *
     * @return mixed 解析后的响应体；未发起请求时为 null
     */
    public function getBody()
    {
        return $this->respObjData['body'];
    }

    /**
     * 获取响应头
     *
     * 除服务端返回的头之外，还附加 content_type、header_size、http_code、
     * request_size 四个键；服务端头名保持响应中的原始大小写。
     *
     * @param  string  $key  头名称，如 content_type；为空时返回全部响应头
     * @return mixed 指定 $key 不存在时返回 null；未指定 $key 时返回全部响应头数组
     */
    public function getHeader(string $key = '')
    {
        if ($key) {
            return $this->respObjData['headers'][$key] ?? null;
        }

        return $this->respObjData['headers'];
    }

    /**
     * 获取 服务器信息
     *
     * 取自本次响应解析阶段汇总的连接信息（如 primary_ip、redirect_count、
     * is_redirect 等）。传入 $key 时取对应字段，字段不存在返回 null；
     * 不传则返回全部服务器信息数组。
     *
     * @param  string  $key  eg: primary_ip:服务器ip
     * @return mixed|null
     */
    public function getServer(string $key = '')
    {
        if ($key) {
            return $this->respObjData['servers'][$key] ?? null;
        }

        return $this->respObjData['servers'];
    }

    /**
     * 判断请求是否发生了重定向
     *
     * 依据 curl_getinfo 的 CURLINFO_REDIRECT_COUNT 是否大于 0，仅统计本次请求；
     * 可通过 getServer('redirect_count') 或 getServer('redirect_url') 查看细节。
     *
     * @return bool 发生过跳转返回 true
     */
    public function hasRedirect()
    {
        return $this->respObjData['servers']['is_redirect'];
    }

    /**
     * 获取请求错误信息
     *
     * 来源为 curl_errno()/curl_error()，只反映传输层错误（DNS、超时、连接失败等），
     * 不含 4xx/5xx 这类业务层状态码；后者请用 isSuccessful() 或 getStatusCode() 判断。
     *
     * @return array 形如 ['no' => 错误码, 'info' => 错误描述]；无错误时为空数组
     */
    public function getError()
    {
        return $this->respObjData['error'];
    }

    /**
     * 获取本次响应的完整原始数据
     *
     * 包含 success、http_code、servers、headers、body、error 六个键，
     * 未发起请求时为空数组。
     *
     * @return array 响应数据集合
     */
    public function getResp(): array
    {
        return $this->respObjData;
    }

    /**
     * 读取本类内部属性的值
     *
     * 供调试/扩展使用，可读取私有属性（如 headers、cookieFile、responseObject）；
     * 属性不存在时返回 null，不会抛异常。
     *
     * @param  string  $attr  属性名
     * @return mixed 属性值；属性不存在时为 null
     */
    public function getAttr(string $attr = '')
    {
        // 判断此类中是否有属性$attr
        if (property_exists($this, $attr)) {
            return $this->$attr;
        } else {
            return null;
        }
    }

    /**
     * 将 stdClass 对象递归转换为数组
     *
     * 多层嵌套对象会被逐层转换；数组中的对象同样会被转换，标量原样返回。
     *
     * @param  mixed  $array  待转换的数据，可为对象、数组或标量
     * @return mixed 对象或数组返回数组，标量原样返回
     */
    protected function objToArr($array)
    {
        if (is_object($array)) {
            $array = (array) $array;
        }
        if (is_array($array)) {
            foreach ($array as $key => $value) {
                $array[$key] = $this->objToArr($value);
            }
        }

        return $array;
    }

    /**
     * 判断字符串是否为 Json 格式
     *
     * @param  string|array  $string  $string Json 字符串
     * @return bool 成功返回true，失败返回 false
     */
    protected function isJson(string|array $string = ''): bool
    {
        try {
            if (is_array($string)) {
                return true;
            }
            $data = json_decode($string, true);
            if (($data && is_object($data)) || (is_array($data) && ! empty($data))) {
                return true;
            }
        } catch (Exception $e) {
        }

        return false;
    }

    /**
     * 异步将远程链接上的内容(图片或内容)写到本地
     *
     * @param  string  $url  远程地址
     * @param  string  $saveFile  保存在服务器上的文件名(e.g. /root/a/b.jpg)
     * @return bool 当返回为true时，代表成功，反之，为失败
     */
    public function putFileFromUrlContent(string $url, string $saveFile): bool
    {
        // 设置运行时间为无限制
        set_time_limit(0);
        // 前一次请求结束后句柄会被置空，这里必须先初始化，否则 curl_setopt(null) 抛 TypeError
        $this->initCurl();
        $url = trim($url);
        // 设置你需要抓取的URL
        curl_setopt($this->ch, CURLOPT_URL, $url);
        // 设置header
        curl_setopt($this->ch, CURLOPT_HEADER, 0);
        // 运行cURL，请求网页
        $file = curl_exec($this->ch);
        // 断开句柄引用（curl_close 自 PHP 8.0 起无作用，8.5 起弃用）
        $this->ch = null;
        // 将文件写入获得的数据
        $write = @fopen($saveFile, 'w');
        if (! $write) {
            return false;
        }
        // 无论写入是否成功都必须关闭句柄：原实现在 fwrite 失败时提前 return，造成句柄泄漏。
        try {
            // curl_exec 失败时 $file 为 false，不应写入。
            // fwrite 返回 0 表示写入 0 字节（空响应，合法），只有严格 false 才算失败，
            // 原写法 `! fwrite(...)` 会把空响应误判为写入错误。
            if ($file === false || fwrite($write, $file) === false) {
                return false;
            }
        } finally {
            fclose($write);
        }

        return true;
    }

    /**
     * 随机生成一个常见浏览器的 User-Agent
     *
     * 从内置的 PC/移动端（含微信内置浏览器等）UA 列表中随机取一条，
     * 供 get()/post()/put() 等方法在请求前覆盖 CURLOPT_USERAGENT。
     *
     * @return string 随机 User-Agent 字符串
     */
    protected function randUserAgent(): string
    {
        $agentarry = [
            // PC端的UserAgent
            'safari 5.1 – MAC' => 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/536.11 (KHTML, like Gecko) Chrome/20.0.1132.57 Safari/536.11',
            'safari 5.1 – Windows' => 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-us) AppleWebKit/534.50 (KHTML, like Gecko) Version/5.1 Safari/534.50',
            'Firefox 38esr' => 'Mozilla/5.0 (Windows NT 10.0; WOW64; rv:38.0) Gecko/20100101 Firefox/38.0',
            'IE 11' => 'Mozilla/5.0 (Windows NT 10.0; WOW64; Trident/7.0; .NET4.0C; .NET4.0E; .NET CLR 2.0.50727; .NET CLR 3.0.30729; .NET CLR 3.5.30729; InfoPath.3; rv:11.0) like Gecko',
            'IE 9.0' => 'Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; Trident/5.0',
            'IE 8.0' => 'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.0; Trident/4.0)',
            'IE 7.0' => 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.0)',
            'IE 6.0' => 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)',
            'Firefox 4.0.1 – MAC' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.6; rv:2.0.1) Gecko/20100101 Firefox/4.0.1',
            'Firefox 4.0.1 – Windows' => 'Mozilla/5.0 (Windows NT 6.1; rv:2.0.1) Gecko/20100101 Firefox/4.0.1',
            'Opera 11.11 – MAC' => 'Opera/9.80 (Macintosh; Intel Mac OS X 10.6.8; U; en) Presto/2.8.131 Version/11.11',
            'Opera 11.11 – Windows' => 'Opera/9.80 (Windows NT 6.1; U; en) Presto/2.8.131 Version/11.11',
            'Chrome 17.0 – MAC' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_7_0) AppleWebKit/535.11 (KHTML, like Gecko) Chrome/17.0.963.56 Safari/535.11',
            '傲游（Maxthon）' => 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; Maxthon 2.0)',
            '腾讯TT' => 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; TencentTraveler 4.0)',
            '世界之窗（The World） 2.x' => 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1)',
            '世界之窗（The World） 3.x' => 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; The World)',
            '360浏览器' => 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; 360SE)',
            '搜狗浏览器 1.x' => 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; Trident/4.0; SE 2.X MetaSr 1.0; SE 2.X MetaSr 1.0; .NET CLR 2.0.50727; SE 2.X MetaSr 1.0)',
            'Avant' => 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; Avant Browser)',
            'Green Browser' => 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1)',
            // 移动端口
            'safari iOS 4.33 – iPhone' => 'Mozilla/5.0 (iPhone; U; CPU iPhone OS 4_3_3 like Mac OS X; en-us) AppleWebKit/533.17.9 (KHTML, like Gecko) Version/5.0.2 Mobile/8J2 Safari/6533.18.5',
            'safari iOS 4.33 – iPod Touch' => 'Mozilla/5.0 (iPod; U; CPU iPhone OS 4_3_3 like Mac OS X; en-us) AppleWebKit/533.17.9 (KHTML, like Gecko) Version/5.0.2 Mobile/8J2 Safari/6533.18.5',
            'safari iOS 4.33 – iPad' => 'Mozilla/5.0 (iPad; U; CPU OS 4_3_3 like Mac OS X; en-us) AppleWebKit/533.17.9 (KHTML, like Gecko) Version/5.0.2 Mobile/8J2 Safari/6533.18.5',
            'Android N1' => 'Mozilla/5.0 (Linux; U; Android 2.3.7; en-us; Nexus One Build/FRF91) AppleWebKit/533.1 (KHTML, like Gecko) Version/4.0 Mobile Safari/533.1',
            'Android QQ浏览器 For android' => 'MQQBrowser/26 Mozilla/5.0 (Linux; U; Android 2.3.7; zh-cn; MB200 Build/GRJ22; CyanogenMod-7) AppleWebKit/533.1 (KHTML, like Gecko) Version/4.0 Mobile Safari/533.1',
            'Android Opera Mobile' => 'Opera/9.80 (Android 2.3.4; Linux; Opera Mobi/build-1107180945; U; en-GB) Presto/2.8.149 Version/11.10',
            'Android Pad Moto Xoom' => 'Mozilla/5.0 (Linux; U; Android 3.0; en-us; Xoom Build/HRI39) AppleWebKit/534.13 (KHTML, like Gecko) Version/4.0 Safari/534.13',
            'BlackBerry' => 'Mozilla/5.0 (BlackBerry; U; BlackBerry 9800; en) AppleWebKit/534.1+ (KHTML, like Gecko) Version/6.0.0.337 Mobile Safari/534.1+',
            'WebOS HP Touchpad' => 'Mozilla/5.0 (hp-tablet; Linux; hpwOS/3.0.0; U; en-US) AppleWebKit/534.6 (KHTML, like Gecko) wOSBrowser/233.70 Safari/534.6 TouchPad/1.0',
            'UC标准' => 'NOKIA5700/ UCWEB7.0.2.37/28/999',
            'UCOpenwave' => 'Openwave/ UCWEB7.0.2.37/28/999',
            'UC Opera' => 'Mozilla/4.0 (compatible; MSIE 6.0; ) Opera/UCWEB7.0.2.37/28/999',
            '微信内置浏览器' => 'Mozilla/5.0 (Linux; Android 6.0; 1503-M02 Build/MRA58K) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/37.0.0.0 Mobile MQQBrowser/6.2 TBS/036558 Safari/537.36 MicroMessenger/6.3.25.861 NetType/WIFI Language/zh_CN',

        ];

        return $agentarry[array_rand($agentarry, 1)]; // 随机浏览器useragent
    }
}
