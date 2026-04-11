<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font;

use function count;

use InvalidArgumentException;

use function ord;
use function strlen;
use function substr;

final readonly class CffFontParser
{
    public function __construct(
        private string $data,
    ) {
        if (strlen($this->data) < 4) {
            throw new InvalidArgumentException('CFF data is too short.');
        }
    }

    public function postScriptName(): string
    {
        $headerSize = ord($this->data[2]);
        $nameIndex = $this->readIndex($headerSize)['items'];

        if ($nameIndex === []) {
            throw new InvalidArgumentException('CFF table does not contain a Name INDEX entry.');
        }

        return $nameIndex[0];
    }

    public function fontBoundingBox(): FontBoundingBox
    {
        $topDict = $this->topDictOperands();
        $operands = $topDict['FontBBox'] ?? null;

        if ($operands === null || count($operands) !== 4) {
            throw new InvalidArgumentException('CFF Top DICT does not contain a valid FontBBox entry.');
        }

        return new FontBoundingBox(
            left: (int) $operands[0],
            bottom: (int) $operands[1],
            right: (int) $operands[2],
            top: (int) $operands[3],
        );
    }

    public function italicAngle(): float
    {
        $topDict = $this->topDictOperands();
        $operands = $topDict['ItalicAngle'] ?? null;

        if ($operands === null || $operands === []) {
            return 0.0;
        }

        return (float) $operands[0];
    }

    public function charsetOffset(): int
    {
        $topDict = $this->topDictOperands();
        $operands = $topDict['charset'] ?? null;

        if ($operands === null || $operands === []) {
            throw new InvalidArgumentException('CFF Top DICT does not contain a charset offset.');
        }

        return (int) $operands[0];
    }

    public function charStringsOffset(): int
    {
        $topDict = $this->topDictOperands();
        $operands = $topDict['CharStrings'] ?? null;

        if ($operands === null || $operands === []) {
            throw new InvalidArgumentException('CFF Top DICT does not contain a CharStrings offset.');
        }

        return (int) $operands[0];
    }

    public function charStringCount(): int
    {
        return count($this->readIndex($this->charStringsOffset())['items']);
    }

    /**
     * @return list<string>
     */
    public function charStrings(): array
    {
        return $this->readIndex($this->charStringsOffset())['items'];
    }

    /**
     * @return list<int>
     */
    public function charsetSids(): array
    {
        $offset = $this->charsetOffset();
        $format = ord($this->readBytes($offset, 1));
        $count = $this->charStringCount();
        $sids = [0];

        if ($format === 0) {
            for ($index = 1; $index < $count; $index++) {
                $sids[] = $this->readUInt16($offset + 1 + (($index - 1) * 2));
            }

            return $sids;
        }

        if ($format !== 1 && $format !== 2) {
            throw new InvalidArgumentException('Unsupported CFF charset format.');
        }

        $cursor = $offset + 1;

        while (count($sids) < $count) {
            $firstSid = $this->readUInt16($cursor);
            $cursor += 2;
            $rangeLength = $format === 1
                ? ord($this->readBytes($cursor, 1))
                : $this->readUInt16($cursor);
            $cursor += $format === 1 ? 1 : 2;

            for ($sid = $firstSid; $sid <= $firstSid + $rangeLength; $sid++) {
                $sids[] = $sid;

                if (count($sids) === $count) {
                    return $sids;
                }
            }
        }

        return $sids;
    }

    /**
     * @return array{items: list<string>, nextOffset: int}
     */
    private function readIndex(int $offset): array
    {
        $count = $this->readUInt16($offset);

        if ($count === 0) {
            return [
                'items' => [],
                'nextOffset' => $offset + 2,
            ];
        }

        $offSize = ord($this->readBytes($offset + 2, 1));
        $offsetsStart = $offset + 3;
        $dataStart = $offsetsStart + (($count + 1) * $offSize);
        $items = [];

        for ($index = 0; $index < $count; $index++) {
            $start = $this->readOffset($offsetsStart + ($index * $offSize), $offSize);
            $end = $this->readOffset($offsetsStart + (($index + 1) * $offSize), $offSize);
            $items[] = $this->readBytes($dataStart + $start - 1, $end - $start);
        }

        $lastOffset = $this->readOffset($offsetsStart + ($count * $offSize), $offSize);

        return [
            'items' => $items,
            'nextOffset' => $dataStart + $lastOffset - 1,
        ];
    }

    /**
     * @return array<string, list<int|float>>
     */
    private function topDictOperands(): array
    {
        $headerSize = ord($this->data[2]);
        $nameIndex = $this->readIndex($headerSize);
        $topDictIndex = $this->readIndex($nameIndex['nextOffset']);
        $topDictData = $topDictIndex['items'][0] ?? null;

        if ($topDictData === null) {
            throw new InvalidArgumentException('CFF table does not contain a Top DICT INDEX entry.');
        }

        $offset = 0;
        $length = strlen($topDictData);
        $operands = [];
        $stack = [];

        while ($offset < $length) {
            $byte = ord($topDictData[$offset]);

            if ($byte === 28) {
                $stack[] = $this->readInt16From($topDictData, $offset + 1);
                $offset += 3;

                continue;
            }

            if ($byte === 29) {
                $stack[] = $this->readInt32From($topDictData, $offset + 1);
                $offset += 5;

                continue;
            }

            if ($byte >= 32 && $byte <= 246) {
                $stack[] = $byte - 139;
                $offset++;

                continue;
            }

            if ($byte >= 247 && $byte <= 250) {
                $stack[] = (($byte - 247) * 256) + ord($topDictData[$offset + 1]) + 108;
                $offset += 2;

                continue;
            }

            if ($byte >= 251 && $byte <= 254) {
                $stack[] = -((($byte - 251) * 256) + ord($topDictData[$offset + 1]) + 108);
                $offset += 2;

                continue;
            }

            $operator = match ($byte) {
                5 => 'FontBBox',
                15 => 'charset',
                17 => 'CharStrings',
                12 => match (ord($topDictData[$offset + 1])) {
                    2 => 'ItalicAngle',
                    default => null,
                },
                default => null,
            };

            $offset += $byte === 12 ? 2 : 1;

            if ($operator === null) {
                $stack = [];

                continue;
            }

            $operands[$operator] = $stack;
            $stack = [];
        }

        return $operands;
    }

    private function readOffset(int $offset, int $size): int
    {
        $value = 0;

        for ($index = 0; $index < $size; $index++) {
            $value = ($value << 8) | ord($this->readBytes($offset + $index, 1));
        }

        return $value;
    }

    private function readUInt16(int $offset): int
    {
        $bytes = $this->readBytes($offset, 2);

        return (ord($bytes[0]) << 8) | ord($bytes[1]);
    }

    private function readInt16From(string $data, int $offset): int
    {
        $bytes = substr($data, $offset, 2);

        if (strlen($bytes) !== 2) {
            throw new InvalidArgumentException('Unexpected end of CFF DICT operand data.');
        }

        $value = (ord($bytes[0]) << 8) | ord($bytes[1]);

        return $value >= 0x8000 ? $value - 0x10000 : $value;
    }

    private function readInt32From(string $data, int $offset): int
    {
        $bytes = substr($data, $offset, 4);

        if (strlen($bytes) !== 4) {
            throw new InvalidArgumentException('Unexpected end of CFF DICT operand data.');
        }

        $value = (ord($bytes[0]) << 24)
            | (ord($bytes[1]) << 16)
            | (ord($bytes[2]) << 8)
            | ord($bytes[3]);

        return $value >= 0x80000000 ? $value - 0x100000000 : $value;
    }

    private function readBytes(int $offset, int $length): string
    {
        $bytes = substr($this->data, $offset, $length);

        if (strlen($bytes) !== $length) {
            throw new InvalidArgumentException('Unexpected end of CFF data.');
        }

        return $bytes;
    }
}
