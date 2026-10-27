<?php

namespace App\Notifications\Channels;

/**
 * Contrato para notificaciones que algún día saldrán por WhatsApp.
 */
interface WhatsAppMessage
{
    public function toWhatsApp(object $notifiable): string;
}
