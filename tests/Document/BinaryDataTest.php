<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\BinaryData;
use Kalle\Pdf\Render\StringPdfOutput;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BinaryDataTest extends TestCase
{
    #[Test]
    public function it_keeps_string_data_in_a_reusable_buffer(): void
    {
        $data = BinaryData::fromString('hello');

        self::assertSame(5, $data->length());
        self::assertSame('hello', $data->contents());
        self::assertSame('hello', $data->contents());
    }

    #[Test]
    public function it_copies_file_data_into_its_own_buffer(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf-binary-data-');
        self::assertNotFalse($path);
        file_put_contents($path, 'original');

        try {
            $data = BinaryData::fromFile($path);
            file_put_contents($path, 'changed');

            self::assertSame(8, $data->length());
            self::assertSame('original', $data->contents());
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function it_writes_buffered_data_to_a_pdf_output(): void
    {
        $data = BinaryData::fromString('hello');
        $output = new StringPdfOutput();

        $data->writeTo($output);

        self::assertSame('hello', $output->contents());
    }
}
