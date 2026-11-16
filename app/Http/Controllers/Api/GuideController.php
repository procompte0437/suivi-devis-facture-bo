<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\GenerateurGuidePdf;
use Symfony\Component\HttpFoundation\Response;

/** Téléchargement du guide utilisateur. */
class GuideController extends Controller
{
    public function pdf(): Response
    {
        return GenerateurGuidePdf::reponse();
    }
}
