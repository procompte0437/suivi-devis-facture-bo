<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    protected function validateData(Request $request, array $rules, array $messages = []): array
    {
        return $request->validate($rules, array_merge([
            'required' => 'Ce champ est requis.',
            'email' => 'Adresse e-mail invalide.',
            'unique' => 'Cette valeur est déjà utilisée.',
            // Les règles de taille attendent un tableau indexé par type de valeur.
            'min' => [
                'string' => 'Au moins :min caractères.',
                'numeric' => 'La valeur minimale est :min.',
                'array' => 'Ajoutez au moins un élément.',
                'file' => 'Fichier trop petit.',
            ],
            'max' => [
                'string' => 'Trop long (:max caractères maximum).',
                'numeric' => 'La valeur maximale est :max.',
                'array' => 'Trop d’éléments (:max maximum).',
                'file' => 'Fichier trop lourd.',
            ],
            'confirmed' => 'La confirmation ne correspond pas.',
            'required_if' => 'Ce champ est requis.',
            'numeric' => 'Doit être un nombre.',
            'integer' => 'Doit être un entier.',
            'exists' => 'Référence introuvable.',
            'in' => 'Valeur non autorisée.',
            'date' => 'Date invalide.',
            'boolean' => 'Valeur invalide.',
            'array' => 'Format invalide.',
        ], $messages));
    }
}
