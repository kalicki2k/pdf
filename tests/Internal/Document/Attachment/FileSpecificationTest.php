<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Document\Attachment;

use Kalle\Pdf\Document\Attachment\AssociatedFileRelationship;
use Kalle\Pdf\Document\Attachment\EmbeddedFileStream;
use Kalle\Pdf\Document\Attachment\FileSpecification;
use Kalle\Pdf\Encryption\Object\ObjectStringEncryptor;
use Kalle\Pdf\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Encryption\Standard\StandardSecurityHandlerData;
use Kalle\Pdf\Security\EncryptionAlgorithm;
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
            \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($fileSpecification),
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

        self::assertStringContainsString('/AFRelationship /Data', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($fileSpecification));
    }

    #[Test]
    public function it_can_render_string_entries_with_an_explicit_object_string_encryptor(): void
    {
        $embeddedFile = new EmbeddedFileStream(7, 'hello');
        $fileSpecification = new FileSpecification(8, 'demo.txt', $embeddedFile, 'Demo attachment');

        $rendered = \Kalle\Pdf\Tests\Support\writeIndirectObjectToString(
            $fileSpecification,
            new ObjectStringEncryptor(
                new StandardObjectEncryptor(
                    new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
                    new StandardSecurityHandlerData('', '', '1234567890123456', -4),
                ),
                8,
            ),
        );

        self::assertStringStartsWith("8 0 obj\n<< /Type /Filespec /F <", $rendered);
        self::assertStringNotContainsString('(demo.txt)', $rendered);
        self::assertStringNotContainsString('(Demo attachment)', $rendered);
    }
}
