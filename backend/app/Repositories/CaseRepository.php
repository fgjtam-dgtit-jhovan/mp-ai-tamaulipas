<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class CaseRepository
{
    /**
     * Devuelve una lista de carpetas coincidentes con el expediente.
     */
    public function searchByExpediente(string $expediente, ?string $coordinacionId = null): array
    {
        $results = DB::connection('sqlsrv')->select(
            'EXEC BUSQUEDA_POR_EXPEDIENTES ?, ?',
            [$expediente, $coordinacionId ?? null]
        );

        if (empty($results)) {
            return [];
        }

        return array_map(function ($item) {
            $data = (array) $item;
            if (isset($data['DESCRIPCION_HECHOS'])) {
                $data['DESCRIPCION_HECHOS'] = trim(strip_tags($data['DESCRIPCION_HECHOS']));
            }

            return $data;
        }, $results);
    }

    /**
     * Busca una carpeta específica por su ID_CARPETA.
     */
    public function findByIdCarpeta(string $expediente, string $idCarpeta): ?array
    {
        $cases = $this->searchByExpediente($expediente);

        foreach ($cases as $case) {
            if ((string) $case['ID_CARPETA'] === (string) $idCarpeta) {
                return $case;
            }
        }

        return null;
    }
}
