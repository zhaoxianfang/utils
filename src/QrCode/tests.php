<?php

/**
 * QrCode 模块全面测试文件
 * 
 * 本测试文件包含:
 * - 基础二维码生成测试
 * - 不同数据类型测试（URL、WiFi、VCard等）
 * - 标签/文本位置测试
 * - Logo和样式测试
 * - 批量生成测试
 * - 边界情况测试
 * 
 * 运行方式: php tests.php
 */

// 自动加载类文件
spl_autoload_register(function ($class) {
    $prefix = 'zxf\\Utils\\QrCode\\';
    $baseDir = __DIR__ . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // 尝试加载 Common 命名空间
        $prefix2 = 'zxf\\Utils\\QrCode\\Common\\';
        if (strncmp($prefix2, $class, strlen($prefix2)) === 0) {
            $baseDir = __DIR__ . '/Common/';
            $relativeClass = substr($class, strlen($prefix2));
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
            if (file_exists($file)) {
                require $file;
            }
            return;
        }
        // 尝试加载 Encoder 命名空间
        $prefix3 = 'zxf\\Utils\\QrCode\\Encoder\\';
        if (strncmp($prefix3, $class, strlen($prefix3)) === 0) {
            $baseDir = __DIR__ . '/Encoder/';
            $relativeClass = substr($class, strlen($prefix3));
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
            if (file_exists($file)) {
                require $file;
            }
            return;
        }
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

use zxf\Utils\QrCode\QrCode;
use zxf\Utils\QrCode\QrCodeHelper;
use zxf\Utils\QrCode\LabelOptions;
use zxf\Utils\QrCode\Color\Color;
use zxf\Utils\QrCode\AdvancedFeatures;

echo "========================================\n";
echo "   QrCode 模块全面测试套件\n";
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
$outputDir = __DIR__ . '/test_output_'.date('Ymd_Hi');
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

echo "【基础功能测试】\n";
echo "----------------\n";

// 基础二维码生成
test('基础二维码生成', function() use ($outputDir) {
    QrCode::make('Hello World')->save($outputDir . '/basic.png');
    return file_exists($outputDir . '/basic.png') ? true : '文件未生成';
});

// 不同尺寸
test('小尺寸二维码 (100x100)', function() use ($outputDir) {
    QrCode::make('Small')->size(100)->save($outputDir . '/small.png');
    return file_exists($outputDir . '/small.png') ? true : '文件未生成';
});

test('大尺寸二维码 (500x500)', function() use ($outputDir) {
    QrCode::make('Large')->size(500)->save($outputDir . '/large.png');
    return file_exists($outputDir . '/large.png') ? true : '文件未生成';
});

// 不同边距
test('大边距二维码', function() use ($outputDir) {
    QrCode::make('Margin Test')->margin(10)->save($outputDir . '/large_margin.png');
    return file_exists($outputDir . '/large_margin.png') ? true : '文件未生成';
});

echo "\n【数据类型测试】\n";
echo "----------------\n";

// URL
test('URL 二维码', function() use ($outputDir) {
    QrCode::make('https://www.example.com')->save($outputDir . '/url.png');
    return file_exists($outputDir . '/url.png') ? true : '文件未生成';
});

// WiFi
test('WiFi 配置二维码', function() use ($outputDir) {
    $wifiString = QrCodeHelper::generateWifiString('MyNetwork', 'password123', 'WPA');
    QrCode::make($wifiString)->save($outputDir . '/wifi.png');
    return file_exists($outputDir . '/wifi.png') ? true : '文件未生成';
});

// VCard
test('VCard 名片二维码', function() use ($outputDir) {
    $vcard = QrCodeHelper::generateVCardString([
        'name' => '张三',
        'phone' => '13800138000',
        'email' => 'zhangsan@example.com',
        'company' => '示例公司'
    ]);
    QrCode::make($vcard)->save($outputDir . '/vcard.png');
    return file_exists($outputDir . '/vcard.png') ? true : '文件未生成';
});

// 邮件
test('邮件二维码', function() use ($outputDir) {
    $email = QrCodeHelper::generateEmailString('contact@example.com', '测试邮件', '这是邮件正文');
    QrCode::make($email)->save($outputDir . '/email.png');
    return file_exists($outputDir . '/email.png') ? true : '文件未生成';
});

// 短信
test('短信二维码', function() use ($outputDir) {
    $sms = QrCodeHelper::generateSmsString('13800138000', '短信内容');
    QrCode::make($sms)->save($outputDir . '/sms.png');
    return file_exists($outputDir . '/sms.png') ? true : '文件未生成';
});

// 电话
test('电话二维码', function() use ($outputDir) {
    $phone = QrCodeHelper::generatePhoneString('13800138000');
    QrCode::make($phone)->save($outputDir . '/phone.png');
    return file_exists($outputDir . '/phone.png') ? true : '文件未生成';
});

// 地理位置
test('地理位置二维码', function() use ($outputDir) {
    $geo = QrCodeHelper::generateGeoString(39.9042, 116.4074, '天安门广场');
    QrCode::make($geo)->save($outputDir . '/geo.png');
    return file_exists($outputDir . '/geo.png') ? true : '文件未生成';
});

// 日历事件
test('日历事件二维码', function() use ($outputDir) {
    $event = QrCodeHelper::generateEventString(
        '会议标题',
        '20260407T100000',
        '20260407T120000',
        '会议室A',
        '会议描述'
    );
    QrCode::make($event)->save($outputDir . '/event.png');
    return file_exists($outputDir . '/event.png') ? true : '文件未生成';
});

echo "\n【标签/文本位置测试】\n";
echo "---------------------\n";

// 基础标签
test('带标签二维码', function() use ($outputDir) {
    QrCode::make('With Label')
        ->labelText('这是标签文本')
        ->save($outputDir . '/with_label.png');
    return file_exists($outputDir . '/with_label.png') ? true : '文件未生成';
});

// 自定义标签样式
test('自定义标签样式', function() use ($outputDir) {
    $label = LabelOptions::create('自定义标签')
        ->fontSize(16)
        ->color('#FF0000')
        ->marginTop(5);
    
    QrCode::make('Custom Label Style')
        ->label($label)
        ->save($outputDir . '/custom_label.png');
    return file_exists($outputDir . '/custom_label.png') ? true : '文件未生成';
});

// 标签背景色
test('标签带背景色', function() use ($outputDir) {
    $label = LabelOptions::create('背景标签')
        ->backgroundColor('#FFFF00')
        ->padding(5);
    
    QrCode::make('Label with BG')
        ->label($label)
        ->save($outputDir . '/label_with_bg.png');
    return file_exists($outputDir . '/label_with_bg.png') ? true : '文件未生成';
});

echo "\n【颜色和样式测试】\n";
echo "------------------\n";

// 前景色背景色
test('自定义前景色/背景色', function() use ($outputDir) {
    QrCode::make('Colors')
        ->foregroundColor(Color::fromHex('#FF0000'))
        ->backgroundColor(Color::fromHex('#FFFF00'))
        ->save($outputDir . '/custom_colors.png');
    return file_exists($outputDir . '/custom_colors.png') ? true : '文件未生成';
});

// 透明背景
test('透明背景', function() use ($outputDir) {
    QrCode::make('Transparent')
        ->transparentBackground(true)
        ->save($outputDir . '/transparent.png');
    return file_exists($outputDir . '/transparent.png') ? true : '文件未生成';
});

// 圆点风格
test('圆点风格二维码', function() use ($outputDir) {
    QrCode::make('Rounded')
        ->rounded(true)
        ->roundedRadius(0.5)
        ->save($outputDir . '/rounded.png');
    return file_exists($outputDir . '/rounded.png') ? true : '文件未生成';
});

echo "\n【纠错级别测试】\n";
echo "----------------\n";

test('纠错级别 L (低)', function() use ($outputDir) {
    QrCode::make('Error Correction L')
        ->errorCorrectionLevel(\zxf\Utils\QrCode\ErrorCorrectionLevel::low())
        ->save($outputDir . '/ec_low.png');
    return file_exists($outputDir . '/ec_low.png') ? true : '文件未生成';
});

test('纠错级别 M (中)', function() use ($outputDir) {
    QrCode::make('Error Correction M')
        ->errorCorrectionLevel(\zxf\Utils\QrCode\ErrorCorrectionLevel::medium())
        ->save($outputDir . '/ec_medium.png');
    return file_exists($outputDir . '/ec_medium.png') ? true : '文件未生成';
});

test('纠错级别 Q (较高)', function() use ($outputDir) {
    QrCode::make('Error Correction Q')
        ->errorCorrectionLevel(\zxf\Utils\QrCode\ErrorCorrectionLevel::quartile())
        ->save($outputDir . '/ec_quartile.png');
    return file_exists($outputDir . '/ec_quartile.png') ? true : '文件未生成';
});

test('纠错级别 H (高)', function() use ($outputDir) {
    QrCode::make('Error Correction H')
        ->errorCorrectionLevel(\zxf\Utils\QrCode\ErrorCorrectionLevel::high())
        ->save($outputDir . '/ec_high.png');
    return file_exists($outputDir . '/ec_high.png') ? true : '文件未生成';
});

echo "\n【Logo 测试】\n";
echo "-------------\n";

// 检查是否有测试用的 logo 文件
$testLogoPath = __DIR__ . '/test_logo.png';
if (file_exists($testLogoPath)) {
    test('带Logo二维码', function() use ($outputDir, $testLogoPath) {
        QrCode::make('With Logo')
            ->logo($testLogoPath)
            ->logoScale(20)
            ->save($outputDir . '/with_logo.png');
        return file_exists($outputDir . '/with_logo.png') ? true : '文件未生成';
    });

    test('圆形Logo', function() use ($outputDir, $testLogoPath) {
        QrCode::make('Circular Logo')
            ->logo($testLogoPath)
            ->logoScale(20)
            ->logoCircular(true)
            ->save($outputDir . '/logo_circular.png');
        return file_exists($outputDir . '/logo_circular.png') ? true : '文件未生成';
    });
} else {
    echo "ℹ 跳过Logo测试 (未找到 {$testLogoPath})\n";
}

echo "\n【QrCodeHelper 测试】\n";
echo "---------------------\n";

// 数据类型分析
test('数据类型分析 - URL', function() {
    $analysis = QrCodeHelper::analyzeData('https://www.example.com');
    return $analysis['type'] === 'url' ? true : "期望类型 'url', 实际 '{$analysis['type']}'";
});

test('数据类型分析 - WiFi', function() {
    $analysis = QrCodeHelper::analyzeData('WIFI:T:WPA;S:Test;P:pass;;');
    return $analysis['type'] === 'wifi' ? true : "期望类型 'wifi', 实际 '{$analysis['type']}'";
});

test('数据类型分析 - VCard', function() {
    $analysis = QrCodeHelper::analyzeData("BEGIN:VCARD\nFN:Test\nEND:VCARD");
    return $analysis['type'] === 'vcard' ? true : "期望类型 'vcard', 实际 '{$analysis['type']}'";
});

// 版本估算
test('版本估算', function() {
    $version = QrCodeHelper::estimateVersion('Test Data', 'M');
    return $version > 0 ? true : "版本估算失败: {$version}";
});

// 环境检查
test('环境检查', function() {
    $env = QrCodeHelper::checkEnvironment();
    return isset($env['passed']) ? true : '环境检查返回格式错误';
});

echo "\n【批量生成测试】\n";
echo "----------------\n";

test('批量生成二维码', function() use ($outputDir) {
    $items = ['Item 1', 'Item 2', 'Item 3'];
    
    $results = AdvancedFeatures::batchGenerate($items, $outputDir . '/batch', 200, 'batch_');
    
    return $results['success'] === 3 ? true : "批量生成失败: 成功 {$results['success']}, 失败 {$results['failed']}";
});

echo "\n【输出格式测试】\n";
echo "----------------\n";

test('PNG 格式输出', function() use ($outputDir) {
    QrCode::make('PNG Format')
        ->format('png')
        ->save($outputDir . '/format_png.png');
    return file_exists($outputDir . '/format_png.png') ? true : '文件未生成';
});

test('JPEG 格式输出', function() use ($outputDir) {
    QrCode::make('JPEG Format')
        ->format('jpg')
        ->quality(90)
        ->save($outputDir . '/format_jpg.jpg');
    return file_exists($outputDir . '/format_jpg.jpg') ? true : '文件未生成';
});

test('Base64 输出', function() {
    $base64 = QrCode::make('Base64 Test')->toBase64();
    return str_starts_with($base64, 'data:image/png;base64,') ? true : 'Base64格式错误';
});

test('width() 别名', function() use ($outputDir) {
    QrCode::make('Width Alias')
        ->width(250)
        ->save($outputDir . '/width_alias.png');
    return file_exists($outputDir . '/width_alias.png') ? true : '文件未生成';
});

test('height() 别名', function() use ($outputDir) {
    QrCode::make('Height Alias')
        ->height(250)
        ->save($outputDir . '/height_alias.png');
    return file_exists($outputDir . '/height_alias.png') ? true : '文件未生成';
});

test('totalWidth/totalHeight 强制缩放', function() use ($outputDir) {
    QrCode::make('Scaled Output')
        ->size(200)
        ->totalWidth(400)
        ->totalHeight(400)
        ->save($outputDir . '/scaled_400.png');
    return file_exists($outputDir . '/scaled_400.png') ? true : '文件未生成';
});

test('margin 和 padding 组合', function() use ($outputDir) {
    QrCode::make('Margin Padding')
        ->size(200)
        ->margin(2)
        ->padding(20)
        ->save($outputDir . '/margin_padding.png');
    return file_exists($outputDir . '/margin_padding.png') ? true : '文件未生成';
});

test('直接获取二进制 PNG 数据', function() use ($outputDir) {
    $data = QrCode::make('Raw PNG')->size(150)->toString();
    file_put_contents($outputDir . '/raw_png.png', $data);
    return file_exists($outputDir . '/raw_png.png') && strlen($data) > 100 ? true : '数据异常';
});

echo "\n【边界情况测试】\n";
echo "----------------\n";

test('空数据二维码应抛出异常', function() use ($outputDir) {
    try {
        QrCode::make('')->save($outputDir . '/empty.png');
        return '空数据应该抛出异常';
    } catch (\Exception $e) {
        return true; // 期望抛出异常
    }
});

test('长文本二维码 (500字符)', function() use ($outputDir) {
    $longText = str_repeat('A', 500);
    QrCode::make($longText)->save($outputDir . '/long_text.png');
    return file_exists($outputDir . '/long_text.png') ? true : '文件未生成';
});

test('中文内容二维码', function() use ($outputDir) {
    QrCode::make('这是一段中文测试内容')->save($outputDir . '/chinese.png');
    return file_exists($outputDir . '/chinese.png') ? true : '文件未生成';
});

test('关闭 ECI 前缀（提升旧设备兼容性）', function() use ($outputDir) {
    QrCode::make('关闭ECI测试')
        ->prefixEci(false)
        ->save($outputDir . '/no_eci_prefix.png');
    return file_exists($outputDir . '/no_eci_prefix.png') ? true : '文件未生成';
});

test('开启 ECI 前缀（默认行为）', function() use ($outputDir) {
    QrCode::make('开启ECI测试')
        ->prefixEci(true)
        ->save($outputDir . '/with_eci_prefix.png');
    return file_exists($outputDir . '/with_eci_prefix.png') ? true : '文件未生成';
});

test('混合内容二维码', function() use ($outputDir) {
    QrCode::make('中文 ABC 123 !@# 日本語')->save($outputDir . '/mixed_content.png');
    return file_exists($outputDir . '/mixed_content.png') ? true : '文件未生成';
});

echo "\n【高级功能测试】\n";
echo "----------------\n";

// 测试克隆功能
test('二维码配置克隆', function() use ($outputDir) {
    $original = QrCode::make('Original')->size(300);
    $cloned = $original->cloneWithData('Cloned Data');
    $cloned->save($outputDir . '/cloned.png');
    return file_exists($outputDir . '/cloned.png') ? true : '文件未生成';
});

// 测试获取信息
test('获取二维码信息', function() {
    $info = QrCode::make('Info Test')->size(300)->getInfo();
    return isset($info['size']) && $info['size'] === 300 ? true : '信息获取失败';
});

// 测试高级功能
test('带背景的渐变效果', function() use ($outputDir) {
    // 创建渐变背景
    $gradient = AdvancedFeatures::createGradientBackground(400, 400, '#FF6B6B', '#4ECDC4', 'diagonal');
    imagepng($gradient, $outputDir . '/gradient_bg.png');

    // 使用背景
    QrCode::make('Gradient BG')
        ->backgroundImage($outputDir . '/gradient_bg.png')
        ->save($outputDir . '/qr_with_gradient.png');
    
    return file_exists($outputDir . '/qr_with_gradient.png') ? true : '文件未生成';
});

echo "\n【扫码识别测试 - 生成标准测试二维码】\n";
echo "--------------------------------------\n";

test('生成基础扫码测试二维码', function() use ($outputDir) {
    QrCode::make('SCAN_TEST_12345')
        ->size(300)
        ->save($outputDir . '/scan_test_basic.png');
    return file_exists($outputDir . '/scan_test_basic.png') ? true : '文件未生成';
});

test('生成URL扫码测试二维码', function() use ($outputDir) {
    QrCode::make('https://www.example.com/test')
        ->size(300)
        ->save($outputDir . '/scan_test_url.png');
    return file_exists($outputDir . '/scan_test_url.png') ? true : '文件未生成';
});

test('生成WiFi扫码测试二维码', function() use ($outputDir) {
    $wifi = QrCodeHelper::generateWifiString('TestWiFi', 'TestPass123', 'WPA');
    QrCode::make($wifi)
        ->size(300)
        ->save($outputDir . '/scan_test_wifi.png');
    return file_exists($outputDir . '/scan_test_wifi.png') ? true : '文件未生成';
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
echo "\n提示: 可以使用手机扫描以下文件验证二维码可读性:\n";
echo "- {$outputDir}/scan_test_basic.png (内容: SCAN_TEST_12345)\n";
echo "- {$outputDir}/scan_test_url.png (内容: https://www.example.com/test)\n";
echo "- {$outputDir}/scan_test_wifi.png (WiFi配置)\n";
exit(0);
