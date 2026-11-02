<?php

namespace App\Scrapers;

interface Scraper
{
    public function sitio(): string;

    /** @return iterable<array<string, mixed>> */
    public function ofertas(): iterable;
}
