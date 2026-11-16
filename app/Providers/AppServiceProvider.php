<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\PlatformCity;
use App\Models\Project;
use App\Models\Quote;
use App\Models\ReferentielItem;
use App\Models\StaffMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale(config('app.locale', 'fr'));

        Relation::enforceMorphMap([
            'client' => Client::class,
            'quote' => Quote::class,
            'invoice' => Invoice::class,
            'project' => Project::class,
            'user' => User::class,
            'company' => Company::class,
            'staff' => StaffMember::class,
            'city' => PlatformCity::class,
            'referentiel' => ReferentielItem::class,
        ]);
    }
}
