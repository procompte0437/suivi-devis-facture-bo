<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public const SUBJECTS = ['demo', 'tarifs', 'support', 'partenariat', 'autre'];

    /** Formulaire public de la page Contact. */
    public function store(Request $request)
    {
        $data = $this->validateData($request, [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'subject' => ['required', 'in:'.implode(',', self::SUBJECTS)],
            'message' => ['required', 'string', 'min:10', 'max:1000'],
            // Champ caché : rempli seulement par les robots.
            'website' => ['nullable', 'max:0'],
        ]);

        unset($data['website']);
        ContactMessage::create($data);

        return response()->json(['message' => 'Message envoyé. Nous vous répondrons par e-mail.'], 201);
    }

    /** Lecture côté administrateur de la plateforme. */
    public function index(Request $request)
    {
        abort_unless($request->user()?->role === 'admin', 403, 'Réservé à l’administrateur de la plateforme.');

        return response()->json(ContactMessage::query()->latest()->limit(200)->get());
    }

    public function handle(Request $request, ContactMessage $message)
    {
        abort_unless($request->user()?->role === 'admin', 403, 'Réservé à l’administrateur de la plateforme.');

        $message->update(['handled_at' => $message->handled_at ? null : now()]);

        return response()->json($message);
    }
}
