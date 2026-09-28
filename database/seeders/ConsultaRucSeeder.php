<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ConsultaRucSeeder extends Seeder
{
    /**
     * Auto-generated from database table `Consulta_Ruc`.
     * Total records: 10
     */
    public function run(): void
    {
        // Desactivar restricciones de claves foráneas temporalmente
        DB::statement('EXEC sp_msforeachtable "ALTER TABLE ? NOCHECK CONSTRAINT all"');

        // Limpiar tabla antes de poblar
        DB::table('Consulta_Ruc')->delete();

        $rows = [
            [
                'ConsultaRucId' => '1',
                'ConsultaRucNumero' => '10712345671',
                'ConsultaRucRazonSocial' => 'SERRANO ESPINOZA NILVER',
                'ConsultaRucEstado' => 'ACTIVO',
                'ConsultaRucCondicion' => 'HABIDO',
                'ConsultaRucDireccion' => 'DIRECCION NO REGISTRADA',
                'ConsultaRucDepartamento' => null,
                'ConsultaRucProvincia' => null,
                'ConsultaRucDistrito' => null,
                'ConsultaRucUbigeo' => null,
                'ConsultaRucTipoContribuyente' => 'PERSONA NATURAL CON NEGOCIO',
                'ConsultaRucFechaInscripcion' => null,
                'ConsultaRucActividadEconomica' => null,
                'ConsultaRucTelefono' => null,
                'ConsultaRucRawJson' => null,
                'ConsultaRucVecesConsultado' => '3',
                'ConsultaRucUltimaConsulta' => '2026-09-07 20:28:38.203',
                'ConsultaRucUsuarioCreacion' => 'SISTEMA',
                'ConsultaRucHostCreacion' => '127.0.0.1',
                'ConsultaRucFechaCreacion' => '2026-09-07 20:17:15.607',
                'ConsultaRucUsuarioModificacion' => null,
                'ConsultaRucHostModificacion' => null,
                'ConsultaRucFechaModificacion' => null,
            ],
            [
                'ConsultaRucId' => '2',
                'ConsultaRucNumero' => '20100070970',
                'ConsultaRucRazonSocial' => 'SUPERMERCADOS PERUANOS SOCIEDAD ANONIMA \'O \' S.P.S.A.',
                'ConsultaRucEstado' => 'ACTIVO',
                'ConsultaRucCondicion' => 'HABIDO',
                'ConsultaRucDireccion' => 'CAL. MORELLI NRO 181 INT. P-2, SAN BORJA - LIMA - LIMA',
                'ConsultaRucDepartamento' => 'LIMA',
                'ConsultaRucProvincia' => 'LIMA',
                'ConsultaRucDistrito' => 'SAN BORJA',
                'ConsultaRucUbigeo' => '150130',
                'ConsultaRucTipoContribuyente' => 'SOCIEDAD ANONIMA',
                'ConsultaRucFechaInscripcion' => null,
                'ConsultaRucActividadEconomica' => '-',
                'ConsultaRucTelefono' => null,
                'ConsultaRucRawJson' => '{"success":true,"is_fallback":false,"source":"GATEWAY_JSON","ruc":"20100070970","razon_social":"SUPERMERCADOS PERUANOS SOCIEDAD ANONIMA \'O \' S.P.S.A.","direccion":"CAL. MORELLI NRO 181 INT. P-2, SAN BORJA - LIMA - LIMA","direccion_completa":"CAL. MORELLI NRO 181 INT. P-2, SAN BORJA - LIMA - LIMA","departamento":"LIMA","provincia":"LIMA","distrito":"SAN BORJA","ubigeo":"150130","estado":"ACTIVO","condicion":"HABIDO","tipo_contribuyente":"SOCIEDAD ANONIMA","nombre_comercial":"-","tipo_documento":"","fecha_inscripcion":"","fecha_inicio_actividades":"","sistema_emision":"","actividad_exterior":"","sistema_contabilidad":"","actividad_economica":"-","emision_electronica":"","emisor_desde":"","comprobantes_electronicos":"","afiliado_ple":"","padrones":"","raw_data":{"ruc":"20100070970","razon_social":"SUPERMERCADOS PERUANOS SOCIEDAD ANONIMA \'O \' S.P.S.A.","estado":"ACTIVO","condicion":"HABIDO","direccion_fiscal":"CAL. MORELLI NRO 181 INT. P-2, SAN BORJA - LIMA - LIMA","direccion":"CAL. MORELLI NRO 181 INT. P-2, SAN BORJA - LIMA - LIMA","distrito":"SAN BORJA","provincia":"LIMA","departamento":"LIMA","ubigeo":"150130","tipo_contribuyente":"SOCIEDAD ANONIMA","nombre_comercial":"-","tipo_documento":"","fecha_inscripcion":"","fecha_inicio_actividades":"","sistema_emision":"","actividad_exterior":"","sistema_contabilidad":"","actividad_economica":"-","emision_electronica":"","emisor_desde":"","comprobantes_electronicos":"","afiliado_ple":"","padrones":"","telefono":""},"cliente":{"ClienteRuc":"20100070970","ClienteNombre":"SUPERMERCADOS PERUANOS SOCIEDAD ANONIMA \'O \' S.P.S.A.","ClienteDireccion":"CAL. MORELLI NRO 181 INT. P-2, SAN BORJA - LIMA - LIMA","direccion":"CAL. MORELLI NRO 181 INT. P-2, SAN BORJA - LIMA - LIMA","ClienteNumero":null,"ClienteEstado":"A","departamento":"LIMA","provincia":"LIMA","distrito":"SAN BORJA","nombre_comercial":"-"},"proveedor":{"ProveedorRuc":"20100070970","ProveedorRazonSocial":"SUPERMERCADOS PERUANOS SOCIEDAD ANONIMA \'O \' ","ProveedorTipoContribuyente":"SOCIEDAD ANONIMA","ProveedorEstado":"A","ProveedorActividadEconomica":"-","ProveedorTelefono":"-","ProveedorDireccion":"CAL. MORELLI NRO 181 INT. P-2, SAN BORJA - LIMA - LIMA","direccion":"CAL. MORELLI NRO 181 INT. P-2, SAN BORJA - LIMA - LIMA","departamento":"LIMA","provincia":"LIMA","distrito":"SAN BORJA","nombre_comercial":"-"}}',
                'ConsultaRucVecesConsultado' => '7',
                'ConsultaRucUltimaConsulta' => '2026-09-07 20:40:53.590',
                'ConsultaRucUsuarioCreacion' => 'SISTEMA',
                'ConsultaRucHostCreacion' => '127.0.0.1',
                'ConsultaRucFechaCreacion' => '2026-09-07 20:17:16.670',
                'ConsultaRucUsuarioModificacion' => 'SISTEMA',
                'ConsultaRucHostModificacion' => '127.0.0.1',
                'ConsultaRucFechaModificacion' => '2026-09-07 20:31:15.207',
            ],
            [
                'ConsultaRucId' => '3',
                'ConsultaRucNumero' => '10417290937',
                'ConsultaRucRazonSocial' => 'MIGUEL BLAS JANETT MARGARITA',
                'ConsultaRucEstado' => 'ACTIVO',
                'ConsultaRucCondicion' => 'HABIDO',
                'ConsultaRucDireccion' => 'DIRECCION NO REGISTRADA',
                'ConsultaRucDepartamento' => null,
                'ConsultaRucProvincia' => null,
                'ConsultaRucDistrito' => null,
                'ConsultaRucUbigeo' => '-',
                'ConsultaRucTipoContribuyente' => null,
                'ConsultaRucFechaInscripcion' => null,
                'ConsultaRucActividadEconomica' => 'ACTIVIDAD COMERCIAL GENERAL',
                'ConsultaRucTelefono' => null,
                'ConsultaRucRawJson' => '{"success":true,"is_fallback":false,"source":"PHP_GATEWAY_FALLBACK","ruc":"10417290937","razon_social":"MIGUEL BLAS JANETT MARGARITA","direccion":"-","direccion_completa":"-","departamento":"","provincia":"","distrito":"","ubigeo":"-","estado":"ACTIVO","condicion":"HABIDO","tipo_contribuyente":"","raw_data":{"ruc":"10417290937","razon_social":"MIGUEL BLAS JANETT MARGARITA","estado":"ACTIVO","condicion":"HABIDO","direccion_fiscal":"-","direccion":"-","distrito":"","provincia":"","departamento":"","ubigeo":"-","tipo_contribuyente":"","fecha_inscripcion":"","actividad_economica":"","telefono":null},"cliente":{"ClienteRuc":"10417290937","ClienteNombre":"MIGUEL BLAS JANETT MARGARITA","ClienteDireccion":"-","direccion":"-","ClienteNumero":null,"ClienteEstado":"A","departamento":"","provincia":"","distrito":""},"proveedor":{"ProveedorRuc":"10417290937","ProveedorRazonSocial":"MIGUEL BLAS JANETT MARGARITA","ProveedorTipoContribuyente":"GENERAL","ProveedorEstado":"A","ProveedorActividadEconomica":"-","ProveedorTelefono":"-","ProveedorDireccion":"-","direccion":"-","departamento":"","provincia":"","distrito":""}}',
                'ConsultaRucVecesConsultado' => '5',
                'ConsultaRucUltimaConsulta' => '2026-09-08 23:10:21.557',
                'ConsultaRucUsuarioCreacion' => 'SISTEMA',
                'ConsultaRucHostCreacion' => '127.0.0.1',
                'ConsultaRucFechaCreacion' => '2026-09-07 20:21:03.410',
                'ConsultaRucUsuarioModificacion' => null,
                'ConsultaRucHostModificacion' => null,
                'ConsultaRucFechaModificacion' => null,
            ],
            [
                'ConsultaRucId' => '4',
                'ConsultaRucNumero' => '10726797451',
                'ConsultaRucRazonSocial' => 'TRIVEÑO PONCIANO ALEJANDRO GABRIEL',
                'ConsultaRucEstado' => 'ACTIVO',
                'ConsultaRucCondicion' => 'HABIDO',
                'ConsultaRucDireccion' => 'DIRECCION NO REGISTRADA',
                'ConsultaRucDepartamento' => null,
                'ConsultaRucProvincia' => null,
                'ConsultaRucDistrito' => null,
                'ConsultaRucUbigeo' => null,
                'ConsultaRucTipoContribuyente' => 'PERSONA NATURAL SIN NEGOCIO',
                'ConsultaRucFechaInscripcion' => '04/02/2023',
                'ConsultaRucActividadEconomica' => 'Principal - 9609 - OTRAS ACTIVIDADES DE SERVICIOS PERSONALES N.C.P.',
                'ConsultaRucTelefono' => null,
                'ConsultaRucRawJson' => '{"success":true,"is_fallback":false,"source":"SUNAT_HTML_SCRAPER","ruc":"10726797451","razon_social":"TRIVE\\u00d1O PONCIANO ALEJANDRO GABRIEL","direccion":"DIRECCION NO REGISTRADA","direccion_completa":"DIRECCION NO REGISTRADA","departamento":"","provincia":"","distrito":"","ubigeo":"","estado":"ACTIVO","condicion":"HABIDO","tipo_contribuyente":"PERSONA NATURAL SIN NEGOCIO","nombre_comercial":"-","tipo_documento":"DNI 72679745 - TRIVE\\u00d1O PONCIANO, ALEJANDRO GABRIEL","fecha_inscripcion":"04\\/02\\/2023","fecha_inicio_actividades":"10\\/02\\/2023","sistema_emision":"MANUAL\\/COMPUTARIZADO","actividad_exterior":"SIN ACTIVIDAD","sistema_contabilidad":"MANUAL\\/COMPUTARIZADO","actividad_economica":"Principal - 9609 - OTRAS ACTIVIDADES DE SERVICIOS PERSONALES N.C.P.","emision_electronica":"RECIBOS POR HONORARIOS AFILIADO DESDE 09\\/07\\/2023 | DESDE LOS SISTEMAS DEL CONTRIBUYENTE. AUTORIZ DESDE 06\\/02\\/2026","emisor_desde":"09\\/07\\/2023","comprobantes_electronicos":"RECIBO POR HONORARIO (desde 09\\/07\\/2023),BOLETA (desde 06\\/02\\/2026),FACTURA (desde 06\\/02\\/2026)","afiliado_ple":"","padrones":"NINGUNO","raw_data":{"ruc":"10726797451","razon_social":"TRIVE\\u00d1O PONCIANO ALEJANDRO GABRIEL","estado":"ACTIVO","condicion":"HABIDO","direccion_fiscal":"DIRECCION NO REGISTRADA","direccion":"DIRECCION NO REGISTRADA","distrito":"","provincia":"","departamento":"","ubigeo":"","tipo_contribuyente":"PERSONA NATURAL SIN NEGOCIO","nombre_comercial":"-","tipo_documento":"DNI 72679745 - TRIVE\\u00d1O PONCIANO, ALEJANDRO GABRIEL","fecha_inscripcion":"04\\/02\\/2023","fecha_inicio_actividades":"10\\/02\\/2023","sistema_emision":"MANUAL\\/COMPUTARIZADO","actividad_exterior":"SIN ACTIVIDAD","sistema_contabilidad":"MANUAL\\/COMPUTARIZADO","actividad_economica":"Principal - 9609 - OTRAS ACTIVIDADES DE SERVICIOS PERSONALES N.C.P.","emision_electronica":"RECIBOS POR HONORARIOS AFILIADO DESDE 09\\/07\\/2023 | DESDE LOS SISTEMAS DEL CONTRIBUYENTE. AUTORIZ DESDE 06\\/02\\/2026","emisor_desde":"09\\/07\\/2023","comprobantes_electronicos":"RECIBO POR HONORARIO (desde 09\\/07\\/2023),BOLETA (desde 06\\/02\\/2026),FACTURA (desde 06\\/02\\/2026)","afiliado_ple":"","padrones":"NINGUNO","telefono":""},"cliente":{"ClienteRuc":"10726797451","ClienteNombre":"TRIVE\\u00d1O PONCIANO ALEJANDRO GABRIEL","ClienteDireccion":"DIRECCION NO REGISTRADA","direccion":"DIRECCION NO REGISTRADA","ClienteNumero":null,"ClienteEstado":"A","departamento":"","provincia":"","distrito":"","nombre_comercial":"-"},"proveedor":{"ProveedorRuc":"10726797451","ProveedorRazonSocial":"TRIVE\\u00d1O PONCIANO ALEJANDRO GABRIEL","ProveedorTipoContribuyente":"PERSONA NATURAL SIN NEGOCIO","ProveedorEstado":"A","ProveedorActividadEconomica":"Principal - 9609 - OTRAS ACTIVIDADES DE SERVI","ProveedorTelefono":"-","ProveedorDireccion":"DIRECCION NO REGISTRADA","direccion":"DIRECCION NO REGISTRADA","departamento":"","provincia":"","distrito":"","nombre_comercial":"-"}}',
                'ConsultaRucVecesConsultado' => '6',
                'ConsultaRucUltimaConsulta' => '2026-09-07 20:28:31.683',
                'ConsultaRucUsuarioCreacion' => 'SISTEMA',
                'ConsultaRucHostCreacion' => '127.0.0.1',
                'ConsultaRucFechaCreacion' => '2026-09-07 20:27:46.387',
                'ConsultaRucUsuarioModificacion' => 'SISTEMA',
                'ConsultaRucHostModificacion' => '127.0.0.1',
                'ConsultaRucFechaModificacion' => '2026-09-07 20:28:31.677',
            ],
            [
                'ConsultaRucId' => '5',
                'ConsultaRucNumero' => '20131312955',
                'ConsultaRucRazonSocial' => 'SUPERINTENDENCIA NACIONAL DE ADUANAS Y DE ADMINISTRACION TRIBUTARIA - SUNAT',
                'ConsultaRucEstado' => 'ACTIVO',
                'ConsultaRucCondicion' => 'HABIDO',
                'ConsultaRucDireccion' => 'AV. GARCILASO DE LA VEGA NRO 1472, LIMA - LIMA - LIMA',
                'ConsultaRucDepartamento' => 'LIMA',
                'ConsultaRucProvincia' => 'LIMA',
                'ConsultaRucDistrito' => 'LIMA',
                'ConsultaRucUbigeo' => '150101',
                'ConsultaRucTipoContribuyente' => 'SOCIEDAD ANONIMA',
                'ConsultaRucFechaInscripcion' => null,
                'ConsultaRucActividadEconomica' => '-',
                'ConsultaRucTelefono' => null,
                'ConsultaRucRawJson' => '{"success":true,"is_fallback":false,"source":"GATEWAY_JSON","ruc":"20131312955","razon_social":"SUPERINTENDENCIA NACIONAL DE ADUANAS Y DE ADMINISTRACION TRIBUTARIA - SUNAT","direccion":"AV. GARCILASO DE LA VEGA NRO 1472, LIMA - LIMA - LIMA","direccion_completa":"AV. GARCILASO DE LA VEGA NRO 1472, LIMA - LIMA - LIMA","departamento":"LIMA","provincia":"LIMA","distrito":"LIMA","ubigeo":"150101","estado":"ACTIVO","condicion":"HABIDO","tipo_contribuyente":"SOCIEDAD ANONIMA","nombre_comercial":"-","tipo_documento":"","fecha_inscripcion":"","fecha_inicio_actividades":"","sistema_emision":"","actividad_exterior":"","sistema_contabilidad":"","actividad_economica":"-","emision_electronica":"","emisor_desde":"","comprobantes_electronicos":"","afiliado_ple":"","padrones":"","raw_data":{"ruc":"20131312955","razon_social":"SUPERINTENDENCIA NACIONAL DE ADUANAS Y DE ADMINISTRACION TRIBUTARIA - SUNAT","estado":"ACTIVO","condicion":"HABIDO","direccion_fiscal":"AV. GARCILASO DE LA VEGA NRO 1472, LIMA - LIMA - LIMA","direccion":"AV. GARCILASO DE LA VEGA NRO 1472, LIMA - LIMA - LIMA","distrito":"LIMA","provincia":"LIMA","departamento":"LIMA","ubigeo":"150101","tipo_contribuyente":"SOCIEDAD ANONIMA","nombre_comercial":"-","tipo_documento":"","fecha_inscripcion":"","fecha_inicio_actividades":"","sistema_emision":"","actividad_exterior":"","sistema_contabilidad":"","actividad_economica":"-","emision_electronica":"","emisor_desde":"","comprobantes_electronicos":"","afiliado_ple":"","padrones":"","telefono":""},"cliente":{"ClienteRuc":"20131312955","ClienteNombre":"SUPERINTENDENCIA NACIONAL DE ADUANAS Y DE ADMINISTRACION TRIBUTARIA - SUNAT","ClienteDireccion":"AV. GARCILASO DE LA VEGA NRO 1472, LIMA - LIMA - LIMA","direccion":"AV. GARCILASO DE LA VEGA NRO 1472, LIMA - LIMA - LIMA","ClienteNumero":null,"ClienteEstado":"A","departamento":"LIMA","provincia":"LIMA","distrito":"LIMA","nombre_comercial":"-"},"proveedor":{"ProveedorRuc":"20131312955","ProveedorRazonSocial":"SUPERINTENDENCIA NACIONAL DE ADUANAS Y DE ADM","ProveedorTipoContribuyente":"SOCIEDAD ANONIMA","ProveedorEstado":"A","ProveedorActividadEconomica":"-","ProveedorTelefono":"-","ProveedorDireccion":"AV. GARCILASO DE LA VEGA NRO 1472, LIMA - LIMA - LIMA","direccion":"AV. GARCILASO DE LA VEGA NRO 1472, LIMA - LIMA - LIMA","departamento":"LIMA","provincia":"LIMA","distrito":"LIMA","nombre_comercial":"-"}}',
                'ConsultaRucVecesConsultado' => '1',
                'ConsultaRucUltimaConsulta' => '2026-09-07 20:28:38.197',
                'ConsultaRucUsuarioCreacion' => 'SISTEMA',
                'ConsultaRucHostCreacion' => '127.0.0.1',
                'ConsultaRucFechaCreacion' => '2026-09-07 20:28:38.197',
                'ConsultaRucUsuarioModificacion' => null,
                'ConsultaRucHostModificacion' => null,
                'ConsultaRucFechaModificacion' => null,
            ],
            [
                'ConsultaRucId' => '6',
                'ConsultaRucNumero' => '20552103816',
                'ConsultaRucRazonSocial' => 'AGROLIGHT PERU S.A.C.',
                'ConsultaRucEstado' => 'SUSPENSION TEMPORAL',
                'ConsultaRucCondicion' => 'HABIDO',
                'ConsultaRucDireccion' => 'PJ. JORGE BASADRE NRO 158 URB. POP LA UNIVERSAL 2DA ET., SANTA ANITA - LIMA - LIMA',
                'ConsultaRucDepartamento' => 'LIMA',
                'ConsultaRucProvincia' => 'LIMA',
                'ConsultaRucDistrito' => 'SANTA ANITA',
                'ConsultaRucUbigeo' => '150137',
                'ConsultaRucTipoContribuyente' => 'SOCIEDAD ANONIMA CERRADA',
                'ConsultaRucFechaInscripcion' => null,
                'ConsultaRucActividadEconomica' => '-',
                'ConsultaRucTelefono' => null,
                'ConsultaRucRawJson' => '{"success":true,"is_fallback":false,"source":"GATEWAY_JSON","ruc":"20552103816","razon_social":"AGROLIGHT PERU S.A.C.","direccion":"PJ. JORGE BASADRE NRO 158 URB. POP LA UNIVERSAL 2DA ET., SANTA ANITA - LIMA - LIMA","direccion_completa":"PJ. JORGE BASADRE NRO 158 URB. POP LA UNIVERSAL 2DA ET., SANTA ANITA - LIMA - LIMA","departamento":"LIMA","provincia":"LIMA","distrito":"SANTA ANITA","ubigeo":"150137","estado":"SUSPENSION TEMPORAL","condicion":"HABIDO","tipo_contribuyente":"SOCIEDAD ANONIMA CERRADA","nombre_comercial":"-","tipo_documento":"","fecha_inscripcion":"","fecha_inicio_actividades":"","sistema_emision":"","actividad_exterior":"","sistema_contabilidad":"","actividad_economica":"-","emision_electronica":"","emisor_desde":"","comprobantes_electronicos":"","afiliado_ple":"","padrones":"","raw_data":{"ruc":"20552103816","razon_social":"AGROLIGHT PERU S.A.C.","estado":"SUSPENSION TEMPORAL","condicion":"HABIDO","direccion_fiscal":"PJ. JORGE BASADRE NRO 158 URB. POP LA UNIVERSAL 2DA ET., SANTA ANITA - LIMA - LIMA","direccion":"PJ. JORGE BASADRE NRO 158 URB. POP LA UNIVERSAL 2DA ET., SANTA ANITA - LIMA - LIMA","distrito":"SANTA ANITA","provincia":"LIMA","departamento":"LIMA","ubigeo":"150137","tipo_contribuyente":"SOCIEDAD ANONIMA CERRADA","nombre_comercial":"-","tipo_documento":"","fecha_inscripcion":"","fecha_inicio_actividades":"","sistema_emision":"","actividad_exterior":"","sistema_contabilidad":"","actividad_economica":"-","emision_electronica":"","emisor_desde":"","comprobantes_electronicos":"","afiliado_ple":"","padrones":"","telefono":""},"cliente":{"ClienteRuc":"20552103816","ClienteNombre":"AGROLIGHT PERU S.A.C.","ClienteDireccion":"PJ. JORGE BASADRE NRO 158 URB. POP LA UNIVERSAL 2DA ET., SANTA ANITA - LIMA - LIMA","direccion":"PJ. JORGE BASADRE NRO 158 URB. POP LA UNIVERSAL 2DA ET., SANTA ANITA - LIMA - LIMA","ClienteNumero":null,"ClienteEstado":"I","departamento":"LIMA","provincia":"LIMA","distrito":"SANTA ANITA","nombre_comercial":"-"},"proveedor":{"ProveedorRuc":"20552103816","ProveedorRazonSocial":"AGROLIGHT PERU S.A.C.","ProveedorTipoContribuyente":"SOCIEDAD ANONIMA CERRADA","ProveedorEstado":"I","ProveedorActividadEconomica":"-","ProveedorTelefono":"-","ProveedorDireccion":"PJ. JORGE BASADRE NRO 158 URB. POP LA UNIVERSAL 2DA ET., SANTA ANITA - LIMA - LIMA","direccion":"PJ. JORGE BASADRE NRO 158 URB. POP LA UNIVERSAL 2DA ET., SANTA ANITA - LIMA - LIMA","departamento":"LIMA","provincia":"LIMA","distrito":"SANTA ANITA","nombre_comercial":"-"}}',
                'ConsultaRucVecesConsultado' => '2',
                'ConsultaRucUltimaConsulta' => '2026-09-07 22:09:26.360',
                'ConsultaRucUsuarioCreacion' => 'SISTEMA',
                'ConsultaRucHostCreacion' => '127.0.0.1',
                'ConsultaRucFechaCreacion' => '2026-09-07 20:28:39.247',
                'ConsultaRucUsuarioModificacion' => null,
                'ConsultaRucHostModificacion' => null,
                'ConsultaRucFechaModificacion' => null,
            ],
            [
                'ConsultaRucId' => '7',
                'ConsultaRucNumero' => '10181935451',
                'ConsultaRucRazonSocial' => 'VALENCIA VILLANUEVA BERNABE',
                'ConsultaRucEstado' => 'ACTIVO',
                'ConsultaRucCondicion' => 'HABIDO',
                'ConsultaRucDireccion' => 'DIRECCION NO REGISTRADA',
                'ConsultaRucDepartamento' => null,
                'ConsultaRucProvincia' => null,
                'ConsultaRucDistrito' => null,
                'ConsultaRucUbigeo' => null,
                'ConsultaRucTipoContribuyente' => 'PERSONA NATURAL CON NEGOCIO',
                'ConsultaRucFechaInscripcion' => null,
                'ConsultaRucActividadEconomica' => 'ACTIVIDAD COMERCIAL Y DE SERVICIOS',
                'ConsultaRucTelefono' => null,
                'ConsultaRucRawJson' => '{"success":true,"is_fallback":false,"source":"GATEWAY_JSON","ruc":"10181935451","razon_social":"VALENCIA VILLANUEVA BERNABE","direccion":"DIRECCION NO REGISTRADA","direccion_completa":"DIRECCION NO REGISTRADA","departamento":"","provincia":"","distrito":"","ubigeo":"","estado":"ACTIVO","condicion":"HABIDO","tipo_contribuyente":"PERSONA NATURAL CON NEGOCIO","nombre_comercial":"-","tipo_documento":"","fecha_inscripcion":"","fecha_inicio_actividades":"","sistema_emision":"","actividad_exterior":"","sistema_contabilidad":"","actividad_economica":"ACTIVIDAD COMERCIAL Y DE SERVICIOS","emision_electronica":"","emisor_desde":"","comprobantes_electronicos":"","afiliado_ple":"","padrones":"","raw_data":{"ruc":"10181935451","razon_social":"VALENCIA VILLANUEVA BERNABE","estado":"ACTIVO","condicion":"HABIDO","direccion_fiscal":"DIRECCION NO REGISTRADA","direccion":"DIRECCION NO REGISTRADA","distrito":"","provincia":"","departamento":"","ubigeo":"","tipo_contribuyente":"PERSONA NATURAL CON NEGOCIO","nombre_comercial":"-","tipo_documento":"","fecha_inscripcion":"","fecha_inicio_actividades":"","sistema_emision":"","actividad_exterior":"","sistema_contabilidad":"","actividad_economica":"ACTIVIDAD COMERCIAL Y DE SERVICIOS","emision_electronica":"","emisor_desde":"","comprobantes_electronicos":"","afiliado_ple":"","padrones":"","telefono":""},"cliente":{"ClienteRuc":"10181935451","ClienteNombre":"VALENCIA VILLANUEVA BERNABE","ClienteDireccion":"DIRECCION NO REGISTRADA","direccion":"DIRECCION NO REGISTRADA","ClienteNumero":null,"ClienteEstado":"A","departamento":"","provincia":"","distrito":"","nombre_comercial":"-"},"proveedor":{"ProveedorRuc":"10181935451","ProveedorRazonSocial":"VALENCIA VILLANUEVA BERNABE","ProveedorTipoContribuyente":"PERSONA NATURAL CON NEGOCIO","ProveedorEstado":"A","ProveedorActividadEconomica":"ACTIVIDAD COMERCIAL Y DE SERVICIOS","ProveedorTelefono":"-","ProveedorDireccion":"DIRECCION NO REGISTRADA","direccion":"DIRECCION NO REGISTRADA","departamento":"","provincia":"","distrito":"","nombre_comercial":"-"}}',
                'ConsultaRucVecesConsultado' => '9',
                'ConsultaRucUltimaConsulta' => '2026-09-09 16:21:40.627',
                'ConsultaRucUsuarioCreacion' => 'SISTEMA',
                'ConsultaRucHostCreacion' => '127.0.0.1',
                'ConsultaRucFechaCreacion' => '2026-09-07 20:39:46.277',
                'ConsultaRucUsuarioModificacion' => 'SISTEMA',
                'ConsultaRucHostModificacion' => '127.0.0.1',
                'ConsultaRucFechaModificacion' => '2026-09-09 16:21:40.627',
            ],
            [
                'ConsultaRucId' => '8',
                'ConsultaRucNumero' => '20544622804',
                'ConsultaRucRazonSocial' => 'PAPACHOS RESTAURANTES S.A.C.',
                'ConsultaRucEstado' => 'ACTIVO',
                'ConsultaRucCondicion' => 'HABIDO',
                'ConsultaRucDireccion' => 'CAL. CORONEL ANDRES REYES NRO 338 INT. 301, SAN ISIDRO - LIMA - LIMA',
                'ConsultaRucDepartamento' => 'LIMA',
                'ConsultaRucProvincia' => 'LIMA',
                'ConsultaRucDistrito' => 'SAN ISIDRO',
                'ConsultaRucUbigeo' => '150131',
                'ConsultaRucTipoContribuyente' => null,
                'ConsultaRucFechaInscripcion' => null,
                'ConsultaRucActividadEconomica' => 'RESTAURANTES Y SERVICIOS DE COMIDAS',
                'ConsultaRucTelefono' => null,
                'ConsultaRucRawJson' => '{"success":true,"is_fallback":false,"source":"PHP_GATEWAY_FALLBACK","ruc":"20544622804","razon_social":"PAPACHOS RESTAURANTES S.A.C.","direccion":"CAL. CORONEL ANDRES REYES NRO 338 INT. 301, SAN ISIDRO - LIMA - LIMA","direccion_completa":"CAL. CORONEL ANDRES REYES NRO 338 INT. 301, SAN ISIDRO - LIMA - LIMA","departamento":"LIMA","provincia":"LIMA","distrito":"SAN ISIDRO","ubigeo":"150131","estado":"ACTIVO","condicion":"HABIDO","tipo_contribuyente":"","raw_data":{"ruc":"20544622804","razon_social":"PAPACHOS RESTAURANTES S.A.C.","estado":"ACTIVO","condicion":"HABIDO","direccion_fiscal":"CAL. CORONEL ANDRES REYES NRO 338 INT. 301, SAN ISIDRO - LIMA - LIMA","direccion":"CAL. CORONEL ANDRES REYES NRO 338 INT. 301, SAN ISIDRO - LIMA - LIMA","distrito":"SAN ISIDRO","provincia":"LIMA","departamento":"LIMA","ubigeo":"150131","tipo_contribuyente":"","fecha_inscripcion":"","actividad_economica":"","telefono":null},"cliente":{"ClienteRuc":"20544622804","ClienteNombre":"PAPACHOS RESTAURANTES S.A.C.","ClienteDireccion":"CAL. CORONEL ANDRES REYES NRO 338 INT. 301, SAN ISIDRO - LIMA - LIMA","direccion":"CAL. CORONEL ANDRES REYES NRO 338 INT. 301, SAN ISIDRO - LIMA - LIMA","ClienteNumero":null,"ClienteEstado":"A","departamento":"LIMA","provincia":"LIMA","distrito":"SAN ISIDRO"},"proveedor":{"ProveedorRuc":"20544622804","ProveedorRazonSocial":"PAPACHOS RESTAURANTES S.A.C.","ProveedorTipoContribuyente":"GENERAL","ProveedorEstado":"A","ProveedorActividadEconomica":"-","ProveedorTelefono":"-","ProveedorDireccion":"CAL. CORONEL ANDRES REYES NRO 338 INT. 301, SAN ISIDRO - LIMA - LIMA","direccion":"CAL. CORONEL ANDRES REYES NRO 338 INT. 301, SAN ISIDRO - LIMA - LIMA","departamento":"LIMA","provincia":"LIMA","distrito":"SAN ISIDRO"}}',
                'ConsultaRucVecesConsultado' => '6',
                'ConsultaRucUltimaConsulta' => '2026-09-07 22:23:11.580',
                'ConsultaRucUsuarioCreacion' => 'SISTEMA',
                'ConsultaRucHostCreacion' => '127.0.0.1',
                'ConsultaRucFechaCreacion' => '2026-09-07 22:10:08.973',
                'ConsultaRucUsuarioModificacion' => null,
                'ConsultaRucHostModificacion' => null,
                'ConsultaRucFechaModificacion' => null,
            ],
            [
                'ConsultaRucId' => '9',
                'ConsultaRucNumero' => '20514680401',
                'ConsultaRucRazonSocial' => 'ANTICUCHOS DEL PERU S.A.C.',
                'ConsultaRucEstado' => 'ACTIVO',
                'ConsultaRucCondicion' => 'HABIDO',
                'ConsultaRucDireccion' => 'CAL. 2 DE MAYO NRO 298, MIRAFLORES - LIMA - LIMA',
                'ConsultaRucDepartamento' => 'LIMA',
                'ConsultaRucProvincia' => 'LIMA',
                'ConsultaRucDistrito' => 'MIRAFLORES',
                'ConsultaRucUbigeo' => '150122',
                'ConsultaRucTipoContribuyente' => null,
                'ConsultaRucFechaInscripcion' => null,
                'ConsultaRucActividadEconomica' => 'ACTIVIDAD COMERCIAL GENERAL',
                'ConsultaRucTelefono' => null,
                'ConsultaRucRawJson' => '{"success":true,"is_fallback":false,"source":"PHP_GATEWAY_FALLBACK","ruc":"20514680401","razon_social":"ANTICUCHOS DEL PERU S.A.C.","direccion":"CAL. 2 DE MAYO NRO 298, MIRAFLORES - LIMA - LIMA","direccion_completa":"CAL. 2 DE MAYO NRO 298, MIRAFLORES - LIMA - LIMA","departamento":"LIMA","provincia":"LIMA","distrito":"MIRAFLORES","ubigeo":"150122","estado":"ACTIVO","condicion":"HABIDO","tipo_contribuyente":"","raw_data":{"ruc":"20514680401","razon_social":"ANTICUCHOS DEL PERU S.A.C.","estado":"ACTIVO","condicion":"HABIDO","direccion_fiscal":"CAL. 2 DE MAYO NRO 298, MIRAFLORES - LIMA - LIMA","direccion":"CAL. 2 DE MAYO NRO 298, MIRAFLORES - LIMA - LIMA","distrito":"MIRAFLORES","provincia":"LIMA","departamento":"LIMA","ubigeo":"150122","tipo_contribuyente":"","fecha_inscripcion":"","actividad_economica":"ACTIVIDAD COMERCIAL GENERAL","telefono":null},"cliente":{"ClienteRuc":"20514680401","ClienteNombre":"ANTICUCHOS DEL PERU S.A.C.","ClienteDireccion":"CAL. 2 DE MAYO NRO 298, MIRAFLORES - LIMA - LIMA","direccion":"CAL. 2 DE MAYO NRO 298, MIRAFLORES - LIMA - LIMA","ClienteNumero":null,"ClienteEstado":"A","departamento":"LIMA","provincia":"LIMA","distrito":"MIRAFLORES"},"proveedor":{"ProveedorRuc":"20514680401","ProveedorRazonSocial":"ANTICUCHOS DEL PERU S.A.C.","ProveedorTipoContribuyente":"GENERAL","ProveedorEstado":"A","ProveedorActividadEconomica":"ACTIVIDAD COMERCIAL GENERAL","ProveedorTelefono":"-","ProveedorDireccion":"CAL. 2 DE MAYO NRO 298, MIRAFLORES - LIMA - LIMA","direccion":"CAL. 2 DE MAYO NRO 298, MIRAFLORES - LIMA - LIMA","departamento":"LIMA","provincia":"LIMA","distrito":"MIRAFLORES"}}',
                'ConsultaRucVecesConsultado' => '1',
                'ConsultaRucUltimaConsulta' => '2026-09-07 22:24:43.183',
                'ConsultaRucUsuarioCreacion' => 'SISTEMA',
                'ConsultaRucHostCreacion' => '127.0.0.1',
                'ConsultaRucFechaCreacion' => '2026-09-07 22:24:43.183',
                'ConsultaRucUsuarioModificacion' => null,
                'ConsultaRucHostModificacion' => null,
                'ConsultaRucFechaModificacion' => null,
            ],
            [
                'ConsultaRucId' => '10',
                'ConsultaRucNumero' => '20615580610',
                'ConsultaRucRazonSocial' => 'VELION TECHNOLOGY E.I.R.L.',
                'ConsultaRucEstado' => 'ACTIVO',
                'ConsultaRucCondicion' => 'HABIDO',
                'ConsultaRucDireccion' => 'AV. LOS LIBERTADORES NRO 824 A.H. RAMON CASTILLA, HUANCHACO - TRUJILLO - LA LIBERTAD',
                'ConsultaRucDepartamento' => 'LA LIBERTAD',
                'ConsultaRucProvincia' => 'TRUJILLO',
                'ConsultaRucDistrito' => 'HUANCHACO',
                'ConsultaRucUbigeo' => '130104',
                'ConsultaRucTipoContribuyente' => null,
                'ConsultaRucFechaInscripcion' => null,
                'ConsultaRucActividadEconomica' => 'ACTIVIDAD COMERCIAL GENERAL',
                'ConsultaRucTelefono' => null,
                'ConsultaRucRawJson' => '{"success":true,"is_fallback":false,"source":"PHP_GATEWAY_FALLBACK","ruc":"20615580610","razon_social":"VELION TECHNOLOGY E.I.R.L.","direccion":"AV. LOS LIBERTADORES NRO 824 A.H. RAMON CASTILLA, HUANCHACO - TRUJILLO - LA LIBERTAD","direccion_completa":"AV. LOS LIBERTADORES NRO 824 A.H. RAMON CASTILLA, HUANCHACO - TRUJILLO - LA LIBERTAD","departamento":"LA LIBERTAD","provincia":"TRUJILLO","distrito":"HUANCHACO","ubigeo":"130104","estado":"ACTIVO","condicion":"HABIDO","tipo_contribuyente":"","raw_data":{"ruc":"20615580610","razon_social":"VELION TECHNOLOGY E.I.R.L.","estado":"ACTIVO","condicion":"HABIDO","direccion_fiscal":"AV. LOS LIBERTADORES NRO 824 A.H. RAMON CASTILLA, HUANCHACO - TRUJILLO - LA LIBERTAD","direccion":"AV. LOS LIBERTADORES NRO 824 A.H. RAMON CASTILLA, HUANCHACO - TRUJILLO - LA LIBERTAD","distrito":"HUANCHACO","provincia":"TRUJILLO","departamento":"LA LIBERTAD","ubigeo":"130104","tipo_contribuyente":"","fecha_inscripcion":"","actividad_economica":"ACTIVIDAD COMERCIAL GENERAL","telefono":null},"cliente":{"ClienteRuc":"20615580610","ClienteNombre":"VELION TECHNOLOGY E.I.R.L.","ClienteDireccion":"AV. LOS LIBERTADORES NRO 824 A.H. RAMON CASTILLA, HUANCHACO - TRUJILLO - LA LIBERTAD","direccion":"AV. LOS LIBERTADORES NRO 824 A.H. RAMON CASTILLA, HUANCHACO - TRUJILLO - LA LIBERTAD","ClienteNumero":null,"ClienteEstado":"A","departamento":"LA LIBERTAD","provincia":"TRUJILLO","distrito":"HUANCHACO"},"proveedor":{"ProveedorRuc":"20615580610","ProveedorRazonSocial":"VELION TECHNOLOGY E.I.R.L.","ProveedorTipoContribuyente":"GENERAL","ProveedorEstado":"A","ProveedorActividadEconomica":"ACTIVIDAD COMERCIAL GENERAL","ProveedorTelefono":"-","ProveedorDireccion":"AV. LOS LIBERTADORES NRO 824 A.H. RAMON CASTILLA, HUANCHACO - TRUJILLO - LA LIBERTAD","direccion":"AV. LOS LIBERTADORES NRO 824 A.H. RAMON CASTILLA, HUANCHACO - TRUJILLO - LA LIBERTAD","departamento":"LA LIBERTAD","provincia":"TRUJILLO","distrito":"HUANCHACO"}}',
                'ConsultaRucVecesConsultado' => '1',
                'ConsultaRucUltimaConsulta' => '2026-09-16 18:01:15.727',
                'ConsultaRucUsuarioCreacion' => 'SISTEMA',
                'ConsultaRucHostCreacion' => '127.0.0.1',
                'ConsultaRucFechaCreacion' => '2026-09-16 18:01:15.727',
                'ConsultaRucUsuarioModificacion' => null,
                'ConsultaRucHostModificacion' => null,
                'ConsultaRucFechaModificacion' => null,
            ],
        ];

        // Inserción en bloques con IDENTITY_INSERT habilitado
        foreach (array_chunk($rows, 25) as $chunk) {
            if (empty($chunk)) continue;
            $columns = array_keys($chunk[0]);
            $colList = '[' . implode('], [', $columns) . ']';

            $valuesSql = [];
            foreach ($chunk as $r) {
                $valParts = [];
                foreach ($r as $val) {
                    if ($val === null) {
                        $valParts[] = 'NULL';
                    } elseif (is_numeric($val) && !is_string($val)) {
                        $valParts[] = $val;
                    } elseif (is_bool($val)) {
                        $valParts[] = $val ? '1' : '0';
                    } else {
                        $escaped = str_replace("'", "''", (string)$val);
                        $valParts[] = "N'" . $escaped . "'";
                    }
                }
                $valuesSql[] = '(' . implode(', ', $valParts) . ')';
            }

            $sql = "SET IDENTITY_INSERT [Consulta_Ruc] ON;\n"
                 . "INSERT INTO [Consulta_Ruc] ($colList) VALUES\n"
                 . implode(",\n", $valuesSql) . ";\n"
                 . "SET IDENTITY_INSERT [Consulta_Ruc] OFF;";

            DB::unprepared($sql);
        }

        // Reactivar restricciones de claves foráneas
        DB::statement('EXEC sp_msforeachtable "ALTER TABLE ? WITH CHECK CHECK CONSTRAINT all"');
    }
}
