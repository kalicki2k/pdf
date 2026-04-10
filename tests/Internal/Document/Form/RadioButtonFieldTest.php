<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Document\Form;

use Kalle\Pdf\Document;
use Kalle\Pdf\Internal\Document\Form\RadioButtonField;
use Kalle\Pdf\Internal\Encryption\Object\ObjectStringEncryptor;
use Kalle\Pdf\Internal\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\Internal\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Internal\Encryption\Standard\StandardSecurityHandlerData;
use Kalle\Pdf\Internal\Page\Annotation\RadioButtonWidgetAnnotation;
use Kalle\Pdf\Internal\Page\Form\RadioButtonAppearanceStream;
use Kalle\Pdf\Internal\Security\EncryptionAlgorithm;
use Kalle\Pdf\Profile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RadioButtonFieldTest extends TestCase
{
    #[Test]
    public function it_renders_a_radio_button_parent_field(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $field = new RadioButtonField(7, 'delivery');
        $field->addWidget(
            new RadioButtonWidgetAnnotation(
                8,
                $page,
                $field,
                10,
                20,
                12,
                'standard',
                true,
                new RadioButtonAppearanceStream(9, 12, false),
                new RadioButtonAppearanceStream(10, 12, true),
            ),
            'standard',
            true,
        );

        self::assertSame(
            "7 0 obj\n"
            . "<< /FT /Btn /T (delivery) /Ff 49152 /Kids [8 0 R] /V /standard >>\n"
            . "endobj\n",
            $field->render(),
        );
    }

    #[Test]
    public function it_renders_accessibility_metadata_for_radio_button_parent_fields(): void
    {
        $document = new Document(profile: Profile::pdfUa1(), title: 'Accessible Spec', language: 'de-DE');
        $page = $document->addPage();
        $field = (new RadioButtonField(7, 'delivery'))->withTooltip('delivery');
        $field->addWidget(
            new RadioButtonWidgetAnnotation(
                8,
                $page,
                $field,
                10,
                20,
                12,
                'standard',
                true,
                new RadioButtonAppearanceStream(9, 12, false),
                new RadioButtonAppearanceStream(10, 12, true),
            ),
            'standard',
            true,
        );

        self::assertStringContainsString('/TU (delivery)', $field->render());
    }

    #[Test]
    public function it_can_render_string_entries_with_an_explicit_object_string_encryptor(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $field = (new RadioButtonField(7, 'delivery'))->withTooltip('delivery');
        $field->addWidget(
            new RadioButtonWidgetAnnotation(
                8,
                $page,
                $field,
                10,
                20,
                12,
                'standard',
                true,
                new RadioButtonAppearanceStream(9, 12, false),
                new RadioButtonAppearanceStream(10, 12, true),
            ),
            'standard',
            true,
        );

        $rendered = $field->renderWithStringEncryptor(
            new ObjectStringEncryptor(
                new StandardObjectEncryptor(
                    new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
                    new StandardSecurityHandlerData('', '', '1234567890123456', -4),
                ),
                7,
            ),
        );

        self::assertStringStartsWith("7 0 obj\n<< /FT /Btn /T <", $rendered);
        self::assertStringNotContainsString('(delivery)', $rendered);
    }
}
