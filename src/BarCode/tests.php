<?php

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

// 创建输出目录
$outputDir = __DIR__ . '/test_output';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

echo "【基础功能测试 - EAN-13】\n";
echo "-------------------------\n";

// 测试 EAN-13 基础生成
test('EAN-13 基础生成 (12位自动计算校验位)', function() use ($outputDir) {
    $barcode = BarcodeBuilder::create()
        ->type('ean13')
        ->data('690123456789')
        ->savePng($outputDir . '/ean13_basic.png');
    return file_exists($outputDir . '/ean13_basic.png') ? true : '文件未生成';
});

// 测试 EAN-13 带校验位
test('EAN-13 带校验位生成 (13位)', function() use ($outputDir) {
    $barcode = BarcodeBuilder::create()
        ->type('ean13')
        ->data('6901234567892')
        ->savePng($outputDir . '/ean13_with_checksum.png');
    return file_exists($outputDir . '/ean13_with_checksum.png') ? true : '文件未生成';
});

// 测试 978 前缀 EAN-13 (ISBN Bookland EAN)
test('EAN-13 978前缀 (Bookland EAN)', function() use ($outputDir) {
    $barcode = BarcodeBuilder::create()
        ->type('ean13')
        ->data('9780201379624')
        ->savePng($outputDir . '/ean13_978_prefix.png');
    return file_exists($outputDir . '/ean13_978_prefix.png') ? true : '文件未生成';
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
        ->savePng($outputDir . '/ean8_basic.png');
    return file_exists($outputDir . '/ean8_basic.png') ? true : '文件未生成';
});

test('EAN-8 生成 (8位带校验位)', function() use ($outputDir) {
    $barcode = BarcodeBuilder::create()
        ->type('ean8')
        ->data('12345670')
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
        ->savePng($outputDir . '/upca_basic.png');
    return file_exists($outputDir . '/upca_basic.png') ? true : '文件未生成';
});

test('UPC-A 生成 (12位带校验位)', function() use ($outputDir) {
    $barcode = BarcodeBuilder::create()
        ->type('upca')
        ->data('012345678905')
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

echo "\n【基础功能测试 - ITF-14】\n";
echo "------------------------\n";

// 测试 ITF-14 生成
test('ITF-14 生成 (13位自动计算校验位)', function() use ($outputDir) {
    $barcode = BarcodeBuilder::create()
        ->type('itf14')
        ->data('1540014128876')
        ->savePng($outputDir . '/itf14_basic.png');
    return file_exists($outputDir . '/itf14_basic.png') ? true : '文件未生成';
});

test('ITF-14 生成 (14位带校验位)', function() use ($outputDir) {
    $barcode = BarcodeBuilder::create()
        ->type('itf14')
        ->data('15400141288763')
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
    $barcode = BarcodeBuilder::create()
        ->type('issn')
        ->data('12345670')
        ->savePng($outputDir . '/issn_with_checksum.png');
    return file_exists($outputDir . '/issn_with_checksum.png') ? true : '文件未生成';
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
    $barcode = BarcodeBuilder::create()
        ->type('ean13')
        ->data('690123456789')
        ->width(4)
        ->height(120)
        ->savePng($outputDir . '/custom_size_large.png');
    return file_exists($outputDir . '/custom_size_large.png') ? true : '文件未生成';
});

test('自定义尺寸 - 小尺寸', function() use ($outputDir) {
    $barcode = BarcodeBuilder::create()
        ->type('ean13')
        ->data('690123456789')
        ->width(2)
        ->height(60)
        ->savePng($outputDir . '/custom_size_small.png');
    return file_exists($outputDir . '/custom_size_small.png') ? true : '文件未生成';
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
        ->savePng($outputDir . '/code128_long.png');
    return file_exists($outputDir . '/code128_long.png') ? true : '文件未生成';
});

// 测试特殊字符
test('Code 128 特殊字符', function() use ($outputDir) {
    $specialChars = 'A@#$%^&*()_+-=[]{}|;\':",./<>?';
    $barcode = BarcodeBuilder::create()
        ->type('code128')
        ->data($specialChars)
        ->savePng($outputDir . '/code128_special.png');
    return file_exists($outputDir . '/code128_special.png') ? true : '文件未生成';
});

echo "\n【扫码识别测试 - 生成标准测试条码】\n";
echo "------------------------------------\n";

// 生成用于扫码测试的标准条码
test('生成 EAN-13 扫码测试条码 (6901234567892)', function() use ($outputDir) {
    $barcode = BarcodeBuilder::create()
        ->type('ean13')
        ->data('6901234567892')
        ->width(3)
        ->height(100)
        ->savePng($outputDir . '/scan_test_ean13.png');
    return file_exists($outputDir . '/scan_test_ean13.png') ? true : '文件未生成';
});

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
