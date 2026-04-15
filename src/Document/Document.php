<?php

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Page\Page;

/**
 * Immutable document model produced by the document builder.
 */
final readonly class Document
{
    /**
     * @param list<Page> $pages Ordered pages of the document.
     */
    public static function make(
        array $pages,
        ?string $title = null,
        ?string $author = null,
        ?string $subject = null,
        ?string $keywords = null,
        ?string $language = null,
        ?string $creator = null,
        ?string $creatorTool = null,
    ): self {
        return new self(
            pages: $pages,
            title: $title,
            author: $author,
            subject: $subject,
            keywords: $keywords,
            language: $language,
            creator: $creator,
            creatorTool: $creatorTool,
        );
    }

    /**
     * @param list<Page> $pages Ordered pages of the document.
     */
    private function __construct(
        public array $pages,
        public ?string $title = null,
        public ?string $author = null,
        public ?string $subject = null,
        public ?string $keywords = null,
        public ?string $language = null,
        public ?string $creator = null,
        public ?string $creatorTool = null,
    ) {
    }
}
