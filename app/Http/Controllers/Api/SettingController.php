<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function show()
    {
        return response()->json(Setting::current());
    }

    public function update(Request $request)
    {
        $data = $this->validateData($request, [
            'company_name' => ['required', 'string', 'max:160'],
            'tagline' => ['nullable', 'string', 'max:180'],
            'address' => ['nullable', 'string', 'max:180'],
            'city' => ['nullable', 'string', 'max:80'],
            'country' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'tax_id' => ['nullable', 'string', 'max:80'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'currency' => ['required', 'in:XAF,EUR,USD'],
            'quote_prefix' => ['required', 'string', 'max:12'],
            'invoice_prefix' => ['required', 'string', 'max:12'],
            'payment_note' => ['nullable', 'string', 'max:1000'],
            'footer' => ['nullable', 'string', 'max:1000'],
        ]);

        $settings = Setting::current();
        $settings->update($data);
        ActivityLogger::log($request->user(), 'maison.maj', null, 'Paramètres de la maison mis à jour.');

        return response()->json($settings->fresh());
    }
}
