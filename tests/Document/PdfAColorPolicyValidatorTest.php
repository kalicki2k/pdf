<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\Metadata\PdfAOutputIntent;
use Kalle\Pdf\Document\PdfAColorPolicyValidator;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Page\PageSize;
use PHPUnit\Framework\TestCase;

final class PdfAColorPolicyValidatorTest extends TestCase
{
    public function testItRejectsCmykGraphicsOperatorsForRgbOutputIntent(): void
    {
        $document = new Document(
            profile: Profile::pdfA1b(),
            title: 'Archive Copy',
            pages: [
                new Page(
                    PageSize::A4(),
                    contents: "0.1 0.2 0.3 0.4 k\n0 0 20 20 re\nf",
                ),
            ],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Profile PDF/A-1b does not allow CMYK color in graphics operations in page content stream on page 1 when the active PDF/A output intent is RGB.',
        );

        (new PdfAColorPolicyValidator())->assertDocumentColors($document);
    }

    public function testItAllowsGrayTextOperatorsForCmykOutputIntent(): void
    {
        $document = new Document(
            profile: Profile::pdfA1b(),
            title: 'Archive Copy',
            pdfaOutputIntent: PdfAOutputIntent::defaultCmyk(),
            pages: [
                new Page(
                    PageSize::A4(),
                    contents: "BT\n0.2 g\n/F1 12 Tf\n10 20 Td\n(Gray) Tj\nET",
                ),
            ],
        );

        (new PdfAColorPolicyValidator())->assertDocumentColors($document);
        self::assertTrue(true);
    }

    public function testItRejectsCmykPageBackgroundForRgbOutputIntent(): void
    {
        $document = new Document(
            profile: Profile::pdfA1b(),
            title: 'Archive Copy',
            pages: [
                new Page(
                    PageSize::A4(),
                    backgroundColor: Color::cmyk(0.1, 0.2, 0.3, 0.4),
                ),
            ],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Profile PDF/A-1b does not allow CMYK color in page background graphics on page 1 when the active PDF/A output intent is RGB.',
        );

        (new PdfAColorPolicyValidator())->assertDocumentColors($document);
    }
}
