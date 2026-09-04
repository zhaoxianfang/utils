# zxf/BarCode - 一维条形码生成器

[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

一个基于 PHP 8.2+ 的标准一维条形码生成器，严格遵循 GS1 国家标准和行业规范，支持多种条码格式和丰富的自定义配置。

## 目录

- [特性](#特性)
- [安装](#安装)
- [快速开始](#快速开始)
- [支持的条码类型](#支持的条码类型)
- [高级配置](#高级配置)
- [技术规范](#技术规范)
- [API参考](#api参考)
- [常见问题](#常见问题)
- [目录结构](#目录结构)
- [许可证](#许可证)

## 特性

- **9种条码类型**：EAN-13、EAN-8、UPC-A、Code 128、Code 39、Codabar、ITF-14、ISSN、ISBN
- **符合GS1标准**：严格遵循国际条形码编码规范，确保100%可识别
- **长竖线特征**：EAN/UPC 条码支持标准长竖线（保护符突出显示）
- **多种输出格式**：支持 PNG 图片、SVG 矢量图和 HTML/CSS 渲染
- **指定宽高**：支持设置生成图片的整体宽度和高度
- **Base64输出**：支持直接返回 Base64 编码数据 URI
- **浏览器输出**：支持直接输出 PNG / SVG / HTML 到浏览器
- **透明背景**：SVG 支持透明背景（设置 `bgColor('transparent')`）
- **丰富的自定义**：颜色、尺寸、字体、渐变、圆角、水印等
- **增强水印**：支持 9 种位置定位、旋转、TTF 字体、斜向平铺
- **Bearer Bar**：ITF-14 支持上下边框（符合物流包装标准）
- **文本对齐**：支持条码下方文本的左 / 中 / 右对齐
- **跳过校验**：支持跳过校验位验证（生成非标准条码）
- **批量生成**：支持批量生成多个条码
- **辅助工具**：提供条码验证、校验位计算、Base64 生成等工具

## 安装

```bash
composer require zxf/bar-code
```

## 快速开始

```php
use zxf\Utils\BarCode\BarcodeBuilder;

// 生成 EAN-13 条码并保存
BarcodeBuilder::create()
    ->type('ean13')
    ->data('690123456789')  // 12位数据，自动计算校验位
    ->savePng('barcode.png');

// 生成 ITF-14 条码
BarcodeBuilder::create()
    ->type('itf14')
    ->data('1540014128876')
    ->savePng('itf14.png');
```

## 支持的条码类型

### 1. EAN-13（欧洲商品编号）

```php
BarcodeBuilder::create()
    ->type('ean13')
    ->data('690123456789')  // 12位或13位数字
    ->height(100)
    ->width(200)            // 条码内容总宽度（像素），0表示自动
    ->savePng('ean13.png');
```

**特征**：
- 13位纯数字
- 3组长竖线（起始 / 分隔 / 终止保护符）
- 数字分「1+6+6」段显示
- 第1位通过左侧奇偶模式隐含编码

### 2. EAN-8（短版EAN）

```php
BarcodeBuilder::create()
    ->type('ean8')
    ->data('1234567')  // 7位或8位数字
    ->savePng('ean8.png');
```

**特征**：
- 8位纯数字
- 用于小包装商品
- 2组长竖线（起始 / 终止）

### 3. UPC-A（北美商品码）

```php
BarcodeBuilder::create()
    ->type('upca')
    ->data('012345678905')  // 12位数字
    ->savePng('upca.png');
```

**特征**：
- 12位纯数字
- 北美地区零售专用
- 2组长竖线（起始 / 终止）
- 数字分「1+5+5+1」段

### 4. Code 128（高密度字母数字码）

```php
BarcodeBuilder::create()
    ->type('code128')
    ->data('Hello123!')  // 支持数字、字母、符号
    ->savePng('code128.png');
```

**特征**：
- 可变长度
- 支持数字 / 字母 / 符号
- 高密度编码
- 无长竖线

### 5. Code 39（工业标准码）

```php
BarcodeBuilder::create()
    ->type('code39')
    ->data('ABC-123')  // 数字、大写字母、部分符号
    ->savePng('code39.png');
```

**特征**：
- 可变长度
- 数字 + 大写字母 + 特殊符号
- 条空等宽
- 自校验

### 6. ITF-14（物流包装码）

```php
BarcodeBuilder::create()
    ->type('itf14')
    ->data('1540014128876')  // 13位或14位数字
    ->bearerBar(3)            // 启用上下边框
    ->savePng('itf14.png');
```

**特征**：
- 14位纯数字
- 交叉25码结构
- 耐磨损设计
- 支持 Bearer Bar（上下边框）

### 7. ISSN（国际标准期刊号）

```php
BarcodeBuilder::create()
    ->type('issn')
    ->data('1234567')  // 7位或8位数字
    ->savePng('issn.png');
```

**特征**：
- 8位纯数字
- 期刊专用
- EAN-8 变体

### 8. Codabar（NW-7）

```php
BarcodeBuilder::create()
    ->type('codabar')
    ->data('A123456B')  // 可包含起始/终止符 A/B/C/D
    ->savePng('codabar.png');
```

**特征**：
- 离散型自校验条码
- 支持数字和 - $ : / . +
- 起始/终止符为 A, B, C, D
- 广泛应用于图书馆、血库

### 9. ISBN（国际标准书号）

```php
// ISBN-13（12位数据自动计算校验位，或13位完整 ISBN）
BarcodeBuilder::create()
    ->type('isbn')
    ->data('978020137962')  // 自动补全校验位
    ->savePng('isbn13.png');

// ISBN-13 带连字符（自动去除格式化符号）
BarcodeBuilder::create()
    ->type('isbn')
    ->data('978-3-16-148410-0')
    ->savePng('isbn13_formatted.png');

// ISBN-10 自动转换为 ISBN-13（EAN-13 条码）
BarcodeBuilder::create()
    ->type('isbn')
    ->data('0-306-40615-2')
    ->savePng('isbn10_convert.png');
```

**特征**：
- 支持 ISBN-10 和 ISBN-13 自动识别
- ISBN-10 自动转换为 ISBN-13（加 978 前缀，重新计算 EAN-13 校验位）
- 自动校验位验证与补全
- 支持带连字符和空格的格式化输入
- 条码基于 EAN-13 标准，兼容所有图书零售扫描系统

**辅助方法**：
```php
$generator = BarcodeFactory::create('isbn');

// ISBN-10 校验位计算（模 11，可能为 X）
$check = $generator->calculateIsbn10Checksum('030640615'); // 返回 '2'

// ISBN-13 校验位计算（EAN-13 模 10）
$check = $generator->calculateIsbn13Checksum('978020137962'); // 返回 '4'

// ISBN-10 转 ISBN-13
$isbn13 = $generator->convertIsbn10To13('0306406152'); // 返回 '9780306406157'

// ISBN-13 转 ISBN-10（仅 978 前缀）
$isbn10 = $generator->convertIsbn13To10('9780306406157'); // 返回 '0306406152'

// 格式化输出
$formatted = $generator->formatIsbn('9783161484100'); // 返回 '978-3-16-148410-0'
```

## 高级配置

### 尺寸设置

```php
BarcodeBuilder::create()
    ->type('ean13')
    ->data('690123456789')
    ->width(200)      // 条码内容总宽度（像素），0表示自动
    ->height(100)     // 条码高度（像素）
    ->savePng('barcode.png');
```

### 指定整体宽度和高度

```php
BarcodeBuilder::create()
    ->type('code128')
    ->data('EXAMPLE')
    ->totalWidth(400)   // 生成图片的整体宽度（像素）
    ->totalHeight(120)  // 生成图片的整体高度（像素）
    ->savePng('sized.png');
```

### 颜色配置

```php
BarcodeBuilder::create()
    ->type('ean13')
    ->data('690123456789')
    ->barColor('#000000')  // 条颜色
    ->bgColor('#FFFFFF')   // 背景颜色
    ->savePng('barcode.png');
```

### SVG 透明背景

```php
BarcodeBuilder::create()
    ->type('code128')
    ->data('TRANSPARENT')
    ->transparentBackground(true)
    ->saveSvg('transparent.svg');
```

### 文字显示

```php
BarcodeBuilder::create()
    ->type('ean13')
    ->data('690123456789')
    ->showText(true)   // 显示文字（默认true）
    ->savePng('with_text.png');

// 隐藏文字
BarcodeBuilder::create()
    ->type('ean13')
    ->data('690123456789')
    ->showText(false)
    ->savePng('no_text.png');
```

### 文本对齐与多行文本

```php
BarcodeBuilder::create()
    ->type('code128')
    ->data('ALIGN_TEST')
    ->textAlign('right')  // left, center, right
    ->savePng('aligned.png');

// 多行文本（使用换行符分隔）
BarcodeBuilder::create()
    ->type('code128')
    ->data("LINE1\nLINE2\nLINE3")
    ->fontSize(14)
    ->savePng('multiline.png');

// 使用 TTF 字体（支持更丰富的排版）
BarcodeBuilder::create()
    ->type('code128')
    ->data('TTF Font')
    ->fontPath('/path/to/font.ttf')
    ->fontSize(16)
    ->savePng('ttf_text.png');
```

### 个性化效果

```php
// 渐变效果
BarcodeBuilder::create()
    ->type('code128')
    ->data('GRADIENT')
    ->gradient('#000000', '#444444')
    ->savePng('gradient.png');

// 圆角条
BarcodeBuilder::create()
    ->type('code128')
    ->data('ROUNDED')
    ->roundedBars(3)
    ->savePng('rounded.png');

// 水印（基础）
BarcodeBuilder::create()
    ->type('code128')
    ->data('WATERMARK')
    ->watermark('SAMPLE', 50, 16, '#888888', 0)
    ->watermarkPosition('center')
    ->savePng('watermark.png');

// 水印（斜向平铺）
BarcodeBuilder::create()
    ->type('code128')
    ->data('WATERMARK')
    ->watermark('SAMPLE', 50, 16, '#AAAAAA', -45)
    ->savePng('watermark_tiled.png');

// 水印（TTF字体 + 指定位置）
BarcodeBuilder::create()
    ->type('code128')
    ->data('WATERMARK')
    ->watermark('Sample', 60, 20, '#666666', 0)
    ->watermarkPosition('bottom-right')
    ->watermarkFontPath('/path/to/font.ttf')
    ->savePng('watermark_ttf.png');

// Bearer Bar（ITF-14上下边框）
BarcodeBuilder::create()
    ->type('itf14')
    ->data('1540014128876')
    ->bearerBar(3)
    ->savePng('itf14_bearer.png');
```

### 跳过校验位验证

```php
// 生成非标准条码（跳过校验位验证）
BarcodeBuilder::create()
    ->type('ean13')
    ->data('6901234567890')  // 校验位错误
    ->skipChecksum(true)
    ->savePng('no_checksum.png');
```

### 直接输出到浏览器

```php
// 直接输出 PNG 到浏览器
BarcodeBuilder::create()
    ->type('ean13')
    ->data('690123456789')
    ->outputPng();

// 直接输出 SVG 到浏览器
BarcodeBuilder::create()
    ->type('ean13')
    ->data('690123456789')
    ->outputSvg();

// 输出 HTML 到浏览器（使用 div/CSS 渲染）
BarcodeBuilder::create()
    ->type('code128')
    ->data('HTML-TEST')
    ->outputHtml();

// 保存为 HTML 文件
BarcodeBuilder::create()
    ->type('code39')
    ->data('TEST123')
    ->saveHtml('barcode.html');

// 获取 HTML 字符串
$html = BarcodeBuilder::create()
    ->type('ean13')
    ->data('690123456789')
    ->toHtml();
```

### Base64 输出

```php
// 获取 PNG 的 Base64
$base64 = BarcodeBuilder::create()
    ->type('ean13')
    ->data('690123456789')
    ->toBase64('png');

echo '<img src="' . $base64 . '" alt="Barcode">';

// 获取 SVG 的 Base64
$base64Svg = BarcodeBuilder::create()
    ->type('ean13')
    ->data('690123456789')
    ->toBase64('svg');
```

## 校验位计算

```php
use zxf\Utils\BarCode\BarcodeFactory;

$ean13 = BarcodeFactory::create('ean13');
$checksum = $ean13->calculateChecksum('690123456789');
echo "校验位: {$checksum}";  // 输出: 2
```

## 验证数据格式

```php
use zxf\Utils\BarCode\BarcodeFactory;

$ean13 = BarcodeFactory::create('ean13');
if ($ean13->validate('690123456789')) {
    echo "数据格式有效";
}
```

## 使用 SVG 格式

```php
// 生成 SVG
$svg = BarcodeBuilder::create()
    ->type('ean13')
    ->data('690123456789')
    ->toSvg();

// 保存 SVG
BarcodeBuilder::create()
    ->type('ean13')
    ->data('690123456789')
    ->saveSvg('barcode.svg');
```

## 静区设置

```php
use zxf\Utils\BarCode\BarcodeFactory;

$ean13 = BarcodeFactory::create('ean13');
$ean13->setQuietZone(15);  // 设置静区为15个模块（默认11）
```

## 条码类型说明

| 条码类型       | 数据长度 | 字符集                 | 长竖线 | 应用场景   |
|----------------|----------|------------------------|--------|------------|
| EAN-13         | 13位     | 数字                   | 有     | 零售商品   |
| EAN-8          | 8位      | 数字                   | 有     | 小包装商品 |
| UPC-A          | 12位     | 数字                   | 有     | 北美零售   |
| ISSN           | 8位      | 数字                   | 有     | 期刊       |
| ISBN           | 13位     | 数字                   | 有     | 图书出版   |
| Code 128       | 可变     | 全ASCII                | 无     | 物流/仓储  |
| Code 39        | 可变     | 数字+大写字母+特殊符号 | 无     | 工业/医疗  |
| Codabar (NW-7) | 可变     | 数字+ -$:/.+           | 无     | 图书馆/血库|
| ITF-14         | 14位     | 数字                   | 无     | 物流包装   |

## 技术规范

### EAN-13 编码结构

```
[静区11模块][起始符101][左侧6位42模块][分隔符01010][右侧6位42模块][终止符101][静区11模块]
```

- 总模块数：117
- 长竖线位置：11, 13, 56, 58, 101, 103

### 校验位算法（MOD 10）

1. 从右向左，奇数位乘以3，偶数位乘以1
2. 求和
3. 校验位 = (10 - (和 mod 10)) mod 10

## API参考

### BarcodeBuilder 方法

| 方法                         | 参数                          | 说明                                     |
|------------------------------|-------------------------------|------------------------------------------|
| `create()`                   | -                             | 静态创建构建器实例                       |
| `type($type)`                | string $type                  | 设置条码类型                             |
| `data($data)`                | string $data                  | 设置条码数据                             |
| `width($width)`              | int $width                    | 设置条码内容总宽度（像素），0表示自动    |
| `height($height)`            | int $height                   | 设置条码高度（像素）                     |
| `totalWidth($width)`         | int $width                    | 设置生成图片的整体宽度                   |
| `totalHeight($height)`       | int $height                   | 设置生成图片的整体高度                   |
| `showText($show)`            | bool $show                    | 是否显示文字                             |
| `bgColor($color)`            | string $color                 | 设置背景颜色                             |
| `barColor($color)`           | string $color                 | 设置条颜色                               |
| `skipChecksum($skip)`        | bool $skip                    | 是否跳过校验位验证                       |
| `textAlign($align)`          | string $align                 | 文本对齐（left/center/right）            |
| `fontSize($size)`            | int $size                     | 设置字体大小（像素）                     |
| `fontPath($path)`            | string $path                  | 设置TTF字体路径                          |
| `margin(...)`                | int...                        | 设置边距（CSS margin逻辑）               |
| `quietZone($show, $width)`   | bool, int                     | 设置静区显示与宽度                       |
| `longBarRatio($ratio)`       | float $ratio                  | 设置长竖线高度比例                       |
| `border($width, $color)`     | int, string                   | 设置图片边框                             |
| `watermark(...)`             | string, int, int, string, int | 设置水印（文本/透明度/字号/颜色/角度）   |
| `watermarkPosition($pos)`    | string $pos                   | 水印位置（9种）                          |
| `watermarkFontPath($path)`   | string\|null $path             | 水印TTF字体路径                          |
| `bearerBar($width)`          | int $width                    | 启用ITF-14上下边框                       |
| `transparentBackground($t)`  | bool $t                       | SVG透明背景                              |
| `roundedBars($radius)`       | int $radius                   | 启用圆角条                               |
| `gradient($s, $e)`           | string, string                | 启用渐变效果                             |
| `generate()`                 | -                             | 生成条码数据                             |
| `toPng()`                    | -                             | 输出PNG数据                              |
| `toSvg()`                    | -                             | 输出SVG数据                              |
| `toHtml()`                   | -                             | 输出HTML字符串                           |
| `savePng($filename)`         | string $filename              | 保存为PNG文件                            |
| `saveSvg($filename)`         | string $filename              | 保存为SVG文件                            |
| `saveHtml($filename)`        | string $filename              | 保存为HTML文件                           |
| `outputPng()`                | -                             | 直接输出PNG到浏览器                      |
| `outputSvg()`                | -                             | 直接输出SVG到浏览器                      |
| `outputHtml()`               | -                             | 直接输出HTML到浏览器                     |
| `toBase64($format)`          | string $format                | 返回Base64数据URI（png/svg/html）        |
| `getFullData()`              | -                             | 获取完整数据（含校验位）                 |
| `getChecksum()`              | -                             | 获取校验位                               |

### 渲染器个性化方法

当需要更底层的控制时，可以直接使用渲染器：

**PngRenderer 特有方法：**

```php
$renderer = new PngRenderer();
$renderer->setTextAlign('right');           // 文本对齐
$renderer->enableGradient('#000', '#444');  // 渐变效果
$renderer->enableRoundedBars(3);            // 圆角条
$renderer->setWatermark('SAMPLE', 70, 5, '#888888', -45); // 水印
$renderer->setWatermarkPosition('bottom-right');          // 水印位置
$renderer->setWatermarkFontPath('/path/to/font.ttf');     // TTF字体
$renderer->enableBearerBar(3);              // ITF-14上下边框
```

**SvgRenderer 特有方法：**

```php
$renderer = new SvgRenderer();
$renderer->enableGradient('#000', '#444');  // 渐变效果
$renderer->enableRoundedBars(3);            // 圆角条
$renderer->setWatermark('SAMPLE', 70, 16, '#888888', 0);  // 水印
$renderer->setTextAlign('right');           // 文本对齐
$renderer->outputToBrowser($data, $text);   // 浏览器输出
$renderer->toBase64($data, $text);          // Base64
```

## 使用 BarcodeHelper 辅助类

### 快速生成条码

```php
use zxf\Utils\BarCode\BarcodeHelper;

// 快速生成条码
BarcodeHelper::quickGenerate('ean13', '690123456789', 'barcode.png', [
    'width' => 200,   // 条码内容总宽度（像素）
    'height' => 100,
    'format' => 'png'
]);
```

### 批量生成条码

```php
use zxf\Utils\BarCode\BarcodeHelper;

$dataList = [
    '690123456789',
    '690987654321',
    ['data' => '690111111111', 'filename' => 'custom_1'],
];

$results = BarcodeHelper::batchGenerate(
    $dataList,
    'ean13',
    './output',
    [
        'width' => 200,   // 条码内容总宽度（像素）
        'height' => 100,
        'prefix' => 'barcode_',
        'format' => 'png'
    ]
);

echo "生成完成：成功 {$results['success']}，失败 {$results['failed']}";
```

### 验证条码数据

```php
use zxf\Utils\BarCode\BarcodeHelper;

$validation = BarcodeHelper::validateData('ean13', '690123456789');

if ($validation['valid']) {
    echo "数据格式有效\n";
    echo "推荐校验位: " . ($validation['info']['checksum'] ?? '无') . "\n";
} else {
    echo "数据格式无效: " . implode(', ', $validation['errors']) . "\n";
}
```

### 获取条码类型信息

```php
use zxf\Utils\BarCode\BarcodeHelper;

$info = BarcodeHelper::getTypeInfo('ean13');
echo "名称: {$info['name']}\n";
echo "描述: {$info['description']}\n";
echo "字符集: {$info['charset']}\n";
echo "推荐长度: {$info['length']}\n";
echo "应用场景: {$info['uses']}\n";
```

### 生成Base64编码的条码

```php
use zxf\Utils\BarCode\BarcodeHelper;

$base64 = BarcodeHelper::toBase64('ean13', '690123456789', [
    'width' => 3,
    'height' => 100
]);

echo '<img src="' . $base64 . '" alt="Barcode">';
```

### 计算校验位

```php
use zxf\Utils\BarCode\BarcodeHelper;

$checksum = BarcodeHelper::calculateChecksum('ean13', '690123456789');
echo "校验位: {$checksum}\n";  // 输出: 2
```

### 检查环境支持

```php
use zxf\Utils\BarCode\BarcodeHelper;

$envCheck = BarcodeHelper::checkEnvironment();
if ($envCheck['passed']) {
    echo "环境检查通过\n";
} else {
    echo "环境问题:\n";
    foreach ($envCheck['checks'] as $check => $info) {
        if ($info['status'] !== 'ok') {
            echo "- {$check}: {$info['message']}\n";
        }
    }
}
```

## 常见问题

### Q: 生成的条码扫描不出来？

A: 可能原因：
1. 数据格式不正确（如 EAN-13 必须是纯数字）
2. 条宽太小（本库默认 4px/模块，勿手动降到 3 以下——模块过小在显示缩放/压缩后极易触发镜像码对误读或 App 侧偶发伪解码）
3. 颜色对比度不足（条和背景对比度应足够）
4. 缺少静区（条码两侧需要留白）

### Q: 如何生成 ISBN-13 条码？

A: ISBN-13 本质上是前缀为 978 或 979 的 EAN-13 条码：
```php
BarcodeBuilder::create()
    ->type('ean13')
    ->data('9787111123453')
    ->savePng('isbn.png');
```

**注意**：部分扫码器会将 978/979 前缀的 EAN-13 自动转换为 ISBN-10 格式显示，导致扫描结果与条码下方印刷的 13 位数字不一致。这是扫码器的正常行为，而非条码生成错误。如需避免此行为，请使用非 ISBN 前缀（如 690）的 EAN-13 条码。

### Q: 支持批量生成条码吗？

A: 支持，使用循环即可：
```php
$codes = ['690123456789', '690987654321'];
foreach ($codes as $i => $code) {
    BarcodeBuilder::create()
        ->type('ean13')
        ->data($code)
        ->savePng("barcode_{$i}.png");
}
```

### Q: 如何调整条码大小？

A: 使用 `width()` 和 `height()` 方法控制条码内容尺寸，或使用 `totalWidth()` / `totalHeight()` 控制生成图片的整体尺寸：
```php
BarcodeBuilder::create()
    ->type('ean13')
    ->data('690123456789')
    ->width(200)       // 条码内容总宽度（像素），0表示自动
    ->height(100)      // 条码高度（像素）
    ->totalWidth(300)  // 图片整体宽度
    ->totalHeight(150) // 图片整体高度
    ->savePng('barcode.png');
```

### Q: 条码文字显示位置不对？

A: 本库已优化文字位置：
- 第1位数字位置适中，避免太靠左
- 数字之间间隔均匀
- 文字与长竖线保持安全距离，避免重叠

### Q: 扫出来的数字和输入不一致（例如输入含 5，却识别成 8）？

A: 这不是生成错误。本库生成的图经像素级逐模块校验，且 zbar、ZXing 等解码器对同一张图都能读出与输入完全一致的文本。个别“误读”来自 **EAN 标准的镜像码对**：

- 数字 `5` 与 `8` 的码字元素宽度互为镜像：R5 = `[1,2,3,1]`、R8 = `[1,2,1,3]`（仅末尾 `3,1 ↔ 1,3` 互换），L/G 码同理。
- 当条码图像发生成像退化（非整数倍缩放插值、模糊、屏幕反光、JPEG/微信图片压缩、打印不良）时，宽度 3 与 1 的条空边界只需错位 1px，激进型扫码 App（手机相机/微信等，部分不校验校验位）就会把 `5` 读成 `8`。

规避方法：
1. 生成时给足清晰度：本库 PNG 默认 `moduleSize = 4`（最细条 = 4px），建议不要低于 4；关键场景（打印、长条、需要远距/快速识别）用 6~8。之前默认 3 时，手机相机对屏幕直扫仍处于临界采样区，可能偶发“校验恰好通过”的伪解码。
2. 查看/扫码时以 **1:1 或整数倍** 显示 PNG 原图，避免预览器/相册的非整数缩小；不要经微信等二次压缩传输。
3. 条码保持足够高度（EAN-13 条区高建议 ≥ 40 模块宽，见 `height()` / `longBarRatio()`），扫码时手机与条码面平行、光线均匀。
4. 仅用于扫码验证时，可先让 zbar / ZXing 复核产物，两者均为“宁可拒读也不误读”，能保证原图正确性。

### Q: 扫码结果与条码下方印刷文字不一致（ISBN/978 前缀）？

A: 微信、手机相机等主流扫码 App 对 978/979 前缀的 EAN-13 会**自动换算成 ISBN-10 显示**（如 `9780201379624` 显示为 `0201379627`；`9783161484100` 显示为 `316148410X`——末尾的 X 正是 ISBN-10 校验位 10 的记法）。这是扫码器的内置图书格式转换，是行业标准行为：

- 条码物理编码的仍是输入的完整 13 位（zbar / ZXing 等返回原始码值的解码器读出即 `9780201379624`，可自行复核验证）；
- 该行为发生在扫码器内部、与生成器无关，**无法也不应通过生成器“修复”**——ISBN/EAN-13 码面必须含 978/979 前缀才能被图书零售体系识别；
- 若你的业务需要扫码结果**完整保留 13 位**，请改用返回原始 EAN 数值的解码 SDK（zbar/ZXing 原生模式），或改用非 ISBN 前缀（如 690）的数据做扫码演示；切勿因此怀疑 ISBN 条码生成错误。

## 测试

运行全面测试套件：

```bash
php src/BarCode/tests.php
```

测试内容包括：
- 所有条码类型的生成和验证
- 校验位计算测试
- 边界条件测试
- 错误处理测试
- 颜色对比度测试
- 性能测试
- 文件输出测试
- **（可选）扫码验证闭环**：当环境装有 `zbarimg`（`brew install zbar`）时自动执行——对生成的 PNG 做真实解码，断言「生成码面 == 标准解码器读出内容」，且**同一图像连续解码两次结果一致**（防「不可重复读取」）。未安装 `zbarimg` 时该段自动跳过、不影响测试结果

> 输出文件写入系统临时目录 `zxf_utils_barcode_test_<时间戳>/`，不污染包源码目录。
> 该闭环是本库「宽度语义统一 / 编码表修正」等改动后的回归护栏：任何导致码面与内容不一致、或成像质量下降（2px/模块）的回归都会被它拦截。

## 更新日志

### v1.7.0 (2026-05-20)
- **【重要】精简条码类型，移除识别率较低的生成器**
  - 移除 Code 11、Code 93、MSI、Standard 2 of 5、Industrial 2 of 5、Pharmacode 生成器
  - 集中维护 9 种高兼容性条码类型：EAN-13、EAN-8、UPC-A、Code 128、Code 39、Codabar、ITF-14、ISSN、ISBN
  - 更新文档、测试和辅助类，移除所有已删除类型的引用
- **【新增】ISBN 条码生成器**
  - 支持 ISBN-10 和 ISBN-13 自动识别
  - ISBN-10 自动转换为 ISBN-13（加 978 前缀，重新计算 EAN-13 校验位）
  - 支持带连字符和空格的格式化输入
- **【增强】静区自动保障机制**
  - PNG / SVG 渲染器自动强制最小静区宽度（11 × 模块宽度）
  - 确保 EAN-13 / UPC-A 等条码符合 GS1 静区规范，提高扫码识别率
- **【修复】EAN-13 978 前缀条码识别问题**
  - 修复编码模块数计算错误，确保与 GS1 规范完全一致
  - 修复预展开模式导致的模块数翻倍问题
- **【修复】二维码多项问题**
  - 修复 PHP 8.2+ `Deprecated: ord()` 警告
  - 修复 SVG 生成纯黑方块问题
  - 统一 PNG 与 SVG 标签文字位置

### v1.6.0 (2026-04-16)
- ✅ **【重要】重构尺寸语义，取消模块宽度设置**
  - `width()` 现在表示**条码内容总宽度**（像素），0 表示自动
  - 移除 `moduleWidth` 概念，由渲染器根据总模块数自动计算单个模块宽度
  - `height()` 表示条码内容高度（像素）
  - 优化 `totalWidth()` / `totalHeight()` 与内容尺寸的协同计算
- ✅ **【增强】新增个性化链式配置方法**
  - 新增 `fontSize()` / `fontPath()` 文本字体控制
  - 新增 `margin()` 统一边距设置（CSS margin 逻辑）
  - 新增 `quietZone()` 静区显示与宽度控制
  - 新增 `longBarRatio()` 长竖线高度比例控制
  - 新增 `border()` 图片边框支持
- ✅ **【增强】提取 BaseRenderer 抽象基类**
  - `PngRenderer` 和 `SvgRenderer` 统一继承 `BaseRenderer`
  - 共享参数、默认配置、通用工具方法全部下沉到基类
- ✅ **【修复】ITF-14 Bearer Bar 超长黑条问题**
  - 将 Bearer Bar 改为完全贴合条码内容区域的矩形框
  - SVG 渲染器同步支持 Bearer Bar 绘制
- ✅ **【修复】Code 128 验证逻辑漏洞**
  - 禁止控制字符（0-31）输入，避免被静默转换为空格
- ✅ 完善文档，同步更新所有 API 说明和示例

### v1.5.0 (2026-04-15)
- ✅ **【增强】BarcodeBuilder 新增链式高级渲染配置**
  - 新增 `totalWidth()` / `totalHeight()` 指定生成图片整体宽高
  - 新增 `watermark()` / `watermarkPosition()` / `watermarkFontPath()` 水印链式调用
  - 新增 `bearerBar()` 启用 ITF-14 上下边框
  - 新增 `transparentBackground()` SVG 透明背景支持
  - 新增 `textAlign()` 文本对齐链式调用
  - 新增 `roundedBars()` 圆角条链式调用
  - 新增 `gradient()` 渐变效果链式调用
- ✅ **【增强】完善 SVG 渲染器功能**
  - SVG 支持透明背景（`bgColor('transparent')`）
  - SVG 支持水印、Base64、浏览器直接输出
- ✅ **【修复】Code 128 校验和计算 bug**
  - 修复长数据和特殊字符组合下可能丢失末尾数据的问题
  - 确保生成内容与识别内容完全一致
- ✅ **【修复】ITF-14 Bearer Bar 未绘制问题**
  - 实现上下边框绘制，符合 GS1 物流包装标准
- ✅ 清理临时诊断文件，保持代码库整洁
- ✅ 完善文档，覆盖所有新增功能

### v1.4.0 (2026-04-07)
- ✅ **【重要】移除 ISBN 条码类型**
  - 删除 ISBNGenerator.php 文件
  - 从工厂、构建器、辅助类中移除所有 ISBN 相关代码
  - 更新文档和测试，移除所有 ISBN 引用
  - 现在支持 7 种条码类型：EAN-13、EAN-8、UPC-A、Code 128、Code 39、ITF-14、ISSN
- ✅ **【重要】增强水印功能**
  - 添加旋转角度支持（-180 到 180 度）
  - 支持自定义水印字号（1-5）
  - 支持自定义水印颜色
  - 提高水印透明度和清晰度
  - 旋转水印自动斜向平铺覆盖整个条码

### v1.3.0 (2026-04-07)
- ✅ **【重要修复】移除所有条码类型的校验位验证功能**
  - 现在传入什么数据，就生成什么数据的条码
  - 保证生成的条码内容与传入内容完全一致
  - 保证识别出来的内容也完全一致
- ✅ **【优化】修复条纹显示问题**
  - 统一所有条码生成器的宽度单位系统
  - 优化 Code 128 和 Code 39 的静区宽度计算
- ✅ **【优化】改进长竖线条码的文本显示**
  - 动态计算字号大小，根据数据密度自动调整
  - 优化文字与长竖线的距离，避免视觉重叠
- ✅ 完善测试覆盖，所有测试用例 100% 通过

### v1.2.0 (2026-04-07)
- ✅ 修复 EAN-13/EAN-8/UPC-A 长竖线渲染问题
- ✅ 优化长竖线条码的文本显示位置，更靠近条码
- ✅ 优化编码算法，提高条码识别率

### v1.1.0 (2026-04-07)
- ✅ 新增 BarcodeHelper 辅助类
- ✅ 优化错误处理和异常类型
- ✅ 完善文档和示例代码

### v1.0.0
- 支持条码类型：EAN-13、EAN-8、UPC-A、Code 128、Code 39、ITF-14、ISSN
- 支持 PNG 和 SVG 输出格式
- 支持长竖线渲染、文本显示、颜色自定义

## 目录结构

```
src/
├── Contracts/          # 接口定义
│   ├── BarcodeGeneratorInterface.php
│   └── RendererInterface.php
├── DTO/                # 数据传输对象
│   └── BarcodeConfig.php
├── Exceptions/         # 异常类
│   ├── BarcodeException.php
│   ├── InvalidDataException.php
│   └── RenderException.php
├── Generator/          # 条码生成器
│   ├── BaseGenerator.php
│   ├── EAN13Generator.php
│   ├── EAN8Generator.php
│   ├── UPCAGenerator.php
│   ├── Code128Generator.php
│   ├── Code39Generator.php
│   ├── CodabarGenerator.php
│   ├── ISBNGenerator.php
│   ├── ITF14Generator.php
│   └── ISSNGenerator.php
├── Renderer/           # 渲染器
│   ├── BaseRenderer.php
│   ├── PngRenderer.php
│   ├── SvgRenderer.php
│   └── HtmlRenderer.php
├── BarcodeFactory.php  # 工厂类
├── BarcodeBuilder.php  # 构建器类
├── BarcodeHelper.php   # 辅助工具类
├── README.md           # 说明文档
└── tests.php           # 测试脚本
```

## 更新日志 / 修复说明

### 模块宽度语义统一与可扫描性加固（2026-09-04）

针对扫码实测（手机/相机 App 出现 Codabar 丢起止符、ISBN/978 前缀变化、1↔7 / 2↔8 误读、ISSN 前缀、同一图像不可重复读取等）逐图排查后的根因修复。结论：多数「内容变化」是扫码 App 的自动格式转换（详见 FAQ），真正的生成侧问题有两个，均已修复：

- **默认 moduleSize 2 → 3（BaseRenderer）**：2px/模块在非整数缩放 / 模糊 / 压缩后，EAN 镜像码对（5↔8、1↔7、2↔8 等宽度 3/1 边界错位 1px 即翻转）极易被激进 App 误读，且带水印 / 长竖线图更不稳定；默认 3px 后带水印图亦稳定可读。
- **宽度语义统一为「1 模块 = 1 单位，像素 = 模块数 × moduleSize」**：历史上 EAN-8 / UPC-A 预先把每模块展开成 2px（`MODULE_WIDTH=2`），Code 39 / Codabar / ITF-14 又把宽窄元素当像素处理，再经渲染器按 moduleSize 二次放大——同一 moduleSize 下不同码制实际像素宽度相差一倍、宽度语义混乱。
  - EAN-8 / UPC-A：删除 `MODULE_WIDTH=2` 预展开与 `expandPattern()`，静区与码字直接按模块输出；
  - Code 39：`NARROW 2→1、WIDE 6→3`（宽:窄=3:1 不变）、字符间隔与静区改为真实 1 / 10 模块；重构中曾丢失起始星号 `*` 与数据首字符之间的间隔（导致字符粘连、被 ZXing/zbar 拒读），已补回并验证；
  - Codabar：`NARROW 2→1、WIDE 4→2`（宽:窄=2:1 不变）、静区 20→10 模块；
  - ITF-14：窄元素由「像素(默认2)」改为「模块(默认1)」，宽元素 = `wideModules()`（窄 × 比例取整）；
  - Code 128：清理死常量 `MODULE_WIDTH=2`，静区 20→10 模块。
- 修复后各码制像素宽度 = moduleSize × 总模块数（moduleSize=3 时：EAN-13 351px、EAN-8 309px、UPC-A 405px、Code39 555px、Codabar 489px、ITF-14 531px；moduleSize=4 默认时：EAN-13 468px、EAN-8 412px、UPC-A 540px、Code39 740px、Codabar 652px、ITF-14 708px），并已用 zbar / ZXing 对全部输出复扫一致。
- 固化回归护栏：`tests.php` 新增【扫码验证闭环】段（可选，需 `zbarimg`），逐码制断言「解码内容 == 期望值」且同一图像两次解码一致；未装 `zbarimg` 自动跳过、不影响测试结果。
- 收尾全面复查（同日追加）：
  - **修复 ISBN 的 SVG 渲染完全无文字**：`splitDisplaySegments` 的分段 switch 只认 `EAN-13/ISSN/UPC-A/EAN-8`，漏了 `ISBN`——ISBN 码面是 13 位 EAN-13 结构，却因未命中任何 case 返回空分段，SVG 下文字元素直接不输出、PNG/HTML 退化为整段居中。已把 `ISBN` 并入 EAN-13 分段逻辑，三种渲染器显示一致（1+6+6）；
  - **Code 39 小写输入自动转大写**（规范字符集本就仅大写；此前小写直接被判非法抛错），无效字符仍照常拦截；
  - 统一三渲染器长竖线延长量取整：HTML 由 `floor` 改 `ceil`，与 PNG/SVG 一致（消除同参数下容器高度 1px 差异）；删除 HTML 渲染器死方法 `getTextSegments`；修正 `resolveModuleSize` 注释残留的「默认 2」；
  - **PngRenderer 增加 GD 扩展缺失防护**：无 GD 时抛清晰的 `RenderException` 并提示改用 `toSvg()/toHtml()`，不再原生 Fatal；
  - 新增固化回归：ISBN SVG 分段文字元素数=13、Code 39 小写转大写生成、Code 39 无效字符拦截；扫码闭环矩阵新增 `code39_lowercase`（小写输入 → zbar 解码 `ABC-123`）。
- **【同日复查轮】默认 moduleSize 3 → 4**：手机相机对屏幕直扫 moduleSize=3 的 351px 图时仍处于对焦/采样临界区——zbar 类严谨解码器“宁可拒读”，而微信/相机等激进解码 App 会输出“校验恰好通过”的伪解码（如 `9780201379624 → 9180201379684`、`6901234567892 → 6907834567898`，均为多字符误读且 EAN 校验位巧合成立），多帧间结果不稳定。对照实验：抗锯齿重采样下 3px/模块图只能稳读到 50% 缩放，4px/模块图可稳读到 35% 甚至 25%——默认 4px 把临界区整体抬高一档，显著降低 App 侧偶发伪解码概率。
  - 同步动作：`resolveModuleSize` 默认兜底 3 → 4、渲染器初始属性同步；`tests.php` 全部显式 `moduleSize` 用例 3 → 4（扫码用图由 351px 升为 468px）；FAQ 与生成建议更新“默认 4、关键场景 6~8”。
  - 再次强调口径：`0201379627`/`0306406152`/`316148410X` 是微信的 ISBN-10 自动转换（三者的 ISBN-10 校验位逐位吻合），属扫码器正常行为而非生成错误，详见上文 FAQ。
- **【全面优化轮】渲染一致性与防御性修复**：
  - **渐变扫码守卫（真实缺陷）**：`gradient()` 允许两端中一端过浅（如 `#dddddd`），该浅色条段会被解码器当作空白 → 起始符/终止符丢失、整码拒读（zbar 实测 0 解码）。新增 `gradientUsable()`：渐变两端必须与背景满足 WCAG 3:1 且均为合法 hex，否则三格式统一自动回退纯色条；对比充足的中度渐变（如 `#888888→#222222`）不受影响且扫码通过。README 示例（`#000→#444`）本就合规。
  - **HTML 渲染器补齐**（此前与 PNG/SVG 行为不一致）：支持边框、渐变条、Bearer Bar 完整四边框（原来只有上下横条）、水印（九宫格定位+旋转+透明度）、对比度不足自动回退黑白；圆角改为四角并带「条宽 > 2×半径」保护（与 PNG/SVG 一致）。
  - **SVG 渲染器**：渐变方向统一为水平（原为垂直，与 PNG/HTML 不一致）；分段文字在无区域信息时回退整段文本（原整段消失，与 PNG/HTML 不一致）；文本/颜色转义统一走 `escapeXmlAttr`（消除 `htmlspecialchars` 各处参数不一的隐患）；水印宽度按原始文本估算（避免实体化后宽度虚高）。
  - **PNG 渲染器**：静区最小值计算不再改写共享 `config`（原实现把 config->marginLeft 持久放大，实例复用会残留副作用），改为本次渲染局部生效；水印 TTF 路径使用原始像素字号（原 16px 被 GD 位图 1-5 档换算放大成 20px，与 SVG/HTML 字号不一致）。
  - **入口层**：`BarcodeFactory::canonicalType()` 统一别名归一（ean-13→ean13、isbn-10→isbn 等），`getSupportedTypes()` 去重返回 9 个规范名，`BarcodeHelper::getTypeInfo()` 因此可直接接受别名；不支持类型异常错误码 1001 → 1003（语义正确）；`batchGenerate` 文件名清洗（basename + 目录穿越/Windows 保留字符防护）。
  - **死代码清理**：删除 `BaseRenderer::disableGradient()/disableBearerBar()`（零调用）与 `SvgRenderer::toBase64()`（Builder/Helper 已有等价实现）。
- 验证基线：`tests.php` 103 项全过（零 ✗）+ `tests/run.php` 全部单测（含渲染/转义/静区断言）ALL PASSED + 富配置组合（边框+渐变+圆角+Bearer+水印）×6 码型 zbar 复扫一致。

### 编码正确性修复（重要）

以下修复直接影响条码的**可扫描性**，均已通过「生成 → 按权威标准编码表解码 → 与原始数据比对」的往返校验（标准校验 17 项，失败 0）：

- **ITF-14（Interleaved 2 of 5）编码表修正**：原编码表中数字 `5`↔`8`、`6`↔`9` 的条宽序列存在互换错误，导致生成的条码无法被标准读码器正确识别。已修正为标准的交错二五码序列：
  - `4 => nnwnw`、`5 => wnnwn`、`6 => nwnwn`、`7 => nnnww`、`8 => wnwnn`、`9 => nwwnn`。
- **Codabar（NW-7）编码表修正**：原编码表中数字 `1`、`2`、`4`、`7` 的模块序列错误，其中数字 `4` 的序列与数字 `2` **完全冲突**（无法区分），已按 AIM/ANSI BC3（convention A）标准修正。

### 结构与健壮性核查

- 全面核查 `BaseGenerator`、`EAN13/EAN8/UPCA`、`Code39`、`Code128`（含 A/B/C 自动切换）、`ISBN/ISSN` 生成器，编码表与校验位算法均与 GS1 标准一致。
- 核查 `BarcodeFactory`、`BarcodeBuilder`、`BarcodeHelper`、`DTO/BarcodeConfig` 与各渲染器（PNG/SVG/HTML），逻辑正确。

### 渲染器安全加固

- **SVG 渲染器**：对背景色、条颜色、渐变色、水印色、文本填充色统一做 XML 属性转义（`escapeXmlAttr`），防止颜色配置中的特殊字符破坏 SVG 结构或注入属性。
- **HTML 渲染器**：对内联样式中的颜色值做白名单净化（`sanitizeCssValue`），对 CSS 类名做字符白名单过滤，防止 `style` / `class` 属性注入。

## 许可证

MIT License

## 致谢

本模块为完全自研实现，无任何第三方依赖。编码与校验位算法参考国际标准：
EAN/UPC/ISBN/ISSN/ITF-14 依据 GS1 通用规范，Code 39 / Codabar 依据 AIM/ANSI 规范。
