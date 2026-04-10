<?php

declare(strict_types=1);

namespace Kalle\Pdf\Image;

use InvalidArgumentException;
use Kalle\Pdf\Binary\BinaryData;
use Kalle\Pdf\Encryption\Object\StandardObjectEncryptor;
use Kalle\Pdf\Render\EncryptingPdfOutput;
use Kalle\Pdf\Render\PdfOutput;
use RuntimeException;

class Image
{
    private int $width;
    private int $height;
    private string $colorSpace;
    private string $filter;
    private readonly BinaryData $data;
    private int $bitsPerComponent;
    private ?string $decodeParameters;
    private ?self $softMask;

    public function __construct(
        int $width,
        int $height,
        string $colorSpace,
        string $filter,
        string | BinaryData $data,
        int $bitsPerComponent = 8,
        ?string $decodeParameters = null,
        ?self $softMask = null,
    ) {
        $this->width = $width;
        $this->height = $height;
        $this->colorSpace = $colorSpace;
        $this->filter = $filter;
        $this->data = is_string($data) ? BinaryData::fromString($data) : $data;
        $this->bitsPerComponent = $bitsPerComponent;
        $this->decodeParameters = $decodeParameters;
        $this->softMask = $softMask;
    }

    public static function fromFile(string $path): self
    {
        $imageInfo = @getimagesize($path);

        if ($imageInfo === false) {
            self::assertReadableImageFile($path);

            throw new InvalidArgumentException("Unsupported or invalid image file '$path'.");
        }

        $data = self::readBinaryImageFile($path);

        return match ($imageInfo[2]) {
            IMAGETYPE_JPEG => self::fromJpegData($path, $data, $imageInfo),
            IMAGETYPE_PNG => self::fromPngData($path, $data),
            default => throw new InvalidArgumentException(sprintf(
                "Unsupported image type '%s'.",
                $imageInfo['mime'],
            )),
        };
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function getSoftMask(): ?self
    {
        return $this->softMask;
    }

    public function streamLength(): int
    {
        return $this->data->length();
    }

    public function encryptedStreamLength(StandardObjectEncryptor $objectEncryptor): int
    {
        return $objectEncryptor->encryptedByteLength($this->streamLength());
    }

    public function writeDictionary(PdfOutput $output, ?int $softMaskObjectId, int $length): void
    {
        $output->write('<< /Type /XObject' . PHP_EOL);
        $output->write('/Subtype /Image' . PHP_EOL);
        $output->write("/Width {$this->width}" . PHP_EOL);
        $output->write("/Height {$this->height}" . PHP_EOL);
        $output->write("/ColorSpace /{$this->colorSpace}" . PHP_EOL);
        $output->write("/BitsPerComponent {$this->bitsPerComponent}" . PHP_EOL);
        $output->write("/Filter /{$this->filter}" . PHP_EOL);

        if ($this->decodeParameters !== null) {
            $output->write("/DecodeParms {$this->decodeParameters}" . PHP_EOL);
        }

        if ($softMaskObjectId !== null) {
            $output->write("/SMask {$softMaskObjectId} 0 R" . PHP_EOL);
        }

        $output->write('/Length ' . $length . ' >>' . PHP_EOL);
    }

    public function writeStreamContents(PdfOutput $output): void
    {
        $this->data->writeTo($output);
    }

    public function writeEncryptedStreamContents(
        PdfOutput $output,
        StandardObjectEncryptor $objectEncryptor,
        int $objectId,
    ): void {
        $encryptedOutput = new EncryptingPdfOutput(
            $output,
            $objectEncryptor->createStreamEncryptor($objectId),
        );
        $this->data->writeTo($encryptedOutput);
        $encryptedOutput->finish();
    }

    public function write(PdfOutput $output, ?int $softMaskObjectId = null): void
    {
        $this->writeDictionary($output, $softMaskObjectId, $this->streamLength());
        $output->write('stream' . PHP_EOL);
        $this->writeStreamContents($output);
        $output->write(PHP_EOL . 'endstream' . PHP_EOL);
    }

    public function writeEncrypted(
        PdfOutput $output,
        StandardObjectEncryptor $objectEncryptor,
        int $objectId,
        ?int $softMaskObjectId = null,
    ): void {
        $this->writeDictionary($output, $softMaskObjectId, $this->encryptedStreamLength($objectEncryptor));
        $output->write('stream' . PHP_EOL);
        $this->writeEncryptedStreamContents($output, $objectEncryptor, $objectId);
        $output->write(PHP_EOL . 'endstream' . PHP_EOL);
    }

    /**
     * @param array{0:int,1:int,2?:int,channels?:int,bits?:int,mime?:string} $imageInfo
     */
    private static function fromJpegData(string $path, string | BinaryData $data, array $imageInfo): self
    {
        $channels = (int) ($imageInfo['channels'] ?? 3);
        $colorSpace = match ($channels) {
            1 => 'DeviceGray',
            3 => 'DeviceRGB',
            4 => 'DeviceCMYK',
            default => throw new InvalidArgumentException("Unsupported JPEG channel count '$channels' in '$path'."),
        };

        return new self(
            width: (int) $imageInfo[0],
            height: (int) $imageInfo[1],
            colorSpace: $colorSpace,
            filter: 'DCTDecode',
            data: $data,
            bitsPerComponent: (int) ($imageInfo['bits'] ?? 8),
        );
    }

    private static function fromPngData(string $path, BinaryData $data): self
    {
        $png = self::readPngImageData($path, $data);

        if ($png['compressionMethod'] !== 0 || $png['filterMethod'] !== 0) {
            throw new InvalidArgumentException("Unsupported PNG compression settings in '$path'.");
        }

        if ($png['interlaceMethod'] !== 0) {
            throw new InvalidArgumentException("Interlaced PNG images are not supported for '$path'.");
        }

        [$colorSpace, $colors] = match ($png['colorType']) {
            0 => ['DeviceGray', 1],
            2 => ['DeviceRGB', 3],
            3 => throw new InvalidArgumentException("Indexed PNG images are not supported for '$path'."),
            4 => ['DeviceGray', 1],
            6 => ['DeviceRGB', 3],
            default => throw new InvalidArgumentException("Unsupported PNG color type '{$png['colorType']}' in '$path'."),
        };

        if ($png['imageData']->length() === 0) {
            throw new InvalidArgumentException("PNG file '$path' does not contain image data.");
        }

        if (in_array($png['colorType'], [4, 6], true)) {
            if ($png['bitDepth'] !== 8) {
                throw new InvalidArgumentException("PNG images with alpha channels currently require 8 bits per component for '$path'.");
            }

            [$colorData, $alphaData] = PngAlphaChannelSplitter::split(
                $path,
                $png['imageData'],
                $png['width'],
                $png['height'],
                $colors,
            );

            return new self(
                width: $png['width'],
                height: $png['height'],
                colorSpace: $colorSpace,
                filter: 'FlateDecode',
                data: $colorData,
                bitsPerComponent: $png['bitDepth'],
                decodeParameters: sprintf(
                    '<< /Predictor 15 /Colors %d /BitsPerComponent %d /Columns %d >>',
                    $colors,
                    $png['bitDepth'],
                    $png['width'],
                ),
                softMask: new self(
                    width: $png['width'],
                    height: $png['height'],
                    colorSpace: 'DeviceGray',
                    filter: 'FlateDecode',
                    data: $alphaData,
                    bitsPerComponent: $png['bitDepth'],
                    decodeParameters: sprintf(
                        '<< /Predictor 15 /Colors 1 /BitsPerComponent %d /Columns %d >>',
                        $png['bitDepth'],
                        $png['width'],
                    ),
                ),
            );
        }

        return new self(
            width: $png['width'],
            height: $png['height'],
            colorSpace: $colorSpace,
            filter: 'FlateDecode',
            data: $png['imageData'],
            bitsPerComponent: $png['bitDepth'],
            decodeParameters: sprintf(
                '<< /Predictor 15 /Colors %d /BitsPerComponent %d /Columns %d >>',
                $colors,
                $png['bitDepth'],
                $png['width'],
            ),
        );
    }

    /**
     * @return array{
     *     width:int,
     *     height:int,
     *     bitDepth:int,
     *     colorType:int,
     *     compressionMethod:int,
     *     filterMethod:int,
     *     interlaceMethod:int,
     *     imageData:BinaryData
     * }
     */
    private static function readPngImageData(string $path, BinaryData $data): array
    {
        if ($data->slice(0, 8) !== "\x89PNG\x0D\x0A\x1A\x0A") {
            throw new InvalidArgumentException("Invalid PNG file '$path'.");
        }

        $offset = 8;
        $dataLength = $data->length();
        $header = null;
        $imageDataChunks = [];

        while ($offset + 8 <= $dataLength) {
            $chunkHeader = $data->slice($offset, 8);

            if (strlen($chunkHeader) !== 8) {
                break;
            }

            $length = self::readUint32($chunkHeader, 0);
            $type = substr($chunkHeader, 4, 4);
            $chunkData = $data->segment($offset + 8, $length);
            $offset += $length + 12;

            if ($type === 'IHDR') {
                $header = $chunkData->slice(0, $chunkData->length());

                continue;
            }

            if ($type === 'IDAT') {
                $imageDataChunks[] = $chunkData;

                continue;
            }

            if ($type === 'IEND') {
                break;
            }
        }

        if ($header === null || strlen($header) < 13) {
            throw new InvalidArgumentException("Invalid PNG file '$path'.");
        }

        return [
            'width' => self::readUint32($header, 0),
            'height' => self::readUint32($header, 4),
            'bitDepth' => ord($header[8]),
            'colorType' => ord($header[9]),
            'compressionMethod' => ord($header[10]),
            'filterMethod' => ord($header[11]),
            'interlaceMethod' => ord($header[12]),
            'imageData' => BinaryData::concatenate(...$imageDataChunks),
        ];
    }

    private static function readBinaryImageFile(string $path): BinaryData
    {
        try {
            return BinaryData::fromFile($path);
        } catch (RuntimeException $exception) {
            throw new InvalidArgumentException("Unable to read image file '$path'.");
        }
    }

    private static function assertReadableImageFile(string $path): void
    {
        $stream = @fopen($path, 'rb');

        if ($stream !== false) {
            fclose($stream);

            return;
        }

        throw new InvalidArgumentException("Unable to read image file '$path'.");
    }

    private static function readUint32(string $data, int $offset): int
    {
        $value = @unpack('N', substr($data, $offset, 4));

        if ($value === false || !isset($value[1]) || !is_int($value[1])) {
            throw new InvalidArgumentException('Unable to read PNG chunk data.');
        }

        return $value[1];
    }

}
