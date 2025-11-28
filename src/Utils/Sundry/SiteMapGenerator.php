<?php

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
 * @see https://weisifang.com/docs/doc/6_209 网站地图生成器
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
 */

/**
 * 使用示例：
 *
 * // 创建 SitemapGenerator 实例
 * $sitemapDir = __DIR__;
 * $baseUrl = 'https://www.example.com';
 * $generator = new SiteMapGenerator($baseUrl, $sitemapDir, 'sitemaps');
 *
 * // 添加 URL
 * $generator->addUrl('/page1', '2024-09-24', 'daily', 0.8);
 *
 * // 获取 XML 字符串内容
 * $xmlContent = $generator->getSitemapXmlString();
 * echo $xmlContent;
 *
 * // 生成文件
 * $result = $generator->generateFile();
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
                $sitemapEntry->addChild('loc', htmlspecialchars($sitemapUrl['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8'));
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
     */
    public function generate(string $outputType = self::OUTPUT_FILE): mixed
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
     */
    public function setPrettyFormat(bool $pretty = true): self
    {
        $this->prettyFormat = $pretty;
        return $this;
    }

    /**
     * 设置 XML 编码格式
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
     */
    public function getUrlCount(): int
    {
        return count($this->urls);
    }

    /**
     * 检查是否包含指定 URL
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
     */
    public function validateSitemap(string $xmlContent): array
    {
        $errors = [];

        // 基本的 XML 语法验证
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadXML($xmlContent);
        $xmlErrors = libxml_get_errors();
        libxml_clear_errors();

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
     */
    public function setValidationRules(array $rules): self
    {
        $this->validationRules = array_merge($this->validationRules, $rules);
        return $this;
    }

    /**
     * 获取内存使用情况
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
     */
    public function clearCache(): self
    {
        $this->cache = [];
        return $this;
    }

    /**
     * 设置 URL 过滤器
     */
    public function setUrlFilter(callable $filter): self
    {
        $this->urlFilter = $filter;
        return $this;
    }

    /**
     * 获取性能统计信息
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
     */
    public function setCustomData(string $key, $value): self
    {
        $this->customData[$key] = $value;
        return $this;
    }

    /**
     * 获取自定义数据
     */
    public function getCustomData(string $key, $default = null)
    {
        return $this->customData[$key] ?? $default;
    }

    // ==================== 私有方法 ====================

    /**
     * 添加 URL 项目（内部使用）
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
     * 格式化 XML 字符串（美化输出）
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
     */
    private function generateSitemapUrls(): array
    {
        $urls = [];
        $chunkCount = max(1, ceil(count($this->urls) / $this->maxUrls));

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
     */
    private function addUrlToXml(SimpleXMLElement $xml, array $url): void
    {
        $urlEntry = $xml->addChild('url');
        $urlEntry->addChild('loc', htmlspecialchars($url['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8'));

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
                $urlEntry->addChild($tag, htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8'));
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
     */
    private function addImageToXml(SimpleXMLElement $urlEntry, array $image): void
    {
        $imageElement = $urlEntry->addChild('image:image');

        if (!empty($image['url'])) {
            $imageElement->addChild('image:loc', htmlspecialchars($image['url'], ENT_XML1 | ENT_QUOTES, 'UTF-8'));
        }

        if (!empty($image['title'])) {
            $imageElement->addChild('image:title', htmlspecialchars($image['title'], ENT_XML1 | ENT_QUOTES, 'UTF-8'));
        }

        if (!empty($image['caption'])) {
            $imageElement->addChild('image:caption', htmlspecialchars($image['caption'], ENT_XML1 | ENT_QUOTES, 'UTF-8'));
        }

        if (!empty($image['geo_location'])) {
            $imageElement->addChild('image:geo_location', htmlspecialchars($image['geo_location'], ENT_XML1 | ENT_QUOTES, 'UTF-8'));
        }

        if (!empty($image['license'])) {
            $imageElement->addChild('image:license', htmlspecialchars($image['license'], ENT_XML1 | ENT_QUOTES, 'UTF-8'));
        }
    }

    /**
     * 添加视频信息到 XML 节点
     */
    private function addVideoToXml(SimpleXMLElement $urlEntry, array $video): void
    {
        $videoElement = $urlEntry->addChild('video:video');

        if (!empty($video['title'])) {
            $videoElement->addChild('video:title', htmlspecialchars($video['title'], ENT_XML1 | ENT_QUOTES, 'UTF-8'));
        }

        if (!empty($video['description'])) {
            $videoElement->addChild('video:description', htmlspecialchars($video['description'], ENT_XML1 | ENT_QUOTES, 'UTF-8'));
        }

        if (!empty($video['thumbnail_loc'])) {
            $videoElement->addChild('video:thumbnail_loc', htmlspecialchars($video['thumbnail_loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8'));
        }

        if (!empty($video['url'])) {
            $videoElement->addChild('video:content_loc', htmlspecialchars($video['url'], ENT_XML1 | ENT_QUOTES, 'UTF-8'));
        }

        if (!empty($video['duration'])) {
            $videoElement->addChild('video:duration', (string) $video['duration']);
        }

        if (!empty($video['publication_date'])) {
            $videoElement->addChild('video:publication_date', $video['publication_date']);
        }

        if (!empty($video['player_loc'])) {
            $videoElement->addChild('video:player_loc', htmlspecialchars($video['player_loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8'));
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
            $newsElement->addChild('news:title', htmlspecialchars($news['title'], ENT_XML1 | ENT_QUOTES, 'UTF-8'));
        }

        if (!empty($news['publication_date'])) {
            $newsElement->addChild('news:publication_date', $news['publication_date']);
        }

        if (!empty($news['keywords'])) {
            $newsElement->addChild('news:keywords', htmlspecialchars($news['keywords'], ENT_XML1 | ENT_QUOTES, 'UTF-8'));
        }

        if (!empty($news['genres'])) {
            $newsElement->addChild('news:genres', htmlspecialchars($news['genres'], ENT_XML1 | ENT_QUOTES, 'UTF-8'));
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

    public function setHeader(string $header): self
    {
        $this->header = $header;
        return $this;
    }

    public function setFooter(string $footer): self
    {
        $this->footer = $footer;
        return $this;
    }

    public function enableCompression(bool $enable = true): self
    {
        $this->useCompression = $enable;
        return $this;
    }

    public function addNamespace(string $prefix, string $url): self
    {
        $this->namespaces[$prefix] = $url;
        return $this;
    }

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

    public function getSitemapDir(): string
    {
        return $this->sitemapDir;
    }

    public function getIndexFilePath(): string
    {
        return $this->indexFilePath;
    }

    public function getGeneratedFiles(): array
    {
        return $this->generatedFiles;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

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

    public function clearErrors(): self
    {
        $this->errors = [];
        return $this;
    }

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

    private function validateUrl(string $url): void
    {
        if (empty($url)) {
            throw new InvalidArgumentException("URL 不能为空");
        }

        if (!preg_match('/^\//', $url) && !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException("URL 必须以斜杠开头或为完整 URL: {$url}");
        }
    }

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

    private function validatePriority(float $priority): void
    {
        if ($priority < 0.0 || $priority > 1.0) {
            throw new InvalidArgumentException("优先级必须在 0.0 到 1.0 之间，当前值: {$priority}");
        }
    }

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
                $sitemapEntry->addChild('loc', htmlspecialchars($sitemapUrl, ENT_XML1 | ENT_QUOTES, 'UTF-8'));
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
