# Módulo "Ejecución" — sisman-suite

**Plan de implementación para Claude Code**
Repositorio: `https://github.com/GobernaciondeNarino/sisman-suite`
Convenciones: namespace `GobernacionNarino\`, prefijo de opciones `gn_`, `sslverify: false`, transients para caché, PSR-4, paleta institucional `#1a5276` / `#E8A020` / `#003087`.

---

## 0. Objetivo

Agregar al plugin `sisman-suite` un módulo llamado **"Ejecución"** que permita crear N seguimientos independientes ("Nuevo") y, dentro de cada uno, navegar mediante acordeones anidados la trazabilidad presupuestal completa de una dependencia: **Dependencia → Rubros → Ejecución consolidada → Disponibilidades (DIS) → Reservas (RES)**.

Toda la información se sirve desde tablas locales MySQL:

| Tabla local | Origen API | Contenido |
|-------------|-----------|-----------|
| `{prefix}sysman_plan_presupuestal` | `numinforme=4` | Catálogo de rubros / Plan Presupuestal |
| `{prefix}sysman_ejecucion_gastos` | `numinforme=1` **y** `numinforme=2&tipo_cpte=DIS` | Ejecución consolidada por rubro + Disponibilidades |
| `{prefix}sysman_auxiliar_cuentas` | `numinforme=2&tipo_cpte=RES` | Reservas / Compromisos (movimientos RES) |

Antes de implementar el módulo se debe **actualizar el schema de las tres tablas** para que contengan todos los campos retornados por las APIs SYSMAN, y se deben **resincronizar los datos** desde los endpoints oficiales.

---

## 1. Pre-requisitos — inspección del estado actual

**Antes de tocar código**, Claude Code debe ejecutar y reportar:

1. Estructura actual de las tablas:
   ```sql
   SHOW CREATE TABLE {prefix}sysman_plan_presupuestal;
   SHOW CREATE TABLE {prefix}sysman_ejecucion_gastos;
   SHOW CREATE TABLE {prefix}sysman_auxiliar_cuentas;
   ```
2. Listado de archivos PHP/JS/CSS existentes en `/includes/`, `/admin/`, `/public/`, `/assets/`.
3. Identificación del autoloader (PSR-4) y del bootstrap principal del plugin.
4. Identificación de los sincronizadores ya existentes (clases que invocan a `wp_remote_get` contra `narino-gob.sysman.com.co`).
5. Verificación de si existen ya un Custom Post Type, un menú de admin o shortcodes registrados por el plugin.

**No se debe iniciar la implementación hasta tener este inventario.** Reportarlo en consola/log antes de continuar.

---

## 2. Arquitectura del módulo

El módulo se compone de:

| Capa | Responsabilidad |
|------|-----------------|
| **Migration** | Actualiza/crea las **3 tablas**: `_sysman_plan_presupuestal`, `_sysman_ejecucion_gastos` y `_sysman_auxiliar_cuentas` con todos los campos de las APIs. |
| **Syncer** | Cuatro clases: `PlanPresupuestalSyncer` (→ plan_presupuestal), `EjecucionConsolidadaSyncer` (→ ejecucion_gastos), `MovimientosDisSyncer` (→ ejecucion_gastos), `MovimientosResSyncer` (→ **auxiliar_cuentas**). Usan los endpoints SYSMAN, almacenan en tablas locales, cachean con transients. |
| **CPT** | `gn_ejecucion` — un post por cada "Nuevo" del módulo. Guarda como meta la dependencia seleccionada y configuración de la vista. |
| **REST internos** | Endpoints WP REST API bajo `/wp-json/gn-sisman/v1/ejecucion/...` que sirven cada nivel del acordeón con carga lazy. |
| **Admin UI** | Página de admin con listado de seguimientos, botón "Nuevo", editor (selector de dependencia) y vista de acordeón. |
| **Frontend (opcional)** | Shortcode `[gn_ejecucion id="X"]` para embebido en páginas públicas. |

Toda comunicación entre el frontend (acordeones) y el servidor pasa por los endpoints REST internos. **Nunca se consulta la API SYSMAN en tiempo real desde el navegador**: el navegador solo habla con WordPress, que sirve desde tablas locales.

---

## 3. Fase 1 — Actualización de schemas

### 3.1 Schema esperado de `{prefix}_sysman_plan_presupuestal`

Fuente: `https://narino-gob.sysman.com.co/sysmanApi/autoservicio/v1/informesGobNar?compania=001&anio=2026&mes=5&numinforme=4`

| Columna | Tipo | Notas |
|---------|------|-------|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT PK | |
| `compania` | VARCHAR(8) | Índice |
| `anio` | SMALLINT UNSIGNED | Índice |
| `mes` | TINYINT UNSIGNED | Índice |
| `codigo` | VARCHAR(255) | **Índice (filtro principal)** — código completo del rubro |
| `nombre` | TEXT | |
| `destino` | VARCHAR(64) | Índice — FUNCIONAMIENTO / INVERSION / DEUDA |
| `naturaleza` | VARCHAR(64) | INGRESOS / GASTOS |
| `movimiento` | VARCHAR(8) | SI / NO |
| `tipovigencia` | VARCHAR(64) | |
| `sector` | VARCHAR(255) | Índice |
| `programa` | VARCHAR(255) | |
| `subPrograma` | VARCHAR(255) | |
| `codigoProducto` | VARCHAR(64) | |
| `codigoBPIN` | VARCHAR(64) | Índice |
| `codigoCCPET` | VARCHAR(64) | |
| `codigoCPCDANE` | VARCHAR(64) | |
| `codigoUnidadEjecutora` | VARCHAR(32) | |
| `codigoFuente` | VARCHAR(32) | |
| `codigoCCPETRegalias` | VARCHAR(64) | |
| `politicaPublica` | VARCHAR(255) | |
| `detalleSectorial` | VARCHAR(255) | |
| `tipoRecurso` | VARCHAR(64) | |
| `codigoSIA` | VARCHAR(64) | |
| `dependencia` | VARCHAR(32) | Índice |
| `nombreDependencia` | VARCHAR(255) | **Índice (filtro principal)** |
| `codigoEquiv` | VARCHAR(255) | |
| `synced_at` | DATETIME | Marca de la última sincronización |

**Índice compuesto sugerido:** `(compania, anio, mes, nombreDependencia, codigo)`.

### 3.2 Schema esperado de `{prefix}sysman_ejecucion_gastos`

Esta tabla **soporta dos formas de registro** porque dos endpoints distintos alimentan información relacionada:

- **Modo CONSOLIDADO** (`numinforme=1`): un registro por cuenta presupuestal, con apropiaciones y ejecución agregada.
- **Modo MOVIMIENTO DIS** (`numinforme=2&tipo_cpte=DIS`): un registro por Disponibilidad Presupuestal.

> **Las RES (Reservas) NO se almacenan aquí — van a `sysman_auxiliar_cuentas` (§3.3).**

Schema unificado propuesto con discriminador `record_type`:

| Columna | Tipo | Origen |
|---------|------|--------|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT PK | — |
| `record_type` | ENUM('CONSOLIDADO','DIS') | Discriminador |
| `compania` | VARCHAR(8) | Ambos |
| `anio` | SMALLINT UNSIGNED | Ambos |
| `mes` | TINYINT UNSIGNED | Ambos |
| **— Campos de numinforme=1 (CONSOLIDADO) —** | | |
| `codigocuenta` | VARCHAR(255) | **Índice — clave para join con plan_presupuestal.codigo** |
| `nombrerubro` | TEXT | |
| `movimiento` | VARCHAR(8) | |
| `destino` | VARCHAR(64) | |
| `bpid` | VARCHAR(64) | |
| `apropiacioninicial` | DECIMAL(20,2) | |
| `adicion` | DECIMAL(20,2) | |
| `reduccion` | DECIMAL(20,2) | |
| `credito` | DECIMAL(20,2) | |
| `contracredito` | DECIMAL(20,2) | |
| `aplazamiento` | DECIMAL(20,2) | |
| `desplazaminento` | DECIMAL(20,2) | (sic — tal cual viene del API) |
| `apropiacionvigente` | DECIMAL(20,2) | |
| `disponibilidades` | DECIMAL(20,2) | |
| `saldodisponible` | DECIMAL(20,2) | |
| `compromisos` | DECIMAL(20,2) | |
| `disponibilidadesabiertas` | DECIMAL(20,2) | |
| `obligacion` | DECIMAL(20,2) | |
| `pagos` | DECIMAL(20,2) | |
| `obligacionesporpagar` | DECIMAL(20,2) | |
| **— Campos de numinforme=2 tipo_cpte=DIS —** | | |
| `numero` | VARCHAR(32) | **Índice** — número de la disponibilidad |
| `nombrepred` | TEXT | |
| `idprede` | VARCHAR(64) | |
| `nombreplan` | TEXT | |
| `rubro` | VARCHAR(255) | **Índice — debe coincidir con codigocuenta para join** |
| `fecha` | DATE | Índice |
| `tipocpte` | VARCHAR(8) | Siempre `DIS` para registros con record_type='DIS' |
| `tercero` | VARCHAR(64) | |
| `nombretercero` | VARCHAR(255) | |
| `descripcion` | TEXT | |
| `nrodocumento` | VARCHAR(64) | |
| `valordebito` | DECIMAL(20,2) | |
| `valorcredito` | DECIMAL(20,2) | |
| `debitoafectado` | DECIMAL(20,2) | |
| `creditoafectado` | DECIMAL(20,2) | |
| `modificaciondebito` | DECIMAL(20,2) | |
| `modificacioncredito` | DECIMAL(20,2) | |
| `saldoporejecutaresp` | DECIMAL(20,2) | |
| `tipocpteafect` | VARCHAR(8) | |
| `cmpteafectado` | VARCHAR(32) | |
| `synced_at` | DATETIME | |

**Índices críticos:**
- `(record_type, codigocuenta)` — para nivel 3 (consolidado por rubro)
- `(record_type, rubro)` — para nivel 4 (DIS por rubro)
- `(numero)` — para que la cadena DIS→RES pueda resolver `numero` rápido

### 3.3 Schema esperado de `{prefix}sysman_auxiliar_cuentas` (RES)

Esta tabla almacena **exclusivamente los movimientos RES** (Reservas / Compromisos / Registros Presupuestales) provenientes del endpoint `numinforme=2&tipo_cpte=RES`. Es estructuralmente idéntica al bloque MOVIMIENTO de §3.2 pero vive separada porque corresponde al "auxiliar de cuentas" en la nomenclatura SYSMAN.

| Columna | Tipo | Notas |
|---------|------|-------|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT PK | |
| `compania` | VARCHAR(8) | Índice |
| `anio` | SMALLINT UNSIGNED | Índice |
| `mes` | TINYINT UNSIGNED | Índice |
| `numero` | VARCHAR(32) | **Índice** — número de la RES |
| `nombrepred` | TEXT | |
| `idprede` | VARCHAR(64) | |
| `nombreplan` | TEXT | |
| `rubro` | VARCHAR(255) | Índice — código de cuenta presupuestal |
| `fecha` | DATE | Índice |
| `tipocpte` | VARCHAR(8) | Siempre `RES` (validar al insertar) |
| `tercero` | VARCHAR(64) | |
| `nombretercero` | VARCHAR(255) | |
| `descripcion` | TEXT | |
| `nrodocumento` | VARCHAR(64) | |
| `valordebito` | DECIMAL(20,2) | |
| `valorcredito` | DECIMAL(20,2) | |
| `debitoafectado` | DECIMAL(20,2) | |
| `creditoafectado` | DECIMAL(20,2) | |
| `modificaciondebito` | DECIMAL(20,2) | |
| `modificacioncredito` | DECIMAL(20,2) | |
| `saldoporejecutaresp` | DECIMAL(20,2) | |
| `tipocpteafect` | VARCHAR(8) | Normalmente `DIS` |
| `cmpteafectado` | VARCHAR(32) | **🔑 Índice CRÍTICO — apunta al `numero` de la DIS afectada. Es la clave de cruce con `sysman_ejecucion_gastos.numero` para el nivel 5 del acordeón.** |
| `synced_at` | DATETIME | |

**Índices críticos para el rendimiento del nivel 5:**
- `(cmpteafectado)` — clave única para resolver "qué RES afectaron a esta DIS"
- `(compania, anio, mes, cmpteafectado)` — versión compuesta cuando se filtra por periodo

### 3.4 Migración con `dbDelta`

Crear `includes/Migration/Schema_v2.php` con:

```php
namespace GobernacionNarino\SismanSuite\Migration;

class Schema_v2 {
    const VERSION = '2.0.0';

    public static function run() {
        if ( get_option( 'gn_sisman_schema_version' ) === self::VERSION ) return;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        // 1) Plan Presupuestal (numinforme=4)
        $sql_pp = "CREATE TABLE {$wpdb->prefix}sysman_plan_presupuestal ( ... ) $charset;";
        dbDelta( $sql_pp );

        // 2) Ejecución Gastos (numinforme=1 + numinforme=2&tipo_cpte=DIS)
        $sql_eg = "CREATE TABLE {$wpdb->prefix}sysman_ejecucion_gastos ( ... ) $charset;";
        dbDelta( $sql_eg );

        // 3) Auxiliar Cuentas (numinforme=2&tipo_cpte=RES)
        $sql_ac = "CREATE TABLE {$wpdb->prefix}sysman_auxiliar_cuentas ( ... ) $charset;";
        dbDelta( $sql_ac );

        update_option( 'gn_sisman_schema_version', self::VERSION );
    }
}
```

Engancharlo en activación del plugin **y** en `admin_init` para upgrades automáticos. `dbDelta` es idempotente: añade columnas faltantes sin destruir datos existentes.

---

## 4. Fase 2 — Sincronizadores con la API SYSMAN

### 4.1 Endpoints (URL base: `https://narino-gob.sysman.com.co`)

| Syncer | URL completa | Tabla destino | Discriminador |
|--------|--------------|---------------|---------------|
| `PlanPresupuestalSyncer` | `/sysmanApi/autoservicio/v1/informesGobNar?compania=001&anio={anio}&mes={mes}&numinforme=4` | `sysman_plan_presupuestal` | — |
| `EjecucionConsolidadaSyncer` | `/sysmanApi/autoservicio/v1/informesGobNar?compania=001&anio={anio}&mes={mes}&numinforme=1` | `sysman_ejecucion_gastos` | `record_type='CONSOLIDADO'` |
| `MovimientosDisSyncer` | `/sysmanApi/autoservicio/v1/informesGobNar?compania=001&anio={anio}&mes={mes}&numinforme=2&tipo_cpte=DIS` | `sysman_ejecucion_gastos` | `record_type='DIS'` |
| `MovimientosResSyncer` | `/sysmanApi/autoservicio/v1/informesGobNar?compania=001&anio={anio}&mes={mes}&numinforme=2&tipo_cpte=RES` | **`sysman_auxiliar_cuentas`** | — (todos los registros son RES) |

> **OJO** — el parámetro de la URL es `tipo_cpte` (con guion bajo), pero el campo en la respuesta JSON es `tipocpte` (sin guion). Mantener esa diferencia en código.
>
> **OJO 2** — RES y DIS comparten estructura de columnas pero **viven en tablas distintas**. No mezclarlos. El cruce DIS↔RES se resuelve en el repositorio mediante un JOIN entre `sysman_ejecucion_gastos.numero` (donde `record_type='DIS'`) y `sysman_auxiliar_cuentas.cmpteafectado`.

### 4.2 Cliente HTTP base

```php
namespace GobernacionNarino\SismanSuite\Api;

class SysmanClient {
    const BASE = 'https://narino-gob.sysman.com.co/sysmanApi/autoservicio/v1';

    public function fetch( $endpoint, $args = [] ) {
        $url = add_query_arg( $args, self::BASE . $endpoint );
        $response = wp_remote_get( $url, [
            'timeout'   => 120,
            'sslverify' => false,
        ]);
        if ( is_wp_error( $response ) ) return $response;
        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) return new \WP_Error( 'sysman_http_'.$code, "HTTP $code" );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        return is_array( $body ) ? $body : new \WP_Error( 'sysman_json', 'JSON inválido' );
    }
}
```

### 4.3 Estrategia de sincronización

- Cada syncer expone:
  - `sync( $compania, $anio, $mes )` → trae todo el dataset, hace `TRUNCATE` + `INSERT` masivo (en batches de 500 con `wpdb->query` y `INSERT ... VALUES (...),(...),(...)`).
  - Marca `synced_at = NOW()` en todas las filas insertadas.
  - Actualiza opción `gn_sisman_last_sync_{nombre}` con timestamp.
- Las llaves duplicadas se evitan con `(compania, anio, mes, codigo|numero)` UNIQUE — Claude Code define la unicidad apropiada por tabla.
- **Cron WP-Cron diario** para sincronizar el periodo actual. Acción manual desde admin para sincronizar periodos específicos.
- Caché con transient `gn_sisman_pp_dependencias_{anio}_{mes}` (12 h) para el listado de dependencias únicas (alimenta el selector de la UI).
- Logs en `wp_options` con clave rotativa `gn_sisman_sync_log` (últimas 50 corridas).

---

## 5. Fase 3 — Backend del módulo

### 5.1 Estructura de archivos sugerida

```
sisman-suite/
├── includes/
│   ├── Module/Ejecucion/
│   │   ├── EjecucionModule.php          (bootstrap del módulo)
│   │   ├── PostType.php                 (registra CPT gn_ejecucion)
│   │   ├── RestController.php           (registra endpoints REST)
│   │   ├── Repository.php               (consultas SQL — incluye JOIN DIS↔RES)
│   │   └── AccordionRenderer.php        (HTML del acordeón)
│   ├── Api/
│   │   └── SysmanClient.php
│   ├── Sync/
│   │   ├── PlanPresupuestalSyncer.php       → sysman_plan_presupuestal
│   │   ├── EjecucionConsolidadaSyncer.php   → sysman_ejecucion_gastos (CONSOLIDADO)
│   │   ├── MovimientosDisSyncer.php         → sysman_ejecucion_gastos (DIS)
│   │   └── MovimientosResSyncer.php         → sysman_auxiliar_cuentas (RES)
│   └── Migration/
│       └── Schema_v2.php
├── admin/
│   ├── views/
│   │   ├── ejecucion-list.php
│   │   ├── ejecucion-edit.php
│   │   └── ejecucion-view.php
│   └── assets/
│       ├── js/ejecucion.js
│       └── css/ejecucion.css
└── sisman-suite.php (bootstrap)
```

### 5.2 Custom Post Type `gn_ejecucion`

Un post = un seguimiento. Metadatos:

| Meta key | Valor |
|----------|-------|
| `_gn_dependencia` | `nombreDependencia` seleccionada |
| `_gn_anio` | Año de referencia |
| `_gn_mes` | Mes de referencia |
| `_gn_compania` | `001` por defecto |
| `_gn_titulo_personalizado` | Título amigable (opcional) |

Capability: `manage_options` (o `gn_sisman_manage`, capability propia del plugin si existe).

### 5.3 Endpoints REST internos

Namespace: `gn-sisman/v1`. Todos requieren `current_user_can('edit_posts')` mínimo.

| Método | Ruta | Parámetros | Devuelve |
|--------|------|------------|----------|
| GET | `/dependencias` | `anio`, `mes` | Lista distinct de `nombreDependencia` desde `sysman_plan_presupuestal`. |
| GET | `/ejecucion/{post_id}/rubros` | — | Rubros del plan presupuestal de la dependencia del post. Tabla: `sysman_plan_presupuestal`. Campos: `codigo, nombre, destino, naturaleza, codigoBPIN, sector, programa, subPrograma, codigoProducto`. |
| GET | `/ejecucion/{post_id}/consolidado` | `codigo` (rubro) | 1 fila desde `sysman_ejecucion_gastos` con `record_type='CONSOLIDADO' AND codigocuenta=:codigo`. Campos: `apropiacioninicial, apropiacionvigente, disponibilidades, saldodisponible, compromisos`. |
| GET | `/ejecucion/{post_id}/dis` | `codigocuenta` | N filas desde `sysman_ejecucion_gastos` con `record_type='DIS' AND rubro=:codigocuenta`. Campos: `nombreplan, numero, nombretercero, valordebito, saldoporejecutaresp, fecha`. |
| GET | `/ejecucion/{post_id}/res` | `numero_dis` | N filas desde **`sysman_auxiliar_cuentas`** con `cmpteafectado=:numero_dis` (y opcionalmente `tipocpte='RES'` como salvaguarda). Campos: `numero, nombretercero, descripcion, nrodocumento, valordebito, saldoporejecutaresp, fecha`. |

Cada endpoint debe:
- Validar nonce REST (`X-WP-Nonce`).
- Sanitizar `codigo`, `codigocuenta`, `numero_dis` con `sanitize_text_field` y consultas preparadas (`$wpdb->prepare`).
- Cachear el resultado en transient por 5 minutos con clave `gn_sisman_ejec_{endpoint}_{md5(args)}`.
- Devolver `[]` (no error 404) si no hay resultados — el frontend lo mostrará como "Sin movimientos".

---

## 6. Fase 4 — Frontend (acordeones)

### 6.1 Lógica de cascada (resumen visual)

```
[ Nuevo Seguimiento ]
└─ Dependencia: SECRETARIA TIC INNOVACION Y GOBIERNO ABIERTO
   ├─ ▶ Rubro 2.3.2.01.01.003... — Adquisición de bienes (INVERSION) [BPIN 2024…]
   │  └─ (al expandir) Tabla consolidada:
   │     apropiacionInicial | apropiacionVigente | disponibilidades | saldoDisponible | compromisos
   │     └─ (al expandir) Lista de DIS:
   │        ▶ DIS 2026011242 — Tercero X — $ 12.000.000 — saldo: $ 0 — 15/01/2026
   │           └─ (al expandir) RES asociadas:
   │              · RES 2026011398 — Tercero X — "Contrato 045 de 2026"
   │                Tabla: nrodocumento | valordebito | saldoporejecutaresp | fecha
   │              · RES 2026011455 — Tercero Y — "Contrato 046 de 2026"
   │                Tabla: nrodocumento | valordebito | saldoporejecutaresp | fecha
   │        ▶ DIS 2026011243 — ...
   ├─ ▶ Rubro 2.3.2.02.02.006... — Servicios profesionales (INVERSION) [BPIN 2024…]
   │  └─ ...
   └─ ▶ Rubro ...
```

### 6.2 HTML base (renderizado por `AccordionRenderer`)

```html
<div class="gn-ejec" data-post-id="{POST_ID}" data-anio="{ANIO}" data-mes="{MES}">
  <header class="gn-ejec__header">
    <h2>Seguimiento — {DEPENDENCIA}</h2>
    <span>Periodo {MES}/{ANIO}</span>
  </header>

  <ul class="gn-ejec__rubros">
    <!-- 1 <li> por rubro, lazy-load del consolidado al expandir -->
    <li class="gn-ejec__rubro" data-codigo="{RUBRO_CODIGO}" aria-expanded="false">
      <button class="gn-ejec__rubro-toggle">
        <span class="codigo">{codigo}</span>
        <span class="nombre">{nombre}</span>
        <span class="meta">{destino} · {naturaleza} · BPIN {codigoBPIN}</span>
      </button>
      <div class="gn-ejec__rubro-body" hidden>
        <!-- inyectado por JS al expandir: tabla consolidada + lista DIS -->
      </div>
    </li>
  </ul>
</div>
```

### 6.3 JavaScript (carga lazy por nivel)

`admin/assets/js/ejecucion.js` (Vanilla JS — sin dependencias):

```js
(function(){
  const root = document.querySelector('.gn-ejec');
  if(!root) return;
  const postId = root.dataset.postId;
  const nonce  = wpApiSettings.nonce;

  const api = (path, params={}) => {
    const url = new URL(`${wpApiSettings.root}gn-sisman/v1${path}`);
    Object.entries(params).forEach(([k,v]) => url.searchParams.set(k,v));
    return fetch(url, { headers: {'X-WP-Nonce': nonce} }).then(r => r.json());
  };

  // 1) Carga inicial: rubros (server-side ya los renderizó). JS solo enlaza toggles.
  root.addEventListener('click', async (e) => {
    const rubroBtn = e.target.closest('.gn-ejec__rubro-toggle');
    if (rubroBtn) return toggleRubro(rubroBtn.parentElement);

    const disBtn = e.target.closest('.gn-ejec__dis-toggle');
    if (disBtn) return toggleDis(disBtn.parentElement);
  });

  async function toggleRubro(li) {
    const expanded = li.getAttribute('aria-expanded') === 'true';
    li.setAttribute('aria-expanded', !expanded);
    const body = li.querySelector('.gn-ejec__rubro-body');
    body.hidden = expanded;
    if (expanded || li.dataset.loaded) return;
    body.innerHTML = '<p>Cargando...</p>';

    const codigo = li.dataset.codigo;
    const [consolidado, disList] = await Promise.all([
      api(`/ejecucion/${postId}/consolidado`, {codigo}),
      api(`/ejecucion/${postId}/dis`,         {codigocuenta: codigo}),
    ]);
    body.innerHTML = renderConsolidado(consolidado) + renderDisList(disList);
    li.dataset.loaded = '1';
  }

  async function toggleDis(li) {
    const expanded = li.getAttribute('aria-expanded') === 'true';
    li.setAttribute('aria-expanded', !expanded);
    const body = li.querySelector('.gn-ejec__dis-body');
    body.hidden = expanded;
    if (expanded || li.dataset.loaded) return;
    body.innerHTML = '<p>Cargando...</p>';

    const numeroDis = li.dataset.numero;
    const resList = await api(`/ejecucion/${postId}/res`, {numero_dis: numeroDis});
    body.innerHTML = renderResList(resList);
    li.dataset.loaded = '1';
  }

  // renderConsolidado, renderDisList, renderResList: helpers que retornan HTML
  // — formato moneda con Intl.NumberFormat('es-CO', { style:'currency', currency:'COP' })
  // — fechas DD/MM/YYYY ya vienen así desde el API
})();
```

Encolar con dependencia `wp-api` y localizar `wpApiSettings` (WordPress lo expone automáticamente al encolar `wp-api`).

### 6.4 CSS (paleta institucional)

```css
.gn-ejec { font-family: system-ui, sans-serif; color:#1f2937; }
.gn-ejec__header { background:#1a5276; color:#fff; padding:1rem; border-radius:6px 6px 0 0; }
.gn-ejec__rubro { border:1px solid #e5e7eb; border-top:none; }
.gn-ejec__rubro-toggle {
  width:100%; text-align:left; background:#fff; padding:.85rem 1rem;
  display:grid; grid-template-columns: 200px 1fr auto; gap:1rem;
  cursor:pointer; border:none; border-left:4px solid transparent;
}
.gn-ejec__rubro[aria-expanded="true"] .gn-ejec__rubro-toggle {
  background:#f8fafc; border-left-color:#E8A020;
}
.gn-ejec__rubro .codigo { font-family: ui-monospace, Consolas; font-size:.85rem; color:#003087; }
.gn-ejec__rubro .nombre { font-weight:600; }
.gn-ejec__rubro .meta   { font-size:.8rem; color:#6b7280; }
.gn-ejec__rubro-body { padding:1rem; background:#fafbfc; }
.gn-ejec table { width:100%; border-collapse:collapse; margin:.5rem 0; }
.gn-ejec th { background:#1a5276; color:#fff; padding:.5rem; text-align:left; font-size:.85rem; }
.gn-ejec td { padding:.45rem .5rem; border-bottom:1px solid #e5e7eb; font-size:.85rem; }
.gn-ejec td.num { text-align:right; font-variant-numeric: tabular-nums; }
```

---

## 7. Fase 5 — Admin UI

Menú: `Sisman Suite → Ejecución` (subpágina).

- **Vista lista** (`ejecucion-list.php`): listado de posts `gn_ejecucion` con columnas: Título, Dependencia, Periodo, Última actualización, Acciones (Ver / Editar / Eliminar). Botón superior **"Nuevo seguimiento"**.
- **Vista edición** (`ejecucion-edit.php`): formulario con:
  - Título personalizado
  - Año (select, últimos 5 años + actual)
  - Mes (select 1–12)
  - **Dependencia** (select alimentado por `/dependencias?anio=X&mes=Y` — se actualiza al cambiar año/mes)
  - Botón "Guardar"
  - Botón "Sincronizar datos ahora" → dispara los **4 syncers** para ese (anio, mes): `PlanPresupuestal`, `EjecucionConsolidada`, `MovimientosDis`, `MovimientosRes`.
- **Vista detalle** (`ejecucion-view.php`): renderiza el acordeón a través de `AccordionRenderer::render($post_id)`.

Encolar JS y CSS solo en estas tres pantallas usando `get_current_screen()`.

---

## 8. Fase 6 — Pruebas y aceptación

Claude Code debe verificar y reportar:

1. **Migración**: las **3 tablas** (`sysman_plan_presupuestal`, `sysman_ejecucion_gastos`, `sysman_auxiliar_cuentas`) creadas/actualizadas con todas las columnas listadas en §3. `DESCRIBE` de las tres tablas en el log.
2. **Sincronización**: los **4 syncers** ejecutan sin errores contra sus endpoints. Conteo de filas insertadas reportado por syncer y por tabla destino.
3. **Endpoints REST**: cada uno responde `200 OK` con datos esperados. Probar con curl + nonce. Verificar especialmente que `/res` consulta `sysman_auxiliar_cuentas` y no `sysman_ejecucion_gastos`.
4. **Flujo completo del acordeón** con un caso real:
   - Crear "Nuevo" → seleccionar **SECRETARIA TIC INNOVACION Y GOBIERNO ABIERTO**.
   - Verificar que aparecen rubros de esa dependencia.
   - Expandir un rubro → ver tabla consolidada con valores monetarios.
   - Expandir DIS → ver lista de disponibilidades.
   - Expandir una DIS → ver RES asociadas.
5. **Performance**: cada expansión debe responder en < 500 ms (carga lazy + caché transient).
6. **Compatibilidad**: el plugin no rompe módulos existentes de `sisman-suite` (BPID, etc.).
7. **Logs limpios**: sin warnings/notices PHP en `WP_DEBUG = true`.

---

## 9. Convenciones del plugin (recordatorio obligatorio)

- Namespace raíz: `GobernacionNarino\SismanSuite\`
- Prefijo de opciones / transients / meta: `gn_sisman_` (o `gn_` para meta cortos)
- Prefijo de tablas: `{$wpdb->prefix}sysman_` (sin doble prefijo)
- `wp_remote_get`: siempre con `'sslverify' => false` (entorno institucional con certificados internos)
- Preferir `Singleton` para servicios (`SysmanClient`, syncers, repository)
- Toda consulta SQL con `$wpdb->prepare`
- Toda salida HTML con `esc_html`, `esc_attr`, `wp_kses_post`
- Encolado de assets con versión basada en `filemtime` para evitar caché del navegador
- Internacionalización: text-domain `sisman-suite` en todas las cadenas visibles
- Paleta: `#1a5276` (azul institucional), `#003087` (azul oscuro), `#E8A020` (dorado)

---

## 10. Notas / dudas pendientes para Jonnathan

Antes del paso a producción, confirmar con Jonnathan:

1. **Nivel 5 — clave de cruce DIS↔RES (confirmar literal)**: el plan implementa el cruce estándar del flujo presupuestal colombiano: las RES en `sysman_auxiliar_cuentas` cuyo campo `cmpteafectado` coincide con el `numero` de la DIS expandida en el nivel 4. Esto resuelve "qué reservas afectaron esta disponibilidad". Si la regla de negocio esperada es distinta (por ejemplo, cruzar por `rubro` de la RES en lugar de por `cmpteafectado`), ajustar únicamente el `WHERE` del endpoint `/res` en `RestController.php`. La separación de tablas (DIS en `sysman_ejecucion_gastos` vs RES en `sysman_auxiliar_cuentas`) ya está confirmada por Jonnathan y no debe modificarse.
2. **Validación de tablas existentes**: en la Fase 1 (inspección), si Claude Code encuentra que `sysman_auxiliar_cuentas` ya existe con columnas adicionales (por ejemplo, OBL, EGR, NOM u otros tipos de comprobante distintos de RES), **no eliminar esas columnas** — solo agregar las faltantes y filtrar por `tipocpte='RES'` en las consultas del módulo Ejecución.
3. **Compañía**: actualmente fijada en `001`. Si se requiere multi-compañía, agregar selector y persistir en meta del CPT.
4. **Periodo histórico**: la sincronización actual reemplaza datos por (compania, anio, mes) vía `TRUNCATE` segmentado. Si se necesita histórico acumulado mes a mes, el modelo ya lo permite — cambiar a `INSERT ... ON DUPLICATE KEY UPDATE` con UNIQUE compuesto.
5. **Otros tipos de comprobante**: si en una versión futura se requiere visualizar OBL (obligaciones) o EGR (egresos/pagos) como nivel 6 del acordeón, ya hay arquitectura preparada — agregar `MovimientosOblSyncer` y `MovimientosEgrSyncer` siguiendo el mismo patrón, decidiendo si van a `sysman_auxiliar_cuentas` o a una nueva tabla.

---

---

**Fin del plan. Claude Code debe ejecutar las fases en orden, validando aceptación de cada una antes de avanzar a la siguiente. La tabla _sysman_plan_presupuestal es importante recostruirla en su totalidad**



