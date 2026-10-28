<?php

declare(strict_types=1);

namespace zxf\Utils\Http;

use Exception;
use zxf\Utils\Xml\Array2XML;

/**
 * HTTP 响应
 */
class Response
{
    protected static ?self $instance = null;

    private array $headers = [];

    private mixed $body;

    private int $statusCode = 200;
/**
 * 构造函数，初始化响应的主体内容与 HTTP 状态码
 *
 * @param  mixed  $body  响应主体内容，可为字符串、数组等任意类型，默认 null
 * @param  int  $statusCode  HTTP 状态码，默认 200
 * @return void
 */
    public function __construct(mixed $body = null, int $statusCode = 200)
    {
        $this->body = $body;
        $this->statusCode = $statusCode;
    }

/**
 * 获取响应单例
 *
 * 同进程内重复调用返回同一实例，便于在任意位置追加响应头/体；
 * $refresh 为 true 时丢弃旧实例重新创建，之前设置的响应数据不会保留。
 *
 * @param  bool  $refresh  是否强制重新实例化（true 时重建单例）
 * @return static
 */
    public static function instance(bool $refresh = false): static
    {
        if (self::$instance === null || $refresh) {
            self::$instance = new static();
        }

        return self::$instance;
    }

/**
 * 设置响应头
 *
 * 支持以关联数组批量设置（键名为头名、键值为头值），或以字符串指定头名、以 $value 指定头值。
 *
 * @param  array|string  $name  响应头名称，或形如 ['Name' => 'Value'] 的关联数组
 * @param  mixed  $value  当 $name 为字符串时的头值，默认 null
 * @return self
 */
    public function setHeader(array|string $name, mixed $value = null): self
    {
        if (is_array($name)) {
            $this->headers = array_merge($this->headers, $name);
        } else {
            $this->headers[$name] = $value;
        }

        return $this;
    }

    /**
     * 获取指定响应头的值
     *
     * @param  string  $name  响应头名称
     * @return mixed 返回对应响应头的值，若不存在则返回 null
     */
    public function getHeader(string $name)
    {
        return $this->headers[$name] ?? null;
    }

/**
 * 获取全部响应头
 *
 * 返回当前已设置的全部响应头快照（含本次调用前通过 setHeader 追加的项），
 * 不含 send() 阶段自动补全的默认 Content-Type。
 *
 * @return array 以头名为键、头值为值的关联数组
 */
    public function getHeaders(): array
    {
        return $this->headers;
    }

/**
 * 设置响应主体内容
 *
 * @param  mixed  $body  响应主体内容
 * @return self
 */
    public function setBody(mixed $body): self
    {
        $this->body = $body;

        return $this;
    }

/**
 * 获取响应主体内容
 *
 * @return mixed 响应主体内容
 */
    public function getBody(): mixed
    {
        return $this->body;
    }

/**
 * 设置 HTTP 状态码
 *
 * @param  int  $statusCode  HTTP 状态码，默认 200
 * @return self
 */
    public function setStatusCode(int $statusCode = 200): self
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    /**
     * 获取 HTTP 状态码
     *
     * @return int 当前响应的 HTTP 状态码
     */
    public function getStatusCode()
    {
        return (int) $this->statusCode;
    }

    /**
     * 重定向
     *
     * 仅记录 Location 头并把状态码设为 301（永久）或 302（临时），不会立即
     * 输出；需再调用 send() 才会真正下发响应头并终止脚本。
     *
     * @param  string  $url  重定向目标地址
     * @param  bool  $permanent  是否永久重定向
     * @return self
     */
    public function redirect(string $url, bool $permanent = false): self
    {
        $this->setHeader('Location', $url);
        $this->setStatusCode($permanent ? 301 : 302);

        return $this;
    }

/**
 * 以附件形式下载指定文件并终止脚本
 *
 * 设置相应的 Content-Type 与 Content-Disposition 响应头后将文件内容输出到浏览器并调用 exit。
 * 会对文件名中的换行/回车/引号进行过滤，防止响应头注入。
 *
 * @param  string  $file  待下载文件的本地路径，默认空字符串
 * @param  string|null  $filename  下载时显示的文件名，默认取 $file 的 basename
 * @return never
 * @throws Exception 当文件不存在时抛出
 */
    public function download(string $file = '', ?string $filename = null): never
    {
        $filename = $filename ?? basename($file);
        // 过滤文件名中的换行/回车与引号，防止响应头注入（CRLF injection）及文件名解析异常
        $filename = str_replace(["\r", "\n", '"'], '', $filename);

        if (! file_exists($file)) {
            throw new Exception('文件不存在');
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file));
        ob_clean();
        flush();
        readfile($file);
        exit;
    }

    /**
     * 设置 JSON 响应
     *
     * 自动设置 Content-Type 为 application/json，并将数据编码为 JSON 字符串写入响应体。
     *
     * @param  array  $data  待输出的关联数组，默认空数组
     * @return self
     */
    public function json(array $data = []): self
    {
        $this->setHeader('Content-Type', 'application/json');
        $this->setBody(json_encode($data));

        return $this;
    }

    /**
     * 设置纯文本响应
     *
     * 自动设置 Content-Type 为 text/plain。
     *
     * @param  string  $text  响应文本内容，默认空字符串
     * @return self
     */
    public function text(string $text = ''): self
    {
        $this->setHeader('Content-Type', 'text/plain');
        $this->setBody($text);

        return $this;
    }

/**
 * 设置 HTML 响应
 *
 * 自动设置 Content-Type 为 text/html。
 *
 * @param  string  $html  响应 HTML 内容
 * @return self
 */
    public function html(string $html): self
    {
        $this->setHeader('Content-Type', 'text/html');
        $this->setBody($html);

        return $this;
    }

/**
 * 设置响应字符集
 *
 * 通过 Content-Type 头设置文档字符集。
 *
 * @param  string  $charset  字符集名称，默认 UTF-8
 * @return self
 */
    public function setCharset(string $charset = 'UTF-8'): self
    {
        $this->setHeader('Content-Type', 'text/html; charset=' . $charset);

        return $this;
    }

/**
 * 设置 XML 响应
 *
 * 清空已有响应头后设置 Content-Type 为 text/xml，并将数组转换为 XML 写入响应体。
 *
 * @param  array  $data  待转换为 XML 的数组数据
 * @param  array  $config  Array2XML 转换配置，默认空数组
 * @return self
 */
    public function xml(array $data, array $config = []): self
    {
        $this->headers = [];
        $this->setHeader('Content-Type', 'text/xml');
        $this->setBody($this->arrayToXml($data, $config));

        return $this;
    }

/**
 * 将数组转换为 XML 字符串
 *
 * @param  mixed  $array  待转换的数组或数据
 * @param  array  $config  Array2XML 转换配置，默认空数组
 * @return mixed 转换后的 XML 字符串
 */
    private function arrayToXml(mixed $array, array $config = []): mixed
    {
        return Array2XML::toXML($array, $config);
    }

    /**
     * 设置空白（空内容）响应
     *
     * @return self
     */
    public function emptyResponse()
    {
        $this->setBody('');

        return $this;
    }

    /**
     * 以 CSV 附件形式输出数据并终止脚本
     *
     * 将二维数组按行写入 CSV 并下载，随后调用 exit。会对文件名中的换行/回车/引号进行过滤。
     *
     * @param  array  $data  二维数组形式的 CSV 数据，每行作为一条记录
     * @param  string  $filename  下载文件名（不含 .csv 后缀），默认 csv_file
     * @return never
     */
    public function csv(array $data, string $filename = 'csv_file'): never
    {
        $this->headers = [];
        // 过滤文件名中的换行/回车与引号，防止响应头注入
        $filename = str_replace(["\r", "\n", '"'], '', $filename);
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        $output = fopen('php://output', 'w');

        foreach ($data as $row) {
            fputcsv($output, $row);
        }

        fclose($output);
        exit();
    }

/**
 * 设置 Content-Length 响应头
 *
 * @param  int  $length  内容长度（字节），默认 0
 * @return self
 */
    public function setLength(int $length = 0): self
    {
        $this->setHeader('Content-Length', $length);

        return $this;
    }

/**
 * 设置 ETag 响应头
 *
 * @param  string  $etag  实体标签值
 * @return self
 */
    public function setEtag(string $etag): self
    {
        $this->setHeader('ETag', $etag);

        return $this;
    }

    /**
     * 将图片内容输出到浏览器并终止脚本
     *
     * 根据文件实际 MIME 类型设置 Content-Type 后输出图片内容并调用 exit；文件不存在时抛出异常。
     *
     * @param  string  $imagePath  图片文件的本地路径
     * @return self
     * @throws Exception 当图片文件不存在时抛出
     */
    public function image(string $imagePath): self
    {
        // 检查文件是否存在
        if (file_exists($imagePath)) {
            // 获取文件信息
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mediaType = finfo_file($finfo, $imagePath);
            finfo_close($finfo);
            // 设置正确的头信息
            header('Content-Type: '.$mediaType);
            ob_clean();
            flush();
            // 将图片内容发送到浏览器
            readfile($imagePath);
            exit;
        } else {
            throw new Exception('图片不存在');
        }
    }

/**
 * 设置 Expires 响应头
 *
 * @param  int  $expires  过期时间（Unix 时间戳），默认 0
 * @return self
 */
    public function setExpires(int $expires = 0): self
    {
        $this->setHeader('Expires', $expires);

        return $this;
    }

/**
 * 设置 Cache-Control 响应头
 *
 * @param  string  $cacheControl  缓存控制指令
 * @return self
 */
    public function setCacheControl(string $cacheControl): self
    {
        $this->setHeader('Cache-Control', $cacheControl);

        return $this;
    }

/**
 * 设置 Basic 认证质询响应头
 *
 * 当 $password 为 null 时仅声明 Basic 认证；否则以 realm 形式携带用户名。
 *
 * @param  string  $username  认证域（realm）名称，或仅作为 Basic 标识
 * @param  string|null  $password  密码，为 null 时不带 realm 值
 * @return self
 */
    public function setAuthentication(string $username, ?string $password = null): self
    {
        if ($password === null) {
            $this->setHeader('WWW-Authenticate', 'Basic');
        } else {
            $this->setHeader('WWW-Authenticate', 'Basic realm="' . $username . '"');
        }

        return $this;
    }

    /**
     * 设置 Content-Encoding 响应头（响应压缩格式）
     *
     * @param  string  $format  压缩编码格式，如 gzip、deflate
     * @return self
     */
    public function setCompression(string $format): self
    {
        $this->setHeader('Content-Encoding', $format);

        return $this;
    }

    /**
     * 设置异常响应
     *
     * 将异常的状态码与消息分别写入响应状态码与响应体。
     *
     * @param  Exception  $exception  待响应的异常对象
     * @return self
     */
    public function sendException(Exception $exception): self
    {
        $this->setStatusCode($exception->getCode());
        $this->setBody($exception->getMessage());

        return $this;
    }

/**
 * 设置代理 Basic 认证质询响应头
 *
 * @param  string  $username  认证域（realm）名称
 * @param  string|null  $password  密码，为 null 时不带 realm 值
 * @return self
 */
    public function setProxyAuthentication(string $username, ?string $password = null): self
    {
        if ($password === null) {
            $this->setHeader('Proxy-Authenticate', 'Basic');
        } else {
            $this->setHeader('Proxy-Authenticate', 'Basic realm="' . $username . '"');
        }

        return $this;
    }

/**
 * 设置 JSONP 响应
 *
 * 当未提供 callback 时退化为普通 JSON 响应；否则以 JavaScript 形式包装回调。
 *
 * @param  mixed  $data  待输出的数据
 * @param  string|null  $callback  JSONP 回调函数名，默认 null
 * @return self
 */
    public function jsonp(mixed $data, ?string $callback = null): self
    {
        if ($callback === null || $callback === '') {
            return $this->json($data);
        }

        $this->setHeader('Content-Type', 'application/javascript');
        $this->setBody($callback . '(' . json_encode($data) . ');');

        return $this;
    }

/**
 * 以 octet-stream 形式读取大文件内容到响应体
 *
 * 流式读取文件内容（避免一次性占用大块内存），读取完成后确保关闭文件句柄。
 *
 * @param  string  $file  待读取的文件路径
 * @return self
 * @throws \RuntimeException 当文件无法打开时抛出
 */
    public function sendLargeFile(string $file): self
    {
        $this->setHeader('Content-Type', 'application/octet-stream');
        $handle = fopen($file, 'rb');
        if ($handle === false) {
            throw new \RuntimeException("无法打开文件: {$file}");
        }
        try {
            // 流式读取（避免一次性分配整文件内存），并在结束时确保关闭句柄
            $body = stream_get_contents($handle);
        } finally {
            fclose($handle);
        }
        $this->setBody($body);

        return $this;
    }

    /**
     * 设置 Server 响应头
     *
     * 提供版本号时以 "名称/版本" 形式写入，否则仅写入名称。
     *
     * @param  string  $serverName  服务器软件名称
     * @param  string|null  $serverVersion  版本号，默认 null
     * @return self
     */
    public function setServerInfo(string $serverName, ?string $serverVersion = null): self
    {
        $this->setHeader('Server', $serverVersion !== null ? $serverName . '/' . $serverVersion : $serverName);

        return $this;
    }

/**
 * 设置 X-Interface 响应头
 *
 * @param  string  $interfaceName  接口名称
 * @param  string|null  $version  版本号，默认 null
 * @return self
 */
    public function setInterfaceInfo(string $interfaceName, ?string $version = null): self
    {
        $this->setHeader('X-Interface', $version !== null ? $interfaceName . '/' . $version : $interfaceName);

        return $this;
    }

/**
 * 设置 Cookie 响应头以携带 PHPSESSID
 *
 * @param  string  $sessionId  会话 ID
 * @return self
 */
    public function sessionId(string $sessionId): self
    {
        $this->setHeader('Cookie', 'PHPSESSID=' . $sessionId);

        return $this;
    }

/**
 * 设置 Last-Modified 响应头
 *
 * @param  string  $lastModified  最后修改时间（HTTP 日期格式字符串）
 * @return self
 */
    public function setLastModified(string $lastModified): self
    {
        $this->setHeader('Last-Modified', $lastModified);

        return $this;
    }

/**
 * 发送响应（输出全部响应头与响应体）并终止脚本
 *
 * 默认补全 Content-Type 头，过滤响应头中的 CRLF 以防止头注入，输出响应体后调用 exit。
 *
 * @return never
 */
    public function send(): never
    {
        if ($this->headers === []) {
            $this->headers = ['Content-Type' => 'text/html; charset=UTF-8'];
        }

        foreach ($this->headers as $header => $value) {
            // 过滤 CRLF，防止响应头注入（Header Injection）
            $header = str_replace(["\r", "\n"], '', (string) $header);
            $value = str_replace(["\r", "\n"], '', (string) $value);
            header($header . ': ' . $value);
        }

        http_response_code($this->statusCode);
        echo $this->body;
        exit();
    }
}
