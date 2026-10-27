<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfertaSnapshot extends Model
{
    protected $table = 'oferta_snapshots';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'fecha_termino' => 'date',
            'precio_oferta' => 'decimal:2',
            'precio_normal' => 'decimal:2',
            'estrellas' => 'decimal:1',
            'extra' => 'array',
        ];
    }

    public function oferta(): BelongsTo
    {
        return $this->belongsTo(Oferta::class);
    }

    public function corrida(): BelongsTo
    {
        return $this->belongsTo(Corrida::class);
    }
}
