<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Empareja las ofertas de Cuponidad con las de un competidor en la misma fecha.
 *
 * Puntaje de un par (misma marca) = parecido del titulo (Jaccard de palabras, 0..1)
 *                                 + 0.5 si el precio es igual (±1%) o 0.25 si difiere menos de 15%.
 * Cada oferta se empareja con una sola del otro sitio (la de mayor puntaje).
 */
class CruceOfertas
{
    public const PROPIO = 'cuponidad';

    private const MINIMO = 0.6;

    /** Entre MINIMO y ALTA la coincidencia es "media": probablemente la misma, hay que revisarla. */
    private const ALTA = 0.7;

    private const VACIAS = ['de', 'la', 'el', 'en', 'y', 'o', 'para', 'con', 'por', 'del', 'los', 'las', 'a', '1', '2',
        'un', 'una', 'x', 'mas', 'todos', 'dias', 'lunes', 'domingo'];

    public function __construct(private FotoOfertas $foto) {}

    public function armar(string $competidor, string $fecha): array
    {
        $propio = $this->foto->armar(self::PROPIO, $fecha);
        $otro = $this->foto->armar($competidor, $fecha);

        $pares = array_map(fn ($p) => [
            'confianza' => $p['puntaje'] >= self::ALTA ? 'alta' : 'media',
            'puntaje' => round($p['puntaje'], 2),
            'marca' => $p['a']['marca'],
            'categoria' => $p['a']['categoria'],
            self::PROPIO => $this->resumen($p['a']),
            $competidor => $this->resumen($p['b']),
        ], $this->emparejarConRetiradas($propio, $otro));

        return [
            'propio' => self::PROPIO,
            'competidor' => $competidor,
            'dia' => $fecha,
            'fecha' => $propio['fecha'],
            'fecha_ant' => $propio['fecha_ant'],
            'desde' => $propio['desde'],
            'ofertas' => [self::PROPIO => count($propio['ofertas']), $competidor => count($otro['ofertas'])],
            'pares' => $pares,
        ];
    }

    /**
     * Primero se emparejan las vigentes entre si, para que una retirada nunca le quite
     * la pareja a una vigente. Las vigentes que quedan solas se buscan entre las
     * retiradas del otro sitio ("la teniamos los dos, pero ellos la sacaron").
     */
    private function emparejarConRetiradas(array $propio, array $otro): array
    {
        $pares = $this->emparejar($propio['ofertas'], $otro['ofertas']);

        $usadasA = array_column(array_column($pares, 'a'), 'id');
        $usadasB = array_column(array_column($pares, 'b'), 'id');
        $solasA = array_filter($propio['ofertas'], fn ($o) => !in_array($o['id'], $usadasA, true));
        $solasB = array_filter($otro['ofertas'], fn ($o) => !in_array($o['id'], $usadasB, true));

        return [
            ...$pares,
            ...$this->emparejar($solasA, array_map($this->comoRetirada(...), $otro['retiradas'])),
            ...$this->emparejar(array_map($this->comoRetirada(...), $propio['retiradas']), $solasB),
        ];
    }

    private function comoRetirada(array $r): array
    {
        return [
            'id' => $r['id'], 'titulo' => $r['titulo'], 'marca' => $r['marca'], 'categoria' => $r['categoria'],
            'url' => $r['url'], 'imagen' => null, 'precio_oferta' => $r['precio_oferta'],
            'delta_vend' => null, 'ingreso' => null, 'nueva' => false, 'delta_precio' => null, 'retirada' => true,
        ];
    }

    /** @return array<int, array{puntaje: float, a: array, b: array}> */
    private function emparejar(array $propias, array $ajenas): array
    {
        $porMarca = [];
        foreach ($ajenas as $b) {
            if ($clave = $this->claveMarca($b['marca'])) {
                $porMarca[$clave][] = $b;
            }
        }

        $candidatos = [];
        foreach ($propias as $a) {
            $clave = $this->claveMarca($a['marca']);
            foreach ($clave ? $porMarca[$clave] ?? [] : [] as $b) {
                $puntaje = $this->puntaje($a, $b);
                if ($puntaje >= self::MINIMO) {
                    $candidatos[] = ['puntaje' => $puntaje, 'a' => $a, 'b' => $b];
                }
            }
        }

        // De mayor a menor puntaje, cada oferta se queda con su mejor pareja libre.
        usort($candidatos, fn ($x, $y) => $y['puntaje'] <=> $x['puntaje']);
        $usadasA = $usadasB = $pares = [];
        foreach ($candidatos as $c) {
            if (isset($usadasA[$c['a']['id']]) || isset($usadasB[$c['b']['id']])) {
                continue;
            }
            $usadasA[$c['a']['id']] = $usadasB[$c['b']['id']] = true;
            $pares[] = $c;
        }

        return $pares;
    }

    private function puntaje(array $a, array $b): float
    {
        $ta = $this->palabras($a['titulo'], $a['marca']);
        $tb = $this->palabras($b['titulo'], $b['marca']);
        $union = count(array_unique([...$ta, ...$tb]));
        $titulo = $union ? count(array_intersect($ta, $tb)) / $union : 0;

        $mayor = max($a['precio_oferta'], $b['precio_oferta']);
        $difPrecio = $mayor > 0 ? abs($a['precio_oferta'] - $b['precio_oferta']) / $mayor : 1;

        return $titulo + ($difPrecio < 0.01 ? 0.5 : ($difPrecio < 0.15 ? 0.25 : 0));
    }

    private function palabras(string $titulo, ?string $marca): array
    {
        $partir = fn (?string $s) => preg_split('/[^a-z0-9]+/', Str::lower(Str::ascii($s ?? '')), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(array_diff($partir($titulo), self::VACIAS, $partir($marca))));
    }

    private function claveMarca(?string $marca): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($marca ?? '')));
    }

    private function resumen(array $o): array
    {
        return [
            'id' => $o['id'],
            'titulo' => $o['titulo'],
            'url' => $o['url'],
            'imagen' => $o['imagen'],
            'precio' => $o['precio_oferta'],
            'vendidos' => $o['delta_vend'],
            'total' => $o['ingreso'],
            'nueva' => $o['nueva'],
            'cambio_precio' => $o['delta_precio'],
            'retirada' => $o['retirada'] ?? false,
        ];
    }
}
