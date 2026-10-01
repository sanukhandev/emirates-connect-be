<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Emirate;
use App\Enums\Industry;
use App\Http\Controllers\Controller;

class MetaController extends Controller
{
    public function industries(): array
    {
        return ['data' => Industry::options()];
    }

    public function emirates(): array
    {
        return ['data' => Emirate::options()];
    }
}
