<?php

declare(strict_types=1);

namespace zxf\Utils\Minify\Exceptions;

/**
 * 输入/输出异常。
 *
 * 当文件无法读取、打开或写入（如权限不足、磁盘空间不够）时抛出。
 *
 * @package zxf\Utils\Minify\Exceptions
 * @version 1.0.0
 * @license MIT
 */
class IOException extends BasicException {}
