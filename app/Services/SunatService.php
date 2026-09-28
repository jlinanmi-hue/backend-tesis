<?php

namespace App\Services;

use App\Models\ConsultaRuc;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class SunatService
{
    public const GENERIC_RUC = '10000000000';

    protected string $pythonBinary;
    protected string $scriptPath;

    public function __construct()
    {
        $this->pythonBinary = config('services.python.path', 'C:\\laragon\\bin\\python\\python-3.13\\python.exe');
        $this->scriptPath = base_path('python/sunat_service.py');
    }

    /**
     * Consulta información de RUC mediante el servicio en Python con fallback de respaldo en PHP.
     * Almacena y recupera los resultados desde la tabla Consulta_Ruc para máxima velocidad.
     */
    public function consultarRuc(string $ruc, string $target = 'all', bool $forceRefresh = false): array
    {
        $ruc = trim($ruc);

        // Soporte para DNI (8 dígitos)
        if (strlen($ruc) === 8 && ctype_digit($ruc)) {
            return $this->consultarDni($ruc, $target);
        }

        // 1. Manejo instantáneo de comodín sin RUC
        if (empty($ruc) || $ruc === self::GENERIC_RUC || $ruc === '00000000000') {
            return $this->generarPlantillaFallback(self::GENERIC_RUC, $target);
        }

        // 2. Consulta en BD Cache Local (Consulta_Ruc) para respuesta en ~1ms
        if (!$forceRefresh) {
            $cached = ConsultaRuc::where('ConsultaRucNumero', $ruc)->first();
            if ($cached) {
                try {
                    $cached->increment('ConsultaRucVecesConsultado');
                    $cached->update(['ConsultaRucUltimaConsulta' => now()]);
                } catch (\Exception $e) {}

                return $this->formatearDesdeCache($cached, $target);
            }
        }

        $resultado = null;

        // 3. Ejecutar motor principal en Python
        try {
            $resultadoPython = $this->ejecutarPythonService($ruc, 'all');
            if (!empty($resultadoPython) && ($resultadoPython['success'] ?? false)) {
                $resultado = $resultadoPython;
            }
        } catch (Exception $e) {
            Log::warning("SunatService: Falla en ejecución de script Python para RUC {$ruc}: " . $e->getMessage());
        }

        // 4. Fallback en PHP ante fallas o bloqueos (Plan B)
        if ($resultado === null) {
            $resultado = $this->getFallbackData($ruc, 'all');
        }

        if ($resultado !== null) {
            // Guardar en tabla Consulta_Ruc para futuras consultas ultrarrápidas
            $this->guardarEnBaseDeDatos($ruc, $resultado);

            if ($target === 'cliente') {
                return array_merge($resultado, [
                    'success' => true,
                    'ruc' => $ruc,
                    'target' => 'cliente',
                    'direccion' => $resultado['direccion'] ?? 'DIRECCION NO REGISTRADA',
                    'data' => $resultado['cliente'] ?? [],
                    'raw_data' => $resultado['raw_data'] ?? [],
                ]);
            }

            if ($target === 'proveedor') {
                return array_merge($resultado, [
                    'success' => true,
                    'ruc' => $ruc,
                    'target' => 'proveedor',
                    'direccion' => $resultado['direccion'] ?? 'DIRECCION NO REGISTRADA',
                    'data' => $resultado['proveedor'] ?? [],
                    'raw_data' => $resultado['raw_data'] ?? [],
                ]);
            }

            return $resultado;
        }

        throw new Exception("No se pudo obtener la información del RUC '{$ruc}' desde SUNAT ni sus servicios alternos.");
    }

    /**
     * Invoca el script en Python mediante Symfony Process.
     */
    protected function ejecutarPythonService(string $ruc, string $target = 'all'): ?array
    {
        if (!file_exists($this->pythonBinary)) {
            Log::warning("SunatService: Binario de Python no encontrado en: {$this->pythonBinary}");
            return null;
        }

        if (!file_exists($this->scriptPath)) {
            Log::warning("SunatService: Script de Python no encontrado en: {$this->scriptPath}");
            return null;
        }

        $command = [
            $this->pythonBinary,
            $this->scriptPath,
            $ruc,
            "--target={$target}",
        ];

        $process = new Process($command);
        $process->setTimeout(12);
        $process->run();

        $output = trim($process->getOutput());
        if (!empty($output)) {
            $data = json_decode($output, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                return $data;
            }
        }

        $errorOutput = $process->getErrorOutput();
        if (!empty($errorOutput)) {
            Log::error("SunatService ErrorOutput: {$errorOutput}");
        }

        return null;
    }

    /**
     * Plan B: Consulta directa a endpoints alternativos desde PHP si Python no estuviera disponible.
     */
    public function getFallbackData(string $ruc, string $target = 'all'): ?array
    {
        $urls = [
            "https://api.apis.net.pe/v1/ruc?numero={$ruc}",
            "https://api.sunat.cloud/ruc/{$ruc}",
            "https://dniruc.apisperu.com/api/v1/ruc/{$ruc}",
        ];

        foreach ($urls as $url) {
            try {
                $response = Http::timeout(4)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                        'Referer' => 'https://apis.net.pe'
                    ])
                    ->get($url);

                if ($response->successful()) {
                    $json = $response->json();
                    if (!empty($json) && (!empty($json['nombre']) || !empty($json['razon_social']))) {
                        return $this->transformarDatosFallback($json, $ruc, $target);
                    }
                }
            } catch (Exception $e) {
                continue;
            }
        }

        return null;
    }

    /**
     * Infiere la actividad económica principal según la razón social y tipo de contribuyente.
     * Retorna una cadena con un máximo estricto de 45 caracteres (compatible con SQL Server).
     */
    public function inferirActividadEconomica(string $razonSocial, ?string $tipoContribuyente = null): string
    {
        $rs = mb_strtoupper(trim($razonSocial));

        if (preg_match('/(RESTAURANT|POLLERIA|CHIFA|CEVICHERIA|PIZZERIA|CAFE|BAR|COMIDAS|GASTRONOM|FAST FOOD|CATERING|SNACK)/i', $rs)) {
            return 'RESTAURANTES Y SERVICIOS DE COMIDAS';
        }
        if (preg_match('/(ALIMENTO|BEBIDA|LICOR|CARNICERIA|PANADERIA|PASTELERIA|AVICOLA|AGRO|FRUTA)/i', $rs)) {
            return 'VENTA AL POR MAYOR DE ALIMENTOS Y BEBIDAS';
        }
        if (preg_match('/(DISTRIBUID|MAYORIST|COMERCIALIZAD|IMPORT|EXPORT|ABARROTES|MERCADERIA)/i', $rs)) {
            return 'VENTA AL POR MAYOR NO ESPECIALIZADA';
        }
        if (preg_match('/(TRANSPORT|CARGA|LOGISTIC|MUDANZA|COURIER|ENCOMIENDA|ENVIO)/i', $rs)) {
            return 'TRANSPORTE DE CARGA POR CARRETERA';
        }
        if (preg_match('/(FARMACIA|BOTICA|MEDIC|SALUD|CLINICA|DENTAL|OPTICA|LABORATORI)/i', $rs)) {
            return 'VENTA DE PRODUCTOS FARMACEUTICOS';
        }
        if (preg_match('/(CONSTRUCC|CONSTRUCTOR|EDIFICAC|INGENIER|FERRETER|OBRAS|MATERIAL)/i', $rs)) {
            return 'CONSTRUCCION DE EDIFICIOS Y OBRAS';
        }
        if (preg_match('/(SISTEMA|TECNOLOG|SOFTWARE|INFORMATIC|COMPUTO|DIGITAL|CONSULT)/i', $rs)) {
            return 'CONSULTORIA DE EQUIPOS Y SISTEMAS';
        }
        if (preg_match('/(TEXTIL|CONFECC|MODA|CALZADO|CUERO|ROPA|VESTIR)/i', $rs)) {
            return 'FABRICACION DE PRENDAS DE VESTIR';
        }
        if (preg_match('/(SERVICIOS|LIMPIEZA|MANTENIMIENTO|SEGURIDAD|VIGILANCIA)/i', $rs)) {
            return 'SERVICIOS DE APOYO A EMPRESAS';
        }

        $tc = mb_strtoupper(trim($tipoContribuyente ?? ''));
        if (str_contains($tc, 'NATURAL')) {
            return 'ACTIVIDAD COMERCIAL Y DE SERVICIOS';
        }

        return 'ACTIVIDAD COMERCIAL GENERAL';
    }

    /**
     * Normaliza la respuesta del fallback PHP para cumplir con la misma estructura que Python.
     */
    protected function transformarDatosFallback(array $json, string $ruc, string $target): array
    {
        $nombre = trim($json['nombre'] ?? $json['razon_social'] ?? '');
        $estado = strtoupper(trim($json['estado'] ?? 'ACTIVO'));
        $condicion = strtoupper(trim($json['condicion'] ?? 'HABIDO'));
        $distrito = trim($json['distrito'] ?? '');
        $provincia = trim($json['provincia'] ?? '');
        $departamento = trim($json['departamento'] ?? '');

        $direccion = trim($json['direccion'] ?? '');
        $ubicacion = array_filter([$distrito, $provincia, $departamento]);
        if (!empty($ubicacion)) {
            $ubStr = implode(' - ', $ubicacion);
            if (!empty($direccion) && $direccion !== '-' && stripos($direccion, $ubStr) === false) {
                $direccion .= ", {$ubStr}";
            } elseif (empty($direccion) || $direccion === '-') {
                $direccion = $ubStr;
            }
        }

        if (empty($direccion) || in_array($direccion, ['-', '--', 'LIMA, PERU', 'None', 'null'])) {
            $direccion = 'DIRECCION NO REGISTRADA';
        }

        $isActive = ($estado === 'ACTIVO' && $condicion === 'HABIDO');
        $estadoChar = $isActive ? 'A' : 'I';

        $actividadInferred = $this->inferirActividadEconomica($nombre, $json['tipo'] ?? null);

        $clienteData = [
            'ClienteRuc' => $ruc,
            'ClienteNombre' => mb_substr($nombre, 0, 100),
            'ClienteDireccion' => mb_substr($direccion, 0, 200),
            'direccion' => mb_substr($direccion, 0, 200),
            'ClienteNumero' => null,
            'ClienteEstado' => $estadoChar,
            'departamento' => $departamento,
            'provincia' => $provincia,
            'distrito' => $distrito,
        ];

        $proveedorData = [
            'ProveedorRuc' => $ruc,
            'ProveedorRazonSocial' => mb_substr($nombre ?: 'SIN RAZON SOCIAL', 0, 45),
            'ProveedorTipoContribuyente' => mb_substr($json['tipo'] ?? 'GENERAL', 0, 45),
            'ProveedorEstado' => $estadoChar,
            'ProveedorActividadEconomica' => mb_substr($actividadInferred, 0, 45),
            'ProveedorTelefono' => '-',
            'ProveedorDireccion' => mb_substr($direccion, 0, 200),
            'direccion' => mb_substr($direccion, 0, 200),
            'departamento' => $departamento,
            'provincia' => $provincia,
            'distrito' => $distrito,
        ];

        $raw = [
            'ruc' => $ruc,
            'razon_social' => $nombre,
            'estado' => $estado,
            'condicion' => $condicion,
            'direccion_fiscal' => $direccion,
            'direccion' => $direccion,
            'distrito' => $distrito,
            'provincia' => $provincia,
            'departamento' => $departamento,
            'ubigeo' => $json['ubigeo'] ?? '',
            'tipo_contribuyente' => $json['tipo'] ?? '',
            'fecha_inscripcion' => '',
            'actividad_economica' => $actividadInferred,
            'telefono' => null,
        ];

        if ($target === 'cliente') {
            return [
                'success' => true,
                'ruc' => $ruc,
                'target' => 'cliente',
                'direccion' => $direccion,
                'data' => $clienteData,
                'raw_data' => $raw,
            ];
        }

        if ($target === 'proveedor') {
            return [
                'success' => true,
                'ruc' => $ruc,
                'target' => 'proveedor',
                'direccion' => $direccion,
                'data' => $proveedorData,
                'raw_data' => $raw,
            ];
        }

        return [
            'success' => true,
            'is_fallback' => false,
            'source' => 'PHP_GATEWAY_FALLBACK',
            'ruc' => $ruc,
            'razon_social' => $nombre,
            'direccion' => $direccion,
            'direccion_completa' => $direccion,
            'departamento' => $departamento,
            'provincia' => $provincia,
            'distrito' => $distrito,
            'ubigeo' => $json['ubigeo'] ?? '',
            'estado' => $estado,
            'condicion' => $condicion,
            'tipo_contribuyente' => $json['tipo'] ?? '',
            'raw_data' => $raw,
            'cliente' => $clienteData,
            'proveedor' => $proveedorData,
        ];
    }

    /**
     * Plantilla rápida para clientes y proveedores sin RUC.
     */
    public function generarPlantillaFallback(string $ruc = self::GENERIC_RUC, string $target = 'all'): array
    {
        $clienteData = [
            'ClienteRuc' => $ruc,
            'ClienteNombre' => 'CLIENTE SIN RUC',
            'ClienteDireccion' => 'DIRECCION NO REGISTRADA',
            'direccion' => 'DIRECCION NO REGISTRADA',
            'ClienteNumero' => null,
            'ClienteEstado' => 'A',
            'departamento' => 'LIMA',
            'provincia' => 'LIMA',
            'distrito' => 'LIMA',
        ];

        $proveedorData = [
            'ProveedorRuc' => $ruc,
            'ProveedorRazonSocial' => 'PROVEEDOR SIN RUC',
            'ProveedorTipoContribuyente' => 'PERSONA NATURAL SIN NEGOCIO',
            'ProveedorEstado' => 'A',
            'ProveedorActividadEconomica' => '-',
            'ProveedorTelefono' => '-',
            'ProveedorDireccion' => 'DIRECCION NO REGISTRADA',
            'direccion' => 'DIRECCION NO REGISTRADA',
            'departamento' => 'LIMA',
            'provincia' => 'LIMA',
            'distrito' => 'LIMA',
        ];

        $raw = [
            'ruc' => $ruc,
            'razon_social' => 'CLIENTE / PROVEEDOR SIN RUC',
            'estado' => 'ACTIVO',
            'condicion' => 'HABIDO',
            'direccion_fiscal' => 'DIRECCION NO REGISTRADA',
            'direccion' => 'DIRECCION NO REGISTRADA',
            'distrito' => 'LIMA',
            'provincia' => 'LIMA',
            'departamento' => 'LIMA',
            'ubigeo' => '150101',
            'tipo_contribuyente' => 'PERSONA NATURAL SIN NEGOCIO',
            'fecha_inscripcion' => '',
            'actividad_economica' => '',
            'telefono' => null,
        ];

        if ($target === 'cliente') {
            return [
                'success' => true,
                'ruc' => $ruc,
                'target' => 'cliente',
                'direccion' => 'DIRECCION NO REGISTRADA',
                'data' => $clienteData,
                'raw_data' => $raw,
            ];
        }

        if ($target === 'proveedor') {
            return [
                'success' => true,
                'ruc' => $ruc,
                'target' => 'proveedor',
                'direccion' => 'DIRECCION NO REGISTRADA',
                'data' => $proveedorData,
                'raw_data' => $raw,
            ];
        }

        return [
            'success' => true,
            'is_fallback' => true,
            'source' => 'FALLBACK_TEMPLATE',
            'ruc' => $ruc,
            'razon_social' => 'CLIENTE / PROVEEDOR SIN RUC',
            'direccion' => 'DIRECCION NO REGISTRADA',
            'direccion_completa' => 'DIRECCION NO REGISTRADA',
            'departamento' => 'LIMA',
            'provincia' => 'LIMA',
            'distrito' => 'LIMA',
            'ubigeo' => '150101',
            'estado' => 'ACTIVO',
            'condicion' => 'HABIDO',
            'tipo_contribuyente' => 'PERSONA NATURAL SIN NEGOCIO',
            'raw_data' => $raw,
            'cliente' => $clienteData,
            'proveedor' => $proveedorData,
        ];
    }

    /**
     * Consulta información de DNI (8 dígitos) para autocompletado en Clientes.
     */
    public function consultarDni(string $dni, string $target = 'cliente'): array
    {
        $urls = [
            "https://api.apis.net.pe/v1/dni?numero={$dni}",
            "https://dniruc.apisperu.com/api/v1/dni/{$dni}",
        ];

        foreach ($urls as $url) {
            try {
                $response = Http::timeout(5)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                        'Referer' => 'https://apis.net.pe'
                    ])
                    ->get($url);

                if ($response->successful()) {
                    $json = $response->json();
                    $nombre = trim($json['nombre'] ?? '');
                    if (empty($nombre) && (!empty($json['nombres']) || !empty($json['apellidoPaterno']))) {
                        $nombre = trim(($json['nombres'] ?? '') . ' ' . ($json['apellidoPaterno'] ?? '') . ' ' . ($json['apellidoMaterno'] ?? ''));
                    }

                    if (!empty($nombre)) {
                        $direccion = trim($json['direccion'] ?? '');
                        if (empty($direccion)) {
                            $distrito = trim($json['distrito'] ?? '');
                            $provincia = trim($json['provincia'] ?? '');
                            $departamento = trim($json['departamento'] ?? '');
                            $ub = array_filter([$distrito, $provincia, $departamento]);
                            $direccion = !empty($ub) ? implode(' - ', $ub) : 'LIMA, PERU';
                        }

                        $clienteData = [
                            'ClienteRuc' => $dni,
                            'ClienteNombre' => mb_substr($nombre, 0, 100),
                            'ClienteDireccion' => mb_substr($direccion, 0, 200),
                            'ClienteNumero' => null,
                            'ClienteEstado' => 'A',
                        ];

                        $raw = [
                            'ruc' => $dni,
                            'dni' => $dni,
                            'razon_social' => $nombre,
                            'nombre' => $nombre,
                            'estado' => 'ACTIVO',
                            'condicion' => 'HABIDO',
                            'direccion_fiscal' => $clienteData['ClienteDireccion'],
                            'telefono' => null,
                        ];

                        $dniBase = [
                            'success' => true,
                            'is_fallback' => false,
                            'source' => 'DNI_RENIEC_FALLBACK',
                            'ruc' => $dni,
                            'dni' => $dni,
                            'razon_social' => $nombre,
                            'nombre' => $nombre,
                            'direccion' => $clienteData['ClienteDireccion'],
                            'direccion_completa' => $clienteData['ClienteDireccion'],
                            'departamento' => $departamento ?? '',
                            'provincia' => $provincia ?? '',
                            'distrito' => $distrito ?? '',
                            'estado' => 'ACTIVO',
                            'condicion' => 'HABIDO',
                            'tipo_contribuyente' => 'PERSONA NATURAL CON DNI',
                            'raw_data' => $raw,
                            'cliente' => $clienteData,
                            'proveedor' => [
                                'ProveedorRuc' => $dni,
                                'ProveedorRazonSocial' => mb_substr($nombre, 0, 45),
                                'ProveedorTipoContribuyente' => 'PERSONA NATURAL CON DNI',
                                'ProveedorEstado' => 'A',
                                'ProveedorActividadEconomica' => '-',
                                'ProveedorTelefono' => '-',
                            ],
                        ];

                        if ($target === 'cliente') {
                            return array_merge($dniBase, [
                                'target' => 'cliente',
                                'data' => $clienteData,
                            ]);
                        }

                        return $dniBase;
                    }
                }
            } catch (Exception $e) {
                continue;
            }
        }

        throw new Exception("No se pudo obtener información para el DNI '{$dni}'.");
    }

    /**
     * Guarda o actualiza los datos del RUC consultado en la tabla Consulta_Ruc.
     */
    public function guardarEnBaseDeDatos(string $ruc, array $resultado): void
    {
        try {
            $raw = $resultado['raw_data'] ?? [];
            $razonSocial = $resultado['razon_social'] ?? $raw['razon_social'] ?? 'SIN RAZON SOCIAL';
            $direccion = $resultado['direccion'] ?? $raw['direccion_fiscal'] ?? 'DIRECCION NO REGISTRADA';
            if (empty($direccion) || in_array($direccion, ['-', '--', 'LIMA, PERU', 'None', 'null'])) {
                $direccion = 'DIRECCION NO REGISTRADA';
            }

            $actividadEconomica = $resultado['actividad_economica'] ?? $raw['actividad_economica'] ?? null;
            if (empty($actividadEconomica) || in_array($actividadEconomica, ['-', '--', 'null', 'None'])) {
                $actividadEconomica = $this->inferirActividadEconomica($razonSocial, $resultado['tipo_contribuyente'] ?? $raw['tipo_contribuyente'] ?? null);
            }

            $user = auth()->user()?->name ?? 'SISTEMA';
            $ip = request()->ip() ?? '127.0.0.1';

            $registro = ConsultaRuc::where('ConsultaRucNumero', $ruc)->first();
            if ($registro) {
                $registro->update([
                    'ConsultaRucRazonSocial' => mb_substr($razonSocial, 0, 255),
                    'ConsultaRucEstado' => mb_substr($resultado['estado'] ?? $raw['estado'] ?? 'ACTIVO', 0, 50),
                    'ConsultaRucCondicion' => mb_substr($resultado['condicion'] ?? $raw['condicion'] ?? 'HABIDO', 0, 50),
                    'ConsultaRucDireccion' => mb_substr($direccion, 0, 500),
                    'ConsultaRucDepartamento' => mb_substr($resultado['departamento'] ?? $raw['departamento'] ?? '', 0, 100) ?: null,
                    'ConsultaRucProvincia' => mb_substr($resultado['provincia'] ?? $raw['provincia'] ?? '', 0, 100) ?: null,
                    'ConsultaRucDistrito' => mb_substr($resultado['distrito'] ?? $raw['distrito'] ?? '', 0, 100) ?: null,
                    'ConsultaRucUbigeo' => mb_substr($resultado['ubigeo'] ?? $raw['ubigeo'] ?? '', 0, 20) ?: null,
                    'ConsultaRucTipoContribuyente' => mb_substr($resultado['tipo_contribuyente'] ?? $raw['tipo_contribuyente'] ?? '', 0, 150) ?: null,
                    'ConsultaRucFechaInscripcion' => mb_substr($raw['fecha_inscripcion'] ?? '', 0, 50) ?: null,
                    'ConsultaRucActividadEconomica' => mb_substr($actividadEconomica, 0, 255) ?: null,
                    'ConsultaRucTelefono' => mb_substr($raw['telefono'] ?? '', 0, 50) ?: null,
                    'ConsultaRucRawJson' => $resultado,
                    'ConsultaRucVecesConsultado' => $registro->ConsultaRucVecesConsultado + 1,
                    'ConsultaRucUltimaConsulta' => now(),
                    'ConsultaRucUsuarioModificacion' => $user,
                    'ConsultaRucHostModificacion' => $ip,
                    'ConsultaRucFechaModificacion' => now(),
                ]);
            } else {
                ConsultaRuc::create([
                    'ConsultaRucNumero' => $ruc,
                    'ConsultaRucRazonSocial' => mb_substr($razonSocial, 0, 255),
                    'ConsultaRucEstado' => mb_substr($resultado['estado'] ?? $raw['estado'] ?? 'ACTIVO', 0, 50),
                    'ConsultaRucCondicion' => mb_substr($resultado['condicion'] ?? $raw['condicion'] ?? 'HABIDO', 0, 50),
                    'ConsultaRucDireccion' => mb_substr($direccion, 0, 500),
                    'ConsultaRucDepartamento' => mb_substr($resultado['departamento'] ?? $raw['departamento'] ?? '', 0, 100) ?: null,
                    'ConsultaRucProvincia' => mb_substr($resultado['provincia'] ?? $raw['provincia'] ?? '', 0, 100) ?: null,
                    'ConsultaRucDistrito' => mb_substr($resultado['distrito'] ?? $raw['distrito'] ?? '', 0, 100) ?: null,
                    'ConsultaRucUbigeo' => mb_substr($resultado['ubigeo'] ?? $raw['ubigeo'] ?? '', 0, 20) ?: null,
                    'ConsultaRucTipoContribuyente' => mb_substr($resultado['tipo_contribuyente'] ?? $raw['tipo_contribuyente'] ?? '', 0, 150) ?: null,
                    'ConsultaRucFechaInscripcion' => mb_substr($raw['fecha_inscripcion'] ?? '', 0, 50) ?: null,
                    'ConsultaRucActividadEconomica' => mb_substr($actividadEconomica, 0, 255) ?: null,
                    'ConsultaRucTelefono' => mb_substr($raw['telefono'] ?? '', 0, 50) ?: null,
                    'ConsultaRucRawJson' => $resultado,
                    'ConsultaRucVecesConsultado' => 1,
                    'ConsultaRucUltimaConsulta' => now(),
                    'ConsultaRucUsuarioCreacion' => $user,
                    'ConsultaRucHostCreacion' => $ip,
                    'ConsultaRucFechaCreacion' => now(),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning("SunatService: Error al guardar consulta RUC {$ruc} en BD: " . $e->getMessage());
        }
    }

    /**
     * Da formato a la respuesta a partir de un registro de la tabla Consulta_Ruc.
     */
    protected function formatearDesdeCache(ConsultaRuc $cached, string $target): array
    {
        $ruc = $cached->ConsultaRucNumero;
        $nombre = $cached->ConsultaRucRazonSocial;
        $direccion = $cached->ConsultaRucDireccion ?: 'DIRECCION NO REGISTRADA';
        $estado = $cached->ConsultaRucEstado;
        $condicion = $cached->ConsultaRucCondicion;
        $isActive = ($estado === 'ACTIVO' && $condicion === 'HABIDO');
        $estadoChar = $isActive ? 'A' : 'I';

        $actividadEconomica = $cached->ConsultaRucActividadEconomica;
        if (empty($actividadEconomica) || in_array($actividadEconomica, ['-', '--', 'null', 'None'])) {
            $actividadEconomica = $this->inferirActividadEconomica($nombre, $cached->ConsultaRucTipoContribuyente);
            try {
                $cached->update(['ConsultaRucActividadEconomica' => $actividadEconomica]);
            } catch (\Exception $e) {}
        }

        $rawJson = is_array($cached->ConsultaRucRawJson)
            ? $cached->ConsultaRucRawJson
            : (json_decode($cached->ConsultaRucRawJson ?? '[]', true) ?: []);
        $rawInner = $rawJson['raw_data'] ?? [];

        $nombreComercial = $rawJson['nombre_comercial'] ?? $rawInner['nombre_comercial'] ?? '-';
        $tipoDocumento = $rawJson['tipo_documento'] ?? $rawInner['tipo_documento'] ?? '';
        $fechaInicioActividades = $rawJson['fecha_inicio_actividades'] ?? $rawInner['fecha_inicio_actividades'] ?? '';
        $sistemaEmision = $rawJson['sistema_emision'] ?? $rawInner['sistema_emision'] ?? '';
        $actividadExterior = $rawJson['actividad_exterior'] ?? $rawInner['actividad_exterior'] ?? '';
        $sistemaContabilidad = $rawJson['sistema_contabilidad'] ?? $rawInner['sistema_contabilidad'] ?? '';
        $emisionElectronica = $rawJson['emision_electronica'] ?? $rawInner['emision_electronica'] ?? '';
        $emisorDesde = $rawJson['emisor_desde'] ?? $rawInner['emisor_desde'] ?? '';
        $comprobantesElectronicos = $rawJson['comprobantes_electronicos'] ?? $rawInner['comprobantes_electronicos'] ?? '';
        $afiliadoPle = $rawJson['afiliado_ple'] ?? $rawInner['afiliado_ple'] ?? '';
        $padrones = $rawJson['padrones'] ?? $rawInner['padrones'] ?? '';

        $clienteData = [
            'ClienteRuc' => $ruc,
            'ClienteNombre' => mb_substr($nombre, 0, 100),
            'ClienteDireccion' => mb_substr($direccion, 0, 200),
            'direccion' => mb_substr($direccion, 0, 200),
            'ClienteNumero' => $cached->ConsultaRucTelefono,
            'ClienteEstado' => $estadoChar,
            'departamento' => $cached->ConsultaRucDepartamento,
            'provincia' => $cached->ConsultaRucProvincia,
            'distrito' => $cached->ConsultaRucDistrito,
            'nombre_comercial' => $nombreComercial,
        ];

        $proveedorData = [
            'ProveedorRuc' => $ruc,
            'ProveedorRazonSocial' => mb_substr($nombre, 0, 45),
            'ProveedorTipoContribuyente' => mb_substr($cached->ConsultaRucTipoContribuyente ?? 'GENERAL', 0, 45),
            'ProveedorEstado' => $estadoChar,
            'ProveedorActividadEconomica' => mb_substr($actividadEconomica, 0, 45),
            'ProveedorTelefono' => mb_substr($cached->ConsultaRucTelefono ?? '-', 0, 45),
            'ProveedorDireccion' => mb_substr($direccion, 0, 200),
            'direccion' => mb_substr($direccion, 0, 200),
            'departamento' => $cached->ConsultaRucDepartamento,
            'provincia' => $cached->ConsultaRucProvincia,
            'distrito' => $cached->ConsultaRucDistrito,
            'nombre_comercial' => $nombreComercial,
        ];

        $raw = [
            'ruc' => $ruc,
            'razon_social' => $nombre,
            'estado' => $estado,
            'condicion' => $condicion,
            'direccion_fiscal' => $direccion,
            'direccion' => $direccion,
            'distrito' => $cached->ConsultaRucDistrito,
            'provincia' => $cached->ConsultaRucProvincia,
            'departamento' => $cached->ConsultaRucDepartamento,
            'ubigeo' => $cached->ConsultaRucUbigeo,
            'tipo_contribuyente' => $cached->ConsultaRucTipoContribuyente,
            'nombre_comercial' => $nombreComercial,
            'tipo_documento' => $tipoDocumento,
            'fecha_inscripcion' => $cached->ConsultaRucFechaInscripcion,
            'fecha_inicio_actividades' => $fechaInicioActividades,
            'sistema_emision' => $sistemaEmision,
            'actividad_exterior' => $actividadExterior,
            'sistema_contabilidad' => $sistemaContabilidad,
            'actividad_economica' => $actividadEconomica,
            'emision_electronica' => $emisionElectronica,
            'emisor_desde' => $emisorDesde,
            'comprobantes_electronicos' => $comprobantesElectronicos,
            'afiliado_ple' => $afiliadoPle,
            'padrones' => $padrones,
            'telefono' => $cached->ConsultaRucTelefono,
        ];

        $payloadCompleto = [
            'success' => true,
            'is_fallback' => false,
            'from_db' => true,
            'source' => 'LOCAL_DB_CACHE',
            'ruc' => $ruc,
            'razon_social' => $nombre,
            'direccion' => $direccion,
            'direccion_completa' => $direccion,
            'departamento' => $cached->ConsultaRucDepartamento,
            'provincia' => $cached->ConsultaRucProvincia,
            'distrito' => $cached->ConsultaRucDistrito,
            'ubigeo' => $cached->ConsultaRucUbigeo,
            'estado' => $estado,
            'condicion' => $condicion,
            'tipo_contribuyente' => $cached->ConsultaRucTipoContribuyente,
            'nombre_comercial' => $nombreComercial,
            'tipo_documento' => $tipoDocumento,
            'fecha_inscripcion' => $cached->ConsultaRucFechaInscripcion,
            'fecha_inicio_actividades' => $fechaInicioActividades,
            'sistema_emision' => $sistemaEmision,
            'actividad_exterior' => $actividadExterior,
            'sistema_contabilidad' => $sistemaContabilidad,
            'actividad_economica' => $actividadEconomica,
            'emision_electronica' => $emisionElectronica,
            'emisor_desde' => $emisorDesde,
            'comprobantes_electronicos' => $comprobantesElectronicos,
            'afiliado_ple' => $afiliadoPle,
            'padrones' => $padrones,
            'veces_consultado' => $cached->ConsultaRucVecesConsultado,
            'ultima_consulta' => $cached->ConsultaRucUltimaConsulta?->toIso8601String(),
            'raw_data' => $raw,
            'cliente' => $clienteData,
            'proveedor' => $proveedorData,
        ];

        if ($target === 'cliente') {
            return array_merge($payloadCompleto, [
                'target' => 'cliente',
                'data' => $clienteData,
            ]);
        }

        if ($target === 'proveedor') {
            return array_merge($payloadCompleto, [
                'target' => 'proveedor',
                'data' => $proveedorData,
            ]);
        }

        return $payloadCompleto;
    }
}
