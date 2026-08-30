<?php

namespace App\Mail;

use App\Models\Usuario;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class verifiedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Usuario $usuario;
    public string $codigo;

    /**
     * @param  Usuario  $usuario
     * @param  string  $codigo  Token de verificacion en claro (sin hashear).
     */
    public function __construct(Usuario $usuario, string $codigo)
    {
        $this->usuario = $usuario;
        $this->codigo = $codigo;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verificacion de correo electronico',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.verifiedmail',
            with: [
                'codigo' => $this->codigo,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
