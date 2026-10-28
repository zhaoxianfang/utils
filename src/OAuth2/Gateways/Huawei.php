<?php
declare(strict_types=1);

/**
 * 华为开发者联盟 https://developer.huawei.com/consumer/cn/doc/development/HMSCore-Guides/dev-process-0000001050123523
 * api接口文档: https://developer.huawei.com/consumer/cn/doc/development/HMSCore-References/server-api-api-0000001050123599
 */
namespace zxf\Utils\OAuth2\Gateways;

use zxf\Utils\OAuth2\Connector\Gateway;
use zxf\Utils\OAuth2\Exception\OAuthException;
use zxf\Utils\OAuth2\Helper\ConstCode;

/**
 * 华为 OAuth2 授权登录网关
 *
 * 对接华为开发者联盟（HMS Core 账号体系），采用 OAuth2 授权码模式
 * 获取用户身份凭证。提供授权跳转、用户信息、openid、令牌刷新与校验等能力，
 * 支持 offline 模式换取 refresh_token 实现续期。
 */
class Huawei extends Gateway
{
    const API_BASE = 'https://oauth-login.cloud.huawei.com/';
    protected $AuthorizeURL = 'https://oauth-login.cloud.huawei.com/oauth2/v3/authorize';
    protected $AccessTokenURL = 'https://oauth-login.cloud.huawei.com/oauth2/v3/token';
    protected $UserInfoURL = 'https://oauth-api.cloud.huawei.com/rest.php';

    /**
     * 得到跳转地址
     *
     * 生成前会先调用 saveState() 把 state 落地，供回调时校验防 CSRF；
     * 参数中固定携带 access_type=offline，以便授权后能拿到 refresh_token 续期。
     * scope 缺省为 "openid profile"。
     *
     * @return string 完整的授权跳转 URL，需引导用户浏览器跳转访问
     */
    public function getRedirectUrl()
    {
        $this->saveState();
        $params = [
            'client_id'     => $this->config['app_id'],
            'redirect_uri'  => $this->config['callback'],
            'response_type' => $this->config['response_type'],
            'scope'         => $this->config['scope'] ?: 'openid profile',
            'state'         => $this->config['state'],
            'access_type'   => 'offline',
        ];
        return $this->AuthorizeURL . '?' . http_build_query($params);
    }

    /**
     * 获取当前授权用户的openid标识
     */
    public function openid()
    {
        $this->getToken();
        return $this->token['open_id'] ?? '';
    }

    /**
     * 获取格式化后的用户信息
     */
    public function userInfo()
    {
        $result = $this->getUserInfo();
        
        $userInfo = [
            'open_id'      => $this->openid(),
            'union_id'     => $this->token['union_id'] ?? '',
            'channel'      => ConstCode::TYPE_HUAWEI,
            'nickname'     => $result['displayName'] ?? '',
            'gender'       => ConstCode::GENDER,
            'avatar'       => $result['headPictureURL'] ?? '',
            'access_token' => $this->token['access_token'] ?? '',
            'native'       => $result
        ];
        return $userInfo;
    }

    /**
     * 获取原始接口返回的用户信息
     */
    public function getUserInfo()
    {
        $this->getToken();
        
        $params = [
            'method'       => 'oauth2/v3/userinfo',
            'access_token' => $this->token['access_token'],
            'nsp_ts'      => time(),
        ];
        
        $data = $this->get($this->UserInfoURL, $params);
        if(is_string($data)){
            $data = json_decode($data, true);
        }
        
        if (!isset($data['displayName'])) {
            throw new OAuthException('获取华为用户信息失败：' . json_encode($data));
        }
        return $data;
    }

    /**
     * 获取access_token
     */
    protected function getToken()
    {
        if (empty($this->token)) {
            $this->checkState();
            $params = [
                'client_id'     => $this->config['app_id'],
                'client_secret' => $this->config['app_secret'],
                'grant_type'    => $this->config['grant_type'],
                'code'          => isset($_REQUEST['code']) ? $_REQUEST['code'] : '',
                'redirect_uri'  => $this->config['callback'],
            ];
            $response = $this->post($this->AccessTokenURL, $params);
            $this->token = $this->parseToken($response);
        }
    }

    /**
     * 解析token
     *
     * 把接口返回的 JSON 字符串解码为数组，含 access_token 字段时返回该数组，
     * 否则抛出 OAuthException（异常信息中附带原始响应，便于排查）。
     * 期望入参为字符串，若上游已返回数组则 json_decode 会因类型不符报错。
     *
     * @param string $token 令牌接口返回的 JSON 字符串
     * @return array<string, mixed> 解析后的令牌数据（含 access_token 等字段）
     *
     * @throws OAuthException 响应非 JSON 或不含 access_token 时抛出
     */
    protected function parseToken($token)
    {
        $data = json_decode($token, true);
        if (isset($data['access_token'])) {
            return $data;
        }
        throw new OAuthException('获取华为 access_token 出错：' . json_encode($data));
    }

    /**
     * 检验授权凭证AccessToken是否有效
     *
     * 调用华为 tokeninfo 接口查询该 token 的信息，返回结果中含 scope 字段即
     * 视为有效；未传 $accessToken 时使用当前 token 缓存中的 access_token。
     * 任何异常都会被捕获并返回 false。
     *
     * @param string|null $accessToken 待校验的 accessToken，null 表示使用当前 token
     * @return bool 有效返回 true，无效或请求异常返回 false
     */
    public function validateAccessToken($accessToken = null)
    {
        try {
            $accessToken = $accessToken ?? ($this->token['access_token'] ?? '');
            $params = [
                'method'       => 'oauth2/v3/tokeninfo',
                'access_token' => $accessToken,
                'nsp_ts'      => time(),
            ];
            $data = $this->get($this->UserInfoURL, $params);
            if(is_string($data)){
                $data = json_decode($data, true);
            }
            return isset($data['scope']);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 刷新AccessToken续期
     *
     * 以 grant_type=refresh_token 调用令牌接口换取新令牌，成功时整体替换
     * 当前 token 缓存。注意本方法直接调用 parseToken()，若接口返回错误
     * （不含 access_token）会抛异常，而不是走到 return false 分支。
     *
     * @param string $refreshToken 刷新令牌，需在授权时以 offline 模式取得
     * @return bool 刷新成功返回 true
     *
     * @throws OAuthException 刷新失败或响应不含 access_token 时抛出
     */
    public function refreshToken($refreshToken)
    {
        $params = [
            'client_id'     => $this->config['app_id'],
            'client_secret' => $this->config['app_secret'],
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refreshToken,
        ];
        
        $token = $this->post($this->AccessTokenURL, $params);
        $token = $this->parseToken($token);
        
        if (isset($token['access_token'])) {
            $this->token = $token;
            return true;
        }
        return false;
    }
}