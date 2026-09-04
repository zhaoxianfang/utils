<?php

declare(strict_types=1);

namespace zxf\Utils\Tests\Unit;

use ReflectionClass;
use Throwable;
use zxf\Utils\BarCode\BarcodeBuilder;
use zxf\Utils\BarCode\Generator\Code128Generator;
use zxf\Utils\BarCode\Generator\Code39Generator;
use zxf\Utils\BarCode\Generator\CodabarGenerator;
use zxf\Utils\BarCode\Generator\EAN13Generator;
use zxf\Utils\BarCode\Generator\EAN8Generator;
use zxf\Utils\BarCode\Generator\ITF14Generator;
use zxf\Utils\BarCode\Generator\UPCAGenerator;
use zxf\Utils\Tests\Assert;
use zxf\Utils\Tests\TestCase;

/**
 * 一维条码「像素级」解码往返自检
 *
 * 目的：保证「库生成的 PNG → 扫码识别的文字」与「输入的数据」完全一致。
 * 传统做法只断言 PNG 能生成，从不验证条码像素内容本身；
 * 本用例逐像素扫描条码区域，用独立实现把像素还原成文字再与期望内容比对，
 * 因此能覆盖 zbar 等第三方工具无法解码的 ITF-14 / Codabar（zbar 对 ITF-14 解码器
 * 存在缺陷、且完全没有 Codabar 解码器，业界参考图同样无法识别）。
 *
 * 运行：php tests/run.php --filter=BarCodeDecodeRoundTrip
 */
class BarCodeDecodeRoundTripTest extends TestCase
{
    /** @var list<string> 本轮收集的问题描述 */
    private array $issues = [];

    protected function setUp(): void
    {
        $this->issues = [];
    }

    /**
     * 独立校验位实现（EAN/UPC/ITF-14 统一 MOD 10，最右侧权重 3）
     */
    private static function mod10CheckDigit(string $digitsNoCheck): string
    {
        $sum = 0;
        $len = strlen($digitsNoCheck);
        for ($i = 0; $i < $len; $i++) {
            $digit = (int) $digitsNoCheck[$len - 1 - $i];
            $sum += $digit * ($i % 2 === 0 ? 3 : 1);
        }
        return (string) ((10 - ($sum % 10)) % 10);
    }

    /* ======================================================================
     * 1. 逐像素解码往返：每种类型都验证 输入内容 == 像素还原内容
     * ==================================================================== */

    public function testPixelDecodeRoundTrip(): void
    {
        if (! function_exists('imagecreatefrompng')) {
            echo "  [跳过] 缺少 GD 扩展\n";
            return;
        }

        $cases = [
            // [说明, builder 类型, 输入数据]
            ['EAN-13 基础(12 位自动补校验)',          'ean13',   '690123456789'],
            ['EAN-13 用户报错样本 6901234567892',     'ean13',   '6901234567892'],
            ['EAN-13 ISBN 前缀 9780201379624',        'ean13',   '9780201379624'],
            ['EAN-13 用户报错 9783161484100',         'ean13',   '9783161484100'],
            ['EAN-13 用户报错 9780306406157',         'ean13',   '9780306406157'],
            ['EAN-13 校验位计算 590123412345',        'ean13',   '590123412345'],
            ['EAN-8 7 位自动补校验',                  'ean8',    '9638507'],
            ['EAN-8 完整 8 位',                       'ean8',    '96385074'],
            ['UPC-A 11 位自动补校验',                 'upca',    '03600029145'],
            ['UPC-A 完整 12 位',                      'upca',    '036000291452'],
            ['ISBN-13 直接输入',                      'isbn',    '9780201379624'],
            ['ISBN-10 转换(用户报错 0306406152)',     'isbn',    '0306406152'],
            ['ISBN-10 带连字符 0-306-40615-2',        'isbn',    '0-306-40615-2'],
            ['ISSN 0028-0836',                        'issn',    '0028-0836'],
            ['Code128 常规 B 集',                     'code128', 'TEST-1234567890'],
            ['Code128 长串(40 字符)',                 'code128', str_repeat('ABCDE', 8)],
            ['Code128 纯数字 C 集',                   'code128', '123456789012345'],
            ['Code128 混合含控制字符',                'code128', "LINE1\nLINE2\x01END"],
            ['Code39 基础',                           'code39',  'CODE39'],
            ['Code39 含空格/连字符',                  'code39',  'BAR CODE-39'],
            ['ITF-14 用户报错 15400141288763',        'itf14',   '15400141288763'],
            ['ITF-14 13 位自动补校验',                'itf14',   '1540014128876'],
            ['ITF-14 13 位自动补校验(978 前缀)',      'itf14',   '9780201379624'],
            ['Codabar 用户报错 A1234567890B',         'codabar', 'A1234567890B'],
            ['Codabar 自动补充起止符',                'codabar', '1234567890'],
            ['Codabar 符号字符',                      'codabar', 'A12-34:56$78+B'],
        ];

        $tmpDir = sys_get_temp_dir() . '/zxf_barcode_roundtrip_' . bin2hex(random_bytes(4));
        mkdir($tmpDir, 0755, true);

        try {
            foreach ($cases as [$desc, $type, $data]) {
                try {
                    $builder = BarcodeBuilder::create()->type($type)->data($data);
                    $expected = $builder->getFullData();

                    $file = $tmpDir . '/' . preg_replace('/[^A-Za-z0-9]+/', '_', $desc) . '.png';
                    $builder->savePng($file);

                    $decoded = $this->decodePng($file, $type);
                    if ($decoded === null) {
                        $this->issues[] = "[{$desc}] 像素解码失败（无任何可识别的行）";
                    } elseif ($decoded !== $expected) {
                        $this->issues[] = sprintf(
                            '[%s] 内容不一致：期望 "%s"，像素还原为 "%s"',
                            $desc,
                            $expected,
                            $decoded
                        );
                    }
                } catch (Throwable $e) {
                    $this->issues[] = "[{$desc}] 生成/解码抛异常: " . $e->getMessage();
                }
            }
        } finally {
            foreach (glob($tmpDir . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($tmpDir);
        }

        Assert::true($this->issues === [], implode("\n", $this->issues));
    }

    /* ======================================================================
     * 2. 编码表规范不变量（独立于像素解码的静态结构审计）
     * ==================================================================== */

    public function testEncodeTablesSpecInvariants(): void
    {
        // Code 128：107 个模式；0-105 长度 11，106(stop) 长度 13；模式唯一；含三种起始符
        $c128 = self::tableOf(Code128Generator::class, 'encoding');
        Assert::true(count($c128) === 107, 'Code128 编码表应有 107 项，实际 ' . count($c128));
        foreach ($c128 as $value => $pattern) {
            $isStop = (int) $value === 106;
            $expectedLen = $isStop ? 13 : 11;
            Assert::true(
                strlen($pattern) === $expectedLen && preg_match('/^[01]+$/', $pattern) === 1,
                "Code128 模式 {$value} 非法"
            );
        }
        Assert::true(count(array_unique($c128)) === count($c128), 'Code128 模式应两两不同');
        Assert::same('11010000100', $c128[103] ?? '', 'Code128 START_A 模式错误');
        Assert::same('11010010000', $c128[104] ?? '', 'Code128 START_B 模式错误');
        Assert::same('11010011100', $c128[105] ?? '', 'Code128 START_C 模式错误');
        Assert::same('1100011101011', $c128[106] ?? '', 'Code128 STOP 模式错误');

        // Code 39：每个字符 9 元素且恰好 3 个宽元素（n/w 字符串）
        $c39 = self::tableOf(Code39Generator::class, 'encoding');
        Assert::true(count($c39) >= 43, 'Code39 编码表应至少 43 项（含 *）');
        foreach ($c39 as $char => $pattern) {
            Assert::true(
                strlen($pattern) === 9 && substr_count($pattern, 'w') === 3,
                "Code39 字符 '{$char}' 模式非法（应为 9 元素 3 宽）"
            );
        }
        Assert::true(isset($c39['*']), 'Code39 缺少起始/终止符 *');

        // Codabar：与 ZXing / jsBarcode 权威表逐字符核验（元素 7 个，条-空交替）
        // 注意：数据字符宽度并不统一——: / . 为 3 宽、+ 为 5 宽；起止符 A-D 为 3 宽。
        // 旧实现曾在 1/2/4/7/+ 上误用自造模式（自我一致但外部扫码器不认）。
        $coda = self::tableOf(CodabarGenerator::class, 'encoding');
        $zxCoda = [ // ZXing CodaBarReader.CODA_BAR_CHAR_SET（n=窄 w=宽，元素自条开始）
            '0' => 'nnnnnww', '1' => 'nnnnwwn', '2' => 'nnnwnnw', '3' => 'wwnnnnn',
            '4' => 'nnwnnwn', '5' => 'wnnnnwn', '6' => 'nwnnnnw', '7' => 'nwnnwnn',
            '8' => 'nwwnnnn', '9' => 'wnnwnnn', '-' => 'nnnwwnn', '$' => 'nnwwnnn',
            ':' => 'wnnnwnw', '/' => 'wnwnnnw', '.' => 'wnwnwnn', '+' => 'nnwwwww',
            'A' => 'nnwwnwn', 'B' => 'nwnwnnw', 'C' => 'nnnwnww', 'D' => 'nnnwwwn',
        ];
        Assert::same(count($zxCoda), count($coda), 'Codabar 编码表应覆盖全部 20 个字符');
        foreach ($zxCoda as $char => $zxPattern) {
            Assert::true(isset($coda[$char]), "Codabar 缺字符 '{$char}'");
            $converted = [];
            for ($i = 0; $i < 7; $i++) {
                $converted[] = $zxPattern[$i] === 'w' ? 2 : 1;
            }
            Assert::same($converted, array_values($coda[$char]), "Codabar 字符 '{$char}' 与 ZXing 权威表不一致");
        }

        // ITF-14：与 jsBarcode/zbar/ZXing 共识的 I2of5 表逐位核验（5 元素恰 2 宽）
        // 历史缺陷：旧表在 5/6/8/9 上宽位错位，真扫码器按标准表把内容确定性翻转（5↔8、6↔9）。
        $itf = self::tableOf(ITF14Generator::class, 'encoding');
        Assert::same(10, count($itf), 'ITF-14 编码表应恰好 10 位数字');
        $authItf = [ // 与 jsBarcode src/barcodes/ITF/constants.js 的 BINARIES 一致
            '0' => 'nnwwn', '1' => 'wnnnw', '2' => 'nwnnw', '3' => 'wwnnn', '4' => 'nnwnw',
            '5' => 'wnwnn', '6' => 'nwwnn', '7' => 'nnnww', '8' => 'wnnwn', '9' => 'nwnwn',
        ];
        foreach ($authItf as $digit => $authPattern) {
            Assert::true(isset($itf[$digit]), "ITF-14 缺数字 {$digit}");
            Assert::same($authPattern, $itf[$digit], "ITF-14 数字 {$digit} 与权威 I2of5 表不一致");
            Assert::true(strlen($authPattern) === 5 && substr_count($authPattern, 'w') === 2, "权威表自身校验失败 {$digit}");
        }

        // EAN-13：A/B/C 各 10 项、7 位二进制串；C 为 A 的按位取反；B 与 A/C 不重复
        $encA = self::tableOf(EAN13Generator::class, 'encodingA');
        $encB = self::tableOf(EAN13Generator::class, 'encodingB');
        $encC = self::tableOf(EAN13Generator::class, 'encodingC');
        foreach (range(0, 9) as $d) {
            Assert::true(
                strlen($encA[$d]) === 7 && preg_match('/^[01]{7}$/', $encA[$d]) === 1,
                "EAN-13 L 码 {$d} 非法"
            );
            Assert::same(
                strtr($encA[$d], ['0' => '1', '1' => '0']),
                $encC[$d],
                "EAN-13 R 码 {$d} 应为 L 码取反"
            );
            Assert::true($encA[$d] !== $encB[$d] && $encB[$d] !== $encC[$d], "EAN-13 奇偶码 {$d} 冲突");
        }
        $parity = self::tableOf(EAN13Generator::class, 'parityPattern');
        Assert::same(10, count($parity), 'EAN-13 奇偶表应有 10 组');
        foreach ($parity as $firstDigit => $seq) {
            Assert::same(6, count($seq), "EAN-13 首位 {$firstDigit} 奇偶表应 6 项");
            Assert::true(count(array_unique($seq)) <= 2, "EAN-13 首位 {$firstDigit} 奇偶表非法字符");
        }

        // EAN-8 / UPC-A 复用同一套 A/C 码
        foreach ([EAN8Generator::class, UPCAGenerator::class] as $class) {
            $a = self::tableOf($class, 'encodingA');
            $c = self::tableOf($class, 'encodingC');
            Assert::same($a, $encA, $class . ' A 码应等于 EAN-13 A 码');
            Assert::same($c, $encC, $class . ' C 码应等于 EAN-13 C 码');
        }
    }

    /* ======================================================================
     * 3. 校验位算法独立验证（生成 13/14/8/12 位完整数据必须与独立 MOD10 一致）
     * ==================================================================== */

    public function testChecksumAlgorithmsIndependent(): void
    {
        $cases = [
            // [类型, 不含校验位的输入, 期望完整数据]
            ['ean13', '690123456789', '6901234567892'],
            ['ean13', '978020137962', '9780201379624'],
            ['ean13', '978316148410', '9783161484100'],
            ['ean8',  '9638507',       '96385074'],
            ['upca',  '03600029145',   '036000291452'],
            ['itf14', '1540014128876', '15400141288763'],
            ['itf14', '9780201379624', '97802013796248'],
        ];
        foreach ($cases as [$type, $input, $expectedFull]) {
            $check = self::mod10CheckDigit($input);
            Assert::same(
                $expectedFull,
                $input . $check,
                "{$type} 独立 MOD10 计算错误：{$input} → 校验位 {$check}"
            );

            // 库计算结果必须与独立实现一致
            $builder = BarcodeBuilder::create()->type($type)->data($input);
            Assert::same($expectedFull, $builder->getFullData(), "{$type} getFullData 与独立 MOD10 不一致");
        }
    }

    /* ======================================================================
     * 像素解码基础设施
     * ==================================================================== */

    /**
     * 尝试解码 PNG 中某一行像素；返回该行还原的内容字符串，失败返回 null。
     *
     * @param \GdImage $image
     * @return list<array{0: int, 1: bool}>
     */
    private static function rowRuns($image, int $y): array
    {
        $w = imagesx($image);
        $runs = [];
        $curColor = null;
        $curLen = 0;

        for ($x = 0; $x < $w; $x++) {
            $rgb = imagecolorat($image, $x, $y);
            $red = ($rgb >> 16) & 0xFF;
            $green = ($rgb >> 8) & 0xFF;
            $blue = $rgb & 0xFF;
            $dark = (($red + $green + $blue) / 3) < 128;

            if ($curColor === null) {
                $curColor = $dark;
            }
            if ($dark === $curColor) {
                $curLen++;
                continue;
            }
            $runs[] = [$curLen, $curColor];
            $curColor = $dark;
            $curLen = 1;
        }
        if ($curLen > 0) {
            $runs[] = [$curLen, $curColor];
        }
        return $runs;
    }

    /**
     * 去掉首尾的空白运行，只保留条码内容本身。
     *
     * @param list<array{0: int, 1: bool}> $runs
     * @return list<array{0: int, 1: bool}>
     */
    private static function contentRuns(array $runs): array
    {
        $first = null;
        $last = null;
        foreach ($runs as $i => [$len, $dark]) {
            if ($dark) {
                $first ??= $i;
                $last = $i;
            }
        }
        if ($first === null || $last === null) {
            return [];
        }
        return array_slice($runs, $first, $last - $first + 1);
    }

    private static function gcd2(int $a, int $b): int
    {
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }
        return max(1, $a);
    }

    /**
     * 把内容运行换算为「模块字符串」：亮=1，暗=0。
     *
     * @param list<array{0: int, 1: bool}> $runs
     */
    private static function moduleBits(array $runs): ?string
    {
        if ($runs === []) {
            return null;
        }
        $unit = 0;
        foreach ($runs as [$len]) {
            $unit = self::gcd2($unit, $len);
        }
        $out = '';
        foreach ($runs as [$len, $dark]) {
            if ($len % $unit !== 0) {
                return null; // 该行像素宽度不是模块的整数倍
            }
            $out .= str_repeat($dark ? '1' : '0', intdiv($len, $unit));
        }
        return $out;
    }

    /** 解码 PNG（从上到下尝试所有行，返回首个成功解码的内容） */
    private function decodePng(string $file, string $type): ?string
    {
        $image = @imagecreatefrompng($file);
        if ($image === false) {
            return null;
        }
        try {
            $h = imagesy($image);
            $start = (int) ($h * 0.05);
            $end = (int) ($h * 0.95);
            for ($y = $start; $y <= $end; $y++) {
                $runs = self::rowRuns($image, $y);
                if ($runs === []) {
                    continue;
                }
                $content = self::contentRuns($runs);
                if ($content === []) {
                    continue;
                }
                $decoded = match ($type) {
                    'ean13', 'isbn', 'issn' => self::decodeEan($content, 95),
                    'ean8' => self::decodeEan($content, 67),
                    'upca' => self::decodeEan($content, 95, true),
                    'code128' => self::decodeCode128($content),
                    'code39' => self::decodeCode39($content),
                    'codabar' => self::decodeCodabar($content),
                    'itf14' => self::decodeITF($content),
                    default => null,
                };
                if ($decoded !== null) {
                    return $decoded;
                }
            }
            return null;
        } finally {
            // PHP 8.0+ imagedestroy() 已是空操作；PHP 8.5 起标记为 deprecated，不再调用。
            if (PHP_VERSION_ID < 80000 && function_exists('imagedestroy')) {
                imagedestroy($image);
            }
        }
    }

    /* ======================================================================
     * 各码制像素解码器
     * ==================================================================== */

    /**
     * EAN-13 / EAN-8 / UPC-A 解码（基于模块字符串切片）
     *
     * EAN-13 与 UPC-A 均为 95 模块；EAN-8 为 67 模块。
     *
     * @param list<array{0: int, 1: bool}> $content
     */
    private static function decodeEan(array $content, int $totalModules, bool $isUpca = false): ?string
    {
        $bits = self::moduleBits($content);
        if ($bits === null || strlen($bits) !== $totalModules) {
            return null;
        }

        if ($totalModules === 95) {
            // EAN-13 与 UPC-A 的保护符相同（右侧保护符位于 92..94）
            if (substr($bits, 0, 3) !== '101' || substr($bits, 45, 5) !== '01010' || substr($bits, 92, 3) !== '101') {
                return null;
            }
            $encA = self::tableOf(EAN13Generator::class, 'encodingA');
            $encB = self::tableOf(EAN13Generator::class, 'encodingB');
            $encC = self::tableOf(EAN13Generator::class, 'encodingC');
            $parity = self::tableOf(EAN13Generator::class, 'parityPattern');

            $leftCodes = [];
            $rightCodes = [];
            for ($i = 0; $i < 6; $i++) {
                $leftCodes[] = substr($bits, 3 + $i * 7, 7);
                $rightCodes[] = substr($bits, 50 + $i * 7, 7);
            }

            // 左侧：UPC-A 全 A 码；EAN-13 按首位奇偶在 A/B 中
            $parityBits = '';
            $leftDigits = [];
            foreach ($leftCodes as $code) {
                $found = null;
                $foundParity = null;
                foreach ($encA as $digit => $pattern) {
                    if ($pattern === $code) {
                        $found = (string) $digit;
                        $foundParity = 'A';
                        break;
                    }
                }
                if ($found === null) {
                    foreach ($encB as $digit => $pattern) {
                        if ($pattern === $code) {
                            $found = (string) $digit;
                            $foundParity = 'B';
                            break;
                        }
                    }
                }
                if ($found === null) {
                    return null;
                }
                $leftDigits[] = $found;
                $parityBits .= $foundParity;
            }

            // 右侧（R 码）
            $rightDigits = [];
            foreach ($rightCodes as $code) {
                $found = null;
                foreach ($encC as $digit => $pattern) {
                    if ($pattern === $code) {
                        $found = (string) $digit;
                        break;
                    }
                }
                if ($found === null) {
                    return null;
                }
                $rightDigits[] = $found;
            }

            $digits = implode('', array_merge($leftDigits, $rightDigits));

            if ($isUpca) {
                // UPC-A：左侧必须全部 A 码；固定 12 位输出
                if ($parityBits !== 'AAAAAA' || strlen($digits) !== 12) {
                    return null;
                }
                if (self::mod10CheckDigit(substr($digits, 0, 11)) !== $digits[11]) {
                    return null;
                }
                return $digits;
            }

            // EAN-13：奇偶模式推导首位（parityBits 全 A 即首位 0）
            $firstDigit = null;
            foreach ($parity as $first => $seq) {
                if (implode('', $seq) === $parityBits) {
                    $firstDigit = (string) $first;
                    break;
                }
            }
            if ($firstDigit === null) {
                return null;
            }
            $digits13 = $firstDigit . $digits;
            if (strlen($digits13) !== 13) {
                return null;
            }
            if (self::mod10CheckDigit(substr($digits13, 0, 12)) !== $digits13[12]) {
                return null;
            }
            return $digits13;
        }

        // EAN-8（67 模块）
        if (substr($bits, 0, 3) !== '101' || substr($bits, 31, 5) !== '01010' || substr($bits, 64, 3) !== '101') {
            return null;
        }
        $encA = self::tableOf(EAN8Generator::class, 'encodingA');
        $encC = self::tableOf(EAN8Generator::class, 'encodingC');

        $digits = '';
        for ($i = 0; $i < 4; $i++) {
            $leftCode = substr($bits, 3 + $i * 7, 7);
            $found = null;
            foreach ($encA as $digit => $pattern) {
                if ($pattern === $leftCode) {
                    $found = (string) $digit;
                    break;
                }
            }
            if ($found === null) {
                return null;
            }
            $digits .= $found;
        }
        for ($i = 0; $i < 4; $i++) {
            $rightCode = substr($bits, 36 + $i * 7, 7);
            $found = null;
            foreach ($encC as $digit => $pattern) {
                if ($pattern === $rightCode) {
                    $found = (string) $digit;
                    break;
                }
            }
            if ($found === null) {
                return null;
            }
            $digits .= $found;
        }
        if (strlen($digits) !== 8 || self::mod10CheckDigit(substr($digits, 0, 7)) !== $digits[7]) {
            return null;
        }
        return $digits;
    }

    /**
     * Code 128 解码：11 模块/码字 + 13 模块停止符。
     *
     * @param list<array{0: int, 1: bool}> $content
     */
    private static function decodeCode128(array $content): ?string
    {
        $bits = self::moduleBits($content);
        if ($bits === null) {
            return null;
        }
        $patterns = self::tableOf(Code128Generator::class, 'encoding');
        $stopPattern = $patterns[106] ?? '';

        $pos = 0;
        $len = strlen($bits);

        // 起始码（11 模块）
        $startPattern = substr($bits, 0, 11);
        $startValue = array_search($startPattern, $patterns, true);
        if ($startValue === false) {
            return null;
        }
        $currentSet = match ($startValue) {
            103 => 'A',
            104 => 'B',
            105 => 'C',
            default => null,
        };
        if ($currentSet === null) {
            return null;
        }
        $values = [(int) $startValue];
        $pos = 11;

        // 数据码字
        while ($pos < $len) {
            // 检查是否到达停止符
            if ($stopPattern !== '' && substr($bits, $pos, strlen($stopPattern)) === $stopPattern) {
                $pos += strlen($stopPattern);
                break;
            }
            if ($len - $pos < 11) {
                return null;
            }
            $code = substr($bits, $pos, 11);
            $value = array_search($code, $patterns, true);
            if ($value === false) {
                return null;
            }
            $values[] = (int) $value;
            $pos += 11;
        }
        if ($pos !== $len) {
            return null;
        }

        // 校验值 = 末位（停止符前的最后一个码字）
        $checksumGiven = array_pop($values);
        $sum = $values[0]; // 起始符权重 1
        for ($i = 1, $count = count($values); $i < $count; $i++) {
            $sum += $values[$i] * $i;
        }
        if ($checksumGiven !== $sum % 103) {
            return null;
        }

        // 字符集翻译
        $out = '';
        $shiftNext = false;
        for ($i = 1, $count = count($values); $i < $count; $i++) {
            $v = $values[$i];
            if (! $shiftNext && $v === 99) {
                $currentSet = 'C';
                continue;
            }
            if (! $shiftNext && $v === 100) {
                $currentSet = 'B';
                continue;
            }
            if (! $shiftNext && $v === 101) {
                $currentSet = 'A';
                continue;
            }
            if (! $shiftNext && $v === 98) {
                $shiftNext = true; // SHIFT：仅影响下一个码字
                continue;
            }

            $effectiveSet = $shiftNext ? ($currentSet === 'A' ? 'B' : 'A') : $currentSet;
            $shiftNext = false;

            if ($effectiveSet === 'C') {
                if ($v < 0 || $v > 99) {
                    return null;
                }
                $out .= str_pad((string) $v, 2, '0', STR_PAD_LEFT);
                continue;
            }
            if ($effectiveSet === 'A') {
                $ord = $v <= 63 ? $v + 32 : $v - 64; // 0-63 -> 32-95；64-95 -> 0-31
            } else { // B
                $ord = $v + 32;
            }
            if ($ord < 0 || $ord > 127) {
                return null;
            }
            $out .= chr($ord);
        }
        return $out;
    }

    /**
     * 宽/窄两态码制共用的元素解析：把内容运行按行转化为 [宽/窄] 标记序列。
     *
     * @param list<array{0: int, 1: bool}> $content
     * @return list<bool>|null true=宽
     */
    private static function classifyNarrowWide(array $content, float $wideFactor = 1.7): ?array
    {
        $narrow = null;
        foreach ($content as [$len]) {
            $narrow = $narrow === null ? $len : min($narrow, $len);
        }
        if ($narrow === null) {
            return null;
        }
        $result = [];
        foreach ($content as [$len, $dark]) {
            $result[] = $len >= $narrow * $wideFactor;
        }
        return $result;
    }

    /**
     * @param list<array{0: int, 1: bool}> $content
     */
    private static function decodeCode39(array $content): ?string
    {
        $marks = self::classifyNarrowWide($content, 1.7);
        if ($marks === null) {
            return null;
        }
        $encoding = self::tableOf(Code39Generator::class, 'encoding');

        $wideSet = static function (string $pattern): array {
            $set = [];
            for ($i = 0; $i < strlen($pattern); $i++) {
                if ($pattern[$i] === 'w') {
                    $set[] = $i;
                }
            }
            return $set;
        };

        $charByWide = [];
        foreach ($encoding as $char => $pattern) {
            $key = implode(',', $wideSet($pattern));
            $charByWide[$key] = $char;
        }

        $n = count($marks);
        $pos = 0;
        $out = '';
        while ($n - $pos >= 9) {
            $nine = array_slice($marks, $pos, 9);
            $wideIdx = [];
            foreach ($nine as $i => $isWide) {
                if ($isWide) {
                    $wideIdx[] = $i;
                }
            }
            $key = implode(',', $wideIdx);
            if (! isset($charByWide[$key])) {
                return null;
            }
            $out .= $charByWide[$key];
            $pos += 9;

            if ($n - $pos === 0) {
                break;
            }
            // 字符间分隔：1 个窄空
            if ($n - $pos < 1 || $marks[$pos] === true) {
                return null;
            }
            $pos++;
        }
        if ($pos !== $n) {
            return null;
        }

        // 去掉起始/终止符 *
        if (! str_starts_with($out, '*') || ! str_ends_with($out, '*') || substr_count($out, '*') !== 2) {
            return null;
        }
        return substr($out, 1, -1);
    }

    /**
     * @param list<array{0: int, 1: bool}> $content
     */
    private static function decodeCodabar(array $content): ?string
    {
        $marks = self::classifyNarrowWide($content, 1.5); // Codabar 宽/窄比为 2:1
        if ($marks === null) {
            return null;
        }
        $encoding = self::tableOf(CodabarGenerator::class, 'encoding');

        $wideSet = static function (array $pattern): array {
            $set = [];
            foreach ($pattern as $i => $v) {
                if ($v === 2) {
                    $set[] = $i;
                }
            }
            return $set;
        };
        $charByWide = [];
        foreach ($encoding as $char => $pattern) {
            $charByWide[implode(',', $wideSet($pattern))] = $char;
        }

        $n = count($marks);
        $pos = 0;
        $out = '';
        while ($n - $pos >= 7) {
            $seven = array_slice($marks, $pos, 7);
            $wideIdx = [];
            foreach ($seven as $i => $isWide) {
                if ($isWide) {
                    $wideIdx[] = $i;
                }
            }
            $key = implode(',', $wideIdx);
            if (! isset($charByWide[$key])) {
                return null;
            }
            $out .= $charByWide[$key];
            $pos += 7;

            if ($n - $pos === 0) {
                break;
            }
            if ($n - $pos < 1 || $marks[$pos] === true) {
                return null;
            }
            $pos++;
        }
        if ($pos !== $n) {
            return null;
        }

        // Codabar 起止符必须为 A-D
        if (strlen($out) < 2 || ! preg_match('/^[A-D]/', $out[0] ?? '') || ! preg_match('/^[A-D]/', $out[strlen($out) - 1] ?? '')) {
            return null;
        }
        return $out;
    }

    /**
     * ITF-14 解码：起始 4 窄（条-空-条-空）→ 每对数字交错 10 元素 → 停止（宽-窄-窄）。
     *
     * @param list<array{0: int, 1: bool}> $content
     */
    private static function decodeITF(array $content): ?string
    {
        $marks = self::classifyNarrowWide($content, 1.8); // 宽/窄 = 2.5
        if ($marks === null) {
            return null;
        }
        $encoding = self::tableOf(ITF14Generator::class, 'encoding');
        $digitByWide = [];
        foreach ($encoding as $digit => $pattern) {
            $wide = [];
            for ($i = 0; $i < 5; $i++) {
                if ($pattern[$i] === 'w') {
                    $wide[] = $i;
                }
            }
            $digitByWide[implode(',', $wide)] = $digit;
        }

        $n = count($marks);
        // 起始：4 个窄元素
        if ($n < 7) {
            return null;
        }
        for ($i = 0; $i < 4; $i++) {
            if ($marks[$i] === true) {
                return null;
            }
        }
        $pos = 4;
        $out = '';

        while (true) {
            // 停止符：宽条-窄空-窄条（bar, space, bar）
            if ($n - $pos === 3) {
                [$w1, $s2, $w2] = array_slice($marks, $pos, 3);
                if ($w1 !== true || $s2 !== false || $w2 !== false) {
                    return null;
                }
                break;
            }
            if ($n - $pos < 13) { // 停止前至少还有一对(10)+停止(3)
                return null;
            }

            $pair = array_slice($marks, $pos, 10);
            $pos += 10;

            $barWide = [];
            $spaceWide = [];
            foreach ($pair as $i => $isWide) {
                if (! $isWide) {
                    continue;
                }
                if ($i % 2 === 0) {
                    $barWide[] = intdiv($i, 2);
                } else {
                    $spaceWide[] = intdiv($i, 2);
                }
            }
            // 权威 I2of5：每数字恰 2 个宽元素
            if (count($barWide) !== 2 || count($spaceWide) !== 2) {
                return null;
            }
            $d1 = $digitByWide[implode(',', $barWide)] ?? null;
            $d2 = $digitByWide[implode(',', $spaceWide)] ?? null;
            if ($d1 === null || $d2 === null) {
                return null;
            }
            $out .= $d1 . $d2;
        }
        if ($out === '' || strlen($out) % 2 !== 0) {
            return null;
        }
        return $out;
    }

    /* ======================================================================
     * 工具：读取生成器类受保护表（保持测试与编码表实现同步）
     * ==================================================================== */

    /** @var array<string, array> */
    private static array $tableCache = [];

    private static function tableOf(string $class, string $prop): array
    {
        $key = $class . '::' . $prop;
        if (isset(self::$tableCache[$key])) {
            return self::$tableCache[$key];
        }
        $ref = new ReflectionClass($class);
        $obj = $ref->newInstanceWithoutConstructor();
        $property = $ref->getProperty($prop);
        // PHP 8.1+ 反射属性默认即可访问，无需（也不能再）调用 setAccessible
        if (PHP_VERSION_ID < 80100 && method_exists($property, 'setAccessible')) {
            $property->setAccessible(true);
        }
        self::$tableCache[$key] = $property->getValue($obj);
        return self::$tableCache[$key];
    }
}
