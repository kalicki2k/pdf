<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Color\ColorSpace;
use Kalle\Pdf\Document\Form\FormFieldRenderContext;
use Kalle\Pdf\Document\Metadata\PdfAOutputIntent;
use Kalle\Pdf\Image\ImageColorSpace;
use Kalle\Pdf\Image\ImageSource;
use Kalle\Pdf\Page\AppearanceStreamAnnotation;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Page\PageAnnotationRenderContext;
use Kalle\Pdf\Page\RelatedObjectsPageAnnotation;

use function sprintf;
use function strlen;

/**
 * Validates PDF/A-1 device color usage against the active output intent.
 *
 * In the current implementation this covers:
 * - page-level graphics such as backgrounds and vector path operators in content streams
 * - text color operators inside BT/ET sections
 * - annotation appearance streams that are allowed in the current PDF/A-1 model
 * - image device color spaces
 *
 * DeviceGray is allowed with Gray, RGB, and CMYK output intents per the PDF/A
 * color guidance. DeviceRGB and DeviceCMYK must match the active output intent
 * unless the document uses device-independent/default-mapped color spaces,
 * which this generator currently does not emit for these paths.
 */
final readonly class PdfAColorPolicyValidator
{
    public function __construct(
        private DocumentMetadataInspector $metadataInspector = new DocumentMetadataInspector(),
        private PdfAFormContextFactory $pdfAFormContextFactory = new PdfAFormContextFactory(),
    ) {
    }

    public function assertDocumentColors(Document $document): void
    {
        if (!$document->profile->isPdfA1()) {
            return;
        }

        $outputIntent = $this->metadataInspector->resolvePdfAOutputIntent($document);

        foreach ($document->pages as $pageIndex => $page) {
            $this->assertPageColors($document, $page, $pageIndex, $outputIntent);
        }

        $this->assertAcroFormColors($document, $outputIntent);
    }

    private function assertPageColors(
        Document $document,
        Page $page,
        int $pageIndex,
        PdfAOutputIntent $outputIntent,
    ): void {
        if ($page->backgroundColor !== null) {
            $this->assertColorCompatible(
                $document,
                $page->backgroundColor,
                sprintf('page background graphics on page %d', $pageIndex + 1),
                $outputIntent,
            );
        }

        $this->assertContentStreamColors(
            $document,
            $page->contents,
            sprintf('page content stream on page %d', $pageIndex + 1),
            $outputIntent,
        );

        foreach ($page->annotations as $annotationIndex => $annotation) {
            if ($annotation instanceof AppearanceStreamAnnotation) {
                $this->assertContentStreamColors(
                    $document,
                    $annotation->appearanceStreamContents(),
                    sprintf('annotation appearance stream %d on page %d', $annotationIndex + 1, $pageIndex + 1),
                    $outputIntent,
                );
            }

            if (!$annotation instanceof RelatedObjectsPageAnnotation) {
                continue;
            }

            foreach ($annotation->relatedObjects(new PageAnnotationRenderContext(
                pageObjectId: $pageIndex + 1,
                printable: true,
                pageObjectIdsByPageNumber: [$pageIndex + 1 => $pageIndex + 1],
                relatedObjectIds: [1],
            )) as $relatedObject) {
                if ($relatedObject->streamContents === null) {
                    continue;
                }

                $this->assertContentStreamColors(
                    $document,
                    $relatedObject->streamContents,
                    sprintf('related annotation appearance stream %d on page %d', $annotationIndex + 1, $pageIndex + 1),
                    $outputIntent,
                );
            }
        }

        $imageResourceIndex = 0;

        foreach ($page->imageResources as $imageSource) {
            $imageResourceIndex++;
            $this->assertImageColorSpaceCompatible($document, $imageSource, $pageIndex, $imageResourceIndex, $outputIntent);
        }
    }

    private function assertAcroFormColors(Document $document, PdfAOutputIntent $outputIntent): void
    {
        if ($document->acroForm === null) {
            return;
        }

        $pageObjectIdsByPageNumber = [];

        foreach ($document->pages as $pageIndex => $_page) {
            $pageObjectIdsByPageNumber[$pageIndex + 1] = $pageIndex + 1;
        }

        $context = $this->pdfAFormRenderContext($document, $pageObjectIdsByPageNumber);

        foreach ($document->acroForm->fields as $fieldIndex => $field) {
            foreach ($field->relatedObjects($context, $fieldIndex + 1, array_fill(0, $field->relatedObjectCount(), 1)) as $relatedObject) {
                if ($relatedObject->streamContents === null) {
                    continue;
                }

                $this->assertContentStreamColors(
                    $document,
                    $relatedObject->streamContents,
                    sprintf('form appearance stream %d', $fieldIndex + 1),
                    $outputIntent,
                );
            }
        }
    }

    /**
     * @param array<int, int> $pageObjectIdsByPageNumber
     */
    private function pdfAFormRenderContext(Document $document, array $pageObjectIdsByPageNumber): FormFieldRenderContext
    {
        return $this->pdfAFormContextFactory->buildRenderContext(
            $document,
            $pageObjectIdsByPageNumber,
            defaultTextFontObjectId: 1,
        );
    }

    private function assertImageColorSpaceCompatible(
        Document $document,
        ImageSource $imageSource,
        int $pageIndex,
        int $imageResourceIndex,
        PdfAOutputIntent $outputIntent,
    ): void {
        if ($imageSource->colorSpaceDefinition !== null) {
            return;
        }

        $deviceColorSpace = match ($imageSource->colorSpace) {
            ImageColorSpace::GRAY => ColorSpace::GRAY,
            ImageColorSpace::RGB => ColorSpace::RGB,
            ImageColorSpace::CMYK => ColorSpace::CMYK,
        };

        $this->assertDeviceColorSpaceCompatible(
            $document,
            $deviceColorSpace,
            sprintf('%s image resource %d on page %d', $this->colorSpaceLabel($deviceColorSpace), $imageResourceIndex, $pageIndex + 1),
            $outputIntent,
            true,
        );
    }

    private function assertColorCompatible(
        Document $document,
        Color $color,
        string $context,
        PdfAOutputIntent $outputIntent,
    ): void {
        $this->assertDeviceColorSpaceCompatible($document, $color->space, $context, $outputIntent);
    }

    private function assertContentStreamColors(
        Document $document,
        string $contents,
        string $streamLabel,
        PdfAOutputIntent $outputIntent,
    ): void {
        if ($contents === '') {
            return;
        }

        $inTextObject = false;
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

            if ($token === 'BT') {
                $inTextObject = true;

                continue;
            }

            if ($token === 'ET') {
                $inTextObject = false;

                continue;
            }

            $deviceColorSpace = match ($token) {
                'g', 'G' => ColorSpace::GRAY,
                'rg', 'RG' => ColorSpace::RGB,
                'k', 'K' => ColorSpace::CMYK,
                default => null,
            };

            if ($deviceColorSpace === null) {
                continue;
            }

            $usage = $inTextObject ? 'text' : 'graphics';

            $this->assertDeviceColorSpaceCompatible(
                $document,
                $deviceColorSpace,
                sprintf('%s operations in %s', $usage, $streamLabel),
                $outputIntent,
            );
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

        return min($index + 1, $length);
    }

    private function assertDeviceColorSpaceCompatible(
        Document $document,
        ColorSpace $deviceColorSpace,
        string $context,
        PdfAOutputIntent $outputIntent,
        bool $forImage = false,
    ): void {
        $intentColorSpace = $this->outputIntentColorSpace($outputIntent);

        if ($deviceColorSpace === ColorSpace::GRAY) {
            return;
        }

        if ($deviceColorSpace === $intentColorSpace) {
            return;
        }

        if ($forImage) {
            throw new DocumentValidationException(DocumentBuildError::PDFA_OUTPUT_INTENT_INVALID, sprintf(
                'Profile %s requires %s %s PDF/A output intent for %s.',
                $document->profile->name(),
                $this->indefiniteArticle($deviceColorSpace),
                $this->colorSpaceLabel($deviceColorSpace),
                $context,
            ));
        }

        throw new DocumentValidationException(DocumentBuildError::PDFA_OUTPUT_INTENT_INVALID, sprintf(
            'Profile %s does not allow %s color in %s when the active PDF/A output intent is %s.',
            $document->profile->name(),
            $this->colorSpaceLabel($deviceColorSpace),
            $context,
            $this->colorSpaceLabel($intentColorSpace),
        ));
    }

    private function outputIntentColorSpace(PdfAOutputIntent $outputIntent): ColorSpace
    {
        return match ($outputIntent->colorComponents) {
            1 => ColorSpace::GRAY,
            3 => ColorSpace::RGB,
            4 => ColorSpace::CMYK,
            default => throw new DocumentValidationException(DocumentBuildError::PDFA_OUTPUT_INTENT_INVALID, sprintf(
                'PDF/A-1 output intent "%s" uses unsupported color component count %d in the current implementation.',
                $outputIntent->outputConditionIdentifier,
                $outputIntent->colorComponents,
            )),
        };
    }

    private function colorSpaceLabel(ColorSpace $colorSpace): string
    {
        return match ($colorSpace) {
            ColorSpace::GRAY => 'Gray',
            ColorSpace::RGB => 'RGB',
            ColorSpace::CMYK => 'CMYK',
        };
    }

    private function indefiniteArticle(ColorSpace $colorSpace): string
    {
        return $colorSpace === ColorSpace::RGB ? 'an' : 'a';
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
