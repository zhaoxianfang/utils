<?php

declare(strict_types=1);

/**
 * BarCode 模块全面测试文件
 * 
 * 本测试文件包含:
 * - 所有条码类型的生成测试
 * - 长竖线特征验证
 * - 文本显示位置验证
 * - 校验位计算验证
 * - 特殊数据格式测试
 * - 边界情况测试
 * - （可选）扫码验证闭环：环境装有 zbarimg 时，对生成 PNG 做真实解码，
 *   断言「生成码面 == 标准解码器读出内容」且同一图像可重复读取
 * 
 * 运行方式: php tests.php
 */

// 自动加载类文件
spl_autoload_register(function ($class) {
    $prefix = 'zxf\\Utils\\BarCode\\';
    $baseDir = __DIR__ . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

use zxf\Utils\BarCode\BarcodeBuilder;
use zxf\Utils\BarCode\BarcodeFactory;
use zxf\Utils\BarCode\BarcodeHelper;
use zxf\Utils\BarCode\Renderer\PngRenderer;
use zxf\Utils\BarCode\Renderer\SvgRenderer;

echo "========================================\n";
echo "   BarCode 模块全面测试套件\n";
echo "========================================\n\n";

$passed = 0;
$failed = 0;
$tests = [];

// 测试辅助函数
function test($name, $callable) {
    global $passed, $failed, $tests;
    
    try {
        $result = $callable();
        if ($result === true) {
            echo "✓ {$name}\n";
            $passed++;
            $tests[] = ['name' => $name, 'status' => 'passed'];
            return true;
        } else {
            echo "✗ {$name}: {$result}\n";
            $failed++;
            $tests[] = ['name' => $name, 'status' => 'failed', 'error' => $result];
            return false;
        }
    } catch (\Exception $e) {
        echo "✗ {$name}: " . $e->getMessage() . "\n";
        $failed++;
        $tests[] = ['name' => $name, 'status' => 'failed', 'error' => $e->getMessage()];
        return false;
    }
}

// 创建输出目录（写入系统临时目录，避免污染包源码目录）
$outputDir = rtrim(sys_get_temp_dir(), '/\\') . '/zxf_utils_barcode_test_' . date('Ymd_His');
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}
echo "输出目录: {$outputDir}\n";

echo "【基础功能测试 - EAN-13】\n";
echo "-------------------------\n";

// 测试 EAN-13 基础生成
test('EAN-13 基础生成 (12位自动计算校验位)', function() use ($outputDir) {
    $barcode = BarcodeBuilder::create()
        ->type('ean13')
        ->data('690123456789')
        ->config(['moduleSize' => 4])
        ->savePng($outputDir . '/ean13_basic.png');
    return file_exists($outputDir . '/ean13_basic.png') ? true : '文件未生成';
});

// 测试 EAN-13 带校验位
test('EAN-13 带校验位生成 (13位)', function() use ($outputDir) {
    $barcode = BarcodeBuilder::create()
        ->type('ean13')
        ->data('6901234567892')
        ->config(['moduleSize' => 4])
        ->savePng($outputDir . '/ean13_with_checksum.png');
    return file_exists($outputDir . '/ean13_with_checksum.png') ? true : '文件未生成';
});

// 测试 978 前缀 EAN-13 (ISBN Bookland EAN)
// 注意：扫描器可能将 978 前缀的 EAN-13 自动转换为 ISBN-10 格式，导致显示内容不一致
test('EAN-13 978前缀 (Bookland EAN)', function() use ($outputDir) {
    $barcode = BarcodeBuilder::create()
        ->type('ean13')
        ->data('9780201379624')
        ->config(['moduleSize' => 4])
        ->savePng($outputDir . '/ean13_978_prefix.png');
    return file_exists($outputDir . '/ean13_978_prefix.png') ? true : '文件未生成';
});

// 测试非 ISBN 前缀的 EAN-13（避免扫描器自动转换）
test('EAN-13 690前缀 (零售商品)', function() use ($outputDir) {
    $barcode = BarcodeBuilder::create()
        ->type('ean13')
        ->data('6901234567892')
        ->config(['moduleSize' => 4])
        ->savePng($outputDir . '/ean13_690_prefix.png');
    return file_exists($outputDir . '/ean13_690_prefix.png') ? true : '文件未生成';
});

// 测试 EAN-13 校验位计算
test('EAN-13 校验位计算验证', function() {
    $builder = BarcodeBuilder::create()->type('ean13')->data('690123456789');
    $checksum = $builder->getChecksum();
    // 690123456789 的校验位应该是 2
    return $checksum === '2' ? true : "校验位错误: {$checksum}, 期望: 2";
});

echo "\n【基础功能测试 - EAN-8】\n";
echo "------------------------\n";

// 测试 EAN-8 生成
test('EAN-8 生成 (7位自动计算校验位)', function() use ($outputDir) {
    $barcode = BarcodeBuilder::create()
        ->type('ean8')
        ->data('1234567')
        ->config(['moduleSize' => 4])
        ->savePng($outputDir . '/ean8_basic.png');
    return file_exists($outputDir . '/ean8_basic.png') ? true : '文件未生成';
});

test('EAN-8 生成 (8位带校验位)', function() use ($outputDir) {
    $barcode = BarcodeBuilder::create()
        ->type('ean8')
        ->data('12345670')
        ->config(['moduleSize' => 4])
        ->savePng($outputDir . '/ean8_with_checksum.png');
    return file_exists($outputDir . '/ean8_with_checksum.png') ? true : '文件未生成';
});

echo "\n【基础功能测试 - UPC-A】\n";
echo "------------------------\n";

// 测试 UPC-A 生成
test('UPC-A 生成 (11位自动计算校验位)', function() use ($outputDir) {
    $barcode = BarcodeBuilder::create()
        ->type('upca')
        ->data('01234567890')
        ->config(['moduleSize' => 4])
        ->savePng($outputDir . '/upca_basic.png');
    return file_exists($outputDir . '/upca_basic.png') ? true : '文件未生成';
});

test('UPC-A 生成 (12位带校验位)', function() use ($outputDir) {
    $barcode = BarcodeBuilder::create()
        ->type('upca')
        ->data('012345678905')
        ->config(['moduleSize' => 4])
        ->savePng($outputDir . '/upca_with_checksum.png');
    return file_exists($outputDir . '/upca_with_checksum.png') ? true : '文件未生成';
});

echo "\n【基础功能测试 - Code 128】\n";
echo "---------------------------\n";

// 测试 Code 128 生成
test('Code 128 字母数字混合', function() use ($outputDir) {
    $barcode = BarcodeBuilder::create()
        ->type('code128')
        ->data('Hello123!')
        ->savePng($outputDir . '/code128_alphanumeric.png');
    return file_exists($outputDir . '/code128_alphanumeric.png') ? true : '文件未生成';
});

test('Code 128 纯数字', function() use ($outputDir) {
    $barcode = BarcodeBuilder::create()
        ->type('code128')
        ->data('123456789012')
        ->savePng($outputDir . '/code128_numeric.png');
    return file_exists($outputDir . '/code128_numeric.png') ? true : '文件未生成';
});

test('Code 128 ASCII全字符', function() use ($outputDir) {
    $barcode = BarcodeBuilder::create()
        ->type('code128')
        ->data('ABC-123_xyz')
        ->savePng($outputDir . '/code128_ascii.png');
    return file_exists($outputDir . '/code128_ascii.png') ? true : '文件未生成';
});

echo "\n【基础功能测试 - Code 39】\n";
echo "--------------------------\n";

// 测试 Code 39 生成
test('Code 39 基础生成', function() use ($outputDir) {
    $barcode = BarcodeBuilder::create()
        ->type('code39')
        ->data('ABC-123')
        ->savePng($outputDir . '/code39_basic.png');
    return file_exists($outputDir . '/code39_basic.png') ? true : '文件未生成';
});

test('Code 39 支持字符', function() use ($outputDir) {
    $barcode = BarcodeBuilder::create()
        ->type('code39')
        ->data('ABCD.1234')
        ->savePng($outputDir . '/code39_special.png');
    return file_exists($outputDir . '/code39_special.png') ? true : '文件未生成';
});

test('Code 39 小写输入自动转大写', function() use ($outputDir) {
    $barcode = BarcodeBuilder::create()
        ->type('code39')
        ->data('abc-123')
        ->savePng($outputDir . '/code39_lowercase.png');
    return file_exists($outputDir . '/code39_lowercase.png') ? true : '文件未生成';
});

test('Code 39 无效字符拦截', function() {
    try {
        BarcodeFactory::create('code39')->generate('ABC_123');
        return false; // 未抛异常 = 未拦截 = 失败
    } catch (\zxf\Utils\BarCode\Exceptions\InvalidDataException $e) {
        return true;
    } catch (\Throwable $e) {
        return '抛错类型错误: ' . get_class($e) . ': ' . $e->getMessage();
    }
});

echo "\n【基础功能测试 - ITF-14】\n";
echo "------------------------\n";

// 测试 ITF-14 生成
test('ITF-14 生成 (13位自动计算校验位)', function() use ($outputDir) {
    $barcode = BarcodeBuilder::create()
        ->type('itf14')
        ->data('1540014128876')
        ->config(['moduleSize' => 4])
        ->savePng($outputDir . '/itf14_basic.png');
    return file_exists($outputDir . '/itf14_basic.png') ? true : '文件未生成';
});

test('ITF-14 生成 (14位带校验位)', function() use ($outputDir) {
    $barcode = BarcodeBuilder::create()
        ->type('itf14')
        ->data('15400141288763')
        ->config(['moduleSize' => 4])
        ->savePng($outputDir . '/itf14_with_checksum.png');
    return file_exists($outputDir . '/itf14_with_checksum.png') ? true : '文件未生成';
});

echo "\n【基础功能测试 - ISSN】\n";
echo "----------------------\n";

// 测试 ISSN 生成
test('ISSN 生成 (7位自动计算校验位)', function() use ($outputDir) {
    $barcode = BarcodeBuilder::create()
        ->type('issn')
        ->data('1234567')
        ->savePng($outputDir . '/issn_basic.png');
    return file_exists($outputDir . '/issn_basic.png') ? true : '文件未生成';
});

test('ISSN 生成 (8位带校验位)', function() use ($outputDir) {
    // 1234567 的 ISSN 模11校验位为 9（12345670 是错误校验位，应被校验拦截）
    $barcode = BarcodeBuilder::create()
        ->type('issn')
        ->data('12345679')
        ->savePng($outputDir . '/issn_with_checksum.png');
    return file_exists($outputDir . '/issn_with_checksum.png') ? true : '文件未生成';
});

echo "\n【校验位一致性 - 错误校验位必须被拦截】\n";
echo "--------------------------------------\n";

// 输入已含校验位时默认验证：校验位错误 → 抛 InvalidDataException（避免生成坏码导致扫码内容不可信）
$checksumCases = [
    ['ean13', '6901234567891', 'EAN-13'],
    ['ean8',  '12345671',      'EAN-8'],
    ['upca',  '012345678904',  'UPC-A'],
    ['itf14', '15400141288761', 'ITF-14'],
    ['issn',  '12345670',      'ISSN'],
    ['issn',  '00000060',      'ISSN X校验位被数字误填'],
];
foreach ($checksumCases as [$type, $data, $label]) {
    test("{$label} 错误校验位拦截 ({$data})", function() use ($type, $data) {
        try {
            BarcodeFactory::create($type)->generate($data);
            return false; // 未抛异常 = 未拦截 = 失败
        } catch (\zxf\Utils\BarCode\Exceptions\InvalidDataException $e) {
            return true;
        } catch (\Throwable $e) {
            return '抛错类型错误: ' . get_class($e) . ': ' . $e->getMessage();
        }
    });
}

test('ISSN X 正确校验位输入通过', function() use ($outputDir) {
    // 0000006 加权和≡1(mod11) → 校验位为 X
    BarcodeFactory::create('issn')->generate('0000006X');
    return true;
});

test('skipChecksum(true) 显式跳过校验位验证', function() {
    $ok = BarcodeBuilder::create()
        ->type('itf14')
        ->data('15400141288761')
        ->skipChecksum(true)
        ->savePng(sys_get_temp_dir() . '/itf14_skip_checksum.png');
    return file_exists(sys_get_temp_dir() . '/itf14_skip_checksum.png') ? true : '文件未生成';
});

echo "\n【基础功能测试 - Codabar】\n";
echo "-------------------------\n";

test('Codabar 基础生成', function() use ($outputDir) {
    $barcode = BarcodeBuilder::create()
        ->type('codabar')
        ->data('A1234567890B')
        ->config(['moduleSize' => 4])
        ->savePng($outputDir . '/codabar_basic.png');
    return file_exists($outputDir . '/codabar_basic.png') ? true : '文件未生成';
});

test('Codabar 无起止符', function() use ($outputDir) {
    $barcode = BarcodeBuilder::create()
        ->type('codabar')
        ->data('1234567890')
        ->config(['moduleSize' => 4])
        ->savePng($outputDir . '/codabar_no_startstop.png');
    return file_exists($outputDir . '/codabar_no_startstop.png') ? true : '文件未生成';
});

echo "\n【基础功能测试 - ISBN】\n";
echo "----------------------\n";

test('ISBN-13 基础生成', function() use ($outputDir) {
    $barcode = BarcodeBuilder::create()
        ->type('isbn')
        ->data('978020137962')
        ->config(['moduleSize' => 4])
        ->savePng($outputDir . '/isbn13_basic.png');
    return file_exists($outputDir . '/isbn13_basic.png') ? true : '文件未生成';
});

test('ISBN-13 带校验位生成', function() use ($outputDir) {
    $barcode = BarcodeBuilder::create()
        ->type('isbn')
        ->data('978-3-16-148410-0')
        ->config(['moduleSize' => 4])
        ->savePng($outputDir . '/isbn13_full.png');
    return file_exists($outputDir . '/isbn13_full.png') ? true : '文件未生成';
});

test('ISBN-10 转 ISBN-13 生成', function() use ($outputDir) {
    $barcode = BarcodeBuilder::create()
        ->type('isbn')
        ->data('0-306-40615-2')
        ->config(['moduleSize' => 4])
        ->savePng($outputDir . '/isbn10_convert.png');
    return file_exists($outputDir . '/isbn10_convert.png') ? true : '文件未生成';
});

test('ISBN 校验位计算', function() {
    $checksum10 = BarcodeHelper::calculateChecksum('isbn', '030640615');
    $checksum13 = BarcodeHelper::calculateChecksum('isbn', '978020137962');
    return $checksum10 === '2' && $checksum13 === '4' ? true : "ISBN-10: {$checksum10}, ISBN-13: {$checksum13}";
});

test('ISBN 数据验证', function() {
    $result = BarcodeHelper::validateData('isbn', '978-3-16-148410-0');
    return $result['valid'] === true ? true : 'ISBN验证失败';
});

echo "\n【SVG 输出测试】\n";
echo "----------------\n";

// 测试 SVG 生成
test('EAN-13 SVG 生成', function() use ($outputDir) {
    $svg = BarcodeBuilder::create()
        ->type('ean13')
        ->data('690123456789')
        ->saveSvg($outputDir . '/ean13_test.svg');
    return file_exists($outputDir . '/ean13_test.svg') ? true : '文件未生成';
});

test('Code 128 SVG 生成', function() use ($outputDir) {
    $svg = BarcodeBuilder::create()
        ->type('code128')
        ->data('TEST123')
        ->saveSvg($outputDir . '/code128_test.svg');
    return file_exists($outputDir . '/code128_test.svg') ? true : '文件未生成';
});

test('ISBN SVG 分段文字完整输出（回归: ISBN 曾被排除在 EAN 分段之外导致 SVG 无文字）', function() {
    $svg = BarcodeBuilder::create()
        ->type('isbn')
        ->data('0-306-40615-2')
        ->toSvg();
    $textCount = substr_count($svg, '<text');
    // 修复前 ISBN SVG 无任何 <text>；修复后与 EAN-13 一致输出 13 个字符元素
    return $textCount === 13 ? true : "ISBN SVG 文字元素数 {$textCount} ≠ 13（修复未生效或回归）";
});

echo "\n【HTML 输出测试】\n";
echo "----------------\n";

test('Code 128 HTML 生成', function() use ($outputDir) {
    $html = BarcodeBuilder::create()
        ->type('code128')
        ->data('HTML-TEST-123')
        ->saveHtml($outputDir . '/code128_test.html');
    return file_exists($outputDir . '/code128_test.html') ? true : '文件未生成';
});

test('EAN-13 HTML 生成', function() use ($outputDir) {
    $html = BarcodeBuilder::create()
        ->type('ean13')
        ->data('690123456789')
        ->toHtml();
    file_put_contents($outputDir . '/ean13_test.html', $html);
    return file_exists($outputDir . '/ean13_test.html') && str_contains($html, '<div') ? true : 'HTML生成失败';
});

test('HTML Base64 输出', function() {
    $base64 = BarcodeBuilder::create()
        ->type('code39')
        ->data('BASE64HTML')
        ->toBase64('html');
    return str_starts_with($base64, 'data:text/html;base64,') ? true : 'Base64 HTML格式错误';
});

echo "\n【长竖线特征测试】\n";
echo "------------------\n";

// 测试长竖线位置计算
test('EAN-13 长竖线位置计算', function() {
    $generator = BarcodeFactory::create('ean13');
    $generator->generate('6901234567892');
    $positions = $generator->getLongBarPositions();
    // EAN-13 应该有6条长竖线（起始符2条 + 中间分隔符2条 + 终止符2条）
    return count($positions) === 6 ? true : "长竖线数量错误: " . count($positions) . ", 期望: 6";
});

test('EAN-8 长竖线位置计算', function() {
    $generator = BarcodeFactory::create('ean8');
    $generator->generate('12345670');
    $positions = $generator->getLongBarPositions();
    // EAN-8 应该有6条长竖线
    return count($positions) === 6 ? true : "长竖线数量错误: " . count($positions) . ", 期望: 6";
});

test('UPC-A 长竖线位置计算', function() {
    $generator = BarcodeFactory::create('upca');
    $generator->generate('012345678905');
    $positions = $generator->getLongBarPositions();
    // UPC-A 应该有6条长竖线
    return count($positions) === 6 ? true : "长竖线数量错误: " . count($positions) . ", 期望: 6";
});

echo "\n【BarcodeHelper 测试】\n";
echo "----------------------\n";

// 测试快速生成
test('BarcodeHelper 快速生成', function() use ($outputDir) {
    $result = BarcodeHelper::quickGenerate('ean13', '690123456789', $outputDir . '/helper_quick.png');
    return $result && file_exists($outputDir . '/helper_quick.png') ? true : '生成失败';
});

// 测试数据验证
test('BarcodeHelper EAN-13 数据验证(有效)', function() {
    $result = BarcodeHelper::validateData('ean13', '690123456789');
    return $result['valid'] === true ? true : '应该验证通过但失败了';
});

test('BarcodeHelper EAN-13 数据验证(无效)', function() {
    $result = BarcodeHelper::validateData('ean13', 'ABC123');
    return $result['valid'] === false ? true : '应该验证失败但通过了';
});

test('BarcodeHelper Code 128 数据验证', function() {
    $result = BarcodeHelper::validateData('code128', 'Hello World!');
    return $result['valid'] === true ? true : 'Code 128应该支持ASCII字符';
});

// 测试校验位计算
test('BarcodeHelper 校验位计算 - EAN13', function() {
    $checksum = BarcodeHelper::calculateChecksum('ean13', '690123456789');
    return $checksum === '2' ? true : "校验位错误: {$checksum}, 期望: 2";
});

test('BarcodeHelper 校验位计算 - EAN8', function() {
    $checksum = BarcodeHelper::calculateChecksum('ean8', '1234567');
    return $checksum === '0' ? true : "校验位错误: {$checksum}, 期望: 0";
});

// 测试类型信息获取
test('BarcodeHelper 类型信息获取', function() {
    $info = BarcodeHelper::getTypeInfo('ean13');
    return isset($info['name']) && $info['name'] === 'EAN-13' ? true : '信息获取失败';
});

// 测试 Base64 生成
test('BarcodeHelper Base64 生成', function() {
    $base64 = BarcodeHelper::toBase64('ean13', '690123456789');
    return str_starts_with($base64, 'data:image/png;base64,') ? true : 'Base64格式错误';
});

test('BarcodeHelper Base64 HTML 生成', function() {
    $base64 = BarcodeHelper::toBase64('code128', 'HTML-BASE64-TEST', ['format' => 'html']);
    return str_starts_with($base64, 'data:text/html;base64,') ? true : 'Base64 HTML格式错误';
});

// 测试新类型数据验证
test('BarcodeHelper Codabar 数据验证', function() {
    $result = BarcodeHelper::validateData('codabar', 'A1234B');
    return $result['valid'] === true ? true : 'Codabar验证失败';
});

test('BarcodeHelper ISBN 数据验证', function() {
    $result = BarcodeHelper::validateData('isbn', '978-3-16-148410-0');
    return $result['valid'] === true ? true : 'ISBN验证失败';
});

// 测试批量生成
test('BarcodeHelper 批量生成', function() use ($outputDir) {
    $dataList = [
        '690123456789',
        '690987654321',
        ['data' => '690111111111', 'filename' => 'custom_1'],
        '690222222222'
    ];
    $results = BarcodeHelper::batchGenerate($dataList, 'ean13', $outputDir . '/batch', [
        'prefix' => 'batch_',
        'format' => 'png'
    ]);
    return $results['success'] === 4 ? true : "批量生成失败: 成功 {$results['success']}, 失败 {$results['failed']}";
});

echo "\n【自定义样式测试】\n";
echo "------------------\n";

// 测试自定义颜色
test('自定义颜色 - 红黄配色', function() use ($outputDir) {
    $barcode = BarcodeBuilder::create()
        ->type('ean13')
        ->data('690123456789')
        ->barColor('#FF0000')
        ->bgColor('#FFFF00')
        ->savePng($outputDir . '/custom_color_red_yellow.png');
    return file_exists($outputDir . '/custom_color_red_yellow.png') ? true : '文件未生成';
});

// 测试自定义尺寸
test('自定义尺寸 - 大尺寸', function() use ($outputDir) {
    BarcodeBuilder::create()
        ->type('ean13')
        ->data('690123456789')
        ->width(400)
        ->height(120)
        ->savePng($outputDir . '/custom_size_large.png');
    return file_exists($outputDir . '/custom_size_large.png') ? true : '文件未生成';
});

test('自定义尺寸 - 小尺寸', function() use ($outputDir) {
    BarcodeBuilder::create()
        ->type('ean13')
        ->data('690123456789')
        ->width(200)
        ->height(60)
        ->savePng($outputDir . '/custom_size_small.png');
    return file_exists($outputDir . '/custom_size_small.png') ? true : '文件未生成';
});

test('设置整体宽度和高度', function() use ($outputDir) {
    BarcodeBuilder::create()
        ->type('ean13')
        ->data('690123456789')
        ->totalWidth(500)
        ->totalHeight(200)
        ->savePng($outputDir . '/total_size.png');
    return file_exists($outputDir . '/total_size.png') ? true : '文件未生成';
});

test('统一边距设置 margin()', function() use ($outputDir) {
    BarcodeBuilder::create()
        ->type('code128')
        ->data('MARGIN-TEST')
        ->margin(20)
        ->savePng($outputDir . '/margin_test.png');
    return file_exists($outputDir . '/margin_test.png') ? true : '文件未生成';
});

test('静区控制 quietZone()', function() use ($outputDir) {
    BarcodeBuilder::create()
        ->type('ean13')
        ->data('690123456789')
        ->quietZone(true, 15)
        ->savePng($outputDir . '/quiet_zone.png');
    return file_exists($outputDir . '/quiet_zone.png') ? true : '文件未生成';
});

test('长竖线比例 longBarRatio()', function() use ($outputDir) {
    BarcodeBuilder::create()
        ->type('ean13')
        ->data('690123456789')
        ->longBarRatio(1.3)
        ->savePng($outputDir . '/long_bar_ratio.png');
    return file_exists($outputDir . '/long_bar_ratio.png') ? true : '文件未生成';
});

test('字体大小设置 fontSize()', function() use ($outputDir) {
    BarcodeBuilder::create()
        ->type('code128')
        ->data('FONT-SIZE-TEST')
        ->fontSize(18)
        ->savePng($outputDir . '/font_size.png');
    return file_exists($outputDir . '/font_size.png') ? true : '文件未生成';
});

// 测试隐藏文字
test('隐藏文字', function() use ($outputDir) {
    $barcode = BarcodeBuilder::create()
        ->type('ean13')
        ->data('690123456789')
        ->showText(false)
        ->savePng($outputDir . '/no_text.png');
    return file_exists($outputDir . '/no_text.png') ? true : '文件未生成';
});

// 测试文本对齐
test('Code 128 文本居中对齐', function() use ($outputDir) {
    $renderer = new PngRenderer();
    $renderer->setTextAlign('center');
    
    $generator = BarcodeFactory::create('code128');
    $barcodeData = $generator->generate('CENTER TEXT');
    
    $renderer->saveToFile($barcodeData, 'CENTER TEXT', $outputDir . '/align_center.png');
    return file_exists($outputDir . '/align_center.png') ? true : '文件未生成';
});

echo "\n【高级渲染器测试】\n";
echo "------------------\n";

// 测试渐变效果
test('PNG 渐变效果', function() use ($outputDir) {
    $renderer = new PngRenderer();
    $renderer->enableGradient('#000000', '#333333');
    
    $generator = BarcodeFactory::create('ean13');
    $barcodeData = $generator->generate('6901234567892');
    
    $renderer->saveToFile($barcodeData, '6901234567892', $outputDir . '/gradient_black.png');
    return file_exists($outputDir . '/gradient_black.png') ? true : '文件未生成';
});

// 测试圆角条
test('PNG 圆角条', function() use ($outputDir) {
    $renderer = new PngRenderer();
    $renderer->enableRoundedBars(3);
    
    $generator = BarcodeFactory::create('code128');
    $barcodeData = $generator->generate('ROUNDED');
    
    $renderer->saveToFile($barcodeData, 'ROUNDED', $outputDir . '/rounded_bars.png');
    return file_exists($outputDir . '/rounded_bars.png') ? true : '文件未生成';
});

// 测试水印
test('PNG 基础水印', function() use ($outputDir) {
    $renderer = new PngRenderer();
    $renderer->setWatermark('SAMPLE', 70, 5, '#888888');
    
    $generator = BarcodeFactory::create('ean13');
    $barcodeData = $generator->generate('6901234567892');
    
    $renderer->saveToFile($barcodeData, '6901234567892', $outputDir . '/watermark_basic.png');
    return file_exists($outputDir . '/watermark_basic.png') ? true : '文件未生成';
});

test('PNG 旋转水印 (-45度)', function() use ($outputDir) {
    $renderer = new PngRenderer();
    $renderer->setWatermark('WATERMARK', 40, 4, '#CCCCCC', -45);
    
    $generator = BarcodeFactory::create('ean13');
    $barcodeData = $generator->generate('6901234567892');
    
    $renderer->saveToFile($barcodeData, '6901234567892', $outputDir . '/watermark_rotated_45.png');
    return file_exists($outputDir . '/watermark_rotated_45.png') ? true : '文件未生成';
});

test('PNG 旋转水印 (30度)', function() use ($outputDir) {
    $renderer = new PngRenderer();
    $renderer->setWatermark('CONFIDENTIAL', 50, 3, '#999999', 30);
    
    $generator = BarcodeFactory::create('code128');
    $barcodeData = $generator->generate('TEST123');
    
    $renderer->saveToFile($barcodeData, 'TEST123', $outputDir . '/watermark_rotated_30.png');
    return file_exists($outputDir . '/watermark_rotated_30.png') ? true : '文件未生成';
});

// 测试 ITF-14 Bearer Bar
test('ITF-14 Bearer Bar', function() use ($outputDir) {
    $renderer = new PngRenderer();
    $renderer->enableBearerBar(3);

    $generator = BarcodeFactory::create('itf14');
    $barcodeData = $generator->generate('15400141288763');

    $renderer->saveToFile($barcodeData, '15400141288763', $outputDir . '/bearer_bar.png');
    return file_exists($outputDir . '/bearer_bar.png') ? true : '文件未生成';
});

test('SVG Bearer Bar', function() use ($outputDir) {
    BarcodeBuilder::create()
        ->type('itf14')
        ->data('15400141288763')
        ->bearerBar(3)
        ->saveSvg($outputDir . '/bearer_bar.svg');
    return file_exists($outputDir . '/bearer_bar.svg') ? true : '文件未生成';
});

test('PNG 边框功能', function() use ($outputDir) {
    BarcodeBuilder::create()
        ->type('ean13')
        ->data('690123456789')
        ->border(3, '#FF0000')
        ->savePng($outputDir . '/border_red.png');
    return file_exists($outputDir . '/border_red.png') ? true : '文件未生成';
});

test('SVG 透明背景', function() use ($outputDir) {
    $svg = BarcodeBuilder::create()
        ->type('code128')
        ->data('TRANSPARENT')
        ->transparentBackground(true)
        ->toSvg();
    file_put_contents($outputDir . '/transparent.svg', $svg);
    $hasBgRect = str_contains($svg, '<rect width="100%" height="100%" fill="');
    return file_exists($outputDir . '/transparent.svg') && !$hasBgRect ? true : '未生成透明SVG';
});

test('Base64 PNG 输出', function() {
    $base64 = BarcodeBuilder::create()
        ->type('ean13')
        ->data('690123456789')
        ->toBase64('png');
    return str_starts_with($base64, 'data:image/png;base64,') ? true : 'Base64格式错误';
});

test('Base64 SVG 输出', function() {
    $base64 = BarcodeBuilder::create()
        ->type('ean13')
        ->data('690123456789')
        ->toBase64('svg');
    return str_starts_with($base64, 'data:image/svg+xml;base64,') ? true : 'Base64格式错误';
});

test('组合样式测试 - 渐变+圆角+水印+边框', function() use ($outputDir) {
    BarcodeBuilder::create()
        ->type('code128')
        ->data('COMBO-STYLE-TEST')
        ->width(350)
        ->height(100)
        ->gradient('#000000', '#444444')
        ->roundedBars(3)
        ->watermark('COMBO', 40, 16, '#AAAAAA', 0)
        ->border(2, '#0000FF')
        ->savePng($outputDir . '/combo_style.png');
    return file_exists($outputDir . '/combo_style.png') ? true : '文件未生成';
});

echo "\n【边界情况测试】\n";
echo "----------------\n";

// 测试最小长度数据
test('EAN-13 最小长度数据', function() use ($outputDir) {
    try {
        $barcode = BarcodeBuilder::create()
            ->type('ean13')
            ->data('000000000000')
            ->savePng($outputDir . '/ean13_min.png');
        return file_exists($outputDir . '/ean13_min.png') ? true : '文件未生成';
    } catch (\Exception $e) {
        return '异常: ' . $e->getMessage();
    }
});

// 测试最大长度数据
test('Code 128 长数据', function() use ($outputDir) {
    $longData = str_repeat('A', 50);
    $barcode = BarcodeBuilder::create()
        ->type('code128')
        ->data($longData)
        ->height(200)
        ->config(['moduleSize' => 4])
        ->savePng($outputDir . '/code128_long.png');
    return file_exists($outputDir . '/code128_long.png') ? true : '文件未生成';
});

// 测试特殊字符
test('Code 128 特殊字符', function() use ($outputDir) {
    $specialChars = 'A@#$%^&*()_+-=[]{}|;\':",./<>?';
    $barcode = BarcodeBuilder::create()
        ->type('code128')
        ->data($specialChars)
        ->height(120)
        ->savePng($outputDir . '/code128_special.png');
    return file_exists($outputDir . '/code128_special.png') ? true : '文件未生成';
});

// 测试控制字符支持（字符集 A）
test('Code 128 控制字符支持（制表符）', function() use ($outputDir) {
    $barcode = BarcodeBuilder::create()
        ->type('code128')
        ->data("A\tB")
        ->savePng($outputDir . '/code128_control_tab.png');
    return file_exists($outputDir . '/code128_control_tab.png') ? true : '文件未生成';
});

test('Code 128 换行符支持', function() use ($outputDir) {
    $barcode = BarcodeBuilder::create()
        ->type('code128')
        ->data("LINE1\nLINE2")
        ->savePng($outputDir . '/code128_control_newline.png');
    return file_exists($outputDir . '/code128_control_newline.png') ? true : '文件未生成';
});

// 测试自动字符集 C 数字压缩
test('Code 128 纯数字自动切换字符集 C', function() use ($outputDir) {
    $barcode = BarcodeBuilder::create()
        ->type('code128')
        ->data('12345678901234')
        ->savePng($outputDir . '/code128_numeric_compressed.png');
    return file_exists($outputDir . '/code128_numeric_compressed.png') ? true : '文件未生成';
});

// 测试混合内容（控制字符 + 字母 + 数字）
test('Code 128 混合内容（字符集 A/B/C 自动切换）', function() use ($outputDir) {
    $barcode = BarcodeBuilder::create()
        ->type('code128')
        ->data("\x01START\x0212345\x03END")
        ->savePng($outputDir . '/code128_mixed_charset.png');
    return file_exists($outputDir . '/code128_mixed_charset.png') ? true : '文件未生成';
});

echo "\n【扫码识别测试 - 生成标准测试条码】\n";
echo "------------------------------------\n";

// 生成用于扫码测试的标准条码
test('生成 EAN-13 扫码测试条码 (6901234567892)', function() use ($outputDir) {
    BarcodeBuilder::create()
        ->type('ean13')
        ->data('6901234567892')
        ->width(400)
        ->height(100)
        ->savePng($outputDir . '/scan_test_ean13.png');
    return file_exists($outputDir . '/scan_test_ean13.png') ? true : '文件未生成';
});

echo "\n【扫码验证闭环（可选 - 需要 zbarimg）】\n";
echo "--------------------------------------\n";

/**
 * 扫码闭环（Scan Loop）：
 * - 用真实开源解码器 zbarimg 重新解码本库生成的 PNG，
 *   证明「生成的码面内容 == 标准解码器读出的内容」（生成→解码闭环一致）。
 * - 本段为可选：未安装 zbarimg（brew install zbar）时整段跳过、不计失败；
 *   安装了但解码不符才计失败。
 * - 每个文件解码两次：两次结果必须一致（防「同一图像不可重复读取」），
 *   且必须等于期望值。
 *
 * 【期望值口径 - 重要，勿把扫码器行为当生成器 bug】：
 * - EAN-13 / EAN-8 / ITF-14：解码即为码面 13 / 8 / 14 位数字；
 * - UPC-A：zbar 按 EAN-13 语义输出前导 0 的 13 位（0 + 12 位），断言前剥掉前导 0 再比；
 * - ISBN / Bookland：zbar 原样输出含 978 前缀的 13 位 EAN-13，不做 ISBN-10 转换——
 *   手机 App 显示「丢 978 / 末位校验位改变」是 App 侧自动改造，不是生成器剥前缀；
 * - ISSN：码面按 ISSN 规范 = 977 + 7 位 ISSN + 2 位附加码 + EAN-13 校验位的 13 位
 *   Bookland EAN，zbar 输出即该 13 位（不是 8 位 ISSN 本身）；
 * - Codabar：zbar 会把 A/B 起止符一并读出（如 A1234567890B），证明起止符确实编码
 *   进了条空；手机 App 只显示中间数字属 App 行为。
 */
$zbarBin = trim((string) @shell_exec('command -v zbarimg 2>/dev/null'));
if ($zbarBin === '') {
    echo "⚠ 未检测到 zbarimg（安装: brew install zbar），跳过【扫码验证闭环】，不影响其它测试\n";
} else {
    $scanDir = $outputDir . '/scanloop';
    if (!is_dir($scanDir)) {
        mkdir($scanDir, 0755, true);
    }
    echo "解码器: {$zbarBin}\n";

    // 解码单个 PNG：成功返回 [true, 内容]，失败返回 [false, '']（zbar 输出形如 TYPE:CONTENT）
    $decodeOne = function (string $png) use ($zbarBin): array {
        $raw = trim((string) shell_exec(escapeshellarg($zbarBin) . ' --quiet ' . escapeshellarg($png) . ' 2>/dev/null'));
        if ($raw === '') {
            return [false, ''];
        }
        $first = explode("\n", $raw)[0];
        $pos = strpos($first, ':');
        return [true, $pos === false ? $first : substr($first, $pos + 1)];
    };

    // 按类型的解码归一化（扫码器格式差异，非生成器缺陷）
    $normalizers = [
        'upca' => function (string $s): string {
            return strlen($s) === 13 && $s[0] === '0' ? substr($s, 1) : $s;
        },
    ];

    // 闭环用例矩阵：[文件名, 类型, 数据, 期望解码, 附加生成配置(可为 null), 备注]
    $scanCases = [
        ['ean13_basic',     'ean13',   '6901234567892',   '6901234567892',   null,
            'EAN-13 含校验位完整输入'],
        ['ean13_bookland',  'ean13',   '9780201379624',   '9780201379624',   null,
            'Bookland(978)：zbar 原样输出 978 前缀，不做 ISBN-10 转换'],
        ['ean8',            'ean8',    '12345670',        '12345670',        null,
            'EAN-8 含校验位完整输入'],
        ['upca',            'upca',    '012345678905',    '012345678905',    null,
            'UPC-A：zbar 输出 0+12 位，断言时剥前导 0 再比'],
        ['code128_text',    'code128', 'Hello123!',       'Hello123!',       null,
            'Code128 字母数字'],
        ['code128_digits',  'code128', '123456789012',    '123456789012',    null,
            'Code128 长数字（自动 Set C 压缩路径）'],
        ['code39',          'code39',  'ABC-123',         'ABC-123',         null,
            'Code39 模块级宽窄比重建后仍可解码'],
        ['code39_lower',    'code39',  'Abc-123',         'ABC-123',         null,
            'Code39 小写输入自动转大写后仍可解码'],
        ['codabar',         'codabar', 'A1234567890B',    'A1234567890B',    null,
            'Codabar：起止符 A/B 被 zbar 一并读出，证明起止符已编码（App 丢弃属其行为）'],
        ['itf14',           'itf14',   '1540014128876',   '15400141288763',  null,
            'ITF-14：13 位自动补校验位；含 5/8，回归「宽位错位表曾被扫码器翻转 5↔8/6↔9」修复'],
        ['issn',            'issn',    '1234567',         '9771234567003',   null,
            'ISSN：码面为 977+ISSN+00+校验位的 13 位 Bookland EAN'],
        ['isbn13',          'isbn',    '978-3-16-148410-0', '9783161484100', null,
            'ISBN-13 连字符输入 → 码面 978... 13 位'],
        ['isbn10',          'isbn',    '0-306-40615-2',   '9780306406157',   null,
            'ISBN-10 自动转 ISBN-13：码面 = 978 + 9 位 + 重算校验位'],
        ['ean13_min',       'ean13',   '000000000000',    '0000000000000',   null,
            '全 0 边界：12 位自动补校验位 → 13 个 0'],
        ['watermark',       'ean13',   '6901234567892',   '6901234567892',
            function ($b) { return $b->watermark('SAMPLE', 70, 5, '#888888', 0); },
            '带水印图（曾因 2px/module 过小导致字符误读；默认 4px/module 后可稳定解码）'],
        ['code128_tab',     'code128', "A\tB",            "A\tB",            null,
            'Code128 控制字符（制表符，Set A 路径）'],
    ];

    foreach ($scanCases as [$file, $type, $data, $expected, $extra, $note]) {
        test("扫码闭环 {$file}（{$note}）", function () use (
            $scanDir, $decodeOne, $normalizers, $file, $type, $data, $expected, $extra
        ) {
            $png = $scanDir . '/' . $file . '.png';
            $builder = BarcodeBuilder::create()
                ->type($type)
                ->data($data)
                ->config(['moduleSize' => 4]);
            if ($extra !== null) {
                $builder = $extra($builder);
            }
            $builder->savePng($png);
            if (!is_file($png)) {
                return 'PNG 未生成';
            }
            [$ok1, $r1] = $decodeOne($png);
            if (!$ok1) {
                return 'zbarimg 无法解码：生成的码面不被标准解码器识别';
            }
            [$ok2, $r2] = $decodeOne($png);
            if ($r2 !== $r1) {
                return "同一图像两次解码不一致（不可重复读取）: {$r1} vs {$r2}";
            }
            $norm = $normalizers[$type] ?? fn (string $s): string => $s;
            $actual = $norm($r1);
            return $actual === $expected ? true : "解码结果 [{$actual}] ≠ 期望 [{$expected}]";
        });
    }
    echo "扫码闭环完成（需人工确认上方无 ✗ 输出）\n";
}

echo "\n========================================\n";
echo "   测试结果汇总\n";
echo "========================================\n";
echo "通过: {$passed}\n";
echo "失败: {$failed}\n";
echo "总计: " . ($passed + $failed) . "\n";
echo "通过率: " . ($passed + $failed > 0 ? round($passed / ($passed + $failed) * 100, 2) : 0) . "%\n";
echo "========================================\n";

if ($failed > 0) {
    echo "\n失败的测试:\n";
    foreach ($tests as $test) {
        if ($test['status'] === 'failed') {
            echo "- {$test['name']}: {$test['error']}\n";
        }
    }
    exit(1);
}

echo "\n✓ 所有测试通过！\n";
echo "测试输出文件保存在: {$outputDir}\n";
echo "\n提示: 可以使用手机扫描以下文件验证条码可读性:\n";
echo "- {$outputDir}/scan_test_ean13.png\n";
exit(0);
