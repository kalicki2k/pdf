# Image Import Architecture

## Einstiegspunkt

Der stabile Einstiegspunkt bleibt `ImageSource::fromPath()`. Intern delegiert dieser Pfad an `ImageSourceImporter`, damit Dateizugriff, Format-Erkennung und Format-Decoder getrennt bleiben.

## Pipeline

Die Import-Pipeline ist bewusst in kleine Schritte zerlegt:

1. `ImageFormatSniffer` erkennt das Containerformat.
2. Ein format-spezifischer Decoder liest Metadaten und Bildinhalt.
3. Rasterbasierte Decoder bauen intern zuerst ein `DecodedRasterImage`.
4. `DecodedRasterImage` erzeugt daraus ein PDF-nahes `ImageSource` mit optionaler Soft-Mask.
5. Formate mit nativer PDF-Pass-Through-Eignung wie JPEG und CCITT-TIFF gehen weiter direkt in passende PDF-Filter.

## Aktuelle Trennung

- Format-Sniffing: `ImageFormatSniffer`
- Decoder pro Format: `JpegImageDecoder`, `PngImageDecoder`, `GifImageDecoder`, `BmpImageDecoder`, `TiffImageDecoder`, `WebpImageDecoder`
- Interne Rasterrepräsentation: `DecodedRasterImage`
- PDF-Bildobjekt: `ImageSource`
- PDF-Filterbeschreibung: `PdfFilter`

## Bewusste Grenzen

- WebP ist nur als Single-Frame-Import vorgesehen; Animation wird explizit abgelehnt.
- TIFF bleibt bewusst auf klar definierte Untervarianten begrenzt und lehnt inkonsistente Strip-/ColorMap-Metadaten deterministisch ab.
- GIF bleibt statisch und nicht interlaced.
- Decoder sollen nicht stillschweigend in exotische Varianten konvertieren.
