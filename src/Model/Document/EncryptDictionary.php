<?php

declare(strict_types=1);

namespace Kalle\Pdf\Model\Document;

use Kalle\Pdf\Internal\Document\Document;
use Kalle\Pdf\Internal\Encryption\Profile\EncryptionProfile;
use Kalle\Pdf\Internal\Security\EncryptionAlgorithm;
use Kalle\Pdf\Object\DictionaryIndirectObject;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\RawType;
use RuntimeException;

class EncryptDictionary extends DictionaryIndirectObject
{
    public function __construct(
        int $id,
        private readonly Document $document,
        private readonly EncryptionProfile $profile,
    ) {
        parent::__construct($id);
    }

    protected function dictionary(): DictionaryType
    {
        $this->document->assertAllowsEncryptionAlgorithm($this->profile->algorithm);

        $securityHandlerData = $this->document->getSecurityHandlerData();

        if ($securityHandlerData === null) {
            throw new RuntimeException('Encryption dictionary requires initialized security handler data.');
        }

        $dictionary = new DictionaryType([
            'Filter' => new NameType('Standard'),
            'V' => new RawType((string) $this->profile->dictionaryVersion),
            'R' => new RawType((string) $this->profile->revision),
            'Length' => new RawType((string) $this->profile->keyLengthInBits),
            'P' => new RawType((string) $securityHandlerData->permissionBits),
            'O' => new RawType('<' . strtoupper(bin2hex($securityHandlerData->ownerValue)) . '>'),
            'U' => new RawType('<' . strtoupper(bin2hex($securityHandlerData->userValue)) . '>'),
        ]);

        if ($this->profile->algorithm === EncryptionAlgorithm::AES_128) {
            $dictionary->add('CF', new DictionaryType([
                'StdCF' => new DictionaryType([
                    'CFM' => new NameType('AESV2'),
                    'AuthEvent' => new NameType('DocOpen'),
                    'Length' => 16,
                ]),
            ]));
            $dictionary->add('StmF', new NameType('StdCF'));
            $dictionary->add('StrF', new NameType('StdCF'));
        }

        if ($this->profile->algorithm === EncryptionAlgorithm::AES_256) {
            $dictionary->add('OE', new RawType('<' . strtoupper(bin2hex($securityHandlerData->ownerEncryptionKey ?? '')) . '>'));
            $dictionary->add('UE', new RawType('<' . strtoupper(bin2hex($securityHandlerData->userEncryptionKey ?? '')) . '>'));
            $dictionary->add('Perms', new RawType('<' . strtoupper(bin2hex($securityHandlerData->permsValue ?? '')) . '>'));
            $dictionary->add('CF', new DictionaryType([
                'StdCF' => new DictionaryType([
                    'CFM' => new NameType('AESV3'),
                    'AuthEvent' => new NameType('DocOpen'),
                    'Length' => 32,
                ]),
            ]));
            $dictionary->add('StmF', new NameType('StdCF'));
            $dictionary->add('StrF', new NameType('StdCF'));
        }

        return $dictionary;
    }
}
