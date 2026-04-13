<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Signature;

use const PKCS7_BINARY;
use const PKCS7_DETACHED;

use function base64_decode;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function is_string;
use function openssl_pkcs7_sign;
use function preg_match;
use function preg_quote;
use function preg_replace;
use function sprintf;
use function str_contains;
use function str_repeat;
use function strlen;
use function strpos;
use function substr;
use function sys_get_temp_dir;
use function tempnam;
use function trim;
use function unlink;

use DateTimeInterface;
use InvalidArgumentException;
use Kalle\Pdf\Document\Document;
use Kalle\Pdf\Document\DocumentRenderer;
use Kalle\Pdf\Document\Form\SignatureField;
use Kalle\Pdf\Writer\Output;
use Kalle\Pdf\Writer\StringOutput;
use RuntimeException;
use Throwable;

final readonly class DocumentSigner
{
    private const int BYTE_RANGE_WIDTH = 20;

    public function __construct(
        private DocumentRenderer $renderer = new DocumentRenderer(),
    ) {
    }

    public function write(
        Document $document,
        Output $output,
        OpenSslPemSigningCredentials $credentials,
        PdfSignatureOptions $options,
    ): void {
        $output->write($this->contents($document, $credentials, $options));
    }

    public function contents(
        Document $document,
        OpenSslPemSigningCredentials $credentials,
        PdfSignatureOptions $options,
    ): string {
        if (!function_exists('openssl_pkcs7_sign')) {
            throw new RuntimeException('The OpenSSL extension is required for PDF signing.');
        }

        if ($document->encryption !== null) {
            throw new InvalidArgumentException('Cryptographic signing of encrypted documents is not supported.');
        }

        $field = $document->acroForm?->field($options->fieldName);

        if (!$field instanceof SignatureField) {
            throw new InvalidArgumentException(sprintf(
                'Document does not contain an unsigned signature field named "%s".',
                $options->fieldName,
            ));
        }

        $unsignedPdf = $this->renderUnsignedDocument($document);
        $trailer = $this->parseTrailer($unsignedPdf);
        $fieldObject = $this->locateSignatureFieldObject($unsignedPdf, $options->fieldName);
        $signatureObjectId = $trailer['size'];

        if (str_contains($fieldObject['contents'], '/V ')) {
            throw new InvalidArgumentException(sprintf(
                'Signature field "%s" already contains a value dictionary.',
                $options->fieldName,
            ));
        }

        $updatedFieldContents = $this->injectSignatureValueReference(
            $fieldObject['contents'],
            $signatureObjectId,
        );
        $signatureDictionary = $this->buildSignatureDictionaryPlaceholder($options);

        [$preparedPdf, $placeholder] = $this->appendIncrementalUpdate(
            $unsignedPdf,
            $trailer,
            $fieldObject['objectId'],
            $updatedFieldContents,
            $signatureObjectId,
            $signatureDictionary,
        );

        $byteRange = $this->calculateByteRange($preparedPdf, $placeholder['contentsHex']);
        $preparedPdf = $this->replaceByteRangePlaceholder($preparedPdf, $placeholder['byteRange'], $byteRange);
        $signedData = substr($preparedPdf, 0, $byteRange[1]) . substr($preparedPdf, $byteRange[2], $byteRange[3]);
        $cmsSignature = $this->createDetachedCmsSignature($signedData, $credentials);
        $preparedPdf = $this->replaceContentsPlaceholder(
            $preparedPdf,
            $placeholder['contentsHex'],
            $cmsSignature,
            $options->reservedContentsLength,
        );

        return $preparedPdf;
    }

    private function renderUnsignedDocument(Document $document): string
    {
        $output = new StringOutput();
        $this->renderer->write($document, $output);

        return $output->contents();
    }

    /**
     * @return array{size: int, root: string, info: ?string, id: ?string, prevStartXref: int}
     */
    private function parseTrailer(string $pdf): array
    {
        if (!preg_match('/trailer\s*<<(.*?)>>\s*startxref\s*(\d+)\s*%%EOF\s*$/s', $pdf, $matches)) {
            throw new RuntimeException('Unable to parse the PDF trailer for signing.');
        }

        $dictionary = $matches[1];

        if (!preg_match('/\/Size\s+(\d+)/', $dictionary, $sizeMatch)) {
            throw new RuntimeException('Unable to determine the PDF trailer size for signing.');
        }

        if (!preg_match('/\/Root\s+(\d+\s+\d+\s+R)/', $dictionary, $rootMatch)) {
            throw new RuntimeException('Unable to determine the PDF catalog reference for signing.');
        }

        preg_match('/\/Info\s+(\d+\s+\d+\s+R)/', $dictionary, $infoMatch);
        preg_match('/\/ID\s+(\[[^\]]+\])/', $dictionary, $idMatch);

        return [
            'size' => (int) $sizeMatch[1],
            'root' => $rootMatch[1],
            'info' => $infoMatch[1] ?? null,
            'id' => $idMatch[1] ?? null,
            'prevStartXref' => (int) $matches[2],
        ];
    }

    /**
     * @return array{objectId: int, contents: string}
     */
    private function locateSignatureFieldObject(string $pdf, string $fieldName): array
    {
        $escapedFieldName = $this->pdfString($fieldName);

        if (!preg_match_all('/(\d+)\s+0\s+obj\s*(.*?)\s*endobj/s', $pdf, $matches, PREG_SET_ORDER)) {
            throw new RuntimeException('Unable to enumerate PDF objects while locating the signature field.');
        }

        foreach ($matches as $match) {
            $contents = trim($match[2]);

            if (!str_contains($contents, '/FT /Sig') || !str_contains($contents, '/T ' . $escapedFieldName)) {
                continue;
            }

            return [
                'objectId' => (int) $match[1],
                'contents' => $contents,
            ];
        }

        throw new InvalidArgumentException(sprintf(
            'Unable to locate the signature field "%s" in the rendered PDF.',
            $fieldName,
        ));
    }

    private function injectSignatureValueReference(string $fieldContents, int $signatureObjectId): string
    {
        $updatedContents = preg_replace(
            '/>>\s*$/',
            ' /V ' . $signatureObjectId . ' 0 R >>',
            $fieldContents,
            1,
        );

        if (!is_string($updatedContents) || $updatedContents === $fieldContents) {
            throw new RuntimeException('Unable to inject the signature value reference into the field object.');
        }

        return $updatedContents;
    }

    /**
     * @param array{size: int, root: string, info: ?string, id: ?string, prevStartXref: int} $trailer
     * @return array{0: string, 1: array{byteRange: string, contentsHex: string}}
     */
    private function appendIncrementalUpdate(
        string $unsignedPdf,
        array $trailer,
        int $fieldObjectId,
        string $updatedFieldContents,
        int $signatureObjectId,
        string $signatureDictionary,
    ): array {
        $appended = "\n";
        $fieldObjectOffset = strlen($unsignedPdf) + strlen($appended);
        $appended .= $fieldObjectId . " 0 obj\n" . $updatedFieldContents . "\nendobj\n";
        $signatureObjectOffset = strlen($unsignedPdf) + strlen($appended);
        $appended .= $signatureObjectId . " 0 obj\n" . $signatureDictionary . "\nendobj\n";
        $xrefOffset = strlen($unsignedPdf) + strlen($appended);
        $appended .= "xref\n";
        $appended .= $fieldObjectId . " 1\n" . $this->xrefEntry($fieldObjectOffset);
        $appended .= $signatureObjectId . " 1\n" . $this->xrefEntry($signatureObjectOffset);
        $appended .= "trailer\n<< /Size " . ($signatureObjectId + 1);
        $appended .= ' /Root ' . $trailer['root'];

        if ($trailer['info'] !== null) {
            $appended .= ' /Info ' . $trailer['info'];
        }

        if ($trailer['id'] !== null) {
            $appended .= ' /ID ' . $trailer['id'];
        }

        $appended .= ' /Prev ' . $trailer['prevStartXref'] . " >>\n";
        $appended .= "startxref\n" . $xrefOffset . "\n%%EOF";

        return [
            $unsignedPdf . $appended,
            [
                'byteRange' => $this->byteRangePlaceholder(),
                'contentsHex' => $this->contentsPlaceholderHex($signatureDictionary),
            ],
        ];
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    private function calculateByteRange(string $pdf, string $contentsPlaceholderHex): array
    {
        $contentsPosition = strpos($pdf, '<' . $contentsPlaceholderHex . '>');

        if ($contentsPosition === false) {
            throw new RuntimeException('Unable to locate the signature contents placeholder in the PDF.');
        }

        $contentsEnd = $contentsPosition + strlen($contentsPlaceholderHex) + 2;

        return [
            0,
            $contentsPosition,
            $contentsEnd,
            strlen($pdf) - $contentsEnd,
        ];
    }

    /**
     * @param array{0: int, 1: int, 2: int, 3: int} $byteRange
     */
    private function replaceByteRangePlaceholder(string $pdf, string $placeholder, array $byteRange): string
    {
        $replacement = sprintf(
            '[%0' . self::BYTE_RANGE_WIDTH . 'd %0' . self::BYTE_RANGE_WIDTH . 'd %0' . self::BYTE_RANGE_WIDTH . 'd %0' . self::BYTE_RANGE_WIDTH . 'd]',
            $byteRange[0],
            $byteRange[1],
            $byteRange[2],
            $byteRange[3],
        );

        if (strlen($replacement) !== strlen($placeholder)) {
            throw new RuntimeException('Signature byte range replacement width mismatch.');
        }

        return str_replace($placeholder, $replacement, $pdf);
    }

    private function replaceContentsPlaceholder(
        string $pdf,
        string $placeholderHex,
        string $cmsSignature,
        int $reservedContentsLength,
    ): string {
        if (strlen($cmsSignature) > $reservedContentsLength) {
            throw new RuntimeException(sprintf(
                'The generated CMS signature requires %d bytes but only %d were reserved.',
                strlen($cmsSignature),
                $reservedContentsLength,
            ));
        }

        $replacement = strtoupper(bin2hex($cmsSignature))
            . str_repeat('0', ($reservedContentsLength - strlen($cmsSignature)) * 2);

        return str_replace($placeholderHex, $replacement, $pdf);
    }

    private function createDetachedCmsSignature(
        string $signedData,
        OpenSslPemSigningCredentials $credentials,
    ): string {
        $inputPath = $this->temporaryPath('pdf2-sign-input-');
        $outputPath = $this->temporaryPath('pdf2-sign-output-');
        $chainPath = $credentials->certificateChainPem === [] ? null : $this->temporaryPath('pdf2-sign-chain-');

        try {
            file_put_contents($inputPath, $signedData);

            if ($chainPath !== null) {
                file_put_contents($chainPath, implode("\n", $credentials->certificateChainPem));
            }

            $result = openssl_pkcs7_sign(
                $inputPath,
                $outputPath,
                $credentials->certificatePem,
                [$credentials->privateKeyPem, $credentials->privateKeyPassphrase ?? ''],
                [],
                PKCS7_BINARY | PKCS7_DETACHED,
                $chainPath,
            );

            if ($result !== true) {
                throw new RuntimeException('OpenSSL was unable to create a detached CMS signature for the PDF.');
            }

            $smime = file_get_contents($outputPath);

            if (!is_string($smime) || $smime === '') {
                throw new RuntimeException('Unable to read the generated CMS signature output.');
            }

            return $this->extractDerSignatureFromSmime($smime);
        } finally {
            $this->unlinkIfExists($inputPath);
            $this->unlinkIfExists($outputPath);

            if ($chainPath !== null) {
                $this->unlinkIfExists($chainPath);
            }
        }
    }

    private function extractDerSignatureFromSmime(string $smime): string
    {
        if (!preg_match('/boundary=\"([^\"]+)\"/', $smime, $boundaryMatch)) {
            throw new RuntimeException('Unable to extract the MIME boundary from the CMS signature output.');
        }

        $boundary = preg_quote($boundaryMatch[1], '/');

        if (!preg_match(
            '/Content-Transfer-Encoding:\s*base64\s+.*?\R\R(.*?)\R--' . $boundary . '--/s',
            $smime,
            $signatureMatch,
        )) {
            throw new RuntimeException('Unable to extract the base64 CMS signature from the MIME output.');
        }

        $decoded = base64_decode(preg_replace('/\s+/', '', $signatureMatch[1]) ?? '', true);

        if (!is_string($decoded) || $decoded === '') {
            throw new RuntimeException('Unable to decode the CMS signature from the MIME output.');
        }

        return $decoded;
    }

    private function buildSignatureDictionaryPlaceholder(PdfSignatureOptions $options): string
    {
        $entries = [
            '/Type /Sig',
            '/Filter /Adobe.PPKLite',
            '/SubFilter /adbe.pkcs7.detached',
            '/ByteRange ' . $this->byteRangePlaceholder(),
            '/Contents <' . str_repeat('0', $options->reservedContentsLength * 2) . '>',
            '/M ' . $this->pdfString($this->pdfDate($options->signingTime)),
        ];

        if ($options->signerName !== null) {
            $entries[] = '/Name ' . $this->pdfString($options->signerName);
        }

        if ($options->reason !== null) {
            $entries[] = '/Reason ' . $this->pdfString($options->reason);
        }

        if ($options->location !== null) {
            $entries[] = '/Location ' . $this->pdfString($options->location);
        }

        if ($options->contactInfo !== null) {
            $entries[] = '/ContactInfo ' . $this->pdfString($options->contactInfo);
        }

        return '<< ' . implode(' ', $entries) . ' >>';
    }

    private function contentsPlaceholderHex(string $signatureDictionary): string
    {
        if (!preg_match('/\/Contents <([0-9A-F]+)>/', $signatureDictionary, $matches)) {
            throw new RuntimeException('Unable to determine the signature contents placeholder.');
        }

        return $matches[1];
    }

    private function byteRangePlaceholder(): string
    {
        $zero = str_repeat('0', self::BYTE_RANGE_WIDTH);

        return '[' . $zero . ' ' . $zero . ' ' . $zero . ' ' . $zero . ']';
    }

    private function pdfString(string $value): string
    {
        return '(' . str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\(', '\)'],
            $value,
        ) . ')';
    }

    private function pdfDate(DateTimeInterface $dateTime): string
    {
        $offset = $dateTime->format('P');

        return sprintf(
            'D:%s%s\'%s\'',
            $dateTime->format('YmdHis'),
            substr($offset, 0, 3),
            substr($offset, 4, 2),
        );
    }

    private function xrefEntry(int $offset): string
    {
        return sprintf('%010d 00000 n ' . "\n", $offset);
    }

    private function temporaryPath(string $prefix): string
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);

        if ($path === false) {
            throw new RuntimeException('Unable to allocate a temporary path for PDF signing.');
        }

        return $path;
    }

    private function unlinkIfExists(string $path): void
    {
        try {
            if ($path !== '') {
                unlink($path);
            }
        } catch (Throwable) {
        }
    }
}
