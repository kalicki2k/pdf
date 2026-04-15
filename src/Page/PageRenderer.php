<?php

namespace Kalle\Pdf\Page;

use InvalidArgumentException;
use Kalle\Pdf\Text\Text;
use Kalle\Pdf\Text\TextWriter;

/**
 * Renders page content entries into a PDF content stream.
 */
final class PageRenderer
{
    /**
     * @param TextWriter $textWriter Renderer for text page content entries.
     */
    public function __construct(
        private TextWriter $textWriter = new TextWriter(),
    ) {
    }

    /**
     * Renders all supported page content entries into a single PDF content stream.
     *
     * @throws InvalidArgumentException When the page contains an unsupported content type.
     */
    public function render(Page $page): string
    {
        $chunks = [];

        foreach ($page->contents as $content) {
            if ($content instanceof Text) {
                $chunks[] = $this->textWriter->write($content->value, $content->x, $content->y);

                continue;
            }

            throw new InvalidArgumentException('Unsupported page content type: ' . $content::class);
        }

        return implode("\n", $chunks);
    }
}
