<?php

namespace App\Http\Middleware;

use App\Services\DatosArgos;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * Vista Blade que carga la primera visita (resources/views/app.blade.php).
     *
     * @var string
     */
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Props que reciben todas las paginas. "sitios" arma el menu de arriba
     * (layouts/Principal.vue); sale del indice en cache, no consulta la base.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'sitios' => fn () => array_keys(app(DatosArgos::class)->indice()['sitios']),
        ];
    }
}
