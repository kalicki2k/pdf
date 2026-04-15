<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Document\TaggedPageAnnotationResolver;
use Kalle\Pdf\Page\AnnotationAppearanceRenderContext;
use Kalle\Pdf\Page\AppearanceStreamAnnotation;
use Kalle\Pdf\Page\HighlightAnnotation;
use Kalle\Pdf\Page\LinkAnnotation;
use Kalle\Pdf\Page\LinkTarget;
use Kalle\Pdf\Page\PageAnnotation;
use Kalle\Pdf\Page\PageAnnotationRenderContext;
use Kalle\Pdf\Page\PdfUaTaggedPageAnnotation;
use PHPUnit\Framework\TestCase;

final class TaggedPageAnnotationResolverTest extends TestCase
{
    public function testItRejectsTaggedPageAnnotationsForProfilesThatDoNotRequireThem(): void
    {
        $resolver = new TaggedPageAnnotationResolver();
        $document = new Document(profile: Profile::standard());
        $annotation = new TaggedPdfUaTestAnnotation();

        self::assertFalse($resolver->supports($document, $annotation));
        self::assertNull($resolver->altText($document, $annotation));
        self::assertNull($resolver->structureTag($document, $annotation));
    }

    public function testItRejectsLinkAnnotationsInTaggedPageAnnotationPath(): void
    {
        $resolver = new TaggedPageAnnotationResolver();
        $document = new Document(profile: Profile::pdfUa1());
        $annotation = new LinkAnnotation(LinkTarget::externalUrl('https://example.com'), 10, 10, 20, 10, accessibleLabel: 'Docs');

        self::assertFalse($resolver->supports($document, $annotation));
        self::assertNull($resolver->altText($document, $annotation));
        self::assertNull($resolver->structureTag($document, $annotation));
    }

    public function testItUsesPdfUaTaggedAnnotationMetadataForPdfUaProfiles(): void
    {
        $resolver = new TaggedPageAnnotationResolver();
        $document = new Document(profile: Profile::pdfUa1());
        $annotation = new TaggedPdfUaTestAnnotation(altText: 'Demo alt', structureTag: 'Annot');

        self::assertTrue($resolver->supports($document, $annotation));
        self::assertSame('Demo alt', $resolver->altText($document, $annotation));
        self::assertSame('Annot', $resolver->structureTag($document, $annotation));
    }

    public function testItRequiresExplicitPdfA1aPolicySupport(): void
    {
        $resolver = new TaggedPageAnnotationResolver();
        $document = new Document(profile: Profile::pdfA1a());
        $annotation = new UnsupportedTaggedPdfA1aTestAnnotation();

        self::assertFalse($resolver->supports($document, $annotation));
        self::assertNull($resolver->altText($document, $annotation));
        self::assertNull($resolver->structureTag($document, $annotation));
    }

    public function testItUsesPdfA1aPolicyMetadataForSupportedAnnotations(): void
    {
        $resolver = new TaggedPageAnnotationResolver();
        $document = new Document(profile: Profile::pdfA1a());
        $annotation = new HighlightAnnotation(10, 10, 20, 10, contents: 'Review comment');

        self::assertTrue($resolver->supports($document, $annotation));
        self::assertSame('Review comment', $resolver->altText($document, $annotation));
        self::assertSame('Annot', $resolver->structureTag($document, $annotation));
    }
}

final readonly class TaggedPdfUaTestAnnotation implements PageAnnotation, PdfUaTaggedPageAnnotation
{
    public function __construct(
        private ?string $altText = 'Demo alt',
        private string $structureTag = 'Annot',
    ) {
    }

    public function pdfObjectContents(PageAnnotationRenderContext $context): string
    {
        return '<< /Type /Annot /Subtype /Text >>';
    }

    public function markedContentId(): ?int
    {
        return null;
    }

    public function taggedAnnotationAltText(): ?string
    {
        return $this->altText;
    }

    public function taggedAnnotationStructureTag(): string
    {
        return $this->structureTag;
    }
}

final readonly class UnsupportedTaggedPdfA1aTestAnnotation implements AppearanceStreamAnnotation, PageAnnotation, PdfUaTaggedPageAnnotation
{
    public function pdfObjectContents(PageAnnotationRenderContext $context): string
    {
        return '<< /Type /Annot /Subtype /Text /AP << /N 1 0 R >> >>';
    }

    public function markedContentId(): ?int
    {
        return null;
    }

    public function taggedAnnotationAltText(): ?string
    {
        return 'Unsupported alt';
    }

    public function taggedAnnotationStructureTag(): string
    {
        return 'Annot';
    }

    public function appearanceStreamDictionaryContents(?AnnotationAppearanceRenderContext $context = null): string
    {
        return '<< /Type /XObject /Subtype /Form /FormType 1 /BBox [0 0 10 10] /Resources << >> /Length 0 >>';
    }

    public function appearanceStreamContents(?AnnotationAppearanceRenderContext $context = null): string
    {
        return '';
    }
}
