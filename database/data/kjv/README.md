# King James Version (KJV) text

`eng-kjv2006_vpl.txt.gz` is the verse text behind devotion scripture references.
It is loaded into the `bible_books` and `bible_verses` tables by:

```
php artisan bible:import-kjv            # check, then import (safe to run again)
php artisan bible:import-kjv --dry-run  # check and report only
```

**Deploy:** run `php artisan bible:import-kjv` once after `php artisan migrate`.
The migration creates the tables empty; the command fills them. A second run
reports `0 inserted, 0 updated, 31102 unchanged, 0 removed`.

## Source

| | |
|---|---|
| Edition | King James Version, the standardized text of 1769, protocanon only (eBible.org id `eng-kjv2006`) |
| Downloaded from | https://eBible.org/Scriptures/eng-kjv2006_vpl.zip |
| Details page | https://ebible.org/find/details.php?id=eng-kjv2006 |
| Downloaded | 2026-11-10 09:21:59 UTC (eBible "about" page dated 2026-11-09) |
| Zip | 4,372,244 bytes, sha256 `ee4cae32cddded36d9dfcb524c70c03dcf930cf04924ca34c9b805bac39210d4` |
| Text (`eng-kjv2006_vpl.txt`, unzipped) | 4,490,049 bytes, sha256 `68c5f764bf1c204868c3eb72592035c0947f057b608c2149dbff765d8ddd86f6` |
| This file (gzip -9 of that text) | 1,340,809 bytes, sha256 `751b9871bd0f54dd08b0415bb3ee8f89b2f6b06ebfdb685b967263b431ebef78` |

The command checks the sha256 of the **unzipped** text (pinned in `config/bible.php`),
not of the `.gz`, because different gzip tools produce different bytes for the same text.

Verified: 66 books in canonical order, no Apocrypha, 1,189 chapters, 31,102 verses,
every chapter numbered from verse 1 with no gaps, UTF-8 with LF line endings.

## Format

One verse per line: `JOH 3:16 ¶ For God so loved the world, ...`. The book codes are
eBible's own; 11 differ from USFM (for example `JOH` for `JHN`, `SOL` for `SNG`). The mapping
is in `app/Support/Bible/KjvCanon.php`, and the tables store the USFM codes.

What the import does to the text:

- **Removes** the pilcrow `¶` (a paragraph mark at the start of 2,970 verses).
- **Removes the square brackets** that mark italic, translator-supplied words (21,503
  spans) but **keeps the words**: `The LORD [is] my shepherd` becomes `The LORD is my shepherd`.
  1 John 2:23 has a nested pair (`[[but] he that ... also]`); both pairs are removed.
- **Keeps** everything else as is, including `’` (1,997), `æ`/`Æ` (93/3) and the one em dash
  (Exodus 32:32). Psalm titles stay part of verse 1, as in this text (`A Psalm of David. ...`).
- The text has no Strong's numbers, footnotes or other markup.

## Licence

Verbatim from the eBible "about" page:

> The King James Version or Authorized Version of the Holy Bible, using the standardized
> text of 1769, protocanon only, with Strong's numbers added.
> Public Domain
>
> Letters patent issued by King James with no expiration date means that to print this
> translation in the United Kingdom or import printed copies into the UK, you need
> permission. Currently, the Cambridge University Press, the Oxford University Press, and
> Collins have the exclusive right to print this Bible translation in the UK. This royal
> decree has no effect outside of the UK, where this work is firmly in the Public Domain.
> Please see http://www.cambridge.org/about-us/who-we-are/queens-printers-patent and
> https://en.wikipedia.org/wiki/King_James_Version#Copyright_status for more information.
>
> This free text of the King James Version of the Holy Bible is brought to you courtesy of
> the Crosswire Bible Society and eBible.org.

## Replacing the file

1. Download the zip above, unzip `eng-kjv2006_vpl.txt` and check its sha256.
2. `gzip -9` it to `eng-kjv2006_vpl.txt.gz` here.
3. If the text changed, update the sha256 in `config/bible.php` and this README, then run
   `php artisan bible:import-kjv --dry-run` to see what would change.
