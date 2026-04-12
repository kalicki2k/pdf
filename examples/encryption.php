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

$variants = [
    'encryption-rc4-128.pdf' => [
        'profile' => Profile::pdf14(),
        'encryption' => Encryption::rc4_128('user-secret', 'owner-secret'),
        'headline' => 'RC4-128 encryption',
        'subject' => 'Minimal RC4-128 example',
        'details' => 'This PDF uses RC4-128 with the same explicit user and owner password pair.',
    ],
    'encryption-aes-128.pdf' => [
        'profile' => Profile::pdf16(),
        'encryption' => Encryption::aes128('user-secret', 'owner-secret')->withPermissions(Permissions::readOnly()),
        'headline' => 'AES-128 encryption',
        'subject' => 'Minimal AES-128 example with read-only permissions',
        'details' => 'This PDF uses AES-128 and disables printing, copying, modification and annotations in the permission flags.',
    ],
    'encryption-aes-256.pdf' => [
        'profile' => Profile::pdf17(),
        'encryption' => Encryption::aes256('user-secret', 'owner-secret')->withPermissions(
            new Permissions(
                print: false,
                modify: true,
                copy: false,
                annotate: true,
            ),
        ),
        'headline' => 'AES-256 encryption',
        'subject' => 'Minimal AES-256 example with explicit permissions',
        'details' => 'This PDF uses AES-256 and keeps modification plus annotations allowed while printing and copying remain disabled.',
    ],
];

foreach ($variants as $fileName => $variant) {
    DefaultDocumentBuilder::make()
        ->profile($variant['profile'])
        ->title($variant['headline'])
        ->author('Kalle PDF')
        ->subject($variant['subject'])
        ->creator('examples/encryption.php')
        ->creatorTool('pdf2')
        ->encryption($variant['encryption'])
        ->paragraph($variant['headline'], new TextOptions(
            fontSize: 14,
            lineHeight: 18,
        ))
        ->paragraph('User password: user-secret | Owner password: owner-secret', new TextOptions(
            fontSize: 11,
            lineHeight: 15,
        ))
        ->paragraph($variant['details'], new TextOptions(
            fontSize: 11,
            lineHeight: 15,
        ))
        ->writeToFile($outputDirectory . '/' . $fileName);

    fwrite(STDOUT, $outputDirectory . '/' . $fileName . PHP_EOL);
}
