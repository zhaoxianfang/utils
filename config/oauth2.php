<?php

use zxf\Utils\OAuth2\Helper\ConstCode;

return [
    /**
     * 腾讯QQ
     *
     * QQ现获取`unionid`，详见: http://wiki.connect.qq.com/unionid%E4%BB%8B%E7%BB%8D
     * 只需要配置参数`$config['is_unioid'] = true`，默认不会请求获取Unionid
     */
    'qq' => [
        'default' => [
            'app_id' => env('OAUTH_QQ_APP_ID', ''),
            'app_secret' => env('OAUTH_QQ_APP_SECRET', ''),
            'callback' => env('OAUTH_QQ_CALLBACK', ''),
            'scope' => 'get_user_info',
            'is_unioid' => true, // 是否已申请 开通unioid
        ],
        'mobile' => [
        ],
    ],
    // 微信
    'wechat' => [
        // PC 扫码登录【需要开通微信开放平台应用 open.weixin.qq.com】
        'default' => [
            'app_id' => env('OAUTH_WECHAT_APP_ID', ''),
            'app_secret' => env('OAUTH_WECHAT_APP_SECRET', ''),
            'callback' => env('OAUTH_WECHAT_CALLBACK', ''),
            'scope' => 'snsapi_login', // PC扫码登录
            // 'proxy_url' => '',//如果不需要代理请注释此行
            // 'proxy_url' => '',//如果不需要代理请注释此行
        ],
        // 移动端登录
        'mobile' => [
            'app_id' => '',
            'app_secret' => '',
            'callback' => 'https://example.com/app/wechat',
            // snsapi_base: 静默授权; snsapi_userinfo: 获取用户信息
            'scope' => 'snsapi_userinfo',
            // 'proxy_url' => '',//如果不需要代理请注释此行
            // 'proxy_url' => '',//如果不需要代理请注释此行
        ],
        // app 登录
        'app' => [
            'app_id' => '',
            'app_secret' => '',
            'type' => 'app', // 登录类型app
        ],
        /**
         * 微信小程序只能获取到 openid session_key
         * 详见文档 https://developers.weixin.qq.com/miniprogram/dev/api-backend/open-api/login/auth.code2Session.html
         */
        'applets' => [
            'app_id' => '',
            'app_secret' => '',
            'type' => 'applets', // 登录类型小程序
        ],

        /**
         * 如果需要微信代理登录(微信app内登录)，则需要：
         * 1.将 example/wx_proxy.php 放置在微信公众号设定的回调域名某个地址，如 https://www.example.com/proxy/wx_proxy.php
         * 2.config中加入配置参数proxy_url，地址为 https://www.example.com/proxy/wx_proxy.php
         * 如下所示:
         *    'proxy_url' = 'http://www.example.com/proxy/wx_proxy.php';
         */
    ],
    // 新浪微博
    'sina' => [
        'default' => [
            'app_id' => env('OAUTH_SINA_APP_ID', ''),
            'app_secret' => env('OAUTH_SINA_APP_SECRET', ''),
            'callback' => env('OAUTH_SINA_CALLBACK', ''),
            'scope' => 'all',
        ],
    ],
    // 企业微信
    'wecom' => [
        'default' => [
            'app_id' => '',
            'app_secret' => '',
            'callback' => '',
            'agent_id' => '1000001', // 企业应用ID
        ],
    ],
    'douyin' => [
        // 抖音官方：请确保授权回调域网站协议为 https
        'pc' => [
            'oauth_type' => ConstCode::TYPE_DOUYIN, // 抖音douyin，头条toutiao，西瓜xigua，使用\zxf\Utils\OAuth2\Helper\ConstCode
            'app_id' => '',
            'app_secret' => '',
            'callback' => '',
            'scope' => 'trial.whitelist,user_info', // trial.whitelist为白名单人员权限,上线后删掉
            'optionalScope' => '', // 应用授权可选作用域,多个授权作用域以英文逗号（,）分隔，每一个授权作用域后需要加上一个是否默认勾选的参数，1为默认勾选，0为默认不勾选
        ],
    ],
    /**
     * 支付宝增加open_id废弃user_id https://opendocs.alipay.com/mini/0ai2i6?pathHash=13dd5946
     * 支付宝unionid布局 https://opendocs.alipay.com/mini/0ai2i8?pathHash=9e717ecc
     */
    'alipay' => [
        'default' => [
            'app_id' => '',
            'app_secret' => '',
            'public_key' => '',
            'callback' => '',
            'scope' => 'auth_user',
        ],
    ],
    // 钉钉开放平台
    'dingtalk' => [
        'default' => [
            'app_id' => '',
            'app_secret' => '',
            'callback' => '',
            'scope' => 'openid',
        ],
    ],
    // 小米
    'xiaomi' => [
        'default' => [
            'app_id' => '',
            'app_secret' => '',
            'callback' => '',
            'scope' => 'profile',
        ],
    ],
    // 华为
    'huawei' => [
        'default' => [
            'app_id' => '',
            'app_secret' => '',
            'callback' => '',
            'scope' => 'openid profile',
        ],
    ],

    // 开发平台
    'github' => [
        'default' => [
            'app_id' => '',
            'app_secret' => '',
            'callback' => '',
            'scope' => 'user',
        ],
    ],
    'gitlab' => [
        'default' => [
            'app_id' => '',
            'app_secret' => '',
            'callback' => '',
            'scope' => 'read_user',
        ],
    ],
    'gitee' => [
        'default' => [
            'app_id' => '',
            'app_secret' => '',
            'callback' => '',
            'scope' => 'user_info',
        ],
    ],
    'oschina' => [
        'default' => [
            'app_id' => '',
            'app_secret' => '',
            'callback' => '',
            'scope' => 'user',
        ],
    ],
];
