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
                    throw new \RuntimeException('UUID sequence overflow after retries');
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

if (! function_exists('is_crawler')) {
    /**
     * 检测爬虫 - 增强版
     *
     * 优化内容：
     * 1. 更新爬虫列表
     * 2. 改进匹配算法
     * 3. 添加缓存机制
     * 4. 支持更多爬虫类型
     *
     * @param bool $returnName 是否返回爬虫名称
     * @param array $extendRules 自定义额外规则
     * @param bool $useCache 是否使用缓存
     * @return bool|string
     */
    function is_crawler(bool $returnName = false, array $extendRules = [], bool $useCache = true): bool|string
    {
        static $cache = [];

        $userAgent = is_laravel() ?
            (request()->userAgent() ?? '') :
            ($_SERVER['HTTP_USER_AGENT'] ?? '');

        if (empty($userAgent)) {
            return $returnName ? '' : false;
        }

        // 缓存检查
        $cacheKey = md5($userAgent . ($returnName ? '1' : '0'));
        if ($useCache && isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $crawlers = [
            // 主流搜索引擎爬虫
            'Googlebot' => 'Google Bot',
            'Google-InspectionTool' => 'Google Inspection Tool',
            'Bingbot' => 'Bing Bot',
            'Slurp' => 'Yahoo Slurp',
            'DuckDuckBot' => 'DuckDuckGo Bot',
            'Baiduspider' => 'Baidu Spider',
            'YandexBot' => 'Yandex Bot',
            'Sogou web spider' => 'Sogou Web Spider',
            'Sogou' => 'Sogou Spider',
            'Exabot' => 'ExaBot',
            'ia_archiver' => 'Alexa Bot',
            '360Spider' => '360 Search Bot',
            'SeznamBot' => 'Seznam Bot',
            'YisouSpider' => 'Yisou Spider',
            'Bytespider' => 'Byte Spider',
            'CCBot' => 'Common Crawler Bot',

            // AI 和数据分析爬虫
            'ChatGPT-User' => 'OpenAI ChatGPT',
            'Claude-Web' => 'Anthropic Claude',
            'cohere-ai' => 'Cohere AI',
            'YouBot' => 'You.com Bot',
            'PerplexityBot' => 'Perplexity AI Bot',

            // 新增国际搜索引擎
            'NaverBot' => 'Naver Bot',
            'Daumoa' => 'Daum Bot',
            'Teoma' => 'Teoma Bot',
            'AOLBuild' => 'AOL Bot',

            // 安全扫描器
            'Nmap' => 'Nmap Security Scanner',
            'Nessus' => 'Nessus Vulnerability Scanner',
            'Qualys' => 'Qualys Security Scanner',
            'Acunetix' => 'Acunetix Web Vulnerability Scanner',
            'BurpSuite' => 'Burp Suite Security Tool',

            // 新增数据抓取工具
            'Apache-HttpClient' => 'Apache HttpClient',
            'Go-http-client' => 'Go Http Client',
            'node-fetch' => 'Node.js Fetch',
            'python-requests' => 'Python Requests',
            'curl' => 'cURL',
            'wget' => 'Wget',

            // 社交媒体爬虫
            'facebookexternalhit' => 'Facebook Bot',
            'Twitterbot' => 'Twitter Bot',
            'LinkedInBot' => 'LinkedIn Bot',
            'Pinterest' => 'Pinterest Bot',
            'WhatsApp' => 'WhatsApp Bot',

            // 新增监控和性能工具
            'UptimeRobot' => 'Uptime Robot',
            'Pingdom' => 'Pingdom Bot',
            'NewRelicPinger' => 'New Relic Pinger',
            'DataDog' => 'Datadog Bot',

            // 通用爬虫标识
            'bot' => 'Generic Bot',
            'crawler' => 'Generic Crawler',
            'spider' => 'Generic Spider',
            'scraper' => 'Generic Scraper',
        ];

        if (!empty($extendRules)) {
            $crawlers = array_merge($crawlers, $extendRules);
        }

        // 改进的匹配逻辑
        $matchedCrawler = null;
        $crawlerName = '';

        foreach ($crawlers as $pattern => $name) {
            // 支持正则表达式
            if (str_starts_with($pattern, '/') && str_ends_with($pattern, '/')) {
                if (preg_match($pattern, $userAgent)) {
                    $matchedCrawler = $pattern;
                    $crawlerName = $name;
                    break;
                }
            }
            // 普通字符串匹配
            elseif (stripos($userAgent, $pattern) !== false) {
                $matchedCrawler = $pattern;
                $crawlerName = $name;
                break;
            }
        }

        if ($matchedCrawler !== null) {
            $result = $returnName ? $crawlerName : true;
            if ($useCache) {
                $cache[$cacheKey] = $result;
            }
            return $result;
        }

        $result = $returnName ? '' : false;
        if ($useCache) {
            $cache[$cacheKey] = $result;
        }
        return $result;
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
            throw new \InvalidArgumentException('图片文件不存在或路径错误');
        }

        // 获取图片信息
        $imageInfo = @getimagesize($imgFile);
        if ($imageInfo === false) {
            throw new \RuntimeException('无法读取图片信息');
        }

        $mimeType = $imageInfo['mime'];
        $extension = pathinfo($imgFile, PATHINFO_EXTENSION);

        // 根据 MIME 类型创建图片资源
        switch ($mimeType) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($imgFile);
                break;
            case 'image/png':
                $image = imagecreatefrompng($imgFile);
                break;
            case 'image/gif':
                $image = imagecreatefromgif($imgFile);
                break;
            case 'image/webp':
                $image = imagecreatefromwebp($imgFile);
                break;
            case 'image/bmp':
                $image = imagecreatefrombmp($imgFile);
                break;
            default:
                throw new \RuntimeException("不支持的图片格式: {$mimeType}");
        }

        if ($image === false) {
            throw new \RuntimeException('创建图片资源失败');
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
                throw new \RuntimeException('应用灰度滤镜失败');
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
                        switch ($mimeType) {
                            case 'image/jpeg':
                                $success = imagejpeg($image, $saveFile, $quality);
                                break;
                            case 'image/png':
                                $success = imagepng($image, $saveFile, (int)($quality / 10));
                                break;
                            case 'image/gif':
                                $success = imagegif($image, $saveFile);
                                break;
                            case 'image/webp':
                                $success = imagewebp($image, $saveFile, $quality);
                                break;
                            default:
                                $success = imagejpeg($image, $saveFile, $quality);
                        }
                }
            } else {
                // 直接输出到浏览器
                header('Content-Type: ' . $mimeType);
                switch ($mimeType) {
                    case 'image/jpeg':
                        $success = imagejpeg($image, null, $quality);
                        break;
                    case 'image/png':
                        $success = imagepng($image, null, (int)($quality / 10));
                        break;
                    case 'image/gif':
                        $success = imagegif($image);
                        break;
                    case 'image/webp':
                        $success = imagewebp($image, null, $quality);
                        break;
                    default:
                        $success = imagejpeg($image, null, $quality);
                }
            }

            imagedestroy($image);
            return $success;

        } catch (\Exception $e) {
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
            throw new \InvalidArgumentException("文件不存在: {$filePath}");
        }

        if (! is_file($filePath)) {
            throw new \InvalidArgumentException("路径不是文件: {$filePath}");
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
            throw new \InvalidArgumentException('文件大小不能为负数');
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
    function tree_to_array(
        array $array,
        string $childField = 'children',
        int $rootId = 0,
        string $keyField = 'id',
        string $pidField = 'pid',
        ?callable $callback = null
    ): array {
        $result = [];

        $flatten = function($node, $parentId) use (&$flatten, &$result, $childField, $keyField, $pidField, $callback) {
            // 处理当前节点
            $currentNode = $node;
            $currentNode[$pidField] = $parentId;

            // 确保有主键
            if (!isset($currentNode[$keyField])) {
                $currentNode[$keyField] = uniqid('node_', true);
            }

            // 回调处理
            if ($callback) {
                $currentNode = $callback($currentNode);
            }

            $children = $currentNode[$childField] ?? [];
            unset($currentNode[$childField]);

            $result[] = $currentNode;

            // 处理子节点
            foreach ($children as $child) {
                $flatten($child, $currentNode[$keyField]);
            }
        };

        foreach ($array as $node) {
            $flatten($node, $rootId);
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
        } catch (\Exception $e) {
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
        } catch (\Exception $e) {
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
        } catch (\Exception $err) {
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

        if (is_array($json) || (is_object($json) && !$json instanceof \JsonSerializable)) {
            return $json;
        }

        $jsonString = (string)$json;

        // 预处理: 去除BOM头
        $jsonString = preg_replace('/^\xEF\xBB\xBF/', '', $jsonString);

        // 检查字符串长度
        if (strlen($jsonString) > 1000000) { // 1MB限制
            throw new \RuntimeException('JSON字符串过长');
        }

        try {
            return json_decode($jsonString, $assoc, $depth, $flags);
        } catch (\JsonException $e) {
            // 尝试修复常见的JSON格式问题
            $cleaned = repairJson($jsonString);

            try {
                return json_decode($cleaned, $assoc, $depth, $flags);
            } catch (\JsonException $e) {
                // 记录错误日志
                error_log("JSON解码失败: " . $e->getMessage() . " | 原始数据: " . substr($jsonString, 0, 200));

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

if (! function_exists('json_string_to_array')) {
    // 判断一个字符串是否为json格式,并返回json数组
    function json_string_to_array($string)
    {
        if (is_array($string)) {
            return $string;
        }
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
                    throw new \Exception("第{$argIndex}个参数的类型不是指定的「{$paramName}」类型");
                }
                // 检查是否有默认值
                if ($parameter->isDefaultValueAvailable()) {
                    // 有默认值直接返回默认值
                    return $parameter->getDefaultValue();
                }
                // 没有默认参数的普通参数
                throw new \Exception("第{$argIndex}个参数「\${$parameter->getName()}」不能为空");
            }, $reflectionMethod->getParameters());

            // 2、 解析依赖注入对象
            $resolvedDependencies = array_map(function ($parameter) {
                // 如果参数是类名，则尝试解析依赖注入
                if (is_string($parameter) && class_exists($parameter)) {
                    // 如果是 Laravel 则使用 app 函数实例化，否则直接 new 一个类
                    return (function_exists('is_laravel') && is_laravel()) ? app($parameter) : new $parameter;
                }

                return $parameter;
            }, $dependencies);

            // 3、 通过反射 $method 方法并传入解析后的依赖注入对象或普通参数
            $reflectionMethod->invokeArgs($class, $resolvedDependencies);
        } catch (\ReflectionException $e) {
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
     * 数据流方式操作数据，不用等待操作结束才打印数据
     *
     * @param Closure $callback ($next)
     *                  示例：
     *                      $next() 执行下一个回调函数
     *                  1. 简单字符串输出
     *                      $next('string')输出普通文本
     *                  2. 带类型的消息输出
     *                      $next->info('信息消息') 等输出带样式的文本
     *                      $next->error('错误消息'); // 错误级别输出
     *                      $next->warning('警告消息'); // 警告级别输出
     *                      $next->success('成功消息'); // 成功级别输出
     *                  3. 多参数输出
     *                      $next('消息1', '消息2', '消息3');
     *                      $next->info('信息1', '信息2', '信息3');
     *                  4. 数组输出
     *                      $data=['name' => '张三','hobbies' => ['篮球', '音乐', '阅读']];
     *                      $next->info('用户数据:', $data);
     *                  5. 对象输出
     *                      $user = new stdClass();
     *                      $user->id = 1;
     *                      $user->username = 'admin';
     *                      $next->warning('用户对象:', $user);
     *                  6. 混合输出
     *                      $next('字符串:', 'Hello');
     *                      $next('数字:', 123.45);
     *                      $next('布尔值:', true);
     *                      $next('空值:', null);
     *                      $next('数组:', [1, 2, 3]);
     *
     * @throws Exception|Throwable
     */
    function stream_output(\Closure $callback): void
    {
        static $initialized = false; // 静态标记是否已初始化
        $isCli = PHP_SAPI === 'cli'; // 检测运行环境

        if (!$initialized) { // 首次调用初始化
            $initialized = true;

            if ($isCli) { // CLI环境信号处理
                if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
                    pcntl_async_signals(true); // 启用异步信号
                    pcntl_signal(SIGTERM, function () { // 注册终止信号处理
                        echo "进程被终止。\n";
                        exit;
                    });
                }
            } else { // Web环境设置
                ini_set('max_execution_time', '0'); // 无执行时间限制
                set_time_limit(0); // 无时间限制
                ignore_user_abort(true); // 忽略用户中断

                if (!headers_sent()) { // 如果头信息未发送
                    header('Content-Type: text/html; charset=UTF-8'); // 设置内容类型
                    header('Cache-Control: no-cache, no-store, must-revalidate'); // 禁用缓存
                    header('Pragma: no-cache'); // 兼容旧浏览器
                    header('Expires: 0'); // 立即过期
                    header('Connection: keep-alive'); // 保持连接
                    header('X-Accel-Buffering: no'); // 禁用Nginx缓冲

                    if (function_exists('apache_setenv')) {
                        @apache_setenv('no-gzip', '1'); // 禁用Apache压缩
                    }
                }
            }

            if (ob_get_level() > 0) ob_end_flush(); // 清除当前缓冲区
            ob_implicit_flush(true); // 启用隐式刷新
        }

        // 创建输出处理器
        $next = new class($isCli)
        {
            private bool $isCli; // 环境标识
            private bool $supportsColors; // 颜色支持
            private string $lineBreak; // 换行符

            // 颜色映射配置
            private const COLOR_MAP = [
                'info'    => ['cli' => "\033[36m", 'browser' => '#0099CC'], // 信息颜色
                'error'   => ['cli' => "\033[31m", 'browser' => '#FF3300'], // 错误颜色
                'warning' => ['cli' => "\033[33m", 'browser' => '#FF9900'], // 警告颜色
                'success' => ['cli' => "\033[32m", 'browser' => '#009900'], // 成功颜色
                'default' => ['cli' => "\033[37m", 'browser' => '#666666']  // 默认颜色
            ];

            public function __construct(bool $isCli)
            {
                $this->isCli = $isCli; // 设置环境
                $this->lineBreak = $isCli ? PHP_EOL : '<br>'; // 设置换行符
                $this->supportsColors = $this->checkColorSupport(); // 检测颜色支持
            }

            public function __invoke(...$data): void // 支持任意参数
            {
                $this->output($data); // 调用输出方法
            }

            public function __call(string $name, array $args): void
            {
                $this->output($args, $name); // 调用带类型的输出
            }

            /**
             * 刷新输出缓冲区（公开方法）
             */
            public function flush(): void
            {
                if (ob_get_level() > 0) ob_flush(); // 刷新输出缓冲区
                flush(); // 刷新系统缓冲区
                if (function_exists('usleep') && $this->isCli) usleep(1000); // CLI环境微延迟
            }

            /**
             * 统一输出处理
             */
            private function output(array $items, ?string $type = null): void
            {
                if (empty($items)) { // 空数据只刷新
                    $this->flush();
                    return;
                }

                $colorMap = self::COLOR_MAP[$type] ?? self::COLOR_MAP['default']; // 获取颜色配置

                foreach ($items as $item) { // 遍历所有数据项
                    $formatted = self::formatData($item); // 格式化数据
                    if ($this->isCli) { // CLI环境输出
                        echo $this->supportsColors ? $colorMap['cli'] . $formatted . "\033[0m" : $formatted; // 带颜色输出
                        echo $this->lineBreak; // 换行
                    } else { // 浏览器环境输出
                        echo '<span style="color: ' . $colorMap['browser'] . '; font-weight: bold; white-space: pre-wrap;">' .
                            htmlspecialchars($formatted, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8') .
                            '</span>' . $this->lineBreak; // HTML格式输出
                    }
                }

                $this->flush(); // 刷新缓冲区
            }

            /**
             * 格式化数据为可读字符串（静态方法）
             */
            public static function formatData($data): string
            {
                if (is_string($data)) return $data; // 字符串直接返回
                if (is_scalar($data) || is_null($data)) return var_export($data, true); // 标量数据转换

                if (is_array($data) || is_object($data)) { // 数组或对象
                    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); // JSON格式化
                    return $json !== false ? $json : '无法编码的数据'; // 返回JSON或错误信息
                }

                return '不支持的数据类型: ' . gettype($data); // 其他类型提示
            }

            /**
             * 检测终端颜色支持
             */
            private function checkColorSupport(): bool
            {
                if (!$this->isCli) return false; // 非CLI环境不支持

                if (DIRECTORY_SEPARATOR === '\\') { // Windows系统
                    return getenv('ANSICON') !== false ||  // ANSICON支持
                        getenv('ConEmuANSI') === 'ON' || // ConEmu支持
                        getenv('TERM') === 'xterm' || // xterm支持
                        (function_exists('sapi_windows_vt100_support') && @sapi_windows_vt100_support(STDOUT)); // VT100支持
                }

                return (function_exists('posix_isatty') && @posix_isatty(STDOUT)) || // Unix TTY检测
                    (($term = getenv('TERM')) !== false && // 环境变量检测
                        (stripos($term, 'color') !== false || stripos($term, 'xterm') !== false || stripos($term, 'vt100') !== false));
            }
        };

        try {
            $callback($next); // 执行用户回调
            $next->flush(); // 最终刷新
        } catch (\Throwable $e) { // 异常处理
            $message = $next::formatData($e->getMessage()); // 使用静态方法格式化错误信息
            if ($isCli) { // CLI错误输出
                echo "错误: " . $message . PHP_EOL;
            } else { // 浏览器错误输出
                echo '<span style="color: #FF3300; font-weight: bold;">错误: ' .
                    htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8') . '</span><br>';
            }
            $next->flush(); // 刷新输出
            throw $e; // 重新抛出
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
     * @throws \InvalidArgumentException 当输入参数无效时抛出
     * @throws \RuntimeException 当查询语法错误时抛出
     */
    function array_get(array $array, string $path, mixed $default = null, string $delimiter = '.'): mixed
    {
        if ($path === '') {
            return $default;
        }

        if (str_contains($delimiter, '*') || str_contains($delimiter, '?') ||
            str_contains($delimiter, '{') || str_contains($delimiter, '}')) {
            throw new \InvalidArgumentException('Delimiter cannot contain special characters');
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
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveArrayIterator($result),
                    \RecursiveIteratorIterator::SELF_FIRST
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
                create_dir($new_file);
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
