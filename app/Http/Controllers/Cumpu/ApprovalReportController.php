<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cumpu;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Actions\Tyanc\Approvals\ListApprovalReports;
use App\Exports\ApprovalRequestsExport;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class ApprovalReportController
{
    public function index(Request $request, #[CurrentUser] User $user, ListApprovalReports $action): Response|JsonResponse
    {
        $payload = $action->handle($user, $request);

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('cumpu/approvals/Reports', $payload);
    }

    public function export(Request $request, #[CurrentUser] User $user, ListApprovalReports $action): StreamedResponse
    {
        $this->ensureExportsEnabled();

        throw_if(
            ! resolve(PermissionResourceAccess::class)->handle($user, PermissionKey::cumpu('reports', 'export')),
            AuthorizationException::class,
        );

        $rows = $action->rows($user, $request);

        return response()->streamDownload(function () use ($rows): void {
            echo new ApprovalRequestsExport($rows)->raw(ExcelFormat::XLSX);
        }, 'approval-requests-report.xlsx');
    }

    private function ensureExportsEnabled(): void
    {
        throw_unless((bool) config('tyanc.features.exports_enabled', false), NotFoundHttpException::class);
    }
}
