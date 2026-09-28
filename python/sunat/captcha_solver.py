import os
import sys
import io
from PIL import Image

class CaptchaSolver:
    """
    Handles Captcha resolution for SUNAT legacy endpoints.
    Uses Tesseract OCR if available, with pre-processing and manual fallback options.
    """

    DEFAULT_TESSERACT_PATHS = [
        r"C:\Program Files\Tesseract-OCR\tesseract.exe",
        r"C:\Program Files (x86)\Tesseract-OCR\tesseract.exe",
        r"C:\laragon\bin\tesseract\tesseract.exe",
    ]

    def __init__(self, tesseract_cmd=None, fallback_manual=False):
        self.tesseract_cmd = tesseract_cmd or self._find_tesseract()
        self.fallback_manual = fallback_manual

    def _find_tesseract(self):
        """Attempts to locate the tesseract executable on Windows."""
        for path in self.DEFAULT_TESSERACT_PATHS:
            if os.path.exists(path):
                return path
        return None

    def download_captcha(self, session_manager, output_path=None):
        """Downloads captcha image from the current session."""
        if not session_manager or not session_manager.captcha_url:
            return None
        try:
            res = session_manager.get(session_manager.captcha_url)
            if res.status_code == 200 and len(res.content) > 100:
                img = Image.open(io.BytesIO(res.content))
                if output_path:
                    img.save(output_path)
                return img
        except Exception:
            pass
        return None

    def solve(self, session_manager):
        """
        Attempts to solve the captcha:
        1. Via OCR (Tesseract)
        2. Via manual console prompt if fallback_manual is enabled and running interactively
        """
        img = self.download_captcha(session_manager)
        if not img:
            return None

        # 1. Try OCR
        code = self._solve_ocr(img)
        if code and len(code) >= 4:
            return code

        # 2. Fallback manual (only if requested and interactive stdin)
        if self.fallback_manual and sys.stdin and sys.stdin.isatty():
            manual_file = os.path.join(os.getcwd(), 'captcha_manual.png')
            img.save(manual_file)
            print(f"[SUNAT Captcha] Imagen guardada en: {manual_file}", file=sys.stderr)
            try:
                entered = input("Ingrese el captcha que observa en la imagen: ").strip().upper()
                if len(entered) >= 4:
                    return entered
            except Exception:
                pass

        return None

    def _solve_ocr(self, img):
        """Attempts OCR with PIL pre-processing and pytesseract."""
        try:
            import pytesseract
            if self.tesseract_cmd:
                pytesseract.pytesseract.tesseract_cmd = self.tesseract_cmd

            # Image pre-processing: Grayscale & simple threshold
            gray = img.convert('L')
            # Increase contrast / threshold
            threshold = 140
            bw = gray.point(lambda p: 255 if p > threshold else 0)
            
            # OCR config: alphanumeric only
            config = '--psm 7 -c tessedit_char_whitelist=ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'
            text = pytesseract.image_to_string(bw, config=config)
            cleaned = ''.join(c for c in text if c.isalnum()).upper()
            return cleaned if len(cleaned) >= 4 else None
        except Exception:
            return None
