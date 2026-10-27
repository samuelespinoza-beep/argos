<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Corrida extends Model
{
    protected $table = 'corridas';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'iniciada_at' => 'datetime',
            'terminada_at' => 'datetime',
        ];
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(OfertaSnapshot::class);
    }
}
