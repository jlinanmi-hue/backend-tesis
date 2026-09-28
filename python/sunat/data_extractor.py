import re
from bs4 import BeautifulSoup

class DataExtractor:
    """
    Parses, cleans, and normalizes SUNAT taxpayer data from both JSON and HTML representations.
    Maps data directly to the required schemas for the 'Cliente' and 'Proveedor' database tables.
    """

    GENERIC_RUC = "10000000000"

    @classmethod
    def clean_text(cls, text):
        """Cleans whitespace, line breaks, and non-standard characters."""
        if not text:
            return ""
        text = re.sub(r'\s+', ' ', str(text)).strip()
        # Clean leading/trailing hyphens or commas
        text = text.strip(" -,\t\r\n")
        return text

    @classmethod
    def parse_json(cls, json_data, ruc):
        """
        Parses JSON responses from standard SUNAT gateways (e.g. apis.net.pe).
        """
        if not isinstance(json_data, dict):
            return None

        # Razón social / Nombre
        razon_social = cls.clean_text(json_data.get('nombre') or json_data.get('razonSocial') or '')
        
        # Estado y Condición
        estado = cls.clean_text(json_data.get('estado') or 'ACTIVO').upper()
        condicion = cls.clean_text(json_data.get('condicion') or 'HABIDO').upper()

        # Ubicación
        distrito = cls.clean_text(json_data.get('distrito') or '')
        provincia = cls.clean_text(json_data.get('provincia') or '')
        departamento = cls.clean_text(json_data.get('departamento') or '')
        ubigeo = cls.clean_text(json_data.get('ubigeo') or '')

        # Dirección completa
        raw_direccion = cls.clean_text(json_data.get('direccion') or '')
        if raw_direccion in ["-", "--", "None", "null", ""]:
            raw_direccion = ""
        direccion_fiscal = raw_direccion
        
        # Si la dirección no contiene distrito/departamento al final, concatenar limpiamente
        ubicacion_parts = [p for p in [distrito, provincia, departamento] if p and p != "-"]
        if ubicacion_parts:
            ubicacion_str = " - ".join(ubicacion_parts)
            if ubicacion_str and ubicacion_str.upper() not in direccion_fiscal.upper():
                if direccion_fiscal:
                    direccion_fiscal = f"{direccion_fiscal}, {ubicacion_str}"
                else:
                    direccion_fiscal = ubicacion_str

        if not direccion_fiscal or direccion_fiscal in ["-", "--", "None", "null", "LIMA, PERU"]:
            direccion_fiscal = "DIRECCION NO REGISTRADA"

        # Tipo contribuyente
        tipo_contribuyente = cls.clean_text(
            json_data.get('tipo') or 
            json_data.get('tipoContribuyente') or 
            cls.infer_tipo_contribuyente(ruc, razon_social)
        )

        fecha_inscripcion = cls.clean_text(json_data.get('fechaInscripcion') or '')
        actividad_economica = cls.clean_text(
            json_data.get('actividadEconomica') or 
            json_data.get('actEconomica') or 
            ''
        )
        telefono = cls.clean_text(json_data.get('telefono') or '')
        if telefono:
            telefono = re.sub(r'[^0-9+]', '', telefono)

        return cls.build_payload(
            ruc=ruc,
            razon_social=razon_social,
            estado=estado,
            condicion=condicion,
            direccion_fiscal=direccion_fiscal,
            distrito=distrito,
            provincia=provincia,
            departamento=departamento,
            ubigeo=ubigeo,
            tipo_contribuyente=tipo_contribuyente,
            fecha_inscripcion=fecha_inscripcion,
            actividad_economica=actividad_economica,
            telefono=telefono,
            source="GATEWAY_JSON"
        )

    @classmethod
    def parse_html(cls, html_content, ruc):
        """
        Parses the HTML result page from SUNAT's jcrS00Alias consultation form.
        """
        if not html_content:
            return None

        soup = BeautifulSoup(html_content, 'html.parser')

        # Si SUNAT devuelve página de error
        if "Pagina de Error" in soup.text or "El RUC ingresado no existe" in soup.text:
            return None

        razon_social = ""
        estado = "ACTIVO"
        condicion = "HABIDO"
        direccion_fiscal = ""
        tipo_contribuyente = ""
        fecha_inscripcion = ""
        actividad_economica = ""
        telefono = ""
        distrito = ""
        provincia = ""
        departamento = ""

        # Extracción detallada de todos los campos del panel Bootstrap de SUNAT
        extracted_fields = {}
        for item in soup.find_all(class_='list-group-item'):
            headings = item.find_all('h4', class_='list-group-item-heading')
            for h in headings:
                label = cls.clean_text(h.get_text(strip=True).rstrip(':')).upper()
                parent_col = h.find_parent('div', class_=re.compile(r'col-sm-'))
                if parent_col:
                    next_col = parent_col.find_next_sibling('div', class_=re.compile(r'col-sm-'))
                    if next_col:
                        text_el = next_col.find(class_='list-group-item-text')
                        if text_el:
                            val = cls.clean_text(text_el.get_text(" ", strip=True))
                        else:
                            tbl = next_col.find('table')
                            if tbl:
                                rows = [cls.clean_text(tr.get_text(" ", strip=True)) for tr in tbl.find_all('tr')]
                                val = " | ".join(r for r in rows if r)
                            else:
                                h4_val = next_col.find('h4')
                                if h4_val:
                                    val = cls.clean_text(h4_val.get_text(" ", strip=True))
                                else:
                                    val = cls.clean_text(next_col.get_text(" ", strip=True))
                        extracted_fields[label] = val

        # 1. Razón Social y RUC
        ruc_heading = extracted_fields.get('NÚMERO DE RUC') or extracted_fields.get('NUMERO DE RUC') or ''
        if ruc_heading and '-' in ruc_heading:
            parts = ruc_heading.split('-', 1)
            razon_social = cls.clean_text(parts[1])
        else:
            razon_social = ruc_heading

        # 2. Datos tributarios principales
        tipo_contribuyente = extracted_fields.get('TIPO CONTRIBUYENTE', '')
        estado = extracted_fields.get('ESTADO DEL CONTRIBUYENTE', 'ACTIVO').upper()
        condicion = extracted_fields.get('CONDICIÓN DEL CONTRIBUYENTE') or extracted_fields.get('CONDICION DEL CONTRIBUYENTE', 'HABIDO').upper()
        direccion_fiscal = extracted_fields.get('DOMICILIO FISCAL') or extracted_fields.get('DIRECCIÓN FISCAL', '')

        # 3. Datos complementarios de SUNAT
        nombre_comercial = extracted_fields.get('NOMBRE COMERCIAL', '-')
        tipo_documento = extracted_fields.get('TIPO DE DOCUMENTO', '')
        fecha_inscripcion = extracted_fields.get('FECHA DE INSCRIPCIÓN') or extracted_fields.get('FECHA DE INSCRIPCION', '')
        fecha_inicio_actividades = extracted_fields.get('FECHA DE INICIO DE ACTIVIDADES', '')
        sistema_emision = extracted_fields.get('SISTEMA EMISIÓN DE COMPROBANTE') or extracted_fields.get('SISTEMA EMISION DE COMPROBANTE', '')
        actividad_exterior = extracted_fields.get('ACTIVIDAD COMERCIO EXTERIOR', '')
        sistema_contabilidad = extracted_fields.get('SISTEMA CONTABILIDAD', '')
        actividad_economica = extracted_fields.get('ACTIVIDAD(ES) ECONÓMICA(S)') or extracted_fields.get('ACTIVIDAD ECONOMICA', '')
        emision_electronica = extracted_fields.get('SISTEMA DE EMISIÓN ELECTRÓNICA') or extracted_fields.get('SISTEMA DE EMISION ELECTRONICA', '')
        emisor_desde = extracted_fields.get('EMISOR ELECTRÓNICO DESDE') or extracted_fields.get('EMISOR ELECTRONICO DESDE', '')
        comprobantes_electronicos = extracted_fields.get('COMPROBANTES ELECTRÓNICOS') or extracted_fields.get('COMPROBANTES ELECTRONICOS', '')
        afiliado_ple = extracted_fields.get('AFILIADO AL PLE DESDE', '')
        padrones = extracted_fields.get('PADRONES', '')

        # Fallback table parsing (para vistas clásicas de tabla sin panel)
        if not razon_social:
            tables = soup.find_all('table')
            for table in tables:
                for row in table.find_all('tr'):
                    cells = [c.get_text(" ", strip=True) for c in row.find_all(['td', 'th'])]
                    if len(cells) >= 2:
                        label, val = cells[0].upper(), cells[1]
                        if "NÚMERO DE RUC" in label or "NUMERO DE RUC" in label:
                            parts = val.split("-", 1)
                            if len(parts) > 1:
                                razon_social = cls.clean_text(parts[1])
                        elif "ESTADO" in label:
                            estado = cls.clean_text(val).upper()
                        elif "CONDICIÓN" in label or "CONDICION" in label:
                            condicion = cls.clean_text(val).upper()
                        elif "DOMICILIO" in label or "DIRECCIÓN" in label:
                            direccion_fiscal = cls.clean_text(val)
                        elif "TIPO" in label and "CONTRIBUYENTE" in label:
                            tipo_contribuyente = cls.clean_text(val)

        if not razon_social:
            return None

        if not direccion_fiscal or direccion_fiscal in ["-", "--", "None", "null"]:
            direccion_fiscal = "DIRECCION NO REGISTRADA"

        return cls.build_payload(
            ruc=ruc,
            razon_social=razon_social,
            estado=estado,
            condicion=condicion,
            direccion_fiscal=direccion_fiscal,
            distrito=distrito,
            provincia=provincia,
            departamento=departamento,
            ubigeo="",
            tipo_contribuyente=tipo_contribuyente or cls.infer_tipo_contribuyente(ruc, razon_social),
            fecha_inscripcion=fecha_inscripcion,
            actividad_economica=actividad_economica,
            telefono=telefono,
            source="SUNAT_HTML_SCRAPER",
            nombre_comercial=nombre_comercial,
            tipo_documento=tipo_documento,
            fecha_inicio_actividades=fecha_inicio_actividades,
            sistema_emision=sistema_emision,
            actividad_exterior=actividad_exterior,
            sistema_contabilidad=sistema_contabilidad,
            emision_electronica=emision_electronica,
            emisor_desde=emisor_desde,
            comprobantes_electronicos=comprobantes_electronicos,
            afiliado_ple=afiliado_ple,
            padrones=padrones
        )

    @classmethod
    def get_fallback_template(cls, ruc=None):
        """
        Generates a standard default payload for entities without RUC or fallback RUC 10000000000.
        Allows immediate manual registration without external network calls.
        """
        actual_ruc = cls.GENERIC_RUC if (not ruc or str(ruc).strip() == cls.GENERIC_RUC) else str(ruc).strip()
        return cls.build_payload(
            ruc=actual_ruc,
            razon_social="CLIENTE / PROVEEDOR SIN RUC",
            estado="ACTIVO",
            condicion="HABIDO",
            direccion_fiscal="DIRECCION NO REGISTRADA",
            distrito="LIMA",
            provincia="LIMA",
            departamento="LIMA",
            ubigeo="150101",
            tipo_contribuyente="PERSONA NATURAL SIN NEGOCIO",
            fecha_inscripcion="",
            actividad_economica="",
            telefono=None,
            source="FALLBACK_TEMPLATE",
            is_fallback=True
        )

    @classmethod
    def infer_tipo_contribuyente(cls, ruc, razon_social=""):
        """Infers the contributor type based on RUC prefix and corporate suffix."""
        if not ruc or len(ruc) < 2:
            return "PERSONA NATURAL"
        prefix = ruc[:2]
        rs = (razon_social or "").upper()
        if prefix == "10":
            return "PERSONA NATURAL CON NEGOCIO"
        elif prefix == "20":
            if "S.A.C." in rs or "SAC" in rs:
                return "SOCIEDAD ANONIMA CERRADA"
            elif "S.A." in rs:
                return "SOCIEDAD ANONIMA"
            elif "E.I.R.L." in rs or "EIRL" in rs:
                return "EMP. INDIVIDUAL DE RESP. LTDA"
            elif "S.R.L." in rs or "SRL" in rs:
                return "SOCIEDAD COMERCIAL DE RESP. LTDA"
            return "SOCIEDAD ANONIMA"
        elif prefix in ["15", "17"]:
            return "PERSONA NATURAL EXTRANJERA"
        return "OTRO TIPO CONTRIBUYENTE"

    @classmethod
    def infer_actividad_economica(cls, razon_social="", tipo_contribuyente=""):
        """
        Infers standard CIIU business activity when external SUNAT gateways do not provide one.
        Ensures output length is strictly <= 45 chars for SQL Server column compatibility.
        """
        rs = (razon_social or "").upper()

        if any(w in rs for w in ["RESTAURANT", "POLLERIA", "CHIFA", "CEVICHERIA", "PIZZERIA", "CAFE", "BAR", "COMIDAS", "GASTRONOM", "FAST FOOD", "CATERING", "SNACK"]):
            return "RESTAURANTES Y SERVICIOS DE COMIDAS"
        if any(w in rs for w in ["ALIMENTO", "BEBIDA", "LICOR", "CARNICERIA", "PANADERIA", "PASTELERIA", "AVICOLA", "AGRO", "FRUTA"]):
            return "VENTA AL POR MAYOR DE ALIMENTOS Y BEBIDAS"
        if any(w in rs for w in ["DISTRIBUID", "MAYORIST", "COMERCIALIZAD", "IMPORT", "EXPORT", "ABARROTES", "MERCADERIA"]):
            return "VENTA AL POR MAYOR NO ESPECIALIZADA"
        if any(w in rs for w in ["TRANSPORT", "CARGA", "LOGISTIC", "MUDANZA", "COURIER", "ENCOMIENDA", "ENVIO"]):
            return "TRANSPORTE DE CARGA POR CARRETERA"
        if any(w in rs for w in ["FARMACIA", "BOTICA", "MEDIC", "SALUD", "CLINICA", "DENTAL", "OPTICA", "LABORATORI"]):
            return "VENTA DE PRODUCTOS FARMACEUTICOS"
        if any(w in rs for w in ["CONSTRUCC", "CONSTRUCTOR", "EDIFICAC", "INGENIER", "FERRETER", "OBRAS", "MATERIAL"]):
            return "CONSTRUCCION DE EDIFICIOS Y OBRAS"
        if any(w in rs for w in ["SISTEMA", "TECNOLOG", "SOFTWARE", "INFORMATIC", "COMPUTO", "DIGITAL", "CONSULT"]):
            return "CONSULTORIA DE EQUIPOS Y SISTEMAS"
        if any(w in rs for w in ["TEXTIL", "CONFECC", "MODA", "CALZADO", "CUERO", "ROPA", "VESTIR"]):
            return "FABRICACION DE PRENDAS DE VESTIR"
        if any(w in rs for w in ["SERVICIOS", "LIMPIEZA", "MANTENIMIENTO", "SEGURIDAD", "VIGILANCIA"]):
            return "SERVICIOS DE APOYO A EMPRESAS"

        tc = (tipo_contribuyente or "").upper()
        if "NATURAL" in tc:
            return "ACTIVIDAD COMERCIAL Y DE SERVICIOS"

        return "ACTIVIDAD COMERCIAL GENERAL"

    @classmethod
    def build_payload(cls, ruc, razon_social, estado, condicion, direccion_fiscal,
                      distrito, provincia, departamento, ubigeo, tipo_contribuyente,
                      fecha_inscripcion, actividad_economica, telefono, source,
                      nombre_comercial=None, tipo_documento=None, fecha_inicio_actividades=None,
                      sistema_emision=None, actividad_exterior=None, sistema_contabilidad=None,
                      emision_electronica=None, emisor_desde=None, comprobantes_electronicos=None,
                      afiliado_ple=None, padrones=None, is_fallback=False, **kwargs):
        """
        Constructs the final dictionary with normalized root keys and tailored sub-schemas
        for Cliente and Proveedor database models.
        """
        is_active = (estado == "ACTIVO" and condicion == "HABIDO")
        estado_char = 'A' if is_active else 'I'

        # Limitar longitud según restricciones estrictas de columnas de BD
        cliente_nombre = razon_social[:100]
        cliente_direccion = (direccion_fiscal or "LIMA, PERU")[:200]
        cliente_telefono = (telefono or None)
        if cliente_telefono:
            cliente_telefono = cliente_telefono[:45]

        # Normalizar e inferir actividad económica si está vacía
        actividad_limpia = (actividad_economica or "").strip()
        if not actividad_limpia or actividad_limpia in ["-", "--", "None", "null", "SIN ACTIVIDAD"]:
            actividad_limpia = cls.infer_actividad_economica(razon_social, tipo_contribuyente)

        # En la tabla Proveedor: ProveedorRazonSocial es varchar(45) y ProveedorActividadEconomica / ProveedorTelefono son NOT NULL
        proveedor_razon_social = (razon_social or "SIN RAZON SOCIAL")[:45]
        proveedor_tipo_contribuyente = (tipo_contribuyente or "GENERAL")[:45]
        proveedor_actividad = (actividad_limpia or "ACTIVIDAD COMERCIAL GENERAL")[:45]
        proveedor_telefono = (cliente_telefono or "-")[:45]

        return {
            "success": True,
            "is_fallback": is_fallback,
            "source": source,
            "ruc": ruc,
            "razon_social": razon_social,
            "direccion": direccion_fiscal,
            "direccion_completa": direccion_fiscal,
            "departamento": departamento,
            "provincia": provincia,
            "distrito": distrito,
            "ubigeo": ubigeo,
            "estado": estado,
            "condicion": condicion,
            "tipo_contribuyente": tipo_contribuyente,
            "nombre_comercial": nombre_comercial or "-",
            "tipo_documento": tipo_documento or "",
            "fecha_inscripcion": fecha_inscripcion or "",
            "fecha_inicio_actividades": fecha_inicio_actividades or "",
            "sistema_emision": sistema_emision or "",
            "actividad_exterior": actividad_exterior or "",
            "sistema_contabilidad": sistema_contabilidad or "",
            "actividad_economica": actividad_limpia or "ACTIVIDAD COMERCIAL GENERAL",
            "emision_electronica": emision_electronica or "",
            "emisor_desde": emisor_desde or "",
            "comprobantes_electronicos": comprobantes_electronicos or "",
            "afiliado_ple": afiliado_ple or "",
            "padrones": padrones or "",
            "raw_data": {
                "ruc": ruc,
                "razon_social": razon_social,
                "estado": estado,
                "condicion": condicion,
                "direccion_fiscal": direccion_fiscal,
                "direccion": direccion_fiscal,
                "distrito": distrito,
                "provincia": provincia,
                "departamento": departamento,
                "ubigeo": ubigeo,
                "tipo_contribuyente": tipo_contribuyente,
                "nombre_comercial": nombre_comercial or "-",
                "tipo_documento": tipo_documento or "",
                "fecha_inscripcion": fecha_inscripcion or "",
                "fecha_inicio_actividades": fecha_inicio_actividades or "",
                "sistema_emision": sistema_emision or "",
                "actividad_exterior": actividad_exterior or "",
                "sistema_contabilidad": sistema_contabilidad or "",
                "actividad_economica": actividad_limpia or "ACTIVIDAD COMERCIAL GENERAL",
                "emision_electronica": emision_electronica or "",
                "emisor_desde": emisor_desde or "",
                "comprobantes_electronicos": comprobantes_electronicos or "",
                "afiliado_ple": afiliado_ple or "",
                "padrones": padrones or "",
                "telefono": telefono,
            },
            "cliente": {
                "ClienteRuc": ruc,
                "ClienteNombre": cliente_nombre,
                "ClienteDireccion": cliente_direccion,
                "direccion": cliente_direccion,
                "ClienteNumero": cliente_telefono,
                "ClienteEstado": estado_char,
                "departamento": departamento,
                "provincia": provincia,
                "distrito": distrito,
                "nombre_comercial": nombre_comercial or "-",
            },
            "proveedor": {
                "ProveedorRuc": ruc,
                "ProveedorRazonSocial": proveedor_razon_social,
                "ProveedorTipoContribuyente": proveedor_tipo_contribuyente,
                "ProveedorEstado": estado_char,
                "ProveedorActividadEconomica": proveedor_actividad,
                "ProveedorTelefono": proveedor_telefono,
                "ProveedorDireccion": cliente_direccion,
                "direccion": cliente_direccion,
                "departamento": departamento,
                "provincia": provincia,
                "distrito": distrito,
                "nombre_comercial": nombre_comercial or "-",
            }
        }
