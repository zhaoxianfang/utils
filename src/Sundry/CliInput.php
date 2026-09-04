<?php

declare(strict_types=1);

namespace zxf\Utils\Sundry;

use InvalidArgumentException;

/**
 * 命令行输入内容
 * eg：$input = new CliInput();
 *     $input = new CliInput();
 *     $name = $input->input('请输入您的名字：');
 *     echo '您的名字为：'.$name ."\n";
 *
 *     $choice = $input->choice('请选择一项：',['a'=>'选项A','b'=>'选项B','c'=>'选项C']);
 *     echo '您选择了：'.$choice ."\n";
 */
class CliInput
{
    /**
     * @param  string  $tips  提示信息
     *                        eg: $name = $input->input('请输入您的名字：');
     *                        echo '您的名字为：'.$name .'\n';
     * @return string|null 读取到的输入；输入流关闭（EOF）时返回 null
     */
    public function input(string $tips = '请输入:'): ?string
    {
        echo "{$tips}";

        // fgets() 在 EOF / 管道输入耗尽时返回 false，
        // 直接交给 trim() 在 strict_types 下会触发 TypeError
        $line = fgets(STDIN);
        if ($line === false) {
            return null;
        }

        return trim($line);
    }

    /**
     * @param  string  $tips  提示信息
     * @param  array  $options  候选项
     *                          eg: $choice = $input->choice('请选择一项：',['a'=>'选项A','b'=>'选项B','c'=>'选项C']);
     * @return string|null 选中的键；输入流关闭（EOF）时返回 null
     *
     * @throws InvalidArgumentException 候选项为空时抛出
     */
    public function choice(string $tips = '请从下列选项中选择其中一项', array $options = []): ?string
    {
        if ($options === []) {
            // 空候选项会让下面的循环永远无法命中，直接失败好过死循环
            throw new InvalidArgumentException('候选项不能为空');
        }

        echo "{$tips}\n";
        // 显示选项列表
        foreach ($options as $key => $value) {
            echo "$key - $value\n";
        }
        // 获取用户的选项
        while (true) {
            echo '请选择: ';
            $line = fgets(STDIN);
            // EOF / 输入流关闭时不能继续死等
            if ($line === false) {
                return null;
            }

            $choice = trim($line);
            if (array_key_exists($choice, $options)) {
                return $choice;
            }
        }
    }
}
