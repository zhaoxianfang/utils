<?php

declare(strict_types=1);

namespace zxf\Utils\QrCode;

use Exception;

/**
 * 二维码助手类
 * 提供便捷的静态方法和工具函数
 */
class QrCodeHelper
{
    /** ISO/IEC 18004 容量表 [version][ecLevel][mode] */
    private const CAPACITY_TABLE = [
        1  => ['L'=>['numeric'=>41,'alphanumeric'=>25,'byte'=>17,'kanji'=>10],'M'=>['numeric'=>34,'alphanumeric'=>20,'byte'=>14,'kanji'=>8],'Q'=>['numeric'=>27,'alphanumeric'=>16,'byte'=>11,'kanji'=>7],'H'=>['numeric'=>17,'alphanumeric'=>10,'byte'=>7,'kanji'=>4]],
        2  => ['L'=>['numeric'=>77,'alphanumeric'=>47,'byte'=>32,'kanji'=>20],'M'=>['numeric'=>63,'alphanumeric'=>38,'byte'=>26,'kanji'=>16],'Q'=>['numeric'=>48,'alphanumeric'=>29,'byte'=>20,'kanji'=>12],'H'=>['numeric'=>34,'alphanumeric'=>20,'byte'=>14,'kanji'=>8]],
        3  => ['L'=>['numeric'=>127,'alphanumeric'=>77,'byte'=>53,'kanji'=>32],'M'=>['numeric'=>101,'alphanumeric'=>61,'byte'=>42,'kanji'=>26],'Q'=>['numeric'=>77,'alphanumeric'=>47,'byte'=>32,'kanji'=>20],'H'=>['numeric'=>58,'alphanumeric'=>35,'byte'=>24,'kanji'=>15]],
        4  => ['L'=>['numeric'=>187,'alphanumeric'=>114,'byte'=>78,'kanji'=>48],'M'=>['numeric'=>149,'alphanumeric'=>90,'byte'=>62,'kanji'=>38],'Q'=>['numeric'=>111,'alphanumeric'=>67,'byte'=>46,'kanji'=>28],'H'=>['numeric'=>82,'alphanumeric'=>50,'byte'=>34,'kanji'=>21]],
        5  => ['L'=>['numeric'=>255,'alphanumeric'=>154,'byte'=>106,'kanji'=>65],'M'=>['numeric'=>202,'alphanumeric'=>122,'byte'=>84,'kanji'=>52],'Q'=>['numeric'=>144,'alphanumeric'=>87,'byte'=>60,'kanji'=>37],'H'=>['numeric'=>106,'alphanumeric'=>64,'byte'=>44,'kanji'=>27]],
        6  => ['L'=>['numeric'=>322,'alphanumeric'=>195,'byte'=>134,'kanji'=>82],'M'=>['numeric'=>255,'alphanumeric'=>154,'byte'=>106,'kanji'=>65],'Q'=>['numeric'=>178,'alphanumeric'=>108,'byte'=>74,'kanji'=>45],'H'=>['numeric'=>139,'alphanumeric'=>84,'byte'=>58,'kanji'=>36]],
        7  => ['L'=>['numeric'=>370,'alphanumeric'=>224,'byte'=>154,'kanji'=>95],'M'=>['numeric'=>293,'alphanumeric'=>178,'byte'=>122,'kanji'=>75],'Q'=>['numeric'=>207,'alphanumeric'=>125,'byte'=>86,'kanji'=>53],'H'=>['numeric'=>154,'alphanumeric'=>93,'byte'=>64,'kanji'=>39]],
        8  => ['L'=>['numeric'=>461,'alphanumeric'=>279,'byte'=>192,'kanji'=>118],'M'=>['numeric'=>365,'alphanumeric'=>221,'byte'=>152,'kanji'=>93],'Q'=>['numeric'=>259,'alphanumeric'=>157,'byte'=>108,'kanji'=>66],'H'=>['numeric'=>202,'alphanumeric'=>122,'byte'=>84,'kanji'=>52]],
        9  => ['L'=>['numeric'=>552,'alphanumeric'=>335,'byte'=>230,'kanji'=>141],'M'=>['numeric'=>432,'alphanumeric'=>262,'byte'=>180,'kanji'=>111],'Q'=>['numeric'=>312,'alphanumeric'=>189,'byte'=>130,'kanji'=>80],'H'=>['numeric'=>235,'alphanumeric'=>143,'byte'=>98,'kanji'=>60]],
        10 => ['L'=>['numeric'=>652,'alphanumeric'=>395,'byte'=>271,'kanji'=>167],'M'=>['numeric'=>513,'alphanumeric'=>311,'byte'=>213,'kanji'=>131],'Q'=>['numeric'=>364,'alphanumeric'=>221,'byte'=>151,'kanji'=>93],'H'=>['numeric'=>288,'alphanumeric'=>174,'byte'=>119,'kanji'=>74]],
        11 => ['L'=>['numeric'=>772,'alphanumeric'=>468,'byte'=>321,'kanji'=>198],'M'=>['numeric'=>604,'alphanumeric'=>366,'byte'=>251,'kanji'=>155],'Q'=>['numeric'=>427,'alphanumeric'=>259,'byte'=>177,'kanji'=>109],'H'=>['numeric'=>331,'alphanumeric'=>200,'byte'=>137,'kanji'=>85]],
        12 => ['L'=>['numeric'=>883,'alphanumeric'=>535,'byte'=>367,'kanji'=>226],'M'=>['numeric'=>691,'alphanumeric'=>419,'byte'=>287,'kanji'=>177],'Q'=>['numeric'=>489,'alphanumeric'=>296,'byte'=>203,'kanji'=>125],'H'=>['numeric'=>374,'alphanumeric'=>227,'byte'=>155,'kanji'=>96]],
        13 => ['L'=>['numeric'=>1022,'alphanumeric'=>619,'byte'=>425,'kanji'=>262],'M'=>['numeric'=>796,'alphanumeric'=>483,'byte'=>331,'kanji'=>204],'Q'=>['numeric'=>580,'alphanumeric'=>352,'byte'=>241,'kanji'=>149],'H'=>['numeric'=>427,'alphanumeric'=>259,'byte'=>177,'kanji'=>109]],
        14 => ['L'=>['numeric'=>1101,'alphanumeric'=>667,'byte'=>458,'kanji'=>282],'M'=>['numeric'=>871,'alphanumeric'=>528,'byte'=>362,'kanji'=>223],'Q'=>['numeric'=>621,'alphanumeric'=>376,'byte'=>258,'kanji'=>159],'H'=>['numeric'=>468,'alphanumeric'=>283,'byte'=>194,'kanji'=>120]],
        15 => ['L'=>['numeric'=>1250,'alphanumeric'=>758,'byte'=>520,'kanji'=>320],'M'=>['numeric'=>991,'alphanumeric'=>600,'byte'=>412,'kanji'=>254],'Q'=>['numeric'=>703,'alphanumeric'=>426,'byte'=>292,'kanji'=>180],'H'=>['numeric'=>530,'alphanumeric'=>321,'byte'=>220,'kanji'=>136]],
        16 => ['L'=>['numeric'=>1408,'alphanumeric'=>854,'byte'=>586,'kanji'=>361],'M'=>['numeric'=>1082,'alphanumeric'=>656,'byte'=>450,'kanji'=>277],'Q'=>['numeric'=>775,'alphanumeric'=>470,'byte'=>322,'kanji'=>198],'H'=>['numeric'=>602,'alphanumeric'=>365,'byte'=>250,'kanji'=>154]],
        17 => ['L'=>['numeric'=>1548,'alphanumeric'=>938,'byte'=>644,'kanji'=>397],'M'=>['numeric'=>1212,'alphanumeric'=>734,'byte'=>504,'kanji'=>310],'Q'=>['numeric'=>876,'alphanumeric'=>531,'byte'=>364,'kanji'=>224],'H'=>['numeric'=>674,'alphanumeric'=>408,'byte'=>280,'kanji'=>173]],
        18 => ['L'=>['numeric'=>1725,'alphanumeric'=>1046,'byte'=>718,'kanji'=>442],'M'=>['numeric'=>1346,'alphanumeric'=>816,'byte'=>560,'kanji'=>345],'Q'=>['numeric'=>948,'alphanumeric'=>574,'byte'=>394,'kanji'=>243],'H'=>['numeric'=>746,'alphanumeric'=>452,'byte'=>310,'kanji'=>191]],
        19 => ['L'=>['numeric'=>1903,'alphanumeric'=>1153,'byte'=>792,'kanji'=>488],'M'=>['numeric'=>1500,'alphanumeric'=>909,'byte'=>624,'kanji'=>384],'Q'=>['numeric'=>1063,'alphanumeric'=>644,'byte'=>442,'kanji'=>272],'H'=>['numeric'=>813,'alphanumeric'=>493,'byte'=>338,'kanji'=>208]],
        20 => ['L'=>['numeric'=>2061,'alphanumeric'=>1249,'byte'=>858,'kanji'=>528],'M'=>['numeric'=>1600,'alphanumeric'=>970,'byte'=>666,'kanji'=>410],'Q'=>['numeric'=>1159,'alphanumeric'=>702,'byte'=>482,'kanji'=>297],'H'=>['numeric'=>919,'alphanumeric'=>557,'byte'=>382,'kanji'=>235]],
        21 => ['L'=>['numeric'=>2232,'alphanumeric'=>1352,'byte'=>929,'kanji'=>572],'M'=>['numeric'=>1708,'alphanumeric'=>1035,'byte'=>711,'kanji'=>438],'Q'=>['numeric'=>1224,'alphanumeric'=>742,'byte'=>509,'kanji'=>314],'H'=>['numeric'=>969,'alphanumeric'=>587,'byte'=>403,'kanji'=>248]],
        22 => ['L'=>['numeric'=>2409,'alphanumeric'=>1460,'byte'=>1003,'kanji'=>618],'M'=>['numeric'=>1872,'alphanumeric'=>1134,'byte'=>779,'kanji'=>480],'Q'=>['numeric'=>1358,'alphanumeric'=>823,'byte'=>565,'kanji'=>348],'H'=>['numeric'=>1056,'alphanumeric'=>640,'byte'=>439,'kanji'=>270]],
        23 => ['L'=>['numeric'=>2620,'alphanumeric'=>1588,'byte'=>1091,'kanji'=>672],'M'=>['numeric'=>2059,'alphanumeric'=>1248,'byte'=>857,'kanji'=>528],'Q'=>['numeric'=>1468,'alphanumeric'=>890,'byte'=>611,'kanji'=>376],'H'=>['numeric'=>1108,'alphanumeric'=>672,'byte'=>461,'kanji'=>284]],
        24 => ['L'=>['numeric'=>2812,'alphanumeric'=>1704,'byte'=>1171,'kanji'=>721],'M'=>['numeric'=>2188,'alphanumeric'=>1326,'byte'=>911,'kanji'=>561],'Q'=>['numeric'=>1588,'alphanumeric'=>963,'byte'=>661,'kanji'=>407],'H'=>['numeric'=>1228,'alphanumeric'=>744,'byte'=>511,'kanji'=>315]],
        25 => ['L'=>['numeric'=>3057,'alphanumeric'=>1853,'byte'=>1273,'kanji'=>784],'M'=>['numeric'=>2395,'alphanumeric'=>1451,'byte'=>997,'kanji'=>614],'Q'=>['numeric'=>1718,'alphanumeric'=>1041,'byte'=>715,'kanji'=>440],'H'=>['numeric'=>1286,'alphanumeric'=>779,'byte'=>535,'kanji'=>330]],
        26 => ['L'=>['numeric'=>3283,'alphanumeric'=>1990,'byte'=>1367,'kanji'=>842],'M'=>['numeric'=>2544,'alphanumeric'=>1542,'byte'=>1059,'kanji'=>652],'Q'=>['numeric'=>1804,'alphanumeric'=>1094,'byte'=>751,'kanji'=>462],'H'=>['numeric'=>1425,'alphanumeric'=>864,'byte'=>593,'kanji'=>365]],
        27 => ['L'=>['numeric'=>3517,'alphanumeric'=>2132,'byte'=>1465,'kanji'=>902],'M'=>['numeric'=>2701,'alphanumeric'=>1637,'byte'=>1125,'kanji'=>692],'Q'=>['numeric'=>1933,'alphanumeric'=>1172,'byte'=>805,'kanji'=>496],'H'=>['numeric'=>1501,'alphanumeric'=>910,'byte'=>625,'kanji'=>385]],
        28 => ['L'=>['numeric'=>3669,'alphanumeric'=>2223,'byte'=>1528,'kanji'=>940],'M'=>['numeric'=>2857,'alphanumeric'=>1732,'byte'=>1190,'kanji'=>732],'Q'=>['numeric'=>2085,'alphanumeric'=>1263,'byte'=>868,'kanji'=>534],'H'=>['numeric'=>1581,'alphanumeric'=>958,'byte'=>658,'kanji'=>405]],
        29 => ['L'=>['numeric'=>3909,'alphanumeric'=>2369,'byte'=>1628,'kanji'=>1002],'M'=>['numeric'=>3035,'alphanumeric'=>1839,'byte'=>1264,'kanji'=>778],'Q'=>['numeric'=>2181,'alphanumeric'=>1322,'byte'=>908,'kanji'=>559],'H'=>['numeric'=>1677,'alphanumeric'=>1016,'byte'=>698,'kanji'=>430]],
        30 => ['L'=>['numeric'=>4158,'alphanumeric'=>2520,'byte'=>1732,'kanji'=>1066],'M'=>['numeric'=>3289,'alphanumeric'=>1994,'byte'=>1370,'kanji'=>843],'Q'=>['numeric'=>2358,'alphanumeric'=>1429,'byte'=>982,'kanji'=>604],'H'=>['numeric'=>1782,'alphanumeric'=>1080,'byte'=>742,'kanji'=>457]],
        31 => ['L'=>['numeric'=>4417,'alphanumeric'=>2677,'byte'=>1840,'kanji'=>1132],'M'=>['numeric'=>3486,'alphanumeric'=>2113,'byte'=>1452,'kanji'=>894],'Q'=>['numeric'=>2473,'alphanumeric'=>1499,'byte'=>1030,'kanji'=>634],'H'=>['numeric'=>1897,'alphanumeric'=>1150,'byte'=>790,'kanji'=>486]],
        32 => ['L'=>['numeric'=>4686,'alphanumeric'=>2840,'byte'=>1952,'kanji'=>1201],'M'=>['numeric'=>3693,'alphanumeric'=>2238,'byte'=>1538,'kanji'=>947],'Q'=>['numeric'=>2670,'alphanumeric'=>1618,'byte'=>1112,'kanji'=>684],'H'=>['numeric'=>2022,'alphanumeric'=>1226,'byte'=>842,'kanji'=>518]],
        33 => ['L'=>['numeric'=>4965,'alphanumeric'=>3009,'byte'=>2068,'kanji'=>1273],'M'=>['numeric'=>3909,'alphanumeric'=>2369,'byte'=>1628,'kanji'=>1002],'Q'=>['numeric'=>2805,'alphanumeric'=>1700,'byte'=>1168,'kanji'=>719],'H'=>['numeric'=>2157,'alphanumeric'=>1307,'byte'=>898,'kanji'=>553]],
        34 => ['L'=>['numeric'=>5253,'alphanumeric'=>3183,'byte'=>2188,'kanji'=>1347],'M'=>['numeric'=>4134,'alphanumeric'=>2506,'byte'=>1722,'kanji'=>1060],'Q'=>['numeric'=>2949,'alphanumeric'=>1787,'byte'=>1228,'kanji'=>756],'H'=>['numeric'=>2301,'alphanumeric'=>1394,'byte'=>958,'kanji'=>590]],
        35 => ['L'=>['numeric'=>5529,'alphanumeric'=>3351,'byte'=>2303,'kanji'=>1417],'M'=>['numeric'=>4343,'alphanumeric'=>2632,'byte'=>1809,'kanji'=>1113],'Q'=>['numeric'=>3081,'alphanumeric'=>1867,'byte'=>1283,'kanji'=>790],'H'=>['numeric'=>2361,'alphanumeric'=>1431,'byte'=>983,'kanji'=>605]],
        36 => ['L'=>['numeric'=>5836,'alphanumeric'=>3537,'byte'=>2431,'kanji'=>1496],'M'=>['numeric'=>4588,'alphanumeric'=>2780,'byte'=>1911,'kanji'=>1176],'Q'=>['numeric'=>3244,'alphanumeric'=>1966,'byte'=>1351,'kanji'=>832],'H'=>['numeric'=>2524,'alphanumeric'=>1530,'byte'=>1051,'kanji'=>647]],
        37 => ['L'=>['numeric'=>6153,'alphanumeric'=>3729,'byte'=>2563,'kanji'=>1577],'M'=>['numeric'=>4775,'alphanumeric'=>2894,'byte'=>1989,'kanji'=>1224],'Q'=>['numeric'=>3417,'alphanumeric'=>2071,'byte'=>1423,'kanji'=>876],'H'=>['numeric'=>2625,'alphanumeric'=>1591,'byte'=>1093,'kanji'=>673]],
        38 => ['L'=>['numeric'=>6479,'alphanumeric'=>3927,'byte'=>2699,'kanji'=>1661],'M'=>['numeric'=>5039,'alphanumeric'=>3054,'byte'=>2099,'kanji'=>1292],'Q'=>['numeric'=>3599,'alphanumeric'=>2181,'byte'=>1499,'kanji'=>923],'H'=>['numeric'=>2735,'alphanumeric'=>1658,'byte'=>1139,'kanji'=>701]],
        39 => ['L'=>['numeric'=>6743,'alphanumeric'=>4087,'byte'=>2809,'kanji'=>1729],'M'=>['numeric'=>5313,'alphanumeric'=>3220,'byte'=>2213,'kanji'=>1362],'Q'=>['numeric'=>3791,'alphanumeric'=>2298,'byte'=>1579,'kanji'=>972],'H'=>['numeric'=>2927,'alphanumeric'=>1774,'byte'=>1219,'kanji'=>750]],
        40 => ['L'=>['numeric'=>7089,'alphanumeric'=>4296,'byte'=>2953,'kanji'=>1817],'M'=>['numeric'=>5596,'alphanumeric'=>3391,'byte'=>2331,'kanji'=>1435],'Q'=>['numeric'=>3993,'alphanumeric'=>2420,'byte'=>1663,'kanji'=>1024],'H'=>['numeric'=>3057,'alphanumeric'=>1852,'byte'=>1273,'kanji'=>784]],
    ];

    /**
     * 估算二维码版本（基于ISO/IEC 18004容量表）
     *
     * @param string $data 要编码的数据
     * @param string $ecLevel 错误纠正级别（L, M, Q, H）
     * @return int 版本号（1-40）
     */
    public static function estimateVersion(string $data, string $ecLevel = 'M'): int
    {
        try {
            $mode = self::estimateDataMode($data);
            $length = match ($mode) {
                'numeric' => strlen($data),
                'alphanumeric' => strlen($data),
                'kanji' => self::countKanji($data),
                default => strlen($data),
            };
            $ecLevel = strtoupper($ecLevel);

            for ($v = 1; $v <= 40; $v++) {
                $cap = self::CAPACITY_TABLE[$v][$ecLevel][$mode] ?? 0;
                if ($length <= $cap) {
                    return $v;
                }
            }
            return 40;
        } catch (Exception $e) {
            return 1;
        }
    }

    /**
     * 估算最佳编码模式
     *
     * @param string $data 要编码的数据
     * @return string 模式（numeric/alphanumeric/byte/kanji）
     */
    public static function estimateDataMode(string $data): string
    {
        if (ctype_digit($data)) return 'numeric';
        if (self::isAlphanumeric($data)) return 'alphanumeric';
        if (self::isKanji($data)) return 'kanji';
        return 'byte';
    }

    private static function isAlphanumeric(string $data): bool
    {
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ $%*+-./:';
        for ($i = 0; $i < strlen($data); $i++) {
            if (str_contains($chars, $data[$i]) === false) return false;
        }
        return true;
    }

    private static function isKanji(string $data): bool
    {
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $b = ord($data[$i]);
            if ($i + 1 < $len && (($b >= 0x81 && $b <= 0x9F) || ($b >= 0xE0 && $b <= 0xEA))) {
                $b2 = ord($data[$i + 1]);
                if ($b2 >= 0x40 && $b2 <= 0xFC && $b2 !== 0x7F) {
                    $i++;
                    continue;
                }
            }
            return false;
        }
        return $len > 0;
    }

    private static function countKanji(string $data): int
    {
        $count = 0;
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $b = ord($data[$i]);
            if ($i + 1 < $len && (($b >= 0x81 && $b <= 0x9F) || ($b >= 0xE0 && $b <= 0xEA))) {
                $i++;
            }
            $count++;
        }
        return $count;
    }

    /**
     * 获取最大数据长度
     *
     * @param int $version 版本号
     * @param string $ecLevel 错误纠正级别
     * @param string $mode 编码模式
     * @return int
     */
    public static function getMaxDataLength(int $version, string $ecLevel = 'M', string $mode = 'byte'): int
    {
        return self::CAPACITY_TABLE[$version][strtoupper($ecLevel)][strtolower($mode)] ?? 0;
    }

    /**
     * 获取二维码容量信息（精确查表）
     *
     * @param int $version 版本号（1-40）
     * @param string $ecLevel 错误纠正级别
     * @return array 容量信息
     */
    public static function getCapacity(int $version, string $ecLevel = 'M'): array
    {
        if ($version < 1 || $version > 40) {
            throw new Exception('版本号必须在1-40之间');
        }
        $ecLevel = strtoupper($ecLevel);
        $cap = self::CAPACITY_TABLE[$version][$ecLevel] ?? [];
        return [
            'version' => $version,
            'ecLevel' => $ecLevel,
            'numeric' => $cap['numeric'] ?? 0,
            'alphanumeric' => $cap['alphanumeric'] ?? 0,
            'byte' => $cap['byte'] ?? 0,
            'kanji' => $cap['kanji'] ?? 0,
            'dimensions' => 17 + 4 * $version,
        ];
    }

    /**
     * 快速生成二维码（简化API）
     *
     * @param string $data 二维码内容
     * @param string $outputPath 输出路径
     * @param array $options 配置选项
     * @return bool 是否成功
     */
    public static function quickGenerate(string $data, string $outputPath, array $options = []): bool
    {
        try {
            $qrCode = QrCode::make($data);

            // 应用选项
            foreach ($options as $key => $value) {
                if (method_exists($qrCode, $key)) {
                    $qrCode = $qrCode->$key($value);
                }
            }

            $qrCode->save($outputPath);
            return true;
        } catch (Exception $e) {
            error_log('二维码生成失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 验证二维码内容是否可编码
     *
     * @param string $data 要编码的内容
     * @param string $encoding 编码格式
     * @return array 验证结果
     */
    public static function validateData(string $data, string $encoding = 'UTF-8'): array
    {
        $result = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'info' => []
        ];

        // 检查空内容
        if (empty($data)) {
            $result['valid'] = false;
            $result['errors'][] = '数据内容不能为空';
            return $result;
        }

        // 检查内容长度
        $length = strlen($data);
        $result['info']['length'] = $length;

        if ($length > 2953) {
            $result['warnings'][] = '数据长度较长，可能需要使用较高版本（35-40）的二维码';
        } elseif ($length > 2000) {
            $result['warnings'][] = '数据长度较长，建议使用较高版本（25-34）的二维码';
        }

        // 尝试编码测试
        try {
            @iconv('utf-8', $encoding, $data);
            $result['info']['encoding'] = $encoding;
        } catch (\Exception) {
            $result['valid'] = false;
            $result['errors'][] = '编码格式不支持或转换失败: ' . $encoding;
        }

        // 检查是否包含特殊字符
        if (preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/', $data)) {
            $result['warnings'][] = '数据包含控制字符，可能影响二维码可读性';
        }

        return $result;
    }

    /**
     * 计算推荐的二维码尺寸
     *
     * @param int $version 二维码版本
     * @param int $moduleSize 模块大小（像素）
     * @param int $margin 边距（模块数）
     * @return int 推荐尺寸（像素）
     */
    public static function calculateRecommendedSize(int $version, int $moduleSize = 5, int $margin = 4): int
    {
        $dimension = 17 + 4 * $version;
        return ($dimension + $margin * 2) * $moduleSize;
    }

    /**
     * 生成批量二维码配置
     *
     * @param array $dataList 数据列表
     * @param string $outputDir 输出目录
     * @param array $globalOptions 全局选项
     * @return array 配置信息
     */
    public static function prepareBatch(array $dataList, string $outputDir, array $globalOptions = []): array
    {
        return [
            'dataList' => $dataList,
            'outputDir' => $outputDir,
            'total' => count($dataList),
            'options' => $globalOptions,
            'estimatedFiles' => count($dataList),
            'estimatedSize' => count($dataList) * 10, // 假设每个文件10KB
        ];
    }

    /**
     * 检查环境是否支持生成二维码
     *
     * @return array 环境检查结果
     */
    public static function checkEnvironment(): array
    {
        $result = [
            'passed' => true,
            'checks' => []
        ];

        // 检查GD库
        if (extension_loaded('gd') && function_exists('imagepng')) {
            $result['checks']['gd'] = ['status' => 'ok', 'message' => 'GD库已加载'];
        } else {
            $result['passed'] = false;
            $result['checks']['gd'] = ['status' => 'error', 'message' => 'GD库未安装或不支持PNG'];
        }

        // 检查内存限制
        $memoryLimit = ini_get('memory_limit');
        $result['checks']['memory'] = [
            'status' => 'ok',
            'limit' => $memoryLimit,
            'message' => '内存限制: ' . $memoryLimit
        ];

        // 检查文件权限
        $tempDir = sys_get_temp_dir();
        $isWritable = is_writable($tempDir);
        $result['checks']['tempDir'] = [
            'status' => $isWritable ? 'ok' : 'warning',
            'path' => $tempDir,
            'writable' => $isWritable
        ];

        return $result;
    }

    /**
     * 生成WiFi配置二维码
     *
     * @param string $ssid WiFi名称
     * @param string $password WiFi密码
     * @param string $encryption 加密方式（WPA, WEP, nopass）
     * @param bool $hidden 是否隐藏网络
     * @return string WiFi配置字符串
     */
    public static function generateWifiString(string $ssid, string $password = '', string $encryption = 'WPA', bool $hidden = false): string
    {
        $enc = self::escapeWifi($encryption);
        $s = self::escapeWifi($ssid);
        $p = self::escapeWifi($password);
        $hiddenStr = $hidden ? ';H:true' : '';
        return sprintf('WIFI:T:%s;S:%s;P:%s%s;', $enc, $s, $p, $hiddenStr);
    }

    /**
     * 转义 WIFI 配置中的特殊字符
     *
     * WIFI:T:...; 格式中，分号 `;` 与反斜杠 `\` 是字段/转义分隔符，
     * 冒号 `:`、逗号 `,`、双引号 `"` 也会被部分扫描器当作保留字符。
     * 若不转义，含这些字符的 SSID / 密码会导致手机无法正确解析。
     *
     * @param string $value 原始值
     * @return string 转义后的值
     */
    private static function escapeWifi(string $value): string
    {
        return preg_replace_callback('/([\\\\;,":])/', fn($m) => '\\' . $m[1], $value);
    }

    /**
     * 生成VCard名片字符串
     *
     * @param array $data 名片数据
     * @return string VCard字符串
     */
    public static function generateVCardString(array $data): string
    {
        $vcard = "BEGIN:VCARD\nVERSION:3.0\n";

        if (isset($data['name'])) {
            $vcard .= 'FN:' . self::escapeVCard($data['name']) . "\n";
        }
        if (isset($data['title'])) {
            $vcard .= 'TITLE:' . self::escapeVCard($data['title']) . "\n";
        }
        if (isset($data['phone'])) {
            $vcard .= 'TEL;TYPE=CELL:' . self::escapeVCard($data['phone']) . "\n";
        }
        if (isset($data['email'])) {
            $vcard .= 'EMAIL:' . self::escapeVCard($data['email']) . "\n";
        }
        if (isset($data['url'])) {
            $vcard .= 'URL:' . self::escapeVCard($data['url']) . "\n";
        }
        if (isset($data['address'])) {
            $vcard .= 'ADR:' . self::escapeVCard($data['address']) . "\n";
        }
        if (isset($data['company'])) {
            $vcard .= 'ORG:' . self::escapeVCard($data['company']) . "\n";
        }

        $vcard .= "END:VCARD";
        return $vcard;
    }

    /**
     * 转义 VCard / iCalendar 值中的特殊字符
     *
     * 按 RFC 2426 / RFC 5545：反斜杠必须最先转义，其后转义逗号 `,`、
     * 分号 `;` 与换行，避免字段被错误截断或注入。
     *
     * @param string $value 原始值
     * @return string 转义后的值
     */
    private static function escapeVCard(string $value): string
    {
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace(',', '\,', $value);
        $value = str_replace(';', '\;', $value);
        return str_replace(["\r\n", "\n", "\r"], '\\n', $value);
    }

    /**
     * 生成邮件二维码字符串
     *
     * @param string $email 邮箱地址
     * @param string $subject 邮件主题（可选）
     * @param string $body 邮件正文（可选）
     * @return string 邮件二维码字符串
     */
    public static function generateEmailString(string $email, string $subject = '', string $body = ''): string
    {
        $mailString = 'mailto:' . $email;
        if ($subject !== '' || $body !== '') {
            $params = [];
            if ($subject !== '') {
                $params[] = 'subject=' . rawurlencode($subject);
            }
            if ($body !== '') {
                $params[] = 'body=' . rawurlencode($body);
            }
            $mailString .= '?' . implode('&', $params);
        }
        return $mailString;
    }

    /**
     * 生成短信二维码字符串
     *
     * @param string $phone 电话号码
     * @param string $message 短信内容
     * @return string 短信二维码字符串
     */
    public static function generateSmsString(string $phone, string $message = ''): string
    {
        $smsString = 'smsto:' . $phone;
        if ($message !== '') {
            $smsString .= ':' . rawurlencode($message);
        }
        return $smsString;
    }

    /**
     * 生成电话号码二维码字符串
     *
     * @param string $phone 电话号码
     * @return string 电话二维码字符串
     */
    public static function generatePhoneString(string $phone): string
    {
        return 'tel:' . $phone;
    }

    /**
     * 生成地理位置二维码字符串
     *
     * @param float $latitude 纬度
     * @param float $longitude 经度
     * @param string $label 位置标签（可选）
     * @return string 地理位置二维码字符串
     */
    public static function generateGeoString(float $latitude, float $longitude, string $label = ''): string
    {
        $geoString = sprintf('geo:%.6f,%.6f', $latitude, $longitude);
        if ($label !== '') {
            $geoString .= '?' . rawurlencode($label);
        }
        return $geoString;
    }

    /**
     * 生成日历事件二维码字符串
     *
     * @param string $title 事件标题
     * @param string $start 开始时间（格式：YYYYMMDDTHHMMSS）
     * @param string $end 结束时间（格式：YYYYMMDDTHHMMSS）
     * @param string $location 地点
     * @param string $description 描述
     * @return string 日历事件二维码字符串
     */
    public static function generateEventString(string $title, string $start, string $end = '', string $location = '', string $description = ''): string
    {
        $vevent = "BEGIN:VEVENT\nSUMMARY:" . self::escapeVCard($title) . "\nDTSTART:" . self::escapeVCard($start) . "\n";
        if ($end !== '') {
            $vevent .= "DTEND:" . self::escapeVCard($end) . "\n";
        }
        if ($location !== '') {
            $vevent .= "LOCATION:" . self::escapeVCard($location) . "\n";
        }
        if ($description !== '') {
            $vevent .= "DESCRIPTION:" . self::escapeVCard($description) . "\n";
        }
        $vevent .= "END:VEVENT";
        return $vevent;
    }

    public static function generateMeCardString(array $data): string
    {
        $parts = ['MECARD:'];
        if (!empty($data['name'])) $parts[] = 'N:' . self::escapeMeCard($data['name']) . ';';
        if (!empty($data['phone'])) $parts[] = 'TEL:' . self::escapeMeCard($data['phone']) . ';';
        if (!empty($data['email'])) $parts[] = 'EMAIL:' . self::escapeMeCard($data['email']) . ';';
        if (!empty($data['url'])) $parts[] = 'URL:' . self::escapeMeCard($data['url']) . ';';
        if (!empty($data['address'])) $parts[] = 'ADR:' . self::escapeMeCard($data['address']) . ';';
        if (!empty($data['memo'])) $parts[] = 'MEMO:' . self::escapeMeCard($data['memo']) . ';';
        $parts[] = ';';
        return implode('', $parts);
    }

    /**
     * 转义 MeCard 值中的特殊字符
     *
     * MeCard 以 `;` 分隔字段，值内的反斜杠 `\`、分号 `;`、逗号 `,`
     * 均需转义，否则解析会错位。
     *
     * @param string $value 原始值
     * @return string 转义后的值
     */
    private static function escapeMeCard(string $value): string
    {
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace(';', '\;', $value);
        return str_replace(',', '\,', $value);
    }

    public static function generateFedExString(string $trackingNumber): string
    {
        return 'https://www.fedex.com/apps/fedextrack/?tracknumbers=' . urlencode($trackingNumber);
    }

    public static function generatePayPalString(string $email, float $amount, string $currency = 'USD'): string
    {
        return 'https://www.paypal.com/paypalme/' . urlencode($email) . '/' . $amount . $currency;
    }

    public static function generateWhatsAppString(string $phone, string $message = ''): string
    {
        $url = 'https://wa.me/' . preg_replace('/[^0-9]/', '', $phone);
        if ($message !== '') $url .= '?text=' . urlencode($message);
        return $url;
    }

    public static function generateSkypeString(string $username, string $action = 'chat'): string
    {
        return 'skype:' . urlencode($username) . '?' . $action;
    }

    public static function generateZoomString(string $meetingId, string $password = ''): string
    {
        $url = 'https://zoom.us/j/' . urlencode($meetingId);
        if ($password !== '') $url .= '?pwd=' . urlencode($password);
        return $url;
    }

    public static function generateInstagramString(string $username): string
    {
        return 'https://www.instagram.com/' . urlencode(ltrim($username, '@'));
    }

    public static function generateLinkedInString(string $profileUrl): string
    {
        return 'https://www.linkedin.com/in/' . urlencode(ltrim($profileUrl, '/'));
    }

    public static function generateYouTubeString(string $videoId): string
    {
        return 'https://www.youtube.com/watch?v=' . urlencode($videoId);
    }

    public static function generateAppStoreString(string $appId, string $country = 'us'): string
    {
        return 'https://apps.apple.com/' . strtolower($country) . '/app/id' . urlencode($appId);
    }

    /**
     * 分析二维码数据并返回推荐配置
     *
     * @param string $data 二维码数据
     * @return array 推荐配置
     */
    public static function analyzeData(string $data): array
    {
        $result = [
            'type' => 'text',
            'length' => strlen($data),
            'recommended' => [
                'errorCorrectionLevel' => 'M',
                'size' => 300,
                'margin' => 4,
            ],
        ];

        if (filter_var($data, FILTER_VALIDATE_URL)) $result['type'] = 'url';
        elseif (str_starts_with($data, 'WIFI:')) $result['type'] = 'wifi';
        elseif (str_starts_with($data, 'BEGIN:VCARD')) $result['type'] = 'vcard';
        elseif (str_starts_with($data, 'BEGIN:VEVENT')) $result['type'] = 'event';
        elseif (str_starts_with($data, 'mailto:')) $result['type'] = 'email';
        elseif (str_starts_with($data, 'tel:')) $result['type'] = 'phone';
        elseif (str_starts_with($data, 'smsto:')) $result['type'] = 'sms';
        elseif (preg_match('/^geo:/', $data)) $result['type'] = 'geo';
        elseif (str_starts_with($data, 'MECARD:')) $result['type'] = 'mecard';
        elseif (str_starts_with($data, 'https://wa.me/')) $result['type'] = 'whatsapp';
        elseif (str_starts_with($data, 'skype:')) $result['type'] = 'skype';

        $length = strlen($data);
        if ($length > 1000) {
            $result['recommended']['errorCorrectionLevel'] = 'L';
            $result['recommended']['size'] = 500;
        } elseif ($length > 500) {
            $result['recommended']['errorCorrectionLevel'] = 'M';
            $result['recommended']['size'] = 400;
        }

        return $result;
    }

    /**
     * 生成SVG格式的二维码（真正的矢量生成）
     *
     * @param string $data 二维码数据
     * @param int $size SVG显示尺寸
     * @param array $options 其他选项
     * @return string SVG内容
     */
    public static function generateSvg(string $data, int $size = 300, array $options = []): string
    {
        try {
            $qrCode = QrCode::make($data)->size($size);
            foreach ($options as $key => $value) {
                if (method_exists($qrCode, $key)) {
                    $qrCode = $qrCode->$key($value);
                }
            }

            $matrix = $qrCode->getMatrix();
            $dimension = count($matrix);
            $moduleSize = $size / $dimension;

            $svg = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $svg .= '<svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="' . $size . '" height="' . $size . '" viewBox="0 0 ' . $size . ' ' . $size . '" shape-rendering="crispEdges">' . "\n";
            $svg .= '  <rect width="100%" height="100%" fill="#FFFFFF"/>' . "\n";

            $paths = [];
            for ($y = 0; $y < $dimension; $y++) {
                for ($x = 0; $x < $dimension; $x++) {
                    if (!empty($matrix[$y][$x])) {
                        $px = $x * $moduleSize;
                        $py = $y * $moduleSize;
                        $paths[] = 'M' . $px . ',' . $py . ' h' . $moduleSize . ' v' . $moduleSize . ' h-' . $moduleSize . ' Z';
                    }
                }
            }

            if (!empty($paths)) {
                $svg .= '  <path d="' . implode(' ', $paths) . '" fill="#000000"/>' . "\n";
            }

            $svg .= '</svg>';
            return $svg;
        } catch (\Exception $e) {
            throw new Exception('生成SVG失败: ' . $e->getMessage());
        }
    }
}
