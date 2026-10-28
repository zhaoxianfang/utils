<?php

declare(strict_types=1);

namespace zxf\Utils\OAuth2\Exception;

use Exception;

/**
 * OAuth2 异常类。
 *
 * 继承 PHP 内置 Exception，用于在 OAuth2 授权流程中（如配置缺失、
 * state 校验失败、换取 token 失败等场景）抛出异常，并可携带第三方返回的
 * 原始响应数据，便于上层定位问题。
 *
 * @package zxf\Utils\OAuth2\Exception
 * @version 1.0.0
 * @license MIT
 */
class OAuthException extends Exception
{
    /**
     * 第三方接口返回的原始数据（响应体或解析后的数组），用于排查错误原因。
     * @var mixed
     */
    protected $raw;

    /**
     * 构造 OAuth2 异常。
     *
     * 在调用父类构造设置消息与错误码的同时，保存第三方返回的原始数据。
     *
     * @param string $message 异常描述信息
     * @param int $code 错误码，默认 0
     * @param mixed $raw 第三方返回的原始数据，默认 null
     */
    public function __construct($message, $code = 0, $raw = null)
    {
        parent::__construct($message, $code);
        $this->raw = $raw;
    }

    /**
     * 获取第三方接口返回的原始数据。
     *
     * @return mixed 构造时传入的 raw 数据
     */
    public function getRaw()
    {
        return $this->raw;
    }
}
