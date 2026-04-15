<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Document;

use Kalle\Pdf\Document\TaggedTextStructureCoordinator;
use PHPUnit\Framework\TestCase;

final class TaggedTextStructureCoordinatorTest extends TestCase
{
    public function testItReturnsInlineContainerKeyForTaggedLinkedText(): void
    {
        $coordinator = new TaggedTextStructureCoordinator(
            true,
            static fn (string $tag, ?string $existingKey): string => $existingKey ?? 'struct:' . $tag,
            static fn (): string => 'unused',
        );

        self::assertSame(
            'struct:P',
            $coordinator->resolveInlineContainerKey('P', true),
        );
    }

    public function testItRegistersStandaloneTaggedTextWithoutInlineContainer(): void
    {
        $registered = [];
        $coordinator = new TaggedTextStructureCoordinator(
            true,
            static fn (): string => 'unused',
            static function (string $tag, array $markedContentIds, ?string $key) use (&$registered): string {
                $registered = [$tag, $markedContentIds, $key];

                return 'text:0';
            },
        );

        $key = $coordinator->resolveTaggedTextKey(
            null,
            'P',
            'BT ET',
            [3, 4],
        );

        self::assertSame('text:0', $key);
        self::assertSame(['P', [3, 4], null], $registered);
    }
}
