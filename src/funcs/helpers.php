<?php

declare(strict_types=1);

if (! function_exists('uuid')) {
    /**
     * 生成 10 位 Base61 分布式有序 ID
     *
     * 基于「时间戳 + 进程/Worker + 序列号」组合编码为 10 位 Base61 字符串，
     * 具备时间有序、分布式不重复、时钟回拨钳制等特性，适用于分布式主键或追踪 ID。
     *
     * @return string 10 位 Base61 编码的字符串 ID
     * @throws \RuntimeException 系统时间早于 2026-10-25 或时间戳超出编码容量时抛出
     */
    function uuid(): string
    {
        static $chars        = '123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        static $epochMs      = 1_792_886_400_000;           // 2026-10-25 00:00:00 UTC
        static $seqPerMs     = 226_981;                     // 61^3
        static $seqPerWorker = 3_721;                       // 226981 / 61
        static $workers      = 61;
        static $maxCode      = 713_342_911_662_882_600;     // 61^10 - 1
        static $lastMs       = -1;
        static $seq          = -1;
        static $lastPid      = -1;
        static $workerId     = -1;

        $pid = getmypid();
        if ($pid !== $lastPid) {
            // fork 或 pid 变化：完全重置
            $lastMs   = -1;
            $seq      = -1;
            $lastPid  = $pid;
            $workerId = $pid % $workers;   // 0..60
        }

        ['sec' => $sec, 'usec' => $usec] = gettimeofday();
        $nowMs = $sec * 1000 + intdiv($usec, 1000) - $epochMs;

        if ($nowMs < 0) {
            throw new \RuntimeException('系统时间早于 2026-10-25，拒绝生成');
        }
        if ($nowMs < $lastMs) {
            $nowMs = $lastMs;              // 时钟回拨：钳制，不倒退
        }

        if ($nowMs === $lastMs) {
            $seq++;
            if ($seq >= $seqPerWorker) {
                // 本毫秒 seq 耗尽 → 等下一毫秒
                do {
                    usleep(100);
                    ['sec' => $sec, 'usec' => $usec] = gettimeofday();
                    $nowMs = $sec * 1000 + intdiv($usec, 1000) - $epochMs;
                } while ($nowMs <= $lastMs);
                $seq = 0;
            }
        } else {
            $seq = random_int(0, $seqPerWorker - 1);
        }
        $lastMs = $nowMs;

        $globalSeq = $workerId * $seqPerWorker + $seq;
        $code      = $nowMs * $seqPerMs + $globalSeq;

        if ($code < 0 || $code > $maxCode) {
            throw new \RuntimeException('时间戳超出 10 位 Base61 编码容量');
        }

        $out = '1111111111';
        for ($i = 9; $i >= 0; $i--) {
            $q       = intdiv($code, 61);
            $out[$i] = $chars[$code - $q * 61];
            $code    = $q;
        }
        return $out;
    }
}


if (!function_exists('parse_uuid')) {
    /**
     * 将 uuid() 生成的 10 位 Base61 字符串解析为结构化信息
     *
     * 反向解码出生成时间戳、Unix 时间、Worker ID、序列号等字段，
     * 可选 $strictRange 校验是否落在合法时间窗口内。
     *
     * @param string $uuid       10 位 Base61 字符串
     * @param bool   $strictRange 是否校验时间窗口（2026-10-25 ~ 2076-10-25）
     * @return array 包含 uuid、时间、seq、worker_id 等字段的关联数组
     * @throws \InvalidArgumentException UUID 长度/字符非法或超出窗口时抛出
     */
    function parse_uuid(string $uuid, bool $strictRange = false): array
    {
        static $chars        = '123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        static $epochMs      = 1_792_886_400_000;
        static $windowMs     = 1_577_923_200_000;
        static $seqPerMs     = 226_981;
        static $seqPerWorker = 3_721;
        static $lookup       = null;

        $lookup ??= array_flip(str_split($chars));

        if (strlen($uuid) !== 10) {
            throw new \InvalidArgumentException('UUID 必须是 10 位 Base61 字符串');
        }

        $code = 0;
        for ($i = 0; $i < 10; $i++) {
            $c = $uuid[$i];
            if (!isset($lookup[$c])) {
                // ✅ {$var} 花括号，防止全角字符被吞入变量名
                throw new \InvalidArgumentException(
                    "UUID 含非法字符 '{$c}'（位置 {$i}）"
                );
            }
            $code = $code * 61 + $lookup[$c];
        }

        $timeMs = intdiv($code, $seqPerMs);
        $seq    = $code - $timeMs * $seqPerMs;
        $unixMs = $timeMs + $epochMs;
        $inWin  = $timeMs >= 0 && $timeMs < $windowMs;

        if ($strictRange && !$inWin) {
            throw new \InvalidArgumentException(sprintf(
                'UUID 时间 %d ms 超出 2026-10-25 ~ 2076-10-25 窗口', $timeMs
            ));
        }

        $sec = intdiv($unixMs, 1000);
        $ms  = $unixMs % 1000;

        return [
            'uuid'           => $uuid,
            'time_ms'        => $timeMs,
            'unix_ms'        => $unixMs,
            'datetime'       => gmdate('Y-m-d H:i:s', $sec) . sprintf('.%03d UTC', $ms),
            'datetime_local' => date('Y-m-d H:i:s', $sec) . sprintf('.%03d CST', $ms),
            'seq'            => $seq,
            'worker_id'      => intdiv($seq, $seqPerWorker),
            'local_seq'      => $seq % $seqPerWorker,
            'seq_permille'   => intdiv($seq * 1000, $seqPerMs),
            'in_window'      => $inWin,
        ];
    }
}

if (! function_exists('is_crawler')) {
    /**
     * [isCrawler 检测是否为爬虫]
     *
     *
     * @DateTime 2019-12-24
     *
     * @param  bool  $returnName  [是否返回爬虫名称]
     * @param  array  $extendRules  [自定义额外规则：eg: ['Googlebot'=> 'Google Bot'])]
     * @return bool|string [description]
     */
    function is_crawler(bool $returnName = false, array $extendRules = []): bool|string
    {
        $userAgent = is_laravel() ? request()->userAgent() : (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '');
        if (! empty($userAgent)) {
            // 扩展的爬虫标识符列表，包括更多类型的爬虫
            $crawlers = [
                // 主流搜索引擎爬虫
                'Googlebot' => 'Google Bot',
                'Bingbot' => 'Bing Bot',
                'Slurp' => 'Yahoo Slurp',
                'DuckDuckBot' => 'DuckDuckGo Bot',
                'Baiduspider' => 'Baidu Spider',
                'YandexBot' => 'Yandex Bot',
                'Sogou web spider' => 'Sogou Web Spider', // Sogou Web 爬虫
                'Sogou' => 'Sogou Spider',
                'Exabot' => 'ExaBot',
                'ia_archiver' => 'Alexa Bot',
                '360Spider' => '360 Search Bot',
                'SeznamBot' => 'Seznam Bot',
                'YisouSpider' => 'Yisou Spider',
                'Bytespider' => 'Byte Spider',

                // 国际搜索引擎爬虫
                'Yeti' => 'Naver Yeti',
                'Coccocbot' => 'CocCoc Bot',
                'archive.org_bot' => 'Internet Archive Bot',
                'MojeekBot' => 'Mojeek Bot',
                'TroveBot' => 'Trove Bot',

                // 数据抓取和内容分析
                'SemrushBot' => 'SEMrush Bot',
                'AhrefsBot' => 'Ahrefs Bot',
                'ZoominfoBot' => 'Zoominfo Bot',
                'DotBot' => 'Moz DotBot',
                'BLEXBot' => 'BLEXBot',
                'MegaIndex' => 'MegaIndex Crawler',
                'SiteAnalyzer' => 'Site Analyzer Bot',
                'DataForSeoBot' => 'DataForSeo Bot',
                'NetcraftSurveyAgent' => 'Netcraft Survey Agent',

                // API与数据采集工具
                'axios' => 'Axios Client',
                'Scrapy' => 'Scrapy Framework',
                'curl' => 'cURL',
                'wget' => 'Wget',

                // 开发语言脚本判断
                'python-requests|python-urllib|scrapy\/|httpx\/|aiohttp\/|tornado\/|python\/[0-9.]+' => 'Python Script',
                'okhttp\/|apache-httpclient\/|jersey-client\/|unirest-java\/|java\/[0-9.]+' => 'JAVA Script',
                'node-fetch\/|axios\/|superagent\/|got\/|node\.js\/[0-9.]+|needle\/|request-promise\/|request' => 'Node.js Script',
                'httparty\/|rest-client\/|faraday\/|mechanize\/|ruby\/[0-9.]+' => 'Ruby Script',
                'guzzlehttp\/|symfony-httpclient\/|curl-php\/|http-request\/|php\/[0-9.]+' => 'PHP Script',
                'lwp::useragent\/|http-simple\/|libwww-perl\/|perl\/[0-9.]+' => 'Perl Script',
                'go-http-client\/|gorequest\/|resty\/|go\/[0-9.]+' => 'Go Script',
                'reqwest\/|hyper\/|rust\/[0-9.]+' => 'Rust Script',
                'powershell\/|invoke-webrequest|invoke-restmethod' => 'PowerShell Script',
                'alamofire\/|swift\/[0-9.]+' => 'Swift Script',
                'httpoison\/|hackney\/|elixir\/[0-9.]+' => 'Elixir Script',
                'akka-http\/|dispatch\/|scalaj-http\/|scala\/[0-9.]+' => 'Scala Script',
                'http-conduit\/|wreq\/|haskell\/[0-9.]+' => 'Haskell Script',
                'dart-http\/|dart\/[0-9.]+' => 'Dart Script',
                'clj-http\/|http-kit\/|clojure\/[0-9.]+' => 'Clojure Script',
                'R-curl\/|R-httr\/' => 'R Script',
                'lua-http\/|luasocket\/|lua\/[0-9.]+' => 'Lua Script',

                // 常用的开发与调试工具
                'Postman' => 'Postman',
                'Insomnia' => 'Insomnia REST Client',
                'RestSharp' => 'RestSharp',
                'Apipost' => 'Apipost',

                // 通用爬虫标识
                'Spider' => 'Generic Spider',
                'Crawler' => 'Generic Crawler',
                'Bot' => 'Generic Bot',
            ];

            if (! empty($extendRules)) {
                $crawlers = array_merge($crawlers, $extendRules);
            }

            // 使用不区分大小写的正则表达式匹配 User-Agent 中的爬虫关键字
            $pattern = '/'.implode('|', array_keys($crawlers)).'/i';
            preg_match_all($pattern, $userAgent, $matches);

            if (! empty($matches[0])) {
                // 返回第一个匹配的爬虫名称
                $matchedCrawler = $matches[0][0];
                $crawlerName = ! empty($crawlers[$matchedCrawler])
                    ? $crawlers[$matchedCrawler]
                    : (! empty($crawlers[ucfirst($matchedCrawler)])
                        ? $crawlers[ucfirst($matchedCrawler)]
                        : $matchedCrawler
                    );

                // 如果匹配到 "Spider" 、 "Crawler" 和 “Bot”，重新截取出前面的字符串
                if (in_array(strtolower(substr($crawlerName, 0, 7)), ['generic', 'unknown'])) {
                    $suffix = (stripos($matchedCrawler, 'Spider') !== false)
                        ? 'Spider'
                        : (stripos($matchedCrawler, 'Crawler') !== false
                            ? 'Crawler'
                            : (stripos($matchedCrawler, 'Bot') !== false
                                ? 'Bot'
                                : ''
                            )
                        );
                    if (! empty($suffix)) {
                        // 找到 "Spider" 或 "Crawler" 的位置
                        $pattern = '/\s+(\S+)'.$suffix.'/i';

                        if (preg_match($pattern, $userAgent, $subMatches)) {
                            if (! empty($subMatches[0])) {
                                return $returnName ? $subMatches[1].$suffix : true;
                            }
                        }
                    }
                }
                return $returnName ? $crawlerName : true;
            }
        }

        // 没有匹配到任何爬虫
        return $returnName ? '' : false;
    }
}

if (! function_exists('img_to_gray')) {
    /**
     * 图片转灰度 - 增强版
     *
     * 优化内容：
     * 1. 支持更多图片格式
     * 2. 改进错误处理
     * 3. 添加质量参数
     * 4. 支持透明度处理
     *
     * @param string $imgFile 源图片地址
     * @param string $saveFile 生成目标地址
     * @param int $quality 输出质量 (1-100)
     * @return bool
     */
    function img_to_gray(string $imgFile = '', string $saveFile = '', int $quality = 90): bool
    {
        if (! $imgFile || ! file_exists($imgFile)) {
            throw new InvalidArgumentException('图片文件不存在或路径错误');
        }

        // 获取图片信息
        $imageInfo = @getimagesize($imgFile);
        if ($imageInfo === false) {
            throw new RuntimeException('无法读取图片信息');
        }

        $mimeType = $imageInfo['mime'];

        // 根据 MIME 类型创建图片资源
        $image = match ($mimeType) {
            'image/jpeg' => imagecreatefromjpeg($imgFile),
            'image/png' => imagecreatefrompng($imgFile),
            'image/gif' => imagecreatefromgif($imgFile),
            'image/webp' => imagecreatefromwebp($imgFile),
            'image/bmp' => imagecreatefrombmp($imgFile),
            default => throw new RuntimeException("不支持的图片格式: {$mimeType}"),
        };

        if ($image === false) {
            throw new RuntimeException('创建图片资源失败');
        }

        try {
            // 启用透明度处理
            if (in_array($mimeType, ['image/png', 'image/gif', 'image/webp'])) {
                imagealphablending($image, false);
                imagesavealpha($image, true);

                $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
                imagefill($image, 0, 0, $transparent);
            }

            // 应用灰度滤镜
            if (! imagefilter($image, IMG_FILTER_GRAYSCALE)) {
                throw new RuntimeException('应用灰度滤镜失败');
            }

            // 可选：调整亮度
            // imagefilter($image, IMG_FILTER_BRIGHTNESS, -20);

            // 保存图片
            $success = false;
            $quality = max(1, min(100, $quality));

            if ($saveFile) {
                switch (strtolower(pathinfo($saveFile, PATHINFO_EXTENSION))) {
                    case 'jpg':
                    case 'jpeg':
                        $success = imagejpeg($image, $saveFile, $quality);
                        break;
                    case 'png':
                        $success = imagepng($image, $saveFile, (int)($quality / 10));
                        break;
                    case 'gif':
                        $success = imagegif($image, $saveFile);
                        break;
                    case 'webp':
                        $success = imagewebp($image, $saveFile, $quality);
                        break;
                    case 'bmp':
                        $success = imagebmp($image, $saveFile);
                        break;
                    default:
                        // 默认使用原格式
                        $success = match ($mimeType) {
                            'image/jpeg' => imagejpeg($image, $saveFile, $quality),
                            'image/png' => imagepng($image, $saveFile, (int)($quality / 10)),
                            'image/gif' => imagegif($image, $saveFile),
                            'image/webp' => imagewebp($image, $saveFile, $quality),
                            default => imagejpeg($image, $saveFile, $quality),
                        };
                }
            } else {
                // 直接输出到浏览器
                header('Content-Type: ' . $mimeType);
                $success = match ($mimeType) {
                    'image/jpeg' => imagejpeg($image, null, $quality),
                    'image/png' => imagepng($image, null, (int)($quality / 10)),
                    'image/gif' => imagegif($image),
                    'image/webp' => imagewebp($image, null, $quality),
                    default => imagejpeg($image, null, $quality),
                };
            }
            imagedestroy($image);
            return $success;

        } catch (Exception $e) {
            if (isset($image) && is_resource($image)) {
                imagedestroy($image);
            }
            throw $e;
        }
    }
}

if (! function_exists('get_filesize')) {
    /**
     * 获取文件大小 - 增强版
     *
     * @param string $filePath 文件路径
     * @return string 格式化后的大小
     */
    function get_filesize(string $filePath): string
    {
        if (! file_exists($filePath)) {
            throw new InvalidArgumentException("文件不存在: {$filePath}");
        }

        if (! is_file($filePath)) {
            throw new InvalidArgumentException("路径不是文件: {$filePath}");
        }

        $size = stat($filePath)['size'];
        return byteFormat($size);
    }
}

if (! function_exists('byteFormat')) {
    /**
     * 文件字节转具体大小 - 增强版
     *
     * 优化内容：
     * 1. 支持二进制和十进制单位
     * 2. 改进精度控制
     * 3. 添加自定义单位支持
     *
     * @param int $size 文件字节
     * @param int $dec 小数位数
     * @param bool $binary 是否使用二进制单位 (true: KiB, MiB; false: KB, MB)
     * @return string
     */
    function byteFormat(int $size, int $dec = 2, bool $binary = false): string
    {
        if ($size < 0) {
            throw new InvalidArgumentException('文件大小不能为负数');
        }

        if ($size === 0) {
            return '0B';
        }

        $base = $binary ? 1024 : 1000;
        $units = $binary ?
            ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB'] :
            ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];

        $pos = 0;
        $formattedSize = $size;

        while ($formattedSize >= $base && $pos < count($units) - 1) {
            $formattedSize /= $base;
            $pos++;
        }

        return round($formattedSize, $dec) . $units[$pos];
    }
}

if (! function_exists('array_to_tree')) {
    /**
     * 二维数组转树形结构 - 增强版
     *
     * 优化内容：
     * 1. 改进性能
     * 2. 添加回调支持
     * 3. 支持自定义排序
     *
     * @param array $array 二维数组
     * @param int $parentId 父级ID
     * @param string $keyField 主键字段名
     * @param string $pidField 父级字段名
     * @param string $childField 子级字段名
     * @param callable|null $callback 回调函数用于处理每个节点
     * @param string|null $sortField 排序字段
     * @return array
     */
    function array_to_tree(
        array $array,
        int $parentId = 0,
        string $keyField = 'id',
        string $pidField = 'pid',
        string $childField = 'children',
        ?callable $callback = null,
        ?string $sortField = null
    ): array {
        // 构建索引（缺失父级字段时归入根级，避免 "Undefined array key" 警告）
        $index = [];
        foreach ($array as $item) {
            $pid = $item[$pidField] ?? $parentId;
            if (is_scalar($pid) || $pid === null) {
                $index[(string) $pid][] = $item;
            }
        }

        // 递归构建树。
        // $path 记录当前递归链路上已访问过的父级 ID：
        // 数据存在循环引用（A 的父是 B、B 的父是 A）或自引用（id === pid）时，
        // 若不做检测会无限递归直至爆栈，此处直接切断并停止下钻。
        $buildTree = function ($parentId, array $path = []) use (&$buildTree, $index, $keyField, $childField, $callback, $sortField) {
            $key = (string) $parentId;

            if (isset($path[$key])) {
                return [];
            }

            $tree = [];

            if (isset($index[$key])) {
                $children = $index[$key];
                $path[$key] = true;

                // 排序
                if ($sortField && !empty($children)) {
                    usort($children, function ($a, $b) use ($sortField) {
                        return ($a[$sortField] ?? 0) <=> ($b[$sortField] ?? 0);
                    });
                }

                foreach ($children as $child) {
                    $node = $child;
                    if ($callback) {
                        $node = $callback($node);
                    }

                    $nodeChildren = $buildTree($node[$keyField] ?? null, $path);
                    if (!empty($nodeChildren)) {
                        $node[$childField] = $nodeChildren;
                    }

                    $tree[] = $node;
                }
            }

            return $tree;
        };

        return $buildTree($parentId);
    }
}

if (! function_exists('tree_to_array')) {
    /**
     * 树形结构转二维数组 - 增强版
     *
     * @param array $array 树形数据
     * @param string $childField 子级的键名
     * @param int $rootId 根ID的值
     * @param string $keyField 主键字段名
     * @param string $pidField 父级字段名
     * @param callable|null $callback 回调函数
     * @return array
     */
    function tree_to_array(array $array, string $childField = 'children', int $rootId = 0, string $keyField = 'id', string $pidField = 'pid', ?callable $callback = null): array {
        $result = [];

        $flatten = function($node, $parentId, $level = 1, $hasPrev=false, $hasNext=false) use (&$flatten, &$result, $childField, $keyField, $pidField, $callback) {
            // 处理当前节点
            $currentNode = $node;
            $currentNode[$pidField] = $parentId;
            $currentNode['_level'] = $level;
            $currentNode['_has_prev'] = $hasPrev;
            $currentNode['_has_next'] = $hasNext;

            // 确保有主键
            if (!isset($currentNode[$keyField])) {
                $currentNode[$keyField] = uniqid('node_', true);
            }

            // 保存当前ID用于递归
            $currentId = $currentNode[$keyField];

            // 回调处理
            if ($callback) {
                // 回调：遍历的当前数据、是否有前一个数据、是否有后一个数据
                $currentNode = $callback($currentNode);
            }

            // 子级字段可能缺失或不是数组（PHP 8 下 count() 对非 Countable 会抛 TypeError）
            $children = $currentNode[$childField] ?? [];
            $children = is_array($children) ? array_values($children) : [];

            unset($currentNode[$childField]);
            unset($currentNode['_level']);
            unset($currentNode['_has_prev']);
            unset($currentNode['_has_next']);

            $result[] = $currentNode;

            $countChi = count($children);
            // 处理子节点
            foreach ($children as $cIndex => $child) {
                $hasPrev = $cIndex > 0; // 是否有前一个数据
                $hasNext = $cIndex < $countChi - 1; // 是否有后一个数据
                $flatten($child, $currentId, $level + 1,$hasPrev,$hasNext);
            }
        };
        $countArr = count($array);
        foreach ($array as $aIndex => $node) {
            $hasPrev = $aIndex > 0; // 是否有前一个数据
            $hasNext = $aIndex < $countArr - 1; // 是否有后一个数据
            $flatten($node, $rootId,1,$hasPrev,$hasNext);
        }

        return $result;
    }
}

if (! function_exists('show_json')) {
    /**
     * 将任意数据格式化为带样式缩进的 JSON 字符串
     *
     * 兼容数组、对象与 JSON 字符串输入；空数据时返回空字符串。
     *
     * @param mixed $data 待格式化的数据，支持数组、对象或 JSON 字符串
     * @return string 美化后的 JSON 字符串
     */
    function show_json(mixed $data = []): string
    {
        if (empty($data)) {
            return '';
        }
        if (is_string($data)) {
            $data = is_json($data) ? json_decode($data, true) : [];
        }
        $data = is_array($data) ? $data : obj_to_arr($data);

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}

if (! function_exists('string_to_utf8')) {
    /**
     * 将字符串自动转换为 UTF-8 编码
     *
     * 借助 mb_convert_encoding 自动探测源编码并转换为 UTF-8。
     *
     * @param string $str 待转换的字符串
     * @return array|bool|string|null 转换后的字符串（失败返回 false）
     */
    function string_to_utf8(string $str = ''): array|bool|string|null
    {
        return mb_convert_encoding($str, 'UTF-8', 'auto');
    }
}

if (! function_exists('string_to_gbk')) {
    /**
     * 将字符串自动转换为 GBK 编码
     *
     * 借助 mb_convert_encoding 自动探测源编码并转换为 GBK。
     *
     * @param string $str 待转换的字符串
     * @return array|bool|string|null 转换后的字符串（失败返回 false）
     */
    function string_to_gbk(string $str = ''): array|bool|string|null
    {
        return mb_convert_encoding($str, 'GBK', 'auto');
    }
}


if (! function_exists('truncate')) {
    /**
     * 文章去除标签截取文字
     *
     * 优化内容：
     * 1. 改进多字节处理
     * 2. 添加更多编码支持
     * 3. 改进截取算法
     *
     * @param string $string 被截取字符串
     * @param int $start 起始位置
     * @param int $length 长度
     * @param bool $append 是否加省略号
     * @param string $encoding 编码格式
     * @return string
     */
    function truncate(
        string $string,
        int $start = 0,
        int $length = 150,
        bool $append = true,
        string $encoding = 'UTF-8'
    ): string {
        if (empty($string)) {
            return $string;
        }

        $string = detach_html($string);
        $strLen = mb_strlen($string, $encoding);

        if ($start >= $strLen) {
            return '';
        }

        if ($length === 0 || $length >= $strLen - $start) {
            return $string;
        }

        if ($length < 0) {
            $length = $strLen + $length - $start;
            if ($length < 0) {
                $length = 0;
            }
        }

        // 确保起始位置有效
        if ($start < 0) {
            $start = max(0, $strLen + $start);
        }

        if ($start >= $strLen) {
            return '';
        }

        $newStr = mb_substr($string, $start, $length, $encoding);

        // 检查是否需要添加省略号
        $shouldAppend = $append && ($start + $length < $strLen);

        return $newStr . ($shouldAppend ? '...' : '');
    }
}

if (! function_exists('detach_html')) {
    /**
     * 去除所有HTML标签 - 增强版
     *
     * 优化内容：
     * 1. 改进标签移除
     * 2. 保留特定标签内容
     * 3. 改进空白处理
     *
     * @param string $string 输入字符串
     * @param bool $preserveLineBreaks 是否保留换行符
     * @param array $allowedTags 允许保留的标签
     * @return string
     */
    function detach_html(string $string, bool $preserveLineBreaks = false, array $allowedTags = []): string
    {
        // 移除 BOM 字符
        $string = preg_replace('/^\xEF\xBB\xBF/', '', $string);

        // 如果有允许的标签，使用 strip_tags
        if (!empty($allowedTags)) {
            $allowedTagsString = '<' . implode('><', $allowedTags) . '>';
            $string = strip_tags($string, $allowedTagsString);
        }

        // 移除 <script> 和 <style> 标签及其内容
        $output = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', '', $string);

        // 移除 HTML 注释
        $output = preg_replace('#<!--.*?-->#s', '', $output);

        // 移除内嵌 CSS 样式
        $output = preg_replace('#\s*style=["\'][^"\']*["\']#i', '', $output);

        // 移除所有 HTML 标签
        $output = preg_replace('#<[^>]+>#', '', $output);

        // 处理空白字符
        if ($preserveLineBreaks) {
            // 保留换行符
            $output = preg_replace('/[ \t]+/', ' ', $output);
        } else {
            // 移除所有空白字符
            $output = preg_replace('/\s+/', ' ', $output);
        }

        // 替换 HTML 实体
        $output = html_entity_decode($output, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // 去除连续空白符
        $output = preg_replace('/\s{2,}/', ' ', $output);

        return trim($output);
    }
}

if (! function_exists('zxf_substr')) {
    /**
     * 字符串截取
     *
     * 先去除字符串中的全部空格，再按 UTF-8 字符截取（优先 mb_substr，其次 iconv_substr，
     * 均不可用时退化为按字节手工扫描处理多字节字符）。
     *
     * @param  string  $string  待截取的字符串
     * @param  int  $start  起始字符位置（从 0 开始），默认 0
     * @param  int  $length  截取的字符数，默认 5
     * @return string 截取后的字符串（去除了空格）
     */
    function zxf_substr($string, $start = 0, $length = 5): bool|string
    {
        $string = str_ireplace(' ', '', $string); // 去除空格
        if (function_exists('mb_substr')) {
            $newstr = mb_substr($string, $start, $length, 'UTF-8');
        } elseif (function_exists('iconv_substr')) {
            $newstr = iconv_substr($string, $start, $length, 'UTF-8');
        } else {
            $newStrings = [];
            for ($i = 0; $i < $length; $i++) {
                $tempString = substr($string, $start, 1);
                if (ord($tempString) > 127) {
                    $i++;
                    if ($i < $length) {
                        $newStrings[] = substr($string, $start, 3);
                        $string = substr($string, 3);
                    }
                } else {
                    $newStrings[] = substr($string, $start, 1);
                    $string = substr($string, 1);
                }
            }
            $newstr = implode($newStrings);
        }

        return $newstr;
    }
}

if (! function_exists('is_json')) {
    /**
     * [is_json 判断json]
     *
     *
     * @DateTime 2018-12-27
     *
     * @param    [type]       $string [description]
     * @return bool [description]
     */
    function is_json($string): bool
    {
        try {
            $data = json_decode_plus($string);
            if (is_null($data)) {
                return false;
            }
            if ((! empty($data) && is_object($data)) || (is_array($data) && ! empty($data))) {
                return true;
            }
        } catch (Exception $e) {
        }

        return false;
    }
}

if (! function_exists('is_xml')) {
    /**
     * 判断字符串是否为有效的 XML
     *
     * 借助 SimpleXML 加载校验，并禁用外部实体（LIBXML_NONET）以防 XXE 攻击。
     *
     * @param string $string 待校验的 XML 字符串
     * @return bool 有效 XML 返回 true，否则返回 false
     */
    function is_xml(string $string): bool
    {
        try {
            // 尝试加载字符串为 XML
            libxml_use_internal_errors(true); // 启用内部错误处理
            simplexml_load_string($string, 'SimpleXMLElement', LIBXML_NONET);

            // 如果发生解析错误，返回 false
            if (libxml_get_errors()) {
                libxml_clear_errors(); // 清除错误

                return false;
            }

            return true; // 如果没有错误，返回 true
        } catch (Exception $e) {
        }

        return false;
    }
}

if (! function_exists('is_mobile')) {
    /**
     * 判断当前浏览器是否为移动端
     * ------------------------------------------------------------
     * 特性：
     *  - 智能识别 120+ 移动设备关键词
     *  - 自动过滤平板与桌面端 UA
     *  - 支持 HarmonyOS / 鸿蒙 NEXT / Fuchsia / KaiOS
     *  - 高性能静态缓存与分层检测机制
     * ------------------------------------------------------------
     * @param string|null $userAgent
     * @param array|null $serverHeaders
     * @return bool
     */
    function is_mobile(?string $userAgent = null, ?array $serverHeaders = null): bool
    {
        static $cache = null;

        // 静态缓存优化（同一请求周期内命中）
        if ($cache !== null && $userAgent === null && $serverHeaders === null) {
            return $cache;
        }

        $headers = $serverHeaders ?? $_SERVER;
        $ua = strtolower($userAgent ?? ($headers['HTTP_USER_AGENT'] ?? ''));

        // 第一层：Header 快速检测
        if (isset($headers['HTTP_X_WAP_PROFILE']) || isset($headers['HTTP_PROFILE'])) {
            return $cache = true;
        }
        if (isset($headers['HTTP_ACCEPT']) && preg_match('/wap|vnd\.wap\.wml|vnd\.wap\.xhtml/i', $headers['HTTP_ACCEPT'])) {
            return $cache = true;
        }

        // UA 为空视为非移动端
        if ($ua === '') {
            return $cache = false;
        }

        // 第二层：桌面端快速排除（早退机制）
        static $DESKTOP_KEYWORDS = ['windows nt', 'macintosh', 'mac os x', 'x11', 'cros','linux x86_64', 'wow64', 'ubuntu', 'debian', 'fedora', 'gentoo'];
        foreach ($DESKTOP_KEYWORDS as $kw) {
            if (str_contains($ua, $kw)) {
                return $cache = false;
            }
        }

        // 第三层：平板端排除（避免 iPad、Galaxy Tab 误判）
        static $TABLET_KEYWORDS = ['ipad', 'tablet', 'playbook', 'kindle', 'silk', 'nexus 7', 'nexus 9', 'nexus 10','xoom', 'transformer', 'surface', 'mediapad', 'galaxy tab', 'lenovo tab', 'mi pad','redmi pad', 'huawei pad', 'honor pad', 'teclast', 'pocketbook'];
        foreach ($TABLET_KEYWORDS as $kw) {
            if (str_contains($ua, $kw)) {
                return $cache = false;
            }
        }

        // 第四层：移动端关键词匹配（涵盖操作系统 / 品牌 / 浏览器）
        static $MOBILE_KEYWORDS = [
            // 操作系统 / 平台
            'android', 'iphone', 'ipod', 'blackberry', 'bb10', 'symbian', 'meego', 'maemo','mobile', 'harmonyos', 'fuchsia', 'kaios', 'bada', 'tizen', 'palm os', 'webos', 'windows phone',
            // 品牌（含国产全覆盖）
            'huawei', 'honor', 'xiaomi', 'redmi', 'meizu', 'oppo', 'vivo', 'oneplus','lenovo', 'zte', 'nubia', 'coolpad', 'realme', 'tecno', 'itel', 'infinix','samsung', 'sony', 'sharp', 'htc', 'motorola', 'asus', 'nokia', 'google pixel',
            // 浏览器特征（含国内主流）
            'ucbrowser', 'qqbrowser', 'baiduboxapp', 'baidubrowser', 'sogoumobilebrowser','2345browser', 'quark', 'maxthon', 'miuibrowser', 'vivo browser', 'oppobrowser','alohabrowser', 'puffin', 'duckduckgo', 'firefox mobile', 'opera mini','opera mobi', 'mobile safari', 'crios', 'fxios', 'yandexmobile', 'micromessenger',
            // 网络层关键词
            'wap', 'wireless', 'midp', 'pda', 'nexus', 'touch', 'mobi', 'phone'
        ];
        foreach ($MOBILE_KEYWORDS as $kw) {
            if (str_contains($ua, $kw)) {
                return $cache = true;
            }
        }

        // 第五层：智能正则匹配（复杂 UA 模糊识别）
        static $REGEX_MOBILE = '/(android(?!.*(pad|tablet))|iphone|ipod|phone|mobi|wap|wireless|midp|pda)/i';
        if (preg_match($REGEX_MOBILE, $ua)) {
            return $cache = true;
        }

        // 第六层：智能修正（处理安卓浏览器伪装成桌面 UA）
        if (str_contains($ua, 'linux') && (str_contains($ua, 'android') || str_contains($ua, 'harmonyos')) && !str_contains($ua, 'x11') ) {
            return $cache = true;
        }

        // 第七层：额外补充（低端机或代理标识）
        if (isset($headers['HTTP_UA_CPU']) && str_contains(strtolower($headers['HTTP_UA_CPU']), 'arm')) {
            return $cache = true;
        }
        return $cache = false;
    }
}

if (! function_exists('parse_json')) {
    /**
     * 解析json字符串、json 数组和数组 返回数据，其他的返回false
     *
     * 数组直接返回；字符串按首字符区分路径：`{`/`[` 走 json_decode（对象还支持把单引号
     * 替换为双引号后重试），其他按 Base64 解码后再尝试 JSON 解析。所有失败分支返回 false。
     *
     * @param  mixed  $data  待解析的数据（数组 / JSON 字符串 / Base64 编码的 JSON 字符串）
     * @return array|false 解析得到的数组，无法解析时返回 false
     */
    function parse_json(mixed $data): array|false
    {
        // [性能] 数组直接返回 - O(1)
        if (is_array($data)) {
            return $data;
        }
        
        // [性能] 非字符串直接失败 - 避免不必要的处理
        if (!is_string($data)) {
            return false;
        }
        
        // [性能] 单次trim，无多余操作
        $json = trim($data);
        
        // [性能] 空字符串快速失败
        if ($json === '') {
            return false;
        }
        
        // [性能] 快速检查首字符 - 单字符操作，最快判断
        $firstChar = $json[0];
        
        // [性能] 标准JSON路径 - 覆盖95%场景
        if ($firstChar === '{' || $firstChar === '[') {
            $decoded = json_decode($json, true);
            
            // [性能] 单次错误检查
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
            
            // [性能] 只需要处理单引号这一个最常见变体
            if ($firstChar === '{' && str_contains($json, "'")) {
                $decoded = json_decode(str_replace("'", '"', $json), true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return $decoded;
                }
            }
            
            return false;
        }
        
        // [性能] Base64快速检测 - 正则匹配最快方式
        if (strlen($json) % 4 === 0 && preg_match('/^[A-Za-z0-9+\/]+=*$/', $json)) {
            $decoded = base64_decode($json, true);
            if ($decoded !== false) {
                $decoded = json_decode(trim($decoded), true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return $decoded;
                }
            }
        }
        
        return false;
    }
}

if (! function_exists('get_raw_input')) {
    /**
     * 获取原始请求内容
     *
     * @param  bool  $returnOriginal  是否返回原始数据；默认为 true；
     *                                true:返回原始数据
     *                                false:返回解析后的数据；
     * @param  bool  $getDataType  是否获取数据类型；默认为 false；
     *                             true:返回数据类型；
     *                             false:只返回请求数据；
     * @return array|string|null 原始内容 / 解析后的数组 / 数据类型；无请求体时为 null
     */
    function get_raw_input(bool $returnOriginal = true, bool $getDataType = false): array|string|null
    {
        // 获取原始数据
        return \zxf\Utils\Http\Request::instance()->getRawInput($returnOriginal, $getDataType);
    }
}


if (! function_exists('convert_underline')) {
    /**
     * 下划线转驼峰
     *
     * 把 `_x` 或 `-x` 形式的连接符与其后的单个字母转换为大写字母（如 user_name → userName），
     * 大小写不敏感匹配，不支持数字开头的情况。
     *
     * @param  string  $str  待转换的字符串
     * @return string 转换后的字符串
     */
    function convert_underline(string $str): array|string|null
    {
        return preg_replace_callback('/([-_]+([a-z]{1}))/i', function ($matches) {
            return strtoupper($matches[2]);
        }, $str);
    }
}

if (! function_exists('underline_convert')) {
    /**
     * 驼峰转下划线
     *
     * 在小写字母后紧跟的大写字母前插入下划线并整体转小写（如 userName → user_name）；
     * 连续大写或开头大写（如 IDCard）不会被拆分为词边界。
     *
     * @param  string  $str  待转换的字符串
     * @return string 转换后的下划线风格字符串
     */
    function underline_convert(string $str): string
    {
        return strtolower(preg_replace('/(?<=[a-z])([A-Z])/', '_$1', $str));
    }
}


if (! function_exists('buildRequestFormAndSend')) {
    /**
     * 构建form表单并提交数据
     * 满足提交大量表单会被数据长度等限制的问题
     * [header 携带大量数据请求的可行性方案]
     *
     * @param  string  $url  数据提交跳转到的URL
     * @param  array  $data  需要提交的数组,支持多维 (按照数组的键值对组装form表单数据)
     * @param  string  $method  提交方式 支持 post|get|put|delete
     * @return string 组装提交表单的HTML文本
     *
     * @throws Exception
     */
    function buildRequestFormAndSend(string $url, array $data = [], string $method = 'post'): string
    {
        $method = $method ? strtolower($method) : 'post';
        $methodIsMorph = in_array($method, ['put', 'delete']) ? strtoupper($method) : ''; // 变形
        $method = in_array($method, ['put', 'delete', 'post']) ? 'post' : 'get';

        $data = obj_to_arr($data);
        $method = strtolower($method) == 'post' ? 'POST' : 'GET';
        $formId = 'requestForm_'.time().'_'.random_int(2383280, 14776335);
        // 对输出做 HTML 转义，防止 action / 字段名 / 字段值中的特殊字符造成属性逃逸与反射型 XSS
        $action = htmlspecialchars($url, ENT_QUOTES);
        $methodAttr = htmlspecialchars($method, ENT_QUOTES);
        $html = "<form id='".htmlspecialchars($formId, ENT_QUOTES)."' action='".$action."' method='".$methodAttr."'>";
        $html .= ! empty($methodIsMorph) ? "<input type='hidden' name='_method' value='".htmlspecialchars($methodIsMorph, ENT_QUOTES)."' />" : '';
        // 遍历子数组（使用闭包，避免每次调用都重复声明全局函数导致重声明致命错误）
        $traverseChildArr = function (array $arr, string $namePrefix = ''): string {
            $arr = obj_to_arr($arr);
            $htmlStr = '';
            foreach ($arr as $key => $item) {
                $name = empty($namePrefix) ? (string) $key : $namePrefix.'['.(string) $key.']';
                $htmlStr .= is_array($item)
                    ? $traverseChildArr($item, $name)
                    : "<input type='hidden' name='".htmlspecialchars($name, ENT_QUOTES)."' value='".htmlspecialchars((string) $item, ENT_QUOTES)."' />";
            }

            return $htmlStr;
        };

        $html .= $traverseChildArr($data, '');
        $html .= "<input type='submit' value='确定' style='display:none;'></form>";
        $html .= "<script>document.forms['".htmlspecialchars($formId, ENT_QUOTES)."'].submit();</script>";

        return $html;
    }
}


if (! function_exists('obj_to_arr')) {
    /**
     * 对象转数组
     *
     * 递归处理：对象先强转为数组（仅一层可见属性），数组的每个值继续递归转换，
     * 其它类型原样返回。
     *
     * @param  mixed  $array  待转换的对象 / 数组 / 标量
     * @return mixed 转换后的数组或原始值
     */
    function obj_to_arr($array): mixed
    {
        if (is_object($array)) {
            $array = (array) $array;
        }
        if (is_array($array)) {
            foreach ($array as $key => $value) {
                $array[$key] = obj_to_arr($value);
            }
        }

        return $array;
    }
}


if (! function_exists('str_en_code')) {
    /**
     * 字符串加密和解密
     *
     * @param  string  $string  字符串
     * @param  string  $operation  de(DECODE)表示解密，en(ENCODE)表示加密；
     * @param  int|string  $expiry  缓存生命周期 0表示永久缓存 默认99年
     *                              支持格式:
     *                              int 缓存多少秒，例如 90 表示缓存90秒，如果小于等于0，则用0替换
     *                              string: 时间字符串格式,例如:+1 day、2023-01-01 09:00:02 等 strtotime 支持的格式均可
     * @param  string  $key  加密密钥（必填，禁止为空；该算法为遗留 XOR 流密码，无完整性保护，敏感数据请改用 Crypto\AES）
     * @return false|string
     */
    function str_en_code(string $string, string $operation = 'en', int|string $expiry = 312206400, string $key = ''): bool|string
    {
        $operation = in_array($operation, ['de', 'DECODE']) ? 'DECODE' : 'ENCODE';
        // 转换字符串
        $string = $operation == 'DECODE' ? str_replace(['_'], ['/'], $string) : $string;
        // 动态密匙长度，相同的明文会生成不同密文就是依靠动态密匙
        $ckey_length = 4;
        // 密匙（禁止硬编码默认密钥，避免“无密钥”等价于明文）
        if ($key === '') {
            throw new InvalidArgumentException('加密密钥不能为空，必须显式传入');
        }
        $key = md5($key);
        // 密匙a会参与加解密
        $keya = md5(substr($key, 0, 16));
        // 密匙b会用来做数据完整性验证
        $keyb = md5(substr($key, 16, 16));
        // 密匙c用于变化生成的密文
        $keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length) : substr(md5(microtime()), -$ckey_length)) : '';
        // 参与运算的密匙
        $cryptkey = $keya.md5($keya.$keyc);
        $key_length = strlen($cryptkey);
        // 明文，前10位用来保存时间戳，解密时验证数据有效性，10到26位用来保存$keyb(密匙b)，
        // 解密时会通过这个密匙验证数据完整性
        // 如果是解码的话，会从第$ckey_length位开始，因为密文前$ckey_length位保存 动态密匙，以保证解密正确
        // 过期时间：数值表示「相对当前的有效秒数」，0 表示永不过期；字符串按日期解析为绝对时间戳
        $expiry = (is_numeric($expiry) || empty($expiry))
            ? ((int) $expiry === 0 ? 0 : time() + (int) $expiry)
            : strtotime($expiry);
        $string = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) : sprintf('%010d', $expiry).substr(md5($string.$keyb), 0, 16).$string;
        $string_length = strlen($string);
        $result = '';
        $box = range(0, 255);
        $rndkey = [];
        // 产生密匙簿
        for ($i = 0; $i <= 255; $i++) {
            $rndkey[$i] = ord($cryptkey[$i % $key_length]);
        }
        // 用固定的算法，打乱密匙簿，增加随机性，好像很复杂，实际上对并不会增加密文的强度
        for ($j = $i = 0; $i < 256; $i++) {
            $j = ($j + $box[$i] + $rndkey[$i]) % 256;
            $tmp = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }
        // 核心加解密部分
        for ($a = $j = $i = 0; $i < $string_length; $i++) {
            $a = ($a + 1) % 256;
            $j = ($j + $box[$a]) % 256;
            $tmp = $box[$a];
            $box[$a] = $box[$j];
            $box[$j] = $tmp;
            // 从密匙簿得出密匙进行异或，再转成字符
            $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
        }
        if ($operation == 'DECODE') {
            // 验证数据有效性，请看未加密明文的格式
            $validUntil = (int) substr($result, 0, 10);
            if (($validUntil === 0 || $validUntil - time() > 0)
                && substr($result, 10, 16) === substr(md5(substr($result, 26) . $keyb), 0, 16)) {
                return substr($result, 26);
            } else {
                return '';
            }
        } else {
            // 把动态密匙保存在密文里，这也是为什么同样的明文，生产不同密文后能解密的原因
            // 因为加密后的密文可能是一些特殊字符，复制过程可能会丢失，所以用base64编码
            $result = $keyc.str_replace('=', '', base64_encode($result));

            // 转换字符串
            return str_replace(['/', '='], ['_', ''], $result);
        }
    }
}

if (! function_exists('get_protected_value')) {
    /**
     * 打印对象里面受保护属性的值
     *
     * 通过把对象强转为数组后读取 `"\0*\0属性名"` 键，绕过可见性限制取得 protected 属性；
     * 属性不存在时会触发未定义索引警告并返回 null，调用前请自行确认属性存在。
     *
     * @param  object  $obj  目标对象
     * @param  string  $name  受保护属性名（不含前缀）
     * @return mixed 属性当前值
     */
    function get_protected_value($obj, $name): mixed
    {
        $array = (array) $obj;
        $prefix = chr(0).'*'.chr(0);

        return $array[$prefix.$name];
    }
}

if (! function_exists('set_protected_value')) {
    /**
     * 使用反射 修改对象里面受保护属性的值
     *
     * 先尝试按静态属性写入，失败后回退为实例属性写入（两者都会绕过可见性限制）。
     *
     * @param  object  $obj  目标对象
     * @param  string  $filed  属性名
     * @param  mixed  $value  要写入的值
     * @return void 无返回值
     *
     * @throws ReflectionException 属性不存在或无法反射时抛出
     */
    function set_protected_value($obj, $filed, $value): void
    {
        $reflectionClass = new ReflectionClass($obj);
        try {
            $reflectionClass->setStaticPropertyValue($filed, $value);
        } catch (Exception $e) {
            $reflectionProperty = $reflectionClass->getProperty($filed);
            $reflectionProperty->setValue($obj, $value);
        }
    }
}

if (! function_exists('json_decode_plus')) {
    /**
     * 增强型 JSON 解码 - 增强版
     *
     * 优化内容：
     * 1. 改进错误处理
     * 2. 支持更多修复场景
     * 3. 添加深度限制
     *
     * @param mixed $json JSON字符串
     * @param bool $assoc 是否返回关联数组
     * @param int $depth 最大递归深度
     * @param int $flags json_decode 标志
     * @return mixed
     */
    function json_decode_plus(mixed $json, bool $assoc = true, int $depth = 512, int $flags = JSON_THROW_ON_ERROR): mixed
    {
        if (empty($json)) {
            return $assoc ? [] : null;
        }

        if (is_array($json) || (is_object($json) && !$json instanceof JsonSerializable)) {
            return $json;
        }

        $jsonString = (string)$json;

        // 预处理: 去除BOM头
        $jsonString = preg_replace('/^\xEF\xBB\xBF/', '', $jsonString);

        // 检查字符串长度
        if (strlen($jsonString) > 1000000) { // 1MB限制
            throw new RuntimeException('JSON字符串过长');
        }

        try {
            return json_decode($jsonString, $assoc, $depth, $flags);
        } catch (Exception $e) {
            // 尝试修复常见的JSON格式问题
            $cleaned = repairJson($jsonString);

            try {
                return json_decode($cleaned, $assoc, $depth, $flags);
            } catch (Exception $e) {
                if ($flags & JSON_THROW_ON_ERROR) {
                    throw $e;
                }

                return $assoc ? [] : null;
            }
        }
    }

    /**
     * 修复常见的JSON格式问题
     *
     * 依次处理：剔除控制字符、单引号转双引号、裸换行转义、删除数组/对象末尾多余逗号、
     * 为未加引号的键名补引号。仅做字符级修补，不校验修复结果是否合法。
     *
     * @param  string  $json  待修复的 JSON 字符串
     * @return string 修复后的字符串
     */
    function repairJson(string $json): string
    {
        // 修复未转义的控制字符
        $json = preg_replace('/[\x00-\x1F\x7F]/', '', $json);

        // 修复单引号
        $json = str_replace("'", '"', $json);

        // 修复未转义的换行
        $json = str_replace(["\r", "\n"], ['\r', '\n'], $json);

        // 修复多余的逗号
        $json = preg_replace('/,\s*([}\]])/', '$1', $json);

        // 修复缺失的引号
        return preg_replace('/([{,]\s*)([a-zA-Z_][a-zA-Z0-9_]*)(\s*:)/', '$1"$2"$3', $json);
    }
}


if (! function_exists('url_conversion')) {
    /**
     * 把 ./ 和 ../ 开头的资源地址转换为绝对地址
     *
     * 入参本身是 `/`、`./`、`../` 开头时直接走 url_conversion_to_prefix_path()；
     * 否则按 $linkAttr 找出 HTML 中引号包裹的相对地址并批量替换，避免同一路径被重复替换。
     * $string 或 $prefixString 为空时原样返回。
     *
     * @param  string  $string  需要转换的字符串
     * @param  string  $prefixString  拼接的前缀字符
     * @param  array  $linkAttr  需要转换的标签属性，例如：href、src、durl
     * @return string 转换后的字符串
     */
    function url_conversion(string $string = '', string $prefixString = '', array $linkAttr = ['href', 'src']): string
    {
        if (empty($string) || empty($prefixString)) {
            return $string;
        }
        // 判断$string是否是 / 、./ 或者 ../ 开头的url字符串
        if (mb_substr($string, 0, 1, 'utf-8') == '/' || mb_substr($string, 0, 2, 'utf-8') == './' || mb_substr($string, 0, 3, 'utf-8') == '../') {
            return url_conversion_to_prefix_path($string, $prefixString);
        }
        $linkAttrString = implode('|', $linkAttr); // 数组转为字符串 用 (竖线)`|` 分割，例如：href|src|durl
        // 正则查找 $linkAttr 属性中 以 ./、../、/ 和文件夹名称开头的图片或超链接的相对路径 URL 地址字符串,要求src、href等前面至少带一个空格，避免操作 src 和 oldsrc 都识别到src的情况
        // $pattern = '/\s+(href|src)\s*=\s*"(?:\.\/|\.\.|\/)?([^"|^\']+)"/';
        $pattern = '/\s+('.$linkAttrString.')\s*=\s*"(?:\.\/|\.\.|\/)?([^"|^\']+)"/';
        preg_match_all($pattern, $string, $matches);

        $relativeURLs = $matches[0];
        $originalPath = []; // 原始的相对路径数组
        $replacePath = []; // 替换成的前缀路径数组
        $plusReplacePath = []; // 加强版替换路径数组
        foreach ($relativeURLs as $findStr) {
            // 删除 $findStr 字符串中的 href= 或者 src= durl= 字符串
            $findStr = preg_replace('/\s+('.$linkAttrString.')\s*=\s*["\']/i', '', $findStr);
            $originalPath[] = $findStr;
            $replacePath[] = url_conversion_to_prefix_path($findStr, $prefixString);
        }
        if (! empty($originalPath) && ! empty($replacePath)) {
            // 批量替换地址;直接在此处替换会导致 出现相同的'link'字符串时候会被替换多次，导致出现错误的结果
            // $string = str_replace($originalPath, $replacePath, $string);

            // 加强版开始开始表演：找出 'link' 相关字符串的前缀(例如src、href等)最为批量替换的前缀，防止被多次替换
            // 强化前缀字符串
            $strengthenAttr = $matches[1];
            foreach ($originalPath as $index => $item) {
                // 判断最后一个引号是单引号还是双引号
                $lastQuotationMark = substr($relativeURLs[$index], -1);
                // 把替换结果拼上 $linkAttr 对应的前缀，例如 ` src="` 或者 ` href="等
                $plusReplacePath[$index] = ' '.$strengthenAttr[$index].'='.$lastQuotationMark.$replacePath[$index];
            }
            // 批量替换地址
            $string = str_replace($relativeURLs, $plusReplacePath, $string);
        }

        return $string;
    }
}


if (! function_exists('get_http_headers')) {
    /**
     * 获取当前请求的所有 HTTP 头（键统一为大写，便于大小写不敏感访问）
     *
     * `getallheaders()` 只在 Apache / FPM 等 SAPI 下存在，CLI 与部分 CGI 环境
     * 调用会直接致命；此处做降级：从 `$_SERVER` 的 `HTTP_*` 变量重建。
     *
     * @return array<string, string> 请求头数组，键形如 'X-QQ-FROM'
     */
    function get_http_headers(): array
    {
        if (function_exists('getallheaders')) {
            $headers = @getallheaders();
            if (is_array($headers)) {
                return array_change_key_case($headers, CASE_UPPER);
            }
        }

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[str_replace('_', '-', substr($key, 5))] = (string) $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $headers[str_replace('_', '-', $key)] = (string) $value;
            }
        }

        return $headers;
    }
}

if (! function_exists('is_qq_browser')) {
    /**
     * 判断来源是否为QQ浏览器
     *
     * @return bool true|false
     */
    function is_qq_browser(): bool
    {
        $headers = get_http_headers();
        $http_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        return str_contains($http_user_agent, 'MQQBrowser')
            || str_contains($http_user_agent, 'QQ')
            || isset($headers['X-QQ-FROM']);
    }
}

if (! function_exists('is_wechat_browser')) {
    /**
     * 判断来源是否为微信浏览器
     *
     * @return bool true|false
     */
    function is_wechat_browser(): bool
    {
        $headers = get_http_headers();
        $http_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        return str_contains($http_user_agent, 'MicroMessenger')
            || str_contains($http_user_agent, 'WeChat')
            || isset($headers['X-WEIXIN-FROM']);
    }
}

if (! function_exists('is_weibo_browser')) {
    /**
     * 判断来源是否为微博浏览器
     *
     * @return bool true|false
     */
    function is_weibo_browser(): bool
    {
        $headers = get_http_headers();
        $http_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        return str_contains($http_user_agent, 'Weibo') || isset($headers['X-WEIBO-FROM']);
    }
}

if (! function_exists('is_alipay_browser')) {
    /**
     * 判断来源是否为支付宝浏览器
     *
     * @return bool true|false
     */
    function is_alipay_browser(): bool
    {
        $headers = get_http_headers();
        $http_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // 注意：此前此处误判了 X-WEIBO-FROM（复制粘贴遗留），
        // 会导致微博来源被同时判成支付宝来源
        return str_contains($http_user_agent, 'AlipayClient')
            || str_contains($http_user_agent, 'Alipay')
            || isset($headers['X-ALIPAY-FROM']);
    }
}

if (! function_exists('is_string_value_array')) {
    /**
     * 检查是否为['字符串键名'=>'不是数组也不是对象格式类型的值']格式的数组
     *      eg:['name'=>'foo']:true
     *         ['name'=>['foo']]:false
     *         [['name','foo']]:false
     *         ['name'=>new stdClass()]:false
     *
     * 判定条件：不是顺序列表（array_is_list 为 false）且所有值均为标量（is_scalar）。
     *
     * @param  array  $array  待检查的数组
     * @return bool 满足「关联数组 + 全部标量值」返回 true，否则返回 false
     */
    function is_string_value_array(array $array): bool
    {
        return ! array_is_list($array) && array_reduce($array, fn ($carry, $value) => $carry && is_scalar($value), true);
    }
}


if (! function_exists('json_string_to_array')) {
    /**
     * 判断字符串是否为 JSON 并返回解析后的数组
     *
     * 数组原样返回；JSON 字符串成功解码为数组则返回，否则回退返回原字符串。
     *
     * @param mixed $string 待判断的字符串（或数组）
     * @return mixed 解析后的数组或原字符串
     */
    function json_string_to_array($string)
    {
        if (is_array($string)) {
            return $string;
        }
        $string = (empty($string) || !is_string($string))?'':$string;
        $data = json_decode($string, true);
        if (json_last_error() == JSON_ERROR_NONE) {
            return $data;
        }

        return $string;
    }
}

if (! function_exists('json_array_to_string')) {
    /**
     * 将数组转换为 JSON 字符串
     *
     * 非数组数据原样返回，便于在不确定入参类型时统一序列化。
     *
     * @param mixed $array 待转换的数组（或其他类型）
     * @return mixed 序列化后的 JSON 字符串或原数据
     */
    function json_array_to_string($array)
    {
        return is_array($array) ? json_encode($array) : $array;
    }
}

if (! function_exists('before_calling_methods')) {
    /**
     * 核心功能
     *  class 类在调用方法之前，先执行指定的初始化方法$method,并解析和传入$method方法中的依赖关系参数
     *
     * 适用场景：
     *   在路由调用控制器方法之前，先执行 initialize 方法，并传入依赖关系参数，需要在构造函数中调用本方法
     *
     * 示例说明（以下代码为示范用法，并非本文件中的真实函数）：
     *   示例类 WebBaseController 的 __construct() 负责在构造阶段调用本函数完成注册；
     *   initialize() 是构造函数中指定、由本函数自动执行的初始化方法，用于演示参数依赖注入；
     *   test() 是借助 $args 传入普通参数（如 $name/$age）的普通方法示例。
     *
     *      eg:
     *          class WebBaseController
     *              public function __construct(Request $request)
     *              {
     *                  parent::__construct($request);
     *                  // 路由执行被调用方法之前，先执行 initialize 方法
     *                  before_calling_methods($this, 'initialize');
     *                  // 路由执行被调用方法之前，先执行 test 方法 ,且传入参数
     *                  before_calling_methods($this, 'test',[ $name='张三',$age = 18]);
     *               }
     *
     *               public function initialize(Request $request,...其他的自定义依赖注入)
     *
     *               public function test(...自定义依赖注入或者不传入参数)
     *          }
     *
     * @param  object  $class  类对象 eg: $this、MyClass、MyController
     * @param  string  $method  方法名称 默认为 initialize
     * @param  array  ...$args  可以给被调用函数传参； eg:[ $name='张三',$age = 18], 数组中参数下标N对应被调用函数的第N个参数
     *
     * @throws Exception
     */
    function before_calling_methods(object $class, string $method = 'initialize', array ...$args): void
    {
        try {
            // 判断 $class 是不是一个class 或者 $method 是不是一个方法
            if (! is_object($class) || ! method_exists($class, $method)) {
                return;
            }
            // 1、获取 $class 中 $method 方法的依赖关系(参数列表)

            // 使用反射获取方法信息
            $reflectionMethod = new \ReflectionMethod($class, $method);

            // 获取$args的参数
            $paramsArgs = ! empty($args) ? reset($args) : [];
            $index = -1;

            // 获取参数类型名，形成数组返回
            $dependencies = array_map(function ($parameter) use (&$index, $paramsArgs) {
                $index++;
                $paramName = $parameter->getType()?->getName(); // 参数类型名, eg: int、string、array
                // 类 或者 函数
                if (! empty($paramName) && (class_exists($paramName) || is_callable($paramName))) {
                    return $paramName;
                }
                $argIndex = $index + 1;
                // 有传入值就使用传入值
                if (! empty($paramsArgs[$index])) {
                    // 没有定义参数类型 || 参数类型不匹配
                    if (empty($paramName) || (call_user_func('is_'.$paramName, $paramsArgs[$index]))) {
                        return $paramsArgs[$index];
                    }
                    throw new Exception("第{$argIndex}个参数的类型不是指定的「{$paramName}」类型");
                }
                // 检查是否有默认值
                if ($parameter->isDefaultValueAvailable()) {
                    // 有默认值直接返回默认值
                    return $parameter->getDefaultValue();
                }
                // 没有默认参数的普通参数
                throw new Exception("第{$argIndex}个参数「\${$parameter->getName()}」不能为空");
            }, $reflectionMethod->getParameters());

            // 2、 解析依赖注入对象
            $resolvedDependencies = array_map(function ($parameter) {
                // 如果参数是类名，则尝试解析依赖注入
                if (is_string($parameter) && class_exists($parameter)) {
                    // 如果是 Laravel 则使用 app 函数实例化，否则直接 new 一个类
                    return (function_exists('is_laravel') && is_laravel()) ? \app($parameter) : new $parameter;
                }

                return $parameter;
            }, $dependencies);

            // 3、 通过反射 $method 方法并传入解析后的依赖注入对象或普通参数
            $reflectionMethod->invokeArgs($class, $resolvedDependencies);
        } catch (ReflectionException $e) {
            return;
        }
    }
}

if (! function_exists('class_basename')) {
    /**
     * 获取类名
     *
     * 把命名空间分隔符统一为 `/` 后取最后一段，并去掉可能存在的 `.php` 后缀，
     * 因此同时适用于「完整类名」与「类文件路径」两种入参。
     *
     * @param  string  $className  类名 eg: \Test\Abc, get_class($this) 等
     * @return string 不含命名空间的短类名
     */
    function class_basename(string $className): string
    {
        // 使用 DIRECTORY_SEPARATOR 确保跨平台兼容性
        $fullClassName = str_replace('/', '\\', $className); // 确保类名中的分隔符统一为反斜线

        // 使用 basename 函数提取路径的最后一部分，相当于提取类名
        return basename(str_replace('\\', '/', $className), '.php');
    }
}


if (! function_exists('relative_path')) {
    /**
     * 获取文件相对于项目根目录的相对路径
     *
     * 以本文件所在目录为基准，截取到 `vendor` 之前的路径作为项目根目录；
     * 文件真实路径位于根目录内时返回去掉该前缀（并去掉开头的 `public/`）的相对路径，
     * 否则原样返回真实路径。注意：realpath() 失败时返回 false，会参与后续字符串比较。
     *
     * @param  string  $filePath  文件路径（相对或绝对）
     * @return string 相对项目根目录的路径或原始真实路径
     */
    function relative_path(string $filePath): string
    {
        // 相对路径
        $dir = dirname(__DIR__);
        $vendorPos = strpos($dir, 'vendor');
        $prefixPath = $vendorPos === false ? '' : substr($dir, 0, $vendorPos);
        $realPath = realpath($filePath); // 获取真实路径

        // realpath() 失败（文件不存在等）时返回 false，直接参与字符串函数会触发 TypeError
        if ($realPath === false) {
            return $filePath;
        }

        if ($prefixPath === '') {
            return $realPath;
        }

        return str_starts_with($realPath, $prefixPath) ? ltrim(substr($realPath, strlen($prefixPath)), 'public/') : $realPath;
    }
}

if (!function_exists('stream_output')) {

    /**
     * 流式输出数据，不用等待操作结束才打印数据，支持 CLI 和 Web 环境实时显示
     *
     * 本函数通过闭包回调提供流式输出能力，自动适配 CLI 和 Web 环境，
     * 支持普通文本、带样式的信息（info/error/warning/success）、多参数混合输出、数组/对象自动格式化。
     *
     * 使用示例：
     *
     * 1. 基本字符串输出
     * ```php
     * stream_output(function ($next) {
     *     $next('输出普通文本');
     *     sleep(1); // 延时等待
     *     $next('处理完成');
     * });
     * ```
     *
     * 2. 带类型的消息输出
     * ```php
     * stream_output(function ($next) {
     *     $next->info('这是一条信息'); // 提示级别输出
     *     $next->error('出错了'); // 错误级别输出
     *     $next->warning('警告'); // 警告级别输出
     *     $next->success('成功'); // 成功级别输出
     * });
     * ```
     *
     * 3. 多参数输出
     * ```php
     * stream_output(function ($next) {
     *     $next('用户名:', 'admin', 'ID:', 1001);
     *     $next->info('详细数据:', ['role' => 'admin', 'status' => 'active']);
     * });
     * ```
     *
     * 4. 数组和对象输出
     * ```php
     * stream_output(function ($next) {
     *     $user = new stdClass();
     *     $user->name = '张三';
     *     $user->hobbies = ['篮球', '音乐'];
     *     $next('用户对象:', $user);
     *
     *     $config = ['db' => ['host' => 'localhost', 'port' => 3306]];
     *     $next->info('配置数组:', $config);
     * });
     * ```
     *
     * 5. 在循环中逐步输出（实时进度）
     * ```php
     * stream_output(function ($next) {
     *     for ($i = 1; $i <= 5; $i++) {
     *         $next("步骤 $i 进行中...");
     *         usleep(500000); // 模拟耗时
     *         $next->success("步骤 $i 完成");
     *     }
     * });
     * ```
     *
     * 6. 混合输出不同类型
     * ```php
     * stream_output(function ($next) {
     *     $next('字符串:', 'Hello');
     *     $next('数字:', 123.45);
     *     $next('布尔:', true);
     *     $next('空值:', null);
     *     $next('数组:', [1, 2, 3]);
     * });
     * ```
     *
     * 7. 手动刷新缓冲区
     * ```php
     * stream_output(function ($next) {
     *     $next('部分输出');
     *     $next->flush(); // 强制立即输出
     *     // 继续其他操作...
     * });
     * ```
     *
     * 8. 异常处理（异常会被捕获并输出，然后重新抛出）
     * ```php
     * try {
     *     stream_output(function ($next) {
     *         $next('开始');
     *         throw new RuntimeException('模拟错误');
     *     });
     * } catch (Throwable $e) {
     *     // 额外处理
     * }
     * ```
     *
     * 9. 仅检查初始化状态
     * ```php
     * if (stream_output(function(){}, true)) {
     *     // 已经初始化过
     * }
     * ```
     *
     * 10. 在未初始化时安全调用（使用 nullsafe 操作符）
     *     示例类 Test 的 demo() 仅用于演示：stream_print() 在未初始化时返回 null，`?->` 会安全跳过；
     *     待 stream_output() 初始化后再调用，则正常输出内容。
     * ```php
     * class Test {
     *     public function demo() {
     *         // stream_print() 是 stream_output 闭包函数的 $next 实现；
     *         // 未初始化时返回 null，?-> 安全跳过；初始化后正常输出
     *         stream_print()?->info('Run Test demo');
     *     }
     * }
     *
     * $test = new Test();
     * $test->demo(); // 未初始化，无输出且无错误
     *
     * stream_output(function ($next) {
     *     $t = new Test();
     *     $t->demo(); // 已初始化，输出 'Run Test demo'
     * });
     * ```
     *
     * @param Closure $callback 回调函数，接收一个 $next 对象（由 stream_print() 返回）。
     *                           该对象可像函数一样调用（直接输出），也提供 info/error/warning/success 等带颜色/样式的方法。
     * @param bool $checkOnly 仅检查是否已初始化，为 true 时忽略 $callback，直接返回初始化状态。
     * @return bool 正常执行返回 true；$checkOnly 时返回静态初始化标记（未初始化返回 false）。
     * @throws Throwable 回调中抛出的异常会被捕获输出后重新抛出。
     */
    function stream_output(Closure $callback, bool $checkOnly = false): bool
    {
        static $initialized = false;          // 标记环境是否已初始化
        $isCli = PHP_SAPI === 'cli';           // 判断运行环境

        // 仅用于检查状态，不执行任何初始化或回调
        if ($checkOnly) {
            return $initialized;
        }

        // 首次调用时进行环境初始化
        if (!$initialized) {
            $initialized = true;

            if ($isCli) {
                // CLI 环境：注册信号处理（只执行一次）
                stream_cli_check();
            } else {
                // Web 环境：取消执行时间限制，忽略用户中断
                ini_set('max_execution_time', '0');
                set_time_limit(0);
                ignore_user_abort(true);

                // 如果 HTTP 头尚未发送，则设置适当的响应头和禁用缓冲
                if (!headers_sent()) {
                    header('Content-Type: text/html; charset=UTF-8');
                    header('Cache-Control: no-cache, no-store, must-revalidate');
                    header('Pragma: no-cache');
                    header('Expires: 0');
                    header('Connection: keep-alive');
                    header('X-Accel-Buffering: no');   // 禁用 Nginx 缓冲

                    if (function_exists('apache_setenv')) {
                        @apache_setenv('no-gzip', '1'); // 禁用 Apache 压缩
                    }
                }
            }

            // 清理所有输出缓冲区，启用隐式刷新
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            ob_implicit_flush(true);
        }

        // 获取打印处理器对象（必须在此之后调用，因为此时 $initialized 为 true）
        $next = stream_print();

        // 正常情况下不会为 null，但为严谨做防御判断
        if ($next === null) {
            // throw new RuntimeException('stream_print 返回 null，可能未正确初始化');
            return false;
        }

        try {
            // 执行用户自定义的回调
            $callback($next);
            // 最终刷新，确保所有数据输出
            $next->flush();
        } catch (Throwable $e) {
            // 异常处理：格式化错误消息并根据环境输出
            $message = $next::formatData($e->getMessage());
            if ($isCli) {
                echo "错误: " . $message . PHP_EOL;
            } else {
                echo '<span style="color: #FF3300; font-weight: bold;">错误: ' .
                    htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8') . '</span><br>';
            }
            $next->flush();
            // 判断 report 函数是否存在
            if (function_exists('report')) {
                report($e);
            } else {
                throw $e;   // 重新抛出异常，让上层处理
            }
        }
        return true;
    }

    /**
     * 获取流式打印处理器对象：stream_print() 是 stream_output 闭包函数的 $next 实现；
     *
     * 该函数必须在 stream_output() 初始化后调用，否则返回 null。
     * 返回的对象同时实现了 __invoke 和 __call，可灵活输出带颜色/样式的信息。
     * 结合 nullsafe 操作符 (?->) 可在未初始化时安全调用而不报错。
     *
     * stream_print('into');
     * stream_print()?->info('Run Test demo');
     *
     * @return callable|null 返回一个匿名类实例（实现了 __invoke），若未初始化则返回 null。
     */
    function stream_print(): callable|null
    {
        // 检查 stream_output 是否已初始化（通过 $checkOnly 模式）
        try {
            $checkFun = stream_output(static function (): void {}, true);
            if (is_bool($checkFun) && !$checkFun) {
                // 未初始化，返回 null 允许 nullsafe 操作符安全跳过
                return null;
            }
        } catch (Throwable $e) {
            // 极少数异常情况，返回 null 保证调用者安全
            // 可根据需要记录错误日志
            // error_log('stream_print check failed: ' . $e->getMessage());
            return null;
        }

        $isCli = PHP_SAPI === 'cli';
        // 再次调用信号检查（内部有静态标记，只会实际注册一次）
        stream_cli_check();

        // 返回匿名类实例，封装所有输出逻辑
        return new class($isCli)
        {
            private bool $isCli;               // 运行环境标识
            private bool $supportsColors;       // 当前终端是否支持颜色
            private string $lineBreak;          // 换行符（CLI: PHP_EOL; Web: <br>）

            // 预定义颜色映射，每种类型在 CLI 和 Web 下分别定义样式
            private const COLOR_MAP = [
                'info'    => ['cli' => "\033[36m", 'browser' => '#0099CC'], // 信息（青色）
                'error'   => ['cli' => "\033[31m", 'browser' => '#FF3300'], // 错误（红色）
                'warning' => ['cli' => "\033[33m", 'browser' => '#FF9900'], // 警告（橙色）
                'success' => ['cli' => "\033[32m", 'browser' => '#009900'], // 成功（绿色）
                'default' => ['cli' => "\033[37m", 'browser' => '#666666']  // 默认（灰色）
            ];

            /**
             * 构造函数：根据运行环境初始化颜色与换行符
             *
             * @param bool $isCli 是否为 CLI 环境（决定换行符与颜色支持）
             */
            public function __construct(bool $isCli)
            {
                $this->isCli = $isCli;
                $this->lineBreak = $isCli ? PHP_EOL : '<br>';
                $this->supportsColors = $this->checkColorSupport();
            }

            /**
             * 直接调用对象时输出数据（无样式或默认样式）
             *
             * @param mixed ...$data 任意数量的输出项（字符串、数组、对象等）
             */
            public function __invoke(...$data): void
            {
                $this->output($data);
            }

            /**
             * 通过方法名指定输出类型（info/error/warning/success）
             *
             * @param string $name 方法名（对应类型）
             * @param array $args  输出项列表
             */
            public function __call(string $name, array $args): void
            {
                $this->output($args, $name);
            }

            /**
             * 强制刷新所有输出缓冲区
             *
             * 循环刷新直到所有缓冲区清空，确保数据立即发送。
             * CLI 环境下加入微延迟以降低 CPU 占用。
             */
            public function flush(): void
            {
                // 刷新所有 PHP 输出缓冲区
                while (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();

                if ($this->isCli && function_exists('usleep')) {
                    usleep(1000); // 1ms 延迟，平衡输出与 CPU
                }
            }

            /**
             * 统一输出处理核心方法
             *
             * @param array $items 要输出的数据项列表
             * @param string|null $type 输出类型（用于颜色映射），null 表示默认样式
             */
            private function output(array $items, ?string $type = null): void
            {
                if (empty($items)) {
                    $this->flush();
                    return;
                }

                $color = self::COLOR_MAP[$type] ?? self::COLOR_MAP['default'];

                foreach ($items as $item) {
                    $formatted = self::formatData($item);

                    if ($this->isCli) {
                        // CLI 输出：支持颜色则添加 ANSI 转义码
                        $output = $this->supportsColors ? $color['cli'] . $formatted . "\033[0m" : $formatted;
                        echo $output . $this->lineBreak;
                    } else {
                        // Web 输出：使用 span 标签和内联样式，保留空格和换行
                        $safe = htmlspecialchars($formatted, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
                        echo '<span style="color: ' . $color['browser'] . '; font-weight: bold; white-space: pre-wrap;">'
                            . $safe . '</span>' . $this->lineBreak;
                    }
                }

                $this->flush();
            }

            /**
             * 将任意数据格式化为可读字符串（静态方法，可在外部调用）
             *
             * 增强功能：当 json_encode 失败时（例如对象包含循环引用），自动降级为 print_r 输出。
             *
             * @param mixed $data 要格式化的数据
             * @return string 格式化后的字符串
             */
            public static function formatData(mixed $data): string
            {
                return match (true) {
                    is_string($data) => $data,
                    is_scalar($data) || is_null($data) => var_export($data, true),
                    is_array($data) || is_object($data) => self::formatComplexData($data),
                    default => '不支持的数据类型: ' . gettype($data),
                };
            }

            /**
             * 格式化数组或对象，优先使用 JSON，失败时使用 print_r
             *
             * @param array|object $data
             * @return string
             */
            private static function formatComplexData(array|object $data): string
            {
                $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                if ($json !== false) {
                    return $json;
                }

                // JSON 编码失败（如循环引用），使用 print_r 并转换为字符串
                $result = print_r($data, true);
                // 在 CLI 环境下直接返回，在 Web 下后续会经过 htmlspecialchars，安全
                return $result;
            }

            /**
             * 检测当前 CLI 终端是否支持 ANSI 颜色
             *
             * 同时检查输出是否被重定向（非终端），若重定向则禁用颜色。
             *
             * @return bool true 支持颜色，false 不支持
             */
            private function checkColorSupport(): bool
            {
                if (!$this->isCli) {
                    return false;
                }

                // 检查输出是否被重定向到非终端（文件、管道等）
                if (function_exists('posix_isatty') && !@posix_isatty(STDOUT)) {
                    return false;
                }

                // Windows 环境下的特殊检测
                if (DIRECTORY_SEPARATOR === '\\') {
                    return getenv('ANSICON') !== false
                        || getenv('ConEmuANSI') === 'ON'
                        || getenv('TERM') === 'xterm'
                        || (function_exists('sapi_windows_vt100_support') && @sapi_windows_vt100_support(STDOUT));
                }

                // Unix/Linux 环境：终端名称包含颜色关键词
                $term = getenv('TERM');
                return $term !== false
                    && (stripos($term, 'color') !== false
                        || stripos($term, 'xterm') !== false
                        || stripos($term, 'vt100') !== false);
            }
        };
    }
    /**
     * CLI 环境信号处理（仅注册一次）
     *
     * 启用异步信号并注册 SIGTERM 和 SIGINT 处理函数，保证进程被终止时输出提示信息。
     */
    function stream_cli_check(): void
    {
        static $registered = false;   // 标记是否已注册过
        if ($registered) {
            return;
        }
        $registered = true;

        if (PHP_SAPI !== 'cli') {
            return;
        }

        if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            // 处理终止信号（kill 或 Ctrl+C）
            $handler = static function (): void {
                echo "\n进程被终止。\n";
                exit;
            };
            pcntl_signal(SIGTERM, $handler);
            pcntl_signal(SIGINT, $handler);
        }
    }
}

if (! function_exists('is_resource_file')) {
    /**
     * 判断是否是资源文件[文件后缀判断]
     *
     * 解析 URL 的路径部分并取其扩展名：布尔模式下按内置后缀表判断，
     * 无扩展名时退化为识别 `captcha/`、`tn_code/` 这类验证码路由；传入数组时仅比对自定义后缀。
     *
     * @param  string  $url  待判断的 URL 或路径
     * @param  bool|array  $simpleOrCustomExt  仅判断简单的几种资源文件
     *                                         true(默认): 仅判断简单的几种资源文件
     *                                         false: 会判断大部分的资源文件
     *                                         array: 仅判断自定义的这些后缀
     * @return bool 是资源文件返回 true，否则返回 false（URL 无路径部分时同样返回 false）
     */
    function is_resource_file(string $url, bool|array $simpleOrCustomExt = true): bool
    {
        // 解析 URL
        $path = parse_url($url, PHP_URL_PATH);
        if (empty($path)){
            return false;
        }
        // 获取文件扩展名
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        // bool: 使用预定义的后缀和特殊规则进行判断
        if (is_bool($simpleOrCustomExt)) {
            // 是否简单判断
            $resourceExtList = $simpleOrCustomExt
                ? ['js', 'css', 'ico', 'ttf', 'jpg', 'jpeg', 'png', 'webp']
                : [
                    'js', 'css', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'ico', 'webp', 'ttf', 'woff', 'woff2',
                    'eot', 'otf', 'mp3', 'mp4', 'wav', 'wma', 'wmv', 'avi', 'mpg', 'mpeg', 'rm', 'rmvb', 'flv',
                    'swf', 'mkv', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip',
                    'rar', '7z', 'tar', 'gz', 'bz2', 'tgz', 'tbz', 'tbz2', 'tb2', 't7z', 'jar', 'war', 'ear', 'zipx',
                    'apk', 'ipa', 'exe', 'dmg', 'pkg', 'deb', 'rpm', 'msi', 'md', 'txt', 'log',
                ];
            if (! empty($ext)) {
                // 检查扩展名是否属于资源文件类型
                return in_array(strtolower($ext), $resourceExtList);
            }

            // 或者一些特殊路由前缀资源：captcha/: 验证码；tn_code/: 滑动验证码
            return str_starts_with(trim($path, '/'), 'captcha/') || str_starts_with(trim($path, '/'), 'tn_code/');
        }

        // array: 全部采用自定义传入的扩展名进行判断
        // 传值不为空?检查扩展名是否属于资源文件类型:false
        return ! empty($ext) && in_array(strtolower($ext), $simpleOrCustomExt);
    }
}

if (! function_exists('array_get')) {
    /**
     * 从数组中进行复杂查询模式查找值
     *
     * @param  array  $array  要查询的数组
     * @param  string  $path  查询路径，支持以下形式：
     *                        - 基本查询: a.b.c                                                    =>  通过点分隔符访问嵌套数组
     *                        - 通配符: *.name, a.*.c                                              => 匹配当前层级所有元素的指定字段
     *                        - 多级通配符: **.name, users.**.number (跨多级匹配)                     => 归匹配所有层级中的指定字段
     *                        - 数组索引: a[0], a.b[1].name                                         => 访问数组指定索引的元素
     *                        - 数组切片: a[start:length], a[1:3]                                   => 从start开始取length个元素
     *                        - 属性存在检查: a.?b                                                   => 检查属性(字段b)是否存在，返回布尔值
     *                        - 正则匹配: a./^name/, *.user./^email_/                               => 使用正则表达式匹配键名,匹配 name开头 或者 email_ 开头
     *                        - 条件查询: a.{id>100}, users.{name=Alice}, user_list.*.{age>20}.name => 筛选满足条件的元素,用户年龄大于20的 名称
     *                        - 数字键: users.1.name                                                => users 下下标为 1 的用户的名称
     * @param  mixed  $default  默认值，当路径不存在时返回
     * @param  string  $delimiter  路径分隔符，默认为点(.)
     *
     * @return mixed 查询到的值或默认值
     *
     * @throws InvalidArgumentException 当输入参数无效时抛出
     * @throws RuntimeException 当查询语法错误时抛出
     */
    function array_get(array $array, string $path, mixed $default = null, string $delimiter = '.'): mixed
    {
        if ($path === '') {
            return $default;
        }

        if (str_contains($delimiter, '*') || str_contains($delimiter, '?') ||
            str_contains($delimiter, '{') || str_contains($delimiter, '}')) {
            throw new InvalidArgumentException('Delimiter cannot contain special characters');
        }

        $parts = explode($delimiter, $path);
        $result = $array;

        foreach ($parts as $i => $part) {
            if ($part === '') {
                continue;
            }

            // 处理条件查询 {field>value}
            if (preg_match('/^{(.*?)(>=|<=|!=|=|>|<)(.*?)}$/', $part, $conditionMatches)) {
                if (! is_array($result)) {
                    return $default;
                }

                $field = trim($conditionMatches[1]);
                $operator = $conditionMatches[2];
                $value = trim($conditionMatches[3]);

                $filteredResults = [];
                foreach ($result as $item) {
                    if (! is_array($item) || ! array_key_exists($field, $item)) {
                        continue;
                    }

                    $itemValue = $item[$field];
                    $match = false;

                    switch ($operator) {
                        case '>':
                            $match = $itemValue > $value;
                            break;
                        case '>=':
                            $match = $itemValue >= $value;
                            break;
                        case '<':
                            $match = $itemValue < $value;
                            break;
                        case '<=':
                            $match = $itemValue <= $value;
                            break;
                        case '=':
                            $match = $itemValue == $value;
                            break;
                        case '!=':
                            $match = $itemValue != $value;
                            break;
                    }

                    if ($match) {
                        $remainingPath = implode($delimiter, array_slice($parts, $i + 1));
                        if ($remainingPath !== '') {
                            $filteredValue = array_get($item, $remainingPath, $default, $delimiter);
                            if ($filteredValue !== $default) {
                                $filteredResults[] = $filteredValue;
                            }
                        } else {
                            $filteredResults[] = $item;
                        }
                    }
                }

                return empty($filteredResults) ? $default :
                    (count($filteredResults) === 1 ? $filteredResults[0] : $filteredResults);
            }

            // 处理属性存在检查 (?)
            if (str_starts_with($part, '?')) {
                $key = substr($part, 1);

                return is_array($result) && (array_key_exists($key, $result) || in_array($key, $result, true));
            }

            // 处理数组索引或切片 [n], [n:length]
            if (preg_match('/^(.+?)(\[(-?\d+)(?::(\d+)?)?\])$/', $part, $matches)) {
                $part = $matches[1];
                $sliceStart = $matches[3] !== '' ? (int) $matches[3] : null;
                $sliceLength = $matches[4] ?? null;

                if (! is_array($result) || ! array_key_exists($part, $result)) {
                    return $default;
                }

                $arrayToSlice = $result[$part];
                if (! is_array($arrayToSlice)) {
                    return $default;
                }

                // 处理 [n] 情况，默认取1个元素
                if ($sliceLength === null && $sliceStart !== null) {
                    return $arrayToSlice[$sliceStart] ?? $default;
                }

                $sliceStart = $sliceStart ?? 0;
                $sliceLength = $sliceLength !== null ? (int) $sliceLength : null;

                $result = array_slice($arrayToSlice, $sliceStart, $sliceLength, true);

                continue;
            }

            // 处理多级通配符 (**)
            if ($part === '**') {
                if (! is_array($result)) {
                    return $default;
                }

                $remainingPath = implode($delimiter, array_slice($parts, $i + 1));
                if ($remainingPath === '') {
                    return $result;
                }

                $values = [];
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveArrayIterator($result),
                    RecursiveIteratorIterator::SELF_FIRST
                );

                foreach ($iterator as $key => $value) {
                    if ($iterator->getDepth() > 0 && is_array($value)) {
                        $nestedValue = array_get($value, $remainingPath, $default, $delimiter);
                        if ($nestedValue !== $default) {
                            if (is_array($nestedValue)) {
                                $values = array_merge($values, $nestedValue);
                            } else {
                                $values[] = $nestedValue;
                            }
                        }
                    }
                }

                return empty($values) ? $default : (count($values) === 1 ? $values[0] : $values);
            }

            // 处理通配符 (*)
            if ($part === '*') {
                if (! is_array($result)) {
                    return $default;
                }

                $remainingPath = implode($delimiter, array_slice($parts, $i + 1));
                if ($remainingPath === '') {
                    return array_values($result);
                }

                $values = [];
                foreach ($result as $value) {
                    if (is_array($value)) {
                        $nestedValue = array_get($value, $remainingPath, $default, $delimiter);
                        if ($nestedValue !== $default) {
                            if (is_array($nestedValue)) {
                                $values = array_merge($values, $nestedValue);
                            } else {
                                $values[] = $nestedValue;
                            }
                        }
                    }
                }

                return empty($values) ? $default : (count($values) === 1 ? $values[0] : $values);
            }

            // 处理正则表达式匹配 (/pattern/)
            if (preg_match('/^\/(.+?)\/$/', $part, $regexMatches)) {
                if (! is_array($result)) {
                    return $default;
                }

                $pattern = $regexMatches[1];
                $matchedValues = [];

                foreach ($result as $key => $value) {
                    if (preg_match("/$pattern/", (string) $key)) {
                        $remainingPath = implode($delimiter, array_slice($parts, $i + 1));
                        if ($remainingPath !== '') {
                            $nestedValue = is_array($value) ? array_get($value, $remainingPath, $default, $delimiter) : $default;
                            if ($nestedValue !== $default) {
                                $matchedValues[] = $nestedValue;
                            }
                        } else {
                            $matchedValues[] = $value;
                        }
                    }
                }

                return empty($matchedValues) ? $default : (count($matchedValues) === 1 ? $matchedValues[0] : $matchedValues);
            }

            // 常规路径部分
            if (! is_array($result) || ! array_key_exists($part, $result)) {
                return $default;
            }

            $result = $result[$part];
        }

        return $result;
    }
}


if (! function_exists('response_and_continue')) {
    /**
     * 输出json后继续在后台执行指定方法
     *
     *
     * @DateTime 2019-01-07
     *
     * @param  array  $responseDara  立即响应的数组数据
     * @param  string|array  $backendFun  需要在后台执行的方法
     * @param  array  $backendFunArgs  给在后台执行的方法传递的参数
     * @param  int  $setTimeLimit  设置后台响应可执行时间
     *
     * @demo     ：先以json格式返回$data，然后在后台执行 $this->pushSuggestToJyblSys(array('suggId' => $id))
     *         response_and_continue($data, array($this, "pushSuggestToJyblSys"), array('suggId' => $id));
     */
    function response_and_continue(array $responseDara, string|array $backendFun, array $backendFunArgs = [], int $setTimeLimit = 0): void
    {
        ignore_user_abort(true);
        set_time_limit($setTimeLimit);
        ob_end_clean();
        ob_start();
        // Windows服务器
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            echo str_repeat(' ', 4096);
        }
        // 返回结果给ajax
        echo json_encode($responseDara);
        $size = ob_get_length();
        header("Content-Length: $size");
        header('Connection: close');
        header('HTTP/1.1 200 OK');
        header('Content-Encoding: none');
        header('Content-Type: application/json;charset=utf-8');
        ob_end_flush();
        if (ob_get_length()) {
            ob_flush();
        }
        flush();
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        sleep(3);
        ignore_user_abort(true);
        set_time_limit($setTimeLimit);
        if (! empty($backendFun)) {
            call_user_func_array($backendFun, $backendFunArgs);
        }
    }
}

if (! function_exists('img_to_base64')) {
    /**
     * 图片转 base64
     *
     *
     * @DateTime 2017-07-18
     *
     * @param    [type]       $image_file [description]
     * @return string [description]
     */
    function img_base64($image_file): string
    {
        $image_info = getimagesize($image_file);
        $image_data = file_get_contents($image_file);

        return 'data:'.$image_info['mime'].';base64,'.chunk_split(base64_encode($image_data));
    }
}

if (! function_exists('base64_to_image')) {
    /**
     * base64图片转文件图片
     * base64_to_image($row['cover'],'./uploads/images')
     *
     * 仅接受白名单内的图片 MIME（png/jpg/jpeg/gif/webp/bmp/wbmp/xbm/ico），
     * 按「$path/年月日/」创建子目录并以随机文件名写盘，返回去掉开头 `.` 的相对路径；
     * 格式不匹配、类型不在白名单或写盘失败时返回 false。
     *
     * @param  string  $base64_image_content  data:image/xxx;base64,... 形式的图片内容
     * @param  string  $path  图片保存的根目录
     * @return bool|string 成功返回文件相对路径，失败返回 false
     */
    function base64_to_image($base64_image_content, $path): bool|string
    {
        // 仅允许图片扩展名，杜绝 data:image/php 之类写入 .php webshell
        $allowedTypes = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'wbmp', 'xbm', 'ico'];
        // 匹配出图片的格式
        if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_image_content, $result)) {
            $type = strtolower($result[2]);
            if (! in_array($type, $allowedTypes, true)) {
                return false;
            }
            $new_file = $path.'/'.date('Ymd', time()).'/';
            if (! file_exists($new_file)) {
                // 检查是否有该文件夹，如果没有就创建，并给予最高权限
                create_dir_or_filepath($new_file);
            }
            $new_file = $new_file.md5(time().random_int(1, 1000000)).".{$type}";
            if (file_put_contents($new_file, base64_decode(str_replace($result[1], '', $base64_image_content)))) {
                return ltrim($new_file, '.');
            } else {
                return false;
            }
        }

        return false;
    }
}

if (! function_exists('show_img')) {
    /**
     * 页面直接输出图片
     *
     * @param string $imgFile 图片文件路径
     */
    function show_img(string $imgFile = ''): never
    {
        header('Content-type:image/png');
        $content = @file_get_contents($imgFile);
        if ($content === false) {
            throw new RuntimeException("无法读取图片文件: {$imgFile}");
        }
        echo $content;
        exit;
    }
}

if (! function_exists('parse_files')) {
    /**
     * 解析文件上传数据
     *
     * 把 PHP 原生 $_FILES 的「按字段聚合」结构（name/type/tmp_name/error/size 各自为数组）
     * 重排为「每个字段一个文件数组」或「每个字段一组文件数组」的形式，便于遍历处理；
     * 多文件场景下缺失的下标以 null 填充。
     *
     * @param  array  $files  $_FILES 形式的上传数据（单文件或多文件）
     * @return array 按字段重排后的文件结构
     */
    function parse_files(array $files): array
    {
        $parsedFiles = [];
        foreach ($files as $key => $file) {
            if (is_array($file['name'])) {
                // 处理多个文件上传
                foreach ($file['name'] as $index => $filename) {
                    $parsedFiles[$key][] = [
                        'name' => $filename,
                        'type' => $file['type'][$index] ?? null,
                        'tmp_name' => $file['tmp_name'][$index] ?? null,
                        'error' => $file['error'][$index] ?? null,
                        'size' => $file['size'][$index] ?? null,
                    ];
                }
            } else {
                // 处理单个文件上传
                $parsedFiles[$key] = [
                    'name' => $file['name'],
                    'type' => $file['type'],
                    'tmp_name' => $file['tmp_name'],
                    'error' => $file['error'],
                    'size' => $file['size'],
                ];
            }
        }

        return $parsedFiles;
    }
}

if (! function_exists('is_laravel')) {
    /**
     * 检测是否在 Laravel 环境中
     *
     * 三选一命中即为真：定义了 LARAVEL_START 常量、存在 Illuminate\Foundation\Application 类、
     * 或全局 app() 返回 Illuminate 应用实例（仅在前两者都不满足时才会真正调用 app()）。
     *
     * @return bool 处于 Laravel 环境返回 true，否则返回 false
     */
    function is_laravel(): bool
    {
        return defined('LARAVEL_START') ||
            class_exists(\Illuminate\Foundation\Application::class) ||
            function_exists('app') && app() instanceof \Illuminate\Contracts\Foundation\Application;
    }
}

if (! function_exists('obj2Arr')) {
    /**
     * 对象转数组
     *
     * 与 obj_to_arr() 逻辑一致（本文件受 function_exists 保护，先定义者生效）：
     * 对象强转为数组、数组逐项递归，其它类型原样返回。
     *
     * @param  mixed  $array  待转换的对象 / 数组 / 标量
     * @return mixed 转换后的数组或原始值
     */
    function obj2Arr($array): mixed
    {
        if (is_object($array)) {
            $array = (array) $array;
        }
        if (is_array($array)) {
            foreach ($array as $key => $value) {
                $array[$key] = obj2Arr($value);
            }
        }

        return $array;
    }
}


if (! function_exists('url_conversion')) {
    /**
     * 把 ./ 和 ../ 开头的资源地址转换为绝对地址
     *
     * 与前面同名函数逻辑一致（本文件受 function_exists 保护，先定义者生效）。
     *
     * @param  string  $string  需要转换的字符串
     * @param  string  $prefixString  拼接的前缀字符
     * @param  array  $linkAttr  需要转换的标签属性，例如：href、src、durl
     * @return string 转换后的字符串
     */
    function url_conversion(string $string = '', string $prefixString = '', array $linkAttr = ['href', 'src']): string
    {
        if (empty($string) || empty($prefixString)) {
            return $string;
        }
        // 判断$string是否是 / 、./ 或者 ../ 开头的url字符串
        if (mb_substr($string, 0, 1, 'utf-8') == '/' || mb_substr($string, 0, 2, 'utf-8') == './' || mb_substr($string, 0, 3, 'utf-8') == '../') {
            return url_conversion_to_prefix_path($string, $prefixString);
        }
        $linkAttrString = implode('|', $linkAttr); // 数组转为字符串 用 (竖线)`|` 分割，例如：href|src|durl
        // 正则查找 $linkAttr 属性中 以 ./、../、/ 和文件夹名称开头的图片或超链接的相对路径 URL 地址字符串,要求src、href等前面至少带一个空格，避免操作 src 和 oldsrc 都识别到src的情况
        // $pattern = '/\s+(href|src)\s*=\s*"(?:\.\/|\.\.|\/)?([^"|^\']+)"/';
        $pattern = '/\s+('.$linkAttrString.')\s*=\s*"(?:\.\/|\.\.|\/)?([^"|^\']+)"/';
        preg_match_all($pattern, $string, $matches);

        $relativeURLs = $matches[0];
        $originalPath = []; // 原始的相对路径数组
        $replacePath = []; // 替换成的前缀路径数组
        $plusReplacePath = []; // 加强版替换路径数组
        foreach ($relativeURLs as $findStr) {
            // 删除 $findStr 字符串中的 href= 或者 src= durl= 字符串
            $findStr = preg_replace('/\s+('.$linkAttrString.')\s*=\s*["\']/i', '', $findStr);
            $originalPath[] = $findStr;
            $replacePath[] = url_conversion_to_prefix_path($findStr, $prefixString);
        }
        if (! empty($originalPath) && ! empty($replacePath)) {
            // 批量替换地址;直接在此处替换会导致 出现相同的'link'字符串时候会被替换多次，导致出现错误的结果
            // $string = str_replace($originalPath, $replacePath, $string);

            // 加强版开始开始表演：找出 'link' 相关字符串的前缀(例如src、href等)最为批量替换的前缀，防止被多次替换
            // 强化前缀字符串
            $strengthenAttr = $matches[1];
            foreach ($originalPath as $index => $item) {
                // 判断最后一个引号是单引号还是双引号
                $lastQuotationMark = substr($relativeURLs[$index], -1);
                // 把替换结果拼上 $linkAttr 对应的前缀，例如 ` src="` 或者 ` href="等
                $plusReplacePath[$index] = ' '.$strengthenAttr[$index].'='.$lastQuotationMark.$replacePath[$index];
            }
            // 批量替换地址
            $string = str_replace($relativeURLs, $plusReplacePath, $string);
        }

        return $string;
    }
}

if (! function_exists('url_conversion_to_prefix_path')) {
    /**
     * 把 $url 中的 相对路径 转换为$prefix前缀路径, 建议调用 url_conversion() 方法
     *
     * 已是 http 开头的地址原样返回；`/` 开头时拼到前缀的域名（或首段目录）之后；
     * 其余情况按 `../` 的层数回退前缀目录层级，再拼接去掉 `../`、`./` 的剩余路径。
     *
     * @param  string  $url  待转换的相对路径
     * @param  string  $prefix  前缀路径或完整域名 URL
     * @return string 拼接后的绝对路径（或原样返回）
     */
    function url_conversion_to_prefix_path(string $url = '', string $prefix = ''): string
    {
        if (empty($url) || empty($prefix)) {
            return $url;
        }
        if (mb_substr($url, 0, 4, 'utf-8') != 'http') {
            // 用 / 把 $prefix  拆分为数组
            $domain_prefix_arr = explode('/', trim($prefix, '/'));
            if (mb_substr($url, 0, 1, 'utf-8') == '/') {
                // 处理 / 开头的路径
                if (mb_substr($prefix, 0, 4, 'utf-8') == 'http') {
                    // 解析URL
                    $urlInfo = parse_url($prefix);
                    $domain = $urlInfo['scheme'].'://'.$urlInfo['host'].(! empty($urlInfo['port']) ? ':'.$urlInfo['port'] : '');

                    return $domain.$url;
                } else {
                    return $domain_prefix_arr[0].$url;
                }
            }
            // 查找 $url 字符串中出现了几次 ../ ,例如：../../ ,不要查找 ./ ，因为 ./ 表示0次
            $count = mb_substr_count($url, '../', 'utf-8');
            // 从 $domain_prefix_arr 中删除 $count 个元素
            $count > 0 && array_splice($domain_prefix_arr, -$count);
            // 用 / 把 $domain_prefix_arr  拼接为字符串
            $prefix = implode('/', $domain_prefix_arr);
            // 去掉 $url 字符串中的 ../ 和 ./
            $url = str_replace(['../', './'], '', $url);
            $url = rtrim($prefix, '/').'/'.ltrim($url, '/');
        }

        return $url;
    }
}

if (! function_exists('array_keys_search')) {
    /**
     * 从二维数组中搜索指定的键名，返回键名对应的值
     *
     * onlyExists 为 true 时只保留数组中真实存在的键；为 false 时以 $keys 为准补齐结果，
     * 缺失的键填 null。入参为空时直接返回空数组。
     *
     * @param  array  $array  二维数组
     * @param  array  $keys  键名数组
     * @param  bool  $onlyExists  是否只返回存在的键名对应的值
     * @return array 键名 => 值 的结果数组
     */
    function array_keys_search(array $array = [], array $keys = [], bool $onlyExists = false): mixed
    {
        $result = [];
        if (empty($array) || empty($keys)) {
            return $result;
        }
        if ($onlyExists) {
            // 方式一：只返回存在的键名对应的值
            foreach ($array as $key => $value) {
                if (in_array($key, $keys)) {
                    $result[$key] = $value;
                }
            }
        } else {
            // 方式二：返回所有指定键名对应的值，不存在的键名返回null
            foreach ($keys as $key) {
                $result[$key] = $array[$key] ?? null;
            }
        }

        return $result;
    }
}

if (! function_exists('get_trace_data')) {
    /**
     * 获取 Throwable 异常信息的关键数据，方便记录到库
     *
     * 过滤掉 vendor 目录下的调用帧；$includeArgs 为 true 时把对象参数压缩为
     * 「类名:主键」或「类名」字符串，避免把整个对象写入存储。
     *
     * @param  Throwable  $exception  异常信息
     * @param  bool  $includeArgs  是否包含参数
     * @return array{message: string, code: int|string, file: string, line: int, trace: array} 异常关键字段与过滤后的调用栈
     */
    function get_trace_data(Throwable $exception, bool $includeArgs = true): array
    {
        $trace = $exception->getTrace();
        $traceData = [];

        foreach ($trace as $item) {
            // 跳过 vendor 目录
            if (isset($item['file']) && str_contains($item['file'], 'vendor')) {
                continue;
            }

            // 处理参数
            if (! $includeArgs) {
                unset($item['args']);
            }
            if ($includeArgs && isset($item['args'])) {
                foreach ($item['args'] as &$arg) {
                    if (is_object($arg)) {
                        $className = get_class($arg);
                        // Laravel 模型
                        if (method_exists($arg, 'getKey')) {
                            $id = $arg->getKey();
                            $arg = $className.':'.($id ?? 'null');
                        } else {
                            // 其他对象
                            $arg = $className;
                        }
                    }
                }
            }
            $traceData[] = $item;
        }

        return [
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $traceData,
        ];
    }
}

if (! function_exists('to_full_text_search_str')) {
    /**
     * 将用户输入的搜索字符串转换为 MySQL 全文索引的布尔模式搜索字符串
     *
     * 本函数解析用户输入，并生成符合 MySQL 全文索引布尔模式语法的搜索字符串。
     * 支持所有标准布尔操作符、分组、短语、邻近搜索以及通配符。
     *
     * MySQL 布尔模式操作符说明：
     *   +     必须包含该词
     *   -     必须排除该词
     *   ~     负相关性（包含该词会降低文档权重）
     *   >     提高该词的相关性
     *   <     降低该词的相关性
     *   *     后缀通配符（仅限词尾，例如 "Larav*" 匹配 Laravel、Larav 等）
     *   "..." 精确短语匹配（引号内单词作为一个整体）
     *
     *   @N    邻近搜索：指定 "..." 内单词之间的最大距离（例如 "word1 word2"@5）
     *   ( )   分组条件，用于组合逻辑
     *   空格  逻辑 OR（可选包含）
     *
     * 用法示例：
     *   1. 必须包含 Laravel，排除 Vue：         "+Laravel -Vue"
     *   2. 提高 PHP 权重，降低 Java 权重：       ">PHP <Java"
     *   3. 必须包含 PHP，排除短语 "end of life"："+PHP -\"end of life\""
     *   4. 必须包含 MySQL 且 (Laravel 或 PHP)：   "+MySQL +(Laravel PHP)"
     *   5. 短语 "Laravel PHP" 邻近搜索（相距≤5）："\"Laravel PHP\"@5"
     *   6. 前缀通配符：                          "Larav* +framework"
     *   7. 包含 MySQL 或 PostgreSQL，排除 Oracle：  "+(MySQL PostgreSQL) -Oracle"
     *   8. 包含 PHP，但包含 legacy 会降低排名：   "+PHP ~legacy"
     *   9. 复杂分组：                           "+((React Vue) (Laravel Django)) +\"最佳实践\""
     *
     * @param  string  $string  原始搜索字符串
     * @param  bool  $autoWildcard  是否为普通词自动添加后缀通配符 *（false-即精确匹配）
     * @return string 可直接用于 MySQL boolean mode 的搜索字符串，若无效则返回空字符串
     *
     * // 自然语言模式
     * // return self::query()->whereFullText('title', $string)->orWhereFullText('content', $string)->get();
     * // 布尔模式
     * // return self::query()->whereFullText(['title', 'content'], '+测试 -公司', ['mode' => 'boolean'])->count();
     * // 自然扩展模式
     * // return self::query()->whereFullText('content', '测试', ['expanded' => true])->paginate(10);
     * // 模型使用
     * // return self::query()->whereFullText('content', '测试')->get();
     */
    function to_full_text_search_str(string $string, bool $autoWildcard = true): string
    {
        // 1. 基本清理：合并空白符，压缩连续的操作符，并去除首尾空格
        $cleaned = preg_replace(['/\s+/', '/\++/', '/\-+/', '/\~+/', '/\*+/'], [' ', '+', '-', '~', '*'], trim($string));
        if ($cleaned === '') {
            return '';
        }

        // 2. 使用正则分词，按 token 解析
        // 匹配模式：操作符、括号、短语（含可能的后缀距离）、普通词（含通配符）、转义字符等
        $pattern = '/(?:
            [+\-~><]                         # 操作符
            | \([^()]*\)                     # 简单括号组（为简化，不处理嵌套，但通常够用）
            | \\([+\-~><"*()]               # 转义的特殊字符，搜索字面量
            | "[^"\\\\]*(?:\\\\.[^"\\\\]*)*"  # 双引号短语（支持内部转义）
            | \S+                            # 其他非空白字符（词、通配符）
        )/ux';

        preg_match_all($pattern, $cleaned, $matches);
        $tokens = $matches[0];

        $result = [];
        $i = 0;
        $len = count($tokens);

        while ($i < $len) {
            $token = $tokens[$i];

            // 处理转义字符：如果以反斜杠开头，去掉反斜杠保留原字符
            if (str_starts_with($token, '\\') && strlen($token) === 2) {
                $result[] = substr($token, 1);
                $i++;

                continue;
            }

            // 处理操作符（单独占一个 token）
            if (in_array($token, ['+', '-', '~', '>', '<'])) {
                // 如果下一个 token 是括号，则操作符作用于整个括号组
                if ($i + 1 < $len && $tokens[$i + 1][0] === '(') {
                    // 将操作符与括号组组合，避免重复添加
                    $result[] = $token.$tokens[$i + 1];
                    $i += 2;
                } else {
                    // 否则先记录操作符，稍后与下一个词组合
                    $operator = $token;
                    $i++;
                    // 跳过可能跟随的操作符（用户可能输入多个，但已压缩）
                    while ($i < $len && in_array($tokens[$i], ['+', '-', '~', '>', '<'])) {
                        $operator = $tokens[$i]; // 取最后一个操作符
                        $i++;
                    }
                    // 如果后面是括号，将操作符和括号合并
                    if ($i < $len && $tokens[$i][0] === '(') {
                        $result[] = $operator.$tokens[$i];
                        $i++;
                    } elseif ($i < $len) {
                        // 否则与下一个普通词组合
                        $next = $tokens[$i];
                        $result[] = $operator.$next.($autoWildcard && ! IsPhraseOrWildcard($next) ? '*' : '');
                        $i++;
                    }
                }

                continue;
            }

            // 处理括号组（可能已包含操作符）
            if ($token[0] === '(') {
                // 如果括号前没有操作符，直接添加
                $result[] = $token;
                $i++;

                continue;
            }

            // 处理双引号短语（可能带 @distance）
            if ($token[0] === '"') {
                // 提取短语内容
                $phrase = $token;
                // 检查后面是否有 @数字
                if ($i + 1 < $len && preg_match('/^@\d+$/', $tokens[$i + 1])) {
                    $distance = $tokens[$i + 1];
                    $result[] = $phrase.$distance;
                    $i += 2;
                } else {
                    $result[] = $phrase;
                    $i++;
                }

                continue;
            }

            // 普通词（可能包含 * 通配符）
            if ($autoWildcard && ! str_contains($token, '*') && ! in_array($token, ['+', '-', '~', '>', '<', '(', ')'])) {
                // 自动添加后缀通配符（但如果是短语、括号等就不加）
                $result[] = $token.'*';
            } else {
                $result[] = $token;
            }
            $i++;
        }

        return implode(' ', $result);
    }

    /**
     * 辅助函数：判断 token 是否已经是短语或包含通配符，避免重复添加 *
     *
     * 首字符为双引号（短语）或 token 内已含 `*` 时视为无需再追加通配符。
     * 注意：传入空字符串会因访问下标 0 而触发告警。
     *
     * @param  string  $token  待判断的搜索词片段
     * @return bool 已是短语或已含通配符返回 true，否则返回 false
     */
    function isPhraseOrWildcard(string $token): bool
    {
        return $token[0] === '"' || str_contains($token, '*');
    }
}


if (! function_exists('base_convert_any')) {
    /**
     * 将任意进制的数值转换为另一个进制的数值,
     *      支持 2 到 62 进制之间的转换
     *      支持负数
     *
     * @param  string  $number  待转换数值，可以是整数或浮点数，支持负数，例如："123", "-456.789"
     * @param  int  $fromBase  源进制
     * @param  int  $toBase  目标进制
     * @return string|int 转换成功返回目标进制下的数值
     *
     * @throws Exception
     */
    function base_convert_any(string $number, int $fromBase = 10, int $toBase = 62): string|int
    {
        // 常量字符集
        static $digits = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

        // 校验进制合法性
        if ($fromBase < 2 || $fromBase > 62 || $toBase < 2 || $toBase > 62) {
            throw new \InvalidArgumentException('Bases must be in range 2–62.');
        }

        // 快速路径（相同进制）
        if ($fromBase === $toBase) {
            return $number;
        }

        // 处理负数
        $isNegative = $number[0] === '-';
        if ($isNegative) {
            $number = substr($number, 1);
        }

        // 去除前导 0
        $number = ltrim($number, '0');
        if ($number === '') {
            return '0';
        }

        // 尝试使用 GMP（如可用）
        if (extension_loaded('gmp')) {
            // GMP 内部使用高效 C 实现，速度远超 BCMath
            $decimal = gmp_init($number, $fromBase);
            $converted = gmp_strval($decimal, $toBase);

            return $isNegative ? '-'.$converted : $converted;
        }

        // fallback to BCMath：构造字符映射表
        static $charMap = null;
        if ($charMap === null) {
            $charMap = [];
            for ($i = 0; $i < 62; $i++) {
                $charMap[$digits[$i]] = $i;
            }
        }

        // === Step 1: 任意进制转 10 进制（字符串）
        $decimal = '0';
        $baseStr = (string) $fromBase;
        $len = strlen($number);

        for ($i = 0; $i < $len; $i++) {
            $char = $number[$i];
            $value = $charMap[$char] ?? null;
            if ($value === null || $value >= $fromBase) {
                throw new \InvalidArgumentException("Invalid character '$char' for base $fromBase.");
            }
            $decimal = bcadd(bcmul($decimal, $baseStr), (string) $value, 0);
        }

        // === Step 2: 十进制转目标进制
        if ($toBase === 10) {
            $result = $decimal;
        } else {
            $result = '';
            $toBaseStr = (string) $toBase;
            while (bccomp($decimal, '0') > 0) {
                $mod = bcmod($decimal, $toBaseStr);
                $result = $digits[(int) $mod].$result;
                $decimal = bcdiv($decimal, $toBaseStr, 0);
            }
        }

        return $isNegative ? '-'.$result : $result;
    }
}

if (! function_exists('from60to10')) {
    /**
     * 60进制转10进制
     *
     * 使用去掉易混字符 o/O 的自定义字典逐位加权求和，返回不带千分位的十进制字符串。
     *
     * @param  string  $str  60 进制字符串
     * @return string 十进制结果（纯数字字符串）
     */
    function from60to10($str): string
    {
        // (去掉oO)
        $dict = '0123456789abcdefghijklmnpqrstuvwxyzABCDEFGHIJKLMNPQRSTUVWXYZ';
        $len = strlen($str);
        $dec = 0;
        for ($i = 0; $i < $len; $i++) {
            // 找到对应字典的下标
            $pos = strpos($dict, $str[$i]);
            $dec += $pos * pow(60, $len - $i - 1);
        }

        return number_format($dec, 0, '', '');
    }
}

if (! function_exists('from10to60')) {
    /**
     * 10进制转60进制
     *
     * 反复取模 60 查自定义字典（去掉易混字符 o/O）拼接结果，除到 0 为止。
     *
     * @param  int|string  $dec  十进制数值
     * @return string 60 进制字符串
     */
    function from10to60($dec): string
    {
        // (去掉oO,因为和0很像)
        $dict = '0123456789abcdefghijklmnpqrstuvwxyzABCDEFGHIJKLMNPQRSTUVWXYZ';
        $result = '';
        do {
            $result = $dict[$dec % 60].$result;
            $dec = intval($dec / 60);
        } while ($dec != 0);

        return $result;
    }
}

if (! function_exists('dict_convert_ten')) {
    /**
     * 把其他进制的字符串转换为10进制（注：针对使用自定义字典的字符串转换，普通的进制转换可以使用 base_convert_any 函数）
     *
     * @param  string  $string  根据自定义字典 $dict 生成的字符串
     * @param  int  $fromBase  源进制
     * @return int|string 十进制结果（超长数值可能以字符串返回）
     *
     * @throws Exception
     */
    function dict_convert_ten(string $string = '', int $fromBase = 16): int|string
    {
        return base_convert_any($string, $fromBase, 10);
    }
}

if (! function_exists('remove_str_emoji')) {
    /**
     * 移除字符串中的 emoji 表情
     *
     * 逐字符判断，字节长度 >= 4 的字符被丢弃（emoji 多为 4 字节）；
     * 因此同样会被误删的还有部分 4 字节汉字（如扩展 B 区字符）。
     *
     * @param  string  $str  待处理的字符串
     * @return string 移除 emoji 后的字符串
     */
    function remove_str_emoji($str): string
    {
        $mbLen = mb_strlen($str);
        $strArr = [];
        for ($i = 0; $i < $mbLen; $i++) {
            $mbSubstr = mb_substr($str, $i, 1, 'utf-8');
            if (strlen($mbSubstr) >= 4) {
                continue;
            }
            $strArr[] = $mbSubstr;
        }

        return implode('', $strArr);
    }
}

if (! function_exists('check_str_exists_emoji')) {
    /**
     * 判断字符串中是否含有 emoji 表情
     *
     * 逐字符检查字节长度，发现任一 >= 4 字节的字符即判定为含 emoji；
     * 与 remove_str_emoji() 的判定口径一致，同样会把部分 4 字节汉字误判为表情。
     *
     * @param  string  $str  待检查的字符串
     * @return bool 含 emoji 返回 true，否则返回 false
     */
    function check_str_exists_emoji($str): bool
    {
        $mbLen = mb_strlen($str);
        $strArr = [];
        for ($i = 0; $i < $mbLen; $i++) {
            $strArr[] = mb_substr($str, $i, 1, 'utf-8');
            if (strlen($strArr[$i]) >= 4) {
                return true;
            }
        }

        return false;
    }
}

if (! function_exists('del_dir')) {
    /**
     * 删除文件夹
     *
     * 使用递归迭代器按「先子后父」顺序删除目录内所有文件与子目录，
     * $delSelf 为 true 时最后删除目录本身；路径不是目录时直接返回 false。
     * 注意：删除失败时不会中断，仍可能返回 true。
     *
     * @param  string  $dirname  目录
     * @param  bool  $delSelf  是否删除自身
     * @return bool 目标不是目录返回 false，否则返回 true（不校验每个条目的删除结果）
     */
    function del_dir(string $dirname, bool $delSelf = true): bool
    {
        if (! is_dir($dirname)) {
            return false;
        }
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dirname, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $item) {
            $todo = ($item->isDir() ? 'rmdir' : 'unlink');
            $todo($item->getRealPath());
        }
        if ($delSelf) {
            rmdir($dirname);
        }

        return true;
    }
}

if (! function_exists('dir_is_empty')) {
    /**
     * 判断文件夹是否为空
     *
     * 打开目录并跳过 `.`、`..`；不存在其它条目即为空。
     *
     * @param  string  $dir  目录路径
     * @return bool 目录为空（或无法打开）时返回 true，存在任一有效条目时返回 false
     */
    function dir_is_empty(string $dir): bool
    {
        $handle = @opendir($dir);
        if ($handle === false) {
            // 目录不存在或无权限读取，按「空」处理，避免 closedir(false) 触发 TypeError
            return true;
        }

        $isEmpty = true;
        try {
            while (($item = readdir($handle)) !== false) {
                if ($item !== '.' && $item !== '..') {
                    $isEmpty = false;
                    break;
                }
            }
        } finally {
            closedir($handle);
        }

        return $isEmpty;
    }
}

if (! function_exists('create_dir')) {
    /**
     * 递归创建目录
     *
     * 目录已存在即视为成功；否则先递归创建父级再用 mkdir 创建自身（已开启 recursive）。
     *
     * @param  string  $dir  目录
     * @param  int  $permissions  权限
     * @return bool 目录最终存在（或创建成功）返回 true，否则返回 false
     */
    function create_dir(string $dir, int $permissions = 0755): bool
    {
        return is_dir($dir) or (create_dir(dirname($dir), $permissions) and mkdir($dir, $permissions, true));
    }
}

if (! function_exists('create_dir_or_filepath')) {
    /**
     * 创建文件夹或文件
     *
     * 空路径返回 false；以分隔符结尾视为目录目标（递归 mkdir），否则按文件目标处理：
     * 先确保父目录存在，再 touch 出空文件，若实际上该路径是目录则退化为创建目录。
     *
     * @param  string  $path  文件夹或者文件路径
     * @param  int  $permissions  创建目录时使用的权限，默认 0755
     * @return bool 目标已存在或创建成功返回 true，创建失败返回 false
     */
    function create_dir_or_filepath(string $path = '', int $permissions = 0755): bool
    {
        if ($path === '') {
            return false;
        }

        // 去掉结尾的目录分隔符后再判断，避免 dirname() 在“目录路径/”时
        // 把目标目录本身误当作最后一级而剥离，从而错误计算出祖父级目录、
        // 导致后续 touch 在目录路径上失败、目标目录始终无法创建。
        $normalized = rtrim($path, '/\\');

        if ($normalized === '' || $normalized === '.') {
            // 指向当前目录，必然已存在
            return true;
        }

        if (file_exists($normalized)) {
            return true;
        }

        // 调用方以分隔符结尾（如 base64_to_image 传入的“目录/”）→ 目标为目录
        $isDirectoryTarget = $path !== $normalized;

        if ($isDirectoryTarget) {
            return mkdir($normalized, $permissions, true) || is_dir($normalized);
        }

        // 文件目标：确保父目录存在
        $dir = dirname($normalized);
        if (! is_dir($dir) && ! mkdir($dir, $permissions, true) && ! is_dir($dir)) {
            // 创建父文件夹失败
            return false;
        }

        // 创建空文件占位；若该路径实为目录（无扩展名等边界情况）则退化为创建目录
        if (file_exists($normalized)) {
            return true;
        }

        if (@touch($normalized)) {
            return true;
        }

        // touch 失败（目标本应是目录）时退化为创建目录
        return mkdir($normalized, $permissions, true) || is_dir($normalized);
    }
}


if (! function_exists('num_to_cn')) {
    /**
     * 数字转换为中文
     *      支持金额转换和小数转换
     *
     * @param  float|int|string  $number  需要转换的数字
     * @param  bool  $mode  模式[true:金额（默认）,false:普通数字表示]
     * @param  bool  $sim  使用小写（默认）
     * @return string 中文大写/小写数字，金额模式带「元」及角分单位，普通模式带「点」和小数位
     *
     * @throws Exception
     */
    function num_to_cn(float|int|string $number, bool $mode = true, bool $sim = true): string
    {
        if (! is_numeric($number)) {
            throw new \Exception('传入参数不是一个数字！');
        }
        // 数字大小写
        $char = $sim ? ['零', '一', '二', '三', '四', '五', '六', '七', '八', '九'] : ['零', '壹', '贰', '叁', '肆', '伍', '陆', '柒', '捌', '玖'];
        // 每一个数级的 四个位置
        $twoUnit = $sim ? ['', '十', '百', '千'] : ['', '拾', '佰', '仟'];
        // 每一个数级的 单位; 1古戈尔 = 10¹⁰⁰， 古戈尔也称为 不可说
        // 1恒河沙 = 10⁵²（佛教用语）
        // 1不可思议 = 10⁶⁴
        // 1腾 = 10¹²⁸
        $twoChat = [
            '', '万', '亿', '兆', '京', '垓', '秭', '穰', '沟', '涧', '正', '载', '极', '恒河沙', '阿僧祇', '那由他', '不可思议',
            '无量', '大数', '无量大数', '不可称', '不可量', '不可数', '不可思', '不可议', '古戈尔',
            '不可说数', '无边数', '无等数', '无等无等数', '无限数', '无限无边数', '腾',
        ];
        $moneyUnit = ['角', '分', '厘', '毫', '丝', '忽', '微', '纤', '沙'];

        // 将整数部分和小数部分分开
        [$num, $dec] = (str_contains($number, '.')) ? [substr($number, 0, strpos($number, '.')), substr($number, strpos($number, '.') + 1)] : [$number, ''];

        // 小数部分
        $decNumStr = '';
        // 整数部分
        $roundNum = [];

        // 将小数部分转换为中文
        for ($j = 0; $j < strlen($dec); $j++) {
            $decNum[$j] = $char[$dec[$j]];
            if ($mode) {
                if ($j < count($moneyUnit)) {
                    $decNumStr .= $decNum[$j].$moneyUnit[$j];
                }
            } else {
                $decNumStr .= $decNum[$j];
            }
        }

        // 反转字符串 处理整数部分
        $str = $mode ? strrev((string) ($num)) : strrev($num);

        $hasZero = false; // 数级上是否有零
        for ($i = 0, $c = strlen($str); $i < $c; $i++) {
            // $str[$i] 小写数字 eg: 2
            $roundNum[$i] = $char[$str[$i]]; // 单个大写数字 eg : 贰

            // 每四位一组，处理中文单位
            if ($i % 4 == 0) {
                $hasZero = false;
                $hasValue = false; // 数级上是否有值
                // 判断数级上是否有值
                for ($k = 0; $k < 4; $k++) {
                    if (! empty($str[$i + $k])) {
                        $hasValue = true;
                        break;
                    }
                }
                if (! $hasValue) {
                    $roundNum[$i] = '';
                } else {
                    // 一个数级的单位，处理每一级的个位
                    if (empty($str[$i])) { // 零万 零亿 等 处理成 万 亿
                        $roundNum[$i] = $twoChat[floor($i / 4)]; // xx万 xx亿
                    } else {
                        $roundNum[$i] .= $twoChat[floor($i / 4)]; // xx万 xx亿
                    }
                }
            } else {
                if (! empty($str[$i])) {
                    $roundNum[$i] .= $twoUnit[$i % 4]; // 加单位 十百千
                    if ($str[$i] == 1 && $i % 4 == 1 && empty($str[$i + 1])) { // 一十 处理成 十
                        $roundNum[$i] = $twoUnit[$i % 4];
                    }
                } else {
                    if ($hasZero) {
                        $roundNum[$i] = '';
                    }
                    // 判断低一位数
                    if (isset($str[$i - 1])) {
                        $hasZero = true;
                        $roundNum[$i] = ! empty($str[$i - 1]) ? '零' : '';
                    }
                }
            }
        }
        // 拼接整数部分和小数部分
        $roundNumStr = implode('', array_reverse($roundNum)); // 整数

        return $roundNumStr.($mode ? '元' : '').((! empty($decNumStr) && ! $mode) ? '点' : '').$decNumStr;
    }
}

if (! function_exists('num_to_word')) {
    /**
     * 数字转换为英文
     *
     * 借助 intl 扩展的 NumberFormatter（en / SPELLOUT）把数字读成英文单词；
     * 依赖 intl 扩展，未安装时构造 NumberFormatter 会失败。
     *
     * @param  float|int|string  $number  需要转换的数字
     * @return string 英文读法
     *
     * @throws Exception
     */
    function num_to_word(float|int|string $number): string
    {
        $formatter = new \NumberFormatter('en', \NumberFormatter::SPELLOUT);

        // 数字转换
        return $formatter->format($number);
    }
}

if (! function_exists('num_to_zhCN')) {
    /**
     * 数字转换为中文（简体）
     *
     * 借助 intl 扩展的 NumberFormatter（zh_cn / SPELLOUT）读成中文数字，
     * 并把其中的「〇」统一替换为「零」；依赖 intl 扩展。
     *
     * @param  float|int|string  $number  需要转换的数字
     * @return string 中文读法
     *
     * @throws Exception
     */
    function num_to_zhCN(float|int|string $number): string
    {
        $formatter = new \NumberFormatter('zh_cn', \NumberFormatter::SPELLOUT);
        // 数字转换
        $str = $formatter->format($number);

        // 把〇替换成零
        return str_replace('〇', '零', $str);
    }
}

if (! function_exists('str_rand')) {
    /**
     * 生成随机字符串
     *
     * 从「大小写字母 + 数字 + 自定义附加值」组成的字符集中逐位取随机字符，
     * 使用 random_int() 保证随机性；字符集内不含 l、I、0、2 等易混字符。
     *
     * @param  int  $length  字符串长度
     * @param  string  $tack  附加值
     * @return string 字符串
     */
    function str_rand(int $length = 6, string $tack = ''): string
    {
        $chars = 'abcdefghijkmnpqrstuvwxyzACDEFGHIJKLMNOPQRSTUVWXYZ12345679'.$tack;
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $str;
    }
}


if (! function_exists('download_url_file')) {
    /**
     * 以附件形式流式下载本地文件到浏览器
     *
     * 安全约束：
     * - 不再从 $_GET['url'] 自动读取，必须由调用方显式传入路径，避免任意文件读取 / SSRF；
     * - 拒绝远程地址（含协议 scheme，如 http://、ftp://），仅允许本地文件路径；
     * - 若传入 $allowedBaseDir，则落盘路径必须位于该目录内部（防目录穿越）；
     * - 文件名经净化，防响应头注入（CRLF / 引号）。
     *
     * @param string $url            待下载的本地文件路径（绝对或相对）
     * @param string $allowedBaseDir 可选，允许下载的根目录；命中后路径必须落在其内部
     * @return never
     * @throws \RuntimeException 路径为空、无法打开或超出允许目录范围时
     */
    function download_url_file(string $url = '', string $allowedBaseDir = ''): never
    {
        if ($url === '') {
            throw new RuntimeException('必须显式传入待下载的文件路径');
        }

        // 拒绝远程地址（SSRF 防护）
        if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.\-]*://#', $url) === 1) {
            throw new RuntimeException('仅支持下载本地文件，拒绝远程地址');
        }

        $realPath = realpath($url);
        if ($realPath === false || ! is_file($realPath)) {
            throw new RuntimeException("无法打开文件: {$url}");
        }

        // 目录穿越防护：若指定了允许根目录，则必须落在其内部
        if ($allowedBaseDir !== '') {
            $base = realpath($allowedBaseDir);
            $basePrefix = $base === false ? '' : $base . DIRECTORY_SEPARATOR;
            if ($base === false || $basePrefix === '' || strncmp($realPath, $basePrefix, strlen($basePrefix)) !== 0) {
                throw new RuntimeException('文件超出允许下载的目录范围');
            }
        }

        $title = basename($realPath);
        // 净化文件名，防 CRLF / 引号等响应头注入
        $title = (string) preg_replace('/[\r\n"]/', '', $title);
        if ($title === '') {
            $title = 'download';
        }

        header('Content-Type: application/octet-stream');
        header('Accept-Ranges: bytes');
        header('Content-Disposition: attachment; filename="' . $title . '"');
        readfile($realPath);
        exit;
    }
}

if (! function_exists('escape')) {
    /**
     * 把字符串转义成 带u格式的 ASCII 字符
     *
     * @param  string  $str  需要转换的字符串，eg:威舍,
     * @return string eg:%u5A01%u820D%2C
     */
    function escape(string $str): string
    {
        return implode('', array_map(function ($char) {
            $ascii = ord($char);
            if ($ascii <= 0x7F) {
                return rawurlencode($char);
            } else {
                $utf16 = mb_convert_encoding($char, 'UTF-16BE', 'UTF-8');

                return '%u'.strtoupper(bin2hex($utf16));
            }
        }, preg_split('//u', $str, -1, PREG_SPLIT_NO_EMPTY)));
    }
}

if (! function_exists('md_to_html')) {
    /**
     * 把markdown转换为html
     *
     * @param  string  $markdown  markdown字符串
     * @param  array  $options  转换选项
     *
     * @return string  html字符串
     */
    function md_to_html(string $markdown = '', array $options = []): string
    {
        $converter = new \zxf\Utils\Text\MdToH5($options);

        return $converter->convert($markdown);
    }
}
