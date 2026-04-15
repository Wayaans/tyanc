<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tyanc;

use App\Actions\Authorization\PermissionResourceAccess;
use App\Exports\ActivityLogExport;
use App\Exports\UsersExport;
use App\Models\User;
use App\Support\Permissions\PermissionKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Container\Attributes\CurrentUser;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Spatie\Activitylog\Models\Activity;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class ExportController
{
    public function users(#[CurrentUser] User $user): StreamedResponse
    {
        $this->ensureExportsEnabled();
        $this->authorizePermission($user, PermissionKey::tyanc('users', 'export'));

        return response()->streamDownload(function (): void {
            echo new UsersExport()->raw(ExcelFormat::XLSX);
        }, 'users-export.xlsx');
    }

    public function usersPdf(#[CurrentUser] User $user): PdfBuilder
    {
        $this->ensureExportsEnabled();
        $this->authorizePermission($user, PermissionKey::tyanc('users', 'export'));

        $users = User::query()
            ->with('roles')
            ->orderBy('name')
            ->orderBy('username')
            ->get();

        return Pdf::view('pdf.user-report', [
            'users' => $users,
            'generatedAt' => now(),
        ])
            ->driver('dompdf')
            ->format(Format::A4)
            ->name('users-report.pdf')
            ->download();
    }

    public function activityLog(#[CurrentUser] User $user): StreamedResponse
    {
        $this->ensureExportsEnabled();
        $this->authorizePermission($user, PermissionKey::tyanc('activity_log', 'export'));

        return response()->streamDownload(function (): void {
            echo new ActivityLogExport()->raw(ExcelFormat::XLSX);
        }, 'activity-log-export.xlsx');
    }

    public function activitySummaryPdf(#[CurrentUser] User $user): PdfBuilder
    {
        $this->ensureExportsEnabled();
        $this->authorizePermission($user, PermissionKey::tyanc('activity_log', 'export'));

        $activities = Activity::query()
            ->with(['subject', 'causer'])
            ->latest('created_at')
            ->limit(100)
            ->get();

        return Pdf::view('pdf.activity-summary', [
            'activities' => $activities,
            'generatedAt' => now(),
        ])
            ->driver('dompdf')
            ->format(Format::A4)
            ->name('activity-summary.pdf')
            ->download();
    }

    private function ensureExportsEnabled(): void
    {
        throw_unless((bool) config('tyanc.features.exports_enabled', false), NotFoundHttpException::class);
    }

    private function authorizePermission(User $user, string $permissionName): void
    {
        throw_if(
            ! resolve(PermissionResourceAccess::class)->handle($user, $permissionName),
            AuthorizationException::class,
        );
    }
}
