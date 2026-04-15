# Import Rules

This file is the source of truth for all future import features in Tyanc.

Any new import feature must follow every required rule in this document before its route, UI, approval rule, or queue execution is exposed to users.

If older docs, examples, plans, or legacy code conflict with this file, this file wins.

## Status

- The current users import backend is legacy reference code only.
- Users import is not an active product surface and must not be re-exposed until it fully follows this document.

## Scope

These rules apply to any feature that imports data into application tables from files such as XLSX, CSV, XML, or similar structured formats.

They apply to:
- Tyanc imports
- Cumpu imports
- App-specific imports such as ERP or Tasks imports
- Approval-gated imports
- Direct imports and queued imports

## Core rules

- Do not use exports as import templates.
- Do not write unvalidated rows into live tables.
- Do not silently skip, coerce, or default values in ways that change business meaning.
- Do not bypass the same domain actions, policies, reserved-record protections, or business rules used by normal create and update flows.
- Do not expose an import route or UI until validation, locking, audit, and tests are complete.
- Keep import code inside the owning app namespace. Tyanc imports stay in `App\Actions\Tyanc\Imports\...`; app-specific imports stay inside that app.

## Priority 1: input contract and validation

Every import must start from a strict input contract.

### Required

- Define one header schema source of truth per import.
- Use that same schema for:
  - template generation
  - header validation
  - row mapping
  - row validation
  - operator help text
- Reject files with missing required headers.
- Reject files with unexpected headers unless the feature explicitly allows extra columns.
- Validate every row before any live mutation runs.
- Validate every imported field with explicit rules.
- Detect duplicate identifiers inside the uploaded file.
- Detect duplicate conflicts against the database when the import contract requires uniqueness.
- Return actionable row-level errors with row numbers and field names.

### Validation expectations

At minimum, validate all relevant fields such as:
- required identifiers
- format rules such as email, date, number, and UUID
- enum values
- foreign keys and referenced records
- locale, timezone, and country codes when used
- length limits
- boolean parsing rules
- business-rule constraints

### Forbidden

- No silent row skipping because a key field is missing.
- No silent fallback from invalid values to a default business value.
- No implicit header guessing beyond the declared schema.

## Template rules

Every importable table or resource must provide a downloadable template.

### Required

- The template must use the exact header schema defined by the import.
- The template must be generated from the same source of truth used by the validator.
- The template must not reuse a human-friendly export file.
- The template must contain only supported columns.
- The first importable sheet must use machine-safe headers, not presentation labels.

### Format guidance

- Default to the primary accepted format for the feature, usually `xlsx`.
- If the feature also accepts `csv` or `xml`, provide matching templates for those formats too.
- If XML is supported, the XML template must follow the same field contract as the spreadsheet template.

### Operator guidance

- Keep the importable sheet clean.
- If instructions or examples are needed, place them in a separate non-import sheet or separate download.
- Do not place example data in the real import rows unless the importer explicitly ignores those rows safely.

## Priority 2: database safety and write model

Imports must protect the database first.

### Required

- Run a preflight validation phase before live writes.
- Keep validation and execution as separate phases.
- Default to all-or-nothing execution per import run.
- Use dedicated Action classes for live mutations.
- Reuse the same domain actions and protections used by normal create and update flows.
- Wrap multi-model writes in transactions.
- Block writes to protected, reserved, or system-owned records unless the feature explicitly allows them and documents the rule.
- Make soft-delete restore behavior explicit. Never restore records implicitly unless the import contract says so.

### Preferred execution model

1. Upload file
2. Parse and validate headers
3. Parse and validate all rows
4. Store validation result and summary
5. Require operator confirmation or approval if needed
6. Execute one controlled import run
7. Commit only validated changes

### Partial imports

Partial imports are discouraged by default.

If a feature explicitly allows partial success, it must also:
- persist row status per row
- persist failure reason per row
- show clear created, updated, skipped, and failed counts
- make retries idempotent

## Priority 3: execution hardening

Queued imports must be safe to retry and safe to operate at scale.

### Required

- Each import run must have a stable identifier.
- Queue processing must be locked or unique per import run.
- A completed import must not run again accidentally.
- A processing import must not be executed by two workers at the same time.
- Retries must be idempotent.
- Import status must move through explicit states such as `queued`, `processing`, `completed`, and `failed`.
- Store operator-safe failure messages for UI.
- Store raw exception detail in logs, not in user-facing messages.

### Required summary data

Track, at minimum:
- total rows
- valid rows
- invalid rows
- processed rows
- created count
- updated count
- restored count when allowed
- skipped count
- failed count
- started at
- finished at
- actor
- source file name
- source file fingerprint or checksum

### Large files

- Use chunking only after the contract is clear and retry behavior is safe.
- Do not let chunk retries duplicate earlier writes.
- Prefer staging plus controlled execution over row-by-row direct writes into live tables.

## Priority 4: approval and file integrity

Approval must cover the actual file being reviewed, not just the action label.

### Required

- Approval-gated imports must bind approval to the uploaded artifact.
- Store a file fingerprint, checksum, media identifier, or equivalent immutable reference.
- If the file changes after approval, require a new approval or explicit revalidation.
- Approval payload must include a reviewable summary such as file name, row counts, important identifiers, and risk signals.
- Do not approve based only on client-provided file name, MIME type, or size.

### File-backed action rule

Tyanc defaults to retry-after-approval, not stored mutation replay.

That means file-backed imports should usually:
1. upload or stage the file
2. validate it
3. request approval if required
4. verify the same approved artifact at execution time
5. execute once after approval

### Forbidden

- Do not replay opaque stored mutation payloads by default.
- Do not allow approval for one file to execute a different file silently.

## Priority 5: operator UX, audit, and testing

A safe import must also be understandable and traceable.

### Required UX

- Provide a downloadable template.
- Show accepted file formats and size limits.
- Show header errors clearly.
- Show row validation errors clearly.
- Show a preflight summary before execution when the import is high risk.
- Show final run summary after execution.
- Show only safe failure text in the UI.

### Required audit trail

Record, at minimum:
- who uploaded the file
- who requested approval
- who approved or rejected it when approvals apply
- which file was executed
- what summary counts were produced
- when the run started and ended

### Required visibility rules

- Only authorized users should see import templates, runs, and failure summaries.
- Do not expose one user's import details to unrelated users unless the feature explicitly allows it.

### Required tests

Every import feature must add or update tests for:
- file validation
- header validation
- row validation
- duplicate detection inside the file
- duplicate detection against the database when relevant
- protected or reserved record behavior
- approval-required flow when relevant
- artifact mismatch after approval when relevant
- queue locking or single-run protection
- retry behavior
- template and validator parity
- final summary counts

## Implementation checklist

Use this checklist when adding a new import feature.

1. Confirm the owning app and namespace.
2. Add or confirm the import permission in `config/permission-sot.php` only if the feature is truly user-facing.
3. Add or confirm approval behavior in `config/approval-sot.php` only if the feature is meant to be approval-governed.
4. Define one import schema source of truth.
5. Generate the import template from that schema.
6. Implement upload validation for file type, MIME type, and size.
7. Implement header validation.
8. Implement row validation.
9. Implement duplicate and business-rule validation.
10. Implement preflight summary storage.
11. Implement the execution action using domain actions and transactions.
12. Add queue locking and retry-safe execution.
13. Add audit fields and run summary fields.
14. Add the operator UI only after the backend is safe.
15. Add and run tests before exposing the feature.

## Ship gate

A new import must not ship until all of the following are true:
- template exists
- header schema is enforced
- row validation is enforced
- live writes reuse domain rules
- execution is retry-safe
- approval is file-bound when used
- safe audit trail exists
- tests pass

If any of these are missing, the import is incomplete and must stay disabled.