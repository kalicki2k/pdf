<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use InvalidArgumentException;
use Kalle\Pdf\Document\BinaryData;
use Kalle\Pdf\Document\IccProfileStream;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IccProfileStreamTest extends TestCase
{
    #[Test]
    public function it_renders_an_icc_profile_stream(): void
    {
        $stream = new IccProfileStream(11, 'ICC', 3);

        self::assertSame(
            "11 0 obj\n"
            . "<< /N 3 /Length 3 >>\n"
            . "stream\n"
            . "ICC\n"
            . "endstream\n"
            . "endobj\n",
            $stream->render(),
        );
    }

    #[Test]
    public function it_renders_an_icc_profile_stream_from_binary_data(): void
    {
        $stream = new IccProfileStream(11, BinaryData::fromString('ICC'), 4);

        self::assertSame(
            "11 0 obj\n"
            . "<< /N 4 /Length 3 >>\n"
            . "stream\n"
            . "ICC\n"
            . "endstream\n"
            . "endobj\n",
            $stream->render(),
        );
    }

    #[Test]
    public function it_reads_an_icc_profile_from_a_file_without_tracking_later_file_changes(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf-icc-profile-');
        self::assertNotFalse($path);
        file_put_contents($path, 'profile-data');

        try {
            $stream = IccProfileStream::fromPath(11, $path, 3);
            file_put_contents($path, 'changed');

            self::assertStringContainsString('/Length 12', $stream->render());
            self::assertStringContainsString("stream\nprofile-data\n", $stream->render());
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function it_rejects_unreadable_icc_profiles(): void
    {
        $missingPath = sys_get_temp_dir() . '/missing-icc-' . uniqid('', true) . '.icc';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unable to read ICC profile '$missingPath'.");

        IccProfileStream::fromPath(11, $missingPath, 3);
    }
}
