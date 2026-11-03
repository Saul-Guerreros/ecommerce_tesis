<?php
// app/Services/Inventario/NotificadorInventario.php
namespace App\Services\Inventario;

use App\Mail\InventarioAlertaMail;
use App\Models\Inventario\AlertaStock;
use App\Models\Usuario;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Avisa por correo a los responsables cuando aparecen alertas URGENTES
 * (agotado o crítico), para que no dependan de entrar al panel.
 *
 * Anti-spam: cada alerta se avisa una sola vez (ast_ntipo guarda el tipo
 * avisado). Solo se vuelve a avisar si empeora de crítico a agotado.
 * Destinatarios: usuarios activos con rol Administrador o con el permiso
 * inventario-inteligente.gestionar.
 */
class NotificadorInventario
{
    public const PERMISO = 'inventario-inteligente.gestionar';
    private const NOMBRE_PRODUCTO = 'COALESCE(pw.pub_titulo, LEFT(pr.prd_descri, 120))';

    /** @return int Cantidad de alertas avisadas (0 si no había nada nuevo). */
    public function avisarUrgentes(): int
    {
        $nuevas = $this->alertasSinAvisar();
        if ($nuevas->isEmpty()) {
            return 0;
        }

        $destinatarios = $this->destinatarios();
        if ($destinatarios->isEmpty()) {
            Log::warning('Inventario: hay alertas urgentes pero ningún usuario para avisar.');
            return 0;
        }

        $totales = AlertaStock::pendientes()
            ->selectRaw('ast_tipo, COUNT(*) AS n')->groupBy('ast_tipo')->pluck('n', 'ast_tipo')->all();

        $enviados = 0;
        foreach ($destinatarios as $correo) {
            try {
                Mail::to($correo)->send(new InventarioAlertaMail($nuevas->all(), $totales, $this->urlPanel()));
                $enviados++;
            } catch (\Throwable $e) {
                Log::error("Inventario: no se pudo enviar el aviso a {$correo}", ['error' => $e->getMessage()]);
            }
        }

        // Solo se marcan como avisadas si al menos un correo salió.
        if ($enviados > 0) {
            foreach ($nuevas as $a) {
                AlertaStock::whereKey($a->id)->update(['ast_ntipo' => $a->tipo]);
            }
        }

        return $enviados > 0 ? $nuevas->count() : 0;
    }

    /** Agotados y críticos nunca avisados, o críticos que pasaron a agotado. */
    private function alertasSinAvisar(): Collection
    {
        return DB::table('alerta_stock as a')
            ->join('producto as pr', 'pr.prd_id', '=', 'a.ast_prdid')
            ->leftJoin('publicacion_web as pw', 'pw.pub_prdid', '=', 'pr.prd_id')
            ->join('deposito as d', 'd.dpo_id', '=', 'a.ast_depid')
            ->where('a.ast_estado', AlertaStock::ESTADO_PENDIENTE)
            ->whereIn('a.ast_tipo', [AlertaStock::TIPO_AGOTADO, AlertaStock::TIPO_CRITICO])
            ->where(fn ($q) => $q->whereNull('a.ast_ntipo')
                ->orWhere(fn ($w) => $w->where('a.ast_ntipo', AlertaStock::TIPO_CRITICO)
                                       ->where('a.ast_tipo', AlertaStock::TIPO_AGOTADO)))
            ->selectRaw('a.ast_id AS id, a.ast_tipo AS tipo, ' . self::NOMBRE_PRODUCTO . ' AS producto,
                         pr.prd_codinv AS codigo, d.dpo_nombre AS deposito, a.ast_dispon AS disponible,
                         a.ast_cobert AS cobertura_dias, a.ast_sugeri AS sugerido')
            ->orderByRaw("CASE a.ast_tipo WHEN 'agotado' THEN 1 ELSE 2 END")
            ->orderBy('a.ast_cobert')
            ->get();
    }

    /** @return Collection<int, string> correos (usu_login) */
    private function destinatarios(): Collection
    {
        return Usuario::query()
            ->where('usu_estado', Usuario::ESTADO_ACTIVO)
            ->whereHas('rol', fn ($r) => $r->where('rol_estado', 1)->where(fn ($q) => $q
                ->where('rol_descri', 'Administrador')
                ->orWhereHas('permisos', fn ($p) => $p->where('prm_descri', self::PERMISO))))
            ->pluck('usu_login')
            ->filter(fn ($c) => filter_var($c, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();
    }

    private function urlPanel(): string
    {
        return rtrim((string) config('app.admin_url'), '/') . '/inventario/inteligente';
    }
}