<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Document\OptionalContent;

use Kalle\Pdf\Document\OptionalContent\OptionalContentGroup;
use Kalle\Pdf\Encryption\Object\ObjectStringEncryptor;
use Kalle\Pdf\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Encryption\Standard\StandardSecurityHandlerData;
use Kalle\Pdf\Security\EncryptionAlgorithm;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OptionalContentGroupTest extends TestCase
{
    #[Test]
    public function it_renders_an_optional_content_group(): void
    {
        $group = new OptionalContentGroup(7, 'Notes');

        self::assertSame(
            "7 0 obj\n<< /Type /OCG /Name (Notes) >>\nendobj\n",
            \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($group),
        );
    }

    #[Test]
    public function it_can_render_string_entries_with_an_explicit_object_string_encryptor(): void
    {
        $group = new OptionalContentGroup(7, 'Notes');

        $rendered = \Kalle\Pdf\Tests\Support\writeIndirectObjectToString(
            $group,
            new ObjectStringEncryptor(
                new StandardObjectEncryptor(
                    new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
                    new StandardSecurityHandlerData('', '', '1234567890123456', -4),
                ),
                7,
            ),
        );

        self::assertStringStartsWith("7 0 obj\n<< /Type /OCG /Name <", $rendered);
        self::assertStringNotContainsString('(Notes)', $rendered);
    }
}
