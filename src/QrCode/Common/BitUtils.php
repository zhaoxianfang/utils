<?php
declare(strict_types = 1);

namespace zxf\Utils\QrCode\Common;

/**
 * General bit utilities.
 *
 * All utility methods are based on 32-bit integers and also work on 64-bit
 * systems.
 */
final class BitUtils
{
    /**
     * 私有构造函数，禁止实例化（仅提供静态位运算工具方法）
     */
    private function __construct()
    {
    }

    /**
     * 执行无符号右移，等价于其他语言中的 “>>>” 运算符。
     *
     * 正数时直接右移；负数时先用掩码 0x7fffffff 抹去符号位再右移，
     * 并通过 0x40000000 >> ($b - 1) 补回被移走的高位，使结果按 32 位
     * 无符号整数解释，因此返回值恒为非负。
     *
     * @param int $a 待移位的 32 位整数，允许为负数
     * @param int $b 右移的位数，需大于 0
     * @return int 无符号右移后的结果，始终为非负整数
     */
    public static function unsignedRightShift(int $a, int $b) : int
    {
        // 位移 0 位应原样返回；负数的无符号解释为 32 位无符号整数。
        // 若不单独处理 $b = 0，会走到 (0x40000000 >> -1) 触发 ArithmeticError。
        if ($b <= 0) {
            return $a >= 0 ? $a : ($a & 0xFFFFFFFF);
        }

        return (
            $a >= 0
            ? $a >> $b
            : (($a & 0x7fffffff) >> $b) | (0x40000000 >> ($b - 1))
        );
    }

    /**
     * 统计整数二进制表示中末尾连续 0 的个数。
     *
     * 先把数值转成 32 位二进制字符串并左补零，再从右往左定位最后一个
     * '1'；若字符串中不含 '1'（即 $i 为 0），则按 32 位全零处理返回 32。
     *
     * @param int $i 待统计的整数
     * @return int 末尾连续 0 的个数，取值区间为 0~32
     */
    public static function numberOfTrailingZeros(int $i) : int
    {
        $lastPos = strrpos(str_pad(decbin($i), 32, '0', STR_PAD_LEFT), '1');
        return $lastPos === false ? 32 : 31 - $lastPos;
    }
}
