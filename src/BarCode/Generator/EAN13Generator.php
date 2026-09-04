<?php

declare(strict_types=1);

namespace zxf\Utils\BarCode\Generator;

use zxf\Utils\BarCode\Exceptions\InvalidDataException;

class EAN13Generator extends BaseGenerator
{
    protected const TYPE = 'EAN-13';
    protected const START_GUARD = '101';
    protected const MIDDLE_GUARD = '01010';
    protected const END_GUARD = '101';

    protected array $parityPattern = [
        ['A','A','A','A','A','A'],['A','A','B','A','B','B'],
        ['A','A','B','B','A','B'],['A','A','B','B','B','A'],
        ['A','B','A','A','B','B'],['A','B','B','A','A','B'],
        ['A','B','B','B','A','A'],['A','B','A','B','A','B'],
        ['A','B','A','B','B','A'],['A','B','B','A','B','A'],
    ];

    protected array $encodingA = [
        '0001101','0011001','0010011','0111101','0100011',
        '0110001','0101111','0111011','0110111','0001011',
    ];

    protected array $encodingB = [
        '0100111','0110011','0011011','0100001','0011101',
        '0111001','0000101','0010001','0001001','0010111',
    ];

    protected array $encodingC = [
        '1110010','1100110','1101100','1000010','1011100',
        '1001110','1010000','1000100','1001000','1110100',
    ];

    protected string $currentData = '';

    public function generate(string $data): array
    {
        $data = $this->sanitizeData($data);
        if (!$this->validate($data)) {
            throw new InvalidDataException('EAN-13 数据必须是12或13位纯数字，当前数据: ' . $data);
        }

        if (strlen($data) === 13) {
            // 输入已含校验位：默认验证正确性（skipChecksum(true) 可跳过），
            // 校验位错误时抛异常，避免生成被校验型扫码器拒绝的坏码
            $this->assertCompleteChecksum($data, 'EAN-13');
            $this->rawData = $data;
            $this->currentData = $data;
        } else {
            $checksum = $this->calculateChecksum($data);
            $this->rawData = $data . $checksum;
            $this->currentData = $this->rawData;
        }

        $this->barcodeArray = [];
        $this->longBarPositions = [];

        $firstDigit = (int) $this->rawData[0];
        $leftData = substr($this->rawData, 1, 6);
        $rightData = substr($this->rawData, 7, 6);

        $seq = self::START_GUARD;
        $parity = $this->parityPattern[$firstDigit];
        for ($i = 0; $i < 6; $i++) {
            $digit = (int) $leftData[$i];
            $seq .= $parity[$i] === 'A' ? $this->encodingA[$digit] : $this->encodingB[$digit];
        }
        $seq .= self::MIDDLE_GUARD;
        for ($i = 0; $i < 6; $i++) {
            $digit = (int) $rightData[$i];
            $seq .= $this->encodingC[$digit];
        }
        $seq .= self::END_GUARD;

        $this->barcodeArray = $this->binaryToBars($seq);

        $this->longBarPositions = [
            $this->binaryOffsetToBarIndex($seq, 0),
            $this->binaryOffsetToBarIndex($seq, 2),
            $this->binaryOffsetToBarIndex($seq, 46),
            $this->binaryOffsetToBarIndex($seq, 48),
            $this->binaryOffsetToBarIndex($seq, 92),
            $this->binaryOffsetToBarIndex($seq, 94),
        ];
        sort($this->longBarPositions);

        return $this->barcodeArray;
    }

    public function validate(string $data): bool
    {
        $data = $this->sanitizeData($data);
        if (strlen($data) !== 12 && strlen($data) !== 13) {
            return false;
        }
        return $this->isNumeric($data);
    }

    public function calculateChecksum(string $data): string
    {
        $data = $this->sanitizeData($data);
        if (strlen($data) < 12 || !$this->isNumeric(substr($data, 0, 12))) {
            throw new InvalidDataException('计算校验位需要至少12位纯数字');
        }
        $baseData = substr($data, 0, 12);
        $sum = 0;
        $length = strlen($baseData);
        for ($i = 0; $i < $length; $i++) {
            $digit = (int) $baseData[$length - 1 - $i];
            $position = $i + 1;
            $multiplier = ($position % 2 === 1) ? 3 : 1;
            $sum += $digit * $multiplier;
        }
        $checksum = (10 - ($sum % 10)) % 10;
        return (string) $checksum;
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    public function getFullData(): string
    {
        return $this->currentData;
    }

    public function getDigitLayout(): array
    {
        return [
            'type' => 'ean13',
            'firstDigit' => $this->currentData[0] ?? '',
            'leftDigits' => substr($this->currentData, 1, 6),
            'rightDigits' => substr($this->currentData, 7, 6),
            'fullData' => $this->currentData,
        ];
    }
}
