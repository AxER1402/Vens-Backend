<?php

namespace App\Http\Requests\DopplerReport;

use App\Models\DopplerReport;

class UpdateDopplerReportRequest extends DopplerReportRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * El paciente no se incluye a propósito: un estudio no se traslada a otro
     * expediente. Si se envía patient_id, simplemente se ignora.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->reglasEstudio();
    }

    /**
     * Estudio que se está editando, resuelto desde la ruta.
     */
    protected function reporte(): ?DopplerReport
    {
        $reporte = $this->route('dopplerReport');

        return $reporte instanceof DopplerReport ? $reporte : null;
    }

    /**
     * Paciente al que pertenece el estudio de esta petición.
     */
    protected function pacienteDelEstudio(): ?int
    {
        return $this->reporte()?->patient_id;
    }
}
