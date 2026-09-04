<?php

declare(strict_types=1);

namespace zxf\Utils\Tests\Unit;

use RuntimeException;
use zxf\Utils\Files\File;
use zxf\Utils\Files\Path;
use zxf\Utils\Tests\Assert;
use zxf\Utils\Tests\TestCase;

/**
 * Files 组件测试（File / Path）
 *
 * 覆盖曾出现的严重缺陷：File::normalizePath() 会丢弃绝对路径的前导分隔符，
 * 导致整个 File 类无法处理任何绝对路径。
 */
class FileTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/zxf_files_test_' . bin2hex(random_bytes(4));
        if (!is_dir($this->tmpDir)) {
            mkdir($this->tmpDir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmpDir)) {
            File::safeDeleteDirectory($this->tmpDir);
        }
    }

    public function testReadWriteAbsolutePath(): void
    {
        $file = $this->tmpDir . '/abs.txt';

        Assert::true(File::createFile($file, 'hello'));
        Assert::same('hello', File::readFile($file));
    }

    public function testReadFileOnMissingPathThrows(): void
    {
        Assert::throws(RuntimeException::class, static fn () => File::readFile('/nonexistent_zxf_dir/xx.txt'));
    }

    public function testNormalizePreservesLeadingSlash(): void
    {
        // 绝对路径被正确保留（曾被截断为相对路径）
        Assert::true(File::createFile($this->tmpDir . '/abs2.txt', 'x'));
        Assert::same('x', File::readFile($this->tmpDir . '/abs2.txt'));
        // 目录不存在时报文件不存在异常（而非 TypeError/警告）
        Assert::throws(RuntimeException::class, fn () => File::readFile($this->tmpDir . '/no_dir/xx.txt'));
    }

    public function testCreateFileAndReadLineByLine(): void
    {
        $file = $this->tmpDir . '/lines.txt';
        File::createFile($file, "a\nb\nc\n");

        $lines = [];
        foreach (File::readFileLineByLine($file) as $line) {
            $lines[] = $line;
        }
        Assert::same(['a', 'b', 'c'], $lines);
    }

    public function testJsonRoundTrip(): void
    {
        $file = $this->tmpDir . '/data.json';

        Assert::true(File::writeJsonFile($file, ['a' => 1, 'b' => [1, 2]]));
        Assert::same(['a' => 1, 'b' => [1, 2]], File::readJsonFile($file));
        // 文件缺失时应抛清晰异常而不是解析空内容
        Assert::throws(RuntimeException::class, fn () => File::readJsonFile($this->tmpDir . '/none.json'));
    }

    public function testCopyAndMove(): void
    {
        $src = $this->tmpDir . '/src.txt';
        $dst = $this->tmpDir . '/dst.txt';
        File::createFile($src, 'x');

        Assert::true(File::copyFile($src, $dst));
        Assert::same('x', File::readFile($dst));
        Assert::true(File::move($dst, $this->tmpDir . '/moved.txt'));
        Assert::false(file_exists($dst));
        Assert::true(file_exists($this->tmpDir . '/moved.txt'));
    }

    public function testCopyDirectory(): void
    {
        $src = $this->tmpDir . '/srcdir';
        $dst = $this->tmpDir . '/dstdir';
        mkdir($src . '/sub', 0755, true);
        File::createFile($src . '/a.txt', '1');
        File::createFile($src . '/sub/b.txt', '2');

        Assert::true(File::copyDirectory($src, $dst));
        Assert::same('1', File::readFile($dst . '/a.txt'));
        Assert::same('2', File::readFile($dst . '/sub/b.txt'));
    }

    public function testFindFiles(): void
    {
        mkdir($this->tmpDir . '/find/sub', 0755, true);
        File::createFile($this->tmpDir . '/find/a.log', 'x');
        File::createFile($this->tmpDir . '/find/b.txt', 'x');
        File::createFile($this->tmpDir . '/find/sub/c.log', 'x');

        Assert::count(3, File::findFiles($this->tmpDir . '/find'));
        Assert::count(2, File::findFilesByExtension($this->tmpDir . '/find', 'log'));
        Assert::count(2, File::findFilesByPattern($this->tmpDir . '/find', '*.log'));
    }

    public function testSearchAndReplace(): void
    {
        $file = $this->tmpDir . '/s.txt';
        File::createFile($file, 'foo bar foo');

        Assert::true(File::searchAndReplace($file, 'foo', 'baz'));
        Assert::same('baz bar baz', File::readFile($file));
    }

    public function testUploadFileValidation(): void
    {
        // 无文件上传时应抛出清晰异常
        Assert::throws(
            RuntimeException::class,
            fn () => File::uploadFile(
                ['error' => UPLOAD_ERR_NO_FILE, 'tmp_name' => '', 'name' => '', 'size' => 0, 'type' => ''],
                $this->tmpDir
            )
        );
    }

    /* ---------------- Path ---------------- */

    public function testPathJoin(): void
    {
        Assert::same('a/b/c.txt', Path::join('a', 'b/', '/c.txt'));
        // join 只做拼接不折叠 ..，折叠交给 normalize
        Assert::same('a/b/../c.txt', Path::join('a', 'b/..', 'c.txt'));
        Assert::same('a/c.txt', Path::normalize(Path::join('a', 'b/..', 'c.txt')));
    }

    public function testPathNormalize(): void
    {
        Assert::same('/a/c', Path::normalize('/a//b/../c'));
        Assert::same('a/c', Path::normalize('a/./b/../c'));
    }

    public function testPathRelative(): void
    {
        // 从 /a/b/c 出发到达 /a/b 本身 → 上一级目录
        Assert::same('..', Path::relative('/a/b/c', '/a/b'));
        // 从 /a/b/c 出发到达 /a/b/x → 先上一级再进入
        Assert::same('../x', Path::relative('/a/b/c', '/a/b/x'));
        Assert::same('d', Path::relative('/a/b/c', '/a/b/c/d'));
    }

    public function testPathAbsoluteDetection(): void
    {
        Assert::true(Path::isAbsolute('/tmp/x'));
        Assert::false(Path::isAbsolute('relative/x'));
    }
}
