# Image Import

## Ziel und Einstiegspunkt

Der stabile Einstiegspunkt fuer Bildimporte ist `ImageSource::fromPath()`. Dieser Pfad kapselt Dateizugriff, Format-Erkennung und PDF-nahe Aufbereitung, damit Dokumentcode keine Decoder-Details kennen muss.

Fuer bereits bekannte oder extern vorbereitete Bilddaten kann stattdessen direkt mit `ImageSource::jpeg(...)`, `ImageSource::flate(...)`, `ImageSource::lzw(...)`, `ImageSource::runLength(...)`, `ImageSource::ccittFax(...)`, `ImageSource::compressed(...)` oder `ImageSource::monochrome(...)` gearbeitet werden.

## Pipeline

Die Import-Pipeline ist bewusst in klar getrennte Stufen zerlegt:

1. `ImageSource::fromPath()` delegiert an `ImageSourceImporter`.
2. `ImageFormatSniffer` erkennt das Containerformat.
3. Ein formatspezifischer Decoder liest Metadaten und Nutzdaten.
4. Rasterbasierte Decoder erzeugen zuerst ein `DecodedRasterImage`.
5. `DecodedRasterImage` wird in ein PDF-nahes `ImageSource` mit optionaler Soft-Mask ueberfuehrt.
6. Formate mit nativer PDF-Eignung wie JPEG oder CCITT-TIFF koennen passende Filter direkt weiterreichen.

Die Trennung ist im Code sichtbar:

- Format-Erkennung: `ImageFormatSniffer`
- Import-Orchestrierung: `ImageSourceImporter`
- Decoder: `JpegImageDecoder`, `PngImageDecoder`, `GifImageDecoder`, `BmpImageDecoder`, `TiffImageDecoder`, `WebpImageDecoder`
- Interne Rasterdarstellung: `DecodedRasterImage`
- PDF-Bildobjekt: `ImageSource`
- Filterbeschreibung: `PdfFilter`
- Kompressionsauswahl fuer Rasterdaten: `ImageCompressionSelector`

## Aktuell unterstuetzter Scope

Der aktuelle Umfang ist absichtlich explizit und nicht "best effort". Nicht erkannte oder nicht sauber abgedeckte Varianten werden mit Fehler beendet statt stillschweigend konvertiert.

| Format | Unterstuetzt | Bewusst nicht unterstuetzt |
| --- | --- | --- |
| JPEG | Gray, RGB, CMYK als Direct-Pass-Through | exotische Kanal- oder Marker-Varianten ausserhalb des erkannten Basisscopes |
| PNG | 8-Bit Gray, RGB, Indexed, Gray+Alpha, RGBA, `tRNS`, nicht interlaced | andere Bit-Tiefen, Adam7-Interlacing |
| GIF | statisch, ein Full-Canvas-Frame, Palette, transparenter Index | Animation, Interlacing, partielle Frames |
| BMP | unkomprimiert 24-Bit RGB, 32-Bit RGBA, 32-Bit `BI_BITFIELDS` mit byte-ausgerichteten RGB(A)-Masken | Paletten, RLE, weitere Bit-Tiefen und weitere Maskenvarianten |
| TIFF | Single-IFD bilevel uncompressed, bilevel CCITT, 8-Bit Gray und RGB mit uncompressed, PackBits, LZW oder Deflate, Palette ohne Predictor | Multi-Page, CMYK, `PlanarConfiguration != 1`, Palette mit Predictor und weitere exotische Varianten |
| WebP | optional ueber vorhandene GD-WebP-Runtime, Single-Frame, verlustbehaftet und je nach Runtime auch lossless, RGB mit optionaler Soft-Mask aus Alpha | Import ohne GD-WebP-Support, Animation |

## Rasterdaten und PDF-Filter

Fuer dekodierte Rasterdaten versucht die Engine nicht, das Ursprungsformat kuenstlich zu konservieren. Stattdessen wird ein PDF-tauglicher Filterpfad gewaehlt:

- bilevel Daten koennen als gepacktes 1-Bit-Bild geschrieben werden
- CCITT-Fax-Kompression ist fuer monochrome Daten verfuegbar
- `ImageSource::compressed(...)` waehlt fuer rohe Rasterdaten eine kompakte PDF-Kompression
- derselbe Auswahlpfad wird auch von dekodierten Rasterimporten fuer nicht indizierte Pixelbilder genutzt

Dadurch teilen manuell erzeugte Raster und `fromPath(...)` dieselbe Kompressionsstrategie.

## Accessibility und Platzierung

Das Importsubsystem endet bei `ImageSource`. Semantik und Seitenplatzierung werden darueber hinaus getrennt modelliert:

- Platzierung auf der Seite: `ImagePlacement`
- semantische Kennzeichnung: `ImageAccessibility`

In Tagged-PDF-Profilen muessen Bilder je nach Profil mit Alternativtext versehen oder explizit als dekorativ markiert werden. Diese Anforderung wird nicht im Decoder, sondern spaeter im Dokument- und Profilpfad validiert.

## Fehlersuche

Wenn ein Import fehlschlaegt, sind fuer die Analyse typischerweise diese Stellen relevant:

- `tests/Image/*Fixture.php` fuer reproduzierbare Fixtures je Format
- `tests/Image/*Test.php` fuer Decoder-, Filter- und Encoder-Verhalten
- `tests/Document/ImageSourcePathTest.php` fuer den Builder-Pfad ueber Dateipfade
- `tests/Document/DocumentFontAndImageObjectBuilderTest.php` fuer die Einbettung in PDF-Objekte

Bei Tagged-PDF- oder PDF/A-Profilen sollte zusaetzlich auf Bild-Accessibility und Farbpfade geachtet werden, da diese erst im Dokumentaufbau und nicht beim nackten Decoder sichtbar werden.
