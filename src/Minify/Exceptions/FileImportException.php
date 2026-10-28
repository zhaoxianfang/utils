<?php

declare(strict_types=1);

namespace zxf\Utils\Minify\Exceptions;

/**
 * 文件导入异常。
 *
 * 当合并 CSS 中的 @import 语句、解析导入文件失败或检测到循环引用时抛出。
 *
 * @package zxf\Utils\Minify\Exceptions
 * @version 1.0.0
 * @license MIT
 */
class FileImportException extends BasicException {}
