<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Quote;
use App\Support\ChatResponder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PublicController extends Controller
{
    public function stats()
    {
        return response()->json([
            'quotes' => Quote::query()->count(),
            'invoices' => Invoice::query()->count(),
            'companies' => Company::query()->count(),
        ]);
    }

    public function chat(Request $request)
    {
        $data = $this->validateData($request, [
            'message' => ['required', 'string', 'max:1000'],
            'token' => ['nullable', 'string', 'max:64'],
        ]);

        $token = $data['token'] ?? null;
        $token = $token ?: Str::random(40);
        ChatMessage::create([
            'guest_token' => $token,
            'role' => 'user',
            'body' => trim($data['message']),
        ]);

        $reply = ChatResponder::reply($data['message']);
        ChatMessage::create([
            'guest_token' => $token,
            'role' => 'assistant',
            'body' => $reply,
        ]);

        return response()->json([
            'token' => $token,
            'reply' => $reply,
        ]);
    }
}
