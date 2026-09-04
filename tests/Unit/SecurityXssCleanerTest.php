<?php

declare(strict_types=1);

namespace zxf\Utils\Tests\Unit;

use zxf\Utils\Security\XssCleaner;
use zxf\Utils\Tests\TestCase;

/**
 * XSS 清理工具测试
 *
 * 覆盖历史上基于正则的实现被绕过的各类手法，防止回归。
 */
class SecurityXssCleanerTest extends TestCase
{
    /**
     * 常见 XSS 攻击向量（clean() 与 strict() 都必须清除执行能力）
     *
     * @return list<string>
     */
    private function attackVectors(): array
    {
        return [
            '<script>alert(1)</script>',
            '<SCRIPT>alert(1)</SCRIPT>',
            '<div onclick=alert(1)>x</div>',                          // 无引号属性
            '<div onclick="alert(1)">x</div>',
            '<a href="javascript:alert(1)">x</a>',
            "<a href=\"java\tscript:alert(1)\">x</a>",                 // 协议中插 TAB
            "<a href=\"java\nscript:alert(1)\">x</a>",                 // 协议中插换行
            "<a href=\"jav&#x09;ascript:alert(1)\">x</a>",             // 实体编码 TAB
            '<a href="&#106;avascript:alert(1)">x</a>',                // 实体编码 j
            '<a href="JaVaScRiPt:alert(1)">x</a>',
            '<a href="data:text/html;base64,PHNjcmlwdD4=">x</a>',
            '<img src=x onerror=alert(1)>',
            '<svg/onload=alert(1)>',
            '<iframe src="javascript:alert(1)"></iframe>',
            '<!--[if IE]><script>alert(1)</script><![endif]-->',       // 条件注释
            '<p style="background:url(javascript:alert(1))">x</p>',
            '<body onload=alert(1)>',
            '<a href="vbscript:msgbox(1)">x</a>',
            '<form action="javascript:alert(1)"><input name=a></form>',
            '<div/onclick="alert(1)">x</div>',                         // 斜杠代替空格
        ];
    }

    /**
     * 清理后的结果不应残留任何可执行片段
     */
    private function assertNoExecutableVestige(string $cleaned, string $original): void
    {
        $dangerous = '/on(error|load|click|mouseover|focus)\s*=|javascript\s*:|vbscript\s*:|<script|<iframe|<svg|<head|<body/i';

        if (preg_match($dangerous, $cleaned) === 1) {
            \zxf\Utils\Tests\Assert::fail(
                sprintf("攻击向量未被清除：\n  原始：%s\n  结果：%s", $original, $cleaned)
            );
        }
    }

    public function testCleanRemovesAllAttackVectors(): void
    {
        foreach ($this->attackVectors() as $vector) {
            $this->assertNoExecutableVestige(XssCleaner::clean($vector), $vector);
        }
    }

    public function testStrictRemovesAllAttackVectors(): void
    {
        foreach ($this->attackVectors() as $vector) {
            $this->assertNoExecutableVestige(XssCleaner::strict($vector), $vector);
        }
    }

    public function testCleanPreservesNormalContent(): void
    {
        $html = '<div><p>中文内容 <b>粗体</b></p><a href="/rel">相对链接</a></div>';
        $cleaned = XssCleaner::clean($html);

        \zxf\Utils\Tests\Assert::stringContains('中文内容', $cleaned);
        \zxf\Utils\Tests\Assert::stringContains('<b>粗体</b>', $cleaned);
        \zxf\Utils\Tests\Assert::stringContains('href="/rel"', $cleaned);
    }

    public function testStrictKeepsOnlyWhitelistedTagsAndAttributes(): void
    {
        $cleaned = XssCleaner::strict(
            '<p class="x" onclick="evil()">你好 <strong>粗</strong>'
            . '<a href="https://a.cn" title="t" target="_blank">链</a>'
            . '<img src="/a.png" alt="i" onerror="x">'
            . '<script>bad()</script></p>'
        );

        \zxf\Utils\Tests\Assert::stringNotContains('onclick', $cleaned);
        \zxf\Utils\Tests\Assert::stringNotContains('onerror', $cleaned);
        \zxf\Utils\Tests\Assert::stringNotContains('script', $cleaned);
        \zxf\Utils\Tests\Assert::stringContains('<strong>粗</strong>', $cleaned);
        // class 不在 p 标签的白名单属性中，应被移除
        \zxf\Utils\Tests\Assert::stringNotContains('class=', $cleaned);
    }

    public function testStrictHonoursCustomWhitelist(): void
    {
        $cleaned = XssCleaner::strict('<b onclick="x">hi</b><i>yo</i>', ['i' => []]);

        \zxf\Utils\Tests\Assert::stringNotContains('<b', $cleaned);
        \zxf\Utils\Tests\Assert::stringContains('<i>yo</i>', $cleaned);
    }

    public function testIsSafeUrlCoversProtocols(): void
    {
        \zxf\Utils\Tests\Assert::false(XssCleaner::isSafeUrl('javascript:alert(1)'));
        \zxf\Utils\Tests\Assert::false(XssCleaner::isSafeUrl('JAVASCRIPT:alert(1)'));
        \zxf\Utils\Tests\Assert::false(XssCleaner::isSafeUrl("java\tscript:alert(1)"));
        \zxf\Utils\Tests\Assert::false(XssCleaner::isSafeUrl('&#106;avascript:alert(1)'));
        \zxf\Utils\Tests\Assert::false(XssCleaner::isSafeUrl('data:text/html,x'));
        \zxf\Utils\Tests\Assert::false(XssCleaner::isSafeUrl('vbscript:x'));
        \zxf\Utils\Tests\Assert::true(XssCleaner::isSafeUrl('https://example.com/a?b=1'));
        \zxf\Utils\Tests\Assert::true(XssCleaner::isSafeUrl('http://example.com'));
        \zxf\Utils\Tests\Assert::true(XssCleaner::isSafeUrl('mailto:a@b.cn'));
        \zxf\Utils\Tests\Assert::true(XssCleaner::isSafeUrl('/relative/path'));
        \zxf\Utils\Tests\Assert::true(XssCleaner::isSafeUrl(''));
    }

    public function testEscapeAndStrip(): void
    {
        \zxf\Utils\Tests\Assert::same(
            '&lt;script&gt;&quot;x&#039;',
            XssCleaner::escape('<script>"x\'')
        );
        \zxf\Utils\Tests\Assert::same('abc', XssCleaner::strip('<p onclick=1>abc</p>'));
    }

    public function testCleanArrayRecurses(): void
    {
        $cleaned = XssCleaner::cleanArray([
            'a' => '<script>x</script>ok',
            'b' => ['c' => '<b onclick="1">hi</b>'],
            'n' => 5,
        ]);

        \zxf\Utils\Tests\Assert::stringNotContains('script', $cleaned['a']);
        \zxf\Utils\Tests\Assert::stringNotContains('onclick', $cleaned['b']['c']);
        \zxf\Utils\Tests\Assert::same(5, $cleaned['n']);
    }

    public function testEmptyInput(): void
    {
        \zxf\Utils\Tests\Assert::same('', XssCleaner::clean(''));
        \zxf\Utils\Tests\Assert::same('', XssCleaner::strict(''));
        \zxf\Utils\Tests\Assert::same('', XssCleaner::clean('   '));
    }
}
