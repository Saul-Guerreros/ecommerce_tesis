<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Factura electronica de una venta, con el KuDE (PDF) y el XML firmado
 * adjuntos, tomados de los archivos que ESTE sistema guardo al aprobarla.
 *
 * Se usa para REENVIAR: el primer envio lo hace la API al aprobar (guia,
 * capitulo 10) y no puede repetirlo. Sale por la cuenta de correo del
 * sistema (MAIL_* del .env), no por la que se cargo en la API.
 *
 * Los adjuntos se leen del disco privado ('local'): son rutas relativas
 * guardadas en venta_fe, nunca un enlace publico.
 */
class FacturaElectronicaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $numero,       // 001-001-0000006
        public string $empresa,      // razon social del emisor (snapshot de la factura)
        public string $cdc,
        public ?string $nombre,      // a quien va dirigido; puede no haber
        public string $rutaXml,
        public ?string $rutaPdf,     // el KuDE puede faltar; el XML es el documento valido
        public ?string $qr,
        public bool $esPrueba,       // ambiente simulado: sin valor fiscal
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Factura electrónica {$this->numero} · {$this->empresa}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.factura-electronica');
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        $adjuntos = [
            Attachment::fromStorageDisk('local', $this->rutaXml)
                ->as("Factura_{$this->numero}.xml")
                ->withMime('application/xml'),
        ];

        if ($this->rutaPdf) {
            $adjuntos[] = Attachment::fromStorageDisk('local', $this->rutaPdf)
                ->as("Factura_{$this->numero}.pdf")
                ->withMime('application/pdf');
        }

        return $adjuntos;
    }
}