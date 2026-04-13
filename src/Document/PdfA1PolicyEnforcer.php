<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use DateTimeImmutable;
use Kalle\Pdf\Document\Form\AcroForm;
use Kalle\Pdf\Document\Metadata\IccProfile;

final readonly class PdfA1PolicyEnforcer
{
    public function __construct(
        private PdfA1ActionPolicy $actionPolicy = new PdfA1ActionPolicy(),
        private PdfA1MetadataConsistencyValidator $metadataConsistencyValidator = new PdfA1MetadataConsistencyValidator(),
        private DocumentMetadataInspector $metadataInspector = new DocumentMetadataInspector(),
    ) {
    }

    public function enforce(Document $document, ?AcroForm $acroForm = null, ?DateTimeImmutable $serializedAt = null): void
    {
        if (!$document->profile->isPdfA1()) {
            return;
        }

        foreach ($document->outlines as $outlineIndex => $outline) {
            $this->actionPolicy->assertOutlineAllowed($document, $outline, $outlineIndex);
        }

        foreach ($document->pages as $pageIndex => $page) {
            foreach ($page->annotations as $annotationIndex => $annotation) {
                $this->actionPolicy->assertAnnotationAllowed($document, $annotation, $pageIndex, $annotationIndex);
            }
        }

        foreach (($acroForm ?? $document->acroForm)->fields ?? [] as $field) {
            $this->actionPolicy->assertFormFieldAllowed($document, $field);
        }

        $this->metadataConsistencyValidator->assertConsistent($document, $serializedAt);

        $outputIntent = $this->metadataInspector->resolvePdfAOutputIntent($document);
        IccProfile::fromPath($outputIntent->iccProfilePath, $outputIntent->colorComponents)->assertPdfA1Compatible($outputIntent);
    }
}
