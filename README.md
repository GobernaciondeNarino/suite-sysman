# SISMAN Suite - Plugin WordPress

Plugin de WordPress para importar, almacenar y visualizar datos presupuestales desde el sistema **SISMAN** de la **Gobernación de Nariño**.

## Descripción

SISMAN Suite permite conectarse a la API del sistema presupuestal SISMAN para obtener y gestionar los siguientes informes:

1. **Ejecución Presupuestal de Gastos Acumulada** (`numinforme=1`)
2. **Auxiliar Presupuestal por Cuentas** (`numinforme=2`)
3. **Plan Presupuestal** (`numinforme=4`)

## Características

- Importación manual y programada (cron) de datos presupuestales
- 3 tablas de base de datos optimizadas para cada tipo de informe
- Visor de registros con filtros, búsqueda y paginación
- Exportación a CSV
- Gráficos interactivos con D3plus (8 tipos: barras, líneas, área, circular, dona, treemap, barras apiladas, barras agrupadas)
- Sistema de gráficos basado en Custom Post Type con shortcodes
- API REST para integración
- Comandos WP-CLI
- Panel de logs y diagnóstico del sistema
- Diseño accesible (WCAG 2.1 AA)

## Requisitos

- WordPress 6.0+
- PHP 8.1+
- MySQL 5.7+

## Instalación

1. Subir la carpeta `sisman-suite` al directorio `/wp-content/plugins/`
2. Activar el plugin desde el menú 'Plugins' de WordPress
3. Navegar a **SISMAN Suite** en el menú de administración
4. Configurar los parámetros de la API (compañía, año, mes)
5. Realizar la primera importación de datos

## Uso

### Importar Datos

1. Ir a **SISMAN Suite > Importar Datos**
2. Seleccionar compañía, año, mes y tipo de informe
3. Hacer clic en "Iniciar Importación"

### Ver Registros

1. Ir a **SISMAN Suite > Registros**
2. Seleccionar la tabla a consultar
3. Usar los filtros de año, mes y búsqueda

### Crear Gráficos

1. Ir a **SISMAN Suite > Gráficos > Nuevo Gráfico**
2. Configurar tipo de gráfico, fuente de datos y filtros
3. Publicar el gráfico
4. Usar el shortcode `[sisman_chart id="XX"]` en cualquier página

### WP-CLI

```bash
# Importar todos los informes
wp sisman import --anio=2024 --mes=6

# Importar solo ejecución presupuestal
wp sisman import --report=ejecucion --anio=2024 --mes=1

# Ver estadísticas
wp sisman stats

# Vaciar tablas
wp sisman truncate --yes
```

## API REST

| Endpoint | Método | Descripción |
|----------|--------|-------------|
| `/wp-json/sisman-suite/v1/records/{tabla}` | GET | Obtener registros con paginación |
| `/wp-json/sisman-suite/v1/stats` | GET | Estadísticas de registros |
| `/wp-json/sisman-suite/v1/tables` | GET | Lista de tablas disponibles |
| `/wp-json/sisman-suite/v1/columns/{tabla}` | GET | Columnas de una tabla |
| `/wp-json/sisman-suite/v1/chart/{id}` | GET | Datos de un gráfico |
| `/wp-json/sisman-suite/v1/chart/{id}/csv` | GET | Descargar CSV de un gráfico |
| `/wp-json/sisman-suite/v1/years/{tabla}` | GET | Años disponibles |

## API SISMAN

URL Base: `https://narino-gob.sysman.com.co/sysmanApi/autoservicio/v1/informesGobNar`

### Parámetros

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `compania` | String | Código de la compañía (ej: 001) |
| `anio` | Integer | Año fiscal |
| `mes` | Integer | Mes de corte (1-12) |
| `numinforme` | Integer | Número del informe (1, 2, 4) |
| `tipo_cpte` | String | Tipo de comprobante (solo informe 2) |

## Licencia

GPL v2 o posterior.

## Autor

**Gobernación de Nariño**
