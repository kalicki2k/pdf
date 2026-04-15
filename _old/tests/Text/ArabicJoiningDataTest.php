<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Text;

use Kalle\Pdf\Text\ArabicJoiningData;
use Kalle\Pdf\Text\ArabicJoiningType;
use PHPUnit\Framework\TestCase;

final class ArabicJoiningDataTest extends TestCase
{
    public function testItKnowsDualJoiningCharacters(): void
    {
        $data = new ArabicJoiningData();

        self::assertSame(ArabicJoiningType::DUAL, $data->typeForCharacter('ب'));
        self::assertTrue($data->canJoinToPrevious('ب'));
        self::assertTrue($data->canJoinToNext('ب'));
    }

    public function testItKnowsRightJoiningCharacters(): void
    {
        $data = new ArabicJoiningData();

        self::assertSame(ArabicJoiningType::RIGHT, $data->typeForCharacter('ا'));
        self::assertTrue($data->canJoinToPrevious('ا'));
        self::assertFalse($data->canJoinToNext('ا'));
    }

    public function testItFallsBackToNonJoiningForUnknownCharacters(): void
    {
        $data = new ArabicJoiningData();

        self::assertSame(ArabicJoiningType::NON_JOINING, $data->typeForCharacter('A'));
    }

    public function testItKnowsTransparentArabicMarks(): void
    {
        $data = new ArabicJoiningData();

        self::assertSame(ArabicJoiningType::TRANSPARENT, $data->typeForCharacter('َ'));
        self::assertTrue($data->isTransparent('َ'));
        self::assertFalse($data->canJoinToPrevious('َ'));
        self::assertFalse($data->canJoinToNext('َ'));
    }
}
