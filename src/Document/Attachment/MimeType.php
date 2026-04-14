<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Attachment;

enum MimeType: string
{
    case XML = 'application/xml';
    case PDF = 'application/pdf';
    case JSON = 'application/json';
    case ZIP = 'application/zip';
    case GZIP = 'application/gzip';
    case MSWORD = 'application/msword';
    case EXCEL = 'application/vnd.ms-excel';
    case POWERPOINT = 'application/vnd.ms-powerpoint';
    case WORDPROCESSING_ML = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    case SPREADSHEET_ML = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    case PRESENTATION_ML = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
    case OCTET_STREAM = 'application/octet-stream';
    case PLAIN_TEXT = 'text/plain';
    case HTML = 'text/html';
    case CSV = 'text/csv';
    case JPEG = 'image/jpeg';
    case PNG = 'image/png';
    case GIF = 'image/gif';
    case TIFF = 'image/tiff';
    case WEBP = 'image/webp';
    case BMP = 'image/bmp';
    case SVG = 'image/svg+xml';

    public static function fromFilename(string $filename): self
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($extension) {
            'xml' => self::XML,
            'pdf' => self::PDF,
            'json' => self::JSON,
            'zip' => self::ZIP,
            'gz' => self::GZIP,
            'txt' => self::PLAIN_TEXT,
            'log' => self::PLAIN_TEXT,
            'md' => self::PLAIN_TEXT,
            'rst' => self::PLAIN_TEXT,
            'html', 'htm' => self::HTML,
            'csv' => self::CSV,
            'tsv' => self::PLAIN_TEXT,
            'doc' => self::MSWORD,
            'xls' => self::EXCEL,
            'ppt' => self::POWERPOINT,
            'docx' => self::WORDPROCESSING_ML,
            'xlsx' => self::SPREADSHEET_ML,
            'pptx' => self::PRESENTATION_ML,
            'jpg', 'jpeg' => self::JPEG,
            'png' => self::PNG,
            'gif' => self::GIF,
            'tif', 'tiff' => self::TIFF,
            'webp' => self::WEBP,
            'bmp' => self::BMP,
            'svg' => self::SVG,
            default => self::OCTET_STREAM,
        };
    }
}
