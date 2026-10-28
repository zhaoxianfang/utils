# 变更记录

本项目的所有重要变更都会记录在此文件中。

## [未发布] — 遗留疑似缺陷清零（第三轮）

第二轮 CHANGELOG 里「已记录但未改」的 8 项全部核实并修复，另在修复过程中连带发现并修掉 `Curl` 的 4 个连带缺陷。

### 本轮修复清单

| # | 位置 | 类别 | 问题 | 修复 |
|---|------|------|------|------|
| 1 | `QrCode/Common/BitArray::setRange()` | 正确性 | 掩码循环写成 `$j < $lastBit`（丢最后一位），单 bit 区间实际一位都不置位；整字分支用 `0x7fffffff` 漏掉第 31 位 | 改为 `$j <= $lastBit`；整字掩码改 `0xFFFFFFFF`（PHP int 为 64 位，不能用 `-1`，否则污染 32~63 位） |
| 2 | `QrCode/Common/BitArray::isRange()` | 正确性 | 同样用 `0x7fffffff`，第 31 位永不参与比较 | 与 `setRange()` 统一为 `0xFFFFFFFF` |
| 3 | `Data/BigNumberCalculator::power()` | 正确性 | 负指数被当作普通数字串逐位处理，静默返回错误结果 | 显式抛 `InvalidArgumentException`（底数为 ±1 时仍返回 ±1） |
| 4 | `Data/Tree::getParentAndMeNodes()/getParentNodes()` | 一致性 | 未 `array_reverse`，顺序为「自身→根」，与同族 `*Ids` 的「根→自身」方向相反 | 统一反转，两个 `*Nodes` 与对应 `*Ids` 顺序一致（**行为变更**） |
| 5 | `Data/Tree::removeNode()` | 正确性 | 只删自身，子节点 pid 指向不存在的父节点成为孤儿 | 默认级联删除全部子孙（`getChildrenIds()`），新增 `$cascade = false` 可只删自身；同时 `array_values` 重排，避免留下空洞键 |
| 6 | `Date/DateTools::age()` | 正确性 | `diff()->format('%y')` 不带符号，未来日期也返回正数 | 生日晚于当前时间时返回负数 |
| 7 | `Array/DataArray::stddev()` | 正确性 | 均值取自 `avg()`（分母为全部元素数），与参与方差的数值子集不一致 → 混入非数值时结果偏差 | 均值改为 `array_sum($values) / count($values)`，与方差同一集合 |
| 8 | `Array/DataArray::pivot()` | 正确性 | `max`/`min` 与未知聚合方式一律按求和处理（静默错误）；avg 需二次遍历 | 真正实现 `max`/`min`（`null` 初始化区分「无数据」与 0，空组合回落 0）；聚合方式大小写归一 + 白名单校验，未知值抛 `InvalidArgumentException`；单次遍历同时计数 |
| 9 | `BarCode/Renderer/HtmlRenderer` | 布局 | 传给 `analyzeBarcodeStructure()` 的是内容宽度（不含左边距），内部又减一次 `marginLeft`，导致 regions 整体左移，EAN/UPC 分段文字错位 | 改传「`marginLeft` + 内容宽度」的右边缘坐标（与 PNG/SVG 渲染器一致） |
| 10 | `OAuth2/Gateways/Wecom::refreshToken()` | 接口一致性 | 直接返回 array（`getAccessToken()` 结果），与接口声明的 `bool` 冲突 | 写入 `$this->token` 后返回 `true`，令牌通过 `getToken()` 读取 |
| 11 | `OAuth2/Connector/Gateway::refreshToken()` | 接口一致性 | 基类「空实现返回 true」，未实现刷新的 7 个网关（Alipay/Aliyun/Douyin/Gitee/Github/Qq/Sina）会让调用方误判令牌已续期 | 基类改为返回 `false`（能力缺失 ≠ 成功） |

### Curl 连带修复（由 #12 引出）

| # | 位置 | 类别 | 问题 | 修复 |
|---|------|------|------|------|
| 12 | `Http/Curl::parserResponse()` | 崩溃 | 设置 `CURLOPT_FILE` 后 `curl_exec()` 返回 bool，`substr(true, …)` 在 `strict_types=1` 下抛 TypeError —— **`download()` 必定失败** | 非字符串结果统一归一为 `''`；`CURLINFO_CONTENT_TYPE` 为 null 时转 `''`（否则 `stripos(null)` 再次 TypeError） |
| 13 | `Http/Curl::download()/downloadByRange()` | 接口约定 | 返回值只有 `run()` 的结果，不含落盘信息，无法判断下载是否成功 | 抽出 `downloadToFile()`，返回 `{success, file, size, status, error}`：`success` = 无传输错误 && 2xx && 落盘字节 > 0 |
| 14 | `Http/Curl::downloadToFile()` | 正确性 | 默认参数 `CURLOPT_HEADER => true`，与 `CURLOPT_FILE` 组合会把响应头一起写进文件（实测 14 字节文件写出 172 字节） | 下载前显式 `CURLOPT_HEADER => false` |
| 15 | `Http/Curl::putFileFromUrlContent()` | 崩溃 | 复用实例时 `$this->ch` 已被置空，`curl_setopt(null, …)` 抛 TypeError | 入口补 `initCurl()` |
| 16 | `Http/Curl`（4 处） | PHP 8.5 兼容 | `curl_close()` 自 8.5 起弃用，任何请求都会产生弃用告警 | 移除调用，改为断开引用交由 GC（8.0 起 `curl_close()` 本已无作用） |

### 验证

- `php tests/run.php` → **159 项全部通过**
- `php tests/lint.php` → **LINT PASSED**
- `php@8.2.28` 本轮 9 个改动文件 `php -l` → **全部通过**
- 功能实测（34 项断言，PHP 8.5 + `E_ALL` 无告警/弃用）：
  - `BitArray`：单 bit 区间、跨字区间、`setRange(0,64)` 全置位、`isRange()` 对应判定
  - `power`：`2^10`、`(-2)^3/-2^4`、`x^0`、`1^-5`、`(-1)^-3`、负数指数抛异常
  - `stddev`：混入非数值、经典样本（=2.0）、空数组
  - `pivot`：count/sum/max/min/avg + 大写 `MAX` + 非法值抛异常
  - `Tree`：`*Ids` 与 `*Nodes` 顺序一致、级联删除、`$cascade=false`、键重排
  - `age`：过去日期、未来日期（负）、当天（0）
  - `Curl`：`download()` 落盘 14 字节纯净内容、404 判定 `success=false`、连接失败 `error.no=7`、`downloadByRange()`、`putFileFromUrlContent()`、常规 `get()`
  - `HtmlRenderer`：`rightQuietZone.right` 等于「左边距 + 内容宽度」，与最后一根条右边缘对齐（修复前少 10px）

## [未发布] — 注释质量全面提升、示例纠正与逻辑缺陷修复（第二轮）

在「覆盖度已达 100%」之后，进一步做**注释质量**审计（不只看有没有注释，还看是否说清功能、参数/返回值是否准确、示例是否可用）。

**质量指标变化**（脚本 `/tmp/quality_audit.php` 口径：缺 docblock / 缺 `@param` / 缺 `@return` / 描述过短）：

| 指标 | 修复前 | 修复后 |
|------|--------|--------|
| 缺 docblock | 8 | 0（余 5 项为 docblock 内示例伪函数，非真实声明） |
| 缺 `@param` | 480 | **0** |
| 缺 `@return` | 467 | **0** |
| 描述过短 | 512 | 0（余 1 项已人工扩写） |
| 有问题的条目 | **1018** | **6（均为示例伪函数误报）** |

覆盖度维持：类级 **173/173（100%）**、方法级 **2906/2906（100%）**；相邻重复 docblock **0**。

### 重点重写：全局函数文档

- `src/funcs/support.php`：24 个全局函数由「一句话注释」重写为含用途、`@param`/`@return`/`@throws` 与**可运行示例**的完整 docblock；并纠正了原先**错误的示例**：
  - `u_pipeline` 示例原写 `->then()->then()->run()`，实际 `then()` 是终止方法、且不存在 `run()`；改为 `->through(...)->then()`。
  - `u_ok`/`u_err` 示例原调用不存在的 `getValue()`/`getError()`；改为真实的 `unwrap()`/`error()`。
  - `u_blank` 原注释把 `0`、`'0'` 说成「空」，与实现相反；已改正并附实测结果。
  - `u_parse_size` 示例原用 `'2M'`（会抛异常），改为 `'2MB'`。
- 所有示例均以真实代码运行核对输出（如 `u_pipeline(1)->through(+1,*2)->then() === 4`、`u_format_size(1536) === '1.5 KB'`）。

### 其他文档修正

- 合并/清理 **10 处相邻重复 docblock**（Pipeline、Retry、RateLimiter、QrCode×3、MdToH5、Github、SiteMapGenerator、TextToImg 的孤立 `@var`）。
- 为 `Curl`、`CSV`、`Hash`、`Password`、`XssCleaner` 补/移正类级 docblock；`SiteMapGenerator` 的「功能列表 + 使用示例」合并为单个类 docblock。

### 本轮修复的逻辑缺陷（已逐项实测）

| # | 位置 | 类别 | 修复 |
|---|------|------|------|
| 1 | `Security/XssCleaner::isSafeUrl()` | 安全 | 反斜杠会被浏览器等价为斜杠，`\evil.com`/`/\evil.com` 未被拦截；现拒绝以 `\` 或 `/\` 开头 |
| 2 | `Files/CSV::write()` | 安全 | 表头行未做公式注入防护（仅数据行做了）；表头同样过 `sanitizeRow()` |
| 3 | `Net/Ip::isPrivate()/isReserved()` | 正确性 | 非法 IP 经 `!(bool)filter_var()` 反被判为 true；先校验格式，非法一律 false |
| 4 | `QrCode/Common/BitUtils::unsignedRightShift()` | 崩溃 | `$b = 0` 时 `0x40000000 >> -1` 抛 ArithmeticError；`$b<=0` 直接返回（负数取 32 位无符号值） |
| 5 | `Http/Request::segments()` | 崩溃 | 无 URI 时 `trim(array,'/')` 抛 TypeError；先 `implode('/', $default)`，空则返回 `[]` |
| 6 | `Http/Request::isPjax()` | 崩溃 | `$pjax=true` 时从 `:bool` 返回字符串触发 TypeError；返回类型改为 `bool\|string` |
| 7 | `Http/Request::referrer()` | 正确性 | 读取 `HTTP_REFERRER`（拼写错误），恒返回默认值；改为 `HTTP_REFERER` |
| 8 | `funcs/helpers.php::dir_is_empty()` | 正确性/崩溃 | 语义与函数名相反（发现条目返回 true），且对不可打开目录 `closedir(false)` 抛 TypeError；已改正语义并加句柄判空、try/finally |
| 9 | `funcs/helpers.php::relative_path()` | 崩溃 | `realpath()` 失败返回 false 参与字符串函数触发 TypeError；失败时原样返回入参 |
| 10 | `Sundry/Command::getOptVal()` | 崩溃 | 无值选项（`-g`）存为 true，与 `array\|string\|null` 返回类型冲突；放宽为 `array\|string\|bool\|null` |
| 11 | `Date/DateTools::chineseZodiac()` | 崩溃 | 负年份取模得负下标；归一化为 `((y%12)+12)%12` |
| 12 | `Cache/MemoryCache::increment()/decrement()` | 正确性 | 写回时丢失原 TTL 与 tags；改为保留元信息（TTL 按剩余时间续期） |
| 13 | `Convert/Json::fromFile()` | 崩溃 | 根节点为标量时 `decode()` 返回非数组，违反 `array` 返回类型；统一 `(array)` 包裹 |
| 14 | `Image/ImagickTool::createDiagonalCollage()` | 崩溃 | 仅 1 张图时分母为 0 抛 DivisionByZeroError；分母兜底为 1 |
| 15 | `Crypto/Random::dateTime()` | 崩溃 | 起止颠倒时 `random_int(min>max)` 抛 ValueError；自动交换 |
| 16 | `Crypto/AES::decryptXTS()` | 崩溃 | `openssl_cipher_iv_length()` 可能返回 false 直接用于 `substr`；显式判 false 抛异常 |
| 17 | `OAuth2/Gateways/Xiaomi::getRedirectUrl()` | 告警 | 缺 `state` 配置时产生未定义索引；改为 `?? ''` |

### 已记录但本轮未改的疑似问题（已在第三轮全部修复，见上方条目）

`BitArray::setRange()` 少置一位、`Curl::download()/downloadByRange()` 返回值不含下载结果、`BigNumberCalculator::power()` 负指数静默错误、`Data/Tree` 父系接口方向不一致与 `removeNode()` 不级联、`DateTools::age()` 未来日期丢符号、`DataArray::pivot()/stddev()` 聚合偏差、`HtmlRenderer` 的 `totalWidth` 少左边距、OAuth2 `refreshToken()` 语义不一致。

### 验证

- `php tests/run.php` → **159 项全部通过**
- `php tests/lint.php` → **LINT PASSED**（248 文件语法/strict_types/无第三方依赖）
- `php@8.2.28` 对全部 176 个自有文件 `php -l` → **全部通过**
- 注释批次经 token 流比对确认「仅注释变更」；本轮 17 处逻辑修复另做功能实测（18 项断言全部 PASS）

## [未发布] — 中文注释与示例补全（第一批）及附带缺陷修复

先做「注释覆盖度审计」摸清真实缺口（而非盲目开工），再按「缺口大 + 文件小」排序逐批补全。
审计口径：自有类文件 174 个（排除第三方 fork 的 PHPMailer）。

**覆盖度变化**：类级 docblock 153/173 → **158/173（88.4% → 91.3%）**；
方法级 docblock 2667/2906 → **2717/2906（91.8% → 93.5%）**。

### 本批补全的文件

| 文件 | 补全内容 |
|------|---------|
| `src/Support/Sanitizer.php` | 类级 docblock（含 @example）+ 21 个方法注释 |
| `src/Convert/DataSize.php` | 11 个方法注释（含各 toXxx/比较方法） |
| `src/OAuth2/Helper/Str.php` | 类级 docblock + 4 个方法注释 |
| `src/OAuth2/Helper/AES.php` | 类级 docblock + 5 个方法注释（并修正错误注释，见下） |
| `src/OAuth2/Helper/Encryptor.php` | 类级 docblock + 方法注释（含返回结构说明） |
| `src/OAuth2/Helper/ConstCode.php` | 类级 docblock + 方法注释 |
| `src/BarCode/Generator/CodabarGenerator.php` | 6 个方法注释 |
| `src/BarCode/Generator/EAN13Generator.php` | 类级 docblock + 6 个方法注释 |

### 补全过程中发现并修复的缺陷

| # | 位置 | 类别 | 修复 |
|---|------|------|------|
| 1 | `src/OAuth2/Helper/ConstCode.php` `getTypeConst()` | 类型混淆 | 用 `==` 比较 `$type`，而参数声明为 `bool\|string`：传入 `true` 时 `true == 'app'` 为真，会误判为 App 渠道。改为 `===`（已验证：传 `true` 现在返回原渠道 1，修复前误得 21） |
| 2 | `src/OAuth2/Helper/AES.php` `getMode()` | 错误注释 | 原 `@param string\|null $method` 与实际参数 `$key` 完全不符，已修正为正确描述 |
| 3 | `src/OAuth2/Helper/AES.php` `encrypt()/decrypt()` | 防御加固 | `openssl_encrypt/decrypt` 返回 `string\|false`，失败时返回 false 会撞上返回类型 `string` 而抛出难定位的 TypeError。改为显式判 false 并抛 `InvalidArgumentException` |
| 4 | `src/OAuth2/Helper/Encryptor.php` `decryptData()` | 防御加固 | 原仅判断真假，若解密结果是标量（如 `5`）会违反返回类型 `array` 触发 TypeError。改为 `is_array()` 校验后抛 `OAuthException` |
| 5 | `src/Support/Sanitizer.php` `strict()` | 错误注释 | 原注释称「抛出异常」，实际是把错误记录进 `errors()`（静默降级），已修正为准确描述 |

### 验证方法（可复用）

新增 `/tmp/verify_comments_only.php`：对同一文件的 git HEAD 版本与当前版本做 **token 流比对**
（剔除 `T_COMMENT`/`T_DOC_COMMENT`/`T_WHITESPACE` 后逐 token 比较），用以**证明「仅注释变更、逻辑零改动」**，
比人工 diff 更可靠。本批 8 个文件中的 5 个纯注释文件全部通过（✓ 逻辑零改动），
另外 3 个含「有意修复」的文件单独做了功能冒烟验证。

- `php tests/run.php` → 159 项 **ALL PASSED**
- `php tests/lint.php` → **LINT PASSED**
- 全部改动文件 `php -l` 通过

### 剩余缺口（按方法缺口降序）

Http 51、Support 33、BarCode 27、Sundry 25、QrCode 24、Net 18、OAuth2 14（另有 8 个类缺类级注释）、
Convert 12、Files 9 等，合计约 189 个方法与 15 个类待补全。

## [未发布] — PHP 8.2+ 兼容性审计与第三轮深度审查

承接上轮审查，完成「PHP 8.2+ 全面支持」专项审计与第三轮错误 / 异常 / 漏洞 / 逻辑 /
功能 / 架构排查。验证手段：**PHP 8.2.28 全量 248 文件 `php -l` 零语法错误**、
**完整测试套件 159 项在 PHP 8.2 下全 PASSED（E_ALL 无弃用告警）**、lint 全过。

### PHP 8.2+ 兼容性修复

| # | 位置 | 修复 |
|---|------|------|
| 1 | `src/Data/IDCardGenerator.php`、`src/Date/CronExpression.php`（3 处）、`src/Date/TimeZone.php` | `DateTime(Immutable)::createFromInterface()` 是 **PHP 8.3** 才引入的方法，8.2 上运行即 `Error: Call to undefined method`。共 5 处改为跨版本写法（`setTimestamp()` + `setTimezone()`） |
| 2 | `src/Data/Random.php`、`src/Security/Password.php` | 捕获 `Random\RandomException`（**PHP 8.3** Random 扩展类，8.2 不存在）改为捕获 `\Exception`，消除 8.2 下类名不解析的隐患 |

兼容性排查结论（已排除）：类型化常量、属性钩子、`#[Override]`、`json_validate`、
`mb_str_pad`、`array_find*`、8.3/8.4 专有函数均未使用；无 `utf8_encode`/`create_function`
等弃用 API；**继承感知扫描确认自有代码 0 处动态属性赋值**（无 PHP 8.2 Deprecated 风险）。

### 第三轮：安全加固与缺陷修复

| # | 位置 | 类别 | 修复 |
|---|------|------|------|
| 1 | `src/Image/TextToImg.php` | 资源耗尽 / 一致性 | 画布尺寸**此前只有下限、没有上限**，`instance()` 甚至完全未钳制。新增 `MAX_CANVAS_SIZE = 20000` 与 `normalizeDimension()`，在构造器 / `instance()` / `setSize()` 三处统一归一化：下限钳 1、超限抛 `InvalidArgumentException`。避免超大尺寸让 GD 尝试 TB 级分配 |
| 2 | `src/Http/Curl.php` | 资源泄漏 / 逻辑 | 写入文件方法在 `fwrite` 失败时提前 `return` 导致**文件句柄泄漏**；且 `! fwrite(...)` 会把「写入 0 字节（空响应，合法）」误判为失败。改为 `try/finally` 保证关闭，并用 `=== false` 严格判定 |
| 3 | `src/Http/Request.php` | 防御加固 | 解析 `Accept*` 请求头的 3 处正则含冗余嵌套量词 `([\w\-]+)+`，移除语义等价的冗余外层 `+` 消除回溯歧义；`parse()` 增加单片段 512 字符防御性裁剪（请求头由客户端完全控制） |
| 4 | `src/Text/MdToH5.php` | 防御加固 | 水平线正则 `(\1[ \t]*)*` 为量词套量词，改为等价但每轮必消耗字符的 `(?:[ \t]*\1){2,}`，回溯由指数降为线性 |
| 5 | `src/Data/Tree.php`、`src/Xml/Array2XML.php` | 类型安全 | 2 处 `in_array()` 缺失 `true` 严格标志，补齐（全库其余均已严格） |

### 本轮核查为「已安全 / 无需修改」的项

- **Curl 默认安全**：`SSL_VERIFYPEER=true`、`SSL_VERIFYHOST=2`、`TIMEOUT=10`、`CONNECTTIMEOUT=10`
- **时序安全**：JWT / AES / Hash / Token 的签名比较均使用 `hash_equals`
- **Zip Slip**：`Files/Archive::extract()` 有 `realpath` 基准 + `str_starts_with` 校验
- **无硬编码密钥**：Crypto 各算法均要求调用方传入，AES 默认 `aes-256-cbc`
- **`strpos` 用法**：全库均 `!== false`，无位置 0 误判
- **文件锁**：`Cache/FileCache`、`Log/Logger`、`Support/Locker` 均已用 `flock`

### 验证

- `php tests/run.php` → 159 项 **ALL PASSED**
- `php tests/lint.php` → **LINT PASSED**（248 文件）
- PHP 8.2.28 `php -l` 全量 → **0 语法错误**
- 新增行为验证：正则等价性（Accept 头 9 样例 / Markdown hr 16 样例判定与捕获**完全一致**）、
  TextToImg 尺寸归一化 5 场景（超限抛异常、0/负值钳 1、正常尺寸不变、`instance()` 复用路径）

## [未发布] — 全面代码审查：安全 / 正确性 / 性能 / 架构修复轮

对全库 248 个 PHP 源文件做系统性审查，覆盖潜在性能问题、安全隐患及错误、异常、漏洞、
逻辑问题、功能问题、系统设计与架构，并逐项修复。本轮新增回归测试
`tests/Unit/ReviewFixesTest.php`（12 项），全量测试 159 项全 PASSED、lint 248 文件全过。

### 安全修复

| # | 位置 | 修复 |
|---|------|------|
| 1 | `src/funcs/helpers.php` `download_url_file()` | 移除 `$_GET['url']` 自动读取，杜绝任意文件读取 / SSRF；改为强制显式 `$url` 参数、拒绝远程 `scheme://`、可选 `$allowedBaseDir` 目录穿越防护、文件名 CRLF 清洗 |
| 2 | `src/funcs/helpers.php` `base64_to_image()` | 新增图片扩展名白名单（png,jpg,jpeg,gif,webp,bmp,wbmp,xbm,ico），拒绝 `data:image/php` 之类写入 `.php` webshell |
| 3 | `src/funcs/helpers.php` `buildRequestFormAndSend()` | 修复反射型 XSS（action/name/value 经 `htmlspecialchars(..., ENT_QUOTES)` 转义）；内部全局函数 `traverseChildArr` 改为闭包变量，避免二次调用重复声明函数致命错误 |
| 4 | `src/funcs/helpers.php` `str_en_code()` | 移除硬编码默认密钥 `'wei_si_fang'`（杜绝「无密钥等价于明文」），空密钥抛 `InvalidArgumentException`；修复 `expiry=0` 永不过期语义（详见正确性 #7） |
| 5 | `src/OAuth2/Connector/Gateway.php` | `$checkState` 默认改为 `true`（默认开启 OAuth2 CSRF state 校验），新增 `disableCheckState()` 供显式关闭 |
| 6 | `src/Net/Ip.php` `getClientIp()` | 改为可信代理模型：默认不信任任何转发头（防 IP 伪造），仅当 `REMOTE_ADDR` 命中 `$trustedProxies` 才解析 `X-Forwarded-For` 等 |
| 7 | `src/Support/Event.php` / `src/Array/DataArray.php` | `matchWildcard()` / `searchKey()` 修复 `preg_quote` 前 `*?` 被错误转义导致通配符完全失效（潜在逻辑绕过） |
| 8 | `src/Support/DataArray.php` `fromXml()` 等 | `simplexml_load_string` 增加 `LIBXML_NONET`（XXE 防护） |

### 正确性与逻辑修复

| # | 位置 | 修复 |
|---|------|------|
| 1 | `src/funcs/helpers.php` `class_basename()` | 修复 Unix 下分隔符方向错误（原为 `str_replace('/','\\',...)`），现 `basename(str_replace('\\','/',$className),'.php')` |
| 2 | `src/funcs/helpers.php` `truncate()` | 调整 `$start >= $strLen` 检查顺序，修复越界 `start` 的 off-by-one |
| 3 | `src/Data/BigNumberCalculator.php` `divide()` | 除数字符串 `"0"` 也被拦截（`\|\| $b === '0'`），修复字符串 "0" 绕过除零保护 |
| 4 | `src/Data/IDCardGenerator.php` `generateSequenceCode()` | 修复序列码奇偶性与性别不符（seq=999 女性分支错误），现用 `($sequence\|1)`/`($sequence&~1)` 钳制 1..999 |
| 5 | `src/Support/Fluent.php` `assertPropertyType()` | **修复交叉类型（A&B）被当作联合类型（A\|B）处理**：缺失 `use ReflectionIntersectionType` 导致 `instanceof` 始终 false 并回退 union 分支；现交叉类型要求全部满足 |
| 6 | `src/Support/Arr.php` `set()` | null 键写入非数组值时抛 `InvalidArgumentException`（原返回非数组违反 `:array` 返回类型） |
| 7 | `src/Support/Fluent.php` `str_en_code()`（详） | `expiry=0` 现嵌入时间戳 `0` 表示永不过期，并修复 `substr(...)==0` 死代码（宽松比较对真实时间戳恒为 false）；解码端用 `(int)` 严格判定 `===0 \|\| 未来` |
| 8 | `src/Support/Arr.php` `unique()` | O(n²) `in_array` 改为基于 hash 键去重（`spl_object_hash`/`md5(serialize)`/`get_debug_type`） |

### 性能与架构修复

| # | 位置 | 修复 |
|---|------|------|
| 1 | `src/Http/Curl.php` | `debug()` 将流存入属性避免句柄泄漏；`closeDebug()`/`__destruct()` 关闭 debugStream 与 curl 句柄；`download()`/`downloadByRange()` 用 `try/finally` 关闭文件句柄 |
| 2 | `src/Http/Response.php` `sendLargeFile()` | 替换 `fread(fopen(...), filesize(...))`（整文件入内存 + 句柄泄漏）为 `stream_get_contents` + `try/finally` + `fclose` |
| 3 | `src/funcs/helpers.php` `img_base64()` | 替换 `fread(fopen(...), filesize(...))` 为 `file_get_contents` |
| 4 | `src/Data/Tree.php` | 新增懒加载索引（`buildIndex`/`findNode`/`invalidateIndex`），`isDescendant`/`getParentAndMeIds` 等由 O(n²) 线性扫描降为 O(n)，并在 `setData`/`addNode`/`removeNode`/`updateNode`/`reset`/`where` 中失效索引 |
| 5 | `src/funcs/helpers.php` `create_dir_or_filepath()` | 修复传入「目录路径/」时 `dirname()` 误剥离目标目录本身（把 `a/b/c/` 当成父级 `a/b` 计算），导致 `touch` 失败、目标目录永不创建；现正确区分目录/文件目标 |

### 验证

- `php tests/run.php`：12 类 / **159 项**全 PASSED（本轮新增 `ReviewFixesTest` 12 项）。
- `php tests/lint.php`：248 文件语法通过，全部声明 `strict_types=1`，无未声明第三方命名空间，无 PHP 结束标记。
- 重点回归用例：`Event` 通配符匹配、`DataArray::searchKey` 含 `/` 模式、`Arr::set` null 键、`BigNumber` 除 "0"/空串、`IDCardGenerator` 性别奇偶、`Fluent` 交叉类型（A&B 必须全满足 / A|B 任一即可）、`Ip::getClientIp` 可信代理、Tree 祖先查询、`class_basename` Unix、`buildRequestFormAndSend` 转义+二次调用、`str_en_code` 强制密钥+`expiry=0` 永不过期、`base64_to_image` 扩展名白名单。
- 冒烟验证：`create_dir_or_filepath` 文件场景（Curl cookie/debug 文件）与目录场景（base64_to_image）均正确创建；`str_en_code` 在 `expiry=0/60/3600` 可往返、过期 TTL 返回空串。

---

## [未发布] — BarCode / QrCode 扫码一致性复检轮（旧产物清理 + 渲染加粗）

针对「用 `tests.php` 生成图扫码仍报错」的复检，逐项定位后确认并修复：

### 根因

1. **源码树残留修复前的旧产物**：`src/BarCode/test_output_*`（6 批）与
   `src/QrCode/test_output_*`（3 批）是**编码表修复前**由旧版 `tests.php`
   输出到包内的 PNG（旧 ITF-14/Codabar 图存在确定性误读：如 itf14_basic
   旧图被 zbarimg 读成 `18400141255793`、codabar_basic 读不出），用户对旧图
   扫码自然复现旧症状。上一轮 CHANGELOG 虽声明「输出目录已改 + 残留已删」，
   但**实际只改了 `src/BarCode/tests.php`，残留目录未删、`src/QrCode/tests.php`
   仍写包内目录**——承诺与实际不符。
2. **默认渲染模块过细**：PNG/SVG 默认 `moduleSize=2`，EAN-13 整图仅约
   234×125px（95 模块 × 2px），真机/弱扫码器易把细条误读（如 `6901234567892`
   在个别工具下被读成 `6907834567898`——后者为合法 EAN-13 但内容错误）。
3. **Bookland EAN（978/979 前缀）的显示差异是扫码器功能**：ISBN/ISSN 条码
   被手机扫码 App 自动转换成 ISBN-10 显示（如 `9780201379624` 显示为
   `0201379627`、`9783161484100` 显示为 `316148410X`，均为其合法 ISBN-10），
   **不是条码内容错误**——zbar/ZXing 等原始输出工具始终读到完整 13 位原文。

### 修复

| # | 位置 | 修复 |
|---|------|------|
| 1 | 源码树 | 删除 `src/BarCode/test_output_*` 与 `src/QrCode/test_output_*` 全部残留产物目录（已被 `.gitignore` 忽略，属历史脏产物），杜绝误扫旧图 |
| 2 | `src/QrCode/tests.php` | 输出目录启用 `sys_get_temp_dir()`（原注释掉的实现），与 BarCode 侧及 CHANGELOG 声明对齐；`.gitignore` 补 `src/QrCode/test_output_*/` |
| 3 | `src/BarCode/tests.php` | 供扫码验证的 15 组基础用例（EAN-8/13、UPC-A、ITF-14、Codabar、ISBN）统一 `config(['moduleSize' => 3])`，`code128_long` 提 `height(200)`——模块 2px→3px，真机可扫性显著提升；文件尾部提示补充「ISBN-10 自动转换为扫码器功能」说明 |

### 验证

- 当前源码 `tests.php` 全量产物经 **zbarimg 真实解码**：用户点名全部正确——
  `itf14_basic → 15400141288763`、`codabar_basic → A1234567890B`、
  `code128_long → A×50`、`ean13_basic/scan_test_ean13 → 6901234567892`、
  `ean13_978_prefix/isbn13_basic → 9780201379624`、`isbn10_convert → 9780306406157`、
  `isbn13_full → 9783161484100`。
- **ZXing** 双端验证 20/20 PASS（`/tmp/bcimg` moduleSize=3 图集）。
- `php tests/run.php` 全量测试 ALL PASSED。
- 注：UPC-A 在 zbar 下输出带前导 0 的 13 位（`0012345678905`）为其 EAN-13
  化显示约定；ISSN 输出 `977` 前缀 EAN-13（`9771234567003`）为国际标准封装，
  二者内容均正确，非缺陷。

---

## [未发布] — 零第三方运行时依赖化

本包正式成为**完全自包含、零第三方 composer 运行时依赖**的独立工具库。

- **移除 `picqer/php-barcode-generator`**：`BarCode` 模块本就是自研实现
  （`Generator/` 下 9 种码型 + 自有渲染器，无任何 Picqer 代码引用），
  仅文档措辞提及过该库，已改写为对齐 GS1 / AIM / ANSI 标准规范。
- **移除 `psr/cache`**：唯一使用点是 `Minify::cache()` 的 PSR-6 适配签名。
  现改为鸭子类型（参数去类型化，仅要求对象提供 `set()` 方法），
  任何 PSR-6 缓存实现或本包 `Cache` 模块缓存项均可直接传入。
- **`composer.json` 收敛**：`require` 仅保留 `php>=8.2`、`ext-json`、`ext-mbstring`；
  可选集成点（Laravel ServiceProvider / PHPMailer OAuth）保留在 `suggest`，
  由宿主环境按需提供，不属于运行时硬依赖。
- **lint 护栏更新**：`tests/lint.php` 白名单移除 `Picqer`/`Psr` 等已废弃根，
  并新增说明——任何新的第三方 `use`（如曾引入过的 picqer/psr/cache）
  都会被标记为「未声明」，防止依赖回归。
- 验证：语法 248 文件全过、lint PASSED、`tests/run.php` 10 类 / 144 项全 PASSED。

---

## [未发布] — BarCode / QrCode 深入修复轮

对 `BarCode`（条形码）与 `QrCode`（二维码）两个模块做了深入分析与修复，
并补充回归测试。全量测试 **10 类 / 144 项 ALL PASSED**。

### BarCode 渲染器（三端行为对齐）

| # | 位置 | 问题 | 修复 |
|---|------|------|------|
| 1 | `Renderer/PngRenderer::createImage` | 长竖线（保护符）从 `marginTop` 起绘 `height×ratio`，但画布高度只预算普通 `height` —— `longBarRatio>1` 时延长部分被画布**底部裁剪**（默认 ratio1.1 裁 8px，1.5 裁 30px） | 条区高度改为 `ceil(height×ratio)`（仅 `rotateLongBars` 且存在长竖线位置时），文字/边框预算顺序不变 |
| 2 | `Renderer/SvgRenderer::render` | 同上，SVG viewBox 高度不足导致长竖线被视口裁剪 | viewBox 高度追加 `ceil(height×(ratio-1))` |
| 3 | `Renderer/BaseRenderer` | `HtmlRenderer` 曾忽略 `longBarRatio/quietZoneWidth/rotateLongBars` 配置 | 抽取公共 `quietZoneWidthPx()` / `longBarsEnabled()` / `buildBarGeometries()` / `splitDisplaySegments()`，Png/Svg/Html 三端共用，消除各自重复实现 |
| 4 | `Renderer/HtmlRenderer` | ① 长竖线超高挤占文本（overlap）；② CSS 容器高度不含延长量；③ 文本不分段 | 长竖线向下延长 `overhang`，wrapper 高度随之增加，文字定位在长竖线底部之下；EAN/UPC/ISSN 文本改为 `splitDisplaySegments` 分段绝对定位（与 Png/Svg 同语义），其余条码按 `textAlign` 对齐；全部文本走 `htmlspecialchars(ENT_QUOTES)` |

### QrCode（SVG 装饰补齐）

- `QrCode::toSvg()`：Logo 圆形/圆角 **clipPath 裁剪**、阴影、背景垫片、水印此前仅 PNG 实现，SVG 路径被静默丢弃 —— 现全部补齐（阴影 rect/circle、垫片、`clip-path` + 前置 `<clipPath>` 定义、九宫格 `<text>` 水印并 XML 转义）。
- 兼容性修正：`<clipPath>` 原出现在引用它的 `<image>` **之后**（部分渲染器不支持后向引用），现前移到 logo 块开头声明；裁剪/非裁剪分支统一输出 `href` data URI 与 `preserveAspectRatio`。
- SVG 文本统一 `htmlspecialchars(ENT_XML1|ENT_QUOTES)`，用户内容（中文/Emoji/`<>`）不破坏 XML 合法性。

### File / 测试基建

- `File` 绝对路径缺陷（此前 `normalizePath` 丢弃前导 `/`）已修复并补回归测试。
- `src/BarCode/tests.php` / `src/QrCode/tests.php` 输出目录由包内 `test_output_*` 改为 `sys_get_temp_dir()`，删除源码树中全部残留 `test_output_*` 产物目录。
- 新增 `tests/Unit/BarCodeQrCodeTest.php`（8 项）与 `tests/Unit/FileTest.php`（14 项）。
- 【API 约定】`BarcodeBuilder` **无** `ean13()/code128()/make()` 快捷方法且 `create()` 不带参；一律 `BarcodeBuilder::create()->type(...)->data(...)` 链式调用（`data` 缺失会抛「数据不能为空」）。

---

## [未发布] — 一维条码「扫码一致性」修复轮（真机解码双端验证）

针对用户报告的「真扫码器读取结果与生成内容不一致」，引入 **ZXing（Google）与
ZBar 两个第三方真实解码器** 对库生成 PNG 做交叉解码验证（含全部报错样本），
揪出并修复 3 个导致生成像素无法被外部扫码器正确识别的编码缺陷。
全量测试 **11 类 / 147 项 ALL PASSED**；ZXing 真实解码 **20/20 一致**、
zbarimg **19/20**（仅含 `:/.+` 符号字符的 Codabar 属 zbar 解码器能力边界，
ZXing 可正确识别）。

| # | 位置 | 缺陷 | 修复 |
|---|------|------|------|
| 1 | `Generator/Code39Generator` | 字符间隔常量 `INTER_CHAR_GAP=2`(px) 被 `÷窄宽` 后仅 1 单位（渲染 2px），**比字符内窄元素（4px）还窄**——间隔被扫码器并进窄元素分类，窄/宽判定失效 | 间隔改为 1 个窄宽（常量 4px → `÷2` 后 2 单位 = 4px），宽窄比恢复标准 3:1 |
| 2 | `Generator/CodabarGenerator` | 编码表在 `1/2/4/7/+` 五个字符上使用自造模式（与 ZXing / jsBarcode 权威表不符），真扫码器按标准表错读或拒读；且旧表对 `:/+.` 误注为“2 宽”（标准中 `:/`、`.` 为 3 宽、`+` 为 5 宽） | 按 ZXing `CodaBarReader` 权威表逐字符修正，注释同步订正 |
| 3 | `Generator/ITF14Generator` | 编码表在 `5/6/8/9` 上宽位错位，真扫码器（zbar/ZXing）按标准表把内容**确定性翻转**（`5↔8`、`6↔9`；用户样本 `15400141288763` 被读成 `18400141255793`） | 按 jsBarcode / zbar / ZXing 三家共识的 I2of5 表（每数字恰 2 宽，jsBarcode `BINARIES`）逐位修正 |

验证与测试增强：

- 新增 `tests/Unit/BarCodeDecodeRoundTripTest.php`（3 方法，像素级往返 + 表不变量）：
  - 25 条用例像素往返（含用户全部报错样本与 978/ISBN/ISSN/校验位边界），
    Codabar/ITF-14 是 zbar 等第三方难以覆盖的码型，故由库内独立解码器逐像素还原；
  - 不变量断言改为**对照 ZXing / jsBarcode 权威表逐字符硬核验**（不再做
    “数据字符一律 2 宽”这类与标准不符的假设）——上轮“自洽但外部不兼容”的
    错误正是因此漏检；
  - ITF 像素解码器同步适配权威表（宽元素个数非固定假设移除）。
- 清理 PHP 8.5 噪音：`imagedestroy()`（8.0 起空操作、8.5 deprecated）与
  `ReflectionProperty::setAccessible()`（8.1 起默认可访问）均按版本守卫。
- 第三方真机验证方法（开发辅助，不入包）：
  `@zxing/library` + `pngjs` 直接解码 PNG 断言内容（ITF/Codabar 等全格式）；
  `zbarimg --raw` 交叉复验。**注意**：`MultiFormatReader` 解码 Codabar 必须显式传
  `POSSIBLE_FORMATS=[CODABAR]`，否则该 reader 不被触发返回 null；
  ZBar 对 UPC-A 以 EAN-13（前导 `0`）形式输出，与 12 位 UPC-A 语义等价，属解码器
  输出约定而非条码缺陷。

---

## [未发布] — 全面审计与修复

本轮对整个代码库做了系统性审计（语法 / 类型 / 逻辑 / 安全 / 打包 / 文档），
共修复 **13 个会导致运行时崩溃的致命缺陷**、**9 个逻辑与功能缺陷**，
并补齐了测试基建与文档。

---

### 🔴 致命缺陷（会导致崩溃或进程终止）

| # | 位置 | 问题 | 修复 |
|---|------|------|------|
| 1 | `Support/Retry::doSleep()` | `usleep(max(0, $delay) * 1000)` 传入 **float**。内部函数在 `strict_types=1` 下即使收到整数值的 float（如 `100.0`）也会抛 `TypeError`——**任何需要等待的重试都必然崩溃** | 显式取整 `usleep((int) round($delay * 1000))`，并补充注释说明该陷阱 |
| 2 | `Data/CountryNumber` | 部分条目的 `number` 字段是数组，代码直接对其调用 `str_contains()` → `TypeError` | 新增 `normalizeNumbers()` 统一归一化为字符串列表，所有查询方法均走它 |
| 3 | `Data/BigNumberCalculator` | 除法用递归逐位试商，除数为 `1` 或数值极大时递归深度指数放大 → 爆栈 | 改为迭代长除法 |
| 4 | `Net/Ip::rangeToCidr()` | 算法方向错误（从 `/32` 起向大块试探、命中即停），**任何区间都返回 /32**；`0.0.0.0~255.255.255.255` 会生成 42 亿条记录 → **内存耗尽** | 重写为「对齐约束 + 容量约束」取交集的标准算法，产出最精简且精确覆盖的 CIDR 列表 |
| 5 | `Http/Request` | 请求头回退实现误用 `headers_list()`——该函数返回的是**待发送的响应头**，不是请求头，导致 FPM/CLI 下所有请求头丢失 | 改为从 `$_SERVER` 的 `HTTP_*` 重建（含 `CONTENT_TYPE`/`CONTENT_LENGTH` 特判） |
| 6 | `Security/XssCleaner` | 基于正则，存在 4 类可绕过缺陷（详见下方「安全」） | 重写为基于 `DOMDocument` 的 DOM 树过滤 |
| 7 | `Support/Fluent::__call()` | 用 `property_exists()` 判断，可从外部写入 `protected`/`private` 属性（绕过封装）；赋 `null` 给非 nullable 类型化属性 → `TypeError` | 反射校验「public 且非 readonly」，并按属性声明类型做前置校验，错误时抛出语义明确的异常 |
| 8 | `Convert/Json::toArray()` | 根节点为标量（`5`、`"str"`）时返回标量 → 触发返回类型的 `TypeError` | 标量包装为单元素数组；新增 `$wrapScalar` 参数可改为抛 `JsonException`。`toObject()` 同样修复（JSON 数组根节点） |
| 9 | `Support/EnumHelper` | 对纯枚举（`UnitEnum`）访问 `->value` → `TypeError`；对非枚举类调用 `::cases()` → `Error` | 纯枚举的 value 回退为 name；所有入口统一 `assertEnum()` 校验 |
| 10 | `funcs/helpers.php::array_to_tree()` | 递归下钻无环检测，`A→B→A` 或 `id === pid` 时**无限递归直至爆栈** | 增加递归路径 `$path` 集合；同时修复缺失 `pid` 字段时的 `Undefined array key` 警告 |
| 11 | `Convert/Number::roundTo()` | `if ($multiple === 0)` 在 strict_types 下 `0.0 === 0` 恒为 `false`，传入 `0.0` 会绕过校验 → `DivisionByZeroError` | 先转 float 再与 `0.0` 比较 |
| 12 | `Str/StringHelper::similarity()` | 直接用原生 `levenshtein()`：按**字节**比较（中文结果错误），且参数超 255 字节时直接返回 `-1` | 新增 `levenshteinDistance()`，按字符计算（滚动数组 DP），并对超限输入给出明确异常 |
| 13 | `Support/Timer::sleep()` | 负数未校验；被信号中断时 `time_nanosleep()` 返回剩余时间数组却未补足，导致睡眠时间远短于预期 | 增加参数校验 + 中断后循环补足剩余时间；`withTimeout()` 增加溢出夹取 |

---

### 🟠 逻辑与功能缺陷

| # | 位置 | 问题 | 修复 |
|---|------|------|------|
| 14 | `Data/Version::satisfies()` | 不支持 `\|\|`（或）、空格分隔的且条件、`!=`、以及 `>=1.0` 这类省略段的部分版本号 | 重构为「先按 `\|\|` 切或组 → 组内按空白/逗号切且条件 → 单个条件解析」；新增 `normalizePartial()` 补齐版本号；补充 `!=` / `<>` 运算符 |
| 15 | `Data/Random::pickMany()` | `unset()` 后 `array_values()` 重建，可能返回重复元素或数量不足 | 改为先洗牌键再切片，保证不重复 |
| 16 | `Support/Hash::consistent()` | 权重用 `crc32`，实测分布极不均匀（3 节点约 50%/25%/25%） | 改用 md5 前 8 位十六进制；实测 4 节点 40000 次分布为 10012/9886/9962/10140 |
| 17 | `Support/Defer` | `execute()` 之后再 `add()` 会被静默丢弃；`while ($cb = array_pop(...))` 用真值判断 | 已执行后再注册抛 `RuntimeException`；循环改用 `!== null` 判断 |
| 18 | `Support/Pipeline` | `peek()` 永远返回初始数据；`then()` 的终点回调不受 `catch()` 保护 | 缓存最近一次执行结果供 `peek()` 返回，新增 `initial()`；终点回调纳入同一异常处理路径；新增 `tap()` |
| 19 | `Support/Result::map()/mapErr()` | `map()` 可能把 `null` 传给 `err()` → `TypeError`；`mapErr()` 未处理回调返回非 `string\|Throwable` | 抽出 `propagateErr()` 兜底；`mapErr()` 对返回值做类型处理 |
| 20 | `funcs/support.php` | ① `u_str()` 传字符串时原样返回字符串（API 设计缺陷，无法链式）<br>② `u_class_uses_recursive()` 对不存在的类，`class_parents()` 返回 `false` 参与运算 → `TypeError`<br>③ `u_data_set()` 文档称支持对象，实际只支持数组<br>④ `u_data_get()` 用 `isset()` 判断属性，属性值为 `null` 时误判为不存在<br>⑤ `u_trait_uses_recursive()` 指数级重复遍历<br>⑥ `u_throw_if()` 对不存在的异常类给出模糊错误 | ① 改为始终返回 `Str` 实例<br>② 前置 `class_exists` 校验<br>③ 支持对象属性写入<br>④ 改用 `property_exists()`；完整键优先整体匹配<br>⑤ 增加 `visited` 集合<br>⑥ 校验异常类，抛出明确 `InvalidArgumentException` |
| 21 | `Convert/DataSize::parse()` | 正则 `[\d.]+` 接受 `1.2.3`，被 `(float)` 静默截断为 `1.2`；缺少 `toPb()`；`autoFormat()` 对字节级也输出小数（`512.00 B`） | 收紧正则 + 数值校验；补充 `toPb()`；字节级不输出小数；补充负数/空值校验 |
| 22 | `Str/StringHelper::snake()/slug()` | 未处理 `preg_replace()` 返回 `null` 的情况，后续 `mb_strtolower(null)` / `trim(null)` 触发弃用警告 | 全部加 `?? $value` 回退 |
| 23 | `funcs/helpers.php` | `getallheaders()` 在 CLI/CGI 下不存在且无守卫（4 处）；`is_alipay_browser()` 误判 `X-Weibo-From`（复制粘贴遗留） | 新增 `get_http_headers()`（大小写不敏感、兼容 CLI），替换全部调用点并修正支付宝判断 |
| 24 | `Support/Fluent::fill()` | 同 #7，未知键与类型不校验 | 与 `__call()` 共用同一套校验逻辑 |

---

### 🔒 安全

- **`Security\XssCleaner` 重写为 DOM 实现**。原正则实现存在 4 类可绕过手法：

  | 绕过手法 | 示例 |
  |----------|------|
  | 属性无引号 | `<div onclick=alert(1)>` — 正则只匹配带引号的属性值 |
  | 协议中插空白/控制字符 | `href="java\tscript:alert(1)"` — 浏览器解析时会忽略 TAB |
  | 实体编码 | `href="&#106;avascript:alert(1)"` — 解码后即为 `javascript:` |
  | `strip_tags()` 不过滤属性 | `<p onclick=...>` 经 `strict()` 后事件处理器原样保留 |

  现实现要点：DOM 解析（`LIBXML_NONET` 防 XXE）→ 移除危险标签与注释 → 逐个属性校验（删除所有 `on*`、URL 属性走协议白名单）→ 协议判定前先解码实体、剔除空白与控制字符。

- **`Files\Archive` 的 Zip Slip 防护**：经核查已存在且实现正确（手动解析路径、禁止 `..` 逃出根目录），本轮未改动。

- **打包依赖补全**：`Minify::cache()` 的公开签名依赖 `Psr\Cache\CacheItemInterface`，但 `psr/cache` 未写入 `composer.json`，使用者调用该方法必然 `Class not found`。已将 `psr/cache` 加入 `require`，并为 `league/oauth2-client`（PHPMailer OAuth）、`illuminate/support` 及各可选扩展补全 `suggest`。

---

### ✨ 新增

- **`Str\Str`** — 流式字符串包装类，把 `StringHelper` 的静态方法包装成链式接口：
  ```php
  (string) u_str('foo-bar')->camel();   // 'fooBar'
  u_str('abc')->contains('b');          // true（非字符串结果直接返回）
  u_str('13812345678')->maskMobile();   // '138****5678'
  ```
- **`get_http_headers()`** — 大小写不敏感的请求头读取，兼容 CLI/CGI（替代 `getallheaders()`）
- **`StringHelper::levenshteinDistance()`** — 按字符的编辑距离（对外可用）
- **`Pipeline::tap()` / `Pipeline::initial()`**
- **`Result`**：`attempt()` `isOkAnd()` `isErrAnd()` `mapOr()` `andThen()` `orElse()` `unwrapErr()` `toArray()` 与 `JsonSerializable`
- **`EnumHelper::isEnum()`**
- **`DataSize::toPb()`**
- **测试套件**：122 个回归测试，零测试框架依赖（`composer test`）
- **静态检查**：`composer lint`，4 项自动检查

---

### ⚠️ 兼容性说明

以下行为变更是刻意的安全/正确性修复，升级时请注意：

1. `Security\XssCleaner::strict()` 现在**会过滤属性**（此前只过滤标签）。白名单之外的属性全部移除。
2. `Convert\Json::toArray()` 对标量根节点返回 `[标量]`（此前抛 `TypeError`）。如需严格语义，传入第二参数 `false` 使其抛 `JsonException`。
3. `Support\Fluent` 不再允许通过 `__call` 写入 `protected`/`private` 属性。
4. `Support\Defer::add()` 在已执行后调用会抛 `RuntimeException`（此前静默丢弃）。
5. `Net\Ip::rangeToCidr()` 的返回值发生变化——原来是逐地址的 `/32` 列表，现在是聚合后的最小 CIDR 集合（**结果正确且与输入区间等价**）。
6. `u_str()` 的返回类型由 `StringHelper|string` 改为 `Str`。

---

### 📝 文档

- 重写 `README.md`：补全全部空章节，新增完整模块总览（27 个命名空间）、分模块用法示例、全局函数参考、测试与静态检查说明、安全说明、常见问题
- 新增 `CHANGELOG.md`（本文件）

---

### 🔧 开发过程中记录的陷阱

供后续维护参考：

1. **`?>` 会终止 PHP 代码块，即使在 `//` 注释中**。在注释里写 `` `<?xml ... ?>` `` 会让 PHP 提前退出代码模式，文件被截断。字符串中的 `?>` 是安全的，注释中的不是。`composer lint` 会自动检测。
2. **数组键会把纯十进制数字字符串转为 int**。`$map['86'] = true` 实际键是 `int 86`，`array_keys()` 返回 `[86]`，导致后续 `in_array('86', ..., true)` 严格比较永远失败。去重应使用值数组 + `array_unique()`。
3. **`usleep()` 等内部函数不接受 float**，即使值恰好是整数。这与用户态 `int` 参数的行为不同。
4. **PHP 允许通过 `->` 调用静态方法**，因此在类上定义 `__call` 无法拦截同名的静态方法调用。要做流式包装需使用独立的包装类（`Str` 即如此）。
