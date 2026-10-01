<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class LogoutController extends Controller
{
    public function __invoke(Request $request)
    {
        if ($token = $request->bearerToken()) {
            PersonalAccessToken::findToken($token)?->delete();
        }

        return response()->noContent();
    }
}
