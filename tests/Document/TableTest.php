<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Layout;

use InvalidArgumentException;
use Kalle\Pdf\Document;
use Kalle\Pdf\Layout\Geometry\Position;
use Kalle\Pdf\Layout\Table\Definition\TableCaption;
use Kalle\Pdf\Layout\Table\Definition\TableCell;
use Kalle\Pdf\Layout\Table\Definition\TableHeaderScope;
use Kalle\Pdf\Layout\Table\Style\CellStyle;
use Kalle\Pdf\Layout\Table\Style\FooterStyle;
use Kalle\Pdf\Layout\Table\Style\HeaderStyle;
use Kalle\Pdf\Layout\Table\Style\RowStyle;
use Kalle\Pdf\Layout\Table\Style\TableBorder;
use Kalle\Pdf\Layout\Table\Style\TablePadding;
use Kalle\Pdf\Layout\Table\Style\TableStyle;
use Kalle\Pdf\Layout\Text\Input\TextSegment;
use Kalle\Pdf\Layout\Value\HorizontalAlign;
use Kalle\Pdf\Layout\Value\VerticalAlign;
use Kalle\Pdf\Profile\Profile;
use Kalle\Pdf\Style\Color;
use Kalle\Pdf\Tests\Support\CreatesPdfUaTestDocument;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TableTest extends TestCase
{
    use CreatesPdfUaTestDocument;

    #[Test]
    public function it_exposes_the_current_cursor_position(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        $table = $page->createTable(new Position(20, 260), 170, [85, 85]);

        self::assertSame(260.0, $table->getCursorY());
    }

    #[Test]
    public function it_allows_configuring_font_and_styles_fluently(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document
            ->registerFont('Helvetica')
            ->registerFont('Helvetica-Bold');
        $page = $document->addPage();
        $table = $page->createTable(new Position(20, 260), 170, [85, 85]);

        $result = $table
            ->font('Helvetica', 10)
            ->style(new TableStyle(border: TableBorder::none()))
            ->rowStyle(new RowStyle(textColor: Color::rgb(255, 0, 0)))
            ->headerStyle(new HeaderStyle(fillColor: Color::gray(0.9)))
            ->addHeaderRow(['A', 'B'])
            ->addRow(['1', '2']);

        self::assertSame($table, $result);
        self::assertStringContainsString('(A) Tj', $page->getContents()->render());
        self::assertStringContainsString('(1) Tj', $page->getContents()->render());
    }

    #[Test]
    public function it_applies_footer_styles_to_footer_rows(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document
            ->registerFont('Helvetica')
            ->registerFont('Helvetica-Bold');
        $page = $document->addPage();

        $page->createTable(new Position(20, 260), 170, [85, 85])
            ->footerStyle(new FooterStyle(
                fillColor: Color::gray(0.85),
                textColor: Color::rgb(255, 0, 0),
            ))
            ->addHeaderRow(['Name', 'Wert'])
            ->addRow(['Produkt A', '19,99 EUR'])
            ->addFooterRow(['Summe', '19,99 EUR']);

        $contents = $document->render();

        self::assertStringContainsString("20 188 85 24 re\nf", $contents);
        self::assertStringContainsString("105 188 85 24 re\nf", $contents);
        self::assertStringContainsString('1 0 0 rg', $contents);
        self::assertStringContainsString('(Summe) Tj', $contents);
    }

    #[Test]
    public function it_renders_a_caption_above_the_table(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document
            ->registerFont('Helvetica')
            ->registerFont('Helvetica-Bold');
        $page = $document->addPage();

        $table = $page->createTable(new Position(20, 260), 170, [85, 85])
            ->caption(new TableCaption('Artikeluebersicht', size: 12, spacingAfter: 4.0))
            ->addHeaderRow(['Artikel', 'Preis'])
            ->addRow(['Produkt A', '19,99 EUR']);

        $contents = $page->getContents()->render();

        self::assertSame(193.6, $table->getCursorY());
        self::assertStringContainsString('(Artikeluebersicht) Tj', $contents);
        self::assertLessThan(
            strpos($contents, '(Artikel) Tj'),
            strpos($contents, '(Artikeluebersicht) Tj'),
        );
    }

    #[Test]
    public function it_renders_a_table_row_with_header_and_body_cells(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document
            ->registerFont('Helvetica')
            ->registerFont('Helvetica-Bold');
        $page = $document->addPage();

        $table = $page->createTable(new Position(20, 260), 170, [50, 70, 50])
            ->headerStyle(new HeaderStyle(
                fillColor: Color::gray(0.9),
                textColor: Color::rgb(255, 0, 0),
            ))
            ->rowStyle(new RowStyle(
                textColor: Color::gray(0.2),
            ))
            ->addHeaderRow(['ID', 'Titel', 'Preis'])
            ->addRow([
                '1',
                new TableCell('Produkt A', style: new CellStyle(horizontalAlign: HorizontalAlign::CENTER)),
                [new TextSegment(text: '19,99 EUR', underline: true)],
            ]);

        self::assertSame($page, $table->getPage());
        self::assertStringContainsString("20 236 50 24 re\nf", $page->getContents()->render());
        self::assertStringContainsString("20 236 50 24 re\nS", $page->getContents()->render());
        self::assertStringContainsString('/BaseFont /Helvetica', $document->render());
        self::assertStringContainsString('/BaseFont /Helvetica-Bold', $document->render());
        self::assertStringContainsString('(Produkt A) Tj', $page->getContents()->render());
        self::assertStringContainsString('(19,99) Tj', $page->getContents()->render());
    }

    #[Test]
    public function it_renders_table_boxes_as_artifacts_for_pdf_ua_1(): void
    {
        $document = $this->createPdfUaTestDocument(title: 'Accessible Table');
        $page = $document->addPage();

        $page->createTable(new Position(20, 260), 170, [85, 85])
            ->font(self::pdfUaRegularFont(), 10)
            ->addHeaderRow(['Column A', 'Column B'])
            ->addRow(['Value A', 'Value B']);

        $rendered = $document->render();

        self::assertStringContainsString('/Artifact BMC', $rendered);
        self::assertStringContainsString('/Type /StructElem /S /Table', $rendered);
        self::assertStringContainsString('/Type /StructElem /S /TH', $rendered);
        self::assertStringContainsString('/Type /StructElem /S /TD', $rendered);
        self::assertStringContainsString('/Scope /Column', $rendered);
    }

    #[Test]
    public function it_supports_row_header_cells_for_pdf_ua_1(): void
    {
        $document = $this->createPdfUaTestDocument(title: 'Accessible Row Header Table');
        $page = $document->addPage();

        $page->createTable(new Position(20, 260), 170, [70, 100])
            ->font(self::pdfUaRegularFont(), 10)
            ->addHeaderRow(['Metric', 'Value'])
            ->addRow([
                new TableCell('Weight', headerScope: TableHeaderScope::Row),
                '12 kg',
            ]);

        $rendered = $document->render();

        self::assertStringContainsString('/Type /StructElem /S /TH', $rendered);
        self::assertStringContainsString('/Scope /Row', $rendered);
        self::assertStringContainsString('/Type /StructElem /S /TD', $rendered);
    }

    #[Test]
    public function it_supports_both_scope_header_cells_for_pdf_ua_1(): void
    {
        $document = $this->createPdfUaTestDocument(title: 'Accessible Matrix Table');
        $page = $document->addPage();

        $page->createTable(new Position(20, 260), 170, [55, 55, 60])
            ->font(self::pdfUaRegularFont(), 10)
            ->addHeaderRow([
                new TableCell('Area', headerScope: TableHeaderScope::Both),
                'Open',
                'Closed',
            ])
            ->addRow([
                new TableCell('North', headerScope: TableHeaderScope::Row),
                '5',
                '1',
            ]);

        $rendered = $document->render();

        self::assertStringContainsString('/Scope /Both', $rendered);
        self::assertStringContainsString('/Scope /Row', $rendered);
    }

    #[Test]
    public function it_renders_table_captions_as_structured_caption_elements_for_pdf_ua_1(): void
    {
        $document = $this->createPdfUaTestDocument(title: 'Accessible Captioned Table');
        $page = $document->addPage();

        $page->createTable(new Position(20, 260), 170, [85, 85])
            ->font(self::pdfUaRegularFont(), 10)
            ->caption(new TableCaption('Quartalszahlen'))
            ->addHeaderRow(['Q1', 'Q2'])
            ->addRow(['10', '12']);

        $rendered = $document->render();

        self::assertStringContainsString('/Type /StructElem /S /Caption', $rendered);
        self::assertLessThan(
            strpos($rendered, '/Type /StructElem /S /TR'),
            strpos($rendered, '/Type /StructElem /S /Caption'),
        );
    }

    #[Test]
    public function it_keeps_pdf_ua_table_captions_on_the_first_page_while_headers_repeat_across_pages(): void
    {
        $document = $this->createPdfUaTestDocument(title: 'Accessible Multipage Table', registerBold: true);
        $firstPage = $document->addPage(220, 180);

        $table = $firstPage->createTable(new Position(12, 132), 196, [46, 50, 50, 50], 18)
            ->font(self::pdfUaRegularFont(), 10)
            ->caption(new TableCaption(
                'Regional service quality by quarter',
                fontName: self::pdfUaBoldFont(),
                size: 12,
                spacingAfter: 5.0,
            ))
            ->addHeaderRow([
                new TableCell('Region', headerScope: TableHeaderScope::Both),
                'January',
                'February',
                'March',
            ]);

        foreach ([
            ['North', '98 %', '97 %', '99 %'],
            ['South', '94 %', '95 %', '96 %'],
            ['West', '99 %', '98 %', '97 %'],
            ['East', '96 %', '97 %', '95 %'],
            ['Central', '93 %', '94 %', '95 %'],
            ['Coastal', '97 %', '96 %', '98 %'],
            ['Mountain', '95 %', '94 %', '96 %'],
            ['Metro', '99 %', '99 %', '98 %'],
            ['Rural', '92 %', '93 %', '94 %'],
            ['Delta', '96 %', '95 %', '97 %'],
            ['Harbor', '98 %', '97 %', '99 %'],
            ['Valley', '94 %', '95 %', '95 %'],
        ] as [$region, $january, $february, $march]) {
            $table->addRow([
                new TableCell($region, headerScope: TableHeaderScope::Row),
                $january,
                $february,
                $march,
            ]);
        }

        $rendered = $document->render();

        self::assertGreaterThan(1, count($document->pages->pages));
        self::assertSame(1, substr_count($rendered, '/Type /StructElem /S /Caption'));
        self::assertGreaterThan(1, substr_count($rendered, '/Scope /Both'));
        self::assertGreaterThan(10, substr_count($rendered, '/Scope /Row'));
        self::assertNotSame($firstPage, $table->getPage());
    }

    #[Test]
    public function it_keeps_pdf_ua_table_captions_and_header_scopes_stable_for_multipage_tables_with_spans(): void
    {
        $document = $this->createPdfUaTestDocument(title: 'Accessible Multipage Table With Spans', registerBold: true);
        $firstPage = $document->addPage(220, 190);

        $table = $firstPage->createTable(new Position(12, 132), 196, [34, 36, 42, 42, 42], 18)
            ->font(self::pdfUaRegularFont(), 10)
            ->caption(new TableCaption(
                'Regional service quality and follow-up metrics',
                fontName: self::pdfUaBoldFont(),
                size: 12,
                spacingAfter: 5.0,
            ))
            ->addHeaderRow([
                new TableCell('Region', headerScope: TableHeaderScope::Both),
                'Metric',
                'January',
                'February',
                'March',
            ]);

        foreach ([
            ['North', '98 %', '97 %', '99 %', '1.2 h', '1.1 h', '1.0 h'],
            ['South', '94 %', '95 %', '96 %', '1.8 h', '1.6 h', '1.5 h'],
            ['West', '99 %', '98 %', '97 %', '0.9 h', '1.0 h', '1.1 h'],
            ['East', '96 %', '97 %', '95 %', '1.4 h', '1.3 h', '1.4 h'],
            ['Central', '93 %', '94 %', '95 %', '2.1 h', '1.9 h', '1.8 h'],
            ['Coastal', '97 %', '96 %', '98 %', '1.0 h', '1.1 h', '1.0 h'],
        ] as [$region, $janAvailability, $febAvailability, $marAvailability, $janResponse, $febResponse, $marResponse]) {
            $table->addRow([
                new TableCell($region, rowspan: 2, headerScope: TableHeaderScope::Row),
                'Availability',
                $janAvailability,
                $febAvailability,
                $marAvailability,
            ]);
            $table->addRow([
                'Response time',
                $janResponse,
                $febResponse,
                $marResponse,
            ]);
            $table->addRow([
                new TableCell($region . ' summary', headerScope: TableHeaderScope::Row),
                new TableCell(
                    'Stable service quality with a dedicated follow-up note across all reported months.',
                    colspan: 4,
                ),
            ]);
        }

        $rendered = $document->render();

        self::assertGreaterThan(1, count($document->pages->pages));
        self::assertSame(1, substr_count($rendered, '/Type /StructElem /S /Caption'));
        self::assertGreaterThan(1, substr_count($rendered, '/Scope /Both'));
        self::assertGreaterThanOrEqual(12, substr_count($rendered, '/Scope /Row'));
        self::assertStringContainsString('/RowSpan 2', $rendered);
        self::assertStringContainsString('/ColSpan 4', $rendered);
        self::assertGreaterThan(20, substr_count($rendered, '/Type /StructElem /S /TD'));
        self::assertNotSame($firstPage, $table->getPage());
    }

    #[Test]
    public function it_keeps_pdf_ua_table_header_matrices_stable_across_multiple_pages(): void
    {
        $document = $this->createPdfUaTestDocument(title: 'Accessible Header Matrix Table', registerBold: true);
        $firstPage = $document->addPage(220, 190);

        $table = $firstPage->createTable(new Position(12, 132), 196, [36, 40, 40, 40, 40], 18)
            ->font(self::pdfUaRegularFont(), 10)
            ->caption(new TableCaption(
                'Regional service review matrix',
                fontName: self::pdfUaBoldFont(),
                size: 12,
                spacingAfter: 5.0,
            ))
            ->addHeaderRow([
                new TableCell('Region', rowspan: 2, headerScope: TableHeaderScope::Both),
                new TableCell('Service quality', colspan: 2, headerScope: TableHeaderScope::Column),
                new TableCell('Follow-up', colspan: 2, headerScope: TableHeaderScope::Column),
            ])
            ->addHeaderRow([
                'Availability',
                'Response time',
                'Escalations',
                'Resolved',
            ]);

        foreach ([
            ['North', '98 %', '1.2 h', '2', '18'],
            ['South', '94 %', '1.8 h', '4', '14'],
            ['West', '99 %', '0.9 h', '1', '20'],
            ['East', '96 %', '1.4 h', '3', '16'],
            ['Central', '93 %', '2.1 h', '5', '12'],
            ['Coastal', '97 %', '1.0 h', '2', '19'],
            ['Mountain', '95 %', '1.5 h', '3', '15'],
            ['Metro', '99 %', '0.8 h', '1', '21'],
            ['Rural', '92 %', '2.3 h', '6', '11'],
            ['Delta', '96 %', '1.3 h', '2', '17'],
            ['Harbor', '98 %', '1.1 h', '1', '20'],
            ['Valley', '94 %', '1.9 h', '4', '13'],
        ] as [$region, $availability, $responseTime, $escalations, $resolved]) {
            $table->addRow([
                new TableCell($region, headerScope: TableHeaderScope::Row),
                $availability,
                $responseTime,
                $escalations,
                $resolved,
            ]);
        }

        $rendered = $document->render();

        self::assertGreaterThan(1, count($document->pages->pages));
        self::assertSame(1, substr_count($rendered, '/Type /StructElem /S /Caption'));
        self::assertGreaterThanOrEqual(2, substr_count($rendered, '/RowSpan 2'));
        self::assertGreaterThanOrEqual(2, substr_count($rendered, '/ColSpan 2'));
        self::assertGreaterThan(10, substr_count($rendered, '/Scope /Column'));
        self::assertGreaterThan(10, substr_count($rendered, '/Scope /Row'));
        self::assertGreaterThan(1, substr_count($rendered, '/Scope /Both'));
        self::assertNotSame($firstPage, $table->getPage());
    }

    #[Test]
    public function it_keeps_pdf_ua_table_spans_stable_with_long_content_and_page_breaks(): void
    {
        $document = $this->createPdfUaTestDocument(title: 'Accessible Span Break Table', registerBold: true);
        $firstPage = $document->addPage(364, 320);

        $table = $firstPage->createTable(new Position(12, 260), 340, [42, 46, 84, 84, 84], 14)
            ->font(self::pdfUaRegularFont(), 9)
            ->caption(new TableCaption(
                'Regional monthly service span review',
                fontName: self::pdfUaBoldFont(),
                size: 12,
                spacingAfter: 4.0,
            ))
            ->addHeaderRow([
                new TableCell('Region', headerScope: TableHeaderScope::Both),
                'Metric',
                'January',
                'February',
                'March',
            ]);

        foreach ([
            [
                'North',
                'Availability review',
                '98 %',
                '97 %',
                '99 %',
                'Follow-up action',
                '1.2 h',
                '1.1 h',
                '1.0 h',
                'North summary',
                'North remains stable overall, but the reconciled figures, closeout note and owner handover all need to stay tied to the same monthly evidence set before archival.',
            ],
            [
                'South',
                'Availability review',
                '94 %',
                '95 %',
                '96 %',
                'Follow-up action',
                '1.8 h',
                '1.6 h',
                '1.5 h',
                'South summary',
                'South is close to completion, but the rollout history, remote branch notes and final acknowledgements still need one consistent summary across all three months.',
            ],
            [
                'West',
                'Availability review',
                '99 %',
                '98 %',
                '97 %',
                'Follow-up action',
                '0.9 h',
                '1.0 h',
                '1.1 h',
                'West summary',
                'West remains within tolerance, but the corrected timeline, merged evidence pack and vendor follow-up still need to remain traceable as one combined record.',
            ],
            [
                'East',
                'Availability review',
                '96 %',
                '97 %',
                '95 %',
                'Follow-up action',
                '1.4 h',
                '1.3 h',
                '1.4 h',
                'East summary',
                'East is structurally stable, but the handover acknowledgements, branch cross references and ownership map still need to stay linked in one archive packet.',
            ],
        ] as [
            $region,
            $firstMetric,
            $janFirst,
            $febFirst,
            $marFirst,
            $secondMetric,
            $janSecond,
            $febSecond,
            $marSecond,
            $summaryLabel,
            $summaryText,
        ]) {
            $table->addRow([
                new TableCell($region, rowspan: 2, headerScope: TableHeaderScope::Row),
                $firstMetric,
                $janFirst,
                $febFirst,
                $marFirst,
            ]);
            $table->addRow([
                $secondMetric,
                $janSecond,
                $febSecond,
                $marSecond,
            ]);
            $table->addRow([
                new TableCell($summaryLabel, headerScope: TableHeaderScope::Row),
                new TableCell($summaryText, colspan: 4),
            ]);
        }

        $rendered = $document->render();

        self::assertGreaterThan(1, count($document->pages->pages));
        self::assertSame(1, substr_count($rendered, '/Type /StructElem /S /Caption'));
        self::assertGreaterThanOrEqual(4, substr_count($rendered, '/RowSpan 2'));
        self::assertGreaterThanOrEqual(4, substr_count($rendered, '/ColSpan 4'));
        self::assertGreaterThan(4, substr_count($rendered, '/Scope /Column'));
        self::assertGreaterThan(7, substr_count($rendered, '/Scope /Row'));
        self::assertGreaterThan(1, substr_count($rendered, '/Scope /Both'));
        self::assertNotSame($firstPage, $table->getPage());
    }

    #[Test]
    public function it_keeps_pdf_ua_table_header_matrices_stable_with_long_content_and_early_page_breaks(): void
    {
        $document = $this->createPdfUaTestDocument(title: 'Accessible Header Matrix Break Table', registerBold: true);
        $firstPage = $document->addPage(364, 260);

        $table = $firstPage->createTable(new Position(12, 200), 340, [46, 44, 80, 66, 104], 14)
            ->font(self::pdfUaRegularFont(), 9)
            ->caption(new TableCaption(
                'Regional issue review matrix',
                fontName: self::pdfUaBoldFont(),
                size: 12,
                spacingAfter: 4.0,
            ))
            ->addHeaderRow([
                new TableCell('Region', rowspan: 2, headerScope: TableHeaderScope::Both),
                new TableCell('Operations', colspan: 2, headerScope: TableHeaderScope::Column),
                new TableCell('Follow-up', colspan: 2, headerScope: TableHeaderScope::Column),
            ])
            ->addHeaderRow([
                'Status',
                'Assessment',
                'Owner',
                'Next step',
            ]);

        foreach ([
            [
                'North',
                'Stable',
                'A longer assessment note confirms that availability stayed high while two edge locations still need manual monitoring during the morning handover.',
                'Regional ops lead',
                'Confirm the revised handover checklist, collect one additional day of evidence and send the final note back to the service desk lead.',
            ],
            [
                'South',
                'Review',
                'The service recovered after a routing issue, but the branch rollout still needs another validation round before the region can close the incident.',
                'Field coordination',
                'Review the remaining branch exceptions, update the communication pack and schedule the final rollback window with the network team.',
            ],
            [
                'West',
                'Stable',
                'The quarterly review is positive, although one escalation requires a written explanation because the response-time target was missed on a single weekend.',
                'Incident manager',
                'Attach the retrospective summary, publish the corrected service note and validate the revised escalation rota with the on-call team.',
            ],
            [
                'East',
                'Watch',
                'The region meets the main targets, but repeated staffing changes created inconsistent follow-up notes and an incomplete ownership handover.',
                'Regional support',
                'Document the ownership changes, align the handover template and review the open actions with the regional support lead.',
            ],
            [
                'Central',
                'Escalated',
                'This region still shows the highest risk because the branch deployment, response backlog and vendor coordination all remain open in parallel.',
                'Programme office',
                'Consolidate the vendor timeline, close the oldest backlog items and escalate unresolved blockers to the steering round this week.',
            ],
            [
                'Coastal',
                'Stable',
                'Coastal performance is strong overall, but the offshore sites need a clearer fallback plan for short-notice weather interruptions.',
                'Site operations',
                'Validate the weather fallback checklist, refresh the standby contacts and publish the updated fallback instructions to local teams.',
            ],
            [
                'Mountain',
                'Review',
                'Monitoring remained stable, yet one remote site produced delayed acknowledgements and therefore a misleading escalation trail in the weekly report.',
                'Monitoring lead',
                'Correct the weekly report, explain the delayed acknowledgements and keep the remote-site checks active for another reporting cycle.',
            ],
            [
                'Metro',
                'Stable',
                'Metro closed all urgent incidents, but the final documentation pass still needs to confirm that the temporary workaround is fully removed.',
                'Service owner',
                'Confirm the workaround removal, archive the temporary instructions and publish the final closeout note to stakeholders.',
            ],
        ] as [$region, $status, $assessment, $owner, $nextStep]) {
            $table->addRow([
                new TableCell($region, headerScope: TableHeaderScope::Row),
                $status,
                $assessment,
                $owner,
                $nextStep,
            ]);
        }

        $rendered = $document->render();

        self::assertGreaterThan(2, count($document->pages->pages));
        self::assertSame(1, substr_count($rendered, '/Type /StructElem /S /Caption'));
        self::assertGreaterThanOrEqual(3, substr_count($rendered, '/RowSpan 2'));
        self::assertGreaterThanOrEqual(3, substr_count($rendered, '/ColSpan 2'));
        self::assertGreaterThan(15, substr_count($rendered, '/Scope /Column'));
        self::assertGreaterThan(7, substr_count($rendered, '/Scope /Row'));
        self::assertGreaterThan(1, substr_count($rendered, '/Scope /Both'));
        self::assertNotSame($firstPage, $table->getPage());
    }

    #[Test]
    public function it_keeps_pdf_ua_tables_stable_with_narrow_columns_empty_cells_and_unbreakable_tokens(): void
    {
        $document = $this->createPdfUaTestDocument(title: 'Accessible Narrow Column Table', registerBold: true);
        $firstPage = $document->addPage(240, 220);

        $table = $firstPage->createTable(new Position(12, 162), 216, [28, 32, 48, 34, 74], 12)
            ->font(self::pdfUaRegularFont(), 9)
            ->caption(new TableCaption(
                'Compact issue constraint log',
                fontName: self::pdfUaBoldFont(),
                size: 11,
                spacingAfter: 4.0,
            ))
            ->addHeaderRow([
                'Area',
                'Queue',
                'Constraint token',
                'Owner',
                'Action',
            ]);

        foreach ([
            ['North', '', 'INC2026ALPHAOMEGA0004711', 'Ops', 'Escalate owner handover and capture the aftercare notes before Friday.'],
            ['South', 'Review', '', '', 'Keep the branch note open until the external approval arrives.'],
            ['West', 'Backlog', 'REGIONALHANDOVERALPHA2026040801', 'Team', 'Consolidate duplicated notes and publish the shortened exception list.'],
            ['East', '', 'SUPPLIERCHAINBLOCKER202604081245', 'Vendor', 'Align the contingency owners and archive the obsolete workaround memo.'],
            ['Central', 'Stable', '', 'Office', ''],
            ['Coastal', 'Watch', 'REMOTESITEFOLLOWUPTOKEN20260408Z', '', 'Refresh the fallback roster and confirm the weather escalation path.'],
        ] as [$area, $queue, $constraintToken, $owner, $action]) {
            $table->addRow([
                new TableCell($area, headerScope: TableHeaderScope::Row),
                $queue,
                $constraintToken,
                $owner,
                $action,
            ]);
        }

        $rendered = $document->render();

        self::assertGreaterThan(1, count($document->pages->pages));
        self::assertSame(1, substr_count($rendered, '/Type /StructElem /S /Caption'));
        self::assertGreaterThan(4, substr_count($rendered, '/Scope /Column'));
        self::assertGreaterThan(5, substr_count($rendered, '/Scope /Row'));
        self::assertNotSame($firstPage, $table->getPage());
    }

    #[Test]
    public function it_renders_cells_with_colspan(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document
            ->registerFont('Helvetica')
            ->registerFont('Helvetica-Bold');
        $page = $document->addPage();

        $page->createTable(new Position(20, 260), 170, [30, 50, 40, 50])
            ->addHeaderRow(['#', 'Titel', 'Status', 'Preis'])
            ->addRow([
                '1',
                new TableCell(
                    'Zusammengefasste Beschreibung',
                    colspan: 2,
                    style: new CellStyle(horizontalAlign: HorizontalAlign::LEFT),
                ),
                '19,99 EUR',
            ]);

        self::assertStringContainsString("50 164 90 55.2 re\nS", $page->getContents()->render());
        self::assertStringContainsString('(Zusammengef) Tj', $page->getContents()->render());
        self::assertStringContainsString('(asste) Tj', $page->getContents()->render());
    }

    #[Test]
    public function it_renders_cells_with_rowspan(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document
            ->registerFont('Helvetica')
            ->registerFont('Helvetica-Bold');
        $page = $document->addPage();

        $page->createTable(new Position(20, 260), 170, [40, 60, 70])
            ->addHeaderRow(['Gruppe', 'Titel', 'Status'])
            ->addRow([
                new TableCell('A', rowspan: 2, style: new CellStyle(horizontalAlign: HorizontalAlign::CENTER)),
                'Eintrag 1',
                'Offen',
            ])
            ->addRow([
                'Eintrag 2',
                'Aktiv',
            ]);

        self::assertStringContainsString("20 137.6 40 81.6 re\nS", $page->getContents()->render());
        self::assertSame(1, substr_count($page->getContents()->render(), '(A) Tj'));
        self::assertStringContainsString('(Eintrag) Tj', $page->getContents()->render());
        self::assertSame(2, substr_count($page->getContents()->render(), '(Eintrag) Tj'));
        self::assertStringContainsString('(Aktiv) Tj', $page->getContents()->render());
    }

    #[Test]
    public function it_renders_footer_rows_after_body_rows(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document
            ->registerFont('Helvetica')
            ->registerFont('Helvetica-Bold');
        $page = $document->addPage();

        $page->createTable(new Position(20, 260), 170, [85, 85])
            ->addHeaderRow(['Name', 'Wert'])
            ->addRow(['Produkt A', '19,99 EUR'])
            ->addFooterRow(['Summe', '19,99 EUR']);

        $contents = $document->render();
        $headerPosition = strpos($contents, '(Name) Tj');
        $bodyPosition = strpos($contents, '(Produkt A) Tj');
        $footerPosition = strrpos($contents, '(Summe) Tj');

        self::assertNotFalse($headerPosition);
        self::assertNotFalse($bodyPosition);
        self::assertNotFalse($footerPosition);
        self::assertLessThan($bodyPosition, $headerPosition);
        self::assertLessThan($footerPosition, $bodyPosition);
    }

    #[Test]
    public function it_repeats_only_repeatable_header_rows_across_pages(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document
            ->registerFont('Helvetica')
            ->registerFont('Helvetica-Bold');
        $firstPage = $document->addPage(200, 160);

        $table = $firstPage->createTable(new Position(20, 115), 160, [80, 80], 20)
            ->font('Helvetica', 12)
            ->addHeaderRow(['Bereich', 'Wert'])
            ->addHeaderRow(['Nur1', 'Legende'], repeat: false);

        foreach (range(1, 8) as $index) {
            $table->addRow(['Eintrag ' . $index, 'Wert ' . $index]);
        }

        $rendered = $document->render();

        self::assertGreaterThan(1, count($document->pages->pages));
        self::assertGreaterThan(1, substr_count($rendered, '(Bereich) Tj'));
        self::assertSame(1, substr_count($rendered, '(Nur1) Tj'));
    }

    #[Test]
    public function it_renders_footer_rows_when_they_are_configured_before_body_rows(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document
            ->registerFont('Helvetica')
            ->registerFont('Helvetica-Bold');
        $firstPage = $document->addPage(200, 160);

        $table = $firstPage->createTable(new Position(20, 115), 160, [80, 80], 20)
            ->font('Helvetica', 12)
            ->addHeaderRow(['Bereich', 'Wert'])
            ->addFooterRow(['Summe', 'fortlaufend']);

        foreach (range(1, 8) as $index) {
            $table->addRow(['Eintrag ' . $index, 'Wert ' . $index]);
        }

        $rendered = $document->render();
        $bodyPosition = strrpos($rendered, '(Eintrag 8) Tj');
        $footerPosition = strrpos($rendered, '(Summe) Tj');

        self::assertGreaterThan(1, count($document->pages->pages));
        self::assertSame(1, substr_count($rendered, '(Summe) Tj'));
        self::assertNotFalse($bodyPosition);
        self::assertNotFalse($footerPosition);
        self::assertLessThan($footerPosition, $bodyPosition);
        self::assertStringContainsString('(Summe) Tj', $table->getPage()->getContents()->render());
    }

    #[Test]
    public function it_supports_partial_cell_borders(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->createTable(new Position(20, 260), 170, [85, 85])
            ->style(new TableStyle(border: TableBorder::none()))
            ->addRow([
                new TableCell('Links', style: new CellStyle(border: TableBorder::only(['left', 'bottom'], color: Color::rgb(255, 0, 0)))),
                new TableCell('Rechts', style: new CellStyle(border: TableBorder::horizontal(color: Color::gray(0.5)))),
            ]);

        self::assertStringContainsString("1 0 0 RG\n1 w\n20 236 m\n20 260 l\nS", $page->getContents()->render());
        self::assertStringContainsString("1 0 0 RG\n1 w\n20 236 m\n105 236 l\nS", $page->getContents()->render());
        self::assertStringContainsString("0.5 G\n1 w\n105 260 m\n190 260 l\nS", $page->getContents()->render());
    }

    #[Test]
    public function it_merges_cell_borders_with_the_table_default_border(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->createTable(new Position(20, 260), 170, [85, 85])
            ->addRow([
                new TableCell('Links', style: new CellStyle(border: TableBorder::only(['left'], color: Color::rgb(0, 255, 0)))),
                'Rechts',
            ]);

        $contents = $page->getContents()->render();

        self::assertStringContainsString("0 1 0 RG\n1 w\n20 236 m\n20 260 l\nS", $contents);
        self::assertStringContainsString("0.75 G\n1 w\n20 260 m\n105 260 l\nS", $contents);
        self::assertStringContainsString("0.75 G\n1 w\n20 236 m\n105 236 l\nS", $contents);
        self::assertStringContainsString("0.75 G\n1 w\n105 236 m\n105 260 l\nS", $contents);
    }

    #[Test]
    public function it_supports_middle_vertical_alignment_as_table_default(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->createTable(new Position(20, 260), 170, [85, 85])
            ->style(new TableStyle(verticalAlign: VerticalAlign::MIDDLE))
            ->addRow([
                new TableCell('Kurz'),
                "Erste Zeile\nZweite Zeile\nDritte Zeile",
            ]);

        $contents = $page->getContents()->render();

        self::assertStringContainsString("26 227.6 Td\n(Kurz) Tj", $contents);
        self::assertStringContainsString("111 240.8 Td\n(Erste Zeile) Tj", $contents);
        self::assertStringContainsString('(Dritte Zeile) Tj', $contents);
    }

    #[Test]
    public function it_supports_table_padding_styles(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->createTable(new Position(20, 260), 170, [85, 85])
            ->style(new TableStyle(padding: TablePadding::symmetric(10, 4)))
            ->addRow([
                'Links',
                'Rechts',
            ]);

        $contents = $page->getContents()->render();

        self::assertStringContainsString("20 240 85 20 re\nS", $contents);
        self::assertStringContainsString("30 244 Td\n(Links) Tj", $contents);
        self::assertStringContainsString("115 244 Td\n(Rechts) Tj", $contents);
    }

    #[Test]
    public function it_allows_cells_to_override_the_table_padding(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->createTable(new Position(20, 260), 170, [170])
            ->style(new TableStyle(padding: TablePadding::all(6)))
            ->addRow([
                new TableCell('Override', style: new CellStyle(padding: TablePadding::only(top: 2, right: 4, bottom: 8, left: 20))),
            ]);

        $contents = $page->getContents()->render();

        self::assertStringContainsString("20 238 170 22 re\nS", $contents);
        self::assertStringContainsString("40 246 Td\n(Override) Tj", $contents);
    }

    #[Test]
    public function it_supports_cell_styles(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->createTable(new Position(20, 260), 170, [170])
            ->addRow([
                new TableCell(
                    'Styled',
                    style: new CellStyle(
                        horizontalAlign: HorizontalAlign::CENTER,
                        verticalAlign: VerticalAlign::MIDDLE,
                        padding: TablePadding::symmetric(10, 4),
                        fillColor: Color::gray(0.9),
                        border: TableBorder::all(color: Color::rgb(255, 0, 0)),
                    ),
                ),
            ]);

        $contents = $page->getContents()->render();

        self::assertStringContainsString("20 240 170 20 re\nf", $contents);
        self::assertStringContainsString("1 0 0 RG\n1 w\n20 240 170 20 re\nS", $contents);
        self::assertStringContainsString("88.326 245.2 Td\n(Styled) Tj", $contents);
    }

    #[Test]
    public function it_supports_table_styles_as_defaults(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->createTable(new Position(20, 260), 170, [170])
            ->style(new TableStyle(
                padding: TablePadding::symmetric(10, 4),
                border: TableBorder::all(color: Color::rgb(255, 0, 0)),
                verticalAlign: VerticalAlign::MIDDLE,
            ))
            ->addRow([
                new TableCell(
                    'Styled',
                    style: new CellStyle(horizontalAlign: HorizontalAlign::CENTER),
                ),
            ]);

        $contents = $page->getContents()->render();

        self::assertStringContainsString("1 0 0 RG\n1 w\n20 240 170 20 re\nS", $contents);
        self::assertStringContainsString("88.326 245.2 Td\n(Styled) Tj", $contents);
    }

    #[Test]
    public function it_supports_row_styles_between_table_and_cell_defaults(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document
            ->registerFont('Helvetica')
            ->registerFont('Helvetica-Bold');
        $page = $document->addPage();

        $page->createTable(new Position(20, 260), 170, [85, 85])
            ->style(new TableStyle(
                padding: TablePadding::all(6),
                border: TableBorder::all(color: Color::gray(0.75)),
                verticalAlign: VerticalAlign::TOP,
            ))
            ->rowStyle(new RowStyle(
                horizontalAlign: HorizontalAlign::CENTER,
                verticalAlign: VerticalAlign::MIDDLE,
                fillColor: Color::gray(0.9),
                textColor: Color::rgb(255, 0, 0),
                border: TableBorder::horizontal(color: Color::rgb(0, 0, 255)),
            ))
            ->addRow([
                'Links',
                new TableCell('Rechts', style: new CellStyle(fillColor: Color::gray(0.8))),
            ]);

        $contents = $page->getContents()->render();

        self::assertStringContainsString("20 236 85 24 re\nf", $contents);
        self::assertStringContainsString("0 0 1 RG\n1 w\n20 260 m\n105 260 l\nS", $contents);
        self::assertStringContainsString('48.496 243.2 Td', $contents);
        self::assertStringContainsString('1 0 0 rg', $contents);
        self::assertStringContainsString("105 236 85 24 re\nf", $contents);
    }

    #[Test]
    public function it_allows_cells_to_override_the_table_vertical_alignment(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->createTable(new Position(20, 260), 170, [85, 85])
            ->style(new TableStyle(verticalAlign: VerticalAlign::MIDDLE))
            ->addRow([
                new TableCell('Kurz', style: new CellStyle(verticalAlign: VerticalAlign::BOTTOM)),
                "Erste Zeile\nZweite Zeile\nDritte Zeile",
            ]);

        $contents = $page->getContents()->render();

        self::assertStringContainsString("26 213.2 Td\n(Kurz) Tj", $contents);
        self::assertStringContainsString("111 240.8 Td\n(Erste Zeile) Tj", $contents);
    }

    #[Test]
    public function it_moves_the_table_to_a_new_page_when_the_next_row_does_not_fit(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document
            ->registerFont('Helvetica')
            ->registerFont('Helvetica-Bold');
        $page = $document->addPage(200, 200);

        $table = $page->createTable(new Position(20, 120), 160, [80, 80], 20)
            ->addHeaderRow(['Kopf', 'Wert'])
            ->addRow(['A', '1'])
            ->addRow(['B', '2'])
            ->addRow(['C', '3'])
            ->addRow(['Lang', 'Body']);

        self::assertNotSame($page, $table->getPage());
        self::assertCount(2, $document->pages->pages);
        self::assertStringContainsString('(Kopf) Tj', $page->getContents()->render());
        self::assertStringContainsString('(Kopf) Tj', $table->getPage()->getContents()->render());
        self::assertStringContainsString('(Body) Tj', $table->getPage()->getContents()->render());
    }

    #[Test]
    public function it_rejects_rows_with_the_wrong_number_of_cells(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();
        $table = $page->createTable(new Position(20, 260), 170, [85, 85]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Table row spans must match the number of columns.');

        $table->addRow(['nur eine Zelle']);
    }

    #[Test]
    public function it_rejects_captions_after_rows_have_been_added(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();
        $table = $page->createTable(new Position(20, 260), 170, [85, 85]);

        $table->addRow(['A', 'B']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Table caption must be configured before rows are added.');

        $table->caption(new TableCaption('Zu spaet'));
    }

    #[Test]
    public function it_rejects_header_rows_after_body_rows(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();
        $table = $page->createTable(new Position(20, 260), 170, [85, 85]);

        $table->addRow(['A', 'B']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Header rows must be added before body or footer rows.');

        $table->addHeaderRow(['Kopf A', 'Kopf B']);
    }

    #[Test]
    public function it_rejects_header_rows_after_footer_rows(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();
        $table = $page->createTable(new Position(20, 260), 170, [85, 85]);

        $table->addFooterRow(['Summe', '19,99 EUR']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Header rows must be added before body or footer rows.');

        $table->addHeaderRow(['Kopf A', 'Kopf B']);
    }

    #[Test]
    public function it_rejects_cells_with_invalid_colspan(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();
        $table = $page->createTable(new Position(20, 260), 170, [85, 85]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Table cell colspan must be greater than zero.');

        $table->addRow([
            new TableCell('Ungueltig', colspan: 0),
            'Wert',
        ]);
    }

    #[Test]
    public function it_rejects_cells_with_invalid_rowspan(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();
        $table = $page->createTable(new Position(20, 260), 170, [85, 85]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Table cell rowspan must be greater than zero.');

        $table->addRow([
            new TableCell('A', rowspan: 0),
            'Wert',
        ]);
    }

    #[Test]
    public function it_rejects_rendering_footer_rows_when_a_body_rowspan_group_is_still_open(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document
            ->registerFont('Helvetica')
            ->registerFont('Helvetica-Bold');
        $page = $document->addPage();
        $table = $page->createTable(new Position(20, 260), 170, [85, 85]);

        $table
            ->addHeaderRow(['Gruppe', 'Wert'])
            ->addRow([
                new TableCell('A', rowspan: 2),
                'Eintrag 1',
            ])
            ->addFooterRow(['Summe', '19,99 EUR']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rowspan groups must be completed before footer rows are rendered.');

        $document->render();
    }

    #[Test]
    public function it_renders_rowspan_groups_across_page_boundaries(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document
            ->registerFont('Helvetica')
            ->registerFont('Helvetica-Bold');
        $page = $document->addPage(200, 120);

        $table = $page->createTable(new Position(20, 90), 160, [80, 80], 20)
            ->font('Helvetica', 12)
            ->addHeaderRow(['Gruppe', 'Wert'])
            ->addRow([
                new TableCell('A', rowspan: 3, style: new CellStyle(horizontalAlign: HorizontalAlign::CENTER)),
                'Eintrag 1',
            ])
            ->addRow(['Eintrag 2'])
            ->addRow(['Eintrag 3']);

        $renderedDocument = $document->render();

        self::assertNotSame($page, $table->getPage());
        self::assertStringContainsString('/Count 4', $renderedDocument);
        self::assertSame(1, substr_count($renderedDocument, '(A) Tj'));
        self::assertStringContainsString('(Eintrag 1) Tj', $renderedDocument);
        self::assertStringContainsString('(Eintrag 2) Tj', $renderedDocument);
        self::assertStringContainsString('(Eintrag 3) Tj', $renderedDocument);
        self::assertStringContainsString("20 42 80 24 re\nS", $renderedDocument);
    }

    #[Test]
    public function it_continues_rowspan_text_across_page_boundaries(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document
            ->registerFont('Helvetica')
            ->registerFont('Helvetica-Bold');
        $page = $document->addPage(200, 140);

        $page->createTable(new Position(20, 118), 160, [70, 90], 20)
            ->font('Helvetica', 12)
            ->addHeaderRow(['Beschreibung', 'Wert'])
            ->addRow([
                new TableCell(
                    'Alpha Beta Gamma Delta Epsilon Zeta Eta Theta Iota',
                    rowspan: 4,
                    style: new CellStyle(horizontalAlign: HorizontalAlign::LEFT),
                ),
                'Eintrag 1',
            ])
            ->addRow(['Eintrag 2'])
            ->addRow(['Eintrag 3'])
            ->addRow(['Eintrag 4']);

        $renderedDocument = $document->render();

        self::assertStringContainsString('(Alpha) Tj', $renderedDocument);
        self::assertStringContainsString('(Gamma) Tj', $renderedDocument);
        self::assertStringContainsString('(Delta) Tj', $renderedDocument);
        self::assertStringContainsString('/Count 3', $renderedDocument);
    }

    #[Test]
    public function it_defers_a_leading_rowspan_split_to_the_next_page_when_only_one_row_would_fit(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document
            ->registerFont('Helvetica')
            ->registerFont('Helvetica-Bold');
        $firstPage = $document->addPage(200, 120);

        $table = $firstPage->createTable(new Position(20, 58), 160, [80, 80], 20)
            ->font('Helvetica', 12)
            ->addHeaderRow(['H1', 'H2'])
            ->addRow([
                new TableCell('Alpha Beta Gamma Delta', rowspan: 2),
                'B',
            ])
            ->addRow(['C']);

        self::assertCount(4, $document->pages->pages);
        self::assertSame($document->pages->pages[3], $table->getPage());
        self::assertSame(1, substr_count($document->pages->pages[0]->getContents()->render(), '(H1) Tj'));
        self::assertStringNotContainsString('(Alpha Beta)', $document->pages->pages[0]->getContents()->render());
        self::assertStringNotContainsString('(B) Tj', $document->pages->pages[0]->getContents()->render());
        self::assertStringContainsString('(H1) Tj', $document->pages->pages[1]->getContents()->render());
        self::assertStringContainsString('(H1) Tj', $document->pages->pages[2]->getContents()->render());
        self::assertStringContainsString('(B) Tj', $document->pages->pages[2]->getContents()->render());
        self::assertStringContainsString('(H1) Tj', $document->pages->pages[3]->getContents()->render());
        self::assertStringContainsString('(Alpha Beta) Tj', $document->pages->pages[3]->getContents()->render());
        self::assertStringContainsString('(C) Tj', $document->pages->pages[3]->getContents()->render());
    }

    #[Test]
    public function it_keeps_rowspan_cell_boxes_across_page_boundaries_even_when_text_is_fully_rendered(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document
            ->registerFont('Helvetica')
            ->registerFont('Helvetica-Bold');
        $firstPage = $document->addPage(200, 120);

        $firstPage->createTable(new Position(20, 90), 160, [40, 120], 20)
            ->font('Helvetica', 12)
            ->addHeaderRow(['Gruppe', 'Wert'])
            ->addRow([
                new TableCell(
                    'G4',
                    rowspan: 4,
                    style: new CellStyle(
                        horizontalAlign: HorizontalAlign::CENTER,
                        verticalAlign: VerticalAlign::MIDDLE,
                        fillColor: Color::rgb(220, 235, 255),
                    ),
                ),
                'Eintrag 1',
            ])
            ->addRow(['Eintrag 2'])
            ->addRow(['Eintrag 3'])
            ->addRow(['Eintrag 4']);

        self::assertSame(1, substr_count($document->render(), '(G4) Tj'));
        self::assertCount(5, $document->pages->pages);

        foreach (array_slice($document->pages->pages, 1) as $page) {
            $contents = $page->getContents()->render();

            self::assertStringContainsString('0.862745 0.921569 1 rg', $contents);
            self::assertStringContainsString("20 25.2 40 24 re\nf", $contents);
            self::assertStringContainsString("20 25.2 40 24 re\nS", $contents);
        }

        self::assertStringContainsString('(Eintrag 4) Tj', $document->pages->pages[4]->getContents()->render());
    }

    #[Test]
    public function it_renders_all_lines_that_fit_exactly_within_the_cell_height(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document->registerFont('Helvetica');
        $page = $document->addPage();

        $page->createTable(new Position(20, 260), 170, [85, 85])
            ->style(new TableStyle(verticalAlign: VerticalAlign::TOP))
            ->addRow([
                "Zeile 1\nZeile 2\nZeile 3",
                'Kurz',
            ]);

        $contents = $page->getContents()->render();

        self::assertStringContainsString('(Zeile 1) Tj', $contents);
        self::assertStringContainsString('(Zeile 2) Tj', $contents);
        self::assertStringContainsString('(Zeile 3) Tj', $contents);
    }

    #[Test]
    public function it_rejects_invalid_table_dimensions_and_column_configuration(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        try {
            $page->createTable(new Position(20, 260), 0, [85, 85]);
            self::fail('Expected invalid table width exception.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Table width must be greater than zero.', $exception->getMessage());
        }

        try {
            $page->createTable(new Position(20, 260), 170, []);
            self::fail('Expected missing columns exception.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Table requires at least one column.', $exception->getMessage());
        }

        try {
            $page->createTable(new Position(20, 260), 170, [85, 0]);
            self::fail('Expected invalid column width exception.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Table column widths must be greater than zero.', $exception->getMessage());
        }

        try {
            $page->createTable(new Position(20, 260), 170, [80, 80]);
            self::fail('Expected width sum exception.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Table column widths must add up to the table width.', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Table bottom margin must be zero or greater.');

        $page->createTable(new Position(20, 260), 170, [85, 85], -1);
    }

    #[Test]
    public function it_rejects_invalid_font_configuration(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $table = $page->createTable(new Position(20, 260), 170, [85, 85]);

        try {
            $table->font('', 12);
            self::fail('Expected empty font exception.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Table base font must not be empty.', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Table font size must be greater than zero.');

        $table->font('Helvetica', 0);
    }

    #[Test]
    public function it_rejects_row_groups_that_do_not_fit_on_a_fresh_page_after_repeating_headers(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document
            ->registerFont('Helvetica')
            ->registerFont('Helvetica-Bold');
        $page = $document->addPage(200, 85);

        $table = $page->createTable(new Position(20, 30), 160, [80, 80], 20)
            ->font('Helvetica', 12)
            ->addHeaderRow(['Header 1', 'Header 2']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Table rows must fit on a fresh page.');

        $table->addRow([
            'A very long text that wraps into multiple lines and should not fit',
            'B',
        ]);
    }

    #[Test]
    public function it_rejects_header_rowspans_that_extend_into_body_rows_when_headers_repeat(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $document
            ->registerFont('Helvetica')
            ->registerFont('Helvetica-Bold');
        $page = $document->addPage(200, 120);

        $table = $page->createTable(new Position(20, 90), 160, [80, 80], 20)
            ->font('Helvetica', 12)
            ->addHeaderRow([
                new TableCell('Header A', rowspan: 2),
                'Header B',
            ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Header rowspans must be completed within the repeated header rows.');

        $table
            ->addRow(['Body 1'])
            ->addRow(['Body 2', 'Body 3'])
            ->addRow(['Body 4', 'Body 5']);
    }
}
