#!/usr/bin/env python3
"""
CLI entry point for SUNAT RUC consultation.
Outputs structured JSON to stdout for consumption by Laravel or other consumers.
"""
import sys
import json
import argparse

if hasattr(sys.stdout, 'reconfigure'):
    try:
        sys.stdout.reconfigure(encoding='utf-8')
    except Exception:
        pass

from sunat.sunat_client import SunatClient

def main():
    parser = argparse.ArgumentParser(description="Consulta RUC SUNAT para Cliente y Proveedor")
    parser.add_argument("ruc", help="Número de RUC (11 dígitos) o 10000000000 para plantilla por defecto")
    parser.add_argument("--target", choices=["all", "cliente", "proveedor"], default="all", help="Alcance de datos requeridos")
    parser.add_argument("--tesseract-cmd", default=None, help="Ruta al ejecutable tesseract.exe")
    parser.add_argument("--manual-captcha", action="store_true", help="Permitir ingreso manual de captcha en terminal")

    args = parser.parse_args()

    client = SunatClient(tesseract_cmd=args.tesseract_cmd, fallback_manual=args.manual_captcha)
    result = client.consultar_ruc(args.ruc)

    # Filtrar salida según --target si se solicitó uno específico
    if result.get("success") and args.target != "all":
        target_data = result.get(args.target, {})
        filtered = {
            "success": True,
            "ruc": args.ruc,
            "target": args.target,
            "direccion": result.get("direccion") or target_data.get("direccion"),
            "data": target_data,
            "raw_data": result.get("raw_data")
        }
        print(json.dumps(filtered, ensure_ascii=False))
        sys.exit(0)

    # Salida completa
    print(json.dumps(result, ensure_ascii=False))
    if not result.get("success"):
        sys.exit(1)
    sys.exit(0)

if __name__ == "__main__":
    main()
