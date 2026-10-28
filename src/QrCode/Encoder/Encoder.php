<?php
declare(strict_types = 1);

namespace zxf\Utils\QrCode\Encoder;

use zxf\Utils\QrCode\Common\BitArray;
use zxf\Utils\QrCode\Common\CharacterSetEci;
use zxf\Utils\QrCode\Common\ErrorCorrectionLevel;
use zxf\Utils\QrCode\Common\Mode;
use zxf\Utils\QrCode\Common\ReedSolomonCodec;
use zxf\Utils\QrCode\Common\Version;
use zxf\Utils\QrCode\Exception\WriterException;
use SplFixedArray;

/**
 * Encoder.
 */
final class Encoder
{
    /**
     * Default byte encoding.
     */
    public const DEFAULT_BYTE_MODE_ENCODING = 'ISO-8859-1';

    /** @deprecated use DEFAULT_BYTE_MODE_ENCODING */
    public const DEFAULT_BYTE_MODE_ECODING = self::DEFAULT_BYTE_MODE_ENCODING;

    /**
     * Allowed characters for the Alphanumeric Mode.
     */
    private const ALPHANUMERIC_CHARS = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ $%*+-./:';

    /**
     * The original table is defined in the table 5 of JISX0510:2004 (p.19).
     */
    private const ALPHANUMERIC_TABLE = [
        -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,  // 0x00-0x0f
        -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,  // 0x10-0x1f
        36, -1, -1, -1, 37, 38, -1, -1, -1, -1, 39, 40, -1, 41, 42, 43,  // 0x20-0x2f
        0,   1,  2,  3,  4,  5,  6,  7,  8,  9, 44, -1, -1, -1, -1, -1,  // 0x30-0x3f
        -1, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24,  // 0x40-0x4f
        25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, -1, -1, -1, -1, -1,  // 0x50-0x5f
    ];

    /**
     * Codec cache.
     *
     * @var array<string,ReedSolomonCodec>
     */
    private static array $codecs = [];

    /**
     * Encodes "content" with the error correction level "ecLevel".
     *
     * 完整执行一次编码：规范化字符集名称 → 选择模式 → 按需写入 ECI 段 →
     * 写入模式与长度指示符 → 终止并填充比特 → 分块计算纠错码并交织 →
     * 在 8 种掩码中挑选罚分最低者 → 生成最终矩阵。版本先按最小容量试探，
     * 再按试探结果二次计算；指定 $forcedVersion 时只允许放大不可缩小。
     *
     * @param string $content 待编码的原始内容，要求为合法 UTF-8 字节序列
     * @param ErrorCorrectionLevel $ecLevel 纠错级别（L/M/Q/H）
     * @param string $encoding 内容的字符集名称，影响 BYTE 模式的转码与 ECI 前缀
     * @param Version|null $forcedVersion 强制使用的版本；为 null 时按内容自动选择
     * @param bool $prefixEci 非默认字符集时是否在数据前写入 ECI 段
     * @return QrCode 编码结果，包含模式、级别、版本、掩码与矩阵
     * @throws WriterException 当内容超出容量、强制版本过小或转码失败时抛出
     */
    public static function encode(
        string $content,
        ErrorCorrectionLevel $ecLevel,
        string $encoding = self::DEFAULT_BYTE_MODE_ENCODING,
        ?Version $forcedVersion = null,
        // Barcode scanner might not be able to read the encoded message of the QR code with the prefix ECI of UTF-8
        bool $prefixEci = true
    ) : QrCode {
        // 统一字符集别名（UTF8/utf-8/latin1/Shift-JIS/GBK…），保证：
        //  1) chooseMode() 的 SHIFT-JIS 判定不受拼写差异影响；
        //  2) ECI 前缀能命中 CharacterSetEci 名称表（拼写不符会静默跳过 ECI，
        //     导致多字节内容被扫码端按猜测字符集解码而失真——这是「输入与扫码结果
        //     不一致」的隐蔽根因之一）；
        //  3) iconv 转码使用一致的规范名称。
        $encoding = self::normalizeEncodingName($encoding);

        // Pick an encoding mode appropriate for the content. Note that this
        // will not attempt to use multiple modes / segments even if that were
        // more efficient. Would be nice.
        $mode = self::chooseMode($content, $encoding);

        // This will store the header information, like mode and length, as well
        // as "header" segments like an ECI segment.
        $headerBits = new BitArray();

        // Append ECI segment if applicable
        if ($prefixEci && Mode::BYTE() === $mode && self::DEFAULT_BYTE_MODE_ENCODING !== $encoding) {
            $eci = CharacterSetEci::getCharacterSetEciByName($encoding);

            if (null !== $eci) {
                self::appendEci($eci, $headerBits);
            }
        }

        // (With ECI in place,) Write the mode marker
        self::appendModeInfo($mode, $headerBits);

        // Collect data within the main segment, separately, to count its size
        // if needed. Don't add it to main payload yet.
        $dataBits = new BitArray();
        self::appendBytes($content, $mode, $dataBits, $encoding);

        // Hard part: need to know version to know how many bits length takes.
        // But need to know how many bits it takes to know version. First we
        // take a guess at version by assuming version will be the minimum, 1:
        $provisionalBitsNeeded = $headerBits->getSize()
            + $mode->getCharacterCountBits(Version::getVersionForNumber(1))
            + $dataBits->getSize();
        $provisionalVersion = self::chooseVersion($provisionalBitsNeeded, $ecLevel);

        // Use that guess to calculate the right version. I am still not sure
        // this works in 100% of cases.
        $bitsNeeded = $headerBits->getSize()
            + $mode->getCharacterCountBits($provisionalVersion)
            + $dataBits->getSize();
        $version = self::chooseVersion($bitsNeeded, $ecLevel);

        if (null !== $forcedVersion) {
            // Forced version check
            if ($version->getVersionNumber() <= $forcedVersion->getVersionNumber()) {
                // Calculated minimum version is same or equal as forced version
                $version = $forcedVersion;
            } else {
                throw new WriterException(
                    'Invalid version! Calculated version: '
                    . $version->getVersionNumber()
                    . ', requested version: '
                    . $forcedVersion->getVersionNumber()
                );
            }
        }

        $headerAndDataBits = new BitArray();
        $headerAndDataBits->appendBitArray($headerBits);

        // Find "length" of main segment and write it.
        $numLetters = match ($mode) {
            Mode::BYTE()                          => $dataBits->getSizeInBytes(),
            Mode::NUMERIC(), Mode::ALPHANUMERIC() => strlen($content),
            Mode::KANJI()                         => iconv_strlen($content, 'utf-8'),
        };
        self::appendLengthInfo($numLetters, $version, $mode, $headerAndDataBits);

        // Put data together into the overall payload.
        $headerAndDataBits->appendBitArray($dataBits);
        $ecBlocks = $version->getEcBlocksForLevel($ecLevel);
        $numDataBytes = $version->getTotalCodewords() - $ecBlocks->getTotalEcCodewords();

        // Terminate the bits properly.
        self::terminateBits($numDataBytes, $headerAndDataBits);

        // Interleave data bits with error correction code.
        $finalBits = self::interleaveWithEcBytes(
            $headerAndDataBits,
            $version->getTotalCodewords(),
            $numDataBytes,
            $ecBlocks->getNumBlocks()
        );

        // Choose the mask pattern.
        $dimension = $version->getDimensionForVersion();
        $matrix = new ByteMatrix($dimension, $dimension);
        $maskPattern = self::chooseMaskPattern($finalBits, $ecLevel, $version, $matrix);

        // Build the matrix.
        MatrixUtil::buildMatrix($finalBits, $ecLevel, $version, $maskPattern, $matrix);

        return new QrCode($mode, $ecLevel, $version, $maskPattern, $matrix);
    }

    /**
     * Gets the alphanumeric code for a byte.
     *
     * 查 JISX0510 字母数字表，把 ASCII 字节映射为 0~44 的编码值；
     * 表中不含该字节（控制字符、小写字母等）时返回 -1 表示不可编码。
     *
     * @param int $byte 待查表的字节值，取值 0~255
     * @return int 对应的字母数字编码，不支持时返回 -1
     */
    private static function getAlphanumericCode(int $byte) : int
    {
        return self::ALPHANUMERIC_TABLE[$byte] ?? -1;
    }

    /**
     * Chooses the best mode for a given content.
     *
     * 按优先级判定：空串固定用 BYTE；字符集为 Shift_JIS 时先尝试
     * 双字节汉字的 KANJI 模式；纯数字用 NUMERIC；全部落在字母数字表
     * 内用 ALPHANUMERIC；其余一律用 BYTE。单次只选一种模式，不做混合分段。
     *
     * @param string $content 待编码内容
     * @param string|null $encoding 字符集名称，仅用于判定是否走 KANJI 模式
     * @return Mode 选定的编码模式
     */
    private static function chooseMode(string $content, ?string $encoding = null) : Mode
    {
        if ('' === $content) {
            return Mode::BYTE();
        }

        if (null !== $encoding && 0 === strcasecmp($encoding, 'Shift_JIS')) {
            return self::isOnlyDoubleByteKanji($content) ? Mode::KANJI() : Mode::BYTE();
        }

        if (ctype_digit($content)) {
            return Mode::NUMERIC();
        }

        if (self::isOnlyAlphanumeric($content)) {
            return Mode::ALPHANUMERIC();
        }

        return Mode::BYTE();
    }

    /**
     * Calculates the mask penalty for a matrix.
     *
     * 按规范的四条规则累加罚分（连续同色块、2×2 同色块、类定位图案
     * 的 1:1:3:1:1 形态、深浅占比偏离），分值越低表示越易被识别。
     *
     * @param ByteMatrix $matrix 已按某个掩码生成的候选矩阵
     * @return int 四条规则的罚分总和
     */
    private static function calculateMaskPenalty(ByteMatrix $matrix) : int
    {
        return (
            MaskUtil::applyMaskPenaltyRule1($matrix)
            + MaskUtil::applyMaskPenaltyRule2($matrix)
            + MaskUtil::applyMaskPenaltyRule3($matrix)
            + MaskUtil::applyMaskPenaltyRule4($matrix)
        );
    }

    /**
     * Checks if content only consists of double-byte kanji characters (or is empty).
     *
     * 先把内容转成 SHIFT-JIS，再按两字节一组检查首字节是否落在
     * 0x81~0x9F 或 0xE0~0xEB 区间；奇数长度、转码失败或出现区间外
     * 首字节均判定为不满足。
     *
     * @param string $content 待判断的内容（UTF-8）
     * @return bool 全部为双字节日文汉字（或空串）时返回 true
     */
    private static function isOnlyDoubleByteKanji(string $content) : bool
    {
        $bytes = @iconv('utf-8', 'SHIFT-JIS', $content);

        if (false === $bytes) {
            return false;
        }

        $length = strlen($bytes);

        if (0 !== $length % 2) {
            return false;
        }

        for ($i = 0; $i < $length; $i += 2) {
            $byte = ord($bytes[$i]);

            if (($byte < 0x81 || $byte > 0x9f) && $byte < 0xe0 || $byte > 0xeb) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks if content only consists of alphanumeric characters (or is empty).
     *
     * 通过 strspn 统计前缀中连续命中字母数字表字符的长度，与总长度相等
     * 即认为整串都可编码；空串会得到 0 与 0 相等，因此返回 true。
     *
     * @param string $content 待判断的内容
     * @return bool 全部字符都在字母数字表内（或为空串）时返回 true
     */
    private static function isOnlyAlphanumeric(string $content) : bool
    {
        return strlen($content) === strspn($content, self::ALPHANUMERIC_CHARS);
    }

    /**
     * Chooses the best mask pattern for a matrix.
     *
     * 依次试用 0~7 号掩码，把比特流铺进同一个 $matrix 缓冲区并计算罚分，
     * 取罚分最小者。由于矩阵被反复覆盖，返回后 $matrix 中保留的是最后
     * 一次（7 号掩码）的结果，调用方需用返回的掩码重新构建矩阵。
     *
     * @param BitArray $bits 已完成纠错交织的最终比特流
     * @param ErrorCorrectionLevel $ecLevel 纠错级别，用于填充格式信息
     * @param Version $version 二维码版本，决定矩阵尺寸与功能图案
     * @param ByteMatrix $matrix 复用的矩阵缓冲区
     * @return int 罚分最低的掩码编号，取值 0~7
     */
    private static function chooseMaskPattern(
        BitArray $bits,
        ErrorCorrectionLevel $ecLevel,
        Version $version,
        ByteMatrix $matrix
    ) : int {
        $minPenalty = PHP_INT_MAX;
        $bestMaskPattern = -1;

        for ($maskPattern = 0; $maskPattern < QrCode::NUM_MASK_PATTERNS; ++$maskPattern) {
            MatrixUtil::buildMatrix($bits, $ecLevel, $version, $maskPattern, $matrix);
            $penalty = self::calculateMaskPenalty($matrix);

            if ($penalty < $minPenalty) {
                $minPenalty = $penalty;
                $bestMaskPattern = $maskPattern;
            }
        }

        return $bestMaskPattern;
    }

    /**
     * 选择最适合的二维码版本
     * 根据数据大小和纠错级别自动选择最佳版本（1-40）
     * 优化了超长文本的错误处理，提供更详细的错误信息
     *
     * @param int $numInputBits 输入数据的比特数
     * @param ErrorCorrectionLevel $ecLevel 错误纠正级别
     * @return Version 选定的版本
     * @throws WriterException 如果数据超过最大容量，抛出包含详细信息的异常
     */
    private static function chooseVersion(int $numInputBits, ErrorCorrectionLevel $ecLevel) : Version
    {
        $maxVersion = 40;
        $lastVersion = null;
        $lastDataBytes = 0;
        $totalInputBytes = intdiv($numInputBits + 8, 8);

        for ($versionNum = 1; $versionNum <= $maxVersion; ++$versionNum) {
            $version = Version::getVersionForNumber($versionNum);
            $numBytes = $version->getTotalCodewords();

            $ecBlocks = $version->getEcBlocksForLevel($ecLevel);
            $numEcBytes = $ecBlocks->getTotalEcCodewords();

            $numDataBytes = $numBytes - $numEcBytes;

            // 记录最后一个版本信息用于错误报告
            $lastVersion = $version;
            $lastDataBytes = $numDataBytes;

            if ($numDataBytes >= $totalInputBytes) {
                return $version;
            }
        }

        // 数据超容，提供详细的错误信息
        $ecLevelName = $ecLevel->getName();
        $excessBytes = $totalInputBytes - $lastDataBytes;

        throw new WriterException(
            "数据量超出二维码最大容量 (版本40)。\n" .
            "错误纠正级别: {$ecLevelName}\n" .
            "需要空间: {$totalInputBytes} 字节\n" .
            "最大可用空间: {$lastDataBytes} 字节\n" .
            "超出容量: {$excessBytes} 字节\n" .
            "建议:\n" .
            "1. 降低错误纠正级别 (尝试使用 'L')\n" .
            "2. 减少数据内容长度\n" .
            "3. 将数据分割为多个二维码"
        );
    }

    /**
     * Terminates the bits in a bit array.
     *
     * 先校验比特数未超容量，随后依次追加至多 4 个终止零位、补齐到字节边界，
     * 再用 0xEC/0x11 交替填充剩余字节，使总长度精确等于数据容量。
     *
     * @param int $numDataBytes 该版本可用的数据码字总数（容量上限）
     * @param BitArray $bits 待终止的比特流，会被就地修改
     * @throws WriterException if data bits cannot fit in the QR code
     * @throws WriterException if bits size does not equal the capacity
     */
    private static function terminateBits(int $numDataBytes, BitArray $bits) : void
    {
        $capacity = $numDataBytes << 3;

        if ($bits->getSize() > $capacity) {
            throw new WriterException('Data bits cannot fit in the QR code');
        }

        for ($i = 0; $i < 4 && $bits->getSize() < $capacity; ++$i) {
            $bits->appendBit(false);
        }

        $numBitsInLastByte = $bits->getSize() & 0x7;

        if ($numBitsInLastByte > 0) {
            for ($i = $numBitsInLastByte; $i < 8; ++$i) {
                $bits->appendBit(false);
            }
        }

        $numPaddingBytes = $numDataBytes - $bits->getSizeInBytes();

        for ($i = 0; $i < $numPaddingBytes; ++$i) {
            $bits->appendBits(0 === ($i & 0x1) ? 0xec : 0x11, 8);
        }

        if ($bits->getSize() !== $capacity) {
            throw new WriterException('Bits size does not equal capacity');
        }
    }

    /**
     * Gets number of data- and EC bytes for a block ID.
     *
     * 当码字总数不能被块数整除时，块被分为两组：前一组每块码字较少，
     * 后一组每块多 1 个码字，但两组的纠错码字数必须相同。方法在返回前
     * 会对分组自洽性做三项校验。
     *
     * @param int $numTotalBytes 该版本总码字数（数据 + 纠错）
     * @param int $numDataBytes 该版本数据码字数
     * @param int $numRsBlocks RS 块数
     * @param int $blockId 目标块编号，从 0 开始，需小于 $numRsBlocks
     * @return int[] 形如 [该块数据码字数, 该块纠错码字数]
     * @throws WriterException if block ID is too large
     * @throws WriterException if EC bytes mismatch
     * @throws WriterException if RS blocks mismatch
     * @throws WriterException if total bytes mismatch
     */
    private static function getNumDataBytesAndNumEcBytesForBlockId(
        int $numTotalBytes,
        int $numDataBytes,
        int $numRsBlocks,
        int $blockId
    ) : array {
        if ($blockId >= $numRsBlocks) {
            throw new WriterException('Block ID too large');
        }

        $numRsBlocksInGroup2 = $numTotalBytes % $numRsBlocks;
        $numRsBlocksInGroup1 = $numRsBlocks - $numRsBlocksInGroup2;
        $numTotalBytesInGroup1 = intdiv($numTotalBytes, $numRsBlocks);
        $numTotalBytesInGroup2 = $numTotalBytesInGroup1 + 1;
        $numDataBytesInGroup1 = intdiv($numDataBytes, $numRsBlocks);
        $numDataBytesInGroup2 = $numDataBytesInGroup1 + 1;
        $numEcBytesInGroup1 = $numTotalBytesInGroup1 - $numDataBytesInGroup1;
        $numEcBytesInGroup2 = $numTotalBytesInGroup2 - $numDataBytesInGroup2;

        if ($numEcBytesInGroup1 !== $numEcBytesInGroup2) {
            throw new WriterException('EC bytes mismatch');
        }

        if ($numRsBlocks !== $numRsBlocksInGroup1 + $numRsBlocksInGroup2) {
            throw new WriterException('RS blocks mismatch');
        }

        if ($numTotalBytes !==
            (($numDataBytesInGroup1 + $numEcBytesInGroup1) * $numRsBlocksInGroup1)
            + (($numDataBytesInGroup2 + $numEcBytesInGroup2) * $numRsBlocksInGroup2)
        ) {
            throw new WriterException('Total bytes mismatch');
        }

        if ($blockId < $numRsBlocksInGroup1) {
            return [$numDataBytesInGroup1, $numEcBytesInGroup1];
        } else {
            return [$numDataBytesInGroup2, $numEcBytesInGroup2];
        }
    }

    /**
     * Interleaves data with EC bytes.
     *
     * 按块切分数据、逐块计算纠错码，然后先按列交织所有数据码字、再按列
     * 交织所有纠错码字（短块跳过缺失位置），得到规范的最终比特流。
     * 交织前后都会校验字节数与预期一致。
     *
     * @param BitArray $bits 已完成终止填充的数据比特流
     * @param int $numTotalBytes 该版本总码字数
     * @param int $numDataBytes 该版本数据码字数
     * @param int $numRsBlocks RS 块数
     * @return BitArray 交织后的最终比特流，长度等于 $numTotalBytes 字节
     * @throws WriterException if number of bits and data bytes does not match
     * @throws WriterException if data bytes does not match offset
     * @throws WriterException if an interleaving error occurs
     */
    private static function interleaveWithEcBytes(
        BitArray $bits,
        int $numTotalBytes,
        int $numDataBytes,
        int $numRsBlocks
    ) : BitArray {
        if ($bits->getSizeInBytes() !== $numDataBytes) {
            throw new WriterException('Number of bits and data bytes does not match');
        }

        $dataBytesOffset = 0;
        $maxNumDataBytes = 0;
        $maxNumEcBytes   = 0;

        $blocks = new SplFixedArray($numRsBlocks);

        for ($i = 0; $i < $numRsBlocks; ++$i) {
            list($numDataBytesInBlock, $numEcBytesInBlock) = self::getNumDataBytesAndNumEcBytesForBlockId(
                $numTotalBytes,
                $numDataBytes,
                $numRsBlocks,
                $i
            );

            $size = $numDataBytesInBlock;
            $dataBytes = $bits->toBytes(8 * $dataBytesOffset, $size);
            $ecBytes = self::generateEcBytes($dataBytes, $numEcBytesInBlock);
            $blocks[$i] = new BlockPair($dataBytes, $ecBytes);

            $maxNumDataBytes = max($maxNumDataBytes, $size);
            $maxNumEcBytes = max($maxNumEcBytes, count($ecBytes));
            $dataBytesOffset += $numDataBytesInBlock;
        }

        if ($numDataBytes !== $dataBytesOffset) {
            throw new WriterException('Data bytes does not match offset');
        }

        $result = new BitArray();

        for ($i = 0; $i < $maxNumDataBytes; ++$i) {
            foreach ($blocks as $block) {
                $dataBytes = $block->getDataBytes();

                if ($i < count($dataBytes)) {
                    $result->appendBits($dataBytes[$i], 8);
                }
            }
        }

        for ($i = 0; $i < $maxNumEcBytes; ++$i) {
            foreach ($blocks as $block) {
                $ecBytes = $block->getErrorCorrectionBytes();

                if ($i < count($ecBytes)) {
                    $result->appendBits($ecBytes[$i], 8);
                }
            }
        }

        if ($numTotalBytes !== $result->getSizeInBytes()) {
            throw new WriterException(
                'Interleaving error: ' . $numTotalBytes . ' and ' . $result->getSizeInBytes() . ' differ'
            );
        }

        return $result;
    }

    /**
     * Generates EC bytes for given data.
     *
     * 把数据码字复制到长度为「数据 + 纠错」的缓冲区尾部保留零位，
     * 再由对应参数的 Reed-Solomon 编码器算出纠错码字。
     *
     * @param SplFixedArray<int> $dataBytes 该块的数据码字
     * @param int $numEcBytesInBlock 该块需要的纠错码字数
     * @return SplFixedArray<int> 计算出的纠错码字，长度等于 $numEcBytesInBlock
     */
    private static function generateEcBytes(SplFixedArray $dataBytes, int $numEcBytesInBlock) : SplFixedArray
    {
        $numDataBytes = count($dataBytes);
        $toEncode = new SplFixedArray($numDataBytes + $numEcBytesInBlock);

        for ($i = 0; $i < $numDataBytes; $i++) {
            $toEncode[$i] = $dataBytes[$i];
        }

        $ecBytes = new SplFixedArray($numEcBytesInBlock);
        $codec = self::getCodec($numDataBytes, $numEcBytesInBlock);
        $codec->encode($toEncode, $ecBytes);

        return $ecBytes;
    }

    /**
     * Gets an RS codec and caches it.
     *
     * 以「数据码字数-纠错码字数」为键缓存编码器，避免同一版本同级别下
     * 重复构建伽罗华域查找表；参数固定为 8 位符号、本原多项式 0x11d。
     *
     * @param int $numDataBytes 每块的数据码字数
     * @param int $numEcBytesInBlock 每块的纠错码字数（即生成多项式次数）
     * @return ReedSolomonCodec 与该块参数匹配的编码器实例（可能来自缓存）
     */
    private static function getCodec(int $numDataBytes, int $numEcBytesInBlock) : ReedSolomonCodec
    {
        $cacheId = $numDataBytes . '-' . $numEcBytesInBlock;

        if (isset(self::$codecs[$cacheId])) {
            return self::$codecs[$cacheId];
        }

        return self::$codecs[$cacheId] = new ReedSolomonCodec(
            8,
            0x11d,
            0,
            1,
            $numEcBytesInBlock,
            255 - $numDataBytes - $numEcBytesInBlock
        );
    }

    /**
     * Appends mode information to a bit array.
     *
     * 把模式的 4 位指示符写入比特流，作为该数据段的起始标记。
     *
     * @param Mode $mode 要声明的编码模式
     * @param BitArray $bits 目标比特流，会被就地追加
     */
    private static function appendModeInfo(Mode $mode, BitArray $bits) : void
    {
        $bits->appendBits($mode->getBits(), 4);
    }

    /**
     * Appends length information to a bit array.
     *
     * 以该版本该模式对应的比特宽度写入字符计数，宽度由
     * Mode::getCharacterCountBits() 决定；长度超出可表示范围时抛异常。
     *
     * @param int $numLetters 字符（或字节）数量，含义随模式变化
     * @param Version $version 二维码版本，决定计数位宽
     * @param Mode $mode 当前编码模式
     * @param BitArray $bits 目标比特流，会被就地追加
     * @throws WriterException if num letters is bigger than expected
     */
    private static function appendLengthInfo(int $numLetters, Version $version, Mode $mode, BitArray $bits) : void
    {
        $numBits = $mode->getCharacterCountBits($version);

        if ($numLetters >= (1 << $numBits)) {
            throw new WriterException($numLetters . ' is bigger than ' . ((1 << $numBits) - 1));
        }

        $bits->appendBits($numLetters, $numBits);
    }

    /**
     * Appends bytes to a bit array in a specific mode.
     *
     * 按模式分派到数字 / 字母数字 / 8 位字节 / 汉字四种写入实现，
     * 未列入 match 的模式不会追加任何比特（静默跳过）。
     *
     * @param string $content 待写入的原始内容
     * @param Mode $mode 编码模式，决定分派目标
     * @param BitArray $bits 目标比特流，会被就地追加
     * @param string $encoding 字符集名称，仅 BYTE 模式使用
     * @throws WriterException 由具体分派方法抛出（非法字符、转码失败等）
     */
    private static function appendBytes(string $content, Mode $mode, BitArray $bits, string $encoding) : void
    {
        match ($mode) {
            Mode::NUMERIC()      => self::appendNumericBytes($content, $bits),
            Mode::ALPHANUMERIC() => self::appendAlphanumericBytes($content, $bits),
            Mode::BYTE()         => self::append8BitBytes($content, $bits, $encoding),
            Mode::KANJI()        => self::appendKanjiBytes($content, $bits),
        };
    }

    /**
     * Appends numeric bytes to a bit array.
     *
     * 按规范做三位一组压缩：满 3 位用 10 比特、剩 2 位用 7 比特、
     * 剩 1 位用 4 比特，直接以字符的 ASCII 码参与数值拼接。
     *
     * @param string $content 纯数字字符串，调用方需保证只含 0~9
     * @param BitArray $bits 目标比特流，会被就地追加
     */
    private static function appendNumericBytes(string $content, BitArray $bits) : void
    {
        $length = strlen($content);
        $i = 0;

        while ($i < $length) {
            $num1 = (int) $content[$i];

            if ($i + 2 < $length) {
                // Encode three numeric letters in ten bits.
                $num2 = (int) $content[$i + 1];
                $num3 = (int) $content[$i + 2];
                $bits->appendBits($num1 * 100 + $num2 * 10 + $num3, 10);
                $i += 3;
            } elseif ($i + 1 < $length) {
                // Encode two numeric letters in seven bits.
                $num2 = (int) $content[$i + 1];
                $bits->appendBits($num1 * 10 + $num2, 7);
                $i += 2;
            } else {
                // Encode one numeric letter in four bits.
                $bits->appendBits($num1, 4);
                ++$i;
            }
        }
    }

    /**
     * Appends alpha-numeric bytes to a bit array.
     *
     * 两字符成对压缩为 11 比特（code1 × 45 + code2），末位单字符占 6 比特；
     * 任一字符不在字母数字表中（查表结果为 -1）即中止并抛出异常。
     *
     * @param string $content 仅含字母数字表内字符的字符串
     * @param BitArray $bits 目标比特流，会被就地追加
     * @throws WriterException if an invalid alphanumeric code was found
     */
    private static function appendAlphanumericBytes(string $content, BitArray $bits) : void
    {
        $length = strlen($content);
        $i = 0;

        while ($i < $length) {
            $code1 = self::getAlphanumericCode(ord($content[$i]));

            if (-1 === $code1) {
                throw new WriterException('Invalid alphanumeric code');
            }

            if ($i + 1 < $length) {
                $code2 = self::getAlphanumericCode(ord($content[$i + 1]));

                if (-1 === $code2) {
                    throw new WriterException('Invalid alphanumeric code');
                }

                // Encode two alphanumeric letters in 11 bits.
                $bits->appendBits($code1 * 45 + $code2, 11);
                $i += 2;
            } else {
                // Encode one alphanumeric letter in six bits.
                $bits->appendBits($code1, 6);
                ++$i;
            }
        }
    }

    /**
     * Appends regular 8-bit bytes to a bit array.
     *
     * 输入内容契约：始终按 UTF-8 字节序列提供（与 chooseMode/ECI 处理一致）。
     * 若内容不是合法 UTF-8，iconv 在「源=目标同字符集」的实现下会原样透传非法字节，
     * 造成「生成时写入的字节 ≠ 解码端按声明字符集解析的字节」的静默失真，
     * 因此必须先显式校验；转码失败（目标字符集无法表示某字符）则抛 WriterException。
     *
     * @param string $content 待写入的内容，必须为合法 UTF-8
     * @param BitArray $bits 目标比特流，会被就地追加
     * @param string $encoding 目标字符集名称，内容会先由 UTF-8 转码到该字符集
     * @throws WriterException if content is not valid UTF-8 or cannot be encoded to target encoding
     */
    private static function append8BitBytes(string $content, BitArray $bits, string $encoding) : void
    {
        if (! self::isValidUtf8($content)) {
            throw new WriterException(
                'Content is not valid UTF-8 and therefore cannot be encoded consistently to ' . $encoding
            );
        }

        $bytes = @iconv('UTF-8', $encoding, $content);

        if (false === $bytes) {
            throw new WriterException('Could not encode content to ' . $encoding);
        }

        $length = strlen($bytes);

        for ($i = 0; $i < $length; $i++) {
            $bits->appendBits(ord($bytes[$i]), 8);
        }
    }

    /**
     * Checks whether content is a valid UTF-8 byte sequence.
     *
     * 用带 u 修饰符的空正则做一次匹配，PCRE 会在 UTF-8 校验失败时返回
     * false；借助错误抑制符避免非法字节触发警告。
     *
     * @param string $content 待校验的字节串
     * @return bool 是合法 UTF-8 时返回 true，否则返回 false
     */
    private static function isValidUtf8(string $content) : bool
    {
        return false !== @preg_match('//u', $content);
    }

    /**
     * Normalizes common character set name spellings to a canonical name
     * that is both iconv-compatible and present in the CharacterSetEci name table.
     * 无法识别的拼写保持原样（交给 iconv / ECI 名称表自行裁决）。
     *
     * 查找键会先转成小写，因此拼写中的大小写差异不影响结果；仅做名称
     * 归一化，不校验目标字符集是否真的可用。
     *
     * @param string $encoding 调用方传入的字符集名称
     * @return string 规范化后的字符集名称；未命中映射表时原样返回
     */
    private static function normalizeEncodingName(string $encoding) : string
    {
        $map = [
            'utf8'                  => 'UTF-8',
            'utf-8'                 => 'UTF-8',
            'utf_8'                 => 'UTF-8',
            'unicode-1-1-utf-8'     => 'UTF-8',
            'utf8mb4'               => 'UTF-8',
            'latin1'                => 'ISO-8859-1',
            'latin-1'               => 'ISO-8859-1',
            'l1'                    => 'ISO-8859-1',
            'iso8859-1'             => 'ISO-8859-1',
            'iso88591'              => 'ISO-8859-1',
            'iso-8859_1'            => 'ISO-8859-1',
            'iso_8859-1'            => 'ISO-8859-1',
            'iso_8859_1'            => 'ISO-8859-1',
            'cp819'                 => 'ISO-8859-1',
            'shift-jis'             => 'Shift_JIS',
            'shift_jis'             => 'Shift_JIS',
            'shiftjis'              => 'Shift_JIS',
            'sjis'                  => 'Shift_JIS',
            'ms-kanji'              => 'Shift_JIS',
            'x-sjis'                => 'Shift_JIS',
            'ascii'                 => 'US-ASCII',
            'us-ascii'              => 'US-ASCII',
            'usascii'               => 'US-ASCII',
            'gb2312'                => 'GB2312',
            'euc-cn'                => 'GB2312',
            'euc_cn'                => 'GB2312',
            'gbk'                   => 'GBK',
            'gb18030'               => 'GB18030',
            'euc-kr'                => 'EUC-KR',
            'euckr'                 => 'EUC-KR',
            'ks_c_5601-1987'        => 'EUC-KR',
        ];

        return $map[strtolower($encoding)] ?? $encoding;
    }

    /**
     * Appends KANJI bytes to a bit array.
     *
     * 先把内容转成 SHIFT-JIS，再按两字节一组的码位区间（0x8140~0x9FFC、
     * 0xE040~0xEBBF）压缩为 13 比特；长度为奇数或码位落在区间外均视为
     * 非 SHIFT-JIS 内容而抛异常。
     *
     * @param string $content 待写入的内容（UTF-8），应仅含日文汉字
     * @param BitArray $bits 目标比特流，会被就地追加
     * @throws WriterException if content does not seem to be encoded in SHIFT-JIS
     * @throws WriterException if an invalid byte sequence occurs
     */
    private static function appendKanjiBytes(string $content, BitArray $bits) : void
    {
        $bytes = @iconv('utf-8', 'SHIFT-JIS', $content);

        if (false === $bytes) {
            throw new WriterException('Content could not be converted to SHIFT-JIS');
        }

        if (strlen($bytes) % 2 > 0) {
            // We just do a simple length check here. The for loop will check
            // individual characters.
            throw new WriterException('Content does not seem to be encoded in SHIFT-JIS');
        }

        $length = strlen($bytes);

        for ($i = 0; $i < $length; $i += 2) {
            $byte1 = ord($bytes[$i]);
            $byte2 = ord($bytes[$i + 1]);
            $code = ($byte1 << 8) | $byte2;

            if ($code >= 0x8140 && $code <= 0x9ffc) {
                $subtracted = $code - 0x8140;
            } elseif ($code >= 0xe040 && $code <= 0xebbf) {
                $subtracted = $code - 0xc140;
            } else {
                throw new WriterException('Invalid byte sequence');
            }

            $encoded = (($subtracted >> 8) * 0xc0) + ($subtracted & 0xff);

            $bits->appendBits($encoded, 13);
        }
    }

    /**
     * Appends ECI information to a bit array.
     *
     * 先写入 ECI 模式的 4 位指示符（0x07），再以 8 位定长写入字符集的
     * 主编号，告知解码方后续字节流使用的字符集。
     *
     * @param CharacterSetEci $eci 要声明的字符集
     * @param BitArray $bits 目标比特流，会被就地追加
     */
    private static function appendEci(CharacterSetEci $eci, BitArray $bits) : void
    {
        $mode = Mode::ECI();
        $bits->appendBits($mode->getBits(), 4);
        $bits->appendBits($eci->getValue(), 8);
    }
}
