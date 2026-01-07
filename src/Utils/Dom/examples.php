<?php

/**
 * zxf/dom 使用示例集合
 *
 * 本文件包含各种实际使用场景的示例代码
 * 展示了库的主要功能和最佳实践
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/Query.php';
require_once __DIR__ . '/Document.php';
require_once __DIR__ . '/Node.php';
require_once __DIR__ . '/Element.php';
require_once __DIR__ . '/ClassAttribute.php';
require_once __DIR__ . '/StyleAttribute.php';
require_once __DIR__ . '/DocumentFragment.php';
require_once __DIR__ . '/Encoder.php';
require_once __DIR__ . '/Errors.php';
require_once __DIR__ . '/Exceptions/InvalidSelectorException.php';

use zxf\Utils\Dom\Document;
use zxf\Utils\Dom\Query;
use zxf\Utils\Dom\Element;

// 初始化 Query
Query::initialize();

echo "=== zxf/dom 使用示例 ===\n\n";

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

// ==================== 示例 15: 错误处理 ====================

echo "\n--- 示例 15: 错误处理 ---\n\n";

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

} catch (Exception $e) {
    echo "发生错误: " . $e->getMessage() . "\n";
}

// ==================== 总结 ====================

echo "\n=== 示例完成 ===\n";
echo "以上示例展示了 zxf/dom 库的主要功能和使用方法。\n";
echo "更多信息请参考 README.md 文件。\n";
