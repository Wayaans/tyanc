<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final readonly class LocaleController
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', Rule::in(array_keys((array) config('tyanc.supported_locales', [])))],
        ]);

        $request->session()->put('locale', $validated['locale']);

        return back();
    }
}
