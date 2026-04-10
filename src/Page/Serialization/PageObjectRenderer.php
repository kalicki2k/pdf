<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page\Serialization;

use Kalle\Pdf\Page;

/**
 * @internal Renders the PDF page object dictionary.
 */
final class PageObjectRenderer
{
    public static function forPage(Page $page): self
    {
        return new self(
            $page,
            new PageDictionaryBuilder(),
        );
    }

    public function __construct(
        private readonly Page $page,
        private readonly PageDictionaryBuilder $dictionaryBuilder,
    ) {
    }

    public function render(bool $hasMarkedContent): string
    {
        $dictionary = $this->dictionaryBuilder->build($this->page, $hasMarkedContent);

        return $this->page->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'endobj' . PHP_EOL;
    }
}
