<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlatformCity;
use App\Support\Access;
use App\Support\AuditLogger;
use Illuminate\Http\Request;

class PlatformCityController extends Controller
{
    public function index()
    {
        return response()->json(
            PlatformCity::query()->orderBy('position')->orderBy('label')->get()
        );
    }

    public function store(Request $request)
    {
        $this->onlyPlatformAdmin($request);
        $data = $this->validateData($request, [
            'label' => ['required', 'string', 'max:80', 'unique:platform_cities,label'],
        ]);
        $label = trim($data['label']);

        $city = PlatformCity::query()->create([
            'label' => $label,
            'position' => (int) PlatformCity::query()->max('position') + 1,
        ]);
        AuditLogger::record($request->user(), 'ville.ajoutee', 'Ville '.$city->label.' ajoutée à l’inscription.', $city);

        return response()->json($city, 201);
    }

    public function destroy(Request $request, PlatformCity $city)
    {
        $this->onlyPlatformAdmin($request);
        if (PlatformCity::query()->count() <= 1) {
            return response()->json([
                'message' => 'Gardez au moins une ville : elle est proposée à l’inscription.',
            ], 422);
        }

        $label = $city->label;
        $city->delete();
        AuditLogger::record($request->user(), 'ville.retiree', 'Ville '.$label.' retirée de l’inscription.');

        return response()->json(['message' => 'Ville retirée.']);
    }

    private function onlyPlatformAdmin(Request $request): void
    {
        abort_unless(Access::isPlatformAdmin($request->user()), 403, 'Réservé à l’administrateur de la plateforme.');
    }
}
