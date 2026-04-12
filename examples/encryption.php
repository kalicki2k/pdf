<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Encryption\Encryption;
use Kalle\Pdf\Encryption\Permissions;
use Kalle\Pdf\Text\TextOptions;

$outputDirectory = __DIR__ . '/../var/examples';

if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0777, true) && !is_dir($outputDirectory)) {
    throw new RuntimeException('Unable to create example output directory.');
}

DefaultDocumentBuilder::make()
    ->profile(Profile::pdf17())
    ->title('Encryption Example')
    ->author('Kalle PDF')
    ->subject('Minimal AES-256 example with explicit permissions')
    ->creator('examples/encryption.php')
    ->creatorTool('pdf2')
    ->encryption(
        Encryption::aes256('user-secret', 'owner-secret')->withPermissions(
            new Permissions(
                print: false,
                modify: true,
                copy: false,
                annotate: true,
            ),
        ),
    )
    ->paragraph('This PDF uses AES-256 encryption with an explicit user and owner password.', new TextOptions(
        fontSize: 14,
        lineHeight: 18,
    ))
    ->paragraph('Printing and copying are disabled in the permission flags while modification and annotations remain allowed.', new TextOptions(
        fontSize: 11,
        lineHeight: 15,
    ))
    ->writeToFile($outputDirectory . '/encryption.pdf');
