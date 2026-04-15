<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\TaggedTextLinkCoordinator;
use Kalle\Pdf\Page\LinkTarget;
use PHPUnit\Framework\TestCase;

final class TaggedTextLinkCoordinatorTest extends TestCase
{
    public function testItFinalizesTaggedMarkedContentWithoutLinks(): void
    {
        $coordinator = new TaggedTextLinkCoordinator(
            static fn (): ?string => null,
            true,
            true,
            static fn (): int => 0,
            static fn (): ?int => 0,
            static fn (string $tag, int $markedContentId, string $contents): string => '/' . $tag . ' ' . $markedContentId . ' ' . $contents,
            static function (): void {
            },
            static function (): void {
            },
        );

        $result = $coordinator->finalizeTaggedTextContents(
            'BT ET',
            false,
            'P',
            7,
            false,
            [],
            static fn (string $contents): string => 'artifact:' . $contents,
        );

        self::assertSame('/P 7 BT ET', $result['contents']);
        self::assertSame([7], $result['textMarkedContentIds']);
    }

    public function testItBuildsTaggedLinkAnnotationAndAttachesInlineChild(): void
    {
        $attached = [];
        $coordinator = new TaggedTextLinkCoordinator(
            static fn (): ?string => null,
            true,
            true,
            static fn (): int => 9,
            static fn (): ?int => 0,
            static fn (string $tag, int $markedContentId, string $contents): string => '/' . $tag . ' ' . $markedContentId . ' ' . $contents,
            static function (?string $inlineContainerKey, ?int $pageIndex, ?string $groupKey) use (&$attached): void {
                $attached = [$inlineContainerKey, $pageIndex, $groupKey];
            },
            static function (): void {
            },
        );

        $result = $coordinator->buildLinkedTextRunContent(
            LinkTarget::externalUrl('https://example.com'),
            'Open Example',
            'Open Example',
            'BT ET',
            10,
            20,
            30,
            12,
            9,
            '0:link',
            0,
            'struct:0',
        );

        self::assertStringContainsString('/Link << /MCID 9 >> BDC', $result['contents']);
        self::assertSame(['struct:0', 0, '0:link'], $attached);
    }
}
