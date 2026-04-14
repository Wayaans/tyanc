---
goal: Introduce Cumpu as a standalone approval app on top of Tyanc
version: 3.0
date_created: 2026-04-11
last_updated: 2026-04-12
owner: Coding Agent
status: 'Completed'
tags: [feature, approvals, governance, cumpu, tyanc, archived]
---

# Introduction

![Status: Completed](https://img.shields.io/badge/status-Completed-green)

This plan is complete.

It introduced Cumpu as Tyanc’s standalone approval app and established the shared governed-action gateway inside Tyanc. The original deferred-replay approach described in earlier revisions of this file is no longer the active architecture.

The current operating model is documented in:

- `.docs/tyanc-approval-simplification-prd.md`
- `.docs/plans/refactor-cumpu-approval-simplification-1.md`

## Implementation Summary

The Cumpu introduction work shipped these outcomes:

- Cumpu is registered as a standalone first-party app
- approval routes, pages, notifications, and rule management live under `cumpu.*`
- Tyanc continues to own the shared approval engine and governance foundation
- governed Tyanc actions call `SubmitGovernedAction`
- approval rules remain runtime-configurable from Cumpu
- Tyanc resource pages surface approval history and governed-action state that link back to Cumpu

## Historical Plan Outcome

The first version of this plan assumed deferred execution would remain the default approval model.

That assumption is now obsolete.

The current shipped architecture uses approval grants instead:

1. governed actions stop before mutation when approval is required
2. the requester provides a reason
3. Cumpu stores an approval request with action and subject context
4. final approval issues a single-use grant with an expiry window
5. the requester retries the action
6. the next valid attempt consumes the grant atomically and runs the live domain action

## Current Alignment

These points supersede the older deferred-replay sections from this plan:

- `approved` means a usable grant, not a queued replay payload
- replay handlers, payload transformers, staged-file helpers, and the `approval_actions` table have been removed
- future apps now integrate through explicit `SubmitGovernedAction` definitions instead of capability discovery
- imports follow request-first and upload-again-after-approval behavior
- reports, reminders, escalations, reassignment, and approval history now track `approved`, `expired`, and `consumed` grants directly

## Follow-up

For active work, use the simplification documents instead of this archived rollout plan:

- `.docs/tyanc-approval-simplification-prd.md`
- `.docs/plans/refactor-cumpu-approval-simplification-1.md`
