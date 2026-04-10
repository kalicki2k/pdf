<?php

declare(strict_types=1);

namespace Kalle\Pdf\Encryption {
    function setStandardObjectEncryptorOpenSslShouldFail(bool $shouldFail): void
    {
        $GLOBALS['kalle_pdf_standard_object_encryptor_openssl_should_fail'] = $shouldFail;
    }

    function setStandardSecurityHandlerOpenSslShouldFail(bool $shouldFail): void
    {
        $GLOBALS['kalle_pdf_standard_security_handler_openssl_should_fail'] = $shouldFail;
    }
}

namespace Kalle\Pdf\Internal\Encryption\Stream {
    use function openssl_encrypt as global_openssl_encrypt;

    function openssl_encrypt(
        string $data,
        string $cipher_algo,
        string $passphrase,
        int $options = 0,
        string $iv = '',
        &$tag = null,
        string $aad = '',
        int $tag_length = 16,
    ): string | false {
        if (($GLOBALS['kalle_pdf_standard_object_encryptor_openssl_should_fail'] ?? false) === true) {
            return false;
        }

        if (($GLOBALS['kalle_pdf_standard_security_handler_openssl_should_fail'] ?? false) === true) {
            return false;
        }

        return global_openssl_encrypt($data, $cipher_algo, $passphrase, $options, $iv, $tag, $aad, $tag_length);
    }
}

namespace Kalle\Pdf\Internal\Encryption\Standard {
    use function openssl_encrypt as global_openssl_encrypt;

    function openssl_encrypt(
        string $data,
        string $cipher_algo,
        string $passphrase,
        int $options = 0,
        string $iv = '',
        &$tag = null,
        string $aad = '',
        int $tag_length = 16,
    ): string | false {
        if (($GLOBALS['kalle_pdf_standard_object_encryptor_openssl_should_fail'] ?? false) === true) {
            return false;
        }

        if (($GLOBALS['kalle_pdf_standard_security_handler_openssl_should_fail'] ?? false) === true) {
            return false;
        }

        return global_openssl_encrypt($data, $cipher_algo, $passphrase, $options, $iv, $tag, $aad, $tag_length);
    }
}
