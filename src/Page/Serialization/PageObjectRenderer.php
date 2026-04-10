<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page\Serialization;

use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Render\PdfOutput;

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

    public function write(PdfOutput $output, bool $hasMarkedContent): void
    {
        $dictionary = $this->dictionaryBuilder->build($this->page, $hasMarkedContent);

        $output->write($this->page->id . ' 0 obj' . PHP_EOL);
        $dictionary->write($output);
        $output->write(PHP_EOL);
        $output->write('endobj' . PHP_EOL);
    }
}
