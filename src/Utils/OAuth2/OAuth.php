<?php
/**
 * 第三方登陆实例抽象类
 *
 */
namespace zxf\Utils\OAuth2;

use zxf\Utils\OAuth2\Connector\GatewayInterface;
use zxf\Utils\OAuth2\Exception\OAuthException;
use zxf\Utils\OAuth2\Gateways;
use zxf\Utils\OAuth2\Helper\Str;
/**
 * @method static Gateways\Alipay Alipay(array $config) 阿里云
 * @method static Gateways\Wechat wechat(array $config) 微信
 * @method static Gateways\Wechat weixin(array $config) 微信
 * @method static Gateways\Qq Qq(array $config) QQ
 * @method static Gateways\Github Github(array $config) Github
 * @method static Gateways\Sina Sina(array $config) Sina
 * @method static Gateways\Douyin Douyin(array $config) 抖音
 * @method static Gateways\Baidu Baidu(array $config) 百度
 * @method static Gateways\Coding Coding(array $config) Coding
 * @method static Gateways\Csdn Csdn(array $config) CSDN
 * @method static Gateways\Gitee Gitee(array $config) Gitee
 * @method static Gateways\Gitlab GitLab(array $config) GitLab
 * @method static Gateways\Oschina OSChina(array $config) OSChina
 * @method static Gateways\Wecom Wecom(array $config) 企业微信
 * @method static Gateways\Kuaishou Kuaishou(array $config) 快手
 */
abstract class OAuth
{

    /**
     * Description:  init
     *
     * @param $gateway
     * @param null $config
     * @return mixed
     * @throws OAuthException
     */
    protected static function init($gateway, $config)
    {
        if(empty($config)){
            throw new OAuthException("第三方登录 [$gateway] config配置不能为空");
        }
        $baseConfig = [
            'app_id'    => '',
            'app_secret'=> '',
            'callback'  => '',
            'scope'     => '',
            'type'      => '',
        ];
        if($gateway == 'weixin'){
            /** 兼容 zxf\Util/oauth v1.0.0完美升级 */
            $gateway = 'wechat';
        }
        $gateway = Str::uFirst($gateway);
        $class = __NAMESPACE__ . '\\Gateways\\' . $gateway;
        if (class_exists($class)) {
            $app = new $class(array_replace_recursive($baseConfig,$config));
            if ($app instanceof GatewayInterface) {
                return $app;
            }
            throw new OAuthException("第三方登录基类 [$gateway] 必须继承抽象类 [GatewayInterface]");
        }
        throw new OAuthException("第三方登录基类 [$gateway] 不存在");
    }

    /**
     * Description:  __callStatic
     *
     * @param $gateway
     * @param $config
     * @return mixed
     * @throws OAuthException
     */
    public static function __callStatic($gateway, $config)
    {
        return self::init($gateway, ...$config);
    }

}
