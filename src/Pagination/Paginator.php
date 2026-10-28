<?php

declare(strict_types=1);

namespace zxf\Utils\Pagination;

use InvalidArgumentException;

/**
 * 分页工具类
 * 提供分页数据计算、页码范围生成、SQL 偏移量计算、HTML 导航渲染等功能
 *
 * @package zxf\Utils\Pagination
 * @version 1.0.0
 * @license MIT
 *
 * @example
 * $pager = new \zxf\Utils\Pagination\Paginator(100, 10, 1); // 总数 100，每页 10
 * $pager->offset();        // 0（SQL OFFSET）
 * $pager->totalPages();    // 10
 * echo $pager->render('/list?page={page}'); // 输出 HTML 分页条
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
     * 按 ceil(total / perPage) 向上取整，并用 max(1, …) 兜底：
     * 即使总记录数为 0 也会返回 1 页，避免下游出现除零或空导航。
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
     * 返回构造时设定的每页条数，构造阶段已校验其不小于 1，
     * 因此该值始终为正数。
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
     * 返回构造时传入的原始总数，不做任何修正；与 totalPages() 的兜底不同，
     * 总数为 0 时本方法返回的仍是 0。
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
     * 以「钳制后的当前页」是否大于 1 判定，因此传入超出范围的页码时
     * 结论依然正确（如 total=0 时恒为 false）。
     *
     * @return bool 存在上一页返回 true
     */
    public function hasPrevious(): bool
    {
        return $this->currentPage() > 1;
    }

    /**
     * 是否存在下一页
     *
     * 以「钳制后的当前页」是否小于总页数判定，因此当前页超出范围时
     * 会先被收敛到最后一页，结果恒为 false。
     *
     * @return bool 存在下一页返回 true
     */
    public function hasNext(): bool
    {
        return $this->currentPage() < $this->totalPages();
    }

    /**
     * 获取上一页页码
     *
     * 基于钳制后的当前页减 1 计算；已在第一页（含总数 0 的情况）时返回 null，
     * 调用方应据此隐藏「上一页」按钮。
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
     * 基于钳制后的当前页加 1 计算；已在最后一页时返回 null。
     * 注意当前页被传入超出总页数的值时，会先收敛到最后一页，因此同样返回 null。
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
     * 固定返回 1（即使总记录数为 0 也是如此），便于前端统一渲染「首页」入口。
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
     * 等价于 totalPages()，同样有「至少 1 页」的兜底；
     * 总记录数为 0 时返回 1 而不是 0。
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

        // 页码范围（只计算一次；两端依据窗口边界与首页/尾页的距离补省略号）
        $rangePages = $this->getPageRange();
        $first = $rangePages[0];
        $last = $rangePages[count($rangePages) - 1];
        $totalPages = $this->totalPages();

        // 首页 / 前省略号：仅当窗口确实与首页拉开距离时才出现（窗口含 2 时不输出多余的 "1 ... 2"）
        if ($first > 1) {
            $pages[] = $this->renderLink(str_replace('{page}', '1', $urlPattern), '1');
            if ($first > 2) {
                $pages[] = '<span class="ellipsis">...</span>';
            }
        }

        // 页码范围
        foreach ($rangePages as $page) {
            if ($page === $current) {
                $pages[] = "<span class=\"current\">{$page}</span>";
            } else {
                $pages[] = $this->renderLink(str_replace('{page}', (string) $page, $urlPattern), (string) $page);
            }
        }

        // 尾页 / 尾省略号
        if ($last < $totalPages) {
            if ($last < $totalPages - 1) {
                $pages[] = '<span class="ellipsis">...</span>';
            }
            $pages[] = $this->renderLink(str_replace('{page}', (string) $totalPages, $urlPattern), (string) $totalPages);
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
