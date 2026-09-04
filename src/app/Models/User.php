<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'rol', 'activo', 'telefono', 'foto_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
        ];
    }

    /**
     * URL pública de la foto de perfil, o null si el usuario no ha subido
     * ninguna: la interfaz cae entonces a las iniciales del nombre.
     *
     * No se añade a `$appends`. Media aplicación carga usuarios por partes
     * —`medico:id,name`, `creator:id,name,email`— y sin la columna en el
     * select el accesor no devolvería «no hay foto», sino «no la pregunté»,
     * que en el JSON se leen igual. Quien necesite la foto la pide.
     *
     * @return Attribute<string|null, never>
     */
    protected function fotoUrl(): Attribute
    {
        return Attribute::get(
            fn (): ?string => $this->foto_path
                ? Storage::disk('public')->url($this->foto_path)
                : null
        );
    }

    /**
     * Enviar la notificación de restablecimiento de contraseña en español,
     * con el enlace apuntando al frontend en lugar de una vista de Laravel.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
