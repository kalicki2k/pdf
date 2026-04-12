<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Attachment\AssociatedFileRelationship;
use Kalle\Pdf\Document\Attachment\EmbeddedFile;
use Kalle\Pdf\Document\Attachment\FileAttachment;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\DocumentAttachmentRelationshipResolver;
use Kalle\Pdf\Document\Profile;
use PHPUnit\Framework\TestCase;

final class DocumentAttachmentRelationshipResolverTest extends TestCase
{
    public function testItPrefersAnExplicitAttachmentRelationship(): void
    {
        $document = new Document(profile: Profile::pdf20());
        $attachment = new FileAttachment(
            'data.xml',
            new EmbeddedFile('<root/>', 'application/xml'),
            associatedFileRelationship: AssociatedFileRelationship::SOURCE,
        );

        $relationship = (new DocumentAttachmentRelationshipResolver())->resolve($document, $attachment);

        self::assertSame(AssociatedFileRelationship::SOURCE, $relationship);
    }

    public function testItDefaultsPdfA3AttachmentsToDataAssociatedFiles(): void
    {
        $document = new Document(profile: Profile::pdfA3b());
        $attachment = new FileAttachment(
            'data.xml',
            new EmbeddedFile('<root/>', 'application/xml'),
        );

        $relationship = (new DocumentAttachmentRelationshipResolver())->resolve($document, $attachment);

        self::assertSame(AssociatedFileRelationship::DATA, $relationship);
    }

    public function testItKeepsPlainPdf20AttachmentsWithoutAssociatedRelationship(): void
    {
        $document = new Document(profile: Profile::pdf20());
        $attachment = new FileAttachment(
            'demo.txt',
            new EmbeddedFile('hello', 'text/plain'),
        );

        $relationship = (new DocumentAttachmentRelationshipResolver())->resolve($document, $attachment);

        self::assertNull($relationship);
    }
}
