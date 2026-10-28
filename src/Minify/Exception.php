<?php

declare(strict_types=1);

namespace zxf\Utils\Minify;

/**
 * Minify 模块所有异常的基类（抽象类）。
 *
 * 压缩模块抛出的任何异常都直接或间接继承自此类，便于调用方统一捕获。
 *
 * @package zxf\Utils\Minify
 * @version 1.0.0
 * @license MIT
 */
abstract class Exception extends \Exception {}
