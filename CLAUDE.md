# SYSMAN Suite — Guía para Claude Code

Plugin de WordPress de la Gobernación de Nariño para importar, almacenar y
visualizar datos presupuestales desde el sistema SYSMAN.

## Arquitectura

- `sisman-suite.php` — bootstrap del plugin (singleton `Sysman_Suite`), autoloader,
  menús de administración, settings y assets.
- `includes/class-database.php` — DDL/DML de las 5 tablas `sysman_*` (whitelist de
  tablas y columnas; toda consulta dinámica debe validarse aquí).
- `includes/class-importer.php` — consumo de la API SYSMAN (AJAX, cron y WP-CLI).
- `includes/class-visualizer.php` — CPT `sysman_chart`, construcción segura de
  consultas para gráficos D3plus y shortcode `[sysman_chart]`.
- `includes/class-rest-api.php` — REST `sysman-suite/v1` (registros, stats, charts).
- `includes/class-logger.php` — log de importaciones (en `wp-content/uploads`).
- `includes/class-updater.php` — actualizaciones desde GitHub Releases.
- `includes/Ejecucion/` — módulo de seguimiento de ejecución presupuestal
  (REST `gn-sisman/v1`, shortcode `[gn_ejecucion]`).
- `includes/DatosAbiertos/` — exportes CSV/TXT/JSON de datos abiertos.
- `templates/` — vistas de admin y frontend. `assets/` — CSS/JS.

## Reglas del proyecto

- PHP ≥ 8.1, WordPress ≥ 6.0. Sigue los estándares de código de WordPress
  (escapado con `esc_html`/`esc_attr`/`esc_url`, nonces + capability checks en
  todo AJAX/REST, `$wpdb->prepare()` en todas las consultas).
- Los nombres de tabla/columna NUNCA se interpolan desde entrada de usuario sin
  pasar por `Database::validate_table()` / `Database::validate_column()`.
- Las consultas personalizadas de gráficos deben pasar por
  `Visualizer::validate_custom_query()` (solo SELECT, una sola sentencia,
  solo tablas permitidas).
- Texto de UI en español, text domain `sysman-suite`.
- Al subir versión: actualizar cabecera de `sisman-suite.php`,
  `SYSMAN_SUITE_VERSION` y el changelog de `README.md`.

## Herramientas instaladas

- `.claude/skills/ui-ux-pro-max/` — skill de diseño UI/UX (usar al trabajar en
  plantillas, CSS o interfaces del plugin).
- `.github/workflows/claude.yml` — asistente `@claude` en issues/PRs
  (anthropics/claude-code-action).
- `.github/workflows/claude-security-review.yml` — revisión de seguridad
  automática de PRs (anthropics/claude-code-security-review).
- Ambos workflows requieren el secreto `ANTHROPIC_API_KEY` en GitHub Actions.

## Auditoría

Los hallazgos pendientes y el plan de mejora viven en `AUDITORIA.md`.
