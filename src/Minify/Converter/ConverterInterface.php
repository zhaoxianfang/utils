<?php

declare(strict_types=1);

namespace zxf\Utils\Minify\Converter;

/**
 * 文件路径转换器接口。
 *
 * 当压缩后的资源（如 CSS）被保存到与源文件不同的目录时，
 * 需要将其中引用的相对路径（url()、@import）重新换算为相对于新位置的路径。
 * 实现该接口即可提供不同的路径换算策略。
 *
 * @package zxf\Utils\Minify\Converter
 * @version 1.0.0
 * @license MIT
 *
 * @example
 * ```php
 * use zxf\Utils\Minify\Converter\Converter;
 *
 * // 将相对于 /css/imports/icons.css 的 ../../images/icon.jpg
 * // 换算为相对于 /css/minified.css 的 ../images/icon.jpg
 * $converter = new Converter('/css/imports/icons.css', '/css/minified.css');
 * $newPath = $converter->convert('../../images/icon.jpg');
 * ```
 */
interface ConverterInterface
{
    /**
     * 将相对路径换算为相对于新基准目录的路径。
     *
     * @param string $path 需要被换算的相对路径
     *
     * @return string 换算后的新相对路径
     */
    public function convert($path);
}
