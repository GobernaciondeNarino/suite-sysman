# Auditoría de Código — SYSMAN Suite

**Fecha:** 2026-08-24 · **Versión auditada:** 5.7.3 → **Versión resultante:** 5.8.0
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

## 3. Lista de auditoría — mejoras pendientes (priorizada)

### Prioridad alta (seguridad / integridad)

- [ ] **Endpoints públicos sin rate-limiting.** `/gn-sisman/v1/reporte/disponibilidades`, `/ejecucion/{id}/export` y los exports AJAX `nopriv` ejecutan JOINs pesados sin límite de frecuencia. Añadir caché de objeto/transient también para consultas con filtros, o un throttle por IP.
- [ ] **Capacidades del CPT `sysman_chart`.** Sigue usando `capability_type => post`: cualquier Autor/Editor puede crear/editar gráficos entrando por URL directa (`post-new.php?post_type=sysman_chart`). Definir capacidades propias (`manage_sysman_charts`) y mapearlas a administradores.
- [ ] **Nonce ausente en los exports públicos de Datos Abiertos** es intencional (datos abiertos), pero conviene documentarlo y validar `format`/`id` contra abuso (hecho parcialmente). Revisar si `per_page` debería ser obligatorio en los exports para evitar volcados completos repetidos.
- [ ] **Escapado de la variable `filename` en descargas**: ya se usa `sanitize_file_name`, pero conviene añadir `Content-Length` y `X-Content-Type-Options: nosniff`.
- [ ] **`date('Y')`/`date('n')` usan la zona horaria del servidor.** Migrar a `wp_date()`/`current_time()` para evitar desfases de mes/año en importaciones programadas cerca de medianoche.

### Prioridad media (bugs y robustez)

- [ ] **El cambio de `sysman_import_frequency` no reprograma el cron.** La frecuencia solo se aplica al activar el plugin. Añadir un hook `update_option_sysman_import_frequency` que haga `wp_clear_scheduled_hook` + `wp_schedule_event`.
- [ ] **`enqueue_frontend_assets` solo detecta el shortcode en `post_content`.** Los gráficos dentro de widgets, plantillas de tema o page builders no cargan D3. Considerar encolar desde `render_shortcode()` con `wp_enqueue_script` tardío (como ya hace el módulo Ejecución).
- [ ] **`Visualizer::get_dependencias()` y `Repository::get_dependencias()` duplican lógica** con firmas distintas (una con caché, otra sin). Unificar en `Repository`.
- [ ] **`Importer::import_all()` no reintenta** peticiones fallidas; una caída transitoria de la API deja el mes vacío (los datos previos se borran con DELETE antes del INSERT). Considerar transacción o staging table + swap para importaciones atómicas.
- [ ] **`insert_ejecucion_records` inserta fila por fila** (lento con miles de registros); `insert_plan_records` ya usa INSERT por lotes. Unificar al patrón por lotes.
- [ ] **`Logger` sin rotación**: el log crece sin límite. Rotar al superar ~5 MB.
- [ ] **`fecha` en `auxiliar_cuentas` es VARCHAR(20)**: impide ordenar/filtrar por fecha real. Migrar a DATE con normalización en importación.

### Prioridad baja (calidad / mantenimiento)

- [ ] **Sin suite de pruebas.** Añadir PHPUnit + wp-env (o Pest) con pruebas para `validate_custom_query`, `build_chart_query`, `Repository::build_filters` y los sanitizadores CSV.
- [ ] **Sin PHPCS/PHPStan en CI.** Añadir workflow con `wordpress-coding-standards` y PHPStan nivel 5+ (los `phpcs:ignore` existentes deberían revisarse).
- [ ] **JS con patrones mixtos** (jQuery en admin, vanilla en frontend) y `innerHTML +=` en `frontend.js` (reflow y pérdida de listeners). Homogeneizar y usar `createElement`/`insertAdjacentHTML` una sola vez.
- [ ] **Traducciones**: el `Text Domain` está declarado pero no existe carpeta `languages/` con `.pot`. Generar con `wp i18n make-pot`.
- [ ] **`Updater::format_changelog`**: conversión Markdown manual frágil; considerar `Parsedown` o mostrar texto plano.
- [ ] **`README.md` desactualizado en estructura** (menciona `logs/` dentro del plugin). Actualizar el árbol del proyecto.
- [ ] **Accesibilidad UI admin**: revisar contraste y focus-visible de los botones personalizados con el skill `ui-ux-pro-max` (prioridad 1: contraste 4.5:1, navegación por teclado en acordeones de Ejecución).
- [ ] **Íconos del updater**: `assets/icon-128.png` referenciado pero no existe en el repo.

## 4. Metodología

Revisión manual archivo por archivo de `includes/`, `templates/`, `assets/js/`, `sisman-suite.php` y `uninstall.php`, con foco en: inyección SQL (interpolación vs. `prepare`), XSS (escapado de salida), CSRF (nonces), control de acceso (capabilities en AJAX/REST), inyección de fórmulas CSV, SSRF/TLS, exposición de información y duplicación de código. Validación posterior: `php -l` sobre todos los archivos y batería de 12 casos de prueba para `validate_custom_query()`.
