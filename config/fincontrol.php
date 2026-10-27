<?php

return [

    /*
    | Notificaciones por correo (recordatorios y resumen mensual).
    | Apagadas por defecto: los comandos corren igual y escriben en consola/log.
    */
    'mail_notifications' => (bool) env('FINCONTROL_MAIL_NOTIFICATIONS', false),

    // Días de anticipación del recordatorio de pago.
    'reminder_days_before' => 2,

];
