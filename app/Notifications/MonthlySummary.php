<?php

namespace App\Notifications;

use App\Notifications\Channels\WhatsAppMessage;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MonthlySummary extends Notification implements WhatsAppMessage
{
    /**
     * @param  array  $summary  Ver MonthlySummaryBuilder::for()
     */
    public function __construct(public array $summary) {}

    public function via(object $notifiable): array
    {
        return config('fincontrol.mail_notifications') ? ['mail'] : [];
    }

    public function monthName(): string
    {
        $period = $this->summary['period'];

        return Carbon::create($period->year, $period->month, 1)->locale('es')->translatedFormat('F Y');
    }

    public function toMail(object $notifiable): MailMessage
    {
        $s = $this->summary;

        $mail = (new MailMessage)
            ->subject('Tu resumen de '.$this->monthName())
            ->greeting('Hola '.$notifiable->name)
            ->line('Así cerró '.$this->monthName().':')
            ->line('Ingresos: '.Money::format($s['income']))
            ->line('Gastado en categorías: '.Money::format($s['spent']))
            ->line('Gastos fijos: '.Money::format($s['paid']))
            ->line('La deuda total bajó: '.Money::format($s['debt_reduction']))
            ->line('Ahorro logrado: '.Money::format($s['saved']).' (meta '.Money::format($s['savings_target']).')');

        if ($s['over_envelopes']) {
            $mail->line('Categorías excedidas: '.collect($s['over_envelopes'])
                ->map(fn ($e) => $e['name'].' ('.Money::format($e['over']).')')->join(', '));
        }

        return $mail->action('Ver el historial', route('history.index', ['period' => $s['period']->id]));
    }

    public function toWhatsApp(object $notifiable): string
    {
        return 'Resumen de '.$this->monthName().': gastaste '.Money::format($this->summary['spent'])
            .' y ahorraste '.Money::format($this->summary['saved']).'.';
    }
}
