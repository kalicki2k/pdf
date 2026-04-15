<?php

declare(strict_types=1);

namespace Kalle\Pdf\Image;

use function count;
use function implode;
use function is_int;
use function preg_replace;
use function rtrim;
use function sprintf;
use function str_starts_with;

use InvalidArgumentException;

final readonly class PdfFilter
{
    /**
     * @param array<string, bool|int|float|string> $decodeParameters
     */
    public function __construct(
        public string $name,
        public array $decodeParameters = [],
    ) {
        if ($this->name === '' || !str_starts_with($this->name, '/')) {
            throw new InvalidArgumentException(sprintf(
                'PDF filter names must start with "/"; received "%s".',
                $this->name,
            ));
        }

        foreach ($this->decodeParameters as $key => $_value) {
            if ($key === '') {
                throw new InvalidArgumentException('PDF decode parameter keys must be non-empty strings.');
            }
        }
    }

    /**
     * @param array<string, bool|int|float|string> $decodeParameters
     */
    public static function named(string $name, array $decodeParameters = []): self
    {
        return new self($name, $decodeParameters);
    }

    public static function dct(): self
    {
        return new self('/DCTDecode');
    }

    /**
     * @param array<string, bool|int|float|string> $decodeParameters
     */
    public static function flate(array $decodeParameters = []): self
    {
        return new self('/FlateDecode', $decodeParameters);
    }

    /**
     * @param array<string, bool|int|float|string> $decodeParameters
     */
    public static function lzw(array $decodeParameters = []): self
    {
        return new self('/LZWDecode', $decodeParameters);
    }

    public static function runLength(): self
    {
        return new self('/RunLengthDecode');
    }

    public static function ccittFax(
        int $columns,
        int $rows,
        int $k = 0,
        bool $blackIs1 = false,
        bool $encodedByteAlign = false,
        bool $endOfLine = false,
        bool $endOfBlock = true,
    ): self {
        $decodeParameters = [
            'K' => $k,
            'Columns' => $columns,
            'Rows' => $rows,
        ];

        if ($blackIs1) {
            $decodeParameters['BlackIs1'] = true;
        }

        if ($encodedByteAlign) {
            $decodeParameters['EncodedByteAlign'] = true;
        }

        if ($endOfLine) {
            $decodeParameters['EndOfLine'] = true;
        }

        if (!$endOfBlock) {
            $decodeParameters['EndOfBlock'] = false;
        }

        return new self('/CCITTFaxDecode', $decodeParameters);
    }

    public function hasDecodeParameters(): bool
    {
        return $this->decodeParameters !== [];
    }

    public function pdfDecodeParametersContents(): string
    {
        if ($this->decodeParameters === []) {
            throw new InvalidArgumentException(sprintf(
                'PDF filter %s does not define decode parameters.',
                $this->name,
            ));
        }

        $entries = [];

        foreach ($this->decodeParameters as $key => $value) {
            $entries[] = '/' . $key . ' ' . $this->pdfValue($value);
        }

        return '<< ' . implode(' ', $entries) . ' >>';
    }

    /**
     * @param list<self> $filters
     * @return list<string>
     */
    public static function names(array $filters): array
    {
        $names = [];

        foreach ($filters as $filter) {
            $names[] = $filter->name;
        }

        return $names;
    }

    /**
     * @param list<self> $filters
     */
    public static function pdfFilterEntry(array $filters): ?string
    {
        if ($filters === []) {
            return null;
        }

        if (count($filters) === 1) {
            return $filters[0]->name;
        }

        return '[' . implode(' ', self::names($filters)) . ']';
    }

    /**
     * @param list<self> $filters
     */
    public static function pdfDecodeParmsEntry(array $filters): ?string
    {
        $hasDecodeParameters = false;

        foreach ($filters as $filter) {
            if ($filter->hasDecodeParameters()) {
                $hasDecodeParameters = true;

                break;
            }
        }

        if (!$hasDecodeParameters) {
            return null;
        }

        if (count($filters) === 1) {
            return $filters[0]->hasDecodeParameters()
                ? $filters[0]->pdfDecodeParametersContents()
                : 'null';
        }

        return '[' . implode(' ', array_map(
            static fn (self $filter): string => $filter->hasDecodeParameters()
                ? $filter->pdfDecodeParametersContents()
                : 'null',
            $filters,
        )) . ']';
    }

    private function pdfValue(bool | int | float | string $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            if (str_starts_with($value, '/')) {
                return $value;
            }

            return '(' . $this->escapePdfString($value) . ')';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');
    }

    private function escapePdfString(string $value): string
    {
        return preg_replace('/([()\\\\])/', '\\\\$1', $value) ?? $value;
    }
}
