<?php

namespace App\Http\Middleware;

use App\Services\DatosArgos;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /** "sitios" arma el menu de arriba en todas las paginas. */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'sitios' => fn () => array_keys(app(DatosArgos::class)->indice()['sitios']),
        ];
    }
}
