<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use DateTimeImmutable;
use InvalidArgumentException;
use Kalle\Pdf\Document\Metadata\XmpMetadata;

use function preg_match;
use function sprintf;
use function str_contains;

final class PdfA1MetadataConsistencyValidator
{
    public function __construct(
        private readonly DocumentInfoDictionaryBuilder $infoDictionaryBuilder = new DocumentInfoDictionaryBuilder(),
        private readonly XmpMetadata $xmpMetadata = new XmpMetadata(),
    ) {
    }

    public function assertConsistent(Document $document): void
    {
        if (!$document->profile->isPdfA1()) {
            return;
        }

        $serializedAt = new DateTimeImmutable('2026-04-12T00:00:00+00:00');
        $info = $this->infoDictionaryBuilder->build($document, $serializedAt);
        $xmp = $this->xmpMetadata->streamContents($document, $serializedAt);

        $this->assertInfoXmpConsistency($document, 'Title', $document->title, $info, $xmp, 'dc:title');
        $this->assertInfoXmpConsistency($document, 'Author', $document->author, $info, $xmp, 'dc:creator');
        $this->assertInfoXmpConsistency($document, 'Subject', $document->subject, $info, $xmp, 'dc:description');
        $this->assertInfoXmpConsistency($document, 'Creator', $document->creator, $info, $xmp, 'xmp:CreatorTool');
        $this->assertInfoXmpConsistency($document, 'Producer', $document->creatorTool, $info, $xmp, 'pdf:Producer');

        if ($document->language !== null && !str_contains($xmp, '<rdf:li>' . $document->language . '</rdf:li>')) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s requires consistent Info/XMP metadata for Language.',
                $document->profile->name(),
            ));
        }

        if (!str_contains($xmp, '<pdfaid:part>1</pdfaid:part>')) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s requires pdfaid:part 1 in XMP metadata.',
                $document->profile->name(),
            ));
        }

        $conformance = $document->profile->pdfaConformance();

        if ($conformance !== null && !str_contains($xmp, '<pdfaid:conformance>' . $conformance . '</pdfaid:conformance>')) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s requires consistent Info/XMP metadata for pdfaid:conformance.',
                $document->profile->name(),
            ));
        }

        if (!preg_match('/\/CreationDate\s+\(.+\)\s+\/ModDate\s+\(.+\)/', $info)) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s requires CreationDate and ModDate in the Info dictionary.',
                $document->profile->name(),
            ));
        }

        if (
            !str_contains($xmp, '<xmp:CreateDate>2026-04-12T00:00:00+00:00</xmp:CreateDate>')
            || !str_contains($xmp, '<xmp:ModifyDate>2026-04-12T00:00:00+00:00</xmp:ModifyDate>')
        ) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s requires consistent Info/XMP metadata for creation and modification dates.',
                $document->profile->name(),
            ));
        }
    }

    private function assertInfoXmpConsistency(
        Document $document,
        string $infoKey,
        ?string $value,
        string $info,
        string $xmp,
        string $xmpKey,
    ): void {
        $hasInfo = str_contains($info, '/' . $infoKey . ' ');
        $hasXmp = str_contains($xmp, '<' . $xmpKey . '>');

        if (($value === null || $value === '') && !$hasInfo && !$hasXmp) {
            return;
        }

        if ($hasInfo !== $hasXmp) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s requires consistent Info/XMP metadata for %s.',
                $document->profile->name(),
                $infoKey,
            ));
        }
    }
}
