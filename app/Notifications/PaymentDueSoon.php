<?php

namespace App\Notifications;

use App\Models\Commitment;
use App\Notifications\Channels\WhatsAppMessage;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentDueSoon extends Notification implements WhatsAppMessage
{
    public function __construct(
        public Commitment $commitment,
        public CarbonImmutable $dueDate,
        public int $daysLeft,
    ) {}

    public function via(object $notifiable): array
    {
        return config('fincontrol.mail_notifications') ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->commitment->name} vence en {$this->daysLeft} días")
            ->greeting('Hola '.$notifiable->name)
            ->line($this->text())
            ->action('Registrar el pago', route('commitments.show', $this->commitment))
            ->line('Si ya pagaste, regístralo en FinControl para que no te volvamos a avisar.');
    }

    public function toWhatsApp(object $notifiable): string
    {
        return $this->text();
    }

    public function text(): string
    {
        return sprintf(
            '%s (%s) vence el %s.',
            $this->commitment->name,
            Money::format($this->commitment->monthly_amount),
            $this->dueDate->format('d/m/Y'),
        );
    }
}
