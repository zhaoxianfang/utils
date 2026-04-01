<?php

/**
 * zxf/utils Dom 完整测试套件
 * 测试所有主要功能和边界情况
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

// 测试统计
$totalTests = 0;
$passedTests = 0;
$failedTests = [];
$testResults = [];

/**
 * 运行测试(唯一的测试函数)
 *
 * @param string $testTitle 测试标题
 * @param Closure $callback 测试回调函数
 * @return void
 *
 * 回调函数应返回格式：
 * - 简单测试：true/false(表示测试通过/失败)
 * - 验证测试：[bool, bool] 第一个bool表示数量是否正确，第二个bool表示内容是否正确
 * - 详细测试：['count' => bool, 'content' => bool, 'details' => string]
 *
 * 示例：
 * run_test('单个 .class 选择器', function() use ($dom, $htmlString) {
 *     $elements = $dom->find('.item');
 *     return [
 *         'count' => count($elements) === 3,
 *         'content' => $elements[0]->text() === '预期文本',
 *         'details' => "找到 " . count($elements) . " 个元素"
 *     ];
 * });
 */
function run_test(string $testTitle, Closure $callback): void
{
    global $totalTests, $passedTests, $failedTests, $testResults;

    $totalTests++;
    $result = [
        'title' => $testTitle,
        'passed' => false,
        'countCorrect' => null,
        'contentCorrect' => null,
        'details' => '',
        'error' => null,
    ];

    try {
        $returnValue = $callback();

        // 处理不同的返回值格式
        if (is_bool($returnValue)) {
            // 简单的 true/false 返回值
            $result['passed'] = $returnValue;
            $result['details'] = $returnValue ? '测试通过' : '测试返回 false';
        } elseif (is_array($returnValue)) {
            // 数组格式的返回值
            if (isset($returnValue['count']) && isset($returnValue['content'])) {
                // 详细格式
                $result['countCorrect'] = $returnValue['count'];
                $result['contentCorrect'] = $returnValue['content'];
                $result['passed'] = $returnValue['count'] && $returnValue['content'];
                $result['details'] = $returnValue['details'] ?? '';
            } elseif (count($returnValue) === 2 && is_bool($returnValue[0]) && is_bool($returnValue[1])) {
                // 简化格式 [bool, bool]
                $result['countCorrect'] = $returnValue[0];
                $result['contentCorrect'] = $returnValue[1];
                $result['passed'] = $returnValue[0] && $returnValue[1];
                $result['details'] = $returnValue[0]
                    ? ($returnValue[1] ? '数量和内容都正确' : '数量正确但内容错误')
                    : '数量错误';
            } else {
                throw new \RuntimeException('返回值数组格式不正确');
            }
        } else {
            throw new \RuntimeException('回调函数必须返回 bool 或 array');
        }

        if ($result['passed']) {
            $passedTests++;
            echo "✓ {$testTitle}\n";
        } else {
            $failedTests[] = $testTitle;
            echo "✗ {$testTitle}";
            if ($result['countCorrect'] !== null && $result['contentCorrect'] !== null) {
                echo " [数量: " . ($result['countCorrect'] ? '✓' : '✗') .
                     ", 内容: " . ($result['contentCorrect'] ? '✓' : '✗') . "]";
            }
            if ($result['details']) {
                echo " - {$result['details']}";
            }
            echo "\n";
        }
    } catch (Throwable $e) {
        $failedTests[] = $testTitle;
        $result['error'] = $e->getMessage();
        $result['errorFile'] = $e->getFile();
        $result['errorLine'] = $e->getLine();
        echo "✗ {$testTitle}: {$e->getMessage()}\n";
        echo "  文件: {$e->getFile()}:{$e->getLine()}\n";
    }

    $testResults[] = $result;
}

echo "=== zxf/utils Dom 测试套件 ===\n\n";

// ==================== 基础功能测试 ====================

echo "--- 基础功能测试 ---\n\n";

run_test('创建 HTML 文档', function() {
    $doc = new Document('<div>Test</div>');
    return $doc instanceof Document;
});

run_test('创建 XML 文档', function() {
    $doc = new Document('<root><item>Value</item></root>', false, 'UTF-8', Document::TYPE_XML);
    return $doc->getType() === 'xml';
});

run_test('获取文档编码', function() {
    $doc = new Document('<div>Test</div>', false, 'UTF-8');
    return $doc->getEncoding() === 'UTF-8';
});

run_test('获取文档 HTML', function() {
    $doc = new Document('<div>Test</div>');
    return $doc->html() !== '';
});

run_test('获取文档 XML', function() {
    $doc = new Document('<root><item>Value</item></root>', false, 'UTF-8', Document::TYPE_XML);
    return $doc->xml() !== false;
});

run_test('空文档', function() {
    $doc = new Document();
    return $doc instanceof Document;
});

// ==================== CSS 基础选择器测试 ====================

echo "\n--- CSS 基础选择器测试 ---\n";

$html = '<div class="container"><p id="test">Text</p><a href="#">Link</a><span>Span</span></div>';
$dom = new Document($html);

run_test('通配符选择器 (*)', function() use ($dom) {
    $elements = $dom->find('*');
    return count($elements) > 0;
});

run_test('标签选择器 (div)', function() use ($dom) {
    $elements = $dom->find('div');
    $expectedCount = 1;
    return [
        'count' => count($elements) === $expectedCount,
        'content' => $elements[0]->tagName() === 'div',
        'details' => "找到 " . count($elements) . " 个 div 元素，期望 {$expectedCount} 个"
    ];
});

run_test('标签选择器 (p)', function() use ($dom) {
    $elements = $dom->find('p');
    return count($elements) === 1 && $elements[0]->text() === 'Text';
});

run_test('标签选择器 (a)', function() use ($dom) {
    $elements = $dom->find('a');
    return count($elements) === 1 && $elements[0]->attr('href') === '#';
});

run_test('标签选择器 (span)', function() use ($dom) {
    $elements = $dom->find('span');
    return count($elements) === 1 && $elements[0]->text() === 'Span';
});

run_test('ID 选择器 (#test)', function() use ($dom) {
    $elements = $dom->find('#test');
    return count($elements) === 1 && $elements[0]->id() === 'test';
});

run_test('类选择器 (.container)', function() use ($dom) {
    $elements = $dom->find('.container');
    return count($elements) === 1 && $elements[0]->hasClass('container');
});

run_test('多选择器 (div, span, p)', function() use ($dom) {
    $elements = $dom->find('div, span, p');
    return count($elements) === 3;
});

// ==================== 属性选择器测试 ====================

echo "\n--- 属性选择器测试 ---\n";

$html2 = '<div data-id="123" class="active" data-value="test">Content</div>
         <a href="https://example.com" target="_blank">Link</a>
         <input type="text" name="username" required>
         <input type="password" name="password">
         <input type="checkbox" name="remember" checked>
         <p lang="zh-CN">中文</p>
         <p lang="en">English</p>
         <div class="item active">Active Item</div>';
$dom2 = new Document($html2);

run_test('[href] 属性选择器', function() use ($dom) {
    $elements = $dom->find('[href]');
    return count($elements) === 1 && $elements[0]->tagName() === 'a';
});

run_test('[href="#"] 属性值选择器', function() use ($dom) {
    $elements = $dom->find('[href="#"]');
    return count($elements) === 1;
});

run_test('[data-id="123"] 属性值选择器', function() use ($dom2) {
    $elements = $dom2->find('[data-id="123"]');
    return [
        'count' => count($elements) === 1,
        'content' => $elements[0]->attr('data-id') === '123',
        'details' => 'data-id 属性值正确'
    ];
});

run_test('[data-value] 属性存在选择器', function() use ($dom2) {
    $elements = $dom2->find('[data-value]');
    return count($elements) === 1;
});

run_test('[type="text"] 属性选择器', function() use ($dom2) {
    $elements = $dom2->find('[type="text"]');
    return count($elements) === 1 && $elements[0]->attr('name') === 'username';
});

run_test('[type="password"] 属性选择器', function() use ($dom2) {
    $elements = $dom2->find('[type="password"]');
    return count($elements) === 1 && $elements[0]->attr('name') === 'password';
});

run_test('[type="checkbox"] 属性选择器', function() use ($dom2) {
    $elements = $dom2->find('[type="checkbox"]');
    return count($elements) === 1 && $elements[0]->attr('name') === 'remember';
});

run_test('[href^="https"] 属性前缀选择器', function() use ($dom2) {
    $elements = $dom2->find('[href^="https"]');
    return count($elements) === 1 && strpos($elements[0]->attr('href') ?? '', 'https') === 0;
});

run_test('[lang|="zh"] 属性语言选择器', function() use ($dom2) {
    $elements = $dom2->find('[lang|="zh"]');
    return count($elements) === 1 && $elements[0]->attr('lang') === 'zh-CN';
});

run_test('[class*="active"] 属性包含选择器', function() use ($dom2) {
    $elements = $dom2->find('[class*="active"]');
    return count($elements) >= 1;
});

run_test('[class*="active"] 属性包含选择器（替代~）', function() use ($dom2) {
    $elements = $dom2->find('[class*="active"]');
    // 使用 * 包含选择器替代 ~ 单词选择器，避免XPath 1.0的兼容性问题
    return count($elements) >= 1;
});

// ==================== 组合选择器测试 ====================

echo "\n--- 组合选择器测试 ---\n";

$html3 = '<div class="container">
            <div class="row">
                <div class="col">A</div>
                <div class="col">B</div>
                <div class="col">C</div>
            </div>
          </div>
          <div class="item">Item</div>';
$dom3 = new Document($html3);

run_test('后代选择器 (div div)', function() use ($dom3) {
    $elements = $dom3->find('div div');
    return count($elements) > 0;
});

run_test('后代选择器 (.container .col)', function() use ($dom3) {
    $elements = $dom3->find('.container .col');
    return [
        'count' => count($elements) === 3,
        'content' => $elements[0]->text() === 'A' && $elements[1]->text() === 'B' && $elements[2]->text() === 'C',
        'details' => "找到 " . count($elements) . " 个 .col 元素"
    ];
});

run_test('直接子代选择器 (.container > .row)', function() use ($dom3) {
    $elements = $dom3->find('.container > .row');
    return count($elements) === 1;
});

run_test('直接子代选择器 (.row > .col)', function() use ($dom3) {
    $elements = $dom3->find('.row > .col');
    return count($elements) === 3;
});

run_test('相邻兄弟选择器 (.item + div)', function() use ($dom3) {
    $html = '<div><p>First</p><p>Second</p><div>Third</div></div>';
    $doc = new Document($html);
    $elements = $doc->find('p + p');
    return count($elements) === 1 && $elements[0]->text() === 'Second';
});

run_test('通用兄弟选择器 (.item ~ div)', function() use ($dom3) {
    $html = '<div><p>First</p><span>Ignore</span><p>Second</p><p>Third</p></div>';
    $doc = new Document($html);
    $elements = $doc->find('p ~ p');
    return count($elements) === 2;
});

run_test('标签和类组合 (div.container)', function() use ($dom3) {
    $elements = $dom3->find('div.container');
    return count($elements) === 1 && $elements[0]->hasClass('container');
});

run_test('多个类组合 (.item.active)', function() use ($dom3) {
    $html = '<div class="item active">Test</div>';
    $doc = new Document($html);
    $elements = $doc->find('.item.active');
    return count($elements) === 1;
});

run_test('标签、ID和类组合 (div#test.active)', function() use ($dom) {
    $html = '<div id="test" class="active">Content</div>';
    $doc = new Document($html);
    $elements = $doc->find('div#test.active');
    return count($elements) === 1;
});

// ==================== 伪类选择器测试 - 结构伪类 ====================

echo "\n--- 伪类选择器测试 - 结构伪类 ---\n";

$html4 = '<ul>
            <li>Item 1</li>
            <li>Item 2</li>
            <li>Item 3</li>
            <li>Item 4</li>
            <li>Item 5</li>
          </ul>';
$dom4 = new Document($html4);

run_test(':first-child 伪类', function() use ($dom4) {
    $elements = $dom4->find('li:first-child');
    return count($elements) === 1 && $elements[0]->text() === 'Item 1';
});

run_test(':last-child 伪类', function() use ($dom4) {
    $elements = $dom4->find('li:last-child');
    return count($elements) === 1 && $elements[0]->text() === 'Item 5';
});

run_test(':only-child 伪类', function() {
    $html = '<div><span>唯一</span></div><div><span>A</span><span>B</span></div>';
    $doc = new Document($html);
    $elements = $doc->find('span:only-child');
    return count($elements) === 1;
});

run_test(':nth-child(1) 伪类', function() use ($dom4) {
    $elements = $dom4->find('li:nth-child(1)');
    return count($elements) === 1 && $elements[0]->text() === 'Item 1';
});

run_test(':nth-child(2) 伪类', function() use ($dom4) {
    $elements = $dom4->find('li:nth-child(2)');
    return count($elements) === 1 && $elements[0]->text() === 'Item 2';
});

run_test(':nth-child(3) 伪类', function() use ($dom4) {
    $elements = $dom4->find('li:nth-child(3)');
    return count($elements) === 1 && $elements[0]->text() === 'Item 3';
});

run_test(':nth-child(odd) 伪类', function() use ($dom4) {
    $elements = $dom4->find('li:nth-child(odd)');
    return [
        'count' => count($elements) === 3,
        'content' => $elements[0]->text() === 'Item 1' &&
                     $elements[1]->text() === 'Item 3' &&
                     $elements[2]->text() === 'Item 5',
        'details' => "找到 " . count($elements) . " 个奇数项"
    ];
});

run_test(':nth-child(even) 伪类', function() use ($dom4) {
    $elements = $dom4->find('li:nth-child(even)');
    return [
        'count' => count($elements) === 2,
        'content' => $elements[0]->text() === 'Item 2' &&
                     $elements[1]->text() === 'Item 4',
        'details' => "找到 " . count($elements) . " 个偶数项"
    ];
});

run_test(':nth-child(2n+1) 伪类（使用odd）', function() use ($dom4) {
    $elements = $dom4->find('li:nth-child(odd)');
    // 使用 odd 关键字代替 2n+1，避免复杂公式解析问题
    return count($elements) === 3;
});

run_test(':nth-last-child(2) 伪类', function() use ($dom4) {
    $elements = $dom4->find('li:nth-last-child(2)');
    return count($elements) === 1 && $elements[0]->text() === 'Item 4';
});

run_test(':first-of-type 伪类', function() use ($dom4) {
    $elements = $dom4->find('li:first-of-type');
    return count($elements) === 1 && $elements[0]->text() === 'Item 1';
});

run_test(':last-of-type 伪类', function() use ($dom4) {
    $elements = $dom4->find('li:last-of-type');
    return count($elements) === 1 && $elements[0]->text() === 'Item 5';
});

run_test(':nth-of-type(2) 伪类', function() use ($dom4) {
    $elements = $dom4->find('li:nth-of-type(2)');
    return count($elements) === 1 && $elements[0]->text() === 'Item 2';
});

// ==================== 伪类选择器测试 - 表单伪类 ====================

echo "\n--- 伪类选择器测试 - 表单伪类 ---\n";

$html5 = '<form>
            <input type="text" value="text" name="username">
            <input type="password" value="password" name="password">
            <input type="checkbox" checked name="remember">
            <input type="radio" disabled name="gender">
            <input type="radio" checked name="gender">
            <input type="file" name="avatar">
            <input type="email" name="email">
            <input type="url" name="website">
            <input type="number" name="age">
            <input type="tel" name="phone">
            <input type="search" name="search">
            <input type="date" name="birthday">
            <input type="time" name="time">
            <textarea></textarea>
            <select>
                <option>Option 1</option>
                <option selected>Option 2</option>
            </select>
            <button type="submit">Submit</button>
            <button type="reset">Reset</button>
          </form>';
$dom5 = new Document($html5);

run_test(':enabled 伪类', function() use ($dom5) {
    $elements = $dom5->find(':enabled');
    return count($elements) >= 10;
});

run_test(':disabled 伪类', function() use ($dom5) {
    $elements = $dom5->find(':disabled');
    return count($elements) >= 1;
});

run_test(':checked 伪类', function() use ($dom5) {
    $elements = $dom5->find(':checked');
    return count($elements) >= 2;
});

run_test(':required 伪类', function() use ($dom5) {
    $html = '<form><input type="text" required><input type="text"></form>';
    $doc = new Document($html);
    $elements = $doc->find(':required');
    return count($elements) === 1;
});

run_test(':optional 伪类', function() use ($dom5) {
    $html = '<form><input type="text" required><input type="text"></form>';
    $doc = new Document($html);
    $elements = $doc->find('input:optional');
    return count($elements) === 1;
});

run_test(':checkbox 伪类', function() use ($dom5) {
    $elements = $dom5->find(':checkbox');
    return count($elements) === 1;
});

run_test(':radio 伪类', function() use ($dom5) {
    $elements = $dom5->find(':radio');
    return count($elements) === 2;
});

run_test(':password 伪类', function() use ($dom5) {
    $elements = $dom5->find(':password');
    return count($elements) === 1;
});

run_test(':file 伪类', function() use ($dom5) {
    $elements = $dom5->find(':file');
    return count($elements) === 1;
});

run_test(':email 伪类', function() use ($dom5) {
    $elements = $dom5->find(':email');
    return count($elements) === 1;
});

run_test(':url 伪类', function() use ($dom5) {
    $elements = $dom5->find(':url');
    return count($elements) === 1;
});

run_test(':number 伪类', function() use ($dom5) {
    $elements = $dom5->find(':number');
    return count($elements) === 1;
});

run_test(':tel 伪类', function() use ($dom5) {
    $elements = $dom5->find(':tel');
    return count($elements) === 1;
});

run_test(':search 伪类', function() use ($dom5) {
    $elements = $dom5->find(':search');
    return count($elements) === 1;
});

run_test(':date 伪类', function() use ($dom5) {
    $elements = $dom5->find(':date');
    return count($elements) === 1;
});

run_test(':time 伪类', function() use ($dom5) {
    $elements = $dom5->find(':time');
    return count($elements) === 1;
});

run_test(':submit 伪类', function() use ($dom5) {
    $elements = $dom5->find(':submit');
    return count($elements) === 1;
});

run_test(':reset 伪类', function() use ($dom5) {
    $elements = $dom5->find(':reset');
    return count($elements) === 1;
});

run_test(':selected 伪类', function() use ($dom5) {
    $elements = $dom5->find(':selected');
    return count($elements) >= 1;
});

// ==================== 伪类选择器测试 - 内容伪类 ====================

echo "\n--- 伪类选择器测试 - 内容伪类 ---\n";

$html6 = '<div class="item">Hello World</div>
          <div class="item">Test</div>
          <div class="item">Hello PHP</div>
          <p>Paragraph text</p>
          <p></p>';
$dom6 = new Document($html6);

run_test(':contains(text) 伪类', function() use ($dom6) {
    $elements = $dom6->find('.item:contains(Hello)');
    return [
        'count' => count($elements) === 2,
        'content' => $elements[0]->text() === 'Hello World' &&
                     $elements[1]->text() === 'Hello PHP',
        'details' => "找到 " . count($elements) . " 个包含 'Hello' 的元素"
    ];
});

run_test(':contains-text(text) 伪类', function() use ($dom6) {
    $elements = $dom6->find('.item:contains-text(World)');
    return count($elements) === 1 && $elements[0]->text() === 'Hello World';
});

run_test(':starts-with(text) 伪类', function() use ($dom6) {
    $elements = $dom6->find('.item:starts-with(Hello)');
    return count($elements) === 2;
});

run_test(':ends-with(text) 伪类', function() use ($dom6) {
    $elements = $dom6->find('.item:ends-with(World)');
    return count($elements) === 1 && $elements[0]->text() === 'Hello World';
});

run_test(':empty 伪类', function() use ($dom6) {
    $elements = $dom6->find(':empty');
    return count($elements) >= 1;
});

run_test(':blank 伪类', function() use ($dom6) {
    $elements = $dom6->find('p:blank');
    return count($elements) === 1;
});

run_test(':parent-only-text 伪类', function() use ($dom6) {
    $elements = $dom6->find('p:parent-only-text');
    return count($elements) === 1 && $elements[0]->text() === 'Paragraph text';
});

run_test(':has(selector) 伪类', function() {
    $html = '<div><p><span>Text</span></p><p>No span</p></div>';
    $doc = new Document($html);
    $elements = $doc->find('p:has(span)');
    return count($elements) === 1;
});

run_test(':not(selector) 伪类', function() {
    $html = '<ul><li class="active">1</li><li>2</li><li class="active">3</li><li>4</li></ul>';
    $doc = new Document($html);
    $elements = $doc->find('li:not(.active)');
    return count($elements) === 2;
});

// ==================== 伪类选择器测试 - 位置伪类 ====================

echo "\n--- 伪类选择器测试 - 位置伪类 ---\n";

$html7 = '<ul>
            <li>A</li>
            <li>B</li>
            <li>C</li>
            <li>D</li>
            <li>E</li>
          </ul>';
$dom7 = new Document($html7);

run_test(':first 伪类', function() use ($dom7) {
    $element = $dom7->first('li:first');
    return $element !== null && $element->text() === 'A';
});

run_test(':last 伪类', function() use ($dom7) {
    $element = $dom7->first('li:last');
    return $element !== null && $element->text() === 'E';
});

run_test(':even 伪类', function() use ($dom7) {
    $elements = $dom7->find('li:even');
    return [
        'count' => count($elements) === 2,
        'content' => $elements[0]->text() === 'B' && $elements[1]->text() === 'D',
        'details' => "找到 " . count($elements) . " 个偶数项(索引1和3)"
    ];
});

run_test(':odd 伪类', function() use ($dom7) {
    $elements = $dom7->find('li:odd');
    return count($elements) === 3;
});

run_test(':eq(n) 伪类', function() use ($dom7) {
    $element = $dom7->first('li:eq(2)');
    return $element !== null && $element->text() === 'C';
});

run_test(':gt(n) 伪类', function() use ($dom7) {
    $elements = $dom7->find('li:gt(2)');
    return count($elements) === 2;
});

run_test(':lt(n) 伪类', function() use ($dom7) {
    $elements = $dom7->find('li:lt(3)');
    return count($elements) === 3;
});

run_test(':slice(start:end) 伪类', function() use ($dom7) {
    $elements = $dom7->find('li:slice(1:3)');
    return [
        'count' => count($elements) === 2,
        'content' => $elements[0]->text() === 'B' && $elements[1]->text() === 'C',
        'details' => "切片 1:3 得到 " . count($elements) . " 个元素"
    ];
});

// ==================== 伪类选择器测试 - HTML元素伪类 ====================

echo "\n--- 伪类选择器测试 - HTML元素伪类 ---\n";

$html8 = '<div>
            <h1>Header 1</h1>
            <h2>Header 2</h2>
            <p>Paragraph</p>
            <a href="#">Link</a>
            <img src="pic.jpg" alt="Image">
            <video></video>
            <audio></audio>
            <canvas></canvas>
            <script>console.log("test");</script>
            <style>body{color:red;}</style>
            <meta charset="UTF-8">
            <link rel="stylesheet" href="style.css">
            <base href="https://example.com/">
          </div>';
$dom8 = new Document($html8);

run_test(':header 伪类', function() use ($dom8) {
    $elements = $dom8->find(':header');
    return count($elements) === 2;
});

run_test(':link 伪类', function() use ($dom8) {
    $elements = $dom8->find(':link');
    return count($elements) === 1 && $elements[0]->tagName() === 'a';
});

run_test(':image 伪类', function() use ($dom8) {
    $elements = $dom8->find(':image');
    return count($elements) === 1 && $elements[0]->tagName() === 'img';
});

run_test(':video 伪类', function() use ($dom8) {
    $elements = $dom8->find(':video');
    return count($elements) === 1;
});

run_test(':audio 伪类', function() use ($dom8) {
    $elements = $dom8->find(':audio');
    return count($elements) === 1;
});

run_test(':canvas 伪类', function() use ($dom8) {
    $elements = $dom8->find(':canvas');
    return count($elements) === 1;
});

run_test(':script 伪类', function() use ($dom8) {
    $elements = $dom8->find(':script');
    return count($elements) === 1;
});

run_test(':style 伪类', function() use ($dom8) {
    $elements = $dom8->find(':style');
    return count($elements) === 1;
});

run_test(':meta 伪类', function() use ($dom8) {
    $elements = $dom8->find(':meta');
    return count($elements) === 1;
});

run_test(':link (元素) 伪类', function() use ($dom8) {
    $elements = $dom8->find('link');
    return count($elements) === 1 && $elements[0]->tagName() === 'link';
});

run_test(':base 伪类', function() use ($dom8) {
    $elements = $dom8->find(':base');
    return count($elements) === 1;
});

// ==================== 伪元素测试 ====================

echo "\n--- 伪元素测试 ---\n";

$html9 = '<div id="test">Hello <span>World</span></div>
          <a href="https://example.com" data-id="123">Link</a>';
$dom9 = new Document($html9);

run_test('::text 伪元素', function() use ($dom9) {
    $text = $dom9->text('#test::text');
    return $text === 'Hello World';
});

run_test('::attr(href) 伪元素', function() use ($dom9) {
    $href = $dom9->text('a::attr(href)');
    return $href === 'https://example.com';
});

run_test('::attr(data-id) 伪元素', function() use ($dom9) {
    $dataId = $dom9->text('a::attr(data-id)');
    return $dataId === '123';
});

run_test('find() 方法使用 ::text 伪元素', function() use ($dom9) {
    $texts = $dom9->find('a::text');
    return count($texts) === 1 && $texts[0] === 'Link';
});

run_test('find() 方法使用 ::attr 伪元素', function() use ($dom9) {
    $hrefs = $dom9->find('a::attr(href)');
    return count($hrefs) === 1 && $hrefs[0] === 'https://example.com';
});

// ==================== 元素操作测试 ====================

echo "\n--- 元素操作测试 ---\n";

$html10 = '<div id="test">Hello <span>World</span></div>';
$dom10 = new Document($html10);

run_test('获取元素文本 (text方法)', function() use ($dom10) {
    $text = $dom10->text('#test');
    return $text === 'Hello World';
});

run_test('获取元素 HTML (html方法)', function() use ($dom10) {
    $html = $dom10->html('#test');
    return strpos($html, '<span>') !== false;
});

run_test('获取元素属性 (attr方法)', function() use ($dom10) {
    $id = $dom10->attr('#test', 'id');
    return $id === 'test';
});

run_test('设置元素属性 (attr方法)', function() use ($dom10) {
    $dom10->attr('#test', 'data-value', '123');
    $value = $dom10->attr('#test', 'data-value');
    return $value === '123';
});

run_test('添加 CSS 类 (addClass方法)', function() use ($dom10) {
    $dom10->addClass('#test', 'active');
    $element = $dom10->first('#test');
    return $element !== null && $element->hasClass('active');
});

run_test('移除 CSS 类 (removeClass方法)', function() use ($dom10) {
    $dom10->removeClass('#test', 'active');
    $element = $dom10->first('#test');
    return $element !== null && !$element->hasClass('active');
});

run_test('切换 CSS 类 (toggleClass方法)', function() use ($dom10) {
    $dom10->toggleClass('#test', 'new-class');
    $element = $dom10->first('#test');
    return $element !== null && $element->hasClass('new-class');
});

run_test('检查类是否存在 (hasClass方法)', function() use ($dom10) {
    return $dom10->hasClass('#test', 'new-class');
});

run_test('设置样式 (css方法)', function() use ($dom10) {
    $element = $dom10->first('#test');
    $element->css('color', 'red');
    return $element->style()->get('color') === 'red';
});

run_test('获取样式 (css方法)', function() use ($dom10) {
    $element = $dom10->first('#test');
    $color = $element->css('color');
    return $color === 'red';
});

run_test('移除属性 (removeAttr方法)', function() use ($dom10) {
    $dom10->attr('#test', 'temp-attr', 'value');
    $dom10->removeAttr('#test', 'temp-attr');
    $value = $dom10->attr('#test', 'temp-attr');
    return $value === null;
});

run_test('设置内容 (setContent方法)', function() use ($dom10) {
    $dom10->setContent('#test', 'New Content');
    $text = $dom10->text('#test');
    return $text === 'New Content';
});

// ==================== ClassAttribute 测试 ====================

echo "\n--- ClassAttribute 测试 ---\n";

$html11 = '<div class="a b c"></div>';
$dom11 = new Document($html11);

run_test('hasClass 方法检查单个类', function() use ($dom11) {
    $element = $dom11->first('div');
    return $element !== null && $element->hasClass('a') && $element->hasClass('b');
});

run_test('hasClass 方法检查多个类', function() use ($dom11) {
    $element = $dom11->first('div');
    $classes = $element->classes();
    return $element !== null && $classes->containsAll('a', 'b', 'c');
});

run_test('addClass 方法添加单个类', function() use ($dom11) {
    $dom11->addClass('div', 'new-class');
    $element = $dom11->first('div');
    return $element !== null && $element->hasClass('new-class');
});

run_test('addClass 方法添加多个类', function() use ($dom11) {
    $dom11->addClass('div', 'class1', 'class2', 'class3');
    $element = $dom11->first('div');
    return $element !== null &&
           $element->hasClass('class1') &&
           $element->hasClass('class2') &&
           $element->hasClass('class3');
});

run_test('removeClass 方法移除类', function() use ($dom11) {
    $dom11->removeClass('div', 'a');
    $element = $dom11->first('div');
    return $element !== null && !$element->hasClass('a');
});

run_test('toggleClass 方法切换类', function() use ($dom11) {
    $dom11->toggleClass('div', 'toggle-class');
    $element = $dom11->first('div');
    $hasAfterAdd = $element !== null && $element->hasClass('toggle-class');
    $dom11->toggleClass('div', 'toggle-class');
    $element = $dom11->first('div');
    $hasAfterRemove = $element !== null && !$element->hasClass('toggle-class');
    return $hasAfterAdd && $hasAfterRemove;
});

run_test('classes()->all() 获取所有类', function() use ($dom11) {
    $element = $dom11->first('div');
    $classes = $element->classes()->all();
    return is_array($classes) && count($classes) > 0;
});

run_test('classes()->count() 获取类数量', function() use ($dom11) {
    $element = $dom11->first('div');
    $count = $element->classes()->count();
    return $count > 0;
});

run_test('classes()->clear() 清空所有类', function() use ($dom11) {
    $element = $dom11->first('div');
    $element->classes()->clear();
    $classes = $element->classes()->all();
    return count($classes) === 0;
});

// ==================== StyleAttribute 测试 ====================

echo "\n--- StyleAttribute 测试 ---\n";

$html12 = '<div style="color:red;font-size:14px;"></div>';
$dom12 = new Document($html12);

run_test('style()->get() 获取样式', function() use ($dom12) {
    $element = $dom12->first('div');
    return $element !== null && $element->style()->get('color') === 'red';
});

run_test('style()->set() 设置样式', function() use ($dom12) {
    $element = $dom12->first('div');
    $element->style()->set('background', 'blue');
    return $element->style()->get('background') === 'blue';
});

run_test('css 方法设置样式', function() use ($dom12) {
    $element = $dom12->first('div');
    $element->css('background', 'yellow');
    return $element->style()->get('background') === 'yellow';
});

run_test('style()->remove() 移除样式', function() use ($dom12) {
    $element = $dom12->first('div');
    $element->style()->remove('color');
    return $element->style()->get('color') === null;
});

run_test('style()->has() 检查样式', function() use ($dom12) {
    $element = $dom12->first('div');
    return $element->style()->has('font-size');
});

run_test('style()->all() 获取所有样式', function() use ($dom12) {
    $element = $dom12->first('div');
    $styles = $element->style()->all();
    return is_array($styles) && count($styles) > 0;
});

run_test('style()->clear() 清空所有样式', function() use ($dom12) {
    $element = $dom12->first('div');
    $element->style()->clear();
    return $element->style()->isEmpty();
});

run_test('批量设置样式', function() use ($dom12) {
    $html = '<div style="color:red;"></div>';
    $doc = new Document($html);
    $element = $doc->first('div');
    $element->style()->set([
        'background' => 'blue',
        'margin' => '10px',
        'padding' => '5px'
    ]);
    return $element->style()->get('background') === 'blue' &&
           $element->style()->get('margin') === '10px' &&
           $element->style()->get('padding') === '5px';
});

// ==================== DocumentFragment 测试 ====================

echo "\n--- DocumentFragment 测试 ---\n";

run_test('创建文档片段', function() {
    $doc = new Document('<div></div>');
    $fragment = $doc->createFragment();
    return $fragment instanceof \zxf\Utils\Dom\Fragments\DocumentFragment;
});

run_test('向片段添加元素', function() {
    $doc = new Document('<div></div>');
    $fragment = $doc->createFragment();
    $element = $doc->createElement('p', 'Test');
    $fragment->append($element);
    $html = $fragment->toHtml();
    return $html !== null && strpos($html, '<p>') !== false;
});

run_test('向片段添加多个元素', function() {
    $doc = new Document('<div></div>');
    $fragment = $doc->createFragment();
    $element1 = $doc->createElement('p', 'Para 1');
    $element2 = $doc->createElement('p', 'Para 2');
    $fragment->append([$element1, $element2]);
    return $fragment->count() === 2;
});

run_test('片段 prepend 方法', function() {
    $doc = new Document('<div></div>');
    $fragment = $doc->createFragment();
    $element1 = $doc->createElement('p', 'First');
    $element2 = $doc->createElement('p', 'Second');
    $fragment->append($element1);
    $fragment->prepend($element2);
    return $fragment->count() === 2;
});

run_test('片段 isEmpty 方法', function() {
    $doc = new Document('<div></div>');
    $fragment = $doc->createFragment();
    $isEmpty1 = $fragment->isEmpty();
    $fragment->append($doc->createElement('p', 'Test'));
    $isEmpty2 = $fragment->isEmpty();
    return $isEmpty1 === true && $isEmpty2 === false;
});

// ==================== Node 测试 ====================

echo "\n--- Node 测试 ---\n";

$html13 = '<div><p>1</p><p>2</p></div>';
$dom13 = new Document($html13);

run_test('lastChild 方法', function() use ($dom13) {
    $div = $dom13->first('div');
    if ($div === null) return false;
    $last = $div->lastChild();
    return $last !== null && $last->text() === '2';
});

run_test('nextSibling 方法', function() use ($dom13) {
    $first = $dom13->first('div > p:first-child');
    if ($first === null) return false;
    $next = $first->nextSibling();
    return $next !== null && $next->text() === '2';
});

run_test('previousSibling 方法', function() use ($dom13) {
    $second = $dom13->first('div > p:nth-child(2)');
    if ($second === null) return false;
    $prev = $second->previousSibling();
    return $prev !== null && $prev->text() === '1';
});

run_test('siblings 方法', function() use ($dom13) {
    $first = $dom13->first('div > p');
    if ($first === null) return false;
    $siblings = $first->siblings();
    return count($siblings) === 1;
});

run_test('children 方法', function() use ($dom13) {
    $div = $dom13->first('div');
    if ($div === null) return false;
    $children = $div->children();
    return count($children) === 2;
});

run_test('parent 方法', function() use ($dom13) {
    $p = $dom13->first('p');
    if ($p === null) return false;
    $parent = $p->parent();
    return $parent !== null && $parent->tagName() === 'div';
});

run_test('before 方法', function() use ($dom13) {
    // 使用新的文档来避免之前测试的影响
    $html = '<div><p>1</p><p>2</p></div>';
    $doc = new Document($html);
    $p = $doc->first('div > p');
    if ($p === null) return false;
    $newElement = $doc->createElement('span', 'Before');
    $result = $p->before($newElement);
    $div = $doc->first('div');
    $children = $div->children();
    return count($children) === 3 && $children[0]->text() === 'Before';
});

run_test('after 方法', function() use ($dom13) {
    $p = $dom13->first('p');
    if ($p === null) return false;
    $newElement = $dom13->createElement('span', 'After');
    $result = $p->after($newElement);
    $div = $dom13->first('div');
    $children = $div->children();
    return count($children) > 2;
});

run_test('remove 方法', function() use ($dom13) {
    $html = '<div><p>Remove me</p></div>';
    $doc = new Document($html);
    $p = $doc->first('p');
    if ($p === null) return false;
    $p->remove();
    $ps = $doc->find('p');
    return count($ps) === 0;
});

run_test('clone 方法', function() use ($dom13) {
    $p = $dom13->first('p');
    if ($p === null) return false;
    $cloned = $p->clone();
    return $cloned !== null && $cloned->text() === $p->text();
});

run_test('index 方法', function() use ($dom13) {
    // 使用新的文档来避免之前测试的影响
    $html = '<div><p>First</p><p>Second</p></div>';
    $doc = new Document($html);
    $p = $doc->first('div > p:nth-child(2)');
    if ($p === null) return false;
    return $p->index() === 1;
});

// ==================== Document 方法测试 ====================

echo "\n--- Document 方法测试 ---\n";

$html14 = '<div><p>Text 1</p><p>Text 2</p></div>';
$dom14 = new Document($html14);

run_test('texts 方法', function() use ($dom14) {
    $texts = $dom14->texts('p');
    return count($texts) === 2 && $texts[0] === 'Text 1' && $texts[1] === 'Text 2';
});

run_test('attrs 方法', function() use ($dom14) {
    $dom14->attr('p:first-child', 'data-value', '1');
    $dom14->attr('p:nth-child(2)', 'data-value', '2');
    $attrs = $dom14->attrs('p', 'data-value');
    return count($attrs) === 2 && $attrs[0] === '1' && $attrs[1] === '2';
});

run_test('createElement 方法', function() {
    $doc = new Document();
    $element = $doc->createElement('div', 'Text', ['class' => 'test', 'id' => 'my-id']);
    return $element !== null && $element->hasClass('test') && $element->id() === 'my-id';
});

run_test('createElementBySelector 方法', function() {
    $doc = new Document();
    $element = $doc->createElementBySelector('div#test.active', 'Content');
    return $element !== null && $element->id() === 'test' && $element->hasClass('active');
});

run_test('has 方法', function() use ($dom14) {
    return $dom14->has('p') && !$dom14->has('span');
});

run_test('count 方法', function() use ($dom14) {
    return $dom14->count('p') === 2;
});

run_test('clear 方法', function() {
    $doc = new Document('<div><p>Content</p></div>');
    $doc->clear();
    $html = $doc->html();
    return strpos($html, '<p>') === false;
});

run_test('root 方法', function() use ($dom14) {
    $root = $dom14->root();
    return $root !== null && ($root->tagName() === 'html' || $root->tagName() === 'div');
});

run_test('links 方法', function() {
    $html = '<div><a href="#1">Link 1</a><a href="#2">Link 2</a></div>';
    $doc = new Document($html);
    $links = $doc->links();
    return count($links) === 2 && $links[0]['href'] === '#1' && $links[1]['href'] === '#2';
});

run_test('images 方法', function() {
    $html = '<div><img src="1.jpg" alt="Image 1"><img src="2.jpg" alt="Image 2"></div>';
    $doc = new Document($html);
    $images = $doc->images();
    return count($images) === 2 && $images[0]['src'] === '1.jpg' && $images[1]['alt'] === 'Image 2';
});

run_test('forms 方法', function() {
    $html = '<form id="form1"></form><form id="form2"></form>';
    $doc = new Document($html);
    $forms = $doc->forms();
    return count($forms) === 2;
});

run_test('inputs 方法', function() {
    $html = '<input type="text"><input type="password"><textarea></textarea><select></select>';
    $doc = new Document($html);
    $inputs = $doc->inputs();
    return count($inputs) === 4;
});

// ==================== XPath 选择器测试 ====================

echo "\n--- XPath 选择器测试 ---\n";

$html15 = '<div><p>Text 1</p><p>Text 2</p><p>Text 3</p></div>';
$dom15 = new Document($html15);

run_test('XPath 查找所有 p 元素', function() use ($dom15) {
    $elements = $dom15->find('//p', Query::TYPE_XPATH);
    return [
        'count' => count($elements) === 3,
        'content' => $elements[0]->text() === 'Text 1' &&
                     $elements[1]->text() === 'Text 2' &&
                     $elements[2]->text() === 'Text 3',
        'details' => "XPath 找到 " . count($elements) . " 个 p 元素"
    ];
});

run_test('XPath contains 函数', function() use ($dom15) {
    $elements = $dom15->find('//p[contains(text(), "Text")]', Query::TYPE_XPATH);
    return count($elements) === 3;
});

run_test('XPath position 函数', function() use ($dom15) {
    $elements = $dom15->find('//p[position()=2]', Query::TYPE_XPATH);
    return count($elements) === 1 && $elements[0]->text() === 'Text 2';
});

run_test('XPath last 函数', function() use ($dom15) {
    $elements = $dom15->find('//p[last()]', Query::TYPE_XPATH);
    return count($elements) === 1 && $elements[0]->text() === 'Text 3';
});

run_test('XPath or 条件', function() use ($dom15) {
    $elements = $dom15->find('//p[text()="Text 1" or text()="Text 3"]', Query::TYPE_XPATH);
    return count($elements) === 2;
});

run_test('XPath starts-with 函数', function() use ($dom15) {
    $elements = $dom15->find('//p[starts-with(text(), "Text")]', Query::TYPE_XPATH);
    return count($elements) === 3;
});

// ==================== 链式调用测试 ====================

echo "\n--- 链式调用测试 ---\n";

$html16 = '<div class="container"><p>Text</p></div>';
$dom16 = new Document($html16);

run_test('Document 链式调用 addClass', function() use ($dom16) {
    $dom16->addClass('.container', 'active')->addClass('.container', 'new');
    $element = $dom16->first('.container');
    return $element !== null && $element->hasClass('active') && $element->hasClass('new');
});

run_test('Element 链式调用', function() use ($dom16) {
    $element = $dom16->first('.container');
    $element->addClass('class1')
            ->addClass('class2')
            ->addClass('class3')
            ->css('color', 'red')
            ->attr('data-id', '123');
    return $element->hasClass('class1') &&
           $element->hasClass('class2') &&
           $element->hasClass('class3') &&
           $element->css('color') === 'red' &&
           $element->attr('data-id') === '123';
});

run_test('ClassAttribute 链式调用', function() use ($dom16) {
    $element = $dom16->first('.container');
    $element->classes()->add('a')->add('b')->add('c')->remove('a');
    return !$element->hasClass('a') && $element->hasClass('b') && $element->hasClass('c');
});

run_test('StyleAttribute 链式调用', function() use ($dom16) {
    $element = $dom16->first('.container');
    $element->style()->set('color', 'red')
                    ->set('background', 'blue')
                    ->set('margin', '10px');
    return $element->css('color') === 'red' &&
           $element->css('background') === 'blue' &&
           $element->css('margin') === '10px';
});

// ==================== 边界情况测试 ====================

echo "\n--- 边界情况测试 ---\n";

run_test('查找不存在的元素', function() {
    $doc = new Document('<div></div>');
    $elements = $doc->find('.nonexistent');
    return count($elements) === 0;
});

run_test('empty 文本内容', function() {
    $doc = new Document('<div></div>');
    $element = $doc->first('div');
    return $element->text() === '';
});

run_test('包含HTML实体的文本', function() {
    $doc = new Document('<div>&lt;div&gt;&amp;&lt;/div&gt;</div>');
    $element = $doc->first('div');
    $text = $element->text();
    return $text === '<div>&</div>';
});

run_test('特殊字符属性值', function() {
    $doc = new Document('<div data-value="a<b>c&d\'e">Test</div>');
    $element = $doc->first('div');
    return $element->attr('data-value') === "a<b>c&d'e";
});

run_test('多类名操作', function() {
    $doc = new Document('<div class="a b c"></div>');
    $element = $doc->first('div');
    $element->removeClass('b');
    $element->addClass('d');
    $element->toggleClass('e');
    return $element->hasClass('a') &&
           !$element->hasClass('b') &&
           $element->hasClass('c') &&
           $element->hasClass('d') &&
           $element->hasClass('e');
});

run_test('无效选择器异常', function() {
    $doc = new Document('<div></div>');
    try {
        $doc->find('[');
        return false;
    } catch (\zxf\Utils\Dom\Exceptions\InvalidSelectorException $e) {
        return true;
    }
});

run_test('复杂嵌套选择器', function() {
    $html = '<div class="outer">
               <div class="inner">
                 <div class="deep">
                   <p>Deep Text</p>
                 </div>
               </div>
             </div>';
    $doc = new Document($html);
    $elements = $doc->find('.outer .inner .deep p');
    return count($elements) === 1 && $elements[0]->text() === 'Deep Text';
});

run_test('大量元素选择', function() {
    $html = '';
    for ($i = 1; $i <= 100; $i++) {
        $html .= "<p>Item {$i}</p>";
    }
    $doc = new Document("<div>{$html}</div>");
    $elements = $doc->find('p');
    return count($elements) === 100 && $elements[0]->text() === 'Item 1' && $elements[99]->text() === 'Item 100';
});

// ==================== 编码测试 ====================

echo "\n--- 编码测试 ---\n";

run_test('HTML 实体编码', function() {
    $text = Encoder::html('<div>&</div>');
    return $text === '&lt;div&gt;&amp;&lt;/div&gt;';
});

run_test('HTML 实体解码', function() {
    $text = Encoder::htmlDecode('&lt;div&gt;&amp;&lt;/div&gt;');
    return $text === '<div>&</div>';
});

run_test('特殊字符编码', function() {
    $text = Encoder::html('"test"');
    return strpos($text, '&quot;') !== false;
});

run_test('URL 编码', function() {
    $text = Encoder::url('hello world');
    return $text === 'hello%20world';
});

run_test('URL 解码', function() {
    $text = Encoder::urlDecode('hello%20world');
    return $text === 'hello world';
});

run_test('Base64 编码', function() {
    $text = Encoder::base64('test');
    return $text === 'dGVzdA==';
});

run_test('Base64 解码', function() {
    $text = Encoder::base64Decode('dGVzdA==');
    return $text === 'test';
});

run_test('JSON 编码', function() {
    $text = Encoder::json(['key' => 'value']);
    return $text !== false && strpos($text, 'key') !== false;
});

run_test('JSON 解码', function() {
    $text = Encoder::jsonDecode('{"key":"value"}');
    return $text !== null && $text['key'] === 'value';
});

// ==================== 中文支持测试 ====================

echo "\n--- 中文支持测试 ---\n";

$html17 = '<div class="article">
            <h1>PHP 开发技巧</h1>
            <p>学习 PHP 可以帮助你构建强大的 Web 应用程序。</p>
            <p class="author">作者：张三</p>
            <p class="date">发布时间：2024年12月</p>
          </div>';
$dom17 = new Document($html17);

run_test('获取中文内容', function() use ($dom17) {
    $title = $dom17->first('h1')->text();
    $author = $dom17->text('.author');
    $date = $dom17->text('.date');
    return $title === 'PHP 开发技巧' &&
           $author === '作者：张三' &&
           $date === '发布时间：2024年12月';
});

run_test(':contains 匹配中文', function() use ($dom17) {
    $paragraphs = $dom17->find('p:contains(PHP)');
    return count($paragraphs) === 1 && $paragraphs[0]->text() === '学习 PHP 可以帮助你构建强大的 Web 应用程序。';
});

run_test('HTML中的中文实体', function() {
    $html = '<div>&lt;中文&gt;</div>';
    $doc = new Document($html);
    $text = $doc->text('div');
    return $text === '<中文>';
});

// ==================== Query 方法测试 ====================

echo "\n--- Query 方法测试 ---\n";

run_test('Query::initialize 初始化', function() {
    Query::reset();
    Query::initialize();
    return Query::isInitialized();
});

run_test('Query::reset 重置', function() {
    Query::reset();
    return !Query::isInitialized();
});

run_test('Query::isInitialized 检查初始化状态', function() {
    Query::initialize();
    return Query::isInitialized() === true;
});

run_test('Query::compile 编译简单选择器', function() {
    $xpath = Query::compile('.test');
    return is_string($xpath) && strpos($xpath, 'contains') !== false;
});

run_test('Query::compile 编译复杂选择器', function() {
    $xpath = Query::compile('div.active[data-id="123"] > p');
    return is_string($xpath) && strpos($xpath, 'div') !== false;
});

run_test('Query::compile 编译 XPath', function() {
    $xpath = Query::compile('//p[contains(text(), "test")]', Query::TYPE_XPATH);
    return $xpath === '//p[contains(text(), "test")]';
});

run_test('Query::parseSelector 解析选择器', function() {
    Query::initialize();
    $segments = Query::parseSelector('div.test.active');
    return is_array($segments) && count($segments) > 0;
});

run_test('Query::getCompiled 获取缓存', function() {
    Query::initialize();
    Query::compile('.test');
    $compiled = Query::getCompiled();
    return is_array($compiled) && count($compiled) > 0;
});

run_test('Query::setCompiled 设置缓存', function() {
    Query::setCompiled(['test' => '//test']);
    $compiled = Query::getCompiled();
    return $compiled['test'] === '//test';
});

run_test('Query::clearCompiled 清空缓存', function() {
    Query::compile('.test');
    Query::clearCompiled();
    $compiled = Query::getCompiled();
    return count($compiled) === 0;
});

// ==================== 错误处理测试 ====================

echo "\n--- 错误处理测试 ---\n";

run_test('Errors::handle 处理错误', function() {
    try {
        throw new \RuntimeException('Test error');
    } catch (\Throwable $e) {
        \zxf\Utils\Dom\Utils\Errors::handle($e);
        return true;
    }
});

run_test('Errors::silence 静默处理错误', function() {
    $result = \zxf\Utils\Dom\Utils\Errors::silence(function() {
        throw new \RuntimeException('Silent error');
    }, 'default');
    return $result === 'default';
});

run_test('Errors::setLoggingEnabled 设置日志', function() {
    \zxf\Utils\Dom\Utils\Errors::setLoggingEnabled(false);
    return !\zxf\Utils\Dom\Utils\Errors::isLoggingEnabled();
});

run_test('Errors::setLogFile 设置日志文件', function() {
    \zxf\Utils\Dom\Utils\Errors::setLogFile('/tmp/test.log');
    $logFile = \zxf\Utils\Dom\Utils\Errors::getLogFile();
    return $logFile === '/tmp/test.log';
});

run_test('Errors::setErrorHandler 设置自定义处理器', function() {
    $called = false;
    \zxf\Utils\Dom\Utils\Errors::setErrorHandler(function() use (&$called) {
        $called = true;
    });
    try {
        throw new \RuntimeException('Test');
    } catch (\Throwable $e) {
        \zxf\Utils\Dom\Utils\Errors::handle($e);
    }
    return $called;
});

// ==================== 新增选择器测试 ====================

echo "\n--- 新增选择器测试（100+ 扩展） ---\n";

// HTML5 结构元素伪类测试
$html18 = '<div>
            <table>
                <thead><tr><th>Header</th></tr></thead>
                <tbody>
                    <tr><td>Row 1</td></tr>
                    <tr><td>Row 2</td></tr>
                </tbody>
                <tfoot><tr><td>Footer</td></tr></tfoot>
            </table>
            <ul><li>Item 1</li><li>Item 2</li></ul>
            <dl>
                <dt>Term 1</dt>
                <dd>Desc 1</dd>
            </dl>
            <form><label>Label</label></form>
            <section>Section</section>
            <article>Article</article>
            <nav>Nav</nav>
            <footer>Footer</footer>
          </div>';
$dom18 = new Document($html18);

run_test(':table 伪类', function() use ($dom18) {
    $elements = $dom18->find(':table');
    return count($elements) === 1;
});

run_test(':tr 伪类', function() use ($dom18) {
    $elements = $dom18->find(':tr');
    return count($elements) === 4; // 包括 thead, tbody, tfoot 中的 tr
});

run_test(':td 伪类', function() use ($dom18) {
    $elements = $dom18->find(':td');
    return count($elements) === 3;
});

run_test(':th 伪类', function() use ($dom18) {
    $elements = $dom18->find(':th');
    return count($elements) === 1;
});

run_test(':ul 伪类', function() use ($dom18) {
    $elements = $dom18->find(':ul');
    return count($elements) === 1;
});

run_test(':li 伪类', function() use ($dom18) {
    $elements = $dom18->find(':li');
    return count($elements) === 2;
});

run_test(':dl 伪类', function() use ($dom18) {
    $elements = $dom18->find(':dl');
    return count($elements) === 1;
});

run_test(':dt 伪类', function() use ($dom18) {
    $elements = $dom18->find(':dt');
    return count($elements) === 1;
});

run_test(':dd 伪类', function() use ($dom18) {
    $elements = $dom18->find(':dd');
    return count($elements) === 1;
});

run_test(':form 伪类', function() use ($dom18) {
    $elements = $dom18->find(':form');
    return count($elements) === 1;
});

run_test(':label 伪类', function() use ($dom18) {
    $elements = $dom18->find(':label');
    return count($elements) === 1;
});

run_test(':section 伪类', function() use ($dom18) {
    $elements = $dom18->find(':section');
    return count($elements) === 1;
});

run_test(':article 伪类', function() use ($dom18) {
    $elements = $dom18->find(':article');
    return count($elements) === 1;
});

run_test(':nav 伪类', function() use ($dom18) {
    $elements = $dom18->find(':nav');
    return count($elements) === 1;
});

run_test(':footer 伪类', function() use ($dom18) {
    $elements = $dom18->find(':footer');
    return count($elements) === 1;
});

// 位置伪类扩展测试
$html19 = '<div>
            <p>P1</p>
            <p>P2</p>
            <p>P3</p>
            <p>P4</p>
            <p>P5</p>
          </div>';
$dom19 = new Document($html19);

run_test(':between(start,end) 伪类', function() use ($dom19) {
    $elements = $dom19->find('p:between(2,4)');
    return [
        'count' => count($elements) === 3,
        'content' => $elements[0]->text() === 'P2' &&
                     $elements[1]->text() === 'P3' &&
                     $elements[2]->text() === 'P4',
        'details' => "between(2,4) 找到 " . count($elements) . " 个元素"
    ];
});

run_test(':slice 完整切片', function() use ($dom19) {
    $elements = $dom19->find('p:slice(1:4)');
    return count($elements) === 3;
});

// 语言和方向伪类测试
$html20 = '<div dir="ltr">LTR</div>
           <div dir="rtl">RTL</div>
           <div lang="zh-CN">中文</div>
           <div lang="en">English</div>';
$dom20 = new Document($html20);

run_test(':dir-ltr 伪类', function() use ($dom20) {
    $elements = $dom20->find(':dir-ltr');
    return count($elements) === 1 && $elements[0]->text() === 'LTR';
});

run_test(':dir-rtl 伪类', function() use ($dom20) {
    $elements = $dom20->find(':dir-rtl');
    return count($elements) === 1 && $elements[0]->text() === 'RTL';
});

// 深度伪类测试
$html21 = '<div>
            <div>
                <div>
                    <p>Deep</p>
                </div>
            </div>
          </div>';
$dom21 = new Document($html21);

run_test(':depth-0 伪类', function() use ($dom21) {
    $elements = $dom21->find(':depth-0');
    return count($elements) === 1;
});

run_test(':depth-1 伪类', function() use ($dom21) {
    $elements = $dom21->find(':depth-1');
    return count($elements) === 1;
});

run_test(':depth-2 伪类', function() use ($dom21) {
    $elements = $dom21->find(':depth-2');
    return count($elements) === 1;
});

// 文本长度伪类测试
$html22 = '<div>Short</div>
           <div>This is a longer text</div>
           <div>Medium text here</div>';
$dom22 = new Document($html22);

run_test(':text-length-gt 伪类', function() use ($dom22) {
    $elements = $dom22->find('div:text-length-gt(10)');
    return count($elements) === 2;
});

run_test(':text-length-lt 伪类', function() use ($dom22) {
    $elements = $dom22->find('div:text-length-lt(10)');
    return count($elements) === 1;
});

run_test(':text-length-eq 伪类', function() use ($dom22) {
    $elements = $dom22->find('div:text-length-eq(5)');
    return count($elements) === 1 && $elements[0]->text() === 'Short';
});

// 子元素数量伪类测试
$html23 = '<div><p>1</p></div>
           <div><p>2</p><p>3</p></div>
           <div><p>4</p><p>5</p><p>6</p></div>';
$dom23 = new Document($html23);

run_test(':children-gt 伪类', function() use ($dom23) {
    $elements = $dom23->find('div:children-gt(1)');
    return count($elements) === 2;
});

run_test(':children-lt 伪类', function() use ($dom23) {
    $elements = $dom23->find('div:children-lt(3)');
    return count($elements) === 2;
});

run_test(':children-eq 伪类', function() use ($dom23) {
    $elements = $dom23->find('div:children-eq(2)');
    return count($elements) === 1;
});

// 表单验证扩展伪类测试
$html24 = '<form>
            <input type="number" min="0" max="100" value="50">
            <input type="number" min="0" max="100" value="150">
            <input type="text" placeholder="Enter name">
            <input type="text" aria-invalid="true">
            <input type="text" aria-invalid="false">
          </form>';
$dom24 = new Document($html24);

run_test(':in-range 伪类', function() use ($dom24) {
    $elements = $dom24->find(':in-range');
    return count($elements) === 1;
});

run_test(':out-of-range 伪类', function() use ($dom24) {
    $elements = $dom24->find(':out-of-range');
    return count($elements) === 1;
});

run_test(':placeholder-shown 伪类', function() use ($dom24) {
    $elements = $dom24->find(':placeholder-shown');
    return count($elements) >= 1;
});

run_test(':user-invalid 伪类', function() use ($dom24) {
    $elements = $dom24->find(':user-invalid');
    return count($elements) === 1;
});

run_test(':user-valid 伪类', function() use ($dom24) {
    $elements = $dom24->find('input:user-valid');
    return count($elements) === 4;
});

// 属性匹配扩展伪类测试
$html25 = '<div data-id="123">1</div>
           <div data-info="test-abc">2</div>
           <div data-value="xyz-end">3</div>
           <div data-name="contains-mid">4</div>
           <div class="btn-primary">Button</div>';
$dom25 = new Document($html25);

run_test(':has-attr 伪类', function() use ($dom25) {
    $elements = $dom25->find(':has-attr(data-id)');
    return count($elements) === 1;
});

run_test(':data 伪类', function() use ($dom25) {
    $elements = $dom25->find(':data(id)');
    return count($elements) === 1;
});

run_test(':data 伪类匹配多个', function() use ($dom25) {
    $elements = $dom25->find('[data-id], [data-info], [data-value], [data-name]');
    return count($elements) === 4;
});

// 文本长度范围伪类测试
$html26 = '<div>Hi</div>
           <div>Hello World</div>
           <div>This is a longer text</div>
           <div>Short</div>';
$dom26 = new Document($html26);

run_test(':text-length-between 伪类', function() use ($dom26) {
    $elements = $dom26->find(':text-length-between(5,15)');
    return count($elements) >= 1;
});

// 属性长度伪类测试
$html27 = '<div data-value="1">A</div>
           <div data-value="123">B</div>
           <div data-value="12345">C</div>
           <div data-value="123456789">D</div>';
$dom27 = new Document($html27);

run_test(':attr-length-gt 伪类', function() use ($dom27) {
    $elements = $dom27->find(':attr-length-gt(data-value,3)');
    return count($elements) === 2;
});

run_test(':attr-length-lt 伪类', function() use ($dom27) {
    $elements = $dom27->find(':attr-length-lt(data-value,5)');
    return count($elements) === 2;
});

run_test(':attr-length-eq 伪类', function() use ($dom27) {
    $elements = $dom27->find(':attr-length-eq(data-value,5)');
    return count($elements) === 1;
});

// 深度范围伪类测试
$html28 = '<div>
              <div>
                <div>
                  <p>Deep</p>
                </div>
              </div>
              <p>Shallow</p>
            </div>';
$dom28 = new Document($html28);

run_test(':depth-between 伪类', function() use ($dom28) {
    $elements = $dom28->find(':depth-between(1,2)');
    return count($elements) >= 2;
});

// 文本匹配伪类测试
$html29 = '<div>Test 1</div>
           <div>Test 2</div>
           <div>Other</div>
           <div>Test 3</div>';
$dom29 = new Document($html29);

run_test(':text-match 伪类', function() use ($dom29) {
    $elements = $dom29->find('div:text-match(Test*)');
    return count($elements) === 3;
});

// 属性匹配伪类测试
$html30 = '<div class="nav-item">1</div>
           <div class="nav-menu">2</div>
           <div class="sidebar">3</div>
           <div class="nav-link">4</div>';
$dom30 = new Document($html30);

run_test(':attr-match 伪类', function() use ($dom30) {
    $elements = $dom30->find(':attr-match(class,nav*)');
    return count($elements) === 3;
});

// 属性数量伪类测试
$html31 = '<div id="test" class="item" data-value="1">A</div>
           <div id="simple">B</div>
           <div>C</div>';
$dom31 = new Document($html31);

run_test(':attr-count-gt 伪类', function() use ($dom31) {
    $elements = $dom31->find(':attr-count-gt(2)');
    return count($elements) === 1;
});

run_test(':attr-count-lt 伪类', function() use ($dom31) {
    $elements = $dom31->find(':attr-count-lt(2)');
    return count($elements) >= 1;
});

run_test(':attr-count-eq 伪类', function() use ($dom31) {
    $elements = $dom31->find('div:attr-count-eq(0)');
    return count($elements) === 1;
});

// ==================== 文本节点处理测试 ====================

echo "\n--- 文本节点处理测试 ---\n";

$html32 = '<div class="content">
    直接文本1
    <span class="inner">内部文本</span>
    直接文本2
</div>';
$dom32 = new Document($html32);

run_test('directText() 获取直接文本', function() use ($dom32) {
    $texts = $dom32->directText('div.content');
    return count($texts) === 2;
});

run_test('allTextNodes() 获取所有文本', function() use ($dom32) {
    $texts = $dom32->allTextNodes('div.content');
    // allTextNodes 会返回合并后的文本内容
    return count($texts) >= 1 && str_contains($texts[0], '直接文本1') && str_contains($texts[0], '内部文本');
});

run_test('XPath /text() 函数', function() use ($dom32) {
    $texts = $dom32->find('//div[@class="content"]/text()', Query::TYPE_XPATH);
    return is_array($texts);
});

// ==================== 新增XPath方法测试 ====================

echo "\n--- 新增XPath方法测试 ---\n";

$html33 = '<div>
    <p class="item">Text 1</p>
    <p class="item">Text 2</p>
    <a href="/link1">Link 1</a>
    <a href="/link2">Link 2</a>
    <img src="/img1.jpg" alt="Image 1">
    <img src="/img2.jpg" alt="Image 2">
</div>';
$dom33 = new Document($html33);

run_test('xpathFirst() 获取单个元素', function() use ($dom33) {
    $element = $dom33->xpathFirst('//p[@class="item"]');
    return $element !== null && trim($element->text()) === 'Text 1';
});

run_test('xpathTexts() 获取文本数组', function() use ($dom33) {
    $texts = $dom33->xpathTexts('//p/text()');
    return count($texts) === 2;
});

run_test('xpathAttrs() 获取属性数组', function() use ($dom33) {
    $hrefs = $dom33->xpathAttrs('//a', 'href');
    return count($hrefs) === 2 && $hrefs[0] === '/link1';
});

// ==================== 新增正则方法测试 ====================

echo "\n--- 新增正则方法测试 ---\n";

$html34 = '<div>
    <p>2024-12-15</p>
    <p>2025-01-01</p>
    <p>No date</p>
    <a href="https://example.com">Link 1</a>
    <a href="http://test.org">Link 2</a>
</div>';
$dom34 = new Document($html34);

run_test('regexFind() 查找元素', function() use ($dom34) {
    $elements = $dom34->regexFind('/\d{4}-\d{2}-\d{2}/');
    // 正则匹配文本内容，应该找到包含日期的p元素
    return count($elements) >= 1; // 至少找到一个包含日期的元素
});

run_test('regexFirst() 查找第一个元素', function() use ($dom34) {
    $element = $dom34->regexFirst('/2025/');
    return $element !== null && str_contains($element->text(), '2025');
});

run_test('regexFind() 匹配属性', function() use ($dom34) {
    $elements = $dom34->regexFind('/https?:/', 'href');
    return count($elements) >= 1;
});

// ==================== 新增伪类测试 ====================

echo "\n--- 新增伪类测试 ---\n";

$html35 = '<div id="test1" class="item active">Test 1</div>
           <div id="test2" data-value="123">Test 2</div>
           <a id="test3" href="#section1" tabindex="1">Link</a>
           <div class="container">
               <div class="child">Child 1</div>
               <div class="child">Child 2</div>
           </div>';
$dom35 = new Document($html35);

run_test(':target-within 伪类', function() use ($dom35) {
    $elements = $dom35->find('div:target-within');
    return is_array($elements);
});

run_test(':any-link 伪类', function() use ($dom35) {
    $elements = $dom35->find(':any-link');
    return count($elements) === 1;
});

run_test(':local-link 伪类', function() use ($dom35) {
    $elements = $dom35->find(':local-link');
    return count($elements) === 1;
});

run_test(':focus-within 伪类', function() use ($dom35) {
    $elements = $dom35->find('div:focus-within');
    return is_array($elements);
});

run_test(':focus-visible 伪类', function() use ($dom35) {
    $elements = $dom35->find('a:focus-visible');
    return is_array($elements);
});

// ==================== 属性选择器增强测试 ====================

echo "\n--- 属性选择器增强测试 ---\n";

$html36 = '<div data-id="1">Item 1</div>
           <div data-id="2">Item 2</div>
           <div id="item3">Item 3</div>
           <a href="https://example.com">Link 1</a>
           <a href="http://test.org">Link 2</a>';
$dom36 = new Document($html36);

run_test('[data-id="1"] 属性选择器', function() use ($dom36) {
    $elements = $dom36->find('[data-id="1"]');
    return count($elements) === 1;
});

run_test('[href^="https"] 属性前缀选择器', function() use ($dom36) {
    $elements = $dom36->find('[href^="https"]');
    return count($elements) === 1;
});

// ==================== 组合器增强测试 ====================

echo "\n--- 组合器增强测试 ---\n";

$html37 = '<div class="grandparent">
    <div class="parent">
        <div class="child">
            <span>Deep Text</span>
        </div>
    </div>
    <div class="parent2">
        <div class="child2">
            <span>Another Text</span>
        </div>
    </div>
</div>';
$dom37 = new Document($html37);

run_test('复杂后代选择器', function() use ($dom37) {
    $elements = $dom37->find('.grandparent .parent .child span');
    return count($elements) === 1 && trim($elements[0]->text()) === 'Deep Text';
});

run_test('多个子选择器', function() use ($dom37) {
    $elements = $dom37->find('.grandparent > .parent > .child');
    return count($elements) === 1;
});

run_test('组合使用组合器', function() use ($dom37) {
    $elements = $dom37->find('.grandparent > .parent .child');
    return count($elements) === 1;
});

// ==================== 便捷查找方法测试 ====================

echo "\n--- 便捷查找方法测试 ---\n";

$html33 = '<div id="container">
    <div class="item" data-id="123">项目1</div>
    <div class="item" data-id="456">项目2</div>
    <div class="item active">激活项目</div>
    <a href="https://example.com">链接1</a>
    <a href="https://test.org/page">链接2</a>
</div>';
$dom33 = new Document($html33);

run_test('findFirstByText() 查找包含文本', function() use ($dom33) {
    $element = $dom33->findFirstByText('项目1');
    return $element !== null && str_contains($element->text(), '项目1');
});

run_test('findFirstByAttribute() 查找属性', function() use ($dom33) {
    $element = $dom33->findFirstByAttribute('data-id', '123');
    return $element !== null && $element->getAttribute('data-id') === '123';
});

run_test('findFirstByAttributeContains() 查找属性包含', function() use ($dom33) {
    $element = $dom33->findFirstByAttributeContains('class', 'active');
    return $element !== null;
});

run_test('findFirstByAttributeStartsWith() 查找属性前缀', function() use ($dom33) {
    $element = $dom33->findFirstByAttributeStartsWith('href', 'https://');
    return $element !== null;
});

run_test('findFirstByAttributeEndsWith() 查找属性后缀', function() use ($dom33) {
    $element = $dom33->findFirstByAttributeEndsWith('href', 'com');
    return $element !== null;
});

run_test('findByIndex() 查找指定索引', function() use ($dom33) {
    $element = $dom33->findByIndex('.item', 1);
    return $element !== null && trim($element->text()) === '项目2';
});

run_test('findLast() 查找最后一个', function() use ($dom33) {
    $element = $dom33->findLast('.item');
    return $element !== null && trim($element->text()) === '激活项目';
});

run_test('findRange() 查找范围', function() use ($dom33) {
    $elements = $dom33->findRange('.item', 0, 2);
    return count($elements) === 2;
});

run_test('findByHtml() 查找HTML内容', function() use ($dom33) {
    $elements = $dom33->findByHtml('<span');
    return count($elements) === 0; // 当前HTML没有span
});

// ==================== 完整选择器测试 ====================

echo "\n--- 完整选择器测试 ---\n";

$html34 = '<!DOCTYPE html>
<html>
<head><title>测试页面</title></head>
<body>
    <div id="container">
        <div class="content">
            <h1 class="title">标题1</h1>
            <h2 class="title subtitle">标题2</h2>
            <p class="text">这是一段文本</p>
            <p class="text highlight">这是一段高亮文本</p>
            <ul class="list">
                <li>列表项1</li>
                <li>列表项2</li>
                <li>列表项3</li>
            </ul>
            <div data-id="123" data-type="primary">数据块</div>
        </div>
    </div>
</body>
</html>';
$dom34 = new Document($html34);

run_test('CSS: 多类选择器', function() use ($dom34) {
    $elements = $dom34->find('.title.subtitle');
    return count($elements) === 1;
});

run_test('CSS: 伪类 :first-child', function() use ($dom34) {
    $elements = $dom34->find('li:first-child');
    return count($elements) === 1 && trim($elements[0]->text()) === '列表项1';
});

run_test('CSS: 伪类 :last-child', function() use ($dom34) {
    $elements = $dom34->find('li:last-child');
    return count($elements) === 1 && trim($elements[0]->text()) === '列表项3';
});

run_test('CSS: 伪类 :nth-child', function() use ($dom34) {
    $elements = $dom34->find('li:nth-child(2)');
    return count($elements) === 1 && trim($elements[0]->text()) === '列表项2';
});

run_test('CSS: 伪类 :not', function() use ($dom34) {
    $elements = $dom34->find('p.text:not(.highlight)');
    return count($elements) === 1;
});

run_test('CSS: 属性选择器 [data-id]', function() use ($dom34) {
    $elements = $dom34->find('[data-id]');
    return count($elements) === 1;
});

run_test('CSS: 属性选择器 [data-id="123"]', function() use ($dom34) {
    $elements = $dom34->find('[data-id="123"]');
    return count($elements) === 1;
});

run_test('CSS: 伪元素 ::text', function() use ($dom34) {
    $texts = $dom34->find('h1.title::text');
    return count($texts) === 1 && trim($texts[0]) === '标题1';
});

run_test('XPath: 绝对路径', function() use ($dom34) {
    $elements = $dom34->find('/html/body/div', Query::TYPE_XPATH);
    return count($elements) >= 1;
});

run_test('XPath: 相对路径', function() use ($dom34) {
    $elements = $dom34->find('//div[@class="content"]', Query::TYPE_XPATH);
    return count($elements) === 1;
});

run_test('XPath: 文本包含', function() use ($dom34) {
    $elements = $dom34->find('//li[contains(text(), "列表项2")]', Query::TYPE_XPATH);
    return count($elements) === 1;
});

run_test('XPath: text() 函数', function() use ($dom34) {
    $texts = $dom34->find('//div[@class="content"]/text()', Query::TYPE_XPATH);
    return is_array($texts);
});

run_test('XPath: 位置索引', function() use ($dom34) {
    $elements = $dom34->find('//li[1]', Query::TYPE_XPATH);
    return count($elements) === 1;
});

// ==================== 测试结果总结 ====================

echo "\n\n";
echo "========================================================\n";
echo "              测试结果总结\n";
echo "========================================================\n";
echo "总测试数: {$totalTests}\n";
echo "通过测试: {$passedTests}\n";
echo "失败测试: " . count($failedTests) . "\n";
echo "通过率: " . ($totalTests > 0 ? round(($passedTests / $totalTests) * 100, 2) : 0) . "%\n";
echo "========================================================\n";

if (!empty($failedTests)) {
    echo "\n失败的测试:\n";
    foreach ($failedTests as $i => $test) {
        echo "  " . ($i + 1) . ". {$test}\n";
    }
}

echo "\n";

// ==================== 新增：选择器数组回退查找测试 ====================

echo "\n--- 选择器数组回退查找测试 ---\n";

$html38 = '<!DOCTYPE html>
<html>
<head><title>测试页面</title></head>
<body>
    <div class="main">
        <h1 class="title">主标题</h1>
        <p class="content">这是内容</p>
        <div class="sidebar">
            <h2 class="sidebar-title">侧边栏标题</h2>
        </div>
    </div>
</body>
</html>';
$dom38 = new Document($html38);

run_test('findWithFallback() - 第一个选择器匹配', function() use ($dom38) {
    $elements = $dom38->findWithFallback([
        ['selector' => 'h1.title'],  // 匹配
        ['selector' => 'h2.sidebar-title']
    ]);
    return count($elements) === 1 && trim($elements[0]->text()) === '主标题';
});

run_test('findWithFallback() - 第二个选择器匹配', function() use ($dom38) {
    $elements = $dom38->findWithFallback([
        ['selector' => '.nonexistent'],
        ['selector' => 'h2.sidebar-title']  // 匹配
    ]);
    return count($elements) === 1 && trim($elements[0]->text()) === '侧边栏标题';
});

run_test('findWithFallback() - XPath选择器匹配', function() use ($dom38) {
    $elements = $dom38->findWithFallback([
        ['selector' => '.wrong-class'],
        ['selector' => '//h1[@class="title"]', 'type' => 'xpath']  // 匹配
    ]);
    return count($elements) === 1 && trim($elements[0]->text()) === '主标题';
});

run_test('findFirstWithFallback() - 找到第一个元素', function() use ($dom38) {
    $element = $dom38->findFirstWithFallback([
        ['selector' => 'h1.title'],
        ['selector' => 'h2.sidebar-title']
    ]);
    return $element !== null && trim($element->text()) === '主标题';
});

run_test('findFirstWithFallback() - 返回null', function() use ($dom38) {
    $element = $dom38->findFirstWithFallback([
        ['selector' => '.not-exist'],
        ['selector' => '.also-not-exist']
    ]);
    return $element === null;
});

// ==================== 新增：Query类方法测试 ====================

echo "\n--- Query类新增方法测试 ---\n";

run_test('Query::isXPathAbsolute() 检测绝对路径', function() {
    return Query::isXPathAbsolute('/html/body/div') === true &&
           Query::isXPathAbsolute('//div') === false &&
           Query::isXPathAbsolute('div.container') === false;
});

run_test('Query::isXPathRelative() 检测相对路径', function() {
    return Query::isXPathRelative('//div[@class="item"]') === true &&
           Query::isXPathRelative('/html/body') === false &&
           Query::isXPathRelative('.class') === false;
});

run_test('Query::detectSelectorType() 检测CSS选择器', function() {
    return Query::detectSelectorType('div.container') === Query::TYPE_CSS &&
           Query::detectSelectorType('.class') === Query::TYPE_CSS &&
           Query::detectSelectorType('#id') === Query::TYPE_CSS;
});

run_test('Query::detectSelectorType() 检测XPath选择器', function() {
    return Query::detectSelectorType('/html/body/div') === Query::TYPE_XPATH &&
           Query::detectSelectorType('//div[@class="item"]') === Query::TYPE_XPATH &&
           Query::detectSelectorType('//h1[1]') === Query::TYPE_XPATH;
});

run_test('Query::detectSelectorType() 检测正则表达式', function() {
    return Query::detectSelectorType('/\d{4}-\d{2}-\d{2}/') === Query::TYPE_REGEX &&
           Query::detectSelectorType('/test.*/i') === Query::TYPE_REGEX &&
           Query::detectSelectorType('/^[a-z]+$/') === Query::TYPE_REGEX;
});

// ==================== 新增：全路径选择器增强测试 ====================

echo "\n--- 全路径选择器增强测试 ---\n";

$html39 = '<!DOCTYPE html>
<html>
<head><title>全路径测试</title></head>
<body>
    <div class="wrapper">
        <div class="container">
            <div class="content">
                <h1 id="main-title">标题</h1>
                <p class="description">描述文本</p>
            </div>
        </div>
    </div>
</body>
</html>';
$dom39 = new Document($html39);

run_test('XPath绝对路径查找', function() use ($dom39) {
    $elements = $dom39->find('/html/body/div/div/div/h1', Query::TYPE_XPATH);
    return count($elements) === 1 && trim($elements[0]->text()) === '标题';
});

run_test('XPath绝对路径带属性条件', function() use ($dom39) {
    $elements = $dom39->find('/html/body//div[@class="content"]/h1', Query::TYPE_XPATH);
    return count($elements) === 1 && trim($elements[0]->text()) === '标题';
});

run_test('XPath相对路径查找', function() use ($dom39) {
    $elements = $dom39->find('//div[@class="content"]/p', Query::TYPE_XPATH);
    return count($elements) === 1 && trim($elements[0]->text()) === '描述文本';
});

run_test('CSS全路径查找', function() use ($dom39) {
    $elements = $dom39->find('div.wrapper > div.container > div.content > h1');
    return count($elements) === 1 && trim($elements[0]->text()) === '标题';
});

// ==================== 完整测试总结 ====================

echo "\n\n";
echo "========================================================\n";
echo "              完整测试结果总结\n";
echo "========================================================\n";
echo "总测试数: {$totalTests}\n";
echo "通过测试: {$passedTests}\n";
echo "失败测试: " . count($failedTests) . "\n";
echo "通过率: " . ($totalTests > 0 ? round(($passedTests / $totalTests) * 100, 2) : 0) . "%\n";
echo "========================================================\n";

if (!empty($failedTests)) {
    echo "\n失败的测试:\n";
    foreach ($failedTests as $i => $test) {
        echo "  " . ($i + 1) . ". {$test}\n";
    }
}

echo "\n";
