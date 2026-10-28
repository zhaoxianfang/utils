<?php
declare(strict_types=1);

namespace zxf\Utils\OAuth2\Connector;

use zxf\Utils\OAuth2\Exception\OAuthException;
use zxf\Utils\OAuth2\Helper\ConstCode;
use zxf\Utils\OAuth2\Helper\Str;

/**
 * OAuth2 第三方登录网关抽象基类。
 *
 * 所有具体的第三方平台（QQ、微信、GitHub 等）连接器均应继承本类，并至少实现
 * GatewayInterface 中约定的方法。本类封装了 OAuth2 授权码模式的通用流程：
 * 构建授权跳转地址、生成并校验 state（防 CSRF）、用 code 换取 AccessToken、
 * 解析与格式化用户信息等。
 *
 * @package zxf\Utils\OAuth2\Connector
 * @version 1.0.0
 * @license MIT
 *
 * @example
 * // 以某个具体网关（如 QQ）为例的 OAuth2 授权码模式基本流程：
 * $config = [
 *     'app_id'     => 'your_app_id',
 *     'app_secret' => 'your_app_secret',
 *     'callback'   => 'https://your.site/callback', // 必须与平台后台配置完全一致
 * ];
 * // 1) 生成授权跳转地址，引导用户前往第三方授权页
 * $gateway = new \zxf\Utils\OAuth2\Gateways\QQ($config);
 * $authUrl = $gateway->getRedirectUrl();   // 内部自动拼接 client_id、redirect_uri、state 等
 * header("Location: {$authUrl}");
 * exit;
 *
 * // 2) 用户在第三方同意授权后回跳到 callback，由业务侧继续：
 * $gateway->checkState();          // 校验回调 state 与 Session 中保存值是否一致（防 CSRF）
 * $token    = $gateway->getToken();// 用回传的 code 换取 AccessToken
 * $userInfo = $gateway->userInfo();// 获取格式化后的用户信息（openid、昵称、头像等）
 */
abstract class Gateway implements GatewayInterface
{
    use GatewayTrait;

    /**
     * 授权地址（Authorization Endpoint），用于拼接引导用户授权的跳转链接。
     * @var string
     */
    protected $AuthorizeURL;

    /**
     * 获取 AccessToken 的地址（Token Endpoint），用于用 code 换取令牌。
     * @var string
     */
    protected $AccessTokenURL;

    /**
     * 获取用户信息的地址（UserInfo Endpoint），用于拉取第三方用户资料。
     * @var string
     */
    protected $UserInfoURL;

    /**
     * 合并默认配置后的完整配置参数数组。
     * @var array
     */
    protected $config;

    /**
     * 第三方平台分配的应用标识 AppId（如微信 appid、QQ appid），为字符串而非数组。
     * @var string
     */
    protected $app_id;

    /**
     * 第三方平台分配的应用密钥 AppSecret。
     * @var string
     */
    protected $app_secret;

    /**
     * 接口权限范围（scope），用于申请对应的数据访问权限。
     * @var string
     */
    protected $scope;

    /**
     * 授权成功后的回调地址（redirect_uri），必须与平台后台配置完全一致。
     * @var string
     */
    protected $callback;

    /**
     * 当前时间戳，构造时初始化，部分平台签名/参数会用到。
     * @var int
     */
    protected $timestamp;

    /**
     * 第三方授权页面样式（display），如 default、mobile、touch 等。
     * @var string
     */
    protected $display = 'default';

    /**
     * 登录类型：app / applets / 空（PC 网页端）。
     * @var string|null
     */
    protected $type;

    /**
     * 第三方返回的 Token 信息（含 access_token、openid 等）。
     * @var array|null
     */
    protected $token = null;

    /**
     * 是否验证回跳地址中的 state 参数（默认开启，防 CSRF）。
     * @var bool
     */
    protected $checkState = true;

    /**
     * 第三方返回的原始用户信息（未做格式转换）。
     * @var array
     */
    protected $userInfo = [];

    /**
     * 格式化后的用户信息（统一字段结构，便于业务侧直接使用）。
     * @var array
     */
    protected $formatUserInfo = [];


    /**
     * Gateway 构造函数，初始化配置并填充各属性。
     *
     * 将传入的配置与默认配置数组合并，再把与类属性同名的配置项赋值到对应属性；
     * 同时校验配置非空、记录当前时间戳。
     *
     * @param array $config 第三方平台配置，至少包含 app_id、app_secret、callback 等
     * @throws OAuthException 当传入的配置为空时抛出
     */
    public function __construct($config)
    {
        if (!$config) {
            throw new OAuthException('传入的配置不能为空');
        }
        // 安全说明：早期版本会将 $_GET['referer'] 直接拼接到 callback 中，
        // 这会被攻击者利用进行开放重定向 / CSRF 辅助。现已移除该行为，
        // callback 应仅由受信任的配置指定，重定向目标需在业务侧自行校验。
        //默认参数
        $_config = [
            'app_id'        => '',
            'app_secret'    => '',
            'callback'      => '',
            'response_type' => 'code',
            'grant_type'    => 'authorization_code',
            'proxy'         => '',
            'state'         => '',
            'type'          => '',
            'is_sandbox'    => false,//是否是沙箱环境
        ];
        $this->config    = array_merge($_config, $config);
        foreach($this->config as $key=>$val){
            if(property_exists($this,$key)) $this->$key=$val;
        }
        $this->timestamp = time();
    }

    /**
     * 设置授权页面样式（display）。
     *
     * @param string $display 页面样式标识，如 default、mobile、touch 等
     * @return static 返回当前实例，支持链式调用
     */
    public function setDisplay($display)
    {
        $this->display = $display;
        return $this;
    }

    /**
     * 设置登录类型（app / applets 等）。
     *
     * @param string|null $type 登录类型，可选值：app、applets，留空表示 PC 网页端
     * @return static 返回当前实例，支持链式调用
     */
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * 强制开启回跳地址中的 state 参数校验（防 CSRF）。
     *
     * 默认即开启，本方法用于显式恢复校验（与 disableCheckState 对应）。
     *
     * @return static 返回当前实例，支持链式调用
     */
    public function mustCheckState(){
        $this->checkState = true;
        return $this;
    }

    /**
     * 显式关闭回跳地址中的 state 参数校验（仅在确有必要时调用，默认开启以防 CSRF）。
     *
     * 关闭后将不再校验回调 state 与 Session 中保存值的一致性，存在被 CSRF 攻击的风险，
     * 仅在无法使用 Session 等极少数场景下谨慎使用。
     *
     * @return static 返回当前实例，支持链式调用
     */
    public function disableCheckState(){
        $this->checkState = false;
        return $this;
    }

    /**
     * 获取合并后的完整配置信息。
     *
     * @return array 当前实例的配置数组
     */
    public function getConfig(){
        return $this->config;
    }

    /**
     * 设置第三方 Token 信息（适用于 App 端已自行获取 token 的场景）。
     *
     * @param array $token 第三方返回的 token 数组，通常含 access_token、openid 等
     * @return static 返回当前实例，支持链式调用
     */
    public function setToken($token){
        $this->token = $token;
        return $this;
    }

    /**
     * 生成并存储 state 到 Session（用于后续回调时校验，防 CSRF）。
     *
     * 仅当 checkState 为 true 时生效：若未开启 Session 则自动开启；
     * 若未显式配置 state，则使用 Str::random() 生成高熵随机串作为 state，
     * 并存入 Session，以便回调时与回传 state 对比，验证请求来源合法性。
     *
     * @return void
     */
    public function saveState(){
        if ($this->checkState === true) {
            //是否开启session
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            if(empty($this->config['state'])){
                $this->config['state'] = Str::random();//生成随机state
            }
            //存储到session
            $_SESSION['zxf\Util_oauth_state'] = $this->config['state'];
        }
    }

    /**
     * 校验回调请求中的 state 参数，防止跨站请求伪造（CSRF）。
     *
     * 仅当 checkState 为 true 时生效：对比回调携带的 state 与 Session 中保存的 state，
     * 使用 hash_equals 进行时序安全（constant-time）比较，避免通过响应时间差异侧信道泄露。
     *
     * @throws OAuthException 当回调 state 缺失或与 Session 中保存值不一致时抛出
     * @return void
     */
    public function checkState(){
        if ($this->checkState === true) {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            if (!isset($_REQUEST['state']) || !isset($_SESSION['zxf\Util_oauth_state'])
                || !hash_equals((string) $_REQUEST['state'], (string) $_SESSION['zxf\Util_oauth_state'])) {
                throw new OAuthException('传递的STATE参数不匹配！');
            }
        }
    }


    /**
     * 从回调请求中获取授权码 code。
     *
     * @return string 第三方回传的授权码 code，未取到时返回空字符串
     */
    public function getCode(){
        return isset($_REQUEST['code']) ? $_REQUEST['code'] : '';
    }

    /**
     * 构建申请 AccessToken 的默认请求参数。
     *
     * 子类如需自定义参数（如追加 scope、state）可重写本方法。
     *
     * @return array 包含 client_id、client_secret、grant_type、code、redirect_uri 的参数数组
     */
    protected function accessTokenParams(){
        $params = [
            'client_id'     => $this->config['app_id'],
            'client_secret' => $this->config['app_secret'],
            'grant_type'    => $this->config['grant_type'],
            'code'          => $this->getCode(),
            'redirect_uri'  => $this->config['callback'],
        ];
        return $params;
    }

    /**
     * 获取 AccessToken（已缓存则直接返回，否则用 code 换取）。
     *
     * 流程：先校验 state（防 CSRF）→ 组装请求参数 → 请求 Token 端点 → 解析返回结果；
     * 解析后的 token 若缺少 access_token 字段，则视为失败并抛出 OAuthException。
     *
     * @return array 解析后的 token 信息数组
     * @throws OAuthException 当换取 token 失败或返回中缺少 access_token 时抛出
     */
    protected function getToken(){
        if (empty($this->token)) {
            /** 验证state参数 */
            $this->checkState();

            /** 获取参数 */
            $params = $this->accessTokenParams();

            /** 获取access_token */
            $token =  $this->post($this->AccessTokenURL, $params,$this->getHeaders());
            /** 解析token值(子类实现此方法) */
            if(is_array($token)){
                $this->token = $token;
            }else{
                $this->token = $this->parseToken($token);
            }
            if (empty($this->token['access_token'])) {
                $code = isset($this->token['error'])?$this->token['error']:(isset($this->token['code'])?$this->token['code']:500);
                $message = isset($this->token['message'])?$this->token['message']:(isset($this->token['error_description'])?$this->token['error_description']:'获取 TOKEN 出错');
                throw new OAuthException($message, $code, $token);
            }
        }
        return $this->token;
    }

    /**
     * 将第三方返回的性别字符串格式化为统一的性别常量。
     *
     * 以 M（Male）开头视为男性，其余（如 F / Female）视为女性。
     *
     * @param string $gender 第三方返回的性别标识，如 M、F、male、female
     * @return int 返回 ConstCode::GENDER_MAN（男）或 ConstCode::GENDER_WOMEN（女）
     */
    public function getGender($gender){
        return strtolower(substr($gender , 0 , 1)) == 'm' ? ConstCode::GENDER_MAN : ConstCode::GENDER_WOMEN;
    }


    /**
     * 刷新 AccessToken（续期）。
     *
     * 基类默认返回 false，表示「当前网关未实现刷新」——不是失败重试，而是能力缺失。
     * 旧实现返回 true，会让调用方误以为令牌已续期而继续使用过期凭证。
     * 支持续期的网关（Baidu/Gitlab/Wechat/Gitee 等）均覆写本方法并返回真实结果。
     *
     * @param string $refreshToken 用于刷新 token 的 refresh_token
     * @return bool 刷新是否成功；未实现刷新的网关恒为 false
     */
    public function refreshToken($refreshToken)
    {
        return false;
    }

    /**
     * 校验授权凭证 AccessToken 是否有效。
     *
     * 基类提供空实现（默认返回 true），具体校验逻辑由子类自行实现。
     *
     * @param string|null $accessToken 待校验的 access_token，留空时由子类自行取用
     * @return bool 凭证是否有效
     */
    public function validateAccessToken($accessToken = null)
    {
        return true;
    }

}
