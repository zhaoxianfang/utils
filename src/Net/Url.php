<?php

/**
 * ============================================================================
 * URL 解析与构建工具
 * ============================================================================
 *
 * 提供比 parse_url 更友好的 API，支持链式构建 URL。
 * 适用于 URL 参数拼接、签名生成、路由构建等场景。
 *
 * @package     zxf\Utils\Net
 * @author      zxf\Utils
 * @version     1.0.0
 * @license     MIT
 * @link        https://github.com/zhaoxianfang/utils
 * @since       1.0.0
 */

declare(strict_types=1);

namespace zxf\Utils\Net;

/**
 * URL 解析与构建工具
 *
 * 提供比 parse_url 更友好的 API，支持链式构建 URL
 *
 * @example
 * $url = Url::parse('https://example.com/path')
 *     ->withQuery(['page' => 2])
 *     ->withFragment('section')
 *     ->toString();
 */
final class Url
{
    private string $scheme = 'https';

    private string $host = '';

    private ?int $port = null;

    private string $path = '';

    private array $query = [];

    private string $fragment = '';

    private ?string $user = null;

    private ?string $password = null;

    private function __construct()
    {
    }

    /**
     * 从字符串解析 URL
     */
    public static function parse(string $url): self
    {
        $parts = parse_url($url);
        if ($parts === false) {
            throw new \InvalidArgumentException('Invalid URL: ' . $url);
        }

        $instance = new self();
        $instance->scheme = $parts['scheme'] ?? 'https';
        $instance->host = $parts['host'] ?? '';
        $instance->port = isset($parts['port']) ? (int) $parts['port'] : null;
        $instance->path = $parts['path'] ?? '';
        $instance->fragment = $parts['fragment'] ?? '';
        $instance->user = $parts['user'] ?? null;
        $instance->password = $parts['pass'] ?? null;

        if (isset($parts['query'])) {
            $query = [];
            parse_str($parts['query'], $query);
            $instance->query = $query;
        }

        return $instance;
    }

    /**
     * 创建空 URL 实例
     */
    public static function make(): self
    {
        return new self();
    }

    public function withScheme(string $scheme): self
    {
        $this->scheme = $scheme;
        return $this;
    }

    public function withHost(string $host): self
    {
        $this->host = $host;
        return $this;
    }

    public function withPort(?int $port): self
    {
        $this->port = $port;
        return $this;
    }

    public function withPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    /**
     * 添加路径段（自动处理斜杠）
     */
    public function joinPath(string $segment): self
    {
        $this->path = rtrim($this->path, '/') . '/' . ltrim($segment, '/');
        return $this;
    }

    public function withQuery(array $query): self
    {
        $this->query = $query;
        return $this;
    }

    public function addQuery(string $key, mixed $value): self
    {
        $this->query[$key] = $value;
        return $this;
    }

    public function removeQuery(string $key): self
    {
        unset($this->query[$key]);
        return $this;
    }

    public function withFragment(string $fragment): self
    {
        $this->fragment = $fragment;
        return $this;
    }

    public function withUserInfo(?string $user, ?string $password = null): self
    {
        $this->user = $user;
        $this->password = $password;
        return $this;
    }

    public function scheme(): string
    {
        return $this->scheme;
    }

    public function host(): string
    {
        return $this->host;
    }

    public function port(): ?int
    {
        return $this->port;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function query(): array
    {
        return $this->query;
    }

    public function fragment(): string
    {
        return $this->fragment;
    }

    public function getQueryString(): string
    {
        return http_build_query($this->query);
    }

    /**
     * 转换为完整 URL 字符串
     */
    public function toString(): string
    {
        $url = $this->scheme . '://';

        if ($this->user !== null) {
            $url .= $this->user;
            if ($this->password !== null) {
                $url .= ':' . $this->password;
            }
            $url .= '@';
        }

        $url .= $this->host;

        if ($this->port !== null) {
            $url .= ':' . $this->port;
        }

        $url .= $this->path;

        if (! empty($this->query)) {
            $url .= '?' . $this->getQueryString();
        }

        if ($this->fragment !== '') {
            $url .= '#' . $this->fragment;
        }

        return $url;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * 判断是否为绝对 URL
     */
    public function isAbsolute(): bool
    {
        return $this->host !== '';
    }

    /**
     * 判断是否为安全连接
     */
    public function isSecure(): bool
    {
        return in_array(strtolower($this->scheme), ['https', 'ftps'], true);
    }

    /**
     * 获取顶级域名
     */
    public function topLevelDomain(): ?string
    {
        if (empty($this->host)) {
            return null;
        }

        $parts = explode('.', $this->host);
        return count($parts) > 1 ? end($parts) : null;
    }

    /**
     * 获取域名（不含 TLD）
     */
    public function domainName(): ?string
    {
        if (empty($this->host)) {
            return null;
        }

        $parts = explode('.', $this->host);
        if (count($parts) > 2) {
            return $parts[count($parts) - 2];
        }

        return $parts[0] ?? null;
    }
}
