<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Page\Annotation;

use Kalle\Pdf\Document;
use Kalle\Pdf\Encryption\Object\ObjectStringEncryptor;
use Kalle\Pdf\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Encryption\Standard\StandardSecurityHandlerData;
use Kalle\Pdf\Page\Annotation\ComboBoxAnnotation;
use Kalle\Pdf\Page\Form\FormFieldFlags;
use Kalle\Pdf\Profile\Profile;
use Kalle\Pdf\Security\EncryptionAlgorithm;
use Kalle\Pdf\Style\Color;
use Kalle\Pdf\Tests\Support\CreatesPdfUaTestDocument;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ComboBoxAnnotationTest extends TestCase
{
    use CreatesPdfUaTestDocument;

    #[Test]
    public function it_renders_a_combo_box_widget_annotation(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $annotation = new ComboBoxAnnotation(
            7,
            $page,
            10,
            20,
            80,
            12,
            'country',
            ['de' => 'Deutschland', 'at' => 'Oesterreich'],
            'de',
            'F1',
            12,
        );

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Widget /FT /Ch /Rect [10 20 90 32] /Border [0 0 1] /P 5 0 R /T (country) /DA (/F1 12 Tf 0 g) /Opt [[(de) (Deutschland)] [(at) (Oesterreich)]] /Ff 131072 /V (de) >>\n"
            . "endobj\n",
            $annotation->render(),
        );
    }

    #[Test]
    public function it_renders_read_only_and_required_flags_for_combo_boxes(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $annotation = new ComboBoxAnnotation(
            7,
            $page,
            10,
            20,
            80,
            12,
            'country',
            ['de' => 'Deutschland'],
            'de',
            'F1',
            12,
            new FormFieldFlags(readOnly: true, required: true),
        );

        self::assertStringContainsString('/Ff 131075', $annotation->render());
    }

    #[Test]
    public function it_renders_editable_combo_boxes(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $annotation = new ComboBoxAnnotation(
            7,
            $page,
            10,
            20,
            80,
            12,
            'country',
            ['de' => 'Deutschland'],
            'de',
            'F1',
            12,
            new FormFieldFlags(editable: true),
        );

        self::assertStringContainsString('/Ff 393216', $annotation->render());
    }

    #[Test]
    public function it_renders_a_default_value_for_combo_boxes(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $annotation = new ComboBoxAnnotation(
            7,
            $page,
            10,
            20,
            80,
            12,
            'country',
            ['de' => 'Deutschland', 'at' => 'Oesterreich'],
            'de',
            'F1',
            12,
            defaultValue: 'at',
        );

        self::assertStringContainsString('/V (de)', $annotation->render());
        self::assertStringContainsString('/DV (at)', $annotation->render());
    }

    #[Test]
    public function it_uses_the_text_color_and_omits_optional_values_when_not_provided(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $annotation = new ComboBoxAnnotation(
            7,
            $page,
            10,
            20,
            80,
            12,
            'country',
            ['de' => 'Deutschland'],
            null,
            'F1',
            12,
            null,
            Color::rgb(255, 0, 0),
        );

        self::assertStringContainsString('/DA (/F1 12 Tf 1 0 0 rg)', $annotation->render());
        self::assertStringNotContainsString('/V (', $annotation->render());
        self::assertStringNotContainsString('/DV (', $annotation->render());
        self::assertSame([], $annotation->getRelatedObjects());
    }

    #[Test]
    public function it_renders_accessibility_metadata_for_combo_boxes(): void
    {
        $document = $this->createPdfUaTestDocument();
        $page = $document->addPage();

        $annotation = new ComboBoxAnnotation(
            7,
            $page,
            10,
            20,
            80,
            12,
            'country',
            ['de' => 'Deutschland'],
            'de',
            'F1',
            12,
            tooltip: 'Country selection',
        );
        $annotation->withStructParent(1);

        self::assertStringContainsString('/StructParent 1', $annotation->render());
        self::assertStringContainsString('/TU (Country selection)', $annotation->render());
    }

    #[Test]
    public function it_can_render_string_entries_with_an_explicit_object_string_encryptor(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $annotation = new ComboBoxAnnotation(
            7,
            $page,
            10,
            20,
            80,
            12,
            'country',
            ['de' => 'Deutschland', 'at' => 'Oesterreich'],
            'de',
            'F1',
            12,
            defaultValue: 'at',
            tooltip: 'Country selection',
        );

        $rendered = $annotation->renderWithStringEncryptor(
            new ObjectStringEncryptor(
                new StandardObjectEncryptor(
                    new EncryptionProfile(EncryptionAlgorithm::RC4_128, 128, 2, 3),
                    new StandardSecurityHandlerData('', '', '1234567890123456', -4),
                ),
                7,
            ),
        );

        self::assertStringStartsWith("7 0 obj\n<< /Type /Annot /Subtype /Widget", $rendered);
        self::assertStringNotContainsString('(country)', $rendered);
        self::assertStringNotContainsString('(de)', $rendered);
        self::assertStringNotContainsString('(at)', $rendered);
        self::assertStringNotContainsString('(Deutschland)', $rendered);
        self::assertStringNotContainsString('(Oesterreich)', $rendered);
        self::assertStringNotContainsString('(Country selection)', $rendered);
    }
}
