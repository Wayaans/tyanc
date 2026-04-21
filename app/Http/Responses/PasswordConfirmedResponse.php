<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Support\Notifications\FlashToast;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\PasswordConfirmedResponse as PasswordConfirmedResponseContract;
use Laravel\Fortify\Fortify;

final class PasswordConfirmedResponse implements PasswordConfirmedResponseContract
{
    public function toResponse($request)
    {
        return $request->wantsJson()
            ? new JsonResponse('', 201)
            : redirect()->intended(Fortify::redirects('password-confirmation'))
                ->with('toast', FlashToast::success(__('Password confirmed.'))->toArray());
    }
}
