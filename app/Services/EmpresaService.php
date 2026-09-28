<?php

namespace App\Services;

use App\Models\Empresa;
use Exception;
use Illuminate\Support\Facades\Log;

class EmpresaService
{
    public function __construct(
        protected SunatService $sunatService
    ) {}

    /**
     * Obtiene los datos de la empresa principal.
     * Si no existe registro previo, crea el registro por defecto de Comercial Valencia.
     */
    public function obtenerEmpresa(): Empresa
    {
        $empresa = Empresa::first();

        if (!$empresa) {
            $empresa = Empresa::create([
                'EmpresaRuc' => '10181935451',
                'EmpresaRazonSocial' => 'VALENCIA VILLANUEVA BERNABE',
                'EmpresaNombreComercial' => 'COMERCIAL VALENCIA',
                'EmpresaDireccion' => 'Av. Principal 123, Urb. San Antonio',
                'EmpresaDepartamento' => 'Lima',
                'EmpresaProvincia' => 'Lima',
                'EmpresaDistrito' => 'Lima',
                'EmpresaUbigeo' => '150101',
                'EmpresaTelefono' => '01 456-7890',
                'EmpresaWhatsapp' => '51900292514',
                'EmpresaEmail' => 'ventas@comercialvalencia.pe',
                'EmpresaLogo' => null,
                'EmpresaMensajeTicket' => '¡Gracias por su compra en Comercial Valencia! Conserve su comprobante para cualquier cambio o reclamo.',
                'EmpresaEstado' => 'A',
                'EmpresaUsuarioCreacion' => 'SISTEMA',
                'EmpresaHostCreacion' => '127.0.0.1',
                'EmpresaFechaCreacion' => now(),
            ]);
        }

        return $empresa;
    }

    /**
     * Actualiza la información de la empresa.
     */
    public function actualizarEmpresa(array $datos, ?string $usuario = 'SISTEMA', ?string $host = '127.0.0.1'): Empresa
    {
        $empresa = $this->obtenerEmpresa();

        $camposPermitidos = [
            'EmpresaRuc',
            'EmpresaRazonSocial',
            'EmpresaNombreComercial',
            'EmpresaDireccion',
            'EmpresaDepartamento',
            'EmpresaProvincia',
            'EmpresaDistrito',
            'EmpresaUbigeo',
            'EmpresaTelefono',
            'EmpresaWhatsapp',
            'EmpresaEmail',
            'EmpresaLogo',
            'EmpresaMensajeTicket',
            'EmpresaEstado',
        ];

        $actualizar = [];
        foreach ($camposPermitidos as $campo) {
            if (array_key_exists($campo, $datos)) {
                $actualizar[$campo] = $datos[$campo];
            }
        }

        $actualizar['EmpresaUsuarioModificacion'] = $usuario;
        $actualizar['EmpresaHostModificacion'] = $host;
        $actualizar['EmpresaFechaModificacion'] = now();

        $empresa->update($actualizar);

        return $empresa->fresh();
    }

    /**
     * Consulta SUNAT para autocompletar la información de la empresa.
     * Opcionalmente guarda los cambios directamente si se solicita.
     */
    public function sincronizarConSunat(string $ruc, bool $guardar = false, ?string $usuario = 'SISTEMA', ?string $host = '127.0.0.1'): array
    {
        $ruc = trim($ruc);
        $sunatData = $this->sunatService->consultarRuc($ruc, 'all', true);

        if (!$sunatData['success']) {
            throw new Exception($sunatData['message'] ?? 'No se pudo consultar el RUC en SUNAT.');
        }

        $sugerido = [
            'EmpresaRuc' => $sunatData['ruc'] ?? $ruc,
            'EmpresaRazonSocial' => $sunatData['razon_social'] ?? '',
            'EmpresaDireccion' => (!empty($sunatData['direccion']) && $sunatData['direccion'] !== 'DIRECCION NO REGISTRADA')
                ? $sunatData['direccion']
                : null,
            'EmpresaDepartamento' => $sunatData['departamento'] ?? null,
            'EmpresaProvincia' => $sunatData['provincia'] ?? null,
            'EmpresaDistrito' => $sunatData['distrito'] ?? null,
            'EmpresaUbigeo' => ($sunatData['ubigeo'] ?? '-') !== '-' ? $sunatData['ubigeo'] : null,
            'EmpresaEstadoContribuyente' => $sunatData['estado'] ?? 'ACTIVO',
            'EmpresaCondicionContribuyente' => $sunatData['condicion'] ?? 'HABIDO',
        ];

        if ($guardar) {
            $datosParaActualizar = array_filter($sugerido, fn($v) => !is_null($v) && $v !== '');
            $empresa = $this->actualizarEmpresa($datosParaActualizar, $usuario, $host);
            return [
                'empresa' => $empresa,
                'sunat' => $sugerido,
                'guardado' => true,
            ];
        }

        return [
            'sunat' => $sugerido,
            'guardado' => false,
        ];
    }
}
