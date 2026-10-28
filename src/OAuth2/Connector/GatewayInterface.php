<?php
declare(strict_types=1);

namespace zxf\Utils\OAuth2\Connector;

/**
 * 第三方登录网关必须实现的接口。
 *
 * 定义了 OAuth2 授权登录流程中的统一契约：获取授权跳转地址、获取 openid、
 * 获取（原始 / 格式化）用户信息、刷新 token 与校验 token 有效性。
 * 具体平台连接器（如 QQ、微信）均需实现这些方法，以保证上层调用方式一致。
 *
 * @package zxf\Utils\OAuth2\Connector
 * @version 1.0.0
 * @license MIT
 */
interface GatewayInterface
{

    /**
     * 获取授权跳转地址（拼接 client_id、redirect_uri、state 等参数后的授权 URL）。
     *
     * @return string 引导用户前往第三方授权页面的完整 URL
     */
    public function getRedirectUrl();

    /**
     * 获取当前授权用户的 openid 标识（第三方平台下唯一用户标识）。
     *
     * @return string 用户的 openid
     */
    public function openid();

    /**
     * 获取格式化后的用户信息（统一字段结构，便于业务侧直接使用）。
     *
     * @return array 格式化后的用户数据，如 openid、nickname、avatar 等
     */
    public function userInfo();

    /**
     * 获取第三方接口返回的原始用户信息（未做格式转换）。
     *
     * @return array 第三方返回的原始用户数据
     */
    public function getUserInfo();


    /**
     * 刷新 AccessToken（续期）。
     *
     * @param string $refreshToken 用于刷新 token 的 refresh_token
     * @return bool 刷新是否成功
     */
    public function refreshToken($refreshToken);

    /**
     * 校验授权凭证 AccessToken 是否有效。
     *
     * @param string|null $accessToken 待校验的 access_token，留空时由实现自行取用
     * @return bool 凭证是否有效
     */
    public function validateAccessToken($accessToken = null);

}
