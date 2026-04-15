<?php

declare(strict_types=1);

namespace Kalle\Pdf\Encryption;

final class EncryptDictionaryBuilder
{
    public function build(EncryptionProfile $profile, StandardSecurityHandlerData $securityHandlerData): string
    {
        $entries = [
            '/Filter /Standard',
            '/V ' . $profile->dictionaryVersion,
            '/R ' . $profile->revision,
            '/Length ' . $profile->keyLengthInBits,
            '/P ' . $securityHandlerData->permissionBits,
            '/O <' . strtoupper(bin2hex($securityHandlerData->ownerValue)) . '>',
            '/U <' . strtoupper(bin2hex($securityHandlerData->userValue)) . '>',
        ];

        if ($profile->algorithm === Algorithm::AES_128) {
            $entries[] = '/CF << /StdCF << /CFM /AESV2 /AuthEvent /DocOpen /Length 16 >> >>';
            $entries[] = '/StmF /StdCF';
            $entries[] = '/StrF /StdCF';
        }

        if ($profile->algorithm === Algorithm::AES_256) {
            $entries[] = '/OE <' . strtoupper(bin2hex($securityHandlerData->ownerEncryptionKey ?? '')) . '>';
            $entries[] = '/UE <' . strtoupper(bin2hex($securityHandlerData->userEncryptionKey ?? '')) . '>';
            $entries[] = '/Perms <' . strtoupper(bin2hex($securityHandlerData->permsValue ?? '')) . '>';
            $entries[] = '/CF << /StdCF << /CFM /AESV3 /AuthEvent /DocOpen /Length 32 >> >>';
            $entries[] = '/StmF /StdCF';
            $entries[] = '/StrF /StdCF';
        }

        return '<< '
            . implode(' ', $entries)
            . ' >>';
    }
}
