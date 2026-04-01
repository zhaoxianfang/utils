<?php

namespace zxf\Utils\QrCode;

use Exception;

/**
 * 二维码助手类
 * 提供便捷的静态方法和工具函数
 */
class QrCodeHelper
{
    /**
     * 估算二维码版本（无需生成二维码）
     *
     * @param string $data 要编码的数据
     * @param string $ecLevel 错误纠正级别（L, M, Q, H）
     * @param string $encoding 编码格式
     * @return int 版本号（1-40）
     */
    public static function estimateVersion(string $data, string $ecLevel = 'M', string $encoding = 'UTF-8'): int
    {
        try {
            // 创建临时二维码估算版本
            $qrCode = QrCode::make($data)
                ->errorCorrectionLevel($ecLevel)
                ->encoding($encoding);

            // 临时渲染以获取版本信息
            $info = $qrCode->getInfo();

            // 根据数据长度估算版本
            $dataLength = strlen($data);

            // 简化的版本估算公式
            // V1-10: 小型数据
            // V11-20: 中型数据
            // V21-30: 大型数据
            // V31-40: 超大数据

            if ($dataLength <= 50) {
                return 1;
            } elseif ($dataLength <= 100) {
                return 2;
            } elseif ($dataLength <= 200) {
                return 4;
            } elseif ($dataLength <= 500) {
                return 10;
            } elseif ($dataLength <= 1000) {
                return 15;
            } elseif ($dataLength <= 2000) {
                return 20;
            } elseif ($dataLength <= 3000) {
                return 25;
            } else {
                return min(40, 30 + (int)ceil(($dataLength - 3000) / 500));
            }
        } catch (Exception $e) {
            return 1; // 默认返回版本1
        }
    }

    /**
     * 获取二维码容量信息
     *
     * @param int $version 版本号（1-40）
     * @param string $ecLevel 错误纠正级别
     * @return array 容量信息
     */
    public static function getCapacity(int $version, string $ecLevel = 'M'): array
    {
        if ($version < 1 || $version > 40) {
            throw new Exception('版本号必须在1-40之间');
        }

        // 基本容量（数字模式，最大容量）
        $capacities = [
            'L' => [
                1 => 41, 2 => 77, 3 => 127, 4 => 187, 5 => 255,
                6 => 322, 7 => 370, 8 => 461, 9 => 552, 10 => 652,
                15 => 1021, 20 => 1499, 25 => 2022, 30 => 2620, 35 => 3311, 40 => 4296
            ],
            'M' => [
                1 => 34, 2 => 63, 3 => 101, 4 => 149, 5 => 202,
                6 => 255, 7 => 293, 8 => 365, 9 => 432, 10 => 513,
                15 => 809, 20 => 1199, 25 => 1617, 30 => 2099, 35 => 2652, 40 => 3432
            ],
            'Q' => [
                1 => 27, 2 => 48, 3 => 77, 4 => 111, 5 => 150,
                6 => 189, 7 => 221, 8 => 272, 9 => 321, 10 => 381,
                15 => 603, 20 => 890, 25 => 1200, 30 => 1560, 35 => 1974, 40 => 2554
            ],
            'H' => [
                1 => 17, 2 => 34, 3 => 58, 4 => 82, 5 => 106,
                6 => 139, 7 => 154, 8 => 202, 9 => 224, 10 => 279,
                15 => 445, 20 => 662, 25 => 894, 30 => 1164, 35 => 1477, 40 => 1914
            ]
        ];

        $ecLevel = strtoupper($ecLevel);
        if (!isset($capacities[$ecLevel])) {
            throw new Exception('无效的错误纠正级别: ' . $ecLevel);
        }

        // 线性插值计算中间版本
        $baseVersion = $capacities[$ecLevel];

        if (isset($baseVersion[$version])) {
            $numericCapacity = $baseVersion[$version];
        } else {
            // 查找最近的版本进行插值
            $versions = array_keys($baseVersion);
            sort($versions);

            $lower = 1;
            $upper = 40;

            foreach ($versions as $v) {
                if ($v <= $version) {
                    $lower = $v;
                }
                if ($v >= $version) {
                    $upper = $v;
                    break;
                }
            }

            if ($lower === $upper) {
                $numericCapacity = $baseVersion[$lower];
            } else {
                $lowerCap = $baseVersion[$lower];
                $upperCap = $baseVersion[$upper];
                $numericCapacity = (int)round(
                    $lowerCap + ($upperCap - $lowerCap) * ($version - $lower) / ($upper - $lower)
                );
            }
        }

        // 不同模式的容量换算
        return [
            'version' => $version,
            'ecLevel' => $ecLevel,
            'numeric' => $numericCapacity,
            'alphanumeric' => (int)($numericCapacity * 0.75),
            'byte' => (int)($numericCapacity * 0.55),
            'kanji' => (int)($numericCapacity * 0.4),
            'dimensions' => 17 + 4 * $version,
        ];
    }

    /**
     * 快速生成二维码（简化API）
     *
     * @param string $data 二维码内容
     * @param string $outputPath 输出路径
     * @param array $options 配置选项
     * @return bool 是否成功
     */
    public static function quickGenerate(string $data, string $outputPath, array $options = []): bool
    {
        try {
            $qrCode = QrCode::make($data);

            // 应用选项
            foreach ($options as $key => $value) {
                if (method_exists($qrCode, $key)) {
                    $qrCode = $qrCode->$key($value);
                }
            }

            $qrCode->save($outputPath);
            return true;
        } catch (Exception $e) {
            error_log('二维码生成失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 验证二维码内容是否可编码
     *
     * @param string $data 要编码的内容
     * @param string $encoding 编码格式
     * @return array 验证结果
     */
    public static function validateData(string $data, string $encoding = 'UTF-8'): array
    {
        $result = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'info' => []
        ];

        // 检查空内容
        if (empty($data)) {
            $result['valid'] = false;
            $result['errors'][] = '数据内容不能为空';
            return $result;
        }

        // 检查内容长度
        $length = strlen($data);
        $result['info']['length'] = $length;

        if ($length > 2953) {
            $result['warnings'][] = '数据长度较长，可能需要使用较高版本（35-40）的二维码';
        } elseif ($length > 2000) {
            $result['warnings'][] = '数据长度较长，建议使用较高版本（25-34）的二维码';
        }

        // 尝试编码测试
        try {
            @iconv('utf-8', $encoding, $data);
            $result['info']['encoding'] = $encoding;
        } catch (\Exception $e) {
            $result['valid'] = false;
            $result['errors'][] = '编码格式不支持或转换失败: ' . $encoding;
        }

        // 检查是否包含特殊字符
        if (preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/', $data)) {
            $result['warnings'][] = '数据包含控制字符，可能影响二维码可读性';
        }

        return $result;
    }

    /**
     * 计算推荐的二维码尺寸
     *
     * @param int $version 二维码版本
     * @param int $moduleSize 模块大小（像素）
     * @param int $margin 边距（模块数）
     * @return int 推荐尺寸（像素）
     */
    public static function calculateRecommendedSize(int $version, int $moduleSize = 5, int $margin = 4): int
    {
        $dimension = 17 + 4 * $version;
        return ($dimension + $margin * 2) * $moduleSize;
    }

    /**
     * 生成批量二维码配置
     *
     * @param array $dataList 数据列表
     * @param string $outputDir 输出目录
     * @param array $globalOptions 全局选项
     * @return array 配置信息
     */
    public static function prepareBatch(array $dataList, string $outputDir, array $globalOptions = []): array
    {
        return [
            'dataList' => $dataList,
            'outputDir' => $outputDir,
            'total' => count($dataList),
            'options' => $globalOptions,
            'estimatedFiles' => count($dataList),
            'estimatedSize' => count($dataList) * 10, // 假设每个文件10KB
        ];
    }

    /**
     * 检查环境是否支持生成二维码
     *
     * @return array 环境检查结果
     */
    public static function checkEnvironment(): array
    {
        $result = [
            'passed' => true,
            'checks' => []
        ];

        // 检查GD库
        if (extension_loaded('gd') && function_exists('imagepng')) {
            $result['checks']['gd'] = ['status' => 'ok', 'message' => 'GD库已加载'];
        } else {
            $result['passed'] = false;
            $result['checks']['gd'] = ['status' => 'error', 'message' => 'GD库未安装或不支持PNG'];
        }

        // 检查内存限制
        $memoryLimit = ini_get('memory_limit');
        $result['checks']['memory'] = [
            'status' => 'ok',
            'limit' => $memoryLimit,
            'message' => '内存限制: ' . $memoryLimit
        ];

        // 检查文件权限
        $tempDir = sys_get_temp_dir();
        $isWritable = is_writable($tempDir);
        $result['checks']['tempDir'] = [
            'status' => $isWritable ? 'ok' : 'warning',
            'path' => $tempDir,
            'writable' => $isWritable
        ];

        return $result;
    }
}
