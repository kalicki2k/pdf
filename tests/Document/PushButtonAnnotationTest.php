<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Feature\Action\GoToAction;
use Kalle\Pdf\Feature\Action\GoToRemoteAction;
use Kalle\Pdf\Feature\Action\HideAction;
use Kalle\Pdf\Feature\Action\ImportDataAction;
use Kalle\Pdf\Feature\Action\JavaScriptAction;
use Kalle\Pdf\Feature\Action\LaunchAction;
use Kalle\Pdf\Feature\Action\NamedAction;
use Kalle\Pdf\Feature\Action\ResetFormAction;
use Kalle\Pdf\Feature\Action\SetOcgStateAction;
use Kalle\Pdf\Feature\Action\SubmitFormAction;
use Kalle\Pdf\Feature\Action\ThreadAction;
use Kalle\Pdf\Feature\Action\UriAction;
use Kalle\Pdf\Feature\Annotation\PushButtonAnnotation;
use Kalle\Pdf\Feature\Form\FormFieldTextAppearanceStream;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Font\StandardFontName;
use Kalle\Pdf\Font\UnicodeFontWidthUpdater;
use Kalle\Pdf\Graphics\Color;
use Kalle\Pdf\Internal\Document\Document;
use Kalle\Pdf\Internal\Encryption\Object\ObjectStringEncryptor;
use Kalle\Pdf\Internal\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\Internal\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Internal\Encryption\Standard\StandardSecurityHandlerData;
use Kalle\Pdf\Internal\Security\EncryptionAlgorithm;
use Kalle\Pdf\Profile;
use Kalle\Pdf\Tests\Support\CreatesPdfUaTestDocument;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PushButtonAnnotationTest extends TestCase
{
    use CreatesPdfUaTestDocument;

    #[Test]
    public function it_renders_a_push_button_widget_annotation(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $annotation = new PushButtonAnnotation(7, $page, 10, 20, 80, 16, 'save_form', 'Speichern', 'F1', 12);

        self::assertSame(
            "7 0 obj\n"
            . "<< /Type /Annot /Subtype /Widget /FT /Btn /Rect [10 20 90 36] /Border [0 0 1] /P 5 0 R /T (save_form) /Ff 65536 /DA (/F1 12 Tf 0 g) /MK << /CA (Speichern) >> >>\n"
            . "endobj\n",
            $annotation->render(),
        );
    }

    #[Test]
    public function it_renders_accessibility_metadata_for_push_buttons(): void
    {
        $document = $this->createPdfUaTestDocument();
        $page = $document->addPage();

        $annotation = new PushButtonAnnotation(7, $page, 10, 20, 80, 16, 'save_form', 'Speichern', 'F1', 12, action: null, tooltip: 'Save form');
        $annotation->withStructParent(1);

        self::assertStringContainsString('/StructParent 1', $annotation->render());
        self::assertStringContainsString('/TU (Save form)', $annotation->render());
    }

    #[Test]
    public function it_renders_an_appearance_stream_for_push_buttons(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();
        $font = new StandardFont(9, StandardFontName::HELVETICA, 'Type1', 'WinAnsiEncoding', 1.4);

        $annotation = new PushButtonAnnotation(
            7,
            $page,
            10,
            20,
            80,
            16,
            'save_form',
            'Speichern',
            'F1',
            12,
            appearance: new FormFieldTextAppearanceStream(8, 80, 16, $font, new UnicodeFontWidthUpdater(), 'F1', 12, ['Speichern']),
        );

        self::assertStringContainsString('/AP << /N 8 0 R >>', $annotation->render());
        self::assertCount(1, $annotation->getRelatedObjects());
    }

    #[Test]
    public function it_renders_a_push_button_with_a_submit_action(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $annotation = new PushButtonAnnotation(
            7,
            $page,
            10,
            20,
            80,
            16,
            'save_form',
            'Speichern',
            'F1',
            12,
            action: new SubmitFormAction('https://example.com/submit'),
        );

        self::assertStringContainsString('/A << /S /SubmitForm /F (https://example.com/submit) >>', $annotation->render());
    }

    #[Test]
    public function it_renders_a_push_button_with_a_reset_action(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $annotation = new PushButtonAnnotation(
            7,
            $page,
            10,
            20,
            80,
            16,
            'reset_form',
            'Zuruecksetzen',
            'F1',
            12,
            action: new ResetFormAction(),
        );

        self::assertStringContainsString('/A << /S /ResetForm >>', $annotation->render());
    }

    #[Test]
    public function it_renders_a_push_button_with_a_javascript_action(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $annotation = new PushButtonAnnotation(
            7,
            $page,
            10,
            20,
            80,
            16,
            'validate_form',
            'Pruefen',
            'F1',
            12,
            action: new JavaScriptAction("app.alert('Hallo');"),
        );

        self::assertStringContainsString("/A << /S /JavaScript /JS (app.alert\\('Hallo'\\);) >>", $annotation->render());
    }

    #[Test]
    public function it_renders_a_push_button_with_a_named_action(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $annotation = new PushButtonAnnotation(
            7,
            $page,
            10,
            20,
            80,
            16,
            'prev_page',
            'Zurueck',
            'F1',
            12,
            action: new NamedAction('PrevPage'),
        );

        self::assertStringContainsString('/A << /S /Named /N /PrevPage >>', $annotation->render());
    }

    #[Test]
    public function it_renders_a_push_button_with_a_goto_action(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $annotation = new PushButtonAnnotation(
            7,
            $page,
            10,
            20,
            80,
            16,
            'goto_table',
            'Zur Tabelle',
            'F1',
            12,
            action: new GoToAction('table-demo'),
        );

        self::assertStringContainsString('/A << /S /GoTo /D /table-demo >>', $annotation->render());
    }

    #[Test]
    public function it_renders_a_push_button_with_a_goto_remote_action(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $annotation = new PushButtonAnnotation(
            7,
            $page,
            10,
            20,
            80,
            16,
            'open_remote',
            'Extern',
            'F1',
            12,
            action: new GoToRemoteAction('guide.pdf', 'chapter-1'),
        );

        self::assertStringContainsString('/A << /S /GoToR /F (guide.pdf) /D /chapter-1 >>', $annotation->render());
    }

    #[Test]
    public function it_renders_a_push_button_with_a_launch_action(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $annotation = new PushButtonAnnotation(
            7,
            $page,
            10,
            20,
            80,
            16,
            'open_file',
            'Datei',
            'F1',
            12,
            action: new LaunchAction('guide.pdf'),
        );

        self::assertStringContainsString('/A << /S /Launch /F (guide.pdf) >>', $annotation->render());
    }

    #[Test]
    public function it_renders_a_push_button_with_a_uri_action(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $annotation = new PushButtonAnnotation(
            7,
            $page,
            10,
            20,
            80,
            16,
            'open_site',
            'Website',
            'F1',
            12,
            action: new UriAction('https://example.com'),
        );

        self::assertStringContainsString('/A << /S /URI /URI (https://example.com) >>', $annotation->render());
    }

    #[Test]
    public function it_renders_a_push_button_with_a_hide_action(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $annotation = new PushButtonAnnotation(
            7,
            $page,
            10,
            20,
            80,
            16,
            'hide_notes',
            'Ausblenden',
            'F1',
            12,
            action: new HideAction('notes_panel'),
        );

        self::assertStringContainsString('/A << /S /Hide /T (notes_panel) >>', $annotation->render());
    }

    #[Test]
    public function it_renders_a_push_button_with_an_import_data_action(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $annotation = new PushButtonAnnotation(
            7,
            $page,
            10,
            20,
            80,
            16,
            'import_data',
            'Import',
            'F1',
            12,
            action: new ImportDataAction('form-data.fdf'),
        );

        self::assertStringContainsString('/A << /S /ImportData /F (form-data.fdf) >>', $annotation->render());
    }

    #[Test]
    public function it_renders_a_push_button_with_a_set_ocg_state_action(): void
    {
        $document = new Document(profile: Profile::standard(1.5));
        $document->registerFont('Helvetica');
        $page = $document->addPage();
        $layer = $document->addLayer('LayerA');

        $annotation = new PushButtonAnnotation(
            7,
            $page,
            10,
            20,
            80,
            16,
            'toggle_layer',
            'Layer',
            'F1',
            12,
            action: new SetOcgStateAction(['Toggle', $layer], false),
        );

        self::assertStringContainsString('/A << /S /SetOCGState /State [/Toggle 8 0 R] /PreserveRB false >>', $annotation->render());
    }

    #[Test]
    public function it_renders_a_push_button_with_a_thread_action(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $annotation = new PushButtonAnnotation(
            7,
            $page,
            10,
            20,
            80,
            16,
            'open_thread',
            'Thread',
            'F1',
            12,
            action: new ThreadAction('article-1', 'threads.pdf'),
        );

        self::assertStringContainsString('/A << /S /Thread /D (article-1) /F (threads.pdf) >>', $annotation->render());
    }

    #[Test]
    public function it_uses_the_text_color_and_has_no_related_objects(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $annotation = new PushButtonAnnotation(
            7,
            $page,
            10,
            20,
            80,
            16,
            'save_form',
            'Speichern',
            'F1',
            12,
            Color::rgb(255, 0, 0),
        );

        self::assertStringContainsString('/DA (/F1 12 Tf 1 0 0 rg)', $annotation->render());
        self::assertSame([], $annotation->getRelatedObjects());
    }

    #[Test]
    public function it_can_render_string_entries_with_an_explicit_object_string_encryptor(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $annotation = new PushButtonAnnotation(
            7,
            $page,
            10,
            20,
            80,
            16,
            'save_form',
            'Speichern',
            'F1',
            12,
            tooltip: 'Save form',
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
        self::assertStringNotContainsString('(save_form)', $rendered);
        self::assertStringNotContainsString('(Speichern)', $rendered);
        self::assertStringNotContainsString('(Save form)', $rendered);
    }
}
