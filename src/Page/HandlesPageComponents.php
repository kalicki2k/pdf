<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use Kalle\Pdf\Layout\Geometry\Position;
use Kalle\Pdf\Layout\Text\Input\TextSegment;
use Kalle\Pdf\Page\Content\Style\BadgeStyle;
use Kalle\Pdf\Page\Content\Style\CalloutStyle;
use Kalle\Pdf\Page\Content\Style\PanelStyle;
use Kalle\Pdf\Page\Link\LinkTarget;

trait HandlesPageComponents
{
    public function addBadge(
        string $text,
        Position $position,
        string $baseFont = 'Helvetica',
        int $size = 11,
        ?BadgeStyle $style = null,
        ?LinkTarget $link = null,
    ): self {
        $this->collaborators->components()->addBadge($text, $position, $baseFont, $size, $style, $link);

        return $this;
    }

    /**
     * @param string|list<TextSegment> $body
     */
    public function addPanel(
        string | array $body,
        float $x,
        float $y,
        float $width,
        float $height,
        ?string $title = null,
        string $bodyFont = 'Helvetica',
        ?PanelStyle $style = null,
        ?string $titleFont = null,
        ?LinkTarget $link = null,
    ): self {
        $this->collaborators->components()->addPanel($body, $x, $y, $width, $height, $title, $bodyFont, $style, $titleFont, $link);

        return $this;
    }

    /**
     * @param string|list<TextSegment> $body
     */
    public function addCallout(
        string | array $body,
        float $x,
        float $y,
        float $width,
        float $height,
        float $pointerX,
        float $pointerY,
        ?string $title = null,
        string $bodyFont = 'Helvetica',
        ?CalloutStyle $style = null,
        ?string $titleFont = null,
        ?LinkTarget $link = null,
    ): self {
        $this->collaborators->components()->addCallout($body, $x, $y, $width, $height, $pointerX, $pointerY, $title, $bodyFont, $style, $titleFont, $link);

        return $this;
    }
}
