<?php

declare(strict_types=1);

namespace zxf\Utils\Minify\Converter;

/**
 * 空路径转换器。
 *
 * 实现 {@see ConverterInterface}，但不做任何路径换算，原样返回输入路径。
 * 适用于无需迁移相对路径（例如仅压缩不移动文件）的场景。
 *
 * @package zxf\Utils\Minify\Converter
 * @version 1.0.0
 * @license MIT
 */
class NoConverter implements ConverterInterface
{
    /**
     * 原样返回传入的路径，不进行任何换算。
     *
     * @param string $path 待处理的路径
     *
     * @return string 与输入完全相同的路径
     */
    public function convert($path)
    {
        return $path;
    }
}
