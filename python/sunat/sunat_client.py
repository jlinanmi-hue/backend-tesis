import requests
from .session_manager import SessionManager
from .captcha_solver import CaptchaSolver
from .data_extractor import DataExtractor

class SunatClient:
    """
    Orchestrates RUC lookup across multiple strategies:
    0. Fast-path for Fallback / Non-RUC (10000000000).
    1. Primary High-Reliability Gateway (apis.net.pe / public mirrors).
    2. Direct SUNAT Web Scraper (with SessionManager and CaptchaSolver).
    """

    GENERIC_RUC = "10000000000"

    PRIMARY_GATEWAY_URL = "https://api.apis.net.pe/v1/ruc?numero={ruc}"
    SECONDARY_GATEWAYS = [
        "https://api.sunat.cloud/ruc/{ruc}",
        "https://dniruc.apisperu.com/api/v1/ruc/{ruc}",
    ]

    def __init__(self, tesseract_cmd=None, fallback_manual=False):
        self.session_mgr = SessionManager()
        self.captcha_solver = CaptchaSolver(tesseract_cmd=tesseract_cmd, fallback_manual=fallback_manual)

    def consultar_ruc(self, ruc):
        """
        Main query entry point.
        """
        clean_ruc = str(ruc).strip() if ruc else ""

        # Strategy 0: Non-RUC or Generic 10000000000
        if not clean_ruc or clean_ruc in [self.GENERIC_RUC, "null", "None", "00000000000"]:
            return DataExtractor.get_fallback_template(self.GENERIC_RUC)

        # Validate numeric format
        if not clean_ruc.isdigit() or len(clean_ruc) != 11:
            return {
                "success": False,
                "error": f"El número de RUC '{clean_ruc}' debe contener exactamente 11 dígitos numéricos.",
                "code": "INVALID_RUC_FORMAT"
            }

        # Strategy 1: High-reliability primary gateway
        try:
            result = self._consultar_gateway_primario(clean_ruc)
            if result and result.get("success"):
                return result
        except Exception:
            pass

        # Strategy 2: Secondary gateways
        for url_pattern in self.SECONDARY_GATEWAYS:
            try:
                result = self._consultar_secondary_gateway(url_pattern, clean_ruc)
                if result and result.get("success"):
                    return result
            except Exception:
                continue

        # Strategy 3: Direct SUNAT scraping
        try:
            result = self._consultar_sunat_directo(clean_ruc)
            if result and result.get("success"):
                return result
        except Exception:
            pass

        return {
            "success": False,
            "error": f"No se pudo obtener información del RUC {clean_ruc} desde SUNAT ni sus servicios asociados.",
            "code": "RUC_NOT_FOUND_OR_BLOCKED"
        }

    def _consultar_gateway_primario(self, ruc):
        """Queries the fast primary gateway."""
        url = self.PRIMARY_GATEWAY_URL.format(ruc=ruc)
        headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko)',
            'Accept': 'application/json, text/plain, */*',
            'Referer': 'https://apis.net.pe',
        }
        res = requests.get(url, headers=headers, timeout=6)
        if res.status_code == 200:
            data = res.json()
            if data and data.get("nombre"):
                return DataExtractor.parse_json(data, ruc)
        elif res.status_code == 422:
            return {
                "success": False,
                "error": f"El RUC {ruc} no es válido para SUNAT.",
                "code": "RUC_INVALIDO"
            }
        elif res.status_code == 404:
            return {
                "success": False,
                "error": f"El RUC {ruc} no existe en los padrones de SUNAT.",
                "code": "RUC_NOT_FOUND"
            }
        return None

    def _consultar_secondary_gateway(self, url_pattern, ruc):
        """Queries alternate fallback gateways."""
        url = url_pattern.format(ruc=ruc)
        headers = {'User-Agent': 'Mozilla/5.0'}
        res = requests.get(url, headers=headers, timeout=4)
        if res.status_code == 200:
            data = res.json()
            if data and (data.get("nombre") or data.get("razon_social")):
                return DataExtractor.parse_json(data, ruc)
        return None

    def _consultar_sunat_directo(self, ruc):
        """Scrapes SUNAT directly using session cookies and form submission."""
        self.session_mgr.get_initial_cookies()
        
        # Try to resolve captcha if needed
        captcha_code = ""
        if self.session_mgr.captcha_url:
            captcha_code = self.captcha_solver.solve(self.session_mgr) or ""

        post_data = {
            'accion': 'consPorRuc',
            'razSoc': '',
            'nroRuc': ruc,
            'nrodoc': '',
            'token': '',
            'contexto': 'ti-it',
            'modo': '1',
            'rbtnTipo': '1',
            'search1': ruc,
            'codigo': captcha_code
        }

        res = self.session_mgr.post(self.session_mgr.ALIAS_URL, data=post_data, timeout=10)
        if res.status_code == 200:
            return DataExtractor.parse_html(res.text, ruc)
        return None
