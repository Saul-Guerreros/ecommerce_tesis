<?php

namespace App\Services\Catalogo;

use App\Models\Catalogo\CategoriaAtributo;
use App\Models\Product\Atributo;
use App\Models\Product\EspecificacionProducto;
use App\Models\Producto;
use App\Models\PublicacionWeb;
use Illuminate\Support\Collection;

/**
 * Service Layer de la Ficha Técnica de una publicación.
 *
 * Une la PLANTILLA de la subcategoría (categoria_atributo: qué datos se
 * piden) con los VALORES de la publicación (especificacion_producto).
 * Lo usan el formulario del panel, la validación al publicar, la ficha
 * de la tienda y, más adelante, el buscador y el LLM: la regla vive en
 * un solo lugar.
 *
 * HERENCIA DE VARIANTES (prd_padre_id):
 *   Una variante guarda SOLO sus filas propias (el atributo que la
 *   diferencia, ej. Color = Azul). Todo lo demás se lee en tiempo real
 *   de la publicación del producto base. Regla de fusión: por cada
 *   atributo, si la variante tiene valor propio, reemplaza al del base.
 *   Así, corregir la RAM del base corrige también todas sus variantes.
 *
 * Formato de una ficha (lo que manda el panel):
 *   [ ['atb_id' => 5, 'valor_texto' => null, 'valor_numero' => 16, 'propiedad_ids' => []], ... ]
 *
 * Cómo se guarda cada tipo de atributo:
 *   1 Texto         -> 1 fila con esp_valibr
 *   2 Número        -> 1 fila con esp_valnum
 *   3 Seleccionable -> 1 fila con esp_prpid
 *   4 Múltiple      -> 1 fila por cada propiedad elegida
 */
class FichaTecnicaService
{
    public const TEXTO = 1;
    public const NUMERO = 2;
    public const SELECCIONABLE = 3;
    public const MULTIPLE = 4;

    public const PROPIO = 'propio';
    public const HEREDADO = 'heredado';

    // =====================================================================
    // Lectura
    // =====================================================================

    /** Plantilla de la subcategoría, en orden, con las opciones de cada atributo. */
    public function plantilla(int $categoriaId): Collection
    {
        return CategoriaAtributo::where('cta_catid', $categoriaId)
            ->whereHas('atributo')
            ->with(['atributo.propiedades' => fn ($q) => $q->orderBy('prp_nombre')])
            ->orderBy('cta_orden')
            ->orderBy('cta_id')
            ->get();
    }

    /** Valores guardados en UNA publicación (sin herencia), indexados por atb_id. */
    public function fichaGuardada(PublicacionWeb $publicacion): Collection
    {
        return EspecificacionProducto::where('esp_pubid', $publicacion->pub_id)
            ->get()
            ->groupBy('esp_atbid')
            ->map(fn (Collection $filas, $atbId) => [
                'atb_id'        => (int) $atbId,
                'valor_texto'   => $filas->first()->esp_valibr,
                'valor_numero'  => $filas->first()->esp_valnum !== null ? (float) $filas->first()->esp_valnum : null,
                'propiedad_ids' => $filas->pluck('esp_prpid')->filter()->map(fn ($id) => (int) $id)->values()->all(),
            ]);
    }

    /** Publicación del producto base, o null si no es variante o el base no está publicado. */
    public function publicacionBase(Producto $producto): ?PublicacionWeb
    {
        return $producto->prd_padre_id ? $producto->padre?->publicacionWeb : null;
    }

    /**
     * Ficha EFECTIVA: base + propios de la variante (los propios ganan).
     * Cada valor lleva 'origen' = propio | heredado.
     * Para un producto que no es variante, todo es propio.
     */
    public function valoresEfectivos(Producto $producto, ?PublicacionWeb $publicacion): Collection
    {
        $propios = $publicacion
            ? $this->fichaGuardada($publicacion)->map(fn ($v) => $v + ['origen' => self::PROPIO])
            : collect();

        $base = $this->publicacionBase($producto);
        if (! $base) {
            return $propios;
        }

        $heredados = $this->fichaGuardada($base)->map(fn ($v) => $v + ['origen' => self::HEREDADO]);

        // replace() conserva las claves (atb_id): lo propio pisa a lo heredado.
        return $heredados->replace($propios);
    }

    /**
     * Campos del formulario del panel: atributos de la plantilla + (en
     * variantes) los atributos propios que no están en la plantilla.
     * Cada campo dice si es editable: en una variante solo lo propio.
     */
    public function campos(int $categoriaId, Producto $producto): Collection
    {
        $publicacion = $producto->publicacionWeb;
        $esVariante = (bool) $producto->prd_padre_id;
        $valores = $this->valoresEfectivos($producto, $publicacion);

        $plantilla = $this->plantilla($categoriaId);
        $enPlantilla = $plantilla->pluck('cta_atbid')->all();

        $campos = $plantilla->map(fn (CategoriaAtributo $item) => $this->armarCampo(
            $item->atributo,
            $valores->get($item->cta_atbid),
            $esVariante,
            obligatorio: $item->cta_obliga === 1,
            orden: $item->cta_orden,
            enPlantilla: true
        ));

        // Atributo diferenciador de la variante que no figura en la plantilla
        // (ej. "Color" en Notebooks): se muestra igual, al final.
        $extras = $valores
            ->filter(fn ($v, $atbId) => $v['origen'] === self::PROPIO && ! in_array($atbId, $enPlantilla))
            ->keys();

        if ($extras->isNotEmpty()) {
            Atributo::with(['propiedades' => fn ($q) => $q->orderBy('prp_nombre')])
                ->whereIn('atb_id', $extras)
                ->orderBy('atb_nombre')
                ->get()
                ->each(fn (Atributo $a) => $campos->push($this->armarCampo(
                    $a,
                    $valores->get($a->atb_id),
                    $esVariante,
                    obligatorio: true,
                    orden: 999,
                    enPlantilla: false
                )));
        }

        return $campos->values();
    }

    /**
     * Ficha lista para mostrar en la tienda (y para indexar en el
     * buscador): [{atributo, valor}], en el orden de la plantilla.
     */
    public function fichaParaMostrar(PublicacionWeb $publicacion): array
    {
        $valores = $this->valoresEfectivos($publicacion->producto, $publicacion);
        if ($valores->isEmpty()) {
            return [];
        }

        $atributos = Atributo::with('propiedades')->whereIn('atb_id', $valores->keys())->get()->keyBy('atb_id');
        $orden = $publicacion->pub_catid
            ? CategoriaAtributo::where('cta_catid', $publicacion->pub_catid)->pluck('cta_orden', 'cta_atbid')
            : collect();

        return $valores
            ->filter(fn ($v, $atbId) => $atributos->has($atbId))
            ->sortBy(fn ($v, $atbId) => [$orden->get($atbId, 999), $atributos[$atbId]->atb_nombre])
            ->map(fn ($v, $atbId) => [
                'atributo' => $atributos[$atbId]->atb_nombre,
                'valor'    => $this->valorComoTexto($atributos[$atbId], $v),
            ])
            ->filter(fn ($fila) => $fila['valor'] !== null && $fila['valor'] !== '')
            ->values()
            ->all();
    }

    // =====================================================================
    // Validación
    // =====================================================================

    /**
     * Valida la ficha de un producto BASE contra la plantilla. Devuelve
     * errores con clave "ficha.{atb_id}" para mostrarlos junto a cada campo.
     *
     * @param bool $exigirObligatorios true al PUBLICAR (pub_estado = 1);
     *             en Borrador se permite guardarla incompleta.
     */
    public function validar(int $categoriaId, array $ficha, bool $exigirObligatorios): array
    {
        $plantilla = $this->plantilla($categoriaId)->keyBy('cta_atbid');
        $valores = $this->indexar($ficha);
        $errores = [];

        foreach ($plantilla as $atbId => $item) {
            $atributo = $item->atributo;
            $valor = $valores->get($atbId);
            $clave = "ficha.{$atbId}";

            if (! $this->tieneValor($atributo->atb_tipo, $valor)) {
                if ($exigirObligatorios && $item->cta_obliga === 1) {
                    $errores[$clave] = "{$atributo->atb_nombre} es obligatorio para publicar el producto.";
                }
                continue;
            }

            if ($error = $this->validarValor($atributo, $valor)) {
                $errores[$clave] = $error;
            }
        }

        return $errores;
    }

    /**
     * Valida la ficha de una VARIANTE:
     *  - sus atributos propios no pueden quedar vacíos (es lo que la
     *    diferencia del base) y deben tener opciones válidas;
     *  - al publicar, los obligatorios heredados deben estar completos
     *    en el producto base (se corrigen ahí, no en la variante).
     */
    public function validarVariante(Producto $variante, int $categoriaId, array $ficha, bool $exigirObligatorios): array
    {
        $publicacion = $variante->publicacionWeb;
        $propiosIds = $publicacion ? $this->fichaGuardada($publicacion)->keys()->all() : [];
        $valores = $this->indexar($ficha);
        $errores = [];

        $atributos = Atributo::with('propiedades')->whereIn('atb_id', $propiosIds)->get();

        foreach ($atributos as $atributo) {
            $valor = $valores->get($atributo->atb_id);
            $clave = "ficha.{$atributo->atb_id}";

            if (! $this->tieneValor($atributo->atb_tipo, $valor)) {
                $errores[$clave] = "{$atributo->atb_nombre} es lo que diferencia a esta variante: no puede quedar vacío.";
                continue;
            }

            if ($error = $this->validarValor($atributo, $valor)) {
                $errores[$clave] = $error;
            }
        }

        if ($exigirObligatorios) {
            $efectivos = $this->valoresEfectivos($variante, $publicacion);

            foreach ($this->plantilla($categoriaId) as $item) {
                if ($item->cta_obliga !== 1 || $efectivos->has($item->cta_atbid)) {
                    continue;
                }
                $errores["ficha.{$item->cta_atbid}"] =
                    "{$item->atributo->atb_nombre} falta en la ficha del producto base: completalo ahí para publicar esta variante.";
            }
        }

        return $errores;
    }

    // =====================================================================
    // Escritura (llamar dentro de la transacción del guardado)
    // =====================================================================

    /**
     * Producto BASE: reemplaza los valores de los atributos de la plantilla.
     * Los que no están en la plantilla no se tocan.
     */
    public function sincronizar(PublicacionWeb $publicacion, int $categoriaId, array $ficha): void
    {
        $atributos = $this->plantilla($categoriaId)->mapWithKeys(fn ($i) => [$i->cta_atbid => $i->atributo]);

        $this->reemplazar($publicacion, $atributos, $this->indexar($ficha));
    }

    /**
     * VARIANTE: solo se reescriben sus atributos propios. Lo heredado no
     * se copia nunca (se lee del base en tiempo real).
     */
    public function sincronizarVariante(PublicacionWeb $publicacion, array $ficha): void
    {
        $propiosIds = $this->fichaGuardada($publicacion)->keys();
        $atributos = Atributo::whereIn('atb_id', $propiosIds)->get()->keyBy('atb_id');

        $this->reemplazar($publicacion, $atributos, $this->indexar($ficha));
    }

    // =====================================================================
    // Diferenciador de variantes (alta desde el formulario de Producto)
    // =====================================================================

    /**
     * Convierte una fila del formulario de Producto (variantes.*) al
     * formato de ficha: así el alta de variantes y la Ficha Técnica
     * comparten las MISMAS reglas de validación y de guardado.
     * prp_id (Seleccionable) y propiedad_ids (Múltiple) se unifican.
     */
    public function valorDesdeFila(array $fila): array
    {
        $propiedadIds = array_merge(
            (array) ($fila['propiedad_ids'] ?? []),
            isset($fila['prp_id']) ? [$fila['prp_id']] : []
        );

        return [
            'atb_id'        => (int) ($fila['atb_id'] ?? 0),
            'valor_texto'   => isset($fila['valor_texto']) ? (string) $fila['valor_texto'] : null,
            'valor_numero'  => $fila['valor_numero'] ?? null,
            'propiedad_ids' => array_values(array_unique(array_map('intval', array_filter($propiedadIds)))),
        ];
    }

    /** Mensaje de error del valor que diferencia a la variante, o null si es válido. */
    public function validarDiferenciador(Atributo $atributo, array $valor): ?string
    {
        if (! $this->tieneValor($atributo->atb_tipo, $valor)) {
            return "Indicá el valor de {$atributo->atb_nombre} para esta variante.";
        }

        return $this->validarValor($atributo, $valor);
    }

    /**
     * Guarda el atributo diferenciador como fila PROPIA de la variante.
     * Queda igual que si se hubiera cargado desde la Ficha Técnica.
     */
    public function guardarDiferenciador(PublicacionWeb $publicacion, Atributo $atributo, array $valor): void
    {
        $this->reemplazar(
            $publicacion,
            collect([$atributo->atb_id => $atributo]),
            collect([$atributo->atb_id => $valor])
        );
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    /** @param Collection<int, Atributo> $atributos indexados por atb_id */
    private function reemplazar(PublicacionWeb $publicacion, Collection $atributos, Collection $valores): void
    {
        EspecificacionProducto::where('esp_pubid', $publicacion->pub_id)
            ->whereIn('esp_atbid', $atributos->keys())
            ->delete();

        foreach ($atributos as $atbId => $atributo) {
            $valor = $valores->get($atbId);

            if (! $this->tieneValor($atributo->atb_tipo, $valor)) {
                continue;
            }

            foreach ($this->filasParaGuardar((int) $atributo->atb_tipo, $valor) as $fila) {
                EspecificacionProducto::create(array_merge([
                    'esp_pubid' => $publicacion->pub_id,
                    'esp_atbid' => $atbId,
                ], $fila));
            }
        }
    }

    private function armarCampo(
        Atributo $atributo,
        ?array $valor,
        bool $esVariante,
        bool $obligatorio,
        int $orden,
        bool $enPlantilla
    ): array {
        $origen = $valor['origen'] ?? ($esVariante ? self::HEREDADO : self::PROPIO);

        return [
            'atributo'      => $atributo,
            'obligatorio'   => $obligatorio,
            'orden'         => $orden,
            'en_plantilla'  => $enPlantilla,
            'origen'        => $origen,
            // Base: todo editable. Variante: solo su atributo diferenciador.
            'editable'      => ! $esVariante || $origen === self::PROPIO,
            'valor_texto'   => $valor['valor_texto'] ?? null,
            'valor_numero'  => $valor['valor_numero'] ?? null,
            'propiedad_ids' => $valor['propiedad_ids'] ?? [],
        ];
    }

    /** Lista recibida -> colección indexada por atb_id (ignora entradas sin atb_id). */
    private function indexar(array $ficha): Collection
    {
        return collect($ficha)
            ->filter(fn ($v) => is_array($v) && isset($v['atb_id']))
            ->keyBy(fn ($v) => (int) $v['atb_id']);
    }

    private function tieneValor($tipo, ?array $valor): bool
    {
        if (! $valor) {
            return false;
        }

        return match ((int) $tipo) {
            self::TEXTO  => trim((string) ($valor['valor_texto'] ?? '')) !== '',
            self::NUMERO => isset($valor['valor_numero']) && $valor['valor_numero'] !== '',
            default      => ! empty($valor['propiedad_ids']),
        };
    }

    private function validarValor(Atributo $atributo, array $valor): ?string
    {
        $nombre = $atributo->atb_nombre;

        switch ((int) $atributo->atb_tipo) {
            case self::TEXTO:
                return mb_strlen(trim($valor['valor_texto'])) > 150
                    ? "{$nombre} no puede superar los 150 caracteres."
                    : null;

            case self::NUMERO:
                $numero = $valor['valor_numero'];
                if (! is_numeric($numero)) {
                    return "{$nombre} debe ser un número.";
                }
                // esp_valnum es decimal(12,2)
                return ($numero < 0 || $numero > 9999999999.99)
                    ? "{$nombre} debe ser un número positivo válido."
                    : null;

            default:
                $ids = array_map('intval', (array) $valor['propiedad_ids']);

                if ((int) $atributo->atb_tipo === self::SELECCIONABLE && count($ids) > 1) {
                    return "{$nombre} admite una sola opción.";
                }

                $validas = $atributo->propiedades->pluck('prp_id')->all();

                return array_diff($ids, $validas)
                    ? "Una o más opciones de {$nombre} no son válidas."
                    : null;
        }
    }

    /** Columnas de especificacion_producto según el tipo de atributo. */
    private function filasParaGuardar(int $tipo, array $valor): array
    {
        return match ($tipo) {
            self::TEXTO  => [['esp_valibr' => trim($valor['valor_texto']), 'esp_valnum' => null, 'esp_prpid' => null]],
            self::NUMERO => [['esp_valibr' => null, 'esp_valnum' => $valor['valor_numero'], 'esp_prpid' => null]],
            default      => collect($valor['propiedad_ids'])
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->map(fn ($id) => ['esp_valibr' => null, 'esp_valnum' => null, 'esp_prpid' => $id])
                ->values()
                ->all(),
        };
    }

    /** Valor legible: "16 GB", "Intel Core i7", "USB, Bluetooth". */
    private function valorComoTexto(Atributo $atributo, array $valor): ?string
    {
        $tipo = (int) $atributo->atb_tipo;

        if ($tipo === self::TEXTO && $valor['valor_texto'] !== null) {
            return $valor['valor_texto'];
        }

        if ($tipo === self::NUMERO && $valor['valor_numero'] !== null) {
            $numero = rtrim(rtrim(number_format((float) $valor['valor_numero'], 2, '.', ''), '0'), '.');
            return trim($numero . ' ' . ($atributo->atb_unidad ?? ''));
        }

        // Seleccionables, y también filas viejas de Texto/Número que la
        // pantalla anterior guardaba solo con propiedad.
        return $atributo->propiedades
            ->whereIn('prp_id', $valor['propiedad_ids'])
            ->pluck('prp_nombre')
            ->implode(', ') ?: null;
    }
}