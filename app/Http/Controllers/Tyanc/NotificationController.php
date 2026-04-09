<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tyanc;

use App\Data\Notifications\NotificationData;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class NotificationController
{
    public function index(#[CurrentUser] User $user): JsonResponse
    {
        return response()->json($this->payload($user));
    }

    public function update(Request $request, #[CurrentUser] User $user, string $notification): RedirectResponse|JsonResponse
    {
        $record = $user->notifications()->whereKey($notification)->first();

        throw_if($record === null, NotFoundHttpException::class);

        $record->markAsRead();

        if ($request->wantsJson()) {
            return response()->json([
                'notification' => NotificationData::fromModel($record->fresh()),
            ]);
        }

        return back();
    }

    public function markAllRead(Request $request, #[CurrentUser] User $user): RedirectResponse|JsonResponse
    {
        $user->unreadNotifications()->update(['read_at' => now()]);

        if ($request->wantsJson()) {
            return response()->json($this->payload($user->fresh()));
        }

        return back();
    }

    /**
     * @return array{unread_count: int, recent: list<NotificationData>}
     */
    private function payload(User $user): array
    {
        return [
            'unread_count' => $user->unreadNotifications()->count(),
            'recent' => $user->notifications()
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (DatabaseNotification $notification): NotificationData => NotificationData::fromModel($notification))
                ->all(),
        ];
    }
}
