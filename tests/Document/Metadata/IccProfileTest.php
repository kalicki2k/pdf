<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document\Metadata;

use InvalidArgumentException;
use Kalle\Pdf\Document\Metadata\IccProfile;
use PHPUnit\Framework\TestCase;

final class IccProfileTest extends TestCase
{
    public function testItBuildsAnIccProfileStream(): void
    {
        $contents = IccProfile::fromPath(IccProfile::defaultSrgbPath())->objectContents();

        self::assertStringStartsWith('<< /N 3 /Length ', $contents);
        self::assertStringContainsString("\nstream\n", $contents);
        self::assertStringEndsWith("\nendstream", $contents);
    }

    public function testItRejectsUnreadableProfiles(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unable to read ICC profile '/tmp/missing-srgb.icc'.");

        IccProfile::fromPath('/tmp/missing-srgb.icc');
    }

    public function testItRejectsProfilesWithAnInvalidHeaderForPdfA1(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'icc-invalid-');

        if ($path === false) {
            self::fail('Failed to create ICC temp file.');
        }

        file_put_contents($path, str_repeat('X', 256));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('ICC profile "%s" declares an invalid profile length.', $path));

        IccProfile::fromPath($path)->assertPdfA1Compatible(new \Kalle\Pdf\Document\Metadata\PdfAOutputIntent($path));
    }
}
