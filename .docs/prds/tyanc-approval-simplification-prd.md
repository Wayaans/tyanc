# PRD: Simplify Cumpu Approval Execution with Single-Use Approval Grants

## Problem Statement

Cumpu should remain Tyanc’s cross-app approval app, but the current approval implementation is too heavy in its execution layer.

Today, the system does more than manage approval workflows. It also stores executable mutation payloads, replays actions after approval, stages files, uses transformer and handler layers for each governed action, and carries action-specific execution plumbing. That makes the approval system harder to understand, harder to extend, and too expensive to maintain for routine governed actions such as updating or deleting a user.

From the product perspective, we still want Cumpu to be a real approval app inside the Tyanc platform. We still want cross-app approval rules, inboxes, request tracking, workflows, reassignment, reminders, escalations, reporting, and notifications. But we do not want approval to act like a full deferred job replay engine for normal admin actions.

The desired simplification is to treat approval as permission to perform one future action on one specific record, not as storage and replay of the original submitted mutation. The system should block execution before the action runs, ask the requester for a reason, route the request through Cumpu, and after approval let that same requester perform that same action once within a configured time limit.

## Solution

Keep Cumpu as Tyanc’s cross-app approval app and preserve the existing workflow features, but replace the current deferred execution model with a simpler approval-grant model.

When a governed action is attempted:

- if no approval rule is enabled, the action executes normally
- if an approval rule is enabled and the actor does not bypass approval, the action is blocked before mutation
- the user is prompted for a reason
- the system creates an approval request tied to:
  - requester
  - governed action
  - specific target record
  - current subject snapshot
  - approval reason
  - workflow metadata
- approvers review the request in Cumpu using the reason and current record context, not a stored before-and-after mutation payload
- if approved, the request becomes a single-use approval grant for that same requester, action, and record
- the requester must manually perform the action again
- on the next attempt, the system detects the valid approved grant, consumes it atomically, and allows the original domain action to execute normally

This keeps Cumpu as the approval center while removing the heaviest replay architecture for routine actions.

## Current Code Alignment

The current codebase now reflects the simplified grant model in these areas:

- Cumpu remains the standalone approval app and request workspace.
- `SubmitGovernedAction` is the shared gate-and-grant entrypoint for governed mutations.
- Approval requests store requester reason, subject snapshot, workflow state, and grant metadata instead of replay payloads.
- Final approval issues a single-use grant with `expires_at`, and successful retry marks the request `consumed`.
- Replay-only schema and code have been removed, including `approval_actions`, replay payload columns, payload transformers, deferred approval handlers, and staged-file helpers.
- Future app integrations now pass explicit live `execute` closures and proposal metadata to `SubmitGovernedAction` instead of registering replay transformers.

The first rollout should target:

- `tyanc.users.update`
- `tyanc.users.delete`

File-backed actions are not part of the first simplified rollout. When they are supported later, the default simplification should prefer re-running or re-uploading after approval instead of storing staged files for replay.

## User Stories

1. As a platform owner, I want Cumpu to remain a standalone app inside Tyanc, so that approvals stay cross-app and centralized.
2. As a platform owner, I want the simplification to reduce execution complexity without removing Cumpu’s approval role, so that the product stays powerful and easier to maintain.
3. As a platform administrator, I want approval rules to stay managed centrally in Cumpu, so that governance stays consistent across Tyanc and future apps.
4. As a platform administrator, I want approval rules to stay scoped by app, resource, and action, so that only the right actions require approval.
5. As a platform administrator, I want approval to stay disabled by default, so that only explicitly governed actions are blocked.
6. As a platform administrator, I want to enable approval for `tyanc.users.update`, so that risky user changes can be reviewed first.
7. As a platform administrator, I want to enable approval for `tyanc.users.delete`, so that destructive user actions are reviewed first.
8. As a platform administrator, I want approved requests to expire after a configurable time limit, so that old approvals cannot be used indefinitely.
9. As a platform administrator, I want approval to stay single-use, so that one decision cannot authorize unlimited future actions.
10. As a requester, I want my action to execute immediately when no approval rule is enabled, so that normal work stays fast.
11. As a requester, I want the system to stop me before mutation when approval is required, so that unapproved changes never partially execute.
12. As a requester, I want a clear prompt that approval is required, so that I understand why the action did not run.
13. As a requester, I want to provide a reason for the approval request, so that approvers understand my intent.
14. As a requester, I want the approval request to be created without storing my full mutation payload, so that the system stays simpler and less coupled to execution details.
15. As a requester, I want my approval request tied to the exact record I was trying to act on, so that the later approval cannot be reused elsewhere.
16. As a requester, I want to see my request in Cumpu My Requests, so that I can track whether it is pending, approved, rejected, cancelled, expired, or consumed.
17. As a requester, I want to cancel my pending request, so that I can stop a request I no longer need.
18. As a requester, I want to receive a real-time notification when my request is approved or rejected, so that I know when to act.
19. As a requester, I want an approved request to let me retry the exact governed action once, so that approval unblocks the work without auto-running it for me.
20. As a requester, I want the approved request to work only for me, so that another user cannot consume my approval.
21. As a requester, I want the approved request to work only on the approved record, so that I cannot use one approval on a different subject.
22. As a requester, I want the approval to be consumed automatically when I use it successfully, so that it cannot be reused.
23. As a requester, I want expired approvals to stop working, so that stale requests do not grant old authority.
24. As an approver, I want to review approval requests in Cumpu, so that all approval work stays in one app.
25. As an approver, I want to see who requested the action, which action is being requested, which record is targeted, and why, so that I can make a decision quickly.
26. As an approver, I want to see a current snapshot of the target record, so that I have enough context without needing a full deferred payload diff.
27. As an approver, I do not need to review exact pending field changes for the default flow, so that routine approval stays lightweight.
28. As an approver, I want to approve with a note, so that my decision is documented.
29. As an approver, I want to reject with a note, so that the requester understands why the request was refused.
30. As an approver, I want self-approval blocked, so that governance remains trustworthy.
31. As an approver, I want only users with the underlying governed permission to be eligible approvers, so that approval authority matches actual action authority.
32. As a qualified actor, I want approval to bypass when I already satisfy the configured approver rules, so that I do not create a request for my own review.
33. As an approval administrator, I want multi-step workflows to remain available, so that higher-risk actions can still require more than one stage.
34. As an approval administrator, I want reassignment to remain available, so that work can move when the current approver cannot act.
35. As an approval administrator, I want reminders to remain available, so that approval queues do not stall.
36. As an approval administrator, I want escalations to remain available, so that overdue approvals become visible.
37. As an approval administrator, I want reporting to remain available, so that I can monitor pending, approved, consumed, expired, and rejected work.
38. As an auditor, I want a timeline of request creation, review, approval, rejection, cancellation, expiry, and consumption, so that I can reconstruct what happened.
39. As an auditor, I want consumed approvals to remain visible in history, so that I can see which approval was actually used to authorize the later action.
40. As a platform engineer, I want governed actions to integrate through a lightweight approval check instead of a replay pipeline, so that new actions are cheaper to adopt.
41. As a platform engineer, I want the original domain action to stay responsible for execution, so that approval does not duplicate business logic.
42. As a platform engineer, I want to remove the need for action-specific replay handlers for normal CRUD governance, so that the codebase becomes easier to reason about.
43. As a platform engineer, I want the first rollout to focus on `tyanc.users.update` and `tyanc.users.delete`, so that the new model is proven on real actions before broader adoption.
44. As a future app developer, I want Cumpu to stay the approval workspace while my app keeps its own domain logic, so that cross-app governance remains centralized.
45. As a future app developer, I want later actions to adopt the same lightweight approval pattern, so that approval does not require a heavy deferred execution stack by default.
46. As a future app developer, I want each governed action to check whether approval is enabled before it executes, so that approval remains runtime-configurable from Cumpu.

## Implementation Decisions

- Cumpu remains Tyanc’s standalone cross-app approval app and continues to own approval operations and approval configuration.
- The simplification targets the execution model, not the overall approval product shape.
- Approval requests should no longer use stored executable mutation payloads as the default way to complete routine governed actions.
- The default review contract becomes requester, governed action, governed subject, current subject snapshot, approval reason, workflow state, and decision history.
- Exact pending field-level changes are intentionally not part of the default approval review contract.
- The system should block governed actions before mutation whenever an enabled approval rule applies and no valid approval grant exists.
- When approval is required, the requester must provide a reason through a dedicated approval prompt or modal before the request is created.
- An approved request should act as the single-use approval grant instead of introducing another deep execution layer by default.
- The approval grant must be tied to the requester, the governed action, and the specific target record.
- The approval grant must be single-use.
- The approval grant must have a configurable expiry window.
- The `approved` state means the grant is usable but not yet consumed.
- After final approval, the requester must manually retry the original action.
- On retry, the domain action should check for a valid approved request, consume it atomically, and then execute the normal business logic.
- If the approved request is expired, already consumed, tied to another user, or tied to another record, it must not authorize execution.
- The simplified lifecycle should be reduced to `pending`, `in_review`, `approved`, `rejected`, `cancelled`, `expired`, and `consumed`.
- Duplicate active requests should remain blocked for the same governed action and subject while a pending, in-review, or still-usable approved request already exists.
- Approvers must still satisfy the current rule model and must still hold the underlying governed permission.
- Self-approval remains disallowed.
- Approval bypass remains when the acting user already qualifies as the required approver under the configured rule.
- Multi-step workflows remain in scope.
- Reassignment remains in scope.
- Reminders remain in scope.
- Escalations remain in scope.
- Reporting remains in scope.
- Notifications remain in scope, including real-time decision visibility for the requester.
- The default simplified rollout should focus first on `tyanc.users.update` and `tyanc.users.delete`.
- File-backed approvals are not part of the first rollout.
- In the first `tyanc.users.update` rollout, governed avatar changes are out of scope and must be rejected with a clear validation message when approval is enabled.
- When file-backed approvals are adopted later, the default simplified pattern should prefer re-running or re-uploading after approval instead of storing staged files for replay.
- The original domain action remains the only place that performs the actual mutation.
- The approval layer should become a gate-and-grant system, not a second execution system.
- The current heavy abstractions used only for deferred replay should be retired or reduced where they are no longer needed by the simplified model.
- Existing workflow and Cumpu workspace capabilities should be preserved as much as possible while the execution layer is simplified.

## Testing Decisions

- A good test should verify externally visible behavior and business guarantees, not internal implementation details or which internal classes were called.
- The most important tests should prove that:
  - actions execute normally when no approval rule is enabled
  - governed actions are blocked before mutation when approval is required
  - the requester is prompted to provide a reason before a request is created
  - approval requests are created with the correct requester, action, subject, reason, and current subject snapshot
  - approvers can approve or reject according to existing authorization rules
  - approved requests allow only the same requester to perform only the same action on only the same record
  - approved requests are consumed atomically after successful use
  - expired approvals cannot be used
  - self-approval stays blocked
  - bypass still works for already-eligible actors
  - multi-step workflows, reassignment, reminders, escalations, and reporting continue to work with the simplified request model
- The modules that should receive strong test coverage are:
  - Approval Policy
  - Approval Request Ledger
  - Approval Consumption Guard
  - Approval Workspace
  - Notifications
- Prior art for these tests already exists in the codebase through the current approval feature tests, Cumpu workflow tests, approval rule tests, resource-context tests, notification tests, and report-focused approval tests.
- Existing approval tests should be reused as regression guidance where the behavior still applies, and rewritten where they currently assert deferred payload replay behavior that is being intentionally removed.

## Out of Scope

- Keeping deferred payload replay as the default execution model for routine governed actions
- Auto-executing the originally submitted mutation after approval
- Requiring approvers to review exact pending field-by-field diffs in the default flow
- Staging and replaying uploaded files in the initial simplified rollout
- Broad rollout to all governed actions before the new pattern is proven on the first pilot actions
- Preserving every legacy execution abstraction if it no longer serves the simplified model
- Solving import, upload, download, or other file-backed approval flows in the first implementation pass

## Further Notes

- This is a simplification, not a rollback of Cumpu as a product.
- The main product choice is intentional: approval will authorize one future action on one specific record, not approve an exact stored mutation payload.
- That tradeoff removes the heaviest execution complexity.
- The first rollout should stay narrow and prove the pattern on user update and user delete before expanding to other actions.
- If a future business action truly requires exact payload approval or file replay, that should be treated as an explicit exception pattern, not the default architecture for Cumpu.
- The existing Cumpu workspace and workflow features should be preserved wherever possible so the user-facing product remains familiar while the backend execution model becomes smaller and easier to maintain.
