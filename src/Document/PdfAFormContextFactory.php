<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function array_filter;
use function array_values;
use function dirname;
use function preg_split;

use Kalle\Pdf\Document\Form\ComboBoxField;
use Kalle\Pdf\Document\Form\FormFieldRenderContext;
use Kalle\Pdf\Document\Form\ListBoxField;
use Kalle\Pdf\Document\Form\PushButtonField;
use Kalle\Pdf\Document\Form\TextField;
use Kalle\Pdf\Font\EmbeddedFontDefinition;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Page\EmbeddedGlyph;
use Kalle\Pdf\Page\PageFont;

final class PdfAFormContextFactory
{
    public function buildDefaultFont(Document $document): ?PageFont
    {
        if ($document->acroForm === null || !$document->profile->isPdfA1()) {
            return null;
        }

        $defaultFont = null;

        foreach ($document->pages as $page) {
            foreach ($page->fontResources as $pageFont) {
                if (!$pageFont->isEmbedded() || !$pageFont->usesUnicodeCids()) {
                    continue;
                }

                $defaultFont = $pageFont;
                break 2;
            }
        }

        if ($defaultFont === null) {
            $defaultFont = PageFont::embeddedUnicode(
                EmbeddedFontDefinition::fromSource(
                    EmbeddedFontSource::fromPath(dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf'),
                ),
                [],
            );
        }

        $additionalGlyphs = [];

        foreach ($document->acroForm->fields as $field) {
            foreach ($this->formFieldVisibleTexts($field) as $text) {
                foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
                    $codePoint = mb_ord($character, 'UTF-8');
                    $additionalGlyphs[] = new EmbeddedGlyph(
                        glyphId: $defaultFont->embeddedDefinition()->parser->getGlyphIdForCodePoint($codePoint),
                        unicodeCodePoint: $codePoint,
                        unicodeText: $character,
                    );
                }
            }
        }

        return $additionalGlyphs === []
            ? $defaultFont
            : $defaultFont->withAdditionalEmbeddedGlyphs($additionalGlyphs);
    }

    /**
     * @param array<int, int> $pageObjectIdsByPageNumber
     * @param array<int, int> $structParentIdsByAnnotationObjectId
     */
    public function buildRenderContext(
        Document $document,
        array $pageObjectIdsByPageNumber,
        array $structParentIdsByAnnotationObjectId = [],
        ?int $defaultTextFontObjectId = null,
    ): FormFieldRenderContext {
        $defaultFont = $this->buildDefaultFont($document);

        if ($defaultFont === null) {
            return new FormFieldRenderContext(
                $pageObjectIdsByPageNumber,
                $structParentIdsByAnnotationObjectId,
            );
        }

        return new FormFieldRenderContext(
            $pageObjectIdsByPageNumber,
            $structParentIdsByAnnotationObjectId,
            $defaultFont,
            'F0',
            $defaultTextFontObjectId,
        );
    }

    /**
     * @return list<string>
     */
    private function formFieldVisibleTexts(object $field): array
    {
        return match (true) {
            $field instanceof TextField => array_values(array_filter([$field->value, $field->defaultValue], static fn (?string $value): bool => $value !== null && $value !== '')),
            $field instanceof ComboBoxField => array_values(array_filter([
                $field->value !== null ? ($field->options[$field->value] ?? null) : null,
                $field->defaultValue !== null ? ($field->options[$field->defaultValue] ?? null) : null,
                ...array_values($field->options),
            ], static fn (?string $value): bool => $value !== null && $value !== '')),
            $field instanceof ListBoxField => array_values(array_filter(array_values($field->options), static fn (?string $value): bool => $value !== null && $value !== '')),
            $field instanceof PushButtonField => [$field->label],
            default => [],
        };
    }
}
