<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;
use Kalle\Pdf\Document\Form\AcroForm;

/**
 * @internal Creates and reuses the document-wide AcroForm while guarding feature support.
 */
final readonly class DocumentAcroFormManager
{
    public function __construct(private Document $document)
    {
    }

    public function ensureAcroForm(): AcroForm
    {
        (new DocumentProfileGuard($this->document))->assertAllowsForms();

        return $this->ensureFormInstance();
    }

    public function ensureTextFieldAcroForm(): AcroForm
    {
        return $this->ensureCurrentImplementation(
            $this->document->getProfile()->supportsCurrentTextFieldImplementation(),
        );
    }

    public function ensureCheckboxAcroForm(): AcroForm
    {
        return $this->ensureCurrentImplementation(
            $this->document->getProfile()->supportsCurrentCheckboxImplementation(),
        );
    }

    public function ensurePushButtonAcroForm(): AcroForm
    {
        return $this->ensureCurrentImplementation(
            $this->document->getProfile()->supportsCurrentPushButtonImplementation(),
        );
    }

    public function ensureRadioButtonAcroForm(): AcroForm
    {
        return $this->ensureCurrentImplementation(
            $this->document->getProfile()->supportsCurrentRadioButtonImplementation(),
        );
    }

    public function ensureComboBoxAcroForm(): AcroForm
    {
        return $this->ensureCurrentImplementation(
            $this->document->getProfile()->supportsCurrentComboBoxImplementation(),
        );
    }

    public function ensureListBoxAcroForm(): AcroForm
    {
        return $this->ensureCurrentImplementation(
            $this->document->getProfile()->supportsCurrentListBoxImplementation(),
        );
    }

    public function ensureSignatureFieldAcroForm(): AcroForm
    {
        return $this->ensureCurrentImplementation(
            $this->document->getProfile()->supportsCurrentSignatureFieldImplementation(),
        );
    }

    private function ensureCurrentImplementation(bool $isSupported): AcroForm
    {
        if (!$isSupported) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s does not allow AcroForm fields in the current implementation.',
                $this->document->getProfile()->name(),
            ));
        }

        return $this->ensureFormInstance();
    }

    private function ensureFormInstance(): AcroForm
    {
        return $this->document->acroForm ??= new AcroForm($this->document->getUniqObjectId());
    }
}
