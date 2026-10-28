<?php
declare(strict_types=1);

/**
 * 快手开放平台 https://open.kuaishou.com/
 * api接口文档: https://open.kuaishou.com/platform/docs/api-overview
 */
namespace zxf\Utils\OAuth2\Gateways;

use zxf\Utils\OAuth2\Connector\Gateway;
use zxf\Utils\OAuth2\Exception\OAuthException;
use zxf\Utils\OAuth2\Helper\ConstCode;

/**
 * 快手 OAuth2 授权登录网关
 *
 * 对接快手开放平台，采用 OAuth2 授权码模式获取用户身份凭证。
 * 因快手 openapi 的参数名（app_id/app_secret）与父类不一致，自带 access_token 获取流程。
 * 提供授权跳转、用户信息、openid、令牌刷新与校验等能力。
 */
class Kuaishou extends Gateway
{
    const API_BASE = 'https://open.kuaishou.com/';
    protected $AuthorizeURL = 'https://open.kuaishou.com/oauth2/authorize';
    protected $AccessTokenURL = 'https://open.kuaishou.com/oauth2/access_token';
    protected $UserInfoURL = 'https://open.kuaishou.com/openapi/user_info';

    /**
     * 得到跳转地址
     *
     * 生成前先调用 saveState() 落地 state 供回调校验；使用快手要求的
     * app_id 参数名（而非 client_id），scope 缺省为 user_info，state 缺省为空串。
     *
     * @return string 完整的授权跳转 URL，需引导用户浏览器跳转访问
     */
    public function getRedirectUrl()
    {
        $this->saveState();
        $params = [
            'app_id'        => $this->config['app_id'],
            'redirect_uri'  => $this->config['callback'],
            'response_type' => $this->config['response_type'],
            'scope'         => $this->config['scope'] ?: 'user_info',
            'state'         => $this->config['state'] ?: '',
        ];
        return $this->AuthorizeURL . '?' . http_build_query($params);
    }

    /**
     * 获取当前授权用户的openid标识
     */
    public function openid()
    {
        // 快手 token 响应自带 open_id；必须走 getAccessToken（快手自有的 token 流程），
        // 父类 getToken() 的参数名与快手 openapi 不匹配
        $this->getAccessToken();
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
            'channel'      => ConstCode::TYPE_KUAISHOU,
            'nickname'     => $result['name'] ?? '',
            'gender'       => $result['sex'] ?? ConstCode::GENDER,
            'avatar'       => $result['head'] ?? '',
            'type'         => ConstCode::getTypeConst(ConstCode::TYPE_KUAISHOU, $this->type),
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
        $this->getAccessToken();

        $params = [
            'app_id'       => $this->config['app_id'],
            'access_token' => $this->token['access_token'] ?? '',
        ];
        
        $data = $this->get($this->UserInfoURL, $params);
        if(is_string($data)){
            $data = json_decode($data, true);
        }
        
        if (!isset($data['result'])) {
            throw new OAuthException('获取快手用户信息失败：' . json_encode($data));
        }
        return $data['result'];
    }

    /**
     * 获取access_token
     */
    protected function getAccessToken()
    {
        // 已获取且未刷新时复用缓存
        if (!empty($this->token['access_token'])) {
            return $this->token;
        }

        $params = [
            'app_id'       => $this->config['app_id'],
            'app_secret'   => $this->config['app_secret'],
            'code'         => isset($_REQUEST['code']) ? $_REQUEST['code'] : '',
            'grant_type'   => 'authorization_code',
        ];
        
        $response = $this->post($this->AccessTokenURL, $params);
        if(is_string($response)){
            $response = json_decode($response, true);
        }
        
        if (!is_array($response) || !isset($response['access_token'])) {
            throw new OAuthException('获取快手 access_token 出错：' . json_encode($response));
        }
        $this->token = $response;
        return $this->token;
    }

    /**
     * 检验授权凭证AccessToken是否有效
     *
     * 携带 access_token 请求用户信息接口，响应中含 result 字段即视为有效；
     * 未传 $accessToken 时使用当前 token 缓存。任何异常都会被捕获并返回 false，
     * 注意即使 token 无效，只要接口返回结构正常也可能被判为有效。
     *
     * @param string|null $accessToken 待校验的 accessToken，null 表示使用当前 token
     * @return bool 有效返回 true，无效或请求异常返回 false
     */
    public function validateAccessToken($accessToken = null)
    {
        try {
            $accessToken = $accessToken ?? ($this->token['access_token'] ?? '');
            $params = [
                'app_id'       => $this->config['app_id'],
                'access_token' => $accessToken,
            ];
            $data = $this->get($this->UserInfoURL, $params);
            if(is_string($data)){
                $data = json_decode($data, true);
            }
            return isset($data['result']);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 刷新AccessToken续期
     *
     * 以 grant_type=refresh_token 换取新令牌，成功后整体替换当前 token 缓存；
     * 响应不含 access_token 时不做修改并返回 false（不抛异常）。
     *
     * @param string $refreshToken 刷新令牌，来自首次授权返回的 refresh_token
     * @return bool 刷新成功返回 true，失败返回 false
     */
    public function refreshToken($refreshToken)
    {
        $params = [
            'app_id'        => $this->config['app_id'],
            'app_secret'    => $this->config['app_secret'],
            'refresh_token' => $refreshToken,
            'grant_type'    => 'refresh_token',
        ];
        
        $response = $this->post($this->AccessTokenURL, $params);
        if(is_string($response)){
            $response = json_decode($response, true);
        }
        
        if (isset($response['access_token'])) {
            $this->token = $response;
            return true;
        }
        return false;
    }
}