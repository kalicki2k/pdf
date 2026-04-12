<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Document\Form\ComboBoxField;
use Kalle\Pdf\Document\Form\FormField;
use Kalle\Pdf\Document\Form\ListBoxField;
use Kalle\Pdf\Document\Form\TextField;

final class PdfA1aFormFieldPolicy
{
    public function supports(FormField $field): bool
    {
        return $field instanceof TextField
            || $field instanceof ComboBoxField
            || $field instanceof ListBoxField;
    }

    public function violationMessage(Profile $profile): string
    {
        return sprintf(
            'Profile %s only allows text and choice fields in the PDF/A-1a form policy.',
            $profile->name(),
        );
    }
}
