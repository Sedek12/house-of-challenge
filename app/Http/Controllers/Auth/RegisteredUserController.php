<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/register');
    }

    public static function profileRules(?int $ignoreId = null): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone_e164' => ['required', 'regex:/^\+[1-9]\d{6,14}$/'],
            'country' => ['required', 'string', 'max:80'],
            'city' => ['nullable', 'string', 'max:120'],
            'tiktok_username' => ['nullable', 'string', 'max:100'],
        ];
    }

    public static function profileMessages(): array
    {
        return ['phone_e164.regex' => 'Le numéro doit être au format international, ex. +22890000000.'];
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            ...self::profileRules(),
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], self::profileMessages());

        $data['phone_e164'] = preg_replace('/\s+/', '', $data['phone_e164']);
        $data['tiktok_username'] = User::normalizeTiktok($data['tiktok_username'] ?? null);
        $data['name'] = trim($data['first_name'].' '.$data['last_name']);

        $user = User::create($data);

        event(new Registered($user));
        Auth::login($user);

        return redirect()->intended(route('space.home'));
    }
}
