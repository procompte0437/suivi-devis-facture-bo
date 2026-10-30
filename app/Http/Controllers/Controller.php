<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    protected function validateData(Request $request, array $rules): array
    {
        return $request->validate($rules, [
            'required' => 'Ce champ est requis.',
            'email' => 'Adresse e-mail invalide.',
            'unique' => 'Cette valeur est déjà utilisée.',
            'min.string' => 'Au moins :min caractères.',
            'min.numeric' => 'La valeur minimale est :min.',
            'min.array' => 'Ajoutez au moins un élément.',
            'max.string' => 'Trop long (:max caractères maximum).',
            'max.numeric' => 'La valeur maximale est :max.',
            'numeric' => 'Doit être un nombre.',
            'integer' => 'Doit être un entier.',
            'exists' => 'Référence introuvable.',
            'in' => 'Valeur non autorisée.',
            'date' => 'Date invalide.',
            'boolean' => 'Valeur invalide.',
            'array' => 'Format invalide.',
        ]);
    }
}
