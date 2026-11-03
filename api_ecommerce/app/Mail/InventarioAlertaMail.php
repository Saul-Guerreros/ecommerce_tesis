<?php
// app/Mail/InventarioAlertaMail.php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Aviso de alertas urgentes de reposición (agotado / crítico). */
class InventarioAlertaMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array $alertas Filas: tipo, producto, codigo, deposito, disponible, cobertura_dias, sugerido.
     * @param array $totales Pendientes por tipo: ['agotado' => n, 'critico' => n, ...].
     */
    public function __construct(
        public array $alertas,
        public array $totales,
        public string $url,
    ) {
    }

    public function envelope(): Envelope
    {
        $agotados = collect($this->alertas)->where('tipo', 'agotado')->count();
        $criticos = collect($this->alertas)->where('tipo', 'critico')->count();

        $partes = array_filter([
            $agotados ? "{$agotados} agotado(s)" : null,
            $criticos ? "{$criticos} crítico(s)" : null,
        ]);

        return new Envelope(subject: '⚠ Reposición de stock: ' . implode(' y ', $partes));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.inventario-alerta');
    }

    public function attachments(): array
    {
        return [];
    }
}