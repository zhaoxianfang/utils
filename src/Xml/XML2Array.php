<?php

declare(strict_types=1);

namespace zxf\Utils\Xml;

use DOMDocument;
use DOMNode;
use DOMProcessingInstruction;
use Generator;
use InvalidArgumentException;
use RuntimeException;
use XMLReader;

/**
 * XML解析器
 *
 * 主要特性：
 * 1. 可控的外层节点保留/移除
 * 2. 增强的流式处理能力
 * 3. 智能内存管理
 * 4. 多层级错误处理
 * 5. 支持XML注释处理
 *
 * @package zxf\Utils\Xml
 * @version 1.0.0
 * @license MIT
 *
 * @example 用法详见 readme.md 文件
 *      1、支持解析微信官方文档中示例的 XML 数据
 *      2、支持解析普通的xml 字符串
 */
final class XML2Array
{
    // 默认配置选项
    private const DEFAULT_OPTIONS = [
        'attributePrefix' => '@',          // 属性前缀
        'textNodeName' => '#text',      // 文本节点名称
        'commentNodeName' => '#comment',   // 注释节点名称
        'autoArray' => false,         // 自动创建数组
        'namespaceHandling' => 'remove',     // 三种命名空间处理模式：none（忽略）、remove（移除前缀）、preserve（保留）
        'parseAttributes' => true,         // 解析属性
        'parseComments' => false,        // 是否解析注释
        'trimValues' => true,         // 修剪值
        'typeDetection' => true,         // 启用类型检测
        'encoding' => 'UTF-8',      // 默认编码
        'preserveRootNode' => false,         // 是否保留根节点
        'removeXmlDeclaration' => true,         // 是否移除XML声明
        'libxmlOptions' => LIBXML_NOCDATA | LIBXML_NOBLANKS | LIBXML_NONET | LIBXML_COMPACT, // libxml解析选项（PARSEHUGE 属不必要的攻击面放宽，仅在确需超大文档时按需追加）
        'maxDepth' => 50,           // 最大递归深度
        'validateOnParse' => false,        // 启用XML验证
        'schemaValidation' => false,        // 使用XSD验证
        'schemaPath' => null,         // XSD文件路径
        'memoryLimit' => null,         // 内存限制(字节)
    ];

    private array $options;

    private array $stats = [
        'nodes' => 0,
        'attributes' => 0,
        'comments' => 0,
        'depth' => 0,
        'memory' => 0,
        'parseTime' => 0,
    ];

    /**
     * 构造 XML 解析器并合并配置、初始化安全设置
     *
     * @param array $options 自定义配置项，覆盖 DEFAULT_OPTIONS 中的默认配置
     */
    public function __construct(array $options = [])
    {
        $this->options = array_merge(self::DEFAULT_OPTIONS, $options);
        $this->initSecurity();
    }

    /**
     * 静态调用 解析 XML 字符串为数组
     *
     * @param  string|DomDocument  $xml  XML 字符串或 DOMDocument 对象
     * @param  array  $options  配置选项
     * @return array 解析后的关联数组
     * @throws InvalidArgumentException|RuntimeException 输入为空/格式非法或解析失败时抛出
     */
    public static function toArray(string|DomDocument $xml, array $options = []): array
    {
        $parse = new self($options);

        return $parse->parse($xml);
    }

    /**
     * 解析XML字符串
     *
     * @param  string|DomDocument  $xml  XML 字符串或 DOMDocument 对象
     * @return array 解析后的关联数组
     * @throws InvalidArgumentException|RuntimeException 输入为空/格式非法或解析失败时抛出
     */
    public function parse(string|DomDocument $xml): array
    {
        if ($xml instanceof DOMDocument) {
            $xml = $xml->saveXML();
        }
        $this->resetStats();
        $this->checkMemoryLimit();
        $this->validateXml($xml);

        try {
            $startTime = microtime(true);
            $dom = $this->createDomDocument();
            $this->stats['memory'] = memory_get_usage();

            if (! $dom->loadXML($xml, $this->options['libxmlOptions'])) {
                throw new RuntimeException('XML解析失败');
            }

            if ($this->options['validateOnParse']) {
                $this->validateDocument($dom);
            }

            $result = $this->processDom($dom);

            $this->stats['parseTime'] = microtime(true) - $startTime;
            $this->stats['memory'] = memory_get_peak_usage() - $this->stats['memory'];

            return $this->processResult($result);
        } finally {
            // 修复：本类从不调用 set_error_handler()，旧的 restore_error_handler()
            // 会弹出调用方（如框架）注册的错误处理器，必须移除。
        }
    }

    /**
     * 流式解析XML文件（边读边产出，适合大文件）
     *
     * @example 流式处理并忽略根节点
     *      foreach ($parser->parseStream('data.xml') as $key => $value) {
     *          // 直接获取子节点，不包含根节点
     *      }
     *
     * @param  string  $filePath  XML 文件路径
     * @return Generator 逐节点产出的键值对（遇到根节点时按 preserveRootNode 配置展平）
     * @throws RuntimeException  文件不存在、不可读或解析失败时抛出
     */
    public function parseStream(string $filePath): Generator
    {
        $this->resetStats();
        $this->checkFileAccess($filePath);

        $reader = new XMLReader;

        try {
            if (! $reader->open($filePath, $this->options['encoding'], $this->options['libxmlOptions'])) {
                throw new RuntimeException("无法打开XML文件: $filePath");
            }

            // 注意：切勿将 SUBST_ENTITIES 设为 true，否则会展开 XML 内部实体，
            // 可能被利用进行 XXE 攻击（读取本地文件等）。此处保持默认（不替换实体），
            // 配合 libxmlOptions 中的 LIBXML_NONET，双重防御外部实体加载。
            $reader->setParserProperty(XMLReader::SUBST_ENTITIES, false);

            while ($reader->read()) {
                $this->checkMemoryLimit();

                switch ($reader->nodeType) {
                    case XMLReader::ELEMENT:
                        $this->stats['nodes']++;
                        $dom = new DOMDocument;
                        $node = $reader->expand($dom);

                        if ($node !== false) {
                            $result = $this->processNode($node);
                            $nodeName = $this->normalizeNodeName($node->nodeName);

                            if (! $this->options['preserveRootNode'] && $reader->depth === 0) {
                                yield from $this->flattenRoot($result);
                            } else {
                                yield $nodeName => $result;
                            }
                        }
                        break;

                    case XMLReader::COMMENT:
                        if ($this->options['parseComments']) {
                            $this->stats['comments']++;
                            yield $this->options['commentNodeName'] => $reader->value;
                        }
                        break;
                }
            }
        } finally {
            $reader->close();
        }
    }

    /**
     * 处理最终结果（控制根节点）
     *
     * preserveRootNode 为 false 时展平根节点（取根元素的值作为结果）；
     * 为 true 时保留根节点名作为键，并按 removeXmlDeclaration 决定是否过滤 `?xml` 声明项。
     *
     * @param array $result 原始解析结果（根节点名 => 内容）
     * @return array 处理后的结果数组
     */
    private function processResult(array $result): array
    {
        if (! $this->options['preserveRootNode']) {
            return $this->flattenRoot($result);
        }

        if ($this->options['removeXmlDeclaration']) {
            unset($result['?xml']);
        }

        return $result;
    }

    /**
     * 展平根节点结构
     *
     * 结果只有 1 个顶层键时取出该键的值（丢失根节点名），否则原样返回；
     * 两种情况都会强制转为数组，故纯文本根节点会得到「仅含 0 号元素」的数组。
     *
     * @param array $result 原始解析结果
     * @return array 展平后的数组
     */
    private function flattenRoot(array $result): array
    {
        if (count($result) === 1) {
            return (array) current($result);
        }

        return (array) $result;
    }

    /**
     * 创建DOM文档对象
     *
     * 按配置的 encoding 初始化，关闭空白保留与严格错误检查、允许恢复（recover）、
     * 关闭实体替换以与流式解析的安全策略保持一致。
     *
     * @return DOMDocument 配置完成的空文档对象
     */
    private function createDomDocument(): DOMDocument
    {
        $dom = new DOMDocument('1.0', $this->options['encoding']);

        $dom->preserveWhiteSpace = false;
        $dom->strictErrorChecking = false;
        // 不展开内部实体：与 parseStream() 的 SUBST_ENTITIES=false 保持一致（防 XXE/实体放大攻击）
        $dom->substituteEntities = false;
        $dom->recover = true;
        $dom->validateOnParse = $this->options['validateOnParse'];

        return $dom;
    }

    /**
     * 处理DOM文档
     *
     * 以 documentElement 为入口递归转换；若首个节点是以 xml 开头的处理指令，
     * 额外记录到 `?xml` 键（是否保留由 processResult() 决定）。
     *
     * @param DOMDocument $dom 待转换的 DOM 文档
     * @return array 根节点名 => 内容（可能附带 `?xml` 项）的数组
     */
    private function processDom(DOMDocument $dom): array
    {
        $result = [];

        if ($dom->documentElement !== null) {
            $rootName = $this->normalizeNodeName($dom->documentElement->nodeName);
            $result[$rootName] = $this->processNode($dom->documentElement);
        }

        // 处理XML声明
        if ($dom->firstChild instanceof DOMProcessingInstruction &&
            str_starts_with($dom->firstChild->target, 'xml')
        ) {
            $result['?xml'] = $dom->firstChild->data;
        }

        return $result;
    }

    /**
     * 递归处理节点
     *
     * 按配置收集属性（加前缀）、元素子节点（同名元素自动聚合成数组）、注释与文本；
     * 无元素且有文本时直接返回文本值，有元素时文本存入 textNodeName 键。
     * 容器完全为空时返回空字符串。
     *
     * @param DOMNode $node 待处理节点
     * @param int $depth 当前递归深度（从 0 开始，用于 maxDepth 校验）
     * @return mixed 节点对应的标量或数组结构
     *
     * @throws RuntimeException 超过最大递归深度时抛出
     */
    private function processNode(DOMNode $node, int $depth = 0): mixed
    {
        $this->checkDepth($depth);
        $this->stats['depth'] = max($this->stats['depth'], $depth);

        $output = [];
        $hasElements = false;
        $hasText = false;
        $textContent = '';

        // 处理属性
        if ($this->options['parseAttributes'] && $node->hasAttributes()) {
            foreach ($node->attributes as $attr) {
                $this->stats['attributes']++;
                $attrName = $this->normalizeNodeName($attr->nodeName);
                $output[$this->options['attributePrefix'].$attrName] =
                    $this->processValue($attr->nodeValue ?? '');
            }
        }

        // 处理子节点
        foreach ($node->childNodes as $child) {
            $this->stats['nodes']++;

            switch ($child->nodeType) {
                case XML_ELEMENT_NODE:
                    $hasElements = true;
                    $childName = $this->normalizeNodeName($child->nodeName);
                    $childValue = $this->processNode($child, $depth + 1);
                    $this->addToOutput($output, $childName, $childValue);
                    break;

                case XML_TEXT_NODE:
                case XML_CDATA_SECTION_NODE:
                    $hasText = true;
                    $textContent .= $child->nodeValue;
                    break;

                case XML_COMMENT_NODE:
                    if ($this->options['parseComments']) {
                        $this->stats['comments']++;
                        $output[$this->options['commentNodeName']][] = $child->nodeValue;
                    }
                    break;
            }
        }

        // 处理文本内容
        if ($hasText) {
            $processedText = $this->processValue($textContent);

            if ($hasElements) {
                if ($processedText !== '') {
                    $output[$this->options['textNodeName']] = $processedText;
                }
            } elseif ($processedText !== '') {
                if ($output === []) {
                    // 纯文本节点：直接返回文本
                    $output = $processedText;
                } else {
                    // 修复：带属性的纯文本节点（如 <a id="1">text</a>）
                    // 旧实现会在这里用 $output = $processedText 把已收集的属性整个覆盖丢弃，
                    // 属性改为以 #text 键名保存
                    $output[$this->options['textNodeName']] = $processedText;
                }
            }
        }

        // 注意：不能用 $output ?: ''——0 / false / null 等「合法解析值」会被当假值吞成空串
        // 静默丢失；仅当容器确实为空（无属性、无子节点、无文本）时才返回空字符串
        return $output === [] ? '' : $output;
    }

    /**
     * 检查内存限制
     *
     * memoryLimit 为 0（未设置）时跳过检查；当前占用超过阈值则中断解析。
     *
     * @return void 无返回值
     *
     * @throws RuntimeException 内存占用超出配置上限时抛出
     */
    private function checkMemoryLimit(): void
    {
        if ($this->options['memoryLimit'] &&
            memory_get_usage(true) > $this->options['memoryLimit']) {
            throw new RuntimeException("超出内存限制: {$this->options['memoryLimit']}字节");
        }
    }

    /**
     * 检查递归深度
     *
     * 深度超过 maxDepth 即中断解析，防止深层嵌套导致栈溢出。
     *
     * @param int $depth 当前递归深度
     * @return void 无返回值
     *
     * @throws RuntimeException 超过最大递归深度时抛出
     */
    private function checkDepth(int $depth): void
    {
        if ($depth > $this->options['maxDepth']) {
            throw new RuntimeException("超过最大递归深度 ({$this->options['maxDepth']})");
        }
    }

    /**
     * 检查文件访问权限
     *
     * 依次确认文件存在且可读，任一不满足即抛出异常；仅做校验，不打开文件。
     *
     * @param string $filePath 待检查的文件路径
     * @return void 无返回值
     *
     * @throws RuntimeException 文件不存在或不可读时抛出
     */
    private function checkFileAccess(string $filePath): void
    {
        if (! file_exists($filePath)) {
            throw new RuntimeException("文件不存在: $filePath");
        }

        if (! is_readable($filePath)) {
            throw new RuntimeException("文件不可读: $filePath");
        }
    }

    /**
     * 初始化安全设置
     *
     * PHP 8.0+ 已移除实体加载器，此处不再禁用；仅把 mbstring 的内部编码
     * 对齐到配置的 encoding，避免后续字符串处理出现编码不一致。
     *
     * @return void 无返回值
     */
    private function initSecurity(): void
    {
        // PHP 8.0+ 不再需要禁用实体加载器
        // 替代方案：使用LIBXML_NONET选项防止网络访问

        // 设置默认编码
        if (function_exists('mb_internal_encoding')) {
            mb_internal_encoding($this->options['encoding']);
        }
    }

    /**
     * 验证XML输入
     *
     * 仅做轻量前置校验：内容非空白且去掉首尾空白后以 `<` 开头，
     * 不做完整语法检查（语法错误交由后续解析器处理）。
     *
     * @param string $xml 待验证的 XML 字符串
     * @return void 无返回值
     *
     * @throws InvalidArgumentException 内容为空或不以 `<` 开头时抛出
     */
    private function validateXml(string $xml): void
    {
        if (empty(trim($xml))) {
            throw new InvalidArgumentException('XML内容不能为空');
        }

        if (! str_starts_with(trim($xml), '<')) {
            throw new InvalidArgumentException('无效的XML格式');
        }
    }

    /**
     * 验证XML文档
     *
     * 启用 schemaValidation 且配置了 schemaPath 时按 XSD 校验（文件缺失即报错）；
     * 否则在 validateOnParse 开启时回退到 DTD 校验。未开启任何校验时直接返回。
     *
     * @param DOMDocument $dom 待校验的文档
     * @return void 无返回值
     *
     * @throws RuntimeException XSD 文件不存在或文档校验失败时抛出
     */
    private function validateDocument(DOMDocument $dom): void
    {
        if ($this->options['schemaValidation'] && $this->options['schemaPath']) {
            if (! file_exists($this->options['schemaPath'])) {
                throw new RuntimeException("XSD架构文件不存在: {$this->options['schemaPath']}");
            }

            if (! $dom->schemaValidate($this->options['schemaPath'])) {
                throw new RuntimeException('XML文档验证失败');
            }
        } elseif ($this->options['validateOnParse']) {
            if (! $dom->validate()) {
                throw new RuntimeException('XML文档验证失败');
            }
        }
    }

    /**
     * 标准化节点名称（处理命名空间）
     *
     * 按 namespaceHandling 决策：`none` 用贪婪匹配去掉最后一个冒号前的内容，
     * `remove` 只去掉第一个前缀段（多前缀名会保留剩余部分），其它取值原样返回。
     *
     * @param string $name 原始节点名（可能含命名空间前缀）
     * @return string 处理后的节点名
     */
    private function normalizeNodeName(string $name): string
    {
        return match ($this->options['namespaceHandling']) {
            'none' => preg_replace('/^.*:/', '', $name),
            'remove' => preg_replace('/^[^:]+:/', '', $name),
            default => $name,
        };
    }

    /**
     * 添加子节点到输出
     *
     * 键已存在时把旧值提升为数组再追加（同名元素自动聚合），
     * 键不存在时按 autoArray 决定是否包成单元素数组。
     *
     * @param array $output 输出容器（按引用修改）
     * @param string $name 子节点名
     * @param mixed $value 子节点值
     * @return void 无返回值
     */
    private function addToOutput(array &$output, string $name, $value): void
    {
        if (isset($output[$name])) {
            if (! is_array($output[$name]) || ! isset($output[$name][0])) {
                $output[$name] = [$output[$name]];
            }
            $output[$name][] = $value;
        } else {
            $output[$name] = $this->options['autoArray'] ? [$value] : $value;
        }
    }

    /**
     * 智能值处理
     *
     * 按配置先 trim，再在 typeDetection 开启时依次尝试识别布尔值（true/false/null）、
     * 数字（含小数点转 float，否则 int）与 JSON 字符串；识别失败或空串时原样返回。
     *
     * @param string $value 原始文本值
     * @return mixed 转换后的值（bool/int/float/array/null 或原字符串）
     */
    private function processValue(string $value): mixed
    {
        if ($this->options['trimValues']) {
            $value = trim($value);
        }

        if (! $this->options['typeDetection'] || $value === '') {
            return $value;
        }

        // 检测布尔值
        $lower = strtolower($value);
        if ($lower === 'true') {
            return true;
        }
        if ($lower === 'false') {
            return false;
        }
        if ($lower === 'null') {
            return null;
        }

        // 检测数字
        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        // 检测JSON
        if ($this->looksLikeJson($value)) {
            $decoded = json_decode($value, true);

            return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
        }

        return $value;
    }

    /**
     * 判断是否为JSON字符串
     *
     * 仅看首尾字符是否成对为 `{}` 或 `[]`，属于轻量启发式判断，
     * 不保证内容为合法 JSON（真正解码由调用方完成并检查错误码）。
     *
     * @param string $value 待判断的文本
     * @return bool 形似 JSON 对象/数组返回 true，否则返回 false
     */
    private function looksLikeJson(string $value): bool
    {
        $value = trim($value);
        if (empty($value)) {
            return false;
        }

        $firstChar = $value[0];
        $lastChar = $value[strlen($value) - 1];

        return ($firstChar === '{' && $lastChar === '}') ||
               ($firstChar === '[' && $lastChar === ']');
    }

    /**
     * 获取解析统计信息
     *
     * 包含节点数、属性数、注释数、最大深度、内存占用与解析耗时等。
     *
     * @return array{nodes:int,attributes:int,comments:int,depth:int,memory:int,parseTime:float} 统计信息数组
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * 重置统计信息
     *
     * 将节点数、属性数、注释数、最大深度、内存占用与解析耗时全部归零，
     * 每次解析开始前调用，保证多次解析的统计互不累加。
     *
     * @return void 无返回值
     */
    private function resetStats(): void
    {
        $this->stats = [
            'nodes' => 0,
            'attributes' => 0,
            'comments' => 0,
            'depth' => 0,
            'memory' => 0,
            'parseTime' => 0,
        ];
    }
}
