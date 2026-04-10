<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Binary;

use Kalle\Pdf\Binary\BinaryData;
use Kalle\Pdf\Render\StringPdfOutput;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BinaryDataTest extends TestCase
{
    #[Test]
    public function it_keeps_string_data_reusable(): void
    {
        $data = BinaryData::fromString('hello');

        self::assertSame(5, $data->length());
        self::assertSame('hello', $data->slice(0, $data->length()));
        self::assertSame('hello', $data->slice(0, $data->length()));
        self::assertSame('ell', $data->slice(1, 3));
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
            self::assertSame('changed', $data->slice(0, $data->length()));
            self::assertSame('ang', $data->slice(2, 3));
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
        self::assertSame('hello', $data->slice(0, $data->length()));
        self::assertSame('ell', $data->slice(1, 3));
        self::assertSame(2, ftell($stream));

        fclose($stream);
    }

    #[Test]
    public function it_writes_non_seekable_stream_data_once_without_buffering_it_for_reuse(): void
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

        try {
            self::assertSame('hello', $output->contents());

            try {
                $data->length();
                self::fail('Expected non-seekable stream length inspection to fail.');
            } catch (RuntimeException $exception) {
                self::assertSame(
                    'Binary data source Kalle\\Pdf\\Binary\\OneShotStreamBinaryDataSource does not support length inspection.',
                    $exception->getMessage(),
                );
            }

            try {
                $data->slice(0, 1);
                self::fail('Expected non-seekable stream slicing to fail.');
            } catch (RuntimeException $exception) {
                self::assertSame(
                    'Binary data source Kalle\\Pdf\\Binary\\OneShotStreamBinaryDataSource does not support random-access slicing.',
                    $exception->getMessage(),
                );
            }
        } finally {
            unset($data);
        }

        self::assertFalse(is_resource($reader));
    }

    #[Test]
    public function it_accepts_non_seekable_streams_with_known_length_as_one_shot_sources(): void
    {
        $streams = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        if ($streams === false) {
            self::markTestSkipped('stream_socket_pair is not available.');
        }

        [$reader, $writer] = $streams;

        $data = BinaryData::fromStream($reader, 5, closeOnDestruct: true);

        fwrite($writer, 'hello');
        fclose($writer);

        $output = new StringPdfOutput();
        $data->writeTo($output);

        try {
            self::assertSame('hello', $output->contents());

            try {
                $data->length();
                self::fail('Expected one-shot source length inspection to fail.');
            } catch (RuntimeException $exception) {
                self::assertSame(
                    'Binary data source Kalle\\Pdf\\Binary\\OneShotStreamBinaryDataSource does not support length inspection.',
                    $exception->getMessage(),
                );
            }

            try {
                $data->writeTo(new StringPdfOutput());
                self::fail('Expected one-shot non-seekable stream replay to fail.');
            } catch (RuntimeException $exception) {
                self::assertSame(
                    'Unable to replay a non-seekable binary stream after it has been consumed.',
                    $exception->getMessage(),
                );
            }
        } finally {
            unset($data);
        }

        self::assertFalse(is_resource($reader));
    }

    #[Test]
    public function it_can_expose_a_segment_without_materializing_the_whole_source(): void
    {
        $data = BinaryData::fromString('hello world');
        $segment = $data->segment(6, 5);

        self::assertSame(5, $segment->length());
        self::assertSame('world', $segment->slice(0, $segment->length()));
        self::assertSame('orl', $segment->slice(1, 3));
    }

    #[Test]
    public function it_can_concatenate_multiple_binary_segments(): void
    {
        $data = BinaryData::concatenate(
            BinaryData::fromString('hello'),
            BinaryData::fromString(' '),
            BinaryData::fromString('world'),
        );
        $output = new StringPdfOutput();

        $data->writeTo($output);

        self::assertSame(11, $data->length());
        self::assertSame('hello world', $data->slice(0, $data->length()));
        self::assertSame('lo wo', $data->slice(3, 5));
        self::assertSame('hello world', $output->contents());
    }

    #[Test]
    public function it_rejects_segment_creation_for_one_shot_stream_sources(): void
    {
        $streams = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        if ($streams === false) {
            self::markTestSkipped('stream_socket_pair is not available.');
        }

        [$reader, $writer] = $streams;
        fwrite($writer, 'hello');
        fclose($writer);

        $data = BinaryData::fromStream($reader, closeOnDestruct: true);

        try {
            $data->segment(0, 1);
            self::fail('Expected one-shot source segment creation to fail.');
        } catch (RuntimeException $exception) {
            self::assertSame(
                'Binary data source Kalle\\Pdf\\Binary\\OneShotStreamBinaryDataSource does not support segment creation.',
                $exception->getMessage(),
            );
        } finally {
            unset($data);
        }

        self::assertFalse(is_resource($reader));
    }
}
