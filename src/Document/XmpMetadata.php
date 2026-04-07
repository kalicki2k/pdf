<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;

final class XmpMetadata extends IndirectObject
{
    public function __construct(int $id, private readonly Document $document)
    {
        parent::__construct($id);
    }

    public function render(): string
    {
        $xml = $this->buildXml();
        $dictionary = new DictionaryType([
            'Type' => new NameType('Metadata'),
            'Subtype' => new NameType('XML'),
            'Length' => strlen($xml),
        ]);

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'stream' . PHP_EOL
            . $xml . PHP_EOL
            . 'endstream' . PHP_EOL
            . 'endobj' . PHP_EOL;
    }

    private function buildXml(): string
    {
        $creationDate = $this->document->getCreationDate()->format('Y-m-d\TH:i:sP');
        $modificationDate = $this->document->getModificationDate()->format('Y-m-d\TH:i:sP');
        $title = $this->renderAltProperty('dc:title', $this->document->getTitle());
        $subject = $this->renderAltProperty('dc:description', $this->document->getSubject());
        $author = $this->renderSeqProperty('dc:creator', $this->document->getAuthor());
        $documentKeywords = $this->document->getKeywords();
        $keywords = $this->renderBagProperty('dc:subject', array_values($documentKeywords));
        $languageValue = $this->document->getLanguage();
        $language = $this->renderBagProperty('dc:language', $languageValue !== null ? [$languageValue] : []);
        $pdfKeywords = $documentKeywords !== []
            ? '    <pdf:Keywords>' . $this->escape(implode(', ', $documentKeywords)) . '</pdf:Keywords>' . PHP_EOL
            : '';
        $pdfAIdentification = $this->renderPdfAIdentification();

        return <<<XML
<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>
<x:xmpmeta xmlns:x="adobe:ns:meta/">
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
  <rdf:Description rdf:about=""
    xmlns:dc="http://purl.org/dc/elements/1.1/"
    xmlns:pdf="http://ns.adobe.com/pdf/1.3/"
    xmlns:xmp="http://ns.adobe.com/xap/1.0/">
    <dc:format>application/pdf</dc:format>
{$title}{$subject}{$author}{$keywords}{$language}    <pdf:Producer>{$this->escape($this->document->getProducer())}</pdf:Producer>
{$pdfKeywords}    <xmp:CreatorTool>{$this->escape($this->document->getCreatorTool())}</xmp:CreatorTool>
    <xmp:CreateDate>{$creationDate}</xmp:CreateDate>
    <xmp:ModifyDate>{$modificationDate}</xmp:ModifyDate>
    <xmp:MetadataDate>{$modificationDate}</xmp:MetadataDate>
  </rdf:Description>
{$pdfAIdentification}</rdf:RDF>
</x:xmpmeta>
<?xpacket end="w"?>
XML;
    }

    private function renderPdfAIdentification(): string
    {
        if (!$this->document->getProfile()->isPdfA()) {
            return '';
        }

        $part = $this->document->getProfile()->pdfaPart();
        $conformance = $this->document->getProfile()->pdfaConformance();

        if ($part === null) {
            return '';
        }

        $revisionXml = $part === 4
            ? '    <pdfaid:rev>2020</pdfaid:rev>' . PHP_EOL
            : '';

        $conformanceXml = $conformance !== null
            ? '    <pdfaid:conformance>' . $this->escape($conformance) . '</pdfaid:conformance>' . PHP_EOL
            : '';

        return <<<XML
  <rdf:Description rdf:about=""
    xmlns:pdfaid="http://www.aiim.org/pdfa/ns/id/">
    <pdfaid:part>{$part}</pdfaid:part>
{$revisionXml}{$conformanceXml}  </rdf:Description>
XML . PHP_EOL;
    }

    private function renderAltProperty(string $name, ?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $escapedValue = $this->escape($value);

        return <<<XML
    <{$name}>
      <rdf:Alt>
        <rdf:li xml:lang="x-default">{$escapedValue}</rdf:li>
      </rdf:Alt>
    </{$name}>
XML . PHP_EOL;
    }

    private function renderSeqProperty(string $name, ?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $escapedValue = $this->escape($value);

        return <<<XML
    <{$name}>
      <rdf:Seq>
        <rdf:li>{$escapedValue}</rdf:li>
      </rdf:Seq>
    </{$name}>
XML . PHP_EOL;
    }

    /**
     * @param list<string> $values
     */
    private function renderBagProperty(string $name, array $values): string
    {
        if ($values === []) {
            return '';
        }

        $items = array_map(
            fn (string $value): string => '        <rdf:li>' . $this->escape($value) . '</rdf:li>',
            $values,
        );
        $itemsXml = implode(PHP_EOL, $items);

        return <<<XML
    <{$name}>
      <rdf:Bag>
{$itemsXml}
      </rdf:Bag>
    </{$name}>
XML . PHP_EOL;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, 'UTF-8');
    }
}
