<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use function dirname;
use function sprintf;

use InvalidArgumentException;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\DocumentSerializationPlanBuilder;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Encryption\Encryption;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Image\ImageAccessibility;
use Kalle\Pdf\Image\ImageColorSpace;
use Kalle\Pdf\Image\ImagePlacement;
use Kalle\Pdf\Image\ImageSource;
use Kalle\Pdf\Text\TextOptions;
use PHPUnit\Framework\TestCase;

final class PdfA1PolicyMatrixTest extends TestCase
{
    public function testPdfA1ProfilesRejectTheCurrentUnsupportedFeatureMatrix(): void
    {
        $builder = new DocumentSerializationPlanBuilder();

        foreach ([Profile::pdfA1a(), Profile::pdfA1b()] as $profile) {
            foreach ($this->unsupportedPdfA1FeatureMatrix($profile) as $scenario => $message) {
                try {
                    $builder->build($this->unsupportedPdfA1Document($profile, $scenario));
                    self::fail(sprintf(
                        'Expected %s to reject scenario "%s".',
                        $profile->name(),
                        $scenario,
                    ));
                } catch (InvalidArgumentException $exception) {
                    self::assertSame(sprintf($message, $profile->name()), $exception->getMessage());
                }
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function unsupportedPdfA1FeatureMatrix(Profile $profile): array
    {
        $matrix = [
            'attachment' => 'Profile %s does not allow embedded file attachments.',
            'encryption' => 'Profile %s does not allow encryption.',
            'indexed-image' => 'Profile %s does not allow custom image color space definitions in the current implementation for image resource 1 on page 1.',
            'soft-mask-image' => 'Profile %s does not allow soft-mask image transparency for image resource 1 on page 1.',
            'uri-link' => 'Profile %s does not allow URI annotation actions in link annotation 1 on page 1. Use an internal /Dest target instead.',
        ];

        if ($profile->pdfaConformance() === 'A') {
            $matrix['popup'] = 'Profile %s does not allow popup related objects for page annotation 1 on page 1.';
        } else {
            $matrix['acroform'] = 'Profile %s does not allow AcroForm fields in the current implementation.';
            $matrix['text-annotation'] = 'Profile %s does not support the current page annotation implementation on page 1.';
        }

        return $matrix;
    }

    private function unsupportedPdfA1Document(Profile $profile, string $scenario): Document
    {
        $builder = $this->pdfA1BaselineBuilder($profile);

        return match ($scenario) {
            'attachment' => $builder
                ->attachment('demo.txt', 'hello')
                ->build(),
            'acroform' => $builder
                ->textField('customer_name', 72, 640, 140, 18, 'Ada', 'Customer name')
                ->build(),
            'encryption' => $builder
                ->encryption(Encryption::rc4_128('user', 'owner'))
                ->build(),
            'text-annotation' => $builder
                ->textAnnotation(40, 500, 18, 18, 'Kommentar', 'QA', 'Comment', true)
                ->build(),
            'indexed-image' => $builder
                ->image(
                    ImageSource::indexed('palette-data', 1, 1, 8, "\x80\x80\x80"),
                    ImagePlacement::at(10, 20),
                    ImageAccessibility::alternativeText('Indexed image'),
                )
                ->build(),
            'popup' => $builder
                ->textAnnotation(40, 500, 18, 18, 'Kommentar', 'QA')
                ->popupAnnotation(70, 520, 120, 60, true)
                ->build(),
            'soft-mask-image' => $builder
                ->image(
                    ImageSource::flate(
                        'rgb-data',
                        2,
                        1,
                        ImageColorSpace::RGB,
                        softMask: ImageSource::alphaMask('alpha-data', 2, 1),
                    ),
                    ImagePlacement::at(10, 20),
                    ImageAccessibility::alternativeText('Transparent image'),
                )
                ->build(),
            'uri-link' => $builder
                ->link('https://example.com', 40, 500, 120, 16, 'Open Example')
                ->build(),
            default => throw new InvalidArgumentException(sprintf('Unknown PDF/A-1 scenario "%s".', $scenario)),
        };
    }

    private function pdfA1BaselineBuilder(Profile $profile): DefaultDocumentBuilder
    {
        return DefaultDocumentBuilder::make()
            ->profile($profile)
            ->title('Archive Copy')
            ->language('de-DE')
            ->text('Baseline Absatz Привет', TextOptions::make(
                x: 72,
                y: 720,
                width: 320,
                fontSize: 12,
                lineHeight: 16,
                embeddedFont: EmbeddedFontSource::fromPath($this->fontPath()),
            ));
    }

    private function fontPath(): string
    {
        return dirname(__DIR__, 2) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf';
    }
}
