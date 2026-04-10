<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Page\Form;

use Kalle\Pdf\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Encryption\Standard\StandardSecurityHandlerData;
use Kalle\Pdf\Page\Form\RadioButtonAppearanceStream;
use Kalle\Pdf\Render\StringPdfOutput;
use Kalle\Pdf\Security\EncryptionAlgorithm;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RadioButtonAppearanceStreamTest extends TestCase
{
    #[Test]
    public function it_renders_a_checked_radio_button_appearance_stream(): void
    {
        $stream = new RadioButtonAppearanceStream(7, 12, true);

        self::assertStringContainsString('/Subtype /Form', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($stream));
        self::assertStringContainsString('6 11.5 m', \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($stream));
        self::assertStringContainsString("\nf\nendstream", \Kalle\Pdf\Tests\Support\writeIndirectObjectToString($stream));
    }

    #[Test]
    public function it_writes_an_encrypted_radio_button_appearance_stream_consistently(): void
    {
        $stream = new RadioButtonAppearanceStream(7, 12, true);
        $stream->prepareLengthObject(9);
        $encryptor = new StandardObjectEncryptor(
            new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
            new StandardSecurityHandlerData('', '', '1234567890123456', -4),
        );
        $output = new StringPdfOutput();

        $stream->writeEncrypted($output, $encryptor);

        self::assertSame(
            $encryptor->encryptStreamObject(\Kalle\Pdf\Tests\Support\writeIndirectObjectToString($stream), 7),
            $output->contents(),
        );
    }
}
