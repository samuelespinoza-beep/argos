<?php

use App\Scrapers\CuponaticScraper;
use App\Scrapers\CuponidadScraper;

return [

    'timezone' => 'America/Lima',

    'hora_diaria' => env('ARGOS_HORA_DIARIA', '06:00'),

    'sitios' => [

        // Sitio propio.
        'cuponidad' => [
            'scraper' => CuponidadScraper::class,
            'sitemap' => 'https://cuponidad.pe/sitemap.xml',
            'concurrencia' => 3,
            'pausa_ms' => 500,
            'user_agent' => 'Mozilla/5.0',
            // El "N vendidos" de la pagina cuenta cada cupon dos veces (OffersSold + COUNT(Purchases) en
            // cuponidad-api). La base guarda el numero tal como se publica; esto lo corrige al mostrarlo.
            // Verificado el 02/11/2026: Cineplanet 45409, +518 en la pagina = 259 cupones reales.
            'factor_vendidos' => 0.5,
        ],

        // Competencia.
        'cuponatic' => [
            'scraper' => CuponaticScraper::class,
            'api' => 'https://pe-api.cuponatic-latam.com/api2/cdn/descuentos/menu',
            'ciudad' => 'Lima',
            'limit' => 1000,
            'pausa_segundos' => 2,
            'categorias' => [
                'gastronomia', 'belleza', 'bienestar-y-salud', 'entretenimiento',
                'productos', 'servicios', 'viajes-y-turismo',
            ],
            'user_agent' => 'Mozilla/5.0',
        ],
    ],
];
