<?php
declare(strict_types=1);

/**
 * 企业微信开放平台 https://work.weixin.qq.com/api/doc/
 * api接口文档: https://work.weixin.qq.com/api/doc/90000/90135/91022
 */
namespace zxf\Utils\OAuth2\Gateways;

use zxf\Utils\OAuth2\Connector\Gateway;
use zxf\Utils\OAuth2\Exception\OAuthException;
use zxf\Utils\OAuth2\Helper\ConstCode;

/**
 * 企业微信 OAuth2 授权登录网关
 *
 * 对接企业微信开放平台（扫码授权登录），采用 OAuth2 授权码模式获取用户身份。
 * 企业微信的 access_token 由 corpid/corpsecret 换取，自带获取流程。
 * 提供授权跳转、用户信息、userid（openid）、令牌校验等能力。
 */
class Wecom extends Gateway
{
    const API_BASE = 'https://qyapi.weixin.qq.com/';
    protected $AuthorizeURL = 'https://open.work.weixin.qq.com/wwopen/sso/qrConnect';
    protected $AccessTokenURL = 'https://qyapi.weixin.qq.com/cgi-bin/gettoken';
    protected $UserInfoURL = 'https://qyapi.weixin.qq.com/cgi-bin/user/getuserinfo';

    /**
     * 得到跳转地址
     *
     * 生成企业微信扫码登录地址：先调用 saveState() 保存 state 供回调校验，
     * 参数使用企业微信要求的 appid/agentid 命名（不是 client_id），
     * agentid 取配置中的 agent_id 且缺省为空串，state 缺省也为空串。
     *
     * @return string 完整的扫码授权跳转 URL，需引导用户浏览器跳转访问
     */
    public function getRedirectUrl()
    {
        // 扫码授权后回调会带 state，需先保存以便校验
        $this->saveState();

        $params = [
            'appid'         => $this->config['app_id'],
            'agentid'       => $this->config['agent_id'] ?? '',
            'redirect_uri'  => $this->config['callback'],
            'state'         => $this->config['state'] ?: '',
        ];
        return $this->AuthorizeURL . '?' . http_build_query($params);
    }

    /**
     * 获取当前授权用户的openid标识
     */
    public function openid()
    {
        $result = $this->getUserInfo();
        return $result['userid'] ?? '';
    }

    /**
     * 获取格式化后的用户信息
     */
    public function userInfo()
    {
        $result = $this->getUserInfo();

        $userInfo = [
            'open_id'      => $result['userid'] ?? '',
            'union_id'     => '',
            'channel'      => ConstCode::TYPE_WECOM,
            'nickname'     => $result['userid'] ?? '',
            'gender'       => ConstCode::GENDER,
            'avatar'       => '',
            'type'         => ConstCode::getTypeConst(ConstCode::TYPE_WECOM, $this->type),
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
        // 企业微信的 access_token 由 corpid/corpsecret 换取（getAccessToken），与授权码 code 无关；
        // 不能走父类 getToken() —— 其参数名与 state 校验流程与 qyapi 不匹配，会直接抛错
        $access = $this->getAccessToken();
        $this->token['access_token'] = $access['access_token'] ?? '';

        $params = [
            'access_token' => $this->token['access_token'],
            'code'         => isset($_REQUEST['code']) ? $_REQUEST['code'] : '',
        ];
        
        $data = $this->get($this->UserInfoURL, $params);
        if(is_string($data)){
            $data = json_decode($data, true);
        }
        
        if (!isset($data['userid'])) {
            throw new OAuthException('获取企业微信用户信息失败：' . json_encode($data));
        }
        return $data;
    }

    /**
     * 获取access_token
     */
    protected function getAccessToken()
    {
        $params = [
            'corpid'     => $this->config['app_id'],
            'corpsecret' => $this->config['app_secret'],
        ];

        $data = $this->get($this->AccessTokenURL, $params);
        if(is_string($data)){
            $data = json_decode($data, true);
        }
        
        if (!isset($data['access_token'])) {
            throw new OAuthException('获取企业微信 access_token 出错：' . json_encode($data));
        }
        return $data;
    }

    /**
     * 检验授权凭证AccessToken是否有效
     *
     * 用一个必然失效的 code 去请求用户信息接口：若 token 有效，企业微信会返回
     * errcode=40029（code 无效），据此判定 token 有效；未传 $accessToken 时
     * 使用当前 token 缓存。任何异常都会被捕获并返回 false。
     * 响应结构异常（如为空、无 errcode）时只会有告警并按无效处理。
     *
     * @param string|null $accessToken 待校验的 accessToken，null 表示使用当前 token
     * @return bool 有效返回 true，无效或请求异常返回 false
     */
    public function validateAccessToken($accessToken = null)
    {
        try {
            $accessToken = $accessToken ?? ($this->token['access_token'] ?? '');
            $params = [
                'access_token' => $accessToken,
                'code'        => 'fake_code'
            ];
            $data = $this->get($this->UserInfoURL, $params);
            if(is_string($data)){
                $data = json_decode($data, true);
            }
            return $data['errcode'] === 40029;  // 无效的code但token有效
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 刷新AccessToken续期
     *
     * 企业微信不提供 refresh_token 机制，内部直接按 corp id/secret 重新获取
     * 一个新的 access_token 并写入 $this->token；入参 $refreshToken 实际未被
     * 使用，仅为保持网关接口一致。
     *
     * 返回值与其他网关统一为 bool（旧实现直接返回响应数组，与网关接口声明的
     * bool 语义不一致），令牌内容请通过 getToken() 读取。
     *
     * @param string $refreshToken 刷新令牌，本网关忽略该参数
     * @return bool 成功获取并写入新令牌时返回 true
     *
     * @throws OAuthException 重新获取令牌失败时抛出
     */
    public function refreshToken($refreshToken)
    {
        $this->token = $this->getAccessToken();

        return true;
    }
}