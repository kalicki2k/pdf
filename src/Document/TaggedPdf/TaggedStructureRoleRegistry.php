<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\TaggedPdf;

use function array_keys;

use function array_unique;
use function array_values;
use function implode;
use function in_array;

use InvalidArgumentException;

use function sort;
use function sprintf;

final class TaggedStructureRoleRegistry
{
    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_CHILDREN = [
        'Document' => [
            'Annot',
            'Art',
            'BibEntry',
            'BlockQuote',
            'Caption',
            'Code',
            'Div',
            'Em',
            'Figure',
            'Form',
            'H1',
            'H2',
            'H3',
            'H4',
            'H5',
            'H6',
            'Index',
            'L',
            'Link',
            'NonStruct',
            'Note',
            'P',
            'Part',
            'Private',
            'Quote',
            'Reference',
            'Sect',
            'Span',
            'Strong',
            'TOC',
            'TOCI',
            'Table',
            'Title',
        ],
        'Art' => ['Annot', 'BibEntry', 'BlockQuote', 'Caption', 'Code', 'Div', 'Em', 'Figure', 'Form', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'Index', 'L', 'Link', 'NonStruct', 'Note', 'P', 'Private', 'Quote', 'Reference', 'Sect', 'Span', 'Strong', 'TOC', 'TOCI', 'Table', 'Title'],
        'BlockQuote' => ['BibEntry', 'Code', 'Em', 'Figure', 'L', 'Link', 'NonStruct', 'Note', 'P', 'Quote', 'Reference', 'Span', 'Strong', 'Table'],
        'Div' => ['Annot', 'Art', 'BibEntry', 'BlockQuote', 'Caption', 'Code', 'Div', 'Em', 'Figure', 'Form', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'Index', 'L', 'Link', 'NonStruct', 'Note', 'P', 'Private', 'Quote', 'Reference', 'Sect', 'Span', 'Strong', 'TOC', 'TOCI', 'Table', 'Title'],
        'Index' => ['BibEntry', 'Div', 'L', 'Link', 'NonStruct', 'P', 'Reference', 'Sect', 'Span', 'TOCI'],
        'NonStruct' => ['Annot', 'BibEntry', 'BlockQuote', 'Caption', 'Code', 'Div', 'Em', 'Figure', 'Form', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'Index', 'L', 'Link', 'NonStruct', 'Note', 'P', 'Private', 'Quote', 'Reference', 'Sect', 'Span', 'Strong', 'TOC', 'TOCI', 'Table', 'Title'],
        'Note' => ['BibEntry', 'Code', 'Em', 'Figure', 'L', 'Link', 'P', 'Quote', 'Reference', 'Span', 'Strong', 'Table'],
        'Part' => ['Annot', 'Art', 'BibEntry', 'BlockQuote', 'Caption', 'Code', 'Div', 'Em', 'Figure', 'Form', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'Index', 'L', 'Link', 'NonStruct', 'Note', 'P', 'Private', 'Quote', 'Reference', 'Sect', 'Span', 'Strong', 'TOC', 'TOCI', 'Table', 'Title'],
        'Private' => ['Annot', 'BibEntry', 'BlockQuote', 'Caption', 'Code', 'Div', 'Em', 'Figure', 'Form', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'Index', 'L', 'Link', 'NonStruct', 'Note', 'P', 'Private', 'Quote', 'Reference', 'Sect', 'Span', 'Strong', 'TOC', 'TOCI', 'Table', 'Title'],
        'Quote' => ['BibEntry', 'Code', 'Em', 'Link', 'Reference', 'Span', 'Strong'],
        'Sect' => ['Annot', 'Art', 'BibEntry', 'BlockQuote', 'Caption', 'Code', 'Div', 'Em', 'Figure', 'Form', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'Index', 'L', 'Link', 'NonStruct', 'Note', 'P', 'Private', 'Quote', 'Reference', 'Sect', 'Span', 'Strong', 'TOC', 'TOCI', 'Table', 'Title'],
        'TOC' => ['Link', 'NonStruct', 'Private', 'TOCI'],
        'TOCI' => ['Em', 'Lbl', 'Link', 'NonStruct', 'P', 'Reference', 'Span', 'Strong'],
    ];

    /**
     * @var list<string>
     */
    private const LEAF_TAGS = [
        'Annot',
        'BibEntry',
        'Caption',
        'Code',
        'Em',
        'Figure',
        'Form',
        'H1',
        'H2',
        'H3',
        'H4',
        'H5',
        'H6',
        'L',
        'Lbl',
        'Link',
        'Note',
        'P',
        'Quote',
        'Reference',
        'Span',
        'Strong',
        'TOCI',
        'Table',
        'Title',
    ];

    public function assertKnownTag(TaggedStructureTag | string $tag): void
    {
        $tag = $this->normalizeTag($tag);

        if (!isset(self::ALLOWED_CHILDREN[$tag]) && !in_array($tag, self::LEAF_TAGS, true)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported tagged PDF structure type "%s". Supported types are [%s].',
                $tag,
                implode(', ', $this->supportedTags()),
            ));
        }
    }

    public function assertChildAllowed(TaggedStructureTag | string $parentTag, TaggedStructureTag | string $childTag): void
    {
        $parentTag = $this->normalizeTag($parentTag);
        $childTag = $this->normalizeTag($childTag);

        $this->assertKnownTag($parentTag);
        $this->assertKnownTag($childTag);

        if (!isset(self::ALLOWED_CHILDREN[$parentTag]) || !in_array($childTag, self::ALLOWED_CHILDREN[$parentTag], true)) {
            throw new InvalidArgumentException(sprintf(
                'Tagged PDF structure type "%s" does not allow child "%s".',
                $parentTag,
                $childTag,
            ));
        }
    }

    public function isContainerTag(TaggedStructureTag | string $tag): bool
    {
        $tag = $this->normalizeTag($tag);

        return isset(self::ALLOWED_CHILDREN[$tag]);
    }

    /**
     * @return list<string>
     */
    public function supportedLeafTextTags(): array
    {
        return [
            'BibEntry',
            'BlockQuote',
            'Code',
            'Em',
            'H1',
            'H2',
            'H3',
            'H4',
            'H5',
            'H6',
            'Note',
            'P',
            'Quote',
            'Reference',
            'Span',
            'Strong',
            'Title',
        ];
    }

    /**
     * @return list<string>
     */
    public function supportedTags(): array
    {
        $tags = [...array_keys(self::ALLOWED_CHILDREN), ...self::LEAF_TAGS];
        sort($tags);

        return array_values(array_unique($tags));
    }

    private function normalizeTag(TaggedStructureTag | string $tag): string
    {
        return $tag instanceof TaggedStructureTag ? $tag->value : $tag;
    }
}
