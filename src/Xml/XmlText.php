<?php

declare(strict_types=1);

namespace Kalle\Pdf\Xml;

final readonly class XmlText implements XmlNode
{
    public function __construct(
        public string $value,
    ) {
    }
}
