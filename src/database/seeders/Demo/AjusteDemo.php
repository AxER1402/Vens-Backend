<?php

namespace Database\Seeders\Demo;

use App\Models\ClinicalHistory;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lleva una base de la demo al estado realista, sin tocar lo que alguien haya
 * creado o editado a mano en ella.
 *
 * Lo sembrado por DemoSeeder se reconoce porque está fechado antes del
 * 7 de noviembre de 2026; lo que se haya hecho desde la pantalla es posterior.
 *
 *   1. Una sola consulta por paciente en el periodo: las de seguimiento a
 *      pocos días de la primera se quitan y lo que se hizo en ellas queda en
 *      las notas de su cita. Una consulta que ya editaste no se toca.
 *   2. Mapeo venoso en la consulta de cada paciente.
 *   3. Bitácora de auditoría de las semanas de la demo.
 *   4. Cuentas de usuario fechadas antes de esas semanas.
 */
class AjusteDemo
{
    /** Consultas más cercanas que esto a la primera no son creíbles. */
    private const DIAS_MINIMOS_ENTRE_CONSULTAS = 90;

    /**
     * @return array{quitar: array<int, ClinicalHistory>, conservar: array<int, ClinicalHistory>, mapeos: array<int, array{0: ClinicalHistory, 1: array<int, array<string, mixed>>}>, auditoria: bool, usuarios: array<int, User>}
     */
    public static function planear(): array
    {
        $plan = ['quitar' => [], 'conservar' => [], 'mapeos' => [], 'auditoria' => false, 'usuarios' => []];

        foreach (Patient::orderBy('id')->get() as $paciente) {
            $historias = ClinicalHistory::where('patient_id', $paciente->id)
                ->orderBy('fecha_consulta')->orderBy('id')->get();

            $primera = $historias->first();
            if (! $primera) {
                continue;
            }

            foreach ($historias->slice(1) as $historia) {
                $sembrada = $historia->created_at->lt(AuditoriaDemo::HASTA);
                $cercana = $historia->fecha_consulta->diffInDays($primera->fecha_consulta, true) < self::DIAS_MINIMOS_ENTRE_CONSULTAS;

                if (! $sembrada || ! $cercana) {
                    continue;
                }

                if ($historia->updated_at->gte(AuditoriaDemo::HASTA)) {
                    $plan['conservar'][] = $historia;
                } else {
                    $plan['quitar'][] = $historia;
                }
            }

            $mapeo = MapeosDemo::para($paciente);
            if ($mapeo && ! $primera->mapeo_venoso_path) {
                $plan['mapeos'][] = [$primera, $mapeo];
            }
        }

        $plan['auditoria'] = ! AuditoriaDemo::yaSembrada();

        // Una cuenta creada después de su fecha de alta es la que acaba de
        // sembrar UserSeeder: se le pone la fecha en que la creó el administrador.
        $plan['usuarios'] = User::whereIn('email', array_keys(self::altasDeUsuarios()))
            ->get()
            ->filter(fn (User $u) => $u->created_at->gt(Carbon::parse(self::altasDeUsuarios()[$u->email])))
            ->values()->all();

        return $plan;
    }

    /**
     * @param  array<string, mixed>  $plan  lo que devolvió planear()
     */
    public static function aplicar(array $plan): void
    {
        DB::transaction(function () use ($plan) {
            foreach ($plan['quitar'] as $historia) {
                self::pasarANotasDeLaCita($historia);
                $historia->delete();
            }

            foreach ($plan['usuarios'] as $usuario) {
                $usuario->timestamps = false;
                $usuario->forceFill([
                    'created_at' => self::altasDeUsuarios()[$usuario->email],
                    'updated_at' => self::altasDeUsuarios()[$usuario->email],
                ])->save();
            }
        });

        foreach ($plan['mapeos'] as [$historia, $objetos]) {
            Carbon::setTestNow($historia->updated_at);
            MapeosDemo::guardar($historia, $objetos);
            Carbon::setTestNow();
        }

        if ($plan['auditoria']) {
            AuditoriaDemo::sembrar();
        }
    }

    /**
     * Lo que se hizo en la visita de seguimiento queda escrito en su cita, que
     * es donde lo anota recepción cuando no se abre una consulta nueva.
     */
    private static function pasarANotasDeLaCita(ClinicalHistory $historia): void
    {
        $cita = $historia->appointment;
        if (! $cita || $cita->updated_at->gte(AuditoriaDemo::HASTA)) {
            return;
        }

        $partes = [];
        if ($historia->esclero_forma) {
            $partes[] = sprintf(
                'Escleroterapia con %s de polidocanol al %s %%, %s ml.',
                mb_strtolower($historia->esclero_forma),
                rtrim(rtrim((string) $historia->esclero_concentracion, '0'), '.'),
                rtrim(rtrim((string) $historia->esclero_volumen, '0'), '.'),
            );
        }
        if ($historia->notas) {
            $partes[] = $historia->notas;
        }
        if ($partes === []) {
            return;
        }

        $cita->timestamps = false;
        $cita->forceFill(['notas' => trim(($cita->notas ? $cita->notas.' ' : '').implode(' ', $partes))])->saveQuietly();
    }

    /**
     * Cuándo se dio de alta cada cuenta: antes de la primera semana de citas.
     *
     * @return array<string, string>
     */
    private static function altasDeUsuarios(): array
    {
        return [
            'admin@vens.com' => '2026-10-09 15:30:00',
            'medico@vens.com' => '2026-10-12 09:20:00',
            'recepcion@vens.com' => '2026-10-12 09:24:00',
        ];
    }
}
