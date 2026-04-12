<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document\Attachment;

use InvalidArgumentException;
use Kalle\Pdf\Document\Attachment\AssociatedFileRelationship;
use Kalle\Pdf\Document\Attachment\EmbeddedFile;
use Kalle\Pdf\Document\Attachment\FileAttachment;
use PHPUnit\Framework\TestCase;

final class FileAttachmentTest extends TestCase
{
    public function testItRejectsEmptyAttachmentFilenames(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Attachment filename must not be empty.');

        new FileAttachment('', new EmbeddedFile('payload'));
    }

    public function testItRejectsEmptyEmbeddedFileMimeTypes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Embedded file MIME type must not be empty.');

        new EmbeddedFile('payload', '');
    }

    public function testItKeepsAttachmentMetadata(): void
    {
        $attachment = new FileAttachment(
            'data.xml',
            new EmbeddedFile('<root/>', 'application/xml'),
            'Machine-readable source',
            AssociatedFileRelationship::DATA,
        );

        self::assertSame('data.xml', $attachment->filename);
        self::assertSame('<root/>', $attachment->embeddedFile->contents);
        self::assertSame(7, $attachment->embeddedFile->size());
        self::assertSame('Machine-readable source', $attachment->description);
        self::assertSame(AssociatedFileRelationship::DATA, $attachment->associatedFileRelationship);
    }
}
