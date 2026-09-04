<?php

namespace App\Providers;

use App\Support\Facturacion\Certificador;
use App\Support\Facturacion\CertificadorPendiente;
use Illuminate\Support\ServiceProvider;

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
        //
    }
}
