<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;

class ActivityController extends Controller
{
    public function index()
    {
        return response()->json(
            Activity::query()->with('user:id,name,role')->latest()->limit(80)->get()
        );
    }
}
