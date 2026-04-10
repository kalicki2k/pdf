<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Page\Form;

use Kalle\Pdf\Internal\Page\Form\FormFieldFlags;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FormFieldFlagsTest extends TestCase
{
    #[Test]
    public function it_returns_zero_when_no_flags_are_enabled(): void
    {
        $flags = new FormFieldFlags();

        self::assertSame(0, $flags->toPdfFlags());
    }

    #[Test]
    public function it_combines_text_field_flags(): void
    {
        $flags = new FormFieldFlags(readOnly: true, required: true, password: true);

        self::assertSame(12291, $flags->toPdfFlags(multiline: true));
    }

    #[Test]
    public function it_combines_combo_box_flags_and_only_enables_editable_with_combo(): void
    {
        $flags = new FormFieldFlags(editable: true);

        self::assertSame(393216, $flags->toPdfFlags(combo: true));
        self::assertSame(0, $flags->toPdfFlags());
    }

    #[Test]
    public function it_combines_list_box_flags_and_only_enables_multi_select_for_list_boxes(): void
    {
        $flags = new FormFieldFlags(multiSelect: true);

        self::assertSame(2097152, $flags->toPdfFlags(listBox: true));
        self::assertSame(0, $flags->toPdfFlags());
    }
}
