<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kalle\Pdf\Color\Color;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\DocumentSerializationPlanBuilder;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Drawing\Units;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Font\StandardFont;
use Kalle\Pdf\Image\ImageColorSpace;
use Kalle\Pdf\Image\ImagePlacement;
use Kalle\Pdf\Image\ImageSource;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Text\TextLink;
use Kalle\Pdf\Text\TextSegment;
use Kalle\Pdf\Writer\Renderer;
use Kalle\Pdf\Writer\StringOutput;
final class PerformanceBenchmark
{
    public const DEFAULT_WARMUP = 1;
    public const DEFAULT_ITERATIONS = 5;

    /**
     * @return array<string, callable(): Document>
     */
    public function scenarios(): array
    {
        return [
            'small' => fn (): Document => $this->buildSmallDocument(),
            'medium' => fn (): Document => $this->buildMediumDocument(),
            'large' => fn (): Document => $this->buildLargeDocument(),
            'text-heavy' => fn (): Document => $this->buildTextHeavyDocument(),
            'content-heavy' => fn (): Document => $this->buildContentHeavyDocument(),
            'many-objects' => fn (): Document => $this->buildManyObjectsDocument(),
            'images' => fn (): Document => $this->buildImagesDocument(),
            'forms' => fn (): Document => $this->buildFormsDocument(),
            'pdfa' => fn (): Document => $this->buildPdfaDocument(),
        ];
    }

    /**
     * @param array<string, callable(): Document> $scenarios
     * @return array<string, array<string, mixed>>
     */
    public function run(array $scenarios, int $warmup, int $iterations): array
    {
        $results = [];

        foreach ($scenarios as $name => $factory) {
            for ($index = 0; $index < $warmup; ++$index) {
                $this->measureScenario($factory);
            }

            $measurements = [];

            for ($index = 0; $index < $iterations; ++$index) {
                $measurements[] = $this->measureScenario($factory);
            }

            $results[$name] = $this->summarize($measurements);
        }

        return $results;
    }

    /**
     * @param callable(): Document $factory
     * @return array<string, int|float>
     */
    private function measureScenario(callable $factory): array
    {
        gc_collect_cycles();

        $baselineMemory = memory_get_usage(true);
        memory_reset_peak_usage();

        $documentBuildStartedAt = hrtime(true);
        $document = $factory();
        $documentBuildMs = $this->elapsedMs($documentBuildStartedAt);
        $documentPeakMb = $this->peakDeltaMb($baselineMemory);

        gc_collect_cycles();

        $planBuilder = new DocumentSerializationPlanBuilder();
        $planBaselineMemory = memory_get_usage(true);
        memory_reset_peak_usage();
        $planBuildStartedAt = hrtime(true);
        $plan = $planBuilder->build($document);
        $planBuildMs = $this->elapsedMs($planBuildStartedAt);
        $planPeakMb = $this->peakDeltaMb($planBaselineMemory);

        gc_collect_cycles();

        $renderer = new Renderer();
        $output = new StringOutput();
        $renderBaselineMemory = memory_get_usage(true);
        memory_reset_peak_usage();
        $renderStartedAt = hrtime(true);
        $renderer->write($plan, $output);
        $renderMs = $this->elapsedMs($renderStartedAt);
        $renderPeakMb = $this->peakDeltaMb($renderBaselineMemory);

        return [
            'document_build_ms' => $documentBuildMs,
            'plan_build_ms' => $planBuildMs,
            'render_ms' => $renderMs,
            'total_ms' => $documentBuildMs + $planBuildMs + $renderMs,
            'document_peak_mb' => $documentPeakMb,
            'plan_peak_mb' => $planPeakMb,
            'render_peak_mb' => $renderPeakMb,
            'peak_memory_mb' => max($documentPeakMb, $planPeakMb, $renderPeakMb),
            'page_count' => count($document->pages),
            'pdf_object_count' => count($plan->objects),
            'bytes' => $output->offset(),
        ];
    }

    /**
     * @param list<array<string, int|float>> $measurements
     * @return array<string, mixed>
     */
    private function summarize(array $measurements): array
    {
        $sample = $measurements[0];
        $summary = [];

        foreach (array_keys($sample) as $metric) {
            $values = array_map(
                static fn (array $measurement): int|float => $measurement[$metric],
                $measurements,
            );

            $summary[$metric] = [
                'avg' => $this->round3(array_sum($values) / count($values)),
                'min' => $this->round3(min($values)),
                'max' => $this->round3(max($values)),
            ];
        }

        $totalMs = (float) $summary['total_ms']['avg'];

        $summary['hotspot_share_pct'] = [
            'document_build' => $this->round3(((float) $summary['document_build_ms']['avg'] / $totalMs) * 100),
            'plan_build' => $this->round3(((float) $summary['plan_build_ms']['avg'] / $totalMs) * 100),
            'render' => $this->round3(((float) $summary['render_ms']['avg'] / $totalMs) * 100),
        ];

        return $summary;
    }

    private function buildSmallDocument(): Document
    {
        return DefaultDocumentBuilder::make()
            ->title('Small document')
            ->text('Hello PDF', TextOptions::make(
                x: 72,
                y: 760,
                fontName: StandardFont::HELVETICA_BOLD->value,
                fontSize: 20,
                lineHeight: 24,
            ))
            ->text($this->lorem(2), TextOptions::make(
                x: 72,
                y: 720,
                width: 460,
                fontSize: 11,
                lineHeight: 15,
            ))
            ->build();
    }

    private function buildMediumDocument(): Document
    {
        $builder = DefaultDocumentBuilder::make()
            ->title('Medium document')
            ->pageSize(PageSize::A4())
            ->margin(Margin::all(Units::mm(18)));

        for ($page = 1; $page <= 10; ++$page) {
            if ($page > 1) {
                $builder = $builder->newPage();
            }

            $builder = $builder->text('Section ' . $page, TextOptions::make(
                fontName: StandardFont::HELVETICA_BOLD->value,
                fontSize: 18,
                lineHeight: 22,
                spacingAfter: 10,
            ));

            for ($paragraph = 0; $paragraph < 12; ++$paragraph) {
                $builder = $builder->text($this->lorem(4), TextOptions::make(
                    width: 480,
                    fontSize: 10.5,
                    lineHeight: 14.5,
                    spacingAfter: 6,
                ));
            }
        }

        return $builder->build();
    }

    private function buildLargeDocument(): Document
    {
        $builder = DefaultDocumentBuilder::make()
            ->title('Large document')
            ->pageSize(PageSize::A4())
            ->margin(Margin::all(Units::mm(16)));

        for ($page = 1; $page <= 25; ++$page) {
            if ($page > 1) {
                $builder = $builder->newPage();
            }

            $builder = $builder->text('Large page ' . $page, TextOptions::make(
                fontName: StandardFont::HELVETICA_BOLD->value,
                fontSize: 16,
                lineHeight: 20,
                spacingAfter: 8,
            ));

            for ($paragraph = 0; $paragraph < 12; ++$paragraph) {
                $builder = $builder->text($this->lorem(5), TextOptions::make(
                    width: 500,
                    fontSize: 10,
                    lineHeight: 14,
                    spacingAfter: 4,
                ));
            }
        }

        return $builder->build();
    }

    private function buildTextHeavyDocument(): Document
    {
        $font = EmbeddedFontSource::fromPath(__DIR__ . '/../assets/fonts/noto-sans/NotoSans-Regular.ttf');
        $builder = DefaultDocumentBuilder::make()
            ->title('Text-heavy document')
            ->pageSize(PageSize::A4())
            ->margin(Margin::all(Units::mm(16)));

        $paragraph = 'Extended Latin mix: ÄÖÜ ß cafe facade resume naive cooperate. ' . $this->lorem(4);

        for ($page = 1; $page <= 10; ++$page) {
            if ($page > 1) {
                $builder = $builder->newPage();
            }

            for ($block = 0; $block < 16; ++$block) {
                $builder = $builder->text($paragraph, TextOptions::make(
                    width: 490,
                    embeddedFont: $font,
                    fontSize: 10.5,
                    lineHeight: 14.5,
                    spacingAfter: 5,
                ));
            }
        }

        return $builder->build();
    }

    private function buildContentHeavyDocument(): Document
    {
        $font = EmbeddedFontSource::fromPath(__DIR__ . '/../assets/fonts/noto-sans/NotoSans-Regular.ttf');
        $builder = DefaultDocumentBuilder::make()
            ->title('Content-heavy document')
            ->pageSize(PageSize::A4())
            ->margin(Margin::all(Units::mm(16)));

        for ($page = 1; $page <= 10; ++$page) {
            if ($page > 1) {
                $builder = $builder->newPage();
            }

            for ($paragraph = 0; $paragraph < 18; ++$paragraph) {
                $builder = $builder->text($this->contentHeavySegments($paragraph), TextOptions::make(
                    width: 490,
                    embeddedFont: $font,
                    fontSize: 10.5,
                    lineHeight: 14.5,
                    spacingAfter: 4,
                ));
            }
        }

        return $builder->build();
    }

    private function buildManyObjectsDocument(): Document
    {
        $builder = DefaultDocumentBuilder::make()
            ->title('Many objects document')
            ->pageSize(PageSize::A4())
            ->margin(Margin::all(Units::mm(12)));

        for ($page = 1; $page <= 15; ++$page) {
            if ($page > 1) {
                $builder = $builder->newPage();
            }

            $builder = $builder->namedDestination('page-' . $page)
                ->text('Object page ' . $page, TextOptions::make(
                    x: 48,
                    y: 780,
                    fontName: StandardFont::HELVETICA_BOLD->value,
                    fontSize: 15,
                    lineHeight: 18,
                ));

            for ($index = 0; $index < 12; ++$index) {
                $top = 740 - ($index * 36);
                $builder = $builder
                    ->rectangle(48, $top, 220, 24, fillColor: Color::hex('#dbeafe'))
                    ->text('Target ' . $page . '-' . $index, TextOptions::make(
                        x: 56,
                        y: $top + 16,
                        fontSize: 10,
                        lineHeight: 12,
                    ))
                    ->linkToNamedDestination(
                        'page-' . max(1, $page - 1),
                        48,
                        $top,
                        220,
                        24,
                        'Open previous page',
                        'Open previous page',
                    );
            }
        }

        return $builder->build();
    }

    private function buildImagesDocument(): Document
    {
        $jpeg = ImageSource::jpeg($this->tinyRgbJpegBytes(), 1, 1, ImageColorSpace::RGB);
        $png = ImageSource::fromPath($this->writeTinyPngFixture());
        $builder = DefaultDocumentBuilder::make()
            ->title('Images document')
            ->pageSize(PageSize::A4())
            ->margin(Margin::all(Units::mm(18)));

        for ($page = 1; $page <= 8; ++$page) {
            if ($page > 1) {
                $builder = $builder->newPage();
            }

            for ($row = 0; $row < 4; ++$row) {
                for ($column = 0; $column < 4; ++$column) {
                    $x = 48 + ($column * 120);
                    $y = 720 - ($row * 96);
                    $source = (($row + $column) % 2) === 0 ? $jpeg : $png;
                    $builder = $builder->image($source, ImagePlacement::at($x, $y, width: 72));
                }
            }
        }

        return $builder->build();
    }

    private function buildFormsDocument(): Document
    {
        $builder = DefaultDocumentBuilder::make()
            ->title('Forms document')
            ->language('en-US')
            ->pageSize(PageSize::A4())
            ->margin(Margin::all(Units::mm(18)));

        for ($page = 1; $page <= 6; ++$page) {
            if ($page > 1) {
                $builder = $builder->newPage();
            }

            $builder = $builder->text('Form page ' . $page, TextOptions::make(
                fontSize: 16,
                lineHeight: 20,
                spacingAfter: 10,
            ));

            for ($field = 0; $field < 6; ++$field) {
                $y = 730 - ($field * 58);
                $builder = $builder
                    ->text('Field ' . $page . '-' . $field, TextOptions::make(
                        x: 48,
                        y: $y + 18,
                        fontSize: 10,
                        lineHeight: 12,
                    ))
                    ->textField('customer_' . $page . '_' . $field, 48, $y, 180, 20, 'Ada', 'Customer')
                    ->checkbox('accept_' . $page . '_' . $field, 248, $y + 3, 14, ($field % 2) === 0, 'Accept')
                    ->radioButton('delivery_' . $page . '_' . $field, 'standard', 292, $y + 3, 14, $field === 0, 'Standard', 'Delivery')
                    ->radioButton('delivery_' . $page . '_' . $field, 'express', 326, $y + 3, 14, $field === 1, 'Express')
                    ->comboBox('status_' . $page . '_' . $field, 370, $y, 84, 18, ['new' => 'New', 'done' => 'Done'], 'done', 'Status')
                    ->listBox('skills_' . $page . '_' . $field, 468, $y - 16, 70, 36, ['php' => 'PHP', 'pdf' => 'PDF'], ['php'], 'Skills');
            }
        }

        return $builder->build();
    }

    private function buildPdfaDocument(): Document
    {
        $font = EmbeddedFontSource::fromPath(__DIR__ . '/../assets/fonts/noto-sans/NotoSans-Regular.ttf');
        $builder = DefaultDocumentBuilder::make()
            ->title('PDF/A document')
            ->language('en-US')
            ->profile(Profile::pdfA1b())
            ->pageSize(PageSize::A4())
            ->margin(Margin::all(Units::mm(18)));

        for ($page = 1; $page <= 6; ++$page) {
            if ($page > 1) {
                $builder = $builder->newPage();
            }

            $builder = $builder->text('Form page ' . $page, TextOptions::make(
                embeddedFont: $font,
                fontSize: 16,
                lineHeight: 20,
                spacingAfter: 10,
            ));

            for ($paragraph = 0; $paragraph < 12; ++$paragraph) {
                $builder = $builder->text(
                    'Archival output requires deterministic metadata, embedded fonts and valid color profiles. ' . $this->lorem(3),
                    TextOptions::make(
                        embeddedFont: $font,
                        width: 490,
                        fontSize: 10.5,
                        lineHeight: 14.5,
                        spacingAfter: 6,
                    ),
                );
            }
        }

        return $builder->build();
    }

    /**
     * @return list<TextSegment>
     */
    private function contentHeavySegments(int $paragraph): array
    {
        $segments = [];

        for ($index = 0; $index < 24; ++$index) {
            $segments[] = TextSegment::plain('Section ' . $paragraph . '-' . $index . ' ');
            $segments[] = TextSegment::link(
                'docs',
                TextLink::externalUrl(
                    'https://example.com/docs/' . $paragraph . '/' . $index,
                    'Open docs',
                    'Open documentation',
                    'docs-' . $paragraph . '-' . $index,
                ),
            );
            $segments[] = TextSegment::plain(' and ');
            $segments[] = TextSegment::link(
                'api',
                TextLink::externalUrl(
                    'https://example.com/api/' . $paragraph . '/' . $index,
                    'Open api',
                    'Open API',
                    'api-' . $paragraph . '-' . $index,
                ),
            );
            $segments[] = TextSegment::plain(' for rollout. ');
        }

        return $segments;
    }

    private function elapsedMs(int $startedAt): float
    {
        return $this->round3((hrtime(true) - $startedAt) / 1_000_000);
    }

    private function peakDeltaMb(int $baselineMemory): float
    {
        return $this->round3((memory_get_peak_usage(true) - $baselineMemory) / 1024 / 1024);
    }

    private function round3(int|float $value): float
    {
        return (float) number_format((float) $value, 3, '.', '');
    }

    private function lorem(int $sentences): string
    {
        $parts = array_fill(0, $sentences, 'Structured PDF output benefits from deterministic layout, stable object graphs and predictable serialization costs.');

        return implode(' ', $parts);
    }

    private function tinyRgbJpegBytes(): string
    {
        return base64_decode(
            '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAMCAgICAgMCAgIDAwMDBAYEBAQEBAgGBgUGCQgKCgkICQkKDA8MCgsOCwkJDRENDg8QEBEQCgwSExIQEw8QEBD/2wBDAQMDAwQDBAgEBAgQCwkLEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBD/wAARCAABAAEDAREAAhEBAxEB/8QAFAABAAAAAAAAAAAAAAAAAAAACP/EABQQAQAAAAAAAAAAAAAAAAAAAAD/xAAVAQEBAAAAAAAAAAAAAAAAAAAHCf/EABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAMAwEAAhEDEQA/ADoDFU3/2Q==',
            true,
        ) ?: throw new RuntimeException('Unable to decode JPEG benchmark fixture.');
    }

    private function writeTinyPngFixture(): string
    {
        $path = __DIR__ . '/../var/benchmarks/tiny-rgba.png';

        if (is_file($path)) {
            return $path;
        }

        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create benchmark fixture directory.');
        }

        $bytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAIGNIUk0AAHomAACAhAAA+gAAAIDoAAB1MAAA6mAAADqYAAAXcJy6UTwAAAANSURBVAjXY/jPwPAfAAUAAf9ynFJnAAAAJXRFWHRkYXRlOmNyZWF0ZQAyMDI2LTA0LTEyVDA2OjA0OjI1KzAwOjAwLhCIzQAAACV0RVh0ZGF0ZTptb2RpZnkAMjAyNi0wNC0xMlQwNjowNDoyNSswMDowMF9NMHEAAAAodEVYdGRhdGU6dGltZXN0YW1wADIwMjYtMDQtMTJUMDY6MDQ6MjUrMDA6MDAIWBGuAAAAAElFTkSuQmCC',
            true,
        );

        if ($bytes === false) {
            throw new RuntimeException('Unable to decode PNG benchmark fixture.');
        }

        if (file_put_contents($path, $bytes) === false) {
            throw new RuntimeException('Unable to write PNG benchmark fixture.');
        }

        return $path;
    }
}

$options = getopt('', ['scenario::', 'format::', 'iterations::', 'warmup::']);
$benchmark = new PerformanceBenchmark();
$availableScenarios = $benchmark->scenarios();
$scenarioOptions = $options['scenario'] ?? 'all';
$scenarioNames = is_array($scenarioOptions) ? $scenarioOptions : [$scenarioOptions];

if ($scenarioNames !== ['all']) {
    $selectedScenarios = [];

    foreach ($scenarioNames as $scenarioName) {
        if (!isset($availableScenarios[$scenarioName])) {
            fwrite(STDERR, "Unknown scenario: {$scenarioName}\n");
            exit(1);
        }

        $selectedScenarios[$scenarioName] = $availableScenarios[$scenarioName];
    }

    $availableScenarios = $selectedScenarios;
}

$iterations = max(1, (int) ($options['iterations'] ?? PerformanceBenchmark::DEFAULT_ITERATIONS));
$warmup = max(0, (int) ($options['warmup'] ?? PerformanceBenchmark::DEFAULT_WARMUP));
$results = $benchmark->run($availableScenarios, $warmup, $iterations);
$format = $options['format'] ?? 'table';

if ($format === 'json') {
    $json = json_encode([
        'warmup' => $warmup,
        'iterations' => $iterations,
        'results' => $results,
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    fwrite(STDOUT, $json . "\n");
    exit(0);
}

fwrite(STDOUT, sprintf("Warmup: %d, iterations: %d\n", $warmup, $iterations));
fwrite(STDOUT, "scenario | total_ms | build_ms | plan_ms | render_ms | peak_mb | objects | pages | bytes\n");

foreach ($results as $name => $summary) {
    fwrite(STDOUT, sprintf(
        "%s | %.3f | %.3f | %.3f | %.3f | %.3f | %.0f | %.0f | %.0f\n",
        $name,
        $summary['total_ms']['avg'],
        $summary['document_build_ms']['avg'],
        $summary['plan_build_ms']['avg'],
        $summary['render_ms']['avg'],
        $summary['peak_memory_mb']['avg'],
        $summary['pdf_object_count']['avg'],
        $summary['page_count']['avg'],
        $summary['bytes']['avg'],
    ));
}
