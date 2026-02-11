<?php

namespace zxf\Utils\QrCode\Encoder;

use Exception;
use zxf\Utils\QrCode\ErrorCorrectionLevel;
use zxf\Utils\QrCode\Encoder\Encoder;
use zxf\Utils\QrCode\Common\Version;

/**
 * 二维码编码器核心类
 * 负责将输入数据转换为二维码矩阵
 * 实现了ISO/IEC 18004标准
 */
class QrEncoder
{
    /** @var Encoder 内部编码器实例 */
    private Encoder $encoder;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->encoder = new Encoder();
    }

    /**
     * 编码数据为二维码矩阵
     *
     * @param string $data 要编码的数据
     * @param ErrorCorrectionLevel $ecLevel 错误纠正级别
     * @param int $version 二维码版本（0表示自动选择）
     * @param string $encoding 字符编码
     * @return array 二维码矩阵
     * @throws Exception
     */
    public function encode(string $data, ErrorCorrectionLevel $ecLevel, int $version = 0, string $encoding = 'UTF-8'): array
    {
        if (empty($data)) {
            throw new Exception('数据不能为空');
        }

        // 将自定义ErrorCorrectionLevel转换为内部的BaconErrorCorrectionLevel
        $internalEcLevel = $this->convertEcLevel($ecLevel);

        // 处理版本
        $forcedVersion = null;
        if ($version > 0) {
            $forcedVersion = Version::getVersionForNumber($version);
        }

        // 使用内部编码器进行编码
        $qrCode = $this->encoder->encode($data, $internalEcLevel, $encoding, $forcedVersion);
        $matrix = $qrCode->getMatrix();

        // 转换为数组格式
        $result = [];
        for ($y = 0; $y < $matrix->getHeight(); $y++) {
            $result[$y] = [];
            for ($x = 0; $x < $matrix->getWidth(); $x++) {
                $result[$y][$x] = $matrix->get($x, $y) === 1;
            }
        }

        return $result;
    }

    /**
     * 获取二维码尺寸（模块数量）
     *
     * @param int $version 二维码版本
     * @return int 模块数量
     * @throws Exception
     */
    public function getDimension(int $version): int
    {
        if ($version < 1 || $version > 40) {
            throw new Exception('无效的二维码版本: ' . $version);
        }
        return 17 + 4 * $version;
    }

    /**
     * 转换自定义错误纠正级别为内部级别
     *
     * @param ErrorCorrectionLevel $customEcLevel 自定义级别
     * @return \zxf\Utils\QrCode\Common\ErrorCorrectionLevel
     */
    private function convertEcLevel(ErrorCorrectionLevel $customEcLevel): \zxf\Utils\QrCode\Common\ErrorCorrectionLevel
    {
        return match($customEcLevel->getName()) {
            'L' => \zxf\Utils\QrCode\Common\ErrorCorrectionLevel::low(),
            'M' => \zxf\Utils\QrCode\Common\ErrorCorrectionLevel::medium(),
            'Q' => \zxf\Utils\QrCode\Common\ErrorCorrectionLevel::quartile(),
            'H' => \zxf\Utils\QrCode\Common\ErrorCorrectionLevel::high(),
        };
    }

    /**
     * 估算所需的版本号
     *
     * @param string $data 数据
     * @param ErrorCorrectionLevel $ecLevel 错误纠正级别
     * @param string $encoding 编码
     * @return int 版本号
     */
    public function estimateVersion(string $data, ErrorCorrectionLevel $ecLevel, string $encoding = 'UTF-8'): int
    {
        try {
            // 使用内部编码器估算版本
            $internalEcLevel = $this->convertEcLevel($ecLevel);
            $qrCode = $this->encoder->encode($data, $internalEcLevel, $encoding, null);
            return $qrCode->getVersion()->getVersionNumber();
        } catch (\Exception $e) {
            return 1; // 默认返回版本1
        }
    }
}
