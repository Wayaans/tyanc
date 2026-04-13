# Cumpu Guide

Cumpu is Tyanc's built-in cross-app approval workspace.

Use Cumpu when an action should not run immediately and must be reviewed first. Cumpu keeps approval rules, reviewer workflow, reminders, escalations, reports, and approval history in one place while the real mutation stays inside the original app action.

This guide is for developers who want to:

- understand what Cumpu ships today
- connect a Tyanc or future-app resource to Cumpu
- integrate approval the Tyanc way

## Status legend

- ✅ **Complete**
- 🟡 **Need improvement**
- ⚪ **Disabled by default**

## What Cumpu ships today

| Feature | What it is for | Status | Notes |
| --- | --- | --- | --- |
| Dashboard | Workspace summary for approvers and requesters | ✅ Complete | `/cumpu/dashboard` |
| My requests | Track requests created by the current user | ✅ Complete | `/cumpu/approvals/my-requests` |
| Approval inbox | Review requests assigned to the current user | ✅ Complete | `/cumpu/approvals` |
| All approvals | Review approval traffic across scope | ✅ Complete | `/cumpu/approvals/all` |
| Approval detail page | View requester, action, subject snapshot, assignments, and history | ✅ Complete | `/cumpu/approvals/{approvalRequest}` |
| Approve action | Approve a request | ✅ Complete | Permission-aware |
| Reject action | Reject a request | ✅ Complete | Permission-aware |
| Cancel action | Cancel an active request | ✅ Complete | Requester or approval manager |
| Reassign action | Move the current step to another eligible approver | ✅ Complete | Current-step only |
| Approval rules | Choose what action is governed and how approval runs | ✅ Complete | App, resource, action, workflow type, steps, validity, reminder, escalation |
| Single-step workflow | One reviewer step | ✅ Complete | Default path |
| Multi-step workflow | Multiple approval steps in sequence | ✅ Complete | Uses rule steps |
| Single-use grant model | Approved request allows one later retry on the same action and subject | ✅ Complete | Current shipped architecture |
| Grant expiry | Limit how long an approved request stays usable | ✅ Complete | Controlled per rule |
| Duplicate request blocking | Prevent repeated active requests for the same actor, action, and subject | ✅ Complete | Pending, in-review, and unused approved grants block duplicates |
| Reminder flow | Remind pending approvers after a configured window | ✅ Complete | Queue-driven |
| Escalation flow | Escalate overdue requests after a configured window | ✅ Complete | Queue-driven |
| Reports | Summaries, filters, and operational visibility | ✅ Complete | `/cumpu/approvals/reports` |
| XLSX report export | Export report rows | ⚪ Disabled by default | Uses Tyanc export feature flag |
| Approval notifications | Notify users about request, approval, rejection, cancellation, reassignment, reminder, and escalation | ✅ Complete | Uses Laravel notifications |
| Approval history timeline | Track request events through activity log entries | ✅ Complete | Visible on approval detail |
| Governed-action state for frontend | Tell the UI whether approval is enabled, required, blocked, or ready to retry | ✅ Complete | Shared through approval context data |
| Rule conditions UI | Configure advanced rule conditions from Cumpu UI | 🟡 Need improvement | Backend matching exists, but the current rule UI focuses on core workflow fields |

## How Cumpu works

Cumpu uses a **gate-and-grant** model.

It does **not** replay a stored mutation payload.

### Approval lifecycle

1. A user attempts a governed action.
2. The domain action calls `SubmitGovernedAction`.
3. If no enabled rule applies, the action runs immediately.
4. If an enabled rule applies and the actor does not bypass approval, the user must provide `request_note`.
5. Cumpu creates an `ApprovalRequest` tied to:
   - requester
   - governed permission name
   - subject model, when there is one
   - subject snapshot
   - approval reason
   - workflow steps
6. Approvers review the request in Cumpu.
7. If approved, the request becomes a **single-use approval grant**.
8. The original requester retries the same action once. If the grant is still valid, it is consumed atomically and the real domain action runs.

### Request statuses

| Status | Meaning |
| --- | --- |
| `pending` | New request waiting at the current step |
| `in_review` | Request moved forward in a multi-step workflow |
| `approved` | Grant is ready to be used |
| `rejected` | Request was rejected |
| `cancelled` | Request was cancelled |
| `expired` | Approved grant expired before use |
| `consumed` | Approved grant was used successfully |

## Key Cumpu building blocks

| File | Purpose |
| --- | --- |
| `app/Contracts/Approvals/ApprovalSubject.php` | Contract for models that can act as approval subjects |
| `app/Models/Concerns/InteractsWithApprovals.php` | Trait that adds approval relationships plus default label and snapshot behavior |
| `app/Actions/Tyanc/Approvals/SubmitGovernedAction.php` | Main approval gate used by domain actions |
| `app/Actions/Tyanc/Approvals/CreateApprovalProposal.php` | Creates approval requests and first-step assignments |
| `app/Actions/Tyanc/Approvals/ConsumeApprovalGrant.php` | Consumes approved grants on retry |
| `app/Actions/Tyanc/Approvals/ResolveApprovalContext.php` | Builds approval context and governed-action state for the UI |
| `app/Data/Cumpu/Approvals/GovernedActionStateData.php` | Typed frontend-friendly state for governed actions |
| `resources/js/components/cumpu/approvals/ApprovalReasonDialog.vue` | Shared requester reason modal |
| `routes/cumpu.php` | Cumpu routes |
| `resources/js/pages/cumpu/*` | Cumpu pages |

## Where to put your integration files

Keep approval integration inside the app that owns the action.

### Tyanc-owned governance feature

| Layer | File location |
| --- | --- |
| Routes | `routes/tyanc.php` |
| Controller | `app/Http/Controllers/Tyanc/...` |
| Action | `app/Actions/Tyanc/...` |
| Page | `resources/js/pages/tyanc/...` |
| Component | `resources/js/components/tyanc/...` |

### Future app feature, for example ERP

| Layer | File location |
| --- | --- |
| Routes | `routes/erp.php` |
| Controller | `app/Http/Controllers/Erp/...` |
| Action | `app/Actions/Erp/...` |
| Page | `resources/js/pages/erp/...` |
| Component | `resources/js/components/erp/...` |

Do **not** move the domain mutation into Cumpu.

Cumpu owns the approval workspace. The original app still owns the real mutation.

## Integration checklist

1. Define the governed permission.
2. Keep the app route, sidebar, and registry aligned.
3. Make the subject model approval-aware when the action works on a real model.
4. Wrap the domain action with `SubmitGovernedAction`.
5. Return `202` for JSON approval submissions and a redirect for browser submissions.
6. Send governed-action state to the frontend with `ResolveApprovalContext`.
7. Reuse `ApprovalReasonDialog.vue` in the requester UI.
8. Create the approval rule in Cumpu.
9. Sync permissions, apps, and route helpers when needed.
10. Add tests for both direct execution and approval-gated execution.

## Step 1: define the governed permission

Add the real action permission to `config/permission-sot.php`.

Example for an ERP order update action:

```php
'erp' => [
    'label' => 'ERP',
    'resources' => [
        'orders' => [
            'label' => 'Orders',
            'actions' => ['viewany', 'view', 'create', 'update', 'delete', 'manage'],
        ],
    ],
],
```

Important rules:

- Use the real permission name, such as `erp.orders.update`.
- Do **not** create fake approval resources such as `erp.order_approvals.update`.
- Cumpu reviewers still use Cumpu permissions such as `cumpu.approvals.approve`.
- The governed business permission stays attached to the real resource action.

## Step 2: keep app metadata aligned

If the feature belongs to a real coded app, keep these files aligned:

- `config/permission-sot.php`
- `config/sidebar-menu.php`
- `routes/{app}.php`
- app namespace code
- page and component paths

After changes:

```bash
php artisan tyanc:permissions-sync --no-interaction
php artisan tyanc:apps-sync --no-interaction
php artisan wayfinder:generate --no-interaction
```

Run `tyanc:apps-sync` only when app or page registry metadata changed.

## Step 3: make the subject approval-aware

If the action targets a real model, implement `ApprovalSubject` and use `InteractsWithApprovals`.

Example:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Approvals\ApprovalSubject;
use App\Models\Concerns\InteractsWithApprovals;
use Illuminate\Database\Eloquent\Model;

final class Order extends Model implements ApprovalSubject
{
    use InteractsWithApprovals;

    public function approvalAppKey(): string
    {
        return 'erp';
    }

    public function approvalResourceKey(): string
    {
        return 'orders';
    }
}
```

What the trait gives you:

- `approvalRequests()` relationship
- `approvalHistory()` relationship
- default subject label resolution from common fields such as `label`, `name`, `title`, `file_name`, `key`, and `email`
- default subject snapshot generation from common attributes

Override `approvalSubjectLabel()` or `approvalSubjectSnapshot()` when you need richer reviewer context.

Current project examples:

- `app/Models/User.php`
- `app/Models/Role.php`
- `app/Models/App.php`
- `app/Models/ImportRun.php`

This repo's current built-in model convention is flat under `app/Models`. If your app already uses an app-specific model namespace, keep that convention consistent inside your own app.

## Step 4: wrap the domain action with `SubmitGovernedAction`

Keep the real mutation inside the app action and let Cumpu decide whether it runs now or later.

Example:

```php
<?php

declare(strict_types=1);

namespace App\Actions\Erp\Orders;

use App\Actions\Tyanc\Approvals\SubmitGovernedAction;
use App\Models\Erp\Order;
use App\Models\User;
use App\Support\Permissions\PermissionKey;

final readonly class UpdateOrder
{
    public function __construct(private SubmitGovernedAction $governedActions) {}

    public function handle(User $actor, Order $order, array $attributes): array
    {
        $requestNote = is_string($attributes['request_note'] ?? null)
            ? mb_trim((string) $attributes['request_note'])
            : null;

        return $this->governedActions->handle(
            actor: $actor,
            permissionName: PermissionKey::make('erp', 'orders', 'update'),
            subject: $order,
            context: [
                'request_note' => $requestNote,
            ],
            definition: [
                'execute' => fn (): Order => $this->persist($order, $attributes),
                'proposal' => [
                    'request_note' => $requestNote,
                    'payload' => [
                        'action_label' => __('Update order'),
                        'subject_label' => $order->approvalSubjectLabel(),
                    ],
                    'subject_snapshot' => $order->approvalSubjectSnapshot(),
                ],
            ],
        );
    }

    private function persist(Order $order, array $attributes): Order
    {
        $order->fill($attributes);
        $order->save();

        return $order;
    }
}
```

The returned shape is:

```php
[
    'executed' => bool,
    'result' => mixed,
    'approval' => ApprovalRequest|null,
    'bypassed' => bool,
]
```

## Step 5: handle controller responses correctly

For JSON requests, return `202` when approval was submitted.

For browser requests, redirect back or to the page the user came from.

Example:

```php
$submission = $action->handle($user, $order, $request->validated());

if ($submission['approval'] instanceof ApprovalRequest) {
    if ($request->wantsJson()) {
        return response()->json([
            'executed' => false,
            'approval' => ApprovalRequestData::fromModel($submission['approval'], $user),
        ], 202);
    }

    return back()->with('status', __('Approval request submitted.'));
}

return back();
```

Do **not** invent a second approval response contract.

## Step 6: send governed-action state to the frontend

Use `ResolveApprovalContext` so the page knows whether approval is enabled, required, already blocked by an active request, or ready to retry with a usable grant.

Example:

```php
'approvalContext' => $approvalContext->handle(
    actor: $user,
    scopeLabel: $order->number,
    appKey: 'erp',
    resourceKey: 'orders',
    subject: $order,
    actionKeys: ['update', 'delete'],
    governedActionKeys: ['update', 'delete'],
),
```

Important governed-action fields:

- `approval_enabled`
- `approval_required`
- `bypasses_for_actor`
- `has_usable_grant`
- `has_blocking_request`
- `relevant_request`

On the frontend:

- reuse `resources/js/components/cumpu/approvals/ApprovalReasonDialog.vue`
- open the reason dialog only when `approval_required` is `true`
- skip the dialog when the action executes directly or a usable grant already exists

## Step 7: create the approval rule in Cumpu

Open `/cumpu/approval-rules` and create a rule for the real governed action.

For each rule, Cumpu currently supports:

- app
- resource
- action
- enabled state
- workflow type
- one or more role-based steps
- grant validity in minutes
- reminder window in minutes
- escalation window in minutes

Important rules:

- reviewers must hold the governed permission itself
- reviewers must also hold Cumpu review permissions
- self-approval is blocked
- actors can bypass approval when they already qualify as the first-step approver
- navigation-only resources are excluded from normal rule options

## Step 8: know the two integration patterns

### Pattern A: model-backed subject

Use this when the action targets a real record such as a user, role, app, or order.

- pass the model as `subject`
- make the model implement `ApprovalSubject`
- send a useful subject snapshot

### Pattern B: subject-less request

Use this when the action does not have one stable model subject yet.

The existing users import flow is the example.

In that case:

- pass `subject: null`
- still provide `proposal.payload.subject_label`
- still provide `proposal.subject_snapshot`

That keeps the request reviewable even without a single model subject.

## Step 9: respect file-backed action rules

Cumpu's current model is **retry after approval**, not stored replay.

That means file-backed actions should usually:

- request approval first
- get approved
- upload or run the action again after approval

Do **not** design new approval work around staged file replay unless the product explicitly needs a special-case exception.

## Step 10: test the integration

At minimum, test these cases:

1. no rule enabled, action executes immediately
2. rule enabled, approval request is created before mutation
3. missing `request_note` causes validation failure when approval is required
4. approved request lets the same requester retry the same action once
5. second retry fails because the grant was consumed
6. expired grant fails
7. another user cannot consume the grant
8. another subject cannot reuse the grant

## Common mistakes

Do not do these things:

- do not create fake approval resources
- do not move domain mutations into Cumpu
- do not hardcode raw permission strings everywhere when `PermissionKey` can build them
- do not forget to sync permissions after editing `config/permission-sot.php`
- do not forget to sync app pages after editing `config/sidebar-menu.php`
- do not use navigation-only permissions as governed business actions
- do not auto-run the mutation after approval by default
- do not store replay payloads for normal CRUD governance
- do not expect one approval to work for another user or another subject

## Quick file map for a new app integration

Example for an ERP orders feature:

| Purpose | File |
| --- | --- |
| Route group | `routes/erp.php` |
| Controller | `app/Http/Controllers/Erp/OrderController.php` |
| Domain action | `app/Actions/Erp/Orders/UpdateOrder.php` |
| Subject model | `app/Models/Order.php` or your existing model location |
| Page | `resources/js/pages/erp/orders/Edit.vue` |
| Shared reason modal | `resources/js/components/cumpu/approvals/ApprovalReasonDialog.vue` |
| Permission source | `config/permission-sot.php` |
| Navigation source | `config/sidebar-menu.php` |

## Operational commands to remember

```bash
php artisan tyanc:permissions-sync --no-interaction
php artisan tyanc:apps-sync --no-interaction
php artisan wayfinder:generate --no-interaction
php artisan approvals:dispatch-escalations
```

The escalation command is already scheduled every ten minutes through `routes/console.php`.

## Related files to study in this repo

- `app/Actions/Tyanc/Approvals/SubmitGovernedAction.php`
- `app/Actions/Tyanc/Approvals/CreateApprovalProposal.php`
- `app/Actions/Tyanc/Approvals/ConsumeApprovalGrant.php`
- `app/Actions/Tyanc/Approvals/ResolveApprovalContext.php`
- `app/Models/User.php`
- `app/Models/Role.php`
- `app/Models/App.php`
- `app/Models/ImportRun.php`
- `app/Actions/Tyanc/Users/UpdateUser.php`
- `app/Actions/Tyanc/Users/DeleteUser.php`
- `app/Actions/Tyanc/Imports/SubmitUsersImport.php`

## Summary

Cumpu is the approval center.

Your app still owns the real mutation.

The correct pattern is:

1. define the real permission
2. make the subject reviewable
3. gate the action with `SubmitGovernedAction`
4. send approval state to the UI
5. manage the rule in Cumpu
6. let the requester retry once after approval
