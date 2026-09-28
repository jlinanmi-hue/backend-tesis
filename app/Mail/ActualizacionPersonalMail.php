<?php

namespace App\Mail;

use App\Models\Empleado;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ActualizacionPersonalMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @param Empleado $empleado
     * @param array $cambios Array of changes with ['campo' => ..., 'anterior' => ..., 'nuevo' => ...]
     */
    public function __construct(
        public Empleado $empleado,
        public array $cambios
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Notificación de Actualización de Datos de Personal - I.E.P Miguel Ángel Buonarroti',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.actualizacion',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
