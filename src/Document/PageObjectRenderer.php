<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

/**
 * @internal Renders the PDF page object dictionary.
 */
final class PageObjectRenderer
{
    private readonly PageDictionaryBuilder $dictionaryBuilder;

    public function __construct(
        private readonly Page $page,
        ?PageDictionaryBuilder $dictionaryBuilder = null,
    ) {
        $this->dictionaryBuilder = $dictionaryBuilder ?? new PageDictionaryBuilder();
    }

    public function render(bool $hasMarkedContent): string
    {
        $dictionary = $this->dictionaryBuilder->build($this->page, $hasMarkedContent);

        return $this->page->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'endobj' . PHP_EOL;
    }
}
