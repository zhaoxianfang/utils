<?php

declare(strict_types=1);

namespace zxf\Utils\Minify\Exceptions;

use zxf\Utils\Minify\Exception;

/**
 * Minify 模块的通用基础异常类。
 *
 * 继承自 {@see Exception}，作为所有具体业务异常（IO、文件导入、
 * 正则匹配等）的父类，方便调用方集中捕获。
 *
 * @package zxf\Utils\Minify\Exceptions
 * @version 1.0.0
 * @license MIT
 */
abstract class BasicException extends Exception {}
