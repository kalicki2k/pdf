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
    public function testItIgnoresColorLikeTokensInsideNamesCommentsAndStrings(): void
    {
        $document = new Document(
            profile: Profile::pdfA1b(),
            title: 'Archive Copy',
            pages: [
                new Page(
                    PageSize::A4(),
                    contents: implode("\n", [
                        '% 0.1 0.2 0.3 0.4 k',
                        '/RG << /k true /rg false >> BDC',
                        'BT',
                        '(rg k RG) Tj',
                        '<7267206b205247> Tj',
                        'ET',
                        'EMC',
                    ]),
                ),
            ],
        );

        (new PdfAColorPolicyValidator())->assertDocumentColors($document);
        self::assertTrue(true);
    }

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

    public function testItAllowsGrayGraphicsOperatorsForRgbOutputIntent(): void
    {
        $document = new Document(
            profile: Profile::pdfA1b(),
            title: 'Archive Copy',
            pages: [
                new Page(
                    PageSize::A4(),
                    contents: "0.2 G\n0 0 m\n20 20 l\nS",
                ),
            ],
        );

        (new PdfAColorPolicyValidator())->assertDocumentColors($document);
        self::assertTrue(true);
    }

    public function testItRejectsCmykTextOperatorsInsideMixedContentStreamsForRgbOutputIntent(): void
    {
        $document = new Document(
            profile: Profile::pdfA1b(),
            title: 'Archive Copy',
            pages: [
                new Page(
                    PageSize::A4(),
                    contents: implode("\n", [
                        '0.1 0.2 0.3 rg',
                        '0 0 20 20 re',
                        'f',
                        'BT',
                        '0.1 0.2 0.3 0.4 k',
                        '/F1 12 Tf',
                        '10 20 Td',
                        '(Mixed) Tj',
                        'ET',
                    ]),
                ),
            ],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Profile PDF/A-1b does not allow CMYK color in text operations in page content stream on page 1 when the active PDF/A output intent is RGB.',
        );

        (new PdfAColorPolicyValidator())->assertDocumentColors($document);
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
