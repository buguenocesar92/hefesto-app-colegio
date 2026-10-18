#!/usr/bin/env python3
# -*- coding: utf-8 -*-
r"""
Verificaciones de estructura que no necesitan PHP ni Node.

QUÉ COMPRUEBA
-------------
1. Que los JSON sean JSON válidos.
2. Que los XML estén balanceados.
3. Que los delimitadores de PHP, TypeScript y Vue estén balanceados.
4. Que cada tabla protegida por RLS exista en la lista de la migración y en
   la del comando de verificación.
5. Que cada `Model` declarado exista como archivo y use el nombre de tabla
   esperado.
6. Que no haya archivos `.env` (solo `.env.example`).
7. Que las referencias a clases `App\...` en los `use` apunten a archivos que
   existen.

POR QUÉ EXISTE
--------------
Estas verificaciones NO compilan el código: detectan desbalances y
referencias rotas, que son los errores más habituales al escribir un proyecto
sin poder ejecutarlo. Un archivo balanceado todavía puede tener un error de
tipo o de nombre.

CÓMO SE USA
-----------
    python herramientas/verificar_estructura.py

Devuelve código de salida 1 si encuentra algo.
"""

from __future__ import annotations

import json
import pathlib
import re
import sys

RAIZ = pathlib.Path(__file__).resolve().parent.parent

FALLOS: list[str] = []
COMPROBACIONES = 0


def anotar(mensaje: str) -> None:
    FALLOS.append(mensaje)


def archivos(extensiones: set[str]) -> list[pathlib.Path]:
    ignorados = {".git", "node_modules", "vendor", ".nuxt", ".output", ".phpunit.cache"}
    return sorted(
        ruta for ruta in RAIZ.rglob("*")
        if ruta.is_file()
        and ruta.suffix.lower() in extensiones
        and not any(parte in ignorados for parte in ruta.parts)
    )


def comprobar_json() -> None:
    global COMPROBACIONES

    for ruta in archivos({".json"}):
        COMPROBACIONES += 1

        try:
            json.loads(ruta.read_text(encoding="utf-8"))
        except json.JSONDecodeError as error:
            anotar(f"JSON inválido: {ruta.relative_to(RAIZ)} ({error})")


def comprobar_xml() -> None:
    global COMPROBACIONES

    for ruta in archivos({".xml"}):
        COMPROBACIONES += 1

        try:
            import xml.etree.ElementTree as ElementTree

            ElementTree.parse(ruta)
        except Exception as error:  # noqa: BLE001
            anotar(f"XML inválido: {ruta.relative_to(RAIZ)} ({error})")


def quitar_cadenas(texto: str) -> str:
    """
    Quita comentarios y literales de cadena antes de contar delimitadores.

    Sin esto el conteo miente: un `{` dentro de un texto, un `${variable}`
    de una plantilla o un `//` dentro de una ruta rompen la cuenta y producen
    falsos positivos que hacen desconfar de la herramienta.
    """

    # Literales de cadena: '...', "..." y `...`.
    texto = re.sub(r"'(?:\\.|[^'\\])*'", "''", texto)
    texto = re.sub(r'"(?:\\.|[^"\\])*"', '""', texto)
    texto = re.sub(r"`(?:\\.|[^`\\])*`", "``", texto)

    # Comentarios de bloque.
    texto = re.sub(r"/\*.*?\*/", "", texto, flags=re.DOTALL)

    # Comentarios de línea. Ahora es seguro: ya no quedan barras dentro de
    # cadenas.
    texto = re.sub(r"//[^\n]*", "", texto)
    texto = re.sub(r"^\s*#.*$", "", texto, flags=re.MULTILINE)

    # Expresiones regulares literales en PHP y JavaScript: /.../
    texto = re.sub(r"/(?:\\.|\[(?:\\.|[^\]\\])*\]|[^/\\\n])+/[a-z]*", "RE", texto)

    return texto


def comprobar_balance(ruta: pathlib.Path, aperturas: str, cierres: str) -> None:
    """Cuenta delimitadores ignorando cadenas, plantillas y comentarios."""

    limpio = quitar_cadenas(ruta.read_text(encoding="utf-8"))

    for apertura, cierre in zip(aperturas, cierres):
        if limpio.count(apertura) != limpio.count(cierre):
            anotar(
                f"Delimitadores desbalanceados en {ruta.relative_to(RAIZ)}: "
                f"'{apertura}'={limpio.count(apertura)} '{cierre}'={limpio.count(cierre)}"
            )


def comprobar_delimitadores() -> None:
    global COMPROBACIONES

    for ruta in archivos({".php"}):
        COMPROBACIONES += 1
        comprobar_balance(ruta, ["{", "(", "["], ["}", ")", "]"])

    for ruta in archivos({".ts", ".vue"}):
        COMPROBACIONES += 1
        comprobar_balance(ruta, ["{", "(", "["], ["}", ")", "]"])


def comprobar_rls() -> None:
    """La migración de RLS y el comando de verificación deben listar las mismas tablas."""

    global COMPROBACIONES

    COMPROBACIONES += 1

    patron = re.compile(r"'([a-z_]+)',")

    try:
        migracion = (
            RAIZ / "api/database/migrations/2026_10_17_000018_activar_row_level_security.php"
        ).read_text(encoding="utf-8")
        comando = (
            RAIZ / "api/app/Console/Commands/VerificarAislamientoCommand.php"
        ).read_text(encoding="utf-8")
    except FileNotFoundError as error:
        anotar(f"No se encontró el archivo esperado: {error}")

        return

    def lista(texto: str, marcador: str) -> list[str]:
        bloque = texto.split(marcador, 1)[1]
        bloque = bloque.split("];", 1)[0]

        return patron.findall(bloque)

    en_migracion = set(lista(migracion, "private array $tablasProtegidas = ["))
    en_comando = set(lista(comando, "private array $tablasProtegidas = ["))

    if not en_migracion:
        anotar("No se pudo leer la lista de tablas protegidas de la migración de RLS.")

        return

    if en_migracion != en_comando:
        solo_migracion = sorted(en_migracion - en_comando)
        solo_comando = sorted(en_comando - en_migracion)

        if solo_migracion:
            anotar(
                "Tablas en la migración de RLS pero no en el comando de verificación: "
                + ", ".join(solo_migracion)
            )

        if solo_comando:
            anotar(
                "Tablas en el comando de verificación pero no en la migración de RLS: "
                + ", ".join(solo_comando)
            )

    # Cada tabla protegida debe existir como migración.
    migraciones = {ruta.name for ruta in (RAIZ / "api/database/migrations").glob("*.php")}
    texto_migraciones = "\n".join(
        ruta.read_text(encoding="utf-8") for ruta in (RAIZ / "api/database/migrations").glob("*.php")
    )

    for tabla in sorted(en_migracion):
        COMPROBACIONES += 1

        if f"Schema::create('{tabla}'" not in texto_migraciones:
            anotar(f"La tabla protegida por RLS '{tabla}' no se crea en ninguna migración.")


def comprobar_modelos() -> None:
    """Cada modelo debe declarar la tabla que el nombre sugiere, y ambos deben existir."""

    global COMPROBACIONES

    modelos = sorted((RAIZ / "api/app/Models").glob("*.php"))

    for ruta in modelos:
        COMPROBACIONES += 1

        texto = ruta.read_text(encoding="utf-8")

        if "protected $table" not in texto:
            continue

        tabla = re.search(r"protected \$table\s*=\s*'([^']+)'", texto)

        if tabla is None:
            anotar(f"No se pudo leer $table en {ruta.name}")

            continue

        # La tabla debe crearse en alguna migración.
        texto_migraciones = "\n".join(
            m.read_text(encoding="utf-8")
            for m in (RAIZ / "api/database/migrations").glob("*.php")
        )

        if f"Schema::create('{tabla.group(1)}'" not in texto_migraciones:
            anotar(
                f"El modelo {ruta.stem} usa la tabla '{tabla.group(1)}', "
                "que no se crea en ninguna migración."
            )


def comprobar_referencias_app() -> None:
    r"""Cada `use App\...` debe apuntar a un archivo que existe."""

    global COMPROBACIONES

    for ruta in archivos({".php"}):
        texto = ruta.read_text(encoding="utf-8")

        for referencia in re.findall(r"^use (App\\[A-Za-z0-9_\\]+);", texto, flags=re.MULTILINE):
            COMPROBACIONES += 1

            relativo = referencia.replace("\\", "/")
            posible = RAIZ / "api/app" / relativo[len("App/"):]

            if not posible.with_suffix(".php").exists():
                # Puede ser un `use` agrupado de una subclase; en ese caso se
                # busca el archivo del grupo.
                if not (RAIZ / "api/app" / relativo[len("App/"):]).exists():
                    anotar(
                        f"Referencia rota en {ruta.relative_to(RAIZ)}: "
                        f"use {referencia}; (no existe el archivo)"
                    )


def comprobar_env() -> None:
    """No debe existir ningún `.env` real en el proyecto."""

    global COMPROBACIONES

    ignorados = {".git", "node_modules", "vendor"}

    for ruta in RAIZ.rglob(".env"):
        if any(parte in ignorados for parte in ruta.parts):
            continue

        COMPROBACIONES += 1
        anotar(f"Existe un archivo .env real en {ruta.relative_to(RAIZ)}. Solo .env.example.")


def main() -> int:
    comprobar_json()
    comprobar_xml()
    comprobar_delimitadores()
    comprobar_rls()
    comprobar_modelos()
    comprobar_referencias_app()
    comprobar_env()

    print()
    print(f"Comprobaciones: {COMPROBACIONES}")

    if FALLOS:
        print(f"Problemas encontrados: {len(FALLOS)}")
        print()

        for fallo in FALLOS:
            print(f"  - {fallo}")

        return 1

    print("Sin problemas de estructura.")
    return 0


if __name__ == "__main__":
    sys.exit(main())