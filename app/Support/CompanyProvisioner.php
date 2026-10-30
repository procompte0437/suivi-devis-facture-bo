<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CompanyProvisioner
{
    public static function attachExisting(): Company
    {
        $settings = Setting::query()->orderBy('id')->first() ?? Setting::current();
        $company = Company::query()->first() ?? Company::create([
            'name' => $settings->company_name,
            'tagline' => $settings->tagline,
            'address' => $settings->address,
            'city' => $settings->city,
            'country' => $settings->country ?: 'Gabon',
            'email' => $settings->email,
            'phone' => $settings->phone,
            'tax_id' => $settings->tax_id,
            'payment_note' => $settings->payment_note,
            'footer' => $settings->footer,
        ]);

        $attach = User::query()->pluck('id')->mapWithKeys(fn ($id) => [$id => ['role' => 'owner']])->all();
        if ($attach) {
            $company->users()->syncWithoutDetaching($attach);
        }

        foreach (['clients', 'projects', 'quotes', 'invoices'] as $table) {
            DB::table($table)->whereNull('company_id')->update(['company_id' => $company->id]);
        }

        if ($company->staff()->doesntExist()) {
            $company->staff()->createMany([
                ['name' => 'Aurore Blanc', 'role_title' => 'Direction', 'email' => 'aurore.blanc@oree.fr'],
                ['name' => 'Marc Essono', 'role_title' => 'Commercial', 'email' => 'marc.essono@oree.fr'],
                ['name' => 'Léa Nguema', 'role_title' => 'Comptabilité', 'email' => 'lea.nguema@oree.fr'],
            ]);
        }

        return $company;
    }
}
