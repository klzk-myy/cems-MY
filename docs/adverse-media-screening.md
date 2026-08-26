# Adverse Media Screening

Adverse media screening runs as a parallel candidate pool alongside sanctions
screening in `CustomerScreeningService`. Customers whose names match adverse
media entries (news articles alleging financial crime, corruption, fraud, etc.)
are flagged for review — they are **never auto-blocked**, because press
allegations are not listing determinations.

## CSV / JSON format

Columns (header row required for CSV; JSON accepts an array of objects):

| Column         | Required | Notes                                        |
|----------------|----------|----------------------------------------------|
| `name`         | yes      | Subject name                                 |
| `title` or `article_title` | yes | Article headline                       |
| `source`       | yes      | Publication/feed name                        |
| `url`          | no       | Article URL                                  |
| `snippet`      | no       | Excerpt                                      |
| `published_at` | no       | Any parsable date (`Y-m-d` recommended)      |
| `severity`     | no       | `low`, `medium`, `high` (case-insensitive); invalid values skip the row |
| `alias`        | no       | Also matched during screening                |

Rows missing `name`, title, or `source` are skipped and counted in the import
log. Rows are upserted by a stable hash of `name + source + url`
(`adverse_media_entries.record_hash`), so re-importing the same file updates
existing rows instead of duplicating them.

## Import command

```bash
# From a file (extension-agnostic; JSON is auto-detected by content)
php artisan adverse-media:import storage/app/adverse-media.csv

# From STDIN
cat adverse-media.json | php artisan adverse-media:import --stdin
```

Each run writes an `adverse_media_import_logs` row with added/updated/skipped
counts and a success/partial/failed status. There is no schedule: imports are
file-driven and manual.

## Thresholds and block vs flag matrix

Scoring reuses the identical levenshtein/token/phonetic weights as sanctions
matching (`config/sanctions.php` → `matching.threshold_flag = 75`,
`threshold_block = 90`).

| Scenario                                  | Result    | Confirm action |
|-------------------------------------------|-----------|----------------|
| Sanctions hit, score >= 90                | `block`   | Customer frozen, transactions blocked, FIU reporting flagged (`handleConfirmedMatch`) |
| Sanctions hit, score 75–89                | `flag`    | Same as above when confirmed |
| Adverse media hit only (any score >= 75)  | `flag`    | EDD review alert raised (`handleConfirmedAdverseMatch`); customer NOT frozen/blocked/rejected |

When both pools hit, sanctions semantics win: the result is stored with
`source = 'sanctions'` and blocking applies as usual.

## Disposition workflow

Adverse hits appear in the existing matches UI
(`compliance/screening/matches`) with an "Adverse Media" source badge and the
article details on the show page. Confirming an adverse match records the
disposition on the `screening_results` row and escalates a
`SystemAlert` (`adverse_media_screening`; `critical` level for high-severity
entries, otherwise `warning`). Dismissing works identically to sanctions
matches.
