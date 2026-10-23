<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Aviso genérico de cambio de estado del servicio técnico (RF-033). */
class ServicioNotificacionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $numero,
        public string $asunto,
        public string $titulo,
        public string $mensaje,
        public string $url,
        public ?string $nombre = null,
        // Cliente sin cuenta (mostrador): el botón invita a registrarse con este correo.
        public bool $invitarRegistro = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->asunto);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.servicio-notificacion');
    }

    public function attachments(): array
    {
        return [];
    }
}
