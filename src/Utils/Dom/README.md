# zxf/dom - 强大的 PHP DOM 操作库

zxf/dom 是一个功能强大、易于使用的 PHP DOM 操作库，提供简洁的 API 来解析、查询和操作 HTML/XML 文档。

## 目录

- [特性](#特性)
- [系统要求](#系统要求)
- [安装](#安装)
- [快速开始](#快速开始)
- [CSS 选择器完整列表](#css-选择器完整列表)
- [伪类选择器完整列表](#伪类选择器完整列表)
- [伪元素完整列表](#伪元素完整列表)
- [XPath 选择器](#xpath-选择器)
- [API 文档](#api-文档)
- [完整示例](#完整示例)
- [性能优化](#性能优化)
- [常见问题](#常见问题)

## 特性

- ✅ **完整的 CSS3 选择器支持** - 支持几乎所有 CSS3 选择器语法
- ✅ **原生 XPath 支持** - 可直接使用 XPath 表达式查询
- ✅ **丰富的伪类** - 支持 60+ 伪类选择器
- ✅ **伪元素支持** - 支持 `::text` 和 `::attr()` 伪元素
- ✅ **链式调用** - 流畅的 API 设计，支持链式操作
- ✅ **PHP 8.2+ 类型系统** - 完整的类型注解，更好的 IDE 支持
- ✅ **HTML/XML 双模式** - 同时支持 HTML 和 XML 文档处理
- ✅ **高性能** - 选择器编译缓存，提升查询速度
- ✅ **UTF-8 编码支持** - 完善的中文等多字节字符支持
- ✅ **表单元素操作** - 专门的表单选择器和操作方法

## 系统要求

- PHP >= 8.2 (支持 8.2、8.3、8.4)
- libxml 扩展

## 安装

```bash
composer require zxf/dom
```

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
```

## CSS 选择器完整列表

### 基础选择器

| 选择器                     | 说明                   | 示例                           |
|-------------------------|----------------------|------------------------------|
| `*`                     | 通配符选择器，匹配所有元素        | `$doc->find('*')`            |
| `tag`                   | 标签选择器，匹配指定标签名的元素     | `$doc->find('div')`          |
| `.class`                | 类选择器，匹配具有指定类的元素      | `$doc->find('.item')`        |
| `#id`                   | ID 选择器，匹配具有指定 ID 的元素 | `$doc->find('#container')`   |
| `selector1, selector2`  | 多选择器，匹配任意一个选择器       | `$doc->find('div, span, p')` |
| `selector1 selector2`   | 后代选择器，匹配后代元素         | `$doc->find('div p')`        |
| `selector1 > selector2` | 直接子代选择器，匹配直接子元素      | `$doc->find('ul > li')`      |
| `selector1 + selector2` | 相邻兄弟选择器，匹配紧接的兄弟元素    | `$doc->find('h2 + p')`       |
| `selector1 ~ selector2` | 通用兄弟选择器，匹配后面的所有兄弟元素  | `$doc->find('h2 ~ p')`       |

### 属性选择器

| 选择器              | 说明                        | 示例                                |
|------------------|---------------------------|-----------------------------------|
| `[attr]`         | 匹配具有指定属性的元素               | `$doc->find('[href]')`            |
| `[attr=value]`   | 匹配属性值完全等于指定值的元素           | `$doc->find('[data-id="123"]')`   |
| `[attr~=value]`  | 匹配属性值包含指定单词的元素（空格分隔）      | `$doc->find('[class~="active"]')` |
| `[attr\|=value]` | 匹配属性值等于指定值或以指定值加连字符开头的元素  | `$doc->find('[lang\|="en"]')`     |
| `[attr^=value]`  | 匹配属性值以指定字符串开头的元素          | `$doc->find('[href^="https"]')`   |
| `[attr$=value]`  | 匹配属性值以指定字符串结尾的元素          | `$doc->find('[src$=".jpg"]')`     |
| `[attr*=value]`  | 匹配属性值包含指定字符串的元素           | `$doc->find('[class*="nav"]')`    |
| `[attr!=value]`  | 匹配属性值不等于指定值的元素（CSS4，部分支持） | `$doc->find('[type!="hidden"]')`  |

### 组合选择器

| 选择器                 | 说明        | 示例                                 |
|---------------------|-----------|------------------------------------|
| `tag.class`         | 标签和类组合    | `$doc->find('div.active')`         |
| `tag#id`            | 标签和 ID 组合 | `$doc->find('div#container')`      |
| `tag[attr]`         | 标签和属性组合   | `$doc->find('input[type="text"]')` |
| `.class1.class2`    | 多个类选择器    | `$doc->find('.btn.primary')`       |
| `tag.class1.class2` | 标签和多个类组合  | `$doc->find('div.item.active')`    |

## 伪类选择器完整列表

### 结构伪类（子元素位置）

| 伪类                   | 说明                   | 示例                                   |
|----------------------|----------------------|--------------------------------------|
| `:first-child`       | 匹配作为其父元素的第一个子元素的元素   | `$doc->find('li:first-child')`       |
| `:last-child`        | 匹配作为其父元素的最后一个子元素的元素  | `$doc->find('li:last-child')`        |
| `:only-child`        | 匹配作为其父元素的唯一子元素的元素    | `$doc->find('div:only-child')`       |
| `:nth-child(n)`      | 匹配作为其父元素的第 n 个子元素的元素 | `$doc->find('li:nth-child(2)')`      |
| `:nth-child(odd)`    | 匹配奇数位置的子元素           | `$doc->find('li:nth-child(odd)')`    |
| `:nth-child(even)`   | 匹配偶数位置的子元素           | `$doc->find('li:nth-child(even)')`   |
| `:nth-child(an+b)`   | 匹配符合公式的子元素           | `$doc->find('li:nth-child(3n+1)')`   |
| `:nth-last-child(n)` | 匹配从后往前数的第 n 个子元素     | `$doc->find('li:nth-last-child(2)')` |

### 结构伪类（同类型元素位置）

| 伪类                     | 说明                   | 示例                                    |
|------------------------|----------------------|---------------------------------------|
| `:first-of-type`       | 匹配作为其父元素的第一个同类型子元素   | `$doc->find('p:first-of-type')`       |
| `:last-of-type`        | 匹配作为其父元素的最后一个同类型子元素  | `$doc->find('p:last-of-type')`        |
| `:only-of-type`        | 匹配作为其父元素的唯一同类型子元素    | `$doc->find('div:only-of-type')`      |
| `:nth-of-type(n)`      | 匹配作为其父元素的第 n 个同类型子元素 | `$doc->find('p:nth-of-type(2)')`      |
| `:nth-last-of-type(n)` | 匹配从后往前数的第 n 个同类型子元素  | `$doc->find('p:nth-last-of-type(2)')` |

### 内容伪类

| 伪类                     | 说明                   | 示例                                       |
|------------------------|----------------------|------------------------------------------|
| `:empty`               | 匹配没有任何子元素或文本的元素      | `$doc->find(':empty')`                   |
| `:contains(text)`      | 匹配包含指定文本的元素（包括子元素文本） | `$doc->find('div:contains(Hello)')`      |
| `:contains-text(text)` | 匹配直接包含指定文本的元素        | `$doc->find('div:contains-text(World)')` |
| `:starts-with(text)`   | 匹配文本以指定字符串开头的元素      | `$doc->find('div:starts-with(Hello)')`   |
| `:ends-with(text)`     | 匹配文本以指定字符串结尾的元素      | `$doc->find('div:ends-with(World)')`     |
| `:has(selector)`       | 匹配包含匹配指定选择器后代元素的元素   | `$doc->find('div:has(a)')`               |
| `:not(selector)`       | 匹配不匹配指定选择器的元素        | `$doc->find('div:not(.active)')`         |
| `:blank`               | 匹配空白元素（无可见文本或子元素）    | `$doc->find('p:blank')`                  |
| `:parent-only-text`    | 匹配只有文本内容没有子元素的元素     | `$doc->find('div:parent-only-text')`     |

### 表单状态伪类

| 伪类                   | 说明             | 示例                                      |
|----------------------|----------------|-----------------------------------------|
| `:enabled`           | 匹配启用的表单元素      | `$doc->find(':enabled')`                |
| `:disabled`          | 匹配禁用的表单元素      | `$doc->find(':disabled')`               |
| `:checked`           | 匹配被选中的复选框或单选按钮 | `$doc->find(':checked')`                |
| `:selected`          | 匹配被选中的选项       | `$doc->find(':selected')`               |
| `:required`          | 匹配必填字段         | `$doc->find(':required')`               |
| `:optional`          | 匹配可选字段         | `$doc->find(':optional')`               |
| `:read-only`         | 匹配只读字段         | `$doc->find(':read-only')`              |
| `:read-write`        | 匹配可写字段         | `$doc->find(':read-write')`             |
| `:in-range`          | 匹配值在指定范围内的输入元素 | `$doc->find('input:in-range')`          |
| `:out-of-range`      | 匹配值超出指定范围的输入元素 | `$doc->find('input:out-of-range')`      |
| `:indeterminate`     | 匹配不确定状态的复选框    | `$doc->find('input:indeterminate')`     |
| `:placeholder-shown` | 匹配显示占位符的输入元素   | `$doc->find('input:placeholder-shown')` |
| `:default`           | 匹配默认选择的表单元素    | `$doc->find('input:default')`           |
| `:valid`             | 匹配验证通过的表单元素    | `$doc->find('input:valid')`             |
| `:invalid`           | 匹配验证失败的表单元素    | `$doc->find('input:invalid')`           |
| `:autofill`          | 匹配被浏览器自动填充的元素  | `$doc->find('input:autofill')`          |

### 表单元素类型伪类

| 伪类                | 说明          | 示例                              |
|-------------------|-------------|---------------------------------|
| `:checkbox`       | 匹配复选框       | `$doc->find(':checkbox')`       |
| `:radio`          | 匹配单选按钮      | `$doc->find(':radio')`          |
| `:password`       | 匹配密码输入框     | `$doc->find(':password')`       |
| `:file`           | 匹配文件上传控件    | `$doc->find(':file')`           |
| `:email`          | 匹配邮箱输入框     | `$doc->find(':email')`          |
| `:url`            | 匹配 URL 输入框  | `$doc->find(':url')`            |
| `:number`         | 匹配数字输入框     | `$doc->find(':number')`         |
| `:tel`            | 匹配电话输入框     | `$doc->find(':tel')`            |
| `:search`         | 匹配搜索框       | `$doc->find(':search')`         |
| `:date`           | 匹配日期选择器     | `$doc->find(':date')`           |
| `:time`           | 匹配时间选择器     | `$doc->find(':time')`           |
| `:datetime`       | 匹配日期时间选择器   | `$doc->find(':datetime')`       |
| `:datetime-local` | 匹配本地日期时间选择器 | `$doc->find(':datetime-local')` |
| `:month`          | 匹配月份选择器     | `$doc->find(':month')`          |
| `:week`           | 匹配周选择器      | `$doc->find(':week')`           |
| `:color`          | 匹配颜色选择器     | `$doc->find(':color')`          |
| `:range`          | 匹配范围滑块      | `$doc->find(':range')`          |
| `:submit`         | 匹配提交按钮      | `$doc->find(':submit')`         |
| `:reset`          | 匹配重置按钮      | `$doc->find(':reset')`          |

### 交互伪类

| 伪类        | 说明        | 示例                      |
|-----------|-----------|-------------------------|
| `:focus`  | 匹配获得焦点的元素 | `$doc->find(':focus')`  |
| `:hover`  | 匹配鼠标悬停的元素 | `$doc->find(':hover')`  |
| `:active` | 匹配激活状态的元素 | `$doc->find(':active')` |

### 可见性伪类

| 伪类         | 说明     | 示例                       |
|------------|--------|--------------------------|
| `:visible` | 匹配可见元素 | `$doc->find(':visible')` |
| `:hidden`  | 匹配隐藏元素 | `$doc->find(':hidden')`  |

### 元素类型伪类

| 伪类         | 说明                                   | 示例                       |
|------------|--------------------------------------|--------------------------|
| `:header`  | 匹配标题元素（h1-h6）                        | `$doc->find(':header')`  |
| `:input`   | 匹配输入元素（input、textarea、select、button） | `$doc->find(':input')`   |
| `:button`  | 匹配按钮元素                               | `$doc->find(':button')`  |
| `:link`    | 匹配链接元素（具有 href 的 a 标签）               | `$doc->find(':link')`    |
| `:visited` | 匹配已访问的链接（注：XPath 无法准确判断）             | `$doc->find(':visited')` |
| `:image`   | 匹配图片元素                               | `$doc->find(':image')`   |

### HTML 元素伪类

| 伪类        | 说明           | 示例                      |
|-----------|--------------|-------------------------|
| `:video`  | 匹配 video 元素  | `$doc->find(':video')`  |
| `:audio`  | 匹配 audio 元素  | `$doc->find(':audio')`  |
| `:canvas` | 匹配 canvas 元素 | `$doc->find(':canvas')` |
| `:svg`    | 匹配 svg 元素    | `$doc->find(':svg')`    |
| `:script` | 匹配 script 元素 | `$doc->find(':script')` |
| `:style`  | 匹配 style 元素  | `$doc->find(':style')`  |
| `:meta`   | 匹配 meta 元素   | `$doc->find(':meta')`   |
| `:base`   | 匹配 base 元素   | `$doc->find(':base')`   |
| `:head`   | 匹配 head 元素   | `$doc->find(':head')`   |
| `:body`   | 匹配 body 元素   | `$doc->find(':body')`   |
| `:title`  | 匹配 title 元素  | `$doc->find(':title')`  |

### 节点类型伪类

| 伪类              | 说明          | 示例                            |
|-----------------|-------------|-------------------------------|
| `:element`      | 匹配元素节点      | `$doc->find(':element')`      |
| `:text-node`    | 匹配文本节点      | `$doc->find(':text-node')`    |
| `:comment-node` | 匹配注释节点      | `$doc->find(':comment-node')` |
| `:cdata`        | 匹配 CDATA 节点 | `$doc->find(':cdata')`        |

### 位置伪类（简写）

| 伪类                  | 说明                | 示例                            |
|---------------------|-------------------|-------------------------------|
| `:first`            | 匹配第一个元素           | `$doc->find('li:first')`      |
| `:last`             | 匹配最后一个元素          | `$doc->find('li:last')`       |
| `:even`             | 匹配偶数位置的元素         | `$doc->find('li:even')`       |
| `:odd`              | 匹配奇数位置的元素         | `$doc->find('li:odd')`        |
| `:eq(n)`            | 匹配第 n 个元素（从 0 开始） | `$doc->find('li:eq(2)')`      |
| `:gt(n)`            | 匹配第 n 个之后的所有元素    | `$doc->find('li:gt(2)')`      |
| `:lt(n)`            | 匹配第 n 个之前的所有元素    | `$doc->find('li:lt(3)')`      |
| `:slice(start:end)` | 匹配指定范围的元素         | `$doc->find('li:slice(1:3)')` |
| `:slice(:end)`      | 匹配前 n 个元素         | `$doc->find('li:slice(:3)')`  |
| `:slice(start:)`    | 匹配从第 n 个开始的所有元素   | `$doc->find('li:slice(2:)')`  |
| `:parent`           | 匹配有子元素的元素         | `$doc->find('div:parent')`    |

### 文档状态伪类

| 伪类            | 说明              | 示例                         |
|---------------|-----------------|----------------------------|
| `:root`       | 匹配文档的根元素        | `$doc->find(':root')`      |
| `:target`     | 匹配当前 URL 锚点目标元素 | `$doc->find(':target')`    |
| `:lang(lang)` | 匹配指定语言的元素       | `$doc->find('p:lang(zh)')` |

### 属性匹配伪类

| 伪类                | 说明                  | 示例                                    |
|-------------------|---------------------|---------------------------------------|
| `:has-attr(attr)` | 匹配具有指定属性的元素         | `$doc->find('div:has-attr(data-id)')` |
| `:data(name)`     | 匹配具有指定 data-* 属性的元素 | `$doc->find('div:data(info)')`        |

## 伪元素完整列表

| 伪元素            | 说明                 | 示例                            |
|----------------|--------------------|-------------------------------|
| `::text`       | 获取元素的完整文本内容（包括子元素） | `$doc->text('div::text')`     |
| `::attr(name)` | 获取元素的指定属性值         | `$doc->text('a::attr(href)')` |

## XPath 选择器

### XPath 基础

| 表达式            | 说明               | 示例                                            |
|----------------|------------------|-----------------------------------------------|
| `//tag`        | 匹配文档中所有指定标签的元素   | `$doc->find('//div', Query::TYPE_XPATH)`      |
| `/tag`         | 匹配文档根下的指定标签元素    | `$doc->find('/html', Query::TYPE_XPATH)`      |
| `.//tag`       | 匹配当前节点下的所有指定标签元素 | `$doc->find('.//p', Query::TYPE_XPATH)`       |
| `/tag1/tag2`   | 匹配直接子元素路径        | `$doc->find('/html/body', Query::TYPE_XPATH)` |
| `//tag1//tag2` | 匹配后代元素路径         | `$doc->find('//div//p', Query::TYPE_XPATH)`   |

### XPath 属性选择

| 表达式                        | 说明            | 示例                                                                     |
|----------------------------|---------------|------------------------------------------------------------------------|
| `//tag[@attr]`             | 匹配具有指定属性的元素   | `$doc->find('//a[@href]', Query::TYPE_XPATH)`                          |
| `//tag[@attr='value']`     | 匹配属性值等于指定值的元素 | `$doc->find('//div[@id="main"]', Query::TYPE_XPATH)`                   |
| `//tag[@attr1 and @attr2]` | 多属性条件         | `$doc->find('//input[@type="text" and @required]', Query::TYPE_XPATH)` |
| `//tag[@attr1 or @attr2]`  | 多属性或条件        | `$doc->find('//a[@href or @name]', Query::TYPE_XPATH)`                 |

### XPath 文本函数

| 函数                           | 说明                     | 示例                                                                   |
|------------------------------|------------------------|----------------------------------------------------------------------|
| `text()`                     | 获取元素的直接文本内容            | `$doc->find('//p[text()="Hello"]', Query::TYPE_XPATH)`               |
| `contains(text(), 'str')`    | 检查文本是否包含指定字符串          | `$doc->find('//div[contains(text(), "world")]', Query::TYPE_XPATH)`  |
| `contains(., 'str')`         | 检查元素内容（包括子元素）是否包含指定字符串 | `$doc->find('//div[contains(., "hello")]', Query::TYPE_XPATH)`       |
| `starts-with(text(), 'str')` | 检查文本是否以指定字符串开头         | `$doc->find('//p[starts-with(text(), "Hello")]', Query::TYPE_XPATH)` |
| `string(.)`                  | 获取元素的完整文本内容            | `$doc->find('//div[string(.)="Hello World"]', Query::TYPE_XPATH)`    |

### XPath 字符串函数

| 函数                           | 说明    | 示例                                                                    |
|------------------------------|-------|-----------------------------------------------------------------------|
| `concat(str1, str2)`         | 连接字符串 | `$doc->find('//a[concat(@id, "_link")]', Query::TYPE_XPATH)`          |
| `substring(str, start, len)` | 截取字符串 | `$doc->find('//a[substring(@href, 1, 4)="http"]', Query::TYPE_XPATH)` |
| `string-length(str)`         | 字符串长度 | `$doc->find('//p[string-length(text()) > 10]', Query::TYPE_XPATH)`    |
| `normalize-space(str)`       | 规范化空白 | `$doc->find('//p[normalize-space(text())]', Query::TYPE_XPATH)`       |
| `translate(str, from, to)`   | 字符转换  | `$doc->find('//p[translate(text(), "a", "A")]', Query::TYPE_XPATH)`   |

### XPath 数值函数

| 函数             | 说明    | 示例                                                              |
|----------------|-------|-----------------------------------------------------------------|
| `number(str)`  | 转换为数字 | `$doc->find('//input[number(@value) > 10]', Query::TYPE_XPATH)` |
| `floor(num)`   | 向下取整  | `$doc->find('//p[floor(position())]', Query::TYPE_XPATH)`       |
| `ceiling(num)` | 向上取整  | `$doc->find('//p[ceiling(position())]', Query::TYPE_XPATH)`     |
| `round(num)`   | 四舍五入  | `$doc->find('//p[round(position())]', Query::TYPE_XPATH)`       |

### XPath 轴选择器

| 轴                   | 说明        | 示例                                                            |
|---------------------|-----------|---------------------------------------------------------------|
| `ancestor`          | 所有祖先元素    | `$doc->find('//p/ancestor::div', Query::TYPE_XPATH)`          |
| `parent`            | 直接父元素     | `$doc->find('//p/parent::div', Query::TYPE_XPATH)`            |
| `child`             | 直接子元素     | `$doc->find('//div/child::p', Query::TYPE_XPATH)`             |
| `descendant`        | 所有后代元素    | `$doc->find('//div/descendant::p', Query::TYPE_XPATH)`        |
| `following-sibling` | 后面的所有兄弟元素 | `$doc->find('//p/following-sibling::div', Query::TYPE_XPATH)` |
| `preceding-sibling` | 前面的所有兄弟元素 | `$doc->find('//p/preceding-sibling::div', Query::TYPE_XPATH)` |
| `following`         | 后面的所有元素   | `$doc->find('//p/following::div', Query::TYPE_XPATH)`         |
| `preceding`         | 前面的所有元素   | `$doc->find('//p/preceding::div', Query::TYPE_XPATH)`         |

### XPath 位置函数

| 函数               | 说明     | 示例                                                         |
|------------------|--------|------------------------------------------------------------|
| `position()`     | 当前位置   | `$doc->find('//li[position()=2]', Query::TYPE_XPATH)`      |
| `last()`         | 最后一个位置 | `$doc->find('//li[position()=last()]', Query::TYPE_XPATH)` |
| `count(nodeset)` | 节点集数量  | `$doc->find('//div[count(p)=3]', Query::TYPE_XPATH)`       |

### XPath 布尔函数

| 函数                | 说明  | 示例                                                                     |
|-------------------|-----|------------------------------------------------------------------------|
| `not(expr)`       | 逻辑非 | `$doc->find('//div[not(@hidden)]', Query::TYPE_XPATH)`                 |
| `expr1 and expr2` | 逻辑与 | `$doc->find('//input[@type="text" and @required]', Query::TYPE_XPATH)` |
| `expr1 or expr2`  | 逻辑或 | `$doc->find('//a[@href or @name]', Query::TYPE_XPATH)`                 |

## API 文档

### Document 类

#### 构造函数

```php
// 从 HTML 字符串创建
$doc = new Document('<div>Content</div>');

// 从 HTML 文件创建
$doc = new Document('/path/to/file.html', true);

// 从 XML 字符串创建
$doc = new Document('<root/>', false, 'UTF-8', Document::TYPE_XML);

// 从 XML 文件创建
$doc = new Document('/path/to/file.xml', true, 'UTF-8', Document::TYPE_XML);
```

#### 静态方法

```php
// 创建文档实例
$doc = Document::create('<div>Content</div>');

// 从 DOMDocument 获取 Document 实例
$dom = new \DOMDocument();
$doc = Document::getFromDomDocument($dom);
```

#### 文档操作

```php
// 加载文档
$doc->load('<div>New content</div>');
$doc->load('/path/to/file.html', true);

// 保存文档
$doc->save('/path/to/file.html');

// 获取文档类型
$type = $doc->getType(); // 'html' or 'xml'

// 获取文档编码
$encoding = $doc->getEncoding();

// 获取文档 HTML
$html = $doc->html();
$html = $doc->html('div');

// 获取文档 XML
$xml = $doc->xml();

// 获取根元素
$root = $doc->root();

// 获取 head 元素
$head = $doc->head();

// 获取 body 元素
$body = $doc->body();

// 获取/设置标题
$title = $doc->title();
$doc->title('新标题');

// 检查是否存在匹配元素
$has = $doc->has('.item');
```

#### 查找元素

```php
use zxf\Utils\Dom\Query;

// 查找所有匹配元素
$elements = $doc->find('.item');

// 查找第一个匹配元素
$element = $doc->first('.item');

// 使用 XPath 查找
$elements = $doc->find('//div', Query::TYPE_XPATH);

// 使用伪元素获取文本
$text = $doc->text('div::text');

// 使用伪元素获取属性
$attr = $doc->text('a::attr(href)');
```

#### 元素操作

```php
// 创建元素
$element = $doc->createElement('div', 'Content', ['class' => 'item']);

// 创建文本节点
$textNode = $doc->createTextNode('Text');

// 创建文档片段
$fragment = $doc->createFragment();
$fragment = $doc->createDocumentFragment();

// 通过选择器创建元素
$element = $doc->createElementBySelector('div.item#test[data-id="123"]', '内容');
```

#### 类操作

```php
// 添加类
$doc->addClass('.item', 'active');

// 移除类
$doc->removeClass('.item', 'active');

// 切换类
$doc->toggleClass('.item', 'active');

// 检查类是否存在
$has = $doc->hasClass('.item', 'active');
```

#### 属性操作

```php
// 获取属性
$id = $doc->attr('#test', 'id');

// 设置属性
$doc->attr('#test', 'data-value', '123');

// 批量设置属性
$doc->setAttr('.item', 'data-id', '123');

// 删除属性
$doc->removeAttr('#test', 'data-value');
```

### Element 类

#### 元素信息

```php
// 获取标签名
$tag = $element->tagName();

// 获取元素 ID
$id = $element->id();

// 获取/设置文本内容
$text = $element->text();
$element->text('New text');

// 获取/设置 HTML 内容
$html = $element->html();
$element->html('<span>New content</span>');

// 检查是否匹配选择器
$matches = $element->matches('.item.active');
```

#### 属性操作

```php
// 获取属性
$value = $element->getAttribute('href');
$value = $element->attr('href');

// 设置属性
$element->setAttribute('href', 'https://example.com');
$element->attr('href', 'https://example.com');

// 删除属性
$element->removeAttribute('href');
$element->removeAttr('href');

// 检查属性是否存在
$has = $element->hasAttribute('href');

// 获取所有属性
$attrs = $element->attributes();
```

#### 类操作

```php
// 获取类属性对象
$classAttr = $element->getClassAttribute();

// 添加类
$element->addClass('active');
$element->class()->add('highlight');

// 移除类
$element->removeClass('active');
$element->class()->remove('highlight');

// 切换类
$element->toggleClass('active');
$element->class()->toggle('highlight');

// 检查类是否存在
$has = $element->hasClass('active');
$has = $element->class()->has('active');

// 获取所有类
$classes = $element->classes();
```

#### 样式操作

```php
// 获取样式属性对象
$styleAttr = $element->getStyleAttribute();

// 设置样式
$element->style()->set('color', 'red');
$element->css('color', 'red');

// 获取样式
$color = $element->style()->get('color');
$color = $element->css('color');

// 移除样式
$element->style()->remove('color');
$element->css('color', '');

// 检查样式是否存在
$has = $element->style()->has('color');
```

#### DOM 操作

```php
// 获取父元素
$parent = $element->parent();

// 获取子元素
$children = $element->children();

// 获取第一个子元素
$firstChild = $element->firstChild();

// 获取最后一个子元素
$lastChild = $element->lastChild();

// 获取下一个兄弟元素
$next = $element->nextSibling();

// 获取上一个兄弟元素
$prev = $element->previousSibling();

// 获取所有兄弟元素
$siblings = $element->siblings();

// 在元素前插入
$element->before($newElement);

// 在元素后插入
$element->after($newElement);

// 在元素内部追加
$element->append($newElement);

// 在元素内部前置
$element->prepend($newElement);

// 替换元素
$element->replaceWith($newElement);

// 删除元素
$element->remove();
```

### Query 类

#### 编译选择器

```php
use zxf\Utils\Dom\Query;

// 编译 CSS 选择器为 XPath
$xpath = Query::compile('.item.active');

// 编译 XPath（直接返回）
$xpath = Query::compile('//div', Query::TYPE_XPATH);

// 解析选择器
$segments = Query::parseSelector('.item.active[data-id="123"]');
```

#### 初始化和重置

```php
// 初始化（设置错误处理）
Query::initialize();

// 检查是否已初始化
$initialized = Query::isInitialized();

// 重置状态（清空缓存）
Query::reset();

// 清空编译缓存
Query::clearCompiled();

// 获取/设置编译缓存
$cache = Query::getCompiled();
Query::setCompiled($cache);
```

### ClassAttribute 类

```php
// 添加类
$classAttr->add('active');
$classAttr->add(['highlight', 'bold']);

// 移除类
$classAttr->remove('active');
$classAttr->remove(['highlight', 'bold']);

// 切换类
$classAttr->toggle('active');

// 检查类是否存在
$has = $classAttr->has('active');

// 获取所有类
$classes = $classAttr->all();

// 清空所有类
$classAttr->clear();

// 检查是否为空
$empty = $classAttr->isEmpty();
```

### StyleAttribute 类

```php
// 设置样式
$styleAttr->set('color', 'red');
$styleAttr->set(['color' => 'red', 'background' => 'blue']);

// 获取样式
$color = $styleAttr->get('color');

// 移除样式
$styleAttr->remove('color');
$styleAttr->remove(['color', 'background']);

// 检查样式是否存在
$has = $styleAttr->has('color');

// 获取所有样式
$styles = $styleAttr->all();

// 清空所有样式
$styleAttr->clear();

// 检查是否为空
$empty = $styleAttr->isEmpty();
```

## 完整示例

### 网页爬虫示例

```php
use zxf\Utils\Dom\Document;

$html = file_get_contents('https://example.com');
$doc = new Document($html);

// 获取所有文章标题
$titles = $doc->find('h1.article-title');
foreach ($titles as $title) {
    echo $title->text() . "\n";
}

// 获取文章链接
$links = $doc->find('a.article-link');
foreach ($links as $link) {
    echo $link->attr('href') . "\n";
}
```

### HTML 处理示例

```php
use zxf\Utils\Dom\Document;

$html = '<div class="container"><h1>标题</h1><ul><li class="item">项目1</li></ul></div>';
$doc = new Document($html);

// 添加新的列表项
$ul = $doc->first('ul');
$newItem = $doc->createElement('li', '项目2', ['class' => 'item']);
$ul->append($newItem);

// 输出修改后的 HTML
echo $doc->html();
```

### XML 解析示例

```php
use zxf\Utils\Dom\Document;

$xml = '<catalog><book id="1"><title>PHP 权威指南</title></book></catalog>';
$doc = new Document($xml, false, 'UTF-8', Document::TYPE_XML);

$books = $doc->find('book');
foreach ($books as $book) {
    echo "ID: " . $book->attr('id') . "\n";
    echo "书名: " . $book->first('title')->text() . "\n";
}
```

## 性能优化

### 选择器缓存

```php
use zxf\Utils\Dom\Query;

// 编译后的选择器会被自动缓存
$xpath1 = Query::compile('.item');  // 编译并缓存
$xpath2 = Query::compile('.item');  // 从缓存读取

// 手动管理缓存
Query::clearCompiled();  // 清空缓存
```

### 批量操作

```php
// 批量设置属性
$items = $doc->find('.item');
foreach ($items as $item) {
    $item->attr('data-id', uniqid());
}

// 批量添加类
foreach ($items as $item) {
    $item->addClass('processed');
}
```

## 常见问题

### Q: 如何处理中文等多字节字符？

A: 库已内置 UTF-8 支持，自动处理编码问题：

```php
$html = '<div>你好世界</div>';
$doc = new Document($html);  // 自动检测并处理 UTF-8
```

### Q: `:contains` 和 `:contains-text` 有什么区别？

A: `:contains` 使用 `string(.)`，匹配包含子元素的完整文本内容；`:contains-text` 使用 `text()`，只匹配直接文本内容。

### Q: 如何使用 XPath 函数？

A: 直接使用 XPath 模式：

```php
$elements = $doc->find('//div[contains(text(), "hello")]', Query::TYPE_XPATH);
```

### Q: 如何获取元素的完整文本（包含子元素）？

A: 使用 `string(.)` 或 `:contains` 伪类：

```php
$text = $doc->text('div::text');  // 包含所有子元素的文本
```

### Q: PHP 8.5 支持吗？

A: 目前库支持 PHP 8.2、8.3、8.4。PHP 8.5 尚未正式发布，待正式发布后我们将立即支持。

## 更新日志

查看 [CHANGELOG.md](CHANGELOG.md) 了解详细的更新历史。

## 许可证

MIT License

## 贡献

欢迎提交 Issue 和 Pull Request！
