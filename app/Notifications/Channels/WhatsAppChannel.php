<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;

/**
 * Canal de WhatsApp: interfaz vacía para una versión futura.
 * Aún no se usa en ningún via().
 */
class WhatsAppChannel
{
    public function send(object $notifiable, Notification&WhatsAppMessage $notification): void
    {
        // Pendiente: integrar un proveedor (p. ej. la API de WhatsApp Business).
    }
}
