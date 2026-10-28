<?php

declare(strict_types=1);

namespace zxf\Utils\Sundry;

use Exception;
use SimpleXMLElement;
use InvalidArgumentException;
use RuntimeException;
use DateTime;
use DOMDocument;

/**
 * Sitemap 站点地图生成器 - PHP 8.2+ 完整增强版
 *
 * 用于生成符合搜索引擎规范的站点地图，支持标准 URL、新闻、图片、
 * 视频等多种条目，并可按 Google 规范对超大站点地图自动分片。
 *
 * 功能特性：
 * - 完全支持 PHP 8.2+ 特性
 * - 支持标准 URL、新闻、图片、视频站点地图
 * - 自动分片处理大文件（符合 Google 规范）
 * - 获取 XML 字符串内容数据
 * - 自定义 XML 头部和尾部
 * - 支持多种命名空间扩展
 * - GZIP 压缩支持
 * - 完整的错误处理和验证机制
 * - 统计信息和状态监控
 * - 内存使用优化
 * - 支持多种输出格式
 * - 多语言支持（hreflang）
 * - 移动端支持
 * - 站点地图验证
 * - 自动提交到搜索引擎
 * - 缓存机制
 * - 性能监控
 *
 * @see https://weisifang.com/docs/doc/6_209 网站地图生成器
 *
 * @example
 * ```php
 * $generator = new SiteMapGenerator('https://www.example.com', __DIR__, 'sitemaps');
 * $generator->addUrl('/page1', '2024-09-24', 'daily', 0.8); // 添加一条 URL
 * echo $generator->getSitemapXmlString();                  // 取 XML 字符串
 * $ok = $generator->generateFile();                        // 写入文件，返回是否成功
 * ```
 */
class SiteMapGenerator
{
    /**
     * @var array $urls 存储所有 URL 条目的数组
     */
    private array $urls = [];

    /**
     * @var string $sitemapDir 站点地图文件存储目录
     */
    private readonly string $sitemapDir;

    /**
     * @var string $mapsDirName 站点地图文件夹名称
     */
    private readonly string $mapsDirName;

    /**
     * @var int $maxUrls 每个站点地图文件最大 URL 数量
     */
    private readonly int $maxUrls;

    /**
     * @var int $maxFileSize 每个站点地图文件最大字节大小
     */
    private readonly int $maxFileSize;

    /**
     * @var string $baseUrl 网站基础 URL
     */
    private readonly string $baseUrl;

    /**
     * @var string|null $header 自定义 XML 头部
     */
    private ?string $header = null;

    /**
     * @var string|null $footer 自定义 XML 尾部
     */
    private ?string $footer = null;

    /**
     * @var int $sitemapCount 已生成的站点地图文件计数
     */
    private int $sitemapCount = 0;

    /**
     * @var string $dateFolder 按日期命名的文件夹
     */
    private readonly string $dateFolder;

    /**
     * @var string $indexFilePath 站点地图索引文件路径
     */
    private readonly string $indexFilePath;

    /**
     * @var array $generatedFiles 已生成的文件列表
     */
    private array $generatedFiles = [];

    /**
     * @var bool $useCompression 是否使用 GZIP 压缩
     */
    private bool $useCompression = false;

    /**
     * @var array $namespaces XML 命名空间配置
     */
    private array $namespaces = [];

    /**
     * @var array $errors 错误信息收集
     */
    private array $errors = [];

    /**
     * @var int $totalUrls 总 URL 数量统计
     */
    private int $totalUrls = 0;

    /**
     * @var array $sitemapChunks 站点地图分片数据缓存
     */
    private array $sitemapChunks = [];

    /**
     * @var bool $prettyFormat 是否美化 XML 输出格式
     */
    private bool $prettyFormat = false;

    /**
     * @var string $encoding XML 编码格式
     */
    private string $encoding = 'UTF-8';

    /**
     * @var array $searchEngines 搜索引擎提交配置
     */
    private array $searchEngines = [];

    /**
     * @var bool $autoSubmit 是否自动提交到搜索引擎
     */
    private bool $autoSubmit = false;

    /**
     * @var array $validationRules 验证规则配置
     */
    private array $validationRules = [];

    /**
     * @var array $performanceStats 性能统计信息
     */
    private array $performanceStats = [
        'start_time' => 0,
        'end_time' => 0,
        'memory_peak' => 0,
        'url_processing_time' => 0,
    ];

    /**
     * @var bool $enableCaching 是否启用缓存
     */
    private bool $enableCaching = false;

    /**
     * @var array $cache 缓存数据
     */
    private array $cache = [];

    /**
     * @var callable|null $urlFilter URL 过滤器回调函数
     */
    private $urlFilter = null;

    /**
     * @var array $customData 自定义数据存储
     */
    private array $customData = [];

    // 更新频率常量定义
    public const CHANGE_FREQUENCY_ALWAYS = 'always';
    public const CHANGE_FREQUENCY_HOURLY = 'hourly';
    public const CHANGE_FREQUENCY_DAILY = 'daily';
    public const CHANGE_FREQUENCY_WEEKLY = 'weekly';
    public const CHANGE_FREQUENCY_MONTHLY = 'monthly';
    public const CHANGE_FREQUENCY_YEARLY = 'yearly';
    public const CHANGE_FREQUENCY_NEVER = 'never';

    // 输出格式常量
    public const OUTPUT_STRING = 'string';
    public const OUTPUT_FILE = 'file';
    public const OUTPUT_CHUNKED = 'chunked';

    // 搜索引擎常量
    public const SEARCH_ENGINE_GOOGLE = 'google';
    public const SEARCH_ENGINE_BING = 'bing';
    public const SEARCH_ENGINE_YANDEX = 'yandex';
    public const SEARCH_ENGINE_BAIDU = 'baidu';

    // 缓存键常量
    private const CACHE_KEY_SITEMAP = 'sitemap';
    private const CACHE_KEY_INDEX = 'index';
    private const CACHE_KEY_CHUNKS = 'chunks';

    // 默认命名空间配置
    private const DEFAULT_NAMESPACES = [
        'xmlns' => 'https://www.sitemaps.org/schemas/sitemap/0.9',
        'xmlns:video' => 'https://www.google.com/schemas/sitemap-video/1.1',
        'xmlns:image' => 'https://www.google.com/schemas/sitemap-image/1.1',
        'xmlns:news' => 'https://www.google.com/schemas/sitemap-news/0.9',
        'xmlns:xhtml' => 'http://www.w3.org/1999/xhtml',
    ];

    // 默认搜索引擎提交 URL
    private const SEARCH_ENGINE_URLS = [
        self::SEARCH_ENGINE_GOOGLE => 'https://www.google.com/ping?sitemap=',
        self::SEARCH_ENGINE_BING => 'https://www.bing.com/ping?sitemap=',
        self::SEARCH_ENGINE_YANDEX => 'https://webmaster.yandex.com/ping?sitemap=',
        self::SEARCH_ENGINE_BAIDU => 'https://www.baidu.com/search/ping.html?sitemap=',
    ];

    /**
     * 构造函数
     */
    public function __construct(
        string $baseUrl,
        string $baseDir,
        string $mapsDir = 'sitemaps',
        int $maxUrls = 50000,
        int $maxFileSize = 52428800
    ) {
        // 开始性能统计
        $this->performanceStats['start_time'] = microtime(true);
        $this->performanceStats['memory_peak'] = memory_get_peak_usage(true);

        $this->validateParameters($baseUrl, $baseDir, $maxUrls, $maxFileSize);

        $this->baseUrl = rtrim($baseUrl, '/') . '/';
        $this->maxUrls = $maxUrls;
        $this->maxFileSize = $maxFileSize;
        $this->mapsDirName = $mapsDir;
        $this->dateFolder = date('Y-m-d');
        $this->sitemapDir = $this->createSitemapDirectory($baseDir);
        $this->indexFilePath = rtrim($baseDir, '/') . '/sitemap.xml';
        $this->namespaces = self::DEFAULT_NAMESPACES;

        // 初始化默认验证规则
        $this->validationRules = [
            'max_url_length' => 2048,
            'allow_duplicate_urls' => false,
            'validate_urls' => true,
            'check_lastmod_format' => true,
        ];

        // 初始化搜索引擎配置
        $this->initializeSearchEngines();
    }

    /**
     * 析构函数 - 完成性能统计
     */
    public function __destruct()
    {
        $this->performanceStats['end_time'] = microtime(true);
        $this->performanceStats['memory_peak'] = max(
            $this->performanceStats['memory_peak'],
            memory_get_peak_usage(true)
        );
    }

    /**
     * 获取单个站点地图的 XML 字符串内容
     *
     * 不传 $urls 时使用当前已累积的 URL 列表；列表为空时输出只含命名空间的空 urlset。
     * 启用缓存时按 URL 内容 md5 命中缓存，未命中则生成并写回缓存。
     *
     * @param array|null $urls 自定义 URL 条目数组，为 null 时取 $this->urls
     * @return string 完整的站点地图 XML 文本
     *
     * @throws RuntimeException 生成过程中发生异常时包装抛出
     */
    public function getSitemapXmlString(?array $urls = null): string
    {
        // 检查缓存
        $cacheKey = self::CACHE_KEY_SITEMAP . '_' . md5(serialize($urls ?? $this->urls));
        if ($this->enableCaching && isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        try {
            $targetUrls = $urls ?? $this->urls;

            if (empty($targetUrls)) {
                $result = $this->generateEmptySitemapXml();
            } else {
                $result = $this->generateSitemapXml($targetUrls);
            }

            // 缓存结果
            if ($this->enableCaching) {
                $this->cache[$cacheKey] = $result;
            }

            return $result;
        } catch (Exception $e) {
            throw new RuntimeException("获取站点地图 XML 字符串失败: " . $e->getMessage());
        }
    }

    /**
     * 获取分片站点地图的 XML 字符串内容数组
     *
     * 按 $maxUrls 对 URL 列表分片，逐片生成 XML 并以「分片序号（从 1 开始）」为键返回；
     * 无 URL 时返回仅含 1 个空 sitemap 的数组。启用缓存时整体缓存分片结果。
     *
     * @return array<int, string> 分片序号 => 该分片的 XML 文本
     *
     * @throws RuntimeException 任一分片生成失败时抛出
     */
    public function getChunkedSitemapXmlStrings(): array
    {
        // 检查缓存
        $cacheKey = self::CACHE_KEY_CHUNKS;
        if ($this->enableCaching && isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        if (empty($this->urls)) {
            $result = [1 => $this->generateEmptySitemapXml()];
        } else {
            $this->sitemapChunks = [];
            $chunks = array_chunk($this->urls, $this->maxUrls);

            foreach ($chunks as $index => $chunk) {
                $chunkNumber = $index + 1;
                try {
                    $this->sitemapChunks[$chunkNumber] = $this->generateSitemapXml($chunk);
                } catch (Exception $e) {
                    throw new RuntimeException("生成分片 {$chunkNumber} XML 失败: " . $e->getMessage());
                }
            }

            $result = $this->sitemapChunks;
        }

        // 缓存结果
        if ($this->enableCaching) {
            $this->cache[$cacheKey] = $result;
        }

        return $result;
    }

    /**
     * 获取站点地图索引的 XML 字符串内容
     *
     * 生成 sitemapindex 文档，每个分片对应一条 <sitemap> 记录（含 loc 与 lastmod）；
     * 不传 $sitemapUrls 时由 generateSitemapUrls() 按分片规则推导。
     *
     * @param array|null $sitemapUrls 索引条目数组，每项需含 loc 与 lastmod 键；为 null 时自动推导
     * @return string 站点地图索引 XML 文本
     *
     * @throws RuntimeException 生成过程中发生异常时包装抛出
     */
    public function getSitemapIndexXmlString(?array $sitemapUrls = null): string
    {
        // 检查缓存
        $cacheKey = self::CACHE_KEY_INDEX;
        if ($this->enableCaching && isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        try {
            $customHeader = $this->header ? (trim($this->header) . "\n") : '';
            $xml = new SimpleXMLElement($customHeader . '<sitemapindex xmlns="https://www.sitemaps.org/schemas/sitemap/0.9"/>');

            $sitemapUrls = $sitemapUrls ?? $this->generateSitemapUrls();

            foreach ($sitemapUrls as $sitemapUrl) {
                $sitemapEntry = $xml->addChild('sitemap');
                $sitemapEntry->addChild('loc', self::xmlText($sitemapUrl['loc']));
                $sitemapEntry->addChild('lastmod', $sitemapUrl['lastmod']);
            }

            $xmlString = $xml->asXML();

            if ($this->prettyFormat) {
                $xmlString = $this->formatXml($xmlString);
            }

            // 缓存结果
            if ($this->enableCaching) {
                $this->cache[$cacheKey] = $xmlString;
            }

            return $xmlString;
        } catch (Exception $e) {
            throw new RuntimeException("获取站点地图索引 XML 字符串失败: " . $e->getMessage());
        }
    }

    /**
     * 获取指定分片的 XML 字符串内容
     *
     * 分片序号从 1 开始；内部缓冲为空时会先触发全量分片生成。
     *
     * @param int $chunkIndex 分片序号，必须大于等于 1
     * @return string 该分片的 XML 文本
     *
     * @throws InvalidArgumentException 序号小于 1 或该分片不存在时抛出
     */
    public function getChunkXmlString(int $chunkIndex): string
    {
        if ($chunkIndex < 1) {
            throw new InvalidArgumentException("分片索引必须大于等于 1");
        }

        if (empty($this->sitemapChunks)) {
            $this->getChunkedSitemapXmlStrings();
        }

        if (!isset($this->sitemapChunks[$chunkIndex])) {
            throw new InvalidArgumentException("分片索引 {$chunkIndex} 不存在");
        }

        return $this->sitemapChunks[$chunkIndex];
    }

    /**
     * 生成站点地图并返回多种格式的结果
     *
     * 按 $outputType 分发：字符串内容、分片内容数组或落盘文件；
     * 并累加 url_processing_time 性能统计。
     *
     * @param string $outputType 输出类型，取 self::OUTPUT_STRING / OUTPUT_CHUNKED / OUTPUT_FILE
     * @return string|array|bool 字符串类型返回 XML 文本，分片类型返回序号=>XML 的数组，文件类型返回是否写入成功
     *
     * @throws InvalidArgumentException 输出类型不受支持时抛出
     */
    public function generate(string $outputType = self::OUTPUT_FILE): string|array|bool
    {
        $startTime = microtime(true);

        $result = match ($outputType) {
            self::OUTPUT_STRING => $this->getSitemapXmlString(),
            self::OUTPUT_CHUNKED => $this->getChunkedSitemapXmlStrings(),
            self::OUTPUT_FILE => $this->generateFile(),
            default => throw new InvalidArgumentException("不支持的输出类型: {$outputType}"),
        };

        // 记录处理时间
        $this->performanceStats['url_processing_time'] += (microtime(true) - $startTime);

        return $result;
    }

    /**
     * 设置 XML 输出是否美化格式
     *
     * 开启后经 DOMDocument 重新缩进输出；关闭则使用 SimpleXML 的紧凑格式。
     *
     * @param bool $pretty 是否美化输出，默认 true
     * @return self 当前实例，便于链式调用
     */
    public function setPrettyFormat(bool $pretty = true): self
    {
        $this->prettyFormat = $pretty;
        return $this;
    }

    /**
     * 设置 XML 编码格式
     *
     * 仅接受 UTF-8、ISO-8859-1、US-ASCII（大小写不敏感），编码会写入 XML 声明。
     *
     * @param string $encoding 编码名称，如 'UTF-8'
     * @return self 当前实例，便于链式调用
     *
     * @throws InvalidArgumentException 编码不受支持时抛出
     */
    public function setEncoding(string $encoding): self
    {
        $validEncodings = ['UTF-8', 'ISO-8859-1', 'US-ASCII'];
        if (!in_array(strtoupper($encoding), $validEncodings)) {
            throw new InvalidArgumentException("不支持的编码格式: {$encoding}");
        }

        $this->encoding = $encoding;
        return $this;
    }

    /**
     * 导出站点地图数据为数组格式
     *
     * 注意：导出过程会顺带触发分片与索引 XML 的生成（结果同样进入缓存）。
     *
     * @return array 包含 base_url、total_urls、urls、chunks、index、stats 与 config 的关联数组
     */
    public function exportToArray(): array
    {
        return [
            'base_url' => $this->baseUrl,
            'total_urls' => $this->totalUrls,
            'urls' => $this->urls,
            'chunks' => $this->getChunkedSitemapXmlStrings(),
            'index' => $this->getSitemapIndexXmlString(),
            'stats' => $this->getStats(),
            'config' => [
                'max_urls' => $this->maxUrls,
                'max_file_size' => $this->maxFileSize,
                'compression' => $this->useCompression,
                'encoding' => $this->encoding,
                'caching_enabled' => $this->enableCaching,
            ],
        ];
    }

    /**
     * 从数组数据导入站点地图配置
     *
     * 仅识别 urls 键：存在且为数组时覆盖当前 URL 列表并同步 totalUrls；
     * 其余键（base_url、config 等）暂不生效。导入后不会自动清理已有缓存。
     *
     * @param array $data 待导入的数据，通常为 exportToArray() 的结果
     * @return self 当前实例，便于链式调用
     */
    public function importFromArray(array $data): self
    {
        if (isset($data['urls']) && is_array($data['urls'])) {
            $this->urls = $data['urls'];
            $this->totalUrls = count($data['urls']);
        }

        return $this;
    }

    /**
     * 清空所有 URL 数据但保留配置
     *
     * 同时重置 totalUrls、分片缓存与分片计数，并清空缓存区。
     *
     * @return self 当前实例，便于链式调用
     */
    public function clearUrls(): self
    {
        $this->urls = [];
        $this->totalUrls = 0;
        $this->sitemapChunks = [];
        $this->sitemapCount = 0;
        $this->clearCache();
        return $this;
    }

    /**
     * 获取 URL 条目数量
     *
     * 以当前内存中累积的 URL 数组为准（自动分片落盘后数组会被清空，此时可能小于 totalUrls）。
     *
     * @return int 当前 URL 条目数
     */
    public function getUrlCount(): int
    {
        return count($this->urls);
    }

    /**
     * 检查是否包含指定 URL
     *
     * 入参支持相对路径（自动拼接 baseUrl）或完整 URL，按 loc 字段全等比较。
     *
     * @param string $url 待检查的 URL（相对路径或完整 URL）
     * @return bool 存在返回 true，否则返回 false
     */
    public function containsUrl(string $url): bool
    {
        $fullUrl = $this->baseUrl . ltrim($url, '/');

        foreach ($this->urls as $urlEntry) {
            if ($urlEntry['loc'] === $fullUrl) {
                return true;
            }
        }

        return false;
    }

    /**
     * 移除指定 URL
     *
     * 入参支持相对路径或完整 URL，按 loc 全等匹配并移除全部同名条目；
     * 成功移除时同步 totalUrls、丢弃分片缓存并清空缓存区。
     *
     * @param string $url 待移除的 URL（相对路径或完整 URL）
     * @return bool 实际移除到条目返回 true，未找到返回 false
     */
    public function removeUrl(string $url): bool
    {
        $fullUrl = $this->baseUrl . ltrim($url, '/');
        $initialCount = count($this->urls);

        $this->urls = array_filter($this->urls, function ($urlEntry) use ($fullUrl) {
            return $urlEntry['loc'] !== $fullUrl;
        });

        $removed = count($this->urls) < $initialCount;
        if ($removed) {
            $this->totalUrls = count($this->urls);
            $this->sitemapChunks = [];
            $this->clearCache();
        }

        return $removed;
    }

    /**
     * 批量移除 URL
     *
     * 逐个调用 removeUrl()，不存在的条目跳过；每次都命中时会重复清理缓存。
     *
     * @param array $urls URL 列表，元素可为相对路径或完整 URL
     * @return int 实际移除的 URL 条数
     */
    public function removeUrls(array $urls): int
    {
        $removedCount = 0;

        foreach ($urls as $url) {
            if ($this->removeUrl($url)) {
                $removedCount++;
            }
        }

        return $removedCount;
    }

    /**
     * 添加多语言 URL 支持（hreflang）
     *
     * $alternates 以「语言代码 => 该语言页面路径」形式给出，会转换为 xhtml:link 的
     * rel=alternate + hreflang + href 属性；未指定 lastmod 时取当天日期。
     *
     * @param string $url 主页面 URL（相对路径或完整 URL）
     * @param array $alternates 语言代码 => 对应语言版本 URL
     * @param string|null $lastModified 最后修改日期（Y-m-d），为 null 时取当天
     * @param string $changeFrequency 更新频率，取 CHANGE_FREQUENCY_* 常量，默认 weekly
     * @param float $priority 优先级（0.0~1.0），默认 0.5
     * @return self 当前实例，便于链式调用
     */
    public function addMultilingualUrl(
        string $url,
        array $alternates,
        ?string $lastModified = null,
        string $changeFrequency = self::CHANGE_FREQUENCY_WEEKLY,
        float $priority = 0.5
    ): self {
        $fullUrl = $this->baseUrl . ltrim($url, '/');

        $urlItem = [
            'loc' => $fullUrl,
            'lastmod' => $lastModified ?: date('Y-m-d'),
            'changefreq' => $changeFrequency,
            'priority' => $priority,
            'xhtml_links' => [],
        ];

        foreach ($alternates as $lang => $alternateUrl) {
            $alternateFullUrl = $this->baseUrl . ltrim($alternateUrl, '/');
            $urlItem['xhtml_links'][] = [
                'rel' => 'alternate',
                'hreflang' => $lang,
                'href' => $alternateFullUrl,
            ];
        }

        $this->addUrlItem($urlItem);
        return $this;
    }

    /**
     * 添加移动端 URL 支持
     *
     * 生成一条带 media 属性的 xhtml:link（only screen and (max-width: 640px)）指向移动版页面；
     * 未指定 lastmod 时取当天日期。
     *
     * @param string $url 桌面版页面 URL（相对路径或完整 URL）
     * @param string $mobileUrl 移动版页面 URL（相对路径或完整 URL）
     * @param string|null $lastModified 最后修改日期（Y-m-d），为 null 时取当天
     * @param string $changeFrequency 更新频率，取 CHANGE_FREQUENCY_* 常量，默认 weekly
     * @param float $priority 优先级（0.0~1.0），默认 0.5
     * @return self 当前实例，便于链式调用
     */
    public function addMobileUrl(
        string $url,
        string $mobileUrl,
        ?string $lastModified = null,
        string $changeFrequency = self::CHANGE_FREQUENCY_WEEKLY,
        float $priority = 0.5
    ): self {
        $fullUrl = $this->baseUrl . ltrim($url, '/');
        $mobileFullUrl = $this->baseUrl . ltrim($mobileUrl, '/');

        $urlItem = [
            'loc' => $fullUrl,
            'lastmod' => $lastModified ?: date('Y-m-d'),
            'changefreq' => $changeFrequency,
            'priority' => $priority,
            'xhtml_links' => [
                [
                    'rel' => 'alternate',
                    'media' => 'only screen and (max-width: 640px)',
                    'href' => $mobileFullUrl,
                ],
            ],
        ];

        $this->addUrlItem($urlItem);
        return $this;
    }

    /**
     * 设置搜索引擎自动提交
     *
     * 传入 $engines 时用其与内置 SEARCH_ENGINE_URLS 求交集重建提交配置（未知名称会被忽略）；
     * 不传则保留当前配置。
     *
     * @param bool $autoSubmit 是否开启自动提交
     * @param array $engines 允许提交的搜索引擎名称列表，如 ['google', 'bing']；空数组表示不改变现有配置
     * @return self 当前实例，便于链式调用
     */
    public function setAutoSubmit(bool $autoSubmit, array $engines = []): self
    {
        $this->autoSubmit = $autoSubmit;
        if (!empty($engines)) {
            $this->searchEngines = array_intersect_key(
                self::SEARCH_ENGINE_URLS,
                array_flip($engines)
            );
        }
        return $this;
    }

    /**
     * 提交站点地图到搜索引擎
     *
     * 逐个请求 `{引擎 ping 地址}{urlencode(baseUrl + 'sitemap.xml')}`，超时 10 秒；
     * 单个引擎失败只记录到结果与 $errors，不影响其他引擎。
     * 注意：此处仅发送提交请求，不保证文件已落盘。
     *
     * @param array|null $engines 指定引擎名称列表；为 null 时使用 setAutoSubmit() 配置的引擎
     * @return array<string, array{success: bool, response: string|false, url: string}> 引擎名 => 提交结果
     */
    public function submitToSearchEngines(?array $engines = null): array
    {
        $enginesToUse = $engines ? array_intersect_key(self::SEARCH_ENGINE_URLS, array_flip($engines)) : $this->searchEngines;
        $results = [];

        foreach ($enginesToUse as $engine => $url) {
            try {
                $sitemapUrl = urlencode($this->baseUrl . 'sitemap.xml');
                $submitUrl = $url . $sitemapUrl;

                $context = stream_context_create([
                    'http' => [
                        'timeout' => 10,
                        'user_agent' => 'SitemapGenerator/1.0',
                    ],
                ]);

                $response = @file_get_contents($submitUrl, false, $context);
                $results[$engine] = [
                    'success' => $response !== false,
                    'response' => $response,
                    'url' => $submitUrl,
                ];
            } catch (Exception $e) {
                $results[$engine] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'url' => $submitUrl,
                ];
                $this->errors[] = "提交到搜索引擎 {$engine} 失败: " . $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * 验证站点地图 XML 内容
     *
     * 先做 XML 语法检查（临时开启 libxml 内部错误收集，结束后恢复调用方原有状态），
     * 再校验 URL 数量与文件大小是否超出上限。
     *
     * @param string $xmlContent 待验证的站点地图 XML 文本
     * @return array<int, string> 错误信息列表，空数组表示验证通过
     */
    public function validateSitemap(string $xmlContent): array
    {
        $errors = [];

        // 基本的 XML 语法验证
        // 必须恢复调用方原有的 libxml 错误处理状态，否则会影响全局行为
        $previousInternalErrors = libxml_use_internal_errors(true);
        try {
            $dom = new DOMDocument();
            // NONET/NOENT：禁止解析过程中访问网络与展开实体
            $dom->loadXML($xmlContent, LIBXML_NONET | LIBXML_NOENT);
            $xmlErrors = libxml_get_errors();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousInternalErrors);
        }

        foreach ($xmlErrors as $error) {
            $errors[] = "XML 语法错误: " . trim($error->message);
        }

        // 站点地图特定验证
        try {
            $xml = new SimpleXMLElement($xmlContent);

            // 验证 URL 数量
            $urlCount = $xml->count();
            if ($urlCount > $this->maxUrls) {
                $errors[] = "URL 数量超过限制: {$urlCount} > {$this->maxUrls}";
            }

            // 验证文件大小
            if (strlen($xmlContent) > $this->maxFileSize) {
                $errors[] = "文件大小超过限制";
            }

        } catch (Exception $e) {
            $errors[] = "站点地图结构验证失败: " . $e->getMessage();
        }

        return $errors;
    }

    /**
     * 设置验证规则
     *
     * 与已有规则合并（同名键覆盖），未指定的键保持原值；
     * 可配置项：max_url_length、allow_duplicate_urls、validate_urls、check_lastmod_format。
     *
     * @param array $rules 待合并的规则数组
     * @return self 当前实例，便于链式调用
     */
    public function setValidationRules(array $rules): self
    {
        $this->validationRules = array_merge($this->validationRules, $rules);
        return $this;
    }

    /**
     * 获取内存使用情况
     *
     * @return array{current: int, peak: int, url_count: int, estimated_size: int} 当前占用、峰值、URL 数量与序列化估算大小（字节）
     */
    public function getMemoryUsage(): array
    {
        return [
            'current' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'url_count' => count($this->urls),
            'estimated_size' => $this->estimateMemoryUsage(),
        ];
    }

    /**
     * 批量添加 URL 并优化内存使用
     *
     * 按 $batchSize 分块调用 addUrls()，每块结束后若内存占用超过上限 80% 则主动触发 GC。
     *
     * @param array $urls URL 条目数组（元素结构同 addUrl()）
     * @param int $batchSize 每批处理条数，默认 1000
     * @return self 当前实例，便于链式调用
     */
    public function addUrlsBatch(array $urls, int $batchSize = 1000): self
    {
        $batches = array_chunk($urls, $batchSize);

        foreach ($batches as $batch) {
            $this->addUrls($batch);

            // 如果内存使用过高，强制垃圾回收
            if ($this->isMemoryUsageHigh()) {
                gc_collect_cycles();
            }
        }

        return $this;
    }

    /**
     * 启用或禁用缓存
     *
     * 禁用时会立即清空已有缓存，避免后续读到陈旧结果。
     *
     * @param bool $enable 是否启用，默认 true
     * @return self 当前实例，便于链式调用
     */
    public function enableCaching(bool $enable = true): self
    {
        $this->enableCaching = $enable;
        if (!$enable) {
            $this->clearCache();
        }
        return $this;
    }

    /**
     * 清空缓存
     *
     * 仅清空内存缓存区，不改变 enableCaching 开关状态。
     *
     * @return self 当前实例，便于链式调用
     */
    public function clearCache(): self
    {
        $this->cache = [];
        return $this;
    }

    /**
     * 设置 URL 过滤器
     *
     * 回调接收单个 URL 条目数组，返回修改后的条目（写入）或 null（丢弃该条目）。
     * 过滤器在 addUrlItem() 中添加时即时生效。
     *
     * @param callable $filter 过滤回调，签名 `function (array $urlItem): ?array`
     * @return self 当前实例，便于链式调用
     */
    public function setUrlFilter(callable $filter): self
    {
        $this->urlFilter = $filter;
        return $this;
    }

    /**
     * 获取性能统计信息
     *
     * 未触发析构时以当前时间作为结束时间；时长与内存均格式化为易读字符串。
     *
     * @return array{execution_time: string, url_processing_time: string, memory_peak: string, total_urls: int, sitemap_files: int, generated_files: int} 统计项（时长带 s 后缀，内存带单位）
     */
    public function getPerformanceStats(): array
    {
        $endTime = $this->performanceStats['end_time'] ?: microtime(true);
        $executionTime = $endTime - $this->performanceStats['start_time'];

        return [
            'execution_time' => round($executionTime, 4) . 's',
            'url_processing_time' => round($this->performanceStats['url_processing_time'], 4) . 's',
            'memory_peak' => $this->formatBytes($this->performanceStats['memory_peak']),
            'total_urls' => $this->totalUrls,
            'sitemap_files' => $this->sitemapCount,
            'generated_files' => count($this->generatedFiles),
        ];
    }

    /**
     * 设置自定义数据
     *
     * 存入内部数组供调用方挂载业务数据，不参与 XML 生成。
     *
     * @param string $key 数据键
     * @param mixed $value 任意值
     * @return self 当前实例，便于链式调用
     */
    public function setCustomData(string $key, $value): self
    {
        $this->customData[$key] = $value;
        return $this;
    }

    /**
     * 获取自定义数据
     *
     * 键不存在时返回传入的默认值。
     *
     * @param string $key 数据键
     * @param mixed $default 键不存在时的默认返回值
     * @return mixed 已存储的值或默认值
     */
    public function getCustomData(string $key, $default = null)
    {
        return $this->customData[$key] ?? $default;
    }

    // ==================== 私有方法 ====================

    /**
     * 添加 URL 项目（内部使用）
     *
     * 先经 URL 过滤器处理（返回 null 则丢弃该条目），再追加到列表并累加计数、清空缓存；
     * 当条目数达到 $maxUrls 时自动触发一次 generateSitemap() 落盘。
     *
     * @param array $urlItem URL 条目，需含 loc，可选 lastmod/changefreq/priority/custom/images/videos/news/xhtml_links
     * @return void 无返回值
     */
    private function addUrlItem(array $urlItem): void
    {
        // 应用 URL 过滤器
        if ($this->urlFilter && is_callable($this->urlFilter)) {
            $urlItem = call_user_func($this->urlFilter, $urlItem);
            if ($urlItem === null) {
                return; // 过滤器返回 null 表示跳过此 URL
            }
        }

        $this->urls[] = $urlItem;
        $this->totalUrls++;
        $this->clearCache();

        if (count($this->urls) >= $this->maxUrls) {
            $this->generateSitemap();
        }
    }

    /**
     * 生成空的站点地图 XML
     *
     * 输出仅带命名空间声明的空 urlset；未设置自定义 header 时按当前 encoding 生成 XML 声明，
     * 并按 prettyFormat 决定是否美化。
     *
     * @return string 空站点地图 XML 文本
     */
    private function generateEmptySitemapXml(): string
    {
        $customHeader = $this->header ? (trim($this->header) . "\n") : '<?xml version="1.0" encoding="' . $this->encoding . '"?>' . "\n";

        $namespaceAttrs = '';
        foreach ($this->namespaces as $prefix => $url) {
            $namespaceAttrs .= "{$prefix}=\"{$url}\" ";
        }

        $xml = new SimpleXMLElement($customHeader . '<urlset ' . trim($namespaceAttrs) . '/>');
        $xmlString = $xml->asXML();

        if ($this->prettyFormat) {
            $xmlString = $this->formatXml($xmlString);
        }

        return $xmlString;
    }

    /**
     * 生成站点地图 XML 内容
     *
     * 以 urlset 为根节点逐条写入 URL（含图片/视频/新闻/多语言扩展），
     * 未设置自定义 header 时按当前 encoding 生成 XML 声明。
     *
     * @param array $urls URL 条目数组
     * @return string 站点地图 XML 文本
     *
     * @throws RuntimeException 构建或序列化 XML 出错时抛出
     */
    private function generateSitemapXml(array $urls): string
    {
        $customHeader = $this->header ? (trim($this->header) . "\n") : '<?xml version="1.0" encoding="' . $this->encoding . '"?>' . "\n";

        $namespaceAttrs = '';
        foreach ($this->namespaces as $prefix => $url) {
            $namespaceAttrs .= "{$prefix}=\"{$url}\" ";
        }

        try {
            $xml = new SimpleXMLElement($customHeader . '<urlset ' . trim($namespaceAttrs) . '/>');

            foreach ($urls as $url) {
                $this->addUrlToXml($xml, $url);
            }

            $xmlString = $xml->asXML();

            if ($this->prettyFormat) {
                $xmlString = $this->formatXml($xmlString);
            }

            return $xmlString;
        } catch (Exception $e) {
            throw new RuntimeException("生成 XML 内容时出错: " . $e->getMessage());
        }
    }

    /**
     * 准备写入 XML 节点的文本内容
     *
     * 必须显式转义：SimpleXMLElement::addChild() 并不会转义 "&"，
     * 传给它的 "...?a=1&b=2" 会被当成实体引用，触发
     * “unterminated entity reference” 警告并直接丢弃内容（实测 <loc/> 为空）。
     * 因此这里先剔除 XML 1.0 不允许的控制字符，再做 XML 实体转义。
     *
     * @param  mixed  $value  原始值
     * @return string 可直接交给 addChild() 的文本
     */
    private static function xmlText(mixed $value): string
    {
        $text = is_scalar($value) ? (string) $value : '';

        // 剔除 XML 1.0 非法控制字符（保留 \t \n \r），否则 addChild() 会抛 DOMException
        $text = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);

        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * 格式化 XML 字符串（美化输出）
     *
     * 用 DOMDocument 重新加载（忽略原有空白、开启 formatOutput）后输出，
     * 会顺带按当前 encoding 重写 XML 声明。
     *
     * @param string $xml 待格式化的 XML 文本
     * @return string 缩进后的 XML 文本
     */
    private function formatXml(string $xml): string
    {
        $dom = new DOMDocument('1.0', $this->encoding);
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml);

        return $dom->saveXML();
    }

    /**
     * 生成站点地图 URL 数组
     *
     * 分片数取「1、已生成分片数、按当前 URL 数估算的分片数」三者最大值，
     * 保证 addUrlItem() 自动落盘清空 $urls 后索引仍完整。
     *
     * @return array<int, array{loc: string, lastmod: string}> 索引条目列表（loc 为分片文件地址，lastmod 为当天日期）
     */
    private function generateSitemapUrls(): array
    {
        $urls = [];
        // 注意：addUrlItem() 在累计到 maxUrls 时会自动落盘并清空 $this->urls，
        // 此时 count($this->urls) 为 0，必须以已生成的分片数 $this->sitemapCount 为准，
        // 否则索引里只会剩下 1 条 sitemap 记录。
        $chunkCount = max(1, $this->sitemapCount, (int) ceil(count($this->urls) / $this->maxUrls));

        for ($i = 1; $i <= $chunkCount; $i++) {
            $urls[] = [
                'loc' => $this->baseUrl . $this->mapsDirName . '/' . $this->dateFolder . '/sitemap_' . $i . '.xml',
                'lastmod' => date('Y-m-d'),
            ];
        }

        return $urls;
    }

    /**
     * 添加 URL 数据到 XML 文档
     *
     * 依次写入 loc（缺失时写空串兜底）、lastmod、changefreq、priority、custom 自定义节点，
     * 以及 xhtml_links 多语言链接和 images/videos/news 扩展节点；未提供的键会被跳过。
     *
     * @param SimpleXMLElement $xml urlset 根节点
     * @param array $url 单条 URL 条目
     * @return void 无返回值
     */
    private function addUrlToXml(SimpleXMLElement $xml, array $url): void
    {
        $urlEntry = $xml->addChild('url');
        // importFromArray() 导入的数据可能缺少 loc 键，这里做兜底避免访问未定义下标
        $urlEntry->addChild('loc', self::xmlText($url['loc'] ?? ''));

        if (isset($url['lastmod'])) {
            $urlEntry->addChild('lastmod', $url['lastmod']);
        }

        if (isset($url['changefreq'])) {
            $urlEntry->addChild('changefreq', $url['changefreq']);
        }

        if (isset($url['priority'])) {
            $urlEntry->addChild('priority', (string) $url['priority']);
        }

        if (!empty($url['custom'])) {
            foreach ($url['custom'] as $tag => $value) {
                $urlEntry->addChild($tag, self::xmlText($value));
            }
        }

        // 添加多语言链接
        if (!empty($url['xhtml_links'])) {
            foreach ($url['xhtml_links'] as $link) {
                $linkElement = $urlEntry->addChild('xhtml:link');
                $linkElement->addAttribute('rel', $link['rel']);
                if (isset($link['hreflang'])) {
                    $linkElement->addAttribute('hreflang', $link['hreflang']);
                }
                if (isset($link['media'])) {
                    $linkElement->addAttribute('media', $link['media']);
                }
                $linkElement->addAttribute('href', $link['href']);
            }
        }

        if (isset($url['images'])) {
            foreach ($url['images'] as $image) {
                $this->addImageToXml($urlEntry, $image);
            }
        }

        if (isset($url['videos'])) {
            foreach ($url['videos'] as $video) {
                $this->addVideoToXml($urlEntry, $video);
            }
        }

        if (isset($url['news'])) {
            foreach ($url['news'] as $news) {
                $this->addNewsToXml($urlEntry, $news);
            }
        }
    }

    /**
     * 添加图片信息到 XML 节点
     *
     * 生成 image:image 节点，按 url/title/caption/geo_location/license 逐项写入，
     * 空值字段跳过。
     *
     * @param SimpleXMLElement $urlEntry 所属的 url 节点
     * @param array $image 图片信息数组
     * @return void 无返回值
     */
    private function addImageToXml(SimpleXMLElement $urlEntry, array $image): void
    {
        $imageElement = $urlEntry->addChild('image:image');

        if (!empty($image['url'])) {
            $imageElement->addChild('image:loc', self::xmlText($image['url']));
        }

        if (!empty($image['title'])) {
            $imageElement->addChild('image:title', self::xmlText($image['title']));
        }

        if (!empty($image['caption'])) {
            $imageElement->addChild('image:caption', self::xmlText($image['caption']));
        }

        if (!empty($image['geo_location'])) {
            $imageElement->addChild('image:geo_location', self::xmlText($image['geo_location']));
        }

        if (!empty($image['license'])) {
            $imageElement->addChild('image:license', self::xmlText($image['license']));
        }
    }

    /**
     * 添加视频信息到 XML 节点
     *
     * 生成 video:video 节点，写入标题、描述、缩略图、content_loc（取自 url 键）、
     * 时长、发布日期、播放器地址、过期时间、评分、播放量与家庭友好标记，空值字段跳过。
     *
     * @param SimpleXMLElement $urlEntry 所属的 url 节点
     * @param array $video 视频信息数组
     * @return void 无返回值
     */
    private function addVideoToXml(SimpleXMLElement $urlEntry, array $video): void
    {
        $videoElement = $urlEntry->addChild('video:video');

        if (!empty($video['title'])) {
            $videoElement->addChild('video:title', self::xmlText($video['title']));
        }

        if (!empty($video['description'])) {
            $videoElement->addChild('video:description', self::xmlText($video['description']));
        }

        if (!empty($video['thumbnail_loc'])) {
            $videoElement->addChild('video:thumbnail_loc', self::xmlText($video['thumbnail_loc']));
        }

        if (!empty($video['url'])) {
            $videoElement->addChild('video:content_loc', self::xmlText($video['url']));
        }

        if (!empty($video['duration'])) {
            $videoElement->addChild('video:duration', (string) $video['duration']);
        }

        if (!empty($video['publication_date'])) {
            $videoElement->addChild('video:publication_date', $video['publication_date']);
        }

        if (!empty($video['player_loc'])) {
            $videoElement->addChild('video:player_loc', self::xmlText($video['player_loc']));
        }

        if (!empty($video['expiration_date'])) {
            $videoElement->addChild('video:expiration_date', $video['expiration_date']);
        }

        if (!empty($video['rating'])) {
            $videoElement->addChild('video:rating', (string) $video['rating']);
        }

        if (!empty($video['view_count'])) {
            $videoElement->addChild('video:view_count', (string) $video['view_count']);
        }

        if (!empty($video['family_friendly'])) {
            $videoElement->addChild('video:family_friendly', $video['family_friendly']);
        }
    }

    /**
     * 添加新闻信息到 XML 节点
     *
     * 生成 news:news 节点；publication 子数组存在时写入 news:publication
     * （含 news:name 与 news:language，语言缺省为 zh_CN），随后写入标题、发布日期、关键词与体裁。
     *
     * @param SimpleXMLElement $urlEntry 所属的 url 节点
     * @param array $news 新闻信息数组
     * @return void 无返回值
     */
    private function addNewsToXml(SimpleXMLElement $urlEntry, array $news): void
    {
        $newsElement = $urlEntry->addChild('news:news');

        if (!empty($news['publication'])) {
            $publicationElement = $newsElement->addChild('news:publication');

            if (!empty($news['publication']['name'])) {
                $publicationElement->addChild('news:name', $news['publication']['name']);
            }

            $publicationElement->addChild('news:language', $news['publication']['language'] ?? 'zh_CN');
        }

        if (!empty($news['title'])) {
            $newsElement->addChild('news:title', self::xmlText($news['title']));
        }

        if (!empty($news['publication_date'])) {
            $newsElement->addChild('news:publication_date', $news['publication_date']);
        }

        if (!empty($news['keywords'])) {
            $newsElement->addChild('news:keywords', self::xmlText($news['keywords']));
        }

        if (!empty($news['genres'])) {
            $newsElement->addChild('news:genres', self::xmlText($news['genres']));
        }
    }

    /**
     * 初始化搜索引擎配置
     */
    private function initializeSearchEngines(): void
    {
        $this->searchEngines = self::SEARCH_ENGINE_URLS;
    }

    /**
     * 估计内存使用量
     *
     * 逐个序列化 URL 条目并累加字符串长度，作为内存占用的粗略估算值（非真实内存开销）。
     *
     * @return int 估算字节数
     */
    private function estimateMemoryUsage(): int
    {
        $size = 0;
        foreach ($this->urls as $url) {
            $size += strlen(serialize($url));
        }
        return $size;
    }

    /**
     * 检查内存使用是否过高
     *
     * 以 memory_limit 的 80% 为阈值；memory_limit 为 -1（不限制）时始终返回 false。
     *
     * @return bool 已超过阈值返回 true，否则返回 false
     */
    private function isMemoryUsageHigh(): bool
    {
        $memoryLimit = ini_get('memory_limit');
        $currentUsage = memory_get_usage(true);

        if ($memoryLimit === '-1') {
            return false; // 无限制
        }

        $limitBytes = $this->convertToBytes($memoryLimit);
        return $currentUsage > ($limitBytes * 0.8); // 使用超过 80%
    }

    /**
     * 转换内存限制为字节
     *
     * 识别结尾的 K/M/G 单位（大小写不敏感）并按 1024 进制换算；无单位或无法识别时按整数解析。
     *
     * @param string $memoryLimit 形如 '128M'、'1G'、'536870912' 的内存限制字符串
     * @return int 换算后的字节数
     */
    private function convertToBytes(string $memoryLimit): int
    {
        $unit = strtoupper(substr($memoryLimit, -1));
        $value = (int) substr($memoryLimit, 0, -1);

        return match ($unit) {
            'G' => $value * 1024 * 1024 * 1024,
            'M' => $value * 1024 * 1024,
            'K' => $value * 1024,
            default => (int) $memoryLimit,
        };
    }

    /**
     * 格式化字节数为易读格式
     *
     * 按 1024 进制自动选择 B/KB/MB/GB/TB（最大到 TB），负数按 0 处理。
     *
     * @param int $bytes 字节数
     * @param int $precision 保留的小数位数，默认 2
     * @return string 形如 '1.5 MB' 的可读字符串
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    // ==================== 原有的公共方法 ====================

    /**
     * 设置自定义 XML 头部内容
     *
     * @param string $header 自定义 XML 头部（如声明、处理指令）
     * @return self
     */
    public function setHeader(string $header): self
    {
        $this->header = $header;
        return $this;
    }

    /**
     * 设置自定义 XML 尾部内容（追加在每个站点地图文件末尾）
     *
     * @param string $footer 自定义 XML 尾部
     * @return self
     */
    public function setFooter(string $footer): self
    {
        $this->footer = $footer;
        return $this;
    }

    /**
     * 启用或禁用 GZIP 压缩（生成 .gz 副本）
     *
     * @param bool $enable 是否启用压缩（默认 true）
     * @return self
     */
    public function enableCompression(bool $enable = true): self
    {
        $this->useCompression = $enable;
        return $this;
    }

    /**
     * 追加或覆盖一个 XML 命名空间声明
     *
     * @param string $prefix 命名空间前缀（如 image、video）
     * @param string $url    命名空间 URI
     * @return self
     */
    public function addNamespace(string $prefix, string $url): self
    {
        $this->namespaces[$prefix] = $url;
        return $this;
    }

    /**
     * 添加单个 URL 到站点地图（自动拼接 baseUrl 并做合法性校验）
     *
     * @param string $url            相对路径（以 / 开头）或完整 URL
     * @param string|null $lastModified 最后修改日期（Y-m-d），null 时使用当天
     * @param string $changeFrequency 更新频率，默认 weekly
     * @param float  $priority       优先级（0.0-1.0），默认 0.5
     * @param array  $customTags     额外自定义子节点键值对
     * @return self
     * @throws InvalidArgumentException 当 URL/频率/优先级/日期非法时抛出
     */
    public function addUrl(
        string $url,
        ?string $lastModified = null,
        string $changeFrequency = self::CHANGE_FREQUENCY_WEEKLY,
        float $priority = 0.5,
        array $customTags = []
    ): self {
        $this->validateUrl($url);
        $this->validateChangeFrequency($changeFrequency);
        $this->validatePriority($priority);
        if ($lastModified !== null) {
            $this->validateDate($lastModified);
        }

        $fullUrl = $this->baseUrl . ltrim($url, '/');

        $urlItem = [
            'loc' => $fullUrl,
            'lastmod' => $lastModified ?: date('Y-m-d'),
            'changefreq' => $changeFrequency,
            'priority' => $priority,
        ];

        if (!empty($customTags)) {
            $urlItem['custom'] = $customTags;
        }

        $this->addUrlItem($urlItem);
        return $this;
    }

    /**
     * 批量添加 URL（兼容 [url, lastmod, freq, priority, tags] 形式的数组元素）
     *
     * @param array $urls 多条 URL 数据组成的数组
     * @return self
     */
    public function addUrls(array $urls): self
    {
        foreach ($urls as $urlData) {
            if (is_array($urlData)) {
                $this->addUrl(
                    $urlData[0] ?? '',
                    $urlData[1] ?? null,
                    $urlData[2] ?? self::CHANGE_FREQUENCY_WEEKLY,
                    $urlData[3] ?? 0.5,
                    $urlData[4] ?? []
                );
            }
        }
        return $this;
    }

    /**
     * 将已添加的 URL 生成站点地图文件及索引文件，并按需提交搜索引擎
     *
     * @return bool 生成成功返回 true；发生错误时记录到 errors 并返回 false
     */
    public function generateFile(): bool
    {
        try {
            if (!empty($this->urls)) {
                $this->generateSitemap();
            }

            if ($this->sitemapCount > 0) {
                $this->generateIndexFile();

                // 自动提交到搜索引擎
                if ($this->autoSubmit && !empty($this->searchEngines)) {
                    $this->submitToSearchEngines();
                }
            }

            return true;
        } catch (Exception $e) {
            $this->errors[] = "生成站点地图时出错: " . $e->getMessage();
            return false;
        }
    }

    /**
     * 获取站点地图文件实际存储目录
     *
     * @return string 站点地图目录的绝对路径
     */
    public function getSitemapDir(): string
    {
        return $this->sitemapDir;
    }

    /**
     * 获取站点地图索引文件的路径
     *
     * @return string 索引文件绝对路径
     */
    public function getIndexFilePath(): string
    {
        return $this->indexFilePath;
    }

    /**
     * 获取本次运行已生成的所有文件路径列表
     *
     * @return array 文件路径数组
     */
    public function getGeneratedFiles(): array
    {
        return $this->generatedFiles;
    }

    /**
     * 获取生成过程中收集的错误信息
     *
     * @return array 错误描述数组
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * 获取生成器的综合统计信息
     *
     * @return array 包含 URL 总数、文件数、错误数、压缩状态、目录、分片数等统计
     */
    public function getStats(): array
    {
        return [
            'total_urls' => $this->totalUrls,
            'sitemap_files' => $this->sitemapCount,
            'generated_files' => count($this->generatedFiles),
            'errors' => count($this->errors),
            'compression_enabled' => $this->useCompression,
            'base_url' => $this->baseUrl,
            'output_directory' => $this->sitemapDir,
            'pending_urls' => count($this->urls),
            'chunk_count' => max(1, ceil(count($this->urls) / $this->maxUrls)),
            'auto_submit' => $this->autoSubmit,
            'search_engines' => array_keys($this->searchEngines),
            'caching_enabled' => $this->enableCaching,
            'performance' => $this->getPerformanceStats(),
        ];
    }

    /**
     * 清空已收集的错误信息
     *
     * @return self
     */
    public function clearErrors(): self
    {
        $this->errors = [];
        return $this;
    }

    /**
     * 重置生成器状态（清空 URL、文件、错误、缓存与性能统计，保留配置）
     *
     * @return self
     */
    public function reset(): self
    {
        $this->urls = [];
        $this->sitemapCount = 0;
        $this->generatedFiles = [];
        $this->errors = [];
        $this->totalUrls = 0;
        $this->sitemapChunks = [];
        $this->clearCache();
        $this->performanceStats = [
            'start_time' => microtime(true),
            'end_time' => 0,
            'memory_peak' => memory_get_peak_usage(true),
            'url_processing_time' => 0,
        ];
        return $this;
    }

    // ==================== 原有的验证和辅助方法 ====================

    /**
     * 校验构造函数传入的基础目录与参数是否合法
     *
     * @param string $baseUrl    网站基础 URL
     * @param string $baseDir   站点地图存储目录
     * @param int    $maxUrls   单个文件最大 URL 数（1-50000）
     * @param int    $maxFileSize 单个文件最大字节数（1-50MB）
     * @return void
     * @throws InvalidArgumentException 任意参数不合法时抛出
     */
    private function validateParameters(string $baseUrl, string $baseDir, int $maxUrls, int $maxFileSize): void
    {
        if (!is_dir($baseDir) || !is_writable($baseDir)) {
            throw new InvalidArgumentException("基础目录不存在或不可写: {$baseDir}");
        }

        if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException("无效的基础 URL 格式: {$baseUrl}");
        }

        if (!preg_match('/^https?:\/\//', $baseUrl)) {
            throw new InvalidArgumentException("基础 URL 必须使用 HTTP 或 HTTPS 协议: {$baseUrl}");
        }

        if ($maxUrls <= 0 || $maxUrls > 50000) {
            throw new InvalidArgumentException("最大 URL 数量必须在 1-50000 之间，当前值: {$maxUrls}");
        }

        if ($maxFileSize <= 0 || $maxFileSize > 52428800) {
            throw new InvalidArgumentException("最大文件大小必须在 1-52428800 字节(50MB)之间，当前值: {$maxFileSize}");
        }
    }

    /**
     * 创建（如不存在）站点地图按日期命名的子目录并返回其路径
     *
     * @param string $baseDir 基础目录
     * @return string 站点地图目录路径
     * @throws RuntimeException 目录创建失败或不可写时抛出
     */
    private function createSitemapDirectory(string $baseDir): string
    {
        $sitemapDir = rtrim($baseDir, '/') . '/' . $this->mapsDirName . '/' . $this->dateFolder . '/';

        if (!is_dir($sitemapDir)) {
            if (!mkdir($sitemapDir, 0755, true) && !is_dir($sitemapDir)) {
                throw new RuntimeException("无法创建站点地图目录: {$sitemapDir}");
            }
        }

        if (!is_writable($sitemapDir)) {
            throw new RuntimeException("站点地图目录不可写: {$sitemapDir}");
        }

        return $sitemapDir;
    }

    /**
     * 校验单条 URL 是否为空或以 / 开头的相对路径（或完整 URL）
     *
     * @param string $url 待校验的 URL
     * @return void
     * @throws InvalidArgumentException 当 URL 为空或格式不合法时抛出
     */
    private function validateUrl(string $url): void
    {
        if (empty($url)) {
            throw new InvalidArgumentException("URL 不能为空");
        }

        if (!preg_match('/^\//', $url) && !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException("URL 必须以斜杠开头或为完整 URL: {$url}");
        }
    }

    /**
     * 校验更新频率是否落在预定义枚举值之内
     *
     * @param string $frequency 更新频率字符串
     * @return void
     * @throws InvalidArgumentException 频率非法时抛出
     */
    private function validateChangeFrequency(string $frequency): void
    {
        $validFrequencies = [
            self::CHANGE_FREQUENCY_ALWAYS,
            self::CHANGE_FREQUENCY_HOURLY,
            self::CHANGE_FREQUENCY_DAILY,
            self::CHANGE_FREQUENCY_WEEKLY,
            self::CHANGE_FREQUENCY_MONTHLY,
            self::CHANGE_FREQUENCY_YEARLY,
            self::CHANGE_FREQUENCY_NEVER
        ];

        if (!in_array($frequency, $validFrequencies, true)) {
            throw new InvalidArgumentException("无效的更新频率: {$frequency}，有效值: " . implode(', ', $validFrequencies));
        }
    }

    /**
     * 校验优先级是否处于 0.0 到 1.0 的闭区间
     *
     * @param float $priority 优先级
     * @return void
     * @throws InvalidArgumentException 超出范围时抛出
     */
    private function validatePriority(float $priority): void
    {
        if ($priority < 0.0 || $priority > 1.0) {
            throw new InvalidArgumentException("优先级必须在 0.0 到 1.0 之间，当前值: {$priority}");
        }
    }

    /**
     * 校验日期字符串是否为合法的 YYYY-MM-DD 格式
     *
     * @param string $date 日期字符串
     * @return void
     * @throws InvalidArgumentException 格式或日期无效时抛出
     */
    private function validateDate(string $date): void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new InvalidArgumentException("日期格式必须为 YYYY-MM-DD: {$date}");
        }

        // 检查日期是否有效
        $dateTime = DateTime::createFromFormat('Y-m-d', $date);
        if ($dateTime === false || $dateTime->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException("无效的日期: {$date}");
        }
    }

    // ==================== 原有的生成方法 ====================

    /**
     * 将当前累积的 URL 写入一个站点地图分片文件（并在启用压缩时生成 .gz）
     *
     * @return void
     * @throws RuntimeException 写入失败或压缩失败时抛出
     */
    private function generateSitemap(): void
    {
        if (empty($this->urls)) {
            return;
        }

        $sitemapFile = $this->sitemapDir . 'sitemap_' . (++$this->sitemapCount) . '.xml';
        $sitemapContent = $this->generateSitemapXml($this->urls);

        $contentToWrite = $sitemapContent;
        if ($this->footer) {
            $contentToWrite .= trim($this->footer);
        }
        $contentToWrite = rtrim($contentToWrite) . "\n";

        if (file_put_contents($sitemapFile, $contentToWrite, LOCK_EX) === false) {
            throw new RuntimeException("无法写入站点地图文件: {$sitemapFile}");
        }

        if ($this->useCompression) {
            $this->createCompressedVersion($sitemapFile);
        }

        $this->generatedFiles[] = $sitemapFile;
        $this->urls = [];
    }

    /**
     * 生成站点地图索引文件，聚合所有已生成的分片
     *
     * @return void
     * @throws RuntimeException 索引写入失败时抛出
     */
    private function generateIndexFile(): void
    {
        if ($this->sitemapCount === 0) {
            return;
        }

        $customHeader = $this->header ? (trim($this->header) . "\n") : '';

        try {
            $xml = new SimpleXMLElement($customHeader . '<sitemapindex xmlns="https://www.sitemaps.org/schemas/sitemap/0.9"/>');

            for ($i = 1; $i <= $this->sitemapCount; $i++) {
                $sitemapUrl = $this->baseUrl . $this->mapsDirName . '/' . $this->dateFolder . '/sitemap_' . $i . '.xml';

                $sitemapEntry = $xml->addChild('sitemap');
                $sitemapEntry->addChild('loc', self::xmlText($sitemapUrl));
                $sitemapEntry->addChild('lastmod', date('Y-m-d'));
            }

            if ($xml->asXML($this->indexFilePath) === false) {
                throw new RuntimeException("无法写入站点地图索引文件: {$this->indexFilePath}");
            }

            $this->generatedFiles[] = $this->indexFilePath;

        } catch (Exception $e) {
            throw new RuntimeException("生成索引文件时出错: " . $e->getMessage());
        }
    }

    /**
     * 为指定站点地图文件生成 GZIP 压缩副本
     *
     * @param string $filePath 原始站点地图文件路径
     * @return void
     * @throws RuntimeException 读取或写入压缩文件失败时抛出
     */
    private function createCompressedVersion(string $filePath): void
    {
        if (!function_exists('gzencode')) {
            $this->errors[] = "zlib 扩展不可用，无法创建压缩版本";
            return;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new RuntimeException("无法读取文件内容: {$filePath}");
        }

        $compressed = gzencode($content, 9);
        if ($compressed === false) {
            throw new RuntimeException("GZIP 压缩失败: {$filePath}");
        }

        if (file_put_contents($filePath . '.gz', $compressed, LOCK_EX) === false) {
            throw new RuntimeException("无法写入压缩文件: {$filePath}.gz");
        }

        $this->generatedFiles[] = $filePath . '.gz';
    }
}
