<?php

declare(strict_types=1);

namespace zxf\Utils\Pagination;

use InvalidArgumentException;

/**
 * 分页工具类
 * 提供分页数据计算、页码范围生成、SQL 偏移量计算、HTML 导航渲染等功能
 *
 * @package Pagination
 * @version 1.0.0
 * @license MIT
 */
class Paginator
{
    /** @var int 总记录数 */
    private int $total;

    /** @var int 每页记录数 */
    private int $perPage;

    /** @var int 当前页码 */
    private int $currentPage;

    /** @var int 页码导航栏两侧显示的页码数量 */
    private int $pageRange = 3;

    /**
     * 构造函数
     *
     * @param int $total       总记录数，必须大于等于 0
     * @param int $perPage     每页记录数，默认为 15
     * @param int $currentPage 当前页码，默认为 1
     * @throws InvalidArgumentException 当总记录数为负数或每页记录数小于 1 时抛出
     */
    public function __construct(int $total, int $perPage = 15, int $currentPage = 1)
    {
        if ($total < 0) {
            throw new InvalidArgumentException('总记录数不能为负数');
        }
        if ($perPage < 1) {
            throw new InvalidArgumentException('每页记录数至少为1');
        }
        if ($currentPage < 1) {
            $currentPage = 1;
        }

        $this->total = $total;
        $this->perPage = $perPage;
        $this->currentPage = $currentPage;
    }

    /**
     * 静态工厂方法创建分页实例
     *
     * @param int $total       总记录数
     * @param int $perPage     每页记录数，默认 15
     * @param int $currentPage 当前页码，默认 1
     * @return self 分页实例
     */
    public static function make(int $total, int $perPage = 15, int $currentPage = 1): self
    {
        return new self($total, $perPage, $currentPage);
    }

    /**
     * 获取总页数
     *
     * @return int 总页数（至少为 1）
     */
    public function totalPages(): int
    {
        return (int) max(1, ceil($this->total / $this->perPage));
    }

    /**
     * 获取当前页码（自动限制在有效范围内）
     *
     * @return int 当前页码
     */
    public function currentPage(): int
    {
        return min($this->currentPage, $this->totalPages());
    }

    /**
     * 获取每页记录数
     *
     * @return int 每页记录数
     */
    public function perPage(): int
    {
        return $this->perPage;
    }

    /**
     * 获取总记录数
     *
     * @return int 总记录数
     */
    public function total(): int
    {
        return $this->total;
    }

    /**
     * 获取当前页的 SQL 起始偏移量（OFFSET）
     *
     * @return int 偏移量，如第 1 页返回 0，第 2 页返回 perPage
     */
    public function offset(): int
    {
        return ($this->currentPage() - 1) * $this->perPage;
    }

    /**
     * 是否存在上一页
     *
     * @return bool 存在返回 true
     */
    public function hasPrevious(): bool
    {
        return $this->currentPage() > 1;
    }

    /**
     * 是否存在下一页
     *
     * @return bool 存在返回 true
     */
    public function hasNext(): bool
    {
        return $this->currentPage() < $this->totalPages();
    }

    /**
     * 获取上一页页码
     *
     * @return int|null 上一页页码；不存在返回 null
     */
    public function previousPage(): ?int
    {
        return $this->hasPrevious() ? $this->currentPage() - 1 : null;
    }

    /**
     * 获取下一页页码
     *
     * @return int|null 下一页页码；不存在返回 null
     */
    public function nextPage(): ?int
    {
        return $this->hasNext() ? $this->currentPage() + 1 : null;
    }

    /**
     * 获取首页页码
     *
     * @return int 首页页码（始终为 1）
     */
    public function firstPage(): int
    {
        return 1;
    }

    /**
     * 获取尾页页码
     *
     * @return int 尾页页码
     */
    public function lastPage(): int
    {
        return $this->totalPages();
    }

    /**
     * 当前页起始记录编号（从 1 开始计数）
     *
     * @return int 起始编号；无记录时返回 0
     */
    public function from(): int
    {
        if ($this->total === 0) return 0;
        return $this->offset() + 1;
    }

    /**
     * 当前页结束记录编号
     *
     * @return int 结束编号
     */
    public function to(): int
    {
        return min($this->offset() + $this->perPage, $this->total);
    }

    /**
     * 将分页信息导出为数组
     *
     * @return array 包含 total, per_page, current_page, total_pages, offset, from, to, has_previous, has_next 等字段的数组
     */
    public function toArray(): array
    {
        return [
            'total'         => $this->total,
            'per_page'      => $this->perPage,
            'current_page'  => $this->currentPage(),
            'total_pages'   => $this->totalPages(),
            'offset'        => $this->offset(),
            'from'          => $this->from(),
            'to'            => $this->to(),
            'has_previous'  => $this->hasPrevious(),
            'has_next'      => $this->hasNext(),
            'previous_page' => $this->previousPage(),
            'next_page'     => $this->nextPage(),
            'first_page'    => $this->firstPage(),
            'last_page'     => $this->lastPage(),
            'page_range'    => $this->getPageRange(),
        ];
    }

    /**
     * 获取页码导航范围数组（用于前端分页条渲染）
     *
     * 例如总 10 页、当前第 5 页、range=2 时返回 [3, 4, 5, 6, 7]
     *
     * @return int[] 页码数组
     */
    public function getPageRange(): array
    {
        $current = $this->currentPage();
        $total = $this->totalPages();
        $range = $this->pageRange;

        $start = max(1, $current - $range);
        $end = min($total, $current + $range);

        // 调整以尽量显示更多页码
        if ($end - $start < $range * 2) {
            if ($start === 1) {
                $end = min($total, $start + $range * 2);
            } elseif ($end === $total) {
                $start = max(1, $end - $range * 2);
            }
        }

        return range($start, $end);
    }

    /**
     * 生成 HTML 分页导航条（简洁样式，含首页/尾页/省略号）
     *
     * @param string $urlPattern URL 模式，需包含 {page} 占位符，如 "/list?page={page}"
     * @return string HTML 分页导航字符串；只有一页时返回空字符串
     */
    public function render(string $urlPattern = '?page={page}'): string
    {
        if ($this->totalPages() <= 1) {
            return '';
        }

        $pages = [];
        $current = $this->currentPage();

        // 上一页
        if ($this->hasPrevious()) {
            $pages[] = $this->renderLink(str_replace('{page}', (string) $this->previousPage(), $urlPattern), '上一页', 'prev');
        }

        // 首页
        if ($current > $this->pageRange + 1) {
            $pages[] = $this->renderLink(str_replace('{page}', '1', $urlPattern), '1');
            $pages[] = '<span class="ellipsis">...</span>';
        }

        // 页码范围
        foreach ($this->getPageRange() as $page) {
            if ($page === $current) {
                $pages[] = "<span class=\"current\">{$page}</span>";
            } else {
                $pages[] = $this->renderLink(str_replace('{page}', (string) $page, $urlPattern), (string) $page);
            }
        }

        // 尾页
        if ($current < $this->totalPages() - $this->pageRange) {
            $pages[] = '<span class="ellipsis">...</span>';
            $pages[] = $this->renderLink(str_replace('{page}', (string) $this->totalPages(), $urlPattern), (string) $this->totalPages());
        }

        // 下一页
        if ($this->hasNext()) {
            $pages[] = $this->renderLink(str_replace('{page}', (string) $this->nextPage(), $urlPattern), '下一页', 'next');
        }

        return '<div class="pagination">' . implode('', $pages) . '</div>';
    }

    /**
     * 设置页码导航栏两侧显示的页码数量
     *
     * @param int $range 页码数量（至少为 1）
     * @return self 支持链式调用
     */
    public function setPageRange(int $range): self
    {
        $this->pageRange = max(1, $range);
        return $this;
    }

    /**
     * 渲染单个分页链接 HTML
     *
     * @param string $url  链接地址
     * @param string $text 显示文本
     * @param string $class CSS 类名
     * @return string HTML 链接标签
     */
    private function renderLink(string $url, string $text, string $class = ''): string
    {
        $cls = $class ? ' class="' . $class . '"' : '';
        return '<a href="' . htmlspecialchars($url) . '"' . $cls . '>' . $text . '</a>';
    }
}
