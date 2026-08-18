# SDM Practical Assessment — Laravel Snapshot

> **Gwanda State University — ICT Department**
> Software Development Manager Pre-Interview (Section B, Question 7)

This repository is a **snapshot of a running Laravel application** used at
Gwanda State University (marks management / results presentation module of
the `pengindb` system). It is **not runnable on its own** — it contains a
single production controller, a structure-only database extract and this
documentation. It exists so the candidate can demonstrate a full developer
workflow: code review, pull request, documentation, database modelling and
work coordination through Trello.

## What is in this repository

| Item | Description |
|------|-------------|
| `ResultsPresentationController.php` | Snapshot of a 1,216-line production controller — the subject of the code review and refactor plan. |
| `pengindb.sql` | Structure-only extract of `studentmember`, `studentprogrammestatus` and `tblregistered_module` (source of truth for the ERD). |
| `pengindb_structure.sql` | **Candidate-added:** cleaned structure with primary keys, foreign keys, corrected data types and indexes. Assumptions documented in the file header. |
| `diagrams/erd.png`, `diagrams/erd.mmd` | **Candidate-added:** Entity-Relationship Diagram (image + Mermaid source). |
| `README.md` | This file. |

## Refactor plan

### Phase 0 — Stabilise (week 1)
1. **Audit** — enumerate every public/private method, its responsibility, its routes and its callers; freeze new features on this module.
2. **Remove debug artefacts** — delete all `dd()` calls and commented-out dead code; restore the commented-out `try/catch` blocks and route exceptions to `ErrorLogEvent` + a user-safe flash message.
3. **Fix critical defects first** — (a) group the ungrouped `orWhere` in `generate_html_view()` so the OR branch cannot leak rows outside the programme/session filter; (b) fix the `is-dean` stage assignment bug (`$exam_stage_1` set twice, `$exam_stage_2` never set); (c) wrap all `decrypt()` calls and return 404 on `DecryptException`; (d) resolve the 49.5 pass-mark boundary so a mark equal to the threshold is classified.

### Phase 1 — Input and authorisation (week 2)
4. **Form Requests** — replace every `$_GET` read with validated Form Request classes; type-hint `Request` and route parameters.
5. **ExamStageResolver** — extract the repeated registrar/dean/chairperson stage logic into one injectable class with unit tests.

### Phase 2 — Data layer (weeks 3–4)
6. **Eloquent models, relationships and query scopes** — replace duplicated `DB::table()` joins; eager-load student data; add pagination/chunking; move the pass mark and stage numbers to config/enums.
7. **ResultsQueryService** — one service owning marks retrieval for screen, PDF and Excel paths.

### Phase 3 — Presentation and export (weeks 5–6)
8. **Blade extraction** — move all inline HTML/CSS into Blade views; PDF becomes `Pdf::loadView()`.
9. **Export classes** — dedicated PDF and spreadsheet export classes; stream large exports.
10. **Split the controller** — thin controllers per concern (filter, present, export), each fronted by tests.

### Phase 4 — Codebase generally (ongoing)
11. **Tests and CI** — feature tests per endpoint before each merge; GitHub Actions running Pint (PSR-12) and PHPUnit; branch protection on `main`.
12. **Conventions** — naming standards, PHPDoc, PR template, CODEOWNERS, and the same review checklist applied to the other controllers in the application.

Each numbered item above exists as a Trello card (assignee, due date, checklist, priority label) on the Question 6 board.

## Database — table overview

Relationships are **implied** by the shared `studentNumber` key; the original
extract declares **no foreign keys** (stated as an assumption in the ERD
legend and in `pengindb_structure.sql`).

| Table | Purpose | Key columns |
|-------|---------|-------------|
| `studentmember` | Master record of every student. Parent entity. | PK `id`; unique `studentNumber`, `applicationNumber`, `nationalId` |
| `studentprogrammestatus` | Student's status on a programme per year/semester/session (registered, exempted, deferred, graduated). 1 student → many status rows. | PK `id`; FK `studentNumber`; composite unique key across (studentNumber, programmeCode, yearOfStudy, semesterOfStudy, session, recordStatus, format) |
| `tblregistered_module` | Modules a student is registered for in a session/semester, incl. supplementary flag and exam seating reference. 1 student → many registrations. | PK `id`; FK `studentNumber`; `seatingIdFk` references an exam-seating table not in this extract |

**Data-quality observations carried into `pengindb_structure.sql`:** `dateBirth` stored as `varchar(255)` (corrected to `DATE`); `studentNumber` length inconsistent between tables (15 vs 20 — standardised to 20); `tblregistered_module` had no index on `studentNumber` despite being its natural join key (index added).

## Contact
icts.director@gsu.ac.zw and deputyregistrarhr@gsu.ac.zw
