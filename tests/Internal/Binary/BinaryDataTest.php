<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Binary;

use Kalle\Pdf\Binary\BinaryData;
use Kalle\Pdf\Render\StringPdfOutput;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BinaryDataTest extends TestCase
{
    #[Test]
    public function it_keeps_string_data_reusable(): void
    {
        $data = BinaryData::fromString('hello');

        self::assertSame(5, $data->length());
        self::assertSame('hello', $data->contents());
        self::assertSame('hello', $data->contents());
    }

    #[Test]
    public function it_reads_file_data_from_its_source_when_requested(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf-binary-data-');
        self::assertNotFalse($path);
        file_put_contents($path, 'original');

        try {
            $data = BinaryData::fromFile($path);
            file_put_contents($path, 'changed');

            self::assertSame(7, $data->length());
            self::assertSame('changed', $data->contents());
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function it_writes_string_data_to_a_pdf_output(): void
    {
        $data = BinaryData::fromString('hello');
        $output = new StringPdfOutput();

        $data->writeTo($output);

        self::assertSame('hello', $output->contents());
    }

    #[Test]
    public function it_writes_stream_data_without_changing_the_caller_position(): void
    {
        $stream = fopen('php://temp', 'w+b');
        self::assertNotFalse($stream);
        fwrite($stream, 'hello');
        fseek($stream, 2);

        $data = BinaryData::fromStream($stream);
        $output = new StringPdfOutput();

        $data->writeTo($output);

        self::assertSame('hello', $output->contents());
        self::assertSame(2, ftell($stream));
        self::assertSame('hello', $data->contents());
        self::assertSame(2, ftell($stream));

        fclose($stream);
    }

    #[Test]
    public function it_can_reuse_non_seekable_stream_data_via_a_temp_buffer(): void
    {
        $streams = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        if ($streams === false) {
            self::markTestSkipped('stream_socket_pair is not available.');
        }

        [$reader, $writer] = $streams;

        fwrite($writer, 'hello');
        fclose($writer);

        $data = BinaryData::fromStream($reader, closeOnDestruct: true);
        $output = new StringPdfOutput();

        $data->writeTo($output);

        self::assertSame(5, $data->length());
        self::assertSame('hello', $output->contents());
        self::assertSame('hello', $data->contents());

        unset($data);

        self::assertFalse(is_resource($reader));
    }
}
