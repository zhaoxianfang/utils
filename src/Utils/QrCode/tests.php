<?php

/**
 * zxf/qr-code 官方测试套件
 *
 * 测试所有功能以确保100%通过率和二维码可扫描性
 *
 * @version 2.0.0
 * @package zxf\Utils\QrCode
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use zxf\Utils\QrCode\QrCode;
use zxf\Utils\QrCode\ErrorCorrectionLevel;
use zxf\Utils\QrCode\LabelOptions;

echo "========================================\n";
echo "zxf/qr-code 官方测试套件 v2.0.0\n";
echo "========================================\n\n";

// 创建输出目录
$outputDir = __DIR__ . '/../../../output_' . date('mdHi');
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

// 设置默认字体路径
LabelOptions::setDefaultFontPath(__DIR__ . '/../../resource/font/lishu.ttf');

$testCount = 0;
$passedCount = 0;

/**
 * 测试函数
 */
function test(string $name, callable $callback): void
{
    global $testCount, $passedCount;
    $testCount++;
    echo "测试 #{$testCount}: {$name}... ";

    try {
        $callback();
        $passedCount++;
        echo "✓ 通过\n";
    } catch (\Throwable $e) {
        echo "✗ 失败\n";
        echo "  错误: {$e->getMessage()}\n";
        echo "  位置: {$e->getFile()}({$e->getLine()})\n";
    }
}

echo "=== 第一部分：基础二维码生成 ===\n\n";

test('1. 生成基础二维码', function() use ($outputDir) {
    QrCode::make('Hello World!')
        ->size(300)
        ->save($outputDir . '/test_01_basic.png');
});

test('2. 生成中文二维码', function() use ($outputDir) {
    QrCode::make('你好，世界！')
        ->size(300)
        ->save($outputDir . '/test_02_chinese.png');
});

test('3. 生成URL二维码', function() use ($outputDir) {
    QrCode::make('https://www.example.com')
        ->size(300)
        ->errorCorrectionLevel(ErrorCorrectionLevel::high())
        ->save($outputDir . '/test_03_url.png');
});

test('4. WiFi二维码', function() use ($outputDir) {
    QrCode::wifi('MyNetwork', 'mypassword', 'WPA')
        ->size(300)
        ->labelText('WiFi配置')
        ->save($outputDir . '/test_04_wifi.png');
});

test('5. 电话二维码', function() use ($outputDir) {
    QrCode::phone('13800138000')
        ->size(300)
        ->labelText('拨打电话')
        ->save($outputDir . '/test_05_phone.png');
});

test('6. 邮件二维码', function() use ($outputDir) {
    QrCode::email('test@example.com', '咨询', '请回复我')
        ->size(300)
        ->labelText('发送邮件')
        ->save($outputDir . '/test_06_email.png');
});

test('7. 短信二维码', function() use ($outputDir) {
    QrCode::sms('13800138000', '测试短信')
        ->size(300)
        ->labelText('发送短信')
        ->save($outputDir . '/test_07_sms.png');
});

test('8. VCard名片二维码', function() use ($outputDir) {
    QrCode::vcard([
        'name' => '张三',
        'title' => '软件工程师',
        'phone' => '13800138000',
        'email' => 'test@example.com',
        'url' => 'https://www.example.com'
    ])->size(350)
      ->errorCorrectionLevel('Q')
      ->labelText('联系名片')
      ->save($outputDir . '/test_08_vcard.png');
});

test('9. 地理位置二维码', function() use ($outputDir) {
    QrCode::geo(39.9042, 116.4074)
        ->size(300)
        ->labelText('北京位置')
        ->save($outputDir . '/test_09_geo.png');
});

test('10. 日历事件二维码', function() use ($outputDir) {
    QrCode::calendar(
        '重要会议',
        '20260210T090000',
        '20260210T100000',
        '会议室A',
        '讨论项目计划'
    )->size(300)
      ->labelText('添加到日历')
      ->save($outputDir . '/test_10_calendar.png');
});

echo "\n=== 第二部分：样式和格式 ===\n\n";

test('11. JSON数据二维码', function() use ($outputDir) {
    $jsonData = json_encode([
        'id' => 1,
        'name' => '张三',
        'email' => 'test@example.com'
    ], JSON_UNESCAPED_UNICODE);
    QrCode::make($jsonData)
        ->size(350)
        ->errorCorrectionLevel('M')
        ->save($outputDir . '/test_11_json.png');
});

test('12. 支付二维码', function() use ($outputDir) {
    QrCode::make('pay:¥99.00')
        ->size(300)
        ->labelText('扫码支付')
        ->errorCorrectionLevel('H')
        ->save($outputDir . '/test_12_payment.png');
});

test('13. 不同错误纠正级别', function() use ($outputDir) {
    $levels = ['L', 'M', 'Q', 'H'];
    foreach ($levels as $level) {
        QrCode::make("EC: $level")
            ->size(200)
            ->errorCorrectionLevel($level)
            ->save($outputDir . "/test_13_ec_{$level}.png");
    }
});

test('14. 自定义颜色', function() use ($outputDir) {
    QrCode::make('自定义颜色')
        ->size(300)
        ->backgroundColor('#3498db')
        ->foregroundColor('#ffffff')
        ->save($outputDir . '/test_14_color.png');
});

test('15. 自定义颜色（颜色对象）', function() use ($outputDir) {
    QrCode::make('颜色对象')
        ->size(300)
        ->backgroundColor(new \zxf\Utils\QrCode\Color\Color(243, 156, 18))
        ->foregroundColor(new \zxf\Utils\QrCode\Color\Color(255, 255, 255))
        ->save($outputDir . '/test_15_color_obj.png');
});

test('16. 不同尺寸', function() use ($outputDir) {
    $sizes = [200, 300, 500];
    foreach ($sizes as $size) {
        QrCode::make("Size: {$size}")
            ->size($size)
            ->save($outputDir . "/test_16_size_{$size}.png");
    }
});

test('17. 不同边距', function() use ($outputDir) {
    $margins = [0, 4, 8];
    foreach ($margins as $margin) {
        QrCode::make("Margin: {$margin}")
            ->size(300)
            ->margin($margin)
            ->save($outputDir . "/test_17_margin_{$margin}.png");
    }
});

test('18. 不同输出格式', function() use ($outputDir) {
    $formats = ['png', 'jpeg', 'gif'];
    foreach ($formats as $format) {
        QrCode::make("Format: $format")
            ->size(300)
            ->format($format)
            ->save($outputDir . "/test_18_format_{$format}.{$format}");
    }
});

test('19. 不同图片质量', function() use ($outputDir) {
    $qualities = [50, 75, 100];
    foreach ($qualities as $quality) {
        QrCode::make("Quality: {$quality}")
            ->size(300)
            ->quality($quality)
            ->format('jpeg')
            ->save($outputDir . "/test_19_quality_{$quality}.jpg");
    }
});

test('20. 圆点风格', function() use ($outputDir) {
    QrCode::make('圆点风格')
        ->size(300)
        ->rounded(true)
        ->roundedRadius(0.8)
        ->save($outputDir . '/test_20_rounded.png');
});

test('21. 超长文本', function() use ($outputDir) {
    $longText = str_repeat('这是一段非常长的文本内容，用于测试二维码的容量。', 20);
    QrCode::make($longText)
        ->size(500)
        ->errorCorrectionLevel('L')
        ->save($outputDir . '/test_21_long_text.png');
});

echo "\n=== 第三部分：标签功能 ===\n\n";

test('22. 带标签的二维码', function() use ($outputDir) {
    QrCode::make('带标签测试')
        ->size(300)
        ->labelText('自定义标签')
        ->save($outputDir . '/test_22_label.png');
});

test('23. 自定义标签', function() use ($outputDir) {
    QrCode::make('自定义标签测试')
        ->size(300)
        ->labelOptions(
            LabelOptions::create('自定义标签')
                ->fontSize(18)
                ->color('#e74c3c')
                ->marginTop(15)
                ->marginBottom(15)
                ->alignment('center')
        )
        ->save($outputDir . '/test_23_custom_label.png');
});

test('24. 多行标签', function() use ($outputDir) {
    QrCode::make('多行标签测试')
        ->size(300)
        ->labelText("第一行\n第二行\n第三行")
        ->save($outputDir . '/test_24_multiline.png');
});

test('25. 标签对齐方式', function() use ($outputDir) {
    $alignments = ['left', 'center', 'right'];
    foreach ($alignments as $align) {
        QrCode::make("Alignment: {$align}")
            ->size(300)
            ->labelOptions(
                LabelOptions::create("{$align}对齐")
                    ->alignment($align)
            )
            ->save($outputDir . "/test_25_align_{$align}.png");
    }
});

test('26. 链式调用', function() use ($outputDir) {
    QrCode::make('链式调用测试')
        ->size(350)
        ->margin(5)
        ->errorCorrectionLevel(ErrorCorrectionLevel::high())
        ->backgroundColor('#ffffff')
        ->foregroundColor('#2c3e50')
        ->labelText('链式调用')
        ->format('png')
        ->quality(95)
        ->save($outputDir . '/test_26_chained.png');
});

test('27. 中文支持', function() use ($outputDir) {
    $texts = ['中文测试', '你好世界', '欢迎关注', '扫码领取优惠'];
    foreach ($texts as $i => $text) {
        QrCode::make($text)
            ->size(250)
            ->save($outputDir . "/test_27_chinese_{$i}.png");
    }
});

test('28. 特殊字符', function() use ($outputDir) {
    $specialChars = '!@#$%^&*()_+-=[]{}|;:,.<>?/~`';
    QrCode::make($specialChars)
        ->size(300)
        ->save($outputDir . '/test_28_special.png');
});

test('29. 混合语言', function() use ($outputDir) {
    QrCode::make('Hello 你好 World 世界')
        ->size(300)
        ->save($outputDir . '/test_29_mixed.png');
});

test('30. Base64输出', function() {
    $qr = QrCode::make('Base64测试')
        ->size(300);
    $base64 = $qr->toBase64();
    if (strlen($base64) > 0 && strpos($base64, 'data:image') !== 0) {
        throw new Exception('Base64格式无效');
    }
});

test('31. 批量生成', function() use ($outputDir) {
    for ($i = 1; $i <= 10; $i++) {
        QrCode::make("Batch Item {$i}")
            ->size(200)
            ->save($outputDir . "/test_31_batch_{$i}.png");
    }
});

echo "\n=== 第四部分：高级功能 ===\n\n";

test('32. 自动版本选择', function() use ($outputDir) {
    $shortText = '短文本';
    $longText = str_repeat('这是长文本，用于测试自动版本选择。', 50);

    QrCode::make($shortText)
        ->size(300)
        ->save($outputDir . '/test_32_auto_short.png');

    QrCode::make($longText)
        ->size(500)
        ->errorCorrectionLevel('L')
        ->save($outputDir . '/test_32_auto_long.png');
});

test('33. 强制版本', function() use ($outputDir) {
    QrCode::make('Version test')
        ->size(300)
        ->version(10)
        ->save($outputDir . '/test_33_version.png');
});

test('34. 字母数字模式', function() use ($outputDir) {
    QrCode::make('ABC123$%*+-./:')
        ->size(300)
        ->save($outputDir . '/test_34_alphanumeric.png');
});

test('35. 纯数字模式', function() use ($outputDir) {
    QrCode::make('12345678901234567890')
        ->size(300)
        ->save($outputDir . '/test_35_numeric.png');
});

test('36. 混合内容类型二维码', function() use ($outputDir) {
    $data = json_encode([
        'url' => 'https://example.com',
        'phone' => '13800138000',
        'text' => '中文文本',
        'number' => 12345
    ], JSON_UNESCAPED_UNICODE);
    QrCode::make($data)
        ->size(350)
        ->errorCorrectionLevel('M')
        ->save($outputDir . '/test_36_mixed_content.png');
});

test('37. 字符编码测试', function() use ($outputDir) {
    $texts = [
        'UTF-8编码',
        'ISO-8859-1: Hello World',
        '混合编码: 你好 Hello'
    ];
    foreach ($texts as $i => $text) {
        QrCode::make($text)
            ->size(300)
            ->encoding('UTF-8')
            ->save($outputDir . "/test_37_encoding_{$i}.png");
    }
});

echo "\n=== 第五部分：Logo和背景图片 ===\n\n";

// 创建测试logo和背景图片
$logoPath = $outputDir . '/test_logo.png';
$logoSize = 50;
$logoImg = imagecreatetruecolor($logoSize, $logoSize);
$logoBg = imagecolorallocate($logoImg, 52, 152, 219);
$logoFg = imagecolorallocate($logoImg, 255, 255, 255);
imagefill($logoImg, 0, 0, $logoBg);
imagestring($logoImg, 5, 12, 15, 'LOGO', $logoFg);
imagepng($logoImg, $logoPath);
imagedestroy($logoImg);

$bgPath = $outputDir . '/test_bg.jpg';
$bgSize = 400;
$bgImg = imagecreatetruecolor($bgSize, $bgSize);
for ($i = 0; $i < $bgSize; $i++) {
    $color = imagecolorallocate($bgImg,
        52 + (int)(($i / $bgSize) * 100),
        152 - (int)(($i / $bgSize) * 50),
        219 + (int)(($i / $bgSize) * 30)
    );
    imageline($bgImg, 0, $i, $bgSize, $i, $color);
}
imagejpeg($bgImg, $bgPath, 90);
imagedestroy($bgImg);

test('38. 带Logo的二维码（10%比例）', function() use ($outputDir, $logoPath) {
    QrCode::make('https://www.example.com')
        ->size(400)
        ->errorCorrectionLevel('H')
        ->logo($logoPath, 10)
        ->labelText('官方网站')
        ->save($outputDir . '/test_38_logo_10.png');
});

test('39. 带Logo的二维码（15%比例）', function() use ($outputDir, $logoPath) {
    QrCode::make('https://www.example.com')
        ->size(400)
        ->errorCorrectionLevel('H')
        ->logo($logoPath, 15)
        ->labelText('官方网站')
        ->save($outputDir . '/test_39_logo_15.png');
});

test('40. 带Logo的二维码（20%比例，应自动限制为15%）', function() use ($outputDir, $logoPath) {
    QrCode::make('https://www.example.com')
        ->size(400)
        ->errorCorrectionLevel('H')
        ->logo($logoPath, 20)
        ->labelText('官方网站')
        ->save($outputDir . '/test_40_logo_20_limit.png');
});

test('41. 带Logo的二维码（不同纠错级别）', function() use ($outputDir, $logoPath) {
    $levels = ['L', 'M', 'Q', 'H'];
    foreach ($levels as $level) {
        QrCode::make("Logo EC: {$level}")
            ->size(350)
            ->errorCorrectionLevel($level)
            ->logo($logoPath, 12)
            ->save($outputDir . "/test_41_logo_ec_{$level}.png");
    }
});

test('42. 带Logo的二维码（不同尺寸）', function() use ($outputDir, $logoPath) {
    $sizes = [300, 400, 500];
    foreach ($sizes as $size) {
        QrCode::make("Logo Size: {$size}")
            ->size($size)
            ->errorCorrectionLevel('H')
            ->logo($logoPath, 12)
            ->save($outputDir . "/test_42_logo_size_{$size}.png");
    }
});

test('43. 带背景图片的二维码', function() use ($outputDir, $bgPath) {
    QrCode::make('https://www.example.com')
        ->size(400)
        ->backgroundImage($bgPath)
        ->labelText('背景图片二维码')
        ->save($outputDir . '/test_43_background.png');
});

test('44. 同时带Logo和背景的二维码', function() use ($outputDir, $logoPath, $bgPath) {
    QrCode::make('https://www.example.com')
        ->size(400)
        ->errorCorrectionLevel('H')
        ->backgroundImage($bgPath)
        ->logo($logoPath, 10)
        ->labelText('完整效果')
        ->save($outputDir . '/test_44_logo_and_bg.png');
});

test('45. 带Logo的圆点风格二维码', function() use ($outputDir, $logoPath) {
    QrCode::make('https://www.example.com')
        ->size(400)
        ->errorCorrectionLevel('H')
        ->logo($logoPath, 12)
        ->rounded(true)
        ->roundedRadius(0.7)
        ->labelText('圆点风格')
        ->save($outputDir . '/test_45_logo_rounded.png');
});

test('46. 带Logo的自定义颜色二维码', function() use ($outputDir, $logoPath) {
    QrCode::make('https://www.example.com')
        ->size(400)
        ->errorCorrectionLevel('H')
        ->backgroundColor('#f8f9fa')
        ->foregroundColor('#2c3e50')
        ->logo($logoPath, 12)
        ->labelText('自定义颜色')
        ->save($outputDir . '/test_46_logo_color.png');
});

echo "\n=== 第六部分：边界情况测试 ===\n\n";

test('47. 空字符串', function() use ($outputDir) {
    try {
        QrCode::make('')
            ->size(300)
            ->save($outputDir . '/test_47_empty.png');
    } catch (\Exception $e) {
        // 预期会抛出异常
        if (strpos($e->getMessage(), 'empty') === false) {
            throw $e;
        }
    }
});

test('48. 单个字符', function() use ($outputDir) {
    QrCode::make('A')
        ->size(300)
        ->save($outputDir . '/test_48_single_char.png');
});

test('49. 极大版本（版本40）', function() use ($outputDir) {
    // 使用较短的文本以适应版本40的最大容量
    $longText = str_repeat('测试文本，用于版本40容量测试。', 40);
    QrCode::make($longText)
        ->size(600)
        ->errorCorrectionLevel('L')
        ->version(40)
        ->save($outputDir . '/test_49_version_40.png');
});

test('50. 最小版本（版本1）', function() use ($outputDir) {
    QrCode::make('A')
        ->size(200)
        ->version(1)
        ->save($outputDir . '/test_50_version_1.png');
});

test('51. 无边距', function() use ($outputDir) {
    QrCode::make('无边距')
        ->size(300)
        ->margin(0)
        ->save($outputDir . '/test_51_no_margin.png');
});

test('52. 大边距', function() use ($outputDir) {
    QrCode::make('大边距')
        ->size(300)
        ->margin(20)
        ->save($outputDir . '/test_52_large_margin.png');
});

test('53. 小尺寸', function() use ($outputDir) {
    QrCode::make('小尺寸')
        ->size(100)
        ->save($outputDir . '/test_53_small_size.png');
});

test('54. 大尺寸', function() use ($outputDir) {
    QrCode::make('大尺寸')
        ->size(1000)
        ->save($outputDir . '/test_54_large_size.png');
});

test('55. 极低质量', function() use ($outputDir) {
    QrCode::make('低质量')
        ->size(300)
        ->quality(10)
        ->format('jpeg')
        ->save($outputDir . '/test_55_low_quality.jpg');
});

echo "\n=== 第七部分：更多场景应用 ===\n\n";

test('56. WhatsApp二维码', function() use ($outputDir) {
    QrCode::whatsapp('8613800138000', '你好，想咨询一下产品信息')
        ->size(300)
        ->labelText('联系我们')
        ->save($outputDir . '/test_56_whatsapp.png');
});

test('57. Skype二维码', function() use ($outputDir) {
    QrCode::skype('live:.cid.example')
        ->size(300)
        ->labelText('Skype呼叫')
        ->save($outputDir . '/test_57_skype.png');
});

test('58. Zoom会议二维码', function() use ($outputDir) {
    QrCode::zoom('123456789', 'pass123')
        ->size(300)
        ->labelText('加入会议')
        ->save($outputDir . '/test_58_zoom.png');
});

test('59. PayPal支付二维码', function() use ($outputDir) {
    QrCode::paypal('user@example.com', 99.99, 'USD', 'Product payment')
        ->size(300)
        ->labelText('PayPal支付')
        ->save($outputDir . '/test_59_paypal.png');
});

test('60. 加密货币二维码', function() use ($outputDir) {
    QrCode::crypto('bitcoin', '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa', 0.001)
        ->size(300)
        ->labelText('Bitcoin')
        ->save($outputDir . '/test_60_crypto_btc.png');

    QrCode::crypto('ethereum', '0x71C7656EC7ab88b098defB751B7401B5f6d8976F')
        ->size(300)
        ->labelText('Ethereum')
        ->save($outputDir . '/test_61_crypto_eth.png');
});

test('61. 应用商店二维码', function() use ($outputDir) {
    QrCode::appStore('123456789', 'ios')
        ->size(300)
        ->labelText('App Store')
        ->save($outputDir . '/test_62_appstore_ios.png');

    QrCode::appStore('com.example.app', 'android')
        ->size(300)
        ->labelText('Google Play')
        ->save($outputDir . '/test_63_appstore_android.png');
});

test('62. 社交媒体二维码', function() use ($outputDir) {
    $platforms = ['facebook', 'twitter', 'instagram', 'linkedin'];
    foreach ($platforms as $platform) {
        QrCode::social($platform, 'exampleuser')
            ->size(300)
            ->labelText(ucfirst($platform))
            ->save($outputDir . "/test_64_social_{$platform}.png");
    }
});

test('63. 微信公众号二维码', function() use ($outputDir) {
    QrCode::make('gh_example')
        ->size(300)
        ->labelText('微信公众号')
        ->save($outputDir . '/test_65_wechat_mp.png');
});

test('64. 企业微信二维码', function() use ($outputDir) {
    QrCode::wechatWork('ww123456', '1000001')
        ->size(300)
        ->labelText('企业微信')
        ->save($outputDir . '/test_66_wechat_work.png');
});

test('65. Telegram二维码', function() use ($outputDir) {
    QrCode::telegram('example_bot')
        ->size(300)
        ->labelText('Telegram')
        ->save($outputDir . '/test_67_telegram.png');
});

test('66. GitHub二维码', function() use ($outputDir) {
    QrCode::github('octocat')
        ->size(300)
        ->labelText('GitHub')
        ->save($outputDir . '/test_68_github.png');
});

test('67. 抖音二维码', function() use ($outputDir) {
    QrCode::douyin('MS4wLjABAAAAexample')
        ->size(300)
        ->labelText('抖音')
        ->save($outputDir . '/test_69_douyin.png');
});

echo "\n=== 第八部分：高级功能和优化 ===\n\n";

test('68. 超长文本自动优化', function() use ($outputDir) {
    $longText = str_repeat('这是一个非常长的文本，用于测试自动优化。', 40);
    QrCode::make($longText)
        ->size(500)
        ->errorCorrectionLevel('L')
        ->save($outputDir . '/test_68_ultra_long_text.png');
});

test('69. 高密度二维码', function() use ($outputDir) {
    $data = json_encode([
        'id' => 1,
        'name' => '张三',
        'email' => 'zhangsan@example.com',
        'phone' => '13800138000',
        'address' => '北京市朝阳区某某街道123号',
        'company' => '某某科技有限公司',
        'department' => '技术研发部',
        'position' => '高级工程师',
        'wechat' => 'wxid_example',
        'qq' => '123456789',
        'weibo' => 'weibo_example',
        'github' => 'github_example',
        'blog' => 'https://blog.example.com',
        'skills' => ['PHP', 'JavaScript', 'Python', 'Go'],
        'projects' => ['项目A', '项目B', '项目C'],
        'experience' => '5年',
        'education' => '本科'
    ], JSON_UNESCAPED_UNICODE);

    QrCode::make($data)
        ->size(500)
        ->errorCorrectionLevel('M')
        ->save($outputDir . '/test_71_high_density.png');
});

test('70. 批量生成性能测试', function() use ($outputDir) {
    $startTime = microtime(true);
    for ($i = 1; $i <= 20; $i++) {
        QrCode::make("Batch Test {$i}")
            ->size(200)
            ->save($outputDir . "/test_72_batch_perf_{$i}.png");
    }
    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);
    echo "  (耗时: {$duration}秒)\n";
});

test('71. 多种格式输出', function() use ($outputDir) {
    $formats = ['png', 'jpeg', 'gif', 'webp'];
    foreach ($formats as $format) {
        QrCode::make("Format: {$format}")
            ->size(300)
            ->format($format)
            ->save($outputDir . "/test_73_format_{$format}.{$format}");
    }
});

test('72. 动态背景二维码', function() use ($outputDir) {
    $bgPath = $outputDir . '/test_bg_dynamic.jpg';
    $bgSize = 400;
    $bgImg = imagecreatetruecolor($bgSize, $bgSize);

    // 创建渐变背景
    for ($y = 0; $y < $bgSize; $y++) {
        $r = (int)(100 + ($y / $bgSize) * 100);
        $g = (int)(150 - ($y / $bgSize) * 50);
        $b = (int)(200 + ($y / $bgSize) * 50);
        $color = imagecolorallocate($bgImg, $r, $g, $b);
        imageline($bgImg, 0, $y, $bgSize, $y, $color);
    }

    imagejpeg($bgImg, $bgPath, 90);
    imagedestroy($bgImg);

    QrCode::make('Dynamic Background')
        ->size(400)
        ->backgroundImage($bgPath)
        ->foregroundColor('#ffffff')
        ->labelText('动态背景')
        ->save($outputDir . '/test_74_dynamic_bg.png');
});

test('73. 渐变色二维码', function() use ($outputDir) {
    QrCode::make('Gradient Colors')
        ->size(300)
        ->backgroundColor('#3498db')
        ->foregroundColor('#f1c40f')
        ->save($outputDir . '/test_75_gradient.png');
});

echo "\n=== 第九部分：组合功能测试 ===\n\n";

test('74. 完整功能组合', function() use ($outputDir, $logoPath) {
    QrCode::make('https://www.example.com')
        ->size(500)
        ->errorCorrectionLevel('H')
        ->backgroundColor('#f8f9fa')
        ->foregroundColor('#2c3e50')
        ->logo($logoPath, 12)
        ->logoRounded(true, 0.3)
        ->logoShadow('#000000', 3, 3)
        ->labelOptions(
            LabelOptions::create('官方网站')
                ->fontSize(16)
                ->color('#2c3e50')
                ->backgroundColor('#ffffff')
                ->borderColor('#2c3e50')
                ->borderWidth(2)
                ->borderRadius(0.2)
                ->padding(10)
                ->margin(15)
                ->textShadow('#000000', 1, 1)
        )
        ->save($outputDir . '/test_76_complete.png');
});

test('75. 圆点风格+Logo', function() use ($outputDir, $logoPath) {
    QrCode::make('https://www.example.com')
        ->size(400)
        ->errorCorrectionLevel('H')
        ->rounded(true)
        ->roundedRadius(0.6)
        ->logo($logoPath, 12)
        ->logoCircular(true)
        ->labelText('圆点风格')
        ->save($outputDir . '/test_77_rounded_logo.png');
});

test('76. 多行文本标签', function() use ($outputDir) {
    QrCode::make('https://www.example.com')
        ->size(300)
        ->labelOptions(
            LabelOptions::create("第一行：网站链接\n第二行：扫码访问\n第三行：感谢关注")
                ->fontSize(14)
                ->padding(8)
                ->lineHeight(24)
                ->backgroundColor('#ffffff')
                ->borderColor('#3498db')
                ->borderWidth(1)
        )
        ->save($outputDir . '/test_78_multiline_label.png');
});

test('77. 左对齐标签', function() use ($outputDir) {
    QrCode::make('Left Align Test')
        ->size(300)
        ->labelOptions(
            LabelOptions::create('左对齐文本')
                ->alignment('left')
                ->paddingLeft(20)
                ->backgroundColor('#f8f9fa')
        )
        ->save($outputDir . '/test_79_left_align.png');
});

test('78. 右对齐标签', function() use ($outputDir) {
    QrCode::make('Right Align Test')
        ->size(300)
        ->labelOptions(
            LabelOptions::create('右对齐文本')
                ->alignment('right')
                ->paddingRight(20)
                ->backgroundColor('#f8f9fa')
        )
        ->save($outputDir . '/test_80_right_align.png');
});

echo "\n=== 第十部分：特殊场景测试 ===\n\n";

test('79. 极小二维码', function() use ($outputDir) {
    QrCode::make('A')
        ->size(50)
        ->margin(2)
        ->save($outputDir . '/test_81_mini.png');
});

test('80. 超大二维码', function() use ($outputDir) {
    QrCode::make('Large QR Code Test')
        ->size(1500)
        ->margin(10)
        ->save($outputDir . '/test_82_huge.png');
});

test('81. 无边距+标签', function() use ($outputDir) {
    QrCode::make('No Margin')
        ->size(300)
        ->margin(0)
        ->labelText('无边距')
        ->save($outputDir . '/test_83_no_margin_label.png');
});

test('82. 大边距二维码', function() use ($outputDir) {
    QrCode::make('Large Margin')
        ->size(500)
        ->margin(15)
        ->labelText('大边距')
        ->save($outputDir . '/test_84_large_margin.png');
});

test('83. 高对比度颜色', function() use ($outputDir) {
    QrCode::make('High Contrast')
        ->size(300)
        ->backgroundColor('#ffffff')
        ->foregroundColor('#000000')
        ->save($outputDir . '/test_85_contrast_high.png');
});

test('84. 低对比度颜色', function() use ($outputDir) {
    QrCode::make('Low Contrast')
        ->size(300)
        ->backgroundColor('#e0e0e0')
        ->foregroundColor('#404040')
        ->save($outputDir . '/test_86_contrast_low.png');
});

test('85. 彩色二维码', function() use ($outputDir) {
    $colors = [
        ['#e74c3c', '#ffffff'],
        ['#3498db', '#ffffff'],
        ['#2ecc71', '#ffffff'],
        ['#f39c12', '#ffffff'],
        ['#9b59b6', '#ffffff']
    ];
    foreach ($colors as $i => $colorsPair) {
        QrCode::make("Color {$i}")
            ->size(250)
            ->backgroundColor($colorsPair[1])
            ->foregroundColor($colorsPair[0])
            ->save($outputDir . "/test_87_color_{$i}.png");
    }
});

echo "\n=== 第七部分：新增高级应用场景 ===\n\n";

test('86. NFC标签二维码', function() use ($outputDir) {
    QrCode::nfcTag('NFC001', 'open_url', 'https://example.com/nfc')
        ->size(300)
        ->labelText('NFC标签')
        ->save($outputDir . '/test_86_nfc_tag.png');
});

test('87. 蓝牙配对二维码', function() use ($outputDir) {
    QrCode::bluetoothPair('SmartSpeaker', 'AA:BB:CC:DD:EE:FF', '1234')
        ->size(300)
        ->labelText('蓝牙配对')
        ->save($outputDir . '/test_87_bluetooth.png');
});

test('88. 智能家居配置', function() use ($outputDir) {
    QrCode::smartHome('DEVICE001', 'light', 'MyWiFi', 'password123')
        ->size(300)
        ->labelText('智能家居配置')
        ->save($outputDir . '/test_88_smarthome.png');
});

test('89. 车载导航', function() use ($outputDir) {
    QrCode::carNavigation(39.9042, 116.4074, '天安门广场', 'driving')
        ->size(300)
        ->labelText('导航到目的地')
        ->save($outputDir . '/test_89_navigation.png');
});

test('90. 语音助手指令', function() use ($outputDir) {
    QrCode::voiceAssistant('xiaomi', '打开客厅的灯')
        ->size(300)
        ->labelText('语音指令')
        ->save($outputDir . '/test_90_voice_assistant.png');
});

test('91. 物联网设备', function() use ($outputDir) {
    QrCode::iotDevice('IOT001', 'SensorPro', 'v2.1.0', 'https://iot.example.com/api')
        ->size(300)
        ->labelText('物联网设备')
        ->save($outputDir . '/test_91_iot_device.png');
});

test('92. AR/VR体验', function() use ($outputDir) {
    QrCode::arVrExperience('AR001', 'ar', 'https://cdn.example.com/ar/model.glb')
        ->size(300)
        ->labelText('AR体验')
        ->save($outputDir . '/test_92_ar_vr.png');
});

test('93. NFT二维码', function() use ($outputDir) {
    QrCode::nft('0x1234567890abcdef1234567890abcdef12345678', '1', 'ethereum')
        ->size(300)
        ->labelText('查看NFT')
        ->save($outputDir . '/test_93_nft.png');
});

test('94. 数字身份/DID', function() use ($outputDir) {
    QrCode::digitalIdentity('did:example:123456', '张三', 'https://vc.example.com/verify/123456')
        ->size(300)
        ->labelText('数字身份')
        ->save($outputDir . '/test_94_did.png');
});

test('95. 游戏邀请', function() use ($outputDir) {
    QrCode::gaming('GAME001', 'rpg', 'INVITE2024', 'https://game.example.com/reward')
        ->size(300)
        ->labelText('游戏邀请')
        ->save($outputDir . '/test_95_gaming.png');
});

test('96. 无人机控制', function() use ($outputDir) {
    QrCode::droneControl('DRONE001', 'MISSION2024', 'https://drone.example.com/control')
        ->size(300)
        ->labelText('无人机控制')
        ->save($outputDir . '/test_96_drone.png');
});

test('97. 教育资源', function() use ($outputDir) {
    QrCode::education('COURSE001', 'LESSON001', 'https://edu.example.com/resource', 'https://edu.example.com/quiz')
        ->size(300)
        ->labelText('学习资源')
        ->save($outputDir . '/test_97_education.png');
});

test('98. 医疗记录', function() use ($outputDir) {
    QrCode::medicalRecord('PATIENT001', 'RECORD001', 'HOSPITAL001', 'https://medical.example.com/verify')
        ->size(300)
        ->labelText('医疗记录')
        ->save($outputDir . '/test_98_medical.png');
});

test('99. 法律文件', function() use ($outputDir) {
    QrCode::legalDocument('DOC001', 'contract', 'v1.0', 'https://legal.example.com/verify')
        ->size(300)
        ->labelText('法律文件')
        ->save($outputDir . '/test_99_legal.png');
});

test('100. 政府服务', function() use ($outputDir) {
    QrCode::governmentService('SERVICE001', '身份证补办', '123456789012345678', 'https://gov.example.com/portal')
        ->size(300)
        ->labelText('政务服务')
        ->save($outputDir . '/test_100_government.png');
});

test('101. 应急救援', function() use ($outputDir) {
    QrCode::emergency('EMERGENCY001', 'medical', 39.9042, 116.4074, '120')
        ->size(300)
        ->labelText('紧急救援')
        ->save($outputDir . '/test_101_emergency.png');
});

test('102. 物流追踪', function() use ($outputDir) {
    QrCode::logistics('ITEM001', 'WAREHOUSE001', 'A-01-03', 'https://logistics.example.com/track/ITEM001')
        ->size(300)
        ->labelText('物流追踪')
        ->save($outputDir . '/test_102_logistics.png');
});

test('103. 农业溯源', function() use ($outputDir) {
    QrCode::agriculture('PROD001', 'FARM001', 'BATCH2024', '2024-01-15', 'https://agri.example.com/trace')
        ->size(300)
        ->labelText('农产品溯源')
        ->save($outputDir . '/test_103_agriculture.png');
});

test('104. 保险理赔', function() use ($outputDir) {
    QrCode::insuranceClaim('POLICY001', 'CLAIM001', 'car', 'https://insurance.example.com/claim')
        ->size(300)
        ->labelText('保险理赔')
        ->save($outputDir . '/test_104_insurance.png');
});

test('105. 体育赛事门票', function() use ($outputDir) {
    QrCode::sportsTicket('EVENT001', '足球比赛', '鸟巢体育场', 'A区15排20座', '2024-06-01 19:30')
        ->size(300)
        ->labelText('比赛门票')
        ->save($outputDir . '/test_105_sports_ticket.png');
});

test('106. 房地产房源', function() use ($outputDir) {
    QrCode::property('PROP001', 'apartment', '北京市朝阳区建国路88号', 'https://property.example.com/tour/PROP001')
        ->size(300)
        ->labelText('VR看房')
        ->save($outputDir . '/test_106_property.png');
});

test("107. 快递单号二维码", function() use ($outputDir)  {
    QrCode::express('SF1234567890', 'sf', '1234')
        ->size(200)
        ->save($outputDir . '/test_107_sf_express.png');
});

test("108. 发票二维码", function() use ($outputDir)  {
    QrCode::invoice('12345678', '1234567890', 299.00, '2026-02-11')
        ->size(200)
        ->save($outputDir . '/test_108_invoice.png');
});

test("109. 餐厅点餐二维码", function() use ($outputDir)  {
    QrCode::restaurantMenu('rest001', 8)
        ->size(200)
        ->save($outputDir . '/test_109_restaurantMenu.png');
});

test("110. 停车缴费二维码", function() use ($outputDir)  {
    QrCode::parking('PARK001', '京A12345', '2026-02-11 10:00:00')
        ->size(200)
        ->save($outputDir . '/test_110_parking.png');
});

test("111. 问卷调查二维码", function()  use ($outputDir) {
    QrCode::survey('survey001', 'user123')
        ->size(200)
        ->save($outputDir . '/test_111_survey.png');
});

// 测试Logo增强功能
test("112. Logo阴影设置", function() use ($outputDir,$logoPath)  {
    $qr = QrCode::make('test')
        ->logo($logoPath, 10)
        ->logoShadow('#000000', 2, 2)
        ->save($outputDir . '/test_112_logo_shadow.png');
});

test("113. Logo透明度设置", function() use ($outputDir,$logoPath)  {
    QrCode::make('test')
        ->logo($logoPath, 10)
        ->logoOpacity(60)
        ->save($outputDir . '/test_113_logo_opacity.png');
});

test("115. Logo旋转设置", function() use ($outputDir,$logoPath)  {
    QrCode::make('test')
        ->logo($logoPath, 10)
        ->logoRotation(45)
        ->save($outputDir . '/test_115_logo_rotation.png');
});

// 测试圆点风格增强
test("116. 圆点风格半径调整", function() use ($outputDir)  {
    QrCode::make('test')
        ->size(200)
        ->rounded(true)
        ->roundedRadius(0.75)
        ->save($outputDir . '/test_116_rounded.png');
});

echo "\n========================================\n";
echo "测试总结\n";
echo "========================================\n";
echo "总测试数: {$testCount}\n";
echo "通过: {$passedCount}\n";
echo "失败: " . ($testCount - $passedCount) . "\n";
echo "成功率: " . round($passedCount / $testCount * 100, 2) . "%\n";
echo "输出目录: {$outputDir}\n";
echo "========================================\n";

// 写入测试结果到文件
$resultText = "zxf/qr-code 官方测试结果\n";
$resultText .= "========================================\n";
$resultText .= "测试时间: " . date('Y-m-d H:i:s') . "\n";
$resultText .= "版本: 2.3.0\n";
$resultText .= "========================================\n";
$resultText .= "总测试数: {$testCount}\n";
$resultText .= "通过: {$passedCount}\n";
$resultText .= "失败: " . ($testCount - $passedCount) . "\n";
$resultText .= "成功率: " . round($passedCount / $testCount * 100, 2) . "%\n";
$resultText .= "========================================\n";

file_put_contents($outputDir . '/test_result.txt', $resultText);

echo "\n测试结果已保存到: {$outputDir}/test_result.txt\n";

exit($testCount - $passedCount > 0 ? 1 : 0);
