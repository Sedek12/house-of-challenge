<?php

namespace App\Http\Middleware;

use App\Services\Settings;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user ? ['id' => $user->id, 'name' => $user->name, 'role' => $user->role] : null,
            ],
            'site' => [
                'whatsapp' => Settings::get('contact_whatsapp'),
                'tiktok' => Settings::get('tiktok_show_url'),
                'timezone' => config('app.display_timezone'),
                'logo' => Settings::get('brand_logo_url'),
                'brand_color' => Settings::get('brand_color'),
                'email' => filter_var($e = trim((string) Settings::get('contact_email')), FILTER_VALIDATE_EMAIL) ? $e : null,
                'studio' => Settings::get('studio_address'),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
            ],
        ];
    }
}
