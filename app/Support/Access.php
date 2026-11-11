<?php

namespace App\Support;

use App\Models\User;

/**
 * Droits par rôle, d'après le tableau des acteurs du cahier des charges.
 * L'administrateur (et le dirigeant qui a créé son compte) a tous les droits.
 * Les autres rôles internes lisent tout, mais n'écrivent que dans leur module.
 */
class Access
{
    public const ROLES = ['admin', 'dirigeant', 'commercial', 'comptable', 'chef_projet', 'client'];

    private const WRITE = [
        'clients' => ['commercial'],
        'quotes' => ['commercial'],
        'quotes.convert' => ['comptable'],
        'invoices' => ['comptable'],
        'projects' => ['chef_projet'],
        'staff' => ['chef_projet'],
        'companies' => [],
    ];

    public static function isManager(?User $user): bool
    {
        return in_array($user?->role, ['admin', 'dirigeant'], true);
    }

    public static function isClient(?User $user): bool
    {
        return $user?->role === 'client';
    }

    public static function allows(?User $user, string $ability): bool
    {
        if (! $user || self::isClient($user)) {
            return false;
        }

        return self::isManager($user) || in_array($user->role, self::WRITE[$ability] ?? [], true);
    }

    /** Liste envoyée au front pour masquer les boutons inutiles. */
    public static function abilities(User $user): array
    {
        return collect(array_keys(self::WRITE))
            ->filter(fn (string $ability) => self::allows($user, $ability))
            ->values()
            ->all();
    }
}
