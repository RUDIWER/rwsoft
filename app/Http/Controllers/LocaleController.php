<?php

namespace App\Http\Controllers;

use App\Http\Requests\SetLocaleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\App;

class LocaleController extends Controller
{
    public function update(SetLocaleRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $locale = (string) $validated['locale'];

        $request->session()->put('locale', $locale);
        App::setLocale($locale);

        return back();
    }
}
