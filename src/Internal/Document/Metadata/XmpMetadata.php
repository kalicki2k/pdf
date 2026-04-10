<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Document\Metadata;

use Kalle\Pdf\Document;
use Kalle\Pdf\Internal\Object\StreamIndirectObject;
use Kalle\Pdf\Internal\PdfType\DictionaryType;
use Kalle\Pdf\Internal\PdfType\NameType;
use Kalle\Pdf\Internal\Render\PdfOutput;

class XmpMetadata extends StreamIndirectObject
{
    public function __construct(int $id, private readonly Document $document)
    {
        parent::__construct($id);
    }

    protected function streamDictionary(int $length): DictionaryType
    {
        return new DictionaryType([
            'Type' => new NameType('Metadata'),
            'Subtype' => new NameType('XML'),
            'Length' => $length,
        ]);
    }

    protected function writeStreamContents(PdfOutput $output): void
    {
        $this->writeLines($output, $this->buildXmlLines());
    }

    /**
     * @return list<string>
     */
    private function buildXmlLines(): array
    {
        $creationDate = $this->document->getCreationDate()->format('Y-m-d\TH:i:sP');
        $modificationDate = $this->document->getModificationDate()->format('Y-m-d\TH:i:sP');
        $creatorTool = $this->resolveCreatorTool();
        $documentKeywords = $this->document->getKeywords();
        $languageValue = $this->document->getLanguage();
        $lines = [
            '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>',
            '<x:xmpmeta xmlns:x="adobe:ns:meta/">',
            '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">',
            '  <rdf:Description rdf:about=""',
            '    xmlns:dc="http://purl.org/dc/elements/1.1/"',
            '    xmlns:pdf="http://ns.adobe.com/pdf/1.3/"',
            '    xmlns:xmp="http://ns.adobe.com/xap/1.0/">',
            '    <dc:format>application/pdf</dc:format>',
            ...$this->renderAltPropertyLines('dc:title', $this->document->getTitle()),
            ...$this->renderAltPropertyLines('dc:description', $this->document->getSubject()),
            ...$this->renderSeqPropertyLines('dc:creator', $this->document->getAuthor()),
            ...$this->renderBagPropertyLines('dc:subject', array_values($documentKeywords)),
            ...$this->renderBagPropertyLines('dc:language', $languageValue !== null ? [$languageValue] : []),
            '    <pdf:Producer>' . $this->escape($this->document->getProducer()) . '</pdf:Producer>',
        ];

        if ($documentKeywords !== []) {
            $lines[] = '    <pdf:Keywords>' . $this->escape(implode(', ', $documentKeywords)) . '</pdf:Keywords>';
        }

        $lines[] = '    <xmp:CreatorTool>' . $this->escape($creatorTool) . '</xmp:CreatorTool>';
        $lines[] = '    <xmp:CreateDate>' . $creationDate . '</xmp:CreateDate>';
        $lines[] = '    <xmp:ModifyDate>' . $modificationDate . '</xmp:ModifyDate>';
        $lines[] = '    <xmp:MetadataDate>' . $modificationDate . '</xmp:MetadataDate>';
        $lines[] = '  </rdf:Description>';
        $lines = [
            ...$lines,
            ...$this->renderPdfAIdentificationLines(),
            ...$this->renderPdfUaIdentificationLines(),
            '</rdf:RDF>',
            '</x:xmpmeta>',
            '<?xpacket end="w"?>',
        ];

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function renderPdfAIdentificationLines(): array
    {
        if (!$this->document->getProfile()->writesPdfAIdentificationMetadata()) {
            return [];
        }

        $part = $this->document->getProfile()->pdfaPart();
        $conformance = $this->document->getProfile()->pdfaConformance();

        if ($part === null) {
            return [];
        }

        $lines = [
            '  <rdf:Description rdf:about=""',
            '    xmlns:pdfaid="http://www.aiim.org/pdfa/ns/id/">',
            "    <pdfaid:part>{$part}</pdfaid:part>",
        ];

        if ($part === 4) {
            $lines[] = '    <pdfaid:rev>2020</pdfaid:rev>';
        }

        if ($conformance !== null) {
            $lines[] = '    <pdfaid:conformance>' . $this->escape($conformance) . '</pdfaid:conformance>';
        }

        $lines[] = '  </rdf:Description>';

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function renderPdfUaIdentificationLines(): array
    {
        if (!$this->document->getProfile()->writesPdfUaIdentificationMetadata()) {
            return [];
        }

        $part = $this->document->getProfile()->pdfuaPart();

        if ($part === null) {
            return [];
        }

        return [
            '  <rdf:Description rdf:about=""',
            '    xmlns:pdfuaid="http://www.aiim.org/pdfua/ns/id/">',
            "    <pdfuaid:part>{$part}</pdfuaid:part>",
            '  </rdf:Description>',
        ];
    }

    private function resolveCreatorTool(): string
    {
        if ($this->document->getProfile()->pdfaPart() === 1) {
            return $this->document->getCreator();
        }

        return $this->document->getCreatorTool();
    }

    /**
     * @return list<string>
     */
    private function renderAltPropertyLines(string $name, ?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $escapedValue = $this->escape($value);

        return [
            "    <{$name}>",
            '      <rdf:Alt>',
            '        <rdf:li xml:lang="x-default">' . $escapedValue . '</rdf:li>',
            '      </rdf:Alt>',
            "    </{$name}>",
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

        $escapedValue = $this->escape($value);

        return [
            "    <{$name}>",
            '      <rdf:Seq>',
            '        <rdf:li>' . $escapedValue . '</rdf:li>',
            '      </rdf:Seq>',
            "    </{$name}>",
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

        $items = array_map(
            fn (string $value): string => '        <rdf:li>' . $this->escape($value) . '</rdf:li>',
            $values,
        );

        return [
            "    <{$name}>",
            '      <rdf:Bag>',
            ...$items,
            '      </rdf:Bag>',
            "    </{$name}>",
        ];
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, 'UTF-8');
    }
}
