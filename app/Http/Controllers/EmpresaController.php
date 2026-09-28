<?php

namespace App\Http\Controllers;

use App\Services\EmpresaService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EmpresaController extends Controller
{
    public function __construct(
        protected EmpresaService $empresaService
    ) {}

    /**
     * Obtener los datos actuales de la empresa.
     */
    public function show(): JsonResponse
    {
        try {
            $empresa = $this->empresaService->obtenerEmpresa();

            return response()->json([
                'success' => true,
                'message' => 'Datos de la empresa obtenidos con éxito.',
                'data' => $empresa,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los datos de la empresa: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar los datos de la empresa.
     */
    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'EmpresaRuc' => 'nullable|string|size:11',
            'EmpresaRazonSocial' => 'nullable|string|max:255',
            'EmpresaNombreComercial' => 'nullable|string|max:255',
            'EmpresaDireccion' => 'nullable|string|max:500',
            'EmpresaDepartamento' => 'nullable|string|max:100',
            'EmpresaProvincia' => 'nullable|string|max:100',
            'EmpresaDistrito' => 'nullable|string|max:100',
            'EmpresaUbigeo' => 'nullable|string|max:20',
            'EmpresaTelefono' => 'nullable|string|max:50',
            'EmpresaWhatsapp' => 'nullable|string|max:50',
            'EmpresaEmail' => 'nullable|email|max:150',
            'EmpresaLogo' => 'nullable|string',
            'EmpresaMensajeTicket' => 'nullable|string|max:1000',
            'EmpresaEstado' => 'nullable|string|in:A,I,1,0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación en los datos enviados.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $usuario = $request->header('X-User-Name', 'ADMIN');
            $host = $request->ip() ?? '127.0.0.1';

            $empresa = $this->empresaService->actualizarEmpresa(
                $request->all(),
                $usuario,
                $host
            );

            return response()->json([
                'success' => true,
                'message' => 'Datos de la empresa actualizados correctamente.',
                'data' => $empresa,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar los datos de la empresa: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sincronizar / autocompletar con SUNAT a partir del RUC.
     */
    public function sincronizarSunat(Request $request): JsonResponse
    {
        $ruc = $request->input('ruc');

        if (!$ruc) {
            $empresaActual = $this->empresaService->obtenerEmpresa();
            $ruc = $empresaActual->EmpresaRuc;
        }

        $ruc = trim($ruc);

        if (strlen($ruc) !== 11 || !ctype_digit($ruc)) {
            return response()->json([
                'success' => false,
                'message' => 'El RUC debe contener exactamente 11 dígitos numéricos.',
            ], 422);
        }

        $guardar = filter_var($request->input('guardar', false), FILTER_VALIDATE_BOOLEAN);

        try {
            $usuario = $request->header('X-User-Name', 'ADMIN');
            $host = $request->ip() ?? '127.0.0.1';

            $resultado = $this->empresaService->sincronizarConSunat($ruc, $guardar, $usuario, $host);

            return response()->json([
                'success' => true,
                'message' => $guardar 
                    ? 'Datos de la empresa sincronizados y guardados desde SUNAT con éxito.' 
                    : 'Información obtenida desde SUNAT exitosamente.',
                'data' => $resultado,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo sincronizar con SUNAT: ' . $e->getMessage(),
            ], 404);
        }
    }
}
