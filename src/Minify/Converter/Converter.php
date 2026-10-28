<?php

declare(strict_types=1);

namespace zxf\Utils\Minify\Converter;

/**
 * 将一个文件中的相对路径换算为相对于另一个文件的相对路径。
 *
 * 例：相对于 /css/imports/icons.css 的 ../../images/icon.jpg，
 * 在文件被保存到 /css/minified.css 后，应换算为 ../images/icon.jpg。
 *
 * @package zxf\Utils\Minify\Converter
 * @version 1.0.0
 * @license MIT
 *
 * @example
 * ```php
 * use zxf\Utils\Minify\Converter\Converter;
 *
 * $converter = new Converter('/css/imports/icons.css', '/css/minified.css');
 * // 结果：../images/icon.jpg
 * $newPath = $converter->convert('../../images/icon.jpg');
 * ```
 */
class Converter implements ConverterInterface
{
    /**
     * @var string 源文件所在的基准目录（已规范化）
     */
    protected $from;

    /**
     * @var string 目标文件所在的基准目录（已规范化）
     */
    protected $to;

    /**
     * 构造转换器。
     *
     * 计算源目录与目标目录的基准路径，供后续 convert() 换算相对路径使用。
     * 当两个路径毫无公共前缀时，会借助 $root 将相对路径补全为绝对路径后再规范化，
     * 以避免“一个是绝对路径、一个是相对路径”导致的换算错误。
     *
     * @param string $from 原始的基准路径（应为目录，而非文件！）
     * @param string $to   新的基准路径（应为目录，而非文件！）
     * @param string $root 根目录，默认为 getcwd() 的返回值
     */
    public function __construct($from, $to, $root = '')
    {
        $shared = $this->shared($from, $to);
        if ($shared === '') {
            // 当两个路径没有任何公共部分时，很可能一个是绝对路径、另一个是相对路径
            $root = $root ?: getcwd();
            $from = str_starts_with($from, $root) ? $from : preg_replace('/\/+/', '/', $root.'/'.$from);
            $to = str_starts_with($to, $root) ? $to : preg_replace('/\/+/', '/', $root.'/'.$to);

            // 通过 realpath 尝试解析为真实路径；若文件不存在则假定原始路径可用
            $from = @realpath($from) ?: $from;
            $to = @realpath($to) ?: $to;
        }

        $from = $this->dirname($from);
        $to = $this->dirname($to);

        $from = $this->normalize($from);
        $to = $this->normalize($to);

        $this->from = $from;
        $this->to = $to;
    }

    /**
     * 规范化路径。
     *
     * 统一目录分隔符、去除多余的 ./ 与 ../ 片段，得到稳定可比较的路径字符串。
     *
     * @param string $path 待规范化的路径
     *
     * @return string 规范化后的路径
     */
    protected function normalize($path)
    {
        // 兼容不同操作系统的目录分隔符，统一转换为正斜杠
        $path = rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $path), '/');

        // 去除开头的当前目录前缀 ./
        if (str_starts_with($path, './')) {
            $path = substr($path, 2);
        }

        // 去除路径中间的当前目录引用 /./，避免干扰后续计算
        $path = str_replace('/./', '/', $path);

        /*
         * 逐步消除“非 .. 的目录名/..”组合，即向上回退一层：
         * 例：
         *     /home/forkcms/frontend/cache/compiled_templates/../../core/layout/css/../images/img.gif
         * 变为
         *     /home/forkcms/frontend/core/layout/images/img.gif
         * （负向回顾 (?<!\\.\\.) 用于避免误消以 .. 结尾的“目录名”）
         */
        do {
            $path = preg_replace('/[^\/]+(?<!\.\.)\/\.\.\//', '', $path, -1, $count);
        } while ($count);

        return $path;
    }

    /**
     * 计算两个路径的公共前缀。
     *
     * 例：
     *     /home/forkcms/frontend/core/layout/images/img.gif
     * 与
     *     /home/forkcms/frontend/cache/minified_css
     * 的公共前缀为
     *     /home/forkcms/frontend
     *
     * @param string $path1 第一个路径
     * @param string $path2 第二个路径
     *
     * @return string 两个路径的公共祖先路径
     */
    protected function shared($path1, $path2)
    {
        // $path 理论上可能为空（例如未传入路径），此时不应展开为 array('')，
        // 否则会错误地把空串当成根目录 / 来比较
        $path1 = $path1 ? explode('/', $path1) : [];
        $path2 = $path2 ? explode('/', $path2) : [];

        $shared = [];

        // 逐段比较路径，剥离完全相同的祖先目录
        foreach ($path1 as $i => $chunk) {
            if (isset($path2[$i]) && $chunk == $path2[$i]) {
                $shared[] = $chunk;
            } else {
                break;
            }
        }

        return implode('/', $shared);
    }

    /**
     * 将一个相对路径从“源文件”换算为“目标文件”的相对路径。
     *
     * 例：相对于 /home/forkcms/frontend/core/layout/css 的 ../images/img.gif
     * 应换算为相对于 /home/forkcms/frontend/cache/minified_css 的
     * ../../core/layout/images/img.gif。
     *
     * @param string $path 需要被换算的相对路径
     *
     * @return string 换算后的新相对路径
     */
    public function convert($path)
    {
        // 源与目标相同，无需换算，直接返回
        if ($this->from === $this->to) {
            return $path;
        }

        $path = $this->normalize($path);
        // 非相对路径（绝对路径）无法换算，原样返回
        if (str_starts_with($path, '/')) {
            return $path;
        }

        // 规范化“源目录 + 相对路径”拼出的绝对路径
        $path = $this->normalize($this->from.'/'.$path);

        // 先扣除两者公共的祖先路径
        $shared = $this->shared($path, $this->to);
        $path = mb_substr($path, mb_strlen($shared));
        $to = mb_substr($this->to, mb_strlen($shared));

        // 目标比公共前缀多出的每一层目录，都需要在结果前补一个 ../
        $to = str_repeat('../', count(array_filter(explode('/', $to))));

        return $to.ltrim($path, '/');
    }

    /**
     * 尝试获取某个路径对应的目录名。
     *
     * 由于输入既可能是文件也可能是目录，需要根据实际情况判断；
     * 在无法直接判断时，依据命名特征（是否以 / 结尾、是否含扩展点）做合理的启发式推断。
     *
     * @param string $path 待判断的路径
     *
     * @return string 该路径对应的目录名
     */
    protected function dirname($path)
    {
        if (@is_file($path)) {
            return dirname($path);
        }

        if (@is_dir($path)) {
            return rtrim($path, '/');
        }

        // 无法确定是文件还是目录时，只能做启发式推断

        // 以 / 结尾，视为目录
        if (mb_substr($path, -1) === '/') {
            return rtrim($path, '/');
        }

        // 名称中带点（如含扩展名），大概率是个文件
        if (preg_match('/.*\..*$/', basename($path)) !== 0) {
            return dirname($path);
        }

        // 实在无法判断，只能原样返回
        return $path;
    }
}
