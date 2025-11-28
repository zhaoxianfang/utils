# 第三方登录

## OAuth2.0 登录

```
来源：https://github.com/majiameng/OAuth2
tags:v2.3.5
date:2025-11-28
```

### 使用

引入命名空间

```php
use zxf\Util\OAuth2\OAuth;
```


1、 初始化实例类

```php
// 实例化方式一：
$oauth = OAuth::Sina(?array $config=[]);
$oauth = OAuth::Qq(?array $config=[]);

// 实例化方式二：
$name = 'qq';
/** @var $oauth \zxf\Login\Contracts\Gateway */
$oauth = OAuth::$name(?array $config=[]);

// 实例化方式三：
$oauth = new \zxf\Login\Gateways\Qq(?array $config=[]);
```

2、 可选：需要强制验证回跳地址中的state参数
> 提示:为了不暴露参数信息，内部会自动生成和处理state参数
> 可以传入一个参数，例如字符串或者数组，在回调中进行自定义业务逻辑处理

```php
// $data 为空时内部会默认生成一个值
// 传入$data数据后可以在回调中获取到
$oauth->mustCheckState(string|array $data=''); // 如需手动验证state,请关闭此行

// 微博、微信：特别指定用于手机端登录【正常情况下不设置】，则需要设定->setDisplay('mobile')
```

3、 得到授权跳转地址

```php
$url = $oauth->getRedirectUrl();
```

4、重定向到外部第三方授权地址

```php
// 各个框架差异，请自行参考框架文档

// Laravel
return redirect()->away($url);
// ThinkPHP
$this->redirect($url);
```

5、可选：回调时验证 `state` 并返回之前传入的参数`$data`

```php
$data = $oauth->mustCheckState()->checkState(); // 如需手动验证state,请关闭此行
```

6、获取第三方用户信息

```php
$userInfo = $oauth->userInfo(); // 【推荐】处理后的用户信息
// OR
$userInfo = $oauth->getUserInfo(); // 第三方返回的原始用户信息
```

### OAuth 公共方法

```php
// 得到跳转地址
public function getRedirectUrl();

// 获取当前授权用户的openid标识
public function openid();

// 【推荐】获取格式化后的用户信息
public function userInfo();

// 获取原始接口返回的用户信息
public function getUserInfo();
```

### 国内平台 (Domestic Platforms)


|Gateways|LoginName|LoginMethod|
|:-:|:-:|:-:|
|qq|腾讯QQ|PC扫码、APP|
|wechat|微信|PC、公众号、小程序、APP|
|wecom|企业微信|PC、APP|
|sina|新浪微博|PC、APP|
|alipay|支付宝|PC、APP|
|aliyun|阿里云|PC|
|baidu|百度|PC|
|douyin|抖音|PC、APP|
|toutiao|头条|PC、APP|
|xigua|西瓜视频|PC、APP|
|dingtalk|钉钉|PC、APP|
|xiaomi|小米|PC、APP|
|huawei|华为|PC、APP|

### 开发平台 (Development Platforms)


|Gateways|LoginName|LoginMethod|
|:-:|:-:|:-:|
|github|GitHub|PC|
|gitlab|GitLab|PC|
|gitee|Gitee|PC|
|coding|Coding|PC|
|oschina|OSChina|PC|
|csdn|CSDN|PC|

### 国际平台 (International Platforms)


|Gateways|LoginName|LoginMethod|
|:-:|:-:|:-:|
|google|Google|PC|
|facebook|Facebook|PC|
|twitter|Twitter|PC|
|line|Line|PC|
|naver|Naver|PC|
|amazon|Amazon|PC|
|apple|Apple|PC、APP|
|yahoo|Yahoo|PC|
|microsoft|Microsoft|PC|

>注意事项 (Notes)：
1. Google、Facebook、Twitter 等国际平台需要使用海外或香港服务器才能正常回调
2. 部分平台支持多种授权方式，如 PC 网页授权、APP 授权等
3. 使用前请先阅读相应平台的开发文档并完成开发者资质认证

>注：Google、facebook、twitter等这些国外平台需要海外或者HK服务器才能回调成功

### Configuration

```
Config::get($name)  获取对应登录类型的配置
```

### 公共方法

在接口文件中，定义了4个方法，是每个第三方基类都必须实现的，用于相关的第三方登录操作和获取数据。方法名如下：
```
    /**
     * Description:  得到跳转地址
     *
     * @return mixed
     */
    public function getRedirectUrl();

    /**
     * Description:  获取当前授权用户的openid标识
     *
     * @return mixed
     */
    public function openid();

    /**
     * Description:  获取格式化后的用户信息
     *
     * @return mixed
     */
    public function userInfo();

    /**
     * Description:  获取原始接口返回的用户信息
     *
     * @return mixed
     */
    public function getUserInfo();
    
```

### 典型用法

以ThinkPHP5为例
```
<?php
namespace app\index\controller;

use think\Config;
use tinymeng\OAuth2\OAuth;
use tinymeng\OAuth2\Helper\Str;
use tinymeng\tools\Tool;

class Login extends Common
{
    protected $config;

    /**
     * Description:  登录
     *
     * @return mixed
     */
    public function index()
    {
        $name="qq";//登录类型,例:qq / google
        if (empty(input('get.'))) {
            /** 登录 */
            $result = $this->login($name);
            $this->redirect($result);
        }
        /** 登录回调 */
        $this->callback($name);
        return $this->fetch('index');
    }
    
    /**
     * Description:  获取配置文件
     *
     * @param $name
     */
    public function getConfig($name){
        //可以设置代理服务器，一般用于调试国外平台
        //$this->config['proxy'] = 'http://127.0.0.1:1080';
        
        $this->config = Config::get($name);
        if($name == 'wechat'){
            if(!Tool::isMobile()){
                $this->config = $this->config['pc'];//微信pc扫码登录
            }elseif(Tool::isWeiXin()){
                $this->config = $this->config['mobile'];//微信浏览器中打开
            }else{
                echo '请使用微信打开!';exit();//手机浏览器打开
            }
        }
        //$this->config['state'] = Str::random();//如需手动验证state,请开启此行并存储state值
    }
    
    /**
     * Description:  登录链接分配，执行跳转操作
     */
    public function login($name){
        /** 获取配置 */
        $this->getConfig($name);

        /** 初始化实例类 */
        $oauth = OAuth::$name($this->config);
        $oauth->mustCheckState();//如需手动验证state,请关闭此行
        if(Tool::isMobile() || Tool::isWeiXin()){
            /**
             * 对于微博，如果登录界面要适用于手机，则需要设定->setDisplay('mobile')
             * 对于微信，如果是公众号登录，则需要设定->setDisplay('mobile')，否则是WEB网站扫码登录
             * 其他登录渠道的这个设置没有任何影响，为了统一，可以都写上
             */
            $oauth->setDisplay('mobile');
        }
        return $oauth->getRedirectUrl();
    }
    
    /**
     * Description:  登录回调
     *
     * @param $name
     * @return bool
     */
    public function callback($name)
    {
        /** 获取配置 */
        $this->getConfig($name);

        /** 初始化实例类 */
        $oauth = OAuth::$name($this->config);
        $oauth->mustCheckState();//如需手动验证state,请关闭此行

        /** 获取第三方用户信息 */
        $userInfo = $oauth->userInfo();
        /**
         * 如果是App登录
         * $type = "applets";
         * $userInfo = OAuth::$name($this->config)->setType($type)->userInfo();
         */
         /**
         * 如果是App登录
         * $type = "applets";
         * $userInfo = OAuth::$name($this->config)->setType($type)->userInfo();
         */

        //获取登录类型
        $userInfo['type'] = \tinymeng\OAuth2\Helper\ConstCode::getTypeConst($userInfo['channel']);

        var_dump($userInfo);die;
        
    }
}
```

通过系统自动设置state，如有需要请自行处理验证，state也放入config里即可 Line和Facebook强制要求传递state，如果你没有设置，则会传递随机值 如果要验证state，则在获取用户信息的时候要加上`->mustCheckState()`方法。

```
$name = "qq";
$snsInfo = OAuth::$name($this->config)->mustCheckState()->userinfo();
```

>注意，不是所有的平台都支持传递state，请自行阅读官方文档链接,各个文档在实现类里有说明.

微信有一个额外的方法，用于获取代理请求的地址
```
    /**
     * 获取中转代理地址
     */
    public function getProxyURL();
```

App登录回调

```
    $name = "qq";
    /**
     * 回调中如果是App登录
     */
    $type = 'app';
    $userInfo = OAuth::$name($this->config)->setType($type)->userInfo();
    //->setType() 或者  在配置文件中设置config['type'] = 'app'


    /**
    * access_token 通过$_REQUEST['access_token'] 进行传值到oauth中
    *    facebook App登录
    *    qq App登录
    *    wechat App登录
    */

    /**
    * code 通过$_REQUEST['code'] 进行传值到oauth中
    *    google App登录
    */
```

>打通unionid的话需要将公众号绑定到同一个微信开放平台 会返回的唯一凭证unionid字段

### `userinfo()`公共返回样例

```
Array
(
    [open_id] => 1047776979*******   //open_id数据唯一凭证
    [access_token] => 444444445*******  //用户的access_token凭证
    [union_id] => 444444445*******  //用户的唯一凭证（在同一平台下多设备参数返回一致）,部分登录此字段值是open_id(例:sina/google),
    [channel] => 1;                 //登录类型请查看 \tinymeng\OAuth2\Helper\ConstCode
    [nickname] => 'Tinymeng'        //昵称
    [gender] => 1;                  //0=>未知 1=>男 2=>女   twitter和line不会返回性别，所以这里是0，Facebook根据你的权限，可能也不会返回，所以也可能是0
    [avatar] => http://thirdqq.qlogo.cn/qqapp/101426434/50D523803F5B51AAC01616105161C7B1/100 //头像
    [type] => 21;                   //登录子类型请查看 \tinymeng\OAuth2\Helper\ConstCode ，例如：channel：微信 type：小程序或app
)
```

>部分登录类型还会返回个别数据,如需返回原数据请使用 `getUserInfo()` 方法
