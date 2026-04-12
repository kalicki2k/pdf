<?php

declare(strict_types=1);

namespace Kalle\Pdf\Encryption;

final class EncryptDictionaryBuilder
{
    public function build(EncryptionProfile $profile, StandardSecurityHandlerData $securityHandlerData): string
    {
        return '<< /Filter /Standard'
            . ' /V ' . $profile->dictionaryVersion
            . ' /R ' . $profile->revision
            . ' /Length ' . $profile->keyLengthInBits
            . ' /P ' . $securityHandlerData->permissionBits
            . ' /O <' . strtoupper(bin2hex($securityHandlerData->ownerValue)) . '>'
            . ' /U <' . strtoupper(bin2hex($securityHandlerData->userValue)) . '>'
            . ' >>';
    }
}
