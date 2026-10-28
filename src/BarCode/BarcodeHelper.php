<?php

declare(strict_types=1);

namespace zxf\Utils\BarCode;

use zxf\Utils\BarCode\Exceptions\BarcodeException;

/**
 * 条形码辅助类
 * 
 * 提供批量生成、快速生成、条码验证等便捷功能
 */
class BarcodeHelper
{
    /**
     * 快速生成条码
     *
     * 按需把 options 中的 width/height/bgColor/barColor/showText 透传给构建器，
     * 再按 options['format']（png/svg/html，默认 png）把结果保存到指定路径。
     * 任何异常都会被捕获并记录日志，方法只以布尔值表示成败，不向上抛出。
     * 
     * @param string $type 条码类型
     * @param string $data 条码数据
     * @param string $outputPath 输出路径
     * @param array $options 配置选项
     * @return bool 是否成功
     */
    public static function quickGenerate(
        string $type,
        string $data,
        string $outputPath,
        array $options = []
    ): bool {
        try {
            $format = $options['format'] ?? 'png';
            
            $builder = BarcodeBuilder::create()
                ->type($type)
                ->data($data);
            
            foreach (['width','height','bgColor','barColor','showText'] as $key) {
                if (isset($options[$key])) {
                    $builder->$key($options[$key]);
                }
            }
            
            return match ($format) {
                'svg' => $builder->saveSvg($outputPath),
                'html' => $builder->saveHtml($outputPath),
                default => $builder->savePng($outputPath),
            };
        } catch (\Exception $e) {
            error_log('条码生成失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 批量生成条码
     *
     * 先校验数据列表非空，创建输出目录并确认可写，然后按 options['batchSize']
     * （默认 100）分批遍历生成。每项可用 ['data' => ..., 'filename' => ...] 指定
     * 文件名基名（会自动清洗目录分隔符与非法字符），否则用 options['prefix']
     * 加序号命名；单项失败只计入 failed，不影响后续项。
     * 
     * @param array $dataList 数据列表 ['data1', 'data2', ...] 或 [['data' => '...', 'filename' => '...'], ...]
     * @param string $type 条码类型
     * @param string $outputDir 输出目录
     * @param array $options 配置选项
     * @return array 生成结果 ['total' => N, 'success' => N, 'failed' => N, 'files' => [...]]
     * @throws BarcodeException
     */
    public static function batchGenerate(
        array $dataList,
        string $type,
        string $outputDir,
        array $options = []
    ): array {
        if (empty($dataList)) {
            throw new BarcodeException('数据列表不能为空');
        }

        // 创建输出目录
        if (!is_dir($outputDir)) {
            if (!mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
                throw new BarcodeException('无法创建输出目录: ' . $outputDir);
            }
        }

        if (!is_writable($outputDir)) {
            throw new BarcodeException('输出目录不可写: ' . $outputDir);
        }

        $format = $options['format'] ?? 'png';
        $prefix = $options['prefix'] ?? 'barcode_';
        $batchSize = $options['batchSize'] ?? 100;

        $results = [
            'total' => count($dataList),
            'success' => 0,
            'failed' => 0,
            'files' => []
        ];

        // 分批处理
        $batches = array_chunk($dataList, $batchSize);

        foreach ($batches as $batchIndex => $batch) {
            foreach ($batch as $index => $item) {
                $realIndex = $batchIndex * $batchSize + $index;

                try {
                    // 处理数据项格式
                    if (is_array($item)) {
                        $data = $item['data'] ?? '';
                        $filename = $item['filename'] ?? ($prefix . $realIndex);
                        // 文件名语义为“无扩展名的基名”：仅取 basename 并清洗目录分隔符/系统非法字符，
                        // 防止 ../ 目录穿越或 Windows 保留字符注入到输出路径
                        if (is_string($filename)) {
                            $filename = preg_replace('#[/\\\\:*?"<>|]#', '_', basename(trim($filename)));
                        }
                        if (!is_string($filename) || $filename === '' || $filename === '.' || $filename === '..') {
                            $filename = $prefix . $realIndex;
                        }
                    } else {
                        $data = (string)$item;
                        $filename = $prefix . $realIndex;
                    }

                    if (empty($data)) {
                        throw new BarcodeException('数据不能为空');
                    }

                    $filepath = $outputDir . '/' . $filename . '.' . $format;
                    
                    $builder = BarcodeBuilder::create()
                        ->type($type)
                        ->data($data);
                    
                    // 应用选项
                    foreach ($options as $key => $value) {
                        if (in_array($key, ['format', 'prefix', 'batchSize'], true)) {
                            continue;
                        }
                        if (method_exists($builder, $key)) {
                            $builder = $builder->$key($value);
                        }
                    }

                    match ($format) {
                        'svg' => $builder->saveSvg($filepath),
                        'html' => $builder->saveHtml($filepath),
                        default => $builder->savePng($filepath),
                    };

                    $results['success']++;
                    $results['files'][] = $filepath;
                    
                    unset($builder);
                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['files'][] = "Index {$realIndex}: " . $e->getMessage();
                }
            }
        }

        return $results;
    }

    /**
     * 验证条码数据格式
     * 
     * @param string $type 条码类型
     * @param string $data 要验证的数据
     * @return array 验证结果 ['valid' => bool, 'errors' => [], 'warnings' => []]
     */
    public static function validateData(string $type, string $data): array
    {
        $result = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'info' => []
        ];

        try {
            $generator = BarcodeFactory::create($type);
            
            if (!$generator->validate($data)) {
                $result['valid'] = false;
                $result['errors'][] = '数据格式不符合 ' . $type . ' 条码规范';
            }

            // 获取条码信息
            $result['info']['type'] = $generator->getType();
            
            // 计算校验位
            try {
                $checksum = $generator->calculateChecksum($data);
                if ($checksum !== '') {
                    $result['info']['checksum'] = $checksum;
                    $result['info']['fullData'] = $data . $checksum;
                }
            } catch (\Exception $e) {
                // 某些条码类型可能不需要校验位
            }

            // 检查数据长度建议
            $length = strlen($data);
            $result['info']['length'] = $length;

            // 根据类型给出建议
            $lengthRecommendations = [
                'ean13' => ['min' => 12, 'max' => 13, 'optimal' => 13],
                'ean8' => ['min' => 7, 'max' => 8, 'optimal' => 8],
                'upca' => ['min' => 11, 'max' => 12, 'optimal' => 12],
                'itf14' => ['min' => 13, 'max' => 14, 'optimal' => 14],
                'issn' => ['min' => 7, 'max' => 8, 'optimal' => 8],
                'isbn' => ['min' => 10, 'max' => 17, 'optimal' => 13],
                'codabar' => ['min' => 1, 'max' => 48, 'optimal' => null],
            ];

            $normalizedType = strtolower($type);
            if (isset($lengthRecommendations[$normalizedType])) {
                $rec = $lengthRecommendations[$normalizedType];
                if ($length < $rec['min']) {
                    $result['warnings'][] = "数据长度({$length})小于推荐最小值({$rec['min']})";
                } elseif ($length > $rec['max']) {
                    $result['warnings'][] = "数据长度({$length})超过推荐最大值({$rec['max']})";
                }
            }

        } catch (\Exception $e) {
            $result['valid'] = false;
            $result['errors'][] = '创建条码生成器失败: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * 获取支持的条码类型列表
     * 
     * @return array 条码类型列表
     */
    public static function getSupportedTypes(): array
    {
        return BarcodeFactory::getSupportedTypes();
    }

    /**
     * 检查条码类型是否支持
     * 
     * @param string $type 条码类型
     * @return bool 是否支持
     */
    public static function isSupported(string $type): bool
    {
        return BarcodeFactory::isSupported($type);
    }

    /**
     * 获取条码类型信息
     * 
     * @param string $type 条码类型
     * @return array 类型信息
     */
    public static function getTypeInfo(string $type): array
    {
        $typeInfo = [
            'ean13' => [
                'name' => 'EAN-13',
                'description' => '欧洲商品编号，13位纯数字，用于零售商品',
                'charset' => '0-9',
                'length' => '12-13位',
                'uses' => '零售商品、超市',
            ],
            'ean8' => [
                'name' => 'EAN-8',
                'description' => '短版EAN，8位纯数字，用于小包装商品',
                'charset' => '0-9',
                'length' => '7-8位',
                'uses' => '小包装商品',
            ],
            'upca' => [
                'name' => 'UPC-A',
                'description' => '北美商品码，12位纯数字',
                'charset' => '0-9',
                'length' => '11-12位',
                'uses' => '北美零售',
            ],
            'code128' => [
                'name' => 'Code 128',
                'description' => '高密度字母数字码，支持全ASCII字符',
                'charset' => '全ASCII',
                'length' => '可变',
                'uses' => '物流、仓储',
            ],
            'code39' => [
                'name' => 'Code 39',
                'description' => '工业标准码，支持数字、大写字母和部分符号',
                'charset' => '0-9, A-Z, - . $ / + % 空格',
                'length' => '可变',
                'uses' => '工业、医疗',
            ],
            'itf14' => [
                'name' => 'ITF-14',
                'description' => '物流包装码，14位纯数字',
                'charset' => '0-9',
                'length' => '13-14位',
                'uses' => '物流包装',
            ],
            'issn' => [
                'name' => 'ISSN',
                'description' => '国际标准期刊号，基于EAN-8',
                'charset' => '0-9',
                'length' => '7-8位',
                'uses' => '期刊杂志',
            ],
            'isbn' => [
                'name' => 'ISBN',
                'description' => '国际标准书号，基于EAN-13，支持ISBN-10自动转换为ISBN-13',
                'charset' => '0-9, -',
                'length' => '10-13位',
                'uses' => '图书出版',
            ],
            'codabar' => [
                'name' => 'Codabar',
                'description' => 'NW-7 离散型自校验条码，用于图书馆、血库',
                'charset' => '0-9, - $ : / . +',
                'length' => '可变',
                'uses' => '图书馆、医疗',
            ],
        ];

        $normalizedType = BarcodeFactory::canonicalType($type);
        return $typeInfo[$normalizedType] ?? [
            'name' => $type,
            'description' => '未知条码类型',
            'charset' => '未知',
            'length' => '未知',
            'uses' => '未知',
        ];
    }

    /**
     * 计算校验位
     *
     * 通过工厂按类型创建临时生成器并委托其计算，数据格式不合法时统一
     * 包装为 BarcodeException 抛出。
     * 
     * @param string $type 条码类型
     * @param string $data 数据
     * @return string 校验位
     * @throws BarcodeException
     */
    public static function calculateChecksum(string $type, string $data): string
    {
        try {
            $generator = BarcodeFactory::create($type);
            return $generator->calculateChecksum($data);
        } catch (\Exception $e) {
            throw new BarcodeException('计算校验位失败: ' . $e->getMessage());
        }
    }

    /**
     * 生成Base64编码的条码
     * 
     * @param string $type 条码类型
     * @param string $data 数据
     * @param array $options 配置选项
     * @return string Base64编码的数据URI
     */
    public static function toBase64(string $type, string $data, array $options = []): string
    {
        $format = $options['format'] ?? 'png';
        
        $builder = BarcodeBuilder::create()
            ->type($type)
            ->data($data);
        
        foreach ($options as $key => $value) {
            if ($key === 'format') continue;
            if (method_exists($builder, $key)) {
                $builder = $builder->$key($value);
            }
        }

        [$content, $mimeType] = match ($format) {
            'svg' => [$builder->toSvg(), 'image/svg+xml'],
            'html' => [$builder->toHtml(), 'text/html'],
            default => [$builder->toPng(), 'image/png'],
        };

        return 'data:' . $mimeType . ';base64,' . base64_encode($content);
    }

    /**
     * 检查环境支持
     *
     * 逐项检查 GD 扩展与 imagepng、memory_limit 配置、临时目录可写性。
     * 仅 GD 缺失会把 passed 置为 false，临时目录不可写只标记为 warning。
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
