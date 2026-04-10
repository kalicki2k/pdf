<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Document;
use Kalle\Pdf\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Encryption\Profile\EncryptionVersionResolver;
use Kalle\Pdf\Encryption\Standard\EncryptDictionary;
use Kalle\Pdf\Encryption\Standard\StandardSecurityHandler;
use Kalle\Pdf\Encryption\Standard\StandardSecurityHandlerData;
use Kalle\Pdf\Security\EncryptionOptions;

/**
 * @internal Manages document encryption state and lazy security handler data creation.
 */
class DocumentEncryptionManager
{
    private ?EncryptionProfile $encryptionProfile;
    private ?EncryptionOptions $encryptionOptions;
    private ?StandardSecurityHandlerData $securityHandlerData;

    public function __construct(
        private readonly Document $document,
        ?EncryptionProfile &$encryptionProfile,
        ?EncryptionOptions &$encryptionOptions,
        ?StandardSecurityHandlerData &$securityHandlerData,
    ) {
        $this->encryptionProfile = & $encryptionProfile;
        $this->encryptionOptions = & $encryptionOptions;
        $this->securityHandlerData = & $securityHandlerData;
    }

    public function encrypt(EncryptionOptions $options): void
    {
        $this->document->assertAllowsEncryptionAlgorithm($options->algorithm);

        $resolver = new EncryptionVersionResolver();
        $this->encryptionOptions = $options;
        $this->encryptionProfile = $resolver->resolve($this->document->getVersion(), $options->algorithm);
        $this->securityHandlerData = null;
        $this->document->encryptDictionary = new EncryptDictionary(
            $this->document->getUniqObjectId(),
            $this->document,
            $this->encryptionProfile,
        );
    }

    public function getEncryptionProfile(): ?EncryptionProfile
    {
        return $this->encryptionProfile;
    }

    public function getEncryptionOptions(): ?EncryptionOptions
    {
        return $this->encryptionOptions;
    }

    public function getSecurityHandlerData(): ?StandardSecurityHandlerData
    {
        if ($this->encryptionProfile === null || $this->encryptionOptions === null) {
            return null;
        }

        if ($this->securityHandlerData === null) {
            $this->securityHandlerData = (new StandardSecurityHandler())->build(
                $this->encryptionOptions,
                $this->encryptionProfile,
                $this->document->getDocumentId()[0],
            );
        }

        return $this->securityHandlerData;
    }
}
