<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Document;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AcroFormTest extends TestCase
{
    #[Test]
    public function it_renders_an_acro_form_with_registered_fields_and_fonts(): void
    {
        $document = new Document(version: 1.4);
        $document->registerFont('Helvetica');
        $page = $document->addPage();
        $page->addTextField('customer_name', 10, 20, 100, 20, 'Ada', 'Helvetica', 12);

        $acroForm = $document->acroForm;

        self::assertNotNull($acroForm);
        self::assertStringContainsString('/Fields [9 0 R]', $acroForm->render());
        self::assertStringContainsString('/NeedAppearances true', $acroForm->render());
        self::assertStringContainsString('/DR << /Font << /F1 4 0 R >> >>', $acroForm->render());
    }
}
