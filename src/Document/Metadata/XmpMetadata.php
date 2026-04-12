<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Metadata;

use DateTimeInterface;
use Kalle\Pdf\Document\Document;

use function htmlspecialchars;
use function implode;
use function strlen;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;
use const ENT_XML1;

final readonly class XmpMetadata
{
    public function objectContents(Document $document, ?DateTimeInterface $serializedAt = null): string
    {
        $xml = $this->xml($document, $serializedAt);

        return '<< /Type /Metadata /Subtype /XML /Length ' . strlen($xml) . " >>\nstream\n"
            . $xml
            . 'endstream';
    }

    public function xml(Document $document, ?DateTimeInterface $serializedAt = null): string
    {
        return implode("\n", $this->buildXmlLines($document, $serializedAt)) . "\n";
    }

    /**
     * @return list<string>
     */
    private function buildXmlLines(Document $document, ?DateTimeInterface $serializedAt): array
    {
        $timestamp = ($serializedAt ?? new \DateTimeImmutable('now'))->format('Y-m-d\TH:i:sP');
        $lines = [
            '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>',
            '<x:xmpmeta xmlns:x="adobe:ns:meta/">',
            '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">',
            '  <rdf:Description rdf:about=""',
            '    xmlns:dc="http://purl.org/dc/elements/1.1/"',
            '    xmlns:pdf="http://ns.adobe.com/pdf/1.3/"',
            '    xmlns:xmp="http://ns.adobe.com/xap/1.0/">',
            '    <dc:format>application/pdf</dc:format>',
            ...$this->renderAltPropertyLines('dc:title', $document->title),
            ...$this->renderAltPropertyLines('dc:description', $document->subject),
            ...$this->renderSeqPropertyLines('dc:creator', $document->author),
            ...$this->renderBagPropertyLines('dc:language', $document->language !== null ? [$document->language] : []),
        ];

        if ($document->creatorTool !== null) {
            $lines[] = '    <pdf:Producer>' . $this->escape($document->creatorTool) . '</pdf:Producer>';
            $lines[] = '    <xmp:CreatorTool>' . $this->escape($document->creatorTool) . '</xmp:CreatorTool>';
        } elseif ($document->creator !== null) {
            $lines[] = '    <xmp:CreatorTool>' . $this->escape($document->creator) . '</xmp:CreatorTool>';
        }

        $lines[] = '    <xmp:CreateDate>' . $timestamp . '</xmp:CreateDate>';
        $lines[] = '    <xmp:ModifyDate>' . $timestamp . '</xmp:ModifyDate>';
        $lines[] = '    <xmp:MetadataDate>' . $timestamp . '</xmp:MetadataDate>';

        $lines[] = '  </rdf:Description>';

        return [
            ...$lines,
            ...$this->renderPdfAIdentificationLines($document),
            ...$this->renderPdfUaIdentificationLines($document),
            '</rdf:RDF>',
            '</x:xmpmeta>',
            '<?xpacket end="w"?>',
        ];
    }

    /**
     * @return list<string>
     */
    private function renderPdfAIdentificationLines(Document $document): array
    {
        if (!$document->profile->writesPdfAIdentificationMetadata()) {
            return [];
        }

        $part = $document->profile->pdfaPart();

        if ($part === null) {
            return [];
        }

        $lines = [
            '  <rdf:Description rdf:about=""',
            '    xmlns:pdfaid="http://www.aiim.org/pdfa/ns/id/">',
            '    <pdfaid:part>' . $part . '</pdfaid:part>',
        ];

        if ($part === 4) {
            $lines[] = '    <pdfaid:rev>2020</pdfaid:rev>';
        }

        $conformance = $document->profile->pdfaConformance();

        if ($conformance !== null) {
            $lines[] = '    <pdfaid:conformance>' . $this->escape($conformance) . '</pdfaid:conformance>';
        }

        $lines[] = '  </rdf:Description>';

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function renderPdfUaIdentificationLines(Document $document): array
    {
        if (!$document->profile->writesPdfUaIdentificationMetadata()) {
            return [];
        }

        $part = $document->profile->pdfuaPart();

        if ($part === null) {
            return [];
        }

        return [
            '  <rdf:Description rdf:about=""',
            '    xmlns:pdfuaid="http://www.aiim.org/pdfua/ns/id/">',
            '    <pdfuaid:part>' . $part . '</pdfuaid:part>',
            '  </rdf:Description>',
        ];
    }

    /**
     * @return list<string>
     */
    private function renderAltPropertyLines(string $name, ?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        return [
            '    <' . $name . '>',
            '      <rdf:Alt>',
            '        <rdf:li xml:lang="x-default">' . $this->escape($value) . '</rdf:li>',
            '      </rdf:Alt>',
            '    </' . $name . '>',
        ];
    }

    /**
     * @return list<string>
     */
    private function renderSeqPropertyLines(string $name, ?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        return [
            '    <' . $name . '>',
            '      <rdf:Seq>',
            '        <rdf:li>' . $this->escape($value) . '</rdf:li>',
            '      </rdf:Seq>',
            '    </' . $name . '>',
        ];
    }

    /**
     * @param list<string> $values
     * @return list<string>
     */
    private function renderBagPropertyLines(string $name, array $values): array
    {
        if ($values === []) {
            return [];
        }

        $lines = [
            '    <' . $name . '>',
            '      <rdf:Bag>',
        ];

        foreach ($values as $value) {
            $lines[] = '        <rdf:li>' . $this->escape($value) . '</rdf:li>';
        }

        $lines[] = '      </rdf:Bag>';
        $lines[] = '    </' . $name . '>';

        return $lines;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, 'UTF-8');
    }
}
