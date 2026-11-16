<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * Inscription et connexion par Google ou GitHub.
 *
 * L'API est sans session (jetons Sanctum) : le jeton anti-falsification « state »
 * est donc conservé en cache 10 minutes et vérifié au retour du fournisseur.
 * Au retour, on redirige vers le front avec un code à usage unique (60 s),
 * échangé contre le jeton de session : le jeton n'apparaît jamais dans une adresse.
 */
class SocialAuthController extends Controller
{
    public const PROVIDERS = ['google', 'github'];

    /** Fournisseurs configurés (identifiants présents dans .env). */
    public function providers()
    {
        return response()->json(collect(self::PROVIDERS)
            ->mapWithKeys(fn (string $provider) => [$provider => $this->configured($provider)]));
    }

    public function redirect(Request $request, string $provider)
    {
        $this->ensureProvider($provider);
        if (! $this->configured($provider)) {
            return $this->backToFront(['erreur' => 'non-configure', 'fournisseur' => $provider]);
        }

        $state = Str::random(40);
        Cache::put('oauth_state:'.$state, $provider, now()->addMinutes(10));

        $driver = Socialite::driver($provider)->stateless()->with(['state' => $state]);
        if ($provider === 'github') {
            $driver->scopes(['read:user', 'user:email']);
        }

        return $driver->redirect();
    }

    public function callback(Request $request, string $provider)
    {
        $this->ensureProvider($provider);

        $state = (string) $request->query('state');
        if ($state === '' || Cache::pull('oauth_state:'.$state) !== $provider) {
            return $this->backToFront(['erreur' => 'session-expiree']);
        }
        if ($request->query('error')) {
            return $this->backToFront(['erreur' => 'annule']);
        }

        try {
            $remote = Socialite::driver($provider)->stateless()->user();
        } catch (Throwable $exception) {
            report($exception);

            return $this->backToFront(['erreur' => 'fournisseur']);
        }

        $email = $remote->getEmail() ? mb_strtolower($remote->getEmail()) : null;
        if (! $email) {
            return $this->backToFront(['erreur' => 'email-manquant', 'fournisseur' => $provider]);
        }

        [$user, $created] = DB::transaction(function () use ($provider, $remote, $email) {
            $link = SocialAccount::query()->where('provider', $provider)->where('provider_id', (string) $remote->getId())->first();
            $user = $link?->user ?? User::query()->where('email', $email)->first();
            $created = false;

            if (! $user) {
                // Nouveau compte « personne » : l'écran d'accueil propose ensuite de créer une entreprise.
                $user = User::create([
                    'name' => $remote->getName() ?: ($remote->getNickname() ?: Str::before($email, '@')),
                    'email' => $email,
                    'password' => Str::password(40),
                    'role' => 'dirigeant',
                    'account_type' => 'personne',
                    'job_title' => 'Indépendant',
                    'is_active' => true,
                ]);
                $user->forceFill(['email_verified_at' => now()])->save();
                $created = true;
            }

            SocialAccount::query()->updateOrCreate(
                ['provider' => $provider, 'provider_id' => (string) $remote->getId()],
                ['user_id' => $user->id, 'email' => $email, 'avatar' => $remote->getAvatar()],
            );

            return [$user, $created];
        });

        if (! $user->is_active) {
            return $this->backToFront(['erreur' => 'compte-inactif']);
        }

        $code = Str::random(48);
        Cache::put('oauth_code:'.$code, $user->id, now()->addSeconds(60));

        return $this->backToFront(['code' => $code, 'nouveau' => $created ? '1' : '0']);
    }

    /** Échange le code à usage unique contre un jeton de session. */
    public function exchange(Request $request)
    {
        $data = $this->validateData($request, ['code' => ['required', 'string', 'size:48']]);
        $userId = Cache::pull('oauth_code:'.$data['code']);
        $user = $userId ? User::query()->find($userId) : null;

        if (! $user || ! $user->is_active) {
            return response()->json(['message' => 'Lien de connexion expiré. Recommencez.'], 422);
        }

        $user->tokens()->where('name', 'atelier')->delete();

        return response()->json(['token' => $user->createToken('atelier')->plainTextToken]);
    }

    private function configured(string $provider): bool
    {
        return filled(config("services.$provider.client_id")) && filled(config("services.$provider.client_secret"));
    }

    private function ensureProvider(string $provider): void
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);
    }

    private function backToFront(array $params)
    {
        $front = rtrim((string) config('services.frontend_url', 'http://127.0.0.1:5173'), '/');

        return redirect()->away($front.'/connexion-sociale#'.http_build_query($params));
    }
}
