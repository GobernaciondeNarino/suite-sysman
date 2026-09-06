# Auditoría de Código — SYSMAN Suite

**Fecha:** 2026-08-24 · **Versión auditada:** 5.7.3 → **Versiones resultantes:** 5.8.0 (hallazgos críticos) y 5.9.0 (plan de mejora implementado)
**Alcance:** todo el código PHP (~6.000 líneas), JavaScript (~2.300 líneas), plantillas y configuración del plugin.

---

## 1. Hallazgos corregidos en v5.8.0

| # | Severidad | Hallazgo | Corrección |
|---|-----------|----------|------------|
| 1 | **Crítica** | Ejecución de SQL arbitrario: la meta `_sysman_custom_query` se ejecutaba sin validación vía el endpoint REST **público** `/sysman-suite/v1/chart/{id}` y el preview AJAX. Cualquier usuario con `edit_post` (p. ej. un Editor) podía guardar `UPDATE wp_users ...` y ejecutarlo. | Nuevo `Visualizer::validate_custom_query()`: una sola sentencia SELECT, sin comentarios SQL, lista negra de palabras clave, solo tablas del plugin, LIMIT forzado. Se valida en cada ejecución (cubre queries antiguas ya guardadas) y guardar requiere `manage_options`. |
| 2 | **Alta** | `sslverify => false` en todas las peticiones a la API SYSMAN y en el test de conexiones → susceptible a MITM sobre datos presupuestales. | Verificación SSL activada por defecto; filtro `sysman_suite_sslverify` como vía de escape documentada. |
| 3 | **Alta** | Log de importaciones dentro del plugin (`logs/import.log`): descargable públicamente en Nginx (el `.htaccess` solo protege Apache), contiene usuarios y URLs internas, y se borraba con cada actualización del plugin. | Log movido a `uploads/sysman-suite/` con nombre con hash, `.htaccess` + `index.php`, y migración automática del log antiguo. |
| 4 | **Media** | Inyección de fórmulas CSV en `/chart/{id}/csv` (endpoint público): valores que inician con `=`, `+`, `-`, `@` se interpretan como fórmulas en Excel. | Celdas neutralizadas con apóstrofe (mismo criterio que Datos Abiertos). |
| 5 | **Media** | Bug: `/chart/{id}/csv` devolvía el CSV como **cadena JSON** (comillas escapadas) porque se retornaba en un `WP_REST_Response`. El archivo descargado era ilegible en Excel. | Salida directa con cabeceras, BOM UTF-8 y `fputcsv`. |
| 6 | **Media** | `uninstall.php` incompleto: no eliminaba las tablas `personal_nomina` ni `ejecucion_ingresos`, ni las opciones nuevas (`sysman_api_base_url`, CDNs, `gn_sisman_*`), ni los posts `gn_ejecucion`, ni los transients del módulo Ejecución. | Desinstalación completa de las 5 tablas, 13 opciones, transients, CPTs y directorio de logs. |
| 7 | **Media** | `ajax_import_status` sin chequeo de capacidad y `ajax_dismiss_update_notice` permitía a cualquier usuario logueado modificar una opción del sitio. | `current_user_can( 'manage_options' )` agregado en ambos. |
| 8 | **Baja** | `per_page=0` en `/records` producía `LIMIT 0` (respuesta vacía silenciosa). | Acotado a mínimo 1, máximo 100. |
| 9 | Código duplicado | Mapa de etiquetas de columnas triplicado; consulta "Vista" duplicada (~100 líneas idénticas entre chart guardado y preview); caché de columnas duplicada en `Database`; array de meses cuadruplicado; `update_option('sysman_last_import')` duplicado; 4 bloques try/catch idénticos en `ajax_sync`; construcción manual de URL en `import_personal`. | Centralizado en `Visualizer::COLUMN_LABELS` + `compose_vista_query()`, `Database::validate_column()` → `get_table_columns()`, `SysmanSuite\Helpers::month_name()`, `Importer::save_last_import()` y `build_url()` con mes opcional, bucle en `ajax_sync`. |
| 10 | Código deficiente | Variable sin uso `_sysman_vista_type` leída y descartada en `build_vista_query`. | Eliminada del flujo (la meta sigue guardándose por compatibilidad). |

## 2. Herramientas instaladas en v5.8.0

- **`.claude/skills/ui-ux-pro-max/`** — skill de inteligencia de diseño UI/UX (nextlevelbuilder/ui-ux-pro-max-skill v2.13.0, licencia MIT) para trabajar plantillas, CSS e interfaces.
- **`.github/workflows/claude.yml`** — asistente `@claude` en issues y PRs ([anthropics/claude-code-action](https://github.com/anthropics/claude-code-action)).
- **`.github/workflows/claude-security-review.yml`** — revisión de seguridad automática de cada PR ([anthropics/claude-code-security-review](https://github.com/anthropics/claude-code-security-review)).
- **`CLAUDE.md`** — guía del proyecto para [Claude Code](https://github.com/anthropics/claude-code) (arquitectura, reglas de seguridad, convenciones).

> ⚠️ **Acción requerida:** los dos workflows necesitan el secreto `ANTHROPIC_API_KEY` (o `CLAUDE_CODE_OAUTH_TOKEN` en `claude.yml`) en *Settings → Secrets and variables → Actions* del repositorio. Hasta que se configure, los workflows fallarán silenciosamente en los PRs.

## 3. Lista de auditoría — plan de mejora

### 3.1 Implementado en v5.9.0

- [x] **Rate-limiting en endpoints públicos.** Throttle por IP con transients (`Helpers::rate_limit_check()`): 120 req/min lectura (`chart`, `gn_public`), 30 req/min exports REST (`chart_csv`, `gn_export`), 20 req/min descargas AJAX de Datos Abiertos (`da_export`). Respuesta HTTP 429; administradores exentos; ajustable/desactivable con el filtro `sysman_suite_rate_limit`.
- [x] **Capacidades del CPT `sysman_chart`.** Todas las capacidades de `sysman_chart` y `gn_ejecucion` mapeadas a `manage_options` (`Visualizer::admin_only_caps()`); un Autor/Editor ya no puede crear gráficos por URL directa.
- [x] **Cabeceras de descarga.** `X-Content-Type-Options: nosniff` + `nocache_headers()` + filename saneado, unificado en `Helpers::download_headers()` para todas las descargas CSV/TXT.
- [x] **Zona horaria.** Todos los `date('Y')`/`date('n')` migrados a `current_time()` (22 ocurrencias en PHP y plantillas).
- [x] **Reprogramación del cron.** Hook `update_option_sysman_import_frequency` reprograma el evento al guardar; la frecuencia se valida contra la whitelist de schedules (`sanitize_import_frequency`).
- [x] **Assets del shortcode.** `render_shortcode()` encola D3/D3plus tardíamente (`enqueue_chart_assets()`), cubriendo widgets y page builders.
- [x] **`get_dependencias` unificado.** Implementación única en `Repository` (flexible, con caché 12 h y filtro `movimiento='SI'`); el Visualizer delega. La invalidación ahora limpia todas las combinaciones de caché.
- [x] **Importaciones atómicas.** `Database::replace_records()` envuelve DELETE + INSERT en una transacción (best effort, requiere InnoDB): un fallo a mitad de carga hace ROLLBACK y conserva los datos anteriores.
- [x] **Inserción por lotes.** Los 5 informes insertan en lotes de 500 filas (antes 4 de ellos iban fila por fila); los 5 métodos duplicados quedaron unificados en `replace_records()`.
- [x] **Rotación de log.** Al superar 5 MB el log rota a `.log.1` (una generación de respaldo).
- [x] **Suite de pruebas.** `tests/run-tests.php` standalone (31 aserciones: validador SQL con 19 casos, rate limiter, sanitizador CSV, helpers) — sin necesidad de instalación WordPress.
- [x] **CI.** `.github/workflows/ci.yml`: lint de sintaxis de todo el PHP + tests en PHP 8.1 y 8.3, en cada push/PR.
- [x] **JS `innerHTML +=`.** `frontend.js` construye las tablas del modal con una sola escritura de `innerHTML`.
- [x] **Ícono del updater.** Solo se referencia `assets/icon-128.png` si el archivo existe.
- [x] **README.** Árbol del proyecto actualizado (tests, workflows, skill; log en uploads).
- [x] **Exports públicos documentados.** El acceso sin nonce es intencional (datos abiertos); el abuso se mitiga con el rate-limiting anterior.

### 3.2 Implementado en v5.10.0 (módulo de gráficos)

- [x] **Modo "Vistas" no creaba gráficos.** Causa raíz: los paneles *Tablas* y *Vistas* compartían el campo `sysman_value_columns[]` y el JS quitaba/ponía el atributo `name` para elegir cuál enviaba. En un gráfico nuevo el panel de Vistas se abría **sin columnas Y** (`loadVistaValues()` solo corría si el gráfico ya estaba guardado como Vista), así que al publicar no viajaba ninguna fuente de datos. Reproducido en navegador y corregido dando campos propios a cada panel (`sysman_vista_value_columns[]`, `sysman_vista_aggregate`), con `save_meta()` eligiendo el conjunto según el modo activo.
- [x] **Contaminación cruzada entre pestañas.** Agregar un filtro estando en Vistas volvía a llamar `loadColumns()`, que re-armaba el `name` del panel oculto de Tablas e inyectaba columnas ajenas. Eliminado al independizar los campos.
- [x] **`select` de agregación de Vistas sin `name`.** Dependía por completo de un sincronizador JS; ahora envía `sysman_vista_aggregate`.
- [x] **Panel "Datos a Graficar"** en la columna lateral bajo *Publicar*: registros, series, total, descripción de la fuente y tabla con los datos reales antes de publicar.
- [x] **Migración D3plus v2 → v4** (`@d3plus/core@4.3.0`): el paquete `d3plus` está congelado en 2.1.3 y sin mantenimiento. API encadenable compatible, verificada método por método y renderizando los 11 tipos de gráfico.
- [x] **D3 ya no se carga por separado** (v4 incluye sus módulos de D3): una petición menos y se elimina la opción `sysman_d3_cdn_url`.
- [x] **Contexto seguro.** D3plus v4 llama `crypto.randomUUID()`, que el navegador solo expone en HTTPS/localhost: en un sitio servido por HTTP plano la librería **no cargaba en absoluto**. Se añade un polyfill inyectado antes del bundle.
- [x] **Locale `es_ES` → `es-ES`** (el valor anterior no coincide con la tabla de locales de la librería y caía al formato inglés).

### 3.3 Implementado en v5.11.0 – v5.12.0 (módulos Gastos e Ingresos)

- [x] **Importador incompleto.** La importación completa solo traía DIS y RES del auxiliar de cuentas: OBL y EGR nunca llegaban a la base de datos, así que la mitad de la cadena de ejecución no existía. Ahora se importan los cuatro tipos.
- [x] **Botón «← Todas las dependencias» sin efecto.** El componente limpiaba su estado interno antes de publicarlo al coordinador de filtro cruzado; el suscriptor recibía el valor que ya tenía y descartaba el evento como redundante. Se publica primero y se limpia al recibir.
- [x] **Lienzo del treemap recortado.** D3plus inserta su propio SVG en el contenedor, que colapsaba a 0 px de alto; con `overflow:hidden` el gráfico quedaba invisible pese a renderizarse correctamente.
- [x] **Contraste del color de resalte.** `#348AFB` se usa en bordes, fondos, barras y anillos de foco, pero el texto usa `#0B62D6` (mismo azul oscurecido): sobre blanco `#348AFB` da 3,4:1 y WCAG AA exige 4,5:1 en texto normal.
- [x] **Duplicación evitada al añadir Ingresos.** En vez de clonar el módulo, se parametrizaron por `modulo` el controlador REST, el motor de análisis y los componentes JS. Las rutas y atributos heredados (`dependencias`, `rubros`, `rubro`, `dependencia`, `[sysman_pre_*]`) se conservan como alias para no romper páginas publicadas.
- [x] **Concordancia de género y plural en español.** Los textos generados producían «las tres primeras rubros» y «Todos los tipo de recursos». El plural y el género se resuelven ahora en PHP con constantes explícitas, no concatenando «s» en JS.
- [x] **`porcrecaudado` excluido de las métricas agregables** de ingresos: es un porcentaje por fila y sumarlo no significa nada. El porcentaje se recalcula sobre los totales.
- [x] **Rate-limiting** también en los endpoints públicos de los dos módulos nuevos (HTTP 429), y caché de agregados invalidada tras cada importación.

### 3.4 Implementado en v5.13.0 (legibilidad del análisis)

- [x] **El análisis se leía como una ficha técnica, no como un texto.** Título, etiqueta «Ámbito», recuadro con borde y cuadrícula de métricas competían con el contenido. Ahora son párrafos corridos con la tipografía del tema; las cifras del cuantitativo se redactan dentro del texto en lugar de listarse en cajas.
- [x] **Concordancia de género y número de la dimensión de ingresos.** El plural se formaba añadiendo «s» («tipo de recursos») y el género estaba fijo en masculino («los tres primeros fuentes de recurso»). Ambos se resuelven ahora con constantes explícitas de `IngresosRepository`.
- [x] **Nombre del rubro empujaba la cifra fuera de la vista.** Los nombres superan a menudo los 200 caracteres; código y valor van en la primera línea y el nombre debajo.
- [x] **El panel de ejecución cargaba en blanco.** Sin selección mostraba «Seleccione una dependencia»; ahora arranca con la primera del listado, resolviéndola en local para no alterar el filtro compartido de la página.

### 3.5 Implementado en v5.14.0 (redacción)

- [x] **El análisis se leía como una lista de frases sueltas.** Cada tipo entrega ahora un único párrafo continuo, en tono institucional y con las cifras encadenadas dentro de la narración.
- [x] **Nombres en mayúscula sostenida dentro del texto.** SYSMAN los entrega así; en un párrafo se leen como un grito. Se convierten a capitalización normal respetando conectores y siglas del sector público, sin inventar tildes que la fuente no trae.
- [x] **Buscador en la vista de ejecución.** Con cientos de rubros por dependencia, encontrar uno exigía recorrer la lista entera. El filtro trabaja sobre las filas ya cargadas, sin peticiones adicionales.

### 3.6 Implementado en v5.15.0 (integridad de la importación)

- [x] **Duplicación de datos entre importaciones (reportado en producción).** Dos importaciones solapadas —cron y manual, o un doble envío— hacían cada una su `DELETE` antes de que la otra insertara, de modo que las filas de ambas convivían en el mismo periodo y las cifras salían infladas. Ahora hay un cerrojo atómico (`add_option`) que impide dos importaciones a la vez, con recuperación automática si un proceso muere a media carga.
- [x] **Verificación posterior a cada carga.** Si tras el `DELETE` + `INSERT` el periodo tiene más filas de las insertadas, la transacción se revierte y se registra el motivo, en lugar de dejar datos duplicados silenciosamente.
- [x] **Detección y limpieza desde la interfaz.** «Verificar duplicados» compara filas frente a registros distintos por tabla y periodo; «Limpiar el periodo antes de importar» reproduce, acotada al informe elegido, la limpieza que había que hacer a mano en SQL.
- [x] **Ámbito de importación centralizado** en `Import_Scope`: qué identifica un registro y qué se borra antes de insertar dejan de estar repetidos en cinco métodos.

### 3.7 Implementado en v5.17.0 (agregados que perdían filas)

- [x] **Ingresos aparecía vacío con la tabla llena (reportado en producción).** Las vistas agrupan por `tiporecurso` y la consulta filtraba `tiporecurso <> ''`; como esa columna viene vacía en todos los registros del periodo, se descartaban todas las filas. El periodo estaba bien resuelto: fallaba la agrupación.
- [x] **Filas sin clasificar descartadas en silencio.** El mismo filtro existía en Gastos sobre `nombredependencia`. Ahora se agrupan bajo «Sin clasificar» / «Sin dependencia» en lugar de desaparecer de los totales, que era el problema de fondo: la cifra global salía menor que la real sin ninguna señal.
- [x] **Respaldo de dimensión** en Ingresos cuando la pedida no tiene valores en el periodo, resuelto al renderizar el shortcode para que las etiquetas y las consultas hablen de la misma dimensión.
- [x] **Cobertura de las consultas reales.** La batería ejecutaba solo código puro; las nuevas pruebas levantan SQLite en memoria con la forma de los datos del sitio y verifican que ningún agregado pierde filas.

### 3.8 Implementado en v5.18.0 (agrupación de ingresos)

- [x] **La agrupación de ingresos no reflejaba cómo se lee el presupuesto.** Con el tipo de recurso vacío y la fuente en un comodín, la vista quedaba en un solo bloque del 100%. Se añade la dimensión `rubro` —prefijo del código de cuenta, longitud configurable— que es la que usa el área financiera.
- [x] **Una dimensión con un único valor no agrupa nada.** La elección automática ahora exige más de un valor distinto en el periodo antes de quedarse con una dimensión.

### 3.9 Pendiente (requiere decisión o herramientas externas)

- [ ] **`fecha` en `auxiliar_cuentas` es VARCHAR(20)**: migrar a DATE exige confirmar el formato exacto que entrega la API SYSMAN y una migración de datos en producción. Planificar con respaldo previo.
- [ ] **PHPCS (WordPress Coding Standards) + PHPStan en CI**: requiere `composer.json` y una pasada inicial de limpieza sobre el código legado para que el pipeline no nazca en rojo. El CI actual (lint + tests) es el primer paso.
- [ ] **Traducciones**: generar `languages/sysman-suite.pot` con `wp i18n make-pot` (la UI es solo en español, prioridad baja).
- [ ] **`Updater::format_changelog`**: conversión Markdown manual frágil; considerar `Parsedown` o texto plano.
- [ ] **Homogeneizar JS** (jQuery en admin vs. vanilla en frontend) — refactor mayor, sin impacto funcional.
- [ ] **Accesibilidad UI admin**: pasada de contraste/focus-visible con el skill `ui-ux-pro-max` sobre `admin.css` y los acordeones de Ejecución.
- [ ] **Crear `assets/icon-128.png`** (ícono del plugin para el updater y la pantalla de plugins).
- [ ] **Índice único por clave natural** en las cinco tablas, que haría imposible la duplicación por construcción. Requiere confirmar antes, con datos reales, que el origen no emite legítimamente dos filas con la misma clave (por ejemplo, un rubro con dos fuentes de financiación); un índice mal elegido descartaría datos válidos en silencio.
- [ ] **Reintentos ante fallos de la API** en `import_all()` (backoff simple); con las transacciones de v5.9.0 un fallo ya no destruye datos, solo pospone la actualización.

## 4. Metodología

Revisión manual archivo por archivo de `includes/`, `templates/`, `assets/js/`, `sisman-suite.php` y `uninstall.php`, con foco en: inyección SQL (interpolación vs. `prepare`), XSS (escapado de salida), CSRF (nonces), control de acceso (capabilities en AJAX/REST), inyección de fórmulas CSV, SSRF/TLS, exposición de información y duplicación de código. Validación posterior: `php -l` sobre todos los archivos y batería de 12 casos de prueba para `validate_custom_query()`.
