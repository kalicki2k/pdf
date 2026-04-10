<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Internal\Page\Link;

use InvalidArgumentException;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Page\Link\LinkTarget;
use Kalle\Pdf\Profile\Profile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LinkTargetTest extends TestCase
{
    #[Test]
    public function it_creates_an_external_url_target(): void
    {
        $target = LinkTarget::externalUrl('https://example.com');

        self::assertTrue($target->isExternalUrl());
        self::assertFalse($target->isNamedDestination());
        self::assertFalse($target->isPage());
        self::assertFalse($target->isPosition());
        self::assertSame('https://example.com', $target->externalUrlValue());
    }

    #[Test]
    public function it_creates_a_named_destination_target(): void
    {
        $target = LinkTarget::namedDestination('chapter-1');

        self::assertFalse($target->isExternalUrl());
        self::assertTrue($target->isNamedDestination());
        self::assertFalse($target->isPage());
        self::assertFalse($target->isPosition());
        self::assertSame('chapter-1', $target->namedDestinationValue());
    }

    #[Test]
    public function it_creates_a_page_target(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $target = LinkTarget::page($page);

        self::assertFalse($target->isExternalUrl());
        self::assertFalse($target->isNamedDestination());
        self::assertTrue($target->isPage());
        self::assertFalse($target->isPosition());
        self::assertSame($page, $target->pageValue());
    }

    #[Test]
    public function it_creates_a_position_target(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $target = LinkTarget::position($page, 15.5, 25.0);

        self::assertFalse($target->isExternalUrl());
        self::assertFalse($target->isNamedDestination());
        self::assertFalse($target->isPage());
        self::assertTrue($target->isPosition());
        self::assertSame($page, $target->pageValue());
        self::assertSame(15.5, $target->xValue());
        self::assertSame(25.0, $target->yValue());
    }

    #[Test]
    public function it_compares_targets_by_value(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();

        self::assertTrue(
            LinkTarget::externalUrl('https://example.com')->equals(LinkTarget::externalUrl('https://example.com')),
        );
        self::assertTrue(
            LinkTarget::namedDestination('chapter-1')->equals(LinkTarget::namedDestination('chapter-1')),
        );
        self::assertTrue(
            LinkTarget::position($page, 10, 20)->equals(LinkTarget::position($page, 10, 20)),
        );
        self::assertFalse(
            LinkTarget::position($page, 10, 20)->equals(LinkTarget::position($page, 10, 21)),
        );
    }

    #[Test]
    public function it_rejects_empty_external_urls(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Link target URL must not be empty.');

        LinkTarget::externalUrl('');
    }

    #[Test]
    public function it_rejects_empty_named_destinations(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Link target destination must not be empty.');

        LinkTarget::namedDestination('');
    }

    #[Test]
    public function it_rejects_reading_the_wrong_value_kind(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $target = LinkTarget::page($page);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Link target does not contain an external URL.');

        $target->externalUrlValue();
    }

    #[Test]
    public function it_rejects_missing_named_destination_values(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $target = LinkTarget::page($page);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Link target does not contain a named destination.');

        $target->namedDestinationValue();
    }

    #[Test]
    public function it_rejects_missing_page_values(): void
    {
        $target = LinkTarget::externalUrl('https://example.com');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Link target does not contain a page destination.');

        $target->pageValue();
    }

    #[Test]
    public function it_rejects_missing_coordinates(): void
    {
        $document = new Document(profile: Profile::standard(1.4));
        $page = $document->addPage();
        $target = LinkTarget::page($page);

        try {
            $target->xValue();
            self::fail('Expected missing x coordinate exception.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Link target does not contain an x coordinate.', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Link target does not contain a y coordinate.');

        $target->yValue();
    }
}
