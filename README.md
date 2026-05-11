# SYSMAN Suite - Plugin WordPress

Plugin de WordPress para importar, almacenar y visualizar datos presupuestales desde el sistema **SYSMAN** de la **Gobernacion de Narino**.

## Descripcion

SYSMAN Suite permite conectarse a la API del sistema presupuestal SYSMAN para obtener y gestionar los siguientes informes:

1. **Ejecucion Presupuestal de Gastos Acumulada** (`numinforme=1`)
2. **Auxiliar Presupuestal por Cuentas** (`numinforme=2`) — DIS, RES, OBL, EGR
3. **Plan Presupuestal** (`numinforme=4`)
4. **Personal Activo de Nomina** (`numinforme=5`)
5. **Ejecucion de Ingresos** (`numinforme=6`)

## Caracteristicas

### Importacion de Datos
- Importacion manual y programada (WP-Cron) de 6 tipos de informes
- Soporte multi-compania configurable
- Importacion individual o masiva (todos los informes a la vez)
- Extraccion automatica del envelope SYSMAN (`codigo`, `mensaje`, `cuerpo`)
- Insercion por lotes de 500 registros para rendimiento optimo
- Logs detallados de cada importacion

### Modulo Ejecucion (Nuevo en v4.0.0)
- Seguimiento presupuestal por dependencia con acordeones anidados
- Navegacion: **Dependencia > Rubros > Ejecucion Consolidada > DIS > RES**
- Custom Post Type (`gn_ejecucion`) para multiples seguimientos independientes
- API REST interna con carga lazy para rendimiento optimo
- Cache con transients (5 min REST, 12 h dependencias)
- Sincronizacion dedicada desde la interfaz de edicion
- Shortcode `[gn_ejecucion id="X"]` para embebido en paginas publicas
- Paleta institucional: azul `#1a5276`, dorado `#E8A020`, azul oscuro `#003087`

### Base de Datos
- 5 tablas optimizadas con indices compuestos
- Migracion automatica con versionamiento (`gn_sisman_schema_version`)
- `dbDelta` para actualizaciones idempotentes
- Esquema alineado entre `class-database.php` y `Schema.php`

### Visualizacion
- Visor de registros con filtros, busqueda y paginacion
- Exportacion a CSV
- Graficos interactivos con D3plus (8 tipos: barras, lineas, area, circular, dona, treemap, barras apiladas, barras agrupadas)
- Sistema de graficos basado en Custom Post Type con shortcodes

### Configuracion
- URL de API SYSMAN configurable
- URLs de CDN (D3.js, D3plus) configurables
- Repositorio GitHub para actualizaciones automaticas
- Test de conexion desde la pagina de configuracion

### Otros
- API REST para integracion externa
- Comandos WP-CLI
- Panel de logs y diagnostico del sistema
- Diseno accesible (WCAG 2.1 AA)

## Requisitos

- WordPress 6.0+
- PHP 8.1+
- MySQL 5.7+

## Instalacion

1. Subir la carpeta `sysman-suite` al directorio `/wp-content/plugins/`
2. Activar el plugin desde el menu 'Plugins' de WordPress
3. Navegar a **SYSMAN Suite > Configuracion** para establecer la URL de la API
4. Ir a **SYSMAN Suite > Importar Datos** para la primera importacion

## Uso

### Importar Datos

1. Ir a **SYSMAN Suite > Importar Datos**
2. Seleccionar compania, ano, mes y tipo de informe
3. Para Auxiliar, seleccionar tipo de comprobante (DIS, RES, OBL, EGR)
4. Hacer clic en "Iniciar Importacion"

### Modulo Ejecucion

1. Ir a **SYSMAN Suite > Ejecucion**
2. Crear un nuevo seguimiento con "Nuevo"
3. Seleccionar ano, mes y dependencia
4. Hacer clic en "Sincronizar" para cargar los datos
5. Ver el seguimiento para navegar los acordeones:
   - Expandir un rubro para ver la ejecucion consolidada
   - Expandir para ver las Disponibilidades (DIS)
   - Expandir una DIS para ver las Reservas (RES) asociadas

### Ver Registros

1. Ir a **SYSMAN Suite > Registros**
2. Seleccionar la tabla a consultar
3. Usar los filtros de ano, mes y busqueda

### Crear Graficos

1. Ir a **SYSMAN Suite > Graficos > Nuevo Grafico**
2. Configurar tipo de grafico, fuente de datos y filtros
3. Publicar el grafico
4. Usar el shortcode `[sysman_chart id="XX"]` en cualquier pagina

### WP-CLI

```bash
# Importar todos los informes
wp sysman import --anio=2026 --mes=5

# Importar solo ejecucion presupuestal
wp sysman import --report=ejecucion --anio=2026 --mes=5

# Ver estadisticas
wp sysman stats

# Vaciar tablas
wp sysman truncate --yes
```

## Tablas de Base de Datos

| Tabla | Informe | Descripcion |
|-------|---------|-------------|
| `{prefix}sysman_ejecucion_gastos` | `numinforme=1` | Ejecucion consolidada por rubro |
| `{prefix}sysman_auxiliar_cuentas` | `numinforme=2` | Movimientos DIS, RES, OBL, EGR |
| `{prefix}sysman_plan_presupuestal` | `numinforme=4` | Catalogo de rubros del plan |
| `{prefix}sysman_personal_nomina` | `numinforme=5` | Personal activo de nomina |
| `{prefix}sysman_ejecucion_ingresos` | `numinforme=6` | Ejecucion de ingresos |

## API REST

### Endpoints Principales

| Endpoint | Metodo | Descripcion |
|----------|--------|-------------|
| `/wp-json/sysman-suite/v1/records/{tabla}` | GET | Obtener registros con paginacion |
| `/wp-json/sysman-suite/v1/stats` | GET | Estadisticas de registros |
| `/wp-json/sysman-suite/v1/tables` | GET | Lista de tablas disponibles |
| `/wp-json/sysman-suite/v1/columns/{tabla}` | GET | Columnas de una tabla |
| `/wp-json/sysman-suite/v1/chart/{id}` | GET | Datos de un grafico |
| `/wp-json/sysman-suite/v1/chart/{id}/csv` | GET | Descargar CSV de un grafico |
| `/wp-json/sysman-suite/v1/years/{tabla}` | GET | Anos disponibles |

### Endpoints Ejecucion

| Endpoint | Metodo | Descripcion |
|----------|--------|-------------|
| `/wp-json/gn-sisman/v1/dependencias` | GET | Dependencias unicas del plan presupuestal |
| `/wp-json/gn-sisman/v1/ejecucion/{post_id}/rubros` | GET | Rubros de la dependencia |
| `/wp-json/gn-sisman/v1/consolidado` | GET | Ejecucion consolidada por rubro |
| `/wp-json/gn-sisman/v1/dis` | GET | Disponibilidades por cuenta |
| `/wp-json/gn-sisman/v1/res` | GET | Reservas por disponibilidad |

## API SYSMAN

URL Base: `https://narino-gob.sysman.com.co/sysmanApi/autoservicio/v1/informesGobNar`

### Parametros

| Parametro | Tipo | Descripcion |
|-----------|------|-------------|
| `compania` | String | Codigo de la compania (ej: 001) |
| `anio` | Integer | Ano fiscal |
| `mes` | Integer | Mes de corte (1-12) |
| `numinforme` | Integer | Numero del informe (1, 2, 4, 5, 6) |
| `tipo_cpte` | String | Tipo de comprobante: DIS, RES, OBL, EGR (solo informe 2) |

### Formato de Respuesta

```json
{
  "codigo": 0,
  "mensaje": "OK",
  "cuerpo": [ ... ]
}
```

## Estructura del Proyecto

```
sisman-suite/
├── sisman-suite.php              # Bootstrap principal
├── uninstall.php                 # Limpieza al desinstalar
├── includes/
│   ├── class-database.php        # Gestion de tablas y consultas
│   ├── class-importer.php        # Importador de datos (6 informes)
│   ├── class-visualizer.php      # Visualizacion de registros
│   ├── class-rest-api.php        # API REST principal
│   ├── class-logger.php          # Sistema de logs
│   ├── class-updater.php         # Actualizaciones desde GitHub
│   ├── class-cli.php             # Comandos WP-CLI
│   └── Ejecucion/                # Modulo Ejecucion
│       ├── Schema.php            # Migracion de tablas (v5.0.0)
│       ├── EjecucionModule.php   # Bootstrap del modulo
│       ├── PostType.php          # CPT gn_ejecucion
│       ├── RestController.php    # Endpoints REST del acordeon
│       ├── Repository.php        # Consultas SQL
│       └── AccordionRenderer.php # HTML del acordeon
├── templates/admin/
│   ├── dashboard-page.php
│   ├── import-page.php
│   ├── records-page.php
│   ├── logs-page.php
│   ├── settings-page.php
│   └── ejecucion/
│       ├── ejecucion-list.php
│       ├── ejecucion-edit.php
│       └── ejecucion-view.php
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   ├── ejecucion.css
│   │   └── frontend.css
│   └── js/
│       ├── admin-import.js
│       ├── admin-charts.js
│       ├── ejecucion.js
│       └── frontend.js
└── logs/
```

## Changelog

### 5.0.0
- **Arquitectura unificada**: toda importacion de datos externos pasa exclusivamente por el modulo Importer
- Eliminacion de sincronizadores duplicados (`SysmanClient`, `PlanPresupuestalSyncer`, `EjecucionConsolidadaSyncer`, `MovimientosSyncer`)
- El boton "Sincronizar Datos Ahora" del modulo Ejecucion ahora usa el Importer centralizado
- Correccion de columna `desplazaminento` (typo) renombrada a `desplazamiento` con migracion automatica ALTER TABLE
- Unificacion de columna timestamp: `synced_at` migrada a `fecha_importacion` en todas las tablas
- Schema v5.0.0 ya no usa DROP TABLE — preserva datos existentes en actualizaciones
- Todas las tablas creadas via `Schema::*_sql()` como unica fuente de verdad (DDL unificado)
- Correccion de scope de borrado: ejecucion e ingresos ahora borran por `compania+anio+mes` (no solo `compania+anio`)
- Nuevo metodo `Database::insert_plan_records()` con INSERT batch de 500 filas
- Importacion de Plan Presupuestal usa fetch propio en vez de delegacion al syncer
- Limpieza de referencias obsoletas en JS (`desplazaminento` en ejecucion.js y admin-import.js)

### 4.3.0
- Correccion de graficos con Query personalizada: la vista previa y el renderizado ahora usan la query custom correctamente
- Deteccion automatica de `has_groups` desde los datos reales del query
- Nuevo filtro **Vigencia** en seguimientos de ejecucion (filtra rubros por `tipovigencia`)
- Shortcode `[gn_ejecucion id="X"]` visible en listado, edicion y vista de seguimientos
- Acordeon publico funciona sin sesion (endpoints REST publicos, sin dependencia de `wp-api`)
- RES renombrado a "Registros de Compromiso" con filtro por rubro
- Cruce de proyecto BPIN con tabla `bpid_suite_contratos` (nombre_proyecto, metas, ODS)
- Metas y ODS renderizados como lista con vinetas

### 4.2.0
- Correccion de URL del menu Ejecucion: redireccionaba a pagina 404 (`wp-admin/sysman-ejecucion` en vez de `admin.php?page=sysman-ejecucion`)
- Ajuste de prioridad en `admin_menu` para garantizar orden de registro correcto

### 4.1.0
- Correccion de creacion de tabla `plan_presupuestal` (uso de `$wpdb->query` en vez de `dbDelta`)
- Timeout de API incrementado a 300 segundos para importaciones grandes
- Campo `codigoUnidadEjecutora` agregado al plan presupuestal (23 campos completos)
- Schema v4.3.0 con indices optimizados y prefijos de longitud
- Verificacion de errores en inserciones batch
- `sslverify` deshabilitado globalmente para certificados institucionales

### 4.0.0
- Nuevo modulo **Ejecucion** con acordeones anidados (Dependencia > Rubros > Consolidada > DIS > RES)
- Custom Post Type `gn_ejecucion` para seguimientos independientes
- 5 endpoints REST internos para carga lazy del acordeon
- Sincronizadores dedicados (reemplazados en v5.0.0 por Importer centralizado)
- Schema v4.1.0: reconstruccion completa de `plan_presupuestal` y `auxiliar_cuentas`
- Extraccion correcta del envelope SYSMAN (`cuerpo`)
- Correccion de valores `tipo_cpte` en dropdown (DIS, RES, OBL, EGR)
- Importacion de auxiliar preserva datos entre tipos (DIS no borra RES)
- Verificacion de errores en inserciones batch
- Esquema `class-database.php` alineado con `Schema.php`

### 3.1.0
- Soporte multi-compania configurable
- Nuevos informes: Personal Activo de Nomina (numinforme=5) y Ejecucion de Ingresos (numinforme=6)

### 3.0.0
- Configuracion de URL de API y CDNs desde pagina de ajustes
- Test de conexion integrado

### 2.0.0
- Graficos multi-serie con D3plus
- Vista previa en tiempo real en admin
- 8 tipos de graficos

### 1.0.0
- Version inicial con importacion, visualizacion y graficos basicos

## Licencia

GPL v2 o posterior.

## Autor

**Gobernacion de Narino**
