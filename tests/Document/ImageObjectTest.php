<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Encryption\EncryptionAlgorithm;
use Kalle\Pdf\Image;
use Kalle\Pdf\Internal\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\Internal\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Internal\Encryption\Standard\StandardSecurityHandlerData;
use Kalle\Pdf\Model\Page\ImageObject;
use Kalle\Pdf\Render\StringPdfOutput;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ImageObjectTest extends TestCase
{
    #[Test]
    public function it_renders_an_image_object_without_a_soft_mask(): void
    {
        $imageObject = new ImageObject(9, new Image(320, 200, 'DeviceRGB', 'DCTDecode', 'abc123'));

        self::assertSame(9, $imageObject->getId());
        self::assertSame(
            "9 0 obj\n"
            . "<< /Type /XObject\n"
            . "/Subtype /Image\n"
            . "/Width 320\n"
            . "/Height 200\n"
            . "/ColorSpace /DeviceRGB\n"
            . "/BitsPerComponent 8\n"
            . "/Filter /DCTDecode\n"
            . "/Length 6 >>\n"
            . "stream\n"
            . "abc123\n"
            . "endstream\n"
            . "endobj\n",
            $imageObject->render(),
        );
        self::assertSame([$imageObject], $imageObject->getRelatedObjects());
    }

    #[Test]
    public function it_renders_an_image_object_with_a_soft_mask_and_returns_related_objects_recursively(): void
    {
        $softMask = new ImageObject(10, new Image(320, 200, 'DeviceGray', 'FlateDecode', 'mask'));
        $imageObject = new ImageObject(9, new Image(320, 200, 'DeviceRGB', 'DCTDecode', 'abc123'), $softMask);

        self::assertSame(
            "9 0 obj\n"
            . "<< /Type /XObject\n"
            . "/Subtype /Image\n"
            . "/Width 320\n"
            . "/Height 200\n"
            . "/ColorSpace /DeviceRGB\n"
            . "/BitsPerComponent 8\n"
            . "/Filter /DCTDecode\n"
            . "/SMask 10 0 R\n"
            . "/Length 6 >>\n"
            . "stream\n"
            . "abc123\n"
            . "endstream\n"
            . "endobj\n",
            $imageObject->render(),
        );
        self::assertSame([$imageObject, $softMask], $imageObject->getRelatedObjects());
    }

    #[Test]
    public function it_writes_an_image_object_to_a_pdf_output(): void
    {
        $imageObject = new ImageObject(9, new Image(320, 200, 'DeviceRGB', 'DCTDecode', 'abc123'));
        $output = new StringPdfOutput();

        $imageObject->write($output);

        self::assertSame($imageObject->render(), $output->contents());
    }

    #[Test]
    public function it_writes_an_encrypted_image_object_consistently(): void
    {
        $imageObject = new ImageObject(9, new Image(320, 200, 'DeviceRGB', 'DCTDecode', 'abc123'));
        $encryptor = new StandardObjectEncryptor(
            new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
            new StandardSecurityHandlerData('', '', '1234567890123456', -4),
        );
        $output = new StringPdfOutput();

        $imageObject->writeEncrypted($output, $encryptor);

        self::assertSame(
            $encryptor->encryptStreamObject($imageObject->render(), 9),
            $output->contents(),
        );
    }
}
