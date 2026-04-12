#!/usr/bin/env php
<?php

declare(strict_types=1);

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\DocumentSerializationPlanBuilder;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Writer\DocumentSerializationPlan;
use Kalle\Pdf\Writer\FileOutput;
use Kalle\Pdf\Writer\IndirectObject;
use Kalle\Pdf\Writer\Renderer;

require dirname(__DIR__) . '/vendor/autoload.php';

if ($argc !== 2) {
    fwrite(STDERR, "Usage: bin/generate-pdfa2u-negative-regression-fixtures.php <output-dir>\n");
    exit(1);
}

$outputDir = $argv[1];

if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    fwrite(STDERR, sprintf("Could not create output directory: %s\n", $outputDir));
    exit(1);
}

$renderer = new Renderer();
$fontPath = regressionFontPath();
$baseOutputPath = rtrim($outputDir, '/\\');
$baseDocument = DefaultDocumentBuilder::make()
    ->profile(Profile::pdfA2u())
    ->title('PDF/A-2u Negative Regression')
    ->author('kalle/pdf2')
    ->subject('Negative PDF/A-2u regression fixtures')
    ->language('de-DE')
    ->creator('Regression Fixture')
    ->creatorTool('bin/generate-pdfa2u-negative-regression-fixtures.php')
    ->text('PDF/A-2u Negative Regression Привет', new TextOptions(
        x: 72,
        y: 760,
        fontSize: 18,
        embeddedFont: EmbeddedFontSource::fromPath($fontPath),
        color: Color::rgb(0.08, 0.16, 0.35),
    ))
    ->textAnnotation(72, 680, 18, 18, 'Kommentar', 'QA', 'Comment', true)
    ->build();
$plan = (new DocumentSerializationPlanBuilder())->build($baseDocument);
$objects = iterator_to_array($plan->objects);

$variants = [
    'pdf-a-2u-invalid-missing-metadata-reference.pdf' => static function (array $objects): array {
        return replaceFirstMatchingObject(
            $objects,
            static fn (IndirectObject $object): bool => $object->objectId === 1 && str_contains($object->contents, '/Metadata '),
            static function (IndirectObject $object): IndirectObject {
                $contents = preg_replace('/\s*\/Metadata\s+\d+\s+0\s+R/', '', $object->contents, 1) ?? $object->contents;

                return IndirectObject::plain($object->objectId, $contents, $object->encryptable);
            },
            'metadata reference',
        );
    },
    'pdf-a-2u-invalid-missing-output-intents.pdf' => static function (array $objects): array {
        return replaceFirstMatchingObject(
            $objects,
            static fn (IndirectObject $object): bool => $object->objectId === 1 && str_contains($object->contents, '/OutputIntents '),
            static function (IndirectObject $object): IndirectObject {
                $contents = preg_replace('/\s*\/OutputIntents\s+\[[^\]]+\]/', '', $object->contents, 1) ?? $object->contents;

                return IndirectObject::plain($object->objectId, $contents, $object->encryptable);
            },
            'OutputIntents array',
        );
    },
    'pdf-a-2u-invalid-missing-annotation-appearance.pdf' => static function (array $objects): array {
        return replaceFirstMatchingObject(
            $objects,
            static fn (IndirectObject $object): bool => str_contains($object->contents, '/Subtype /Text') && str_contains($object->contents, '/AP << /N '),
            static function (IndirectObject $object): IndirectObject {
                $contents = preg_replace('/\s*\/AP\s*<<\s*\/N\s+\d+\s+0\s+R\s*>>/', '', $object->contents, 1) ?? $object->contents;

                return IndirectObject::plain($object->objectId, $contents, $object->encryptable);
            },
            'annotation appearance reference',
        );
    },
];

$paths = [];

foreach ($variants as $filename => $mutator) {
    $path = $baseOutputPath . DIRECTORY_SEPARATOR . $filename;
    $output = new FileOutput($path);
    $renderer->write(
        new DocumentSerializationPlan($mutator($objects), $plan->fileStructure, $plan->objectEncryptor),
        $output,
    );
    $output->close();
    $paths[] = $path;
}

echo implode(PHP_EOL, $paths) . PHP_EOL;

function regressionFontPath(): string
{
    $path = dirname(__DIR__) . '/assets/fonts/noto-sans/NotoSans-Regular.ttf';

    if (!is_file($path)) {
        throw new RuntimeException(sprintf('Required regression font not found: %s', $path));
    }

    return $path;
}

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
        throw new RuntimeException(sprintf('Failed to mutate negative PDF/A-2u regression fixture for %s.', $label));
    }

    return $mutatedObjects;
}
