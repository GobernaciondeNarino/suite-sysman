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

### Modulos Gastos e Ingresos (v5.12.0)
- Vistas presupuestales prediseñadas y publicables con shortcodes, sin configurar nada
- **Gastos**: agrupa por dependencia y detalla por rubro, con la cadena DIS > RES > OBL > EGR
- **Ingresos**: agrupa por tipo o fuente de recurso y detalla por cuenta, con avance del recaudo
- Seis componentes por modulo: `treemap`, `lista`, `ejecucion`, `explora`, `analisis`, `selector`
- Filtrado cruzado opcional entre shortcodes de la misma pagina (`enlazar="si|no"`, `grupo`)
- Campo y tooltip parametrizables, validados contra una whitelist de metricas
- Analisis descriptivo, cualitativo y cuantitativo derivados de los datos reales
- Catalogo de shortcodes copiables en *SYSMAN Suite > Gastos* y *SYSMAN Suite > Ingresos*

### Base de Datos
- 5 tablas optimizadas con indices compuestos
- Migracion automatica con versionamiento (`gn_sisman_schema_version`)
- `CREATE TABLE IF NOT EXISTS` con migraciones idempotentes (sin dependencia de `dbDelta`)
- Esquema centralizado en `Schema.php` como unica fuente de verdad

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

### Endpoints Ejecucion (`gn-sisman/v1`)

| Endpoint | Metodo | Acceso | Descripcion |
|----------|--------|--------|-------------|
| `/dependencias` | GET | Admin | Dependencias unicas del plan presupuestal |
| `/vigencias` | GET | Admin | Vigencias unicas del plan presupuestal |
| `/ejecucion/{post_id}/rubros` | GET | Publico | Rubros de la dependencia |
| `/ejecucion/{post_id}/consolidado?codigo=X` | GET | Publico | Ejecucion consolidada por rubro |
| `/ejecucion/{post_id}/dis?codigocuenta=X` | GET | Publico | Disponibilidades por cuenta |
| `/ejecucion/{post_id}/res?numero_dis=X&rubro=X` | GET | Publico | Reservas por disponibilidad |
| `/ejecucion/{post_id}/proyecto?codigobpin=X` | GET | Publico | Datos del proyecto BPIN |

### Endpoints Datos Abiertos (`gn-sisman/v1`)

| Endpoint | Metodo | Acceso | Descripcion |
|----------|--------|--------|-------------|
| `/ejecucion/{post_id}/export` | GET | Publico | Rubros + ejecucion consolidada de un seguimiento (JSON) |
| `/reporte/disponibilidades?anio=X&mes=X` | GET | Publico | Disponibilidades (DIS) cruzadas con plan presupuestal (JSON) |

#### Filtros dinamicos (v5.7.2+)

Ambos endpoints aceptan parametros adicionales para filtrar, buscar, paginar y ordenar:

| Parametro | Tipo | Descripcion |
|-----------|------|-------------|
| `buscar` | string | Busqueda global LIKE en todos los campos de texto |
| `per_page` | int | Registros por pagina (1–1000). Activa paginacion |
| `pagina` | int | Numero de pagina (def: 1) |
| `orderby` | string | Campo para ordenar (nombre de filtro valido) |
| `order` | string | ASC o DESC (def: ASC) |

**Filtros por campo** — cada columna del resultado se puede usar como parametro de filtro:

- `/reporte/disponibilidades`: `numero`, `tercero`, `nombretercero`, `rubro`, `nombrerubro`, `descripcion`, `nrodocumento`, `cmpteafectado`, `fecha`, `nombredependencia`, `destino`, `naturaleza`, `sector`, `programa`, `subprograma`, `codigoproducto`, `codigobpin`
- `/ejecucion/{id}/export`: `codigo`, `nombre`, `destino`, `naturaleza`, `sector`, `programa`, `subprograma`, `codigoproducto`, `codigobpin`

Ejemplo: `/reporte/disponibilidades?anio=2026&mes=5&numero=2026040001&per_page=50&pagina=1`

### Descargas (admin-ajax, publico)

| Accion | Formatos | Descripcion |
|--------|----------|-------------|
| `gn_ejecucion_export` | `csv`, `txt` | Exporta la ejecucion consolidada de un seguimiento |
| `gn_reporte_dis_export` | `csv`, `txt` | Exporta el reporte de disponibilidades |

Los endpoints de descarga tambien aceptan los filtros dinamicos y `buscar` como parametros GET.

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
│   ├── Ejecucion/                # Modulo Ejecucion
│   │   ├── Schema.php            # Migracion de tablas (v5.0.0)
│   │   ├── EjecucionModule.php   # Bootstrap del modulo
│   │   ├── PostType.php          # CPT gn_ejecucion
│   │   ├── RestController.php    # Endpoints REST del acordeon + exportacion
│   │   ├── Repository.php        # Consultas SQL
│   │   └── AccordionRenderer.php # HTML del acordeon
│   ├── DatosAbiertos/            # Modulo Datos Abiertos (v5.7.0)
│   │   └── DatosAbiertosModule.php # Shortcodes de exportacion + descargas CSV/TXT
│   └── Presupuesto/              # Modulos Gastos e Ingresos (v5.11.0 / v5.12.0)
│       ├── PresupuestoModule.php  # Bootstrap, shortcodes y menus de admin
│       ├── Repository.php         # Consultas de gastos (dependencia > rubro > cadena)
│       ├── IngresosRepository.php # Consultas de ingresos (recurso > cuenta)
│       ├── RestController.php     # Endpoints REST de ambos modulos
│       └── Analysis.php           # Motor de analisis automatico
├── templates/admin/
│   ├── dashboard-page.php
│   ├── import-page.php
│   ├── records-page.php
│   ├── logs-page.php
│   ├── settings-page.php
│   ├── ejecucion/
│   │   ├── ejecucion-list.php
│   │   ├── ejecucion-edit.php
│   │   └── ejecucion-view.php
│   ├── datos-abiertos/
│   │   └── datos-abiertos.php
│   └── presupuesto/
│       └── catalogo.php
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   ├── ejecucion.css
│   │   ├── datos-abiertos.css
│   │   ├── presupuesto.css
│   │   └── frontend.css
│   └── js/
│       ├── admin-import.js
│       ├── admin-charts.js
│       ├── ejecucion.js
│       ├── datos-abiertos.js
│       ├── presupuesto.js
│       └── frontend.js
├── tests/                  # Tests unitarios standalone (php tests/run-tests.php)
├── .github/workflows/      # CI, asistente @claude y revisión de seguridad
└── .claude/skills/         # Skill ui-ux-pro-max para Claude Code
```

> Nota: el log de importaciones vive en `wp-content/uploads/sysman-suite/`
> (protegido con `.htaccess` + `index.php`), no dentro del plugin.

## Changelog

### 5.12.0 — Gastos e Ingresos
El módulo Presupuesto pasa a llamarse **Gastos** y se añade un módulo gemelo para **Ingresos**.

**Shortcodes**

| Gastos | Ingresos | Qué hace |
|--------|----------|----------|
| `[sysman_gastos_treemap]` | `[sysman_ingresos_treemap]` | Treemap por el campo elegido. Al hacer clic baja al detalle. |
| `[sysman_gastos_lista]` | `[sysman_ingresos_lista]` | Lista con nº de rubros/cuentas y valor, con buscador. |
| `[sysman_gastos_ejecucion]` | `[sysman_ingresos_ejecucion]` | Detalle: consolidado y, en gastos, modificaciones y cadena documental. |
| `[sysman_gastos_explora]` | `[sysman_ingresos_explora]` | Maestro-detalle en dos columnas. |
| `[sysman_gastos_analisis]` | `[sysman_ingresos_analisis]` | Descripción, análisis cualitativo o cuantitativo. |
| `[sysman_gastos_selector]` | `[sysman_ingresos_selector]` | Desplegable que fija el filtro compartido de la página. |

Los `[sysman_pre_*]` de la 5.11.0 siguen funcionando como alias de Gastos, así que las páginas ya publicadas no se rompen.

- **Fix**: el botón «← Todas las dependencias» no volvía a la vista inicial. El componente limpiaba su estado interno *antes* de publicarlo al coordinador, de modo que el suscriptor recibía el valor que ya tenía y no repintaba. Ahora se publica primero y el estado se limpia al recibir el evento.
- **Nuevo atributo `tooltip`**: lista de campos separados por coma que se muestran en el tooltip del gráfico, p. ej. `tooltip="compromisos,pagos"`. Se validan contra la whitelist de métricas y la métrica principal no se repite aunque se incluya.
- **Lista más compacta**: el nombre y las cifras (nº de rubros · valor) van ahora en la misma línea, con el valor alineado a la derecha.
- **Color institucional `#348AFB`** para los elementos resaltados y seleccionables (bordes, fondos, barras y anillos de foco). El *texto* de los ítems usa `#0B62D6`, el mismo azul oscurecido: `#348AFB` sobre blanco da 3,4:1 y no alcanza el 4,5:1 que exige WCAG AA en texto normal.
- **Módulo Ingresos**: en ingresos no hay dependencias, así que la agrupación es **tipo de recurso → cuenta** por defecto, con `dimension="fuenterecurso"` como alternativa. Campos: apropiado, modificaciones, total presupuesto, recaudos anteriores/del mes/acumulados y por recaudar (`porcrecaudado` se excluye por ser un porcentaje por fila: sumarlo no significa nada; el % se recalcula sobre los totales). El detalle muestra KPIs, barra de avance del recaudo, composición del recaudo y clasificación, sin cadena documental (los ingresos no la tienen).
- **Sin duplicar código**: el controlador REST, el motor de análisis y los componentes JS se parametrizaron por módulo en lugar de copiarse. Las rutas `dependencias`/`rubros`/`rubro` conservan su nombre y se añaden los alias genéricos `dimensiones`/`detalle`/`item`.
- **Análisis por módulo**: el texto se adapta al lado presupuestal — gastos habla de comprometido/obligado/pagado; ingresos, de recaudado, recaudado en el mes y por recaudar. Corregida también la concordancia de género y el plural en español («los tres primeros rubros», «Todos los tipos de recurso»).
- **Fix (CSS)**: el lienzo del treemap colapsaba a 0 px de alto y, con `overflow:hidden`, recortaba el gráfico entero.
- **Admin**: dos catálogos de shortcodes copiables, *SYSMAN Suite → Gastos* y *SYSMAN Suite → Ingresos*.
- **Pruebas**: 76 aserciones (antes 62). Los cinco cambios se verificaron además en navegador sobre el JS y las plantillas reales.

### 5.11.0 — Nuevo módulo Presupuesto
Vistas presupuestales prediseñadas, publicables con shortcodes y enlazables entre sí.

**Shortcodes** (catálogo copiable en *SYSMAN Suite → Presupuesto*):

| Shortcode | Qué hace |
|-----------|----------|
| `[sysman_pre_treemap]` | Treemap de todas las dependencias por el campo elegido. Al hacer clic baja a los rubros de esa dependencia. |
| `[sysman_pre_lista]` | Lista de dependencias con nº de rubros y valor, con buscador. |
| `[sysman_pre_ejecucion]` | Ejecución de una dependencia por rubro: consolidado, modificaciones y cadena documental. |
| `[sysman_pre_explora]` | Maestro-detalle: dependencias a la izquierda, su ejecución a la derecha. |
| `[sysman_pre_analisis]` | Descripción, análisis cualitativo o cuantitativo generados de los datos. |
| `[sysman_pre_selector]` | Desplegable que fija el filtro compartido de la página. |

- **Filtrado cruzado opcional**: todos aceptan `enlazar="si|no"` (por defecto `si`). Los enlazados comparten el filtro de la página: al hacer clic en el treemap o la lista se actualizan el detalle, el análisis y el selector. Con `enlazar="no"` el elemento queda aislado. El atributo `grupo` permite tener dos conjuntos enlazados independientes en la misma página.
- **Campo parametrizable**: `campo="apropiacionvigente"` (por defecto) y cualquiera de las 15 métricas de ejecución (compromisos, obligacion, pagos, saldodisponible, adicion, reduccion…), validadas contra una whitelist.
- **Periodo automático**: si se omiten `anio`/`mes`, las vistas usan siempre el periodo más reciente con datos.
- **Cadena de ejecución completa**: cada rubro despliega Disponibilidad (CDP) → Registro de compromiso (RP) → Obligación → Pago, anidados por `tipocpteafect`/`cmpteafectado` en una sola consulta. Los documentos cuyo comprobante padre está fuera del periodo se listan aparte en lugar de descartarse.
- **Importador ampliado**: la importación completa ahora trae los 4 tipos del auxiliar (DIS, RES, **OBL** y **EGR**); antes solo DIS y RES, por lo que la mitad de la cadena no existía en la base de datos. La barra de progreso pasa de 6 a 8 pasos.
- **Análisis automático**: la descripción resume qué se muestra; el cualitativo interpreta concentración (top 3 y Pareto 80%), niveles de ejecución y dispersión; el cuantitativo entrega total, promedio, mediana, máximo, mínimo, desviación, coeficiente de variación y los % comprometido/obligado/pagado. Cada frase se deriva de las cifras reales: si un ratio no se puede calcular, la frase se omite.
- **REST**: `sysman-suite/v1/presupuesto/{periodos,dependencias,rubros,rubro,analisis}`, públicos y con rate-limiting por IP.
- **Caché**: agregados cacheados 5 minutos e invalidados automáticamente tras cada importación.
- **Pruebas**: 62 aserciones (antes 38), con cobertura del armado de la cadena documental (incluidos huérfanos y ciclos), la whitelist de campos y el motor de análisis. Verificado en navegador de punta a punta.

### 5.10.1
- **Fix**: el panel "Datos a Graficar" mostraba los contadores en `0 REGISTROS / 0 SERIES / —` aunque nunca se hubiera consultado. La regla `.sysman-data-panel__summary { display: grid }` tiene la misma especificidad que el `[hidden] { display: none }` del navegador y, al ir después, lo anulaba
- **Nuevo: diagnostico automatico de Vistas.** Cuando la Vista no devuelve registros, el panel ya no dice solo "0": explica cual de las condiciones del cruce falla — si Plan Presupuestal no tiene rubros con `movimiento = SI`, si Ejecucion de Gastos no tiene datos del periodo (o los tiene pero con otro valor de `movimiento`, listando cuales), si los codigos cruzan pero en años/meses distintos (mostrando los periodos disponibles en cada tabla), o si `pp.codigo` y `eg.codigocuenta` no coinciden en formato (con ejemplos de ambos)
- **Robustez**: `loadDependencias()` ya no rompe la pantalla de configuracion si la respuesta REST no es una lista (por ejemplo ante un HTTP 429 del rate-limiting)

### 5.10.0
- **Fix critico (Vistas)**: los graficos en modo "Vistas" no se podian crear. Los paneles "Tablas" y "Vistas" compartian el mismo campo `sysman_value_columns[]` y el codigo activaba/desactivaba el atributo `name` con jQuery para decidir cual enviaba. En un grafico nuevo el panel de Vistas se mostraba **sin ninguna columna Y** (la funcion que las carga solo se ejecutaba si el grafico ya estaba guardado como Vista), asi que al publicar no se enviaba fuente de datos alguna. Ahora cada panel tiene sus propios campos (`sysman_vista_value_columns[]`, `sysman_vista_aggregate`) y PHP guarda el conjunto que corresponde al modo activo
- **Fix (Vistas)**: el panel de Vistas se rellena con las metricas por defecto (Apropiacion Vigente, Compromisos, Pagos) al abrir la pestaña, y las mantiene al alternar entre pestañas
- **Fix (Vistas)**: se elimina la contaminacion cruzada entre pestañas — agregar un filtro estando en Vistas reactivaba el panel oculto de Tablas e inyectaba columnas ajenas en la configuracion guardada
- **Fix (Vistas)**: `<select>` de agregacion del panel Vistas no tenia atributo `name`; solo se guardaba por un sincronizador de JS
- **Nuevo: panel "Datos a Graficar"** en la columna lateral, justo debajo de "Publicar": muestra registros, series, total y una tabla con los datos reales que alimentaran la grafica antes de publicar, con la descripcion de la fuente (tabla/vista, agrupacion, agregacion y periodo)
- **D3plus v2 → v4**: se migra del paquete `d3plus` (congelado en 2.1.3) a `@d3plus/core` v4.3.0. La API encadenable que usa el plugin es compatible, verificada tipo por tipo
- **D3plus v4**: ya no se carga D3 por separado — v4 incluye sus propios modulos de D3. La opcion `sysman_d3_cdn_url` se elimina y el campo desaparece de Configuracion
- **D3plus v4**: la libreria usa `crypto.randomUUID()`, disponible solo en contexto seguro. En sitios servidos por HTTP plano la libreria no cargaba en absoluto; se inyecta un polyfill antes del bundle para que siga funcionando
- **D3plus v4**: locale corregido a `es-ES` (el valor anterior `es_ES` no coincide con la tabla de locales de la libreria y caia al formato en ingles)
- **Migracion automatica**: las instalaciones existentes que apunten a cualquier CDN de d3plus v1/v2 se actualizan solas a la URL de v4
- **Pruebas**: 38 aserciones (antes 31), con cobertura de regresion para el guardado de Vistas frente a Tablas. Los 11 tipos de grafico se verificaron renderizando con el `frontend.js` real sobre D3plus v4

### 5.9.0
Implementación del plan de mejora de `AUDITORIA.md` (v5.8.0):
- **Seguridad**: Rate-limiting por IP en todos los endpoints públicos (REST `sysman-suite/v1/chart/*`, `gn-sisman/v1/*` y exports AJAX `nopriv`): 120 req/min para lectura, 30 req/min para exports pesados, 20 req/min para descargas de Datos Abiertos; devuelve HTTP 429. Los administradores no se limitan; ajustable con el filtro `sysman_suite_rate_limit`
- **Seguridad**: Los CPTs `sysman_chart` y `gn_ejecucion` ahora exigen `manage_options` en todas sus capacidades (antes cualquier Autor/Editor podia crear graficos entrando por `post-new.php?post_type=sysman_chart`)
- **Seguridad**: Cabecera `X-Content-Type-Options: nosniff` en todas las descargas (CSV/TXT), unificadas en `Helpers::download_headers()`
- **Fix**: Todas las fechas por defecto usan la zona horaria de WordPress (`current_time`) en lugar de la del servidor (`date`) — evita importar el mes equivocado cerca de medianoche
- **Fix**: Cambiar la frecuencia de importacion en Configuracion ahora reprograma el cron inmediatamente (antes solo se aplicaba al reactivar el plugin); la frecuencia se valida contra la lista de schedules permitidos
- **Fix**: Los graficos dentro de widgets o page builders ahora cargan D3/D3plus (encolado tardio desde el propio shortcode ademas de la deteccion en `post_content`)
- **Fix**: El updater ya no referencia `assets/icon-128.png` si el archivo no existe
- **Rendimiento**: Importaciones por lotes (INSERT de 500 filas) para los 5 informes — antes ejecucion/auxiliar/personal/ingresos insertaban fila por fila
- **Robustez**: Cada importacion (DELETE + INSERT) se ejecuta dentro de una transaccion: un fallo a mitad de carga revierte todo y conserva los datos anteriores (antes el mes quedaba vacio o a medias)
- **Robustez**: Rotacion automatica del log al superar 5 MB (una generacion de respaldo `.log.1`)
- **Refactor**: Los 5 metodos de insercion duplicados en `Database` se unifican en `replace_records()` (~180 lineas menos); `get_dependencias()` unificado en `Repository` (el Visualizer delega); invalidacion de cache de dependencias completa (todas las combinaciones compania/anio/mes)
- **Calidad**: Nueva suite de tests standalone (`tests/run-tests.php`, 31 aserciones: validador SQL, rate limiter, sanitizador CSV, helpers) y workflow de CI (`.github/workflows/ci.yml`) con lint de sintaxis y tests en PHP 8.1 y 8.3
- **Calidad**: `frontend.js` construye las tablas del modal con una sola escritura de `innerHTML` (antes reparseaba el nodo en cada fila)

### 5.8.0
- **Seguridad (critico)**: Las queries personalizadas de graficos (`custom_query`) ahora pasan por `Visualizer::validate_custom_query()` en cada ejecucion: solo se admite una unica sentencia SELECT, sin comentarios SQL ni palabras clave peligrosas (INSERT/UPDATE/DELETE/DROP/OUTFILE/SLEEP/etc.), y unicamente sobre las tablas del plugin. Antes, una query almacenada se ejecutaba sin validacion a traves del endpoint REST publico `/chart/{id}`
- **Seguridad**: Guardar una query personalizada ahora requiere la capacidad `manage_options` (antes bastaba `edit_post`, lo que permitia a un Editor ejecutar SQL arbitrario)
- **Seguridad**: Verificacion SSL activada por defecto en todas las peticiones a la API SYSMAN (`sslverify => true`). Si el certificado del endpoint no es validable, puede desactivarse con el filtro `sysman_suite_sslverify`
- **Seguridad**: El log de importaciones se movio a `wp-content/uploads/sysman-suite/` con nombre no adivinable, `.htaccess` (Require all denied) e `index.php`; antes vivia dentro del plugin (se borraba al actualizar y era descargable publicamente en servidores Nginx). El log antiguo se migra automaticamente
- **Seguridad**: Proteccion contra inyeccion de formulas CSV en la descarga `/chart/{id}/csv` (igual que en Datos Abiertos)
- **Seguridad**: Chequeos de capacidad agregados en `ajax_import_status` y `ajax_dismiss_update_notice`
- **Fix**: La descarga CSV de graficos (`/chart/{id}/csv`) devolvia el CSV codificado como cadena JSON (comillas escapadas); ahora sirve el archivo real con BOM UTF-8 y `fputcsv`
- **Fix**: `uninstall.php` ahora elimina las 5 tablas (faltaban `personal_nomina` y `ejecucion_ingresos`), todas las opciones (`sysman_api_base_url`, CDNs, `gn_sisman_*`), los transients `gn_sisman_*`, los seguimientos `gn_ejecucion` y el directorio de logs
- **Fix**: `per_page`/`page` del endpoint `/records` se acotan a un minimo de 1 (per_page=0 devolvia vacio)
- **Refactor**: Mapa de etiquetas de columnas unificado en `Visualizer::COLUMN_LABELS` (estaba triplicado); consultas Vista unificadas en `compose_vista_query()` (guardadas y preview compartian ~100 lineas duplicadas)
- **Refactor**: `Database::validate_column()` reutiliza `get_table_columns()` (cache duplicada); `Importer::build_url()` acepta `mes` opcional (elimina construccion manual en `import_personal`); helper `save_last_import()`; `ajax_sync` del modulo Ejecucion refactorizado a un bucle
- **Refactor**: Nombres de meses centralizados en `SysmanSuite\Helpers::month_name()` (estaban cuadruplicados)
- **Herramientas**: Instalado el skill `ui-ux-pro-max` en `.claude/skills/`, workflows de GitHub Actions `claude.yml` (asistente @claude, anthropics/claude-code-action) y `claude-security-review.yml` (anthropics/claude-code-security-review), y guia `CLAUDE.md` para Claude Code
- **Documentacion**: Nueva lista de auditoria `AUDITORIA.md` con hallazgos pendientes y plan de mejora priorizado

### 5.7.3
- **Fix critico (graficos)**: Todas las consultas del modulo de graficos ahora filtran por `movimiento = 'SI'` en las tablas `plan_presupuestal`, `ejecucion_gastos` y `ejecucion_ingresos`
- Las queries en modo "tabla" (`build_chart_query`, `build_multi_y_query`) inyectan automaticamente el filtro cuando la tabla es financiera
- Las queries en modo "Vista" ahora filtran tanto `pp.movimiento = 'SI'` como `eg.movimiento = 'SI'` (antes solo filtraban pp)
- La vista previa (preview AJAX) aplica el mismo filtro en ambos modos
- `get_dependencias()` del Visualizer ahora solo muestra dependencias con rubros de movimiento
- `get_consolidado()` en Repository ahora filtra `movimiento = 'SI'` al consultar `ejecucion_gastos`
- `get_export_data()` agrega `eg.movimiento = 'SI'` en la condicion LEFT JOIN para excluir registros sin movimiento

### 5.7.2
- **APIs flexibles**: Los endpoints `/ejecucion/{id}/export` y `/reporte/disponibilidades` ahora aceptan filtros dinamicos por cualquier campo del resultado (ej: `?numero=2026040001&nombretercero=empresa`)
- **Busqueda global**: parametro `buscar` busca simultaneamente en todos los campos de texto (LIKE)
- **Paginacion**: parametros `per_page` y `pagina` para paginacion server-side; la respuesta incluye `total`, `paginas`, `pagina` y `per_page`
- **Ordenamiento**: parametros `orderby` y `order` (ASC/DESC) para ordenar por cualquier campo filtrable
- Los filtros tipo "exacto" usan `=` y los tipo "contiene" usan `LIKE %valor%`; ambos con `$wpdb->prepare()` para seguridad
- Las descargas CSV/TXT via AJAX tambien aceptan los filtros dinamicos y `buscar`
- Panel de Datos Abiertos actualizado con documentacion completa de filtros, tipos de busqueda y ejemplo de paginacion
- Cache transient se mantiene para consultas sin filtros (retrocompatible); consultas con filtros se ejecutan directamente

### 5.7.1
- **Menus**: Eliminado el submenu duplicado "Panel de Control" — el panel ahora abre al hacer clic en el menu principal "SYSMAN Suite" (`remove_submenu_page` del item autogenerado)
- **Menus**: El submenu "Ejecucion" se oculta del menu (pagina con parent `null`); sigue accesible por URL `admin.php?page=sysman-ejecucion` y enlazado desde "Datos Abiertos" con el boton "Gestionar seguimientos"
- **Seguridad**: Proteccion contra inyeccion de formulas CSV (valores que inician con `=`, `+`, `-`, `@`, tab o CR se prefijan con apostrofe) en exportaciones CSV y TXT
- **Seguridad**: Saneamiento de celdas TXT (colapsa tabs/saltos de linea para no romper la estructura del archivo)
- **Seguridad**: `Content-Disposition` con nombre de archivo saneado (`sanitize_file_name`) + `nocache_headers()`
- **Rendimiento/Seguridad**: Cache transient (5 min) en las consultas de exportacion y reporte DIS para mitigar carga de DB en endpoints publicos
- `@set_time_limit(300)` en las descargas para datasets grandes

### 5.7.0
- **Nuevo modulo "Datos Abiertos"**: pagina admin dedicada con generador interactivo de shortcodes, tablas de parametros y documentacion de endpoints API
- Los shortcodes `[gn_ejecucion_export]` y `[gn_reporte_dis]` se trasladan del modulo Ejecucion al nuevo modulo
- Nuevas tarjetas frontend con cabecera en degradado, copiar-al-portapapeles de la URL del API y pie institucional
- Metodo compartido `stream_download()` elimina la logica duplicada de CSV/TXT
- Assets propios: `datos-abiertos.css` y `datos-abiertos.js`

### 5.6.4
- **Nuevo shortcode `[gn_reporte_dis anio="" mes=""]`**: reporte de disponibilidades (DIS) cruzado con plan presupuestal
- Endpoint publico `GET /gn-sisman/v1/reporte/disponibilidades` con filtros opcionales `compania` y `dependencia`
- Descargas CSV/TXT y enlace al API JSON

### 5.6.3
- **Nueva tarjeta de exportacion** en la vista del seguimiento: descargas CSV/TXT y endpoint JSON publico `/ejecucion/{id}/export`
- Nuevo shortcode `[gn_ejecucion_export id="X"]`

### 5.6.2
- **Fix**: "Error al cargar datos." en multiples rubros — `Promise.all()` rechazaba todo si una sola llamada fallaba (p.ej. proyecto BPIN inexistente)
- Cada llamada (consolidado, DIS, proyecto) tiene su propio `.catch()`; el proyecto se omite en silencio si no existe, consolidado/DIS muestran error especifico

### 5.6.1
- **Fix**: error al sincronizar — timeout de PHP durante 4 llamadas secuenciales al API. Solucion: `set_time_limit(600)`, try/catch por importacion, timeout jQuery 600s y mensajes de error detallados
- Eliminadas las columnas "Aplazamiento" y "Desplazamiento" de la tabla consolidada en el frontend

### 5.6.0
- **Nueva pestana "Vistas"** en el modulo de graficos: JOIN entre `plan_presupuestal` y `ejecucion_gastos`
- Configuracion personalizada de etiquetas de tooltip (categoria, valor, serie)

### 5.5.2
- **Fix**: Multiples shortcodes `[gn_ejecucion]` en la misma pagina — solo el primero funcionaba
- Causa raiz: `document.querySelector('.gn-ejec')` solo seleccionaba la primera instancia; click handlers, postId y BPID sidebar quedaban ligados unicamente a ella
- Refactor: `document.querySelectorAll('.gn-ejec').forEach(initInstance)` — cada acordeon recibe su propio listener con su propio `postId`
- Funciones de renderizado (consolidado, DIS, RES, proyecto, contratos) son compartidas y stateless
- `selectBpid()` y `toggleRubro/Dis()` ahora reciben `root`/`postId` como parametro en vez de depender del closure global

### 5.5.1 (M — para pruebas)
- **Nueva funcionalidad**: Agrupacion por BPIN en modulo Ejecucion (configurable por seguimiento)
- Nuevo toggle "Agrupar por BPIN" en formulario de edicion del seguimiento
- Layout de dos columnas: panel lateral con lista de proyectos BPIN + acordeon de rubros filtrado
- Click en un proyecto BPIN muestra solo los rubros correspondientes
- Lookup de nombres de proyecto desde tabla `bpid_suite_contratos`
- Rubros sin BPIN asignado se agrupan como "Sin BPIN asignado"
- **Nueva funcionalidad**: Links a contratos SECOP en DIS y RES
- Cruce automatico de `auxiliar_cuentas.nrodocumento` con `secop_contracts.numero_de_proceso`
- Si hay coincidencia, el documento se muestra como enlace `<a>` a `secop_contracts.url_contrato`
- Batch lookup eficiente (una sola consulta IN por peticion REST)
- Deteccion segura de tabla `secop_contracts` (no falla si el plugin de contratacion no esta instalado)
- Responsive: en pantallas < 900px el layout cambia a una columna con sidebar horizontal

### 5.0.3
- **Fix critico**: Modulo de graficos no renderizaba — `ReferenceError: d3plus is not defined`
- Causa raiz: el CDN `https://d3plus.org/js/d3plus.v2.0.full.min.js` ahora devuelve **HTTP 404** (d3plus.org removio la ruta)
- Nuevo CDN por defecto: `https://cdn.jsdelivr.net/npm/d3plus@2.0.2/build/d3plus.full.min.js` (jsDelivr, verificado funcional)
- Actualizados los 3 puntos donde se carga d3plus: `Sysman_Suite::enqueue_admin_assets()`, `Visualizer::enqueue_frontend_assets()`, y `register_setting()` default
- Nueva migracion automatica `Sysman_Suite::migrate_options()`: si la opcion almacenada `sysman_d3plus_cdn_url` apunta a la URL rota de d3plus.org, se reemplaza automaticamente al jsDelivr CDN al cargar el admin
- Tambien actualizado el placeholder del campo D3Plus CDN en la pagina de Configuracion

### 5.0.2
- **Fix**: Vista principal del modulo Ejecucion desconfigurada — tabla con 8 columnas desbordaba el contenedor
- Eliminado `table-layout: fixed` (clase `fixed`) que forzaba anchos rigidos incompatibles con el espacio disponible
- Reducido de 8 a 7 columnas: Compania integrada como subtexto del titulo
- Envuelto tabla en contenedor con `overflow-x: auto` para scroll horizontal en pantallas pequenas
- Titulo del seguimiento ahora es enlace directo a la vista detallada
- Ajuste de anchos de columnas para distribucion equilibrada

### 5.0.1
- **HOTFIX**: Error fatal de `dbDelta()` indefinido en `Database::create_tables()` (faltaba `require_once upgrade.php` removido en v5.0.0)
- Refactor: Toda la DDL ahora vive en `Schema.php` como unica fuente de verdad — eliminada dependencia de `dbDelta()`
- Nuevos metodos `Schema::personal_nomina_sql()` y `Schema::ejecucion_ingresos_sql()`
- INSERTs defensivos: `filter_to_existing_columns()` filtra automaticamente claves de datos que no existen como columnas en la tabla (resiliencia ante migraciones incompletas)
- Manejo de errores: los INSERTs ya no abortan con `break` al primer error — usan `continue` para procesar todos los registros y reportar errores
- Refactor de `migrate_column_names()`: ahora idempotente y robusto contra null/empty
- Compatibilidad: `codigocuenta` ampliado a VARCHAR(255), `nombrerubro` a TEXT, `bpid` a VARCHAR(64) — coincide con esquema anterior, evita truncamiento
- Schema bump a 5.0.1 para forzar re-migracion en instalaciones donde v5.0.0 fallo a medias

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
