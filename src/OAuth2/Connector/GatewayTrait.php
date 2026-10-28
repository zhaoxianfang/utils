<?php
declare(strict_types=1);

namespace zxf\Utils\OAuth2\Connector;

use zxf\Utils\Http\Curl;

/**
 * 网关通用 HTTP 请求与缓存能力 Trait。
 *
 * 为 Gateway 提供 GET / POST 请求封装、请求头管理以及 token 缓存的读写能力，
 * 避免各平台连接器重复实现底层通信逻辑。
 *
 * @package zxf\Utils\OAuth2\Connector
 * @version 1.0.0
 * @license MIT
 */
trait GatewayTrait
{

    /**
     * 请求头数组，可被子类追加自定义头部（如 Content-Type、Authorization 等）。
     * @var array
     */
    protected $headers = [];

    /**
     * 获取当前设置的请求头。
     *
     * @return array 请求头键值对数组
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * 缓存处理器实例（实现 get / set 接口的缓存对象，如 Redis、文件缓存等）。
     * @var mixed
     */
    protected $cache;

    /**
     * 执行 GET 请求操作。
     *
     * @param string $url 请求地址
     * @param array $params 查询参数（键值对）
     * @param array $headers 自定义请求头
     * @return string 接口返回的响应内容
     */
    protected function get($url, $params = [], $headers = [])
    {
        return Curl::instance()->setParams($params)->setHeader($headers)->get($url);
    }

    /**
     * 执行 POST 请求操作。
     *
     * @param string $url 请求地址
     * @param array $params 表单 / 请求体参数（键值对）
     * @param array $headers 自定义请求头
     * @return mixed 接口返回的响应内容
     */
    protected function post($url, $params = [], $headers = [])
    {
        return Curl::instance()->setParams($params)->setHeader($headers)->post($url);
    }


    /**
     * 设置缓存处理器。
     *
     * @param mixed $cache 实现 get / set 接口的缓存实例
     * @return static 返回当前实例，支持链式调用
     */
    public function setCache($cache)
    {
        $this->cache = $cache;
        return $this;
    }

    /**
     * 从缓存中读取 token。
     *
     * 未配置缓存处理器时返回 null。
     *
     * @param string $key 缓存键名
     * @return mixed|null 缓存的 token，未命中或无可缓存处理器时返回 null
     */
    protected function getTokenFromCache($key)
    {
        return $this->cache ? $this->cache->get($key) : null;
    }

    /**
     * 将 token 写入缓存。
     *
     * 仅在配置了缓存处理器时生效；建议传入 expires 与 token 有效期保持一致。
     *
     * @param string $key 缓存键名
     * @param mixed $token 待缓存的 token 数据
     * @param int $expires 缓存有效期（秒），默认 7200
     * @return void
     */
    protected function setTokenToCache($key, $token, $expires = 7200)
    {
        if ($this->cache) {
            $this->cache->set($key, $token, $expires);
        }
    }

}
