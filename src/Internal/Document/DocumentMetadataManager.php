<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Document;

use Composer\InstalledVersions;
use InvalidArgumentException;
use Kalle\Pdf\Document;
use Kalle\Pdf\Internal\Document\Metadata\IccProfileStream;
use Kalle\Pdf\Internal\Document\Metadata\XmpMetadata;

/**
 * @internal Manages document metadata values and lazy metadata-related objects.
 */
class DocumentMetadataManager
{
    private const string PDFA_SRGB_ICC_PROFILE_PATH = __DIR__ . '/../../../assets/color-srgb.icc';
    private const string PDFA1_SRGB_ICC_PROFILE_PATH = __DIR__ . '/../../../assets/color-srgb-pdfa1.icc';

    private string $creator;
    private string $creatorTool;
    private string $producer;
    private ?IccProfileStream $pdfaOutputIntentProfile;
    private ?XmpMetadata $xmpMetadata;

    /** @var list<string> */
    private array $keywords;

    /**
     * @param list<string> $keywords
     */
    public function __construct(
        private readonly Document $document,
        string &$creator,
        string &$creatorTool,
        string &$producer,
        ?IccProfileStream &$pdfaOutputIntentProfile,
        ?XmpMetadata &$xmpMetadata,
        array &$keywords,
    ) {
        $this->creator = & $creator;
        $this->creatorTool = & $creatorTool;
        $this->producer = & $producer;
        $this->pdfaOutputIntentProfile = & $pdfaOutputIntentProfile;
        $this->xmpMetadata = & $xmpMetadata;
        $this->keywords = & $keywords;
    }

    public static function resolveDefaultProducer(string $packageName): string
    {
        $version = InstalledVersions::getPrettyVersion($packageName);

        return is_string($version) && $version !== ''
            ? $packageName . ' ' . $version
            : $packageName;
    }

    public function getPdfAOutputIntentProfile(): ?IccProfileStream
    {
        if (!$this->document->getProfile()->usesPdfAOutputIntent()) {
            return null;
        }

        if ($this->pdfaOutputIntentProfile === null) {
            $this->pdfaOutputIntentProfile = IccProfileStream::fromPath(
                $this->document->getUniqObjectId(),
                $this->document->getProfile()->pdfaPart() === 1
                    ? self::PDFA1_SRGB_ICC_PROFILE_PATH
                    : self::PDFA_SRGB_ICC_PROFILE_PATH,
            );
        }

        return $this->pdfaOutputIntentProfile;
    }

    public function getCreator(): string
    {
        return $this->creator;
    }

    public function setCreator(string $creator): void
    {
        if ($creator === '') {
            throw new InvalidArgumentException('Creator must not be empty.');
        }

        $this->creator = $creator;
    }

    public function getProducer(): string
    {
        return $this->producer;
    }

    public function setProducer(string $producer): void
    {
        if ($producer === '') {
            throw new InvalidArgumentException('Producer must not be empty.');
        }

        $this->producer = $producer;
    }

    public function getCreatorTool(): string
    {
        return $this->creatorTool;
    }

    public function setCreatorTool(string $creatorTool): void
    {
        if ($creatorTool === '') {
            throw new InvalidArgumentException('Creator tool must not be empty.');
        }

        $this->creatorTool = $creatorTool;
    }

    public function getXmpMetadata(): ?XmpMetadata
    {
        if (!$this->document->getProfile()->supportsXmpMetadata()) {
            return null;
        }

        if ($this->xmpMetadata === null) {
            $this->xmpMetadata = new XmpMetadata($this->document->getUniqObjectId(), $this->document);
        }

        return $this->xmpMetadata;
    }

    public function addKeyword(string $keyword): void
    {
        $this->keywords = self::normalizeKeywords([...$this->keywords, $keyword]);
    }

    /**
     * @return list<string>
     */
    public function getKeywords(): array
    {
        return $this->keywords;
    }

    /**
     * @param list<string> $values
     * @return list<string>
     */
    private static function normalizeKeywords(array $values): array
    {
        return array_values(array_unique(array_filter(
            array_map(
                static fn (string $value): string => trim($value),
                $values,
            ),
            static fn (string $value): bool => $value !== '',
        )));
    }
}
