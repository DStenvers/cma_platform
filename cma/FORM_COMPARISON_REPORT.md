# Form Comparison Report v2
Generated: 2026-02-02 12:13:39
Updated: 2026-02-02 (fixes applied)
Source of truth: repository.mdb

## Summary

| Metric | Count | Status |
|--------|-------|--------|
| Forms in database | 128 | - |
| JSON files with sourceFormId | 125 | +3 created |
| Forms missing JSON file | 3 | Was 6, 3 fixed |
| ParentField mismatches | 0 | Was 8, all fixed |
| Subforms missing in JSON | 0 | Was 1, fixed |

---

## Fixes Applied

### 1. Created New JSON Files

| Form ID | Name | File Created |
|---------|------|--------------|
| 145 | Documenten (subform of Deelnemers) | `opleidingen_deelnemers_documenten.json` |
| 88 | Toetsing (subform of Deelnemers) | `opleidingen_deelnemers_toetsing.json` |
| 209 | DeelnameVrijstellingBijlagen | `vrijstellingaanvragen_bijlagen.json` |

### 2. Fixed ParentField Mismatches

| Parent Form | Subform | Added parentField |
|-------------|---------|-------------------|
| Contactpersonen (184) | Login (187) | `fkKlantContactpersoon` |
| Contactpersonen (184) | Laatste 100 berichten (185) | `fkKlantContactpersoon` |
| Contactpersonen Inventarisatie (137) | Login (196) | `fkSRHForumlid` |
| Contactpersonen Inventarisatie (137) | Laatste 100 berichten (202) | `fkSRHForumlid` |
| Toetsing Deelnemers (100) | Bijlagen (233) | `fkToetsPerDeelnemer` |
| Toetsing Deelnemers (100) | Bijlagen beoordeling (232) | `fkToetsPerDeelnemer` |
| Toetsing Deelnemers (100) | Toetsen-archief (231) | `fkToetsPerDeelnemer` |

### 3. Updated Subform References

| Parent Form | Subform | Updated Form Reference |
|-------------|---------|------------------------|
| opleidingen_deelnemers | Documenten (145) | `opleidingen_deelnemers_documenten` |
| opleidingen_deelnemers | Toetsing (88) | `opleidingen_deelnemers_toetsing` |
| vrijstellingaanvragen | Bijlagen (209) | `vrijstellingaanvragen_bijlagen` |

---

## Remaining Forms Missing JSON Files

These forms exist in the database but are lower priority (not part of core opleidingen workflow):

| Form Name | ID | ParentID | DB ParentField | Table |
|-----------|-----|----------|----------------|-------|
| Laatste 100 berichten | 181 | 136 | fkSRHServicepakket | (none) |
| Login | 182 | 136 | fkSRHServicepakket | (none) |
| Login | 150 | 149 | fkServiceBureau | (none) |

---

## Forms with Duplicate Names (Reference)

These forms have the same name but different IDs (different forms):

### Documenten
| ID | Parent | JSON | File |
|----|--------|------|------|
| 57 | 0 (main) | YES | documenten.json |
| 145 | 69 (Deelnemers) | YES | opleidingen_deelnemers_documenten.json |

### Toetsing
| ID | Parent | JSON | File |
|----|--------|------|------|
| 90 | 0 (main) | YES | toetsing.json |
| 88 | 69 (Deelnemers) | YES | opleidingen_deelnemers_toetsing.json |

### Deelnemers
| ID | Parent | JSON | File |
|----|--------|------|------|
| 61 | 0 (main) | YES | deelnemers.json |
| 69 | 68 (Opleidingen) | YES | opleidingen_deelnemers.json |
| 100 | 90 (Toetsing) | YES | toetsing_deelnemers.json |
| 215 | 216 (Dispensatie) | YES | dispensatie_deelnemers.json |

### Bijlagen
| ID | Parent | JSON | File |
|----|--------|------|------|
| 119 | 88 (Toetsing subform) | YES | bijlagen.json |
| 228 | 90 (Toetsing main) | YES | toetsing_bijlagen.json |
| 233 | 100 (Toetsing_deelnemers) | YES | deelnemers_bijlagen.json |
| 178 | 176 (Afspraak) | YES | afspraak_bijlagen.json |
| 209 | 208 (Vrijstellingaanvragen) | YES | vrijstellingaanvragen_bijlagen.json |

---

## Special Cases

### IOP Form (89)
- ParentField in DB: `tblDeelname.ID`
- ParentField in JSON: `ID`
- **Status**: OK - special case, same data view as parent

