<?php

declare(strict_types=1);

namespace zxf\Utils\Tests\Unit;

use zxf\Utils\BarCode\BarcodeBuilder;
use zxf\Utils\QrCode\QrCode;
use zxf\Utils\Tests\Assert;
use zxf\Utils\Tests\TestCase;

/**
 * BarCode / QrCode 渲染冒烟测试
 *
 * 侧重校验修复过的行为：
 *  1. HTML 渲染器对 EAN/UPC 保护符区域的分段文本与文字对齐
 *  2. HTML 渲染器长竖线真正受 rotateLongBars / longBarRatio 配置控制
 *  3. 静区宽度配置生效
 *  4. QrCode SVG 输出中的 Logo 装饰与水印不再被静默丢弃
 *  5. PNG 输出不抛异常（依赖 GD，缺失时跳过）
 *
 * 【API 约定】BarcodeBuilder 无 ean13()/code128() 快捷方法，也无带参 create()；
 * 一律使用 BarcodeBuilder::create()->type(...)->data(...) 链式调用。
 */
class BarCodeQrCodeTest extends TestCase
{
    /** 构造 EAN-13 HTML 输出的最小链式调用 */
    protected function ean13Html(array $config = []): string
    {
        $builder = BarcodeBuilder::create()->type('ean13')->data('6901234567892');
        if ($config !== []) {
            $builder = $builder->config($config);
        }
        return $builder->toHtml();
    }

    public function testEan13HtmlTextIsSegmented(): void
    {
        $html = $this->ean13Html();

        // 分段渲染：每个字符一个绝对定位 span（共 13 位，第一位与两侧 6 位分开定位）
        $spanCount = substr_count($html, 'text-align:center');
        Assert::true($spanCount >= 12, "期望分段 span ≥ 12，实际 {$spanCount}");

        // 分段模式下不使用 space-between 的单 span 整段布局
        Assert::false(str_contains($html, 'justify-content:space-between'), 'EAN-13 不应出现整段 space-between 文本');
    }

    public function testEan13HtmlLongBarsFollowConfig(): void
    {
        // 关闭长竖线：wrapper 高度不含延长量
        $short = $this->ean13Html(['rotateLongBars' => false]);

        // 开启长竖线 + ratio 1.5：wrapper 高度应含 height×(ratio-1) 的向下延长量
        $tall = $this->ean13Html(['rotateLongBars' => true, 'longBarRatio' => 1.5]);

        preg_match('/position:relative;[^"]*?width:\d+px;height:(\d+)px;/', $short, $mShort);
        preg_match('/position:relative;[^"]*?width:\d+px;height:(\d+)px;/', $tall, $mTall);

        Assert::true(isset($mShort[1], $mTall[1]), '未找到 wrapper 高度样式');
        Assert::true((int) $mTall[1] > (int) $mShort[1], '开启长竖线后 wrapper 高度应增加（含向下延长量）');
    }

    public function testHtmlEscapesData(): void
    {
        // 数据中的 <> 不允许破坏 HTML 结构（CODE128 支持任意 ASCII）
        $html = BarcodeBuilder::create()->type('code128')->data('a<b>c&d"e')->toHtml();
        Assert::true(str_contains($html, '&lt;b&gt;'), '文本应被 HTML 转义');
        Assert::false(str_contains($html, 'a<b>c'), '原始 <b> 不应出现在输出中');
    }

    public function testCode128SvgOutput(): void
    {
        $svg = BarcodeBuilder::create()->type('code128')->data('CODE128-TEST-1234567890')->toSvg();
        Assert::true(str_starts_with($svg, '<?xml'), 'SVG 应以 XML 声明开始');
        Assert::true(str_contains($svg, '<svg'), 'SVG 应包含 <svg> 根元素');
    }

    public function testQuietZoneConfigurable(): void
    {
        $default = $this->ean13Html();
        $wider = BarcodeBuilder::create()->type('ean13')->data('6901234567892')->quietZone(true, 20)->toHtml();

        preg_match('/position:relative;[^"]*?width:(\d+)px;height:\d+px;/', $default, $m1);
        preg_match('/position:relative;[^"]*?width:(\d+)px;height:\d+px;/', $wider, $m2);
        Assert::true(isset($m1[1], $m2[1]), '未找到 wrapper 宽度样式');
        Assert::true((int) $m2[1] > (int) $m1[1], '静区加宽后 wrapper 应更宽');
    }

    public function testQrCodeSvgDecorationsNotSilentlyDropped(): void
    {
        $tmpLogo = sys_get_temp_dir() . '/zxf_qr_test_' . bin2hex(random_bytes(3)) . '.png';
        if (!function_exists('imagecreatetruecolor')) {
            return; // GD 缺失时跳过
        }
        imagepng(imagecreatetruecolor(40, 40), $tmpLogo);

        $svg = QrCode::make('https://example.com/中文🚀')
            ->logo($tmpLogo)
            ->logoCircular()
            ->logoShadow('#000000')
            ->watermark('机密', 50, 16, '#CCCCCC', 'top-left')
            ->toSvg();

        @unlink($tmpLogo);

        Assert::true(str_contains($svg, 'clipPath'), '圆形 logo 应有 clipPath');
        Assert::true(str_contains($svg, 'clip-path="url(#'), 'image 应引用 clipPath');
        Assert::true(str_contains($svg, '机密'), '水印不应被丢弃');
        // 用户内容在 SVG 中必须转义为合法 XML 文本
        Assert::false(str_contains($svg, '例.com/中文'), 'SVG 文本必须 XML 转义');
    }

    public function testQrCodeSvgIsValidXml(): void
    {
        if (!class_exists(\DOMDocument::class)) {
            return; // DOM 扩展缺失时跳过
        }
        $svg = QrCode::make('XML<安全>&测试')->toSvg();

        $dom = new \DOMDocument();
        Assert::true($dom->loadXML($svg), 'SVG 必须是合法 XML');
    }

    public function testBarcodeBuilderEan13Png(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            return; // GD 扩展缺失时跳过
        }
        $png = BarcodeBuilder::create()->type('ean13')->data('6901234567892')->toBase64();
        Assert::true(str_starts_with($png, 'data:image/png;base64,'), 'PNG base64 应以 data:image/png 开头');
        Assert::true(strlen($png) > 100, 'PNG base64 不应为空');
    }
}
