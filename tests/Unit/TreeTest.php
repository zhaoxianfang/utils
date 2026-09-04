<?php

declare(strict_types=1);

namespace zxf\Utils\Tests\Unit;

use zxf\Utils\Data\Tree;
use zxf\Utils\Tests\Assert;
use zxf\Utils\Tests\TestCase;

/**
 * 树形结构测试
 *
 * 重点覆盖循环引用：旧实现在 A→B→A 或 id===pid 时会无限递归直至爆栈。
 */
class TreeTest extends TestCase
{
    /**
     * @return list<array{id: int, pid: int, name: string}>
     */
    private function normalRows(): array
    {
        return [
            ['id' => 1, 'pid' => 0, 'name' => 'A'],
            ['id' => 2, 'pid' => 1, 'name' => 'B'],
            ['id' => 3, 'pid' => 1, 'name' => 'C'],
            ['id' => 4, 'pid' => 2, 'name' => 'D'],
        ];
    }

    public function testBuildsNormalTree(): void
    {
        $tree = (new Tree($this->normalRows()))->setRootId(0)->toTree();

        Assert::count(1, $tree);
        Assert::same('A', $tree[0]['name']);
        Assert::count(2, $tree[0]['children']);
    }

    public function testDoesNotHangOnCyclicReference(): void
    {
        $rows = [
            ['id' => 1, 'pid' => 2, 'name' => 'X'],
            ['id' => 2, 'pid' => 1, 'name' => 'Y'],
        ];

        $tree = Assert::doesNotThrow(
            static fn () => (new Tree($rows))->setRootId(1)->toTree(),
            '循环引用不应导致崩溃'
        );

        Assert::count(1, $tree);
        Assert::same('Y', $tree[0]['name']);
    }

    public function testDoesNotHangOnSelfReference(): void
    {
        $rows = [
            ['id' => 1, 'pid' => 1, 'name' => 'Self'],
            ['id' => 2, 'pid' => 1, 'name' => 'Child'],
        ];

        $tree = Assert::doesNotThrow(
            static fn () => (new Tree($rows))->setRootId(1)->toTree(),
            '自引用不应导致崩溃'
        );

        Assert::count(2, $tree);
    }

    public function testArrayToTreeDetectsCycle(): void
    {
        $rows = [['id' => 1, 'pid' => 2], ['id' => 2, 'pid' => 1]];

        $result = Assert::doesNotThrow(static fn () => array_to_tree($rows, 1));

        // 以 1 为根：1 的子节点是 2，2 的子节点本应回到 1（成环），
        // 环被切断后 2 不再有子节点，因此结果只有一个根（id=2）
        Assert::count(1, $result);
        Assert::same(2, $result[0]['id']);
        Assert::same(1, $result[0]['children'][0]['id']);
        Assert::false(isset($result[0]['children'][0]['children']));
    }

    public function testArrayToTreeToleratesMissingPidField(): void
    {
        $result = Assert::doesNotThrow(static fn () => array_to_tree([['id' => 1, 'name' => 'M']], 0));

        Assert::count(1, $result);
        Assert::same('M', $result[0]['name']);
    }

    public function testTreeToArrayFlattens(): void
    {
        $tree = (new Tree($this->normalRows()))->setRootId(0)->toTree();
        $flat = tree_to_array($tree);

        Assert::count(4, $flat);
        Assert::same('A', $flat[0]['name']);
        Assert::same(0, $flat[0]['pid']);
    }

    public function testGetDescendantsAndAncestors(): void
    {
        $tree = new Tree($this->normalRows());

        Assert::same([2, 3, 4], $tree->getChildrenIds(1));
        // 数据为 1→{2,3}，2→{4}，故 2 的子孙只有 4
        Assert::same([4], $tree->getChildrenIds(2));
        Assert::same([1, 2, 3, 4], $tree->getChildrenAndMeIds(1));
        Assert::same([1, 2, 4], $tree->getParentAndMeIds(4));
        // 向上遍历在遇到根节点值 0 时停止，因此 0 本身不计入祖先
        Assert::same([1], $tree->getParentIds(3));
        Assert::count(3, $tree->getParentAndMeNodes(4));
        Assert::same([1], $tree->getRootsIds());
    }

    public function testGetAncestorsDoesNotHangOnCycle(): void
    {
        $tree = new Tree([['id' => 1, 'pid' => 2], ['id' => 2, 'pid' => 1]]);

        Assert::doesNotThrow(static fn () => $tree->getParentAndMeIds(1));
        Assert::doesNotThrow(static fn () => $tree->getParentIds(2));
    }
}
