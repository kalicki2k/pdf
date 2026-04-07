<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\AssociatedFileRelationship;
use Kalle\Pdf\Document\EmbeddedFileStream;
use Kalle\Pdf\Document\FileSpecification;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FileSpecificationTest extends TestCase
{
    #[Test]
    public function it_renders_a_file_specification_with_embedded_file_references(): void
    {
        $embeddedFile = new EmbeddedFileStream(7, 'hello');
        $fileSpecification = new FileSpecification(8, 'demo.txt', $embeddedFile, 'Demo attachment');

        self::assertSame(
            "8 0 obj\n"
            . "<< /Type /Filespec /F (demo.txt) /UF (demo.txt) /EF << /F 7 0 R /UF 7 0 R >> /Desc (Demo attachment) >>\n"
            . "endobj\n",
            $fileSpecification->render(),
        );
    }

    #[Test]
    public function it_renders_a_file_specification_with_an_associated_file_relationship(): void
    {
        $embeddedFile = new EmbeddedFileStream(7, 'hello');
        $fileSpecification = new FileSpecification(
            8,
            'demo.txt',
            $embeddedFile,
            'Demo attachment',
            AssociatedFileRelationship::DATA,
        );

        self::assertStringContainsString('/AFRelationship /Data', $fileSpecification->render());
    }
}
