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
    ) {
    }

    public function build(Encryption $encryption, EncryptionProfile $profile, string $documentId): StandardSecurityHandlerData
    {
        if ($profile->revision !== 3) {
            throw new InvalidArgumentException('Only standard security handler revision 3 is supported in this stage.');
        }

        $permissionBits = -4;
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
        if ($profile->revision !== 3) {
            throw new InvalidArgumentException('Only standard security handler revision 3 is supported in this stage.');
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
}
