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
 * 实例内部按 scheme / user / password / host / port / path / query / fragment
 * 八个部分分别保存，with* 方法只覆盖对应部分、其余部分保持不变，
 * 最终由 toString() 按标准顺序拼回字符串。
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

    /**
     * 私有构造函数，禁止直接 new 实例化
     *
     * 实例只能由 parse() 或 make() 创建，从而保证 scheme 默认为 https、
     * query 恒为数组等内部状态始终一致。
     */
    private function __construct()
    {
    }

    /**
     * 从字符串解析 URL
     *
     * 内部调用 parse_url() 拆分各组成部分：缺少 scheme 时回退为 https，
     * 缺少 host / path / fragment 时置为空字符串，缺少 port / user / password
     * 时置为 null；query 会经 parse_str() 解析成数组（因此键名中的点和空格
     * 会被转成下划线，"a[]=1" 这类写法会解析成嵌套数组）。
     *
     * 本方法只做拆分，不校验 host 是否合法：传入相对路径（如 "/a/b"）不会报错，
     * 但得到的实例 host 为空、isAbsolute() 为 false；只有严重畸形的串
     * （如 "http:///"）才会因 parse_url() 返回 false 而抛出异常。
     *
     * @param string $url 待解析的 URL 字符串
     * @return self 新的 Url 实例
     * @throws \InvalidArgumentException 当 parse_url() 返回 false（URL 严重畸形）时抛出
     *
     * @example
     * Url::parse('https://example.com/a?x=1#top')->host();     // 'example.com'
     * Url::parse('https://example.com/a?x=1#top')->query();    // ['x' => '1']
     * Url::parse('https://example.com/a?x=1#top')->fragment(); // 'top'
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
     * 创建空白 URL 实例
     *
     * 与 parse() 不同，此方法不接收输入：scheme 为默认的 https，
     * host / path / fragment 为空字符串、query 为空数组、port 与 userInfo 为 null，
     * 适合从零开始用链式调用构建 URL。
     *
     * @return self 空白 Url 实例
     *
     * @example
     * Url::make()->host();   // ''
     * Url::make()->scheme(); // 'https'
     */
    public static function make(): self
    {
        return new self();
    }

    /**
     * 设置协议（scheme）
     *
     * 直接覆盖当前协议，不做合法性校验，也不做小写归一化；
     * 传入空字符串同样会被接受，此时 toString() 会输出形如 "://host" 的畸形串。
     *
     * @param string $scheme 协议名，如 http、https、ftp
     * @return self 返回自身，支持链式调用
     *
     * @example
     * Url::make()->withScheme('http')->scheme(); // 'http'
     */
    public function withScheme(string $scheme): self
    {
        $this->scheme = $scheme;
        return $this;
    }

    /**
     * 设置主机名（host）
     *
     * 直接覆盖当前主机名，不校验格式、不补全端口；host 非空即意味着
     * isAbsolute() 返回 true，因此解析相对地址后可用本方法补上域名。
     *
     * @param string $host 主机名或 IP，如 example.com、127.0.0.1
     * @return self 返回自身，支持链式调用
     *
     * @example
     * Url::make()->withHost('example.com')->isAbsolute(); // true
     */
    public function withHost(string $host): self
    {
        $this->host = $host;
        return $this;
    }

    /**
     * 设置端口
     *
     * 不做范围校验，也不会省略协议默认端口（443 也会显式输出）；
     * 传入 null 表示清除端口，toString() 中不再拼接 ":port"。
     *
     * @param int|null $port 端口号；null 表示移除端口
     * @return self 返回自身，支持链式调用
     *
     * @example
     * Url::parse('https://example.com/')->withPort(8443)->toString(); // 'https://example.com:8443/'
     * Url::parse('https://example.com:8443/')->withPort(null)->toString(); // 'https://example.com/'
     */
    public function withPort(?int $port): self
    {
        $this->port = $port;
        return $this;
    }

    /**
     * 设置路径
     *
     * 整体覆盖当前路径，原样保存：不自动补前导斜杠、不剥离尾部斜杠、
     * 也不做 URL 编码。若需要在已有路径后追加片段，请改用 joinPath()。
     *
     * @param string $path 路径字符串，通常以 "/" 开头，如 /api/users
     * @return self 返回自身，支持链式调用
     *
     * @example
     * Url::parse('https://example.com/old')->withPath('/new')->path(); // '/new'
     */
    public function withPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    /**
     * 追加路径段（自动处理两端斜杠）
     *
     * 先去掉当前路径末尾的 "/"，再去掉新片段开头的 "/"，中间补一个 "/" 拼接。
     * 因此当前路径为空时会得到以 "/" 开头的结果，重复斜杠不会累积。
     *
     * @param string $segment 要追加的路径片段，可带或不带前导 "/"
     * @return self 返回自身，支持链式调用
     *
     * @example
     * Url::parse('https://example.com/api/')->joinPath('/users')->path(); // '/api/users'
     * Url::make()->joinPath('users')->path();                              // '/users'
     */
    public function joinPath(string $segment): self
    {
        $this->path = rtrim($this->path, '/') . '/' . ltrim($segment, '/');
        return $this;
    }

    /**
     * 整体替换查询参数
     *
     * 直接覆盖整个 query 数组，不与已有参数合并（单键合并请用 addQuery()）。
     * 值不需要预编码，输出时统一由 http_build_query() 处理。
     *
     * @param array $query 查询参数键值对，值可为标量或数组
     * @return self 返回自身，支持链式调用
     *
     * @example
     * Url::parse('https://example.com/?a=1')->withQuery(['b' => 2])->query(); // ['b' => 2]
     */
    public function withQuery(array $query): self
    {
        $this->query = $query;
        return $this;
    }

    /**
     * 添加单个查询参数
     *
     * 以键写入，同名键会被新值覆盖而不是追加成数组；值为数组时，
     * 在 getQueryString() 中会展开为 "key[0]=..." 形式。
     *
     * @param string $key 参数名
     * @param mixed $value 参数值，可为标量、数组或 null
     * @return self 返回自身，支持链式调用
     *
     * @example
     * Url::make()->addQuery('page', 2)->addQuery('page', 3)->query(); // ['page' => 3]
     */
    public function addQuery(string $key, mixed $value): self
    {
        $this->query[$key] = $value;
        return $this;
    }

    /**
     * 移除单个查询参数
     *
     * 按 key 从 query 数组中删除；键不存在时静默忽略、不报错。
     * 删除后若 query 变为空数组，toString() 不再输出 "?"。
     *
     * @param string $key 要移除的参数名
     * @return self 返回自身，支持链式调用
     *
     * @example
     * Url::parse('https://example.com/?a=1&b=2')->removeQuery('a')->query(); // ['b' => '2']
     */
    public function removeQuery(string $key): self
    {
        unset($this->query[$key]);
        return $this;
    }

    /**
     * 设置锚点（fragment）
     *
     * 直接覆盖，参数不含 "#"，输出时由 toString() 自动补上；
     * 传入空字符串表示清除锚点（空串不会被输出）。
     *
     * @param string $fragment 锚点内容，不含 "#"
     * @return self 返回自身，支持链式调用
     *
     * @example
     * Url::parse('https://example.com/#old')->withFragment('new')->toString(); // 'https://example.com/#new'
     */
    public function withFragment(string $fragment): self
    {
        $this->fragment = $fragment;
        return $this;
    }

    /**
     * 设置用户信息（用户名与密码）
     *
     * 两者原样保存、不做 URL 编码。用户名为 null 时整段用户信息都不会输出；
     * 用户名为非 null、密码为 null 时只输出 "user@"，即密码是可选的。
     *
     * @param string|null $user 用户名；null 表示清除用户信息
     * @param string|null $password 密码，可选；null 表示不输出密码
     * @return self 返回自身，支持链式调用
     *
     * @example
     * Url::parse('https://example.com/')->withUserInfo('u', 'p')->toString(); // 'https://u:p@example.com/'
     * Url::parse('https://example.com/')->withUserInfo('u')->toString();      // 'https://u@example.com/'
     */
    public function withUserInfo(?string $user, ?string $password = null): self
    {
        $this->user = $user;
        $this->password = $password;
        return $this;
    }

    /**
     * 获取协议（scheme）
     *
     * @return string 当前协议，未显式设置时为默认值 https
     *
     * @example
     * Url::make()->scheme(); // 'https'
     */
    public function scheme(): string
    {
        return $this->scheme;
    }

    /**
     * 获取主机名（host）
     *
     * @return string 当前主机名；未设置或解析结果缺少 host 时返回空字符串
     *
     * @example
     * Url::parse('https://example.com/a')->host(); // 'example.com'
     */
    public function host(): string
    {
        return $this->host;
    }

    /**
     * 获取端口
     *
     * @return int|null 显式设置的端口号；未设置时为 null（不含协议默认端口）
     *
     * @example
     * Url::parse('https://example.com/a')->port();      // null
     * Url::parse('https://example.com:8080/a')->port(); // 8080
     */
    public function port(): ?int
    {
        return $this->port;
    }

    /**
     * 获取路径
     *
     * @return string 当前保存的原始路径字符串，可能为空串，不保证以 "/" 开头
     *
     * @example
     * Url::parse('https://example.com/a/b')->path(); // '/a/b'
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * 获取全部查询参数
     *
     * 返回内部保存的数组，未经 http_build_query() 编码；由 parse() 创建时，
     * 该数组是 parse_str() 的解析结果（同名参数会变成数组，如 "a=1&a=2"）。
     *
     * @return array 查询参数键值对，无参数时为空数组
     *
     * @example
     * Url::parse('https://example.com/?a=1')->query(); // ['a' => '1']
     */
    public function query(): array
    {
        return $this->query;
    }

    /**
     * 获取锚点（fragment）
     *
     * @return string 锚点内容，不含 "#"；未设置时为空字符串
     *
     * @example
     * Url::parse('https://example.com/#top')->fragment(); // 'top'
     */
    public function fragment(): string
    {
        return $this->fragment;
    }

    /**
     * 生成查询字符串
     *
     * 使用 PHP 默认的 http_build_query()（RFC1738 编码）序列化当前 query：
     * 空格编码为 "+"，中文等字符做百分号编码，嵌套数组展开为 "key[i]=v"。
     * 结果不带前导 "?"，query 为空时返回空字符串。
     *
     * @return string 形如 "a=1&b=2" 的查询串，无参数时为空字符串
     *
     * @example
     * Url::parse('https://example.com/?a=1&b=2')->getQueryString(); // 'a=1&b=2'
     * Url::make()->getQueryString();                               // ''
     */
    public function getQueryString(): string
    {
        return http_build_query($this->query);
    }

    /**
     * 转换为完整 URL 字符串
     *
     * 按 scheme://user:password@host:port/path?query#fragment 的顺序拼接：
     * 协议与 "://" 始终输出（host 为空时会得到 "https://" 这类残缺串），
     * path 原样拼接不补斜杠，query 为空不输出 "?"，fragment 为空不输出 "#"，
     * 端口只要非 null 就输出（不省略协议默认端口）。
     *
     * @return string 拼接后的 URL
     *
     * @example
     * Url::parse('https://example.com/a')->addQuery('x', 1)->toString(); // 'https://example.com/a?x=1'
     * Url::make()->withHost('example.com')->toString();                   // 'https://example.com'
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

    /**
     * 字符串上下文转换
     *
     * 供 echo、字符串拼接等场景隐式调用，内部直接委托给 toString()，
     * 两者输出完全一致，且不会抛出异常。
     *
     * @return string 拼接后的 URL
     *
     * @example
     * (string) Url::parse('https://example.com/a'); // 'https://example.com/a'
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * 判断是否为绝对 URL
     *
     * 仅以 host 是否为空作为判据，不检查 scheme：host 非空即为真，
     * 因此 make()->withHost('example.com') 在尚未拼接 path 时也被视作绝对地址。
     *
     * @return bool host 非空返回 true，否则返回 false
     *
     * @example
     * Url::parse('https://example.com/a')->isAbsolute(); // true
     * Url::make()->isAbsolute();                         // false
     */
    public function isAbsolute(): bool
    {
        return $this->host !== '';
    }

    /**
     * 判断是否为安全连接
     *
     * 将 scheme 转小写后与 https、ftps 比较，与端口号无关；
     * 因此 "https://example.com:80" 仍返回 true。
     *
     * @return bool scheme 为 https 或 ftps（不区分大小写）返回 true
     *
     * @example
     * Url::parse('https://example.com/')->isSecure(); // true
     * Url::parse('http://example.com/')->isSecure();  // false
     */
    public function isSecure(): bool
    {
        return in_array(strtolower($this->scheme), ['https', 'ftps'], true);
    }

    /**
     * 获取顶级域名（TLD）
     *
     * 简单按 "." 切分取最后一段，不是公共后缀列表（PSL）感知的：
     * "example.co.uk" 会返回 "uk" 而非 "co.uk"，"example.com" 返回 "com"。
     *
     * @return string|null 最后一段域名；host 为空或只有一段（如 localhost、IP 字面量）时返回 null
     *
     * @example
     * Url::parse('https://example.com/')->topLevelDomain();  // 'com'
     * Url::parse('https://localhost/')->topLevelDomain();    // null
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
     *
     * 与 topLevelDomain() 对应，同样只按 "." 切分、不识别公共后缀：
     * 多于两段时取倒数第二段（"a.b.example.com" → "example"），
     * 不足三段时取第一段（"example.com" → "example"）。
     * 注意单段主机（如 localhost）会原样返回该段。
     *
     * @return string|null 域名主体；host 为空时返回 null
     *
     * @example
     * Url::parse('https://example.com/')->domainName();     // 'example'
     * Url::parse('https://api.example.com/')->domainName(); // 'example'
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
