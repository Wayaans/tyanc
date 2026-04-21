<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Support\Notifications\FlashToast;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\TwoFactorEnabledResponse as TwoFactorEnabledResponseContract;

final class TwoFactorEnabledResponse implements TwoFactorEnabledResponseContract
{
    public function toResponse($request)
    {
        return $request->wantsJson()
            ? new JsonResponse('', 200)
            : back()->with('toast', FlashToast::success(__('Two-factor authentication enabled.'))->toArray());
    }
}
