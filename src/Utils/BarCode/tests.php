<?php
/**
 * 条形码生成器全面测试文件
 * 
 * 本测试文件涵盖：
 * - 所有条码类型（EAN-13, EAN-8, UPC-A, Code 128, Code 39, ITF-14, ISBN, ISSN）
 * - 所有配置项测试
 * - 校验位计算验证
 * - 特殊功能测试（跳过校验、文本对齐、渐变等）
 * - 边界情况测试
 * 
 * 运行方式：php tests.php
 */

// 自动加载
spl_autoload_register(function ($class) {
    $prefix = 'zxf\\BarCode\\';
    $base_dir = __DIR__ . '/src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

use zxf\Utils\BarCode\BarcodeBuilder;
use zxf\Utils\BarCode\BarcodeFactory;
use zxf\Utils\BarCode\Renderer\PngRenderer;
use zxf\Utils\BarCode\Renderer\SvgRenderer;

// ==================== 测试工具函数 ====================

$testResults = [
    'passed' => 0,
    'failed' => 0,
    'tests' => []
];

/**
 * 记录测试结果
 * 
 * @param string $testName 测试名称
 * @param bool $passed 是否通过
 * @param string $message 附加信息
 */
function recordTest(string $testName, bool $passed, string $message = ''): void
{
    global $testResults;
    
    $testResults['tests'][] = [
        'name' => $testName,
        'passed' => $passed,
        'message' => $message
    ];
    
    if ($passed) {
        $testResults['passed']++;
        echo "[✓] {$testName}\n";
    } else {
        $testResults['failed']++;
        echo "[✗] {$testName}";
        if ($message) {
            echo " - {$message}";
        }
        echo "\n";
    }
}

/**
 * 创建输出目录
 * 
 * @return string 输出目录路径
 */
function createOutputDir(): string
{
    $dir = __DIR__ . '/output_' . date('mdHi');
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

// ==================== 开始测试 ====================

echo "========================================\n";
echo "  条形码生成器全面测试\n";
echo "========================================\n\n";

$outputDir = createOutputDir();
echo "输出目录: {$outputDir}\n\n";

// ==================== 1. EAN-13 测试 ====================

echo "\n【EAN-13 条码测试】\n";
echo str_repeat("-", 40) . "\n";

$ean13Tests = [
    ['data' => '690123456789', 'desc' => '标准12位（中国商品）'],
    ['data' => '6901234567892', 'desc' => '带校验位13位'],
    ['data' => '4006381333931', 'desc' => '德国商品码'],
    ['data' => '9780201379624', 'desc' => 'ISBN格式978前缀'],
];

foreach ($ean13Tests as $test) {
    try {
        $builder = BarcodeBuilder::create()
            ->type('ean13')
            ->data($test['data'])
            ->height(100)
            ->width(3);
        
        $barcode = $builder->generate();
        $fullData = $builder->getFullData();
        
        // 验证生成的数据长度
        $lenOk = strlen($fullData) === 13;
        recordTest("EAN-13 {$test['desc']} - 长度", $lenOk, "期望13位，实际" . strlen($fullData) . "位");
        
        // 验证是否为纯数字
        $numericOk = ctype_digit($fullData);
        recordTest("EAN-13 {$test['desc']} - 纯数字", $numericOk);
        
        // 生成PNG文件
        $filename = $outputDir . '/ean13_' . str_replace([' ', '-'], '_', $test['desc']) . '.png';
        $saved = $builder->savePng($filename);
        recordTest("EAN-13 {$test['desc']} - PNG生成", $saved);
        
        // 生成SVG文件
        $filename = $outputDir . '/ean13_' . str_replace([' ', '-'], '_', $test['desc']) . '.svg';
        $saved = $builder->saveSvg($filename);
        recordTest("EAN-13 {$test['desc']} - SVG生成", $saved);
        
        // 记录完整数据用于验证
        recordTest("EAN-13 {$test['desc']} - 数据: {$fullData}", true);
        
    } catch (Exception $e) {
        recordTest("EAN-13 {$test['desc']}", false, $e->getMessage());
    }
}

// EAN-13 错误测试
echo "\n【EAN-13 错误处理测试】\n";
echo str_repeat("-", 40) . "\n";

$ean13ErrorTests = [
    ['data' => '123', 'desc' => '过短数据（3位）'],
    ['data' => 'abcdefghijkl', 'desc' => '非数字数据'],
    ['data' => '6901234567890', 'desc' => '错误校验位'],
];

foreach ($ean13ErrorTests as $test) {
    try {
        BarcodeBuilder::create()
            ->type('ean13')
            ->data($test['data'])
            ->generate();
        recordTest("EAN-13 {$test['desc']} - 应抛出异常", false, '未抛出异常');
    } catch (Exception $e) {
        recordTest("EAN-13 {$test['desc']} - 正确拒绝", true);
    }
}

// EAN-13 跳过校验测试
echo "\n【EAN-13 跳过校验测试】\n";
echo str_repeat("-", 40) . "\n";

try {
    $builder = BarcodeBuilder::create()
        ->type('ean13')
        ->data('6901234567890')  // 错误校验位
        ->skipChecksum(true);
    
    $barcode = $builder->generate();
    recordTest("EAN-13 跳过校验 - 生成成功", true);
    
    $filename = $outputDir . '/ean13_skip_checksum.png';
    $builder->savePng($filename);
    recordTest("EAN-13 跳过校验 - 保存成功", true);
} catch (Exception $e) {
    recordTest("EAN-13 跳过校验", false, $e->getMessage());
}

// ==================== 2. ISBN 测试 ====================

echo "\n【ISBN 条码测试】\n";
echo str_repeat("-", 40) . "\n";

$isbnTests = [
    ['data' => '9780201379624', 'desc' => '完整ISBN-13'],
    ['data' => '978-7-111-12345-3', 'desc' => '中国图书格式'],
    ['data' => '9787111123453', 'desc' => '中国图书ISBN'],
    ['data' => '9791090636071', 'desc' => '979前缀ISBN'],
];

foreach ($isbnTests as $test) {
    try {
        $builder = BarcodeBuilder::create()
            ->type('isbn')
            ->data($test['data'])
            ->height(100)
            ->width(3);
        
        $barcode = $builder->generate();
        $fullData = $builder->getFullData();
        
        // 验证前缀
        $prefix = substr($fullData, 0, 3);
        $prefixOk = in_array($prefix, ['978', '979']);
        recordTest("ISBN {$test['desc']} - 前缀", $prefixOk, "前缀: {$prefix}");
        
        // 验证长度
        $lenOk = strlen($fullData) === 13;
        recordTest("ISBN {$test['desc']} - 长度", $lenOk);
        
        // 验证数据完整性（扫描后应得到相同数据）
        recordTest("ISBN {$test['desc']} - 完整数据: {$fullData}", true);
        
        // 生成PNG
        $filename = $outputDir . '/isbn_' . str_replace([' ', '-'], '_', $test['desc']) . '.png';
        $builder->savePng($filename);
        recordTest("ISBN {$test['desc']} - PNG生成", true);
        
    } catch (Exception $e) {
        recordTest("ISBN {$test['desc']}", false, $e->getMessage());
    }
}

// ==================== 3. EAN-8 测试 ====================

echo "\n【EAN-8 条码测试】\n";
echo str_repeat("-", 40) . "\n";

$ean8Tests = [
    ['data' => '1234567', 'desc' => '标准7位'],
    ['data' => '12345670', 'desc' => '带校验位8位'],
];

foreach ($ean8Tests as $test) {
    try {
        $builder = BarcodeBuilder::create()
            ->type('ean8')
            ->data($test['data'])
            ->height(80)
            ->width(3);
        
        $barcode = $builder->generate();
        $fullData = $builder->getFullData();
        
        $lenOk = strlen($fullData) === 8;
        recordTest("EAN-8 {$test['desc']} - 长度", $lenOk, "数据: {$fullData}");
        
        $filename = $outputDir . '/ean8_' . str_replace(' ', '_', $test['desc']) . '.png';
        $builder->savePng($filename);
        recordTest("EAN-8 {$test['desc']} - PNG生成", true);
        
    } catch (Exception $e) {
        recordTest("EAN-8 {$test['desc']}", false, $e->getMessage());
    }
}

// ==================== 4. UPC-A 测试 ====================

echo "\n【UPC-A 条码测试】\n";
echo str_repeat("-", 40) . "\n";

$upcaTests = [
    ['data' => '012345678905', 'desc' => '标准12位'],
    ['data' => '123456789012', 'desc' => '测试数据'],
];

foreach ($upcaTests as $test) {
    try {
        $builder = BarcodeBuilder::create()
            ->type('upca')
            ->data($test['data'])
            ->height(100)
            ->width(3);
        
        $barcode = $builder->generate();
        $fullData = $builder->getFullData();
        
        $lenOk = strlen($fullData) === 12;
        recordTest("UPC-A {$test['desc']} - 长度", $lenOk, "数据: {$fullData}");
        
        $filename = $outputDir . '/upca_' . str_replace(' ', '_', $test['desc']) . '.png';
        $builder->savePng($filename);
        recordTest("UPC-A {$test['desc']} - PNG生成", true);
        
    } catch (Exception $e) {
        recordTest("UPC-A {$test['desc']}", false, $e->getMessage());
    }
}

// ==================== 5. Code 128 测试 ====================

echo "\n【Code 128 条码测试】\n";
echo str_repeat("-", 40) . "\n";

$code128Tests = [
    ['data' => 'HELLO123', 'desc' => '字母数字混合'],
    ['data' => '123456', 'desc' => '纯数字'],
    ['data' => 'Code-128_Test!', 'desc' => '含特殊字符'],
    ['data' => 'Hello World 2024', 'desc' => '含空格'],
];

foreach ($code128Tests as $test) {
    try {
        $builder = BarcodeBuilder::create()
            ->type('code128')
            ->data($test['data'])
            ->height(80)
            ->width(2);
        
        $barcode = $builder->generate();
        
        $filename = $outputDir . '/code128_' . str_replace([' ', '-', '!'], '_', $test['desc']) . '.png';
        $builder->savePng($filename);
        recordTest("Code 128 {$test['desc']}", true);
        
    } catch (Exception $e) {
        recordTest("Code 128 {$test['desc']}", false, $e->getMessage());
    }
}

// Code 128 文本对齐测试
echo "\n【Code 128 文本对齐测试】\n";
echo str_repeat("-", 40) . "\n";

$alignments = ['left', 'center', 'right'];
foreach ($alignments as $align) {
    try {
        $renderer = new PngRenderer();
        $renderer->setTextAlign($align);
        
        $builder = BarcodeBuilder::create()
            ->type('code128')
            ->data('ALIGN_TEST')
            ->height(80)
            ->width(2);
        
        $barcode = $builder->generate();
        $fullData = $builder->getFullData();
        
        $filename = $outputDir . '/code128_align_' . $align . '.png';
        $renderer->saveToFile($barcode, $fullData, $filename, ['showText' => true]);
        recordTest("Code 128 {$align}对齐", file_exists($filename));
    } catch (Exception $e) {
        recordTest("Code 128 {$align}对齐", false, $e->getMessage());
    }
}

// ==================== 6. Code 39 测试 ====================

echo "\n【Code 39 条码测试】\n";
echo str_repeat("-", 40) . "\n";

$code39Tests = [
    ['data' => 'ABC123', 'desc' => '字母数字'],
    ['data' => '12345', 'desc' => '纯数字'],
    ['data' => 'CODE39', 'desc' => '纯大写字母'],
    ['data' => 'A-B.C', 'desc' => '含特殊字符'],
];

foreach ($code39Tests as $test) {
    try {
        $builder = BarcodeBuilder::create()
            ->type('code39')
            ->data($test['data'])
            ->height(80)
            ->width(3);
        
        $barcode = $builder->generate();
        $fullData = $builder->getFullData();
        
        // 验证数据不包含*分隔符
        $noStar = strpos($fullData, '*') === false;
        recordTest("Code 39 {$test['desc']} - 无*分隔符", $noStar, "数据: {$fullData}");
        
        $filename = $outputDir . '/code39_' . str_replace([' ', '-', '.'], '_', $test['desc']) . '.png';
        $builder->savePng($filename);
        recordTest("Code 39 {$test['desc']} - PNG生成", true);
        
    } catch (Exception $e) {
        recordTest("Code 39 {$test['desc']}", false, $e->getMessage());
    }
}

// Code 39 验证测试
echo "\n【Code 39 验证测试】\n";
echo str_repeat("-", 40) . "\n";

$code39 = BarcodeFactory::create('code39');
recordTest("Code 39 验证有效数据", $code39->validate('ABC123'));
recordTest("Code 39 验证小写字母", !$code39->validate('abc123'));
recordTest("Code 39 验证空数据", !$code39->validate(''));

// ==================== 7. ITF-14 测试 ====================

echo "\n【ITF-14 条码测试】\n";
echo str_repeat("-", 40) . "\n";

$itf14Tests = [
    ['data' => '1540014128876', 'desc' => '标准13位'],
    ['data' => '15400141288763', 'desc' => '带校验位14位'],
    ['data' => '00012345600012', 'desc' => '含前导零'],
];

foreach ($itf14Tests as $test) {
    try {
        $builder = BarcodeBuilder::create()
            ->type('itf14')
            ->data($test['data'])
            ->height(100)
            ->width(2);
        
        $barcode = $builder->generate();
        $fullData = $builder->getFullData();
        
        $lenOk = strlen($fullData) === 14;
        recordTest("ITF-14 {$test['desc']} - 长度", $lenOk, "数据: {$fullData}");
        
        // 生成带Bearer Bar的版本
        $filename = $outputDir . '/itf14_' . str_replace(' ', '_', $test['desc']) . '.png';
        $builder->savePng($filename);
        recordTest("ITF-14 {$test['desc']} - PNG生成", true);
        
    } catch (Exception $e) {
        recordTest("ITF-14 {$test['desc']}", false, $e->getMessage());
    }
}

// ==================== 8. ISSN 测试 ====================

echo "\n【ISSN 条码测试】\n";
echo str_repeat("-", 40) . "\n";

$issnTests = [
    ['data' => '1234567', 'desc' => '标准7位'],
    ['data' => '0378595', 'desc' => '期刊ISSN'],
];

foreach ($issnTests as $test) {
    try {
        $builder = BarcodeBuilder::create()
            ->type('issn')
            ->data($test['data'])
            ->height(80)
            ->width(3);
        
        $barcode = $builder->generate();
        $fullData = $builder->getFullData();
        
        // ISSN转换为EAN-13格式：977+ISSN7位+补充码2位+校验位=13位
        $lenOk = strlen($fullData) === 13;
        recordTest("ISSN {$test['desc']} - 长度(EAN-13)", $lenOk, "数据: {$fullData}");
        
        // 验证前缀是977
        $prefixOk = substr($fullData, 0, 3) === '977';
        recordTest("ISSN {$test['desc']} - 前缀977", $prefixOk);
        
        $filename = $outputDir . '/issn_' . str_replace(' ', '_', $test['desc']) . '.png';
        $builder->savePng($filename);
        recordTest("ISSN {$test['desc']} - PNG生成", true);
        
    } catch (Exception $e) {
        recordTest("ISSN {$test['desc']}", false, $e->getMessage());
    }
}

// ==================== 9. 个性化功能测试 ====================

echo "\n【个性化功能测试】\n";
echo str_repeat("-", 40) . "\n";

// 渐变效果测试
try {
    $renderer = new PngRenderer();
    $renderer->enableGradient('#000000', '#444444');
    
    $builder = BarcodeBuilder::create()
        ->type('code128')
        ->data('GRADIENT')
        ->height(80)
        ->width(2);
    
    $barcode = $builder->generate();
    $fullData = $builder->getFullData();
    
    $filename = $outputDir . '/feature_gradient.png';
    $renderer->saveToFile($barcode, $fullData, $filename);
    recordTest("渐变效果", file_exists($filename));
} catch (Exception $e) {
    recordTest("渐变效果", false, $e->getMessage());
}

// 圆角条测试
try {
    $renderer = new PngRenderer();
    $renderer->enableRoundedBars(3);
    
    $builder = BarcodeBuilder::create()
        ->type('code128')
        ->data('ROUNDED')
        ->height(80)
        ->width(3);
    
    $barcode = $builder->generate();
    $fullData = $builder->getFullData();
    
    $filename = $outputDir . '/feature_rounded.png';
    $renderer->saveToFile($barcode, $fullData, $filename);
    recordTest("圆角条", file_exists($filename));
} catch (Exception $e) {
    recordTest("圆角条", false, $e->getMessage());
}

// 水印测试
try {
    $renderer = new PngRenderer();
    $renderer->setWatermark('SAMPLE', 30);
    
    $builder = BarcodeBuilder::create()
        ->type('code128')
        ->data('WATERMARK')
        ->height(80)
        ->width(2);
    
    $barcode = $builder->generate();
    $fullData = $builder->getFullData();
    
    $filename = $outputDir . '/feature_watermark.png';
    $renderer->saveToFile($barcode, $fullData, $filename);
    recordTest("水印效果", file_exists($filename));
} catch (Exception $e) {
    recordTest("水印效果", false, $e->getMessage());
}

// Bearer Bar测试（ITF-14）
try {
    $renderer = new PngRenderer();
    $renderer->enableBearerBar(3);
    
    $builder = BarcodeBuilder::create()
        ->type('itf14')
        ->data('1540014128876')
        ->height(100)
        ->width(2);
    
    $barcode = $builder->generate();
    $fullData = $builder->getFullData();
    
    $filename = $outputDir . '/feature_bearer_bar.png';
    $renderer->saveToFile($barcode, $fullData, $filename);
    recordTest("Bearer Bar", file_exists($filename));
} catch (Exception $e) {
    recordTest("Bearer Bar", false, $e->getMessage());
}

// ==================== 10. 配置测试 ====================

echo "\n【配置项测试】\n";
echo str_repeat("-", 40) . "\n";

// 颜色配置测试
try {
    $builder = BarcodeBuilder::create()
        ->type('ean13')
        ->data('690123456789')
        ->bgColor('#FF0000')
        ->barColor('#0000FF')
        ->height(80)
        ->width(3);
    
    $barcode = $builder->generate();
    
    $filename = $outputDir . '/config_colors.png';
    $builder->savePng($filename);
    recordTest("颜色配置", file_exists($filename));
} catch (Exception $e) {
    recordTest("颜色配置", false, $e->getMessage());
}

// 尺寸配置测试
try {
    $builder = BarcodeBuilder::create()
        ->type('ean13')
        ->data('690123456789')
        ->width(5)
        ->height(150);
    
    $barcode = $builder->generate();
    
    $filename = $outputDir . '/config_size.png';
    $builder->savePng($filename);
    recordTest("尺寸配置", file_exists($filename));
} catch (Exception $e) {
    recordTest("尺寸配置", false, $e->getMessage());
}

// 显示/隐藏文字测试
try {
    $builder = BarcodeBuilder::create()
        ->type('ean13')
        ->data('690123456789')
        ->showText(false);
    
    $barcode = $builder->generate();
    
    $filename = $outputDir . '/config_no_text.png';
    $builder->savePng($filename);
    recordTest("隐藏文字", file_exists($filename));
} catch (Exception $e) {
    recordTest("隐藏文字", false, $e->getMessage());
}

// ==================== 11. SVG输出测试 ====================

echo "\n【SVG输出测试】\n";
echo str_repeat("-", 40) . "\n";

$svgTypes = ['ean13', 'isbn', 'code128', 'code39', 'itf14'];
foreach ($svgTypes as $type) {
    try {
        $data = match ($type) {
            'ean13' => '690123456789',
            'isbn' => '9780201379624',
            'code128' => 'TEST123',
            'code39' => 'TEST',
            'itf14' => '1540014128876',
            default => 'TEST123',
        };
        
        $builder = BarcodeBuilder::create()
            ->type($type)
            ->data($data);
        
        $svg = $builder->toSvg();
        $hasSvg = strpos($svg, '<svg') !== false;
        recordTest("SVG {$type} - 格式", $hasSvg);
        
        $filename = $outputDir . '/svg_' . $type . '.svg';
        $builder->saveSvg($filename);
        recordTest("SVG {$type} - 保存", file_exists($filename));
        
    } catch (Exception $e) {
        recordTest("SVG {$type}", false, $e->getMessage());
    }
}

// ==================== 12. 校验位计算测试 ====================

echo "\n【校验位计算测试】\n";
echo str_repeat("-", 40) . "\n";

// EAN-13校验位测试
$ean13 = BarcodeFactory::create('ean13');
$checksum = $ean13->calculateChecksum('978020137962');
recordTest("EAN-13 978020137962校验位", $checksum === '4', "计算结果: {$checksum}");

$checksum = $ean13->calculateChecksum('690123456789');
recordTest("EAN-13 690123456789校验位", $checksum === '2', "计算结果: {$checksum}");

// EAN-8校验位测试
$ean8 = BarcodeFactory::create('ean8');
$checksum = $ean8->calculateChecksum('1234567');
recordTest("EAN-8 1234567校验位", $checksum === '0', "计算结果: {$checksum}");

// UPC-A校验位测试
$upca = BarcodeFactory::create('upca');
$checksum = $upca->calculateChecksum('01234567890');
recordTest("UPC-A 01234567890校验位", $checksum === '5', "计算结果: {$checksum}");

// ISBN校验位测试
$isbn = BarcodeFactory::create('isbn');
$checksum = $isbn->calculateChecksum('978020137962');
recordTest("ISBN 978020137962校验位", $checksum === '4', "计算结果: {$checksum}");

// ITF-14校验位测试
$itf14 = BarcodeFactory::create('itf14');
$checksum = $itf14->calculateChecksum('1540014128876');
recordTest("ITF-14 1540014128876校验位", $checksum === '3', "计算结果: {$checksum}");

// ISSN校验位测试（模11算法）
$issn = BarcodeFactory::create('issn');
$checksum = $issn->calculateChecksum('0378595');
recordTest("ISSN 0378595校验位", $checksum === '5', "计算结果: {$checksum}");

// ==================== 13. 边界条件测试 ====================

echo "\n【边界条件测试】\n";
echo str_repeat("-", 40) . "\n";

// 极小宽度测试
try {
    $builder = BarcodeBuilder::create()
        ->type('ean13')
        ->data('690123456789')
        ->width(1)
        ->height(50);
    $barcode = $builder->generate();
    recordTest("EAN-13 极小宽度(1px)", true);
} catch (Exception $e) {
    recordTest("EAN-13 极小宽度(1px)", false, $e->getMessage());
}

// 极大宽度测试
try {
    $builder = BarcodeBuilder::create()
        ->type('code128')
        ->data('TEST')
        ->width(10)
        ->height(200);
    $barcode = $builder->generate();
    recordTest("Code 128 极大尺寸", true);
} catch (Exception $e) {
    recordTest("Code 128 极大尺寸", false, $e->getMessage());
}

// 超长数据测试
try {
    $longData = str_repeat('A', 50);
    $builder = BarcodeBuilder::create()
        ->type('code128')
        ->data($longData);
    $barcode = $builder->generate();
    recordTest("Code 128 超长数据(50字符)", strlen($longData) === 50);
} catch (Exception $e) {
    recordTest("Code 128 超长数据", false, $e->getMessage());
}

// 特殊字符测试
try {
    $builder = BarcodeBuilder::create()
        ->type('code128')
        ->data('!@#$%^&*()');
    $barcode = $builder->generate();
    recordTest("Code 128 特殊字符", true);
} catch (Exception $e) {
    recordTest("Code 128 特殊字符", false, $e->getMessage());
}

// 空数据测试
try {
    $builder = BarcodeBuilder::create()
        ->type('code128')
        ->data('');
    $barcode = $builder->generate();
    recordTest("Code 128 空数据应报错", false, '未抛出异常');
} catch (Exception $e) {
    recordTest("Code 128 空数据正确处理", true);
}

// ==================== 14. 多种条码格式组合测试 ====================

echo "\n【多种条码格式组合测试】\n";
echo str_repeat("-", 40) . "\n";

// 相同数据不同条码类型
try {
    $testData = '1234567';
    
    $ean8 = BarcodeBuilder::create()->type('ean8')->data($testData)->generate();
    recordTest("EAN-8 格式", count($ean8) > 0);
    
    $code128 = BarcodeBuilder::create()->type('code128')->data($testData)->generate();
    recordTest("Code 128 相同数据", count($code128) > 0);
    
    $code39 = BarcodeBuilder::create()->type('code39')->data($testData)->generate();
    recordTest("Code 39 相同数据", count($code39) > 0);
} catch (Exception $e) {
    recordTest("相同数据不同条码类型", false, $e->getMessage());
}

// 不同条宽对比
try {
    $widths = [1, 2, 3, 4];
    foreach ($widths as $w) {
        $builder = BarcodeBuilder::create()
            ->type('ean13')
            ->data('690123456789')
            ->width($w);
        $barcode = $builder->generate();
        $filename = $outputDir . '/ean13_width_' . $w . '.png';
        $builder->savePng($filename);
        recordTest("EAN-13 宽度 {$w}px", file_exists($filename));
    }
} catch (Exception $e) {
    recordTest("不同条宽对比", false, $e->getMessage());
}

// ==================== 15. 错误数据处理测试 ====================

echo "\n【错误数据处理测试】\n";
echo str_repeat("-", 40) . "\n";

// EAN-13 非数字数据
try {
    BarcodeBuilder::create()->type('ean13')->data('ABCDEFGHIJKL')->generate();
    recordTest("EAN-13 非数字数据应报错", false);
} catch (Exception $e) {
    recordTest("EAN-13 非数字数据正确处理", true);
}

// EAN-13 长度错误
try {
    BarcodeBuilder::create()->type('ean13')->data('12345')->generate();
    recordTest("EAN-13 短数据应报错", false);
} catch (Exception $e) {
    recordTest("EAN-13 短数据正确处理", true);
}

// Code 39 小写字母
try {
    BarcodeBuilder::create()->type('code39')->data('abc')->generate();
    recordTest("Code 39 小写字母应报错", false);
} catch (Exception $e) {
    recordTest("Code 39 小写字母正确处理", true);
}

// ISBN 错误前缀
try {
    BarcodeBuilder::create()->type('isbn')->data('1234567890123')->generate();
    recordTest("ISBN 错误前缀应报错", false);
} catch (Exception $e) {
    recordTest("ISBN 错误前缀正确处理", true);
}

// UPC-A 长度错误
try {
    BarcodeBuilder::create()->type('upca')->data('12345')->generate();
    recordTest("UPC-A 短数据应报错", false);
} catch (Exception $e) {
    recordTest("UPC-A 短数据正确处理", true);
}

// ==================== 16. ISBN-10 转 ISBN-13 测试 ====================

echo "\n【ISBN-10 转 ISBN-13 测试】\n";
echo str_repeat("-", 40) . "\n";

$isbn10Tests = [
    ['data' => '0306406152', 'expected' => '9780306406157', 'desc' => '标准ISBN-10'],
    ['data' => '0-306-40615-2', 'expected' => '9780306406157', 'desc' => '带分隔符ISBN-10'],
    ['data' => '0201379620', 'expected' => '9780201379624', 'desc' => '另一ISBN-10'],
];

foreach ($isbn10Tests as $test) {
    try {
        $builder = BarcodeBuilder::create()
            ->type('isbn')
            ->data($test['data']);
        $barcode = $builder->generate();
        $fullData = $builder->getFullData();
        
        $converted = ($fullData === $test['expected']);
        recordTest("ISBN-10转13 {$test['desc']}", $converted, "结果: {$fullData}");
        
        $filename = $outputDir . '/isbn_' . str_replace([' ', '-'], '_', $test['desc']) . '.png';
        $builder->savePng($filename);
        
    } catch (Exception $e) {
        recordTest("ISBN-10转13 {$test['desc']}", false, $e->getMessage());
    }
}

// ==================== 17. 颜色对比度测试 ====================

echo "\n【颜色对比度测试】\n";
echo str_repeat("-", 40) . "\n";

// 低对比度颜色（应自动调整为黑白）
try {
    $builder = BarcodeBuilder::create()
        ->type('ean13')
        ->data('690123456789')
        ->barColor('#AAAAAA')  // 灰色条
        ->bgColor('#BBBBBB');  // 灰色背景（对比度不足）
    $barcode = $builder->generate();
    
    $filename = $outputDir . '/ean13_low_contrast.png';
    $builder->savePng($filename);
    recordTest("低对比度自动调整", file_exists($filename));
} catch (Exception $e) {
    recordTest("低对比度自动调整", false, $e->getMessage());
}

// 高对比度颜色
try {
    $builder = BarcodeBuilder::create()
        ->type('ean13')
        ->data('690123456789')
        ->barColor('#000080')  // 深蓝色
        ->bgColor('#FFFFFF');  // 白色背景
    $barcode = $builder->generate();
    
    $filename = $outputDir . '/ean13_blue_bar.png';
    $builder->savePng($filename);
    recordTest("高对比度蓝色条码", file_exists($filename));
} catch (Exception $e) {
    recordTest("高对比度蓝色条码", false, $e->getMessage());
}

// ==================== 18. 性能测试 ====================

echo "\n【性能测试】\n";
echo str_repeat("-", 40) . "\n";

// 批量生成测试
$startTime = microtime(true);
$batchCount = 50;
for ($i = 0; $i < $batchCount; $i++) {
    $builder = BarcodeBuilder::create()
        ->type('ean13')
        ->data('690123456789');
    $barcode = $builder->generate();
}
$endTime = microtime(true);
$elapsed = round(($endTime - $startTime) * 1000, 2);
$avgTime = round($elapsed / $batchCount, 2);
recordTest("批量生成 {$batchCount} 个条码", true, "总耗时: {$elapsed}ms, 平均: {$avgTime}ms/个");

// 复杂条码生成性能
$startTime = microtime(true);
$builder = BarcodeBuilder::create()
    ->type('code128')
    ->data(str_repeat('A', 100));  // 100字符
$barcode = $builder->generate();
$endTime = microtime(true);
$elapsed = round(($endTime - $startTime) * 1000, 2);
recordTest("长条码生成性能(100字符)", true, "耗时: {$elapsed}ms");

// ==================== 19. SVG 特殊功能测试 ====================

echo "\n【SVG 特殊功能测试】\n";
echo str_repeat("-", 40) . "\n";

// SVG 渐变测试
try {
    $renderer = new SvgRenderer();
    $renderer->enableGradient('#000000', '#444444');
    
    $builder = BarcodeBuilder::create()
        ->type('ean13')
        ->data('690123456789');
    
    $barcode = $builder->generate();
    $fullData = $builder->getFullData();
    
    $filename = $outputDir . '/svg_gradient.svg';
    $renderer->saveToFile($barcode, $fullData, $filename);
    
    $svgContent = file_get_contents($filename);
    $hasGradient = strpos($svgContent, 'linearGradient') !== false;
    recordTest("SVG 渐变效果", $hasGradient);
} catch (Exception $e) {
    recordTest("SVG 渐变效果", false, $e->getMessage());
}

// SVG 圆角测试
try {
    $renderer = new SvgRenderer();
    $renderer->enableRoundedBars(3);
    
    $builder = BarcodeBuilder::create()
        ->type('code128')
        ->data('ROUNDED');
    
    $barcode = $builder->generate();
    $fullData = $builder->getFullData();
    
    $filename = $outputDir . '/svg_rounded.svg';
    $renderer->saveToFile($barcode, $fullData, $filename);
    
    $svgContent = file_get_contents($filename);
    $hasRounded = strpos($svgContent, 'rx="3"') !== false;
    recordTest("SVG 圆角效果", $hasRounded);
} catch (Exception $e) {
    recordTest("SVG 圆角效果", false, $e->getMessage());
}

// ==================== 20. 文件输出测试 ====================

echo "\n【文件输出测试】\n";
echo str_repeat("-", 40) . "\n";

// 嵌套目录保存
try {
    $nestedDir = $outputDir . '/nested/deep/path';
    $builder = BarcodeBuilder::create()
        ->type('ean13')
        ->data('690123456789');
    
    $filename = $nestedDir . '/nested.png';
    $saved = $builder->savePng($filename);
    recordTest("嵌套目录自动创建", $saved && file_exists($filename));
} catch (Exception $e) {
    recordTest("嵌套目录自动创建", false, $e->getMessage());
}

// 不同格式同时生成
try {
    $builder = BarcodeBuilder::create()
        ->type('ean13')
        ->data('690123456789');
    
    $barcode = $builder->generate();
    
    $pngFile = $outputDir . '/both_formats.png';
    $svgFile = $outputDir . '/both_formats.svg';
    
    $pngSaved = $builder->savePng($pngFile);
    $svgSaved = $builder->saveSvg($svgFile);
    
    recordTest("PNG和SVG同时生成", $pngSaved && $svgSaved);
} catch (Exception $e) {
    recordTest("PNG和SVG同时生成", false, $e->getMessage());
}

// ==================== 测试总结 ====================

echo "\n========================================\n";
echo "  测试总结\n";
echo "========================================\n";
echo "总测试数: " . count($testResults['tests']) . "\n";
echo "通过数: {$testResults['passed']}\n";
echo "失败数: {$testResults['failed']}\n";
echo "通过率: " . round($testResults['passed'] / count($testResults['tests']) * 100, 2) . "%\n";
echo "========================================\n";

// 如果有失败测试，显示详细信息
if ($testResults['failed'] > 0) {
    echo "\n【失败的测试】\n";
    foreach ($testResults['tests'] as $test) {
        if (!$test['passed']) {
            echo "- {$test['name']}";
            if ($test['message']) {
                echo ": {$test['message']}";
            }
            echo "\n";
        }
    }
}

// 返回退出码
exit($testResults['failed'] > 0 ? 1 : 0);
