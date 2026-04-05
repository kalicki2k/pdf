<?php

declare(strict_types=1);

namespace Kalle\Pdf\Encryption;

use InvalidArgumentException;

final class StandardSecurityHandler
{
    private const PASSWORD_PADDING = "\x28\xBF\x4E\x5E\x4E\x75\x8A\x41\x64\x00\x4E\x56\xFF\xFA\x01\x08"
        . "\x2E\x2E\x00\xB6\xD0\x68\x3E\x80\x2F\x0C\xA9\xFE\x64\x53\x69\x7A";

    public function __construct(
        private readonly Rc4Cipher $rc4Cipher = new Rc4Cipher(),
        private readonly PermissionBitsResolver $permissionBitsResolver = new PermissionBitsResolver(),
    ) {
    }

    public function build(EncryptionOptions $options, EncryptionProfile $profile, string $fileId): StandardSecurityHandlerData
    {
        if ($profile->revision === 5) {
            return $this->buildRevision5($options, $profile);
        }

        if (!in_array($profile->revision, [2, 3, 4], true)) {
            throw new InvalidArgumentException('Only standard security handler revisions 2, 3, 4 and 5 are supported in this stage.');
        }

        $permissionBits = $this->permissionBitsResolver->resolve($options->permissions, $profile);
        $ownerValue = $this->computeOwnerValue($options, $profile);
        $encryptionKey = $this->computeEncryptionKey($options, $profile, $ownerValue, $permissionBits, $fileId);
        $userValue = $this->computeUserValue($profile, $encryptionKey, $fileId);

        return new StandardSecurityHandlerData(
            $ownerValue,
            $userValue,
            $encryptionKey,
            $permissionBits,
        );
    }

    private function buildRevision5(EncryptionOptions $options, EncryptionProfile $profile): StandardSecurityHandlerData
    {
        $permissionBits = $this->permissionBitsResolver->resolve($options->permissions, $profile);
        $fileEncryptionKey = random_bytes(32);
        $userPassword = $this->truncatePassword($options->userPassword);
        $ownerPassword = $this->truncatePassword($options->ownerPassword !== '' ? $options->ownerPassword : $options->userPassword);

        $userValidationSalt = random_bytes(8);
        $userKeySalt = random_bytes(8);
        $ownerValidationSalt = random_bytes(8);
        $ownerKeySalt = random_bytes(8);

        $userValue = hash('sha256', $userPassword . $userValidationSalt, true)
            . $userValidationSalt
            . $userKeySalt;
        $userEncryptionKey = $this->encryptAes256CbcNoPadding(
            $fileEncryptionKey,
            hash('sha256', $userPassword . $userKeySalt, true),
        );

        $ownerValue = hash('sha256', $ownerPassword . $ownerValidationSalt . $userValue, true)
            . $ownerValidationSalt
            . $ownerKeySalt;
        $ownerEncryptionKey = $this->encryptAes256CbcNoPadding(
            $fileEncryptionKey,
            hash('sha256', $ownerPassword . $ownerKeySalt . $userValue, true),
        );

        $permsValue = $this->encryptAes256EcbNoPadding(
            pack('V', $permissionBits & 0xFFFFFFFF)
            . "\xFF\xFF\xFF\xFF"
            . 'T'
            . 'adb'
            . random_bytes(4),
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

    private function computeOwnerValue(EncryptionOptions $options, EncryptionProfile $profile): string
    {
        $ownerPassword = $options->ownerPassword !== '' ? $options->ownerPassword : $options->userPassword;
        $digest = md5($this->padPassword($ownerPassword), true);

        if ($profile->revision >= 3) {
            for ($i = 0; $i < 50; $i++) {
                $digest = md5($digest, true);
            }
        }

        $key = substr($digest, 0, $this->keyLengthInBytes($profile));
        $result = $this->rc4Cipher->encrypt($key, $this->padPassword($options->userPassword));

        if ($profile->revision >= 3) {
            for ($i = 1; $i <= 19; $i++) {
                $result = $this->rc4Cipher->encrypt($this->xorKey($key, $i), $result);
            }
        }

        return $result;
    }

    private function computeEncryptionKey(
        EncryptionOptions $options,
        EncryptionProfile $profile,
        string $ownerValue,
        int $permissionBits,
        string $fileId,
    ): string {
        $digest = md5(
            $this->padPassword($options->userPassword)
            . $ownerValue
            . pack('V', $permissionBits & 0xFFFFFFFF)
            . hex2bin($fileId),
            true,
        );

        if ($profile->revision >= 3) {
            for ($i = 0; $i < 50; $i++) {
                $digest = md5($digest, true);
            }
        }

        return substr($digest, 0, $this->keyLengthInBytes($profile));
    }

    private function computeUserValue(EncryptionProfile $profile, string $encryptionKey, string $fileId): string
    {
        if ($profile->revision === 2) {
            return $this->rc4Cipher->encrypt($encryptionKey, self::PASSWORD_PADDING);
        }

        $digest = md5(self::PASSWORD_PADDING . hex2bin($fileId), true);
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

    private function keyLengthInBytes(EncryptionProfile $profile): int
    {
        return $profile->revision === 2 ? 5 : intdiv($profile->keyLengthInBits, 8);
    }

    private function xorKey(string $key, int $value): string
    {
        $result = '';

        foreach (str_split($key) as $byte) {
            $result .= chr(ord($byte) ^ $value);
        }

        return $result;
    }

    private function truncatePassword(string $password): string
    {
        return substr($password, 0, 127);
    }

    private function encryptAes256CbcNoPadding(string $plaintext, string $key): string
    {
        $encrypted = openssl_encrypt(
            $plaintext,
            'aes-256-cbc',
            $key,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            str_repeat("\x00", 16),
        );

        if ($encrypted === false) {
            throw new InvalidArgumentException('Unable to encrypt AES-256-CBC security handler payload.');
        }

        return $encrypted;
    }

    private function encryptAes256EcbNoPadding(string $plaintext, string $key): string
    {
        $encrypted = openssl_encrypt(
            $plaintext,
            'aes-256-ecb',
            $key,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
        );

        if ($encrypted === false) {
            throw new InvalidArgumentException('Unable to encrypt AES-256-ECB security handler payload.');
        }

        return $encrypted;
    }
}
