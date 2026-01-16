## 选择器数组回退查找

选择器数组回退查找是一项强大的功能，允许您传入多个选择器，系统会按顺序尝试，找到第一个非空结果即返回。这为处理不同结构的网页提供了极大的灵活性。

### 基本语法

```php
$doc->findWithFallback([
    ['selector' => '选择器1', 'type' => 'css', 'attribute' => null],
    ['selector' => '选择器2', 'type' => 'xpath', 'attribute' => null],
    ['selector' => '/正则表达式/', 'type' => 'regex', 'attribute' => 'href']
]);
```

### 参数说明

| 参数 | 类型 | 必需 | 说明 |
|------|------|------|------|
| selector | string | 是 | 选择器表达式 |
| type | string | 否 | 选择器类型：'css'（默认）、'xpath'、'regex' |
| attribute | string | 否 | 仅当 type='regex' 时使用，指定要匹配的属性名 |

### 使用场景

#### 1. 应对不同网页结构

```php
// 场景：不同版本的网站可能使用不同的结构
$result = $doc->findWithFallback([
    // 尝试新版结构
    ['selector' => 'div.main-content > h1.title'],
    // 回退到旧版结构
    ['selector' => '#content > h1.article-title'],
    // 最后尝试XPath
    ['selector' => '//h1[contains(@class, "title")]', 'type' => 'xpath']
]);
```

#### 2. 混合使用CSS和XPath

```php
// 场景：有些选择器用CSS更简洁，有些用XPath更强大
$result = $doc->findWithFallback([
    // CSS选择器（简单直观）
    ['selector' => 'div.container .item.active'],
    // XPath选择器（功能强大）
    ['selector' => '//div[@class="container"]//div[contains(@class, "item") and contains(@class, "active")]', 'type' => 'xpath'],
    // 全路径（精确快速）
    ['selector' => '/html/body/div[1]/div[2]/div[@class="item"]', 'type' => 'xpath']
]);
```

#### 3. 使用正则表达式作为最后备选

```php
// 场景：当结构不确定时，使用正则表达式匹配内容
$result = $doc->findWithFallback([
    ['selector' => '.date'],
    ['selector' => '[data-date]'],
    ['selector' => 'time'],
    // 最后使用正则表达式查找日期格式
    ['selector' => '/\d{4}-\d{2}-\d{2}/', 'type' => 'regex']
]);
```

#### 4. 匹配属性值的模式

```php
// 场景：查找特定模式的链接
$result = $doc->findWithFallback([
    ['selector' => 'a.external'],
    ['selector' => 'a[data-external="true"]'],
    // 使用正则表达式匹配外部链接
    ['selector' => '/^https?:\\/\\//', 'type' => 'regex', 'attribute' => 'href']
]);
```

### findFirstWithFallback 方法

`findFirstWithFallback()` 方法是 `findWithFallback()` 的便捷版本，只返回第一个匹配的元素：

```php
$element = $doc->findFirstWithFallback([
    ['selector' => '.main-title'],
    ['selector' => 'h1.title'],
    ['selector' => '//h1[1]', 'type' => 'xpath']
]);

if ($element !== null) {
    echo $element->text();
}
```

### 性能考虑

1. **按优先级排列选择器**：将最可能匹配的选择器放在前面
2. **使用更具体的选择器**：避免过于宽泛的选择器
3. **优先使用CSS选择器**：CSS选择器通常比XPath更高效
4. **避免过多的备选方案**：3-5个选择器为宜

### 错误处理

如果所有选择器都未找到结果，`findWithFallback()` 返回空数组，`findFirstWithFallback()` 返回 null。不会抛出异常。

### 完整示例

```php
// 创建文档
$html = '<div class="main">
    <h1 class="title">文章标题</h1>
    <a href="https://example.com" class="link">外部链接</a>
    <span class="date">2026-01-15</span>
</div>';
$doc = new Document($html);

// 使用回退查找获取标题
$titles = $doc->findWithFallback([
    ['selector' => 'h1.title'],
    ['selector' => '#content h1'],
    ['selector' => '//h1[@class="title"]', 'type' => 'xpath']
]);
echo $titles[0]->text() ?? "未找到标题\n";

// 使用回退查找获取外部链接
$externalLinks = $doc->findWithFallback([
    ['selector' => 'a.external'],
    ['selector' => 'a[href^="https"]'],
    ['selector' => '/^https:\\/\\//', 'type' => 'regex', 'attribute' => 'href']
]);
echo count($externalLinks) . " 个外部链接\n";

// 使用findFirstWithFallback获取日期
$dateElement = $doc->findFirstWithFallback([
    ['selector' => 'span.date'],
    ['selector' => '[data-date]'],
    ['selector' => 'time'],
    ['selector' => '/\d{4}-\d{2}-\d{2}/', 'type' => 'regex']
]);
echo $dateElement ? $dateElement->text() : "未找到日期\n";
```

### Query类辅助方法

#### isXPathAbsolute()

检测是否为XPath绝对路径（以单个`/`开头）：

```php
$isAbsolute = Query::isXPathAbsolute('/html/body/div');  // true
$isAbsolute = Query::isXPathAbsolute('//div');          // false
```

#### isXPathRelative()

检测是否为XPath相对路径（以`//`开头）：

```php
$isRelative = Query::isXPathRelative('//div[@class="item"]'); // true
$isRelative = Query::isXPathRelative('/html/body');          // false
```

#### detectSelectorType()

智能检测选择器类型（返回 'css'、'xpath' 或 'regex'）：

```php
$type = Query::detectSelectorType('div.container');       // 'css'
$type = Query::detectSelectorType('/html/body/div');      // 'xpath'
$type = Query::detectSelectorType('//div[@class="item"]'); // 'xpath'
$type = Query::detectSelectorType('/\d{4}-\d{2}/');     // 'regex'
```

---

*此文档最后更新于 2026-01-15*
