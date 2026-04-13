<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use const ENT_QUOTES;
use const ENT_XML1;

use function array_key_exists;
use function html_entity_decode;
use function mb_convert_encoding;
use function pack;
use function preg_match;
use function preg_match_all;
use function sprintf;
use function strip_tags;
use function trim;

use DateTimeImmutable;
use Kalle\Pdf\Document\Metadata\XmpMetadata;

final readonly class PdfA1MetadataConsistencyValidator
{
    public function __construct(
        private DocumentInfoDictionaryBuilder $infoDictionaryBuilder = new DocumentInfoDictionaryBuilder(),
        private XmpMetadata $xmpMetadata = new XmpMetadata(),
    ) {
    }

    public function assertConsistent(Document $document, ?DateTimeImmutable $serializedAt = null): void
    {
        if (!$document->profile->isPdfA1()) {
            return;
        }

        $serializedAt ??= new DateTimeImmutable('now');
        $info = $this->infoDictionaryBuilder->build($document, $serializedAt);
        $xmp = $this->xmpMetadata->streamContents($document, $serializedAt);
        $infoValues = $this->extractInfoValues($info);
        $expectedDate = $this->extractInfoValues(
            $this->infoDictionaryBuilder->build(new Document(), $serializedAt),
        );

        $this->assertInfoValueEquals($document, $infoValues, 'Title', $document->title);
        $this->assertInfoValueEquals($document, $infoValues, 'Author', $document->author);
        $this->assertInfoValueEquals($document, $infoValues, 'Subject', $document->subject);
        $this->assertInfoValueEquals($document, $infoValues, 'Keywords', $document->keywords);
        $this->assertInfoValueEquals($document, $infoValues, 'Creator', $document->creator);
        $this->assertInfoValueEquals($document, $infoValues, 'Producer', $document->creatorTool);
        $this->assertXmpScalarValueEquals($document, 'Title', $this->extractFirstAltValue($xmp, 'dc:title'), $document->title);
        $this->assertXmpScalarValueEquals($document, 'Author', $this->extractFirstSeqValue($xmp, 'dc:creator'), $document->author);
        $this->assertXmpScalarValueEquals($document, 'Subject', $this->extractFirstAltValue($xmp, 'dc:description'), $document->subject);
        $this->assertXmpScalarValueEquals($document, 'Keywords', $this->extractFirstTagText($xmp, 'pdf:Keywords'), $document->keywords);
        $this->assertXmpScalarValueEquals($document, 'Creator', $this->extractFirstTagText($xmp, 'xmp:CreatorTool'), $document->creator);
        $this->assertXmpScalarValueEquals($document, 'Producer', $this->extractFirstTagText($xmp, 'pdf:Producer'), $document->creatorTool);
        $this->assertXmpScalarValueEquals($document, 'Language', $this->extractFirstBagValue($xmp, 'dc:language'), $document->language);
        $this->assertXmpScalarValueEquals(
            $document,
            'CreationDate',
            $this->extractFirstTagText($xmp, 'xmp:CreateDate'),
            $serializedAt->format('Y-m-d\TH:i:sP'),
        );
        $this->assertXmpScalarValueEquals(
            $document,
            'ModDate',
            $this->extractFirstTagText($xmp, 'xmp:ModifyDate'),
            $serializedAt->format('Y-m-d\TH:i:sP'),
        );
        $this->assertXmpScalarValueEquals($document, 'pdfaid:part', $this->extractFirstTagText($xmp, 'pdfaid:part'), '1');
        $this->assertInfoValueEquals($document, $infoValues, 'CreationDate', $expectedDate['CreationDate'] ?? null);
        $this->assertInfoValueEquals($document, $infoValues, 'ModDate', $expectedDate['ModDate'] ?? null);

        $conformance = $document->profile->pdfaConformance();

        if ($conformance !== null) {
            $this->assertXmpScalarValueEquals(
                $document,
                'pdfaid:conformance',
                $this->extractFirstTagText($xmp, 'pdfaid:conformance'),
                $conformance,
            );
        }
    }

    /**
     * @return array<string, string>
     */
    private function extractInfoValues(string $info): array
    {
        $values = [];

        if (preg_match_all('/\/([A-Za-z]+)\s+(\((?:\\\\.|[^\\\\)])*\))/', $info, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                $values[$match[1]] = $this->decodePdfLiteralString($match[2]);
            }
        }

        return $values;
    }

    /**
     * @param array<string, string> $values
     */
    private function assertInfoValueEquals(Document $document, array $values, string $label, ?string $expected): void
    {
        $hasValue = array_key_exists($label, $values);

        if (($expected === null || $expected === '') && !$hasValue) {
            return;
        }

        if (!$hasValue || $values[$label] !== $expected) {
            throw new DocumentValidationException(DocumentBuildError::PDFA_METADATA_INCONSISTENT, sprintf(
                'Profile %s requires consistent Info/XMP metadata for %s.',
                $document->profile->name(),
                $label,
            ));
        }
    }

    private function assertXmpScalarValueEquals(
        Document $document,
        string $label,
        ?string $actual,
        ?string $expected,
    ): void {
        if (($expected === null || $expected === '') && $actual === null) {
            return;
        }

        if ($actual !== $expected) {
            throw new DocumentValidationException(DocumentBuildError::PDFA_METADATA_INCONSISTENT, sprintf(
                'Profile %s requires consistent Info/XMP metadata for %s.',
                $document->profile->name(),
                $label,
            ));
        }
    }

    private function extractFirstTagText(string $xmp, string $tagName): ?string
    {
        if (preg_match('/<' . preg_quote($tagName, '/') . '>(.*?)<\\/' . preg_quote($tagName, '/') . '>/s', $xmp, $matches) !== 1) {
            return null;
        }

        return html_entity_decode(trim(strip_tags($matches[1])), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function extractFirstAltValue(string $xmp, string $tagName): ?string
    {
        return $this->extractContainerValue($xmp, $tagName);
    }

    private function extractFirstSeqValue(string $xmp, string $tagName): ?string
    {
        return $this->extractContainerValue($xmp, $tagName);
    }

    private function extractFirstBagValue(string $xmp, string $tagName): ?string
    {
        return $this->extractContainerValue($xmp, $tagName);
    }

    private function extractContainerValue(string $xmp, string $tagName): ?string
    {
        if (preg_match('/<' . preg_quote($tagName, '/') . '>.*?<rdf:li(?:\s+xml:lang="[^"]*")?>(.*?)<\\/rdf:li>.*?<\\/' . preg_quote($tagName, '/') . '>/s', $xmp, $matches) !== 1) {
            return null;
        }

        return html_entity_decode(trim(strip_tags($matches[1])), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function decodePdfLiteralString(string $literal): string
    {
        $value = trim($literal, '()');
        $decodedBytes = '';
        $length = strlen($value);

        for ($index = 0; $index < $length; $index++) {
            $character = $value[$index];

            if ($character !== '\\') {
                $decodedBytes .= $character;

                continue;
            }

            $next = $value[$index + 1] ?? '';

            if ($next === '' || $next === '\\' || $next === '(' || $next === ')') {
                $decodedBytes .= $next;
                $index++;

                continue;
            }

            if (preg_match('/^[0-7]{1,3}/', substr($value, $index + 1), $octalMatch) === 1) {
                $decodedBytes .= pack('C', (int) octdec($octalMatch[0]) & 0xFF);
                $index += strlen($octalMatch[0]);

                continue;
            }

            $decodedBytes .= $next;
            $index++;
        }

        if (str_starts_with($decodedBytes, "\xFE\xFF")) {
            return mb_convert_encoding(substr($decodedBytes, 2), 'UTF-8', 'UTF-16BE');
        }

        return mb_convert_encoding($decodedBytes, 'UTF-8', 'ISO-8859-1');
    }
}
