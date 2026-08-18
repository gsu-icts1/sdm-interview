# Refactor plan: ResultsPresentationController.php

**Branch:** `refactor/results-presentation` → `main`

## Summary
`ResultsPresentationController.php` is a 1,216-line "god controller" that mixes routing, authorisation, query building, business rules, HTML/CSS generation, PDF rendering and Excel export in one class. This PR documents the review findings, delivers a stubbed refactor skeleton, and sets out a step-by-step modularisation plan. The full refactor will be executed as the Trello cards created from this review (see board link in the submission email).

## (a) What is wrong with the current code

**1. Single Responsibility Principle violations (severity: high)**
- One controller does authorisation, data access, business rules, HTML string-building, PDF generation (Dompdf) and spreadsheet export (PhpSpreadsheet). Any change risks breaking unrelated features and the file is effectively untestable.

**2. Security and correctness defects (severity: critical)**
- **Raw superglobals:** `results_presentation()` and `results_summary_remark()` read `$_GET['programme_code']`, `$_GET['state']`, etc. directly instead of the injected `Request`. This bypasses Laravel's validation, middleware and testability, and `$_GET['state']` / `$_GET['academic']` are used unvalidated.
- **Ungrouped `orWhere` (data-leak bug):** in `generate_html_view()` the main `where([...])` array is followed by an `->orWhere(function(...))` that is **not wrapped with the primary constraints**, so the generated SQL is `(...programme AND year AND session...) OR (...semester 1 clause...)` — the OR branch is not constrained by `exam_type`, which can return marks rows outside the intended filter. All constraints must be grouped inside a single closure.
- **Unhandled `decrypt()`:** every `decrypt($...)` call is outside try/catch; a tampered URL parameter throws `DecryptException` and yields an unhandled 500.
- **Exception handling commented out:** at least 5 `try/catch` blocks are commented out, and several remaining catch blocks call `dd($exception->getMessage())` — which dumps internals to the end user in production.

**3. Debug artefacts in production (severity: high)**
- ~20 `dd()` calls (live and commented) and large blocks of commented-out dead code remain in the file.

**4. Logic bugs (severity: high)**
- In `generate_html_view()`, the `is-dean` branch assigns `$exam_stage_1 = 1; $exam_stage_1 = 2;` — the second assignment overwrites the first and `$exam_stage_2` is never set (copy-paste error). The same stage-resolution block is duplicated in at least three methods, each slightly different.
- Duplicate `orderBy('tblexam_marks.semester_of_study')` appears twice in one query.
- Pass mark is hard-coded as the magic number `49.5` in `owedModules()`; a mark of exactly 49.5 is neither failed (`< 49.5`) nor passed (`> 49.5`).

**5. Maintainability problems (severity: medium)**
- Inline HTML/CSS strings (~200 lines) built in the controller for PDF output instead of Blade views.
- Raw `DB::table()` joins duplicated across methods instead of Eloquent relationships/scopes; `->get()` with no pagination or chunking on large result sets.
- Inconsistent naming (`filter_results` vs `pendingFailed`), typo parameters (`$blade_viev`), no type hints, no return types, no PHPDoc, no tests.

## (b) How the file can be made modular (Laravel patterns)

1. **Form Requests** — `FilterResultsRequest`, `GenerateResultsPdfRequest` etc. to replace `$_GET` access, centralise validation and safely decrypt route parameters (with `abort(404)` on `DecryptException`).
2. **Policies / a dedicated `ExamStageResolver`** — extract the repeated Gate/stage logic (registrar=3, dean=2–3, chairperson=1–3) into one injectable class; fixes the dean copy-paste bug in a single place.
3. **Service class** — `ResultsQueryService` (or repository) owning the marks queries, with the `orWhere` grouping corrected and constants (`PASS_MARK = 50`) in config/enum.
4. **Eloquent relationships & scopes** — `ExamMark::forProgramme()->forSession()` scopes replace duplicated `DB::table` joins; eager-load `studentmember` to avoid N+1.
5. **View extraction** — move all HTML/CSS into Blade templates (`resources/views/exams/pdf/...`); the PDF method becomes `Pdf::loadView(...)`.
6. **Export classes** — `ResultsPdfExport` and `ResultsSpreadsheetExport` (or Laravel Excel `FromView` exports) so the controller only orchestrates.
7. **Thin controllers, one per concern** — split into `ResultsFilterController`, `ResultsPresentationController`, `ResultsExportController` (or single-action controllers), each ≤ ~100 lines, returning early and type-hinted.
8. **Cross-cutting** — logging via the existing `ErrorLogEvent`, feature tests per endpoint, remove all `dd()`/dead code, apply PSR-12 via Pint, add CI (GitHub Actions: pint + phpunit).

## Changes in this PR
- `README.md` — refactor plan and database table documentation completed.
- `pengindb_structure.sql` — cleaned structure for the three tables with PKs, FKs and indexes (assumptions documented in-file).
- `diagrams/erd.png` + `diagrams/erd.mmd` — ER diagram (image + Mermaid source).
- Stub skeleton showing the target shape of the refactor (no behaviour change).

## Test plan
- No behavioural change in this PR; refactor cards on the Trello board each carry their own acceptance criteria and feature-test requirement before merge.
