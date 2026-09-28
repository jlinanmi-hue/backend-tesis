import requests
from requests.adapters import HTTPAdapter
from urllib3.util.retry import Retry
import random

class SessionManager:
    """
    Manages HTTP session for SUNAT portal communication.
    Maintains cookies, simulates real browser headers, and handles transient failures.
    """

    USER_AGENTS = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:129.0) Gecko/20100101 Firefox/129.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
    ]

    BASE_URL = "https://e-consultaruc.sunat.gob.pe/cl-ti-itmrconsruc"
    FRAME_URL = f"{BASE_URL}/FrameCriterioBusquedaWeb.jsp"
    ALIAS_URL = f"{BASE_URL}/jcrS00Alias"

    def __init__(self, timeout=10, retries=3):
        self.timeout = timeout
        self.retries = retries
        self.session = None
        self.captcha_url = None
        self._init_session()

    def _init_session(self):
        """Initializes a new requests Session with retry strategy and default headers."""
        self.session = requests.Session()
        
        retry_strategy = Retry(
            total=self.retries,
            backoff_factor=0.5,
            status_forcelist=[429, 500, 502, 503, 504],
            allowed_methods=["HEAD", "GET", "POST", "OPTIONS"]
        )
        adapter = HTTPAdapter(max_retries=retry_strategy)
        self.session.mount("https://", adapter)
        self.session.mount("http://", adapter)

        ua = random.choice(self.USER_AGENTS)
        self.session.headers.update({
            'User-Agent': ua,
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language': 'es-PE,es;q=0.9,en-US;q=0.8,en;q=0.7',
            'Connection': 'keep-alive',
            'Upgrade-Insecure-Requests': '1',
            'Sec-Fetch-Dest': 'document',
            'Sec-Fetch-Mode': 'navigate',
            'Sec-Fetch-Site': 'same-origin',
            'Sec-Fetch-User': '?1',
            'Cache-Control': 'max-age=0',
        })

    def get_initial_cookies(self):
        """
        Visits the search frame to acquire session cookies (ITMRCONSRUCSESSION, TS01..., etc.).
        """
        try:
            res = self.session.get(self.FRAME_URL, timeout=self.timeout)
            if res.status_code == 200:
                self.captcha_url = f"{self.BASE_URL}/captcha?accion=image"
                return True
        except Exception as e:
            pass
        return False

    def get(self, url, **kwargs):
        kwargs.setdefault('timeout', self.timeout)
        return self.session.get(url, **kwargs)

    def post(self, url, data=None, json=None, **kwargs):
        kwargs.setdefault('timeout', self.timeout)
        return self.session.post(url, data=data, json=json, **kwargs)

    def get_cookies_dict(self):
        return self.session.cookies.get_dict() if self.session else {}
