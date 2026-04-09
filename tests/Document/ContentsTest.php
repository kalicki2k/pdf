<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Element\Element;
use Kalle\Pdf\Encryption\EncryptionAlgorithm;
use Kalle\Pdf\Encryption\EncryptionProfile;
use Kalle\Pdf\Encryption\StandardObjectEncryptor;
use Kalle\Pdf\Encryption\StandardSecurityHandlerData;
use Kalle\Pdf\Model\Page\Contents;
use Kalle\Pdf\Render\StringPdfOutput;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ContentsTest extends TestCase
{
    #[Test]
    public function it_returns_itself_when_adding_an_element(): void
    {
        $contents = new Contents(8);

        $result = $contents->addElement($this->createElement('BT'));

        self::assertSame($contents, $result);
    }

    #[Test]
    public function it_renders_an_empty_stream_with_zero_length(): void
    {
        $contents = new Contents(8);
        $contents->prepareLengthObject(9);

        self::assertSame(
            "8 0 obj\n<< /Length 9 0 R >>\nstream\n\nendstream\nendobj\n",
            $contents->render(),
        );
        self::assertSame("9 0 obj\n0\nendobj\n", $contents->getLengthObject()->render());
    }

    #[Test]
    public function it_renders_all_elements_in_order_and_sets_the_stream_length(): void
    {
        $contents = new Contents(12);
        $contents->prepareLengthObject(13);
        $contents->addElement($this->createElement('BT'));
        $contents->addElement($this->createElement('ET'));

        self::assertSame(
            "12 0 obj\n<< /Length 13 0 R >>\nstream\nBT\nET\nendstream\nendobj\n",
            $contents->render(),
        );
        self::assertSame("13 0 obj\n5\nendobj\n", $contents->getLengthObject()->render());
    }

    #[Test]
    public function it_can_append_more_elements_after_the_stream_was_rendered_once(): void
    {
        $contents = new Contents(12);
        $contents->prepareLengthObject(13);
        $contents->addElement($this->createElement('BT'));

        self::assertSame(
            "12 0 obj\n<< /Length 13 0 R >>\nstream\nBT\nendstream\nendobj\n",
            $contents->render(),
        );
        self::assertSame("13 0 obj\n2\nendobj\n", $contents->getLengthObject()->render());

        $contents->addElement($this->createElement('ET'));

        self::assertSame(
            "12 0 obj\n<< /Length 13 0 R >>\nstream\nBT\nET\nendstream\nendobj\n",
            $contents->render(),
        );
        self::assertSame("13 0 obj\n5\nendobj\n", $contents->getLengthObject()->render());
    }

    #[Test]
    public function it_writes_an_empty_stream_with_zero_length(): void
    {
        $contents = new Contents(8);
        $contents->prepareLengthObject(9);
        $output = new StringPdfOutput();

        $contents->write($output);

        self::assertSame($contents->render(), $output->contents());
    }

    #[Test]
    public function it_writes_all_elements_in_order_and_keeps_the_stream_reusable(): void
    {
        $contents = new Contents(12);
        $contents->prepareLengthObject(13);
        $contents->addElement($this->createElement('BT'));
        $contents->addElement($this->createElement('ET'));
        $output = new StringPdfOutput();

        $contents->write($output);

        self::assertSame($contents->render(), $output->contents());
        self::assertSame(
            "12 0 obj\n<< /Length 13 0 R >>\nstream\nBT\nET\nendstream\nendobj\n",
            $contents->render(),
        );
    }

    #[Test]
    public function it_writes_encrypted_contents_with_the_same_result_as_the_legacy_stream_encryption_path(): void
    {
        $contents = new Contents(12);
        $contents->prepareLengthObject(13);
        $contents->addElement($this->createElement('BT'));
        $contents->addElement($this->createElement('ET'));
        $encryptor = new StandardObjectEncryptor(
            new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
            new StandardSecurityHandlerData('', '', '1234567890123456', -4),
        );
        $output = new StringPdfOutput();

        $contents->writeEncrypted($output, $encryptor);

        self::assertSame(
            $encryptor->encryptStreamObject($contents->render(), 12),
            $output->contents(),
        );
    }

    private function createElement(string $renderedValue): Element
    {
        return new class ($renderedValue) extends Element {
            public function __construct(private readonly string $renderedValue)
            {
            }

            public function render(): string
            {
                return $this->renderedValue;
            }
        };
    }
}
