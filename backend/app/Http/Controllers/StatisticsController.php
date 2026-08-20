<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\CrimeGrouperService;
use App\Models\Ms\StoredProcedure;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\Ms\{
    Corporation,
    Crime,
    Gender,
    Municipality,
    Modality,
    MunicipalityCoordination,
    Unidad,
    Coordination,
    Violence,
};
use App\Models\Cards\{
    Card,
    File,
};

class StatisticsController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        // Si el usuario es level 3, redirigir a tarjetas informativas
        if ($user->level == 3) {
            return redirect()->route('tarjetas');
        }
        $date = new \DateTime();

        $currentMonth = $date->format('m');
        $currentYear  = $date->format('Y');

        $year  = $currentYear;
        $month = $currentMonth;
        $municipality_id = null;

        $queryTotal = $this->getStoredProcedureCarpetas($year, $month, null, null, null);
        $totalCarpetas = 0;

        foreach ($queryTotal as $q) {
            $flag = 0;
            foreach ($q as $value) {
                if ($flag > 0) {
                    $totalCarpetas += $value;
                }
                $flag++;
            }
        }

        $altoImpactoIds = $this->altoImpactoIds();
        $totalAltoImpacto = 0;

        foreach ($altoImpactoIds as $crime_id) {
            $queryAI = $this->getStoredProcedureCarpetas($year, $month, null, $crime_id, null);
            foreach ($queryAI as $q) {
                $flag = 0;
                foreach ($q as $value) {
                    if ($flag > 0) {
                        $totalAltoImpacto += $value;
                    }
                    $flag++;
                }
            }
        }

        $percentAltoImpacto = $totalCarpetas > 0
            ? round(($totalAltoImpacto * 100) / $totalCarpetas, 2)
            : 0;

        $violenceId   = 1;
        $noViolenceId = 0;
        $doloso       = 1;
        $culposo      = 2;

        $homicidioId        = 33;
        $secuestroId        = 48;
        $violacionId        = 43;

        $robosViolencia    = [];
        $robosSinViolencia = [];
        $roboVehiculos     = [];
        $homicidios        = [];
        $altoImpacto = [];

        $transeunteIDs = [
            184, // Robo a transeunte en via publica
            185, // Robo a transeunte en espacio abierto al publico
        ];

        /* ===== Robos con violencia ===== */
        foreach ($this->roboIds() as $crime_id) {
            $total = $this->getStoredProcedureDelitos($year, $month, $municipality_id, $crime_id, null, $violenceId);
            $crime = Crime::select('DLTO')->where('ID_DLTO', $crime_id)->first();

            $robosViolencia[] = [
                'crime' => $crime->DLTO,
                'days'  => $this->sumDays($total),
            ];
        }

        /* ===== Robos sin violencia ===== */
        foreach ($this->roboIds() as $crime_id) {
            $total = $this->getStoredProcedureDelitos($year, $month, $municipality_id, $crime_id, null, $noViolenceId);
            $crime = Crime::select('DLTO')->where('ID_DLTO', $crime_id)->first();

            $robosSinViolencia[] = [
                'crime' => $crime->DLTO,
                'days'  => $this->sumDays($total),
            ];
        }

        /* ===== Robo de vehículos ===== */
        foreach ([$noViolenceId, $violenceId] as $violence_id) {
            $total = $this->getStoredProcedureDelitos($year, $month, $municipality_id, 98, null, $violence_id);

            $roboVehiculos[] = [
                'crime' => $violence_id == $violenceId ? 'CON VIOLENCIA' : 'SIN VIOLENCIA',
                'days'  => $this->sumDays($total),
            ];
        }

        /* ===== Alto Impacto ===== */
        foreach ($this->altoImpactoIds() as $crime_id) {
            $total = $this->getStoredProcedureDelitos($year, $month, $municipality_id, $crime_id, null);
            $crime = Crime::select('DLTO')->where('ID_DLTO', $crime_id)->first();
            $altoImpacto[] = [
                'crime' => $crime->DLTO,
                'days'  => $this->sumDays($total),
            ];
        }

        foreach ($transeunteIDs as $crime_id) {
            $total = $this->getStoredProcedureDelitos($year, $month, $municipality_id, $crime_id, null);
            $crime = Crime::select('DLTO')->where('ID_DLTO', $crime_id)->first();
            $altoImpacto[] = [
                'crime' => $crime->DLTO,
                'days'  => $this->sumDays($total),
            ];
        }

        $altoImpacto = $this->normalizeTranseunteAltoImpacto($altoImpacto);

        /* ===== Homicidios ===== */
        foreach ([$doloso, $culposo] as $modality_id) {
            $total = $this->getStoredProcedureDelitos(
                $year,
                $month,
                $municipality_id,
                $homicidioId,
                $modality_id,
                null
            );

            $homicidios[] = [
                'crime' => $modality_id == $doloso ? 'HOMICIDIO DOLOSO' : 'HOMICIDIO CULPOSO',
                'days'  => $this->sumDays($total),
            ];
        }

        /* ===== Otros delitos ===== */
        $crimeGrouper = new CrimeGrouperService();

        $groupedCrimes = $crimeGrouper->group([
            'robosViolencia'    => $robosViolencia,
            'robosSinViolencia' => $robosSinViolencia,
            'roboVehiculos'     => $roboVehiculos,
            'homicidios'        => $homicidios,
            'altoImpacto'       => $altoImpacto,
            'secuestros'        => $this->sumDays(
                $this->getStoredProcedureDelitos($year, $month, $municipality_id, $secuestroId, null, null)
            ),
            'violacion'         => $this->sumDays(
                $this->getStoredProcedureDelitos($year, $month, $municipality_id, $violacionId, null, null)
            ),
        ]);

        // ============================================================
        // SOLO MODIFICAR EL ORDEN DE SUBTIPOS DE ALTO IMPACTO
        // ============================================================

        // 1. Eliminar HOMICIDIO genérico
        unset($groupedCrimes['ALTO_IMPACTO']['subtipos']['HOMICIDIO']);

        // 2. Agregar HOMICIDIO DOLOSO y CULPOSO
        $groupedCrimes['ALTO_IMPACTO']['subtipos']['HOMICIDIO DOLOSO'] = $groupedCrimes['HOMICIDIOS']['DOLOSO'];
        $groupedCrimes['ALTO_IMPACTO']['subtipos']['HOMICIDIO CULPOSO'] = $groupedCrimes['HOMICIDIOS']['CULPOSO'];

        // 3. ORDEN ESPECÍFICO
        $ordenAltoImpacto = [
            'HOMICIDIO DOLOSO',
            'FEMINICIDIO',
            'HOMICIDIO CULPOSO',
            'SECUESTRO',
            'EXTORSION',
            'ROBO DE VEHICULOS',
            'ROBO A NEGOCIO',
            'ROBO A TRANSEUNTE',
            'ROBO A CASA HABITACIÓN',
            'NARCOMENUDEO'
        ];

        // Obtener los subtipos actuales (objeto)
        $subtiposActuales = $groupedCrimes['ALTO_IMPACTO']['subtipos'];

        // Crear un NUEVO ARRAY INDEXADO en el orden específico
        $subtiposOrdenados = [];

        // Primero: agregar en el orden específico
        foreach ($ordenAltoImpacto as $nombreDelito) {
            if (isset($subtiposActuales[$nombreDelito])) {
                $subtiposOrdenados[] = [
                    'nombre' => $nombreDelito,
                    'total' => $subtiposActuales[$nombreDelito]['total'],
                    'days' => $subtiposActuales[$nombreDelito]['days']
                ];
            }
        }

        // Segundo: agregar el resto de delitos que no están en el orden
        foreach ($subtiposActuales as $nombre => $datos) {
            if (!in_array($nombre, $ordenAltoImpacto)) {
                $subtiposOrdenados[] = [
                    'nombre' => $nombre,
                    'total' => $datos['total'],
                    'days' => $datos['days']
                ];
            }
        }

        // Reemplazar los subtipos con el array indexado ordenado
        $groupedCrimes['ALTO_IMPACTO']['subtipos'] = $subtiposOrdenados;

        // 4. Recalcular el total de ALTO_IMPACTO
        $nuevoTotal = 0;
        foreach ($groupedCrimes['ALTO_IMPACTO']['subtipos'] as $sub) {
            $nuevoTotal += $sub['total'];
        }
        $groupedCrimes['ALTO_IMPACTO']['total'] = $nuevoTotal;

        // ============================================================
        // FIN DEL ORDENAMIENTO
        // ============================================================
        //dd($groupedCrimes['ALTO_IMPACTO']['subtipos']);
        /* ===================================================== */
        return Inertia::render('Dashboard', [
            'currentMonth' => [
                'value' => (int)$currentMonth,
                'label' => $this->month($currentMonth - 1),
            ],
            'currentYear'        => $currentYear,
            'months'             => $this->currentMonths(),
            'years'              => $this->years(),

            'totalCarpetas'      => $totalCarpetas,
            'totalAltoImpacto'   => $totalAltoImpacto,
            'percentAltoImpacto' => $percentAltoImpacto,
            'groupedCrimes'      => $groupedCrimes,
        ]);
    }

    public function showAvisoPrivacidad()
    {
        return Inertia::render('AvisoPrivacidad');
    }

    public function metodologia(): \Inertia\Response
    {
        return Inertia::render('Metodologia');
    }

    public function getFilteredData(Request $request)
    {
        // Validar los parámetros
        $validated = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
        ]);

        $month = $validated['month'];
        $year = $validated['year'];
        $municipality_id = null;

        // Total de carpetas
        $queryTotal = $this->getStoredProcedureCarpetas($year, $month, null, null, null);
        $totalCarpetas = 0;

        foreach ($queryTotal as $q) {
            $flag = 0;
            foreach ($q as $value) {
                if ($flag > 0) {
                    $totalCarpetas += $value;
                }
                $flag++;
            }
        }

        // Total de alto impacto
        $altoImpactoIds = $this->altoImpactoIds();
        $totalAltoImpacto = 0;

        foreach ($altoImpactoIds as $crime_id) {
            $queryAI = $this->getStoredProcedureCarpetas($year, $month, null, $crime_id, null);
            foreach ($queryAI as $q) {
                $flag = 0;
                foreach ($q as $value) {
                    if ($flag > 0) {
                        $totalAltoImpacto += $value;
                    }
                    $flag++;
                }
            }
        }

        $percentAltoImpacto = $totalCarpetas > 0
            ? round(($totalAltoImpacto * 100) / $totalCarpetas, 2)
            : 0;

        // Configuración de constantes
        $violenceId   = 1;
        $noViolenceId = 0;
        $doloso       = 1;
        $culposo      = 2;

        $homicidioId        = 33;
        $secuestroId        = 48;
        $violacionId        = 43;

        $robosViolencia    = [];
        $robosSinViolencia = [];
        $roboVehiculos     = [];
        $homicidios        = [];
        $altoImpacto = [];

        $transeunteIDs = [
            184, // Robo a transeunte en via publica
            185, // Robo a transeunte en espacio abierto al publico
        ];

        /* ===== Robos con violencia ===== */
        foreach ($this->roboIds() as $crime_id) {
            $total = $this->getStoredProcedureDelitos($year, $month, $municipality_id, $crime_id, null, $violenceId);
            $crime = Crime::select('DLTO')->where('ID_DLTO', $crime_id)->first();

            $robosViolencia[] = [
                'crime' => $crime->DLTO,
                'days'  => $this->sumDays($total),
            ];
        }

        /* ===== Robos sin violencia ===== */
        foreach ($this->roboIds() as $crime_id) {
            $total = $this->getStoredProcedureDelitos($year, $month, $municipality_id, $crime_id, null, $noViolenceId);
            $crime = Crime::select('DLTO')->where('ID_DLTO', $crime_id)->first();

            $robosSinViolencia[] = [
                'crime' => $crime->DLTO,
                'days'  => $this->sumDays($total),
            ];
        }

        /* ===== Robo de vehículos ===== */
        foreach ([$noViolenceId, $violenceId] as $violence_id) {
            $total = $this->getStoredProcedureDelitos($year, $month, $municipality_id, 98, null, $violence_id);

            $roboVehiculos[] = [
                'crime' => $violence_id == $violenceId ? 'CON VIOLENCIA' : 'SIN VIOLENCIA',
                'days'  => $this->sumDays($total),
            ];
        }

        foreach ($this->altoImpactoIds() as $crime_id) {
            $total = $this->getStoredProcedureDelitos($year, $month, $municipality_id, $crime_id, null);
            $crime = Crime::select('DLTO')->where('ID_DLTO', $crime_id)->first();
            $altoImpacto[] = [
                'crime' => $crime->DLTO,
                'days'  => $this->sumDays($total),
            ];
        }

        foreach ($transeunteIDs as $crime_id) {
            $total = $this->getStoredProcedureDelitos($year, $month, $municipality_id, $crime_id, null);
            $crime = Crime::select('DLTO')->where('ID_DLTO', $crime_id)->first();
            $altoImpacto[] = [
                'crime' => $crime->DLTO,
                'days'  => $this->sumDays($total),
            ];
        }

        $altoImpacto = $this->normalizeTranseunteAltoImpacto($altoImpacto);

        /* ===== Homicidios ===== */
        foreach ([$doloso, $culposo] as $modality_id) {
            $total = $this->getStoredProcedureDelitos(
                $year,
                $month,
                $municipality_id,
                $homicidioId,
                $modality_id,
                null
            );

            $homicidios[] = [
                'crime' => $modality_id == $doloso ? 'HOMICIDIO DOLOSO' : 'HOMICIDIO CULPOSO',
                'days'  => $this->sumDays($total),
            ];
        }

        /* ===== Otros delitos ===== */
        $crimeGrouper = new CrimeGrouperService();

        $groupedCrimes = $crimeGrouper->group([
            'robosViolencia'    => $robosViolencia,
            'robosSinViolencia' => $robosSinViolencia,
            'roboVehiculos'     => $roboVehiculos,
            'homicidios'        => $homicidios,
            'altoImpacto'       => $altoImpacto,
            'secuestros'        => $this->sumDays(
                $this->getStoredProcedureDelitos($year, $month, $municipality_id, $secuestroId, null, null)
            ),
            'violacion'         => $this->sumDays(
                $this->getStoredProcedureDelitos($year, $month, $municipality_id, $violacionId, null, null)
            ),
        ]);

        // ============================================================
        // ORDENAR SUBTIPOS DE ALTO IMPACTO (IGUAL QUE EN INDEX)
        // ============================================================

        // 1. Eliminar HOMICIDIO genérico
        unset($groupedCrimes['ALTO_IMPACTO']['subtipos']['HOMICIDIO']);

        // 2. Agregar HOMICIDIO DOLOSO y CULPOSO
        $groupedCrimes['ALTO_IMPACTO']['subtipos']['HOMICIDIO DOLOSO'] = $groupedCrimes['HOMICIDIOS']['DOLOSO'];
        $groupedCrimes['ALTO_IMPACTO']['subtipos']['HOMICIDIO CULPOSO'] = $groupedCrimes['HOMICIDIOS']['CULPOSO'];

        // 3. ORDEN ESPECÍFICO
        $ordenAltoImpacto = [
            'HOMICIDIO DOLOSO',
            'FEMINICIDIO',
            'HOMICIDIO CULPOSO',
            'SECUESTRO',
            'EXTORSION',
            'ROBO DE VEHICULOS',
            'ROBO A NEGOCIO',
            'ROBO A TRANSEUNTE',
            'ROBO A CASA HABITACIÓN',
            'NARCOMENUDEO'
        ];

        // Obtener los subtipos actuales (objeto)
        $subtiposActuales = $groupedCrimes['ALTO_IMPACTO']['subtipos'];

        // Crear un NUEVO ARRAY INDEXADO en el orden específico
        $subtiposOrdenados = [];

        // Primero: agregar en el orden específico
        foreach ($ordenAltoImpacto as $nombreDelito) {
            if (isset($subtiposActuales[$nombreDelito])) {
                $subtiposOrdenados[] = [
                    'nombre' => $nombreDelito,
                    'total' => $subtiposActuales[$nombreDelito]['total'],
                    'days' => $subtiposActuales[$nombreDelito]['days']
                ];
            }
        }

        // Segundo: agregar el resto de delitos que no están en el orden
        foreach ($subtiposActuales as $nombre => $datos) {
            if (!in_array($nombre, $ordenAltoImpacto)) {
                $subtiposOrdenados[] = [
                    'nombre' => $nombre,
                    'total' => $datos['total'],
                    'days' => $datos['days']
                ];
            }
        }

        // Reemplazar los subtipos con el array indexado ordenado
        $groupedCrimes['ALTO_IMPACTO']['subtipos'] = $subtiposOrdenados;

        // 4. Recalcular el total de ALTO_IMPACTO
        $nuevoTotal = 0;
        foreach ($groupedCrimes['ALTO_IMPACTO']['subtipos'] as $sub) {
            $nuevoTotal += $sub['total'];
        }
        $groupedCrimes['ALTO_IMPACTO']['total'] = $nuevoTotal;

        // Retornar solo los datos (sin la vista)
        return response()->json([
            'success' => true,
            'data' => [
                'totalCarpetas'      => $totalCarpetas,
                'totalAltoImpacto'   => $totalAltoImpacto,
                'percentAltoImpacto' => $percentAltoImpacto,
                'groupedCrimes'      => $groupedCrimes,
                'currentMonth' => [
                    'value' => (int)$month,
                    'label' => $this->month($month - 1),
                ],
                'currentYear' => $year,
            ]
        ]);
    }
    /**
     * Obtiene los meses disponibles
     */
    public function getMonths()
    {
        return response()->json([
            'success' => true,
            'months' => $this->currentMonths(),
        ]);
    }

    /**
     * Obtiene los años disponibles
     */
    public function getYears()
    {
        return response()->json([
            'success' => true,
            'years' => $this->years(),
        ]);
    }

    private function normalizeTranseunteAltoImpacto(array $items): array
    {
        $totalDays = [];
        $filtered  = [];

        foreach ($items as $item) {
            if (str_starts_with($item['crime'], 'ROBO A TRANSEUNTE')) {
                foreach ($item['days'] as $day => $count) {
                    $totalDays[$day] = ($totalDays[$day] ?? 0) + $count;
                }
            } else {
                $filtered[] = $item;
            }
        }

        if (!empty($totalDays)) {
            $filtered[] = [
                'crime' => 'ROBO A TRANSEUNTE',
                'days'  => $totalDays,
            ];
        }

        return $filtered;
    }



    public function altoImpacto()
    {
        $date = new \DateTime();
        if ($date->format('G') < 12) {
            $grettings = 'Buenos días ';
        } elseif ($date->format('G') >= 12 && $date->format('G') < 20) {
            $grettings = 'Buenas tardes ';
        } else {
            $grettings = 'Buenas noches ';
        }
        $currentMonth = $date->format('m');
        $currentYear = $date->format('Y');
        $altoImpactoIds = $this->altoImpactoIds();
        $crimesAltoImpacto = Crime::whereIn('ID_DLTO', $altoImpactoIds)->orderBy('DLTO', 'ASC')->get();
        return Inertia::render('AltoImpacto', [
            'grettings' => $grettings,
            'currentMonth' => [
                'value' => (int)$currentMonth,
                'label' => $this->month($currentMonth - 1),
            ],
            'currentYear' => $currentYear,
            'months' => $this->currentMonths(),
            'years' => $this->years(),
            'crimesAltoImpacto' => $crimesAltoImpacto,
        ]);
    }

    public function carpetas()
    {
        $date = new \DateTime();
        $currentMonth = $date->format('m');
        $currentYear = $date->format('Y');

        return Inertia::render('Carpetas', [
            'municipalities' => $this->municipalities(),
            'crimes' => $this->crimes(),
            'months' => $this->currentMonths(),
            'modalities' => $this->modalities(),
            'years' => $this->years(),
            'currentMonth' => [
                'value' => (int)$currentMonth,
                'label' => $this->month($currentMonth - 1),
            ],
            'currentYear' => $currentYear,
        ]);
    }

    public function getCarpetas(Request $request)
    {
        $request->validate([
            'year' => 'required',
            'month' => 'required',
        ]);

        $state = [];
        $year = $request->input('year');
        $month = $request->input('month');
        $municipality_id = $request->input('municipality_id') ?? NULL;
        $crime_id = $request->input('crime_id') ?? NULL;
        $modality_id = $request->input('modality_id') ?? NULL;
        $violence_id = $request->input('violence_id') ?? NULL;
        $grado = $request->input('grado') ?? NULL;
        $todos_hom = $request->input('todos_hom') ?? NULL;
        $data = $this->getStoredProcedureCarpetas($year, $month, $municipality_id, $crime_id, $modality_id, $violence_id, $grado, $todos_hom);
        if ($data) {
            $fiscalias = $this->fiscalias();
            $currentDate = new \DateTime();
            $currentMonth = $currentDate->format('m');
            $currentYear = $currentDate->format('Y');
            $toDay = null;

            if ($year == $currentYear && $month == $currentMonth) {
                $toDay = $currentDate->format('d');
            } else {
                $date = new \DateTime("$year-$month-1");
                $toDay = $date->format('t');
            }

            foreach ($fiscalias as $key => $arrFiscalias) {
                $municipalities = null;
                foreach ($data as $k => $d) {
                    if (in_array(rtrim($d->MUNICIPIO_HECHO), $arrFiscalias)) {
                        $days = [];
                        $daysToArray = (array) $d;
                        if (!$toDay) {
                            $toDay = count($daysToArray) - 1;
                        }
                        // Show only valid days depends on current month and year
                        for ($i = 1; $i <= $toDay; $i++) {
                            $days[$i] = $daysToArray[$i];
                        }

                        $municipalities[] = [
                            'name' => $d->MUNICIPIO_HECHO,
                            'days' => $days
                        ];
                        unset($data[$k]);
                    }
                }
                if ($municipalities) {
                    $state[] = [
                        'fiscalia' => $key,
                        'municipalities' => $municipalities,
                    ];
                }
            }
        }

        return response()->json($state);
    }


    public function consultaIncidencias()
    {
        $date = new \DateTime();
        $currentMonth = $date->format('m');
        $currentYear = $date->format('Y');

        return Inertia::render('IncidenciaCarpetas', [
            'municipalities' => $this->municipalities(),
            'crimes' => $this->crimes(),
            'months' => $this->currentMonths(),
            'modalities' => $this->modalities(),
            'years' => $this->years(),
            'currentMonth' => [
                'value' => (int)$currentMonth,
                'label' => $this->month($currentMonth - 1),
            ],
            'currentYear' => $currentYear,
        ]);
    }

    public function comparativaIncidencias()
    {
        $date = new \DateTime();
        $currentMonth = $date->format('m');
        $currentYear = $date->format('Y');
        $coordinaciones = $this->coordinaciones();
        array_unshift($coordinaciones, [
            'value' => null,
            'label' => 'TODAS LAS REGIONES',
        ]);
        //dd($coordinaciones);
        return Inertia::render('ComparativaIncidencias', [
            'municipalities' => $this->municipalities(),
            'crimes' => $this->crimes(),
            'months' => $this->currentMonths(),
            'modalities' => $this->modalities(),
            'municipalitiesForCoordination' => $this->muncipalitiesForCoordination(),
            'coordinaciones' => $coordinaciones,
            'years' => $this->years(),
            'currentMonth' => [
                'value' => (int)$currentMonth,
                'label' => $this->month($currentMonth - 1),
            ],
            'currentYear' => $currentYear,
        ]);
    }


    public function getComparativaMes(Request $request)
    {
        $municipality_id = $request->input('municipality_id') ?? NULL;
        $crime_id = $request->input('crime_id') ?? NULL;
        $modality_id = $request->input('modality_id') ?? NULL;
        $violence_id = $request->input('violence_id') ?? NULL;
        $grado = $request->input('grado') ?? NULL;
        $coordination_id = $request->input('coordination_id') ?? NULL;
        $todos_hom = $request->input('todos_hom') ?? NULL;
        //dd($coordination_id, $municipality_id, $crime_id, $modality_id, $violence_id, $grado, $todos_hom);
        try {
            $data = DB::connection('sqlsrv')->select(
                'EXEC CONTEOS_POR_DIA_Y_ACUM_GRAFICA_COORDINACION ?, ?, ?, ?, ?, ?, ?',
                [$municipality_id, $crime_id, $modality_id, $violence_id, $grado, $coordination_id, $todos_hom]
            );
            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Error en getComparativaMes: ' . $e->getMessage());
            return response()->json(['error' => 'Error en la búsqueda'], 500);
        }
    }

    public function getComparativaAno(Request $request)
    {

        $municipality_id = $request->input('municipality_id') ?? NULL;
        $crime_id = $request->input('crime_id') ?? NULL;
        $modality_id = $request->input('modality_id') ?? NULL;
        $violence_id = $request->input('violence_id') ?? NULL;
        $grado = $request->input('grado') ?? NULL;
        $todos_hom = $request->input('todos_hom') ?? NULL;

        try {
            $data = DB::connection('sqlsrv')->select(
                'EXEC CONTEOS_POR_DIA_Y_ACUM_GRAFICA_AÑO ?, ?, ?, ?, ?, ?',
                [$municipality_id, $crime_id, $modality_id, $violence_id, $grado, $todos_hom]
            );

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Error en getComparativaAno: ' . $e->getMessage());
            return response()->json(['error' => 'Error en la búsqueda'], 500);
        }
    }




    public function consultaHomicidios()
    {
        $date = new \DateTime();
        $currentMonth = $date->format('m');
        $currentYear = $date->format('Y');

        return Inertia::render('IncidenciaHomicidios', [
            'municipalities' => $this->municipalities(),
            'crimes' => $this->crimes(),
            'months' => $this->currentMonths(),
            'modalities' => $this->modalities(),
            'years' => $this->years(),
            'currentMonth' => [
                'value' => (int)$currentMonth,
                'label' => $this->month($currentMonth - 1),
            ],
            'currentYear' => $currentYear,
        ]);
    }



    public function incidenciaRobos()
    {
        $date = new \DateTime();

        if ($date->format('G') < 12) {
            $grettings = 'Buenos días ';
        } elseif ($date->format('G') < 20) {
            $grettings = 'Buenas tardes ';
        } else {
            $grettings = 'Buenas noches ';
        }

        $currentMonth = $date->format('m');
        $currentYear  = $date->format('Y');

        $year  = $currentYear;
        $month = $currentMonth;
        $municipality_id = null;

        $violenceId   = 1;
        $noViolenceId = 0;

        /* =====================================================
       1️⃣ DATOS CRUDOS
    ===================================================== */

        $robosViolencia    = [];
        $robosSinViolencia = [];
        $roboVehiculos     = [];

        foreach ($this->robosIncidenciaIds() as $crime_id) {
            $crime = Crime::select('DLTO')->where('ID_DLTO', $crime_id)->first();

            $robosViolencia[] = [
                'crime' => $crime->DLTO,
                'days'  => $this->sumDays(
                    $this->getStoredProcedureDelitos(
                        $year,
                        $month,
                        $municipality_id,
                        $crime_id,
                        null,
                        $violenceId
                    )
                ),
            ];

            $robosSinViolencia[] = [
                'crime' => $crime->DLTO,
                'days'  => $this->sumDays(
                    $this->getStoredProcedureDelitos(
                        $year,
                        $month,
                        $municipality_id,
                        $crime_id,
                        null,
                        $noViolenceId
                    )
                ),
            ];
        }

        foreach ([$noViolenceId, $violenceId] as $violence_id) {
            $roboVehiculos[] = [
                'crime' => $violence_id === $violenceId ? 'CON VIOLENCIA' : 'SIN VIOLENCIA',
                'days'  => $this->sumDays(
                    $this->getStoredProcedureDelitos(
                        $year,
                        $month,
                        $municipality_id,
                        98,
                        null,
                        $violence_id
                    )
                ),
            ];
        }

        $otherRobosDays = $this->sumDays(
            $this->getGetOtherRobosProcedure($year, $month)
        );
        $otherRobosViolencia = $this->sumDays(
            $this->getGetOtherRobosProcedure($year, $month, $violenceId)
        );
        $otherRobosSinViolencia = $this->sumDays(
            $this->getGetOtherRobosProcedure($year, $month, $noViolenceId)
        );

        $totalOtherRobos = array_sum($otherRobosDays);
        $totalOtherRobosViolencia = array_sum($otherRobosViolencia);
        $totalOtherRobosSinViolencia = array_sum($otherRobosSinViolencia);


        /* =====================================================
       2️⃣ AGRUPACIÓN
    ===================================================== */

        $crimeGrouper = new CrimeGrouperService();

        $groupedCrimes = $crimeGrouper->group([
            'robosViolencia'    => $robosViolencia,
            'robosSinViolencia' => $robosSinViolencia,
            'roboVehiculos'     => $roboVehiculos,
        ]);
        // CON VIOLENCIA
        if (isset($groupedCrimes['ROBOS']['CON_VIOLENCIA']['subtipos'])) {
            $this->normalizeTranseunte(
                $groupedCrimes['ROBOS']['CON_VIOLENCIA']['subtipos']
            );
        }

        // SIN VIOLENCIA
        if (isset($groupedCrimes['ROBOS']['SIN_VIOLENCIA']['subtipos'])) {
            $this->normalizeTranseunte(
                $groupedCrimes['ROBOS']['SIN_VIOLENCIA']['subtipos']
            );
        }
        $normalizeKey = fn($key) => strtoupper(str_replace(' ', '_', $key));


        $robosConViolencia = [];

        foreach ($groupedCrimes['ROBOS']['CON_VIOLENCIA']['subtipos'] as $name => $data) {
            $robosConViolencia[$normalizeKey($name)] = [
                'total' => $data['total'],
                'days'  => $data['days'] ?? [],
            ];
        }

        $robosConViolencia['ROBO_VEHICULOS'] = [
            'total' => $groupedCrimes['ROBO_VEHICULOS']['subtipos']['CON VIOLENCIA']['total'] ?? 0,
            'days'  => $groupedCrimes['ROBO_VEHICULOS']['subtipos']['CON VIOLENCIA']['days'] ?? [],
        ];

        $robosSinViolencia = [];

        foreach ($groupedCrimes['ROBOS']['SIN_VIOLENCIA']['subtipos'] as $name => $data) {
            $robosSinViolencia[$normalizeKey($name)] = [
                'total' => $data['total'],
                'days'  => $data['days'] ?? [],
            ];
        }

        $robosSinViolencia['ROBO_VEHICULOS'] = [
            'total' => $groupedCrimes['ROBO_VEHICULOS']['subtipos']['SIN VIOLENCIA']['total'] ?? 0,
            'days'  => $groupedCrimes['ROBO_VEHICULOS']['subtipos']['SIN VIOLENCIA']['days'] ?? [],
        ];

        $totalesPorRobo = [];

        foreach (
            array_unique(array_merge(
                array_keys($robosConViolencia),
                array_keys($robosSinViolencia)
            )) as $tipo
        ) {
            $totalesPorRobo[$tipo] =
                ($robosConViolencia[$tipo]['total'] ?? 0) +
                ($robosSinViolencia[$tipo]['total'] ?? 0);
        }

        $totalConViolencia =
            ($groupedCrimes['ROBOS']['CON_VIOLENCIA']['total'] ?? 0) +
            ($groupedCrimes['ROBO_VEHICULOS']['subtipos']['CON VIOLENCIA']['total'] ?? 0);
        $totalSinViolencia =
            ($groupedCrimes['ROBOS']['SIN_VIOLENCIA']['total'] ?? 0) +
            ($groupedCrimes['ROBO_VEHICULOS']['subtipos']['SIN VIOLENCIA']['total'] ?? 0);

        $totalRobos =
            $totalConViolencia +
            $totalSinViolencia +
            $totalOtherRobos;

        $totalConViolencia += $totalOtherRobosViolencia;
        $totalSinViolencia += $totalOtherRobosSinViolencia;

        return Inertia::render('Robos', [
            'grettings' => $grettings,

            'data' => [
                'total' => [
                    'total'         => $totalRobos,
                    'con_violencia' => $totalConViolencia,
                    'sin_violencia' => $totalSinViolencia,
                    'otros'         => $totalOtherRobos,
                    'otros_con_violencia' => $totalOtherRobosViolencia,
                    'otros_sin_violencia' => $totalOtherRobosSinViolencia,
                ],

                'robos_con_violencia' => [
                    'total'    => $totalConViolencia + $totalOtherRobosViolencia,
                    'subtipos' => $robosConViolencia,
                ],

                'robos_sin_violencia' => [
                    'total'    => $totalSinViolencia + $totalOtherRobosSinViolencia,
                    'subtipos' => $robosSinViolencia,
                ],
                'totales_por_robo' => $totalesPorRobo,
            ],

            'currentMonth' => [
                'value' => (int) $currentMonth,
                'label' => $this->month($currentMonth - 1),
            ],

            'currentYear' => $currentYear,
            'months'      => $this->currentMonths(),
            'years'       => $this->years(),
        ]);
    }

    public function getFilterRobos(Request $request)
    {
        $year = $request->input('year', date('Y'));
        $month = $request->input('month', date('n'));

        $municipality_id = null;
        $violenceId = 1;
        $noViolenceId = 0;

        // Copia la lógica de incidenciaRobos() pero con los parámetros dinámicos
        $robosViolencia = [];
        $robosSinViolencia = [];
        $roboVehiculos = [];

        foreach ($this->robosIncidenciaIds() as $crime_id) {
            $crime = Crime::select('DLTO')->where('ID_DLTO', $crime_id)->first();

            $robosViolencia[] = [
                'crime' => $crime->DLTO,
                'days'  => $this->sumDays(
                    $this->getStoredProcedureDelitos(
                        $year,
                        $month,
                        $municipality_id,
                        $crime_id,
                        null,
                        $violenceId
                    )
                ),
            ];

            $robosSinViolencia[] = [
                'crime' => $crime->DLTO,
                'days'  => $this->sumDays(
                    $this->getStoredProcedureDelitos(
                        $year,
                        $month,
                        $municipality_id,
                        $crime_id,
                        null,
                        $noViolenceId
                    )
                ),
            ];
        }

        foreach ([$noViolenceId, $violenceId] as $violence_id) {
            $roboVehiculos[] = [
                'crime' => $violence_id === $violenceId ? 'CON VIOLENCIA' : 'SIN VIOLENCIA',
                'days'  => $this->sumDays(
                    $this->getStoredProcedureDelitos(
                        $year,
                        $month,
                        $municipality_id,
                        98,
                        null,
                        $violence_id
                    )
                ),
            ];
        }

        $otherRobosDays = $this->sumDays(
            $this->getGetOtherRobosProcedure($year, $month)
        );
        $otherRobosViolencia = $this->sumDays(
            $this->getGetOtherRobosProcedure($year, $month, $violenceId)
        );
        $otherRobosSinViolencia = $this->sumDays(
            $this->getGetOtherRobosProcedure($year, $month, $noViolenceId)
        );

        $totalOtherRobos = array_sum($otherRobosDays);
        $totalOtherRobosViolencia = array_sum($otherRobosViolencia);
        $totalOtherRobosSinViolencia = array_sum($otherRobosSinViolencia);

        // Agrupación de datos
        $crimeGrouper = new CrimeGrouperService();

        $groupedCrimes = $crimeGrouper->group([
            'robosViolencia'    => $robosViolencia,
            'robosSinViolencia' => $robosSinViolencia,
            'roboVehiculos'     => $roboVehiculos,
        ]);

        // Normalización de datos
        if (isset($groupedCrimes['ROBOS']['CON_VIOLENCIA']['subtipos'])) {
            $this->normalizeTranseunte(
                $groupedCrimes['ROBOS']['CON_VIOLENCIA']['subtipos']
            );
        }

        if (isset($groupedCrimes['ROBOS']['SIN_VIOLENCIA']['subtipos'])) {
            $this->normalizeTranseunte(
                $groupedCrimes['ROBOS']['SIN_VIOLENCIA']['subtipos']
            );
        }

        // Que sea un string " "
        $normalizeKey = fn($key) => strtoupper(str_replace(' ', '_', $key));

        $robosConViolencia = [];
        foreach ($groupedCrimes['ROBOS']['CON_VIOLENCIA']['subtipos'] as $name => $data) {
            $robosConViolencia[$normalizeKey($name)] = [
                'total' => $data['total'],
                'days'  => $data['days'] ?? [],
            ];
        }

        $robosConViolencia['ROBO_VEHICULOS'] = [
            'total' => $groupedCrimes['ROBO_VEHICULOS']['subtipos']['CON VIOLENCIA']['total'] ?? 0,
            'days'  => $groupedCrimes['ROBO_VEHICULOS']['subtipos']['CON VIOLENCIA']['days'] ?? [],
        ];

        $robosSinViolencia = [];
        foreach ($groupedCrimes['ROBOS']['SIN_VIOLENCIA']['subtipos'] as $name => $data) {
            $robosSinViolencia[$normalizeKey($name)] = [
                'total' => $data['total'],
                'days'  => $data['days'] ?? [],
            ];
        }

        $robosSinViolencia['ROBO_VEHICULOS'] = [
            'total' => $groupedCrimes['ROBO_VEHICULOS']['subtipos']['SIN VIOLENCIA']['total'] ?? 0,
            'days'  => $groupedCrimes['ROBO_VEHICULOS']['subtipos']['SIN VIOLENCIA']['days'] ?? [],
        ];

        $totalesPorRobo = [];
        foreach (
            array_unique(array_merge(
                array_keys($robosConViolencia),
                array_keys($robosSinViolencia)
            )) as $tipo
        ) {
            $totalesPorRobo[$tipo] =
                ($robosConViolencia[$tipo]['total'] ?? 0) +
                ($robosSinViolencia[$tipo]['total'] ?? 0);
        }

        $totalConViolencia =
            ($groupedCrimes['ROBOS']['CON_VIOLENCIA']['total'] ?? 0) +
            ($groupedCrimes['ROBO_VEHICULOS']['subtipos']['CON VIOLENCIA']['total'] ?? 0);
        $totalSinViolencia =
            ($groupedCrimes['ROBOS']['SIN_VIOLENCIA']['total'] ?? 0) +
            ($groupedCrimes['ROBO_VEHICULOS']['subtipos']['SIN VIOLENCIA']['total'] ?? 0);

        $totalRobos =
            $totalConViolencia +
            $totalSinViolencia +
            $totalOtherRobos;

        $totalConViolencia += $totalOtherRobosViolencia;
        $totalSinViolencia += $totalOtherRobosSinViolencia;
        return response()->json([
            'success' => true,
            'data' => [
                'total' => [
                    'total'         => $totalRobos,
                    'con_violencia' => $totalConViolencia,
                    'sin_violencia' => $totalSinViolencia,
                    'otros'         => $totalOtherRobos,
                    'otros_con_violencia' => $totalOtherRobosViolencia,
                    'otros_sin_violencia' => $totalOtherRobosSinViolencia,
                ],

                'robos_con_violencia' => [
                    'total'    => $totalConViolencia,
                    'subtipos' => $robosConViolencia,
                ],

                'robos_sin_violencia' => [
                    'total'    => $totalSinViolencia,
                    'subtipos' => $robosSinViolencia,
                ],
                'totales_por_robo' => $totalesPorRobo,
            ],
            'selected_month' => $month,
            'selected_year' => $year,
        ]);
    }

    private function normalizeTranseunte(array &$subtipos): void
    {
        $keys = [
            'ROBO A TRANSEUNTE',
            'ROBO A TRANSEUNTE EN VIA PUBLICA',
            'ROBO A TRANSEUNTE EN ESPACIO ABIERTO AL PUBLICO',
        ];

        $total = 0;
        $days  = [];

        foreach ($keys as $key) {
            if (!isset($subtipos[$key])) {
                continue;
            }

            $total += $subtipos[$key]['total'] ?? 0;

            foreach ($subtipos[$key]['days'] ?? [] as $day => $count) {
                $days[$day] = ($days[$day] ?? 0) + $count;
            }

            unset($subtipos[$key]);
        }

        // Reinsertar como un solo subtipo
        $subtipos['ROBO A TRANSEUNTE'] = [
            'total' => $total,
            'days'  => $days,
        ];
    }

    /**
     * Tarjetas Informativas
     */
    public function informativeCards(Request $request)
    {
        $year = $request->get('year');

        $query = Card::select('id', 'title', 'paper_id', 'date')
            ->orderBy('date', 'DESC');

        // Aplicar filtro por año si se proporciona
        if ($year) {
            $query->whereYear('date', $year);
        }

        $totalCards = $query->count();
        $cards = $query->take(20)->get();

        $data = [];
        foreach ($cards as $card) {
            $date = new \DateTime($card->date);
            $created = $date->format('d') . '-' . $this->month($date->format('n') - 1) . '-' . $date->format('Y');
            $data[] = [
                'id' => $card->id,
                'title' => $card->title,
                'paper' => $card->paper->name,
                'created' => $created,
            ];
        }

        $years = Card::distinct()
            ->pluck('date')
            ->filter()
            ->map(fn($date) => \Carbon\Carbon::parse($date)->year)
            ->unique()
            ->sort()
            ->reverse()
            ->values();

        return Inertia::render('TarjetasInformativas', [
            'initialProps' => $data,
            'totalCards' => $totalCards,
            'years_notes' => $years,
            'selectedYear' => $year, // Pasar el año seleccionado
        ]);
    }

    public function moreInformativeCards(Request $request)
    {
        $request->validate([
            'offset' => 'required',
            'year' => 'nullable|integer',
        ]);

        $offset = $request->input('offset');
        $year = $request->input('year');

        $query = Card::select('id', 'title', 'paper_id', 'date')
            ->orderBy('date', 'DESC');

        if ($year) {
            $query->whereYear('date', $year);
        }

        $cards = $query->offset($offset)
            ->take(20)
            ->get();

        $data = [];
        foreach ($cards as $card) {
            $date = new \DateTime($card->date);
            $created = $date->format('d') . '-' . $this->month($date->format('n') - 1) . '-' . $date->format('Y');
            $data[] = [
                'id' => $card->id,
                'title' => $card->title,
                'paper' => $card->paper->name,
                'created' => $created,
            ];
        }

        return response()->json([
            'cards' => $data,
        ]);
    }

    public function searchInformativeCards(Request $request)
    {
        $request->validate([
            'search' => 'required',
            'year' => 'nullable|integer' // Añadir año como parámetro opcional
        ]);

        $search = $request->input('search');
        $year = $request->input('year');
        $data = [];

        $query = "MATCH (title, commentary) AGAINST ('$search' IN NATURAL LANGUAGE MODE)";

        $cardsQuery = Card::whereRaw($query);

        // Aplicar filtro por año si se proporciona
        if ($year) {
            $cardsQuery->whereYear('date', $year);
        }

        $cards = $cardsQuery->orderBy('date', 'desc')->get();

        foreach ($cards as $card) {
            $date = new \DateTime($card->date);
            $created = $date->format('d') . '-' . $this->month($date->format('n') - 1) . '-' . $date->format('Y');
            $data[] = [
                'id' => $card->id,
                'title' => $card->title,
                'paper' => $card->paper->name,
                'created' => $created,
            ];
        }

        if (count($cards) > 0) {
            Log::info("Buscó tarjeta informativa: $search" . ($year ? " (año: $year)" : ""));
        }

        return response()->json([
            'cards' => $data,
        ]);
    }

    public function informativeCard($id)
    {
        $card = Card::findOrFail($id);
        $card->load('paper');

        $date = new \DateTime($card->date);
        $created = $date->format('d') . '-' . $this->month($date->format('n') - 1) . '-' . $date->format('Y');

        Log::info("Consultó tarjeta informativa: $card->title");

        return Inertia::render('TarjetaInformativa', [
            'card' => $card,
            'created' => $created,
        ]);
    }

    public function getFile($card_id)
    {
        $URI_FILE = 'http://172.23.5.230:8001/';

        try {
            $cardPath = File::select('path')->where('card_id', $card_id)->orderBy('created_at', 'DESC')->first();
            $path = $URI_FILE . $cardPath->path;
            $blob = file_get_contents($path);
        } catch (\Exception $e) {
            Log::error('Error al obtener PDF. ' . $e->getMessage());
            abort(500);
        }

        header('Content-type: application/pdf');
        header("Cache-Control: no-cache");
        header("Pragma: no-cache");
        header("Content-Disposition: inline;filename='document.pdf'");
        header("Content-length: " . strlen($blob));
        echo $blob;
        return false;
    }
    /**
     * Victimas
     */
    public function victimas()
    {
        $date = new \DateTime();
        $currentMonth = $date->format('m');
        $currentYear = $date->format('Y');

        return Inertia::render('Victimas', [
            'municipalities' => $this->municipalities(),
            'crimes' => $this->crimes(),
            'months' => $this->currentMonths(),
            'modalities' => $this->modalities(),
            'years' => $this->years(),
            'genders' => $this->genders(),
            'currentMonth' => [
                'value' => (int)$currentMonth,
                'label' => $this->month($currentMonth - 1),
            ],
            'currentYear' => $currentYear,
        ]);
    }

    public function getVictimas(Request $request)
    {
        $request->validate([
            'year' => 'required',
            'month' => 'required',
        ]);

        $state = [];
        $year = $request->input('year');
        $month = $request->input('month');
        $municipality_id = $request->input('municipality_id') ?? NULL;
        $crime_id = $request->input('crime_id') ?? NULL;
        $modality_id = $request->input('modality_id') ?? NULL;
        $gender_id = $request->input('gender_id') ?? NULL;

        $data = $this->getStoredProcedureOfendidos($year, $month, $municipality_id, $crime_id, $modality_id, $gender_id);

        if ($data) {
            $fiscalias = $this->fiscalias();
            $currentDate = new \DateTime();
            $currentMonth = $currentDate->format('m');
            $currentYear = $currentDate->format('Y');
            $toDay = null;

            if ($year == $currentYear && $month == $currentMonth) {
                $toDay = $currentDate->format('d');
            } else {
                $date = new \DateTime("$year-$month-1");
                $toDay = $date->format('t');
            }

            foreach ($fiscalias as $key => $arrFiscalias) {
                $municipalities = null;
                foreach ($data as $k => $d) {
                    if (in_array(rtrim($d->MUNICIPIO_HECHO), $arrFiscalias)) {
                        $days = [];
                        $daysToArray = (array) $d;
                        if (!$toDay) {
                            $toDay = count($daysToArray) - 1;
                        }
                        // Show only valid days depends on current month and year
                        for ($i = 1; $i <= $toDay; $i++) {
                            $days[$i] = $daysToArray[$i];
                        }

                        $municipalities[] = [
                            'name' => $d->MUNICIPIO_HECHO,
                            'days' => $days
                        ];
                        unset($data[$k]);
                    }
                }
                if ($municipalities) {
                    $state[] = [
                        'fiscalia' => 'FISCALÍA ' . $key,
                        'municipalities' => $municipalities,
                    ];
                }
            }
        }

        return response()->json($state);
    }
    /**
     * Details on click day
     */
    public function details(Request $request, $about)
    {
        $year = $request->input('year') ?? NULL;
        $month = $request->input('month') ?? NULL;
        $municipality_id = $request->input('municipality_id') ?? NULL;
        $crime_id = $request->input('crime_id') ?? NULL;
        $day = $request->input('day') ?? NULL;
        $todos_hom = $request->input('todos_hom') ?? NULL;
        if (!empty($request->input('municipality'))) {
            $municipality = Municipality::select('ID_MNCPIO')->where('MNCPIO', $request->input('municipality'))->first();
            $municipality_id = $municipality->ID_MNCPIO;
        }

        if ($about == 'carpetas') {
            $modality_id = $request->input('modality_id') ?? NULL;
            $violence_id = $request->input('violence_id') ?? NULL;
            $storedProcedure = 'EXEC DETALLE_POR_DIA_EXPEDIENTES ?, ?, ?, ?, ?, ?, ?, ?';
            $columns = array($year, $month, $municipality_id, $crime_id, $modality_id, $day, $violence_id, $todos_hom);
            $loggingMessage = 'Detalle de Carpetas: ';
        } elseif ($about == 'detenidos') {
            $corporation_id = $request->input('corporation_id') ?? NULL;
            $storedProcedure = 'EXEC DETALLE_POR_DIA_DETENIDOS ?, ?, ?, ?, ?, ?';
            $columns = array($year, $month, $municipality_id, $crime_id, $day, $corporation_id);
            $loggingMessage = 'Detalle de Detenidos: ';
        } elseif ($about == 'victimas') {
            $storedProcedure = 'EXEC DETALLE_POR_DIA_OFENDIDOS ?, ?, ?, ?, ?, ?, ?';
            $modality_id = $request->input('modality_id') ?? NULL;
            $gender_id = $request->input('gender_id') ?? NULL;
            $columns = array($year, $month, $municipality_id, $crime_id, $day, $modality_id, $gender_id);
            $loggingMessage = 'Detalle de Víctimas: ';
        } else {
            abort(404);
        }

        $data = DB::connection('sqlsrv')
            ->select(
                $storedProcedure,
                $columns
            );

        Log::info($loggingMessage . implode(" - ", $columns));

        return response()->json($data);
    }

    public function months($year)
    {
        return $this->currentMonths($year);
    }

    private function currentMonths($year = null)
    {
        $months = [];
        $date = new \DateTime();
        $initialMonth = 0;

        if ($year) {
            if ($year == $date->format('Y')) {
                $month = $date->format('n');
            } else {
                $month = 12;
            }
        } else {
            $month = $date->format('n');
        }

        if ($date->format('Y') == 2022 || $year == 2022) {
            $initialMonth = 9;
        }

        for ($i = $initialMonth; $i < $month; $i++) {
            $months[] = array(
                'value' => $i + 1,
                'label' => $this->month($i),
            );
        }

        return $months;
    }
    /**
     * Detenidos
     */
    public function detenidos()
    {
        $date = new \DateTime();
        $currentMonth = $date->format('m');
        $currentYear = $date->format('Y');

        return Inertia::render('Detenidos', [
            'municipalities' => $this->municipalities(),
            'corporations' => $this->corporations(),
            'crimes' => $this->crimes(),
            'months' => $this->currentMonths(),
            'years' => $this->years(),
            'currentMonth' => [
                'value' => (int)$currentMonth,
                'label' => $this->month($currentMonth - 1),
            ],
            'currentYear' => $currentYear,
        ]);
    }

    public function getDetenidos(Request $request)
    {
        $request->validate([
            'year' => 'required',
            'month' => 'required',
        ]);

        $state = [];
        $year = $request->input('year');
        $month = $request->input('month');
        $municipality_id = $request->input('municipality_id') ?? NULL;
        $crime_id = $request->input('crime_id') ?? NULL;
        $corporation_id = $request->input('corporation_id') ?? NULL;

        $data = $this->getStoredProcedureDetenidos($year, $month, $municipality_id, $crime_id, $corporation_id);

        if ($data) {
            $fiscalias = $this->fiscalias();
            $currentDate = new \DateTime();
            $currentMonth = $currentDate->format('m');
            $currentYear = $currentDate->format('Y');
            $toDay = null;

            if ($year == $currentYear && $month == $currentMonth) {
                $toDay = $currentDate->format('d');
            } else {
                $date = new \DateTime("$year-$month-1");
                $toDay = $date->format('t');
            }

            foreach ($fiscalias as $key => $arrFiscalias) {
                $municipalities = null;
                foreach ($data as $k => $d) {
                    if (in_array(rtrim($d->MUNICIPIO_HECHO), $arrFiscalias)) {
                        $days = [];
                        $daysToArray = (array) $d;
                        if (!$toDay) {
                            $toDay = count($daysToArray) - 1;
                        }
                        // Show only valid days depends on current month and year
                        for ($i = 1; $i <= $toDay; $i++) {
                            $days[$i] = $daysToArray[$i];
                        }

                        $municipalities[] = [
                            'name' => $d->MUNICIPIO_HECHO,
                            'days' => $days
                        ];
                        unset($data[$k]);
                    }
                }
                if ($municipalities) {
                    $state[] = [
                        'fiscalia' => 'FISCALÍA ' . $key,
                        'municipalities' => $municipalities,
                    ];
                }
            }
        }

        return response()->json($state);
    }
    /**
     * Generate Charts Data
     */
    public function chart(Request $request, $name)
    {
        $request->validate([
            'year' => 'required',
            'month' => 'required',
            'crime_id' => 'required'
        ]);

        $year = $request->input('year');
        $month = $request->input('month');
        $municipality_id = $request->input('municipality_id') ?? NULL;
        $crime_id = $request->input('crime_id') ?? NULL;
        $modality_id = $request->input('modality_id') ?? NULL;
        $gender_id = $request->input('gender_id') ?? NULL;

        $data = [];
        $result = [];
        $totalGeneral = 0;
        $title = '';
        $qCrimes = null;

        // ============================================================
        // ORDEN ESPECÍFICO PARA DELITOS DE ALTO IMPACTO
        // ============================================================
        $ordenAltoImpacto = [
            'HOMICIDIO DOLOSO',
            'FEMINICIDIO',
            'HOMICIDIO CULPOSO',
            'SECUESTRO',
            'EXTORSION',
            'ROBO DE VEHICULOS',
            'ROBO A NEGOCIO',
            'ROBO A TRANSEUNTE',
            'ROBO A CASA HABITACIÓN',
            'NARCOMENUDEO'
        ];

        if ($name == 'victimas') {
            if ($crime_id == 33) {
                $title = 'VÍCTIMAS IDENTIFICADAS POR EL DELITO DE ' . Crime::findOrFail($crime_id)->DLTO;
                $qGenders = Gender::whereIn('ID_SEXO', [1, 2])->orderBy('SEXO', 'ASC')->get();
                foreach ($qGenders as $gender) {
                    $total = 0;
                    $query = $this->getStoredProcedureOfendidos($year, $month, $municipality_id, $crime_id, $modality_id, $gender->ID_SEXO);
                    foreach ($query as $q) {
                        $flag = 0;
                        foreach ($q as $value) {
                            if ($flag > 0) {
                                $total += $value;
                            }
                            $flag++;
                        }
                    }

                    $totalGeneral += $total;

                    if ($total > 0) {
                        $data[] = [
                            'label' => $gender->SEXO,
                            'tooltip' => $gender->SEXO,
                            'total' => $total,
                            'percent' => '',
                        ];
                    }
                }
            }
        } elseif ($name == 'carpetas') {
            $roboSemovientesIds = [96, 114];
            if (in_array($crime_id, $roboSemovientesIds)) {
                $title = 'CARPETAS POR EL DELITO DE ROBO DE SEMOVIENTES';
                $qCrimes = Crime::whereIn('ID_DLTO', $roboSemovientesIds)->orderBy('DLTO', 'ASC')->get();
            } elseif ($crime_id == 'altoimpacto') {
                $altoImpactoIds = $this->altoImpactoIds();
                $title = 'CARPETAS DE INVESTIGACIÓN';

                // Obtener todos los delitos de alto impacto
                $crimes = Crime::whereIn('ID_DLTO', $altoImpactoIds)
                    ->orderBy('DLTO', 'ASC')
                    ->get();

                // ============================================================
                // 1. PRIMERO: Calcular el total de carpetas
                // ============================================================
                $queryTotal = $this->getStoredProcedureCarpetas($year, $month, $municipality_id, null, $modality_id);
                $totalCarpetas = 0;
                foreach ($queryTotal as $q) {
                    $flag = 0;
                    foreach ($q as $value) {
                        if ($flag > 0) {
                            $totalCarpetas += $value;
                        }
                        $flag++;
                    }
                }

                // ============================================================
                // 2. SEGUNDO: Recolectar datos de todos los delitos
                // ============================================================
                $crimeData = [];
                foreach ($crimes as $crime) {
                    $total = 0;
                    $query = $this->getStoredProcedureCarpetas($year, $month, $municipality_id, $crime->ID_DLTO, $modality_id);
                    foreach ($query as $q) {
                        $flag = 0;
                        foreach ($q as $value) {
                            if ($flag > 0) {
                                $total += $value;
                            }
                            $flag++;
                        }
                    }

                    // Guardar SOLO si el total es > 0 O es un delito de alto impacto
                    // (para mantener consistencia con la gráfica)
                    if ($total > 0 || in_array($crime->DLTO, $ordenAltoImpacto)) {
                        $crimeData[$crime->DLTO] = [
                            'label' => $crime->DLTO,
                            'tooltip' => $crime->DLTO,
                            'total' => $total,
                            'percent' => $totalCarpetas > 0 ? round(($total * 100) / $totalCarpetas, 2) : 0,
                        ];
                    }
                }

                // ============================================================
                // 3. TERCERO: Agregar HOMICIDIO DOLOSO y HOMICIDIO CULPOSO si no existen
                // ============================================================
                $modalityIds = [1, 2];
                $modalities = ['HOMICIDIO DOLOSO', 'HOMICIDIO CULPOSO'];

                foreach ($modalityIds as $index => $modId) {
                    if (!isset($crimeData[$modalities[$index]])) {
                        $total = 0;
                        $query = $this->getStoredProcedureCarpetas($year, $month, $municipality_id, 33, $modId);
                        foreach ($query as $q) {
                            $flag = 0;
                            foreach ($q as $value) {
                                if ($flag > 0) {
                                    $total += $value;
                                }
                                $flag++;
                            }
                        }

                        $crimeData[$modalities[$index]] = [
                            'label' => $modalities[$index],
                            'tooltip' => $modalities[$index],
                            'total' => $total,
                            'percent' => $totalCarpetas > 0 ? round(($total * 100) / $totalCarpetas, 2) : 0,
                        ];
                    }
                }

                // ============================================================
                // 4. CUARTO: ELIMINAR "HOMICIDIO" genérico si existe
                // ============================================================
                unset($crimeData['HOMICIDIO']);

                // ============================================================
                // 5. QUINTO: ORDENAR según el orden específico
                // ============================================================
                $sortedData = [];

                // Primero: agregar en el orden específico
                foreach ($ordenAltoImpacto as $nombreDelito) {
                    if (isset($crimeData[$nombreDelito])) {
                        $sortedData[] = $crimeData[$nombreDelito];
                        unset($crimeData[$nombreDelito]);
                    }
                }

                // Segundo: agregar el resto de delitos al final
                foreach ($crimeData as $datos) {
                    $sortedData[] = $datos;
                }

                $data = $sortedData;

                // Calcular total general para porcentajes
                foreach ($data as $item) {
                    $totalGeneral += $item['total'];
                }
            }
        }

        // ============================================================
        // 6. FINAL: Formatear resultado
        // ============================================================
        $flag = 0;
        foreach ($data as $d) {
            $flag++;
            $percent = 0;
            if ($totalGeneral > 0 && $d['total'] > 0) {
                $percent = round(($d['total'] * 100) / $totalGeneral, 2);
            }

            $result[] = [
                'id' => $flag,
                'label' => $d['label'],
                'tooltip' => $d['tooltip'],
                'total' => $d['total'],
                'percent' => $percent,
            ];
        }

        return response()->json([
            'data' => $result,
            'title' => $totalGeneral . ' ' . $title,
        ]);
    }

    public function lastUpdated()
    {
        $last_updated = 'No se pudo conectar con el servidor. Intente de nuevo en un momento.';
        $data = false;

        $data = DB::connection('sqlsrv')
            ->select("EXEC CONSULTA_ULTIMA_ACTUALIZACION_EXPEDIENTES");

        if ($data) {
            $date = new \DateTime($data[0]->FECHA);
            $last_updated = 'CIFRAS ACTUALIZADAS EL ' . $date->format('j') . ' DE ' . mb_strtoupper($this->month($date->format('m') - 1)) . ' DEL ' . $date->format('Y') . ' - ' . $date->format('g:i A');
        }
        return response()->json([
            'last_updated' => $last_updated,
        ]);
    }

    public function incidencia()
    {
        $date = new \DateTime();
        $currentMonth = $date->format('m');
        $currentYear = $date->format('Y');

        return Inertia::render(
            'Incidencia',
            [
                'municipalities' => $this->municipalities(),
                'months' => $this->currentMonths(),
                'years' => $this->years(),
                'currentMonth' => [
                    'value' => (int)$currentMonth,
                    'label' => $this->month($currentMonth - 1),
                ],
                'currentYear' => $currentYear,
            ]
        );
    }

    public function consultaFechas()
    {
        $date = new \DateTime();
        $currentMonth = $date->format('m');
        $currentYear = $date->format('Y');

        // * load municipalities geometries
        $path = public_path('tam-geometries.json');

        // Verificar si el archivo existe
        if (!file_exists($path)) {
            Log::error("El archivo no existe en: " . $path);
            $municipalitiesPolygons = []; // Array vacío como fallback
        } else {
            // Leer el archivo con file_get_contents
            $contents = file_get_contents($path);
            $jsonData = json_decode($contents, true)["features"];

            // * process the json data
            $municipalitiesPolygons = array();
            foreach ($jsonData as $element) {
                $municipalitiesGeomItem = array();
                $municipalitiesGeomItem["properties"] = $element["properties"];
                $geometryItems = array();
                foreach ($element["geometry"]["coordinates"][0] as $geomItem) {
                    array_push($geometryItems, array($geomItem[1], $geomItem[0]));
                }
                $municipalitiesGeomItem["geometry"] = $geometryItems;
                array_push($municipalitiesPolygons, $municipalitiesGeomItem);
            }

            foreach ($municipalitiesPolygons as &$value) {
                $value['center'] = $this->calculateCenter($value['geometry']);
            }
            unset($value);
        }
        return Inertia::render('IncidenciaCarpetasRangoFechas', [
            'municipalities' => $this->municipalities(),
            'crimes' => $this->crimes(),
            'months' => $this->currentMonths(),
            'modalities' => $this->modalities(),
            //'violences' => $this->violence(),
            'years' => $this->years(),
            'currentMonth' => [
                'value' => (int)$currentMonth,
                'label' => $this->month($currentMonth - 1),
            ],
            'currentYear' => $currentYear,
            'municipalitiesPolygons' => $municipalitiesPolygons, // Si necesitas pasar los polígonos a la vista
        ]);
    }

    public function mapaIncidencia()
    {
        $date = new \DateTime();
        $currentMonth = $date->format('m');
        $currentYear = $date->format('Y');

        // * load municipalities geometries
        $path = public_path('tam-geometries.json');

        // Verificar si el archivo existe
        if (!file_exists($path)) {
            Log::error("El archivo no existe en: " . $path);
            $municipalitiesPolygons = []; // Array vacío como fallback
        } else {
            // Leer el archivo con file_get_contents
            $contents = file_get_contents($path);
            $jsonData = json_decode($contents, true)["features"];

            // * process the json data
            $municipalitiesPolygons = array();
            foreach ($jsonData as $element) {
                $municipalitiesGeomItem = array();
                $municipalitiesGeomItem["properties"] = $element["properties"];
                $geometryItems = array();
                foreach ($element["geometry"]["coordinates"][0] as $geomItem) {
                    array_push($geometryItems, array($geomItem[1], $geomItem[0]));
                }
                $municipalitiesGeomItem["geometry"] = $geometryItems;
                array_push($municipalitiesPolygons, $municipalitiesGeomItem);
            }

            foreach ($municipalitiesPolygons as &$value) {
                $value['center'] = $this->calculateCenter($value['geometry']);
            }
            unset($value);
        }
        $crimes = $this->crimes();
        $filteredCrimes = array_filter($crimes, function ($crime) {
            return $crime['label'] !== 'Todos los delitos';
        });

        // O si quieres reindexar el array numéricamente
        $filteredCrimes = array_values($filteredCrimes);
        return Inertia::render('MapaIncidencia', [
            'municipalities' => $this->municipalities(),
            'crimes' => $filteredCrimes,
            'months' => $this->currentMonths(),
            'modalities' => $this->modalities(),
            'municipalitiesForCoordination' => $this->muncipalitiesForCoordination(),
            'years' => $this->years(),
            'currentMonth' => [
                'value' => (int)$currentMonth,
                'label' => $this->month($currentMonth - 1),
            ],
            'currentYear' => $currentYear,
            'municipalitiesPolygons' => $municipalitiesPolygons, // Si necesitas pasar los polígonos a la vista
        ]);
    }

    function calculateCenter($coordinates)
    {
        $latSum = 0;
        $lonSum = 0;

        foreach ($coordinates as $coord) {
            $latSum += $coord[0];
            $lonSum += $coord[1];
        }

        $center = [$latSum / count($coordinates), $lonSum / count($coordinates)];

        return $center;
    }

    /**
     * Obtener solo datos con coordenadas válidas (consulta principal)
     */
    public function getMapData(Request $request)
    {
        $request->validate([
            'startDate' => 'required|date',
            'endDate'   => 'required|date',
        ]);

        $start_date = Carbon::parse($request->startDate)->format('Ymd');
        $end_date   = Carbon::parse($request->endDate)->format('Ymd');

        $municipality_id = $request->input('municipality_id');
        $crime_id        = $request->input('crime_id');
        $coordinacion_id = $request->input('coordinacion_id');

        try {
            // Solo obtener datos con coordenadas válidas
            $data = $this->getStoredProcedureGetMapData(
                $start_date,
                $end_date,
                $municipality_id,
                $crime_id,
            );

            return response()->json([
                'valid' => $data,
                'meta' => [
                    'total_valid' => count($data),
                    'has_invalid' => true,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error en getMapData:', [
                'error' => $e->getMessage(),
                'start_date' => $start_date,
                'end_date' => $end_date,
            ]);

            return response()->json([
                'error' => 'Error al obtener los datos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Endpoint separado para obtener datos sin coordenadas válidas
     */
    public function getInvalidCoords(Request $request)
    {
        $request->validate([
            'startDate' => 'required|date',
            'endDate'   => 'required|date',
        ]);

        $start_date = Carbon::parse($request->startDate)->format('Ymd');
        $end_date   = Carbon::parse($request->endDate)->format('Ymd');

        $municipality_id = $request->input('municipality_id');
        $crime_id        = $request->input('crime_id');
        $coordinacion_id = $request->input('coordinacion_id');

        try {
            $invalidData = $this->getStoredProcedureNoValidCoords(
                $start_date,
                $end_date,
                $municipality_id,
                $crime_id,
            );

            return response()->json([
                'invalid' => $invalidData,
                'meta' => [
                    'total_invalid' => count($invalidData),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener datos sin coordenadas:', [
                'error' => $e->getMessage(),
                'start_date' => $start_date,
                'end_date' => $end_date,
                'municipality_id' => $municipality_id,
                'crime_id' => $crime_id
            ]);

            return response()->json([
                'error' => 'Error al obtener los datos: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getStoredProcedureGetMapData(
        string $start_date,
        string $end_date,
        ?int $municipality_id = null,
        ?int $crime_id = null,
    ) {
        try {
            DB::connection('sqlsrv')->statement('SET LOCK_TIMEOUT 300000');
            DB::connection('sqlsrv')->statement('SET QUERY_GOVERNOR_COST_LIMIT 0');

            return DB::connection('sqlsrv')->select(
                'EXEC DETALLE_EXPEDIENTES_COORDENADAS ?, ?, ?, ?',
                [
                    $start_date,
                    $end_date,
                    $municipality_id,
                    $crime_id,
                ]
            );
        } catch (\Exception $e) {
            Log::error('Error en getStoredProcedureGetMapData:', [
                'error' => $e->getMessage(),
                'start_date' => $start_date,
                'end_date' => $end_date,
            ]);
            return [];
        }
    }

    public function getStoredProcedureNoValidCoords(
        string $start_date,
        string $end_date,
        ?int $municipality_id = null,
        ?int $crime_id = null,
    ) {
        try {
            DB::connection('sqlsrv')->statement('SET LOCK_TIMEOUT 300000');
            DB::connection('sqlsrv')->statement('SET QUERY_GOVERNOR_COST_LIMIT 0');

            return DB::connection('sqlsrv')->select(
                'EXEC DETALLE_EXPEDIENTES_COORDENADAS_NO_VALIDAS ?, ?, ?, ?',
                [
                    $start_date,
                    $end_date,
                    $municipality_id,
                    $crime_id,
                ]
            );
        } catch (\Exception $e) {
            Log::error('Error en getStoredProcedureNoValidCoords:', [
                'error' => $e->getMessage(),
                'start_date' => $start_date,
                'end_date' => $end_date,
            ]);
            return [];
        }
    }

    public function getStoredProcedureGetMapColonies(Request $request)
    {
        $start_date = $request->input('start_date');
        $end_date = $request->input('end_date');
        $municipality_id = $request->input('municipality_id');
        $crime_id = $request->input('crime_id');

        try {
            $start_clean = $start_date ? str_replace('-', '', $start_date) : null;
            $end_clean = $end_date ? str_replace('-', '', $end_date) : null;

            DB::connection('sqlsrv')->statement('SET LOCK_TIMEOUT 300000');

            $result = DB::connection('sqlsrv')->select(
                "DECLARE @start DATETIME = CONVERT(DATETIME, ?);
                 DECLARE @end DATETIME = CONVERT(DATETIME, ?);
                 EXEC DETALLE_EXPEDIENTES_COORDENADAS_COLONIAS @start, @end, ?, ?",
                [
                    $start_clean,
                    $end_clean,
                    $municipality_id,
                    $crime_id,
                ]
            );

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Error al ejecutar el stored procedure de colonias:', [
                'error' => $e->getMessage(),
                'start_date' => $start_date,
                'end_date' => $end_date,
                'municipality_id' => $municipality_id,
                'crime_id' => $crime_id
            ]);

            return response()->json([
                'error' => 'Error al obtener los datos: ' . $e->getMessage()
            ], 500);
        }
    }

    // Método para obtener datos con chunking para rangos grandes
    public function getMapDataChunked(Request $request)
    {
        $request->validate([
            'startDate' => 'required|date',
            'endDate'   => 'required|date',
        ]);

        $start_date = Carbon::parse($request->startDate);
        $end_date = Carbon::parse($request->endDate);

        // Limitar el rango máximo a 2 años
        if ($start_date->diffInMonths($end_date) > 24) {
            return response()->json([
                'error' => 'El rango de fechas no puede superar los 2 años'
            ], 400);
        }

        $municipality_id = $request->input('municipality_id');
        $crime_id = $request->input('crime_id');
        $coordinacion_id = $request->input('coordinacion_id');

        try {
            $data = $this->getStoredProcedureGetMapDataOptimized(
                $start_date,
                $end_date,
                $municipality_id,
                $crime_id,
            );

            return response()->json([
                'valid' => $data,
                'meta' => [
                    'total_valid' => count($data),
                    'has_invalid' => true,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error en getMapDataChunked:', [
                'error' => $e->getMessage(),
                'start_date' => $start_date->format('Y-m-d'),
                'end_date' => $end_date->format('Y-m-d'),
            ]);

            return response()->json([
                'error' => 'Error al obtener los datos: ' . $e->getMessage()
            ], 500);
        }
    }

    private function getStoredProcedureGetMapDataOptimized(
        Carbon $start_date,
        Carbon $end_date,
        ?int $municipality_id = null,
        ?int $crime_id = null,
    ): array {
        $allResults = [];
        $chunkSize = 30;
        $totalDays = $start_date->diffInDays($end_date);

        if ($totalDays <= $chunkSize) {
            return $this->getStoredProcedureGetMapData(
                $start_date->format('Ymd'),
                $end_date->format('Ymd'),
                $municipality_id,
                $crime_id
            );
        }

        $currentDate = clone $start_date;

        while ($currentDate->lte($end_date)) {
            $chunkEnd = (clone $currentDate)->addDays($chunkSize - 1);
            if ($chunkEnd->gt($end_date)) {
                $chunkEnd = clone $end_date;
            }

            try {
                $chunkResults = $this->getStoredProcedureGetMapData(
                    $currentDate->format('Ymd'),
                    $chunkEnd->format('Ymd'),
                    $municipality_id,
                    $crime_id
                );

                $allResults = array_merge($allResults, $chunkResults);

                Log::info('Chunk procesado', [
                    'start' => $currentDate->format('Y-m-d'),
                    'end' => $chunkEnd->format('Y-m-d'),
                    'records' => count($chunkResults),
                    'total' => count($allResults)
                ]);
            } catch (\Exception $e) {
                Log::error('Error en chunk', [
                    'start' => $currentDate->format('Y-m-d'),
                    'end' => $chunkEnd->format('Y-m-d'),
                    'error' => $e->getMessage()
                ]);
            }

            $currentDate = (clone $chunkEnd)->addDay();
        }

        return $this->removeDuplicates($allResults);
    }

    private function removeDuplicates(array $data): array
    {
        $unique = [];
        $seen = [];

        foreach ($data as $item) {
            $key = $item->EXPEDIENTE ?? null;

            if ($key && !isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $item;
            } elseif (!$key) {
                $unique[] = $item;
            }
        }

        return $unique;
    }

    public function getIncidenciaRangoFechas(Request $request)
    {
        $request->validate([
            'startDate' => 'required|date',
            'endDate'   => 'required|date',
        ]);

        $start_date = Carbon::parse($request->startDate)->format('Ymd');
        $end_date   = Carbon::parse($request->endDate)->format('Ymd');

        $municipality_id = $request->input('municipality_id');
        $crime_id        = $request->input('crime_id');
        $modality_id     = $request->input('modality_id');
        $grado      = $request->input('grado');
        $todos_hom  = $request->input('todos_hom');
        $violence_id   = $request->input('violence_id');
        $data = $this->getStoredProcedureDelitosRangoFechas(
            $start_date,
            $end_date,
            $municipality_id,
            $crime_id,
            $modality_id,
            $violence_id,
            $grado,
            $todos_hom
        );

        return response()->json($data);
    }

    public function getStoredProcedureDelitosRangoFechas(
        string $start_date,
        string $end_date,
        ?int $municipality_id = null,
        ?int $crime_id = null,
        ?int $modality_id = null,
        $violence_id = NULL,
        $grado = NULL,
        $todos_hom = NULL
    ) {
        return DB::connection('sqlsrv')->select(
            'EXEC CONTEOS_POR_MUNICIPIO_HECHO_ACUMULADO_COOR_RANGO_FECHAS ?, ?, ?, ?, ?, ?, ?, ?',
            [
                $start_date,
                $end_date,
                $municipality_id,
                $crime_id,
                $modality_id,
                $violence_id,
                $grado,
                $todos_hom
            ]
        );
    }

    public function showBusquedaExpediente()
    {
        return Inertia::render('BusquedaExpediente', [
            'municipalities' => $this->municipalities(),
            'crimes' => $this->crimes(),
            'months' => $this->currentMonths(),
            'years' => $this->years(),
            'modalities' => $this->modalities(),
            'unidades' => $this->unidades(),
            'coordinaciones' => $this->coordinaciones(),
        ]);
    }


    public function searchExpediente(Request $request)
    {
        $request->validate([
            'expediente' => 'required|string',
            'coordinacion_id' => 'nullable|integer',
        ]);

        $expediente = $request->input('expediente');
        $coordinacionId = $request->input('coordinacion_id');

        try {
            $data = DB::connection('sqlsrv')->select(
                'EXEC BUSQUEDA_POR_EXPEDIENTES ?, ?',
                [$expediente, $coordinacionId ?? null]
            );

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Error en búsqueda de expedientes: ' . $e->getMessage());
            return response()->json(['error' => 'Error en la búsqueda'], 500);
        }
    }


    public function showBusquedaPartes()
    {
        return Inertia::render('BusquedaPartes', [
            'municipalities' => $this->municipalities(),
            'crimes' => $this->crimes(),
            'months' => $this->currentMonths(),
            'years' => $this->years(),
            'modalities' => $this->modalities(),
        ]);
    }

    public function searchPartes(Request $request)
    {
        $request->validate([
            'parte' => 'required',
        ]);

        $parte = $request->input('parte');

        $data = DB::connection('sqlsrv')->select(
            'EXEC BUSQUEDA_POR_NOMBRE ?',
            [
                $parte,
            ]
        );

        return response()->json($data);
    }


    public function searchPartesDetails(Request $request)
    {
        $request->validate([
            'idCarpeta' => 'required|string',
            'idUnidad' => 'required|integer',
        ]);

        $idCarpeta = $request->input('idCarpeta');
        $idUnidad = $request->input('idUnidad');
        $data = DB::connection('sqlsrv')->select(
            'EXEC DETALLE_BUSQUEDA_EXPEDIENTE ?, ?',
            [
                $idCarpeta,
                $idUnidad
            ]
        );

        return response()->json($data);
    }


    /**
     * Excel Report
     */
    public function getIncidencia(Request $request)
    {
        $request->validate([
            'year' => 'required',
            'month' => 'required',
        ]);

        $state = [];
        $year = $request->input('year');
        $month = $request->input('month');
        $municipality_id = $request->input('municipality_id') ?? NULL;
        $currentDate = new \DateTime();
        $currentMonth = $currentDate->format('m');
        $currentYear = $currentDate->format('Y');
        $state = array();
        $altoImpactoIds = $this->altoImpactoIds();
        $violenceId = 1;
        $noViolenceId = 0;
        $doloso = 1;
        $culposo = 2;
        $homicidioId = 33;
        $secuestroId = 48;
        $extorsionId = 88;
        $violacionId = 43;
        $lesionesId = 15;
        $violenciaFamiliarId = 94;
        $narcomenudeoId = 145;

        if ($year == $currentYear && $month == $currentMonth) {
            $toDay = $currentDate->format('d');
        } else {
            $date = new \DateTime("$year-$month-1");
            $toDay = $date->format('t');
        }

        // Total de Carpetas Iniciadas
        $getAllCrimes = $this->getStoredProcedureDelitos($year, $month, NULL, NULL, NULL, NULL);
        $days[] = $this->sumDays($getAllCrimes);
        $totalCarpetas = $this->mergeDays($days);

        // Total Carpetas de Alto Impacto
        $days = [];
        foreach ($altoImpactoIds as $crime_id) {
            // Year, month, municipality_id, crime_id, modality_id, violence_id
            $total = $this->getStoredProcedureDelitos($year, $month, $municipality_id, $crime_id, NULL, NULL);
            $days[] = $this->sumDays($total);
        }
        $totalCarpetasAltoImpacto = $this->mergeDays($days);

        // Robo con Violencia
        $daysViolencia = [];
        $robosViolencia = [];
        foreach ($this->roboIds() as $crime_id) {
            $total = $this->getStoredProcedureDelitos($year, $month, $municipality_id, $crime_id, NULL, $violenceId);
            $crime = Crime::select('DLTO')->where('ID_DLTO', $crime_id)->first();
            $robosViolencia[] = [
                'crime' => $crime->DLTO,
                'days' => $this->sumDays($total),
            ];
            $daysViolencia[] = $this->sumDays($total);
        }

        // Otros robos con violencia
        $daysOtros = [];
        foreach ($this->otrosRobosIds() as $crime_id) {
            $total = $this->getStoredProcedureDelitos($year, $month, $municipality_id, $crime_id, NULL, $violenceId);
            $daysViolencia[] = $this->sumDays($total);
            $daysOtros[] = $this->sumDays($total);
        }

        $totalOtrosRobosConViolencia  = $this->mergeDays($daysOtros);
        $totalRoboConViolencia = $this->mergeDays($daysViolencia);

        // Robo sin violencia
        $days = [];
        $robosSinViolencia = [];
        foreach ($this->roboIds() as $crime_id) {
            $total = $this->getStoredProcedureDelitos($year, $month, $municipality_id, $crime_id, NULL, $noViolenceId);
            $crime = Crime::select('DLTO')->where('ID_DLTO', $crime_id)->first();
            $robosSinViolencia[] = [
                'crime' => $crime->DLTO,
                'days' => $this->sumDays($total),
            ];
            $days[] = $this->sumDays($total);
        }
        // $totalRoboSinViolencia = $this->mergeDays($days);

        // Otros robos sin violencia
        $daysOtros = [];
        foreach ($this->otrosRobosIds() as $crime_id) {
            $total = $this->getStoredProcedureDelitos($year, $month, $municipality_id, $crime_id, NULL, $noViolenceId);
            $days[] = $this->sumDays($total);
            $daysOtros[] = $this->sumDays($total);
        }

        $totalOtrosRobosSinViolencia  = $this->mergeDays($daysOtros);
        $totalRoboSinViolencia = $this->mergeDays($days);

        // Robo de vehiculos
        $days = [];
        foreach ([$noViolenceId, $violenceId] as $violence_id) {
            $total = $this->getStoredProcedureDelitos($year, $month, $municipality_id, 98, NULL, $violence_id);
            $roboVehiculos[] = [
                'crime' => ($violence_id == $violenceId) ? 'CON VIOLENCIA' : 'SIN VIOLENCIA',
                'days' => $this->sumDays($total),
            ];
            $days[] = $this->sumDays($total);
        }
        $totalRoboVehiculos = $this->mergeDays($days);

        // Homicidio Doloso
        foreach ([$doloso, $culposo] as $modality_id) {
            $total = $this->getStoredProcedureDelitos($year, $month, $municipality_id, $homicidioId, $modality_id, NULL);
            $homicidios[] = [
                'crime' => ($modality_id == $doloso) ? 'HOMICIDIO DOLOSO' : 'HOMICIDIO CULPOSO',
                'days' => $this->sumDays($total),
            ];
        }

        // Secuestros
        $totalSecuestros = $this->getStoredProcedureDelitos($year, $month, $municipality_id, $secuestroId, NULL, NULL);

        // Extorsion
        $totalExtorsion = $this->getStoredProcedureDelitos($year, $month, $municipality_id, $extorsionId, NULL, NULL);

        //violacion
        $totalViolacion = $this->getStoredProcedureDelitos($year, $month, $municipality_id, $violacionId, NULL, NULL);

        // lesiones dolosas
        $totalLesiones = $this->getStoredProcedureDelitos($year, $month, $municipality_id, $lesionesId, $doloso, NULL);

        // Violencia familiar
        $totalViolenciaFamiliar = $this->getStoredProcedureDelitos($year, $month, $municipality_id, $violenciaFamiliarId, NULL, NULL);

        // NarcoMenudeo
        $totalNarcomenudeo = $this->getStoredProcedureDelitos($year, $month, $municipality_id, $narcomenudeoId, NULL, NULL);

        $state = [
            'totalCarpetas' => $totalCarpetas,
            'totalCarpetasAltoImpacto' => $totalCarpetasAltoImpacto,

            'robosViolencia' => $robosViolencia,
            'totalOtrosRobosConViolencia' =>  $totalOtrosRobosConViolencia,
            'totalRoboConViolencia' => $totalRoboConViolencia,

            'totalRoboSinViolencia' => $totalRoboSinViolencia,
            'robosSinViolencia' => $robosSinViolencia,
            'totalOtrosRobosSinViolencia' => $totalOtrosRobosSinViolencia,

            'totalRoboVehiculos' => $totalRoboVehiculos,
            'roboVehiculos' => $roboVehiculos,

            'homicidios' => $homicidios,
            'secuestros' => $this->sumDays($totalSecuestros),
            'extorsion' => $this->sumDays($totalExtorsion),
            'violacion' => $this->sumDays($totalViolacion),
            'lesiones' => $this->sumDays($totalLesiones),
            'violenciaFamiliar' => $this->sumDays($totalViolenciaFamiliar),
            'narcomenudeo' => $this->sumDays($totalNarcomenudeo),
        ];

        $nameMonth = $this->month($month - 1);
        $date = strtoupper($nameMonth) . ' DE ' . $year;
        // Create Excel
        $report = new ExcelController($state, $date);
        $report->create();

        return response()->json($state);
    }
    /**
     * Private functiones
     */
    private function altoImpactoIds()
    {
        return [
            33, // Homicidio (doloso y culposo)
            146, // Feminicidio
            48, // Secuestro
            88, // Extorsion
            98, // Robo de vehiculo
            100, // Robo de negocio
            97, // Robo transeunte
            92, // Robo domiciliario
            //43, // Violacion
            145, // Narcomenudeo
        ];
    }

    private function corporations()
    {
        $corporations[] = [
            'value' => '',
            'label' => 'Todas las corporaciones'
        ];
        $dataCorps = Corporation::where('ID_PUSO_DISPOSICION', '!=', NULL)->distinct()->orderBy('PUSO_DISPOSICION')->get();
        foreach ($dataCorps as $corp) {
            $corporations[] = [
                'value' => $corp->ID_PUSO_DISPOSICION,
                'label' => $corp->PUSO_DISPOSICION,
            ];
        }
        return $corporations;
    }

    private function crimes()
    {
        $crimes[] = [
            'value' => '',
            'label' => 'Todos los delitos'
        ];

        $dataCrimes = Crime::select('ID_DLTO', 'DLTO')
            ->where('ID_DLTO', '!=', 0)
            ->whereNotIn('ID_DLTO', [95, 134, 143, 147, 159, 160, 184, 185, 89, 183, 96, 114, 218, 216, 138, 168, 118])
            ->orderBy('DLTO', 'ASC')
            ->get();

        // New structure crimes for react-select library
        foreach ($dataCrimes as $crime) {
            $crimes[] = [
                'value' => $crime->ID_DLTO,
                'label' => $crime->DLTO,
            ];
        }
        return $crimes;
    }

    private function unidades()
    {
        $dataUnidades = Unidad::where('ID_UNDD', '!=', 0)
            ->where('UNDD_ACTVO', 1)
            ->orderBy('ALIAS', 'ASC')
            ->get();

        // New structure crimes for react-select library
        foreach ($dataUnidades as $unidad) {
            $unidades[] = [
                'value' => $unidad->ID_UNDD,
                'label' => $unidad->ALIAS,
            ];
        }
        return $unidades;
    }


    private function coordinaciones()
    {
        $coordinaciones = [];
        $dataCoordinaciones = Coordination::where('ID_COORDINACION', '!=', 0)
            ->where('ACTIVO', 1)
            ->orderBy('COORDINACION', 'ASC')
            ->get();

        // New structure crimes for react-select library
        foreach ($dataCoordinaciones as $coordinacion) {
            $coordinaciones[] = [
                'value' => $coordinacion->ID_COORDINACION,
                'label' => preg_replace('/^Fiscalia de Distrito\s*(de\s+)?/i', '', $coordinacion->COORDINACION),
            ];
        }
        return $coordinaciones;
    }

    private function fiscalias()
    {
        $fiscalias['MANTE'] = [
            'ALDAMA',
            'ANTIGUO MORELOS',
            'LLERA',
            'GOMEZ FARIAS',
            'MANTE,EL',
            'NUEVO MORELOS',
            'OCAMPO',
            'XICOTENCATL',
            'GONZALEZ',
        ];
        $fiscalias['ZONA SUR'] = [
            'ALTAMIRA',
            'CIUDAD MADERO',
            'TAMPICO',
        ];
        $fiscalias['NUEVO LAREDO'] = array(
            'NUEVO LAREDO'
        );
        $fiscalias['REYNOSA'] = array(
            'CAMARGO',
            'GUSTAVO DIAZ ORDAZ',
            'GUERRERO',
            'MIER',
            'MIGUEL ALEMAN',
            'REYNOSA',
            'RIO BRAVO',
        );
        $fiscalias['MATAMOROS'] = [
            'BURGOS',
            'CRUILLAS',
            'MATAMOROS',
            'MENDEZ',
            'SAN FERNANDO',
            'VALLE HERMOSO',
        ];
        $fiscalias['VICTORIA'] = [
            'ABASOLO',
            'BUSTAMANTE',
            'CASAS',
            'GUEMEZ',
            'HIDALGO',
            'JAUMAVE',
            'JIMENEZ',
            'MAINERO',
            'MIQUIHUANA',
            'PADILLA',
            'PALMILLAS',
            'SAN CARLOS',
            'SAN NICOLAS',
            'SOTO LA MARINA',
            'TULA',
            'VICTORIA',
            'VILLAGRAN',
        ];
        return $fiscalias;
    }

    private function genders()
    {
        $genders[] = [
            'value' => '',
            'label' => 'Todos los sexos'
        ];
        $qGenders = Gender::whereIn('ID_SEXO', [1, 2])->orderBy('SEXO', 'ASC')->get();
        foreach ($qGenders as $gender) {
            $genders[] = [
                'value' => $gender->ID_SEXO,
                'label' => $gender->SEXO,
            ];
        }
        return $genders;
    }

    private function getStoredProcedureCarpetas($year, $month, $municipality_id = NULL, $crime_id = NULL, $modality_id = NULL, $violence_id = NULL, $grado = NULL, $todos_hom = NULL)
    {
        // Stored procedure: Year, Month, municipality_id, crime_id
        $data = DB::connection('sqlsrv')
            ->select(
                "EXEC CONTEOS_POR_DIA_Y_ACUM ?, ?, ?, ?, ?, ?, ?, ?",
                array($year, $month, $municipality_id, $crime_id, $modality_id, $violence_id, $grado, $todos_hom)
            );
        return $data;
    }

    private function getStoredProcedureDetenidos($year, $month, $municipality_id = NULL, $crime_id = NULL, $corporation_id = NULL)
    {
        // Stored procedure: Year, Month, municipality_id, crime_id
        $data = DB::connection('sqlsrv')
            ->select(
                "EXEC CONTEOS_POR_DIA_Y_ACUM_DETENIDOS ?, ?, ?, ?, ?",
                array($year, $month, $municipality_id, $crime_id, $corporation_id)
            );
        return $data;
    }

    private function getStoredProcedureDelitos($year, $month, $municipality_id = NULL, $crime_id = NULL, $modality_id = NULL, $violence_id = NULL)
    {
        // Stored procedure: Year, Month, municipality_id, crime_id
        $data = DB::connection('sqlsrv')
            ->select(
                "EXEC CONTEOS_POR_DIA_Y_ACUM_DELITOS ?, ?, ?, ?, ?, ?",
                array($year, $month, $municipality_id, $crime_id, $modality_id, $violence_id)
            );
        return $data;
    }


    private function getGetOtherRobosProcedure($year, $month, $violence_id = NULL)
    {
        // Stored procedure: Year, Month, municipality_id, crime_id
        $data = DB::connection('sqlsrv')
            ->select(
                "EXEC CONTEOS_POR_DIA_Y_ACUM_DELITOS_RESTO_ROBOS ?, ?, ?",
                array($year, $month, $violence_id)
            );
        return $data;
    }


    private function getStoredProcedureOfendidos($year, $month, $municipality_id = NULL, $crime_id = NULL, $modality_id = NULL, $gender_id = NULL)
    {
        // Stored procedure: Year, Month, municipality_id, crime_id
        $data = DB::connection('sqlsrv')
            ->select(
                "EXEC CONTEOS_POR_DIA_Y_ACUM_OFENDIDOS ?, ?, ?, ?, ?, ?",
                array($year, $month, $municipality_id, $crime_id, $modality_id, $gender_id)
            );
        return $data;
    }

    public function impacto($year, $month, $crime_id, Request $request)
    {
        $grado = $request->query('grado'); // puede ser null
        $modalidad_id = $request->query('modalidad_id');
        return DB::connection('sqlsrv')->select(
            'EXEC DETALLE_POR_ESTADO_DELITO_EXPEDIENTES ?, ?, ?, ?,?',
            [
                (int) $year,
                (int) $month,
                (int) $crime_id,
                $grado,
                $modalidad_id
            ]
        );
    }


    private function municipalities()
    {
        $municipalities[] = [
            'value' => '',
            'label' => 'Todos los municipios'
        ];
        $dataMunicipalities = Municipality::select('ID_MNCPIO', 'MNCPIO')->orderBy('MNCPIO', 'ASC')->get();
        // New structure municipalities for react-select library
        foreach ($dataMunicipalities as $municipality) {
            $municipalities[] = [
                'value' => $municipality->ID_MNCPIO,
                'label' => $municipality->MNCPIO,
            ];
        }
        return $municipalities;
    }

    private function muncipalitiesForCoordination()
    {
        $municipalities[] = [
            'value' => '',
            'label' => 'Todos los municipios'
        ];

        $dataMunicipalities = MunicipalityCoordination::select('ID_MNCPIO', 'ID_COORDINACION', 'MNCPIO')->orderBy('MNCPIO', 'ASC')->get();
        // New structure municipalities for react-select library
        foreach ($dataMunicipalities as $municipality) {
            $municipalities[] = [
                'value' => $municipality->ID_MNCPIO,
                'label' => $municipality->MNCPIO,
                'coordination_id' => $municipality->ID_COORDINACION,
            ];
        }
        return $municipalities;
    }

    private function modalities()
    {
        $modalities[] = [
            'value' => '',
            'label' => 'Todas las modalidades'
        ];
        $datamodalities = Modality::select('ID_MDLDD', 'MDLDD')->orderBy('MDLDD', 'ASC')->get();
        // New structure modalities for react-select library
        foreach ($datamodalities as $modality) {
            $modalities[] = [
                'value' => $modality->ID_MDLDD,
                'label' => $modality->MDLDD,
            ];
        }
        return $modalities;
    }

    private function violence()
    {
        $violence[] = [
            'value' => '',
            'label' => 'Todos'
        ];
        $dataViolence = Violence::select('ID_VIOLENCIA', 'VIOLENCIA')->whereIn('ID_VIOLENCIA', [0, 1])->orderBy('VIOLENCIA', 'ASC')->get(); // Replace [1, 2, 3] with actual IDs if needed
        // New structure violence for react-select library
        foreach ($dataViolence as $violence) {
            $violence[] = [
                'value' => $violence->ID_VIOLENCIA,
                'label' => ($violence->ID_VIOLENCIA == 1) ? 'Con violencia' : 'Sin violencia'
            ];
        }
        return $violence;
    }

    private function month($index)
    {
        $months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        return $months[$index];
    }

    private function mergeDays($arrays)
    {
        return array_reduce($arrays, function ($carry, $item) {
            foreach ($item as $clave => $valor) {
                if (array_key_exists($clave, $carry)) {
                    $carry[$clave] += $valor;
                } else {
                    $carry[$clave] = $valor;
                }
            }
            return $carry;
        }, []);
    }

    private function robosIncidenciaIds()
    {
        return [
            107, // Robo simple
            92, // Casa habitacion
            100, //Negocios
            97, // Transeunte
            184,
            185,
        ];
    }


    private function roboIds()
    {
        return [
            92, // Casa habitacion
            100, //Negocios
            113, //Transporte de carga
            112, // Transporte de pasajeros
            97, // Transeunte
            99, // Instituciones Bancarias
            200, // A ganado
            105, // A escuelas
            107, // Robo simple
        ];
    }

    private function otrosRobosIds()
    {

        return [
            107, // Robo simple
            // 20, // Robo tentativa
            101, // Robo a industrias
            102, // Robo a gasolineras
            103, // Robo con  escalonamiento
            104, // Robo a lugar cerrado
            106, // Robo a hospitales
            108, // Robo con violencia
            119, // Robo de templos (iglesias)
            118, // Robo a tiendas de autoservicio
            117, // Robo a instituciones de credito
            116, // Robo en despoblado
            115, // Robo apoderamiento o destruccion de expedientes (oficina o archivos publicos)
            120, // Robo de cableado
            150, // Robo de identidad
            // 147, // El que resulte vehiculo recuperado (sin reporte de robo)
            172, // Robo de autopartes
        ];
    }

    private function sumDays($data)
    {
        $days = [];

        foreach ($data as $d) {
            foreach ($d as $i => $day) {
                if (array_key_exists($i, $days) && isset($day)) {
                    $days[$i] += intval($day);
                } else {
                    $days[$i] = intval($day);
                }
            }
        }

        unset($days['DELITO']);
        return $days;
    }

    private function years()
    {
        $currentYear = now()->year;

        // Si el usuario es administrador (level = 1)
        if (Auth::check() && Auth::user()->level == 1) {
            return [
                ['value' => $currentYear],
            ];
        }

        // Para usuarios normales
        $INITIAL_YEAR = 2025;
        $years = [];

        for ($year = $currentYear; $year >= $INITIAL_YEAR; $year--) {
            $years[] = [
                'value' => $year,
            ];
        }

        return $years;
    }
}
