<?php

namespace App\Mail;

use App\Models\Usuario;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ForgotPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public Usuario $usuario;
    public string $codigo;

    /**
     * @param  Usuario  $usuario
     * @param  string  $codigo  Codigo de recuperacion en claro (sin hashear).
     */
    public function __construct(Usuario $usuario, string $codigo)
    {
        $this->usuario = $usuario;
        $this->codigo = $codigo;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Codigo de verificacion',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.forgotpassword',
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
