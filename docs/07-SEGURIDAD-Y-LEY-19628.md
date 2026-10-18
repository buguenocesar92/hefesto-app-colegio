# 07 - Seguridad y Ley 19.628

Este documento lista lo que se hizo, lo que **no** se hizo, y qué está
pendiente de verificación legal.

**Advertencia que va primero, porque ordena todo lo demás:**

> Este sistema organiza datos personales de menores de edad. Su uso tiene
> obligaciones legales que **ningún software reemplaza**. Tratar datos de
> menores no es un problema técnico: es un problema legal, y el software solo
> puede ayudar a no cometer los errores más obvios.

---

## 1. La ley, y qué se verificó

| Fuente | Qué dice | ¿Verificado? |
|---|---|---|
| **Ley 19.628** (1999), sobre protección de la vida privada | Regula el tratamiento de datos personales | **Sí**, registro oficial de la Biblioteca del Congreso Nacional |
| **Ley 20.720** (2014) | Modificó la Ley 19.628 "con el objeto de incorporar, como **dato sensible**, aquel vinculado a menores de edad" | **Sí**, según la descripción oficial en BCN |
| **Ley 21.719** | Modifica la Ley 19.628 | **Parcial**: se verificó que existe y que modifica la ley; **NO se verificó su contenido artículo por artículo** |

### Lo que se puede afirmar con confianza

**Desde la Ley 20.720, el dato vinculado a menores de edad es un dato
sensible bajo la Ley 19.628.**

Eso tiene consecuencias concretas que este diseño asume:

1. El tratamiento de datos de menores exige **consentimiento explícito**, no
   un consentimiento genérico.
2. El derecho de acceso, la rectificación y la eliminación se exercise con
   más exigentes.
3. La seguridad debe ser **proporcionalmente mayor**.

### Lo que NO se verificó, y hay que verificar antes de usar esto

> **Pendiente de verificación con la asesoría legal del colegio:**
>
> - El **contenido artículo por artículo** de la Ley 21.719 y cómo modifica la
>   Ley 19.628. Se verificó que existe y que modifica la ley, pero no se leyó
>   el texto consolidado.
> - Si la Ley 21.719 exige **inscripción en un registro** de agentes que
>   tratan datos de niños, niñas y adolescentes, y qué plazos rigen.
> - Si existe algún plazo de conservación específico para datos de menores.
> - Los **tiempos de retención** que aplican a la bitácora de accesos.
> - Si la normativa sectorial (**Ley 20.370, Ley 19.979** de biblioteca
>   escolar, y normas del Ministerio de Educación) impone requisitos
>   adicionales sobre la conservación de documentos Escolares.
>
> Hefesto **no es una asesoría legal**. Este sistema se entrega con el
> análisis técnico hecho y el legal pendiente.

---

## 2. Qué hizo el sistema con esto

### 2.1 Tratar todo dato de menor como sensible

El dato de un menor no está en una clase aparte de "dato normal": está en
`alumnos`, `apoderados`, `contactos_emergencia`, `asistencias`,
`conducta_incidentes`, `entrevistas` y `bitacora_accesos`. **Todas** esas
tablas:

- tienen `tenant_id` y RLS;
- su lectura pasa por una Policy;
- quedan registradas en la bitácora de accesos.

No hay ningún camino de lectura a esos datos que no pase por las dos capas.

### 2.2 Consentimientos

`consentimientos` existe para poder **demostrar** que una familia autorizó el
tratamiento.

Se guardan: alcance, **versión del documento firmado**, quién firmó, cuándo, y
la ruta del documento escaneado.

**La versión del documento es obligatoria.** Sin ella no se puede probar que
el consentimiento cubría el texto vigente cuando se recogió.

Revocar **no borra** la fila: deja `revocado_en`. Un registro que se borra al
revocarse no sirve para demostrar que hubo un período autorizado.

### 2.3 Minimización

Cada campo sensible tiene una razón de existir:

| Dato | Por qué está |
|---|---|
| Fecha de nacimiento | Para calcular la edad y validar que el alumno está en el nivel correcto |
| Contactos de emergencia | Para llamar cuando hay una urgencia |
| `rut` del alumno | Identificación; **opcional**, porque hay casos en que aún no existe |

Y cada cosa se **puede** escribir sin lo demás: la dirección del alumno no es
obligatoria, y el contacto de emergencia no bloquea la matrícula.

### 2.4 La bitácora de accesos

`bitacora_accesos` registra quién abrió qué dato: usuario, acción, ruta,
método, IP, agente de usuario, código de respuesta y si tuvo éxito.

Es lo que permite responder "¿quién abrió la ficha de este alumno?".

**Y es a la vez un repositorio de datos personales** (IP, agente de usuario, y
sobre todo a qué alumno se accedió). Por eso tiene retención configurable y
por defecto de un año:

```dotenv
COLEGIO_RETENCION_BITACORA_HORAS=8760
```

**La retención definitiva la decide el colegio con su asesoría legal.**

---

## 3. Seguridad implementada

### 3.1 Sesión y cookies

| Medida | Detalle |
|---|---|
| Cookie **HttpOnly** | El JavaScript de la página no puede leerla. No hay token en `localStorage` |
| Cookie **Secure** | Solo viaja por HTTPS. Obligatorio en producción |
| Cookie **SameSite=Lax** | `strict` impediría el ingreso: la cookie no viaja en la redirección desde Authentik |
| `session.use_strict_mode = 1` | Un identificador de sesión no generado por la aplicación se rechaza |
| Sesión en base de datos | Múltiples réplicas sin perder la sesión |
| Regeneración de identificador | Al iniciar sesión, para evitar ataque de fijación de sesión |

### 3.2 CSRF

El grupo `web` de Laravel aplica `VerifyCsrfToken`. El token viaja como
cabecera **`X-XSRF-TOKEN`**, que el BFF de Nuxt copia desde la cookie
`XSRF-TOKEN`.

Consecuencias: el token no es accesible desde el JavaScript de la página, y
toda escritura pasa por el BFF, así que todas pasan por la comprobación.

### 3.3 Autorización

Dos capas separadas a propósito (ver [03](03-MULTITENANCY-Y-ROLES.md)):

| Capa | Dónde | Qué decide |
|---|---|---|
| Row Level Security | PostgreSQL | De qué colegio son los datos |
| Policies | Laravel | Qué puede hacer la persona dentro de su colegio |

Reglas que importan:

- Un recurso de **otro colegio** responde **404**, no 403: un 403 confirmaría
  que existe.
- Un apoderado **nunca** ve alumnos que no son sus hijos.
- Un maestro de comunicaciones **nunca** ve fichas completas ni conducta.
- Un apoderado **nunca** ve el registro de incidentes de conducta.
- Las cuentas de acceso solo las gestiona **dirección**.

### 3.4 Validación de entrada

- Todas las entradas pasan por un `FormRequest` con reglas explícitas.
- Los mensajes están en español y son para la persona, no para quien
  desarrolla.
- Las consultas usan **binding** de Eloquent. **No hay SQL armado con cadenas**
  en ningún punto.
- Las búsquedas usan `ilike` con parámetros.
- El par (tipo de destinatario, identificador) de una comunicación se valida:
  no se aceptan campos que no corresponden al tipo.

### 3.5 Redirección abierta

`redirigir_a` (a dónde volver tras el ingreso) solo acepta rutas internas.
Se rechazan URLs completas, las que empiezan con `//` (protocolo relativo) y
las que empiezan con `/\`.

Está validado en **dos** capas: la API (`AuthController::destinoSeguro`) y el
BFF. No se estorban.

### 3.6 Autorización del token de identidad

- Se verifica la **firma** contra las llaves públicas de Authentik.
- Se verifica el **algoritmo esperado** (`RS256`), no el que venga en la
  cabecera: aceptar el que venga cierra el ataque de confusión de algoritmo
  (firmar con la clave pública usando HS256).
- Se verifican `iss`, `aud`, `exp` y `nonce`.
- El `nonce` se lee **una sola vez** al partir el código de autorización.

**`email_verified` no decide nada.** Desde Authentik 2025.10 ese claim vale
`false` salvo que se configure el flujo que lo verifica. Apoyar el alta en él
dejaría a personas fuera sin motivo.

### 3.7 El grupo de Authentik no da permiso

El grupo de Authentik define el **primer alta** de una cuenta nueva cuando el
dominio de correo coincide con el declarado por un colegio.

**Una vez dentro, lo que decide es el `rol` en `usuarios`, que es del colegio.**
Si el grupo cambiara mañana, ninguna cuenta existente perdería acceso.

### 3.8 El dominio de correo no es prueba de identidad

Un alta solo ocurre si:

1. el correo pertenece al **dominio declarado** por un colegio, o
2. la cuenta ya existía (creada por la secretaria al matricular).

No hay un tercer camino: **no se crea una cuenta porque alguien se inventó un
correo con el dominio del colegio.** El dominio es una declaración del
colegio, no una prueba de quién es la persona.

Y el rol por defecto de un alta automática es `comunicaciones`, el más acotado
de los que no son familia. **Nadie entra como dirección por tener un correo
del dominio**: dirección se asigna a mano.

---

## 4. Lo que NO se hizo

Esto es importante: **no hubo auditoría de seguridad, no hubo pruebas de
penetración y no hubo revisión externa**. Lo que hay es el análisis de este
documento y del código.

Pendientes conocidos:

1. **Las pruebas están escritas pero no se ejecutaron.** No había PostgreSQL
   ni PHP en el entorno donde se escribió el código. Ver
   [10](10-PRUEBAS-Y-ESTADO.md). Es el pendiente más grave: si el SQL de las
   migraciones tiene un error, el aislamiento se cae entero y la aplicación
   no da ningún aviso visible.
2. **No hay límites de tasa** en los endpoints. El de autenticación debería
   tenerlos: es lo primero que se ataca.
3. **La `Content-Security-Policy` está incompleta.** Nuxt inyecta scripts con
   nonce; la política de Caddy no los contempla. Hay que ajustarla antes de
   producción.
4. **Las imágenes no están fijadas por digest**, solo por tag exacto.
5. **No hay rotación de `APP_KEY`.** Si se filtra, hay que rotarlo y eso
   invalida todas las sesiones.
6. **La tabla `colegios` no tiene RLS**, a propósito. Quien tenga SQL lee la
   razón social, el RUT y los contactos de todos los colegios. Ver
   [03](03-MULTITENANCY-Y-ROLES.md), sección 2.
7. **El canal de log de seguridad escribe correos y dominios.** Es útil para
   investigar, pero es un registro de datos personales y entra en la política
   de retención del colegio.
8. **No hay doble factor de autenticación** gestionado por el portal.
   Authentik puede ofrecerlo, pero se configura ahí.
9. **No hay cifrado a nivel de columna.** Los datos sensibles están en claro
   en la base. El cifrado en reposo depende del volumen y del proveedor de
   almacenamiento.

---

## 5. Responsabilidades que NO son del sistema

Conviene dejarlas por escrito:

| Responsabilidad | De quién es |
|---|---|
| Consentimiento válido de las familias | Del colegio |
| Avisar a una familia que sus datos se tratan y para qué | Del colegio |
| Guardar la evidencia del consentimiento firmado | Del colegio |
| Definir cuánto tiempo se conserva cada dato | Del colegio, con asesoría legal |
| Responder a un requerimiento de acceso de un titular | Del colegio |
| Definir el reglamento de convivencia que origina los incidentes | Del colegio |
| Decidir si la familia recibe el parte de conducta o la comunicación | Del colegio |
| Cumplir la normativa de registro de agentes de tratamiento | Del colegio |
| Tener un plan de respuesta ante una brecha de seguridad | Del colegio |

**El sistema registra y ordena. No decide.**

---

## 6. Antes de usar esto con alumnos reales

Lista mínima, en orden:

1. Ejecutar las pruebas de aislamiento contra PostgreSQL de verdad.
   (Ver [10](10-PRUEBAS-Y-ESTADO.md).)
2. Correr `php artisan colegio:verificar-aislamiento` y confirmar que pasa.
3. Revisar con la asesoría legal el contenido de la Ley 21.719 y las
   obligaciones sectoriales.
4. Redactar el documento de consentimiento con esa asesoría, con su versión
   y su evidencia.
5. Definir y documentar la retención de cada tipo de dato.
6. Completar la `Content-Security-Policy`.
7. Fijar las imágenes por digest.
8. Probar la restauración de respaldos.
9. Recién ahí, cargar datos reales.

Los puntos 1 y 2 son técnicos y los puede hacer Hefesto. Los puntos 3, 4 y
5 **no**: son del colegio con su asesoría legal.