#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Barrido de caracteres fuera del español.

QUÉ BUSCA
---------
Fragmentos de otros alfabetos que se cuelan al escribir: cirílico (Ruso),
CJK (chino, japonés, coreano), árabe, hebreo, devanagari y otros.

POR QUÉ EXISTE ESTE SCRIPT
--------------------------
En un proyecto donde casi todo está en español, un carácter cirílico en un
comentario es una señal de que se pegó un fragmento de otra respuesta. Es
inofensivo en un archivo de texto, pero en código puede estar dentro de un
nombre de variable, una cadena de configuración o un mensaje al usuario, y ahí
sí importa.

Este script NO reemplaza revisar el texto: solo marca lo que hay que mirar.

CÓMO SE USA
-----------
    python herramientas/verificar_caracteres.py

Devuelve código de salida 1 si encuentra algo, para poder encadenarlo.
"""

from __future__ import annotations

import pathlib
import sys
import unicodedata

# Se excluyen las carpetas que no son código ni documentación del proyecto.
CARPETAS_IGNORADAS = {
    ".git",
    "node_modules",
    "vendor",
    ".nuxt",
    ".output",
    "dist",
    ".phpunit.cache",
    "storage",
}

EXTENSIONES = {
    ".php", ".ts", ".vue", ".md", ".json", ".yml", ".yaml",
    ".xml", ".env", ".example", ".sh", ".conf", ".txt",
}

# Rangos que no deberían aparecer en un proyecto en español.
RANGOS_SOSPECHOSOS = [
    (0x0400, 0x04FF, "cirílico"),
    (0x0370, 0x03FF, "griego"),
    (0x0530, 0x058F, "armenio"),
    (0x0590, 0x05FF, "hebreo"),
    (0x0600, 0x06FF, "árabe"),
    (0x0900, 0x097F, "devanagari"),
    (0x0E00, 0x0E7F, "tailandés"),
    (0x3040, 0x309F, "hiragana"),
    (0x30A0, 0x30FF, "katakana"),
    (0x3400, 0x4DBF, "CJK unificado"),
    (0x4E00, 0x9FFF, "CJK unificado"),
    (0xAC00, 0xD7AF, "hangul"),
    (0xF900, 0xFAFF, "CJK de compatibilidad"),
    (0xFF00, 0xFFEF, "ancho completo"),
]

def revisar_linea(linea: str, numero: int) -> list[str]:
    hallazgos: list[str] = []

    for indice, caracter in enumerate(linea):
        punto = ord(caracter)

        for inicio, fin, nombre in RANGOS_SOSPECHOSOS:
            if inicio <= punto <= fin:
                contexto = linea[max(0, indice - 25):indice + 25].strip()
                hallazgos.append(
                    f"    línea {numero}: {nombre} "
                    f"({unicodedata.name(caracter, 'desconocido')}) "
                    f"-> ...{contexto}..."
                )
                break

    return hallazgos


def es_revisable(ruta: pathlib.Path) -> bool:
    if any(parte in CARPETAS_IGNORADAS for parte in ruta.parts):
        return False

    if ruta.name.startswith(".env"):
        return True

    return ruta.suffix.lower() in EXTENSIONES


def main() -> int:
    raiz = pathlib.Path(__file__).resolve().parent.parent

    if not raiz.exists():
        print(f"No se encontró la raíz del proyecto en {raiz}")
        return 1

    archivos = sorted(
        ruta for ruta in raiz.rglob("*")
        if ruta.is_file() and es_revisable(ruta)
    )

    total_hallazgos = 0

    for archivo in archivos:
        try:
            contenido = archivo.read_text(encoding="utf-8")
        except (UnicodeDecodeError, OSError):
            # Un archivo que no es UTF-8 se reporta aparte, más abajo.
            continue

        hallazgos: list[str] = []

        for numero, linea in enumerate(contenido.splitlines(), start=1):
            hallazgos.extend(revisar_linea(linea, numero))

        if hallazgos:
            relative = archivo.relative_to(raiz)
            print(f"\n  {relative}")
            for hallazgo in hallazgos:
                print(hallazgo)
            total_hallazgos += len(hallazgos)

    print()
    print(f"Archivos revisados: {len(archivos)}")
    print(f"Fragmentos fuera del español: {total_hallazgos}")

    if total_hallazgos > 0:
        print("\nRevisa los lugares marcados y reemplaza por texto en español.")
        return 1

    print("Sin fragmentos de otros alfabetos.")
    return 0


if __name__ == "__main__":
    sys.exit(main())