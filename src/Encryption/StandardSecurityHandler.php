<?php

declare(strict_types=1);

namespace Kalle\Pdf\Encryption;

use Closure;
use InvalidArgumentException;

final readonly class StandardSecurityHandler
{
    private const string PASSWORD_PADDING = "\x28\xBF\x4E\x5E\x4E\x75\x8A\x41\x64\x00\x4E\x56\xFF\xFA\x01\x08"
        . "\x2E\x2E\x00\xB6\xD0\x68\x3E\x80\x2F\x0C\xA9\xFE\x64\x53\x69\x7A";

    public function __construct(
        private Rc4Cipher $rc4Cipher = new Rc4Cipher(),
        private PermissionBitsResolver $permissionBitsResolver = new PermissionBitsResolver(),
        private Aes256CbcNoPaddingCipher $aes256CbcNoPaddingCipher = new Aes256CbcNoPaddingCipher(),
        private Aes256EcbNoPaddingCipher $aes256EcbNoPaddingCipher = new Aes256EcbNoPaddingCipher(),
        ?callable $randomBytesGenerator = null,
    ) {
        $this->randomBytesGenerator = $randomBytesGenerator instanceof Closure
            ? $randomBytesGenerator
            : Closure::fromCallable($randomBytesGenerator ?? static fn (int $length): string => random_bytes(max(1, $length)));
    }

    private Closure $randomBytesGenerator;

    public function build(Encryption $encryption, EncryptionProfile $profile, string $documentId): StandardSecurityHandlerData
    {
        if ($profile->revision === 5) {
            return $this->buildRevision5($encryption);
        }

        if (!in_array($profile->revision, [3, 4], true)) {
            throw new InvalidArgumentException('Only standard security handler revisions 3, 4 and 5 are supported in this stage.');
        }

        $permissionBits = $this->permissionBitsResolver->resolve($encryption->permissions, $profile);
        $ownerValue = $this->computeOwnerValue($encryption, $profile);
        $encryptionKey = $this->computeEncryptionKey($encryption, $profile, $ownerValue, $permissionBits, $documentId);
        $userValue = $this->computeUserValue($profile, $encryptionKey, $documentId);

        return new StandardSecurityHandlerData(
            $ownerValue,
            $userValue,
            $encryptionKey,
            $permissionBits,
        );
    }

    private function buildRevision5(Encryption $encryption): StandardSecurityHandlerData
    {
        $permissionBits = $this->permissionBitsResolver->resolve(
            $encryption->permissions,
            new EncryptionProfile(Algorithm::AES_256, 256, 5, 5),
        );
        $fileEncryptionKey = $this->randomBytes(32);
        $userPassword = $this->truncatePassword($encryption->userPassword);
        $ownerPassword = $this->truncatePassword($encryption->ownerPassword !== '' ? $encryption->ownerPassword : $encryption->userPassword);

        $userValidationSalt = $this->randomBytes(8);
        $userKeySalt = $this->randomBytes(8);
        $ownerValidationSalt = $this->randomBytes(8);
        $ownerKeySalt = $this->randomBytes(8);

        $userValue = hash('sha256', $userPassword . $userValidationSalt, true)
            . $userValidationSalt
            . $userKeySalt;
        $userEncryptionKey = $this->aes256CbcNoPaddingCipher->encrypt(
            $fileEncryptionKey,
            hash('sha256', $userPassword . $userKeySalt, true),
        );

        $ownerValue = hash('sha256', $ownerPassword . $ownerValidationSalt . $userValue, true)
            . $ownerValidationSalt
            . $ownerKeySalt;
        $ownerEncryptionKey = $this->aes256CbcNoPaddingCipher->encrypt(
            $fileEncryptionKey,
            hash('sha256', $ownerPassword . $ownerKeySalt . $userValue, true),
        );

        $permsValue = $this->aes256EcbNoPaddingCipher->encrypt(
            pack('V', $permissionBits & 0xFFFFFFFF)
            . "\xFF\xFF\xFF\xFF"
            . 'T'
            . 'adb'
            . $this->randomBytes(4),
            $fileEncryptionKey,
        );

        return new StandardSecurityHandlerData(
            $ownerValue,
            $userValue,
            $fileEncryptionKey,
            $permissionBits,
            $ownerEncryptionKey,
            $userEncryptionKey,
            $permsValue,
        );
    }

    private function computeOwnerValue(Encryption $encryption, EncryptionProfile $profile): string
    {
        $digest = md5($this->padPassword($encryption->ownerPassword), true);

        for ($i = 0; $i < 50; $i++) {
            $digest = md5($digest, true);
        }

        $key = substr($digest, 0, intdiv($profile->keyLengthInBits, 8));
        $result = $this->rc4Cipher->encrypt($key, $this->padPassword($encryption->userPassword));

        for ($i = 1; $i <= 19; $i++) {
            $result = $this->rc4Cipher->encrypt($this->xorKey($key, $i), $result);
        }

        return $result;
    }

    private function computeEncryptionKey(
        Encryption $encryption,
        EncryptionProfile $profile,
        string $ownerValue,
        int $permissionBits,
        string $documentId,
    ): string {
        $digest = md5(
            $this->padPassword($encryption->userPassword)
            . $ownerValue
            . pack('V', $permissionBits & 0xFFFFFFFF)
            . hex2bin($documentId),
            true,
        );

        for ($i = 0; $i < 50; $i++) {
            $digest = md5($digest, true);
        }

        return substr($digest, 0, intdiv($profile->keyLengthInBits, 8));
    }

    private function computeUserValue(EncryptionProfile $profile, string $encryptionKey, string $documentId): string
    {
        if (!in_array($profile->revision, [3, 4], true)) {
            throw new InvalidArgumentException('Only standard security handler revisions 3 and 4 are supported in this stage.');
        }

        $digest = md5(self::PASSWORD_PADDING . hex2bin($documentId), true);
        $result = $this->rc4Cipher->encrypt($encryptionKey, $digest);

        for ($i = 1; $i <= 19; $i++) {
            $result = $this->rc4Cipher->encrypt($this->xorKey($encryptionKey, $i), $result);
        }

        return $result . str_repeat("\x00", 16);
    }

    private function padPassword(string $password): string
    {
        $truncated = substr($password, 0, 32);

        if (strlen($truncated) === 32) {
            return $truncated;
        }

        return $truncated . substr(self::PASSWORD_PADDING, 0, 32 - strlen($truncated));
    }

    private function xorKey(string $key, int $value): string
    {
        $result = '';

        foreach (str_split($key) as $byte) {
            $result .= chr((ord($byte) ^ $value) & 0xFF);
        }

        return $result;
    }

    private function truncatePassword(string $password): string
    {
        return substr($password, 0, 127);
    }

    private function randomBytes(int $length): string
    {
        $bytes = ($this->randomBytesGenerator)($length);

        if (!is_string($bytes) || strlen($bytes) !== $length) {
            throw new InvalidArgumentException(sprintf(
                'Random byte generator must return exactly %d bytes.',
                $length,
            ));
        }

        return $bytes;
    }
}
