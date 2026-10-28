<?php
declare(strict_types=1);

/**
 * 钉钉开放平台 https://open.dingtalk.com/
 * api接口文档: https://open.dingtalk.com/document/orgapp-server/obtain-identity-credentials
 */
namespace zxf\Utils\OAuth2\Gateways;

use zxf\Utils\OAuth2\Connector\Gateway;
use zxf\Utils\OAuth2\Exception\OAuthException;
use zxf\Utils\OAuth2\Helper\ConstCode;

/**
 * 钉钉 OAuth2 授权登录网关
 *
 * 对接钉钉开放平台（https://open.dingtalk.com/），采用 OAuth2 授权码模式
 * 获取用户身份凭证。内置钉钉特有的 userAccessToken 获取流程（驼峰参数、JSON 提交），
 * 并提供获取授权跳转地址、用户信息、openid、令牌刷新与校验等能力。
 */
class Dingtalk extends Gateway
{
    const API_BASE = 'https://api.dingtalk.com/';
    protected $AuthorizeURL = 'https://login.dingtalk.com/oauth2/auth';
    protected $AccessTokenURL = 'https://api.dingtalk.com/v1.0/oauth2/userAccessToken';
    protected $UserInfoURL = 'https://api.dingtalk.com/v1.0/contact/users/me';

    /**
     * 得到跳转地址
     *
     * 以钉钉 AuthorizeURL 为基地址拼接查询参数：client_id 取配置的 app_id，
     * redirect_uri 取 callback，scope 缺省为 openid，state 缺省为空串，
     * 并固定携带 prompt=consent 以强制显示授权确认页。
     *
     * @return string 完整的授权跳转 URL，需引导用户浏览器跳转访问
     */
    public function getRedirectUrl()
    {
        $params = [
            'client_id'     => $this->config['app_id'],
            'redirect_uri'  => $this->config['callback'],
            'response_type' => $this->config['response_type'],
            'scope'         => $this->config['scope'] ?: 'openid',
            'state'         => $this->config['state'] ?: '',
            'prompt'        => 'consent'
        ];
        return $this->AuthorizeURL . '?' . http_build_query($params);
    }

    /**
     * 获取当前授权用户的openid标识
     */
    public function openid()
    {
        $token = $this->getAccessToken();
        return $token['openId'] ?? $token['unionId'] ?? '';
    }

    /**
     * 获取格式化后的用户信息
     */
    public function userInfo()
    {
        $result = $this->getUserInfo();

        $userInfo = [
            'open_id'      => $this->openid(),
            'union_id'     => $this->token['unionId'] ?? '',
            'channel'      => ConstCode::TYPE_DINGTALK,
            'nickname'     => $result['nick'] ?? '',
            'gender'       => ConstCode::GENDER,
            'avatar'       => $result['avatarUrl'] ?? '',
            'type'         => ConstCode::getTypeConst(ConstCode::TYPE_DINGTALK, $this->type),
            'access_token' => $this->token['accessToken'] ?? '',
            'native'       => $result
        ];
        return $userInfo;
    }

    /**
     * 获取原始接口返回的用户信息
     */
    public function getUserInfo()
    {
        $token = $this->getAccessToken();

        $headers = [
            'x-acs-dingtalk-access-token' => $token['accessToken'] ?? ''
        ];

        $data = $this->get($this->UserInfoURL, [], $headers);
        if (is_string($data)) {
            $data = json_decode($data, true);
        }
        if (!is_array($data)) {
            $data = [];
        }
        return $data;
    }

    /**
     * 获取access_token（钉钉 v1.0 userAccessToken：body 为驼峰参数，且要求 JSON 提交，
     * 因此不能复用父类 getToken() 的下划线参数流程；此处自带请求与缓存）
     *
     * @return array<string,mixed>
     * @throws OAuthException
     */
    protected function getAccessToken()
    {
        // 已获取过且未刷新时直接复用缓存
        if (!empty($this->token['accessToken'])) {
            return $this->token;
        }

        $params = [
            'clientId'     => $this->config['app_id'],
            'clientSecret' => $this->config['app_secret'],
            'code'         => isset($_REQUEST['code']) ? $_REQUEST['code'] : '',
            'grantType'    => 'authorization_code'
        ];

        $response = $this->post($this->AccessTokenURL, $params);
        if (is_string($response)) {
            $response = json_decode($response, true);
        }

        if (!is_array($response) || !isset($response['accessToken'])) {
            throw new OAuthException('获取钉钉 access_token 出错：' . (is_string($response) ? $response : json_encode($response)));
        }
        $this->token = $response;
        return $this->token;
    }

    /**
     * 检验授权凭证AccessToken是否有效
     *
     * 用该 token 请求用户信息接口，能取到 nick 字段即视为有效；
     * 未传 $accessToken 时取当前已获取的 accessToken。
     * 网络异常、token 失效等任何异常都会被捕获并返回 false，不向外抛出。
     *
     * @param string|null $accessToken 待校验的 accessToken，null 表示使用当前 token
     * @return bool 有效返回 true，无效或请求异常返回 false
     */
    public function validateAccessToken($accessToken = null)
    {
        try {
            $token = $this->getAccessToken();
            $accessToken = $accessToken ?? ($token['accessToken'] ?? '');
            $headers = [
                'x-acs-dingtalk-access-token' => $accessToken
            ];
            $data = $this->get($this->UserInfoURL, [], $headers);
            if (is_string($data)) {
                $data = json_decode($data, true);
            }
            return is_array($data) && isset($data['nick']);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 刷新AccessToken续期
     *
     * 以 grantType=refresh_token 调用 userAccessToken 接口换取新令牌；
     * 成功时把返回的完整响应整体替换到当前 token 缓存，后续调用可直接复用。
     * 响应中不含 accessToken 时不做任何改动，直接返回 false。
     *
     * @param string $refreshToken 刷新令牌，来自首次授权返回的 refreshToken
     * @return bool 刷新成功返回 true，失败返回 false
     */
    public function refreshToken($refreshToken)
    {
        $params = [
            'clientId'     => $this->config['app_id'],
            'clientSecret' => $this->config['app_secret'],
            'grantType'    => 'refresh_token',
            'refreshToken' => $refreshToken,
        ];

        $response = $this->post($this->AccessTokenURL, $params);
        if (is_string($response)) {
            $response = json_decode($response, true);
        }

        if (is_array($response) && isset($response['accessToken'])) {
            $this->token = $response;
            return true;
        }
        return false;
    }
}
