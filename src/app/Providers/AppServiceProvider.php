<?php

namespace App\Providers;

use App\Support\Ajustes\Ajustes;
use App\Support\Facturacion\Certificador;
use App\Support\Facturacion\CertificadorPendiente;
use App\Support\Sesion\VencimientoDeSesion;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Quién certifica las facturas ante la SAT. Mientras no haya API
        // contratada responde el que no certifica nada y deja el documento
        // pendiente; el día que se contrate, esta línea es lo único que cambia.
        $this->app->bind(Certificador::class, CertificadorPendiente::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Lo que la clínica ajustó desde la pantalla pisa a lo que traen
        // config/reportes.php y config/facturacion.php. Va aquí, y no en cada
        // sitio que lee la configuración, para que los dieciséis que ya lo
        // hacían no tengan que enterarse de que ahora hay una tabla detrás.
        Ajustes::aplicarAConfig();

        // La sesión se cierra por inactividad, no a la hora en punto. Sanctum
        // pregunta aquí si el token sigue sirviendo, y lo hace justo antes de
        // anotar la petición en curso: en este instante `last_used_at` todavía
        // guarda la marca de la petición anterior, que es exactamente contra lo
        // que hay que medir el hueco.
        Sanctum::authenticateAccessTokensUsing(
            fn (PersonalAccessToken $token, bool $esValido): bool => $esValido
                && VencimientoDeSesion::siguePorActividad($token)
        );
    }
}
