<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SiteImage;
use App\Support\Access;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Images du site public. Chaque emplacement a une image par défaut côté front ;
 * l'administrateur de la plateforme peut la remplacer, changer son texte alternatif
 * ou revenir à l'image d'origine.
 */
class SiteImageController extends Controller
{
    public const SLOTS = [
        'hero_1', 'hero_2', 'hero_3', 'project_cta', 'banner',
        'sector_artisans', 'sector_agences', 'sector_btp', 'sector_independants',
        'about', 'login',
    ];

    /** Public : images remplacées, indexées par emplacement. */
    public function index()
    {
        $images = SiteImage::query()->whereIn('slot', self::SLOTS)->get()
            ->mapWithKeys(fn (SiteImage $image) => [$image->slot => [
                'url' => $image->url,
                'alt' => $image->alt,
            ]]);

        return response()->json($images);
    }

    /** Administration : détail avec l'auteur et la date de chaque modification. */
    public function admin(Request $request)
    {
        $this->ensurePlatformAdmin($request);

        return response()->json(
            SiteImage::query()->with('editor:id,name')->whereIn('slot', self::SLOTS)->get()->keyBy('slot')
        );
    }

    /** Remplace l'image d'un emplacement (et, au passage, son texte alternatif). */
    public function upload(Request $request, string $slot)
    {
        $this->ensurePlatformAdmin($request);
        $this->ensureSlot($slot);

        $data = $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'alt' => ['nullable', 'string', 'max:200'],
        ], [
            'image.required' => 'Choisissez une image.',
            'image.image' => 'Le fichier doit être une image.',
            'image.mimes' => 'L’image doit être un JPG, un PNG ou un WebP.',
            'image.max' => 'L’image ne doit pas dépasser 5 Mo.',
            'alt.max' => 'Texte trop long (200 caractères maximum).',
        ]);

        $image = SiteImage::query()->firstOrNew(['slot' => $slot]);
        if ($image->path) {
            Storage::disk('public')->delete($image->path);
        }

        $image->path = $request->file('image')->store('site', 'public');
        if (array_key_exists('alt', $data) && $data['alt'] !== null) {
            $image->alt = $data['alt'];
        }
        $image->updated_by = $request->user()->id;
        $image->save();

        ActivityLogger::log($request->user(), 'site.image', $image, 'Image du site « '.$slot.' » remplacée.');

        return response()->json($image->load('editor:id,name'));
    }

    /** Modifie seulement le texte alternatif. */
    public function update(Request $request, string $slot)
    {
        $this->ensurePlatformAdmin($request);
        $this->ensureSlot($slot);

        $data = $this->validateData($request, ['alt' => ['nullable', 'string', 'max:200']]);

        $image = SiteImage::query()->firstOrNew(['slot' => $slot]);
        $image->alt = $data['alt'] ?? null;
        $image->updated_by = $request->user()->id;
        $image->save();

        return response()->json($image->load('editor:id,name'));
    }

    /** Revient à l'image d'origine du site. */
    public function reset(Request $request, string $slot)
    {
        $this->ensurePlatformAdmin($request);
        $this->ensureSlot($slot);

        $image = SiteImage::query()->where('slot', $slot)->first();
        if ($image) {
            if ($image->path) {
                Storage::disk('public')->delete($image->path);
            }
            $image->delete();
        }

        ActivityLogger::log($request->user(), 'site.image', null, 'Image du site « '.$slot.' » remise par défaut.');

        return response()->json(['message' => 'Image d’origine rétablie.']);
    }

    private function ensurePlatformAdmin(Request $request): void
    {
        abort_unless(Access::isPlatformAdmin($request->user()), 403, 'Réservé à l’administrateur de la plateforme.');
    }

    private function ensureSlot(string $slot): void
    {
        abort_unless(in_array($slot, self::SLOTS, true), 404, 'Emplacement inconnu.');
    }
}
