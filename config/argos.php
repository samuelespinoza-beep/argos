<?php

use App\Scrapers\CuponaticScraper;
use App\Scrapers\CuponidadScraper;

return [

    // Zona horaria de las fechas de los snapshots y del horario de la tarea diaria.
    'timezone' => 'America/Lima',

    // Hora (Lima) a la que corre el scraping diario de todos los sitios.
    'hora_diaria' => env('ARGOS_HORA_DIARIA', '06:00'),

    'sitios' => [

        // Sitio propio.
        'cuponidad' => [
            'scraper' => CuponidadScraper::class,
            'sitemap' => 'https://cuponidad.pe/sitemap.xml',
            'concurrencia' => 3,
            'pausa_ms' => 500,
            'user_agent' => 'Mozilla/5.0',
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
