<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Support;

use Kalle\Pdf\Document;
use Kalle\Pdf\Profile\Profile;

trait CreatesPdfUaTestDocument
{
    private function createPdfUaTestDocument(
        ?string $title = 'Accessible Spec',
        ?string $language = 'de-DE',
        bool $registerBold = false,
    ): Document {
        $document = new Document(
            profile: Profile::pdfUa1(),
            title: $title,
            language: $language,
            fontConfig: self::pdfUaFontConfig(),
        );
        $document->registerFont(self::pdfUaRegularFont());

        if ($registerBold) {
            $document->registerFont(self::pdfUaBoldFont());
        }

        return $document;
    }

    private static function pdfUaRegularFont(): string
    {
        return 'NotoSans-Regular';
    }

    private static function pdfUaBoldFont(): string
    {
        return 'NotoSans-Bold';
    }

    /**
     * @return list<array{
     *     baseFont: string,
     *     path: string,
     *     unicode: true,
     *     subtype: 'CIDFontType2',
     *     encoding: 'Identity-H'
     * }>
     */
    private static function pdfUaFontConfig(): array
    {
        return [
            [
                'baseFont' => self::pdfUaRegularFont(),
                'path' => __DIR__ . '/../../assets/fonts/NotoSans-Regular.ttf',
                'unicode' => true,
                'subtype' => 'CIDFontType2',
                'encoding' => 'Identity-H',
            ],
            [
                'baseFont' => self::pdfUaBoldFont(),
                'path' => __DIR__ . '/../../assets/fonts/NotoSans-Bold.ttf',
                'unicode' => true,
                'subtype' => 'CIDFontType2',
                'encoding' => 'Identity-H',
            ],
        ];
    }
}
