<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Form;

use InvalidArgumentException;
use Kalle\Pdf\Form\FormFieldLabel;
use Kalle\Pdf\Geometry\Position;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FormFieldLabelTest extends TestCase
{
    #[Test]
    public function it_requires_non_empty_text_and_font_information(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Form field label text must not be empty.');

        new FormFieldLabel('', new Position(10, 20), 'Helvetica', 10);
    }

    #[Test]
    public function it_requires_a_positive_font_size(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Form field label font size must be greater than zero.');

        new FormFieldLabel('Customer name', new Position(10, 20), 'Helvetica', 0);
    }
}
