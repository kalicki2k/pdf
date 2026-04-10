<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Encryption\EncryptionAlgorithm;
use Kalle\Pdf\Encryption\EncryptionProfile;
use Kalle\Pdf\Encryption\ObjectStringEncryptor;
use Kalle\Pdf\Encryption\StandardObjectEncryptor;
use Kalle\Pdf\Encryption\StandardSecurityHandlerData;
use Kalle\Pdf\Feature\Outline\OutlineItem;
use Kalle\Pdf\Internal\Document\Document;
use Kalle\Pdf\Profile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class OutlineItemTest extends TestCase
{
    #[Test]
    public function it_renders_an_outline_item(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $document->addOutline('Intro', $page);

        /** @var OutlineItem $outlineItem */
        $outlineItem = $document->outlineRoot?->getItems()[0] ?? throw new RuntimeException('Expected outline item.');

        self::assertSame(
            "8 0 obj\n<< /Title (Intro) /Parent 7 0 R /Dest [4 0 R /Fit] >>\nendobj\n",
            $outlineItem->render(),
        );
    }

    #[Test]
    public function it_can_render_titles_with_an_explicit_object_string_encryptor(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $document->addOutline('Intro', $page);

        /** @var OutlineItem $outlineItem */
        $outlineItem = $document->outlineRoot?->getItems()[0] ?? throw new RuntimeException('Expected outline item.');

        $rendered = $outlineItem->renderWithStringEncryptor(
            new ObjectStringEncryptor(
                new StandardObjectEncryptor(
                    new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
                    new StandardSecurityHandlerData('', '', '1234567890123456', -4),
                ),
                8,
            ),
        );

        self::assertStringStartsWith("8 0 obj\n<< /Title <", $rendered);
        self::assertStringNotContainsString('(Intro)', $rendered);
    }
}
