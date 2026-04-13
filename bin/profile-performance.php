<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Kalle\Pdf\Debug\DebugConfig;
use Kalle\Pdf\Debug\InMemoryDebugSink;
use Kalle\Pdf\Debug\LogLevel;
use Kalle\Pdf\Document\DefaultDocumentBuilder;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\DocumentRenderer;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Drawing\Units;
use Kalle\Pdf\Font\EmbeddedFontSource;
use Kalle\Pdf\Page\Margin;
use Kalle\Pdf\Page\PageSize;
use Kalle\Pdf\Text\TextOptions;
use Kalle\Pdf\Text\TextLink;
use Kalle\Pdf\Text\TextSegment;
use Kalle\Pdf\Writer\StringOutput;

final class PerformanceProfiler
{
    /**
     * @return array<string, callable(InMemoryDebugSink): Document>
     */
    public function scenarios(): array
    {
        return [
            'text-heavy' => fn (InMemoryDebugSink $sink): Document => $this->buildTextHeavyDocument($sink),
            'content-heavy' => fn (InMemoryDebugSink $sink): Document => $this->buildContentHeavyDocument($sink),
            'pdfa' => fn (InMemoryDebugSink $sink): Document => $this->buildPdfaDocument($sink),
        ];
    }

    /**
     * @param array<string, callable(InMemoryDebugSink): Document> $scenarios
     * @return array<string, array<string, mixed>>
     */
    public function profile(array $scenarios, int $iterations): array
    {
        $results = [];

        foreach ($scenarios as $name => $factory) {
            $iterationsResults = [];

            for ($iteration = 0; $iteration < $iterations; ++$iteration) {
                $sink = new InMemoryDebugSink();

                $buildStartedAt = hrtime(true);
                $document = $factory($sink);
                $buildMs = $this->elapsedMs($buildStartedAt);

                $renderStartedAt = hrtime(true);
                $output = new StringOutput();
                (new DocumentRenderer())->write($document, $output);
                $renderMs = $this->elapsedMs($renderStartedAt);

                $iterationsResults[] = [
                    'build_ms' => $buildMs,
                    'render_ms' => $renderMs,
                    'scopes' => $this->summarizeScopes($sink->records()),
                ];
            }

            $results[$name] = $this->summarizeIterations($iterationsResults);
        }

        return $results;
    }

    private function buildTextHeavyDocument(InMemoryDebugSink $sink): Document
    {
        $font = EmbeddedFontSource::fromPath(__DIR__ . '/../assets/fonts/noto-sans/NotoSans-Regular.ttf');
        $builder = DefaultDocumentBuilder::make()
            ->debug(DebugConfig::make()->logPerformance(LogLevel::Info)->sink($sink))
            ->title('Text-heavy document')
            ->pageSize(PageSize::A4())
            ->margin(Margin::all(Units::mm(16)));

        $paragraph = 'Extended Latin mix: ÄÖÜ ß cafe facade resume naive cooperate. ' . $this->lorem(4);

        for ($page = 1; $page <= 10; ++$page) {
            if ($page > 1) {
                $builder = $builder->newPage();
            }

            for ($block = 0; $block < 16; ++$block) {
                $builder = $builder->text($paragraph, new TextOptions(
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

    private function buildPdfaDocument(InMemoryDebugSink $sink): Document
    {
        $font = EmbeddedFontSource::fromPath(__DIR__ . '/../assets/fonts/noto-sans/NotoSans-Regular.ttf');
        $builder = DefaultDocumentBuilder::make()
            ->debug(DebugConfig::make()->logPerformance(LogLevel::Info)->sink($sink))
            ->title('PDF/A document')
            ->language('en-US')
            ->profile(Profile::pdfA1b())
            ->pageSize(PageSize::A4())
            ->margin(Margin::all(Units::mm(18)));

        for ($page = 1; $page <= 6; ++$page) {
            if ($page > 1) {
                $builder = $builder->newPage();
            }

            $builder = $builder->text('Form page ' . $page, new TextOptions(
                embeddedFont: $font,
                fontSize: 16,
                lineHeight: 20,
                spacingAfter: 10,
            ));

            for ($paragraph = 0; $paragraph < 12; ++$paragraph) {
                $builder = $builder->text(
                    'Archival output requires deterministic metadata, embedded fonts and valid color profiles. ' . $this->lorem(3),
                    new TextOptions(
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

    private function buildContentHeavyDocument(InMemoryDebugSink $sink): Document
    {
        $font = EmbeddedFontSource::fromPath(__DIR__ . '/../assets/fonts/noto-sans/NotoSans-Regular.ttf');
        $builder = DefaultDocumentBuilder::make()
            ->debug(DebugConfig::make()->logPerformance(LogLevel::Info)->sink($sink))
            ->title('Content-heavy document')
            ->pageSize(PageSize::A4())
            ->margin(Margin::all(Units::mm(16)));

        for ($page = 1; $page <= 10; ++$page) {
            if ($page > 1) {
                $builder = $builder->newPage();
            }

            for ($paragraph = 0; $paragraph < 18; ++$paragraph) {
                $builder = $builder->text($this->contentHeavySegments($paragraph), new TextOptions(
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

    private function lorem(int $sentences): string
    {
        $parts = array_fill(0, $sentences, 'Structured PDF output benefits from deterministic layout, stable object graphs and predictable serialization costs.');

        return implode(' ', $parts);
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
        return round((hrtime(true) - $startedAt) / 1_000_000, 3);
    }

    /**
     * @param list<array{channel: string, level: LogLevel, event: string, context: array<string, scalar|null>}> $records
     * @return array<string, array{count: int, total_ms: float, avg_ms: float, max_ms: float}>
     */
    private function summarizeScopes(array $records): array
    {
        $summary = [];

        foreach ($records as $record) {
            if ($record['channel'] !== 'performance') {
                continue;
            }

            $duration = $record['context']['duration_ms'] ?? null;

            if (!is_float($duration) && !is_int($duration)) {
                continue;
            }

            $event = $record['event'];
            $summary[$event] ??= [
                'count' => 0,
                'total_ms' => 0.0,
                'avg_ms' => 0.0,
                'max_ms' => 0.0,
            ];
            $summary[$event]['count']++;
            $summary[$event]['total_ms'] += (float) $duration;
            $summary[$event]['max_ms'] = max($summary[$event]['max_ms'], (float) $duration);
        }

        foreach ($summary as $event => $eventSummary) {
            $summary[$event]['total_ms'] = round($eventSummary['total_ms'], 3);
            $summary[$event]['avg_ms'] = round($eventSummary['total_ms'] / max(1, $eventSummary['count']), 3);
            $summary[$event]['max_ms'] = round($eventSummary['max_ms'], 3);
        }

        uasort(
            $summary,
            static fn (array $left, array $right): int => $right['total_ms'] <=> $left['total_ms'],
        );

        return $summary;
    }

    /**
     * @param list<array{build_ms: float, render_ms: float, scopes: array<string, array{count: int, total_ms: float, avg_ms: float, max_ms: float}>}> $iterationsResults
     * @return array<string, mixed>
     */
    private function summarizeIterations(array $iterationsResults): array
    {
        $buildMs = [];
        $renderMs = [];
        $scopeTotals = [];

        foreach ($iterationsResults as $iterationResult) {
            $buildMs[] = $iterationResult['build_ms'];
            $renderMs[] = $iterationResult['render_ms'];

            foreach ($iterationResult['scopes'] as $event => $scopeSummary) {
                $scopeTotals[$event] ??= ['count' => 0, 'total_ms' => 0.0, 'max_ms' => 0.0];
                $scopeTotals[$event]['count'] += $scopeSummary['count'];
                $scopeTotals[$event]['total_ms'] += $scopeSummary['total_ms'];
                $scopeTotals[$event]['max_ms'] = max($scopeTotals[$event]['max_ms'], $scopeSummary['max_ms']);
            }
        }

        foreach ($scopeTotals as $event => $scopeSummary) {
            $scopeTotals[$event] = [
                'count' => $scopeSummary['count'],
                'total_ms' => round($scopeSummary['total_ms'], 3),
                'avg_ms_per_iteration' => round($scopeSummary['total_ms'] / max(1, count($iterationsResults)), 3),
                'avg_ms_per_scope' => round($scopeSummary['total_ms'] / max(1, $scopeSummary['count']), 3),
                'max_ms' => round($scopeSummary['max_ms'], 3),
            ];
        }

        uasort(
            $scopeTotals,
            static fn (array $left, array $right): int => $right['total_ms'] <=> $left['total_ms'],
        );

        return [
            'iterations' => count($iterationsResults),
            'build_ms_avg' => round(array_sum($buildMs) / max(1, count($buildMs)), 3),
            'render_ms_avg' => round(array_sum($renderMs) / max(1, count($renderMs)), 3),
            'top_scopes' => array_slice($scopeTotals, 0, 10, true),
        ];
    }
}

$options = getopt('', ['scenario::', 'iterations::']);
$profiler = new PerformanceProfiler();
$availableScenarios = $profiler->scenarios();
$scenarioOptions = $options['scenario'] ?? ['text-heavy', 'pdfa'];
$scenarioNames = is_array($scenarioOptions) ? $scenarioOptions : [$scenarioOptions];
$iterations = max(1, (int) ($options['iterations'] ?? 3));
$selectedScenarios = [];

foreach ($scenarioNames as $scenarioName) {
    if (!isset($availableScenarios[$scenarioName])) {
        fwrite(STDERR, "Unknown scenario: {$scenarioName}\n");
        exit(1);
    }

    $selectedScenarios[$scenarioName] = $availableScenarios[$scenarioName];
}

$results = $profiler->profile($selectedScenarios, $iterations);
$json = json_encode($results, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

if (!is_string($json)) {
    throw new RuntimeException('Unable to encode profiling results.');
}

fwrite(STDOUT, $json . "\n");
