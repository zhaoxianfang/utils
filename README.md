# zxf/utils — PHP 通用工具包与函数集

![](https://img.shields.io/packagist/dt/zxf/utils) ![](https://img.shields.io/github/stars/zhaoxianfang/utils.svg) ![](https://img.shields.io/github/forks/zhaoxianfang/utils.svg) ![](https://img.shields.io/github/tag/zhaoxianfang/utils.svg) ![](https://img.shields.io/github/release/zhaoxianfang/utils.svg) ![](https://img.shields.io/github/issues/zhaoxianfang/utils.svg) ![](https://img.shields.io/badge/php-%E2%89%A58.2-787CB5) ![](https://img.shields.io/badge/tests-144%20passing-brightgreen)

基于 PHP 8.2+ 的现代化工具库：**全量严格类型**、零隐式全局副作用、框架无关。

---

## 目录

- [环境要求](#环境要求)
- [安装与配置](#安装与配置)
- [核心特性](#核心特性)
- [快速上手](#快速上手)
- [模块总览](#模块总览)
- [分模块用法](#分模块用法)
- [全局函数参考](#全局函数参考)
- [测试与静态检查](#测试与静态检查)
- [安全说明](#安全说明)
- [常见问题](#常见问题)
- [变更记录](#变更记录)

---

## 环境要求

| 项目 | 要求 |
|------|------|
| PHP | >= 8.2 |
| 必需扩展 | `json`、`mbstring` |
| 运行时依赖 | 无 —— 零第三方运行时依赖，完全自包含（BarCode/QrCode/PHPMailer 等均以源码内嵌或自研实现） |

可选扩展按需启用（缺失时相关类会给出明确提示，而不是静默失败）：
`curl`、`dom`、`gd`、`imagick`、`openssl`、`simplexml`、`zip`。

完整清单见 [`composer.json`](composer.json) 的 `suggest` 段落。

## 安装与配置

```bash
composer require zxf/utils
```

**Laravel 用户**：本包通过 `extra.laravel.providers` 自动注册 `Providers\UtilsServiceProvider`，无需手动添加。发布配置文件：

```bash
php artisan vendor:publish --tag=utils-config
```

**非 Laravel 用户**：直接 `require 'vendor/autoload.php'` 即可，服务提供者不会被加载。

---

## 核心特性

- **全量严格类型**：`src/` 下所有文件均声明 `declare(strict_types=1)`（由 `composer lint` 强制校验）
- **现代 PHP 语法**：`match`、联合类型、交叉类型、`readonly` 类、`never` 返回类型、枚举、首类可调用语法
- **零全局副作用**：加载即声明函数与类，不设置时区、不产生输出、不修改全局状态
- **防御式实现**：树形构建防循环引用、解压防路径穿越、一致性哈希均匀分布、XSS 过滤基于 DOM 而非正则
- **自带测试基建**：144 个回归测试 + 4 项静态检查，一条命令运行，无需 PHPUnit

---

## 快速上手

```php
require 'vendor/autoload.php';

use zxf\Utils\Support\Result;
use zxf\Utils\Str\Str;
use zxf\Utils\Convert\DataSize;
use zxf\Utils\Support\Retry;

// 结果类型：用返回值代替异常控制流程
Result::attempt(fn () => riskyOperation())
    ->map(fn ($data) => transform($data))
    ->unwrapOr('fallback');

// 字符串流式处理
(string) Str::camel('foo-bar');        // 'fooBar'
(string) u_str('Hello World')->slug(); // 'hello-world'

// 数据大小换算
DataSize::parse('2.5GB')->toBytes();   // 2684354560
DataSize::format(1536);                // '1.5 KB'

// 指数退避 + 抖动重试
Retry::make(fn () => fetchRemote())
    ->times(5)
    ->sleep(100)
    ->backoff(1.5)
    ->jitter()
    ->run();
```

---

## 模块总览

共 24 个类命名空间、139 个类文件（另有 `PHPMailer`、`QrCode` 两个内置的第三方实现及 `funcs` 全局函数集）。下表列出顶层模块与主要类：

| 命名空间 | 主要类 | 说明 |
|----------|--------|------|
| `Support` | `Arr` `Result` `Pipeline` `Retry` `Once` `Defer` `Fluent` `EnumHelper` `RateLimiter` `Timer` `Locker` `Event` `Condition` `Sanitizer` `Benchmark` | 通用编程构件：结果类型、流水线、重试、限流、计时、进程锁、事件 |
| `Security` | `XssCleaner` `Password` `Hash` `Token` | 安全：XSS 清理、密码哈希与强度、哈希算法、HMAC 签名 Token |
| `Convert` | `DataSize` `Number` `Json` `Color` `Unit` | 转换：数据大小、数字格式化、JSON、颜色空间、单位 |
| `Data` | `Tree` `Collection` `Version` `BigNumberCalculator` `Random` `CountryNumber` `IDCardGenerator` | 数据：树形结构、集合、SemVer、大数运算、随机、国家区号 |
| `Net` | `Ip` `Url` `Domain` | 网络：IP 计算与 CIDR、URL 解析构建、域名与 WHOIS |
| `Str` | `StringHelper` `Str` | 字符串：命名风格转换、脱敏、相似度、slug |
| `Files` | `File` `Path` `CSV` `Archive` | 文件：读写遍历、路径处理、CSV 流式读写、ZIP 压缩解压 |
| `Cache` | `MemoryCache` `FileCache` | 缓存：带 TTL 与标签的内存缓存、文件缓存 |
| `Crypto` | `AES` `RSA` `ECC` `JWT` `Hash` `Password` `Base64` `Random` | 加密解密与非对称算法 |
| `Http` | `Curl` `Request` `Response` | HTTP 客户端与请求/响应封装 |
| `Image` | `Compressor` `ImagickTool` `TextToImg` | 图片压缩、Imagick 高级处理、文字转图 |
| `Date` | `DateTools` `CronExpression` `TimeZone` | 日期运算、Cron 解析、时区转换 |
| `Text` | `Template` `Diff` `MdToH5` | 模板引擎、文本差异、Markdown 转 H5 |
| `Validate` | `Validator` | 数据校验 |
| `Xml` | `Array2XML` `XML2Array` | XML 与数组互转 |
| `BarCode` | `BarcodeBuilder` `BarcodeFactory` `BarcodeHelper` | 一维码生成（EAN/UPC/Code128/Code39/ISBN 等 10 种） |
| `QrCode` | `QrCode` `QrCodeHelper` | 二维码生成（内置实现，无需扩展） |
| `OAuth2` | `OAuth` + 20 个平台网关 | 第三方登录（微信/QQ/微博/GitHub/Gitee/支付宝 等） |
| `Minify` | `Minify` `CSS` `JS` | CSS/JS 代码压缩 |
| `Array` | `DataArray` | 增强数组对象 |
| `Pagination` | `Paginator` | 分页计算 |
| `Id` | `Snowflake` | 雪花算法分布式 ID |
| `Env` | `Env` | `.env` 文件解析 |
| `Log` | `Logger` | 轻量日志 |
| `Sundry` | `Command` `CliInput` `CliOutput` `SiteMapGenerator` | CLI 工具与站点地图生成 |
| `Providers` | `UtilsServiceProvider` | Laravel 服务提供者 |
| `funcs` | `helpers.php` `support.php` | 全局函数集 |

---

## 分模块用法

### Support — 通用编程构件

#### Result：用返回值替代异常流程

```php
use zxf\Utils\Support\Result;

$result = Result::attempt(fn () => riskyOperation());

$result->isOk();                                  // 是否成功
$result->unwrap();                                // 取值（失败时抛 RuntimeException）
$result->unwrapOr('default');                     // 取值或兜底
$result->unwrapOrElse(fn ($e) => handle($e));     // 取值或按异常处理
$result->map(fn ($v) => $v * 2);                  // 映射成功值（失败原样传递）
$result->mapErr(fn ($e) => "包装: {$e->getMessage()}");
$result->andThen(fn ($v) => Result::ok(next($v)));// 链式（失败短路）
$result->orElse(fn ($e) => Result::ok(recover()));// 失败兜底
$result->isOkAnd(fn ($v) => $v > 0);              // 条件判断
json_encode($result);                             // {"ok":true,"value":...}
```

#### Pipeline：数据流水线

```php
use zxf\Utils\Support\Pipeline;

Pipeline::make(['  a ', ' b', ''])
    ->through(fn (array $d) => array_map('trim', $d))   // 变换
    ->tap(fn ($d) => logger()->debug($d))               // 副作用（不改变数据）
    ->when($strict, fn (array $d) => array_filter($d))  // 条件执行
    ->catch(fn (Throwable $e, $data) => 'handled')      // 异常兜底
    ->then(fn (array $d) => array_values($d));          // 终点
```

`catch()` 会同时保护管道与 `then()` 的终点回调；未设置 `catch()` 时，异常默认包装为 `RuntimeException` 抛出，也可 `haltOnException(false)` 跳过出错的管道并保留进入时的数据。

#### Retry：指数退避重试

```php
use zxf\Utils\Support\Retry;

Retry::make(fn () => fetchRemote())
    ->times(5)          // 最多 5 次
    ->sleep(100)        // 基础等待 100 毫秒
    ->backoff(1.5)      // 每次 ×1.5
    ->jitter()          // 叠加 50%~100% 抖动，避免惊群
    ->when(fn ($v) => $v === null)  // 结果不满足时也重试
    ->run();
```

#### 其它构件

```php
use zxf\Utils\Support\{Once, Defer, Timer, Locker, RateLimiter, EnumHelper, Fluent};

// 只执行一次
$value = Once::make(fn () => expensiveSetup());
$value(); $value();              // 回调仅首次真正执行
$value->value();                 // 读取缓存值（不触发执行）

// Go 风格延迟执行（后进先出）
$defer = Defer::make()->add(fn () => cleanupB())->add(fn () => cleanupA());
$defer->execute();               // 执行顺序：cleanupA → cleanupB

// 纳秒级计时
$timer = Timer::start();
Timer::sleep(0.05);              // 高精度睡眠（秒，支持小数）
$timer->elapsed();               // 0.05x
Timer::withTimeout(2.0)->isTimedOut();

// 进程级互斥锁
$lock = new Locker('daily-report');
$lock->synchronized(fn () => generateReport());   // 阻塞获取 + 自动释放
$lock->trySynchronized(fn () => job(), 'skipped');// 非阻塞

// 速率限制
$limiter = RateLimiter::make('api:'.$ip, 60, 60);
$limiter->attempt() ? handle() : abort(429);
$limiter->remaining();

// 枚举工具（同时支持 BackedEnum 与纯枚举）
EnumHelper::values(Suit::class);     // ['h', 's']
EnumHelper::toArray(Suit::class);    // ['H' => 'h', 'S' => 's']
EnumHelper::tryFrom(Suit::class, 'h');

// 流式接口基类（仅暴露 public 属性，写入前校验类型）
class Query extends Fluent {
    public string $table = '';
    public int $limit = 10;
}
(new Query())->table('users')->limit(20);
```

`Fluent` 只写入 **public** 属性，且会按属性声明的类型校验；对 `protected`/`private` 属性或类型不符的值分别抛出 `BadMethodCallException` / `InvalidArgumentException`。

---

### Security — 安全

#### XssCleaner：基于 DOM 的 XSS 过滤

```php
use zxf\Utils\Security\XssCleaner;

// 基础清理：移除危险标签、所有 on* 事件属性、危险协议
XssCleaner::clean('<p>你好 <b>粗体</b></p><script>alert(1)</script>');

// 严格模式：标签 + 属性双重白名单
XssCleaner::strict($html);                       // 使用内置白名单
XssCleaner::strict($html, ['p' => [], 'a' => ['href', 'title']]);

XssCleaner::escape($text);                       // HTML 实体转义（输出时的首选）
XssCleaner::strip($html);                        // 移除全部标签
XssCleaner::cleanArray($data);                   // 递归清理数组
XssCleaner::isSafeUrl($url);                     // URL 协议白名单校验
```

> **实现说明**：过滤在 DOM 树上进行，而非正则。正则方案存在多处可绕过之处，包括：
> `<div onclick=alert(1)>`（属性无引号）、`href="java\tscript:..."`（协议中插 TAB）、
> `href="&#106;avascript:..."`（实体编码）、以及 `strip_tags()` 只过滤标签不过滤属性。
> 本实现在协议判定前会先解码实体、剔除空白与控制字符。

#### Password / Hash / Token

```php
use zxf\Utils\Security\{Password, Hash, Token};

Password::hash($plain);                          // password_hash 封装
Password::verify($plain, $hash);
Password::strength($plain);                      // 强度评分
Password::generate(16);                          // 安全随机密码

Hash::md5($str); Hash::sha256($str);
Hash::hmac($data, $key);                         // HMAC-SHA256
Hash::file($path);                               // 文件哈希
Hash::consistent($key, ['node1', 'node2']);      // 一致性哈希（均匀分布）

$token = new Token($secret);                     // 密钥至少 16 字节
$signed = $token->encode(['uid' => 1], 3600);    // 带 TTL 的签名 Token
$token->decode($signed);                         // 验签并取载荷，过期/篡改抛异常
$token->verify($signed);                         // 仅校验，返回 bool
Token::random(32);                               // 随机串（CSRF / API Key）
```

---

### Convert — 转换

```php
use zxf\Utils\Convert\{DataSize, Number, Json, Color, Unit};

// 数据大小
DataSize::parse('2.5GB')->toBytes();   // 2684354560（支持 512、.5MB、1024 kb 等写法）
DataSize::format(1536);                // '1.5 KB'（字节级不输出小数）
DataSize::bytes(100)->add(DataSize::bytes(30))->toBytes(); // 130

// 数字
Number::currency(1234567.891);         // '1,234,567.89'
Number::chinese(1234.56);              // '壹仟贰佰叁拾肆元伍角陆分'
Number::abbreviate(1234567);           // '1.2M'
Number::ordinal(21);                   // '21st'
Number::roundTo(7, 5);                 // 5.0

// JSON
Json::get('{"a":{"b":[1,2,3]}}', 'a.b.1');   // 2
Json::set('{"a":1}', 'b.c', 2);              // '{"a":1,"b":{"c":2}}'
Json::merge('{"a":1}', '{"b":2}');
Json::toArray('5');                          // [5]（标量根节点包装为数组）
Json::decodeSafe('{bad}', 'default');        // 非法 JSON 返回默认值

// 颜色
Color::hexToRgb('#ff0000');            // ['r' => 255, 'g' => 0, 'b' => 0, 'a' => 1]

// 单位换算（按类别分别提供方法）
Unit::convertLength(1, 'km', 'm');     // 1000
Unit::convertWeight(1, 'kg', 'g');     // 1000
Unit::secondsToHuman(3661);            // '1 小时 1 分钟 1 秒'
```

---

### Data — 数据处理

```php
use zxf\Utils\Data\{Tree, Version, Collection, BigNumberCalculator, Random};

// 树形结构（自动防御循环引用，不会爆栈）
$tree = new Tree($rows);                      // [['id'=>1,'pid'=>0], ...]
$tree->toTree();                              // 树形数组
$tree->getChildrenIds(1);                     // 子孙 ID
$tree->getParentIds(5);                       // 祖先 ID
$tree->where('status', 1)->toArray();         // 条件过滤

// 语义化版本（完整 Composer / npm 约束语法）
Version::compare('1.0.0-alpha', '1.0.0');     // -1（预发布低于正式版）
Version::of('1.2.3')->satisfies('>=1.0.0 <2.0.0');  // true
Version::of('1.5.0')->satisfies('1.2.0 || >=1.4.0');// true
Version::of('1.2.3')->satisfies('^1.2');      // true
Version::of('1.2.3')->increment('minor');     // 1.3.0

// 大数运算（字符串进出，不受 int 范围限制）
BigNumberCalculator::add('999999999999999999999999', '1');
BigNumberCalculator::divide('10', '3', 2);    // '3.33'

// 安全随机
Random::string(32);
Random::password(16);
Random::pickMany($items, 3);                  // 不重复抽取
```

---

### Net — 网络

```php
use zxf\Utils\Net\{Ip, Url, Domain};

// IP：区间转 CIDR 会产出最精简的块，且精确覆盖原区间
Ip::rangeToCidr('192.168.1.0', '192.168.1.255');  // ['192.168.1.0/24']
Ip::rangeToCidr('0.0.0.0', '255.255.255.255');    // ['0.0.0.0/0']
Ip::rangeToCidr('192.168.1.1', '192.168.1.5');    // ['.../32', '.../31', '.../31']
Ip::isV4($ip);
Ip::v4ToLong('192.168.1.1');

Url::parse($url)->withQuery(['page' => 2])->build();
Domain::isValid('example.com');
```

---

### Str — 字符串

提供两种等价风格：静态工具类 `StringHelper` 与流式包装 `Str` / `u_str()`。

```php
use zxf\Utils\Str\StringHelper;

StringHelper::camel('foo-bar');        // 'fooBar'
StringHelper::snake('FooBar');         // 'foo_bar'
StringHelper::slug('Hello World');     // 'hello-world'
StringHelper::maskMobile('13812345678'); // '138****5678'
StringHelper::maskEmail('abc@example.com'); // 'ab****@example.com'
StringHelper::similarity('中文测试', '中文测式'); // 0.75（按字符计算）

// 流式：返回字符串的方法继续返回可链式的实例
(string) u_str('foo-bar')->camel();            // 'fooBar'
u_str('abc')->contains('b');                   // true（非字符串结果直接返回）
u_str('中文abc')->length();                     // 5
```

> `similarity()` 按**字符**而非字节计算编辑距离。原生的 `levenshtein()` 按字节比较，
> 且对超过 255 字节的参数直接返回 -1，中文与长文本结果会完全错误。

---

### Files — 文件

```php
use zxf\Utils\Files\{File, Path, CSV, Archive};

File::readFile($path);                         // 读取为字符串
File::readFileLineByLine($path);               // 生成器逐行读取（大文件低内存）
File::readJsonFile($path);                     // 读取并解析 JSON
File::writeJsonFile($path, $data);
File::findFiles($dir, fn ($f) => ..., recursive: true);
File::copyDirectory($src, $dst);
Path::join('a', 'b/c', 'd.txt');               // 'a/b/c/d.txt'（跨平台拼接）
Path::normalize('/a//b/../c');                 // '/a/c'

CSV::read($path);                              // 读取为数组
CSV::write($path, $rows);                      // 写入
CSV::stream($path, fn ($row) => ...);          // 流式读取

Archive::open($zip)                            // 创建/打开压缩包
    ->add('/path/to/file', 'inner/name.txt')
    ->addDir('/path/to/dir')
    ->password('secret')                       // 仅 ZipCrypto/WinZip AES 有效
    ->close();

$files = Archive::open($zip, false)->extractTo('/tmp/dest');
```

> 解压前会逐个校验条目名，禁止 `..`、绝对路径与盘符逃出目标目录（防 Zip Slip）。

---

### 其它模块

```php
use zxf\Utils\Cache\MemoryCache;
use zxf\Utils\Date\{CronExpression, DateTools};
use zxf\Utils\Text\{Template, Diff};
use zxf\Utils\Validate\Validator;
use zxf\Utils\Id\Snowflake;
use zxf\Utils\Pagination\Paginator;
use zxf\Utils\Support\{Sanitizer, Event, Condition};

// 缓存
$cache = new MemoryCache();
$cache->remember('key', fn () => expensive(), ttl: 60);
$cache->set('tagged', $v, ttl: 60, tags: ['user']);
$cache->flushByTags('user');                   // 按标签批量失效

// 日期与 Cron
CronExpression::make('*/5 * * * *')->getNextRunDate();   // DateTimeImmutable|null
CronExpression::make('0 9 * * 1')->getMultipleRunDates(5);
CronExpression::isValid('*/5 * * * *');
DateTools::diffInDays($a, $b);

// 文本
Template::make('Hello {{ name }}!')            // 静态工厂
    ->with('name', 'World')                    // 单个变量
    ->with(['role' => 'admin'])                // 或批量
    ->filter('upper', fn ($v) => strtoupper($v))
    ->render();
Template::fromFile($path)->with($vars)->render();
Diff::compare($old, $new);                     // 差异数组
Diff::toUnified($old, $new);                   // 统一差异格式
Diff::toHtml($old, $new);                      // HTML 高亮

// 校验（静态方法，单值判断）
Validator::isEmail('a@b.cn');
Validator::isMobile('13812345678');
Validator::isIdCard($idNo);
Validator::isBankCard($cardNo);

// 分布式 ID
$snowflake = new Snowflake($dataCenterId, $workerId);
$snowflake->nextId();                          // int
$snowflake->nextIdString();                    // string（避免 JS 精度丢失）
Snowflake::parse($id);                         // 反解出时间戳与节点信息

// 分页
$paginator = new Paginator($total, $perPage, $page);
$paginator->totalPages();
$paginator->render('?page={page}');            // 生成分页 HTML

// 输入清理（链式声明规则）
Sanitizer::make($_POST)
    ->string('name', maxLength: 50)
    ->email('email')
    ->int('age', min: 0, max: 150)
    ->get();

// 事件
$events = new Event();
$events->listen('user.created', fn ($user) => notify($user), priority: 10);
$events->once('boot', fn () => warmUp());
$events->emit('user.created', $user);

// 声明式条件
Condition::make($value)
    ->when(fn ($v) => $v > 10, fn ($v) => "big:{$v}")
    ->when(fn ($v) => $v <= 10, fn ($v) => "small:{$v}")
    ->evaluate();
```

---

## 全局函数参考

自动加载 `src/funcs/helpers.php` 与 `src/funcs/support.php`。所有函数均以 `function_exists()` 守卫，与 Laravel 等框架的同名函数共存时不会产生重声明冲突。

### support.php（流式与数据）

| 函数 | 说明 |
|------|------|
| `u_pipeline($data)` | 创建 Pipeline |
| `u_ok($value)` / `u_err($error)` | 创建 Result |
| `u_retry($cb, $times, $sleep)` | 便捷重试 |
| `u_benchmark($cb)` | 性能测试 |
| `u_once($cb)` | 只执行一次 |
| `u_tap($value, $cb)` | 执行副作用后返回原值 |
| `u_with($value, $cb)` | 返回回调结果 |
| `u_value($value, ...$args)` | 值是闭包则调用，否则原样返回 |
| `u_throw_if($cond, $ex, $msg)` | 条件抛异常 |
| `u_throw_unless($cond, $ex, $msg)` | 条件不成立时抛异常 |
| `u_blank($v)` / `u_filled($v)` | 空值判断（`'0'`、`0` 视为非空） |
| `u_str($value)` | 创建流式字符串 `Str` 实例 |
| `u_limiter($key, $max, $decay)` | 创建限流器 |
| `u_when($v, $cb, $default)` / `u_unless(...)` | 条件执行 |
| `u_data_get($target, 'a.b', $default)` | 点号路径取值（支持数组与对象） |
| `u_data_set(&$target, 'a.b', $value)` | 点号路径写值（支持数组与对象） |
| `u_data_fill(&$target, 'a.b', $value)` | 仅键不存在时写入 |
| `u_head($arr)` / `u_last($arr)` | 首/末元素 |
| `u_collect($value)` | 创建 Collection |
| `u_env($key, $default)` | 读取环境变量（自动转换 true/false/null） |
| `u_parse_size($str)` / `u_format_size($bytes)` | 数据大小解析与格式化 |
| `u_class_basename($class)` | 类名（不含命名空间） |
| `u_class_uses_recursive($class)` / `u_trait_uses_recursive($trait)` | 递归获取 trait |

### helpers.php（通用）

包含 UUID 生成、数组与树形互转（`array_to_tree` / `tree_to_array`）、
字符串处理、浏览器/爬虫识别、请求头读取（`get_http_headers()`）、
数字与金额处理、调试输出等 60+ 个函数。

```php
uuid();                       // 紧凑唯一 ID（base62，11 字符，进程内并发安全）
uuid('0123456789');           // 可指定字符集
uuid_node_id(7);              // 集群部署时显式设置节点 ID（0–65535）
array_to_tree($rows, 0);      // 二维数组转树（防循环引用）
tree_to_array($tree);         // 树转二维数组
get_http_headers();           // 请求头（键统一大写，兼容 CLI/CGI）
is_wechat_browser();          // 微信内置浏览器识别
```

---

## 测试与静态检查

测试套件不依赖 PHPUnit——`composer install` 之后即可直接运行：

```bash
composer test                       # 运行 144 个回归测试
php tests/run.php --filter=Version  # 只运行类名含 Version 的用例
composer lint                       # 静态检查（语法 / strict_types / 依赖 / 结束标记）
```

`composer lint` 的检查项：

1. **语法检查**：对 `src/` 下全部 248 个文件执行 `php -l`
2. **strict_types 覆盖率**：确保所有类文件均已声明
3. **第三方命名空间**：扫描 `use` 语句，发现未声明（非本包 / 非内嵌 / 非可选集成点）的命名空间根 —— 本包为零第三方运行时依赖，误引外部库会被拦截
4. **PHP 结束标记**：检测注释中误写的 `?>`（会提前终止 PHP 代码块、截断文件）

新增测试用例：在 `tests/Unit/` 下新建 `*Test.php`，继承 `zxf\Utils\Tests\TestCase`，
以 `test` 开头的 public 无参方法会被自动发现。断言使用 `zxf\Utils\Tests\Assert`。

---

## 安全说明

- **XSS 过滤请在解析后的 DOM 上进行**（本包 `XssCleaner` 即如此实现）。正则方案对无引号属性、协议中插空白、实体编码等手法均存在绕过。
- **输出到 HTML 的首选方案永远是转义**（`XssCleaner::escape()` / `htmlspecialchars`），过滤仅适用于必须保留富文本格式的场景。
- **解压不可信压缩包前必须校验条目名**（`Archive` 已内置 Zip Slip 防护）。
- **进程锁只在单机的进程间有效**（`Locker` 基于 `flock`），分布式场景请使用 Redis 等外部锁。
- **内存型组件不跨进程共享**（`RateLimiter`、`MemoryCache`），多进程 FPM 下每个进程各有一份计数。

---

## 常见问题

**Q：全局函数与 Laravel 冲突怎么办？**
所有函数均有 `function_exists()` 守卫，已存在的同名函数不会被覆盖。

**Q：为什么 `usleep()` 相关代码要显式取整？**
`usleep()` 是内部函数，在 `strict_types=1` 下即使传入整数值的 `float`（如 `100.0`）也会抛 `TypeError`——这与用户态 `int` 参数的行为不同，极易踩坑。

**Q：为什么注释里不能写 `?>`？**
PHP 词法分析器在 `//` 注释状态下遇到 `?>` 会终止 PHP 代码块，导致文件被截断。本包的 `composer lint` 会自动检测该问题。

**Q：`Version::satisfies()` 支持哪些约束？**
`||` 或组、空格/逗号且条件、`1.0 - 2.0` 范围、`~`、`^`、`x` 通配、`>=/<=/>/</=/!=/<>` 比较符、精确匹配，并允许省略段（`>=1.0` 等价于 `>=1.0.0`）。

---

## 变更记录

本轮的缺陷修复与增强详见 [CHANGELOG.md](CHANGELOG.md)。

## 许可

MIT
