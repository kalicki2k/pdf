<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Page\Annotation;

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Encryption\Object\ObjectStringEncryptor;
use Kalle\Pdf\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Encryption\Standard\StandardSecurityHandlerData;
use Kalle\Pdf\Page\Annotation\ListBoxAnnotation;
use Kalle\Pdf\Page\Form\FormFieldFlags;
use Kalle\Pdf\Profile\Profile;
use Kalle\Pdf\Security\EncryptionAlgorithm;
use Kalle\Pdf\Style\Color;
use Kalle\Pdf\Tests\Support\CreatesPdfUaTestDocument;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ListBoxAnnotationTest extends TestCase
{
    use CreatesPdfUaTestDocument;

    #[Test]
    public function it_renders_a_list_box_widget_annotation(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $annotation = new ListBoxAnnotation(
            7,
            $page,
            10,
            20,
            80,
            40,
            'topics',
            ['pdf' => 'PDF', 'forms' => 'Forms', 'tables' => 'Tables'],
            'forms',
            'F1',
            12,
        );

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Widget /FT /Ch /Rect [10 20 90 60] /Border [0 0 1] /P 5 0 R /T (topics) /DA (/F1 12 Tf 0 g) /Opt [[(pdf) (PDF)] [(forms) (Forms)] [(tables) (Tables)]] /V (forms) >>\n"
            . "endobj\n",
            $annotation->render(),
        );
    }

    #[Test]
    public function it_renders_read_only_and_required_flags_for_list_boxes(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $annotation = new ListBoxAnnotation(
            7,
            $page,
            10,
            20,
            80,
            40,
            'topics',
            ['pdf' => 'PDF'],
            'pdf',
            'F1',
            12,
            new FormFieldFlags(readOnly: true, required: true),
        );

        self::assertStringContainsString('/Ff 3', $annotation->render());
    }

    #[Test]
    public function it_renders_a_multi_select_list_box_widget_annotation(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $annotation = new ListBoxAnnotation(
            7,
            $page,
            10,
            20,
            80,
            40,
            'topics',
            ['pdf' => 'PDF', 'forms' => 'Forms', 'tables' => 'Tables'],
            ['pdf', 'forms'],
            'F1',
            12,
            new FormFieldFlags(multiSelect: true),
        );

        self::assertStringContainsString('/Ff 2097152', $annotation->render());
        self::assertStringContainsString('/V [(pdf) (forms)]', $annotation->render());
    }

    #[Test]
    public function it_renders_a_default_value_for_list_boxes(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $annotation = new ListBoxAnnotation(
            7,
            $page,
            10,
            20,
            80,
            40,
            'topics',
            ['pdf' => 'PDF', 'forms' => 'Forms'],
            'forms',
            'F1',
            12,
            defaultValue: 'pdf',
        );

        self::assertStringContainsString('/V (forms)', $annotation->render());
        self::assertStringContainsString('/DV (pdf)', $annotation->render());
    }

    #[Test]
    public function it_renders_a_multi_select_default_value_for_list_boxes(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $annotation = new ListBoxAnnotation(
            7,
            $page,
            10,
            20,
            80,
            40,
            'topics',
            ['pdf' => 'PDF', 'forms' => 'Forms'],
            ['forms'],
            'F1',
            12,
            new FormFieldFlags(multiSelect: true),
            defaultValue: ['pdf', 'forms'],
        );

        self::assertStringContainsString('/DV [(pdf) (forms)]', $annotation->render());
    }

    #[Test]
    public function it_uses_the_text_color_and_omits_optional_values_when_not_provided(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $annotation = new ListBoxAnnotation(
            7,
            $page,
            10,
            20,
            80,
            40,
            'topics',
            ['pdf' => 'PDF'],
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
    public function it_renders_accessibility_metadata_for_list_boxes(): void
    {
        $document = $this->createPdfUaTestDocument();
        $page = $document->addPage();

        $annotation = new ListBoxAnnotation(
            7,
            $page,
            10,
            20,
            80,
            40,
            'topics',
            ['pdf' => 'PDF'],
            'pdf',
            'F1',
            12,
            tooltip: 'Topics selection',
        );
        $annotation->withStructParent(1);

        self::assertStringContainsString('/StructParent 1', $annotation->render());
        self::assertStringContainsString('/TU (Topics selection)', $annotation->render());
    }

    #[Test]
    public function it_can_render_string_entries_with_an_explicit_object_string_encryptor(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $annotation = new ListBoxAnnotation(
            7,
            $page,
            10,
            20,
            80,
            40,
            'topics',
            ['pdf' => 'PDF', 'forms' => 'Forms'],
            ['forms'],
            'F1',
            12,
            new FormFieldFlags(multiSelect: true),
            defaultValue: ['pdf', 'forms'],
            tooltip: 'Topics selection',
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
        self::assertStringNotContainsString('(topics)', $rendered);
        self::assertStringNotContainsString('(pdf)', $rendered);
        self::assertStringNotContainsString('(forms)', $rendered);
        self::assertStringNotContainsString('(PDF)', $rendered);
        self::assertStringNotContainsString('(Forms)', $rendered);
        self::assertStringNotContainsString('(Topics selection)', $rendered);
    }
}
