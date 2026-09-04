# 变更记录

本项目的所有重要变更都会记录在此文件中。

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
