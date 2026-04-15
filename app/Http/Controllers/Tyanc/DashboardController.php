<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tyanc;

use App\Actions\Tyanc\ResolveDashboardOverview;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class DashboardController
{
    public function __construct(private ResolveDashboardOverview $dashboardOverview) {}

    public function show(Request $request, #[CurrentUser] User $user): Response|JsonResponse
    {
        $payload = $this->dashboardOverview->handle($user);

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('tyanc/Dashboard', $payload);
    }
}
