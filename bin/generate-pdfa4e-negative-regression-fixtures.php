#!/usr/bin/env php
<?php

declare(strict_types=1);

use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\DocumentSerializationPlanBuilder;
use Kalle\Pdf\Document\Attachment\EmbeddedFile;
use Kalle\Pdf\Document\OptionalContentConfiguration;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Page\OptionalContentGroup;
use Kalle\Pdf\Page\Page;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Page\RichMediaAnnotation;
use Kalle\Pdf\Page\RichMediaAssetType;
use Kalle\Pdf\Page\RichMediaPresentationStyle;
use Kalle\Pdf\Page\ThreeDAnnotation;
use Kalle\Pdf\Page\ThreeDAssetType;
use Kalle\Pdf\Page\ThreeDViewPreset;
use Kalle\Pdf\Writer\DocumentSerializationPlan;
use Kalle\Pdf\Writer\FileOutput;
use Kalle\Pdf\Writer\IndirectObject;
use Kalle\Pdf\Writer\Renderer;

require dirname(__DIR__) . '/vendor/autoload.php';

if ($argc !== 2) {
    fwrite(STDERR, "Usage: bin/generate-pdfa4e-negative-regression-fixtures.php <output-dir>\n");
    exit(1);
}

$outputDir = $argv[1];

if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    fwrite(STDERR, sprintf("Could not create output directory: %s\n", $outputDir));
    exit(1);
}

$renderer = new Renderer();
$baseDocument = new Document(
    profile: Profile::pdfA4e(),
    title: 'PDF/A-4e Negative Regression',
    author: 'kalle/pdf2',
    subject: 'Negative PDF/A-4e regression fixtures',
    language: 'de-DE',
    creator: 'Regression Fixture',
    creatorTool: 'bin/generate-pdfa4e-negative-regression-fixtures.php',
    pages: [
        new Page(
            PageSize::A4(),
            contents: "/OC /Layer1 BDC\nEMC",
            optionalContentGroups: [
                'Layer1' => new OptionalContentGroup('Engineering View'),
            ],
        ),
    ],
    optionalContentConfigurations: [
        new OptionalContentConfiguration('Engineering View', ['Layer1']),
    ],
);
$plan = (new DocumentSerializationPlanBuilder())->build($baseDocument);
$objects = iterator_to_array($plan->objects);
$baseOutputPath = rtrim($outputDir, '/\\');

$variants = [
    'pdf-a-4e-invalid-missing-pdfaid-rev.pdf' => static function () use ($plan, $objects): DocumentSerializationPlan {
        return clonePlanWithObjects(
            $plan,
            replaceFirstMatchingObject(
                $objects,
                static fn (IndirectObject $object): bool => $object->streamContents !== null
                    && str_contains($object->streamContents, '<pdfaid:rev>2020</pdfaid:rev>'),
                static function (IndirectObject $object): IndirectObject {
                    return IndirectObject::stream(
                        $object->objectId,
                        $object->streamDictionaryContents ?? '',
                        str_replace('<pdfaid:rev>2020</pdfaid:rev>', '', $object->streamContents ?? ''),
                        $object->encryptable,
                    );
                },
                'pdfaid:rev metadata',
            ),
        );
    },
    'pdf-a-4e-invalid-richmedia-missing-afrelationship.pdf' => static function (): DocumentSerializationPlan {
        $plan = buildPlan(createRichMediaBaseDocument());

        return clonePlanWithObjects(
            $plan,
            mutatePlanObjects(
                iterator_to_array($plan->objects),
                static fn (IndirectObject $object): bool => str_contains($object->contents, '/Type /Filespec')
                    && str_contains($object->contents, '/AFRelationship /Supplement'),
                static function (IndirectObject $object): IndirectObject {
                    $contents = preg_replace('/\s*\/AFRelationship\s*\/Supplement/', '', $object->contents, 1) ?? $object->contents;

                    return IndirectObject::plain($object->objectId, $contents, $object->encryptable);
                },
                'RichMedia Filespec AFRelationship',
            ),
        );
    },
    'pdf-a-4e-invalid-richmedia-devicegray-poster.pdf' => static function (): DocumentSerializationPlan {
        $plan = buildPlan(createRichMediaBaseDocument());

        return clonePlanWithObjects(
            $plan,
            mutatePlanObjects(
                iterator_to_array($plan->objects),
                static fn (IndirectObject $object): bool => $object->streamContents !== null
                    && $object->streamDictionaryContents !== null
                    && str_contains($object->streamDictionaryContents, '/Type /XObject')
                    && str_contains($object->streamDictionaryContents, '/Subtype /Form'),
                static function (IndirectObject $object): IndirectObject {
                    return IndirectObject::stream(
                        $object->objectId,
                        $object->streamDictionaryContents ?? '',
                        "0.95 g\n0 G\n1 w\n0 0 160 90 re\nB",
                        $object->encryptable,
                    );
                },
                'RichMedia poster appearance using DeviceGray',
            ),
        );
    },
    'pdf-a-4e-invalid-3d-devicegray-poster.pdf' => static function (): DocumentSerializationPlan {
        $plan = buildPlan(createThreeDBaseDocument());

        return clonePlanWithObjects(
            $plan,
            mutatePlanObjects(
                iterator_to_array($plan->objects),
                static fn (IndirectObject $object): bool => $object->streamContents !== null
                    && $object->streamDictionaryContents !== null
                    && str_contains($object->streamDictionaryContents, '/Type /XObject')
                    && str_contains($object->streamDictionaryContents, '/Subtype /Form'),
                static function (IndirectObject $object): IndirectObject {
                    return IndirectObject::stream(
                        $object->objectId,
                        $object->streamDictionaryContents ?? '',
                        "0.9 g\n0 G\n1 w\n0 0 160 90 re\nB",
                        $object->encryptable,
                    );
                },
                '3D poster appearance using DeviceGray',
            ),
        );
    },
];

$paths = [];

foreach ($variants as $filename => $mutator) {
    $path = $baseOutputPath . DIRECTORY_SEPARATOR . $filename;
    $output = new FileOutput($path);
    $renderer->write($mutator(), $output);
    $output->close();
    $paths[] = $path;
}

echo implode(PHP_EOL, $paths) . PHP_EOL;

/**
 * @param list<IndirectObject> $objects
 * @param callable(IndirectObject): bool $matcher
 * @param callable(IndirectObject): IndirectObject $mutator
 * @return list<IndirectObject>
 */
function replaceFirstMatchingObject(array $objects, callable $matcher, callable $mutator, string $label): array
{
    $didMutate = false;

    $mutatedObjects = array_map(
        static function (IndirectObject $object) use ($matcher, $mutator, &$didMutate): IndirectObject {
            if ($didMutate || !$matcher($object)) {
                return $object;
            }

            $didMutate = true;

            return $mutator($object);
        },
        $objects,
    );

    if (!$didMutate) {
        throw new RuntimeException(sprintf('Failed to mutate negative PDF/A-4e regression fixture for %s.', $label));
    }

    return $mutatedObjects;
}

function createRichMediaBaseDocument(): Document
{
    return new Document(
        profile: Profile::pdfA4e(),
        title: 'PDF/A-4e Negative RichMedia Regression',
        author: 'kalle/pdf2',
        subject: 'Negative PDF/A-4e RichMedia regression fixture',
        language: 'de-DE',
        creator: 'Regression Fixture',
        creatorTool: 'bin/generate-pdfa4e-negative-regression-fixtures.php',
        pages: [
            new Page(
                PageSize::A4(),
                annotations: [
                    new RichMediaAnnotation(
                        40,
                        500,
                        160,
                        90,
                        'demo.mp4',
                        new EmbeddedFile('demo-video', 'video/mp4'),
                        RichMediaAssetType::VIDEO,
                        'Demo video',
                        null,
                        RichMediaPresentationStyle::WINDOWED,
                    ),
                ],
            ),
        ],
    );
}

function createThreeDBaseDocument(): Document
{
    return new Document(
        profile: Profile::pdfA4e(),
        title: 'PDF/A-4e Negative 3D Regression',
        author: 'kalle/pdf2',
        subject: 'Negative PDF/A-4e 3D regression fixture',
        language: 'de-DE',
        creator: 'Regression Fixture',
        creatorTool: 'bin/generate-pdfa4e-negative-regression-fixtures.php',
        pages: [
            new Page(
                PageSize::A4(),
                annotations: [
                    new ThreeDAnnotation(
                        40,
                        500,
                        160,
                        90,
                        'u3d-data',
                        ThreeDAssetType::U3D,
                        '3D model',
                        null,
                        ThreeDViewPreset::EXPLODED,
                    ),
                ],
            ),
        ],
    );
}

/**
 * @return DocumentSerializationPlan
 */
function buildPlan(Document $document): DocumentSerializationPlan
{
    return (new DocumentSerializationPlanBuilder())->build($document);
}

/**
 * @param list<IndirectObject> $objects
 * @param callable(IndirectObject): bool $matcher
 * @param callable(IndirectObject): IndirectObject $mutator
 * @return list<IndirectObject>
 */
function mutatePlanObjects(array $objects, callable $matcher, callable $mutator, string $label): array
{
    return replaceFirstMatchingObject($objects, $matcher, $mutator, $label);
}

/**
 * @param list<IndirectObject> $objects
 */
function clonePlanWithObjects(DocumentSerializationPlan $plan, array $objects): DocumentSerializationPlan
{
    return new DocumentSerializationPlan($objects, $plan->fileStructure, $plan->objectEncryptor);
}
