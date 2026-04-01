<?php

declare(strict_types=1);

namespace zxf\Utils\Dom\Fragments;

use zxf\Utils\Dom\Node;

use DOMDocumentFragment as NativeDOMDocumentFragment;
use DOMNode;
use InvalidArgumentException;
use RuntimeException;

/**
 * DOM 文档片段类
 * 
 * 提供对 DOM 文档片段的便捷操作方法
 * 支持创建片段、添加节点、插入文档等
 * 
 * 特性：
 * - PHP 8.2+ 类型系统
 * - 流畅的链式调用
 * - 完整的片段操作 API
 * 
 * @example
 * $fragment = $document->createDocumentFragment();
 * $fragment->append('<p>段落1</p>');
 * $fragment->append('<p>段落2</p>');
 * $document->body()->append($fragment);
 * 
 * @package zxf\Utils\Dom
 */
class DocumentFragment extends Node
{
    /**
     * 原生 DOM 文档片段对象
     * 
     * @var NativeDOMDocumentFragment
     */
    protected NativeDOMDocumentFragment $fragment;

    /**
     * 构造函数
     * 
     * @param  NativeDOMDocumentFragment  $fragment  DOM 文档片段对象
     */
    public function __construct(NativeDOMDocumentFragment $fragment)
    {
        $this->fragment = $fragment;
        parent::__construct($fragment);
    }

    /**
     * 获取原生 DOM 文档片段对象
     * 
     * @return NativeDOMDocumentFragment
     */
    public function getFragment(): NativeDOMDocumentFragment
    {
        return $this->fragment;
    }

    /**
     * 添加 HTML 内容到片段
     * 
     * @param  string  $html  HTML 内容
     * @return self
     * 
     * @throws RuntimeException 当 HTML 加载失败时抛出
     */
    public function appendHtml(string $html): self
    {
        // 禁用错误输出
        libxml_use_internal_errors(true);
        
        // 清空当前内容
        while ($this->fragment->firstChild !== null) {
            $this->fragment->removeChild($this->fragment->firstChild);
        }

        $result = $this->fragment->appendXML($html);
        
        libxml_clear_errors();

        if (! $result) {
            throw new RuntimeException('无法将 HTML 添加到文档片段。');
        }

        return $this;
    }

    /**
     * 添加 XML 内容到片段
     * 
     * @param  string  $xml  XML 内容
     * @return self
     * 
     * @throws RuntimeException 当 XML 加载失败时抛出
     */
    public function appendXml(string $xml): self
    {
        return $this->appendHtml($xml);
    }

    /**
     * 添加文本内容到片段
     * 
     * @param  string  $text  文本内容
     * @return self
     */
    public function appendText(string $text): self
    {
        if ($this->fragment->ownerDocument !== null) {
            $textNode = $this->fragment->ownerDocument->createTextNode($text);
            $this->fragment->appendChild($textNode);
        }
        return $this;
    }

    /**
     * 添加节点到片段
     * 
     * @param  Node|DOMNode|array  $nodes  要添加的节点
     * @return self
     */
    public function append(Node|DOMNode|array $nodes): self
    {
        if (! is_array($nodes)) {
            $nodes = [$nodes];
        }

        foreach ($nodes as $node) {
            if ($node instanceof Node) {
                $node = $node->getNode();
            }

            if (! $node instanceof \DOMNode) {
                throw new InvalidArgumentException('参数必须是 Node 或 DOMNode 的实例。');
            }

            $importedNode = $this->fragment->ownerDocument->importNode($node, true);
            $this->fragment->appendChild($importedNode);
        }

        return $this;
    }

    /**
     * 在片段开头添加内容
     * 
     * @param  Node|DOMNode|array  $nodes  要添加的节点
     * @return self
     */
    public function prepend(Node|DOMNode|array $nodes): self
    {
        if (! is_array($nodes)) {
            $nodes = [$nodes];
        }

        $nodes = array_reverse($nodes);

        foreach ($nodes as $node) {
            if ($node instanceof Node) {
                $node = $node->getNode();
            }

            if (! $node instanceof \DOMNode) {
                throw new InvalidArgumentException('参数必须是 Node 或 DOMNode 的实例。');
            }

            $importedNode = $this->fragment->ownerDocument->importNode($node, true);
            
            if ($this->fragment->firstChild === null) {
                $this->fragment->appendChild($importedNode);
            } else {
                $this->fragment->insertBefore($importedNode, $this->fragment->firstChild);
            }
        }

        return $this;
    }

    /**
     * 检查片段是否为空
     * 
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->fragment->firstChild === null;
    }

    /**
     * 获取片段中的子节点数量
     * 
     * @return int
     */
    public function count(): int
    {
        return $this->fragment->childNodes->length;
    }

    /**
     * 清空片段内容
     * 
     * @return self
     */
    public function clear(): self
    {
        while ($this->fragment->firstChild !== null) {
            $this->fragment->removeChild($this->fragment->firstChild);
        }
        return $this;
    }

    /**
     * 获取片段的 HTML 内容
     * 
     * @return string
     */
    public function toHtml(): string
    {
        $html = '';
        foreach ($this->fragment->childNodes as $child) {
            $html .= $this->fragment->ownerDocument->saveHTML($child);
        }
        return $html;
    }

    /**
     * 获取片段的文本内容
     * 
     * @return string
     */
    public function toText(): string
    {
        return $this->fragment->textContent ?? '';
    }

    /**
     * 魔术方法：转换为字符串
     * 
     * @return string
     */
    public function __toString(): string
    {
        return $this->toHtml();
    }

    /**
     * 可数接口：获取子节点数量
     * 
     * @return int
     */
    public function __invoke(): int
    {
        return $this->count();
    }
}
