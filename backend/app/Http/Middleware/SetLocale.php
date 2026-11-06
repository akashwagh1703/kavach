<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $portal = (string) $request->input('portal', '');
        if ($request->hasSession() && $request->session()->isStarted()) {
            $portal = (string) $request->session()->get('kavach.portal', $portal);
        }
        $locale = 'en';
        if (in_array($portal, ['guard', 'supervisor'], true)) {
            $preferred = $request->user()?->locale ?: $request->getPreferredLanguage(['en', 'hi', 'mr']);
            if (in_array($preferred, ['en', 'hi', 'mr'], true)) {
                $locale = $preferred;
            }
        }
        app()->setLocale($locale);

        return $next($request);
    }
}
