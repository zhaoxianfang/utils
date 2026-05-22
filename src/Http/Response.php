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

    public function __construct(mixed $body = null, int $statusCode = 200)
    {
        $this->body = $body;
        $this->statusCode = $statusCode;
    }

    public static function instance(bool $refresh = false): static
    {
        if (self::$instance === null || $refresh) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    public function setHeader(array|string $name, mixed $value = null): self
    {
        if (is_array($name)) {
            $this->headers = array_merge($this->headers, $name);
        } else {
            $this->headers[$name] = $value;
        }

        return $this;
    }

    //  获取响应头
    public function getHeader(string $name)
    {
        return $this->headers[$name] ?? null;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function setBody(mixed $body): self
    {
        $this->body = $body;

        return $this;
    }

    public function getBody(): mixed
    {
        return $this->body;
    }

    public function setStatusCode(int $statusCode = 200): self
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    //  获取状态码
    public function getStatusCode()
    {
        return (int) $this->statusCode;
    }

    /**
     * 重定向
     *
     * @param  bool  $permanent  是否永久重定向
     * @return self
     */
    public function redirect(string $url, bool $permanent = false): self
    {
        $this->setHeader('Location', $url);
        $this->setStatusCode($permanent ? 301 : 302);

        return $this;
    }

    public function download(string $file = '', ?string $filename = null): never
    {
        $filename = $filename ?? basename($file);

        if (! file_exists($file)) {
            throw new Exception('文件不存在');
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . $filename);
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

    //  发送 JSON 响应
    // $response->json(['message' => 'Hello, world!']);
    public function json(array $data = []): self
    {
        $this->setHeader('Content-Type', 'application/json');
        $this->setBody(json_encode($data));

        return $this;
    }

    //  发送文本响应
    public function text(string $text = ''): self
    {
        $this->setHeader('Content-Type', 'text/plain');
        $this->setBody($text);

        return $this;
    }

    public function html(string $html): self
    {
        $this->setHeader('Content-Type', 'text/html');
        $this->setBody($html);

        return $this;
    }

    public function setCharset(string $charset = 'UTF-8'): self
    {
        $this->setHeader('Content-Type', 'text/html; charset=' . $charset);

        return $this;
    }

    public function xml(array $data, array $config = []): self
    {
        $this->headers = [];
        $this->setHeader('Content-Type', 'text/xml');
        $this->setBody($this->arrayToXml($data, $config));

        return $this;
    }

    private function arrayToXml(mixed $array, array $config = []): mixed
    {
        return Array2XML::toXML($array, $config);
    }

    //  发送空白响应（空内容响应）
    public function emptyResponse()
    {
        $this->setBody('');

        return $this;
    }

    //  发送 CSV 响应
    public function csv(array $data, string $filename = 'csv_file'): never
    {
        $this->headers = [];
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        $output = fopen('php://output', 'w');

        foreach ($data as $row) {
            fputcsv($output, $row);
        }

        fclose($output);
        exit();
    }

    public function setLength(int $length = 0): self
    {
        $this->setHeader('Content-Length', $length);

        return $this;
    }

    public function setEtag(string $etag): self
    {
        $this->setHeader('ETag', $etag);

        return $this;
    }

    //  展示图片内容到浏览器
    // $response->image();
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

    public function setExpires(int $expires = 0): self
    {
        $this->setHeader('Expires', $expires);

        return $this;
    }

    public function setCacheControl(string $cacheControl): self
    {
        $this->setHeader('Cache-Control', $cacheControl);

        return $this;
    }

    public function setAuthentication(string $username, ?string $password = null): self
    {
        if ($password === null) {
            $this->setHeader('WWW-Authenticate', 'Basic');
        } else {
            $this->setHeader('WWW-Authenticate', 'Basic realm="' . $username . '"');
        }

        return $this;
    }

    //  设置响应的压缩格式
    public function setCompression(string $format): self
    {
        $this->setHeader('Content-Encoding', $format);

        return $this;
    }

    //  发送异常响应
    // $response->sendException(new Exception('Something went wrong!'));
    public function sendException(Exception $exception): self
    {
        $this->setStatusCode($exception->getCode());
        $this->setBody($exception->getMessage());

        return $this;
    }

    public function setProxyAuthentication(string $username, ?string $password = null): self
    {
        if ($password === null) {
            $this->setHeader('Proxy-Authenticate', 'Basic');
        } else {
            $this->setHeader('Proxy-Authenticate', 'Basic realm="' . $username . '"');
        }

        return $this;
    }

    public function jsonp(mixed $data, ?string $callback = null): self
    {
        if ($callback === null || $callback === '') {
            return $this->json($data);
        }

        $this->setHeader('Content-Type', 'application/javascript');
        $this->setBody($callback . '(' . json_encode($data) . ');');

        return $this;
    }

    public function sendLargeFile(string $file): self
    {
        $this->setHeader('Content-Type', 'application/octet-stream');
        $this->setBody(fread(fopen($file, 'rb'), filesize($file)));

        return $this;
    }

    //  设置响应的服务器信息
    // $response->setServerInfo('Apache/2.4.18 (Unix) PHP/5.6.34', '1.0');
    public function setServerInfo(string $serverName, ?string $serverVersion = null): self
    {
        $this->setHeader('Server', $serverVersion !== null ? $serverName . '/' . $serverVersion : $serverName);

        return $this;
    }

    public function setInterfaceInfo(string $interfaceName, ?string $version = null): self
    {
        $this->setHeader('X-Interface', $version !== null ? $interfaceName . '/' . $version : $interfaceName);

        return $this;
    }

    public function sessionId(string $sessionId): self
    {
        $this->setHeader('Cookie', 'PHPSESSID=' . $sessionId);

        return $this;
    }

    public function setLastModified(string $lastModified): self
    {
        $this->setHeader('Last-Modified', $lastModified);

        return $this;
    }

    public function send(): never
    {
        if ($this->headers === []) {
            $this->headers = ['Content-Type' => 'text/html; charset=UTF-8'];
        }

        foreach ($this->headers as $header => $value) {
            header($header . ': ' . $value);
        }

        http_response_code($this->statusCode);
        echo $this->body;
        exit();
    }
}
