Internal encryption is split by technical responsibility:

- `Profile` resolves PDF-version-dependent encryption capabilities
- `Standard` builds standard security handler data for the document
- `Object` encrypts PDF strings and stream objects
- `Stream` performs incremental payload encryption
- `Crypto` contains low-level cipher primitives

Only public encryption configuration stays in `src/Security`.
