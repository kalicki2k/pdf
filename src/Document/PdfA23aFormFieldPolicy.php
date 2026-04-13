<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use function sprintf;

use Kalle\Pdf\Document\Form\CheckboxField;
use Kalle\Pdf\Document\Form\ComboBoxField;
use Kalle\Pdf\Document\Form\FormField;
use Kalle\Pdf\Document\Form\ListBoxField;
use Kalle\Pdf\Document\Form\RadioButtonGroup;
use Kalle\Pdf\Document\Form\TextField;

final class PdfA23aFormFieldPolicy
{
    public function supports(FormField $field): bool
    {
        return $field instanceof TextField
            || $field instanceof CheckboxField
            || $field instanceof RadioButtonGroup
            || $field instanceof ComboBoxField
            || $field instanceof ListBoxField;
    }

    public function violationMessage(Profile $profile): string
    {
        return sprintf(
            'Profile %s only allows tagged text fields, checkboxes, radio buttons and choice fields in the current PDF/A-%dA form policy.',
            $profile->name(),
            $profile->pdfaPart(),
        );
    }
}
