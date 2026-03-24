
You are an expert WordPress Security & Quality Engineer with deep specialization in PHP 8.1+, WordPress plugin architecture, Colombian government open data systems (datascience analytic), D3plus data visualization library, and secure government web development. You combine the rigor of an OWASP security auditor with the practical knowledge of a senior WordPress developer and a D3plus visualization expert.


## Review
You will need to capture the data from (the repositories indicate which tables were created and the fields from other plugins with data import functions):

https://github.com/GobernaciondeNarino/sisman-suite/blob/claude/sisman-wordpress-plugin-nzOmo/includes/class-database.php
https://github.com/GobernaciondeNarino/secop-suite/blob/main/includes/class-database.php

## Documentation & References

| Resource | URL |
|----------|-----|
| D3plus API Reference | https://d3plus.org/ |
| D3plus Chart Gallery | https://d3plus.org/examples/ |
| D3plus GitHub | https://github.com/d3plus/d3plus |
| WordPress Plugin Handbook | https://developer.wordpress.org/plugins/ |
| WordPress Security | https://developer.wordpress.org/apis/security/ |
| SECOP II API | https://www.datos.gov.co/resource/jbjy-vk9h.json |
| OWASP Top 10 | https://owasp.org/www-project-top-ten/ |
| Colombia DANE Codes | https://www.dane.gov.co/index.php/territorio/codigos-divipola |
| Sistema financiero | https://narino-gob.sysman.com.co/sysmanApi/autoservicio/v1/informesGobNar?compania=001&anio=2024&mes=1&numinforme=1
| AUXILIAR PRESUPUESTAL POR CUENTAS | https://narino-gob.sysman.com.co/sysmanApi/autoservicio/v1/informesGobNar?compania=001&anio=2024&mes=1&numinforme=2&tipo_cpte=RES
| PLAN PRESUPUESTAL | https://narino-gob.sysman.com.co/sysmanApi/autoservicio/v1/informesGobNar?compania=001&anio=2024&mes=1&numinforme=4

## Update

### v1.1.0 — Mejoras identificadas e implementadas

#### 1. Admin JS — Integración completa con REST API
- **Problema:** `admin.js` solo manejaba cache flush, shortcode builder y tabs, pero NO tenía handlers para los botones "Cargar Datos", "Analizar", "Proyectar" y "Evaluar" en las páginas descriptivo, diagnóstico, predictivo y prescriptivo.
- **Solución:** Se agregaron funciones `initDescriptive()`, `initDiagnostic()`, `initPredictive()`, `initPrescriptive()` y `initDashboardKpis()` que fetch data desde la REST API (`dss/v1/*`) y renderizan gráficos D3plus y KPIs dinámicamente.

#### 2. Dashboard.js — Transformación de datos REST → D3plus
- **Problema:** Los métodos `buildChart()` esperaban datos planos pero la REST API devuelve respuestas anidadas (`{data: {kpis, monthly, by_rubro}}`).
- **Solución:** Se creó capa de transformación `DSSDataTransformer` que adapta respuestas REST a formatos D3plus:
  - `descriptive.monthly` → `[{mes: "Ene", apropiacion: N, pagos: N}]` para BarChart/LinePlot
  - `descriptive.by_rubro` → `[{rubro: "X", total: N}]` para Treemap
  - `descriptive.contracts_by_type` → `[{tipo: "X", value: N}]` para Donut
  - `diagnostic.variations` → stacked data para StackedBar
  - `diagnostic.scatter` → scatter data para Plot

#### 3. Database whitelist — Columnas faltantes de SECOP
- **Problema:** La whitelist de `secop_contracts` no incluía columnas importantes como `modalidad_de_contratacion`, `sector`, `nombre_entidad`, `departamento`.
- **Solución:** Se añadieron las columnas usadas por el AnalyticsEngine para consultas de agregación y filtrado.

#### 4. Seguridad y mejores prácticas
- El plugin ya sigue buenas prácticas OWASP: prepared statements, nonce verification, capability checks, table/column whitelists.
- Se verifica que `wp_add_inline_script` use `esc_url()` para el fallback CDN.
- Rate limiting implementado correctamente con transients.

#### 5. Versión incrementada a 1.1.0

## Actions
- Verify the plugin's functionality
- Consult the documentation
- Identify improvements and best practices
- Update this document with the improvements to be made in the "Update" section.
- Implement the improvements
- Update the code
- Update the repository, maintaining the plugin version increment
