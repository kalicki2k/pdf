<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;
use Kalle\Pdf\Font\CidFont;
use Kalle\Pdf\Font\CidToGidMap;
use Kalle\Pdf\Font\EncodingDictionary;
use Kalle\Pdf\Font\FontDescriptor;
use Kalle\Pdf\Font\FontFileStream;
use Kalle\Pdf\Font\FontRegistry;
use Kalle\Pdf\Font\OpenTypeFontParser;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Font\ToUnicodeCMap;
use Kalle\Pdf\Font\UnicodeFont;
use Kalle\Pdf\Font\UnicodeGlyphMap;
use RuntimeException;

/**
 * @internal Resolves document font registrations and creates the corresponding font objects.
 */
final readonly class DocumentFontFactory
{
    public function __construct(private Document $document)
    {
    }

    /**
     * @return array{
     *     baseFont: string,
     *     subtype: string,
     *     encoding: string,
     *     unicode: bool,
     *     fontFilePath: ?string
     * }
     */
    public function resolveRegistrationOptions(
        string $fontName,
        string $subtype,
        ?string $encoding,
        bool $unicode,
        ?string $fontFilePath,
    ): array {
        if (!FontRegistry::has($fontName, $this->document->getFontConfig())) {
            return [
                'baseFont' => $fontName,
                'subtype' => $subtype,
                'encoding' => $encoding ?? $this->resolveDefaultStandardFontEncoding(),
                'unicode' => $unicode,
                'fontFilePath' => $fontFilePath,
            ];
        }

        $preset = FontRegistry::get($fontName, $this->document->getFontConfig());

        return [
            'baseFont' => $preset->baseFont,
            'subtype' => $preset->subtype,
            'encoding' => $encoding ?? $preset->encoding,
            'unicode' => $preset->unicode,
            'fontFilePath' => $preset->path,
        ];
    }

    /**
     * @param array{
     *     baseFont: string,
     *     subtype: string,
     *     encoding: string,
     *     unicode: bool,
     *     fontFilePath: ?string
     * } $options
     */
    public function createFont(array $options): StandardFont | UnicodeFont
    {
        if ($options['unicode']) {
            return $this->createUnicodeFont($options['baseFont'], $options['subtype'], $options['fontFilePath']);
        }

        return $this->createStandardFont(
            $options['baseFont'],
            $options['subtype'],
            $options['encoding'],
            $options['fontFilePath'],
        );
    }

    private function resolveDefaultStandardFontEncoding(): string
    {
        if (!$this->document->getProfile()->supportsWinAnsiEncoding()) {
            return 'StandardEncoding';
        }

        return 'WinAnsiEncoding';
    }

    private function createUnicodeFont(string $baseFont, string $subtype, ?string $fontFilePath): UnicodeFont
    {
        $glyphMap = new UnicodeGlyphMap();
        $embeddedFontResources = $this->createEmbeddedUnicodeFontResources($baseFont, $subtype, $fontFilePath);

        if ($embeddedFontResources !== null) {
            $fontDescriptor = $embeddedFontResources['fontDescriptor'];
            $fontParser = $embeddedFontResources['fontParser'];
            $subtype = $embeddedFontResources['subtype'];
        } else {
            $fontDescriptor = null;
            $fontParser = null;
        }

        $cidToGidMap = $fontParser !== null
            ? new CidToGidMap($this->document->getUniqObjectId(), $glyphMap, $fontParser)
            : null;
        $descendantFont = new CidFont(
            $this->document->getUniqObjectId(),
            $baseFont,
            $subtype,
            fontDescriptor: $fontDescriptor,
            cidToGidMap: $cidToGidMap,
            defaultWidth: 1000,
            widths: [],
        );
        $toUnicode = new ToUnicodeCMap($this->document->getUniqObjectId(), $glyphMap);

        return new UnicodeFont(
            $this->document->getUniqObjectId(),
            $descendantFont,
            $toUnicode,
            $glyphMap,
        );
    }

    private function createStandardFont(
        string $baseFont,
        string $subtype,
        string $encoding,
        ?string $fontFilePath,
    ): StandardFont {
        $encodingDictionary = null;
        $byteMap = [];

        if ($fontFilePath === null && $encoding === 'StandardEncoding' && $this->supportsWesternStandardEncodingDifferences($baseFont)) {
            $encodingDictionary = new EncodingDictionary(
                $this->document->getUniqObjectId(),
                'StandardEncoding',
                $this->westernStandardEncodingDifferences(),
            );
            $byteMap = $this->westernStandardEncodingByteMap();
        }

        $fontId = $this->document->getUniqObjectId();

        return new StandardFont(
            $fontId,
            $baseFont,
            $subtype,
            $encoding,
            $this->document->getVersion(),
            $this->createOptionalFontParser($fontFilePath),
            $encodingDictionary,
            $byteMap,
        );
    }

    private function createOptionalFontParser(?string $fontFilePath): ?OpenTypeFontParser
    {
        if ($fontFilePath === null) {
            return null;
        }

        return $this->loadFontParser($fontFilePath);
    }

    /**
     * @return array{
     *     fontDescriptor: FontDescriptor,
     *     fontParser: OpenTypeFontParser,
     *     subtype: string
     * }|null
     */
    private function createEmbeddedUnicodeFontResources(
        string $baseFont,
        string $subtype,
        ?string $fontFilePath,
    ): ?array {
        if ($fontFilePath === null) {
            return null;
        }

        $fontFile = FontFileStream::fromPath($this->document->getUniqObjectId(), $fontFilePath);
        $fontParser = $fontFile->parser();

        return [
            'fontDescriptor' => new FontDescriptor($this->document->getUniqObjectId(), $baseFont, $fontFile),
            'fontParser' => $fontParser,
            'subtype' => $fontParser->hasCffOutlines() ? 'CIDFontType0' : $subtype,
        ];
    }

    private function loadFontParser(string $fontFilePath): OpenTypeFontParser
    {
        try {
            return new OpenTypeFontParser(BinaryData::fromFile($fontFilePath)->contents());
        } catch (RuntimeException $exception) {
            throw new InvalidArgumentException("Unable to read font file '$fontFilePath'.");
        }
    }

    private function supportsWesternStandardEncodingDifferences(string $baseFont): bool
    {
        return !in_array($baseFont, ['Symbol', 'ZapfDingbats'], true);
    }

    /**
     * @return array<int, string>
     */
    private function westernStandardEncodingDifferences(): array
    {
        return [
            128 => 'Adieresis',
            129 => 'Aring',
            130 => 'Ccedilla',
            131 => 'Eacute',
            132 => 'Ntilde',
            133 => 'Odieresis',
            134 => 'Udieresis',
            135 => 'aacute',
            136 => 'agrave',
            137 => 'acircumflex',
            138 => 'adieresis',
            139 => 'atilde',
            140 => 'aring',
            141 => 'ccedilla',
            142 => 'eacute',
            143 => 'egrave',
            144 => 'ecircumflex',
            145 => 'edieresis',
            146 => 'iacute',
            147 => 'igrave',
            148 => 'icircumflex',
            149 => 'idieresis',
            150 => 'ntilde',
            151 => 'oacute',
            152 => 'ograve',
            153 => 'ocircumflex',
            154 => 'odieresis',
            155 => 'otilde',
            156 => 'uacute',
            157 => 'ugrave',
            158 => 'ucircumflex',
            159 => 'udieresis',
            160 => 'dagger',
            161 => 'degree',
            162 => 'cent',
            163 => 'sterling',
            164 => 'section',
            165 => 'bullet',
            166 => 'paragraph',
            167 => 'germandbls',
            168 => 'registered',
            169 => 'copyright',
            170 => 'trademark',
            171 => 'acute',
            172 => 'dieresis',
            174 => 'AE',
            175 => 'Oslash',
            177 => 'plusminus',
            180 => 'yen',
            181 => 'mu',
            187 => 'ordfeminine',
            188 => 'ordmasculine',
            190 => 'ae',
            191 => 'oslash',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function westernStandardEncodingByteMap(): array
    {
        return [
            'Ä' => "\x80",
            'Å' => "\x81",
            'Ç' => "\x82",
            'É' => "\x83",
            'Ñ' => "\x84",
            'Ö' => "\x85",
            'Ü' => "\x86",
            'á' => "\x87",
            'à' => "\x88",
            'â' => "\x89",
            'ä' => "\x8A",
            'ã' => "\x8B",
            'å' => "\x8C",
            'ç' => "\x8D",
            'é' => "\x8E",
            'è' => "\x8F",
            'ê' => "\x90",
            'ë' => "\x91",
            'í' => "\x92",
            'ì' => "\x93",
            'î' => "\x94",
            'ï' => "\x95",
            'ñ' => "\x96",
            'ó' => "\x97",
            'ò' => "\x98",
            'ô' => "\x99",
            'ö' => "\x9A",
            'õ' => "\x9B",
            'ú' => "\x9C",
            'ù' => "\x9D",
            'û' => "\x9E",
            'ü' => "\x9F",
            '†' => "\xA0",
            '°' => "\xA1",
            '¢' => "\xA2",
            '£' => "\xA3",
            '§' => "\xA4",
            '•' => "\xA5",
            '¶' => "\xA6",
            'ß' => "\xA7",
            '®' => "\xA8",
            '©' => "\xA9",
            '™' => "\xAA",
            '´' => "\xAB",
            '¨' => "\xAC",
            'Æ' => "\xAE",
            'Ø' => "\xAF",
            '±' => "\xB1",
            '¥' => "\xB4",
            'µ' => "\xB5",
            'ª' => "\xBB",
            'º' => "\xBC",
            'æ' => "\xBE",
            'ø' => "\xBF",
        ];
    }
}
