<?php

/**
 * zxf/utils Dom 使用示例集合
 *
 * 本文件包含各种实际使用场景的示例代码
 * 展示了库的主要功能和最佳实践
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../Document.php';
require_once __DIR__ . '/../Node.php';
require_once __DIR__ . '/../Element.php';
require_once __DIR__ . '/../Selectors/Query.php';
require_once __DIR__ . '/../Attributes/ClassAttribute.php';
require_once __DIR__ . '/../Attributes/StyleAttribute.php';
require_once __DIR__ . '/../Fragments/DocumentFragment.php';
require_once __DIR__ . '/../Utils/Encoder.php';
require_once __DIR__ . '/../Utils/Errors.php';
require_once __DIR__ . '/../Exceptions/InvalidSelectorException.php';

use zxf\Utils\Dom\Document;
use zxf\Utils\Dom\Selectors\Query;
use zxf\Utils\Dom\Element;
use zxf\Utils\Dom\Utils\Encoder;

// 初始化 Query
Query::initialize();

echo "=== zxf/utils Dom 使用示例 ===\n\n";

// ==================== 示例 1: 基础 HTML 解析 ====================

echo "--- 示例 1: 基础 HTML 解析 ---\n\n";

$html = '
<!DOCTYPE html>
<html>
<head>
    <title>示例页面</title>
</head>
<body>
    <div class="container">
        <h1>欢迎</h1>
        <p>这是一个示例页面。</p>
        <a href="https://example.com">点击这里</a>
    </div>
</body>
</html>';

$doc = new Document($html);

// 获取标题
$title = $doc->title();
echo "标题: {$title}\n";

// 获取段落文本
$paragraph = $doc->first('p');
echo "段落: {$paragraph->text()}\n";

// 获取链接
$link = $doc->first('a');
echo "链接: {$link->text()} ({$link->attr('href')})\n";

// ==================== 示例 2: 列表操作 ====================

echo "\n--- 示例 2: 列表操作 ---\n\n";

$html = '
<ul class="todo-list">
    <li class="item completed">任务 1</li>
    <li class="item">任务 2</li>
    <li class="item active">任务 3</li>
    <li class="item">任务 4</li>
</ul>';

$doc = new Document($html);

// 获取所有列表项
$items = $doc->find('.item');
echo "总任务数: " . count($items) . "\n";

// 获取已完成的任务
$completed = $doc->find('.item.completed');
echo "已完成: " . count($completed) . "\n";

// 获取活动任务
$active = $doc->find('.item.active');
echo "活动任务: " . count($active) . "\n";

// 使用位置伪类
$first = $doc->first('li:first-child');
echo "第一个任务: {$first->text()}\n";

$last = $doc->first('li:last-child');
echo "最后一个任务: {$last->text()}\n";

// 使用 :not 伪类
$pending = $doc->find('li:not(.completed)');
echo "待处理任务: " . count($pending) . "\n";

// ==================== 示例 3: 表单处理 ====================

echo "\n--- 示例 3: 表单处理 ---\n\n";

$html = '
<form id="login-form">
    <input type="text" name="username" placeholder="用户名" required>
    <input type="password" name="password" placeholder="密码" required>
    <input type="email" name="email" placeholder="邮箱">
    <input type="checkbox" name="remember" id="remember">
    <label for="remember">记住我</label>
    <input type="radio" name="gender" value="male" id="male">
    <label for="male">男</label>
    <input type="radio" name="gender" value="female" id="female" checked>
    <label for="female">女</label>
    <select name="city">
        <option value="">请选择城市</option>
        <option value="beijing">北京</option>
        <option value="shanghai" selected>上海</option>
        <option value="guangzhou">广州</option>
    </select>
    <button type="submit">提交</button>
</form>';

$doc = new Document($html);

// 获取所有必填字段
$required = $doc->find(':required');
echo "必填字段: " . count($required) . "\n";

// 获取文本输入框
$textInputs = $doc->find(':text, :password, :email');
echo "文本输入框: " . count($textInputs) . "\n";

// 获取选中的元素
$checked = $doc->find(':checked');
foreach ($checked as $item) {
    echo "已选中: " . $item->attr('name') . " = " . $item->attr('value') . "\n";
}

// 填充表单
$doc->attr('input[name="username"]', 'value', '张三');
$doc->attr('input[name="password"]', 'value', '123456');
$doc->attr('input[name="email"]', 'value', 'zhangsan@example.com');

// 添加类
$doc->addClass('#login-form', 'form-container');

// ==================== 示例 4: 复杂选择器 ====================

echo "\n--- 示例 4: 复杂选择器 ---\n\n";

$html = '
<div class="grid">
    <div class="row">
        <div class="col">单元格 1-1</div>
        <div class="col highlight">单元格 1-2</div>
        <div class="col">单元格 1-3</div>
    </div>
    <div class="row">
        <div class="col">单元格 2-1</div>
        <div class="col active">单元格 2-2</div>
        <div class="col highlight">单元格 2-3</div>
    </div>
</div>';

$doc = new Document($html);

// 多层级后代选择器
$cells = $doc->find('.grid .col');
echo "总单元格: " . count($cells) . "\n";

// 直接子代选择器
$rows = $doc->find('.grid > .row');
echo "行数: " . count($rows) . "\n";

// 相邻兄弟选择器
$nextToHighlight = $doc->find('.highlight + .col');
echo "highlight 后面的单元格: " . count($nextToHighlight) . "\n";

// 通用兄弟选择器
$allSiblings = $doc->find('.active ~ .col');
echo "active 后面的所有单元格: " . count($allSiblings) . "\n";

// 组合选择器
$highlightedCells = $doc->find('.row:has(.highlight) .col');
echo "包含 highlight 的行中的单元格: " . count($highlightedCells) . "\n";

// ==================== 示例 5: XML 解析 ====================

echo "\n--- 示例 5: XML 解析 ---\n\n";

$xml = '<?xml version="1.0" encoding="UTF-8"?>
<catalog>
    <book id="1">
        <title>PHP 权威指南</title>
        <author>张三</author>
        <price>89.00</price>
        <category>编程</category>
    </book>
    <book id="2">
        <title>Web 开发实战</title>
        <author>李四</author>
        <price>99.00</price>
        <category>编程</category>
    </book>
    <book id="3">
        <title>数据结构与算法</title>
        <author>王五</author>
        <price>79.00</price>
        <category>计算机科学</category>
    </book>
</catalog>';

$doc = new Document($xml, false, 'UTF-8', Document::TYPE_XML);

// 查找所有书籍
$books = $doc->find('book');
echo "书籍总数: " . count($books) . "\n";

// 遍历书籍
foreach ($books as $book) {
    $id = $book->attr('id');
    $title = $book->first('title')->text();
    $author = $book->first('author')->text();
    $price = $book->first('price')->text();
    echo "书籍 [{$id}]: {$title} - {$author} - ¥{$price}\n";
}

// 查找特定分类的书籍
$programmingBooks = $doc->find('book[category="编程"]');
echo "编程类书籍: " . count($programmingBooks) . "\n";

// ==================== 示例 6: 动态创建元素 ====================

echo "\n--- 示例 6: 动态创建元素 ---\n\n";

$doc = new Document('<div class="container"></div>');

// 创建新元素
$heading = $doc->createElement('h2', '动态创建的标题', ['class' => 'section-title']);
$paragraph = $doc->createElement('p', '这是动态创建的段落文本。');
$link = $doc->createElement('a', '点击这里', [
    'href' => 'https://example.com',
    'target' => '_blank',
    'class' => 'btn-link'
]);

// 添加到容器
$container = $doc->first('.container');
$container->append($heading);
$container->append($paragraph);
$container->append($link);

echo "创建的 HTML:\n";
echo $doc->html('.container') . "\n";

// ==================== 示例 7: 修改现有内容 ====================

echo "\n--- 示例 7: 修改现有内容 ---\n\n";

$html = '
<div class="content">
    <h2 class="title">原始标题</h2>
    <p class="description">原始描述文本。</p>
    <div class="box">原始内容</div>
</div>';

$doc = new Document($html);

// 修改文本内容（需要先获取元素）
$titleElement = $doc->first('.title');
$titleElement->text('新标题');

$descElement = $doc->first('.description');
$descElement->text('新的描述文本，已更新。');

// 修改 HTML 内容（需要先获取元素）
$boxElement = $doc->first('.box');
$boxElement->html('<span>新的内容</span><em>已替换</em>');

// 添加类
$doc->addClass('.title', 'highlight');
$doc->addClass('.description', 'updated');

// 设置样式（需要先获取元素）
$titleElement = $doc->first('.title');
$titleElement->css('color', 'blue');

$descElement = $doc->first('.description');
$descElement->css('font-style', 'italic');

// 添加属性（需要先获取元素）
$spanElement = $boxElement->first('span');
if ($spanElement !== null) {
    $spanElement->attr('data-id', '123');
}

echo "修改后的 HTML:\n";
echo $doc->html('.content') . "\n";

// ==================== 示例 8: 网页爬虫示例 ====================

echo "\n--- 示例 8: 网页爬虫示例 ---\n\n";

$html = '
<!DOCTYPE html>
<html>
<head>
    <title>新闻网站</title>
</head>
<body>
    <div class="news-list">
        <article class="news-item">
            <h2 class="news-title"><a href="/news/1">PHP 8.4 发布</a></h2>
            <p class="news-summary">PHP 8.4 带来了许多新特性...</p>
            <div class="news-meta">
                <span class="author">张三</span>
                <span class="date">2024-12-01</span>
                <span class="category">技术</span>
            </div>
        </article>
        <article class="news-item">
            <h2 class="news-title"><a href="/news/2">Web 开发最佳实践</a></h2>
            <p class="news-summary">现代 Web 开发需要遵循...</p>
            <div class="news-meta">
                <span class="author">李四</span>
                <span class="date">2024-12-02</span>
                <span class="category">教程</span>
            </div>
        </article>
        <article class="news-item">
            <h2 class="news-title"><a href="/news/3">数据结构入门</a></h2>
            <p class="news-summary">学习数据结构的重要性...</p>
            <div class="news-meta">
                <span class="author">王五</span>
                <span class="date">2024-12-03</span>
                <span class="category">计算机科学</span>
            </div>
        </article>
    </div>
</body>
</html>';

$doc = new Document($html);

// 提取所有新闻
$newsItems = $doc->find('.news-item');
echo "新闻总数: " . count($newsItems) . "\n";

foreach ($newsItems as $index => $item) {
    $title = $item->first('.news-title a')->text();
    $link = $item->first('.news-title a')->attr('href');
    $summary = $item->first('.news-summary')->text();
    $author = $item->first('.author')->text();
    $date = $item->first('.date')->text();
    $category = $item->first('.category')->text();

    echo "\n新闻 " . ($index + 1) . ":\n";
    echo "  标题: {$title}\n";
    echo "  链接: {$link}\n";
    echo "  摘要: {$summary}\n";
    echo "  作者: {$author}\n";
    echo "  日期: {$date}\n";
    echo "  分类: {$category}\n";
}

// ==================== 示例 9: 伪元素使用 ====================

echo "\n--- 示例 9: 伪元素使用 ---\n\n";

$html = '
<div>
    <a href="https://example.com" data-id="123">示例链接</a>
    <span class="value">42</span>
</div>';

$doc = new Document($html);

// 使用 ::text 伪元素获取文本
$linkText = $doc->text('a::text');
echo "链接文本: {$linkText}\n";

// 使用 ::attr 伪元素获取属性
$linkHref = $doc->text('a::attr(href)');
$linkDataId = $doc->text('a::attr(data-id)');
echo "链接 href: {$linkHref}\n";
echo "链接 data-id: {$linkDataId}\n";

// 批量获取属性
$allLinks = $doc->find('a::attr(href)');
echo "所有链接 href: " . implode(', ', $allLinks) . "\n";

// ==================== 示例 10: XPath 使用 ====================

echo "\n--- 示例 10: XPath 使用 ---\n\n";

$html = '
<div class="container">
    <p>段落 1</p>
    <p>段落 2</p>
    <p>段落 3</p>
    <div>
        <p>嵌套段落 1</p>
        <p>嵌套段落 2</p>
    </div>
</div>';

$doc = new Document($html);

// 使用 XPath 查找
$allParagraphs = $doc->find('//p', Query::TYPE_XPATH);
echo "所有段落: " . count($allParagraphs) . "\n";

// 使用 XPath 函数
$paragraphsWithText = $doc->find('//p[contains(text(), "嵌套")]', Query::TYPE_XPATH);
echo "包含'嵌套'的段落: " . count($paragraphsWithText) . "\n";

// 使用 XPath 轴
$nestedParagraphs = $doc->find('//div//p', Query::TYPE_XPATH);
echo "嵌套的段落: " . count($nestedParagraphs) . "\n";

// 使用 XPath 位置
$firstParagraph = $doc->find('//p[position()=1]', Query::TYPE_XPATH);
echo "第一个段落: " . $firstParagraph[0]->text() . "\n";

$lastParagraph = $doc->find('//p[last()]', Query::TYPE_XPATH);
echo "最后一个段落: " . $lastParagraph[0]->text() . "\n";

// ==================== 示例 11: 表格操作 ====================

echo "\n--- 示例 11: 表格操作 ---\n\n";

$html = '
<table>
    <thead>
        <tr>
            <th>姓名</th>
            <th>年龄</th>
            <th>城市</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>张三</td>
            <td>25</td>
            <td>北京</td>
        </tr>
        <tr>
            <td>李四</td>
            <td>30</td>
            <td>上海</td>
        </tr>
        <tr>
            <td>王五</td>
            <td>28</td>
            <td>广州</td>
        </tr>
    </tbody>
</table>';

$doc = new Document($html);

// 获取表头
$headers = $doc->find('thead th');
echo "表头: ";
foreach ($headers as $header) {
    echo $header->text() . " ";
}
echo "\n";

// 获取数据行
$rows = $doc->find('tbody tr');
echo "数据行数: " . count($rows) . "\n";

foreach ($rows as $index => $row) {
    $cells = $row->find('td');
    $data = array_map(fn($cell) => $cell->text(), $cells);
    echo "行 " . ($index + 1) . ": " . implode(' | ', $data) . "\n";
}

// 使用 :nth-child 选择特定行
$secondRow = $doc->first('tbody tr:nth-child(2)');
echo "第二行第一个单元格: " . $secondRow->first('td')->text() . "\n";

// ==================== 示例 12: 链式操作 ====================

echo "\n--- 示例 12: 链式操作 ---\n\n";

$doc = new Document('<div class="box">内容</div>');

// 链式调用（通过 Element 对象）
$box = $doc->first('.box');
$box
    ->addClass('active')
    ->addClass('highlight')
    ->css('color', 'red')
    ->css('background', 'yellow')
    ->attr('data-id', '123');

$box = $doc->first('.box');
echo "类名: " . implode(', ', $box->classes()->all()) . "\n";
echo "颜色: " . $box->css('color') . "\n";
echo "背景: " . $box->css('background') . "\n";
echo "数据 ID: " . $box->attr('data-id') . "\n";

// ==================== 示例 13: 中文和 UTF-8 支持 ====================

echo "\n--- 示例 13: 中文和 UTF-8 支持 ---\n\n";

$html = '
<div class="article">
    <h1>PHP 开发技巧</h1>
    <p>学习 PHP 可以帮助你构建强大的 Web 应用程序。</p>
    <p class="author">作者：张三</p>
    <p class="date">发布时间：2024年12月</p>
</div>';

$doc = new Document($html);

// 获取中文内容
$title = $doc->first('h1')->text();
$author = $doc->text('.author');
$date = $doc->text('.date');

echo "标题: {$title}\n";
echo "{$author}\n";
echo "{$date}\n";

// 使用 :contains 伪类匹配中文
$paragraphs = $doc->find('p:contains(PHP)');
echo "包含'PHP'的段落: " . count($paragraphs) . "\n";

// ==================== 示例 14: 高级伪类使用 ====================

echo "\n--- 示例 14: 高级伪类使用 ---\n\n";

$html = '
<div class="container">
    <div class="item">项目 1</div>
    <div class="item active">项目 2</div>
    <div class="item">项目 3</div>
    <div class="item">项目 4</div>
    <div class="item active">项目 5</div>
</div>';

$doc = new Document($html);

// 使用 :slice 伪类
$items2to4 = $doc->find('.item:slice(1:3)');
echo "第2-4项: " . count($items2to4) . "\n";

// 使用 :even 和 :odd
$evenItems = $doc->find('.item:even');
$oddItems = $doc->find('.item:odd');
echo "偶数项: " . count($evenItems) . ", 奇数项: " . count($oddItems) . "\n";

// 使用 :gt 和 :lt
$itemsAfter2 = $doc->find('.item:gt(1)');
$itemsBefore3 = $doc->find('.item:lt(2)');
echo "第3项之后: " . count($itemsAfter2) . ", 前3项: " . count($itemsBefore3) . "\n";

// 使用 :eq
$thirdItem = $doc->first('.item:eq(2)');
echo "第3项: " . $thirdItem->text() . "\n";

// ==================== 示例 15: 属性选择器 ====================

echo "\n--- 示例 15: 属性选择器 ---\n\n";

$html = '
<div>
    <a href="https://example.com" target="_blank">链接1</a>
    <a href="http://example.org" target="_self">链接2</a>
    <a href="ftp://example.net" target="_blank">链接3</a>
    <div data-value="123">Div 1</div>
    <div data-value="456">Div 2</div>
    <div class="test active">Div 3</div>
</div>';

$doc = new Document($html);

// href 属性选择器
$httpsLinks = $doc->find('a[href^="https"]');
echo "HTTPS 链接: " . count($httpsLinks) . "\n";

// target 属性选择器
$blankLinks = $doc->find('a[target="_blank"]');
echo "新窗口链接: " . count($blankLinks) . "\n";

// data-* 属性选择器
$divsWithData = $doc->find('div[data-value]');
echo "带 data-value 的 div: " . count($divsWithData) . "\n";

// 多属性选择器
$div123 = $doc->find('div[data-value="123"]');
echo "data-value 为 123 的 div: " . count($div123) . "\n";

// ==================== 示例 16: 错误处理 ====================

echo "\n--- 示例 16: 错误处理 ---\n\n";

try {
    $doc = new Document('<div></div>');

    // 查找不存在的元素（不会抛出异常，返回空数组）
    $elements = $doc->find('.nonexistent');
    echo "不存在的元素数量: " . count($elements) . "\n";

    // 使用无效选择器（会抛出异常）
    try {
        $doc->find('[');
    } catch (\zxf\Utils\Dom\Exceptions\InvalidSelectorException $e) {
        echo "捕获到无效选择器异常: " . $e->getMessage() . "\n";
    }

    // 使用 Errors::silence 静默处理错误
    $result = \zxf\Utils\Dom\Utils\Errors::silence(function() {
        throw new \RuntimeException('这是一个错误');
    }, '默认值');
    echo "静默处理错误后的结果: {$result}\n";

} catch (Exception $e) {
    echo "发生错误: " . $e->getMessage() . "\n";
}

// ==================== 示例 17: 文档片段 ====================

echo "\n--- 示例 17: 文档片段 ---\n\n";

$doc = new Document('<div id="container"></div>');

// 创建文档片段
$fragment = $doc->createDocumentFragment();

// 向片段添加多个元素
$fragment->append($doc->createElement('p', '段落 1'));
$fragment->append($doc->createElement('p', '段落 2'));
$fragment->append($doc->createElement('p', '段落 3'));

// 将片段添加到文档
$container = $doc->first('#container');
$container->append($fragment);

echo "容器 HTML:\n" . $doc->html('#container') . "\n";

// ==================== 示例 18: 编码和解码 ====================

echo "\n--- 示例 18: 编码和解码 ---\n\n";

// HTML 编码
$htmlText = '<script>alert("XSS")</script>';
$encoded = Encoder::html($htmlText);
echo "HTML 编码: {$encoded}\n";

// HTML 解码
$decoded = Encoder::htmlDecode($encoded);
echo "HTML 解码: {$decoded}\n";

// URL 编码
$url = 'https://example.com?q=测试&lang=中文';
$urlEncoded = Encoder::url($url);
echo "URL 编码: {$urlEncoded}\n";

// URL 解码
$urlDecoded = Encoder::urlDecode($urlEncoded);
echo "URL 解码: {$urlDecoded}\n";

// JSON 编码
$data = ['name' => '张三', 'age' => 30, 'city' => '北京'];
$json = Encoder::json($data);
echo "JSON 编码: {$json}\n";

// JSON 解码
$decodedJson = Encoder::jsonDecode($json);
echo "JSON 解码: " . print_r($decodedJson, true) . "\n";

// ==================== 示例 19: 表单数据提取 ====================

echo "\n--- 示例 19: 表单数据提取 ---\n\n";

$html = '
<form id="contact-form">
    <input type="text" name="name" value="张三">
    <input type="email" name="email" value="zhangsan@example.com">
    <input type="tel" name="phone" value="13800138000">
    <select name="gender">
        <option value="male" selected>男</option>
        <option value="female">女</option>
    </select>
    <textarea name="message">这是一条消息</textarea>
    <input type="checkbox" name="agree" checked>
    <input type="checkbox" name="subscribe" checked>
    <input type="radio" name="age-group" value="18-25">
    <input type="radio" name="age-group" value="26-35" checked>
</form>';

$doc = new Document($html);

// 提取所有输入元素的名称和值
$inputs = $doc->find('input, select, textarea');
$formData = [];

foreach ($inputs as $input) {
    $name = $input->attr('name');
    if ($name) {
        $type = $input->attr('type');
        if ($type === 'checkbox' || $type === 'radio') {
            if ($input->attr('checked')) {
                if (!isset($formData[$name])) {
                    $formData[$name] = [];
                }
                $formData[$name][] = $input->attr('value');
            }
        } elseif ($input->tagName() === 'select') {
            $selected = $input->find(':selected');
            if (!empty($selected)) {
                $formData[$name] = $selected[0]->attr('value');
            }
        } else {
            $formData[$name] = $input->attr('value');
        }
    }
}

echo "表单数据:\n";
print_r($formData);

// ==================== 示例 20: 深度嵌套查询 ====================

echo "\n--- 示例 20: 深度嵌套查询 ---\n\n";

$html = '
<div class="level-1">
    <div class="level-2">
        <div class="level-3">
            <div class="level-4">
                <p class="deep-text">深层文本</p>
            </div>
        </div>
    </div>
    <div class="level-2">
        <p>浅层文本</p>
    </div>
</div>';

$doc = new Document($html);

// 深度查找
$deepText = $doc->text('.level-1 .deep-text');
echo "深层文本: {$deepText}\n";

// 使用 XPath 查找特定层级
$level4P = $doc->find('.level-1 .level-2 .level-3 .level-4 p');
echo "第4层段落数量: " . count($level4P) . "\n";

// ==================== 示例 21: 动态修改类和样式 ====================

echo "\n--- 示例 21: 动态修改类和样式 ---\n\n";

$html = '<div id="element">内容</div>';
$doc = new Document($html);

$element = $doc->first('#element');

// 添加多个类
$element->classes()->add('class1', 'class2', 'class3');
echo "添加类后: " . implode(' ', $element->classes()->all()) . "\n";

// 移除类
$element->classes()->remove('class2');
echo "移除类后: " . implode(' ', $element->classes()->all()) . "\n";

// 切换类
$element->classes()->toggle('class4');
echo "切换类后: " . implode(' ', $element->classes()->all()) . "\n";

// 检查类
$hasClass1 = $element->hasClass('class1');
$hasClass2 = $element->hasClass('class2');
$hasClass4 = $element->hasClass('class4');
echo "包含 class1: " . ($hasClass1 ? '是' : '否') . "\n";
echo "包含 class2: " . ($hasClass2 ? '是' : '否') . "\n";
echo "包含 class4: " . ($hasClass4 ? '是' : '否') . "\n";

// 设置样式
$element->css('color', 'red');
$element->css('background', 'blue');
$element->css('padding', '10px');
echo "设置样式后的 style: " . $element->attr('style') . "\n";

// 批量设置样式
$element->style()->set([
    'margin' => '20px',
    'border' => '1px solid black',
    'font-size' => '16px'
]);
echo "批量设置样式后的 style: " . $element->attr('style') . "\n";

// ==================== 示例 22: 节点操作 ====================

echo "\n--- 示例 22: 节点操作 ---\n\n";

$html = '<div id="container"><p>原始段落</p></div>';
$doc = new Document($html);

$container = $doc->first('#container');

// 在节点前插入
$beforeElement = $doc->createElement('p', '前面插入');
$container->first('p')->before($beforeElement);

// 在节点后插入
$afterElement = $doc->createElement('p', '后面插入');
$container->first('p')->after($afterElement);

// 在开头插入
$prependElement = $doc->createElement('p', '开头插入');
$container->prepend($prependElement);

// 在末尾添加
$appendElement = $doc->createElement('p', '末尾添加');
$container->append($appendElement);

echo "节点操作后:\n" . $doc->html('#container') . "\n";

// 移除节点
$container->first('p')->remove();
echo "移除第一个段落后:\n" . $doc->html('#container') . "\n";

// ==================== 示例 23: 高级 CSS 选择器 ====================

echo "\n--- 示例 23: 高级 CSS 选择器 ---\n\n";

$html = '
<div class="main">
    <article id="post-1" data-category="tech" data-published="true">
        <h2 class="title">技术文章</h2>
        <p class="content">这是技术文章的内容。</p>
        <footer class="author">作者: John</footer>
    </article>
    <article id="post-2" data-category="design">
        <h2 class="title">设计文章</h2>
        <p class="content">这是设计文章的内容。</p>
        <footer class="author">作者: Jane</footer>
    </article>
    <article id="post-3" data-category="tech" data-published="true">
        <h2 class="title">技术文章 2</h2>
        <p class="content">这是另一篇技术文章。</p>
        <footer class="author">作者: Bob</footer>
    </article>
</div>';

$doc = new Document($html);

// 属性选择器
echo "使用属性选择器:\n";
$techPosts = $doc->find('article[data-category="tech"]');
echo "技术文章数: " . count($techPosts) . "\n";

$publishedPosts = $doc->find('[data-published="true"]');
echo "已发布文章数: " . count($publishedPosts) . "\n";

// 组合选择器
echo "\n使用组合选择器:\n";
$firstTechPost = $doc->first('article[data-category="tech"]:first-child');
echo "第一篇技术文章: " . $firstTechPost->first('h2')->text() . "\n";

// 伪类选择器
echo "\n使用结构伪类:\n";
$firstPost = $doc->first('article:first-of-type');
echo "第一篇文章: " . $firstPost->first('h2')->text() . "\n";

$lastPost = $doc->first('article:last-of-type');
echo "最后一篇文章: " . $lastPost->first('h2')->text() . "\n";

$evenPosts = $doc->find('article:nth-of-type(even)');
echo "偶数位置文章数: " . count($evenPosts) . "\n";

// 内容伪类
echo "\n使用内容伪类:\n";
$techArticles = $doc->find('article:contains(技术)');
echo "包含'技术'的文章数: " . count($techArticles) . "\n";

// ==================== 示例 24: XPath 高级应用 ====================

echo "\n--- 示例 24: XPath 高级应用 ---\n\n";

$html = '
<html>
<body>
    <div class="products">
        <div class="product" data-id="1">
            <h3>产品 1</h3>
            <span class="price">$100</span>
            <span class="stock">10</span>
        </div>
        <div class="product" data-id="2">
            <h3>产品 2</h3>
            <span class="price">$150</span>
            <span class="stock">5</span>
        </div>
        <div class="product" data-id="3">
            <h3>产品 3</h3>
            <span class="price">$200</span>
            <span class="stock">0</span>
        </div>
    </div>
</body>
</html>';

$doc = new Document($html);

// 使用 XPath 查找特定产品
echo "使用 XPath:\n";
$product1 = $doc->xpath('//div[@data-id="1"]');
echo "产品 1 名称: " . $product1[0]->first('h3')->text() . "\n";

// 使用 XPath 函数
echo "\n使用 XPath 函数:\n";
$productsWithPrice = $doc->xpath('//div[contains(@class, "product") and .//span[@class="price"]]');
echo "有价格的产品数: " . count($productsWithPrice) . "\n";

// 使用 position() 函数
$firstProduct = $doc->xpath('(//div[@class="product"])[1]');
echo "第一个产品: " . $firstProduct[0]->first('h3')->text() . "\n";

$lastProduct = $doc->xpath('(//div[@class="product"])[last()]');
echo "最后一个产品: " . $lastProduct[0]->first('h3')->text() . "\n";

// ==================== 示例 25: 特殊选择器和伪元素 ====================

echo "\n--- 示例 25: 特殊选择器和伪元素 ---\n\n";

$html = '
<div class="container">
    <a href="https://example.com" data-id="123" data-category="link" title="示例链接">点击这里</a>
    <img src="image.jpg" alt="示例图片">
    <input type="text" name="username" placeholder="请输入用户名" required>
    <input type="password" name="password" required>
    <input type="email" name="email" placeholder="请输入邮箱">
</div>';

$doc = new Document($html);

// 使用 ::text 伪元素获取文本
echo "使用 ::text 伪元素:\n";
$linkText = $doc->text('a::text');
echo "链接文本: {$linkText}\n";

// 使用 ::attr() 伪元素获取属性
echo "\n使用 ::attr() 伪元素:\n";
$href = $doc->text('a::attr(href)');
echo "链接 URL: {$href}\n";

$dataId = $doc->text('a::attr(data-id)');
echo "链接 data-id: {$dataId}\n";

$imgSrc = $doc->text('img::attr(src)');
echo "图片 src: {$imgSrc}\n";

$imgAlt = $doc->text('img::attr(alt)');
echo "图片 alt: {$imgAlt}\n";

// 表单伪类
echo "\n使用表单伪类:\n";
$requiredFields = $doc->find(':required');
echo "必填字段数: " . count($requiredFields) . "\n";

$textInputs = $doc->find('input[type="text"]');
echo "文本输入框数: " . count($textInputs) . "\n";

$passwordInputs = $doc->find('input[type="password"]');
echo "密码输入框数: " . count($passwordInputs) . "\n";

$emailInputs = $doc->find('input[type="email"]');
echo "邮箱输入框数: " . count($emailInputs) . "\n";

// ==================== 示例 26: 复杂选择器组合 ====================

echo "\n--- 示例 26: 复杂选择器组合 ---\n\n";

$html = '
<div class="menu">
    <ul class="main-menu">
        <li class="menu-item active"><a href="#home">首页</a></li>
        <li class="menu-item"><a href="#about">关于</a></li>
        <li class="menu-item has-submenu">
            <a href="#services">服务</a>
            <ul class="submenu">
                <li><a href="#web">网站开发</a></li>
                <li><a href="#app">应用开发</a></li>
            </ul>
        </li>
        <li class="menu-item"><a href="#contact">联系我们</a></li>
    </ul>
</div>';

$doc = new Document($html);

// 多条件选择器
echo "多条件选择:\n";
$activeMenuItem = $doc->find('.menu-item.active');
echo "活动菜单项数: " . count($activeMenuItem) . "\n";

// 层级选择器
$submenuLinks = $doc->find('.menu-item.has-submenu .submenu li a');
echo "子菜单链接数: " . count($submenuLinks) . "\n";

// 复杂伪类组合
$nonActiveItems = $doc->find('.menu-item:not(.active)');
echo "非活动菜单项数: " . count($nonActiveItems) . "\n";

// 使用 :has 伪类
$itemsWithSubmenu = $doc->find('.menu-item:has(.submenu)');
echo "有子菜单的项数: " . count($itemsWithSubmenu) . "\n";

// ==================== 示例 27: 数据提取和处理 ====================

echo "\n--- 示例 27: 数据提取和处理 ---\n\n";

$html = '
<table class="data-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>名称</th>
            <th>价格</th>
            <th>库存</th>
        </tr>
    </thead>
    <tbody>
        <tr data-id="1">
            <td>1</td>
            <td>产品 A</td>
            <td class="price">$99</td>
            <td class="stock">100</td>
        </tr>
        <tr data-id="2">
            <td>2</td>
            <td>产品 B</td>
            <td class="price">$199</td>
            <td class="stock">50</td>
        </tr>
        <tr data-id="3">
            <td>3</td>
            <td>产品 C</td>
            <td class="price">$299</td>
            <td class="stock">0</td>
        </tr>
    </tbody>
</table>';

$doc = new Document($html);

// 提取表格数据
echo "提取表格数据:\n";
$rows = $doc->find('tbody tr');
$data = [];
foreach ($rows as $row) {
    $id = $row->first('td:first-child')->text();
    $name = $row->first('td:nth-child(2)')->text();
    $price = $row->first('.price')->text();
    $stock = $row->first('.stock')->text();
    $data[] = [
        'id' => $id,
        'name' => $name,
        'price' => $price,
        'stock' => $stock
    ];
}

foreach ($data as $item) {
    echo "ID: {$item['id']}, 名称: {$item['name']}, 价格: {$item['price']}, 库存: {$item['stock']}\n";
}

// 筛选有库存的产品
echo "\n有库存的产品:\n";
$inStockProducts = $doc->find('tbody tr .stock:contains([^0])');
echo "有库存的产品数: " . count($inStockProducts) . "\n";

// ==================== 示例 28: HTML5 结构元素伪类 ====================

echo "\n--- 示例 28: HTML5 结构元素伪类 ---\n";

$html = '<article>
    <header>
        <h1>文章标题</h1>
        <nav>
            <ul><li>首页</li><li>分类</li><li>标签</li></ul>
        </nav>
    </header>
    <main>
        <section>
            <h2>第一节</h2>
            <p>内容段落</p>
            <figure>
                <img src="figure.jpg" alt="图片">
                <figcaption>图片说明</figcaption>
            </figure>
        </section>
        <aside>
            <h3>侧边栏</h3>
            <p>相关内容</p>
        </aside>
    </main>
    <footer>
        <p>页脚信息</p>
    </footer>
</article>';

$doc = new Document($html);

// 使用 HTML5 元素伪类
echo "HTML5 元素:\n";
$header = $doc->first(':header');
echo "标题元素: " . ($header ? $header->tagName() : '无') . "\n";

$nav = $doc->first(':nav');
echo "导航元素: " . ($nav ? $nav->tagName() : '无') . "\n";

$main = $doc->first(':main');
echo "主要内容元素: " . ($main ? $main->tagName() : '无') . "\n";

$section = $doc->first(':section');
echo "章节元素: " . ($section ? $section->tagName() : '无') . "\n";

$aside = $doc->first(':aside');
echo "侧边栏元素: " . ($aside ? $aside->tagName() : '无') . "\n";

$footer = $doc->first(':footer');
echo "页脚元素: " . ($footer ? $footer->tagName() : '无') . "\n";

$figure = $doc->first(':figure');
echo "图表元素: " . ($figure ? $figure->tagName() : '无') . "\n";

// ==================== 示例 29: 表格操作扩展 ====================

echo "\n--- 示例 29: 表格操作扩展 ---\n";

$html = '<table>
    <thead>
        <tr><th>列1</th><th>列2</th><th>列3</th></tr>
    </thead>
    <tbody>
        <tr><td>1-1</td><td>1-2</td><td>1-3</td></tr>
        <tr><td>2-1</td><td>2-2</td><td>2-3</td></tr>
        <tr><td>3-1</td><td>3-2</td><td>3-3</td></tr>
    </tbody>
    <tfoot>
        <tr><td>合计</td><td>6</td><td>9</td></tr>
    </tfoot>
</table>';

$doc = new Document($html);

// 使用表格伪类
$table = $doc->first(':table');
echo "表格元素: " . ($table ? '存在' : '不存在') . "\n";

$thead = $doc->first(':thead');
echo "表头元素: " . ($thead ? '存在' : '不存在') . "\n";

$tbody = $doc->first(':tbody');
echo "表体元素: " . ($tbody ? '存在' : '不存在') . "\n";

$tfoot = $doc->first(':tfoot');
echo "表尾元素: " . ($tfoot ? '存在' : '不存在') . "\n";

$trs = $doc->find(':tr');
echo "表格行数: " . count($trs) . "\n";

$ths = $doc->find(':th');
echo "表头单元格数: " . count($ths) . "\n";

$tds = $doc->find(':td');
echo "数据单元格数: " . count($tds) . "\n";

// ==================== 示例 30: 深度伪类 ====================

echo "\n--- 示例 30: 深度伪类 ---\n";

$html = '<div class="root">
    <div class="level1">
        <div class="level2">
            <div class="level3">
                <p>深层内容</p>
            </div>
        </div>
    </div>
    <div class="level1">
        <p>浅层内容</p>
    </div>
</div>';

$doc = new Document($html);

// 使用深度伪类
$depth0 = $doc->find(':depth-0');
echo "深度0元素数: " . count($depth0) . "\n";

$depth1 = $doc->find(':depth-1');
echo "深度1元素数: " . count($depth1) . "\n";

$depth2 = $doc->find(':depth-2');
echo "深度2元素数: " . count($depth2) . "\n";

$depth3 = $doc->find(':depth-3');
echo "深度3元素数: " . count($depth3) . "\n";

// ==================== 示例 31: 文本长度伪类 ====================

echo "\n--- 示例 31: 文本长度伪类 ---\n";

$html = '<div>短</div>
         <div>中等长度文本</div>
         <div>这是一个很长的文本内容，包含了很多个字符</div>';

$doc = new Document($html);

// 使用文本长度伪类
$shortTexts = $doc->find(':text-length-lt(5)');
echo "短文本元素数: " . count($shortTexts) . "\n";

$mediumTexts = $doc->find(':text-length-gt(5) :text-length-lt(10)');
echo "中等长度文本元素数: " . count($mediumTexts) . "\n";

$longTexts = $doc->find(':text-length-gt(10)');
echo "长文本元素数: " . count($longTexts) . "\n";

// ==================== 示例 32: 子元素数量伪类 ====================

echo "\n--- 示例 32: 子元素数量伪类 ---\n";

$html = '<div><p>一个子元素</p></div>
         <div><p>两个</p><p>子元素</p></div>
         <div><p>三个</p><p>子</p><p>元素</p></div>';

$doc = new Document($html);

// 使用子元素数量伪类
$singleChild = $doc->find(':children-eq(1)');
echo "单子元素元素数: " . count($singleChild) . "\n";

$twoChildren = $doc->find(':children-eq(2)');
echo "双子元素元素数: " . count($twoChildren) . "\n";

$multipleChildren = $doc->find(':children-gt(1)');
echo "多子元素元素数: " . count($multipleChildren) . "\n";

$fewChildren = $doc->find(':children-lt(3)');
echo "少子元素元素数: " . count($fewChildren) . "\n";

// ==================== 示例 33: 语言和方向伪类 ====================

echo "\n--- 示例 33: 语言和方向伪类 ---\n";

$html = '<div lang="zh-CN" dir="ltr">中文内容</div>
         <div lang="en-US" dir="ltr">English Content</div>
         <div lang="ar-SA" dir="rtl">المحتوى العربي</div>
         <div dir="auto">自动方向</div>';

$doc = new Document($html);

// 使用语言伪类
$zhContent = $doc->find('[lang|="zh"]');
echo "中文内容数: " . count($zhContent) . "\n";

$enContent = $doc->find('[lang|="en"]');
echo "英文内容数: " . count($enContent) . "\n";

// 使用方向伪类
$ltrElements = $doc->find(':dir-ltr');
echo "左到右元素数: " . count($ltrElements) . "\n";

$rtlElements = $doc->find(':dir-rtl');
echo "右到左元素数: " . count($rtlElements) . "\n";

$autoElements = $doc->find(':dir-auto');
echo "自动方向元素数: " . count($autoElements) . "\n";

// ==================== 示例 34: 表单验证伪类 ====================

echo "\n--- 示例 34: 表单验证伪类 ---\n";

$html = '<form>
    <input type="number" min="0" max="100" value="50">
    <input type="number" min="0" max="100" value="150">
    <input type="text" placeholder="输入文本">
    <input type="text" aria-invalid="true">
    <input type="text" aria-invalid="false">
</form>';

$doc = new Document($html);

// 使用表单验证伪类
$inRangeInputs = $doc->find(':in-range');
echo "在范围内的输入: " . count($inRangeInputs) . "\n";

$outOfRangeInputs = $doc->find(':out-of-range');
echo "超出范围的输入: " . count($outOfRangeInputs) . "\n";

$placeholderShown = $doc->find(':placeholder-shown');
echo "显示占位符的输入: " . count($placeholderShown) . "\n";

$userInvalid = $doc->find(':user-invalid');
echo "用户验证失败的输入: " . count($userInvalid) . "\n";

$userValid = $doc->find(':user-valid');
echo "用户验证通过的输入: " . count($userValid) . "\n";

// ==================== 示例 35: 属性匹配扩展伪类 ====================

echo "\n--- 示例 35: 属性匹配扩展伪类 ---\n";

$html = '<div data-id="123">1</div>
         <div data-id="456">2</div>
         <div class="btn-primary">按钮</div>
         <div class="btn-secondary">按钮2</div>
         <div src="image.jpg">图片</div>';

$doc = new Document($html);

// 使用属性匹配扩展伪类
$hasDataId = $doc->find(':has-attr(data-id)');
echo "有 data-id 属性的元素: " . count($hasDataId) . "\n";

$hasData = $doc->find(':data(id)');
echo "有 data-* 属性的元素: " . count($hasData) . "\n";

// ==================== 示例 36: 位置范围伪类 ====================

echo "\n--- 示例 36: 位置范围伪类 ---\n";

$html = '<div class="list">
    <p>项目 1</p>
    <p>项目 2</p>
    <p>项目 3</p>
    <p>项目 4</p>
    <p>项目 5</p>
    <p>项目 6</p>
    <p>项目 7</p>
    <p>项目 8</p>
</div>';

$doc = new Document($html);

// 使用位置范围伪类
$items2to5 = $doc->find('p:between(2,5)');
echo "第2-5项数: " . count($items2to5) . "\n";

$items1to3 = $doc->find('p:slice(0:3)');
echo "切片0:3项数: " . count($items1to3) . "\n";

$items3to6 = $doc->find('p:slice(2:6)');
echo "切片2:6项数: " . count($items3to6) . "\n";

// ==================== 示例 37: 列表元素伪类 ====================

echo "\n--- 示例 37: 列表元素伪类 ---\n";

$html = '<ul><li>项目1</li><li>项目2</li></ul>
         <ol><li>项目1</li><li>项目2</li></ol>
         <dl><dt>术语</dt><dd>描述</dd></dl>';

$doc = new Document($html);

// 使用列表伪类
$ulElements = $doc->find(':ul');
echo "无序列表数: " . count($ulElements) . "\n";

$olElements = $doc->find(':ol');
echo "有序列表数: " . count($olElements) . "\n";

$dlElements = $doc->find(':dl');
echo "定义列表数: " . count($dlElements) . "\n";

$liElements = $doc->find(':li');
echo "列表项数: " . count($liElements) . "\n";

$dtElements = $doc->find(':dt');
echo "定义术语数: " . count($dtElements) . "\n";

$ddElements = $doc->find(':dd');
echo "定义描述数: " . count($ddElements) . "\n";

// ==================== 示例 38: 表单元素类型扩展 ====================

echo "\n--- 示例 38: 表单元素类型扩展 ---\n";

$html = '<form>
    <input type="text">
    <input type="search">
    <input type="tel">
    <input type="url">
    <input type="email">
    <input type="password">
    <input type="number">
    <input type="range">
    <input type="color">
    <input type="date">
    <input type="time">
    <input type="datetime-local">
    <input type="month">
    <input type="week">
    <input type="file">
    <input type="image">
</form>';

$doc = new Document($html);

// 使用表单元素类型伪类
echo "各种输入类型:\n";
echo "text: " . count($doc->find(':text')) . "\n";
echo "search: " . count($doc->find(':search')) . "\n";
echo "tel: " . count($doc->find(':tel')) . "\n";
echo "url: " . count($doc->find(':url')) . "\n";
echo "email: " . count($doc->find(':email')) . "\n";
echo "password: " . count($doc->find(':password')) . "\n";
echo "number: " . count($doc->find(':number')) . "\n";
echo "range: " . count($doc->find(':range')) . "\n";
echo "color: " . count($doc->find(':color')) . "\n";
echo "date: " . count($doc->find(':date')) . "\n";
echo "time: " . count($doc->find(':time')) . "\n";
echo "datetime-local: " . count($doc->find(':datetime-local')) . "\n";
echo "month: " . count($doc->find(':month')) . "\n";
echo "week: " . count($doc->find(':week')) . "\n";
echo "file: " . count($doc->find(':file')) . "\n";
echo "image: " . count($doc->find(':image')) . "\n";

// ==================== 示例 39: 文本节点处理 ====================

echo "\n--- 示例 39: 文本节点处理 ---\n\n";

$html39 = '<div class="content">
    这是直接文本1
    <span class="highlight">这是span内的文本</span>
    这是直接文本2
</div>';

$doc39 = new Document($html39);

// 获取直接文本节点（不包括子元素）
echo "获取直接文本节点:\n";
$directTexts = $doc39->directText('div.content');
echo "直接文本数量: " . count($directTexts) . "\n";
foreach ($directTexts as $i => $text) {
    echo "  [$i] $text\n";
}

// 获取所有文本节点（包括子元素）
echo "\n获取所有文本节点:\n";
$allTexts = $doc39->allTextNodes('div.content');
echo "文本数量: " . count($allTexts) . "\n";
foreach ($allTexts as $i => $text) {
    echo "  [$i] $text\n";
}

// 使用XPath的text()函数
echo "\n使用XPath的text()函数:\n";
$xpathTexts = $doc39->find('//div[@class="content"]/text()', Query::TYPE_XPATH);
echo "文本节点数量: " . count($xpathTexts) . "\n";

// ==================== 示例 40: 便捷查找方法 ====================

echo "\n--- 示例 40: 便捷查找方法 ---\n\n";

$html40 = '<div id="container">
    <div class="item" data-id="123" data-type="primary">项目1</div>
    <div class="item" data-id="456">项目2</div>
    <div class="item active">激活项目</div>
    <div class="item">项目3</div>
</div>';

$doc40 = new Document($html40);

// findFirstByText - 查找包含指定文本的第一个元素
echo "findFirstByText():\n";
$element = $doc40->findFirstByText('项目1');
if ($element) {
    echo "找到: " . trim($element->text()) . "\n";
}

// findFirstByAttribute - 查找具有指定属性值的第一个元素
echo "\nfindFirstByAttribute():\n";
$element = $doc40->findFirstByAttribute('data-id', '123');
if ($element) {
    echo "找到: " . trim($element->text()) . "\n";
}

// findFirstByAttributeContains - 查找属性包含指定值的第一个元素
echo "\nfindFirstByAttributeContains():\n";
$element = $doc40->findFirstByAttributeContains('class', 'active');
if ($element) {
    echo "找到: " . trim($element->text()) . "\n";
}

// findFirstByAttributeStartsWith - 查找属性值以指定前缀开头的第一个元素
echo "\nfindFirstByAttributeStartsWith():\n";
$element = $doc40->findFirstByAttributeStartsWith('data-id', '1');
if ($element) {
    echo "找到: " . trim($element->text()) . "\n";
}

// findByIndex - 查找指定索引位置的元素
echo "\nfindByIndex():\n";
$element = $doc40->findByIndex('.item', 2);
if ($element) {
    echo "索引2的元素: " . trim($element->text()) . "\n";
}

// findLast - 查找最后一个匹配的元素
echo "\nfindLast():\n";
$element = $doc40->findLast('.item');
if ($element) {
    echo "最后一个元素: " . trim($element->text()) . "\n";
}

// findRange - 查找指定范围内的元素
echo "\nfindRange(0, 3):\n";
$elements = $doc40->findRange('.item', 0, 3);
echo "找到 " . count($elements) . " 个元素:\n";
foreach ($elements as $i => $el) {
    echo "  [$i] " . trim($el->text()) . "\n";
}

// ==================== 示例 40: 选择器数组回退查找 ====================

echo "\n--- 示例 40: 选择器数组回退查找 ---\n\n";

$html41 = '<!DOCTYPE html>
<html>
<head><title>回退查找示例</title></head>
<body>
    <div class="main-content">
        <h1 class="page-title">新版标题</h1>
        <p>这是新版的内容。</p>
    </div>
    <div id="content">
        <h2 class="article-title">旧版标题</h2>
    </div>
    <a href="https://example.com" class="external-link">外部链接</a>
    <span class="date">2026-01-15</span>
</body>
</html>';

$doc41 = new Document($html41);

// 使用 findWithFallback 应对不同网页结构
echo "使用回退查找获取标题:\n";
$titles = $doc41->findWithFallback([
    ['selector' => '.page-title'],                           // 尝试新版
    ['selector' => '.article-title'],                         // 尝试旧版
    ['selector' => '//h1[@class="page-title"]', 'type' => 'xpath'], // XPath
    ['selector' => 'h1, h2']                                  // 通用选择器
]);
if (!empty($titles)) {
    echo "找到标题: " . trim($titles[0]->text()) . "\n";
} else {
    echo "未找到标题\n";
}

// 混合使用 CSS 和 XPath
echo "\n混合使用多种选择器:\n";
$elements = $doc41->findWithFallback([
    ['selector' => '.main-content > h1'],                     // CSS 子选择器
    ['selector' => '//div[@class="main-content"]/h1', 'type' => 'xpath'], // XPath
    ['selector' => '/html/body/div/h1', 'type' => 'xpath']    // XPath 绝对路径
]);
if (!empty($elements)) {
    echo "找到元素: " . trim($elements[0]->text()) . "\n";
}

// 使用正则表达式作为最后备选
echo "\n使用正则表达式查找日期:\n";
$dates = $doc41->findWithFallback([
    ['selector' => 'time.date'],
    ['selector' => '[data-date]'],
    ['selector' => '.date'],                                  // 匹配到
    ['selector' => '/\d{4}-\d{2}-\d{2}/', 'type' => 'regex']
]);
if (!empty($dates)) {
    echo "找到日期: " . trim($dates[0]->text()) . "\n";
}

// 查找外部链接
echo "\n查找外部链接:\n";
$links = $doc41->findWithFallback([
    ['selector' => 'a.external-link'],
    ['selector' => 'a[href^="https"]'],
    ['selector' => '/^https:\/\//', 'type' => 'regex', 'attribute' => 'href']
]);
if (!empty($links)) {
    echo "找到 " . count($links) . " 个外部链接\n";
    foreach ($links as $link) {
        echo "  - " . $link->attr('href') . "\n";
    }
}

// 使用 findFirstWithFallback
echo "\n使用 findFirstWithFallback:\n";
$titleElement = $doc41->findFirstWithFallback([
    ['selector' => 'h1.page-title'],
    ['selector' => 'h2.article-title'],
    ['selector' => '//h1|//h2', 'type' => 'xpath']
]);
if ($titleElement) {
    echo "首个标题元素: " . trim($titleElement->text()) . " (" . $titleElement->tagName() . ")\n";
}

// 所有选择器都不匹配的情况
echo "\n所有选择器都不匹配:\n";
$results = $doc41->findWithFallback([
    ['selector' => '.not-exist-1'],
    ['selector' => '.not-exist-2'],
    ['selector' => '//div[@class="wrong"]', 'type' => 'xpath']
]);
echo "找到元素: " . count($results) . " 个\n";

// ==================== 示例 41: 智能选择器类型检测 ====================

echo "\n--- 示例 41: 智能选择器类型检测 ---\n\n";

// Query::detectSelectorType() 示例
echo "自动检测选择器类型:\n";
$testSelectors = [
    'div.container',
    '.active',
    '#main',
    'ul > li',
    '//div[@class="item"]',
    '/html/body/div[1]',
    '//a[contains(@href, "example")]',
    '/\d{4}-\d{2}-\d{2}/',
    '/test.*/i'
];

foreach ($testSelectors as $sel) {
    $type = Query::detectSelectorType($sel);
    echo "  '$sel' -> $type\n";
}

// Query::isXPathAbsolute() 示例
echo "\n检测 XPath 绝对路径:\n";
$absoluteTests = [
    '/html/body/div' => true,
    '/html/body//div[@class="item"]' => true,
    '//div' => false,
    'div.container' => false,
    '.class' => false
];

foreach ($absoluteTests as $path => $expected) {
    $result = Query::isXPathAbsolute($path);
    $status = $result === $expected ? '✓' : '✗';
    echo "  $status isXPathAbsolute('$path'): " . ($result ? 'true' : 'false') . "\n";
}

// Query::isXPathRelative() 示例
echo "\n检测 XPath 相对路径:\n";
$relativeTests = [
    '//div[@class="item"]' => true,
    '//a[@href]' => true,
    '//ul/li' => true,
    '/html/body' => false,
    'div' => false
];

foreach ($relativeTests as $path => $expected) {
    $result = Query::isXPathRelative($path);
    $status = $result === $expected ? '✓' : '✗';
    echo "  $status isXPathRelative('$path'): " . ($result ? 'true' : 'false') . "\n";
}

// ==================== 总结 ====================

echo "\n=== 示例完成 ===\n";
echo "以上示例展示了 zxf/utils Dom 库的主要功能和使用方法。\n";
echo "包括 100+ 种选择器的完整支持。\n";
echo "更多信息请参考 README.md 文件和 RULE_GUIDE.md 文件。\n";

