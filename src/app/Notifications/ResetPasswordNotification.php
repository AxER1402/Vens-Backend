<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    /**
     * @param  string  $token  Token de restablecimiento generado por el broker de contraseñas.
     */
    public function __construct(public string $token) {}

    /**
     * Canales por los que se entrega la notificación.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Construir el correo de restablecimiento de contraseña.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $minutos = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Restablecer contraseña — Centro Médico Vens')
            ->greeting('Hola '.$notifiable->name.',')
            ->line('Recibimos una solicitud para restablecer la contraseña de su cuenta.')
            ->action('Restablecer contraseña', $this->resetUrl($notifiable))
            ->line('Este enlace vencerá en '.$minutos.' minutos.')
            ->line('Si usted no solicitó el restablecimiento, puede ignorar este correo; su contraseña actual no se modificará.')
            ->salutation('Atentamente, Centro Médico Vens');
    }

    /**
     * Armar el enlace hacia el frontend con el token y el correo del usuario.
     */
    protected function resetUrl(object $notifiable): string
    {
        return rtrim((string) config('app.frontend_url'), '/')
            .'/restablecer-contrasena?'
            .http_build_query([
                'token' => $this->token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);
    }
}
