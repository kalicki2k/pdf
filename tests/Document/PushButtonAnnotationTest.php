<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\GoToAction;
use Kalle\Pdf\Document\GoToRemoteAction;
use Kalle\Pdf\Document\HideAction;
use Kalle\Pdf\Document\ImportDataAction;
use Kalle\Pdf\Document\JavaScriptAction;
use Kalle\Pdf\Document\LaunchAction;
use Kalle\Pdf\Document\NamedAction;
use Kalle\Pdf\Document\PushButtonAnnotation;
use Kalle\Pdf\Document\ResetFormAction;
use Kalle\Pdf\Document\SetOcgStateAction;
use Kalle\Pdf\Document\SubmitFormAction;
use Kalle\Pdf\Document\ThreadAction;
use Kalle\Pdf\Document\UriAction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PushButtonAnnotationTest extends TestCase
{
    #[Test]
    public function it_renders_a_push_button_widget_annotation(): void
    {
        $document = new Document(version: 1.4);
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
    public function it_renders_a_push_button_with_a_submit_action(): void
    {
        $document = new Document(version: 1.4);
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
        $document = new Document(version: 1.4);
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
        $document = new Document(version: 1.4);
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
        $document = new Document(version: 1.4);
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
        $document = new Document(version: 1.4);
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
        $document = new Document(version: 1.4);
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
        $document = new Document(version: 1.4);
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
        $document = new Document(version: 1.4);
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
        $document = new Document(version: 1.4);
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
        $document = new Document(version: 1.4);
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
        $document = new Document(version: 1.4);
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
        $document = new Document(version: 1.4);
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
}
