<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Feature\Text\StructureTag;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StructureTagTest extends TestCase
{
    #[Test]
    public function it_exposes_all_expected_structure_tag_values(): void
    {
        self::assertSame([
            'Document' => 'Document',
            'Heading1' => 'H1',
            'Heading2' => 'H2',
            'Heading3' => 'H3',
            'Heading4' => 'H4',
            'Heading5' => 'H5',
            'Heading6' => 'H6',
            'Paragraph' => 'P',
            'Span' => 'Span',
            'Link' => 'Link',
            'Form' => 'Form',
            'List' => 'L',
            'ListItem' => 'LI',
            'Label' => 'Lbl',
            'ListBody' => 'LBody',
            'Quote' => 'Quote',
            'Note' => 'Note',
            'Annotation' => 'Annot',
            'Part' => 'Part',
            'Section' => 'Sect',
            'Article' => 'Art',
            'Division' => 'Div',
            'Table' => 'Table',
            'Caption' => 'Caption',
            'TableRow' => 'TR',
            'TableHeaderCell' => 'TH',
            'TableDataCell' => 'TD',
            'Figure' => 'Figure',
        ], array_column(StructureTag::cases(), 'value', 'name'));
    }
}
