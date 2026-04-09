<?php

declare(strict_types=1);

namespace Kalle\Pdf\Render;

use Kalle\Pdf\Encryption\EncryptionProfile;
use Kalle\Pdf\Encryption\StandardSecurityHandlerData;
use Kalle\Pdf\Object\IndirectObject;

final readonly class PdfSerializationPlan
{
    /**
     * @param list<IndirectObject> $objects
     * @param array{string, string} $documentId
     */
    public function __construct(
        public float $version,
        public array $objects,
        public int $rootObjectId,
        public ?int $infoObjectId,
        public ?int $encryptObjectId,
        public array $documentId,
        public ?EncryptionProfile $encryptionProfile = null,
        public ?StandardSecurityHandlerData $securityHandlerData = null,
    ) {
    }
}
