<?php

namespace App\Services;

use App\Models\Corrida;
use App\Models\Oferta;
use App\Models\OfertaSnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * La "foto" de un sitio en una fecha: sus ofertas con los cambios respecto a una
 * foto anterior (vendidos, ventas estimadas, precio, nuevas y retiradas).
 */
class FotoOfertas
{
    /** De la mas reciente a la mas antigua. */
    public function fechas(string $sitio): Collection
    {
        return OfertaSnapshot::query()
            ->whereIn('oferta_id', Oferta::where('sitio', $sitio)->select('id'))
            ->distinct()->orderByDesc('fecha')->pluck('fecha')
            ->map(fn ($f) => $f->toDateString());
    }

    /**
     * @param  string|null  $fecha   Foto a mostrar (por defecto la mas reciente)
     * @param  string|null  $contra  Foto con la que comparar (por defecto la anterior a $fecha)
     */
    public function armar(string $sitio, ?string $fecha = null, ?string $contra = null): array
    {
        $fechas = $this->fechas($sitio);
        if ($fechas->isEmpty()) {
            throw new InvalidArgumentException("No hay fotos de \"{$sitio}\". Scrapea o importa ese sitio primero.");
        }

        $fecha = $fecha ?: $fechas->first();
        $contra = $contra ?: $fechas->first(fn ($f) => $f < $fecha);

        foreach (array_filter([$fecha, $contra]) as $f) {
            if (!$fechas->contains($f)) {
                throw new InvalidArgumentException("No hay foto de {$sitio} del {$f}. Disponibles: " . $fechas->implode(', '));
            }
        }

        return [
            'sitio' => $sitio,
            'dia' => $fecha,
            'dia_ant' => $contra,
            'fecha' => $this->capturadoEl($sitio, $fecha),
            'fecha_ant' => $contra ? $this->capturadoEl($sitio, $contra) : null,
            'desde' => $this->desde($fecha, $contra),
            'ofertas' => $this->ofertas($sitio, $fecha, $contra),
            'retiradas' => $contra ? $this->retiradas($sitio, $fecha, $contra) : [],
        ];
    }

    private function capturadoEl(string $sitio, string $fecha): ?string
    {
        $corridaId = OfertaSnapshot::query()
            ->whereIn('oferta_id', Oferta::where('sitio', $sitio)->select('id'))
            ->whereDate('fecha', $fecha)
            ->max('corrida_id');

        return Corrida::find($corridaId)?->iniciada_at?->toIso8601ZuluString() ?? "{$fecha}T00:00:00Z";
    }

    private function ofertas(string $sitio, string $fecha, ?string $contra): array
    {
        $filas = DB::select(<<<'SQL'
            SELECT o.external_id, o.titulo, o.marca, o.categoria, o.subcategoria, o.tipo, o.url, o.imagen,
                   s.precio_oferta, s.precio_normal, s.descuento_pct, s.vendidos, s.valoraciones, s.estrellas,
                   s.stock_maximo, s.estado, s.fecha_termino, s.extra,
                   p.oferta_id IS NOT NULL AS habia, p.vendidos AS vendidos_ant, p.precio_oferta AS precio_ant
            FROM ofertas o
            JOIN oferta_snapshots s ON s.oferta_id = o.id AND s.fecha = :fecha
            LEFT JOIN oferta_snapshots p ON p.oferta_id = o.id AND p.fecha = :contra
            WHERE o.sitio = :sitio
            ORDER BY o.id
            SQL, ['sitio' => $sitio, 'fecha' => $fecha, 'contra' => $contra]);

        return array_map(function ($f) use ($contra) {
            $extra = json_decode($f->extra ?? 'null', true) ?: [];
            $precio = (float) $f->precio_oferta;
            $normal = $f->precio_normal !== null ? (float) $f->precio_normal : null;
            $precioAnt = $f->precio_ant !== null ? (float) $f->precio_ant : null;
            $delta = $f->habia && $f->vendidos !== null && $f->vendidos_ant !== null ? $f->vendidos - $f->vendidos_ant : null;

            return [
                'id' => $f->external_id,
                'titulo' => $f->titulo,
                'marca' => $f->marca ?? '',
                // Una oferta puede estar en varias categorias (Cuponatic).
                'categoria' => implode(' | ', $extra['categorias'] ?? [$f->categoria ?? 'sin categoria']),
                'subcategoria' => $f->subcategoria,
                'tipo' => $f->tipo,
                'url' => $f->url,
                'imagen' => $f->imagen,
                'precio_oferta' => $precio,
                'precio_normal' => $normal,
                'descuento_pct' => $f->descuento_pct,
                'ahorro' => $normal ? round($normal - $precio, 2) : null,
                'valoraciones' => $f->valoraciones,
                'estrellas_5' => $f->estrellas !== null ? (float) $f->estrellas : null,
                'valoraciones_de' => $f->valoraciones ? 'producto' : null,
                'vendidos' => $f->vendidos,
                'stock_maximo' => $f->stock_maximo,
                'estado' => $f->estado,
                'vencido' => $extra['vencido'] ?? false,
                'fecha_termino' => $f->fecha_termino ? "{$f->fecha_termino} 23:59:00" : null,
                'nueva' => $contra && !$f->habia,
                'vendidos_ant' => $f->vendidos_ant,
                'delta_vend' => $delta,
                // Ventas brutas estimadas del periodo: cupones vendidos x precio de la oferta.
                'ingreso' => $delta > 0 ? round($delta * $precio, 2) : null,
                'precio_ant' => $precioAnt,
                'delta_precio' => $precioAnt !== null && abs($precio - $precioAnt) > 0.001 ? round($precio - $precioAnt, 2) : null,
            ];
        }, $filas);
    }

    private function retiradas(string $sitio, string $fecha, string $contra): array
    {
        $filas = DB::select(<<<'SQL'
            SELECT o.external_id AS id, o.titulo, o.marca, o.categoria, o.url, p.precio_oferta, p.vendidos
            FROM ofertas o
            JOIN oferta_snapshots p ON p.oferta_id = o.id AND p.fecha = :contra
            LEFT JOIN oferta_snapshots s ON s.oferta_id = o.id AND s.fecha = :fecha
            WHERE o.sitio = :sitio AND s.id IS NULL
            ORDER BY p.vendidos DESC NULLS LAST, o.id
            SQL, ['sitio' => $sitio, 'fecha' => $fecha, 'contra' => $contra]);

        return array_map(fn ($f) => [...(array) $f, 'precio_oferta' => (float) $f->precio_oferta], $filas);
    }

    private function desde(string $fecha, ?string $contra): string
    {
        if (!$contra) {
            return 'desde la foto anterior';
        }

        return Carbon::parse($contra)->addDay()->toDateString() === $fecha
            ? 'desde ayer'
            : 'desde el ' . Carbon::parse($contra)->format('d/m');
    }
}
