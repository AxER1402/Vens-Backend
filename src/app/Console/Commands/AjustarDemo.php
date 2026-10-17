<?php

namespace App\Console\Commands;

use Database\Seeders\Demo\AjusteDemo;
use Illuminate\Console\Command;

/**
 * Pone al día una demo ya sembrada sin resetearla (ver AjusteDemo).
 *
 * Sin --aplicar solo enseña lo que haría: es una base que alguien pudo haber
 * editado a mano, y nada se borra sin haberlo visto antes.
 */
class AjustarDemo extends Command
{
    protected $signature = 'demo:ajustar {--aplicar : Aplicar los cambios; sin esto solo se muestran}';

    protected $description = 'Ajusta la base de la demo: una consulta por paciente, mapeos venosos y bitácora';

    public function handle(): int
    {
        if (app()->environment('production') && ! str_contains((string) config('app.url'), 'demo')) {
            $this->error('Este comando solo corre en el entorno de demostración (APP_URL con "demo").');

            return self::FAILURE;
        }

        $plan = AjusteDemo::planear();

        $this->info('Consultas de seguimiento que se quitan (lo hecho pasa a las notas de su cita):');
        $this->lista(array_map(fn ($h) => "#{$h->id} {$h->patient->nombre} — {$h->fecha_consulta->format('d/m/Y')}", $plan['quitar']));

        if ($plan['conservar'] !== []) {
            $this->warn('Consultas de seguimiento que se CONSERVAN porque las editaste en la demo:');
            $this->lista(array_map(fn ($h) => "#{$h->id} {$h->patient->nombre} — {$h->fecha_consulta->format('d/m/Y')}", $plan['conservar']));
        }

        $this->info('Mapeos venosos que se dibujan:');
        $this->lista(array_map(fn ($m) => "#{$m[0]->id} {$m[0]->patient->nombre} — ".count($m[1]).' elementos', $plan['mapeos']));

        $this->info('Bitácora de auditoría del 28/09 al 16/10: '.($plan['auditoria'] ? 'se registra' : 'ya estaba'));
        $this->info('Cuentas fechadas antes de la primera semana: '.(count($plan['usuarios']) ?: 'ya lo estaban'));

        if (! $this->option('aplicar')) {
            $this->newLine();
            $this->comment('Nada se modificó. Para aplicarlo: php artisan demo:ajustar --aplicar');

            return self::SUCCESS;
        }

        AjusteDemo::aplicar($plan);
        $this->newLine();
        $this->info('✔ Demo ajustada.');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $lineas
     */
    private function lista(array $lineas): void
    {
        foreach ($lineas ?: ['(ninguna)'] as $linea) {
            $this->line('  · '.$linea);
        }
    }
}
