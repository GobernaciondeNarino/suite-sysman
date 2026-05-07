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
| `{prefix}sysman_ejecucion_gastos` | `numinforme=1` | Ejecución consolidada por rubro (apropiaciones, compromisos, pagos) |
| `{prefix}sysman_auxiliar_cuentas` | `numinforme=2` (todos los `tipo_cpte`) | Movimientos / comprobantes — DIS, RES, OBL, EGR, etc. Se discriminan por el campo `tipocpte` ya presente en cada registro. |

> **Importante:** los movimientos DIS y RES viven en la **misma tabla** (`sysman_auxiliar_cuentas`) y vienen del **mismo endpoint** (`numinforme=2`). Lo único que cambia entre ambos es el parámetro de URL `tipo_cpte` al sincronizar (filtra qué traer del API) y el filtro `WHERE tipocpte='...'` al consultar.

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
| **Migration** | Crea/actualiza las **3 tablas**: `sysman_plan_presupuestal`, `sysman_ejecucion_gastos` y `sysman_auxiliar_cuentas` con todos los campos de las APIs. |
| **Syncer** | Tres clases: `PlanPresupuestalSyncer` (→ plan_presupuestal), `EjecucionConsolidadaSyncer` (→ ejecucion_gastos), `MovimientosSyncer` (→ **auxiliar_cuentas**, parametrizable por `tipo_cpte`: DIS, RES, etc.). Usan los endpoints SYSMAN, almacenan en tablas locales, cachean con transients. |
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

Esta tabla almacena **exclusivamente la ejecución consolidada por rubro** proveniente del endpoint `numinforme=1`. Un registro por cuenta presupuestal con apropiaciones, modificaciones y ejecución agregada al periodo (mes/año).

> **Los movimientos (DIS, RES, etc.) NO viven aquí — van todos a `sysman_auxiliar_cuentas` (§3.3).**

| Columna | Tipo | Notas |
|---------|------|-------|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT PK | |
| `compania` | VARCHAR(8) | Índice |
| `anio` | SMALLINT UNSIGNED | Índice |
| `mes` | TINYINT UNSIGNED | Índice |
| `codigocuenta` | VARCHAR(255) | **🔑 Índice — clave para join con `plan_presupuestal.codigo`** |
| `nombrerubro` | TEXT | |
| `movimiento` | VARCHAR(8) | SI / NO |
| `destino` | VARCHAR(64) | FUNCIONAMIENTO / INVERSION / DEUDA |
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
| `synced_at` | DATETIME | |

**Índice único compuesto sugerido:** `(compania, anio, mes, codigocuenta)` — garantiza idempotencia en la sincronización.

### 3.3 Schema esperado de `{prefix}sysman_auxiliar_cuentas`

Esta tabla almacena **todos los movimientos / comprobantes** provenientes del endpoint `numinforme=2`, sin importar su tipo. El discriminador es el campo `tipocpte` que ya viene en cada registro del API: `DIS` (Disponibilidades), `RES` (Reservas / Compromisos), y eventualmente `OBL` (Obligaciones), `EGR` (Egresos / Pagos), etc.

**El módulo Ejecución usa esta misma tabla para los niveles 4 y 5 del acordeón:**
- Nivel 4 (Disponibilidades): `WHERE tipocpte='DIS' AND rubro=:codigocuenta`
- Nivel 5 (Reservas): `WHERE tipocpte='RES' AND cmpteafectado=:numero_dis`

| Columna | Tipo | Notas |
|---------|------|-------|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT PK | |
| `compania` | VARCHAR(8) | Índice |
| `anio` | SMALLINT UNSIGNED | Índice |
| `mes` | TINYINT UNSIGNED | Índice |
| `numero` | VARCHAR(32) | **🔑 Índice — número del comprobante (ej. `2026011242`)** |
| `nombrepred` | TEXT | |
| `idprede` | VARCHAR(64) | |
| `nombreplan` | TEXT | |
| `rubro` | VARCHAR(255) | **🔑 Índice — código de cuenta presupuestal; se cruza con `ejecucion_gastos.codigocuenta` en el nivel 4** |
| `fecha` | DATE | Índice |
| `tipocpte` | VARCHAR(8) | **🔑 Índice — DIS / RES / OBL / EGR. Discriminador principal de la tabla** |
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
| `tipocpteafect` | VARCHAR(8) | Tipo del comprobante afectado (ej. una RES afecta a una DIS) |
| `cmpteafectado` | VARCHAR(32) | **🔑 Índice CRÍTICO — apunta al `numero` del comprobante afectado. En las RES vale el `numero` de la DIS que están afectando. Es la clave de cruce del nivel 5 del acordeón.** |
| `synced_at` | DATETIME | |

**Índices críticos para el rendimiento del acordeón:**
- `(tipocpte, rubro)` — para resolver rápidamente el nivel 4 (DIS por rubro)
- `(tipocpte, cmpteafectado)` — para resolver rápidamente el nivel 5 (RES que afectan a una DIS)
- `(compania, anio, mes, tipocpte, numero)` UNIQUE — garantiza idempotencia en la sincronización

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

| Syncer | Tabla destino | URL completa |
|--------|---------------|--------------|
| `PlanPresupuestalSyncer` | `sysman_plan_presupuestal` | `/sysmanApi/autoservicio/v1/informesGobNar?compania=001&anio={anio}&mes={mes}&numinforme=4` |
| `EjecucionConsolidadaSyncer` | `sysman_ejecucion_gastos` | `/sysmanApi/autoservicio/v1/informesGobNar?compania=001&anio={anio}&mes={mes}&numinforme=1` |
| `MovimientosSyncer` (DIS) | `sysman_auxiliar_cuentas` | `/sysmanApi/autoservicio/v1/informesGobNar?compania=001&anio={anio}&mes={mes}&numinforme=2&tipo_cpte=DIS` |
| `MovimientosSyncer` (RES) | `sysman_auxiliar_cuentas` | `/sysmanApi/autoservicio/v1/informesGobNar?compania=001&anio={anio}&mes={mes}&numinforme=2&tipo_cpte=RES` |

`MovimientosSyncer` es **una sola clase** con un método parametrizable:

```php
public function sync( $compania, $anio, $mes, $tipoCpte ) {
    // tipoCpte = 'DIS' | 'RES' | 'OBL' | 'EGR' ...
    // 1. Llama al API con &tipo_cpte={$tipoCpte}
    // 2. Borra solo los registros del periodo y tipo: 
    //    DELETE FROM auxiliar_cuentas WHERE compania=? AND anio=? AND mes=? AND tipocpte=?
    // 3. Inserta en lotes de 500
}
```

Esto evita duplicar código y deja la puerta abierta para futuros tipos (OBL, EGR, etc.) sin cambios estructurales.

> **OJO 1** — el parámetro de la URL es `tipo_cpte` (con guion bajo), pero el campo en la respuesta JSON es `tipocpte` (sin guion). Mantener esa diferencia en código.
>
> **OJO 2** — DIS y RES viven en la **misma tabla** `sysman_auxiliar_cuentas`, diferenciados por la columna `tipocpte`. **Toda consulta que las distinga debe filtrar por `WHERE tipocpte='DIS'` o `WHERE tipocpte='RES'`** según corresponda. El cruce DIS↔RES se resuelve dentro de la misma tabla mediante `auto-join`: `auxiliar_cuentas AS dis JOIN auxiliar_cuentas AS res ON res.cmpteafectado = dis.numero AND dis.tipocpte='DIS' AND res.tipocpte='RES'`.

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
│   │   ├── Repository.php               (consultas SQL — incluye auto-join DIS↔RES)
│   │   └── AccordionRenderer.php        (HTML del acordeón)
│   ├── Api/
│   │   └── SysmanClient.php
│   ├── Sync/
│   │   ├── PlanPresupuestalSyncer.php       → sysman_plan_presupuestal
│   │   ├── EjecucionConsolidadaSyncer.php   → sysman_ejecucion_gastos
│   │   └── MovimientosSyncer.php            → sysman_auxiliar_cuentas (parametrizable: DIS, RES, ...)
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
| GET | `/ejecucion/{post_id}/consolidado` | `codigo` (rubro) | 1 fila desde `sysman_ejecucion_gastos` con `codigocuenta=:codigo`. Campos: `apropiacioninicial, apropiacionvigente, disponibilidades, saldodisponible, compromisos`. |
| GET | `/ejecucion/{post_id}/dis` | `codigocuenta` | N filas desde **`sysman_auxiliar_cuentas`** con `tipocpte='DIS' AND rubro=:codigocuenta`. Campos: `nombreplan, numero, nombretercero, valordebito, saldoporejecutaresp, fecha`. |
| GET | `/ejecucion/{post_id}/res` | `numero_dis` | N filas desde **`sysman_auxiliar_cuentas`** con `tipocpte='RES' AND cmpteafectado=:numero_dis`. Campos: `numero, nombretercero, descripcion, nrodocumento, valordebito, saldoporejecutaresp, fecha`. |

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
  - Botón "Sincronizar datos ahora" → ejecuta secuencialmente: `PlanPresupuestalSyncer::sync()`, `EjecucionConsolidadaSyncer::sync()`, `MovimientosSyncer::sync(... 'DIS')` y `MovimientosSyncer::sync(... 'RES')`. (3 clases, 4 llamadas).
- **Vista detalle** (`ejecucion-view.php`): renderiza el acordeón a través de `AccordionRenderer::render($post_id)`.

Encolar JS y CSS solo en estas tres pantallas usando `get_current_screen()`.

---

## 8. Fase 6 — Pruebas y aceptación

Claude Code debe verificar y reportar:

1. **Migración**: las **3 tablas** (`sysman_plan_presupuestal`, `sysman_ejecucion_gastos`, `sysman_auxiliar_cuentas`) creadas/actualizadas con todas las columnas listadas en §3. `DESCRIBE` de las tres tablas en el log.
2. **Sincronización**: los **3 syncers** corren sin errores (con `MovimientosSyncer` ejecutado dos veces: una para `DIS` y otra para `RES`). Conteo de filas insertadas reportado por syncer/tipo. Verificar con `SELECT tipocpte, COUNT(*) FROM {prefix}sysman_auxiliar_cuentas GROUP BY tipocpte` que aparezcan **al menos** los tipos `DIS` y `RES`.
3. **Endpoints REST**: cada uno responde `200 OK` con datos esperados. Probar con curl + nonce. **Verificar especialmente que tanto `/dis` como `/res` consultan `sysman_auxiliar_cuentas`** (no `sysman_ejecucion_gastos`), filtrando cada uno por `tipocpte` correspondiente.
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

1. **Nivel 5 — clave de cruce DIS↔RES (confirmar literal)**: el plan implementa el cruce estándar del flujo presupuestal colombiano: las RES (registros con `tipocpte='RES'` en `sysman_auxiliar_cuentas`) cuyo campo `cmpteafectado` coincide con el `numero` de la DIS expandida en el nivel 4. Esto resuelve "qué reservas afectaron esta disponibilidad". Si la regla de negocio esperada es distinta (por ejemplo, cruzar por `rubro` en lugar de por `cmpteafectado`), ajustar únicamente el `WHERE` del endpoint `/res` en `RestController.php`. La arquitectura de tablas (DIS y RES en la misma `sysman_auxiliar_cuentas`, discriminadas por `tipocpte`) ya está confirmada por Jonnathan y no debe modificarse.
2. **Validación de tablas existentes**: en la Fase 1 (inspección), si Claude Code encuentra que `sysman_auxiliar_cuentas` ya contiene otros tipos de comprobante (OBL, EGR, NOM, etc.) además de DIS y RES, **no eliminarlos** — el módulo Ejecución filtra explícitamente por `tipocpte IN ('DIS','RES')` y respeta el resto de los datos.
3. **Compañía**: actualmente fijada en `001`. Si se requiere multi-compañía, agregar selector y persistir en meta del CPT.
4. **Periodo histórico**: la sincronización borra los registros del periodo y tipo antes de insertar (`DELETE WHERE compania=? AND anio=? AND mes=? AND tipocpte=?`). Esto garantiza idempotencia sin perder histórico de otros meses. Si en algún caso se necesita preservar todo el histórico crudo, cambiar a `INSERT ... ON DUPLICATE KEY UPDATE` con UNIQUE compuesto.
5. **Otros tipos de comprobante**: si en una versión futura se requiere visualizar OBL (obligaciones) o EGR (egresos/pagos) como nivel 6 del acordeón, **no se necesitan tablas ni syncers nuevos** — basta con invocar `MovimientosSyncer::sync(..., 'OBL')` o `'EGR'` y agregar los endpoints REST correspondientes.

---

**Fin del plan. Claude Code debe ejecutar las fases en orden, validando aceptación de cada una antes de avanzar a la siguiente.**
