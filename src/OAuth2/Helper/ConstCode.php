<?php
declare(strict_types=1);

/**
 * 公共常量
 */
namespace zxf\Utils\OAuth2\Helper;

/**
 * 第三方登录渠道与通用状态常量表。
 *
 * 集中定义各 OAuth2 平台的渠道标识（TYPE_*）、通用状态与性别常量，
 * 避免魔法数字散落在业务代码与各 Gateway 中。
 *
 * @package zxf\Utils\OAuth2\Helper
 * @version 1.0.0
 * @license MIT
 *
 * @example
 * ```php
 * ConstCode::TYPE_WECHAT;                        // 2（微信）
 * ConstCode::getTypeConst(ConstCode::TYPE_QQ, 'app'); // 21（QQ App）
 * ```
 */
class ConstCode
{

    /** 公共状态 */
    const STATUS_DELETE = 0;//删除
    const STATUS_NORMAL = 1;//正常

    /** 性别 */
    const GENDER = 0;//未知
    const GENDER_MAN = 1;//男
    const GENDER_WOMEN = 2;//女

    /** 登录类型 */
    const TYPE_QQ               = 1; //QQ
    const TYPE_WECHAT           = 2; //微信
    const TYPE_WECHAT_MOBILE    = 3; //微信mobile
    const TYPE_SINA             = 4; //sina新浪微博
    const TYPE_GITHUB           = 5; //GitHub
    const TYPE_ALIPAY           = 6; //AliPay
    const TYPE_ALIYUN           = 12; //阿里云
    const TYPE_BAIDU            = 13; //百度
    const TYPE_CODING           = 14; //Coding
    const TYPE_CSDN             = 15; //CSDN
    const TYPE_GITEE            = 16; //Gitee
    const TYPE_GITLAB           = 17; //GitLab
    const TYPE_OSCHINA          = 18; //OSChina
    const TYPE_DINGTALK         = 19; //钉钉
    const TYPE_WECOM            = 20; //企业微信
    const TYPE_KUAISHOU         = 41; //快手;
    const TYPE_XIAOMI           = 42; //小米
    const TYPE_HUAWEI           = 43; //华为

    const TYPE_QQ_APP           = 21; //qqAPP
    const TYPE_WECHAT_APP       = 22; //微信APP
    const TYPE_WECHAT_APPLETS   = 23; //微信小程序

    const TYPE_DOUYIN           = 31; //抖音
    const TYPE_TOUTIAO          = 32; //头条
    const TYPE_XIGUA            = 33; //西瓜

    /**
     * 根据渠道与终端类型推导最终的渠道常量。
     *
     * 部分渠道在不同终端下有独立标识（如 QQ 在 App 内为 TYPE_QQ_APP），
     * 本方法完成渠道 → 具体终端常量的映射；无对应映射时原样返回渠道值。
     *
     * 注意：微信渠道在「非 app / 非 applets」且当前处于微信内置浏览器时，
     * 会进一步归为 TYPE_WECHAT_MOBILE（依赖全局函数 is_wechat_browser()）。
     *
     * @param  int           $channel  渠道：登录方式，取值为本类 TYPE_* 常量
     * @param  bool|string   $type     终端类型：''（默认）/'app'/'applets'
     * @return int 推导后的渠道常量
     *
     * @example
     * ```php
     * ConstCode::getTypeConst(ConstCode::TYPE_QQ, 'app');      // 21 (TYPE_QQ_APP)
     * ConstCode::getTypeConst(ConstCode::TYPE_WECHAT, 'app');  // 22 (TYPE_WECHAT_APP)
     * ConstCode::getTypeConst(ConstCode::TYPE_GITHUB, '');     // 5  (原样返回)
     * ```
     */
    static public function getTypeConst(int $channel, bool|string $type=""): int
    {
        switch ($channel){
            case self::TYPE_QQ:
                if($type === 'app'){
                    $typeConst = self::TYPE_QQ_APP;//qqApp
                }else{
                    $typeConst = $channel;
                }
                break;
            case self::TYPE_WECHAT:
                if($type === 'app'){
                    $typeConst = self::TYPE_WECHAT_APP;//微信App
                }else if($type === 'applets'){
                    $typeConst =  self::TYPE_WECHAT_APPLETS;//微信小程序
                }else if(is_wechat_browser()){
                    $typeConst =  self::TYPE_WECHAT_MOBILE;//微信mobile
                }else{
                    $typeConst = $channel;
                }
                break;
            default:
                $typeConst = $channel;
                break;
        }
        return $typeConst;
    }

}
