<?php

declare(strict_types=1);

namespace zxf\Utils\Dom;

use DOMCdataSection;
use DOMComment;
use DOMDocument;
use DOMDocumentFragment;
use DOMElement;
use DOMNode;
use DOMText;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

/**
 * DOM 节点基类
 * 
 * 提供对 DOM 节点的通用操作方法
 * 支持 DOM 元素、文本节点、注释节点等
 * 
 * 特性：
 * - PHP 8.2+ 类型系统
 * - 联合类型支持
 * - 只读属性访问
 * - 完整的节点操作 API
 * 
 * @package zxf\Utils\Dom
 */
abstract class Node
{
    /**
     * DOM 节点对象
     * 
     * @var DOMElement|DOMText|DOMComment|DOMCdataSection|DOMDocumentFragment
     */
    protected DOMElement|DOMText|DOMComment|DOMCdataSection|DOMDocumentFragment $node;

    /**
     * 构造函数
     * 
     * @param  DOMElement|DOMText|DOMComment|DOMCdataSection|DOMDocumentFragment  $node  DOM 节点
     */
    public function __construct(DOMElement|DOMText|DOMComment|DOMCdataSection|DOMDocumentFragment $node)
    {
        $this->node = $node;
    }

    /**
     * 获取 DOM 节点对象
     * 
     * @return DOMElement|DOMText|DOMComment|DOMCdataSection|DOMDocumentFragment
     */
    public function getNode(): DOMNode
    {
        return $this->node;
    }

    /**
     * 设置 DOM 节点对象
     * 
     * @param  DOMElement|DOMText|DOMComment|DOMCdataSection|DOMDocumentFragment  $node  DOM 节点
     * @return void
     */
    public function setNode($node): void
    {
        if (! $node instanceof DOMNode) {
            throw new InvalidArgumentException('参数必须是 DOMNode 的实例。');
        }

        $this->node = $node;
    }

    /**
     * 检查是否为元素节点
     * 
     * @return bool
     */
    public function isElementNode(): bool
    {
        return $this->node->nodeType === XML_ELEMENT_NODE;
    }

    /**
     * 检查是否为文本节点
     * 
     * @return bool
     */
    public function isTextNode(): bool
    {
        return $this->node->nodeType === XML_TEXT_NODE;
    }

    /**
     * 检查是否为注释节点
     * 
     * @return bool
     */
    public function isCommentNode(): bool
    {
        return $this->node->nodeType === XML_COMMENT_NODE;
    }

    /**
     * 获取节点名称
     * 
     * @return string
     */
    public function getNodeName(): string
    {
        return $this->node->nodeName;
    }

    /**
     * 获取节点值
     * 
     * @return string|null
     */
    public function getNodeValue(): ?string
    {
        return $this->node->nodeValue;
    }

    /**
     * 设置节点值
     * 
     * @param  string|null  $value  节点值
     * @return self
     */
    public function setNodeValue(?string $value): self
    {
        $this->node->nodeValue = $value;
        return $this;
    }

    /**
     * 获取节点类型
     * 
     * @return int
     */
    public function getNodeType(): int
    {
        return $this->node->nodeType;
    }

    /**
     * 获取父节点
     * 
     * @return DOMNode|null
     */
    public function getParentNode(): ?DOMNode
    {
        return $this->node->parentNode;
    }

    /**
     * 获取子节点
     * 
     * @return \DOMNodeList
     */
    public function getChildNodes(): \DOMNodeList
    {
        return $this->node->childNodes;
    }

    /**
     * 获取第一个子节点
     * 
     * @return DOMNode|null
     */
    public function getFirstChild(): ?DOMNode
    {
        return $this->node->firstChild;
    }

    /**
     * 获取最后一个子节点
     * 
     * @return DOMNode|null
     */
    public function getLastChild(): ?DOMNode
    {
        return $this->node->lastChild;
    }

    /**
     * 获取下一个兄弟节点
     * 
     * @return DOMNode|null
     */
    public function getNextSibling(): ?DOMNode
    {
        return $this->node->nextSibling;
    }

    /**
     * 获取前一个兄弟节点
     * 
     * @return DOMNode|null
     */
    public function getPreviousSibling(): ?DOMNode
    {
        return $this->node->previousSibling;
    }

    /**
     * 获取文档对象
     * 
     * @return DOMDocument|null
     */
    public function getOwnerDocument(): ?DOMDocument
    {
        return $this->node->ownerDocument;
    }

    /**
     * 获取节点的 HTML 内容
     * 
     * @return string
     */
    public function html(): string
    {
        $doc = $this->node->ownerDocument;
        if ($doc === null) {
            return '';
        }

        return $doc->saveHTML($this->node);
    }

    /**
     * 获取节点的文本内容
     * 
     * @return string
     */
    public function text(): string
    {
        return $this->node->textContent ?? '';
    }

    /**
     * 设置节点值
     * 
     * @param  string|int|float|bool  $value  节点值
     * @return self
     */
    public function setValue(string|int|float|bool $value): self
    {
        $this->node->nodeValue = (string) $value;
        return $this;
    }

    /**
     * 设置节点的 HTML 内容
     * 
     * @param  string  $html  HTML 内容
     * @return self
     */
    public function setInnerHtml(string $html): self
    {
        if ($this->node->ownerDocument === null) {
            throw new LogicException('无法设置 HTML：节点没有所属文档。');
        }

        // 清空当前节点
        while ($this->node->firstChild !== null) {
            $this->node->removeChild($this->node->firstChild);
        }

        // 创建文档片段并加载 HTML
        $fragment = $this->node->ownerDocument->createDocumentFragment();
        
        // 防止 HTML5 标签自动添加
        libxml_use_internal_errors(true);
        $loaded = $fragment->appendXML($html);
        libxml_clear_errors();

        if ($loaded) {
            $this->node->appendChild($fragment);
        }

        return $this;
    }

    /**
     * 设置节点的文本内容
     * 
     * @param  string  $text  文本内容
     * @return self
     */
    public function setText(string $text): self
    {
        // 清空当前节点
        while ($this->node->firstChild !== null) {
            $this->node->removeChild($this->node->firstChild);
        }

        if ($this->node->ownerDocument !== null) {
            $textNode = $this->node->ownerDocument->createTextNode($text);
            $this->node->appendChild($textNode);
        }

        return $this;
    }

    /**
     * 在当前节点前插入节点
     * 
     * @param  Node|DOMNode|array  $nodes  要插入的节点
     * @return Node|Node[]
     */
    public function before(Node|DOMNode|array $nodes): Node|array
    {
        $parent = $this->node->parentNode;
        
        if ($parent === null) {
            throw new RuntimeException('无法在节点前插入：节点没有父节点。');
        }

        $returnArray = is_array($nodes);
        if (! is_array($nodes)) {
            $nodes = [$nodes];
        }

        $result = [];
        $document = $this->node->ownerDocument;

        foreach (array_reverse($nodes) as $node) {
            if ($node instanceof Node) {
                $node = $node->getNode();
            }

            if (! $node instanceof DOMNode) {
                throw new InvalidArgumentException('参数必须是 Node 或 DOMNode 的实例。');
            }

            $node = $document->importNode($node, true);
            $parent->insertBefore($node, $this->node);
            $result[] = $this->wrapImportedNode($node);
        }

        return $returnArray ? $result : $result[0];
    }

    /**
     * 在当前节点后插入节点
     * 
     * @param  Node|DOMNode|array  $nodes  要插入的节点
     * @return Node|Node[]
     */
    public function after(Node|DOMNode|array $nodes): Node|array
    {
        $parent = $this->node->parentNode;
        
        if ($parent === null) {
            throw new RuntimeException('无法在节点后插入：节点没有父节点。');
        }

        $returnArray = is_array($nodes);
        if (! is_array($nodes)) {
            $nodes = [$nodes];
        }

        $result = [];
        $document = $this->node->ownerDocument;
        $referenceNode = $this->node->nextSibling;

        foreach ($nodes as $node) {
            if ($node instanceof Node) {
                $node = $node->getNode();
            }

            if (! $node instanceof DOMNode) {
                throw new InvalidArgumentException('参数必须是 Node 或 DOMNode 的实例。');
            }

            $node = $document->importNode($node, true);
            
            if ($referenceNode === null) {
                $parent->appendChild($node);
            } else {
                $parent->insertBefore($node, $referenceNode);
            }

            $result[] = $this->wrapImportedNode($node);
        }

        return $returnArray ? $result : $result[0];
    }

    /**
     * 在当前节点开头插入子节点
     * 
     * @param  Node|DOMNode|array  $nodes  要插入的节点
     * @return Node|Node[]
     */
    public function prepend(Node|DOMNode|array $nodes): Node|array
    {
        return $this->prependChild($nodes);
    }

    /**
     * 在当前节点末尾添加子节点
     * 
     * @param  Node|DOMNode|array  $nodes  要添加的节点
     * @return Node|Node[]
     */
    public function append(Node|DOMNode|array $nodes): Node|array
    {
        return $this->appendChild($nodes);
    }

    /**
     * 添加子节点到开头
     * 
     * @param  Node|DOMNode|array  $nodes  要添加的节点
     * @return Node|Node[]
     */
    public function prependChild(Node|DOMNode|array $nodes): Node|array
    {
        $returnArray = is_array($nodes);
        if (! is_array($nodes)) {
            $nodes = [$nodes];
        }

        $nodes = array_reverse($nodes);
        $result = [];
        $referenceNode = $this->node->firstChild;
        $document = $this->node->ownerDocument;

        foreach ($nodes as $node) {
            if ($node instanceof Node) {
                $node = $node->getNode();
            }

            if (! $node instanceof DOMNode) {
                throw new InvalidArgumentException('参数必须是 Node 或 DOMNode 的实例。');
            }

            $node = $document->importNode($node, true);
            
            if ($referenceNode === null) {
                $this->node->appendChild($node);
            } else {
                $this->node->insertBefore($node, $referenceNode);
            }

            $result[] = $this->wrapImportedNode($node);
            $referenceNode = $this->node->firstChild;
        }

        return $returnArray ? $result : $result[0];
    }

    /**
     * 添加子节点到末尾
     * 
     * @param  Node|DOMNode|array  $nodes  要添加的节点
     * @return Node|Node[]
     */
    public function appendChild(Node|DOMNode|array $nodes): Node|array
    {
        $returnArray = is_array($nodes);
        if (! is_array($nodes)) {
            $nodes = [$nodes];
        }

        $result = [];
        $document = $this->node->ownerDocument;

        foreach ($nodes as $node) {
            if ($node instanceof Node) {
                $node = $node->getNode();
            }

            if (! $node instanceof DOMNode) {
                throw new InvalidArgumentException('参数必须是 Node 或 DOMNode 的实例。');
            }

            $node = $document->importNode($node, true);
            $this->node->appendChild($node);
            $result[] = $this->wrapImportedNode($node);
        }

        return $returnArray ? $result : $result[0];
    }

    /**
     * 替换当前节点
     * 
     * @param  Node|DOMNode|array  $nodes  新节点
     * @return self
     */
    public function replaceWith(Node|DOMNode|array $nodes): self
    {
        $this->after($nodes);
        $this->remove();
        return $this;
    }

    /**
     * 移除当前节点
     * 
     * @return self
     */
    public function remove(): self
    {
        $parent = $this->node->parentNode;
        
        if ($parent !== null) {
            $parent->removeChild($this->node);
        }

        return $this;
    }

    /**
     * 克隆当前节点
     * 
     * @param  bool  $deep  是否深度克隆（包含子节点）
     * @return Node
     */
    public function clone(bool $deep = true): Node
    {
        $clonedNode = $this->node->cloneNode($deep);
        return $this->wrapImportedNode($clonedNode);
    }

    /**
     * 获取节点在父节点的子节点列表中的索引
     * 
     * @return int
     */
    public function index(): int
    {
        $index = 0;
        $node = $this->node->previousSibling;

        while ($node !== null) {
            if ($node->nodeType === XML_ELEMENT_NODE) {
                $index++;
            }
            $node = $node->previousSibling;
        }

        return $index;
    }

    /**
     * 检查节点是否匹配选择器
     * 
     * @param  string  $selector  选择器
     * @param  string|bool  $typeOrStrict  选择器类型或严格模式标志（子类可覆盖）
     * @return bool
     */
    public function matches(string $selector, string|bool $typeOrStrict = Query::TYPE_CSS): bool
    {
        if ($this->node->ownerDocument === null) {
            return false;
        }

        $document = Document::getFromDomDocument($this->node->ownerDocument);
        if ($document === null) {
            return false;
        }

        $type = is_string($typeOrStrict) ? $typeOrStrict : Query::TYPE_CSS;
        $elements = $document->find($selector, $type, $this->node->parentNode);
        return in_array($this, $elements, true);
    }

    /**
     * 包装导入的节点
     * 
     * @param  DOMNode  $node  导入的节点
     * @return Node
     */
    protected function wrapImportedNode(DOMNode $node): Node
    {
        if ($node instanceof DOMElement) {
            return new Element($node);
        }
        
        return new class($node) extends Node {};
    }

    /**
     * 魔术方法：获取属性
     * 
     * @param  string  $name  属性名
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        return match ($name) {
            'tag' => strtolower($this->node->nodeName),
            'name' => $this->node->nodeName,
            'value' => $this->node->nodeValue,
            'text' => $this->text(),
            'html' => $this->html(),
            default => null,
        };
    }

    /**
     * 魔术方法：转换为字符串
     * 
     * @return string
     */
    public function __toString(): string
    {
        return $this->html();
    }
}
