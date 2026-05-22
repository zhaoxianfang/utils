# 通用工具包和通用函数集

![](https://img.shields.io/packagist/dt/zxf/utils) ![](https://img.shields.io/github/stars/zhaoxianfang/utils.svg) ![](https://img.shields.io/github/forks/zhaoxianfang/utils.svg) ![](https://img.shields.io/github/tag/zhaoxianfang/utils.svg) ![](https://img.shields.io/github/release/zhaoxianfang/utils.svg) ![](https://img.shields.io/github/issues/zhaoxianfang/utils.svg)

基于 PHP 8.2+ 的现代化工具库，严格类型检查，丰富的实用类和函数。

## 📦 安装

```bash
composer require zxf/utils
```

### 配置

```bash
# Laravel 中发布配置文件
php artisan vendor:publish --tag=utils-config
```

## 核心特性

- **PHP 8.2+ 严格类型**：所有文件均启用 `declare(strict_types=1)`
- **现代化语法**：充分利用 `match`、联合类型、`readonly` 类、`never` 返回类型等
- **零全局副作用**：无隐式时区设置、无意外输出
- **丰富的辅助函数**：50+ 全局便捷函数

## 常用函数

```php
// 极速并发 UUID 生成器
uuid(): string

// 数据获取与设置
u_data_get($target, 'user.name', 'default');
u_data_set($array, 'user.name', 'John');

// 条件执行
u_when($value, fn ($v) => $v * 2);
u_unless($value, fn () => 'empty');

// 结果封装
$result = u_ok($data);   // Result::ok()
$result = u_err('Error'); // Result::err()

// 性能测试
u_benchmark(fn () => heavyOperation());

// 重试机制
u_retry(fn () => fetchData(), times: 3, sleep: 100);

// 速率限制
u_limiter('api', 60, 60)->attempt();

// 数据大小格式化
u_format_size(1536); // "1.5 KB"
u_parse_size('2.5GB'); // 2684354560
```

## 新增工具类

| 类 | 说明 |
|----|------|
| `Support\Pipeline` | 数据流水线处理，支持异常捕获、条件执行 |
| `Support\Result` | 成功/失败结果封装，链式操作 |
| `Support\Retry` | 重试机制，支持指数退避、抖动策略 |
| `Support\Benchmark` | 性能基准测试，支持多次平均和对比 |
| `Support\Once` | 确保回调只执行一次 |
| `Support\Fluent` | 流式接口构建器基类 |
| `Support\EnumHelper` | 枚举工具类（names/values/random/options） |
| `Support\RateLimiter` | 内存速率限制器 |
| `Support\Condition` | 声明式条件执行构建器 |
| `Support\Defer` | Go 风格延迟执行（LIFO） |
| `Support\Sanitizer` | 输入数据清理与规范化 |
| `Net\Url` | URL 解析与链式构建 |
| `Net\Domain` | 域名验证、Punycode、DNS/WHOIS 查询 |
| `Convert\DataSize` | 数据大小转换（B/KB/MB/GB/TB/PB） |
| `Convert\Number` | 数字格式化、中文大写、缩写、序数 |
| `Convert\Color` | 颜色空间转换（HEX/RGB/HSL/HSV/CMYK） |
| `Convert\Unit` | 单位换算工具 |
| `Security\XssCleaner` | XSS 清理工具 |
| `Security\Password` | 密码哈希、强度检测、安全生成、HIBP 检测 |
| `Security\Hash` | 通用哈希工具（MD5/SHA/HMAC/文件哈希/CRC32） |
| `Support\Arr` | 静态数组工具（点符号路径、扁平化、分组、笛卡尔积） |
| `Convert\Json` | JSON 编码解码、路径查询、合并、文件读写 |
| `Files\CSV` | CSV 读写、流式处理、编码转换、JSON 互转 |
| `Text\Template` | 轻量级模板引擎（变量、条件、循环、过滤器） |
| `Date\CronExpression` | Cron 表达式解析与下次执行时间计算 |
| `Cache\MemoryCache` | 带 TTL 和标签的内存缓存，支持 remember 和原子递增 |
| `Files\Path` | 跨平台路径处理（拼接、规范化、相对路径、扩展名） |
| `Data\Random` | 安全随机数据生成（密码、UUID v4、颜色、假数据） |
| `Security\Token` | HMAC 签名 Token 生成与验证（类似简化版 JWT） |
| `Support\Timer` | 纳秒级计时器与超时控制，支持高精度睡眠 |
| `Text\Diff` | 文本差异比较（LCS 算法），支持统一差异和 HTML 高亮 |
| `Files\Archive` | ZIP 压缩解压，支持链式 API、密码保护和目录递归 |
| `Support\Locker` | 基于 flock 的进程级互斥锁，支持超时和同步回调 |
| `Support\Event` | 轻量级事件分发器，支持通配符和一次性监听器 |
| `Data\Version` | 语义化版本号（SemVer）解析、比较和约束匹配 |

## 现有模块

### BarCode 一维条形码
[一维条形码文档](src/BarCode/README.md)

### QrCode 二维码
[二维码文档](src/QrCode/README.md)

### Xml 转换处理
[查看文档](src/Xml/readme.md)

### Minify 代码压缩
[查看文档](src/Minify/README.md)

### Image 图片处理

### Http 网络请求

### Crypto 加密解密

### Data 数据转换、Tree 树形结构、大数计算

### Date 日期时间工具

### Validate 数据验证

### Array 增强数组操作

### Str 字符串处理

## 更多
