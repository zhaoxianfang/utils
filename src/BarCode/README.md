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

- **7种条码类型**：EAN-13、EAN-8、UPC-A、Code 128、Code 39、ITF-14、ISSN
- **符合GS1标准**：严格遵循国际条形码编码规范，确保100%可识别
- **长竖线特征**：EAN/UPC 条码支持标准长竖线（保护符突出显示）
- **多种输出格式**：支持 PNG 图片和 SVG 矢量图
- **指定宽高**：支持设置生成图片的整体宽度和高度
- **Base64输出**：支持直接返回 Base64 编码数据 URI
- **浏览器输出**：支持直接输出 PNG / SVG 到浏览器
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

### 文本对齐

```php
BarcodeBuilder::create()
    ->type('code128')
    ->data('ALIGN_TEST')
    ->textAlign('right')  // left, center, right
    ->savePng('aligned.png');
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

| 条码类型   | 数据长度 | 字符集           | 长竖线 | 应用场景   |
|------------|----------|------------------|--------|------------|
| EAN-13     | 13位     | 数字             | 有     | 零售商品   |
| EAN-8      | 8位      | 数字             | 有     | 小包装商品 |
| UPC-A      | 12位     | 数字             | 有     | 北美零售   |
| ISSN       | 8位      | 数字             | 有     | 期刊       |
| Code 128   | 可变     | 全ASCII          | 无     | 物流/仓储  |
| Code 39    | 可变     | 数字+大写字母+特殊符号 | 无 | 工业/医疗  |
| ITF-14     | 14位     | 数字             | 无     | 物流包装   |

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
| `savePng($filename)`         | string $filename              | 保存为PNG文件                            |
| `saveSvg($filename)`         | string $filename              | 保存为SVG文件                            |
| `outputPng()`                | -                             | 直接输出PNG到浏览器                      |
| `outputSvg()`                | -                             | 直接输出SVG到浏览器                      |
| `toBase64($format)`          | string $format                | 返回Base64数据URI（png/svg）             |
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
2. 条宽度太小（建议至少 2 像素）
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

## 更新日志

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
│   ├── ITF14Generator.php
│   └── ISSNGenerator.php
├── Renderer/           # 渲染器
│   ├── BaseRenderer.php
│   ├── PngRenderer.php
│   └── SvgRenderer.php
├── BarcodeFactory.php  # 工厂类
├── BarcodeBuilder.php  # 构建器类
├── BarcodeHelper.php   # 辅助工具类
├── README.md           # 说明文档
└── tests.php           # 测试脚本
```

## 许可证

MIT License

## 致谢

本项目参考了 GS1 标准和 picqer/php-barcode-generator 的编码规范。
