<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;
use Kalle\Pdf\Image\ImageSource;
use Kalle\Pdf\Page\AnnotationAppearanceRenderContext;
use Kalle\Pdf\Page\AppearanceStreamAnnotation;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Page\PageAnnotation;
use Kalle\Pdf\Page\PageAnnotationRenderContext;
use Kalle\Pdf\Page\PageFont;

use function in_array;
use function count;
use function preg_match;
use function sprintf;
use function strlen;
use function substr;

/**
 * Guards raw low-level injection paths for PDF/A profiles.
 *
 * The generator can validate its own high-level builders structurally, but raw
 * page content streams, annotation dictionaries and image dictionary fragments
 * can otherwise bypass those checks. For PDF/A we therefore allow only what is
 * safely understood and reject opaque low-level extensions.
 */
final class PdfALowLevelPolicyValidator
{
    /**
     * @var list<string>
     */
    private const ALLOWED_MARKED_CONTENT_TAGS = [
        '/Artifact',
        '/BlockQuote',
        '/Caption',
        '/Code',
        '/Em',
        '/Figure',
        '/H1',
        '/H2',
        '/H3',
        '/H4',
        '/H5',
        '/H6',
        '/LBody',
        '/Lbl',
        '/Link',
        '/Note',
        '/P',
        '/Quote',
        '/Reference',
        '/Span',
        '/Strong',
        '/TD',
        '/TH',
        '/Title',
    ];

    /**
     * @var list<string>
     */
    private const FORBIDDEN_ANNOTATION_KEYS = [
        'AA',
        'JS',
        'JavaScript',
        'Launch',
        'EmbeddedFile',
        'Filespec',
        'RichMedia',
        'Movie',
        'Sound',
        'Rendition',
        'SetOCGState',
        'Hide',
        'Named',
        'ResetForm',
        'ImportData',
        'GoToE',
        'GoToR',
        'Thread',
        'Trans',
    ];

    /**
     * @var list<string>
     */
    private const FORBIDDEN_APPEARANCE_DICTIONARY_KEYS = [
        'Group',
        'SMask',
        'OC',
        'OCG',
        'OCMD',
        'BM',
        'TR',
        'TR2',
        'OPM',
        'CA',
        'ca',
    ];

    /**
     * @var list<string>
     */
    private const FORBIDDEN_CONTENT_TOKENS = [
        'BI',
        'ID',
        'EI',
        'BX',
        'EX',
        'MP',
        'DP',
        'sh',
        'gs',
        'CS',
        'cs',
        'SC',
        'SCN',
        'sc',
        'scn',
        'ri',
        'd0',
        'd1',
    ];

    /**
     * @var list<string>
     */
    private const ALLOWED_PDF_A1_CONTENT_OPERATORS = [
        'q',
        'Q',
        'cm',
        'w',
        'J',
        'j',
        'M',
        'd',
        'm',
        'l',
        'c',
        'v',
        'y',
        'h',
        're',
        'S',
        's',
        'f',
        'F',
        'f*',
        'B',
        'B*',
        'b',
        'b*',
        'n',
        'W',
        'W*',
        'BT',
        'ET',
        'Tc',
        'Tw',
        'Tz',
        'TL',
        'Tf',
        'Tr',
        'Ts',
        'Td',
        'TD',
        'Tm',
        'T*',
        'Tj',
        'TJ',
        "'",
        '"',
        'Do',
        'g',
        'G',
        'rg',
        'RG',
        'k',
        'K',
        'BMC',
        'BDC',
        'EMC',
        'true',
        'false',
        'null',
    ];

    public function assertDocumentLowLevelSafety(Document $document): void
    {
        if (!$document->profile->isPdfA()) {
            return;
        }

        $pageObjectIdsByPageNumber = [];

        foreach ($document->pages as $pageIndex => $page) {
            $pageObjectIdsByPageNumber[$pageIndex + 1] = $pageIndex + 1;
        }

        foreach ($document->pages as $pageIndex => $page) {
            $this->assertPageLowLevelSafety($document, $page, $pageIndex, $pageObjectIdsByPageNumber);
        }
    }

    /**
     * @param array<int, int> $pageObjectIdsByPageNumber
     */
    private function assertPageLowLevelSafety(
        Document $document,
        Page $page,
        int $pageIndex,
        array $pageObjectIdsByPageNumber,
    ): void {
        $this->assertPdfAContentStreamSafe(
            $document,
            $page->contents,
            sprintf('page content stream on page %d', $pageIndex + 1),
        );
        $this->assertPageContentStreamSemantics(
            $document,
            $page,
            $pageIndex,
            sprintf('page content stream on page %d', $pageIndex + 1),
        );

        $imageResourceIndex = 0;

        foreach ($page->imageResources as $imageSource) {
            $imageResourceIndex++;
            $this->assertImageResourceLowLevelSafety($document, $imageSource, $pageIndex, $imageResourceIndex);
        }

        $annotationRenderContext = new PageAnnotationRenderContext(
            pageObjectId: $pageIndex + 1,
            printable: true,
            pageObjectIdsByPageNumber: $pageObjectIdsByPageNumber,
            structParentId: 1,
            appearanceObjectId: 1,
        );

        $appearanceRenderContext = new AnnotationAppearanceRenderContext(
            array_fill_keys(array_keys($page->fontResources), 1),
        );

        foreach ($page->annotations as $annotationIndex => $annotation) {
            $this->assertAnnotationLowLevelSafety(
                $document,
                $annotation,
                $pageIndex,
                $annotationIndex + 1,
                $annotationRenderContext,
                $appearanceRenderContext,
            );
        }
    }

    private function assertImageResourceLowLevelSafety(
        Document $document,
        ImageSource $imageSource,
        int $pageIndex,
        int $imageResourceIndex,
    ): void {
        if ($imageSource->additionalDictionaryEntries === []) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'Profile %s does not allow additional low-level image dictionary entries for image resource %d on page %d because they cannot be validated safely for PDF/A.',
            $document->profile->name(),
            $imageResourceIndex,
            $pageIndex + 1,
        ));
    }

    private function assertAnnotationLowLevelSafety(
        Document $document,
        PageAnnotation $annotation,
        int $pageIndex,
        int $annotationIndex,
        PageAnnotationRenderContext $annotationRenderContext,
        AnnotationAppearanceRenderContext $appearanceRenderContext,
    ): void {
        $dictionaryContents = $annotation->pdfObjectContents($annotationRenderContext);

        $this->assertForbiddenDictionaryKeysAbsent(
            $document,
            $dictionaryContents,
            self::FORBIDDEN_ANNOTATION_KEYS,
            sprintf('annotation %d on page %d', $annotationIndex, $pageIndex + 1),
        );

        if (!$annotation instanceof AppearanceStreamAnnotation) {
            return;
        }

        $appearanceDictionaryContents = $annotation->appearanceStreamDictionaryContents($appearanceRenderContext);

        $this->assertForbiddenDictionaryKeysAbsent(
            $document,
            $appearanceDictionaryContents,
            self::FORBIDDEN_APPEARANCE_DICTIONARY_KEYS,
            sprintf('annotation appearance dictionary %d on page %d', $annotationIndex, $pageIndex + 1),
        );

        $this->assertPdfAContentStreamSafe(
            $document,
            $annotation->appearanceStreamContents($appearanceRenderContext),
            sprintf('annotation appearance stream %d on page %d', $annotationIndex, $pageIndex + 1),
        );
    }

    private function assertForbiddenDictionaryKeysAbsent(
        Document $document,
        string $dictionaryContents,
        array $forbiddenKeys,
        string $context,
    ): void {
        foreach ($forbiddenKeys as $forbiddenKey) {
            if (preg_match('/\/' . preg_quote($forbiddenKey, '/') . '(?:\b|(?=\s|\/|<|\[|\())/', $dictionaryContents) !== 1) {
                continue;
            }

            throw new InvalidArgumentException(sprintf(
                'Profile %s does not allow low-level key /%s in %s.',
                $document->profile->name(),
                $forbiddenKey,
                $context,
            ));
        }
    }

    private function assertPdfAContentStreamSafe(
        Document $document,
        string $contents,
        string $context,
    ): void {
        if ($contents === '') {
            return;
        }

        $previousTokenWasNameDelimiter = false;

        foreach ($this->tokenizePdfContentStream($contents) as $token) {
            if ($token === '/') {
                $previousTokenWasNameDelimiter = true;

                continue;
            }

            if ($previousTokenWasNameDelimiter) {
                $previousTokenWasNameDelimiter = false;

                continue;
            }

            if ($this->isDelimiterToken($token) || $this->isOperandToken($token)) {
                continue;
            }

            if (in_array($token, self::FORBIDDEN_CONTENT_TOKENS, true)) {
                throw new InvalidArgumentException(sprintf(
                    'Profile %s does not allow low-level PDF operator "%s" in %s.',
                    $document->profile->name(),
                    $token,
                    $context,
                ));
            }

            if (
                $document->profile->isPdfA1()
                && !in_array($token, self::ALLOWED_PDF_A1_CONTENT_OPERATORS, true)
            ) {
                throw new InvalidArgumentException(sprintf(
                    'Profile %s does not allow unvalidated low-level PDF operator "%s" in %s.',
                    $document->profile->name(),
                    $token,
                    $context,
                ));
            }
        }
    }

    private function assertPageContentStreamSemantics(
        Document $document,
        Page $page,
        int $pageIndex,
        string $context,
    ): void {
        if ($page->contents === '') {
            return;
        }

        $inTextObject = false;
        $currentTextFontAlias = null;
        $previousTokenWasNameDelimiter = false;
        $operands = [];

        foreach ($this->tokenizePdfContentStream($page->contents) as $token) {
            if ($token === '/') {
                $previousTokenWasNameDelimiter = true;

                continue;
            }

            if ($previousTokenWasNameDelimiter) {
                $operands[] = '/' . $token;
                $previousTokenWasNameDelimiter = false;

                continue;
            }

            if ($this->isSemanticOperandToken($token)) {
                $operands[] = $token;

                continue;
            }

            match ($token) {
                'BT' => $this->assertBeginTextObject($document, $context, $inTextObject, $currentTextFontAlias),
                'ET' => $this->assertEndTextObject($document, $context, $inTextObject, $currentTextFontAlias),
                'Tf' => $currentTextFontAlias = $this->assertTextFontOperand(
                    $document,
                    $page,
                    $pageIndex,
                    $context,
                    $inTextObject,
                    $operands,
                ),
                'Tj', 'TJ', "'", '"' => $this->assertTextShowingOperatorAllowed(
                    $document,
                    $context,
                    $token,
                    $inTextObject,
                    $currentTextFontAlias,
                ),
                'Do' => $this->assertDoOperandResolvable($document, $page, $pageIndex, $context, $operands),
                'BMC' => $this->assertBmcOperandsAllowed($document, $context, $operands),
                'BDC' => $this->assertBdcOperandsAllowed($document, $context, $operands),
                default => null,
            };

            $operands = [];
        }

        if ($inTextObject) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s does not allow unterminated text objects in %s.',
                $document->profile->name(),
                $context,
            ));
        }
    }

    /**
     * @return list<string>
     */
    private function tokenizePdfContentStream(string $contents): array
    {
        $tokens = [];
        $length = strlen($contents);
        $index = 0;

        while ($index < $length) {
            $character = $contents[$index];

            if ($this->isWhitespace($character)) {
                $index++;

                continue;
            }

            if ($character === '%') {
                $index = $this->skipComment($contents, $index + 1);

                continue;
            }

            if ($character === '(') {
                $index = $this->skipLiteralString($contents, $index + 1);

                continue;
            }

            if ($character === '<') {
                if (($contents[$index + 1] ?? '') === '<') {
                    $tokens[] = '<<';
                    $index += 2;

                    continue;
                }

                $index = $this->skipHexString($contents, $index + 1);

                continue;
            }

            if ($character === '>') {
                $tokens[] = ($contents[$index + 1] ?? '') === '>' ? '>>' : '>';
                $index += ($contents[$index + 1] ?? '') === '>' ? 2 : 1;

                continue;
            }

            if ($this->isSingleCharacterDelimiter($character)) {
                $tokens[] = $character;
                $index++;

                continue;
            }

            $start = $index;

            while (
                $index < $length
                && !$this->isWhitespace($contents[$index])
                && !$this->isDelimiter($contents[$index])
            ) {
                $index++;
            }

            $tokens[] = substr($contents, $start, $index - $start);
        }

        return $tokens;
    }

    private function skipComment(string $contents, int $index): int
    {
        $length = strlen($contents);

        while ($index < $length && $contents[$index] !== "\n" && $contents[$index] !== "\r") {
            $index++;
        }

        return $index;
    }

    private function skipLiteralString(string $contents, int $index): int
    {
        $length = strlen($contents);
        $depth = 1;

        while ($index < $length && $depth > 0) {
            $character = $contents[$index];

            if ($character === '\\') {
                $index += 2;

                continue;
            }

            if ($character === '(') {
                $depth++;
            } elseif ($character === ')') {
                $depth--;
            }

            $index++;
        }

        return $index;
    }

    private function skipHexString(string $contents, int $index): int
    {
        $length = strlen($contents);

        while ($index < $length && $contents[$index] !== '>') {
            $index++;
        }

        return $index < $length ? $index + 1 : $length;
    }

    private function isDelimiterToken(string $token): bool
    {
        return $token === '['
            || $token === ']'
            || $token === '<<'
            || $token === '>>'
            || $token === '<'
            || $token === '>'
            || $token === '{'
            || $token === '}';
    }

    private function isSemanticOperandToken(string $token): bool
    {
        return $this->isDelimiterToken($token)
            || $this->isOperandToken($token)
            || $token === 'true'
            || $token === 'false'
            || $token === 'null';
    }

    private function isOperandToken(string $token): bool
    {
        return preg_match('/^[+-]?(?:\d+(?:\.\d+)?|\.\d+)$/', $token) === 1;
    }

    private function assertBeginTextObject(
        Document $document,
        string $context,
        bool &$inTextObject,
        ?string &$currentTextFontAlias,
    ): void {
        if ($inTextObject) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s does not allow nested text objects in %s.',
                $document->profile->name(),
                $context,
            ));
        }

        $inTextObject = true;
        $currentTextFontAlias = null;
    }

    private function assertEndTextObject(
        Document $document,
        string $context,
        bool &$inTextObject,
        ?string &$currentTextFontAlias,
    ): void {
        if (!$inTextObject) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s does not allow ET outside a text object in %s.',
                $document->profile->name(),
                $context,
            ));
        }

        $inTextObject = false;
        $currentTextFontAlias = null;
    }

    /**
     * @param list<string> $operands
     */
    private function assertTextFontOperand(
        Document $document,
        Page $page,
        int $pageIndex,
        string $context,
        bool $inTextObject,
        array $operands,
    ): string {
        if (!$inTextObject) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s does not allow Tf outside a text object in %s.',
                $document->profile->name(),
                $context,
            ));
        }

        $fontOperand = count($operands) >= 2 ? $operands[count($operands) - 2] : null;

        if ($fontOperand === null || !str_starts_with($fontOperand, '/')) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s requires a resolvable font resource before Tf in %s.',
                $document->profile->name(),
                $context,
            ));
        }

        $fontAlias = substr($fontOperand, 1);
        $pageFont = $page->fontResources[$fontAlias] ?? null;

        if (!$pageFont instanceof PageFont) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s does not allow text operators in %s to reference missing font resource "%s" on page %d.',
                $document->profile->name(),
                $context,
                $fontAlias,
                $pageIndex + 1,
            ));
        }

        $this->assertPageFontAllowed($document, $pageFont, $pageIndex, $fontAlias, $context);

        return $fontAlias;
    }

    private function assertTextShowingOperatorAllowed(
        Document $document,
        string $context,
        string $operator,
        bool $inTextObject,
        ?string $currentTextFontAlias,
    ): void {
        if (!$inTextObject) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s does not allow text-showing operator "%s" outside a text object in %s.',
                $document->profile->name(),
                $operator,
                $context,
            ));
        }

        if ($currentTextFontAlias !== null) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'Profile %s requires a valid Tf font selection before text-showing operator "%s" in %s.',
            $document->profile->name(),
            $operator,
            $context,
        ));
    }

    /**
     * @param list<string> $operands
     */
    private function assertDoOperandResolvable(
        Document $document,
        Page $page,
        int $pageIndex,
        string $context,
        array $operands,
    ): void {
        $xObjectOperand = $operands !== [] ? $operands[count($operands) - 1] : null;

        if ($xObjectOperand === null || !str_starts_with($xObjectOperand, '/')) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s requires a resolvable XObject resource before Do in %s.',
                $document->profile->name(),
                $context,
            ));
        }

        $xObjectAlias = substr($xObjectOperand, 1);

        if (isset($page->imageResources[$xObjectAlias])) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'Profile %s does not allow Do in %s to reference missing XObject resource "%s" on page %d.',
            $document->profile->name(),
            $context,
            $xObjectAlias,
            $pageIndex + 1,
        ));
    }

    /**
     * @param list<string> $operands
     */
    private function assertBmcOperandsAllowed(Document $document, string $context, array $operands): void
    {
        if ($operands === ['/Artifact']) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'Profile %s does not allow unvalidated marked-content operator BMC in %s.',
            $document->profile->name(),
            $context,
        ));
    }

    /**
     * @param list<string> $operands
     */
    private function assertBdcOperandsAllowed(Document $document, string $context, array $operands): void
    {
        if (
            count($operands) === 5
            && in_array($operands[0], self::ALLOWED_MARKED_CONTENT_TAGS, true)
            && $operands[1] === '<<'
            && $operands[2] === '/MCID'
            && $this->isOperandToken($operands[3] ?? '')
            && $operands[4] === '>>'
        ) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'Profile %s does not allow unvalidated marked-content property usage in %s.',
            $document->profile->name(),
            $context,
        ));
    }

    private function assertPageFontAllowed(
        Document $document,
        PageFont $pageFont,
        int $pageIndex,
        string $fontAlias,
        string $context,
    ): void {
        if (!$pageFont->isEmbedded()) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s does not allow text operators in %s to reference non-embedded font resource "%s" on page %d.',
                $document->profile->name(),
                $context,
                $fontAlias,
                $pageIndex + 1,
            ));
        }

        if (!$document->profile->requiresExtractableEmbeddedUnicodeFonts() || $pageFont->usesUnicodeCids()) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'Profile %s does not allow text operators in %s to reference simple embedded font resource "%s" on page %d because the active profile requires extractable Unicode fonts.',
            $document->profile->name(),
            $context,
            $fontAlias,
            $pageIndex + 1,
        ));
    }

    private function isWhitespace(string $character): bool
    {
        return $character === ' '
            || $character === "\n"
            || $character === "\r"
            || $character === "\t"
            || $character === "\f"
            || $character === "\0";
    }

    private function isDelimiter(string $character): bool
    {
        return $this->isSingleCharacterDelimiter($character)
            || $character === '<'
            || $character === '>';
    }

    private function isSingleCharacterDelimiter(string $character): bool
    {
        return $character === '('
            || $character === ')'
            || $character === '['
            || $character === ']'
            || $character === '{'
            || $character === '}'
            || $character === '/'
            || $character === '%';
    }
}
