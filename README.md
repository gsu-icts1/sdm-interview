# SDM Practical Assessment — Laravel Snapshot

> **Gwanda State University — ICT Department**
> Software Development Manager Pre-Interview (Section B, Question 7)

This repository is a **snapshot of a running Laravel application** used at
Gwanda State University. It has been prepared as a practical, hands-on
exercise for candidates who are being assessed for the position of
**Software Development Manager**.

The candidate is **not** expected to run the full Laravel application. What
is required is a working developer workflow: reading real code, opening a
pull request, updating documentation, modelling a small piece of the
database and producing an entity-relationship diagram.

---

## What is in this repository

| Item | Description |
|------|-------------|
| `ResultsPresentationController.php` | A snapshot of a single Laravel controller (over 1 200 lines) taken from the running production application. This is the file to review and refactor. |
| `db.sql` | A database extract that contains the **structure** of three tables — `studentmember`, `tbl_registered_module` and `studentprogrammestatus`. Use this file as the source of truth when building the ER diagram. |
| `diagrams/` | Location for the Entity-Relationship Diagram (ERD) that the candidate must produce and upload. |
| `README.md` | This file. |

> The controller (`ResultsPresentationController.php`) and the database
> extract (`pengindb.sql`) are uploaded by the panel. The candidate is
> expected to add, edit and commit everything else.

---

## Your task

You have been given a snapshot of a running Laravel application. Complete
the following tasks and share the deliverables with **icts.director@gsu.ac.zw and deputyregistrarhr@gsu.ac.zw**.

### 1. Review `ResultsPresentationController.php`

Read the file carefully and, in your pull request description, explain:

- **What is wrong with the file** in its current form (for example: length,
  mixed responsibilities, business logic inside the controller, 
- **How the file can be made modular**, referencing concrete Laravel
  patterns

### 2. Raise a Pull Request

- Fork or clone the repository.
- Create a working branch (for example `refactor/results-presentation`).
- Commit a short, focused change (a stubbed refactor is acceptable — the
  panel is more interested in your **plan** and **PR description** than in
  a full rewrite).
- Open a pull request against the `main` branch of this repository.
- Use the PR description to document the review findings and the proposed
  refactor plan.

### 3. Update this README

Replace the section below (**"Refactor plan"**) with a structured,
step-by-step list of what needs to be done on the controller and on the
codebase generally.

### 4. Update the Trello board

Create Trello cards on the board that you built for **Question 6** (the
project planning question) for every clean-up task you identify. For each
card include:

- A clear title and a short description.
- An assignee (a hypothetical junior developer name is fine).
- A due date.
- A checklist of the **step-by-step actions** to complete the task.
- Labels for **priority** and **project**.

### 5. Database and ERD

- Open the provided **`pengindb_erd.sql`** file. This is a database extract
  that contains the structure of the three tables:
  - `studentmember`
  - `tbl_registered_module`
  - `studentprogrammestatus`
- Read the `CREATE TABLE` statements and identify the columns, data types,
  primary keys, foreign keys and constraints.
- Based on that structure, produce an **Entity-Relationship Diagram (ERD)**
  that shows the three tables and the relationships between them. Any of
  the following formats is acceptable:
  - A PNG or SVG image of the diagram.
  - A `.drawio` source file (from [diagrams.net](https://app.diagrams.net)).
  - A Mermaid `erDiagram` block in a `.mmd` or `.md` file.
  - A `dbdiagram.io` DBML file.
- Upload the diagram to the `diagrams/` folder of this repository.
- If any relationships are implied but not explicitly enforced in
  `pengindb.sql` (for example, a foreign key that is missing), state the
  assumption clearly in the diagram legend or in this README.

### 6. Share the artefacts

Send the following links to **icts.director@gsu.ac.zw and deputyregistrarhr@gsu.ac.zw**:

1. The URL of your fork of the repository.
2. The URL of the pull request.
3. The URL of the updated Trello board.

---

## Refactor plan

Refactor plan: ResultsPresentationController.php

Branch: `refactor/results-presentation` → `main`

 Summary
`ResultsPresentationController.php` is a 1,216-line "god controller" that mixes routing, authorization, query building, business rules, HTML/CSS generation, PDF rendering and Excel export in one class. This PR documents the review findings, delivers a stubbed refactor skeleton, and sets out a step-by-step modularization plan. The full refactor will be executed as the Trello cards created from this review (see board link in the submission email).

 (a) What is wrong with the current code

1. Single Responsibility Principle violations (severity: high)
- One controller does authorisation, data access, business rules, HTML string-building, PDF generation (Dompdf) and spreadsheet export (PhpSpreadsheet). Any change risks breaking unrelated features and the file is effectively untestable.

2. Security and correctness defects (severity: critical)
- Raw superglobals: `results_presentation()` and `results_summary_remark()` read `$_GET['programme_code']`, `$_GET['state']`, etc. directly instead of the injected `Request`. This bypasses Laravel's validation, middleware and testability, and `$_GET['state']` / `$_GET['academic']` are used unvalidated.
- Ungrouped `orWhere` (data-leak bug): in `generate_html_view()` the main `where([...])` array is followed by an `->orWhere(function(...))` that is not wrapped with the primary constraints, so the generated SQL is `(...programme AND year AND session...) OR (...semester 1 clause...)` — the OR branch is not constrained by `exam_type`, which can return marks rows outside the intended filter. All constraints must be grouped inside a single closure.
- Unhandled `decrypt()`: every `decrypt($...)` call is outside try/catch; a tampered URL parameter throws `DecryptException` and yields an unhandled 500.
- Exception handling commented out: at least 5 `try/catch` blocks are commented out, and several remaining catch blocks call `dd($exception->getMessage())` — which dumps internals to the end user in production.

3. Debug artefacts in production (severity: high)
- ~20 `dd()` calls (live and commented) and large blocks of commented-out dead code remain in the file.

4. Logic bugs (severity: high)
- In `generate_html_view()`, the `is-dean` branch assigns `$exam_stage_1 = 1; $exam_stage_1 = 2;` — the second assignment overwrites the first and `$exam_stage_2` is never set (copy-paste error). The same stage-resolution block is duplicated in at least three methods, each slightly different.
- Duplicate `orderBy('tblexam_marks.semester_of_study')` appears twice in one query.
- Pass mark is hard-coded as the magic number `49.5` in `owedModules()`; a mark of exactly 49.5 is neither failed (`< 49.5`) nor passed (`> 49.5`).

5. Maintainability problems (severity: medium)
- Inline HTML/CSS strings (~200 lines) built in the controller for PDF output instead of Blade views.
- Raw `DB::table()` joins duplicated across methods instead of Eloquent relationships/scopes; `->get()` with no pagination or chunking on large result sets.
- Inconsistent naming (`filter_results` vs `pendingFailed`), typo parameters (`$blade_viev`), no type hints, no return types, no PHPDoc, no tests.

 (b) How the file can be made modular (Laravel patterns)

1. Form Requests — `FilterResultsRequest`, `GenerateResultsPdfRequest` etc. to replace `$_GET` access, centralise validation and safely decrypt route parameters (with `abort(404)` on `DecryptException`).
2. Policies / a dedicated `ExamStageResolver` — extract the repeated Gate/stage logic (registrar=3, dean=2–3, chairperson=1–3) into one injectable class; fixes the dean copy-paste bug in a single place.
3. Service class — `ResultsQueryService` (or repository) owning the marks queries, with the `orWhere` grouping corrected and constants (`PASS_MARK = 50`) in config/enum.
4. Eloquent relationships & scopes — `ExamMark::forProgramme()->forSession()` scopes replace duplicated `DB::table` joins; eager-load `studentmember` to avoid N+1.
5. View extraction — move all HTML/CSS into Blade templates (`resources/views/exams/pdf/...`); the PDF method becomes `Pdf::loadView(...)`.
6. Export classes — `ResultsPdfExport` and `ResultsSpreadsheetExport` (or Laravel Excel `FromView` exports) so the controller only orchestrates.
7. Thin controllers, one per concern — split into `ResultsFilterController`, `ResultsPresentationController`, `ResultsExportController` (or single-action controllers), each ≤ ~100 lines, returning early and type-hinted.
8. Cross-cutting — logging via the existing `ErrorLogEvent`, feature tests per endpoint, remove all `dd()`/dead code, apply PSR-12 via Pint, add CI (GitHub Actions: pint + phpunit).

---

## Database — table overview

> **Candidate — expand this with the actual columns from the SQL you add.**

| Table | Purpose |
|-------|---------|
| `studentmember` | Master record of every student registered at the University. |
| `tbl_registered_module` | Modules that a student is registered for in a given session / semester. |
| `studentprogrammestatus` | The status of the student on their programme (active, deferred, discontinued, completed, etc.). |

The ER diagram in `diagrams/` must reflect the actual structure declared
in `pengindb.sql` and show the relationships between these three tables
(typically `studentmember` 1 &mdash; * `tbl_registered_module` and
`studentmember` 1 &mdash; * `studentprogrammestatus`).

---

## Marking criteria (for panel use)

| Area | Weight |
|------|--------|
| Quality of the code review (what is wrong, why it matters) | 6 |
| Quality of the modularisation plan and Laravel patterns used | 6 |
| Pull request hygiene (branch, commits, PR description) | 3 |
| Trello updates (cards, assignments, checklists, due dates) | 4 |
| Correct interpretation of `db.sql` (keys, types, relationships) | 3 |
| Entity-Relationship Diagram (clarity, correctness, format) | 3 |
| **Total** | **25** |

---

## Contact

For any queries about this assessment please contact
**icts.director@gsu.ac.zw and deputyregistrarhr@gsu.ac.zw**.
