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
the following tasks and share the deliverables with **icts@gsu.ac.zw**.

### 1. Review `ResultsPresentationController.php`

Read the file carefully and, in your pull request description, explain:

- **What is wrong with the file** in its current form (for example: length,
  mixed responsibilities, business logic inside the controller, 
- **How the file can be made modular**, referencing concrete Laravel
  patterns and SOLID principles, for example:
  - Extracting business logic into **Service** classes.
  - Extracting data access into **Repository** classes or dedicated
    **Eloquent scopes**.
  - Moving validation into **Form Request** classes.
  - Shaping responses through **API Resources** or **View Models**.
  - Moving long-running work into **Jobs** and **Queues**.
  - Using **Traits**, **Interfaces** and **Dependency Injection**.
  - Splitting the controller into several thinner controllers grouped by
    responsibility (for example, one per report or per action).
  - Adding **feature** and **unit tests** for the extracted classes.

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

Send the following links to **icts@gsu.ac.zw**:

1. The URL of your fork of the repository.
2. The URL of the pull request.
3. The URL of the updated Trello board.

---

## Refactor plan

> **Candidate — replace this section with your own step-by-step plan.**

Suggested template:

1. **Audit** — enumerate every public method in the controller and record
   its responsibility.
2. **Group** — cluster methods by responsibility (for example: transcript
   generation, mark computation, PDF export, reporting).
3. **Extract services** — for each group, create a service class under
   `app/Services/Results/` and move the business logic there.
4. **Extract repositories** — move Eloquent queries into repository classes
   under `app/Repositories/Results/`.
5. **Form requests** — replace inline validation with dedicated Form
   Request classes under `app/Http/Requests/Results/`.
6. **API resources / view models** — replace ad-hoc arrays with API
   Resources or dedicated View Models.
7. **Split the controller** — replace the single controller with several
   thinner controllers, each with a small, well-named set of actions.
8. **Queues and jobs** — move slow, batch or export-style operations into
   queued jobs.
9. **Tests** — add feature tests for the endpoints and unit tests for the
   extracted services.
10. **Documentation** — update PHPDoc blocks and this README as the
    refactor progresses.

---

## Database — table overview

> **Candidate — expand this with the actual columns from the SQL you add.**

| Table | Purpose |
|-------|---------|
| `studentmember` | Master record of every student registered at the University. |
| `tbl_registered_module` | Modules that a student is registered for in a given session / semester. |
| `studentprogrammestatus` | The status of the student on their programme (active, deferred, discontinued, completed, etc.). |

The ER diagram in `diagrams/` must reflect the actual structure declared
in `db.sql` and show the relationships between these three tables
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
**icts@gsu.ac.zw**.
