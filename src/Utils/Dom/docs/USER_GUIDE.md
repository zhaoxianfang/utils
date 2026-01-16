# zxf/utils Dom 完整使用指南

## 目录

- [简介](#简介)
- [安装](#安装)
- [快速开始](#快速开始)
- [核心概念](#核心概念)
- [文档操作](#文档操作)
- [元素查询](#元素查询)
- [元素操作](#元素操作)
- [属性操作](#属性操作)
- [类名操作](#类名操作)
- [样式操作](#样式操作)
- [节点操作](#节点操作)
- [文档片段](#文档片段)
- [编码处理](#编码处理)
- [错误处理](#错误处理)
- [高级用法](#高级用法)
- [选择器数组回退查找](#选择器数组回退查找)
- [智能选择器类型检测](#智能选择器类型检测)
- [最佳实践](#最佳实践)
- [常见问题](#常见问题)

---

## 简介

zxf/utils Dom 是一个功能强大、易于使用的 PHP DOM 操作库，提供简洁的 API 来解析、查询和操作 HTML/XML 文档。

### 主要特性

- ✅ **完整的 CSS3 选择器支持** - 支持 70+ 种 CSS 选择器
- ✅ **原生 XPath 支持** - 可直接使用 XPath 表达式查询
- ✅ **丰富的伪类** - 支持 60+ 伪类选择器
- ✅ **伪元素支持** - 支持 `::text` 和 `::attr()` 伪元素
- ✅ **链式调用** - 流畅的 API 设计，支持链式操作
- ✅ **PHP 8.2+ 类型系统** - 完整的类型注解，更好的 IDE 支持
- ✅ **HTML/XML 双模式** - 同时支持 HTML 和 XML 文档处理
- ✅ **高性能** - 选择器编译缓存，提升查询速度
- ✅ **UTF-8 编码支持** - 完善的中文等多字节字符支持

---

## 安装

### 使用 Composer 安装

```bash
composer require zxf/utils Dom
```

### 手动安装

下载源代码，然后引入文件：

```php
require_once 'path/to/Query.php';
require_once 'path/to/Document.php';
require_once 'path/to/Element.php';
require_once 'path/to/Node.php';
require_once 'path/to/Selectors/Query.php';
require_once 'path/to/Attributes/ClassAttribute.php';
require_once 'path/to/Attributes/StyleAttribute.php';
require_once 'path/to/Fragments/DocumentFragment.php';
require_once 'path/to/Utils/Encoder.php';
require_once 'path/to/Utils/Errors.php';
require_once 'path/to/Exceptions/InvalidSelectorException.php';

use zxf\Utils\Dom\Selectors\Query;
use zxf\Utils\Dom\Document;

// 初始化 Query
Query::initialize();
```

---

## 快速开始

### 基本用法

```php
use zxf\Utils\Dom\Document;

// 从 HTML 字符串创建文档
$doc = new Document('<div class="container"><p>Hello World</p></div>');

// 查找元素
$elements = $doc->find('.container p');
echo $elements[0]->text(); // 输出: Hello World

// 获取第一个元素
$element = $doc->first('.container');
echo $element->html(); // 输出: <p>Hello World</p>

// 使用伪元素获取文本
$text = $doc->text('.container p::text');
echo $text; // 输出: Hello World

// 使用伪元素获取属性
$html = '<a href="https://example.com">Link</a>';
$doc = new Document($html);
$url = $doc->text('a::attr(href)');
echo $url; // 输出: https://example.com
```

### XML 文档处理

```php
$xml = '<root><item id="1">Item 1</item><item id="2">Item 2</item></root>';
$doc = new Document($xml, false, 'UTF-8', Document::TYPE_XML);

$items = $doc->find('item');
foreach ($items as $item) {
    echo $item->attr('id') . ': ' . $item->text() . "\n";
}
// 输出:
// 1: Item 1
// 2: Item 2
```

### 链式调用

```php
$doc = new Document('<div class="container"><p>Text</p></div>');

// Document 链式调用
$doc->addClass('.container', 'active')
    ->addClass('.container', 'highlight')
    ->css('.container', 'color', 'red');

// Element 链式调用
$element = $doc->first('.container');
$element->addClass('class1')
        ->addClass('class2')
        ->css('background', 'blue')
        ->attr('data-id', '123');
```

---

## 核心概念

### Document（文档）

Document 类代表整个 HTML/XML 文档，是所有操作的入口点。

```php
use zxf\Utils\Dom\Document;

$doc = new Document('<div>内容</div>');
```

### Element（元素）

Element 类代表文档中的一个元素节点。

```php
$element = $doc->first('div');
echo $element->text();
```

### Node（节点）

Node 类是 Element 和其他节点类型的基类。

### Query（查询）

Query 类负责将 CSS 选择器转换为 XPath 表达式。

```php
use zxf\Utils\Dom\Selectors\Query;

Query::initialize();
$xpath = Query::compile('.item.active');
```

---

## 文档操作

### 创建文档

```php
// 从 HTML 字符串创建
$doc = new Document('<div>内容</div>');

// 从文件创建
$doc = new Document('path/to/file.html', true);

// 从 XML 字符串创建
$doc = new Document('<root><item>数据</item></root>', false, 'UTF-8', Document::TYPE_XML);

// 从 XML 文件创建
$doc = new Document('path/to/file.xml', true, 'UTF-8', Document::TYPE_XML);

// 创建空文档
$doc = new Document();
```

### 加载内容

```php
// 加载 HTML 字符串
$doc->load('<div>新内容</div>');

// 加载 HTML 文件
$doc->load('path/to/file.html', true);

// 加载 XML 字符串
$doc->load('<root><data>内容</data></root>', false, null, Document::TYPE_XML);

// 加载 XML 文件
$doc->load('path/to/file.xml', true, null, Document::TYPE_XML);
```

### 保存文档

```php
// 保存为 HTML 文件
$doc->save('path/to/output.html');

// 保存为 XML 文件
$doc->type = Document::TYPE_XML;
$doc->save('path/to/output.xml');
```

### 获取文档内容

```php
// 获取整个文档的 HTML
$html = $doc->html();

// 获取整个文档的文本
$text = $doc->text();

// 获取文档标题
$title = $doc->title();

// 获取文档元数据
$meta = $doc->meta();
```

---

## 元素查询

### 查找元素

```php
// 使用 CSS 选择器查找所有匹配元素
$elements = $doc->find('div');
$elements = $doc->find('.class');
$elements = $doc->find('#id');
$elements = $doc->find('div > p');

// 使用 XPath 查找元素
$elements = $doc->xpath('//div[@class="container"]');

// 获取第一个匹配元素
$element = $doc->first('div');
$element = $doc->first('.container');

// 获取最后一个匹配元素
$element = $doc->last('div');
```

### 使用伪元素

```php
// 获取元素的文本内容
$text = $doc->text('div::text');
$text = $doc->text('a::text');

// 获取元素的属性值
$href = $doc->text('a::attr(href)');
$src = $doc->text('img::attr(src)');
$dataId = $doc->text('div::attr(data-id)');
```

### 高级查询

```php
// 使用属性选择器
$elements = $doc->find('[href]'); // 有 href 属性
$elements = $doc->find('[data-id="123"]'); // 属性值等于
$elements = $doc->find('[class~="active"]'); // 类名包含
$elements = $doc->find('[href^="https"]'); // href 以 https 开头
$elements = $doc->find('[src$=".jpg"]'); // src 以 .jpg 结尾
$elements = $doc->find('[class*="nav"]'); // class 包含 nav

// 使用伪类
$elements = $doc->find('li:first-child'); // 第一个子元素
$elements = $doc->find('li:last-child'); // 最后一个子元素
$elements = $doc->find('li:nth-child(odd)'); // 奇数位置
$elements = $doc->find('li:contains(文本)'); // 包含文本
$elements = $doc->find('div:not(.active)'); // 不包含 active 类
$elements = $doc->find('div:has(a)'); // 包含 a 元素

// 组合选择器
$elements = $doc->find('div.container > p.highlight');
$elements = $doc->find('div#main ul.nav > li.item.active');
```

---

## 元素操作

### 获取内容

```php
$element = $doc->first('div');

// 获取文本内容
$text = $element->text();

// 获取 HTML 内容
$html = $element->html();

// 获取外部 HTML（包括自身）
$outerHtml = $element->toHtml();
```

### 修改内容

```php
$element = $doc->first('div');

// 设置文本内容
$element->setValue('新文本');

// 设置 HTML 内容
$element->setHtml('<p>新的 HTML</p>');

// 使用 Document 方法设置内容
$doc->setContent('div', '新内容');
```

### 创建元素

```php
// 创建新元素
$div = $doc->createElement('div', '内容');
$div = $doc->createElement('div', '内容', ['class' => 'container', 'id' => 'main']);
$div = $doc->createElement('a', '链接', ['href' => 'https://example.com', 'target' => '_blank']);

// 创建文本节点
$textNode = $doc->createTextNode('纯文本');
```

### 添加元素

```php
$container = $doc->first('.container');
$newElement = $doc->createElement('p', '新段落');

// 添加到末尾
$container->append($newElement);

// 添加到开头
$container->prepend($newElement);

// 在元素后插入
$element->after($newElement);

// 在元素前插入
$element->before($newElement);
```

### 克隆和删除

```php
// 克隆元素
$element = $doc->first('div');
$cloned = $element->clone();

// 移除元素
$element->remove();

// 清空元素内容
$element->empty();
```

---

## 属性操作

### 获取属性

```php
$element = $doc->first('a');

// 获取属性
$href = $element->attr('href');
$class = $element->attr('class');
$dataId = $element->getAttribute('data-id');

// 获取所有属性
$allAttrs = $element->attributes();
```

### 设置属性

```php
$element = $doc->first('div');

// 设置属性
$element->attr('class', 'new-class');
$element->attr('data-id', '123');
$element->setAttribute('title', '提示信息');

// 设置多个属性
$element->attrs([
    'class' => 'container',
    'id' => 'main',
    'data-value' => 'test'
]);
```

### 删除属性

```php
$element = $doc->first('div');

// 删除属性
$element->removeAttr('class');
$element->removeAttribute('data-id');

// 使用 Document 方法删除
$doc->removeAttr('div', 'title');
```

### 检查属性

```php
$element = $doc->first('div');

// 检查属性是否存在
$hasId = $element->hasAttribute('id');
$hasClass = $element->hasAttribute('class');

// 检查特定属性值
$isRequired = $element->attr('required') !== null;
```

---

## 类名操作

### 添加类名

```php
$element = $doc->first('div');

// 使用便捷方法
$element->addClass('active');
$element->addClass('highlight', 'large');

// 使用 ClassAttribute
$element->classes()->add('active');
$element->classes()->add('highlight', 'large');

// 使用 Document 方法
$doc->addClass('div', 'active');
```

### 移除类名

```php
$element = $doc->first('div');

// 使用便捷方法
$element->removeClass('active');

// 使用 ClassAttribute
$element->classes()->remove('active');

// 使用 Document 方法
$doc->removeClass('div', 'active');
```

### 检查类名

```php
$element = $doc->first('div');

// 使用便捷方法
$hasClass = $element->hasClass('active');

// 使用 ClassAttribute
$hasClass = $element->classes()->has('active');

// 使用 Document 方法
$hasClass = $doc->hasClass('div', 'active');
```

### 切换类名

```php
$element = $doc->first('div');

// 使用 ClassAttribute
$element->classes()->toggle('active');
```

### 获取所有类名

```php
$element = $doc->first('div');

// 获取所有类名数组
$classes = $element->classes()->all();
// ['class1', 'class2', 'active']

// 获取类名字符串
$classString = $element->attr('class');
// 'class1 class2 active'
```

### 清空类名

```php
$element = $doc->first('div');

// 使用 ClassAttribute
$element->classes()->clear();
```

---

## 样式操作

### 设置样式

```php
$element = $doc->first('div');

// 使用便捷方法
$element->css('color', 'red');
$element->css('font-size', '16px');

// 使用 StyleAttribute
$element->style()->set('color', 'red');
$element->style()->set('font-size', '16px');

// 设置多个样式
$element->style()->set([
    'color' => 'red',
    'background' => 'blue',
    'font-size' => '16px'
]);

// 使用 Document 方法
$doc->css('div', 'color', 'red');
```

### 获取样式

```php
$element = $doc->first('div');

// 使用便捷方法
$color = $element->css('color');

// 使用 StyleAttribute
$color = $element->style()->get('color');

// 使用 Document 方法
$color = $doc->css('div', 'color');

// 获取所有样式
$allStyles = $element->style()->all();
```

### 删除样式

```php
$element = $doc->first('div');

// 使用 StyleAttribute
$element->style()->remove('color');

// 设置为 null 来删除
$element->style()->set('color', null);
```

### 驼峰命名

StyleAttribute 支持驼峰命名，会自动转换为短横线命名：

```php
$element->style()->set('backgroundColor', 'red');
$element->style()->set('fontSize', '16px');
$element->style()->set('borderRadius', '5px');
```

---

## 节点操作

### 遍历节点

```php
$element = $doc->first('div');

// 获取父元素
$parent = $element->parent();

// 获取第一个子元素
$firstChild = $element->firstChild();

// 获取最后一个子元素
$lastChild = $element->lastChild();

// 获取下一个兄弟元素
$nextSibling = $element->nextSibling();

// 获取前一个兄弟元素
$previousSibling = $element->previousSibling();

// 获取所有兄弟元素
$siblings = $element->siblings();

// 获取所有子元素
$children = $element->children();
```

### 节点位置

```php
$element = $doc->first('div');

// 获取节点在兄弟节点中的索引（从 0 开始）
$index = $element->index();

// 获取文档根元素
$root = $doc->root();
```

### 节点信息

```php
$element = $doc->first('div');

// 获取标签名
$tagName = $element->tagName();

// 获取节点类型
$isElement = $element->isElementNode();
$isText = $element->isTextNode();
$isComment = $element->isCommentNode();

// 检查节点是否匹配选择器
$matches = $element->matches('.active');
```

---

## 文档片段

DocumentFragment 允许你创建和操作文档片段，然后一次性插入到文档中。

### 创建片段

```php
use zxf\Utils\Dom\Fragments\DocumentFragment;

$fragment = new DocumentFragment($doc);

// 添加内容
$fragment->append('<p>段落 1</p>');
$fragment->append('<p>段落 2</p>');

// 添加元素
$div = $doc->createElement('div', '内容');
$fragment->append($div);
```

### 插入片段

```php
$container = $doc->first('.container');
$container->append($fragment);
```

---

## 编码处理

### UTF-8 编码

zxf/utils Dom 默认使用 UTF-8 编码处理所有文档。

```php
// 创建 UTF-8 文档
$doc = new Document('<div>中文内容</div>', false, 'UTF-8');

// 处理中文
$text = $doc->text('.div');
echo $text; // 输出: 中文内容
```

### 编码转换

```php
use zxf\Utils\Dom\Utils\Encoder;

// HTML 实体编码
$html = Encoder::encodeHtml('<script>alert("XSS")</script>');
// &lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;

// HTML 实体解码
$html = Encoder::decodeHtml('&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;');
// <script>alert("XSS")</script>

// URL 编码
$url = Encoder::encodeUrl('中文内容');
// %E4%B8%AD%E6%96%87%E5%86%85%E5%AE%B9

// URL 解码
$url = Encoder::decodeUrl('%E4%B8%AD%E6%96%87%E5%86%85%E5%AE%B9');
// 中文内容
```

---

## 错误处理

### 异常处理

zxf/utils Dom 使用异常来报告错误。

```php
use zxf\Utils\Dom\Exceptions\InvalidSelectorException;

try {
    $elements = $doc->find('invalid::selector');
} catch (InvalidSelectorException $e) {
    echo '选择器错误: ' . $e->getMessage();
}

try {
    $doc->load('non-existent-file.html', true);
} catch (\RuntimeException $e) {
    echo '加载错误: ' . $e->getMessage();
}
```

### 错误配置

```php
use zxf\Utils\Dom\Utils\Errors;

// 静默处理错误
Errors::silence();

// 启用日志
Errors::setLoggingEnabled(true);

// 设置日志文件
Errors::setLogFile('/path/to/log.txt');

// 设置自定义错误处理器
Errors::setErrorHandler(function($errno, $errstr, $errfile, $errline) {
    error_log("[$errno] $errstr in $errfile:$errline");
});
```

---

## 高级用法

### 复杂选择器组合

```php
// 多条件选择器
$elements = $doc->find('div.container > p.highlight.active');

// 使用伪类
$elements = $doc->find('ul > li:nth-child(odd):not(.disabled)');

// 组合使用
$elements = $doc->find('div:has(a[href^="https"])');
```

### 数据提取

```php
$html = '
<table>
    <tr><td>ID</td><td>名称</td><td>价格</td></tr>
    <tr><td>1</td><td>产品 A</td><td>100</td></tr>
    <tr><td>2</td><td>产品 B</td><td>200</td></tr>
</table>';

$doc = new Document($html);
$rows = $doc->find('table tr:not(:first-child)');

$data = [];
foreach ($rows as $row) {
    $cells = $row->find('td');
    $data[] = [
        'id' => $cells[0]->text(),
        'name' => $cells[1]->text(),
        'price' => $cells[2]->text()
    ];
}

print_r($data);
// [
//     ['id' => '1', 'name' => '产品 A', 'price' => '100'],
//     ['id' => '2', 'name' => '产品 B', 'price' => '200']
// ]
```

### 网页爬虫

```php
$html = file_get_contents('https://example.com');
$doc = new Document($html);

// 提取所有链接
$links = $doc->find('a[href]');
foreach ($links as $link) {
    echo $link->text() . ': ' . $link->attr('href') . "\n";
}

// 提取所有图片
$images = $doc->find('img[src]');
foreach ($images as $img) {
    echo $img->attr('alt') . ': ' . $img->attr('src') . "\n";
}
```

### XPath 高级用法

```php
// 使用 XPath 函数
$elements = $doc->xpath('//div[contains(@class, "item")]');
$elements = $doc->xpath('//a[starts-with(@href, "https")]');

// 使用位置函数
$firstElement = $doc->xpath('(//div)[1]');
$lastElement = $doc->xpath('(//div)[last()]');

// 复杂 XPath 查询
$elements = $doc->xpath('//div[@class="container" and count(.//p) > 2]');
```

### 选择器数组回退查找

选择器数组回退查找是一项强大的功能，允许您传入多个选择器，按顺序尝试，找到第一个非空结果即返回：

```php
// 应对不同网页结构
$titles = $doc->findWithFallback([
    ['selector' => '.main-content > h1.title'],      // 新版结构
    ['selector' => '#content > h1.article-title'],    // 旧版结构
    ['selector' => '//h1[contains(@class, "title")]', 'type' => 'xpath']
]);

if (!empty($titles)) {
    echo "标题: " . $titles[0]->text() . "\n";
}

// 使用 findFirstWithFallback 获取单个元素
$element = $doc->findFirstWithFallback([
    ['selector' => '.main-title'],
    ['selector' => 'h1.title'],
    ['selector' => '//h1[1]', 'type' => 'xpath']
]);

if ($element !== null) {
    echo $element->text();
}

// 混合使用 CSS、XPath 和正则表达式
$dates = $doc->findWithFallback([
    ['selector' => 'time.date'],
    ['selector' => '[data-date]'],
    ['selector' => '.date'],
    ['selector' => '/\d{4}-\d{2}-\d{2}/', 'type' => 'regex']
]);
```

### 智能选择器类型检测

库提供了智能的选择器类型检测功能：

```php
use zxf\Utils\Dom\Selectors\Query;

// 自动检测选择器类型
$type = Query::detectSelectorType('div.container');           // 'css'
$type = Query::detectSelectorType('//div[@class="item"]');      // 'xpath'
$type = Query::detectSelectorType('/\d{4}-\d{2}-\d{2}/');      // 'regex'

// 检测 XPath 路径类型
$isAbsolute = Query::isXPathAbsolute('/html/body/div');         // true
$isRelative = Query::isXPathRelative('//div[@class="item"]');   // true
```

---

## 选择器数组回退查找

选择器数组回退查找是一项强大的功能，允许您传入多个选择器，按顺序尝试，找到第一个非空结果即返回。这为处理不同结构的网页提供了极大的灵活性。

### 基本用法

```php
// 应对不同网页结构
$titles = $doc->findWithFallback([
    ['selector' => '.main-content > h1.title'],      // 新版结构
    ['selector' => '#content > h1.article-title'],    // 旧版结构
    ['selector' => '//h1[contains(@class, "title")]', 'type' => 'xpath']
]);

if (!empty($titles)) {
    echo "标题: " . $titles[0]->text() . "\n";
}
```

### findFirstWithFallback

```php
// 使用 findFirstWithFallback 获取单个元素
$element = $doc->findFirstWithFallback([
    ['selector' => '.main-title'],
    ['selector' => 'h1.title'],
    ['selector' => '//h1[1]', 'type' => 'xpath']
]);

if ($element !== null) {
    echo $element->text();
}
```

### 混合使用多种选择器

```php
// 混合使用 CSS、XPath 和正则表达式
$dates = $doc->findWithFallback([
    ['selector' => 'time.date'],
    ['selector' => '[data-date]'],
    ['selector' => '.date'],
    ['selector' => '/\d{4}-\d{2}-\d{2}/', 'type' => 'regex']
]);
```

---

## 智能选择器类型检测

库提供了智能的选择器类型检测功能，可以自动识别 CSS、XPath 和正则表达式。

### 检测选择器类型

```php
use zxf\Utils\Dom\Selectors\Query;

// 自动检测选择器类型
$type = Query::detectSelectorType('div.container');           // 'css'
$type = Query::detectSelectorType('//div[@class="item"]');      // 'xpath'
$type = Query::detectSelectorType('/\d{4}-\d{2}-\d{2}/');      // 'regex'
```

### 检测 XPath 路径类型

```php
// 检测 XPath 绝对路径
$isAbsolute = Query::isXPathAbsolute('/html/body/div');         // true
$isAbsolute = Query::isXPathAbsolute('//div');                 // false

// 检测 XPath 相对路径
$isRelative = Query::isXPathRelative('//div[@class="item"]');   // true
$isRelative = Query::isXPathRelative('/html/body');           // false
```

---
---

## 最佳实践

### 1. 使用更具体的选择器

```php
// ❌ 不好：选择器太宽泛
$elements = $doc->find('div p');

// ✅ 好：选择器更具体
$elements = $doc->find('div.container > p.content');
```

### 2. 缓存查询结果

```php
// ❌ 不好：重复查询
$doc->find('.item')[0]->addClass('active');
$doc->find('.item')[0]->text();
$doc->find('.item')[0]->attr('data-id');

// ✅ 好：缓存查询结果
$item = $doc->first('.item');
$item->addClass('active');
echo $item->text();
echo $item->attr('data-id');
```

### 3. 使用链式调用

```php
// ❌ 不好：多次调用
$doc->addClass('.container', 'active');
$doc->addClass('.container', 'highlight');
$doc->css('.container', 'color', 'red');

// ✅ 好：使用链式调用
$doc->addClass('.container', 'active')
    ->addClass('.container', 'highlight')
    ->css('.container', 'color', 'red');
```

### 4. 始终初始化 Query

```php
use zxf\Utils\Dom\Selectors\Query;

// 在应用启动时初始化一次
Query::initialize();
```

### 5. 正确处理编码

```php
// 始终指定 UTF-8 编码
$doc = new Document($html, false, 'UTF-8');

// 处理中文时使用 UTF-8
$chineseText = '中文内容';
$doc = new Document("<div>$chineseText</div>");
```

### 6. 使用异常处理

```php
try {
    $doc = new Document($htmlString);
    $elements = $doc->find('.selector');
} catch (\Exception $e) {
    error_log('错误: ' . $e->getMessage());
    // 处理错误
}
```

---

## 常见问题

### Q1: 如何处理包含特殊字符的 HTML？

**A:** 使用 Encoder 类进行编码：

```php
$html = Encoder::encodeHtml('<script>alert("XSS")</script>');
$doc = new Document("<div>$html</div>");
```

### Q2: 如何查找包含特定文本的元素？

**A:** 使用 `:contains` 伪类：

```php
$elements = $doc->find('div:contains(Hello)');
```

### Q3: 如何获取元素的纯文本，不包括子元素？

**A:** 使用 `:parent-only-text` 伪类：

```php
$elements = $doc->find('div:parent-only-text');
```

### Q4: 如何处理大型 HTML 文档？

**A:** 使用更具体的选择器，并缓存查询结果：

```php
// 使用具体的选择器减少查询范围
$container = $doc->first('div#main');
$items = $container->find('.item');
```

### Q5: 如何同时查询多个选择器？

**A:** 使用逗号分隔：

```php
$elements = $doc->find('div, p, span');
```

### Q6: 如何处理 XML 文档？

**A:** 指定文档类型为 XML：

```php
$doc = new Document($xmlString, false, 'UTF-8', Document::TYPE_XML);
```

### Q7: 如何获取元素的所有属性？

**A:** 使用 `attributes()` 方法：

```php
$attrs = $element->attributes();
foreach ($attrs as $name => $value) {
    echo "$name: $value\n";
}
```

### Q8: 如何判断元素是否可见？

**A:** 使用 `:visible` 伪类：

```php
$visible = $doc->find('div:visible');
$hidden = $doc->find('div:hidden');
```

### Q9: 如何同时添加多个类名？

**A:** 使用 `addClass` 方法的可变参数：

```php
$element->addClass('class1', 'class2', 'class3');
```

### Q10: 如何删除元素的所有内容？

**A:** 使用 `empty()` 方法：

```php
$element->empty();
```

---

## 总结

zxf/utils Dom 是一个功能强大的 PHP DOM 操作库，提供了：

- 70+ 种 CSS 选择器
- 完整的 XPath 支持
- 60+ 种伪类
- 流畅的链式 API
- 完整的类型注解
- UTF-8 编码支持
- HTML/XML 双模式

通过遵循本指南的最佳实践，你可以高效地操作 HTML/XML 文档。

---

*文档版本: 1.0*  
*最后更新: 2026-01-07*
