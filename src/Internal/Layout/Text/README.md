Dieses Paket enthaelt den internen Textlayout-Kern.

- `Input` enthaelt die gemeinsamen Eingabetypen fuer Textsegmente und Textoptionen.
- `TextFrame`, `PageParagraphRenderer`, `TextLayoutEngine` und die weiteren Klassen kapseln Absatzlayout, Textboxen und mehrseitigen Textfluss.

Die Eingabetypen liegen bewusst nahe am Layoutkern, weil sie nur fuer diesen Ablauf und dessen direkte Aufrufer relevant sind.
