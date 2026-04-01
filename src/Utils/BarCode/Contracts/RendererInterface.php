<?php

declare(strict_types=1);

namespace zxf\Utils\BarCode\Contracts;

/**
 * 渲染器接口
 * 
 * 定义所有条形码渲染器必须实现的方法
 * 支持将条形码数据渲染为不同格式（PNG、SVG等）
 */
interface RendererInterface
{
    /**
     * 渲染条形码
     * 
     * @param array  $barcodeData 条形码条空模式数组
     * @param string $data         原始数据
     * @param array  $config       渲染配置选项
     * @return mixed 根据具体渲染器返回相应格式的数据
     */
    public function render(array $barcodeData, string $data, array $config = []): mixed;

    /**
     * 将条形码保存到文件
     * 
     * @param array  $barcodeData 条形码条空模式数组
     * @param string $data         原始数据
     * @param string $filename     目标文件路径
     * @param array  $config       渲染配置选项
     * @return bool 保存成功返回true，失败返回false
     */
    public function saveToFile(array $barcodeData, string $data, string $filename, array $config = []): bool;

    /**
     * 获取渲染器类型
     * 
     * @return string 返回渲染器类型名称（如'png'、'svg'等）
     */
    public function getType(): string;
}
