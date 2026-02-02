# 选择器规则说明文档

本文档详细说明了 zxf/utils Dom 支持的所有 CSS 选择器、XPath 选择器和特殊选择器的使用方法。

## 目录

- [选择器数组回退查找](#选择器数组回退查找)
- [CSS 基础选择器](#css-基础选择器)
- [CSS 属性选择器](#css-属性选择器)
- [CSS 组合选择器](#css-组合选择器)
- [CSS 结构伪类](#css-结构伪类)
- [CSS 内容伪类](#css-内容伪类)
- [CSS 表单伪类](#css-表单伪类)
- [CSS 表单元素伪类](#css-表单元素伪类)
- [CSS HTML 元素伪类](#css-html-元素伪类)
- [CSS 位置伪类](#css-位置伪类)
- [CSS 可见性伪类](#css-可见性伪类)
- [CSS 伪元素](#css-伪元素)
- [XPath 选择器](#xpath-选择器)
- [全路径选择器详解](#全路径选择器详解)
- [特殊选择器](#特殊选择器)

---

## 所有支持的选择器总览

| 选择器                               | 类型          | 参数说明                                      | 使用示例                                                              | 描述                                                                                                                                    |
|-----------------------------------|-------------|-------------------------------------------|-------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------|
| **便捷查找方法**                        |
| `findWithFallback()`              | 回退查找        | selectors: 选择器数组                          | `$doc->findWithFallback([...])`                                   | 传入多个选择器，按顺序尝试，找到第一个非空结果即返回。支持混合使用CSS、XPath和正则表达式选择器。适用于处理不同结构的网页，提供极大的灵活性。                                                            |
| `findFirstWithFallback()`         | 回退查找首个      | selectors: 选择器数组                          | `$doc->findFirstWithFallback([...])`                              | `findWithFallback()` 的便捷版本，只返回第一个匹配的元素。适用于只需要获取单个匹配元素的场景。                                                                             |
| `findByPath()`                    | 路径查找        | path: 路径表达式, relative: 是否相对路径             | `$doc->findByPath('/html/body/div')`                              | 按路径表达式查找元素，支持类似文件系统的路径语法。提供完整的全路径选择能力，包括XPath绝对路径、相对路径和CSS路径。                                                                         |
| `findByText()`                    | 文本查找        | text: 文本内容, context: 上下文节点                | `$doc->findByText('Hello')`                                       | 查找包含指定文本的元素。搜索范围包括元素自身和所有子元素的文本内容。常用于快速定位包含特定关键词的元素。                                                                                  |
| `findByAttribute()`               | 属性查找        | attr: 属性名, value: 属性值                     | `$doc->findByAttribute('href', 'https://')`                       | 查找具有指定属性或属性值的元素。如果只提供属性名，则查找所有具有该属性的元素；如果同时提供属性值，则查找属性值完全匹配的元素。                                                                       |
| `findByAttributeContains()`       | 属性包含查找      | attr: 属性名, value: 包含值                     | `$doc->findByAttributeContains('class', 'nav')`                   | 查找属性值包含指定字符串的元素。与CSS的 `[attr*=value]` 选择器功能相同，但以方法形式提供，更直观易用。                                                                         |
| `findByAttributeStartsWith()`     | 属性前缀查找      | attr: 属性名, value: 前缀                      | `$doc->findByAttributeStartsWith('src', 'https')`                 | 查找属性值以指定字符串开头的元素。适用于查找特定协议的链接、特定前缀的图片等。                                                                                               |
| `findByIndex()`                   | 索引查找        | selector: 选择器, index: 索引                  | `$doc->findByIndex('.item', 2)`                                   | 查找匹配选择器的元素集合中指定索引位置的元素。索引从0开始，负数表示从后往前计数（-1表示最后一个）。                                                                                   |
| `findLast()`                      | 查找最后一个      | selector: 选择器                             | `$doc->findLast('.item')`                                         | 查找匹配选择器的所有元素中的最后一个元素。等同于获取结果集的最后一个元素，语法更简洁。                                                                                           |
| `findRange()`                     | 范围查找        | selector: 选择器, start: 开始, end: 结束         | `$doc->findRange('.item', 0, 5)`                                  | 查找匹配选择器的元素集合中指定索引范围内的元素。返回从start到end（包含）之间的所有元素。索引从0开始。                                                                               |
| `extractTable()`                  | 表格查找        | CSS/XPath/正则/Element 选择器                  | `$doc->extractTable() 或者 $doc->extractTable('table.mymy_table') ` | 提取表格数据（CSS/XPath/正则/Element）                                                                                                          |
| `extractList()`                   | 表格查找：提取列表数据 | CSS/XPath/正则/Element 选择器                  | `$doc->extractList()`                                             | 提取列表数据                                                                                                                                |
| `extractFormData()`               | 表格查找：提取表单数据 | CSS/XPath/正则/Element 选择器                  | `$doc->extractFormData()`                                         | 提取表单数据                                                                                                                                |
| `extractLinks()`                  | 提取链接数据      | CSS/XPath/正则/Element 选择器                  | `$doc->extractLinks()`                                            | 提取链接数据                                                                                                                                |
| `extractImages()`                 | 提取图片数据      | CSS/XPath/正则/Element 选择器                  | `$doc->extractImages()`                                           | 提取图片数据                                                                                                                                |
| **选择器类型检测方法**                     |
| `Query::detectSelectorType()`     | 类型检测        | selector: 选择器表达式                          | `Query::detectSelectorType('div')`                                | 智能识别选择器类型，返回 'css'、'xpath' 或 'regex'。可以自动区分CSS选择器、XPath表达式和正则表达式，为开发者提供便利的类型判断功能。                                                     |
| `Query::isXPathAbsolute()`        | XPath绝对路径检测 | expression: XPath表达式                      | `Query::isXPathAbsolute('/html/body/div')`                        | 检测字符串是否为XPath绝对路径（以/开头但不是//）。XPath绝对路径从文档根元素开始，提供精确的元素定位路径。                                                                           |
| `Query::isXPathRelative()`        | XPath相对路径检测 | expression: XPath表达式                      | `Query::isXPathRelative('//div')`                                 | 检测字符串是否为XPath相对路径（以//开头）。XPath相对路径从文档中任意位置开始查找，提供更灵活的查询方式。                                                                            |
| **XPath便捷方法**                     |
| `xpath()`                         | XPath查询     | expression: XPath表达式                      | `$doc->xpath('//div[@class="item"]')`                             | 执行XPath查询，返回匹配的元素数组。支持完整的XPath 1.0语法，包括所有标准函数、轴和节点测试。提供比CSS更强大和灵活的查询能力。                                                               |
| `xpathFirst()`                    | 首个XPath     | expression: XPath表达式                      | `$doc->xpathFirst('//div[@class="item"]')`                        | 执行XPath查询并返回第一个匹配的元素。等同于 `xpath()` 的便捷版本，适用于只需要获取单个元素的场景。                                                                             |
| `xpathTexts()`                    | XPath文本     | expression: XPath表达式                      | `$doc->xpathTexts('//div/text()')`                                | 执行XPath查询并返回匹配元素的文本内容数组。适用于快速提取多个元素的文本。                                                                                               |
| `xpathAttrs()`                    | XPath属性     | expression: XPath表达式, attr: 属性名           | `$doc->xpathAttrs('//a', 'href')`                                 | 执行XPath查询并返回匹配元素的指定属性值数组。适用于批量提取元素的特定属性。                                                                                              |
| **正则表达式方法**                       |
| `regex()`                         | 正则匹配        | pattern: 正则表达式                            | `$doc->regex('/\d{4}-\d{2}-\d{2}/')`                              | 使用正则表达式匹配元素的文本内容。支持所有PCRE正则表达式语法，提供灵活的文本匹配能力。                                                                                         |
| `regexMatch()`                    | 正则匹配,       | pattern: 正则表达式                            | `$doc->regexMatch('/(\d{4})-(\d{2})-(\d{2})/')`                   | 提取所有匹配文本                                                                                                                              |
| `regexMatchWithElement()`         | 正则匹配,       | pattern: 正则表达式                            | `$doc->regexMatchWithElement('/\d+/')`                            | 提取匹配及元素信息                                                                                                                             |
| `regexMulti()`                    | 正则匹配        | pattern: 正则表达式                            | `$doc->regexMulti(['dates' => '/.../', 'emails' => '/.../'])`     | 多列数据同时匹配                                                                                                                              |
| `regexReplace()`                  | 正则匹配        | pattern: 正则表达式                            | `$doc->regexReplace('/\s+/', ' ')`                                | 正则替换文本                                                                                                                                |
| `regexFind()`                     | 正则查找        | pattern: 正则表达式, context: 上下文节点            | `$doc->regexFind('/test/', $element)`                             | 在指定上下文节点中使用正则表达式查找匹配的元素。上下文节点可以是任意DOMElement，支持局部范围内的文本匹配。                                                                            |
| `regexFirst()`                    | 正则首个        | pattern: 正则表达式                            | `$doc->regexFirst('/\d{4}-\d{2}-\d{2}/')`                         | 使用正则表达式匹配并返回第一个匹配的文本内容。适用于只需要获取单个匹配结果的场景。                                                                                             |
| `regexByAttr()`                   | 正则属性匹配      | pattern: 正则表达式, attr: 属性名                 | `$doc->regexByAttr('/^https/', 'href')`                           | 使用正则表达式匹配元素的属性值。支持在指定属性中进行正则匹配，适用于查找特定模式的属性值，如URL、邮箱、电话等。                                                                             |
| `regexByAttrFind()`               | 正则属性查找      | pattern: 正则表达式, attr: 属性名, context: 上下文节点 | `$doc->regexByAttrFind('/^https/', 'href', $div)`                 | 在指定上下文节点中使用正则表达式匹配元素的属性值。提供局部范围内的属性匹配能力。                                                                                              |
| `regexByAttrFirst()`              | 正则属性首个      | pattern: 正则表达式, attr: 属性名                 | `$doc->regexByAttrFirst('/^https/', 'href')`                      | 使用正则表达式匹配并返回第一个元素的属性值。适用于只需要获取单个属性匹配结果的场景。                                                                                            |
| **基础选择器**                         |
| `*`                               | 通配符         | 无                                         | `$doc->find('*')`                                                 | 匹配文档中的所有元素。通常与其他选择器组合使用，如 `div *` 匹配 div 内的所有后代元素。注意：此选择器性能开销较大，谨慎使用。                                                                 |
| `tag`                             | 标签          | tag: 标签名                                  | `$doc->find('div')`                                               | 根据标签名匹配所有指定的 HTML 元素。不区分大小写（HTML 模式下）。可以匹配任何有效的 HTML 标签，如 div、p、span、a 等。这是最基本和最常用的选择器之一。                                             |
| `.class`                          | 类           | class: 类名                                 | `$doc->find('.item')`                                             | 匹配所有具有指定 class 属性值的元素。一个元素可以有多个类，`.class` 会匹配所有包含该类的元素。可以链式使用多个类选择器（如 `.class1.class2`）来匹配同时拥有多个类的元素。                                 |
| `#id`                             | ID          | id: ID名                                   | `$doc->find('#main')`                                             | 根据元素的 id 属性值匹配元素。在 HTML 文档中，id 应该是唯一的。如果有多个元素使用相同 ID，此选择器只返回第一个。ID 选择器具有很高的优先级和性能优势。                                                  |
| `s1, s2`                          | 多选          | 多个选择器                                     | `$doc->find('div, p')`                                            | 使用逗号分隔多个选择器，匹配任意一个选择器选中的元素。这允许您一次性选择多种不同类型的元素。选择器之间用逗号分隔，每个选择器可以独立工作。                                                                 |
| `s1 s2`                           | 后代          | 父选择器 子选择器                                 | `$doc->find('div p')`                                             | 匹配作为第一个选择器后代的第二个选择器的所有元素。后代关系不限层级，可以是直接子元素、孙元素、曾孙元素等。这是最常用的组合选择器之一。                                                                   |
| `s1 > s2`                         | 子代          | 父选择器 > 子选择器                               | `$doc->find('ul > li')`                                           | 匹配作为第一个选择器直接子元素的第二个选择器的所有元素。只匹配直接子元素，不包括更深层级的后代。此选择器比后代选择器更精确，性能也更好。                                                                  |
| `s1 + s2`                         | 相邻兄弟        | 前一个 + 后一个                                 | `$doc->find('h2 + p')`                                            | 匹配紧接在第一个选择器之后的第二个选择器。两个元素必须拥有相同的父元素，并且第二个元素必须紧跟在第一个元素之后。只匹配一个元素。                                                                      |
| `s1 ~ s2`                         | 通用兄弟        | 前一个 ~ 后面所有                                | `$doc->find('h2 ~ p')`                                            | 匹配第一个选择器之后的所有第二个选择器元素。两个元素必须拥有相同的父元素，但不要求相邻。会匹配所有满足条件的兄弟元素。                                                                           |
| **属性选择器**                         |
| `[attr]`                          | 存在          | attr: 属性名                                 | `$doc->find('[href]')`                                            | 匹配所有具有指定属性的元素，无论属性值是什么。只要元素存在该属性就会被选中。这是属性选择器中最简单的一种，常用于检查元素是否有某个特性。                                                                  |
| `[attr=value]`                    | 等于          | attr, value                               | `$doc->find('[id="123"]')`                                        | 匹配属性值完全等于指定值的元素。比较是区分大小写的。值必须完全匹配，包括空格和特殊字符。常用于精确匹配特定的属性值。                                                                            |
| `[attr~=value]`                   | 包含单词        | attr, value                               | `$doc->find('[class~="active"]')`                                 | 匹配属性值包含指定单词的元素。属性值通常是由空格分隔的单词列表，此选择器会匹配包含其中某个单词的元素。不会匹配部分字符串（如 "item-active" 不会匹配 "active"）。                                          |
| `[attr\|=value]`                  | 等于或前缀       | attr, value                               | `$doc->find('[lang\|="en"]')`                                     | 匹配属性值等于指定值，或以指定值加连字符开头的元素。最常用于语言选择，例如 `lang="en"`、`lang="en-US"`、`lang="en-GB"` 都会被 `[lang\|="en"]` 匹配。                               |
| `[attr^=value]`                   | 以...开头      | attr, value                               | `$doc->find('[src^="https"]')`                                    | 匹配属性值以指定字符串开头的元素。比较是区分大小写的。常用于匹配具有特定前缀的 URL、类名或 ID。例如，`[href^="http"]` 匹配所有 HTTP 链接。                                                  |
| `[attr$=value]`                   | 以...结尾      | attr, value                               | `$doc->find('[src$=".jpg"]')`                                     | 匹配属性值以指定字符串结尾的元素。比较是区分大小写的。常用于匹配特定文件类型的 URL，如 `[src$=".png"]` 匹配所有 PNG 图片。                                                            |
| `[attr*=value]`                   | 包含          | attr, value                               | `$doc->find('[class*="nav"]')`                                    | 匹配属性值包含指定子字符串的元素。可以在属性值的任何位置匹配。例如，`[class*="nav"]` 可以匹配 `class="main-nav"`、`class="navbar"`、`class="navigation"` 等。                   |
| **结构伪类**                          |
| `:first-child`                    | 第一个子元素      | 无                                         | `$doc->find('li:first-child')`                                    | 匹配作为其父元素第一个子元素的元素。与 `:first` 不同，`:first-child` 是基于 DOM 结构中父元素的子元素位置，不是基于查询结果集。每个父元素可以有多个 `:first-child`。                              |
| `:last-child`                     | 最后一个子元素     | 无                                         | `$doc->find('li:last-child')`                                     | 匹配作为其父元素最后一个子元素的元素。同样基于父元素的子元素位置，不是基于查询结果集。每个父元素可以有多个 `:last-child`。                                                                  |
| `:only-child`                     | 唯一子元素       | 无                                         | `$doc->find('div:only-child')`                                    | 匹配作为其父元素唯一子元素的元素。即该父元素只有一个子元素，并且就是当前元素。常用于判断元素是否是独生子元素。                                                                               |
| `:nth-child(n)`                   | 第n个子元素      | n: 数字/odd/even/an+b                       | `$doc->find('li:nth-child(2)')`                                   | 匹配作为其父元素第 n 个子元素的元素。参数 n 可以是：1) 数字（如 2、3）表示具体位置；2) odd 表示奇数位置（1,3,5...）；3) even 表示偶数位置（2,4,6...）；4) an+b 公式（如 2n+1、3n+2）表示符合特定模式的子元素。 |
| `:nth-last-child(n)`              | 倒数第n个       | n: 数字/odd/even/an+b                       | `$doc->find('li:nth-last-child(2)')`                              | 与 `:nth-child(n)` 类似，但从后往前计数。即匹配作为其父元素倒数第 n 个子元素的元素。参数格式与 `:nth-child` 相同。                                                            |
| `:first-of-type`                  | 同类型第一个      | 无                                         | `$doc->find('p:first-of-type')`                                   | 匹配作为其父元素第一个同类型子元素的元素。只考虑相同标签名的元素，忽略其他类型的元素。如果一个父元素有 p、span、div，那么 p、span、div 各自会有一个 first-of-type。                                    |
| `:last-of-type`                   | 同类型最后一个     | 无                                         | `$doc->find('p:last-of-type')`                                    | 匹配作为其父元素最后一个同类型子元素的元素。同样只考虑相同标签名的元素。常用于选择某种类型的最后一个元素。                                                                                 |
| `:only-of-type`                   | 唯一同类型       | 无                                         | `$doc->find('div:only-of-type')`                                  | 匹配作为其父元素中唯一同类型子元素的元素。即该父元素中只有一个该类型的元素。不同于 `:only-child`，`only-of-type` 允许有其他类型的兄弟元素。                                                  |
| `:nth-of-type(n)`                 | 同类型第n个      | n: 数字/odd/even/an+b                       | `$doc->find('p:nth-of-type(2)')`                                  | 匹配作为其父元素第 n 个同类型子元素的元素。只计算同标签名的元素，忽略其他类型。参数格式与 `:nth-child` 相同。                                                                       |
| `:nth-last-of-type(n)`            | 同类型倒数第n个    | n: 数字/odd/even/an+b                       | `$doc->find('p:nth-last-of-type(2)')`                             | 与 `:nth-of-type(n)` 类似，但从后往前计数。匹配作为其父元素倒数第 n 个同类型子元素的元素。                                                                              |
| **内容伪类**                          |
| `:empty`                          | 空元素         | 无                                         | `$doc->find('div:empty')`                                         | 匹配没有任何子元素和文本内容的元素。包括没有子元素、文本内容为空或仅包含空白字符的元素都不会被匹配。这是判断元素是否为空的精确方法。                                                                    |
| `:contains(text)`                 | 包含文本        | text: 文本                                  | `$doc->find('div:contains(Hello)')`                               | 匹配包含指定文本的元素。搜索范围包括元素自身的文本内容和所有子元素的文本内容。区分大小写。常用于查找包含特定关键词的元素。                                                                         |
| `:contains-text(text)`            | 直接包含文本      | text: 文本                                  | `$doc->find('div:contains-text(Hello)')`                          | 匹配直接包含指定文本的元素。与 `:contains` 不同，此选择器只检查元素自身的文本内容，不包括子元素的文本。用于精确定位直接包含某文本的元素。                                                           |
| `:starts-with(text)`              | 以...开头      | text: 文本                                  | `$doc->find('div:starts-with(Hello)')`                            | 匹配文本内容以指定字符串开头的元素。只检查元素自身的文本内容。常用于查找具有特定前缀的文本内容。区分大小写。                                                                                |
| `:ends-with(text)`                | 以...结尾      | text: 文本                                  | `$doc->find('div:ends-with(World)')`                              | 匹配文本内容以指定字符串结尾的元素。只检查元素自身的文本内容。常用于查找具有特定后缀的文本内容。区分大小写。                                                                                |
| `:has(selector)`                  | 包含后代        | selector: 选择器                             | `$doc->find('div:has(a)')`                                        | 匹配包含匹配指定选择器后代元素的父元素。即如果某个元素的后代中（不限层级）有元素匹配给定的选择器，那么该父元素就会被选中。这是查找特定结构元素的强大工具。                                                         |
| `:not(selector)`                  | 不匹配         | selector: 选择器                             | `$doc->find('div:not(.active)')`                                  | 匹配不匹配指定选择器的元素。即反向选择，选择所有不满足给定条件的元素。可以用于排除特定类型的元素。注意：参数选择器不能过于复杂。                                                                      |
| `:blank`                          | 空白          | 无                                         | `$doc->find('p:blank')`                                           | 匹配空白元素（无可见文本或子元素）。与 `:empty` 类似，但允许包含空白字符。元素如果没有任何可见内容或只有空白字符（如空格、换行、制表符），则会被匹配。                                                      |
| `:parent-only-text`               | 只有文本        | 无                                         | `$doc->find('div:parent-only-text')`                              | 匹配只有文本内容且没有子元素的元素。即元素必须包含非空白文本，但不能有任何子元素。常用于查找纯文本节点。                                                                                  |
| **表单伪类**                          |
| `:enabled`                        | 启用          | 无                                         | `$doc->find(':enabled')`                                          | 启用的表单元素                                                                                                                               |
| `:disabled`                       | 禁用          | 无                                         | `$doc->find(':disabled')`                                         | 禁用的表单元素                                                                                                                               |
| `:checked`                        | 选中          | 无                                         | `$doc->find(':checked')`                                          | 选中的复选框/单选                                                                                                                             |
| `:selected`                       | 选中的选项       | 无                                         | `$doc->find('option:selected')`                                   | 选中的选项                                                                                                                                 |
| `:required`                       | 必填          | 无                                         | `$doc->find(':required')`                                         | 必填字段                                                                                                                                  |
| `:optional`                       | 可选          | 无                                         | `$doc->find(':optional')`                                         | 可选字段                                                                                                                                  |
| `:read-only`                      | 只读          | 无                                         | `$doc->find(':read-only')`                                        | 只读字段                                                                                                                                  |
| `:read-write`                     | 可写          | 无                                         | `$doc->find(':read-write')`                                       | 可写字段                                                                                                                                  |
| **表单元素类型**                        |
| `:text`                           | 文本输入        | 无                                         | `$doc->find('input:text')`                                        | 文本输入框                                                                                                                                 |
| `:password`                       | 密码          | 无                                         | `$doc->find('input:password')`                                    | 密码框                                                                                                                                   |
| `:checkbox`                       | 复选框         | 无                                         | `$doc->find('input:checkbox')`                                    | 复选框                                                                                                                                   |
| `:radio`                          | 单选按钮        | 无                                         | `$doc->find('input:radio')`                                       | 单选按钮                                                                                                                                  |
| `:file`                           | 文件          | 无                                         | `$doc->find('input:file')`                                        | 文件上传                                                                                                                                  |
| `:email`                          | 邮箱          | 无                                         | `$doc->find('input:email')`                                       | 邮箱输入                                                                                                                                  |
| `:url`                            | URL         | 无                                         | `$doc->find('input:url')`                                         | URL输入                                                                                                                                 |
| `:number`                         | 数字          | 无                                         | `$doc->find('input:number')`                                      | 数字输入                                                                                                                                  |
| `:tel`                            | 电话          | 无                                         | `$doc->find('input:tel')`                                         | 电话输入                                                                                                                                  |
| `:search`                         | 搜索          | 无                                         | `$doc->find('input:search')`                                      | 搜索框                                                                                                                                   |
| `:date`                           | 日期          | 无                                         | `$doc->find('input:date')`                                        | 日期选择                                                                                                                                  |
| `:time`                           | 时间          | 无                                         | `$doc->find('input:time')`                                        | 时间选择                                                                                                                                  |
| `:datetime`                       | 日期时间        | 无                                         | `$doc->find('input:datetime')`                                    | 日期时间                                                                                                                                  |
| `:datetime-local`                 | 本地日期时间      | 无                                         | `$doc->find('input:datetime-local')`                              | 本地日期时间                                                                                                                                |
| `:month`                          | 月份          | 无                                         | `$doc->find('input:month')`                                       | 月份选择                                                                                                                                  |
| `:week`                           | 周           | 无                                         | `$doc->find('input:week')`                                        | 周选择                                                                                                                                   |
| `:color`                          | 颜色          | 无                                         | `$doc->find('input:color')`                                       | 颜色选择器                                                                                                                                 |
| `:range`                          | 范围          | 无                                         | `$doc->find('input:range')`                                       | 范围滑块                                                                                                                                  |
| `:submit`                         | 提交按钮        | 无                                         | `$doc->find('input:submit')`                                      | 提交按钮                                                                                                                                  |
| `:reset`                          | 重置按钮        | 无                                         | `$doc->find('input:reset')`                                       | 重置按钮                                                                                                                                  |
| `:image`                          | 图片按钮        | 无                                         | `$doc->find('input:image')`                                       | 图片按钮                                                                                                                                  |
| **HTML元素伪类**                      |
| `:header`                         | 标题          | 无                                         | `$doc->find(':header')`                                           | h1-h6                                                                                                                                 |
| `:input`                          | 表单输入        | 无                                         | `$doc->find(':input')`                                            | input/textarea/select/button                                                                                                          |
| `:button`                         | 按钮          | 无                                         | `$doc->find(':button')`                                           | button/input button                                                                                                                   |
| `:link`                           | 链接          | 无                                         | `$doc->find('a:link')`                                            | 有href的a标签                                                                                                                             |
| `:visited`                        | 已访问链接       | 无                                         | `$doc->find('a:visited')`                                         | a标签                                                                                                                                   |
| `:image`                          | 图片          | 无                                         | `$doc->find(':image')`                                            | img标签                                                                                                                                 |
| `:video`                          | 视频          | 无                                         | `$doc->find(':video')`                                            | video标签                                                                                                                               |
| `:audio`                          | 音频          | 无                                         | `$doc->find(':audio')`                                            | audio标签                                                                                                                               |
| `:canvas`                         | 画布          | 无                                         | `$doc->find(':canvas')`                                           | canvas标签                                                                                                                              |
| `:svg`                            | SVG         | 无                                         | `$doc->find(':svg')`                                              | svg标签                                                                                                                                 |
| `:script`                         | 脚本          | 无                                         | `$doc->find(':script')`                                           | script标签                                                                                                                              |
| `:style`                          | 样式          | 无                                         | `$doc->find(':style')`                                            | style标签                                                                                                                               |
| `:meta`                           | 元信息         | 无                                         | `$doc->find(':meta')`                                             | meta标签                                                                                                                                |
| `:link`                           | 链接          | 无                                         | `$doc->find(':link')`                                             | link标签                                                                                                                                |
| `:base`                           | 基准URL       | 无                                         | `$doc->find(':base')`                                             | base标签                                                                                                                                |
| `:head`                           | 头部          | 无                                         | `$doc->find(':head')`                                             | head标签                                                                                                                                |
| `:body`                           | 主体          | 无                                         | `$doc->find(':body')`                                             | body标签                                                                                                                                |
| `:title`                          | 标题          | 无                                         | `$doc->find(':title')`                                            | title标签                                                                                                                               |
| **HTML5结构元素**                     |
| `:table`                          | 表格          | 无                                         | `$doc->find(':table')`                                            | table标签                                                                                                                               |
| `:tr`                             | 表格行         | 无                                         | `$doc->find(':tr')`                                               | tr标签                                                                                                                                  |
| `:td`                             | 表格单元格       | 无                                         | `$doc->find(':td')`                                               | td标签                                                                                                                                  |
| `:th`                             | 表格头         | 无                                         | `$doc->find(':th')`                                               | th标签                                                                                                                                  |
| `:thead`                          | 表格头         | 无                                         | `$doc->find(':thead')`                                            | thead标签                                                                                                                               |
| `:tbody`                          | 表格主体        | 无                                         | `$doc->find(':tbody')`                                            | tbody标签                                                                                                                               |
| `:tfoot`                          | 表格尾         | 无                                         | `$doc->find(':tfoot')`                                            | tfoot标签                                                                                                                               |
| `:ul`                             | 无序列表        | 无                                         | `$doc->find(':ul')`                                               | ul标签                                                                                                                                  |
| `:ol`                             | 有序列表        | 无                                         | `$doc->find(':ol')`                                               | ol标签                                                                                                                                  |
| `:li`                             | 列表项         | 无                                         | `$doc->find(':li')`                                               | li标签                                                                                                                                  |
| `:dl`                             | 定义列表        | 无                                         | `$doc->find(':dl')`                                               | dl标签                                                                                                                                  |
| `:dt`                             | 定义术语        | 无                                         | `$doc->find(':dt')`                                               | dt标签                                                                                                                                  |
| `:dd`                             | 定义描述        | 无                                         | `$doc->find(':dd')`                                               | dd标签                                                                                                                                  |
| `:form`                           | 表单          | 无                                         | `$doc->find(':form')`                                             | form标签                                                                                                                                |
| `:label`                          | 标签          | 无                                         | `$doc->find(':label')`                                            | label标签                                                                                                                               |
| `:fieldset`                       | 字段集         | 无                                         | `$doc->find(':fieldset')`                                         | fieldset标签                                                                                                                            |
| `:legend`                         | 图例          | 无                                         | `$doc->find(':legend')`                                           | legend标签                                                                                                                              |
| `:section`                        | 章节          | 无                                         | `$doc->find(':section')`                                          | section标签                                                                                                                             |
| `:article`                        | 文章          | 无                                         | `$doc->find(':article')`                                          | article标签                                                                                                                             |
| `:aside`                          | 侧边栏         | 无                                         | `$doc->find(':aside')`                                            | aside标签                                                                                                                               |
| `:nav`                            | 导航          | 无                                         | `$doc->find(':nav')`                                              | nav标签                                                                                                                                 |
| `:main`                           | 主内容         | 无                                         | `$doc->find(':main')`                                             | main标签                                                                                                                                |
| `:footer`                         | 页脚          | 无                                         | `$doc->find(':footer')`                                           | footer标签                                                                                                                              |
| `:figure`                         | 图表          | 无                                         | `$doc->find(':figure')`                                           | figure标签                                                                                                                              |
| `:figcaption`                     | 图表标题        | 无                                         | `$doc->find(':figcaption')`                                       | figcaption标签                                                                                                                          |
| `:details`                        | 详情          | 无                                         | `$doc->find(':details')`                                          | details标签                                                                                                                             |
| `:summary`                        | 摘要          | 无                                         | `$doc->find(':summary')`                                          | summary标签                                                                                                                             |
| `:dialog`                         | 对话框         | 无                                         | `$doc->find(':dialog')`                                           | dialog标签                                                                                                                              |
| `:menu`                           | 菜单          | 无                                         | `$doc->find(':menu')`                                             | menu标签                                                                                                                                |
| **位置伪类**                          |
| `:first`                          | 第一个         | 无                                         | `$doc->find('li:first')`                                          | 结果集第一个                                                                                                                                |
| `:last`                           | 最后一个        | 无                                         | `$doc->find('li:last')`                                           | 结果集最后一个                                                                                                                               |
| `:even`                           | 偶数          | 无                                         | `$doc->find('li:even')`                                           | 偶数位置(1,3,5)                                                                                                                           |
| `:odd`                            | 奇数          | 无                                         | `$doc->find('li:odd')`                                            | 奇数位置(0,2,4)                                                                                                                           |
| `:eq(n)`                          | 等于索引        | n: 索引                                     | `$doc->find('li:eq(2)')`                                          | 索引等于n                                                                                                                                 |
| `:gt(n)`                          | 大于索引        | n: 索引                                     | `$doc->find('li:gt(2)')`                                          | 索引大于n                                                                                                                                 |
| `:lt(n)`                          | 小于索引        | n: 索引                                     | `$doc->find('li:lt(3)')`                                          | 索引小于n                                                                                                                                 |
| `:parent`                         | 父元素         | 无                                         | `$doc->find(':parent')`                                           | 有子元素的元素                                                                                                                               |
| `:between(start,end)`             | 索引范围        | start, end                                | `$doc->find('li:between(2,5)')`                                   | 索引在2-5之间                                                                                                                              |
| `:slice(start:end)`               | 切片          | start:end                                 | `$doc->find('li:slice(1:3)')`                                     | 切片范围                                                                                                                                  |
| **可见性伪类**                         |
| `:visible`                        | 可见          | 无                                         | `$doc->find(':visible')`                                          | 可见元素                                                                                                                                  |
| `:hidden`                         | 隐藏          | 无                                         | `$doc->find(':hidden')`                                           | 隐藏元素                                                                                                                                  |
| **状态伪类**                          |
| `:root`                           | 根元素         | 无                                         | `$doc->find(':root')`                                             | 文档根元素                                                                                                                                 |
| `:target`                         | 目标          | 无                                         | `$doc->find(':target')`                                           | URL锚点目标                                                                                                                               |
| `:focus`                          | 焦点          | 无                                         | `$doc->find(':focus')`                                            | 获得焦点                                                                                                                                  |
| `:hover`                          | 悬停          | 无                                         | `$doc->find(':hover')`                                            | 鼠标悬停                                                                                                                                  |
| `:active`                         | 激活          | 无                                         | `$doc->find(':active')`                                           | 被激活                                                                                                                                   |
| **语言和方向**                         |
| `:lang(lang)`                     | 语言          | lang: 语言代码                                | `$doc->find(':lang(zh)')`                                         | 指定语言                                                                                                                                  |
| `:dir-ltr`                        | 左到右         | 无                                         | `$doc->find(':dir-ltr')`                                          | 左到右方向                                                                                                                                 |
| `:dir-rtl`                        | 右到左         | 无                                         | `$doc->find(':dir-rtl')`                                          | 右到左方向                                                                                                                                 |
| `:dir-auto`                       | 自动          | 无                                         | `$doc->find(':dir-auto')`                                         | 自动方向                                                                                                                                  |
| **深度伪类**                          |
| `:depth-0`                        | 根级别         | 无                                         | `$doc->find(':depth-0')`                                          | 无祖先元素                                                                                                                                 |
| `:depth-1`                        | 深度1         | 无                                         | `$doc->find(':depth-1')`                                          | 1层祖先                                                                                                                                  |
| `:depth-2`                        | 深度2         | 无                                         | `$doc->find(':depth-2')`                                          | 2层祖先                                                                                                                                  |
| `:depth-3`                        | 深度3         | 无                                         | `$doc->find(':depth-3')`                                          | 3层祖先                                                                                                                                  |
| **文本相关伪类**                        |
| `:text-node`                      | 文本节点        | 无                                         | `$doc->find(':text-node')`                                        | 文本节点                                                                                                                                  |
| `:comment-node`                   | 注释节点        | 无                                         | `$doc->find(':comment-node')`                                     | 注释节点                                                                                                                                  |
| `:whitespace`                     | 空白文本        | 无                                         | `$doc->find(':whitespace')`                                       | 空白文本节点                                                                                                                                |
| `:non-whitespace`                 | 非空白文本       | 无                                         | `$doc->find(':non-whitespace')`                                   | 非空白文本                                                                                                                                 |
| `:text-length-gt(n)`              | 文本长度大于      | n: 长度                                     | `$doc->find(':text-length-gt(10)')`                               | 文本长度>n                                                                                                                                |
| `:text-length-lt(n)`              | 文本长度小于      | n: 长度                                     | `$doc->find(':text-length-lt(10)')`                               | 文本长度<n                                                                                                                                |
| `:text-length-eq(n)`              | 文本长度等于      | n: 长度                                     | `$doc->find(':text-length-eq(10)')`                               | 文本长度=n                                                                                                                                |
| `:text-length-between(start,end)` | 文本长度范围      | start, end                                | `$doc->find(':text-length-between(5,10)')`                        | 文本长度在5-10之间                                                                                                                           |
| **子元素数量伪类**                       |
| `:children-gt(n)`                 | 子元素大于       | n: 数量                                     | `$doc->find(':children-gt(3)')`                                   | 子元素数>n                                                                                                                                |
| `:children-lt(n)`                 | 子元素小于       | n: 数量                                     | `$doc->find(':children-lt(3)')`                                   | 子元素数<n                                                                                                                                |
| `:children-eq(n)`                 | 子元素等于       | n: 数量                                     | `$doc->find(':children-eq(3)')`                                   | 子元素数=n                                                                                                                                |
| **属性数量伪类**                        |
| `:attr-count-gt(n)`               | 属性数大于       | n: 数量                                     | `$doc->find(':attr-count-gt(3)')`                                 | 属性数>n                                                                                                                                 |
| `:attr-count-lt(n)`               | 属性数小于       | n: 数量                                     | `$doc->find(':attr-count-lt(3)')`                                 | 属性数<n                                                                                                                                 |
| `:attr-count-eq(n)`               | 属性数等于       | n: 数量                                     | `$doc->find(':attr-count-eq(3)')`                                 | 属性数=n                                                                                                                                 |
| **属性值长度伪类**                       |
| `:attr-length-gt(attr,n)`         | 属性值长度大于     | attr: 属性名, n: 长度                          | `$doc->find(':attr-length-gt(href,10)')`                          | 属性值长度>n                                                                                                                               |
| `:attr-length-lt(attr,n)`         | 属性值长度小于     | attr: 属性名, n: 长度                          | `$doc->find(':attr-length-lt(href,10)')`                          | 属性值长度<n                                                                                                                               |
| `:attr-length-eq(attr,n)`         | 属性值长度等于     | attr: 属性名, n: 长度                          | `$doc->find(':attr-length-eq(href,10)')`                          | 属性值长度=n                                                                                                                               |
| **节点类型伪类**                        |
| `:element`                        | 元素节点        | 无                                         | `$doc->find(':element')`                                          | 元素节点                                                                                                                                  |
| `:cdata`                          | CDATA节点     | 无                                         | `$doc->find(':cdata')`                                            | CDATA节点                                                                                                                               |
| **深度范围伪类**                        |
| `:depth-between(start,end)`       | 深度范围        | start, end                                | `$doc->find(':depth-between(1,3)')`                               | 深度在1-3之间                                                                                                                              |
| **文本内容匹配伪类**                      |
| `:text-match(pattern)`            | 文本匹配        | pattern: 正则模式                             | `$doc->find(':text-match(^test)')`                                | 文本匹配正则表达式                                                                                                                             |
| **属性值匹配伪类**                       |
| `:attr-match(attr,pattern)`       | 属性值匹配       | attr: 属性名, pattern: 正则模式                  | `$doc->find(':attr-match(href,^http)')`                           | 属性值匹配正则表达式                                                                                                                            |
| `:data(name)`                     | data属性      | name: data名                               | `$doc->find(':data(id)')`                                         | data-*属性                                                                                                                              |
| **表单验证伪类**                        |
| `:in-range`                       | 在范围内        | 无                                         | `$doc->find(':in-range')`                                         | 值在min-max间                                                                                                                            |
| `:out-of-range`                   | 超出范围        | 无                                         | `$doc->find(':out-of-range')`                                     | 值超出范围                                                                                                                                 |
| `:indeterminate`                  | 不确定         | 无                                         | `$doc->find(':indeterminate')`                                    | 状态不确定                                                                                                                                 |
| `:placeholder-shown`              | 显示占位符       | 无                                         | `$doc->find(':placeholder-shown')`                                | 显示占位符                                                                                                                                 |
| `:default`                        | 默认          | 无                                         | `$doc->find(':default')`                                          | 默认选项                                                                                                                                  |
| `:valid`                          | 有效          | 无                                         | `$doc->find(':valid')`                                            | 验证通过                                                                                                                                  |
| `:invalid`                        | 无效          | 无                                         | `$doc->find(':invalid')`                                          | 验证失败                                                                                                                                  |
| `:user-invalid`                   | 用户验证失败      | 无                                         | `$doc->find(':user-invalid')`                                     | 用户验证失败                                                                                                                                |
| `:user-valid`                     | 用户验证通过      | 无                                         | `$doc->find(':user-valid')`                                       | 用户验证通过                                                                                                                                |
| `:autofill`                       | 自动填充        | 无                                         | `$doc->find(':autofill')`                                         | 浏览器自动填充                                                                                                                               |
| **伪元素**                           |
| `::text`                          | 文本内容        | 无                                         | `$doc->text('div::text')`                                         | 获取文本                                                                                                                                  |
| `::attr(name)`                    | 属性值         | name: 属性名                                 | `$doc->text('a::attr(href)')`                                     | 获取属性                                                                                                                                  |


### 选择器分类说明

本表格按功能和类型将选择器分为以下六大类别：

1. **便捷查找方法**（12种）- 提供简化查询接口的便捷方法
2. **选择器类型检测**（4种）- 检测和识别不同类型的选择器
3. **XPath便捷方法**（5种）- XPath查询的便捷封装
4. **正则表达式方法**（7种）- 正则匹配相关方法
5. **CSS选择器**（160+ 种）- 标准CSS选择器及扩展
    - 基础选择器（9种）
    - 属性选择器（7种）
    - 结构伪类（10种）
    - 内容伪类（9种）
    - 表单伪类（8种）
    - 表单元素类型（24种）
    - HTML元素伪类（19种）
    - HTML5结构元素（29种）
    - 位置伪类（11种）
    - 可见性伪类（2种）
    - 状态伪类（4种）
    - 语言方向伪类（4种）
    - 深度伪类（5种）
    - 文本相关伪类（8种）
    - 节点类型伪类（3种）
    - 子元素数量伪类（3种）
    - 属性数量伪类（3种）
    - 属性值长度伪类（3种）
    - 表单验证伪类（9种）
    - 文本内容匹配伪类（1种）
    - 属性值匹配伪类（2种）
6. **伪元素**（2种）- 用于提取特定内容

### 参数说明
- **无**: 表示该选择器不需要额外参数
- **selector/expression**: 选择器或XPath表达式字符串
- **type**: 选择器类型，可选值为 'css'、'xpath'、'regex'
- **attr**: 属性名称
- **value**: 属性值、前缀、后缀等字符串
- **pattern**: 正则表达式模式
- **context**: 上下文节点，限定查找范围
- **index/n**: 数字索引，支持负数（从后往前）
- **start/end**: 范围或切片的起始和结束位置
- **group**: 正则表达式分组索引
- **selectors**: 选择器数组，每个元素包含selector、type、attribute等字段
- **extractMode**: 提取模式，可选值为 'elements'、'text'、'attr'、'match'
- **location**: 提取位置配置，用于返回关联数组

### 使用说明
- 所有CSS选择器可以通过 `$doc->find('selector')` 调用
- 便捷方法直接通过 `$doc->methodName()` 调用
- 类型检测方法通过 `Query::methodName()` 静态调用
- XPath方法通过 `$doc->xpath()` 系列方法调用
- 正则方法通过 `$doc->regex()` 系列方法调用

**总计**: 180+ 种选择器和便捷方法（包括：便捷查找方法12种、选择器类型检测方法4种、XPath便捷方法5种、正则表达式方法7种、CSS基础选择器9种、CSS属性选择器7种、结构伪类10种、内容伪类9种、表单伪类8种、表单元素类型24种、HTML元素伪类19种、HTML5结构元素29种、位置伪类11种、可见性伪类2种、状态伪类4种、语言方向伪类4种、深度伪类5种（含深度范围）、文本相关伪类8种、节点类型伪类3种、子元素数量伪类3种、属性数量伪类3种、属性值长度伪类3种、表单验证伪类9种、文本内容匹配伪类1种、属性值匹配伪类2种、伪元素2种）

**核心特性**:
1. **选择器数组回退查找**: 支持传入多个选择器按顺序尝试，找到第一个非空结果即返回
2. **智能类型检测**: 自动识别 CSS、XPath 和正则表达式类型
3. **全路径选择器支持**: 支持 CSS 和 XPath 的全路径语法，实现精确定位
4. **XPath 1.0 完整支持**: 包括所有标准函数、轴和节点测试
5. **正则表达式选择器**: 使用正则表达式匹配文本和属性
6. **选择器编译缓存**: 自动缓存编译结果，提升性能
7. **混合路径选择**: 支持 CSS 和 XPath 的混合使用
8. **180+ 种选择器**: 覆盖 CSS3、XPath 和自定义选择器
9. **强大的便捷方法**: 提供多种简化的查询接口，提升开发效率

---

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
| extractMode | string | 否 | 仅当 type='regex' 时使用，提取模式：'elements'、'text'、'attr'、'match' |
| group | int | 否 | 仅当 extractMode='match' 时使用，指定分组索引 |
| location | array | 否 | 仅当 type='regex' 时使用，指定提取多个分组并返回关联数组 |


1. **分组索引从0开始**: 正则表达式的分组索引从0开始，0表示完整匹配，1表示第一个捕获分组
2. **description字段可选**: `description` 字段是可选的，主要用于代码注释和文档说明
3. **返回关联数组**: 使用 `location` 参数时，返回的是关联数组，而不是简单数组
4. **空值处理**: 如果某个分组没有匹配到，对应的字段值将设为空字符串
5. **性能考虑**: location参数会在每次匹配时提取所有指定的分组，建议只提取需要的字段

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

#### 5. 使用 location 参数提取多个字段

```php
// 场景：从文本中提取多个字段并返回关联数组
$html = '<div>
    <div class="item">张三:30</div>
    <div class="item">李四:25</div>
    <div class="item">王五:35</div>
</div>';
$doc = new Document($html);

$results = $doc->findWithFallback([
    [
        'selector' => '/(\w+)\s*[:：]\s*(\d+)/',
        'type' => 'regex',
        'location' => [
            'name' => ['index' => 1, 'description' => '姓名'],
            'age' => ['index' => 2, 'description' => '年龄']
        ]
    ]
]);

print_r($results);
// 输出:
// [
//     ['name' => '张三', 'age' => '30'],
//     ['name' => '李四', 'age' => '25'],
//     ['name' => '王五', 'age' => '35']
// ]
```

提取HTML链接

```php
$html = '<div>
    <a href="https://example.com/page1">首页</a>
    <a href="https://example.com/page2">关于</a>
    <a href="https://example.com/page3">联系</a>
</div>';

$doc = new Document($html);

$links = $doc->findWithFallback([
    [
        'selector' => '/<a[^>]*href="([^"]+)"[^>]*>([^<]+)<\/a>/is',
        'type' => 'regex',
        'location' => [
            'href' => ['index' => 1, 'description' => '链接地址'],
            'text' => ['index' => 2, 'description' => '链接文本']
        ]
    ]
]);
```

#### 6. 提取复杂的HTML结构

```php
// 场景：从HTML中提取链接文本和URL
$html = '<div>
    <a href="https://example.com/page1">首页</a>
    <a href="https://example.com/page2">关于</a>
</div>';
$doc = new Document($html);

$links = $doc->findWithFallback([
    [
        'selector' => '/<a[^>]*href="([^"]+)"[^>]*>([^<]+)<\/a>/is',
        'type' => 'regex',
        'location' => [
            'href' => ['index' => 1, 'description' => '链接地址'],
            'text' => ['index' => 2, 'description' => '链接文本']
        ]
    ]
]);

print_r($links);
// 输出:
// [
//     ['href' => 'https://example.com/page1', 'text' => '首页'],
//     ['href' => 'https://example.com/page2', 'text' => '关于']
// ]
```

#### 7. 提取日期时间

```php
// 场景：从复杂格式中提取日期和时间
$html = '<div>
    <div class="event">活动1: 2026-01-15 14:30:00</div>
    <div class="event">活动2: 2026-02-20 09:00:00</div>
</div>';
$doc = new Document($html);

$events = $doc->findWithFallback([
    [
        'selector' => '/(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2}:\d{2})/',
        'type' => 'regex',
        'location' => [
            'date' => ['index' => 1, 'description' => '日期'],
            'time' => ['index' => 2, 'description' => '时间']
        ]
    ]
]);

print_r($events);
// 输出:
// [
//     ['date' => '2026-01-15', 'time' => '14:30:00'],
//     ['date' => '2026-02-20', 'time' => '09:00:00']
// ]
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

### findWithFallback 和 findFirstWithFallback 完整示例

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

### 性能考虑

1. **按优先级排列选择器**：将最可能匹配的选择器放在前面
2. **使用更具体的选择器**：避免过于宽泛的选择器
3. **优先使用CSS选择器**：CSS选择器通常比XPath更高效
4. **避免过多的备选方案**：3-5个选择器为宜

### 错误处理

如果所有选择器都未找到结果，`findWithFallback()` 返回空数组，`findFirstWithFallback()` 返回 null。不会抛出异常。

### 智能选择器类型检测

库提供了 `Query::detectSelectorType()` 方法来智能识别选择器类型：

```php
// 自动检测CSS选择器
$type = Query::detectSelectorType('div.container');  // 返回 'css'

// 自动检测XPath选择器
$type = Query::detectSelectorType('//div[@class="item"]');  // 返回 'xpath'
$type = Query::detectSelectorType('/html/body/div');        // 返回 'xpath'

// 自动检测正则表达式
$type = Query::detectSelectorType('/\d{4}-\d{2}-\d{2}/');  // 返回 'regex'
```

还可以使用具体的检测方法：

```php
// 检测是否为XPath绝对路径
$isAbsolute = Query::isXPathAbsolute('/html/body/div');     // true
$isAbsolute = Query::isXPathAbsolute('//div');               // false

// 检测是否为XPath相对路径
$isRelative = Query::isXPathRelative('//div[@class="item"]'); // true
$isRelative = Query::isXPathRelative('/html/body');         // false
```

---

## CSS 基础选择器

### 1. 通配符选择器 `*`

| 选择器 | 类型  | 说明     | 使用示例              |
|-----|-----|--------|-------------------|
| `*` | 通配符 | 匹配所有元素 | `$doc->find('*')` |

**示例：**
```php
$doc = new Document('<div><p>文本</p><span>内容</span></div>');
$all = $doc->find('*'); // 匹配 div, p, span
echo count($all); // 输出: 3
```


---

### 2. 标签选择器 `tag`

| 选择器   | 类型 | 参数                    | 说明         | 使用示例                |
|-------|----|-----------------------|------------|---------------------|
| `tag` | 标签 | tag: 标签名（如 div, p, a） | 匹配指定标签名的元素 | `$doc->find('div')` |

**示例：**
```php
$doc = new Document('<div>1</div><p>2</p><div>3</div>');
$divs = $doc->find('div');
echo count($divs); // 输出: 2
```


---

### 3. 类选择器 `.class`

| 选择器      | 类型 | 参数        | 说明         | 使用示例                  |
|----------|----|-----------|------------|-----------------------|
| `.class` | 类  | class: 类名 | 匹配具有指定类的元素 | `$doc->find('.item')` |

**示例：**
```php
$doc = new Document('<div class="item">1</div><div class="container">2</div>');
$items = $doc->find('.item');
echo count($items); // 输出: 1
```

**注意事项：**
- 一个元素可以有多个类，`.class` 会匹配所有包含该类的元素
- 多个类选择器可以连用：`.class1.class2`（匹配同时拥有 class1 和 class2 的元素）


---

### 4. ID 选择器 `#id`

| 选择器   | 类型 | 参数        | 说明            | 使用示例                       |
|-------|----|-----------|---------------|----------------------------|
| `#id` | ID | id: ID 名称 | 匹配具有指定 ID 的元素 | `$doc->find('#container')` |

**示例：**
```php
$doc = new Document('<div id="main">主内容</div>');
$main = $doc->find('#main');
echo $main[0]->text(); // 输出: 主内容
```

**注意事项：**
- 在 HTML 中，ID 应该是唯一的
- 如果有多个元素使用相同 ID，只返回第一个


---

### 5. 多选择器 `selector1, selector2`

| 选择器      | 类型 | 参数                  | 说明           | 使用示例                         |
|----------|----|---------------------|--------------|------------------------------|
| `s1, s2` | 多选 | s1, s2: 多个选择器，用逗号分隔 | 匹配任意一个选择器的元素 | `$doc->find('div, p, span')` |

**示例：**
```php
$doc = new Document('<div>1</div><p>2</p><span>3</span><a>4</a>');
$elements = $doc->find('div, p, span');
echo count($elements); // 输出: 3
```


---

### 6. 后代选择器 `selector1 selector2`

| 选择器     | 类型 | 参数                 | 说明             | 使用示例                  |
|---------|----|--------------------|----------------|-----------------------|
| `s1 s2` | 后代 | s1: 父选择器, s2: 子选择器 | 匹配 s1 的所有后代 s2 | `$doc->find('div p')` |

**示例：**
```php
$doc = new Document('<div><p>1</p><span><p>2</p></span></div>');
$ps = $doc->find('div p');
echo count($ps); // 输出: 2（包括嵌套在 span 中的 p）
```


---

### 7. 直接子代选择器 `selector1 > selector2`

| 选择器       | 类型 | 参数                 | 说明              | 使用示例                    |
|-----------|----|--------------------|-----------------|-------------------------|
| `s1 > s2` | 子代 | s1: 父选择器, s2: 子选择器 | 匹配 s1 的直接子元素 s2 | `$doc->find('ul > li')` |

**示例：**
```php
$doc = new Document('<ul><li>1</li><li><span>2</span></li></ul>');
$spans = $doc->find('ul > li > span');
echo count($spans); // 输出: 1
```

**注意事项：**
- 只匹配直接子元素，不包括后代元素
- 这是比后代选择器更精确的选择器


---

### 8. 相邻兄弟选择器 `selector1 + selector2`

| 选择器       | 类型 | 参数                   | 说明              | 使用示例                   |
|-----------|----|----------------------|-----------------|------------------------|
| `s1 + s2` | 兄弟 | s1: 前一个元素, s2: 后一个元素 | 匹配紧接在 s1 后面的 s2 | `$doc->find('h2 + p')` |

**示例：**
```php
$doc = new Document('<h2>标题</h2><p>段落1</p><p>段落2</p>');
$firstP = $doc->find('h2 + p');
echo count($firstP); // 输出: 1（只匹配第一个 p）
```


---

### 9. 通用兄弟选择器 `selector1 ~ selector2`

| 选择器       | 类型 | 参数                    | 说明               | 使用示例                   |
|-----------|----|-----------------------|------------------|------------------------|
| `s1 ~ s2` | 兄弟 | s1: 前一个元素, s2: 后面所有元素 | 匹配 s1 后面的所有兄弟 s2 | `$doc->find('h2 ~ p')` |

**示例：**
```php
$doc = new Document('<h2>标题</h2><p>段落1</p><p>段落2</p>');
$allPs = $doc->find('h2 ~ p');
echo count($allPs); // 输出: 2（匹配所有在 h2 后的 p）
```


---

## CSS 属性选择器

### 1. 存在属性 `[attr]`

| 选择器      | 类型   | 参数        | 说明          | 使用示例                   |
|----------|------|-----------|-------------|------------------------|
| `[attr]` | 属性存在 | attr: 属性名 | 匹配具有指定属性的元素 | `$doc->find('[href]')` |

**示例：**
```php
$doc = new Document('<a href="https://example.com">链接</a><span>文本</span>');
$links = $doc->find('[href]');
echo count($links); // 输出: 1
```


---

### 2. 属性值等于 `[attr=value]`

| 选择器 | 类型 | 参数 | 说明 | 使用示例 |
|--------|------|------|------|----------|
| `[attr=value]` | 等于 | attr: 属性名, value: 属性值 | 匹配属性值完全等于指定值的元素 | `$doc->find('[data-id="123"]')` |

**示例：**
```php
$doc = new Document('<div data-id="123">1</div><div data-id="456">2</div>');
$items = $doc->find('[data-id="123"]');
echo count($items); // 输出: 1
```

**注意事项：**
- 值区分大小写（在 HTML 中）
- 如果值包含空格，需要用引号包裹


---

### 3. 属性值包含单词 `[attr~=value]`

| 选择器             | 类型   | 参数                   | 说明                   | 使用示例                              |
|-----------------|------|----------------------|----------------------|-----------------------------------|
| `[attr~=value]` | 包含单词 | attr: 属性名, value: 单词 | 匹配属性值包含指定单词（空格分隔）的元素 | `$doc->find('[class~="active"]')` |

**示例：**
```php
$doc = new Document('<div class="item active">1</div><div class="item-active">2</div>');
$active = $doc->find('[class~="active"]');
echo count($active); // 输出: 1（只匹配第一个）
```

**注意事项：**
- 匹配由空格分隔的完整单词
- 不匹配部分匹配（如 "item-active" 不会匹配 "active"）


---

### 4. 属性值等于或前缀 `[attr|=value]`

| 选择器              | 类型    | 参数                     | 说明                       | 使用示例                          |
|------------------|-------|------------------------|--------------------------|-------------------------------|
| `[attr\|=value]` | 等于或前缀 | attr: 属性名, value: 值或前缀 | 匹配属性值等于指定值或以指定值加连字符开头的元素 | `$doc->find('[lang\|="en"]')` |

**示例：**
```php
$doc = new Document('<div lang="en">English</div><div lang="en-US">US</div><div lang="fr">French</div>');
$en = $doc->find('[lang|="en"]');
echo count($en); // 输出: 2（匹配 en 和 en-US）
```

**用途：**
- 常用于语言选择（lang 属性）
- 匹配语言代码及其变体


---

### 5. 属性值以...开头 `[attr^=value]`

| 选择器             | 类型   | 参数                      | 说明               | 使用示例                            |
|-----------------|------|-------------------------|------------------|---------------------------------|
| `[attr^=value]` | 开头匹配 | attr: 属性名, value: 开头字符串 | 匹配属性值以指定字符串开头的元素 | `$doc->find('[href^="https"]')` |

**示例：**
```php
$doc = new Document('<a href="https://example.com">HTTPS</a><a href="http://example.com">HTTP</a>');
$httpsLinks = $doc->find('[href^="https"]');
echo count($httpsLinks); // 输出: 1
```


---

### 6. 属性值以...结尾 `[attr$=value]`

| 选择器             | 类型   | 参数                      | 说明               | 使用示例                          |
|-----------------|------|-------------------------|------------------|-------------------------------|
| `[attr$=value]` | 结尾匹配 | attr: 属性名, value: 结尾字符串 | 匹配属性值以指定字符串结尾的元素 | `$doc->find('[src$=".jpg"]')` |

**示例：**
```php
$doc = new Document('<img src="image1.jpg"><img src="image2.png"><img src="image3.jpg">');
$jpgImages = $doc->find('[src$=".jpg"]');
echo count($jpgImages); // 输出: 2
```


---

### 7. 属性值包含 `[attr*=value]`

| 选择器 | 类型 | 参数 | 说明 | 使用示例 |
|--------|------|------|------|----------|
| `[attr*=value]` | 包含匹配 | attr: 属性名, value: 包含字符串 | 匹配属性值包含指定字符串的元素 | `$doc->find('[class*="nav"]')` |

**示例：**
```php
$doc = new Document('<div class="main-nav">1</div><div class="nav-item">2</div><div class="content">3</div>');
$nav = $doc->find('[class*="nav"]');
echo count($nav); // 输出: 2
```

**注意事项：**
- 匹配任意位置的子字符串
- 不区分大小写（在某些浏览器中，但这里区分）


---

## CSS 结构伪类

### 1. 第一个子元素 `:first-child`

| 伪类 | 类型 | 说明 | 使用示例 |
|------|------|------|----------|
| `:first-child` | 结构 | 匹配作为其父元素的第一个子元素的元素 | `$doc->find('li:first-child')` |

**示例：**
```php
$doc = new Document('<ul><li>1</li><li>2</li><li>3</li></ul>');
$first = $doc->find('li:first-child');
echo $first[0]->text(); // 输出: 1
```


---

### 2. 最后一个子元素 `:last-child`

| 伪类 | 类型 | 说明 | 使用示例 |
|------|------|------|----------|
| `:last-child` | 结构 | 匹配作为其父元素的最后一个子元素的元素 | `$doc->find('li:last-child')` |

**示例：**
```php
$doc = new Document('<ul><li>1</li><li>2</li><li>3</li></ul>');
$last = $doc->find('li:last-child');
echo $last[0]->text(); // 输出: 3
```


---

### 3. 唯一子元素 `:only-child`

| 伪类 | 类型 | 说明 | 使用示例 |
|------|------|------|----------|
| `:only-child` | 结构 | 匹配作为其父元素的唯一子元素的元素 | `$doc->find('div:only-child')` |

**示例：**
```php
$doc = new Document('<div><span>唯一</span></div><div><span>A</span><span>B</span></div>');
$only = $doc->find('span:only-child');
echo count($only); // 输出: 1
```


---

### 4. 第 n 个子元素 `:nth-child(n)`

| 伪类 | 参数 | 说明 | 使用示例 |
|------|------|------|----------|
| `:nth-child(n)` | n: 数字、odd、even、an+b | 匹配作为其父元素的第 n 个子元素的元素 | `$doc->find('li:nth-child(2)')` |

**参数说明：**
- 数字：如 `2`、`3`，匹配第几个子元素
- `odd`：匹配奇数位置的子元素（1, 3, 5...）
- `even`：匹配偶数位置的子元素（2, 4, 6...）
- `an+b`：如 `2n+1`、`3n+2`，匹配符合公式的子元素

**示例：**
```php
$doc = new Document('<ul><li>1</li><li>2</li><li>3</li><li>4</li><li>5</li></ul>');
$second = $doc->find('li:nth-child(2)');
echo $second[0]->text(); // 输出: 2

$odd = $doc->find('li:nth-child(odd)');
echo count($odd); // 输出: 3（1, 3, 5）

$even = $doc->find('li:nth-child(even)');
echo count($even); // 输出: 2（2, 4）

$custom = $doc->find('li:nth-child(2n+1)');
echo count($custom); // 输出: 3（1, 3, 5）
```


---

### 5. 倒数第 n 个子元素 `:nth-last-child(n)`

| 伪类 | 参数 | 说明 | 使用示例 |
|------|------|------|----------|
| `:nth-last-child(n)` | n: 数字、odd、even、an+b | 匹配从后往前数的第 n 个子元素 | `$doc->find('li:nth-last-child(2)')` |

**示例：**
```php
$doc = new Document('<ul><li>1</li><li>2</li><li>3</li><li>4</li><li>5</li></ul>');
$secondLast = $doc->find('li:nth-last-child(2)');
echo $secondLast[0]->text(); // 输出: 4
```


---

### 6. 第一个同类型子元素 `:first-of-type`

| 伪类 | 类型 | 说明 | 使用示例 |
|------|------|------|----------|
| `:first-of-type` | 结构 | 匹配作为其父元素的第一个同类型子元素 | `$doc->find('p:first-of-type')` |

**示例：**
```php
$doc = new Document('<div><h2>标题</h2><p>段落1</p><p>段落2</p></div>');
$firstP = $doc->find('p:first-of-type');
echo $firstP[0]->text(); // 输出: 段落1
```

**注意事项：**
- 只考虑同类型的元素
- 如果有多个不同类型的元素，每种类型都会有一个 first-of-type


---

### 7. 最后一个同类型子元素 `:last-of-type`

| 伪类 | 类型 | 说明 | 使用示例 |
|------|------|------|----------|
| `:last-of-type` | 结构 | 匹配作为其父元素的最后一个同类型子元素 | `$doc->find('p:last-of-type')` |

**示例：**
```php
$doc = new Document('<div><h2>标题</h2><p>段落1</p><p>段落2</p></div>');
$lastP = $doc->find('p:last-of-type');
echo $lastP[0]->text(); // 输出: 段落2
```


---

### 8. 第 n 个同类型子元素 `:nth-of-type(n)`

| 伪类 | 参数 | 说明 | 使用示例 |
|------|------|------|----------|
| `:nth-of-type(n)` | n: 数字、odd、even、an+b | 匹配作为其父元素的第 n 个同类型子元素 | `$doc->find('p:nth-of-type(2)')` |

**示例：**
```php
$doc = new Document('<div><h2>标题</h2><p>段落1</p><p>段落2</p><p>段落3</p></div>');
$secondP = $doc->find('p:nth-of-type(2)');
echo $secondP[0]->text(); // 输出: 段落2
```


---

### 9. 倒数第 n 个同类型子元素 `:nth-last-of-type(n)`

| 伪类 | 参数 | 说明 | 使用示例 |
|------|------|------|----------|
| `:nth-last-of-type(n)` | n: 数字、odd、even、an+b | 匹配从后往前数的第 n 个同类型子元素 | `$doc->find('p:nth-last-of-type(2)')` |

**示例：**
```php
$doc = new Document('<div><h2>标题</h2><p>段落1</p><p>段落2</p><p>段落3</p></div>');
$secondLastP = $doc->find('p:nth-last-of-type(2)');
echo $secondLastP[0]->text(); // 输出: 段落2
```


---

### 10. 唯一同类型子元素 `:only-of-type`

| 伪类 | 类型 | 说明 | 使用示例 |
|------|------|------|----------|
| `:only-of-type` | 结构 | 匹配作为其父元素的唯一同类型子元素 | `$doc->find('div:only-of-type')` |

**示例：**
```php
$doc = new Document('<div><p>段落</p></div><div><h2>标题</h2><h2>副标题</h2></div>');
$onlyP = $doc->find('p:only-of-type');
echo count($onlyP); // 输出: 1
```


---

## CSS 内容伪类

### 1. 空元素 `:empty`

| 伪类 | 类型 | 说明 | 使用示例 |
|------|------|------|----------|
| `:empty` | 内容 | 匹配没有任何子元素或文本的元素 | `$doc->find('div:empty')` |

**示例：**
```php
$doc = new Document('<div></div><div>文本</div>');
$empty = $doc->find('div:empty');
echo count($empty); // 输出: 1
```

**注意事项：**
- 不包含子元素
- 不包含文本内容（包括空白字符）


---

### 2. 包含文本 `:contains(text)`

| 伪类 | 参数 | 说明 | 使用示例 |
|------|------|------|----------|
| `:contains(text)` | text: 要查找的文本 | 匹配包含指定文本的元素（包括子元素文本） | `$doc->find('div:contains(Hello)')` |

**示例：**
```php
$doc = new Document('<div><p>Hello World</p></div>');
$matched = $doc->find('div:contains(Hello)');
echo count($matched); // 输出: 1
```

**注意事项：**
- 搜索包括所有子元素的文本内容
- 区分大小写
- 不匹配 HTML 标签或属性


---

### 3. 直接包含文本 `:contains-text(text)`

| 伪类 | 参数 | 说明 | 使用示例 |
|------|------|------|----------|
| `:contains-text(text)` | text: 要查找的文本 | 匹配直接包含指定文本的元素（不包括子元素） | `$doc->find('div:contains-text(World)')` |

**示例：**
```php
$doc = new Document('<div>Hello <span>World</span></div>');
$matched = $doc->find('div:contains-text(World)');
echo count($matched); // 输出: 0（World 在 span 中，不在 div 直接文本中）
```


---

### 4. 文本以...开头 `:starts-with(text)`

| 伪类 | 参数 | 说明 | 使用示例 |
|------|------|------|----------|
| `:starts-with(text)` | text: 开头文本 | 匹配文本以指定字符串开头的元素 | `$doc->find('div:starts-with(Hello)')` |

**示例：**
```php
$doc = new Document('<div>Hello World</div>');
$matched = $doc->find('div:starts-with(Hello)');
echo count($matched); // 输出: 1
```


---

### 5. 文本以...结尾 `:ends-with(text)`

| 伪类 | 参数 | 说明 | 使用示例 |
|------|------|------|----------|
| `:ends-with(text)` | text: 结尾文本 | 匹配文本以指定字符串结尾的元素 | `$doc->find('div:ends-with(World)')` |

**示例：**
```php
$doc = new Document('<div>Hello World</div>');
$matched = $doc->find('div:ends-with(World)');
echo count($matched); // 输出: 1
```


---

### 6. 包含后代元素 `:has(selector)`

| 伪类 | 参数 | 说明 | 使用示例 |
|------|------|------|----------|
| `:has(selector)` | selector: 选择器 | 匹配包含匹配指定选择器后代元素的元素 | `$doc->find('div:has(a)')` |

**示例：**
```php
$doc = new Document('<div><a>链接</a></div><div>纯文本</div>');
$hasLink = $doc->find('div:has(a)');
echo count($hasLink); // 输出: 1
```

**注意事项：**
- 匹配包含指定后代元素的父元素
- 可以嵌套使用


---

### 7. 不匹配选择器 `:not(selector)`

| 伪类 | 参数 | 说明 | 使用示例 |
|------|------|------|----------|
| `:not(selector)` | selector: 选择器 | 匹配不匹配指定选择器的元素 | `$doc->find('div:not(.active)')` |

**示例：**
```php
$doc = new Document('<div class="active">1</div><div class="inactive">2</div>');
$inactive = $doc->find('div:not(.active)');
echo count($inactive); // 输出: 1
```

**注意事项：**
- 可以与其他选择器组合使用
- 不支持复杂选择器作为参数


---

### 8. 空白元素 `:blank`

| 伪类 | 类型 | 说明 | 使用示例 |
|------|------|------|----------|
| `:blank` | 内容 | 匹配空白元素（无可见文本或子元素） | `$doc->find('p:blank')` |

**示例：**
```php
$doc = new Document('<p>   </p><p>文本</p>');
$blank = $doc->find('p:blank');
echo count($blank); // 输出: 1
```

**注意事项：**
- 与 `:empty` 不同，`:blank` 允许空白字符
- 只检查可见内容


---

### 9. 只有文本内容 `:parent-only-text`

| 伪类 | 类型 | 说明 | 使用示例 |
|------|------|------|----------|
| `:parent-only-text` | 内容 | 匹配只有文本内容没有子元素的元素 | `$doc->find('div:parent-only-text')` |

**示例：**
```php
$doc = new Document('<div>纯文本</div><div><span>子元素</span></div>');
$onlyText = $doc->find('div:parent-only-text');
echo count($onlyText); // 输出: 1
```


---

## CSS 表单伪类

### 1. 启用的元素 `:enabled`

| 伪类 | 类型 | 说明 | 使用示例 |
|------|------|------|----------|
| `:enabled` | 表单状态 | 匹配启用的表单元素 | `$doc->find(':enabled')` |

**示例：**
```php
$doc = new Document('<input type="text"><input type="text" disabled>');
$enabled = $doc->find('input:enabled');
echo count($enabled); // 输出: 1
```


---

### 2. 禁用的元素 `:disabled`

| 伪类 | 类型 | 说明 | 使用示例 |
|------|------|------|----------|
| `:disabled` | 表单状态 | 匹配禁用的表单元素 | `$doc->find(':disabled')` |

**示例：**
```php
$doc = new Document('<input type="text"><input type="text" disabled>');
$disabled = $doc->find('input:disabled');
echo count($disabled); // 输出: 1
```

**注意事项：**
- 匹配 `disabled` 属性（可以是 `disabled` 或 `disabled="disabled"`）


---

### 3. 选中的元素 `:checked`

| 伪类 | 类型 | 说明 | 使用示例 |
|------|------|------|----------|
| `:checked` | 表单状态 | 匹配被选中的复选框或单选按钮 | `$doc->find(':checked')` |

**示例：**
```php
$doc = new Document('<input type="checkbox" checked><input type="checkbox">');
$checked = $doc->find('input:checked');
echo count($checked); // 输出: 1
```


---

### 4. 选中的选项 `:selected`

| 伪类 | 类型 | 说明 | 使用示例 |
|------|------|------|----------|
| `:selected` | 表单状态 | 匹配被选中的选项 | `$doc->find('option:selected')` |

**示例：**
```php
$doc = new Document('<select><option>1</option><option selected>2</option></select>');
$selected = $doc->find('option:selected');
echo count($selected); // 输出: 1
```


---

### 5. 必填字段 `:required`

| 伪类 | 类型 | 说明 | 使用示例 |
|------|------|------|----------|
| `:required` | 表单状态 | 匹配必填字段 | `$doc->find(':required')` |

**示例：**
```php
$doc = new Document('<input type="text" required><input type="text">');
$required = $doc->find('input:required');
echo count($required); // 输出: 1
```

**注意事项：**
- 匹配 `required` 属性（可以是 `required` 或 `required="required"`）


---

### 6. 可选字段 `:optional`

| 伪类 | 类型 | 说明 | 使用示例 |
|------|------|------|----------|
| `:optional` | 表单状态 | 匹配可选字段 | `$doc->find(':optional')` |

**示例：**
```php
$doc = new Document('<input type="text" required><input type="text">');
$optional = $doc->find('input:optional');
echo count($optional); // 输出: 1
```


---

### 7. 只读字段 `:read-only`

| 伪类 | 类型 | 说明 | 使用示例 |
|------|------|------|----------|
| `:read-only` | 表单状态 | 匹配只读字段 | `$doc->find(':read-only')` |

**示例：**
```php
$doc = new Document('<input type="text" readonly><input type="text">');
$readOnly = $doc->find('input:read-only');
echo count($readOnly); // 输出: 1
```


---

### 8. 可写字段 `:read-write`

| 伪类 | 类型 | 说明 | 使用示例 |
|------|------|------|----------|
| `:read-write` | 表单状态 | 匹配可写字段 | `$doc->find(':read-write')` |

**示例：**
```php
$doc = new Document('<input type="text" readonly><input type="text">');
$readWrite = $doc->find('input:read-write');
echo count($readWrite); // 输出: 1
```


---

## CSS 表单元素伪类

### 1. 文本输入 `:text`

| 伪类 | 类型 | 说明 | 使用示例 |
|------|------|------|----------|
| `:text` | 表单元素 | 匹配文本输入框 | `$doc->find('input:text')` |

**示例：**
```php
$doc = new Document('<input type="text"><input type="password">');
$textInputs = $doc->find('input:text');
echo count($textInputs); // 输出: 1
```


---

### 2. 密码输入 `:password`

| 伪类 | 类型 | 说明 | 使用示例 |
|------|------|------|----------|
| `:password` | 表单元素 | 匹配密码输入框 | `$doc->find('input:password')` |

**示例：**
```php
$doc = new Document('<input type="text"><input type="password">');
$passwordInputs = $doc->find('input:password');
echo count($passwordInputs); // 输出: 1
```


---

### 3. 单选按钮 `:radio`

| 伪类 | 类型 | 说明 | 使用示例 |
|------|------|------|----------|
| `:radio` | 表单元素 | 匹配单选按钮 | `$doc->find('input:radio')` |

**示例：**
```php
$doc = new Document('<input type="radio"><input type="checkbox">');
$radioInputs = $doc->find('input:radio');
echo count($radioInputs); // 输出: 1
```


---

### 4. 复选框 `:checkbox`

| 伪类 | 类型 | 说明 | 使用示例 |
|------|------|------|----------|
| `:checkbox` | 表单元素 | 匹配复选框 | `$doc->find('input:checkbox')` |

**示例：**
```php
$doc = new Document('<input type="radio"><input type="checkbox">');
$checkboxInputs = $doc->find('input:checkbox');
echo count($checkboxInputs); // 输出: 1
```


---

### 5. 提交按钮 `:submit`

| 伪类 | 类型 | 说明 | 使用示例 |
|------|------|------|----------|
| `:submit` | 表单元素 | 匹配提交按钮 | `$doc->find('input:submit')` |

**示例：**
```php
$doc = new Document('<input type="submit"><input type="button">');
$submitInputs = $doc->find('input:submit');
echo count($submitInputs); // 输出: 1
```


---

### 6. 按钮 `:button`

| 伪类 | 类型 | 说明 | 使用示例 |
|------|------|------|----------|
| `:button` | 表单元素 | 匹配按钮 | `$doc->find('button, input:button')` |

**示例：**
```php
$doc = new Document('<button>点击</button><input type="button">');
$buttons = $doc->find(':button');
echo count($buttons); // 输出: 2
```


---

## CSS 位置伪类

### 1. 第一个元素 `:first`

| 伪类 | 类型 | 说明 | 使用示例 |
|------|------|------|----------|
| `:first` | 位置 | 匹配结果集中的第一个元素 | `$doc->find('li:first')` |

**示例：**
```php
$doc = new Document('<ul><li>1</li><li>2</li><li>3</li></ul>');
$first = $doc->find('li:first');
echo $first[0]->text(); // 输出: 1
```

**注意事项：**
- 与 `:first-child` 不同，`:first` 作用于查询结果集
- 限制返回结果为第一个元素


---

### 2. 最后一个元素 `:last`

| 伪类 | 类型 | 说明 | 使用示例 |
|------|------|------|----------|
| `:last` | 位置 | 匹配结果集中的最后一个元素 | `$doc->find('li:last')` |

**示例：**
```php
$doc = new Document('<ul><li>1</li><li>2</li><li>3</li></ul>');
$last = $doc->find('li:last');
echo $last[0]->text(); // 输出: 3
```


---

### 3. 偶数位置 `:even`

| 伪类 | 类型 | 说明 | 使用示例 |
|------|------|------|----------|
| `:even` | 位置 | 匹配结果集中偶数位置的元素（索引 1, 3, 5...） | `$doc->find('li:even')` |

**示例：**
```php
$doc = new Document('<ul><li>A</li><li>B</li><li>C</li><li>D</li><li>E</li></ul>');
$even = $doc->find('li:even');
echo count($even); // 输出: 2（B, D）
```

**注意事项：**
- 基于结果集的索引（从 0 开始）
- `:even` 匹配索引 1, 3, 5...（第 2、4、6... 个元素）


---

### 4. 奇数位置 `:odd`

| 伪类 | 类型 | 说明 | 使用示例 |
|------|------|------|----------|
| `:odd` | 位置 | 匹配结果集中奇数位置的元素（索引 0, 2, 4...） | `$doc->find('li:odd')` |

**示例：**
```php
$doc = new Document('<ul><li>A</li><li>B</li><li>C</li><li>D</li><li>E</li></ul>');
$odd = $doc->find('li:odd');
echo count($odd); // 输出: 3（A, C, E）
```

**注意事项：**
- 基于结果集的索引（从 0 开始）
- `:odd` 匹配索引 0, 2, 4...（第 1、3、5... 个元素）


---

### 5. 小于索引 `:lt(n)`

| 伪类 | 参数 | 说明 | 使用示例 |
|------|------|------|----------|
| `:lt(n)` | n: 索引值 | 匹配索引小于 n 的元素 | `$doc->find('li:lt(3)')` |

**示例：**
```php
$doc = new Document('<ul><li>1</li><li>2</li><li>3</li><li>4</li><li>5</li></ul>');
$lt3 = $doc->find('li:lt(3)');
echo count($lt3); // 输出: 3（索引 0, 1, 2）
```


---

### 6. 大于索引 `:gt(n)`

| 伪类 | 参数 | 说明 | 使用示例 |
|------|------|------|----------|
| `:gt(n)` | n: 索引值 | 匹配索引大于 n 的元素 | `$doc->find('li:gt(2)')` |

**示例：**
```php
$doc = new Document('<ul><li>1</li><li>2</li><li>3</li><li>4</li><li>5</li></ul>');
$gt2 = $doc->find('li:gt(2)');
echo count($gt2); // 输出: 2（索引 3, 4）
```


---

### 7. 等于索引 `:eq(n)`

| 伪类 | 参数 | 说明 | 使用示例 |
|------|------|------|----------|
| `:eq(n)` | n: 索引值 | 匹配索引等于 n 的元素 | `$doc->find('li:eq(2)')` |

**示例：**
```php
$doc = new Document('<ul><li>1</li><li>2</li><li>3</li><li>4</li><li>5</li></ul>');
$eq2 = $doc->find('li:eq(2)');
echo $eq2[0]->text(); // 输出: 3
```


---

## CSS 可见性伪类

### 1. 可见元素 `:visible`

| 伪类 | 类型 | 说明 | 使用示例 |
|------|------|------|----------|
| `:visible` | 可见性 | 匹配可见元素 | `$doc->find('div:visible')` |

**示例：**
```php
$doc = new Document('<div>可见</div><div style="display:none">隐藏</div>');
$visible = $doc->find('div:visible');
echo count($visible); // 输出: 1
```

**注意事项：**
- 不匹配 `display: none` 或 `visibility: hidden` 的元素
- 不匹配 `hidden` 属性的元素
- 不匹配 `type="hidden"` 的输入框


---

### 2. 隐藏元素 `:hidden`

| 伪类 | 类型 | 说明 | 使用示例 |
|------|------|------|----------|
| `:hidden` | 可见性 | 匹配隐藏元素 | `$doc->find('div:hidden')` |

**示例：**
```php
$doc = new Document('<div>可见</div><div style="display:none">隐藏</div>');
$hidden = $doc->find('div:hidden');
echo count($hidden); // 输出: 1
```


---

## 全路径选择器详解

全路径选择器是 DOM 查询中的高级技巧，通过指定完整的元素路径来实现精确、高效的元素定位。

### 全路径选择器的类型

**1. XPath 绝对路径**

以 `/` 开头，从文档根元素开始的完整路径：

```php
// 基本语法
/html/body/div[1]/div[2]/p[3]

// 实际示例
$doc = new Document('<html><body><div><div><p>第一段</p><p>第二段</p><p>第三段</p></div></div></body></html>');
$element = $doc->xpath('/html/body/div[1]/div[1]/p[3]');
echo $element[0]->text(); // 输出: 第三段
```

**2. XPath 相对路径**

以 `//` 开头，从文档任意位置开始搜索：

```php
// 基本语法
//div[@class="container"]/div[@class="content"]/p

// 实际示例
$doc = new Document('<div class="container"><div class="content"><p>内容</p></div></div>');
$element = $doc->xpath('//div[@class="container"]/div[@class="content"]/p');
echo $element[0]->text(); // 输出: 内容
```

**3. CSS 全路径选择器**

使用 CSS 组合选择器模拟全路径：

```php
// 基本语法
div.container > div.content > p.text

// 实际示例
$doc = new Document('<div class="container"><div class="content"><p class="text">内容</p></div></div>');
$element = $doc->find('div.container > div.content > p.text');
echo $element[0]->text(); // 输出: 内容
```

**4. 混合路径选择器**

结合 CSS 选择器和 XPath 语法的优势：

```php
// 在 Document 类中使用 findByPath 方法
$element = $doc->findByPath('div.content > div.pages-date > span');

// 自动识别路径类型并转换
$element = $doc->findByPath('/html/body/div[1]/div[2]/span');
$element = $doc->findByPath('//div[@class="item"]/a');
```

### 全路径选择器的使用场景

**场景1：提取固定结构的网页内容**

```php
// 网页结构固定，使用全路径提取
$html = file_get_contents('https://example.com');
$doc = new Document($html);

// 提取文章标题
$title = $doc->xpath('/html/body/div[1]/div[@class="article"]/h1');
echo $title[0]->text();

// 提取文章内容
$content = $doc->xpath('/html/body/div[1]/div[@class="article"]/div[@class="content"]');
echo $content[0]->html();
```

**场景2：处理复杂的表格数据**

```php
// 使用全路径精确定位表格单元格
$cell = $doc->xpath('/html/body/div[3]/div[@class="data-table"]/table[1]/tbody/tr[5]/td[3]');
echo $cell[0]->text();

// 使用属性条件
$cell = $doc->xpath('//table[@class="data"]/tbody/tr[@data-id="123"]/td[@class="value"]');
echo $cell[0]->text();
```

**场景3：提取特定区域的文本节点**

```php
// 获取元素的直接文本（不包括子元素）
$texts = $doc->xpathTexts('//div[@class="item"]/text()');

// 获取所有文本节点（包括后代）
$texts = $doc->xpathTexts('//div[@class="content"]//text()');

// 使用全路径
$texts = $doc->xpathTexts('/html/body/div[1]/div[@class="article"]/p[1]/text()');
```

**场景4：提取链接和图片**

```php
// 提取特定区域的链接
$hrefs = $doc->xpathAttrs('//div[@class="nav"]/ul/li/a', 'href');

// 使用全路径
$hrefs = $doc->xpathAttrs('/html/body/div[1]/div[@class="footer"]/a', 'href');

// 提取特定区域的图片
$srcs = $doc->xpathAttrs('//div[@class="gallery"]/img', 'src');
```

### 全路径选择器 vs 相对选择器

| 特性 | 全路径选择器 | 相对选择器 |
|------|------------|-----------|
| **性能** | ✅ 高性能，直接定位 | ⚠️ 需要遍历DOM树 |
| **精确度** | ✅ 精确定位，无歧义 | ⚠️ 可能匹配多个元素 |
| **灵活性** | ❌ 结构变化时失效 | ✅ 适应结构变化 |
| **适用场景** | 结构稳定的文档 | 结构可变的文档 |
| **可读性** | ✅ 清晰明确 | ⚠️ 需要理解搜索逻辑 |

**选择建议：**
```php
// 场景1：网页结构固定，使用全路径
$element = $doc->xpath('/html/body/div[3]/div[@class="content"]/h1');

// 场景2：网页结构多变，使用相对路径
$element = $doc->xpath('//h1[@class="main-title"]');

// 场景3：在已知区域内搜索
$container = $doc->first('div.content');
$element = $doc->find('h1', Query::TYPE_CSS, $container->getNode());

// 场景4：混合使用
$container = $doc->xpathFirst('//div[@class="main-container"]');
if ($container) {
    $element = $doc->findFirstByAttribute('data-id', '123', '.item');
}
```

### 全路径选择器的最佳实践

**1. 使用属性而非索引**

```php
// ✅ 推荐：使用属性更稳定
$element = $doc->xpath('//div[@class="container"]/div[@class="item"][1]');

// ⚠️ 可用：使用索引但不够稳定
$element = $doc->xpath('/html/body/div[3]/div[1]/div[2]/div[1]');
```

**2. 组合使用路径和条件**

```php
// ✅ 推荐：路径 + 属性条件
$element = $doc->xpath('//div[@class="container"]/div[contains(@class, "item") and @data-active="true"]');
```

**3. 使用便捷方法简化操作**

```php
// 获取单个元素
$element = $doc->xpathFirst('//div[@class="container"]/p[1]');

// 获取文本数组
$texts = $doc->xpathTexts('//div[@class="item"]/text()');

// 获取属性数组
$hrefs = $doc->xpathAttrs('//div[@class="nav"]//a', 'href');
```

**4. 在复杂文档中使用 findByPath**

```php
// 自动识别路径类型
$element = $doc->findByPath('/html/body/div[1]/div[2]/span');  // XPath
$element = $doc->findByPath('div.content > div.item > a');      // CSS

// 处理解析失败的情况
try {
    $elements = $doc->findByPath('complex/path/expression');
} catch (Exception $e) {
    // 回退到其他方法
    $elements = $doc->find('div.complex .path .expression');
}
```

### 性能对比示例

```php
// 假设文档结构：
// <html><body><div><div><div><div><div class="target">目标</div></div></div></div></div></body></html>

// 方式1：全XPath路径（最快）
$start = microtime(true);
$element = $doc->xpath('/html/body/div[1]/div[1]/div[1]/div[1]/div[@class="target"]');
$time1 = microtime(true) - $start;

// 方式2：XPath相对路径（较快）
$start = microtime(true);
$element = $doc->xpath('//div[@class="target"]');
$time2 = microtime(true) - $start;

// 方式3：CSS选择器（最慢）
$start = microtime(true);
$element = $doc->find('div.target');
$time3 = microtime(true) - $start;

// 结果：time1 < time2 < time3
```

**结论：**
- 对于深层嵌套且结构稳定的文档，全路径选择器性能最优
- 对于需要灵活匹配的场景，相对路径更合适
- 在性能关键的场景中，优先使用全路径选择器


---

## CSS 伪元素

### 1. 文本内容 `::text`

| 伪元素 | 类型 | 说明 | 使用示例 |
|--------|------|------|----------|
| `::text` | 伪元素 | 获取元素的文本内容 | `$doc->text('div::text')` |

**示例：**
```php
$doc = new Document('<div>Hello <span>World</span></div>');
$text = $doc->text('div::text');
echo $text; // 输出: Hello World
```

**注意事项：**
- 返回元素及其所有子元素的文本内容
- 可以与 `find()` 或 `text()` 方法一起使用
- 与 `text()` 方法配合使用时，直接返回文本字符串


---

### 2. 属性值 `::attr(name)`

| 伪元素 | 参数 | 说明 | 使用示例 |
|--------|------|------|----------|
| `::attr(name)` | name: 属性名 | 获取元素的属性值 | `$doc->text('a::attr(href)')` |

**示例：**
```php
$doc = new Document('<a href="https://example.com" data-id="123">链接</a>');
$href = $doc->text('a::attr(href)');
$dataId = $doc->text('a::attr(data-id)');
echo $href; // 输出: https://example.com
echo $dataId; // 输出: 123
```

**注意事项：**
- 可以获取任何属性的值
- 属性名需要用引号包裹
- 返回属性的字符串值


---

## XPath 选择器

XPath 是一种强大的查询语言，可以精确地定位 XML/HTML 文档中的元素。本库完全支持 XPath 1.0 规范，并提供全路径选择能力。

### 基本语法

| 语法 | 说明 | 示例 |
|------|------|------|
| `//tag` | 匹配所有指定标签的元素 | `//div` |
| `/tag` | 匹配根元素 | `/html` |
| `tag1/tag2` | 匹配 tag1 下的 tag2（直接子元素） | `html/body/div` |
| `tag1//tag2` | 匹配 tag1 下的所有 tag2（后代元素） | `html//div` |
| `@attr` | 匹配属性 | `//div[@class="item"]` |
| `[n]` | 匹配第 n 个元素 | `(//div)[1]` |
| `[last()]` | 匹配最后一个元素 | `(//div)[last()]` |

### 全路径选择器

全路径选择器使用完整的 DOM 树路径来精确定位元素，具有以下优势：

**1. 绝对路径（从根元素开始）**
```
/html/body/div[3]/div[1]/div/div[1]/span
```
- 以 `/` 开头，从文档根元素（html）开始
- 精确指定每一层的标签和索引
- 适合结构稳定的文档

**2. 相对路径（从任意位置开始）**
```
//div[@class="container"]/div[@class="content"]/p
```
- 以 `//` 开头，从文档任意位置匹配
- 使用属性条件定位元素
- 更灵活，不依赖完整的DOM结构

**3. 文本节点路径**
```
//div[@class="item"]/text()
//body/div[3]/div[1]/div/div[1]/text()
```
- 使用 `text()` 函数获取文本节点
- 可以提取元素的直接文本或所有后代文本

### XPath 轴

XPath 轴用于定义相对于当前节点的节点集：

| 轴 | 说明 | 示例 |
|------|------|------|
| `ancestor` | 祖先元素 | `//div/ancestor::*` |
| `ancestor-or-self` | 祖先元素和自身 | `//div/ancestor-or-self::*` |
| `child` | 子元素 | `//div/child::p` |
| `descendant` | 后代元素 | `//div/descendant::p` |
| `following-sibling` | 后面的兄弟元素 | `//div/following-sibling::p` |
| `preceding-sibling` | 前面的兄弟元素 | `//div/preceding-sibling::p` |

**轴选择器示例：**
```php
// 获取 div 的所有祖先元素
$ancestors = $doc->xpath('//div/ancestor::*');

// 获取当前元素之后的所有兄弟
$siblings = $doc->xpath('//div/following-sibling::*');

// 获取 div 的所有后代 p 元素
$paragraphs = $doc->xpath('//div/descendant::p');
```

**示例：**
```php
$doc = new Document('<div><p>1</p><p>2</p></div>');
$ps = $doc->xpath('//div//p'); // 匹配所有 div 下的 p
echo count($ps); // 输出: 2

$firstP = $doc->xpath('(//div//p)[1]');
echo $firstP[0]->text(); // 输出: 1
```


---

### XPath 函数

**字符串函数**

| 函数 | 说明 | 示例 |
|------|------|------|
| `contains(text, 'str')` | 包含文本 | `//div[contains(text(), "hello")]` |
| `starts-with(text, 'str')` | 以...开头 | `//div[starts-with(@class, "item")]` |
| `ends-with(text, 'str')` | 以...结尾 | `//div[ends-with(@id, "-item")]` |
| `substring(text, start, length)` | 子字符串 | `//div[substring(@title, 1, 3)]` |
| `string-length(text)` | 字符串长度 | `//div[string-length(text()) > 10]` |
| `concat(str1, str2, ...)` | 连接字符串 | `concat(@first, " ", @last)` |
| `translate(text, src, dest)` | 字符转换 | `translate(@text, "abc", "ABC")` |
| `normalize-space(text)` | 规范化空格 | `//div[normalize-space(text())]` |

**数值函数**

| 函数 | 说明 | 示例 |
|------|------|------|
| `position()` | 当前位置 | `//li[position()=1]` |
| `last()` | 最后一个 | `//li[last()]` |
| `count(node-set)` | 计数 | `//ul[count(li) > 2]` |
| `sum(node-set)` | 求和 | `//td[sum(//td[@class="price"]) > 100]` |
| `number(text)` | 转换为数字 | `//div[number(@value) > 100]` |
| `floor(num)` | 向下取整 | `//div[floor(@value) = 10]` |
| `ceiling(num)` | 向上取整 | `//div[ceiling(@value) = 20]` |
| `round(num)` | 四舍五入 | `//div[round(@value) = 15]` |

**布尔函数**

| 函数 | 说明 | 示例 |
|------|------|------|
| `true()` | 真 | `//div[true()]` |
| `false()` | 假 | `//div[false()]` |
| `not(condition)` | 非 | `//div[not(@hidden)]` |
| `and` | 与 | `//div[@class="a" and @class="b"]` |
| `or` | 或 | `//div[@class="a" or @class="b"]` |

**节点测试**

| 函数 | 说明 | 示例 |
|------|------|------|
| `text()` | 文本节点 | `//div/text()` |
| `comment()` | 注释节点 | `//comment()` |
| `node()` | 任意节点 | `//div/node()` |
| `element()` | 元素节点 | `//*[element()]` |
| `processing-instruction()` | 处理指令 | `//processing-instruction()` |

**XPath 使用示例：**

```php
// 基本路径查询
$doc = new Document('<div><p>1</p><p>2</p></div>');
$ps = $doc->xpath('//div//p'); // 匹配所有 div 下的 p
echo count($ps); // 输出: 2

$firstP = $doc->xpath('(//div//p)[1]');
echo $firstP[0]->text(); // 输出: 1

// 绝对路径（全路径）
$doc = new Document('<html><body><div class="container"><p>内容</p></div></body></html>');
$element = $doc->xpath('/html/body/div[@class="container"]/p');
echo $element[0]->text(); // 输出: 内容

// 使用函数过滤
$doc = new Document('<div class="item-1">1</div><div class="item-2">2</div>');
$items = $doc->xpath('//div[starts-with(@class, "item")]');
echo count($items); // 输出: 2

// 组合条件
$doc = new Document('<div class="item" data-id="123">1</div><div class="item" data-id="456">2</div>');
$element = $doc->xpath('//div[@class="item" and @data-id="123"]');
echo $element[0]->text(); // 输出: 1

// 使用数值函数
$doc = new Document('<ul><li>1</li><li>2</li><li>3</li></ul>');
$items = $doc->xpath('//ul[count(li) > 2]/li');
echo count($items); // 输出: 3

// 使用字符串函数
$doc = new Document('<div data-length="10">内容</div>');
$divs = $doc->xpath('//div[string-length(text()) > 2]');
echo count($divs); // 输出: 1
```

### CSS 选择器 vs XPath 选择器

| 特性 | CSS 选择器 | XPath 选择器 |
|------|-----------|-------------|
| 简洁性 | ✅ 更简洁直观 | ⚠️ 相对复杂 |
| 学习曲线 | ✅ 容易上手 | ⚠️ 需要学习 |
| 功能强大 | ✅ 覆盖大部分场景 | ✅ 功能更强大 |
| 文本匹配 | ⚠️ 需要伪类 | ✅ 内置函数支持 |
| 属性匹配 | ✅ 简洁语法 | ✅ 更灵活 |
| 轴选择 | ❌ 不支持 | ✅ 完整支持 |
| 数值计算 | ❌ 不支持 | ✅ 内置函数 |
| 全路径 | ⚠️ 通过组合器 | ✅ 原生支持 |

**选择建议：**
- **使用 CSS 选择器**：日常开发、简单的元素选择、需要快速编写
- **使用 XPath 选择器**：复杂查询、需要轴选择、需要文本/数值计算、使用全路径


---

### XPath 轴

| 轴 | 说明 | 示例 |
|------|------|------|
| `ancestor` | 祖先元素 | `//div/ancestor::*` |
| `ancestor-or-self` | 祖先元素和自身 | `//div/ancestor-or-self::*` |
| `child` | 子元素 | `//div/child::p` |
| `descendant` | 后代元素 | `//div/descendant::p` |
| `following-sibling` | 后面的兄弟元素 | `//div/following-sibling::p` |
| `preceding-sibling` | 前面的兄弟元素 | `//div/preceding-sibling::p` |


---

## 特殊选择器

### 1. 全路径选择器（CSS + XPath 混合）

本库支持在同一个表达式中混合使用 CSS 选择器和 XPath 语法，提供最大化的灵活性。

**使用方式：**
```php
// 纯 CSS 选择器
$elements = $doc->find('div.container > div.content > p');

// 纯 XPath 选择器
$elements = $doc->xpath('//div[@class="container"]/div[@class="content"]/p');

// 混合使用（自动识别）
$elements = $doc->find('div[@class="container"]/div.content > p');
$elements = $doc->xpath('/html/body/div[3]/div[@class="item"]/a');

// 使用 findByPath 方法
$elements = $doc->findByPath('/html/body/div[1]/div[2]/span');
$elements = $doc->findByPath('div.content > div.pages-date > span');
```

**全路径选择器的优势：**

1. **精确定位**
   ```php
   // 直接定位到特定位置
   $element = $doc->xpath('/html/body/div[3]/div[1]/div/div[1]/span');
   ```

2. **减少搜索范围**
   ```php
   // 比模糊搜索更高效
   $elements = $doc->xpath('//div[@id="content"]/div[@class="article"]/p');
   ```

3. **复杂条件组合**
   ```php
   // 多条件过滤
   $elements = $doc->xpath('//div[contains(@class, "item") and @data-id > 100 and position() < 5]');
   ```

4. **文本和属性提取**
   ```php
   // 直接提取文本
   $texts = $doc->xpathTexts('//div[@class="content"]/p/text()');

   // 提取属性
   $hrefs = $doc->xpathAttrs('//a[contains(@class, "link")]', 'href');
   ```

### 2. 根元素 `:root`

| 伪类 | 类型 | 说明 | 使用示例 |
|------|------|------|----------|
| `:root` | 文档 | 匹配文档根元素 | `$doc->find(':root')` |

**示例：**
```php
$doc = new Document('<html><body><div>内容</div></body></html>');
$root = $doc->find(':root');
echo $root[0]->tagName(); // 输出: html
```


---

### 2. 目标元素 `:target`

| 伪类 | 类型 | 说明 | 使用示例 |
|------|------|------|----------|
| `:target` | 文档 | 匹配 URL 哈希指向的元素 | `$doc->find(':target')` |

**示例：**
```php
$doc = new Document('<div id="section1">第一节</div>');
$target = $doc->find(':target');
echo count($target); // 输出: 1
```


---

### 3. 焦点元素 `:focus`

| 伪类 | 类型 | 说明 | 使用示例 |
|------|------|------|----------|
| `:focus` | 用户交互 | 匹配获得焦点的元素 | `$doc->find(':focus')` |

**注意事项：**
- 这个伪类主要用于 CSS，在 DOM 解析中可能无法准确判断


---

### 4. 悬停元素 `:hover`

| 伪类 | 类型 | 说明 | 使用示例 |
|------|------|------|----------|
| `:hover` | 用户交互 | 匹配鼠标悬停的元素 | `$doc->find(':hover')` |

**注意事项：**
- 这个伪类主要用于 CSS，在 DOM 解析中可能无法准确判断


---

### 5. 激活元素 `:active`

| 伪类 | 类型 | 说明 | 使用示例 |
|------|------|------|----------|
| `:active` | 用户交互 | 匹配被激活的元素 | `$doc->find(':active')` |

**注意事项：**
- 这个伪类主要用于 CSS，在 DOM 解析中可能无法准确判断


---

## 选择器组合技巧

### 1. 全路径组合选择

**场景：需要精确定位深层嵌套的元素**

```php
// 方式1：使用全XPath路径
$element = $doc->xpath('/html/body/div[3]/div[1]/div/div[1]/span[@class="title"]');

// 方式2：使用 CSS 组合选择器
$element = $doc->find('div.container > div.content > div.item > span.title');

// 方式3：使用 findByPath 方法
$element = $doc->findByPath('div.container > div.content > div.item > span.title');

// 方式4：混合使用属性和标签
$element = $doc->xpath('//div[@class="container"]/div[@class="content"]/span[@class="title"]');
```

**选择建议：**
- 简单场景：使用 CSS 选择器（更易读）
- 复杂条件：使用 XPath（更强大）
- 需要精确定位：使用全路径（更高效）
- 不确定结构：使用相对路径（更灵活）

### 2. 嵌套选择器

```php
$doc = new Document('<div class="container"><div class="item"><span>文本</span></div></div>');
$span = $doc->find('.container .item span');
echo $span[0]->text(); // 输出: 文本
```

### 2. 多条件选择器

```php
$doc = new Document('<div class="item active" data-id="123">内容</div>');
$element = $doc->find('div.item.active[data-id="123"]');
echo count($element); // 输出: 1
```

### 3. 使用伪类过滤

```php
$doc = new Document('<ul><li class="active">1</li><li>2</li><li class="active">3</li></ul>');
$activeLis = $doc->find('li.active:not(:last-child)');
echo count($activeLis); // 输出: 1（不包括最后一个）
```


---

## 性能优化建议

### 1. 使用更具体的选择器

选择越具体，查询越高效：

```php
// ✅ 推荐：使用类名和标签组合
$elements = $doc->find('div.container > p.highlight');

// ❌ 避免：过于宽泛
$elements = $doc->find('div p');
```

### 2. 使用全路径选择器

对于结构稳定的文档，全路径选择器性能最优：

```php
// ✅ 推荐：使用完整路径直接定位
$element = $doc->xpath('/html/body/div[3]/div[1]/div/div[1]/span');

// ⚠️ 中等：使用相对路径
$element = $doc->xpath('//div[@class="container"]/div[@class="content"]/span');

// ❌ 避免：过于模糊的搜索
$element = $doc->find('div span');
```

### 3. 避免过度使用通配符

通配符会匹配大量元素，降低性能：

```php
// ✅ 推荐：指定具体标签
$elements = $doc->find('div.item');

// ❌ 避免：使用通配符
$elements = $doc->find('.container *');
```

### 4. 使用 ID 选择器

ID 选择器性能最好（在 HTML 中唯一）：

```php
// ✅ 推荐：使用 ID
$element = $doc->find('#main-content');

// ❌ 避免：使用属性选择器
$element = $doc->find('div[class="main-content"]');
```

### 5. 利用选择器编译缓存

```php
// Query 类会自动缓存编译结果
// 第一次调用会编译并缓存
$elements1 = $doc->find('div.container > .item');

// 第二次调用直接使用缓存
$elements2 = $doc->find('div.container > .item');

// 手动管理缓存
Query::clearCompiled(); // 清空缓存
$compiled = Query::getCompiled(); // 获取缓存
```

### 6. 优先使用 XPath 函数而非 CSS 伪类

对于复杂条件，XPath 函数更高效：

```php
// ✅ 推荐：使用 XPath 函数
$elements = $doc->xpath('//div[contains(@class, "item") and @data-id > 100]');

// ⚠️ 可用：使用 CSS 伪类（性能稍差）
$elements = $doc->find('div.item[data-id]');
// 然后在 PHP 中过滤
$filtered = array_filter($elements, fn($el) => $el->getAttribute('data-id') > 100);
```

### 7. 减少嵌套层级

```php
// ✅ 推荐：直接定位
$element = $doc->find('div.target-item');

// ❌ 避免：过度嵌套
$element = $doc->find('body > div.container > div.content > div.main > div.target-item');
```

### 8. 使用上下文节点

在已知区域内搜索，减少搜索范围：

```php
// ✅ 推荐：在指定上下文中搜索
$container = $doc->first('div.container');
$elements = $doc->find('.item', Query::TYPE_CSS, $container->getNode());

// ❌ 避免：在整个文档中搜索
$elements = $doc->find('div.container .item');
```


---

## 常见问题

### Q1: 为什么 `:nth-child(1)` 和 `:first` 不同？

**A:** `:nth-child(1)` 是基于父元素的子元素位置，而 `:first` 是基于查询结果集。

### Q2: `:contains` 和 `:contains-text` 有什么区别？

**A:** `:contains` 搜索所有子元素的文本，而 `:contains-text` 只搜索元素的直接文本内容。

### Q3: XPath 和 CSS 选择器哪个更好？

**A:** 根据场景选择：
- **CSS 选择器**：更简洁易读，适合大多数日常场景
- **XPath 选择器**：功能更强大，适合复杂查询、文本处理、数值计算等需求
- **全路径选择器**：在需要精确定位时，使用完整路径可以提高性能

**示例对比：**
```php
// CSS 选择器（简洁）
$elements = $doc->find('div.container > div.item.active');

// XPath 选择器（强大）
$elements = $doc->xpath('//div[contains(@class, "container")]/div[contains(@class, "item") and contains(@class, "active")]');

// 全路径（精确）
$elements = $doc->xpath('/html/body/div[1]/div[3]/div[@class="item"][@class="active"]');
```

### Q4: 如何处理特殊字符？

**A:** 使用转义或引号包裹：
```php
$doc->find('div[data-value="with\\"quote"]');
```


---

## 总结

zxf/utils Dom 支持以下选择器（150+ 种）：

### CSS 选择器系列（130+ 种）

- **CSS 基础选择器**: 9 种（通配符、标签、类、ID、多选、后代、子代、相邻兄弟、通用兄弟）
- **CSS 属性选择器**: 8 种（存在、等于、不等于、包含单词、等于或前缀、以...开头、以...结尾、包含）
- **CSS 组合选择器**: 5 种（已包含在基础选择器中）
- **CSS 结构伪类**: 10 种（first/last/only/nth-child, first/last/only/nth-of-type）
- **CSS 内容伪类**: 9 种（empty, contains, contains-text, starts-with, ends-with, has, not, blank, parent-only-text）
- **CSS 表单伪类**: 8 种（enabled, disabled, checked, selected, required, optional, read-only, read-write）
- **CSS 表单元素类型**: 24 种（text, password, checkbox, radio, file, email, url, number, tel, search, date, time, datetime, datetime-local, month, week, color, range, submit, reset, image, button, input）
- **CSS HTML元素伪类**: 19 种（header, input, button, link, visited, image, video, audio, canvas, svg, script, style, meta, link, base, head, body, title, table）
- **HTML5 结构元素伪类**: 24 种（tr, td, th, thead, tbody, tfoot, ul, ol, li, dl, dt, dd, form, label, fieldset, legend, section, article, aside, nav, main, footer, figure, figcaption, details, summary, dialog, menu）
- **CSS 位置伪类**: 10 种（first, last, even, odd, eq, gt, lt, between, slice, parent）
- **CSS 可见性伪类**: 2 种（visible, hidden）
- **CSS 状态伪类**: 8 种（root, target, focus, focus-within, focus-visible, hover, active, target-within）
- **CSS 链接状态伪类**: 4 种（any-link, link, local-link, visited）
- **CSS 语言和方向伪类**: 4 种（lang, dir-ltr, dir-rtl, dir-auto）
- **CSS 深度伪类**: 6 种（depth-0/1/2/3/4/5, depth-between）
- **CSS 文本相关伪类**: 8 种（text-node, comment-node, cdata, whitespace, non-whitespace, text-length-gt/lt/eq/between）
- **CSS 子元素数量伪类**: 3 种（children-gt/lt/eq）
- **CSS 属性匹配扩展伪类**: 3 种（has-attr, data, attr-match）
- **CSS 属性值长度伪类**: 3 种（attr-length-gt/lt/eq）
- **CSS 属性数量伪类**: 3 种（attr-count-gt/lt/eq）
- **CSS 表单验证伪类**: 9 种（in-range, out-of-range, indeterminate, placeholder-shown, default, valid, invalid, user-invalid, user-valid, autofill）

### XPath 选择器（完整 XPath 1.0 支持）

- **路径表达式**: 绝对路径（/）、相对路径（//）、父节点（..）
- **XPath 轴**: ancestor, ancestor-or-self, child, descendant, following-sibling, preceding-sibling
- **XPath 函数**:
  - 字符串函数：contains, starts-with, ends-with, substring, string-length, concat, translate, normalize-space
  - 数值函数：position, last, count, sum, number, floor, ceiling, round
  - 布尔函数：true, false, not, and, or
- **节点测试**: node(), text(), comment(), element(), processing-instruction()

### 全路径选择器

- **XPath 绝对路径**: /html/body/div[1]/div[2]/p[3]
- **XPath 相对路径**: //div[@class="container"]/div[@class="content"]/p
- **CSS 全路径**: div.container > div.content > p.text
- **混合路径**: 自动识别和转换 CSS/XPath 路径

### 正则表达式选择器

- **文本匹配**: 匹配元素的文本内容
- **属性匹配**: 匹配元素的属性值
- **复杂模式**: 支持所有 PCRE 正则表达式

### 伪元素（2 种）

- **::text**: 获取元素的文本内容
- **::attr(name)**: 获取元素的属性值

### 便捷方法

- **XPath 方法**: xpath(), xpathFirst(), xpathTexts(), xpathAttrs()
- **正则方法**: regex(), regexFind(), regexFirst()
- **查找方法**: findByPath(), findByText(), findByAttribute(), findByIndex(), findLast(), findRange()
- **文本方法**: directText(), allTextNodes()

### 核心特性

1. **全路径选择器支持**: 支持 CSS 和 XPath 的全路径语法，实现精确定位
2. **XPath 1.0 完整支持**: 包括所有标准函数、轴和节点测试
3. **正则表达式选择器**: 使用正则表达式匹配文本和属性
4. **选择器编译缓存**: 自动缓存编译结果，提升性能
5. **混合路径选择**: 支持 CSS 和 XPath 的混合使用
6. **150+ 种选择器**: 覆盖 CSS3、XPath 和自定义选择器
7. **强大的便捷方法**: 提供多种简化的查询接口
8. **location参数支持**: 支持使用location参数提取正则表达式多个分组并返回关联数组，实现灵活的数据提取

---

## findWithFallback 高级用法

### location 参数详解

`location` 参数是 `findWithFallback` 方法中正则表达式选择器的一个强大功能，它允许您指定提取正则表达式匹配结果中的特定分组，并将结果以关联数组的形式返回。

#### 参数格式

```php
'location' => [
    '字段名1' => [
        'index' => 分组索引,         // 正则表达式分组索引（从0开始）
        'description' => '字段描述'  // 可选，用于说明该字段的含义
    ],
    '字段名2' => [
        'index' => 分组索引,
        'description' => '字段描述'
    ]
]
```

---

## 🔍 正则表达式增强功能

正则表达式选择器提供了强大的数据提取和处理能力。详细说明请参阅 [正则表达式增强功能文档](REGEX_ENHANCED.md)。

### 核心方法

| 方法 | 功能 | 使用示例 |
|------|------|----------|
| `regex()` | 查找匹配的元素 | `$doc->regex('/\d+/')` |
| `regexMatch()` | 提取所有匹配文本 | `$doc->regexMatch('/(\d{4})-(\d{2})-(\d{2})/')` |
| `regexMatchWithElement()` | 提取匹配及元素信息 | `$doc->regexMatchWithElement('/\d+/')` |
| `regexMulti()` | 多列数据同时匹配 | `$doc->regexMulti(['dates' => '/.../', 'emails' => '/.../'])` |
| `regexReplace()` | 正则替换文本 | `$doc->regexReplace('/\s+/', ' ')` |
| `extractTable()` | 提取表格数据（CSS/XPath/正则/Element） | `$doc->extractTable()` |
| `extractList()` | 提取列表数据 | `$doc->extractList()` |
| `extractFormData()` | 提取表单数据 | `$doc->extractFormData()` |
| `extractLinks()` | 提取链接数据 | `$doc->extractLinks()` |
| `extractImages()` | 提取图片数据 | `$doc->extractImages()` |

### 主要特性

- ✅ **多数据匹配**：使用 `preg_match_all` 提取所有匹配项
- ✅ **分组捕获**：支持正则表达式分组提取多个相关数据
- ✅ **多列数据**：同时使用多个正则表达式匹配不同类型的数据
- ✅ **元素追踪**：返回匹配数据及其所在的元素信息
- ✅ **文本替换**：支持使用正则表达式批量替换文本内容
- ✅ **findWithFallback 增强**：支持直接提取文本、属性值和匹配数据
- ✅ **HTML 表格提取**：自动处理空白字符，提取结构化表格数据
- ✅ **多种数据提取**：支持提取列表、表单、链接、图片等数据

### 快速示例

```php
// 提取所有日期
$dates = $doc->regexMatch('/\d{4}-\d{2}-\d{2}/');

// 提取分组数据（姓名和年龄）
$people = $doc->regexMatch('/(\w+)\s*[:：]\s*(\d+)/');
// 返回: [['张三', '30'], ['李四', '25'], ...]

// 同时提取多种数据
$data = $doc->regexMulti([
    'dates' => '/\d{4}-\d{2}-\d{2}/',
    'emails' => '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
    'phones' => '/\d{3}-\d{4}-\d{4}/'
]);

// 正则替换
$doc->regexReplace('/(\d{4})-(\d{2})-(\d{2})/', '$1年$2月$3日');

// 提取表格数据
$tableData = $doc->extractTable();
// 返回: [['姓名' => '张三', '年龄' => '30'], ...]

// 提取列表数据
$listData = $doc->extractList('ul.products');
// 返回: ['产品1', '产品2', '产品3']

// 提取表单数据
$formData = $doc->extractFormData('form');
// 返回: ['username' => 'john', 'password' => '***']

// findWithFallback 增强用法
$dates = $doc->findWithFallback([
    ['selector' => '/\d{4}-\d{2}-\d{2}/', 'type' => 'regex', 'extractMode' => 'text']
]);
// 返回: ['2026-01-01', '2026-01-02', ...]
```

---

## 📋 Document 和 Element 类完整方法枚举

### 🎯 Document 类方法总览（100+ 方法）

#### 1. 构造和静态创建方法

| 方法名 | 类型 | 参数 | 返回值 | 示例 |
|--------|------|------|--------|------|
| `__construct` | 构造函数 | `$string`, `$isFile`, `$encoding`, `$type` | self | `new Document('<div>content</div>')` |
| `create` | 静态方法 | `$string`, `$isFile`, `$encoding`, `$type` | self | `Document::create('file.html', true)` |
| `getFromDomDocument` | 静态方法 | `$domDocument` | self\|null | `Document::getFromDomDocument($domDoc)` |

#### 2. 文档加载和保存方法

| 方法名 | 类型 | 参数 | 返回值 | 示例 |
|--------|------|------|--------|------|
| `load` | 加载 | `$string`, `$isFile`, `$type` | self | `$doc->load('file.html', true)` |
| `save` | 保存 | `$filename` | self | `$doc->save('output.html')` |
| `toString` | 获取内容 | 无 | string | `$doc->toString()` |
| `loadHtml` | 加载HTML | `$html` | self | `$doc->loadHtml('<div>content</div>')` |
| `loadXml` | 加载XML | `$xml` | self | `$doc->loadXml('<?xml version="1.0"?>')` |
| `isRemoteUrl` | 检测远程URL | `$url` | bool | `$doc->isRemoteUrl('https://example.com')` |

#### 3. 基础查找方法

| 方法名 | 类型 | 参数 | 返回值 | 示例 |
|--------|------|------|--------|------|
| `find` | 查找 | `$expression`, `$type`, `$contextNode` | array | `$doc->find('.item')` |
| `first` | 查找第一个 | `$expression`, `$type`, `$contextNode` | Element\|null | `$doc->first('.item')` |
| `has` | 检查存在 | `$expression`, `$type` | bool | `$doc->has('.item')` |
| `count` | 计数 | `$expression`, `$type` | int | `$doc->count('.item')` |
| `findLast` | 查找最后一个 | `$selector`, `$type` | Element\|null | `$doc->findLast('li')` |
| `findByIndex` | 按索引查找 | `$selector`, `$index`, `$contextNode` | Element\|null | `$doc->findByIndex('div', 2)` |
| `findByRange` | 按范围查找 | `$selector`, `$start`, `$end`, `$contextNode` | array | `$doc->findByRange('li', 0, 5)` |
| `findByPath` | 按路径查找 | `$path`, `$relative` | array | `$doc->findByPath('/html/body/div')` |
| `findWithFallback` | 多选择器回退查找 | `$selectors`, `$contextNode`, `$getFirst` | array | `$doc->findWithFallback([...])` |
| `findFirstWithFallback` | 多选择器回退找第一个 | `$selectors`, `$contextNode` | Element\|null | `$doc->findFirstWithFallback([...])` |
| `queryWithFallback` | 多选择器查询列表 | `$selectors`, `$contextNode` | array | `$doc->queryWithFallback([...])` |

#### 4. XPath 相关方法

| 方法名 | 类型 | 参数 | 返回值 | 示例 |
|--------|------|------|--------|------|
| `xpath` | XPath查询 | `$xpathExpression` | array | `$doc->xpath('//div[@class="item"]')` |
| `xpathFirst` | XPath查询第一个 | `$xpathExpression` | Element\|null | `$doc->xpathFirst('//div[@class="item"]')` |
| `xpathTexts` | XPath获取文本 | `$xpathExpression` | array | `$doc->xpathTexts('//div[@class="item"]/text()')` |
| `xpathAttrs` | XPath获取属性 | `$xpathExpression`, `$attributeName` | array | `$doc->xpathAttrs('//a', 'href')` |

#### 5. 正则表达式方法

| 方法名 | 类型 | 参数 | 返回值 | 示例 |
|--------|------|------|--------|------|
| `regex` | 正则查找 | `$pattern`, `$contextNode`, `$attribute` | array | `$doc->regex('/\d{4}-\d{2}-\d{2}/')` |
| `regexFind` | 正则查找(别名) | `$pattern`, `$attribute` | array | `$doc->regexFind('/\d+/')` |
| `regexFirst` | 正则查找第一个 | `$pattern`, `$attribute` | Element\|null | `$doc->regexFirst('/\d+/')` |
| `regexMatch` | 正则提取文本 | `$pattern`, `$contextNode`, `$attribute` | array | `$doc->regexMatch('/\d{4}-\d{2}-\d{2}/')` |
| `regexMatchWithElement` | 正则提取(带元素) | `$pattern`, `$contextNode`, `$attribute` | array | `$doc->regexMatchWithElement('/\d+/')` |
| `regexMulti` | 多正则提取 | `$patterns`, `$contextNode`, `$attribute` | array | `$doc->regexMulti(['date' => '/\d{4}-\d{2}-\d{2}/'])` |
| `regexReplace` | 正则替换 | `$pattern`, `$replacement`, `$contextNode`, `$attribute` | self | `$doc->regexReplace('/\s+/', ' ')` |
| `regexExtractTable` | 正则提取表格 | `$pattern`, `$options` | array | `$doc->regexExtractTable('/<table[^>]*>.*?<\/table>/is')` |

#### 6. 数据提取方法

| 方法名 | 类型 | 参数 | 返回值 | 示例 |
|--------|------|------|--------|------|
| `text` | 提取文本 | `$expression`, `$type` | string\|null | `$doc->text('.item::text')` |
| `html` | 提取HTML | `$expression`, `$type` | string\|null | `$doc->html('.item')` |
| `xml` | 获取XML | 无 | string\|false | `$doc->xml()` |
| `texts` | 提取多个文本 | `$expression`, `$type` | array | `$doc->texts('.item')` |
| `allTexts` | 提取所有文本 | `$selector`, `$type`, `$trim`, `$unique` | array | `$doc->allTexts('.item', true, true, true)` |
| `allTextNodes` | 提取所有文本节点 | `$selector`, `$type`, `$trim` | array | `$doc->allTextNodes('.content')` |
| `directText` | 提取直接文本 | `$selector`, `$type` | array | `$doc->directText('.content')` |
| `attrs` | 提取属性 | `$expression`, `$attrName`, `$type` | array | `$doc->attrs('.item', 'href')` |
| `extractAttributes` | 批量提取属性 | `$selector`, `$attribute`, `$type` | array | `$doc->extractAttributes('a', 'href')` |
| `extractTexts` | 批量提取文本 | `$selector`, `$type`, `$trim` | array | `$doc->extractTexts('div.item')` |

#### 7. 文本查找方法

| 方法名 | 类型 | 参数 | 返回值 | 示例 |
|--------|------|------|--------|------|
| `findByText` | 按文本查找 | `$text`, `$selector` | array | `$doc->findByText('hello', 'div')` |
| `findByTextIgnoreCase` | 按文本查找(忽略大小写) | `$text`, `$selector` | array | `$doc->findByTextIgnoreCase('Hello')` |
| `findFirstByText` | 按文本找第一个 | `$text`, `$selector` | Element\|null | `$doc->findFirstByText('hello')` |
| `findFirstByTextIgnoreCase` | 按文本找第一个(忽略大小写) | `$text`, `$selector` | Element\|null | `$doc->findFirstByTextIgnoreCase('Hello')` |
| `findByHtml` | 按HTML查找 | `$html`, `$selector` | array | `$doc->findByHtml('<span class="highlight">')` |
| `findFirstByHtml` | 按HTML找第一个 | `$html`, `$selector` | Element\|null | `$doc->findFirstByHtml('<span>')` |

#### 8. 属性查找方法

| 方法名 | 类型 | 参数 | 返回值 | 示例 |
|--------|------|------|--------|------|
| `findByAttribute` | 按属性查找 | `$attribute`, `$value`, `$selector` | array | `$doc->findByAttribute('data-id', '123')` |
| `findByAttributeContains` | 按属性包含查找 | `$attribute`, `$value`, `$selector` | array | `$doc->findByAttributeContains('class', 'active')` |
| `findByAttributeStartsWith` | 按属性开头查找 | `$attribute`, `$prefix`, `$selector` | array | `$doc->findByAttributeStartsWith('href', 'https')` |
| `findByAttributeEndsWith` | 按属性结尾查找 | `$attribute`, `$suffix`, `$selector` | array | `$doc->findByAttributeEndsWith('href', '.pdf')` |
| `findFirstByAttribute` | 按属性找第一个 | `$attribute`, `$value`, `$selector` | Element\|null | `$doc->findFirstByAttribute('id', 'main')` |
| `findFirstByAttributeContains` | 按属性包含找第一个 | `$attribute`, `$value`, `$selector` | Element\|null | `$doc->findFirstByAttributeContains('class', 'active')` |
| `findFirstByAttributeStartsWith` | 按属性开头找第一个 | `$attribute`, `$prefix`, `$selector` | Element\|null | `$doc->findFirstByAttributeStartsWith('href', 'https')` |
| `findFirstByAttributeEndsWith` | 按属性结尾找第一个 | `$attribute`, `$suffix`, `$selector` | Element\|null | `$doc->findFirstByAttributeEndsWith('href', '.pdf')` |
| `findByData` | 按data属性查找 | `$dataName`, `$value`, `$selector` | array | `$doc->findByData('id', '123')` |
| `findByDataContains` | 按data属性包含查找 | `$dataName`, `$value`, `$selector` | array | `$doc->findByDataContains('category', 'news')` |
| `findByDataStartsWith` | 按data属性开头查找 | `$dataName`, `$prefix`, `$selector` | array | `$doc->findByDataStartsWith('id', 'user-')` |
| `findByDataEndsWith` | 按data属性结尾查找 | `$dataName`, `$suffix`, `$selector` | array | `$doc->findByDataEndsWith('id', '-active')` |

#### 9. 表格处理方法

| 方法名 | 类型 | 参数 | 返回值 | 示例 |
|--------|------|------|--------|------|
| `extractTable` | 提取表格 | `$table`, `$options` | array | `$doc->extractTable('table.data-table')` |
| `extractAllTables` | 提取所有表格 | `$selector`, `$options` | array | `$doc->extractAllTables('table')` |
| `extractTableBySelector` | 按选择器提取表格 | `$selector`, `$options` | array | `$doc->extractTableBySelector('table')` |
| `extractTableByAttribute` | 按属性提取表格 | `$attr`, `$value`, `$options` | array | `$doc->extractTableByAttribute('class', 'data')` |
| `extractTableByClass` | 按类名提取表格 | `$className`, `$options` | array | `$doc->extractTableByClass('data-table')` |
| `extractTableById` | 按ID提取表格 | `$id`, `$options` | array | `$doc->extractTableById('myTable')` |
| `extractTableByText` | 按文本提取表格 | `$text`, `$options` | array | `$doc->extractTableByText('姓名')` |
| `extractTableByColumn` | 按列提取表格 | `$column`, `$options` | array | `$doc->extractTableByColumn('年龄')` |

#### 10. 列表处理方法

| 方法名 | 类型 | 参数 | 返回值 | 示例 |
|--------|------|------|--------|------|
| `extractList` | 提取列表 | `$list`, `$options` | array | `$doc->extractList('ul.products')` |
| `extractAllLists` | 提取所有列表 | `$selector`, `$options` | array | `$doc->extractAllLists('ul, ol')` |
| `extractDefinitionList` | 提取定义列表 | `$dl`, `$options` | array | `$doc->extractDefinitionList('dl')` |

#### 11. 表单和链接图片提取

| 方法名 | 类型 | 参数 | 返回值 | 示例 |
|--------|------|------|--------|------|
| `extractFormData` | 提取表单数据 | `$form` | array | `$doc->extractFormData('form#login')` |
| `links` | 获取所有链接 | 无 | array | `$doc->links()` |
| `extractLinks` | 提取链接数据 | `$selector` | array | `$doc->extractLinks('a.external')` |
| `images` | 获取所有图片 | 无 | array | `$doc->images()` |
| `extractImages` | 提取图片数据 | `$selector` | array | `$doc->extractImages('img.thumbnail')` |
| `forms` | 获取所有表单 | 无 | array | `$doc->forms()` |
| `inputs` | 获取所有输入元素 | 无 | array | `$doc->inputs()` |

#### 12. 元素操作方法

| 方法名 | 类型 | 参数 | 返回值 | 示例 |
|--------|------|------|--------|------|
| `setAttr` | 设置属性 | `$expression`, `$name`, `$value` | self | `$doc->setAttr('.item', 'id', '123')` |
| `set` | 设置(便捷方法) | `$expression`, `$name`, `$value`, `$type` | self | `$doc->set('.item', 'class', 'active')` |
| `attr` | 获取/设置属性 | `$expression`, `$name`, `$value`, `$type` | self\|string\|null | `$doc->attr('.item', 'href')` |
| `removeAttr` | 删除属性 | `$expression`, `$name` | self | `$doc->removeAttr('.item', 'class')` |
| `addClass` | 添加类名 | `$expression`, `...$classNames` | self | `$doc->addClass('.item', 'active')` |
| `removeClass` | 移除类名 | `$expression`, `...$classNames` | self | `$doc->removeClass('.item', 'active')` |
| `toggleClass` | 切换类名 | `$expression`, `$className` | self | `$doc->toggleClass('.item', 'active')` |
| `hasClass` | 检查类名 | `$expression`, `$className` | bool | `$doc->hasClass('.item', 'active')` |
| `setContent` | 设置内容 | `$expression`, `$content` | self | `$doc->setContent('.item', 'new content')` |

#### 13. 元素创建方法

| 方法名 | 类型 | 参数 | 返回值 | 示例 |
|--------|------|------|--------|------|
| `createElement` | 创建元素 | `$tagName`, `$content`, `$attributes` | Element | `$doc->createElement('div', 'content', ['class' => 'item'])` |
| `createElementBySelector` | 按选择器创建 | `$selector`, `$content`, `$attributes` | Element | `$doc->createElementBySelector('div.item#123')` |
| `createTextNode` | 创建文本节点 | `$text` | DOMText | `$doc->createTextNode('text')` |
| `createDocumentFragment` | 创建文档片段 | 无 | DocumentFragment | `$doc->createDocumentFragment()` |
| `createFragment` | 创建文档片段(别名) | 无 | DocumentFragment | `$doc->createFragment()` |

#### 14. 文档结构方法

| 方法名 | 类型 | 参数 | 返回值 | 示例 |
|--------|------|------|--------|------|
| `root` | 获取根元素 | 无 | Element\|null | `$doc->root()` |
| `head` | 获取head元素 | 无 | Element\|null | `$doc->head()` |
| `body` | 获取body元素 | 无 | Element\|null | `$doc->body()` |
| `title` | 获取标题文本 | 无 | string\|null | `$doc->title()` |
| `setTitle` | 设置标题 | `$title` | self | `$doc->setTitle('New Title')` |
| `getDocument` | 获取DOMDocument | 无 | DOMDocument | `$doc->getDocument()` |
| `getType` | 获取文档类型 | 无 | string | `$doc->getType()` |
| `getEncoding` | 获取文档编码 | 无 | string | `$doc->getEncoding()` |
| `extractMetaData` | 提取元数据 | `$name` | array\|string | `$doc->extractMetaData('description')` |

#### 15. 辅助和调试方法

| 方法名 | 类型 | 参数 | 返回值 | 示例 |
|--------|------|------|--------|------|
| `preserveWhiteSpace` | 设置保留空白 | `$preserve` | self | `$doc->preserveWhiteSpace(true)` |
| `format` | 格式化输出 | `$format` | self | `$doc->format(true)` |
| `clear` | 清空文档 | 无 | self | `$doc->clear()` |
| `wrapNode` | 包装节点 | `$node` | Element | `$doc->wrapNode($domNode)` |
| `wrapAndInsertBefore` | 包装并插入 | `$node`, `$referenceNode` | Element | `$doc->wrapAndInsertBefore($node, $ref)` |
| `wrapAndAppend` | 包装并追加 | `$node`, `$parentNode` | Element | `$doc->wrapAndAppend($node, $parent)` |
| `getDebugInfo` | 获取调试信息 | 无 | array | `$doc->getDebugInfo()` |
| `getStatistics` | 获取统计信息 | `$tagName` | array | `$doc->getStatistics()` |
| `isValid` | 验证文档 | 无 | bool | `$doc->isValid()` |

### 🎨 Element 类方法总览（80+ 方法）

#### 1. 构造和创建方法

| 方法名 | 类型 | 参数 | 返回值 | 示例 |
|--------|------|------|--------|------|
| `__construct` | 构造函数 | `$tagName`, `$value`, `$attributes` | - | `new Element('div', 'content', ['class' => 'item'])` |
| `create` | 静态创建 | `$name`, `$value`, `$attributes` | self | `Element::create('div', 'content')` |
| `createBySelector` | 按选择器创建 | `$selector`, `$value`, `$attributes` | self | `Element::createBySelector('div.item#123', 'content')` |

#### 2. 查找和选择方法

| 方法名 | 类型 | 参数 | 返回值 | 示例 |
|--------|------|------|--------|------|
| `find` | 查找后代 | `$selector`, `$type` | array | `$element->find('.item')` |
| `first` | 查找第一个 | `$selector`, `$type` | Element\|null | `$element->first('.item')` |
| `matches` | 匹配检查 | `$selector`, `$typeOrStrict` | bool | `$element->matches('.item')` |
| `findChildren` | 查找直接子元素 | `$selector` | array | `$element->findChildren('.item')` |
| `findFirstChild` | 查找第一个直接子元素 | `$selector` | Element\|null | `$element->findFirstChild('.item')` |

#### 3. XPath 和正则表达式方法

| 方法名 | 类型 | 参数 | 返回值 | 示例 |
|--------|------|------|--------|------|
| `xpath` | XPath查询 | `$xpathExpression` | array | `$element->xpath('.//div')` |
| `regex` | 正则查找 | `$pattern`, `$attribute` | array | `$element->regex('/\d+/')` |
| `regexMatch` | 正则提取文本 | `$pattern`, `$attribute` | array | `$element->regexMatch('/\d{4}-\d{2}-\d{2}/')` |
| `regexMatchWithElement` | 正则提取(带元素) | `$pattern`, `$attribute` | array | `$element->regexMatchWithElement('/\d+/')` |
| `regexMulti` | 多正则提取 | `$patterns`, `$attribute` | array | `$element->regexMulti(['date' => '/\d+/'])` |
| `regexReplace` | 正则替换 | `$pattern`, `$replacement`, `$attribute` | Element | `$element->regexReplace('/\s+/', ' ')` |

#### 4. 文本和属性查找方法

| 方法名 | 类型 | 参数 | 返回值 | 示例 |
|--------|------|------|--------|------|
| `findByText` | 按文本查找 | `$text`, `$selector` | array | `$element->findByText('hello', 'div')` |
| `findByTextIgnoreCase` | 按文本查找(忽略大小写) | `$text`, `$selector` | array | `$element->findByTextIgnoreCase('Hello')` |
| `findByAttribute` | 按属性查找 | `$attribute`, `$value`, `$selector` | array | `$element->findByAttribute('data-id', '123')` |

#### 5. 表格处理方法

| 方法名 | 类型 | 参数 | 返回值 | 示例 |
|--------|------|------|--------|------|
| `extractTable` | 提取子表格 | `$selector`, `$options` | array | `$element->extractTable('table')` |
| `extractTableData` | 提取当前表格数据 | `$options` | array | `$element->extractTableData()` |
| `extractTableRows` | 提取表格行 | `$options` | array | `$element->extractTableRows()` |
| `extractTableHeaders` | 提取表格表头 | `$options` | array | `$element->extractTableHeaders()` |
| `extractTableColumn` | 提取表格列 | `$column`, `$options` | array | `$element->extractTableColumn('姓名')` |
| `extractTableAsAssociative` | 提取关联格式表格 | `$options` | array | `$element->extractTableAsAssociative()` |
| `extractNestedTables` | 提取嵌套表格 | `$selector`, `$options` | array | `$element->extractNestedTables('table')` |

#### 6. 列表、表单和链接提取方法

| 方法名 | 类型 | 参数 | 返回值 | 示例 |
|--------|------|------|--------|------|
| `extractList` | 提取子列表 | `$selector`, `$options` | array | `$element->extractList('ul')` |
| `extractFormData` | 提取子表单数据 | `$selector` | array | `$element->extractFormData('form')` |
| `extractLinks` | 提取子链接 | `$selector` | array | `$element->extractLinks('a')` |
| `extractImages` | 提取子图片 | `$selector` | array | `$element->extractImages('img')` |
| `extractTexts` | 提取子元素文本 | `$selector`, `$trim` | array | `$element->extractTexts('div.item')` |

#### 7. 属性操作方法

| 方法名 | 类型 | 参数 | 返回值 | 示例 |
|--------|------|------|--------|------|
| `hasAttribute` | 检查属性存在 | `$name` | bool | `$element->hasAttribute('class')` |
| `getAttribute` | 获取属性 | `$name`, `$default` | string\|null | `$element->getAttribute('href')` |
| `setAttribute` | 设置属性 | `$name`, `$value` | Element | `$element->setAttribute('class', 'active')` |
| `removeAttribute` | 删除属性 | `$name` | self | `$element->removeAttribute('class')` |
| `removeAttr` | 删除属性(别名) | `$name` | self | `$element->removeAttr('class')` |
| `removeAllAttributes` | 删除所有属性 | `$preserved` | self | `$element->removeAllAttributes(['id'])` |
| `attributes` | 获取所有属性 | `$names` | array\|null | `$element->attributes()` |
| `attr` | 获取/设置属性 | `$name`, `$value` | Element\|string\|null | `$element->attr('href')` |
| `id` | 获取元素ID | 无 | string\|null | `$element->id()` |
| `setId` | 设置元素ID | `$id` | self | `$element->setId('myId')` |

#### 8. 类和样式操作方法

| 方法名 | 类型 | 参数 | 返回值 | 示例 |
|--------|------|------|--------|------|
| `classes` | 获取类管理对象 | 无 | ClassAttribute | `$element->classes()->add('active')` |
| `class` | 获取类管理对象(别名) | 无 | ClassAttribute | `$element->class()->add('active')` |
| `addClass` | 添加类名 | `...$classNames` | self | `$element->addClass('active', 'highlight')` |
| `removeClass` | 移除类名 | `...$classNames` | self | `$element->removeClass('active')` |
| `toggleClass` | 切换类名 | `$className` | self | `$element->toggleClass('active')` |
| `hasClass` | 检查类名 | `$className` | bool | `$element->hasClass('active')` |
| `style` | 获取样式管理对象 | 无 | StyleAttribute | `$element->style()->set('color', 'red')` |
| `css` | 获取/设置样式 | `$name`, `$value` | self\|string\|null | `$element->css('color', 'red')` |
| `removeStyle` | 移除样式 | `...$names` | StyleAttribute | `$element->removeStyle('color')` |
| `hasStyle` | 检查样式 | `$name` | bool | `$element->hasStyle('color')` |

#### 9. 节点关系方法

| 方法名 | 类型 | 参数 | 返回值 | 示例 |
|--------|------|------|--------|------|
| `tagName` | 获取标签名 | 无 | string | `$element->tagName()` |
| `children` | 获取子元素 | 无 | array | `$element->children()` |
| `parent` | 获取父元素 | 无 | Element\|null | `$element->parent()` |
| `ownerDocument` | 获取所属文档 | 无 | Document\|null | `$element->ownerDocument()` |
| `firstChild` | 获取第一个子元素 | 无 | Element\|null | `$element->firstChild()` |
| `lastChild` | 获取最后一个子元素 | 无 | Element\|null | `$element->lastChild()` |
| `nextSibling` | 获取下一个兄弟元素 | 无 | Element\|null | `$element->nextSibling()` |
| `previousSibling` | 获取前一个兄弟元素 | 无 | Element\|null | `$element->previousSibling()` |
| `siblings` | 获取所有兄弟元素 | 无 | array | `$element->siblings()` |

#### 10. 元素内容方法

| 方法名 | 类型 | 参数 | 返回值 | 示例 |
|--------|------|------|--------|------|
| `setHtml` | 获取/设置HTML | `$html` | self\|string | `$element->setHtml('<span>new</span>')` |

#### 11. 魔术方法

| 方法名 | 类型 | 参数 | 返回值 | 示例 |
|--------|------|------|--------|------|
| `__set` | 动态设置属性 | `$name`, `$value` | Element | `$element->title = 'New Title'` |
| `__get` | 动态获取属性 | `$name` | string\|null | `$title = $element->title` |
| `__isset` | 检查属性 | `$name` | bool | `isset($element->title)` |
| `__unset` | 删除属性 | `$name` | - | `unset($element->title)` |
- `findFirstChild()` - 查找第一个直接子元素
- `regex()` - 使用正则表达式查找后代元素
- `regexMatch()` - 使用正则表达式查找元素并提取匹配文本
- `regexMatchWithElement()` - 使用正则表达式查找元素并提取匹配文本（带元素信息）
- `regexMulti()` - 使用多个正则表达式同时查找元素
- `regexReplace()` - 使用正则表达式替换文本内容

#### 节点关系方法
- `parent()` - 获取父元素
- `firstChild()` - 获取第一个子元素
- `lastChild()` - 获取最后一个子元素
- `nextSibling()` - 获取下一个兄弟元素
- `previousSibling()` - 获取前一个兄弟元素
- `siblings()` - 获取所有兄弟元素

#### 类名和样式操作
- `classes()` - 获取类属性管理对象
- `class` - 获取类属性管理对象（别名方法）
- `style()` - 获取样式属性管理对象
- `css()` - 获取或设置样式（便捷方法）
- `removeStyle()` - 移除样式
- `hasStyle()` - 检查样式是否存在

#### 动态属性访问
- `__get()` - 动态获取属性
- `__set()` - 动态设置属性
- `__isset()` - 动态检查属性
- `__unset()` - 动态删除属性

### 🔧 属性管理类

#### ClassAttribute类
- `add()` - 添加类名
- `remove()` - 移除类名
- `toggle()` - 切换类名
- `contains()` - 检查类名是否存在
- `replace()` - 替换类名
- `clear()` - 清空类名
- `toArray()` - 转换为数组
- `__toString()` - 转换为字符串

#### StyleAttribute类
- `set()` - 设置样式
- `get()` - 获取样式值
- `remove()` - 移除样式
- `has()` - 检查样式是否存在
- `toArray()` - 转换为数组
- `__toString()` - 转换为字符串

### 📊 表格数据格式说明

`extractTable()` 方法返回的标准格式：

```php
[
    0 => [  // 第一个表格
        'thead' => [  // 表头数据（一维数组，包含表头单元格文本）
            0 => '姓名',
            1 => '性别',
            2 => '国籍',
            3 => '手机号'
        ],
        'tbody' => [  // 表体数据（二维数组，每行一个一维数组）
            0 => ['张三', '男', '中国', '183xxx'],
            1 => ['jick liu', '男', '英国', '163xxx'],
            2 => [...更多行...]
        ],
        'tfoot' => [...]  // 表尾数据（二维数组，如果有）
    ],
    1 => [...]  // 第二个表格
]
```

**关键特性**：
- `thead` 是一维数组，包含表头单元格文本（使用 `th` 选择器）
- `tbody` 是二维数组，每行是一个一维数组（使用 `td` 选择器）
- **数据分离**：thead 和 tbody 使用不同的选择器（th vs td），完全避免数据混杂
- 自动处理HTML空白字符（换行、制表符等）
- 支持CSS/XPath/正则三种选择器查找表格
- 支持提取所有表格、第一个表格或指定第n个表格
- **数据验证和规范化**：自动验证表格数据格式并规范化输出

### 📋 表格提取方法汇总

#### Document 类表格方法

| 方法 | 说明 | 参数 | 返回格式 |
|------|------|------|----------|
| `extractTable()` | 提取表格数据 | `$table, $options` | 结构化数组 |
| `extractTableBySelector()` | 通过选择器提取 | `$selector, $options` | 数组 |
| `extractTableByAttribute()` | 通过属性提取 | `$attr, $value, $options` | 数组 |
| `extractTableByClass()` | 通过类名提取 | `$className, $options` | 数组 |
| `extractTableById()` | 通过ID提取 | `$id, $options` | 单个数组 |
| `extractTableByText()` | 提取包含指定文本的表格 | `$text, $options` | 数组 |
| `extractTableByColumn()` | 提取包含指定列的表格 | `$column, $options` | 数组 |
| `extractAllTables()` | 批量提取所有表格 | `$selector, $options` | 二维数组 |

#### Element 类表格方法

| 方法 | 说明 | 参数 | 返回格式 |
|------|------|------|----------|
| `extractTable()` | 从子元素提取表格 | `$selector, $options` | 结构化数组 |
| `extractTableData()` | 当前元素作为表格提取 | `$options` | 结构化数组 |
| `extractTableRows()` | 提取表格行数据 | `$options` | 二维数组 |
| `extractTableHeaders()` | 提取表格表头 | `$options` | 一维数组 |
| `extractTableColumn()` | 提取指定列数据 | `$column, $options` | 一维数组 |
| `extractTableAsAssociative()` | 提取为关联数组格式 | `$options` | 关联数组 |
| `extractNestedTables()` | 提取嵌套表格 | `$selector, $options` | 二维数组 |

#### 表格提取选项

```php
$options = [
    'selectorType' => 'auto',      // 自动检测选择器类型：auto/css/xpath/regex
    'headerRow' => 0,              // 表头行索引
    'skipRows' => 0,               // 跳过的行数
    'includeHeader' => true,        // 是否包含表头
    'includeHeaderAsFirstRow' => false,  // 是否将表头作为第一行
    'trimText' => true,            // 是否修剪文本空白
    'removeEmpty' => true,          // 是否移除空行
    'cellSelector' => 'td, th',    // 单元格选择器（内部已优化为自动选择）
    'rowSelector' => 'tr',         // 行选择器
    'returnFormat' => 'structured', // 返回格式：structured/indexed/associative/both
    'preserveStructure' => true,     // 是否保留thead/tbody/tfoot结构
    'returnAllTables' => true,      // 是否返回所有表格
    'tableIndex' => null           // 指定表格索引，null表示返回所有
];
```
