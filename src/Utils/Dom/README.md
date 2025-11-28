# zxf/dom 模块说明

>来源： 本模块由 https://github.com/Imangazaliev/DiDOM  而来
使用时需要加上命名空间 `zxf\Utils\Dom`
update_at:2023-05-09

## 快速开始


```php
use zxf\Utils\Dom\Document;

$document = new Document('http://www.news.com/', true);

$posts = $document->find('.post');

foreach($posts as $post) {
    echo $post->text(), "\n";
}
```

## 创建新文档

`zxf\Utils\Dom` 允许通过几种方式加载 HTML：

##### 通过构造函数


```php
// 第一个参数是包含 HTML 的字符串
$document = new Document($html);

// 文件路径
$document = new Document('page.html', true);

// 或 URL
$document = new Document('http://www.example.com/', true);
```

第二个参数指定是否需要加载文件。默认为 `false`。
签名：

```php
__construct($string = null, $isFile = false, $encoding = 'UTF-8', $type = Document::TYPE_HTML)
```

`$string` - HTML 或 XML 字符串，或文件路径。
`$isFile` - 指示第一个参数是否是文件路径。
`$encoding` - 文档编码。
`$type` - 文档类型 (HTML - `Document::TYPE_HTML`, XML - `Document::TYPE_XML`)。

##### 使用独立的方法


```php
$document = new Document();

$document->loadHtml($html);

$document->loadHtmlFile('page.html');

$document->loadHtmlFile('http://www.example.com/');
```

有两个可用于加载 XML 的方法：`loadXml` 和 `loadXmlFile`。
这些方法接受额外的[选项](http://php.net/manual/en/libxml.constants.php)：

```php
$document->loadHtml($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
$document->loadHtmlFile($url, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

$document->loadXml($xml, LIBXML_PARSEHUGE);
$document->loadXmlFile($url, LIBXML_PARSEHUGE);
```

## 搜索元素

DiDOM 接受 CSS 选择器或 XPath 作为搜索表达式。您需要将表达式作为第一个参数传递，并在第二个参数中指定其类型（默认类型是 `Query::TYPE_CSS`）：

##### 使用 `find()` 方法：


```php
use zxf\Utils\Dom\Document;
use zxf\Utils\Dom\Query;

...

// CSS 选择器
$posts = $document->find('.post');

// XPath
$posts = $document->find("//div[contains(@class, 'post')]", Query::TYPE_XPATH);
```

如果找到与给定表达式匹配的元素，则方法返回一个包含 `zxf\Utils\Dom\Element` 实例的数组，否则返回一个空数组。您也可以获取一个包含 `DOMElement` 对象的数组。要获取此结果，请将 `false` 作为第三个参数传递。

##### 使用魔术方法 `__invoke()`：


```php
$posts = $document('.post');
```

**警告：** 不建议使用此方法，因为它可能在将来被移除。

##### 使用 `xpath()` 方法：

```php
$posts = $document->xpath("//*[contains(concat(' ', normalize-space(@class), ' '), ' post ')]");
```

您可以在元素内部进行搜索：

```php
echo $document->find('nav')[0]->first('ul.menu')->xpath('//li')[0]->text();
```

### 验证元素是否存在

要验证元素是否存在，请使用 `has()` 方法：

```php
if ($document->has('.post')) {
    // 代码
}
```

如果您需要检查元素是否存在然后获取它：

```php
if ($document->has('.post')) {
    $elements = $document->find('.post');
    // 代码
}
```

但像下面这样做会更快：

```php
if (count($elements = $document->find('.post')) > 0) {
    // 代码
}
```

因为在第一种情况下会进行两次查询。

## 在元素内搜索

方法 `find()`, `first()`, `xpath()`, `has()`, `count()` 在 Element 类中也可用。
示例：

```php
echo $document->find('nav')[0]->first('ul.menu')->xpath('//li')[0]->text();
```

#### 方法 `findInDocument()`

如果您更改、替换或移除另一个元素中找到的元素，文档将不会改变。这是因为 `Element` 类的 `find()` 方法（以及相应的 `first()` 和 `xpath` 方法）会创建一个新文档进行搜索。
要在源文档中搜索元素，您必须使用 `findInDocument()` 和 `firstInDocument()` 方法：

```php
// 什么也不会发生
$document->first('head')->first('title')->remove();

// 但这个会生效
$document->first('head')->firstInDocument('title')->remove();
```

**警告：** `findInDocument()` 和 `firstInDocument()` 方法仅适用于属于文档的元素，以及通过 `new Element(...)` 创建的元素。如果元素不属于任何文档，将会抛出 `LogicException`；
## 支持的选择器

`zxf\Utils\Dom` 支持通过以下方式搜索：
- 标签
- 类、ID、名称和属性值
- 伪类：
  - first-, last-, nth-child
  - empty 和 not-empty
  - contains
  - has


```php
// 所有链接
$document->find('a');

// 任何 id 为 "foo" 且 class 为 "bar" 的元素
$document->find('#foo.bar');

// 任何具有 "name" 属性的元素
$document->find('[name]');
// 同上
$document->find('*[name]');

// 名称为 "foo" 的输入框
$document->find('input[name=foo]');
$document->find('input[name=\'bar\']');
$document->find('input[name="baz"]');

// 任何属性以 "data-" 开头且值为 "foo" 的元素
$document->find('*[^data-=foo]');

// 所有以 https 开头的链接
$document->find('a[href^=https]');

// 所有扩展名为 png 的图片
$document->find('img[src$=png]');

// 所有包含字符串 "example.com" 的链接
$document->find('a[href*=example.com]');

// 带有 "foo" 类的链接的文本
$document->find('a.foo::text');

// 所有带有 "bar" 类的字段的地址和标题
$document->find('a.bar::attr(href|title)');
```

## 更改内容

### 更改内部 HTML


```php
$element->setInnerHtml('<a href="#">Foo</a>');
```
### 更改内部 XML

```php
$element->setInnerXml(' Foo <span>Bar</span><!-- Baz --><![CDATA[
    <root>Hello world!</root>
]]>');
```

### 更改值（作为纯文本）


```php
$element->setValue('Foo');
// 将像使用 htmlentities() 一样进行编码
$element->setValue('<a href="#">Foo</a>');
```

## 输出

### 获取 HTML

##### 使用 `html()` 方法：


```php
$posts = $document->find('.post');

echo $posts[0]->html();
```

##### 转换为字符串：

```php
$html = (string) $posts[0];
```

##### 格式化 HTML 输出

```php
$html = $document->format()->html();
```

元素没有 `format()` 方法，因此如果您需要输出元素的格式化 HTML，首先需要将其转换为文档：

```php
$html = $element->toDocument()->format()->html();
```

#### 内部 HTML


```php
$innerHtml = $element->innerHtml();
```

文档没有 `innerHtml()` 方法，因此，如果您需要获取文档的内部 HTML，请先将其转换为元素：

```php
$innerHtml = $document->toElement()->innerHtml();
```

### 获取 XML

```php
echo $document->xml();

echo $document->first('book')->xml();
```

### 获取内容

```php
$posts = $document->find('.post');

echo $posts[0]->text();
```

## 创建新元素

### 创建类的实例

```php
use zxf\Utils\Dom\Element;

$element = new Element('span', 'Hello');

// 输出 "<span>Hello</span>"
echo $element->html();
```

第一个参数是属性名称，第二个参数是其值（可选），第三个参数是元素属性（可选）。
创建带属性的元素的示例：

```php
$attributes = ['name' => 'description', 'placeholder' => 'Enter description of item'];

$element = new Element('textarea', 'Text', $attributes);
```

可以从 `DOMElement` 类的实例创建元素：

```php
use zxf\Utils\Dom\Element;
use zxf\Utils\Dom\DOMElement;

$domElement = new DOMElement('span', 'Hello');

$element = new Element($domElement);
```

### 使用 `createElement` 方法

```php
$document = new Document($html);

$element = $document->createElement('span', 'Hello');
```

## 获取元素名称

```php
$element->tagName();
```
## 获取父元素

```php
$document = new Document($html);

$input = $document->find('input[name=email]')[0];

var_dump($input->parent());
```

## 获取兄弟元素

```php
$document = new Document($html);

$item = $document->find('ul.menu > li')[1];

var_dump($item->previousSibling());

var_dump($item->nextSibling());
```

## 获取子元素

```php
$html = '<div>Foo<span>Bar</span><!--Baz--></div>';

$document = new Document($html);

$div = $document->first('div');

// 元素节点 (DOMElement)
// string(3) "Bar"
var_dump($div->child(1)->text());

// 文本节点 (DOMText)
// string(3) "Foo"
var_dump($div->firstChild()->text());

// 注释节点 (DOMComment)
// string(3) "Baz"
var_dump($div->lastChild()->text());

// array(3) { ... }
var_dump($div->children());
```

## 获取所属文档

```php
$document = new Document($html);

$element = $document->find('input[name=email]')[0];

$document2 = $element->ownerDocument();

// bool(true)
var_dump($document->is($document2));
```

## 处理元素属性

#### 创建/更新属性

##### 使用 `setAttribute` 方法：

```php
$element->setAttribute('name', 'username');
```

##### 使用 `attr` 方法：

```php
$element->attr('name', 'username');
```

##### 使用魔术方法 `__set`：

```php
$element->name = 'username';
```
#### 获取属性值

##### 使用 `getAttribute` 方法：

```php
$username = $element->getAttribute('value');
```

##### 使用 `attr` 方法：

```php
$username = $element->attr('value');
```

##### 使用魔术方法 `__get`：

```php
$username = $element->name;
```

如果未找到属性，则返回 `null`。

#### 验证属性是否存在

##### 使用 `hasAttribute` 方法：

```php
if ($element->hasAttribute('name')) {
    // 代码
}
```

##### 使用魔术方法 `__isset`：

```php
if (isset($element->name)) {
    // 代码
}
```

#### 移除属性：

##### 使用 `removeAttribute` 方法：

```php
$element->removeAttribute('name');
```

##### 使用魔术方法 `__unset`：

```php
unset($element->name);
```

## 比较元素

```php
$element  = new Element('span', 'hello');
$element2 = new Element('span', 'hello');

// bool(true)
var_dump($element->is($element));

// bool(false)
var_dump($element->is($element2));
```

## 追加子元素

```php
$list = new Element('ul');

$item = new Element('li', 'Item 1');

$list->appendChild($item);

$items = [
    new Element('li', 'Item 2'),
    new Element('li', 'Item 3'),
];

$list->appendChild($items);
```

## 添加子元素

```php
$list = new Element('ul');

$item = new Element('li', 'Item 1');
$items = [
    new Element('li', 'Item 2'),
    new Element('li', 'Item 3'),
];

$list->appendChild($item);
$list->appendChild($items);
```

## 替换元素

```php
$element = new Element('span', 'hello');

$document->find('.post')[0]->replace($element);
```

**警告：** 您只能替换直接在文档中找到的元素：

```php
// 什么也不会发生
$document->first('head')->first('title')->replace($title);

// 但这个会生效
$document->first('head title')->replace($title);
```

更多关于此内容在  部分。

## 移除元素

```php
$document->find('.post')[0]->remove();
```

**警告：** 您只能移除直接在文档中找到的元素：

```php
// 什么也不会发生
$document->first('head')->first('title')->remove();

// 但这个会生效
$document->first('head title')->remove();
```

更多关于此内容在  部分。

## 使用缓存

缓存是从 CSS 转换而来的 XPath 表达式数组。

#### 从缓存获取

```php
use zxf\Utils\Dom\Query;

...

$xpath    = Query::compile('h2');
$compiled = Query::getCompiled();

// array('h2' => '//h2')
var_dump($compiled);
```

#### 缓存设置

```php
Query::setCompiled(['h2' => '//h2']);
```

## 杂项

#### `preserveWhiteSpace`

默认情况下，保留空白字符是禁用的。
您可以在加载文档之前启用 `preserveWhiteSpace` 选项：

```php
$document = new Document();

$document->preserveWhiteSpace();

$document->loadXml($xml);
```

#### `count`

`count ()` 方法计算与选择器匹配的子元素数量：

```php
// 打印文档中的链接数量
echo $document->count('a');
```

```php
// 打印列表中的项目数
echo $document->first('ul')->count('li');
```

#### `matches`

如果节点与选择器匹配，则返回 `true`：

```php
$element->matches('div#content');

// 严格匹配
// 如果元素是一个 id 为 content 的 div 且没有其他内容，则返回 true
// 如果元素有任何其他属性，则方法返回 false
$element->matches('div#content', true);
```

#### `isElementNode`

检查元素是否是一个元素节点 (DOMElement)：

```php
$element->isElementNode();
```

#### `isTextNode`

检查元素是否是一个文本节点 (DOMText)：

```php
$element->isTextNode();
```

#### `isCommentNode`

检查元素是否是一个注释节点 (DOMComment)：

```php
$element->isCommentNode();
```
