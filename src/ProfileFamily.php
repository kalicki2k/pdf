<?php

declare(strict_types=1);

namespace Kalle\Pdf;

enum ProfileFamily: string
{
    case STANDARD = 'standard';
    case PDFA = 'pdfa';
    case PDFE = 'pdfe';
    case PDFR = 'pdfr';
    case PDFUA = 'pdfua';
    case PDFVCR = 'pdfvcr';
    case PDFVT = 'pdfvt';
    case PDFX = 'pdfx';
}
