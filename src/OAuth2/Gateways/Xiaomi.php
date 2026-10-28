<?php
declare(strict_types=1);

/**
 * 小米开放平台 https://dev.mi.com/console/doc/detail?pId=711
 * api接口文档: https://dev.mi.com/console/doc/detail?pId=707
 */
namespace zxf\Utils\OAuth2\Gateways;

use zxf\Utils\OAuth2\Connector\Gateway;
use zxf\Utils\OAuth2\Exception\OAuthException;
use zxf\Utils\OAuth2\Helper\ConstCode;

/**
 * 小米 OAuth2 授权登录网关
 *
 * 对接小米开放平台账号体系，采用 OAuth2 授权码模式获取用户身份凭证。
 * 提供授权跳转、用户信息、user_id（openid）、性别映射、令牌刷新与校验等能力。
 */
class Xiaomi extends Gateway
{
    const API_BASE = 'https://open.account.xiaomi.com/';
    protected $AuthorizeURL = 'https://account.xiaomi.com/oauth2/authorize';
    protected $AccessTokenURL = 'https://account.xiaomi.com/oauth2/token';
    protected $UserInfoURL = 'https://open.account.xiaomi.com/user/profile';

    /**
     * 得到跳转地址
     *
     * 生成前先调用 saveState() 保存 state 供回调校验；scope 缺省为 profile，
     * 并固定携带 skip_confirm=1 跳过二次确认页，直接进入授权流程。
     * state 直接取自配置，配置中未设置该键时会产生未定义索引告警。
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
            'scope'         => $this->config['scope'] ?: 'profile',
            'state'         => $this->config['state'] ?? '',
            'skip_confirm'  => 1,
        ];
        return $this->AuthorizeURL . '?' . http_build_query($params);
    }

    /**
     * 获取当前授权用户的openid标识
     */
    public function openid()
    {
        $this->getToken();
        return $this->token['user_id'] ?? '';
    }

    /**
     * 获取格式化后的用户信息
     */
    public function userInfo()
    {
        $result = $this->getUserInfo();
        
        $userInfo = [
            'open_id'      => $this->openid(),
            'union_id'     => $this->token['user_id'] ?? '',
            'channel'      => ConstCode::TYPE_XIAOMI,
            'nickname'     => $result['miliaoNick'] ?? '',
            'gender'       => $this->getGender($result['sex'] ?? ''),
            'avatar'       => $result['miliaoIcon'] ?? '',
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
            'clientId'     => $this->config['app_id'],
            'token'        => $this->token['access_token'],
        ];
        
        $data = $this->get($this->UserInfoURL, $params);
        if(is_string($data)){
            $data = json_decode($data, true);
        }
        
        if (!isset($data['result']) || $data['result'] !== 'ok') {
            throw new OAuthException('获取小米用户信息失败：' . json_encode($data));
        }
        return $data['data'];
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
     * 把令牌接口返回的 JSON 字符串解码为数组，含 access_token 时原样返回，
     * 否则抛出 OAuthException 并附带原始响应。入参期望为字符串，
     * 若上游返回的已是数组，json_decode 会因类型不符而报错。
     *
     * @param string $token 令牌接口返回的 JSON 字符串
     * @return array<string, mixed> 解析后的令牌数据
     *
     * @throws OAuthException 响应非 JSON 或不含 access_token 时抛出
     */
    protected function parseToken($token)
    {
        $data = json_decode($token, true);
        if (isset($data['access_token'])) {
            return $data;
        }
        throw new OAuthException('获取小米 access_token 出错：' . json_encode($data));
    }

    /**
     * 获取性别
     *
     * 把小米接口返回的 sex 值映射为项目统一的性别常量：'1' 对应男、
     * '0' 对应女，其余取值（含空串、未来新增的枚举）一律回退为未知性别常量。
     *
     * @param string $gender 上游返回的性别标识，如 '1'、'0'
     * @return string 性别常量（ConstCode::GENDER_MAN / GENDER_WOMEN / GENDER）
     */
    public function getGender($gender)
    {
        $map = [
            '1' => ConstCode::GENDER_MAN,
            '0' => ConstCode::GENDER_WOMEN,
        ];
        return $map[$gender] ?? ConstCode::GENDER;
    }

    /**
     * 检验授权凭证AccessToken是否有效
     *
     * 携带 token 请求用户资料接口，返回 result 为 'ok' 时判定有效
     * （与 getUserInfo() 的判定口径一致）；未传 $accessToken 时使用当前缓存。
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
                'clientId' => $this->config['app_id'],
                'token'    => $accessToken,
            ];
            $data = $this->get($this->UserInfoURL, $params);
            if(is_string($data)){
                $data = json_decode($data, true);
            }
            return isset($data['result']) && $data['result'] === 'ok';
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 刷新AccessToken续期
     *
     * 以 grant_type=refresh_token 换取新令牌，成功后整体替换当前 token 缓存。
     * 令牌接口返回错误时会由 parseToken() 抛异常，因此 return false 分支
     * 实际极少走到。
     *
     * @param string $refreshToken 刷新令牌，来自首次授权返回的 refresh_token
     * @return bool 刷新成功返回 true，失败返回 false
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