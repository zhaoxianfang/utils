<?php

declare(strict_types=1);

namespace zxf\Utils\Data;

use Closure;
use InvalidArgumentException;

/**
 * 树形结构工具类
 * 用于将一维数组转换为树形结构
 *  结构：
 *      $data = [
 *          ['id' => 1, 'pid' => 0, 'name' => 'Node 1',...],
 *          ['id' => 2, 'pid' => 1, 'name' => 'Node 2', 'weight' => 10,...],
 *      ];
 *  用法：
 *      // 使用默认配置 初始化数据（非单例推荐直接使用 new）
 *      $tree = new Tree($data);
 *      OR
 *      $tree = Tree::instance($data);
 *      $tree->setData($data);
 *      // 自定义id、pid、children配置
 *      $tree->setId('id')->setPid('pid')->setChildlist('children')->toTree();
 *      // 自定义权重字段和排序方式
 *      $tree->setWeight('weight')->setSortType('desc')->toTree();
 *      // 自定义根节点id，默认为0
 *      $tree->setRootId(1)->toTree();
 *      // 注意：instance() 为单例模式，重复传入新数据会自动更新 data
 *  接口:
 *      // 获取结构树
 *      $nodes = $tree->toTree();
 *      // 获取所有子节点的主键（包含自己）
 *      $nodes = $tree->getChildrenAndMeIds(1);
 *      // 获取所有子节点列表（包含自己）
 *      $nodes = $tree->getChildrenAndMeNodes(1);
 *      // 获取所有子节点的主键（不包含自己）
 *      $nodes = $tree->getChildrenIds(1);
 *      // 获取所有子节点列表（不包含自己）
 *      $nodes = $tree->getChildrenNodes(1);
 *      // 获取所有父节点主键(包含自己)
 *      $nodes = $tree->getParentAndMeIds(5);
 *      // 获取所有父节点列表(包含自己)
 *      $nodes = $tree->getParentAndMeNodes(5);
 *      // 获取所有父节点主键(不包含自己)
 *      $nodes = $tree->getParentIds(5);
 *      // 获取所有父节点列表(不包含自己)
 *      $nodes = $tree->getParentNodes(5);
 *      // 获取所有根节点主键
 *      $roots = $tree->getRootsIds();
 *      // 重新初始化数据
 *      $tree->reset();
 *      // 添加新节点
 *      $tree->addNode(['id' => 7, 'pid' => 0, 'name' => 'New Node']);
 *      // 删除节点
 *      $tree->removeNode(7);
 *      // 更新节点
 *      $tree->updateNode(2, ['name' => 'Updated Node']);
 *      // 克隆: 可以防止连续的链式调用导致 data 数据变更带来的问题
 *      $tree->clone();
 *      // 获取所有数据id
 *      $ids = $tree->getAllIds();
 *      // 获取字段值集合
 *      $ages = $tree->pluck('age');  // 获取所有age值
 *      // 将树形结构扁平化
 *      $treeData = $tree->toTree(); // 带children 的 tree 树形数组数据
 *      $arrayData = $tree->toArray(); // 转换为Tree 树形结构，再展开为一维数组结构
 *      $flattened = $tree->flatten($treeData);
 *  前缀与图标：
 *      // 为输出结果中指定字段统一添加前缀（延迟到 toTree/toArray 时生效）
 *      $tree->setPrefix('name', '【部门】')->toTree();
 *      // 为 toArray/toTree 输出中的 title 字段添加层级图标前缀
 *      $tree->withIcon(true)->toArray();
 *  条件查询：
 *      // 基础形式
 *      $tree->where('age', 18)->where('score', '>', 60)->where('name', 'like', '%张%');
 *      // 数组形式
 *      $tree->where(['age' => 18])->where([['age', '>', 20],['status', 1]]);
 *      // 闭包形式
 *      $tree->where(fn($item) => $item['age'] > 18 && $item['score'] > 80);
 *      // 空值判断
 *      $tree->whereNull('name');
 *      $tree->whereNotNull('name');
 *      // 范围查询
 *      $tree->whereIn('score', [70,99,88]);
 *      $tree->whereNotIn('score', [70,99,88]);
 *      $tree->whereBetween('score', [70,99]);
 *      $tree->whereNotBetween('score', [70,99]);
 *      // 模糊匹配
 *      $tree->whereLike('name', '%张%');       // 查询name包含"张"的记录
 *  字段修改：
 *      // 给满足 $where 条件的节点数据添加/更新字段属性
 *      $tree->addField($where, function() {
 *          return ['is_man' => 1, 'gender_text' => '男性'];
 *      });
 *      // 为满足条件的节点移除字段属性
 *      $tree->removeField(['gender' => 1], function() {
 *          return 'is_man'; // 删除 is_man 字段属性
 *          // 或者返回数组 ['is_man', 'gender_text']
 *      });
 *      // 给满足 $where 条件的[第一个]节点及其所有父节点 添加/更新字段属性
 *      $tree->addFieldWithParentIds($where, function() {
 *          return ['is_man' => 1, 'gender_text' => '男性'];
 *      });
 *      // 给满足 $where 条件的[第一个]节点及其所有子节点 移除字段属性
 *      $tree->removeFieldWithParentIds(['gender' => 1], function() {
 *          return 'is_man'; // 删除 is_man 字段属性
 *          // 或者返回数组 ['is_man', 'gender_text']
 *      });
 */
class Tree
{
    private static ?self $instance = null;

    private array $data = []; // 原始数据数组

    private ?array $indexById = null; // id -> 节点 的惰性索引，避免祖先/子孙查询退化为 O(n^2)

    private string $id = 'id'; // 节点ID字段名，默认为'id'

    private string $pid = 'pid'; // 父节点ID字段名，默认为'pid'

    private string $title = 'title'; // 父节点名称/标题字段名，默认为'title'

    private string $weight = 'weight'; // 根据权重排序字段，如果结构中存在此字段则降序排序，不不存在则跳过，默认为'weight'

    private string $sortType = 'desc'; // 如果存在 $weight 的前提下 根据权重排序的方式，desc 降序 asc 升序，默认为'desc'

    private string $childlist = 'children'; // 子节点列表字段名，默认为'children'

    private int $rootId = 0; // 根节点ID，默认为0

    /**
     * 生成树型结构所需修饰符号，可以换成图片
     */
    protected array $iconStyle = [' ︱', ' ├', ' └'];

    private bool $withIcon = false; // toTree/toArray 是否携带层级前缀

    private ?array $prefixConfig = null; // setPrefix 记录的配置 ['field' => ..., 'prefix' => ...]

    /**
     * 构造函数，接受原始数据数组
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * 获取全局单例（可顺带注入数据）
     *
     * 实例不存在时以 $data 创建；已存在且 $data 非空时用其覆盖现有数据
     * （会同时使 id 索引失效）。因此传入的非空数据会替换已有内容，而不是追加。
     *
     * @param array $data 初始/替换用的原始数据数组，空数组表示不改动现有数据
     * @return static 单例实例
     */
    public static function instance(array $data = []): static
    {
        if (self::$instance === null) {
            self::$instance = new static($data);
        } elseif ($data !== []) {
            self::$instance->setData($data);
        }

        return self::$instance;
    }

    /**
     * 设置原始数据
     *
     * 整体替换内部数据数组（不追加），并让缓存的 id 索引失效，保证后续
     * findNode() 等查询基于新数据重建索引。
     *
     * @param array $data 节点数组，每个元素为一条含 id/pid 等字段的记录
     * @return self 当前实例，便于链式调用
     */
    public function setData(array $data = [])
    {
        $this->data = $data;
        $this->invalidateIndex();

        return $this;
    }

    /**
     * 设置节点ID字段名
     *
     * 指定数据中作为主键的字段名，同时使 id 索引失效以便按新字段重建。
     *
     * @param string $id 主键字段名，默认 id
     * @return Tree 当前实例，便于链式调用
     */
    public function setId(string $id = 'id')
    {
        $this->id = $id;
        $this->invalidateIndex();

        return $this;
    }

    /**
     * 设置父节点ID字段名
     *
     * @param string $pid 父节点字段名，默认 pid
     * @return Tree 当前实例，便于链式调用
     */
    public function setPid(string $pid = 'pid')
    {
        $this->pid = $pid;

        return $this;
    }

    /**
     * 设置节点名称/标题 字段名
     *
     * @param string $title 标题字段名，默认 title
     * @return Tree 当前实例，便于链式调用
     */
    public function setTitle(string $title = 'title')
    {
        $this->title = $title;

        return $this;
    }

    /**
     * 设置子节点列表字段名
     *
     * 该字段用于承载 toTree() 生成的子节点集合。
     *
     * @param string $childlist 子节点列表字段名，默认 childlist
     * @return Tree 当前实例，便于链式调用
     */
    public function setChildlist(string $childlist = 'childlist')
    {
        $this->childlist = $childlist;

        return $this;
    }

    /**
     * 设置排序字段
     *
     * 指定 toTree() 排序时使用的权重字段名；仅记录配置，实际排序在输出阶段
     * 由 sortNodesByWeight() 执行，缺失该字段的节点按权重 0 参与比较。
     *
     * @param string $field 权重字段名，默认 weight
     * @return self 当前实例，便于链式调用
     */
    public function setWeight(string $field = 'weight')
    {
        $this->weight = $field;

        return $this;
    }

    /**
     * 设置权重排序方向
     *
     * 仅在设置了权重字段（setWeight）时生效；除 desc/asc 之外的取值一律
     * 回退为降序，避免产生未定义顺序。
     *
     * @param string $sortType 排序方向，desc 降序（默认）或 asc 升序
     * @return self 当前实例，便于链式调用
     */
    public function setSortType(string $sortType = 'desc')
    {
        $this->sortType = in_array($sortType, ['desc', 'asc'], true) ? strtolower($sortType) : 'desc';

        return $this;
    }

    /**
     * 设置根节点 ID（默认 0）
     *
     * 用于界定树形结构的根节点判定条件，影响 toTree() 等方法的输出。
     *
     * @param int $rootId 作为根节点判定的父 ID 值
     * @return self
     */
    public function setRootId(int $rootId = 0)
    {
        $this->rootId = (int) $rootId;

        return $this;
    }

    /**
     * 设置是否在为 toArray 输出时添加层级图标前缀
     *
     * @param bool $withIcon true=添加前缀，false=不添加
     * @return self
     */
    public function withIcon(bool $withIcon = true): self
    {
        $this->withIcon = $withIcon;

        return $this;
    }

    /**
     * 记录为指定字段添加统一前缀的配置
     *
     * 实际的前缀添加会延迟到 toTree() 或 toArray() 输出时执行，
     * 避免在调用本方法后新增/修改的数据遗漏前缀处理。
     *
     * @param string $field 要修改的字段名
     * @param string $prefix 前缀字符串
     * @return self
     */
    public function setPrefix(string $field, string $prefix): self
    {
        $this->prefixConfig = ['field' => $field, 'prefix' => $prefix];

        return $this;
    }

    /**
     * 将原始数据转换为树形结构
     *
     * 以 rootId 为根判定条件组装出多层级数组并返回根节点列表；根节点及其子
     * 节点均按权重字段与排序方向排序。若之前调用了 setPrefix() 或
     * withIcon(true)，输出结果会自动应用对应的前缀和层级图标。
     *
     * @return array 根节点列表，每个节点含 childlist 字段（无子节点时该字段可能不存在）
     */
    public function toTree(): array
    {
        $treeMap = array_to_tree($this->data, $this->rootId, $this->id, $this->pid, $this->childlist);

        // 找出根节点（指定根节点id），使用严格比较避免 null/空字符串被误判
        $rootNodes = array_filter($treeMap, fn ($item) => (int) $item[$this->pid] === $this->rootId);

        // 使用传统方法对根节点进行排序
        $rootNodes && $this->sortNodesByWeight($rootNodes);

        $tree = array_values($rootNodes);

        // 延迟应用前缀配置
        if ($this->prefixConfig !== null) {
            $tree = $this->applyPrefixToTreeNodes($tree, $this->prefixConfig['field'], $this->prefixConfig['prefix']);
        }

        // 延迟应用层级图标前缀
        if ($this->withIcon) {
            $tree = $this->processTreeWithPrefix($tree);
        }

        return $tree;
    }

    /**
     * 根据权重字段对节点数组进行排序
     *
     * 通过引用就地重排 $nodes（按 sortType 决定升降序，缺失权重字段按 0 处理），
     * 随后递归对每个节点的子节点做同样排序，最后返回同一数组。
     *
     * @param  array  &$nodes  节点数组，传入即被排序
     * @return array 排序后的节点数组（与入参为同一份数据）
     */
    private function sortNodesByWeight(array &$nodes): array
    {
        $sortType = $this->sortType;
        $weightField = $this->weight;

        // 根据权重字段对节点进行排序，使用 ?? 正确处理 0 值
        usort($nodes, function ($a, $b) use ($sortType, $weightField) {
            $weightA = $a[$weightField] ?? 0;
            $weightB = $b[$weightField] ?? 0;

            if ($sortType === 'desc') {
                return $weightB <=> $weightA;
            }

            return $weightA <=> $weightB;
        });

        $nodes && $this->sortChildrenByWeight($nodes); // 递归调用对子节点的子节点进行排序

        return $nodes;
    }

    /**
     * 递归地对节点的子节点进行排序
     *
     * @param  array  &$nodes  节点数组
     */
    private function sortChildrenByWeight(array &$nodes): void
    {
        foreach ($nodes as &$node) {
            if (isset($node[$this->childlist])) {
                $children = &$node[$this->childlist];

                // 使用传统方法对子节点进行排序
                $children && $this->sortNodesByWeight($children);
            }
        }
    }

    /**
     * 获取指定节点的所有子孙节点ID(包含自己)
     *
     * 遍历原始数据，用 isDescendant() 判断每个节点是否位于 $id 子树中；
     * 结果顺序与数据存储顺序一致，命中顺序码不保证与层级顺序相同。
     *
     * @param int $id 目标节点ID
     * @return array 子孙节点（含自身）的ID列表
     */
    public function getChildrenAndMeIds(int $id): array
    {
        $descendants = [];

        foreach ($this->data as $item) {
            if ($this->isDescendant($item[$this->id], $id)) {
                $descendants[] = $item[$this->id];
            }
        }

        return $descendants;
    }

    /**
     * 获取指定节点的所有子孙节点列表(包含自己)
     *
     * 与 getChildrenAndMeIds() 判定逻辑相同，但返回完整的节点数组，顺序为
     * 数据存储顺序。
     *
     * @param int $id 目标节点ID
     * @return array 子孙节点（含自身）的记录数组
     */
    public function getChildrenAndMeNodes(int $id): array
    {
        $descendants = [];

        foreach ($this->data as $item) {
            if ($this->isDescendant($item[$this->id], $id)) {
                $descendants[] = $item;
            }
        }

        return $descendants;
    }

    /**
     * 获取指定节点的所有子孙节点ID(不包含自己)
     *
     * 以 isDescendant(..., false) 判定，仅收集 $id 的下级节点；$id 不存在
     * 或没有下级时返回空数组。
     *
     * @param int $id 目标节点ID
     * @return array 子孙节点的ID列表（不含自身）
     */
    public function getChildrenIds(int $id): array
    {
        $descendants = [];

        foreach ($this->data as $item) {
            if ($this->isDescendant($item[$this->id], $id, false)) {
                $descendants[] = $item[$this->id];
            }
        }

        return $descendants;
    }

    /**
     * 获取指定节点的所有子孙节点列表(不包含自己)
     *
     * 与 getChildrenIds() 判定逻辑相同，返回完整节点数组而非仅 ID。
     *
     * @param int $id 目标节点ID
     * @return array 子孙节点的记录数组（不含自身）
     */
    public function getChildrenNodes(int $id): array
    {
        $descendants = [];

        foreach ($this->data as $item) {
            if ($this->isDescendant($item[$this->id], $id, false)) {
                $descendants[] = $item;
            }
        }

        return $descendants;
    }

    /**
     * 惰性构建 id -> 节点 索引（仅构建一次，数据变更后失效）
     */
    private function buildIndex(): void
    {
        $this->indexById = [];
        foreach ($this->data as $item) {
            if (isset($item[$this->id])) {
                $this->indexById[$item[$this->id]] = $item;
            }
        }
    }

    /**
     * 通过 id 查找节点（O(1)，首次访问时构建索引）
     *
     * 首次调用会建立 id => 节点 的索引并缓存，之后直接命中；数据经
     * setData()、addNode() 等方法变更时索引会被置空以触发重建。
     *
     * @param int|string $id 节点ID（与数据中 id 字段类型保持一致才能命中）
     * @return array|null 节点记录；未找到返回 null
     */
    private function findNode(int|string $id): ?array
    {
        if ($this->indexById === null) {
            $this->buildIndex();
        }

        return $this->indexById[$id] ?? null;
    }

    /**
     * 数据变更后使索引失效
     */
    private function invalidateIndex(): void
    {
        $this->indexById = null;
    }

    /**
     * 判断指定节点是否是指定父节点的子孙节点
     *
     * 沿 pid 链自下而上逐级查找，命中目标父节点即返回 true。通过 $visited
     * 记录已访问节点，遇到环形数据时提前返回 false，避免无限递归。
     *
     * @param  int  $itemId  要查询的节点id
     * @param  int  $parentId  要查询的父节点id
     * @param  bool  $includeSelf  是否包含自己
     * @param  array  &$visited  已访问节点集合（内部递归用，外部一般留空）
     * @return bool 属于该父节点的子孙（或等于自身且 includeSelf 为 true）时返回 true
     */
    private function isDescendant(int $itemId, int $parentId, bool $includeSelf = true, array &$visited = []): bool
    {
        if (isset($visited[$itemId])) {
            return false; // 检测到循环引用，防止栈溢出
        }
        $visited[$itemId] = true;

        $item = $this->findNode($itemId);
        if ($item !== null) {// 先找到要查询的那个节点
            if ($includeSelf ? $item[$this->id] === $parentId : $item[$this->pid] === $parentId) {
                return true;
            }

            // 一直向上找父节点，判断父节点是否存在要查询的指定 id父节点
            if ($this->isDescendant($item[$this->pid], $parentId, $includeSelf, $visited)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取指定节点的所有父节点ids(包含自己)
     *
     * 沿 pid 链向上收集直到 pid 为 0 或节点缺失，并对 $visited 检测环；
     * 返回顺序经 array_reverse 处理，为「根 → … → 自身」。
     *
     * @param int $id 目标节点ID
     * @return array 祖辈链ID列表（含自身），由顶层到自身排列
     */
    public function getParentAndMeIds(int $id): array
    {
        $parents = [];
        $currentId = $id;
        $visited = [];

        while ($currentId !== 0) {
            if (isset($visited[$currentId])) {
                break; // 检测到循环引用，终止查找
            }
            $visited[$currentId] = true;

            $found = false;
            $item = $this->findNode($currentId);
            if ($item !== null) {
                $parents[] = $currentId;
                $currentId = $item[$this->pid];
                $found = true;
            }

            if (! $found) {
                break; // 找不到对应节点，终止查找
            }
        }

        return array_reverse($parents);
    }

    /**
     * 获取指定节点的所有父节点列表(包含自己)
     *
     * 与 getParentAndMeIds() 数据来源与顺序完全一致：沿 pid 链向上收集后
     * array_reverse，返回「根 → … → 自身」的节点记录数组。
     *
     * @param int $id 目标节点ID
     * @return array 祖辈链节点记录（含自身），由顶层到自身排列
     */
    public function getParentAndMeNodes(int $id): array
    {
        $parents = [];
        $currentId = $id;
        $visited = [];

        while ($currentId !== 0) {
            if (isset($visited[$currentId])) {
                break;
            }
            $visited[$currentId] = true;

            $found = false;
            $item = $this->findNode($currentId);
            if ($item !== null) {
                $parents[] = $item;
                $currentId = $item[$this->pid];
                $found = true;
            }

            if (! $found) {
                break;
            }
        }

        return array_reverse($parents);
    }

    /**
     * 获取指定节点的所有父节点ids(不包含自己)
     *
     * 沿 pid 链向上收集但跳过起点自身，并对 $visited 检测环；结果经
     * array_reverse 处理，为「根 → … → 直接父节点」。
     *
     * @param int $id 目标节点ID
     * @return array 祖辈链ID列表（不含自身），由顶层到直接父节点排列
     */
    public function getParentIds(int $id): array
    {
        $parents = [];
        $currentId = $id;
        $visited = [];

        while ($currentId !== 0) {
            if (isset($visited[$currentId])) {
                break;
            }
            $visited[$currentId] = true;

            $found = false;
            $item = $this->findNode($currentId);
            if ($item !== null) {
                if ($currentId !== $id) {
                    $parents[] = $currentId;
                }
                $currentId = $item[$this->pid];
                $found = true;
            }

            if (! $found) {
                break;
            }
        }

        return array_reverse($parents);
    }

    /**
     * 获取指定节点的所有父节点列表(不包含自己)
     *
     * 与 getParentIds() 判定与顺序完全一致：跳过起点自身向上收集后
     * array_reverse，返回「根 → … → 直接父节点」的节点记录数组。
     *
     * @param int $id 目标节点ID
     * @return array 祖辈链节点记录（不含自身），由顶层到直接父节点排列
     */
    public function getParentNodes(int $id): array
    {
        $parents = [];
        $currentId = $id;
        $visited = [];

        while ($currentId !== 0) {
            if (isset($visited[$currentId])) {
                break;
            }
            $visited[$currentId] = true;

            $found = false;
            $item = $this->findNode($currentId);
            if ($item !== null) {
                if ($currentId !== $id) {
                    $parents[] = $item;
                }
                $currentId = $item[$this->pid];
                $found = true;
            }

            if (! $found) {
                break;
            }
        }

        return array_reverse($parents);
    }

    /**
     * 获取所有的根节点ids
     *
     * 以严格比较筛选 pid 等于 rootId 的记录（默认 rootId 为 0），返回其 id
     * 列表；注意 pid 为字符串 "0" 时不会命中，需与 rootId 类型保持一致。
     *
     * @return array 根节点ID列表，顺序与数据存储顺序一致
     */
    public function getRootsIds(): array
    {
        $roots = [];

        foreach ($this->data as $item) {
            if ($item[$this->pid] === $this->rootId) {
                $roots[] = $item[$this->id];
            }
        }

        return $roots;
    }

    /**********************************************
     *              条件查询方法组 开始                 *
     **********************************************/
    /**
     * 基础条件查询
     * 支持三种形式：
     * 1. 基础形式:
     *    where('age', 19)
     *    where('age', '=', 19)
     *    where('age', '>', 19)
     *    where('name', 'like', '%三%')
     *
     * 2. 数组形式:
     *    where(['age' => 19])
     *    where([
     *      ['age', '>', 20],
     *      ['status', 1]
     *    ])
     *
     * 3. 闭包形式:
     *    where(fn($item) => $item['age'] > 18)
     *
     * @param  mixed  ...$args
     * @return self
     */
    public function where(...$args): self
    {
        if (empty($args)) {
            return $this;
        }
        $this->invalidateIndex();

        // 闭包形式处理
        if ($args[0] instanceof Closure) {
            $this->data = array_filter($this->data, $args[0]);

            return $this;
        }

        // 数组形式处理
        if (is_array($args[0])) {
            $conditions = $args[0];

            // 关联数组转换为条件数组
            if (! array_is_list($conditions)) {
                $conditions = array_map(
                    fn ($k, $v) => is_array($v) ? [$k, ...$v] : [$k, $v],
                    array_keys($conditions),
                    array_values($conditions)
                );
            }

            // 处理多维条件
            $this->data = array_filter($this->data, function ($item) use ($conditions) {
                foreach ($conditions as $condition) {
                    if (! $this->compareCondition($item, $condition)) {
                        return false;
                    }
                }

                return true;
            });

            return $this;
        }

        // 基础形式处理
        $this->data = array_filter($this->data, fn ($item) => $this->compareCondition($item, $args));

        return $this;
    }

    /**
     * NULL条件查询
     *
     * @param  string  $field  要查询的字段名
     * @return self
     *
     * 使用示例:
     * $tree->whereNull('deleted_at');  // 查询deleted_at为NULL的记录
     */
    public function whereNull(string $field): self
    {
        $this->data = array_filter($this->data,
            fn ($item) => ! array_key_exists($field, $item) || $item[$field] === null
        );

        return $this;
    }

    /**
     * 非NULL条件查询
     *
     * @param  string  $field  要查询的字段名
     * @return self
     *
     * 使用示例:
     * $tree->whereNotNull('email');  // 查询email不为NULL的记录
     */
    public function whereNotNull(string $field): self
    {
        $this->data = array_filter($this->data,
            fn ($item) => array_key_exists($field, $item) && $item[$field] !== null
        );

        return $this;
    }

    /**
     * IN条件查询
     *
     * @param  string  $field  要查询的字段名
     * @param  array  $values  允许的值数组
     * @return self
     *
     * 使用示例:
     * $tree->whereIn('status', [1, 2, 3]);  // 查询status为1,2或3的记录
     */
    public function whereIn(string $field, array $values): self
    {
        $this->data = array_filter($this->data,
            fn ($item) => array_key_exists($field, $item) && in_array($item[$field], $values, true)
        );

        return $this;
    }

    /**
     * NOT IN条件查询
     *
     * @param  string  $field  要查询的字段名
     * @param  array  $values  排除的值数组
     * @return self
     *
     * 使用示例:
     * $tree->whereNotIn('role', ['guest', 'test']);  // 查询role不是guest或test的记录
     */
    public function whereNotIn(string $field, array $values): self
    {
        $this->data = array_filter($this->data,
            fn ($item) => array_key_exists($field, $item) && ! in_array($item[$field], $values, true)
        );

        return $this;
    }

    /**
     * BETWEEN条件查询
     *
     * @param  string  $field  要查询的字段名
     * @param  array  $range  范围数组[min, max]
     * @return self
     *
     * @throws InvalidArgumentException 当范围参数无效时抛出异常
     *
     * 使用示例:
     * $tree->whereBetween('age', [18, 30]);  // 查询age在18到30之间的记录
     */
    public function whereBetween(string $field, array $range): self
    {
        if (count($range) !== 2) {
            throw new InvalidArgumentException('BETWEEN条件需要包含最小值和最大值的数组');
        }

        $this->data = array_filter($this->data,
            fn ($item) => array_key_exists($field, $item) &&
                         $item[$field] >= $range[0] &&
                         $item[$field] <= $range[1]
        );

        return $this;
    }

    /**
     * NOT BETWEEN条件查询
     *
     * @param  string  $field  要查询的字段名
     * @param  array  $range  范围数组[min, max]
     * @return self
     *
     * @throws InvalidArgumentException 当范围参数无效时抛出异常
     *
     * 使用示例:
     * $tree->whereNotBetween('score', [60, 80]);  // 查询score不在60到80之间的记录
     */
    public function whereNotBetween(string $field, array $range): self
    {
        if (count($range) !== 2) {
            throw new InvalidArgumentException('NOT BETWEEN条件需要包含最小值和最大值的数组');
        }

        $this->data = array_filter($this->data,
            fn ($item) => array_key_exists($field, $item) &&
                         ($item[$field] < $range[0] || $item[$field] > $range[1])
        );

        return $this;
    }

    /**
     * LIKE条件查询
     *
     * @param  string  $field  要查询的字段名
     * @param  string  $pattern  匹配模式(可以使用%通配符)
     * @param  bool  $caseSensitive  是否区分大小写，默认false
     * @return self
     *
     * 使用示例:
     * $tree->whereLike('name', '%张%');       // 查询name包含"张"的记录
     * $tree->whereLike('email', '%@gmail.%'); // 查询gmail邮箱
     */
    public function whereLike(string $field, string $pattern, bool $caseSensitive = false): self
    {
        $pattern = str_replace('%', '', $pattern);
        if ($caseSensitive) {
            $this->data = array_filter($this->data,
                fn ($item) => array_key_exists($field, $item) &&
                             str_contains((string) $item[$field], $pattern)
            );
        } else {
            $this->data = array_filter($this->data,
                fn ($item) => array_key_exists($field, $item) &&
                             str_contains(mb_strtolower((string) $item[$field]), mb_strtolower($pattern))
            );
        }

        return $this;
    }

    /**
     * 比较条件核心方法
     *
     * @param  array  $item  当前数据项
     * @param  mixed  $condition  条件参数
     * @return bool 返回是否匹配
     *
     * @throws InvalidArgumentException 当操作符不支持时抛出异常
     */
    private function compareCondition(array $item, mixed $condition): bool
    {
        // 标准化条件格式
        if (! is_array($condition)) {
            $condition = [$condition];
        }

        $count = count($condition);
        $field = $condition[0] ?? null;
        $operator = $count > 2 ? $condition[1] : '=';
        $value = $count > 2 ? $condition[2] : ($count > 1 ? $condition[1] : null);

        // 检查字段是否存在
        if (! array_key_exists($field, $item)) {
            return false;
        }

        $fieldValue = $item[$field];

        return match ($operator) {
            // 等于比较
            '=', '==' => $fieldValue == $value,
            '===', 'eq' => $fieldValue === $value,

            // 不等于比较
            '!=', '<>' => $fieldValue != $value,
            '!==', 'neq' => $fieldValue !== $value,

            // 数值比较
            '>', 'gt' => $fieldValue > $value,
            '>=', 'gte' => $fieldValue >= $value,
            '<', 'lt' => $fieldValue < $value,
            '<=', 'lte' => $fieldValue <= $value,

            // 字符串比较
            'like' => str_contains((string) $fieldValue, str_replace('%', '', (string) $value)),
            'not like' => ! str_contains((string) $fieldValue, str_replace('%', '', (string) $value)),

            // 包含比较
            'contains' => is_array($fieldValue)
                ? in_array($value, $fieldValue, true)
                : str_contains((string) $fieldValue, (string) $value),
            'not contains' => is_array($fieldValue)
                ? ! in_array($value, $fieldValue, true)
                : ! str_contains((string) $fieldValue, (string) $value),

            // 开头/结尾比较
            'starts with' => str_starts_with((string) $fieldValue, (string) $value),
            'ends with' => str_ends_with((string) $fieldValue, (string) $value),

            // 默认抛出异常
            default => throw new InvalidArgumentException("不支持的操作符: {$operator}"),
        };
    }

    /**********************************************
     *              条件查询方法组 结束                *
     **********************************************/

    /**
     * 获取原始数据(副本)
     *
     * @return array 原始数据数组的副本
     *
     * 使用示例:
     * $rawData = $tree->getData();
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * 获取第一个匹配的节点
     *
     * @param  mixed  ...$args  同where方法的参数
     * @return array|null 返回第一个匹配的节点或null
     *
     * 使用示例:
     * $node = $tree->firstWhere('id', 5);  // 获取id=5的第一个节点
     */
    public function firstWhere(...$args): ?array
    {
        $clone = clone $this;
        $filtered = $clone->where(...$args)->getData();

        $firstKey = array_key_first($filtered);

        return $filtered[$firstKey] ?? null;
    }

    /**********************************************
     *              修改数据字段方法组开始           *
     **********************************************/
    /**
     * 为满足条件的节点添加/更新字段属性
     *
     * @param  array  $where  条件参数，格式同where方法
     * @param  callable  $attrCallback  返回要添加的属性的回调函数
     * @return self
     *
     * 使用示例:
     * $tree->addField(['gender' => 1], function() {
     *     return ['is_man' => 1, 'gender_text' => '男性'];
     * });
     */
    public function addField(array $where, callable $attrCallback): self
    {
        // 获取要添加的属性
        $attributes = $attrCallback();
        if (! is_array($attributes) || empty($attributes)) {
            return $this;
        }

        // 筛选符合条件的节点
        $filteredKeys = [];
        $clone = $this->clone();
        $filteredData = $clone->where($where)->getData();

        // 记录符合条件的节点键
        foreach ($filteredData as $item) {
            $filteredKeys[$item[$this->id]] = true;
        }

        // 添加属性
        foreach ($this->data as &$item) {
            if (in_array($item[$this->id], array_keys($filteredKeys))) {
                $item = array_merge($item, $attributes);
            }
        }

        return $this;
    }

    /**
     * 为满足条件的[第一个]节点及其所有父节点 添加/更新字段属性
     *
     * @param  array  $where  条件参数，格式同where方法
     * @param  callable  $attrCallback  返回要添加的属性的回调函数
     * @return self
     *
     * 使用示例:
     * $tree->addFieldWithParentIds(['gender' => 1], function() {
     *     return ['is_man' => 1, 'gender_text' => '男性'];
     * });
     */
    public function addFieldWithParentIds(array $where, callable $attrCallback): self
    {
        // 获取要添加的属性
        $attributes = $attrCallback();
        if (! is_array($attributes) || empty($attributes)) {
            return $this;
        }

        // 筛选符合条件的节点
        $filteredKeys = [];
        $clone = $this->clone();
        $firstData = $clone->firstWhere($where);
        if (! empty($firstData)) {
            $filteredKeyIds = $clone->getParentAndMeIds($firstData[$this->id]);
            // 记录符合条件的节点键
            foreach ($filteredKeyIds as $id) {
                $filteredKeys[$id] = true;
            }
        }
        // 添加属性
        foreach ($this->data as &$item) {
            if (in_array($item[$this->id], array_keys($filteredKeys))) {
                $item = array_merge($item, $attributes);
            }
        }

        return $this;
    }

    /**
     * 为满足条件的节点移除字段属性
     *
     * @param  array  $where  条件参数，格式同where方法
     * @param  callable  $attrCallback  返回要移除的属性名的回调函数
     * @return self
     *
     * 使用示例:
     * $tree->removeField(['gender' => 1], function() {
     *     return 'is_man';
     *     // 或者返回数组 ['is_man', 'gender_text']
     * });
     */
    public function removeField(array $where, callable $attrCallback): self
    {
        // 获取要移除的属性名
        $attributes = $attrCallback();
        if (empty($attributes)) {
            return $this;
        }

        // 统一转换为数组
        $attributes = is_array($attributes) ? $attributes : [$attributes];

        // 筛选符合条件的节点
        $filteredKeys = [];
        $clone = $this->clone();
        $filteredData = $clone->where($where)->getData();

        // 记录符合条件的节点键
        foreach ($filteredData as $item) {
            $filteredKeys[$item[$this->id]] = true;
        }

        // 移除属性
        foreach ($this->data as &$item) {
            if (in_array($item[$this->id], array_keys($filteredKeys))) {
                foreach ($attributes as $attr) {
                    unset($item[$attr]);
                }
            }
        }

        return $this;
    }

    /**
     * 为满足条件的第一个节点 及其所有子节点 移除字段属性
     *
     * @param  array  $where  条件参数，格式同where方法
     * @param  callable  $attrCallback  返回要移除的属性名的回调函数
     * @return self
     *
     * 使用示例:
     * $tree->removeFieldWithParentIds(['gender' => 1], function() {
     *     return 'is_man';
     *     // 或者返回数组 ['is_man', 'gender_text']
     * });
     */
    public function removeFieldWithParentIds(array $where, callable $attrCallback): self
    {
        // 获取要移除的属性名
        $attributes = $attrCallback();
        if (empty($attributes)) {
            return $this;
        }

        // 统一转换为数组
        $attributes = is_array($attributes) ? $attributes : [$attributes];

        // 筛选符合条件的节点
        $filteredKeys = [];
        $clone = $this->clone();
        $firstData = $clone->firstWhere($where);
        if (! empty($firstData)) {
            $filteredKeyIds = $clone->getChildrenAndMeIds($firstData[$this->id]);
            // 记录符合条件的节点键
            foreach ($filteredKeyIds as $id) {
                $filteredKeys[$id] = true;
            }
        }

        // 移除属性
        foreach ($this->data as &$item) {
            if (in_array($item[$this->id], array_keys($filteredKeys))) {
                foreach ($attributes as $attr) {
                    unset($item[$attr]);
                }
            }
        }

        return $this;
    }
    /**********************************************
    *              修改数据字段方法组结束           *
    **********************************************/

    /**
     * 获取所有数据的ID值
     *
     * 按当前 id 字段名抽取全部主键，返回扁平数组（可能含重复值）。
     *
     * @return array 全部节点ID的一维数组
     */
    public function getAllIds(): array
    {
        return array_column($this->data, $this->id);
    }

    /**
     * 克隆当前 Tree 实例（PHP 8 风格）
     *
     * 用同一份数据新建实例，并逐个复制字段名配置、rootId、图标与前缀设置；
     * 新实例的 id 索引为惰性重建状态，与源实例互不影响。
     *
     * @return self 配置与数据一致的新实例
     */
    public function clone(): self
    {
        $clone = new self($this->data);
        $clone->id = $this->id;
        $clone->pid = $this->pid;
        $clone->title = $this->title;
        $clone->weight = $this->weight;
        $clone->sortType = $this->sortType;
        $clone->childlist = $this->childlist;
        $clone->rootId = $this->rootId;
        $clone->withIcon = $this->withIcon;
        $clone->prefixConfig = $this->prefixConfig;

        return $clone;
    }

    /**
     * 获取字段值集合
     *
     * @param  string  $field  字段名
     * @param  bool  $unique  是否去重，默认true
     * @return array 字段值数组
     *
     * 使用示例:
     * $ages = $tree->pluck('age');  // 获取所有age值
     */
    public function pluck(string $field, bool $unique = true): array
    {
        $values = array_column($this->data, $field);

        return $unique ? array_unique($values) : $values;
    }

    /**
     * 将树形结构扁平化
     *
     * @param  array  $tree  树形结构数据
     * @param  int  $level  当前层级(内部使用)
     * @return array 扁平化后的数组
     *
     * 使用示例:
     * $treeData = $tree->toTree();
     * $flattened = $tree->flatten($treeData);
     */
    public function flatten(array $tree, int $level = 1): array
    {
        $result = [];
        foreach ($tree as $node) {
            $node['_level'] = $level;
            $result[] = $node;
            if (! empty($node[$this->childlist])) {
                $result = array_merge($result, $this->flatten($node[$this->childlist], $level + 1));
            }
        }

        return $result;
    }

    /**
     * 转换为Tree 树形结构，再展开为一维数组结构
     *
     * 注意：展开后会自动移除 children 字段。
     *
     * @return array
     */
    public function toArray(): array
    {
        $tree = $this->toTree();
        $flat = $this->flatten($tree);

        // 展开为一维数组时应移除 children 字段
        foreach ($flat as &$node) {
            unset($node[$this->childlist]);
            unset($node['_level']);
        }

        return $flat;
    }

    /**
     * 递归为树节点指定字段添加统一前缀
     *
     * @param array $tree 树形数据
     * @param string $field 要添加前缀的字段
     * @param string $prefix 前缀字符串
     * @return array 处理后的树形数据
     */
    private function applyPrefixToTreeNodes(array $tree, string $field, string $prefix): array
    {
        $result = [];

        foreach ($tree as $node) {
            if (array_key_exists($field, $node)) {
                $node[$field] = $prefix . $node[$field];
            }

            if (! empty($node[$this->childlist])) {
                $node[$this->childlist] = $this->applyPrefixToTreeNodes($node[$this->childlist], $field, $prefix);
            }

            $result[] = $node;
        }

        return $result;
    }

    /**
     * 处理树形数据，为节点添加前缀
     *
     * @param array $tree 树形数据
     * @param array $prefixes 前缀路径（内部使用）
     * @return array 处理后的树形数据
     */
    private function processTreeWithPrefix(array $tree, array $prefixes = []): array
    {
        $result = [];
        $total = count($tree);
        
        foreach ($tree as $index => $node) {
            $isLast = ($index === $total - 1);
            $isRoot = empty($prefixes);
            $hasChildren = !empty($node[$this->childlist]);
            
            // 构建当前节点的前缀
            $currentPrefix = '';
            if (!$isRoot) {
                // 非根节点，根据层级构建前缀
                foreach ($prefixes as $i => $isParentLast) {
                    if ($i === count($prefixes) - 1) {
                        // 当前层级的父节点
                        $currentPrefix .= $isParentLast ? $this->iconStyle[2] : $this->iconStyle[1];
                    } else {
                        // 更高层级的父节点
                        $currentPrefix .= $isParentLast ? '  ' : $this->iconStyle[0];
                    }
                }
            }
            
            // 修改title字段，添加前缀
            if (isset($node[$this->title])) {
                $node[$this->title] = $currentPrefix . $node[$this->title];
            }
            
            // 处理子节点
            if ($hasChildren) {
                // 递归处理子节点，传递当前节点是否最后一个的信息
                $newPrefixes = array_merge($prefixes, [$isLast]);
                $node[$this->childlist] = $this->processTreeWithPrefix($node[$this->childlist], $newPrefixes);
            }
            
            $result[] = $node;
        }
        
        return $result;
    }

    /**
     * 重置所有属性并清空数据
     *
     * @return self
     */
    public function reset(): self
    {
        $this->id = 'id';
        $this->pid = 'pid';
        $this->title = 'title';
        $this->weight = 'weight';
        $this->sortType = 'desc';
        $this->rootId = 0;
        $this->childlist = 'children';
        $this->withIcon = false;
        $this->prefixConfig = null;
        // $this->data      = [];
        $this->invalidateIndex();

        return $this;
    }

    /**
     * 添加新节点
     *
     * 把节点追加到原始数据末尾（不校验 id 是否重复），并使索引失效，
     * 后续 toTree() 调用会按 pid 关系重新组装。
     *
     * @param array $node 节点记录，需含 id/pid 等字段才能被正确挂载
     * @return self 当前实例，便于链式调用
     */
    public function addNode(array $node)
    {
        $this->data[] = $node;
        $this->invalidateIndex();

        return $this;
    }

    /**
     * 通过主键id删除节点
     *
     * 默认级联删除：先收集该节点的全部子孙 ID（getChildrenIds()），再一并移除，
     * 避免留下 pid 指向已不存在父节点的孤儿记录。传 $cascade = false 可只删自身
     * （子节点会变成孤儿）。删除后重建索引；索引为顺序列表，不会留下空洞键。
     *
     * @param int $id 待删除节点的主键值
     * @param bool $cascade 是否同时删除全部子孙节点，默认 true
     * @return self 当前实例，便于链式调用
     */
    public function removeNode(int $id, bool $cascade = true)
    {
        $ids = $cascade ? array_merge([$id], $this->getChildrenIds($id)) : [$id];

        $this->data = array_values(array_filter(
            $this->data,
            fn ($item) => ! in_array($item[$this->id] ?? null, $ids, true)
        ));
        $this->invalidateIndex();

        return $this;
    }

    /**
     * 通过主键id更新节点
     *
     * 找到第一个主键匹配的节点后用 array_merge 合并新字段（同名键被覆盖，
     * 其余字段保留），随后跳出循环并重建索引。未命中时不修改任何数据。
     *
     * @param int $id 待更新节点的主键值
     * @param array $newData 待合并的字段集合
     * @return self 当前实例，便于链式调用
     */
    public function updateNode(int $id, array $newData)
    {
        foreach ($this->data as &$item) {
            if ($item[$this->id] === $id) {
                $item = array_merge($item, $newData);
                break;
            }
        }
        unset($item);
        $this->invalidateIndex();

        return $this;
    }
}
