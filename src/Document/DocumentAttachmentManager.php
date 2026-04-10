<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;
use Kalle\Pdf\Binary\BinaryData;
use Kalle\Pdf\Document;
use Kalle\Pdf\Document\Attachment\AssociatedFileRelationship;
use Kalle\Pdf\Document\Attachment\EmbeddedFileStream;
use Kalle\Pdf\Document\Attachment\FileSpecification;
use Kalle\Pdf\Document\Preparation\DocumentProfileGuard;
use RuntimeException;

/**
 * @internal Manages document attachments and associated-file validation.
 */
class DocumentAttachmentManager
{
    /** @var list<FileSpecification> */
    private array $attachments;

    /**
     * @param list<FileSpecification> $attachments
     */
    public function __construct(
        private readonly Document $document,
        array &$attachments,
        private readonly DocumentProfileGuard $profileGuard,
    ) {
        $this->attachments = & $attachments;
    }

    public function addAttachment(
        string $filename,
        string $contents,
        ?string $description = null,
        ?string $mimeType = null,
        ?AssociatedFileRelationship $afRelationship = null,
    ): void {
        $this->storeAttachment(
            $filename,
            BinaryData::fromString($contents),
            $description,
            $mimeType,
            $afRelationship,
        );
    }

    public function addAttachmentFromFile(
        string $path,
        ?string $filename = null,
        ?string $description = null,
        ?string $mimeType = null,
        ?AssociatedFileRelationship $afRelationship = null,
    ): void {
        if (!is_file($path)) {
            throw new InvalidArgumentException("Attachment file '$path' does not exist.");
        }

        $filename ??= basename($path);

        try {
            $contents = BinaryData::fromFile($path);
        } catch (RuntimeException $exception) {
            throw new InvalidArgumentException("Attachment file '$path' could not be read.");
        }

        $this->storeAttachment($filename, $contents, $description, $mimeType, $afRelationship);
    }

    /**
     * @return list<FileSpecification>
     */
    public function getAttachments(): array
    {
        return $this->attachments;
    }

    public function getAttachment(string $filename): ?FileSpecification
    {
        return array_find(
            $this->attachments,
            static fn (FileSpecification $attachment): bool => $attachment->getFilename() === $filename,
        );
    }

    private function storeAttachment(
        string $filename,
        BinaryData $contents,
        ?string $description,
        ?string $mimeType,
        ?AssociatedFileRelationship $afRelationship,
    ): void {
        $this->document->assertAllowsAttachments();

        if ($filename === '') {
            throw new InvalidArgumentException('Attachment filename must not be empty.');
        }

        $afRelationship ??= $this->document->getProfile()->defaultsAttachmentRelationshipToData()
            ? AssociatedFileRelationship::DATA
            : null;

        if ($afRelationship !== null) {
            $this->profileGuard->assertAllowsAssociatedFiles();
        }

        $embeddedFile = new EmbeddedFileStream($this->document->getUniqObjectId(), $contents, $mimeType);
        $this->attachments = [
            ...$this->attachments,
            new FileSpecification(
                $this->document->getUniqObjectId(),
                $filename,
                $embeddedFile,
                $description,
                $afRelationship,
            ),
        ];
    }
}
