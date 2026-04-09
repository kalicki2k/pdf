<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Encryption\ObjectStringEncryptor;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\ArrayType;
use Kalle\Pdf\Types\BooleanType;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use Kalle\Pdf\Types\ReferenceType;
use Kalle\Pdf\Types\StringType;

final class Catalog extends IndirectObject
{
    public function __construct(int $id, private readonly Document $document)
    {
        parent::__construct($id);
    }

    public function render(): string
    {
        return $this->renderWithStringEncryptor();
    }

    public function renderWithStringEncryptor(?ObjectStringEncryptor $encryptor = null): string
    {
        $metadata = $this->document->getXmpMetadata();
        $dictionary = new DictionaryType([
            'Type' => new NameType('Catalog'),
            'Pages' => new ReferenceType($this->document->pages),
        ]);

        if ($metadata !== null) {
            $dictionary->add('Metadata', new ReferenceType($metadata));
        }

        if ($this->document->getProfile()->displaysDocumentTitleInViewer()) {
            $dictionary->add('ViewerPreferences', new DictionaryType([
                'DisplayDocTitle' => new BooleanType(true),
            ]));
        }

        $pdfaOutputIntentProfile = $this->document->getPdfAOutputIntentProfile();

        if ($pdfaOutputIntentProfile !== null) {
            $dictionary->add('OutputIntents', new ArrayType([
                new DictionaryType([
                    'Type' => new NameType('OutputIntent'),
                    'S' => new NameType('GTS_PDFA1'),
                    'OutputConditionIdentifier' => new StringType('sRGB IEC61966-2.1'),
                    'Info' => new StringType('sRGB IEC61966-2.1'),
                    'DestOutputProfile' => new ReferenceType($pdfaOutputIntentProfile),
                ]),
            ]));
        }

        if ($this->document->outlineRoot !== null) {
            $dictionary->add('Outlines', new ReferenceType($this->document->outlineRoot));
            $dictionary->add('PageMode', new NameType('UseOutlines'));
        }

        if ($this->document->getDestinations() !== []) {
            $destinations = new DictionaryType([]);

            foreach ($this->document->getDestinations() as $name => $page) {
                $destinations->add($name, new ArrayType([
                    new ReferenceType($page),
                    new NameType('Fit'),
                ]));
            }

            $dictionary->add('Dests', $destinations);
        }

        if ($this->document->getAttachments() !== []) {
            $embeddedFileEntries = [];
            $associatedFiles = [];

            foreach ($this->document->getAttachments() as $attachment) {
                $embeddedFileEntries[] = new StringType($attachment->getFilename());
                $embeddedFileEntries[] = new ReferenceType($attachment);

                if ($attachment->hasAfRelationship()) {
                    $associatedFiles[] = new ReferenceType($attachment);
                }
            }

            $dictionary->add('Names', new DictionaryType([
                'EmbeddedFiles' => new DictionaryType([
                    'Names' => new ArrayType($embeddedFileEntries),
                ]),
            ]));

            if ($associatedFiles !== []) {
                $dictionary->add('AF', new ArrayType($associatedFiles));
            }
        }

        if ($this->document->acroForm !== null) {
            $dictionary->add('AcroForm', new ReferenceType($this->document->acroForm));
        }

        if ($this->document->getOptionalContentGroups() !== []) {
            $groups = $this->document->getOptionalContentGroups();
            $onGroups = [];
            $offGroups = [];

            foreach ($groups as $group) {
                if ($group->isVisibleByDefault()) {
                    $onGroups[] = new ReferenceType($group);
                    continue;
                }

                $offGroups[] = new ReferenceType($group);
            }

            $defaultConfiguration = new DictionaryType([
                'Order' => new ArrayType(array_map(
                    static fn (OptionalContentGroup $group): ReferenceType => new ReferenceType($group),
                    $groups,
                )),
            ]);

            if ($onGroups !== []) {
                $defaultConfiguration->add('ON', new ArrayType($onGroups));
            }

            if ($offGroups !== []) {
                $defaultConfiguration->add('OFF', new ArrayType($offGroups));
            }

            $dictionary->add('OCProperties', new DictionaryType([
                'OCGs' => new ArrayType(array_map(
                    static fn (OptionalContentGroup $group): ReferenceType => new ReferenceType($group),
                    $groups,
                )),
                'D' => $defaultConfiguration,
            ]));
        }

        if ($this->document->getProfile()->supportsStructure() && $this->document->structTreeRoot !== null) {
            $dictionary->add('MarkInfo', new DictionaryType([
                'Marked' => new BooleanType(true),
            ]));
            $dictionary->add('Lang', new StringType($this->document->getLanguage() ?? ''));
            $dictionary->add('StructTreeRoot', new ReferenceType($this->document->structTreeRoot));
        }

        return "$this->id 0 obj" . PHP_EOL
            . $dictionary->render($encryptor) . PHP_EOL
            . 'endobj' . PHP_EOL;
    }
}
