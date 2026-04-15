<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document\Metadata;

use function pack;
use function str_repeat;
use function substr_replace;

use InvalidArgumentException;
use Kalle\Pdf\Document\Metadata\IccProfile;
use Kalle\Pdf\Document\Metadata\PdfAOutputIntent;
use PHPUnit\Framework\TestCase;

final class IccProfileTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryPaths = [];

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

        $this->temporaryPaths[] = $path;
        file_put_contents($path, str_repeat('X', 256));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('ICC profile "%s" declares an invalid profile length.', $path));

        IccProfile::fromPath($path)->assertPdfA1Compatible(new PdfAOutputIntent($path));
    }

    public function testItRejectsProfilesWithoutTheIccSignatureForPdfA1(): void
    {
        $path = $this->writeIccFixture($this->minimalIccProfileData(signature: 'zzzz'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('ICC profile "%s" is missing the ICC signature.', $path));

        IccProfile::fromPath($path)->assertPdfA1Compatible(new PdfAOutputIntent($path));
    }

    public function testItRejectsProfilesWithUnsupportedDeviceClassesForPdfA1(): void
    {
        $path = $this->writeIccFixture($this->minimalIccProfileData(deviceClass: 'link'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            'ICC profile "%s" uses unsupported device class "link" for PDF/A-1.',
            $path,
        ));

        IccProfile::fromPath($path)->assertPdfA1Compatible(new PdfAOutputIntent($path));
    }

    public function testItRejectsProfilesWithMismatchingColorSpacesForPdfA1(): void
    {
        $path = $this->writeIccFixture($this->minimalIccProfileData(colorSpace: 'CMYK'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            'ICC profile "%s" color space "CMYK" does not match the PDF/A output intent component count 3.',
            $path,
        ));

        IccProfile::fromPath($path)->assertPdfA1Compatible(new PdfAOutputIntent($path, colorComponents: 3));
    }

    public function testItRejectsImplausibleRgbOutputIntentIdentifiersForPdfA1(): void
    {
        $path = $this->writeIccFixture($this->minimalIccProfileData(colorSpace: 'RGB '));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PDF/A output intent "Press Condition" is not plausible for an RGB ICC profile.');

        IccProfile::fromPath($path)->assertPdfA1Compatible(new PdfAOutputIntent($path, 'Press Condition', null, 3));
    }

    public function testItRejectsImplausibleCmykOutputIntentIdentifiersForPdfA1(): void
    {
        $path = $this->writeIccFixture($this->minimalIccProfileData(colorSpace: 'CMYK'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PDF/A output intent "Generic Press" is not plausible for a CMYK ICC profile.');

        IccProfile::fromPath($path)->assertPdfA1Compatible(new PdfAOutputIntent($path, 'Generic Press', null, 4));
    }

    private function writeIccFixture(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'icc-valid-');

        if ($path === false) {
            self::fail('Failed to create ICC temp file.');
        }

        $this->temporaryPaths[] = $path;
        file_put_contents($path, $contents);

        return $path;
    }

    private function minimalIccProfileData(
        string $deviceClass = 'mntr',
        string $colorSpace = 'RGB ',
        string $signature = 'acsp',
        int $length = 132,
    ): string {
        $data = str_repeat("\0", $length);
        $data = substr_replace($data, pack('N', $length), 0, 4);
        $data = substr_replace($data, $deviceClass, 12, 4);
        $data = substr_replace($data, $colorSpace, 16, 4);
        $data = substr_replace($data, $signature, 36, 4);

        return $data;
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryPaths as $path) {
            @unlink($path);
        }

        $this->temporaryPaths = [];
    }
}
