<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Signature;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

final readonly class PdfSignatureOptions
{
    public DateTimeImmutable $signingTime;

    public function __construct(
        public string $fieldName,
        public ?string $signerName = null,
        public ?string $reason = null,
        public ?string $location = null,
        public ?string $contactInfo = null,
        ?DateTimeInterface $signingTime = null,
        public int $reservedContentsLength = 16384,
    ) {
        if ($this->fieldName === '') {
            throw new InvalidArgumentException('Signature field name must not be empty.');
        }

        if ($this->reservedContentsLength < 2048) {
            throw new InvalidArgumentException('Reserved signature contents length must be at least 2048 bytes.');
        }

        $this->signingTime = $signingTime instanceof DateTimeImmutable
            ? $signingTime
            : ($signingTime === null ? new DateTimeImmutable() : DateTimeImmutable::createFromInterface($signingTime));
    }
}
