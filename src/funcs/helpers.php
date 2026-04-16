<?php

declare(strict_types=1);

if (! function_exists('uuid')) {
    /**
     * 极速并发UUID生成器
     *
     * 优化内容：
     * 1. 添加类型安全
     * 2. 增强随机性
     * 3. 改进错误处理
     * 4. 添加自定义编码支持
     *
     * @param string $charset 自定义字符集
     * @return string 返回10-11字符的全局唯一UUID
     * @throws Exception 随机数生成失败时抛出异常
     */
    function uuid(string $charset = ''): string
    {
        static $base62 = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        static $last_microtime = 0;
        static $sequence = 0;
        static $process_hash = 0;
        static $entropy_seed = 0;

        // 一次性初始化
        if ($process_hash === 0) {
            $process_hash = (getmypid() & 0x7FF) | (random_int(0, 0x7FF) << 11);
            $entropy_seed = random_int(1, 0x3FFFFFF);
        }

        // 高精度时间获取
        $current_microtime = intdiv(hrtime(true), 1000);
        $relative_microtime = $current_microtime - 1788211200000000;

        // 无锁并发控制
        if ($relative_microtime === $last_microtime) {
            $sequence = ($sequence + 1) & 0x7FF;
            if ($sequence === 0) {
                // 微秒级快速重试，最多重试3次
                $retryCount = 0;
                do {
                    $current_microtime = intdiv(hrtime(true), 1000);
                    $relative_microtime = $current_microtime - 1788211200000000;
                    $retryCount++;
                } while ($relative_microtime === $last_microtime && $retryCount < 3);

                if ($relative_microtime === $last_microtime) {
                    throw new RuntimeException('UUID sequence overflow after retries');
                }
            }
        } else {
            $sequence = random_int(1, 0x3FF);
            $last_microtime = $relative_microtime;
        }

        // 动态熵增强
        $entropy_seed = ($entropy_seed * 1103515245 + 12345) & 0x3FFFFFF;
        $mixed_entropy = ($entropy_seed ^ $process_hash ^ $relative_microtime) & 0x7FF;

        // 最优位分配
        $numeric_id = (($relative_microtime & 0x3FFFFFFFFFF) << 22) | ($sequence << 11) | $mixed_entropy;

        // 支持自定义字符集
        $charsetToUse = $charset ?: $base62;
        $base = strlen($charsetToUse);

        // 极致编码
        $result = '';
        $num = $numeric_id;
        do {
            $result = $charsetToUse[$num % $base] . $result;
            $num = intdiv($num, $base);
        } while ($num > 0);

        return $result ?: $charsetToUse[0];
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
        $extension = pathinfo($imgFile, PATHINFO_EXTENSION);

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
        // 构建索引
        $index = [];
        foreach ($array as $item) {
            $index[$item[$pidField]][] = $item;
        }

        // 递归构建树
        $buildTree = function($parentId) use (&$buildTree, $index, $keyField, $childField, $callback, $sortField) {
            $tree = [];

            if (isset($index[$parentId])) {
                $children = $index[$parentId];

                // 排序
                if ($sortField && !empty($children)) {
                    usort($children, function($a, $b) use ($sortField) {
                        return ($a[$sortField] ?? 0) <=> ($b[$sortField] ?? 0);
                    });
                }

                foreach ($children as $child) {
                    $node = $child;
                    if ($callback) {
                        $node = $callback($node);
                    }

                    $nodeChildren = $buildTree($child[$keyField]);
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

            $children = $currentNode[$childField] ?? [];
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
    /*
     * 对json数据格式化输入展示 [转化为json格式，并格式化样式]
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
    /*
     * 字符串自动转utf8编码
     */
    function string_to_utf8(string $str = ''): array|bool|string|null
    {
        return mb_convert_encoding($str, 'UTF-8', 'auto');
    }
}

if (! function_exists('string_to_gbk')) {
    /*
     * 字符串自动转gbk编码
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
    // 检查是否是有效的 XML 字符串
    function is_xml(string $string): bool
    {
        try {
            // 尝试加载字符串为 XML
            libxml_use_internal_errors(true); // 启用内部错误处理
            simplexml_load_string($string);

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
     *
     * @DateTime 2018-08-29
     *
     * @return array|string|null [type]       [description]
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
     *
     * @DateTime 2018-08-29
     *
     * @return string [description]
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
        $html = "<form id='".$formId."' action='".$url."' method='".$method."'>";
        $html .= ! empty($methodIsMorph) ? '<input type="hidden" name="_method" value="'.$methodIsMorph.'" />' : '';
        // 遍历子数组
        function traverseChildArr($arr, $namePrefix = ''): string
        {
            $arr = obj_to_arr($arr);
            $htmlStr = '';
            foreach ($arr as $key => $item) {
                $name = empty($namePrefix) ? $key : $namePrefix.'['.$key.']';
                $htmlStr .= is_array($item) ? traverseChildArr($item, $name) : "<input type='hidden' name='".$name."' value='".$item."' />";
            }

            return $htmlStr;
        }

        $html .= traverseChildArr($data, '');
        $html .= "<input type='submit' value='确定' style='display:none;'></form>";
        $html .= "<script>document.forms['".$formId."'].submit();</script>";

        return $html;
    }
}


if (! function_exists('obj_to_arr')) {
    /**
     * 对象转数组
     *
     *
     * @return array|mixed
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
     * @return false|string
     */
    function str_en_code(string $string, string $operation = 'en', int|string $expiry = 312206400, string $key = ''): bool|string
    {
        $operation = in_array($operation, ['de', 'DECODE']) ? 'DECODE' : 'ENCODE';
        // 转换字符串
        $string = $operation == 'DECODE' ? str_replace(['_'], ['/'], $string) : $string;
        // 动态密匙长度，相同的明文会生成不同密文就是依靠动态密匙
        $ckey_length = 4;
        // 密匙
        $key = md5(! empty($key) ? $key : 'wei_si_fang');
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
        $expiry = (is_numeric($expiry) || empty($expiry)) ? time() + (int) $expiry : strtotime($expiry);
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
            if ((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16)) {
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
     *
     * @throws ReflectionException
     */
    function set_protected_value($obj, $filed, $value): void
    {
        $reflectionClass = new ReflectionClass($obj);
        try {
            $reflectionClass->setStaticPropertyValue($filed, $value);
        } catch (Exception $err) {
            $reflectionProperty = $reflectionClass->getProperty($filed);
            $reflectionProperty->setAccessible(true);
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
     * @param  string  $string  需要转换的字符串
     * @param  string  $prefixString  拼接的前缀字符
     * @param  array  $linkAttr  需要转换的标签属性，例如：href、src、durl
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


if (! function_exists('is_qq_browser')) {
    /**
     * 判断来源是否为QQ浏览器
     *
     * @return bool true|false
     */
    function is_qq_browser(): bool
    {
        // 获取所有的header信息
        $headers = getallheaders();
        $http_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        return str_contains($http_user_agent, 'MQQBrowser') || str_contains($http_user_agent, 'QQ') || isset($headers['X-QQ-From']);
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
        // 获取所有的header信息
        $headers = getallheaders();
        $http_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        return str_contains($http_user_agent, 'MicroMessenger') || str_contains($http_user_agent, 'WeChat') || isset($headers['X-Weixin-From']);
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
        // 获取所有的header信息
        $headers = getallheaders();
        $http_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        return str_contains($http_user_agent, 'Weibo') || isset($headers['X-Weibo-From']);
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
        // 获取所有的header信息
        $headers = getallheaders();
        $http_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        return str_contains($http_user_agent, 'AlipayClient') || str_contains($http_user_agent, 'Alipay') || isset($headers['X-Weibo-From']);
    }
}

if (! function_exists('is_string_value_array')) {
    /**
     * 检查是否为['字符串键名'=>'不是数组也不是对象格式类型的值']格式的数组
     *      eg:['name'=>'foo']:true
     *         ['name'=>['foo']]:false
     *         [['name','foo']]:false
     *         ['name'=>new stdClass()]:false
     */
    function is_string_value_array(array $array): bool
    {
        return ! array_is_list($array) && array_reduce($array, fn ($carry, $value) => $carry && is_scalar($value), true);
    }
}


if (! function_exists('json_string_to_array')) {
    // 判断一个字符串是否为json格式,并返回json数组
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
    // 判断json格式转换为字符串
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
     * @param  string  $className  类名 eg: \Test\Abc, get_class($this) 等
     */
    function class_basename(string $className): string
    {
        // 使用 DIRECTORY_SEPARATOR 确保跨平台兼容性
        $fullClassName = str_replace('/', '\\', $className); // 确保类名中的分隔符统一为反斜线

        // 使用 basename 函数提取路径的最后一部分，相当于提取类名
        return basename($fullClassName, '.php'); // 如果类名字符串以 ".php" 结尾，这会移除它
    }
}


if (! function_exists('relative_path')) {
    /**
     * 获取文件相对于项目根目录的相对路径
     */
    function relative_path(string $filePath): string
    {
        // 相对路径
        $dir = dirname(__DIR__);
        $prefixPath = substr($dir, 0, strpos($dir, 'vendor'));
        $realPath = realpath($filePath); // 获取真实路径

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
     * @param  bool|array  $simpleOrCustomExt  仅判断简单的几种资源文件
     *                                         true(默认): 仅判断简单的几种资源文件
     *                                         false: 会判断大部分的资源文件
     *                                         array: 仅判断自定义的这些后缀
     */
    function is_resource_file(string $url, bool|array $simpleOrCustomExt = true): bool
    {
        // 解析 URL
        $path = parse_url($url, PHP_URL_PATH);
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
        $image_data = fread(fopen($image_file, 'r'), filesize($image_file));

        return 'data:'.$image_info['mime'].';base64,'.chunk_split(base64_encode($image_data));
    }
}

if (! function_exists('base64_to_image')) {
    /**
     * base64图片转文件图片
     * base64_to_image($row['cover'],'./uploads/images')
     */
    function base64_to_image($base64_image_content, $path): bool|string
    {
        // 匹配出图片的格式
        if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_image_content, $result)) {
            $type = $result[2];
            $new_file = $path.'/'.date('Ymd', time()).'/';
            if (! file_exists($new_file)) {
                // 检查是否有该文件夹，如果没有就创建，并给予最高权限
                create_dir_or_filepath($new_file);
            }
            $new_file = $new_file.md5(time().mt_rand(1, 1000000)).".{$type}";
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
    /*
     * 页面直接输出图片
     */
    #[NoReturn]
    function show_img($imgFile = ''): void
    {
        header('Content-type:image/png');
        exit(file_get_contents($imgFile));
    }
}

if (! function_exists('parse_files')) {
    // 解析文件上传数据
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
     *
     * @return array|mixed
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
     * @param  string  $string  需要转换的字符串
     * @param  string  $prefixString  拼接的前缀字符
     * @param  array  $linkAttr  需要转换的标签属性，例如：href、src、durl
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
     * @param  array  $array  二维数组
     * @param  array  $keys  键名数组
     * @param  bool  $onlyExists  是否只返回存在的键名对应的值
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
     * @param  Throwable  $exception  异常信息
     * @param  bool  $includeArgs  是否包含参数
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
     *
     * @throws Exception
     */
    function dict_convert_ten(string $string = '', int $fromBase = 16): int|string
    {
        return base_convert_any($string, $fromBase, 10);
    }
}

if (! function_exists('remove_str_emoji')) {
    // 移除字符串中的 emoji 表情
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
    // 判断字符串中是否含有 emoji 表情
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
     * @param  string  $dirname  目录
     * @param  bool  $delSelf  是否删除自身
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
     */
    function dir_is_empty(string $dir): bool
    {
        $res = false;
        if ($handle = opendir($dir)) {
            while (! $res && ($item = readdir($handle))) {
                if ($item != '.' && $item != '..') {
                    $res = true;
                }
            }
        }
        closedir($handle);

        return $res;
    }
}

if (! function_exists('create_dir')) {
    /**
     * 递归创建目录
     *
     * @param  string  $dir  目录
     * @param  int  $permissions  权限
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
     * @param  string  $path  文件夹或者文件路径
     */
    function create_dir_or_filepath(string $path = '', int $permissions = 0755): bool
    {
        // 如果路径不存在，则尝试创建它
        if (! file_exists($path)) {
            // 创建目录（如果不存在）
            $dir = dirname($path);
            if (! is_dir($dir) && ! mkdir($dir, $permissions, true) && ! is_dir($dir)) {
                // 创建文件夹失败
                return false;
            }
            // 如果不是现有目录，则尝试创建文件
            if (! is_dir($path) && ! touch($path)) {
                // 创建文件失败
                return false;
            }
        }

        // 路径已存在或成功创建
        return true;
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
     * @param  float|int|string  $number  需要转换的数字
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
     * 数字转换为英文
     *
     * @param  float|int|string  $number  需要转换的数字
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
     *
     * @DateTime 2017-06-28
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
            $str .= $chars[mt_rand(0, strlen($chars) - 1)];
        }

        return $str;
    }
}


if (! function_exists('download_url_file')) {
    /**
     * 下载url文件
     */
    #[NoReturn]
    function download_url_file($url = ''): void
    {
        $filename = ! empty($url) ? $url : (! empty($_GPC['url']) ? $_GPC['url'] : '');
        $title = substr($filename, strrpos($filename, '/') + 1);
        $file = fopen($filename, 'rb');
        header('Content-type:application/octet-stream');
        header('Accept-Ranges:bytes');
        header("Content-Disposition:attachment;filename=$title");
        while (! feof($file)) {
            echo fread($file, 8192);
            ob_flush();
            flush();
        }
        fclose($file);
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