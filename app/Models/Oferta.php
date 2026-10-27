<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Oferta extends Model
{
    protected $table = 'ofertas';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
            'primera_vez_vista' => 'date',
            'ultima_vez_vista' => 'date',
        ];
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(OfertaSnapshot::class);
    }

    public function ultimoSnapshot(): HasOne
    {
        return $this->hasOne(OfertaSnapshot::class)->latestOfMany('fecha');
    }
}
