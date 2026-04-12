<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Debug\DebugConfig;
use Kalle\Pdf\Debug\InMemoryDebugSink;
use Kalle\Pdf\Debug\LogLevel;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\DocumentRenderer;
use Kalle\Pdf\Writer\StringOutput;
use PHPUnit\Framework\TestCase;

final class DocumentDebugIntegrationTest extends TestCase
{
    public function testDocumentMakeExposesTheDebugApi(): void
    {
        $sink = new InMemoryDebugSink();

        $document = Document::make()
            ->title('Rechnung 2026-001')
            ->debug(DebugConfig::make()->logLifecycle(LogLevel::Info)->sink($sink))
            ->build();

        self::assertSame('Rechnung 2026-001', $document->title);
        self::assertTrue($document->debugger->config->shouldLogLifecycle());
        self::assertCount(2, $sink->records());
        self::assertSame('document.created', $sink->records()[0]['event']);
        self::assertSame('page.added', $sink->records()[1]['event']);
    }

    public function testRenderingEmitsLifecyclePdfAndPerformanceEvents(): void
    {
        $sink = new InMemoryDebugSink();
        $document = Document::make()
            ->title('Observed Document')
            ->debug(
                DebugConfig::json()
                    ->logLifecycle(LogLevel::Info)
                    ->logPdfStructure(LogLevel::Trace)
                    ->logPerformance(LogLevel::Info)
                    ->sink($sink),
            )
            ->text('Hello PDF')
            ->build();

        $output = new StringOutput();

        (new DocumentRenderer())->write($document, $output);
        $records = $sink->records();

        self::assertSame('%PDF-', substr($output->contents(), 0, 5));
        self::assertContains('write.started', $this->eventsForChannel($records, 'lifecycle'));
        self::assertContains('write.finished', $this->eventsForChannel($records, 'lifecycle'));
        self::assertContains('object.created', $this->eventsForChannel($records, 'pdf'));
        self::assertContains('object.serialized', $this->eventsForChannel($records, 'pdf'));
        self::assertContains('stream.serialized', $this->eventsForChannel($records, 'pdf'));
        self::assertContains('xref.written', $this->eventsForChannel($records, 'pdf'));
        self::assertContains('trailer.written', $this->eventsForChannel($records, 'pdf'));
        self::assertContains('document.render', $this->eventsForChannel($records, 'performance'));
        self::assertContains('page.render', $this->eventsForChannel($records, 'performance'));
    }

    /**
     * @param list<array{channel: string, level: LogLevel, event: string, context: array<string, scalar|null>}> $records
     * @return list<string>
     */
    private function eventsForChannel(array $records, string $channel): array
    {
        return array_values(array_map(
            static fn (array $record): string => $record['event'],
            array_filter(
                $records,
                static fn (array $record): bool => $record['channel'] === $channel,
            ),
        ));
    }
}
