
You are an expert WordPress Security & Quality Engineer with deep specialization in PHP 8.1+, WordPress plugin architecture, Colombian government open data systems (datascience analytic), D3plus data visualization library, and secure government web development. You combine the rigor of an OWASP security auditor with the practical knowledge of a senior WordPress developer and a D3plus visualization expert.


## Review
Update and create data import options with the APIs, create and update tables, and the corresponding modules for proper functionality:

- EJECUCION PRESUPUESTAL DE GASTOS ACUMULADA
https://narino-gob.sysman.com.co/sysmanApi/autoservicio/v1/informesGobNar?compania=001&anio=2025&mes=11&numinforme=1
- AUXILIAR PRESUPUESTAL POR CUENTAS
https://narino-gob.sysman.com.co/sysmanApi/autoservicio/v1/informesGobNar?compania=001&anio=2025&mes=11&numinforme=2&tipo_cpte=RES
- PLAN PRESUPUESTAL
https://narino-gob.sysman.com.co/sysmanApi/autoservicio/v1/informesGobNar?compania=001&anio=2025&mes=11&numinforme=4
- PERSONAL ACTIVO DE NOMINA
https://narino-gob.sysman.com.co/sysmanApi/autoservicio/v1/informesGobNar?compania=001&anio=2025&numinforme=5
- EJECUCION DE INGRESOS
https://narino-gob.sysman.com.co/sysmanApi/autoservicio/v1/informesGobNar?compania=001&anio=2025&mes=11&numinforme=6


-------------------------------- Educacion SED-------------------------------------------------------------------------------
- PLAN PRESUPUESTAL SED
https://narino-gob.sysman.com.co/sysmanApi/autoservicio/v1/informesGobNar?compania=007&anio=2025&mes=1&numinforme=4
- EJECUCION PRESUPUESTAL DE GASTOS ACUMULADA SED
https://narino-gob.sysman.com.co/sysmanApi/autoservicio/v1/informesGobNar?compania=007&anio=2025&mes=3&numinforme=1
- ECUCION DE INGRESOS SED
https://narino-gob.sysman.com.co/sysmanApi/autoservicio/v1/informesGobNar?compania=007&anio=2025&mes=3&numinforme=6
- AUXILIAR PRESUPUESTAL POR CUENTAS SED
https://narino-gob.sysman.com.co/sysmanApi/autoservicio/v1/informesGobNar?compania=007&anio=2025&mes=3&numinforme=2&tipo_cpte=RES

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

## Update

### v2.3.0 — Nuevos informes, multi-compañía y mejoras (2026-03-24)

#### 1. Nuevos informes (numinforme=5 y numinforme=6)
- [x] **Personal Activo de Nómina** (numinforme=5): nueva tabla `wp_sysman_personal_nomina` con 25 campos del API (iddeempleado, nombres, apellidos, cargo, categoría, escalafón, salario, dependencia, etc.). Nota: este informe NO usa parámetro `mes`.
- [x] **Ejecución de Ingresos** (numinforme=6): nueva tabla `wp_sysman_ejecucion_ingresos` con campos de recaudo (apropiado, modificaciones, totalPresupuesto, recaudos, porRecaudar, porcRecaudado).
- [x] Métodos de importación: `import_personal()`, `import_ingresos()` en class-importer.php
- [x] Métodos de inserción: `insert_personal_records()`, `insert_ingresos_records()` en class-database.php
- [x] Field maps: `get_personal_field_map()`, `get_ingresos_field_map()` en class-database.php

#### 2. Soporte multi-compañía (001 Gobernación, 007 SED)
- [x] Selector de compañía con opciones predefinidas: 001 (Gobernación de Nariño), 007 (SED - Secretaría de Educación)
- [x] Campo libre para otros códigos de compañía
- [x] Importación respeta compañía seleccionada en todas las consultas

#### 3. Actualización de la UI de importación
- [x] Selector de compañía reemplaza campo de texto libre por dropdown con opciones + campo personalizado
- [x] Dropdown de informes incluye los 5 tipos + opción "Todos"
- [x] Indicadores de pasos actualizados a 5 pasos
- [x] Labels de resultados actualizados para los 5 informes en admin-import.js

#### 4. Mejoras en visualización de datos
- [x] Las 5 tablas disponibles para gráficos en el selector de fuente de datos
- [x] Labels de columnas para campos de Personal y de Ingresos en admin-charts.js
- [x] Column types (numeric/text) registrados para los nuevos campos
- [x] Dashboard muestra estado de las 5 tablas

#### 5. Mejoras de configuración (implementadas en v2.2.0)
- [x] Página de Configuración con URLs de API, GitHub y CDN editables
- [x] Botón de verificación de conexión para todas las URLs
- [x] Todas las URLs se leen desde `wp_options` con valores por defecto



## Actions
- Verify the plugin's functionality
- Consult the documentation
- Identify improvements and best practices
- Update this document with the improvements to be made in the "Update" section.
- Implement the improvements
- Update the code
- Update the repository, maintaining the plugin version increment
- After performing all the actions, review the document https://github.com/GobernaciondeNarino/sisman-suite/blob/claude/sisman-wordpress-plugin-nzOmo/instructions.md.
