<?php

declare(strict_types=1);

namespace zxf\Utils\Tests\Unit;

use InvalidArgumentException;
use zxf\Utils\Array\DataArray;
use zxf\Utils\Data\BigNumberCalculator;
use zxf\Utils\Data\IDCardGenerator;
use zxf\Utils\Data\Tree;
use zxf\Utils\Net\Ip;
use zxf\Utils\Support\Arr;
use zxf\Utils\Support\Event;
use zxf\Utils\Support\Fluent;
use zxf\Utils\Tests\Assert;
use zxf\Utils\Tests\TestCase;

// ============================================================================
// 回归测试：本轮审查修复的缺陷
// ============================================================================

interface ReviewFixFluentA
{
}
interface ReviewFixFluentB
{
}
class ReviewFixFluentBoth implements ReviewFixFluentA, ReviewFixFluentB
{
}
class ReviewFixFluentOnlyA implements ReviewFixFluentA
{
}
class ReviewFixFluentModel extends Fluent
{
    // 交叉类型属性：必须同时满足 A 与 B
    public ReviewFixFluentA&ReviewFixFluentB $combo;
}

class ReviewFixesTest extends TestCase
{
    /**
     * Event 通配符 * 必须能匹配（此前 preg_quote 后 str_replace 搜不到 * 导致失效）
     */
    public function testEventWildcardMatch(): void
    {
        $event = new Event();
        $hit = false;
        $event->listen('user.*', static function () use (&$hit): void {
            $hit = true;
        });
        $event->emit('user.created');

        Assert::true($hit, '通配符监听 user.* 应能匹配 user.created');
    }

    /**
     * DataArray::searchKey 通配符模式含 / 时，必须先 preg_quote 否则生成非法正则
     */
    public function testDataArraySearchKeyWithSlash(): void
    {
        $data = new DataArray(['a/b' => 1, 'a/c' => 2, 'x/y/z' => 3]);

        $matches = $data->searchKey('a/*');
        Assert::count(2, $matches);

        $exact = $data->searchKey('x/y/z');
        Assert::count(1, $exact);
    }

    /**
     * Arr::set 使用 null 键写入非数组值时应抛出，而非 TypeError
     */
    public function testArrSetNullKeyRequiresArray(): void
    {
        $arr = ['a' => 1];
        Assert::throws(InvalidArgumentException::class, static fn () => Arr::set($arr, null, 'not-array'));

        $result = Arr::set($arr, null, ['k' => 1]);
        Assert::same(['k' => 1], $result);
    }

    /**
     * BigNumberCalculator::divide 传入字符串 "0" 触发除零错误；空字符串属于非法输入走校验异常
     */
    public function testBigNumberDivideByZeroString(): void
    {
        Assert::throws(\DivisionByZeroError::class, static fn () => BigNumberCalculator::divide('10', '0'));
        Assert::throws(InvalidArgumentException::class, static fn () => BigNumberCalculator::divide('10', ''));
    }

    /**
     * IDCardGenerator 生成的身份证顺序码奇偶必须与请求性别一致（含序列=999 边界）
     */
    public function testIDCardGeneratorGenderParity(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $male = IDCardGenerator::generate(['gender' => 'm', 'length' => 18]);
            Assert::same(1, (int) substr($male, 16, 1) % 2, "男性第17位应为奇数：{$male}");

            $female = IDCardGenerator::generate(['gender' => 'f', 'length' => 18]);
            Assert::same(0, (int) substr($female, 16, 1) % 2, "女性第17位应为偶数：{$female}");
        }
    }

    /**
     * Fluent 交叉类型（A&B）应要求全部满足，而非按联合类型「任一满足即通过」
     */
    public function testFluentIntersectionType(): void
    {
        $model = new ReviewFixFluentModel();
        $both = new ReviewFixFluentBoth();
        $model->combo($both);
        Assert::same($both, $model->combo);

        Assert::throws(
            InvalidArgumentException::class,
            static fn () => (new ReviewFixFluentModel())->combo(new ReviewFixFluentOnlyA())
        );
    }

    /**
     * Net\Ip::getClientIp 默认不信任转发头；仅当 REMOTE_ADDR 命中可信代理时才采纳
     */
    public function testGetClientIpTrustedProxyModel(): void
    {
        $backup = $_SERVER;
        try {
            $_SERVER = [];
            $_SERVER['REMOTE_ADDR'] = '203.0.113.5';
            $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.7, 203.0.113.5';

            // 无可信代理：直接返回直连 IP，忽略伪造的 XFF
            Assert::same('203.0.113.5', Ip::getClientIp());

            // 可信代理：采纳 XFF 最左侧（原始客户端）
            $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
            Assert::same('198.51.100.7', Ip::getClientIp(['10.0.0.1']));

            // 无直连地址时返回 null
            unset($_SERVER['REMOTE_ADDR']);
            Assert::null(Ip::getClientIp());
        } finally {
            $_SERVER = $backup;
        }
    }

    /**
     * Tree 祖先/后代查询引入索引后，结果与线性扫描一致
     */
    public function testTreeParentAndMeIds(): void
    {
        $tree = new Tree([
            ['id' => 1, 'pid' => 0],
            ['id' => 2, 'pid' => 1],
            ['id' => 3, 'pid' => 2],
            ['id' => 4, 'pid' => 1],
        ]);

        Assert::same([1, 2, 3], $tree->getParentAndMeIds(3));
        Assert::same([1, 2], $tree->getParentAndMeIds(2));
        Assert::same([1, 4], $tree->getParentAndMeIds(4));
    }

    /**
     * class_basename 在 Unix 下应正确提取类名（原实现仅认 / 分隔符）
     */
    public function testClassBasenameUnix(): void
    {
        Assert::same('Bar', class_basename('\\Foo\\Bar'));
        Assert::same('Bar', class_basename('Foo\\Bar'));
        Assert::same('Bar', class_basename('\\Foo\\Bar.php'));
    }

    /**
     * buildRequestFormAndSend 必须对输出做 HTML 转义，且二次调用不得重复声明函数而致命
     */
    public function testBuildRequestFormAndSendEscapes(): void
    {
        $html = buildRequestFormAndSend(
            "https://e.x/'><script>",
            ['name' => '<b>x'],
            'post'
        );

        Assert::stringNotContains('<b>x', $html);
        Assert::stringContains('&lt;b&gt;x', $html);
        Assert::stringNotContains("'><script>", $html);

        // 二次调用：原实现会因重复声明全局函数而 fatal
        buildRequestFormAndSend('https://e.x/', ['k' => 'v']);
        buildRequestFormAndSend('https://e.x/', ['k' => 'v']);
        Assert::true(true);
    }

    /**
     * str_en_code 必须显式传入密钥，禁止空密钥（杜绝「无密钥等价于明文」）
     */
    public function testStrEnCodeRequiresKey(): void
    {
        Assert::throws(InvalidArgumentException::class, static fn () => str_en_code('hello'));

        $enc = str_en_code('secret-data', 'en', 0, 'my-key');
        $dec = str_en_code($enc, 'de', 0, 'my-key');
        Assert::same('secret-data', $dec);
    }

    /**
     * base64_to_image 必须拒绝非图片扩展名（如 data:image/php 写入 .php webshell）
     */
    public function testBase64ToImageExtensionAllowlist(): void
    {
        $dir = sys_get_temp_dir() . '/zxf_review_test_' . uniqid();
        mkdir($dir, 0o777, true);

        try {
            // 合法图片扩展名应返回写入路径
            $png = 'data:image/png;base64,' . base64_encode('fake-png-bytes');
            $result = base64_to_image($png, $dir);
            Assert::true(is_string($result) && $result !== '', '合法图片应写入成功');

            // 伪装成 php 的「图片」必须被拒绝
            $php = 'data:image/php;base64,' . base64_encode('<?php echo 1;');
            Assert::false(base64_to_image($php, $dir), 'data:image/php 必须被拒绝');
        } finally {
            array_map('unlink', glob($dir . '/*/*') ?: []);
            @rmdir($dir . '/' . date('Ymd'));
            @rmdir($dir);
        }
    }
}
