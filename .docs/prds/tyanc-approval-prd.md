# PRD: Cumpu — Cross-App Approval App Built on Tyanc

## Status

This document reflects the shipped approval architecture as of 2026-04-12.

Cumpu is the standalone approval app. Tyanc remains the platform and governance layer underneath it.

## Problem Statement

Tyanc needed a real cross-app approval product, not a Tyanc-only submenu.

Approval rules, inboxes, workflows, reassignment, reminders, escalations, reporting, and notifications had to live in one place for Tyanc and future apps. At the same time, the original deferred replay engine had become too heavy for routine governance work.

The platform needed a simpler execution model:

- keep Cumpu as the approval workspace
- keep Tyanc as the shared approval engine
- stop storing replay payloads for normal governed actions
- approve one future action for one requester on one record

## Solution

Cumpu is a standalone first-party app with its own routes, navigation, and permission namespace.

- app key: `cumpu`
- route prefix: `/cumpu/*`
- route names: `cumpu.*`
- permission namespace: `cumpu`

Tyanc still owns the shared approval engine. Cumpu owns the user-facing approval workspace and approval-rule management.

The shipped execution model is approval grants, not deferred replay.

When a governed action runs:

1. the domain action checks whether an enabled approval rule applies
2. if no rule applies, the action executes immediately
3. if the actor already qualifies as the approver, the action bypasses approval and executes immediately
4. if approval is required, the action stops before mutation and requires a requester reason
5. Cumpu stores an approval request tied to the requester, action, subject, and subject snapshot
6. approvers review the request in Cumpu
7. final approval issues a single-use grant with an expiry window
8. the requester performs the action again
9. the next valid attempt consumes the grant atomically and runs the original domain action

The approval layer is now a gate-and-grant system. It is no longer the default mutation replay system.

## Current Code Alignment

The current codebase now matches this architecture in these areas:

- Cumpu is registered as a standalone app and owns the approval workspace
- approval rules are configured from Cumpu
- Tyanc governed actions use `SubmitGovernedAction` as the shared gateway
- `approved` means a usable grant, not a completed replay
- grants are single-use and expire through `grant_validity_minutes`
- approval detail, history, reminders, escalations, reassignment, and reporting all distinguish `approved`, `expired`, and `consumed`
- requester-side governed screens use reason-modal UX instead of permanent inline approval-note fields
- `tyanc.users.update`, `tyanc.users.delete`, `tyanc.users.suspend`, `tyanc.roles.update`, `tyanc.apps.update`, `tyanc.apps.toggle`, settings updates, and imports all follow the retry-after-approval pattern
- import approval is request-first and upload-again-after-approval
- replay-only schema and code have been removed, including `approval_actions`, replay payload columns, payload transformers, deferred approval handlers, staged-file helpers, and container tags
- future apps now integrate by calling `SubmitGovernedAction` with an explicit live `execute` closure and proposal metadata

## Key Product Decisions

- Cumpu remains the only approval workspace for Tyanc and future apps.
- Tyanc remains the shared approval engine and governance foundation.
- Approval is disabled by default and becomes active only through Cumpu-managed rules.
- Approvers must still hold the governed permission for the target action.
- Self-approval remains blocked.
- Eligible actors still bypass approval.
- Multi-step workflows remain supported.
- Reassignment, reminders, escalations, notifications, and reporting remain supported.
- The default review contract is requester, action, subject, reason, subject snapshot, workflow state, and decision history.
- The default review contract does not depend on before-and-after replay payloads.
- File-backed actions should prefer re-run or re-upload after approval instead of staged replay.

## Testing Decisions

The approval suite should prove external behavior, not internal plumbing.

The strongest coverage now focuses on:

- direct execution when no rule applies
- request creation when approval is required
- requester reason capture
- subject snapshot capture
- bypass behavior
- multi-step progression
- reassignment
- grant expiry
- atomic grant consumption
- duplicate blocking
- requester and subject binding
- reporting visibility for pending, approved, expired, and consumed requests
- future-app integration through explicit `SubmitGovernedAction` usage

## Notes for Future Expansion

Future app developers do not need replay transformers or deferred approval handlers.

They should:

1. define the governed permission
2. create or enable the approval rule in Cumpu
3. call `SubmitGovernedAction` from the domain action
4. pass an explicit live `execute` closure plus proposal labels and subject snapshot
5. let the domain action remain the only place that performs the mutation

That keeps future approval adoption small, explicit, and consistent with the shipped grant model.
