<?php
/**
 * 阿里云
 * api接口文档
 *      https://help.aliyun.com/zh/ram/user-guide/overview-of-oauth-applications
*/
namespace zxf\Utils\OAuth2\Gateways;
use zxf\Utils\OAuth2\Connector\Gateway;
use zxf\Utils\OAuth2\Exception\OAuthException;
use zxf\Utils\OAuth2\Helper\ConstCode;

/**
 * Class Aliyun
 * @package zxf\Utils\OAuth2\Gateways
 * *
 * @Created: 2023/07/09
 */
class Aliyun extends Gateway
{
    const API_BASE            = 'https://signin.aliyun.com/';
    protected $AuthorizeURL   = 'https://signin.aliyun.com/oauth2/v1/auth';
    protected $AccessTokenURL = 'https://oauth.aliyun.com/v1/token';
    protected $UserInfoURL = 'https://oauth.aliyun.com/v1/userinfo';

    /**
     * Description:  得到跳转地址
     *
     * @return string
     */
    public function getRedirectUrl()
    {
        //存储state
        $this->saveState();
        //登录参数
        $params = [
            'response_type' => $this->config['response_type'],
            'client_id'     => $this->config['app_id'],
            'redirect_uri'  => $this->config['callback'],
            'state'         => $this->config['state'],
            'scope'         => $this->config['scope'],
        ];
        return $this->AuthorizeURL . '?' . http_build_query($params);
    }

    /**
     * Description:  获取格式化后的用户信息
     * @return array
     * @throws OAuthException
     *
     */
    public function userInfo()
    {
        $result = $this->getUserInfo();
        $userInfo = [
            'open_id' => isset($result['uid']) ? $result['uid'] : '',
            'union_id'=> isset($result['aid']) ? $result['aid'] : '',
            'channel' => ConstCode::TYPE_ALIYUN,
            'nickname'=> $result['login_name'],
            'gender'  => ConstCode::GENDER,
            'avatar'  => '',
            'birthday'=> '',
            'access_token'=> $this->token['access_token'] ?? '',
            'native'=> $result,
        ];
        return $userInfo;
    }

    /**
     * Description:  获取原始接口返回的用户信息
     * @return array
     * @throws OAuthException
     *
     */
    public function getUserInfo()
    {
        /** 获取用户信息 */
        $this->openid();

        $headers = ['Authorization: Bearer '.$this->token['access_token']];
        $data = $this->get($this->UserInfoURL, [],$headers);
        if(is_string($data)){
            $data = json_decode($data, true);
        }
        return $data;
    }

    /**
     * Description:  获取当前授权用户的openid标识
     *
     * @return mixed
     * @throws OAuthException
     */
    public function openid()
    {
        $this->getToken();
    }


    /**
     * Description:  获取AccessToken
     *
     */
    protected function getToken(){
        if (empty($this->token)) {
            /** 验证state参数 */
            $this->CheckState();

            /** 获取参数 */
            $params = $this->accessTokenParams();

            /** 获取access_token */
            $this->AccessTokenURL = $this->AccessTokenURL . '?' . http_build_query($params);
            $token =  $this->post($this->AccessTokenURL);
            /** 解析token值(子类实现此方法) */
            $this->token = $this->parseToken($token);
        }
    }

    /**
     * Description:  解析access_token方法请求后的返回值
     *
     * @param $token
     * @return mixed
     * @throws OAuthException
     */
    protected function parseToken($token)
    {
        $data = json_decode($token, true);
        if (isset($data['access_token'])) {
            return $data;
        } else {
            throw new OAuthException("获取Aliyun ACCESS_TOKEN出错：{$data['error']}");
        }
    }

}
