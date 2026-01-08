# 选择器规则说明文档

本文档详细说明了 zxf/utils Dom 支持的所有 CSS 选择器、XPath 选择器和特殊选择器的使用方法。

## 目录

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
- [特殊选择器](#特殊选择器)

---


## 📊 所有支持的选择器总览（100+ 种）

| 选择器                    | 类型       | 参数说明                | 使用示例                                     | 描述                                                                                                                                    |
|------------------------|----------|---------------------|------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------|
| **基础选择器**              |
| `*`                    | 通配符      | 无                   | `$doc->find('*')`                        | 匹配文档中的所有元素。通常与其他选择器组合使用，如 `div *` 匹配 div 内的所有后代元素。注意：此选择器性能开销较大，谨慎使用。                                                                 |
| `tag`                  | 标签       | tag: 标签名            | `$doc->find('div')`                      | 根据标签名匹配所有指定的 HTML 元素。不区分大小写（HTML 模式下）。可以匹配任何有效的 HTML 标签，如 div、p、span、a 等。这是最基本和最常用的选择器之一。                                             |
| `.class`               | 类        | class: 类名           | `$doc->find('.item')`                    | 匹配所有具有指定 class 属性值的元素。一个元素可以有多个类，`.class` 会匹配所有包含该类的元素。可以链式使用多个类选择器（如 `.class1.class2`）来匹配同时拥有多个类的元素。                                 |
| `#id`                  | ID       | id: ID名             | `$doc->find('#main')`                    | 根据元素的 id 属性值匹配元素。在 HTML 文档中，id 应该是唯一的。如果有多个元素使用相同 ID，此选择器只返回第一个。ID 选择器具有很高的优先级和性能优势。                                                  |
| `s1, s2`               | 多选       | 多个选择器               | `$doc->find('div, p')`                   | 使用逗号分隔多个选择器，匹配任意一个选择器选中的元素。这允许您一次性选择多种不同类型的元素。选择器之间用逗号分隔，每个选择器可以独立工作。                                                                 |
| `s1 s2`                | 后代       | 父选择器 子选择器           | `$doc->find('div p')`                    | 匹配作为第一个选择器后代的第二个选择器的所有元素。后代关系不限层级，可以是直接子元素、孙元素、曾孙元素等。这是最常用的组合选择器之一。                                                                   |
| `s1 > s2`              | 子代       | 父选择器 > 子选择器         | `$doc->find('ul > li')`                  | 匹配作为第一个选择器直接子元素的第二个选择器的所有元素。只匹配直接子元素，不包括更深层级的后代。此选择器比后代选择器更精确，性能也更好。                                                                  |
| `s1 + s2`              | 相邻兄弟     | 前一个 + 后一个           | `$doc->find('h2 + p')`                   | 匹配紧接在第一个选择器之后的第二个选择器。两个元素必须拥有相同的父元素，并且第二个元素必须紧跟在第一个元素之后。只匹配一个元素。                                                                      |
| `s1 ~ s2`              | 通用兄弟     | 前一个 ~ 后面所有          | `$doc->find('h2 ~ p')`                   | 匹配第一个选择器之后的所有第二个选择器元素。两个元素必须拥有相同的父元素，但不要求相邻。会匹配所有满足条件的兄弟元素。                                                                           |
| **属性选择器**              |
| `[attr]`               | 存在       | attr: 属性名           | `$doc->find('[href]')`                   | 匹配所有具有指定属性的元素，无论属性值是什么。只要元素存在该属性就会被选中。这是属性选择器中最简单的一种，常用于检查元素是否有某个特性。                                                                  |
| `[attr=value]`         | 等于       | attr, value         | `$doc->find('[id="123"]')`               | 匹配属性值完全等于指定值的元素。比较是区分大小写的。值必须完全匹配，包括空格和特殊字符。常用于精确匹配特定的属性值。                                                                            |
| `[attr~=value]`        | 包含单词     | attr, value         | `$doc->find('[class~="active"]')`        | 匹配属性值包含指定单词的元素。属性值通常是由空格分隔的单词列表，此选择器会匹配包含其中某个单词的元素。不会匹配部分字符串（如 "item-active" 不会匹配 "active"）。                                          |
| `[attr\|=value]`       | 等于或前缀    | attr, value         | `$doc->find('[lang\|="en"]')`            | 匹配属性值等于指定值，或以指定值加连字符开头的元素。最常用于语言选择，例如 `lang="en"`、`lang="en-US"`、`lang="en-GB"` 都会被 `[lang\|="en"]` 匹配。                               |
| `[attr^=value]`        | 以...开头   | attr, value         | `$doc->find('[src^="https"]')`           | 匹配属性值以指定字符串开头的元素。比较是区分大小写的。常用于匹配具有特定前缀的 URL、类名或 ID。例如，`[href^="http"]` 匹配所有 HTTP 链接。                                                  |
| `[attr$=value]`        | 以...结尾   | attr, value         | `$doc->find('[src$=".jpg"]')`            | 匹配属性值以指定字符串结尾的元素。比较是区分大小写的。常用于匹配特定文件类型的 URL，如 `[src$=".png"]` 匹配所有 PNG 图片。                                                            |
| `[attr*=value]`        | 包含       | attr, value         | `$doc->find('[class*="nav"]')`           | 匹配属性值包含指定子字符串的元素。可以在属性值的任何位置匹配。例如，`[class*="nav"]` 可以匹配 `class="main-nav"`、`class="navbar"`、`class="navigation"` 等。                   |
| **结构伪类**               |
| `:first-child`         | 第一个子元素   | 无                   | `$doc->find('li:first-child')`           | 匹配作为其父元素第一个子元素的元素。与 `:first` 不同，`:first-child` 是基于 DOM 结构中父元素的子元素位置，不是基于查询结果集。每个父元素可以有多个 `:first-child`。                              |
| `:last-child`          | 最后一个子元素  | 无                   | `$doc->find('li:last-child')`            | 匹配作为其父元素最后一个子元素的元素。同样基于父元素的子元素位置，不是基于查询结果集。每个父元素可以有多个 `:last-child`。                                                                  |
| `:only-child`          | 唯一子元素    | 无                   | `$doc->find('div:only-child')`           | 匹配作为其父元素唯一子元素的元素。即该父元素只有一个子元素，并且就是当前元素。常用于判断元素是否是独生子元素。                                                                               |
| `:nth-child(n)`        | 第n个子元素   | n: 数字/odd/even/an+b | `$doc->find('li:nth-child(2)')`          | 匹配作为其父元素第 n 个子元素的元素。参数 n 可以是：1) 数字（如 2、3）表示具体位置；2) odd 表示奇数位置（1,3,5...）；3) even 表示偶数位置（2,4,6...）；4) an+b 公式（如 2n+1、3n+2）表示符合特定模式的子元素。 |
| `:nth-last-child(n)`   | 倒数第n个    | n: 数字/odd/even/an+b | `$doc->find('li:nth-last-child(2)')`     | 与 `:nth-child(n)` 类似，但从后往前计数。即匹配作为其父元素倒数第 n 个子元素的元素。参数格式与 `:nth-child` 相同。                                                            |
| `:first-of-type`       | 同类型第一个   | 无                   | `$doc->find('p:first-of-type')`          | 匹配作为其父元素第一个同类型子元素的元素。只考虑相同标签名的元素，忽略其他类型的元素。如果一个父元素有 p、span、div，那么 p、span、div 各自会有一个 first-of-type。                                    |
| `:last-of-type`        | 同类型最后一个  | 无                   | `$doc->find('p:last-of-type')`           | 匹配作为其父元素最后一个同类型子元素的元素。同样只考虑相同标签名的元素。常用于选择某种类型的最后一个元素。                                                                                 |
| `:only-of-type`        | 唯一同类型    | 无                   | `$doc->find('div:only-of-type')`         | 匹配作为其父元素中唯一同类型子元素的元素。即该父元素中只有一个该类型的元素。不同于 `:only-child`，`only-of-type` 允许有其他类型的兄弟元素。                                                  |
| `:nth-of-type(n)`      | 同类型第n个   | n: 数字/odd/even/an+b | `$doc->find('p:nth-of-type(2)')`         | 匹配作为其父元素第 n 个同类型子元素的元素。只计算同标签名的元素，忽略其他类型。参数格式与 `:nth-child` 相同。                                                                       |
| `:nth-last-of-type(n)` | 同类型倒数第n个 | n: 数字/odd/even/an+b | `$doc->find('p:nth-last-of-type(2)')`    | 与 `:nth-of-type(n)` 类似，但从后往前计数。匹配作为其父元素倒数第 n 个同类型子元素的元素。                                                                              |
| **内容伪类**               |
| `:empty`               | 空元素      | 无                   | `$doc->find('div:empty')`                | 匹配没有任何子元素和文本内容的元素。包括没有子元素、文本内容为空或仅包含空白字符的元素都不会被匹配。这是判断元素是否为空的精确方法。                                                                    |
| `:contains(text)`      | 包含文本     | text: 文本            | `$doc->find('div:contains(Hello)')`      | 匹配包含指定文本的元素。搜索范围包括元素自身的文本内容和所有子元素的文本内容。区分大小写。常用于查找包含特定关键词的元素。                                                                         |
| `:contains-text(text)` | 直接包含文本   | text: 文本            | `$doc->find('div:contains-text(Hello)')` | 匹配直接包含指定文本的元素。与 `:contains` 不同，此选择器只检查元素自身的文本内容，不包括子元素的文本。用于精确定位直接包含某文本的元素。                                                           |
| `:starts-with(text)`   | 以...开头   | text: 文本            | `$doc->find('div:starts-with(Hello)')`   | 匹配文本内容以指定字符串开头的元素。只检查元素自身的文本内容。常用于查找具有特定前缀的文本内容。区分大小写。                                                                                |
| `:ends-with(text)`     | 以...结尾   | text: 文本            | `$doc->find('div:ends-with(World)')`     | 匹配文本内容以指定字符串结尾的元素。只检查元素自身的文本内容。常用于查找具有特定后缀的文本内容。区分大小写。                                                                                |
| `:has(selector)`       | 包含后代     | selector: 选择器       | `$doc->find('div:has(a)')`               | 匹配包含匹配指定选择器后代元素的父元素。即如果某个元素的后代中（不限层级）有元素匹配给定的选择器，那么该父元素就会被选中。这是查找特定结构元素的强大工具。                                                         |
| `:not(selector)`       | 不匹配      | selector: 选择器       | `$doc->find('div:not(.active)')`         | 匹配不匹配指定选择器的元素。即反向选择，选择所有不满足给定条件的元素。可以用于排除特定类型的元素。注意：参数选择器不能过于复杂。                                                                      |
| `:blank`               | 空白       | 无                   | `$doc->find('p:blank')`                  | 匹配空白元素（无可见文本或子元素）。与 `:empty` 类似，但允许包含空白字符。元素如果没有任何可见内容或只有空白字符（如空格、换行、制表符），则会被匹配。                                                      |
| `:parent-only-text`    | 只有文本     | 无                   | `$doc->find('div:parent-only-text')`     | 匹配只有文本内容且没有子元素的元素。即元素必须包含非空白文本，但不能有任何子元素。常用于查找纯文本节点。                                                                                  |
| **表单伪类**               |
| `:enabled`             | 启用       | 无                   | `$doc->find(':enabled')`                 | 启用的表单元素                                                                                                                               |
| `:disabled`            | 禁用       | 无                   | `$doc->find(':disabled')`                | 禁用的表单元素                                                                                                                               |
| `:checked`             | 选中       | 无                   | `$doc->find(':checked')`                 | 选中的复选框/单选                                                                                                                             |
| `:selected`            | 选中的选项    | 无                   | `$doc->find('option:selected')`          | 选中的选项                                                                                                                                 |
| `:required`            | 必填       | 无                   | `$doc->find(':required')`                | 必填字段                                                                                                                                  |
| `:optional`            | 可选       | 无                   | `$doc->find(':optional')`                | 可选字段                                                                                                                                  |
| `:read-only`           | 只读       | 无                   | `$doc->find(':read-only')`               | 只读字段                                                                                                                                  |
| `:read-write`          | 可写       | 无                   | `$doc->find(':read-write')`              | 可写字段                                                                                                                                  |
| **表单元素类型**             |
| `:text`                | 文本输入     | 无                   | `$doc->find('input:text')`               | 文本输入框                                                                                                                                 |
| `:password`            | 密码       | 无                   | `$doc->find('input:password')`           | 密码框                                                                                                                                   |
| `:checkbox`            | 复选框      | 无                   | `$doc->find('input:checkbox')`           | 复选框                                                                                                                                   |
| `:radio`               | 单选按钮     | 无                   | `$doc->find('input:radio')`              | 单选按钮                                                                                                                                  |
| `:file`                | 文件       | 无                   | `$doc->find('input:file')`               | 文件上传                                                                                                                                  |
| `:email`               | 邮箱       | 无                   | `$doc->find('input:email')`              | 邮箱输入                                                                                                                                  |
| `:url`                 | URL      | 无                   | `$doc->find('input:url')`                | URL输入                                                                                                                                 |
| `:number`              | 数字       | 无                   | `$doc->find('input:number')`             | 数字输入                                                                                                                                  |
| `:tel`                 | 电话       | 无                   | `$doc->find('input:tel')`                | 电话输入                                                                                                                                  |
| `:search`              | 搜索       | 无                   | `$doc->find('input:search')`             | 搜索框                                                                                                                                   |
| `:date`                | 日期       | 无                   | `$doc->find('input:date')`               | 日期选择                                                                                                                                  |
| `:time`                | 时间       | 无                   | `$doc->find('input:time')`               | 时间选择                                                                                                                                  |
| `:datetime`            | 日期时间     | 无                   | `$doc->find('input:datetime')`           | 日期时间                                                                                                                                  |
| `:datetime-local`      | 本地日期时间   | 无                   | `$doc->find('input:datetime-local')`     | 本地日期时间                                                                                                                                |
| `:month`               | 月份       | 无                   | `$doc->find('input:month')`              | 月份选择                                                                                                                                  |
| `:week`                | 周        | 无                   | `$doc->find('input:week')`               | 周选择                                                                                                                                   |
| `:color`               | 颜色       | 无                   | `$doc->find('input:color')`              | 颜色选择器                                                                                                                                 |
| `:range`               | 范围       | 无                   | `$doc->find('input:range')`              | 范围滑块                                                                                                                                  |
| `:submit`              | 提交按钮     | 无                   | `$doc->find('input:submit')`             | 提交按钮                                                                                                                                  |
| `:reset`               | 重置按钮     | 无                   | `$doc->find('input:reset')`              | 重置按钮                                                                                                                                  |
| `:image`               | 图片按钮     | 无                   | `$doc->find('input:image')`              | 图片按钮                                                                                                                                  |
| **HTML元素伪类**           |
| `:header`              | 标题       | 无                   | `$doc->find(':header')`                  | h1-h6                                                                                                                                 |
| `:input`               | 表单输入     | 无                   | `$doc->find(':input')`                   | input/textarea/select/button                                                                                                          |
| `:button`              | 按钮       | 无                   | `$doc->find(':button')`                  | button/input button                                                                                                                   |
| `:link`                | 链接       | 无                   | `$doc->find('a:link')`                   | 有href的a标签                                                                                                                             |
| `:visited`             | 已访问链接    | 无                   | `$doc->find('a:visited')`                | a标签                                                                                                                                   |
| `:image`               | 图片       | 无                   | `$doc->find(':image')`                   | img标签                                                                                                                                 |
| `:video`               | 视频       | 无                   | `$doc->find(':video')`                   | video标签                                                                                                                               |
| `:audio`               | 音频       | 无                   | `$doc->find(':audio')`                   | audio标签                                                                                                                               |
| `:canvas`              | 画布       | 无                   | `$doc->find(':canvas')`                  | canvas标签                                                                                                                              |
| `:svg`                 | SVG      | 无                   | `$doc->find(':svg')`                     | svg标签                                                                                                                                 |
| `:script`              | 脚本       | 无                   | `$doc->find(':script')`                  | script标签                                                                                                                              |
| `:style`               | 样式       | 无                   | `$doc->find(':style')`                   | style标签                                                                                                                               |
| `:meta`                | 元信息      | 无                   | `$doc->find(':meta')`                    | meta标签                                                                                                                                |
| `:link`                | 链接       | 无                   | `$doc->find(':link')`                    | link标签                                                                                                                                |
| `:base`                | 基准URL    | 无                   | `$doc->find(':base')`                    | base标签                                                                                                                                |
| `:head`                | 头部       | 无                   | `$doc->find(':head')`                    | head标签                                                                                                                                |
| `:body`                | 主体       | 无                   | `$doc->find(':body')`                    | body标签                                                                                                                                |
| `:title`               | 标题       | 无                   | `$doc->find(':title')`                   | title标签                                                                                                                               |
| **HTML5结构元素**          |
| `:table`               | 表格       | 无                   | `$doc->find(':table')`                   | table标签                                                                                                                               |
| `:tr`                  | 表格行      | 无                   | `$doc->find(':tr')`                      | tr标签                                                                                                                                  |
| `:td`                  | 表格单元格    | 无                   | `$doc->find(':td')`                      | td标签                                                                                                                                  |
| `:th`                  | 表格头      | 无                   | `$doc->find(':th')`                      | th标签                                                                                                                                  |
| `:thead`               | 表格头      | 无                   | `$doc->find(':thead')`                   | thead标签                                                                                                                               |
| `:tbody`               | 表格主体     | 无                   | `$doc->find(':tbody')`                   | tbody标签                                                                                                                               |
| `:tfoot`               | 表格尾      | 无                   | `$doc->find(':tfoot')`                   | tfoot标签                                                                                                                               |
| `:ul`                  | 无序列表     | 无                   | `$doc->find(':ul')`                      | ul标签                                                                                                                                  |
| `:ol`                  | 有序列表     | 无                   | `$doc->find(':ol')`                      | ol标签                                                                                                                                  |
| `:li`                  | 列表项      | 无                   | `$doc->find(':li')`                      | li标签                                                                                                                                  |
| `:dl`                  | 定义列表     | 无                   | `$doc->find(':dl')`                      | dl标签                                                                                                                                  |
| `:dt`                  | 定义术语     | 无                   | `$doc->find(':dt')`                      | dt标签                                                                                                                                  |
| `:dd`                  | 定义描述     | 无                   | `$doc->find(':dd')`                      | dd标签                                                                                                                                  |
| `:form`                | 表单       | 无                   | `$doc->find(':form')`                    | form标签                                                                                                                                |
| `:label`               | 标签       | 无                   | `$doc->find(':label')`                   | label标签                                                                                                                               |
| `:fieldset`            | 字段集      | 无                   | `$doc->find(':fieldset')`                | fieldset标签                                                                                                                            |
| `:legend`              | 图例       | 无                   | `$doc->find(':legend')`                  | legend标签                                                                                                                              |
| `:section`             | 章节       | 无                   | `$doc->find(':section')`                 | section标签                                                                                                                             |
| `:article`             | 文章       | 无                   | `$doc->find(':article')`                 | article标签                                                                                                                             |
| `:aside`               | 侧边栏      | 无                   | `$doc->find(':aside')`                   | aside标签                                                                                                                               |
| `:nav`                 | 导航       | 无                   | `$doc->find(':nav')`                     | nav标签                                                                                                                                 |
| `:main`                | 主内容      | 无                   | `$doc->find(':main')`                    | main标签                                                                                                                                |
| `:footer`              | 页脚       | 无                   | `$doc->find(':footer')`                  | footer标签                                                                                                                              |
| `:figure`              | 图表       | 无                   | `$doc->find(':figure')`                  | figure标签                                                                                                                              |
| `:figcaption`          | 图表标题     | 无                   | `$doc->find(':figcaption')`              | figcaption标签                                                                                                                          |
| `:details`             | 详情       | 无                   | `$doc->find(':details')`                 | details标签                                                                                                                             |
| `:summary`             | 摘要       | 无                   | `$doc->find(':summary')`                 | summary标签                                                                                                                             |
| `:dialog`              | 对话框      | 无                   | `$doc->find(':dialog')`                  | dialog标签                                                                                                                              |
| `:menu`                | 菜单       | 无                   | `$doc->find(':menu')`                    | menu标签                                                                                                                                |
| **位置伪类**               |
| `:first`               | 第一个      | 无                   | `$doc->find('li:first')`                 | 结果集第一个                                                                                                                                |
| `:last`                | 最后一个     | 无                   | `$doc->find('li:last')`                  | 结果集最后一个                                                                                                                               |
| `:even`                | 偶数       | 无                   | `$doc->find('li:even')`                  | 偶数位置(1,3,5)                                                                                                                           |
| `:odd`                 | 奇数       | 无                   | `$doc->find('li:odd')`                   | 奇数位置(0,2,4)                                                                                                                           |
| `:eq(n)`               | 等于索引     | n: 索引               | `$doc->find('li:eq(2)')`                 | 索引等于n                                                                                                                                 |
| `:gt(n)`               | 大于索引     | n: 索引               | `$doc->find('li:gt(2)')`                 | 索引大于n                                                                                                                                 |
| `:lt(n)`               | 小于索引     | n: 索引               | `$doc->find('li:lt(3)')`                 | 索引小于n                                                                                                                                 |
| `:parent`              | 父元素      | 无                   | `$doc->find(':parent')`                | 有子元素的元素                                                                                                                              |
| `:between(start,end)`  | 索引范围     | start, end          | `$doc->find('li:between(2,5)')`          | 索引在2-5之间                                                                                                                              |
| `:slice(start:end)`    | 切片       | start:end           | `$doc->find('li:slice(1:3)')`            | 切片范围                                                                                                                                  |
| **可见性伪类**              |
| `:visible`             | 可见       | 无                   | `$doc->find(':visible')`                 | 可见元素                                                                                                                                  |
| `:hidden`              | 隐藏       | 无                   | `$doc->find(':hidden')`                  | 隐藏元素                                                                                                                                  |
| **状态伪类**               |
| `:root`                | 根元素      | 无                   | `$doc->find(':root')`                    | 文档根元素                                                                                                                                 |
| `:target`              | 目标       | 无                   | `$doc->find(':target')`                  | URL锚点目标                                                                                                                               |
| `:focus`               | 焦点       | 无                   | `$doc->find(':focus')`                   | 获得焦点                                                                                                                                  |
| `:hover`               | 悬停       | 无                   | `$doc->find(':hover')`                   | 鼠标悬停                                                                                                                                  |
| `:active`              | 激活       | 无                   | `$doc->find(':active')`                  | 被激活                                                                                                                                   |
| **语言和方向**              |
| `:lang(lang)`          | 语言       | lang: 语言代码          | `$doc->find(':lang(zh)')`                | 指定语言                                                                                                                                  |
| `:dir-ltr`             | 左到右      | 无                   | `$doc->find(':dir-ltr')`                 | 左到右方向                                                                                                                                 |
| `:dir-rtl`             | 右到左      | 无                   | `$doc->find(':dir-rtl')`                 | 右到左方向                                                                                                                                 |
| `:dir-auto`            | 自动       | 无                   | `$doc->find(':dir-auto')`                | 自动方向                                                                                                                                  |
| **深度伪类**               |
| `:depth-0`             | 根级别      | 无                   | `$doc->find(':depth-0')`                 | 无祖先元素                                                                                                                                 |
| `:depth-1`             | 深度1      | 无                   | `$doc->find(':depth-1')`                 | 1层祖先                                                                                                                                  |
| `:depth-2`             | 深度2      | 无                   | `$doc->find(':depth-2')`                 | 2层祖先                                                                                                                                  |
| `:depth-3`             | 深度3      | 无                   | `$doc->find(':depth-3')`                 | 3层祖先                                                                                                                                  |
| **文本相关伪类**             |
| `:text-node`           | 文本节点     | 无                   | `$doc->find(':text-node')`               | 文本节点                                                                                                                                  |
| `:comment-node`        | 注释节点     | 无                   | `$doc->find(':comment-node')`            | 注释节点                                                                                                                                  |
| `:whitespace`          | 空白文本     | 无                   | `$doc->find(':whitespace')`              | 空白文本节点                                                                                                                                |
| `:non-whitespace`      | 非空白文本    | 无                   | `$doc->find(':non-whitespace')`          | 非空白文本                                                                                                                                 |
| `:text-length-gt(n)`   | 文本长度大于   | n: 长度               | `$doc->find(':text-length-gt(10)')`      | 文本长度>n                                                                                                                                |
| `:text-length-lt(n)`   | 文本长度小于   | n: 长度               | `$doc->find(':text-length-lt(10)')`      | 文本长度<n                                                                                                                                |
| `:text-length-eq(n)`   | 文本长度等于   | n: 长度               | `$doc->find(':text-length-eq(10)')`      | 文本长度=n                                                                                                                                |
| `:text-length-between(start,end)` | 文本长度范围 | start, end | `$doc->find(':text-length-between(5,10)')` | 文本长度在5-10之间                                                                                                                        |
| **子元素数量伪类**            |
| `:children-gt(n)`      | 子元素大于    | n: 数量               | `$doc->find(':children-gt(3)')`          | 子元素数>n                                                                                                                                |
| `:children-lt(n)`      | 子元素小于    | n: 数量               | `$doc->find(':children-lt(3)')`          | 子元素数<n                                                                                                                                |
| `:children-eq(n)`      | 子元素等于    | n: 数量               | `$doc->find(':children-eq(3)')`          | 子元素数=n                                                                                                                                |
| **属性数量伪类**            |
| `:attr-count-gt(n)`   | 属性数大于    | n: 数量               | `$doc->find(':attr-count-gt(3)')`       | 属性数>n                                                                                                                                 |
| `:attr-count-lt(n)`   | 属性数小于    | n: 数量               | `$doc->find(':attr-count-lt(3)')`       | 属性数<n                                                                                                                                 |
| `:attr-count-eq(n)`   | 属性数等于    | n: 数量               | `$doc->find(':attr-count-eq(3)')`       | 属性数=n                                                                                                                                 |
| **属性值长度伪类**           |
| `:attr-length-gt(attr,n)` | 属性值长度大于 | attr: 属性名, n: 长度 | `$doc->find(':attr-length-gt(href,10)')` | 属性值长度>n                                                                                                                              |
| `:attr-length-lt(attr,n)` | 属性值长度小于 | attr: 属性名, n: 长度 | `$doc->find(':attr-length-lt(href,10)')` | 属性值长度<n                                                                                                                              |
| `:attr-length-eq(attr,n)` | 属性值长度等于 | attr: 属性名, n: 长度 | `$doc->find(':attr-length-eq(href,10)')` | 属性值长度=n                                                                                                                              |
| **节点类型伪类**             |
| `:element`            | 元素节点    | 无                   | `$doc->find(':element')`                | 元素节点                                                                                                                                  |
| `:cdata`              | CDATA节点   | 无                   | `$doc->find(':cdata')`                  | CDATA节点                                                                                                                                 |
| **深度范围伪类**             |
| `:depth-between(start,end)` | 深度范围    | start, end          | `$doc->find(':depth-between(1,3)')`       | 深度在1-3之间                                                                                                                              |
| **文本内容匹配伪类**         |
| `:text-match(pattern)` | 文本匹配    | pattern: 正则模式    | `$doc->find(':text-match(^test)')`       | 文本匹配正则表达式                                                                                                                          |
| **属性值匹配伪类**           |
| `:attr-match(attr,pattern)` | 属性值匹配    | attr: 属性名, pattern: 正则模式 | `$doc->find(':attr-match(href,^http)')` | 属性值匹配正则表达式                                                                                                                        |
| `:data(name)`          | data属性   | name: data名         | `$doc->find(':data(id)')`                | data-*属性                                                                                                                              |
| **表单验证伪类**             |
| `:in-range`            | 在范围内     | 无                   | `$doc->find(':in-range')`                | 值在min-max间                                                                                                                            |
| `:out-of-range`        | 超出范围     | 无                   | `$doc->find(':out-of-range')`            | 值超出范围                                                                                                                                 |
| `:indeterminate`       | 不确定      | 无                   | `$doc->find(':indeterminate')`           | 状态不确定                                                                                                                                 |
| `:placeholder-shown`   | 显示占位符    | 无                   | `$doc->find(':placeholder-shown')`       | 显示占位符                                                                                                                                 |
| `:default`             | 默认       | 无                   | `$doc->find(':default')`                 | 默认选项                                                                                                                                  |
| `:valid`               | 有效       | 无                   | `$doc->find(':valid')`                   | 验证通过                                                                                                                                  |
| `:invalid`             | 无效       | 无                   | `$doc->find(':invalid')`                 | 验证失败                                                                                                                                  |
| `:user-invalid`        | 用户验证失败   | 无                   | `$doc->find(':user-invalid')`            | 用户验证失败                                                                                                                                |
| `:user-valid`          | 用户验证通过   | 无                   | `$doc->find(':user-valid')`              | 用户验证通过                                                                                                                                |
| `:autofill`            | 自动填充     | 无                   | `$doc->find(':autofill')`                | 浏览器自动填充                                                                                                                               |
| **伪元素**                |
| `::text`               | 文本内容     | 无                   | `$doc->text('div::text')`                | 获取文本                                                                                                                                  |
| `::attr(name)`         | 属性值      | name: 属性名           | `$doc->text('a::attr(href)')`            | 获取属性                                                                                                                                  |

**总计**: 130+ 种选择器（包括基础选择器9种、属性选择器7种、结构伪类10种、内容伪类9种、文档状态伪类2种、表单伪类8种、表单元素类型24种、HTML元素伪类19种、HTML5结构元素24种、位置伪类11种、可见性伪类2种、用户交互伪类3种、语言方向伪类4种、深度伪类5种、文本相关伪类11种、内容匹配伪类3种、节点类型伪类4种、子元素数量伪类3种、属性数量伪类3种、属性匹配扩展5种、属性值长度伪类3种、表单验证伪类9种、表单验证扩展伪类2种、深度范围伪类1种、文本内容匹配伪类1种、属性值匹配伪类1种、伪元素2种）

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

| 选择器 | 类型 | 参数 | 说明 | 使用示例 |
|--------|------|------|------|----------|
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

| 选择器 | 类型 | 参数 | 说明 | 使用示例 |
|--------|------|------|------|----------|
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

| 选择器 | 类型 | 参数 | 说明 | 使用示例 |
|--------|------|------|------|----------|
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

| 选择器 | 类型 | 参数 | 说明 | 使用示例 |
|--------|------|------|------|----------|
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

| 选择器 | 类型 | 参数 | 说明 | 使用示例 |
|--------|------|------|------|----------|
| `[attr|=value]` | 等于或前缀 | attr: 属性名, value: 值或前缀 | 匹配属性值等于指定值或以指定值加连字符开头的元素 | `$doc->find('[lang|="en"]')` |

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

| 选择器 | 类型 | 参数 | 说明 | 使用示例 |
|--------|------|------|------|----------|
| `[attr^=value]` | 开头匹配 | attr: 属性名, value: 开头字符串 | 匹配属性值以指定字符串开头的元素 | `$doc->find('[href^="https"]')` |

**示例：**
```php
$doc = new Document('<a href="https://example.com">HTTPS</a><a href="http://example.com">HTTP</a>');
$httpsLinks = $doc->find('[href^="https"]');
echo count($httpsLinks); // 输出: 1
```

---

### 6. 属性值以...结尾 `[attr$=value]`

| 选择器 | 类型 | 参数 | 说明 | 使用示例 |
|--------|------|------|------|----------|
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

XPath 是一种强大的查询语言，可以精确地定位 XML/HTML 文档中的元素。

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

| 函数 | 说明 | 示例 |
|------|------|------|
| `contains(text, 'str')` | 包含文本 | `//div[contains(text(), "hello")]` |
| `starts-with(text, 'str')` | 以...开头 | `//div[starts-with(@class, "item")]` |
| `ends-with(text, 'str')` | 以...结尾 | `//div[ends-with(@id, "-item")]` |
| `position()` | 当前位置 | `//li[position()=1]` |
| `last()` | 最后一个 | `//li[last()]` |
| `count(node-set)` | 计数 | `//ul[count(li) > 2]` |

**示例：**
```php
$doc = new Document('<div class="item-1">1</div><div class="item-2">2</div>');
$items = $doc->xpath('//div[starts-with(@class, "item")]');
echo count($items); // 输出: 2
```

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

### 1. 根元素 `:root`

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

### 1. 嵌套选择器

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

1. **使用更具体的选择器**
   - ✅ 推荐：`div.container > p.highlight`
   - ❌ 避免：`div p`

2. **避免过度使用通配符**
   - ✅ 推荐：`div.item`
   - ❌ 避免：`.container *`

3. **使用 ID 选择器**
   - ✅ 推荐：`#main-content`
   - ❌ 避免：`div[class="main-content"]`

4. **利用缓存**
   - 查询结果可以缓存，避免重复查询
   - 使用 `Query` 的编译缓存

---

## 常见问题

### Q1: 为什么 `:nth-child(1)` 和 `:first` 不同？

**A:** `:nth-child(1)` 是基于父元素的子元素位置，而 `:first` 是基于查询结果集。

### Q2: `:contains` 和 `:contains-text` 有什么区别？

**A:** `:contains` 搜索所有子元素的文本，而 `:contains-text` 只搜索元素的直接文本内容。

### Q3: XPath 和 CSS 选择器哪个更好？

**A:** CSS 选择器更简洁易读，适合大多数情况；XPath 更强大，适合复杂的查询需求。

### Q4: 如何处理特殊字符？

**A:** 使用转义或引号包裹：
```php
$doc->find('div[data-value="with\\"quote"]');
```

---

## 总结

zxf/utils Dom 支持以下选择器（100+ 种）：

- **CSS 基础选择器**: 9 种（通配符、标签、类、ID、多选、后代、子代、相邻兄弟、通用兄弟）
- **CSS 属性选择器**: 7 种（存在、等于、包含单词、等于或前缀、以...开头、以...结尾、包含）
- **CSS 组合选择器**: 5 种（已包含在基础选择器中）
- **CSS 结构伪类**: 10 种（first/last/only/nth-child, first/last/only/nth-of-type）
- **CSS 内容伪类**: 9 种（empty, contains, contains-text, starts-with, ends-with, has, not, blank, parent-only-text）
- **CSS 表单伪类**: 8 种（enabled, disabled, checked, selected, required, optional, read-only, read-write）
- **CSS 表单元素类型**: 24 种（text, password, checkbox, radio, file, email, url, number, tel, search, date, time, datetime, datetime-local, month, week, color, range, submit, reset, image, button, input）
- **CSS HTML元素伪类**: 19 种（header, input, button, link, visited, image, video, audio, canvas, svg, script, style, meta, link, base, head, body, title, table）
- **HTML5 结构元素伪类**: 24 种（tr, td, th, thead, tbody, tfoot, ul, ol, li, dl, dt, dd, form, label, fieldset, legend, section, article, aside, nav, main, footer, figure, figcaption, details, summary, dialog, menu）
- **CSS 位置伪类**: 10 种（first, last, even, odd, eq, gt, lt, between, slice, parent）
- **CSS 可见性伪类**: 2 种（visible, hidden）
- **CSS 状态伪类**: 5 种（root, target, focus, hover, active）
- **CSS 语言和方向伪类**: 4 种（lang, dir-ltr, dir-rtl, dir-auto）
- **CSS 深度伪类**: 4 种（depth-0, depth-1, depth-2, depth-3）
- **CSS 文本相关伪类**: 7 种（text-node, comment-node, whitespace, non-whitespace, text-length-gt/lt/eq）
- **CSS 子元素数量伪类**: 3 种（children-gt/lt/eq）
- **CSS 属性匹配扩展伪类**: 2 种（has-attr, data）
- **CSS 表单验证伪类**: 9 种（in-range, out-of-range, indeterminate, placeholder-shown, default, valid, invalid, user-invalid, user-valid, autofill）
- **CSS 伪元素**: 2 种（::text, ::attr()）
- **XPath 选择器**: 完整 XPath 1.0 支持

**总计**: 95+ 种选择器和伪类

---

*文档版本: 2.0*  
*最后更新: 2026-01-07*  
*支持的选择器数量: 100+*
