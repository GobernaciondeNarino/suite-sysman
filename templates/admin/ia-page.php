<?php
/**
 * Página de ajustes: Inteligencia artificial.
 *
 * Variables: $url_consulta, $secreto, $temas, $nonce (desde IAModule::render_admin).
 */
use SysmanSuite\IA\Config;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$proveedor = Config::proveedor();

/**
 * Campo de clave: nunca se muestra; vacío conserva la guardada.
 */
$campo_clave = static function ( string $servicio, string $opcion, string $id ): void {
    $en_constante = Config::clave_en_constante( $servicio );
    $mascara      = Config::clave_enmascarada( $servicio );
    ?>
    <input type="password" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $opcion ); ?>"
           value="" class="regular-text" autocomplete="new-password" spellcheck="false"
           placeholder="<?php echo esc_attr( $mascara ? sprintf( __( 'Guardada (%s)', 'sysman-suite' ), $mascara ) : __( 'Pegue aquí la clave', 'sysman-suite' ) ); ?>"
           <?php disabled( $en_constante ); ?>>
    <p class="description">
        <?php
        if ( $en_constante ) {
            printf(
                /* translators: %s: constant name */
                esc_html__( 'Definida en wp-config.php (%s): el panel no la modifica.', 'sysman-suite' ),
                '<code>' . esc_html( Config::CLAVES[ $servicio ][0] ) . '</code>'
            );
        } else {
            printf(
                /* translators: %s: constant name */
                esc_html__( 'Déjelo vacío para conservar la clave guardada; escriba «borrar» para eliminarla. Recomendado: definirla en wp-config.php como %s, así no queda en la base de datos.', 'sysman-suite' ),
                '<code>' . esc_html( Config::CLAVES[ $servicio ][0] ) . '</code>'
            );
        }
        ?>
    </p>
    <?php
};
?>
<div class="wrap sysman-admin-wrap">

    <div class="sysman-page-header">
        <div class="sysman-page-header-content">
            <div>
                <h1 class="sysman-page-title"><?php esc_html_e( 'Inteligencia artificial', 'sysman-suite' ); ?></h1>
                <p class="sysman-page-subtitle"><?php esc_html_e( 'Redacción de análisis con Claude o ChatGPT y asistente de voz con ElevenLabs', 'sysman-suite' ); ?></p>
            </div>
        </div>
        <div class="sysman-header-actions">
            <span class="sysman-version-badge">v<?php echo esc_html( SYSMAN_SUITE_VERSION ); ?></span>
        </div>
    </div>

    <form method="post" action="options.php">
        <?php settings_fields( 'sysman_ia' ); ?>

        <!-- Redacción de análisis -->
        <div class="sysman-card">
            <div class="sysman-card-header">
                <h2>
                    <span class="dashicons dashicons-edit-page" aria-hidden="true"></span>
                    <?php esc_html_e( 'Análisis de datos con IA', 'sysman-suite' ); ?>
                </h2>
            </div>
            <div class="sysman-card-body">
                <p class="description">
                    <?php esc_html_e( 'Con ia="si" en los shortcodes de análisis (por ejemplo [sysman_gastos_analisis ia="si"]), el texto por reglas se muestra de inmediato y se reemplaza por una redacción más natural hecha por el modelo. El modelo no consulta la base de datos: recibe las cifras ya calculadas y el texto por reglas, y el plugin verifica que cada importe y porcentaje del resultado esté en esos datos. Si aparece una cifra distinta, si el proveedor falla o se alcanza el tope por hora, se publica el texto por reglas.', 'sysman-suite' ); ?>
                </p>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="sysman_ia_proveedor"><?php esc_html_e( 'Proveedor', 'sysman-suite' ); ?></label></th>
                        <td>
                            <select id="sysman_ia_proveedor" name="sysman_ia_proveedor">
                                <?php foreach ( Config::PROVEEDORES as $k => $label ) : ?>
                                    <option value="<?php echo esc_attr( $k ); ?>" <?php selected( $proveedor, $k ); ?>><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sysman_ia_anthropic_key"><?php esc_html_e( 'Clave de API de Claude', 'sysman-suite' ); ?></label></th>
                        <td><?php $campo_clave( 'anthropic', 'sysman_ia_anthropic_key', 'sysman_ia_anthropic_key' ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sysman_ia_anthropic_modelo"><?php esc_html_e( 'Modelo de Claude', 'sysman-suite' ); ?></label></th>
                        <td>
                            <select id="sysman_ia_anthropic_modelo" name="sysman_ia_anthropic_modelo">
                                <?php foreach ( Config::MODELOS_CLAUDE as $k => $label ) : ?>
                                    <option value="<?php echo esc_attr( $k ); ?>" <?php selected( Config::modelo( 'anthropic' ), $k ); ?>><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select id="sysman_ia_esfuerzo" name="sysman_ia_esfuerzo" aria-label="<?php esc_attr_e( 'Esfuerzo de razonamiento', 'sysman-suite' ); ?>">
                                <?php foreach ( Config::ESFUERZOS as $k => $label ) : ?>
                                    <option value="<?php echo esc_attr( $k ); ?>" <?php selected( Config::esfuerzo(), $k ); ?>><?php echo esc_html( sprintf( __( 'Esfuerzo: %s', 'sysman-suite' ), $label ) ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e( 'Más esfuerzo: mejor redacción, más tiempo y costo por texto. Como cada texto se reutiliza mientras los datos no cambien, «Medio» suele bastar. Si Claude declina una solicitud, la API la reintenta automáticamente con el modelo alternativo recomendado.', 'sysman-suite' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sysman_ia_openai_key"><?php esc_html_e( 'Clave de API de OpenAI', 'sysman-suite' ); ?></label></th>
                        <td><?php $campo_clave( 'openai', 'sysman_ia_openai_key', 'sysman_ia_openai_key' ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sysman_ia_openai_modelo"><?php esc_html_e( 'Modelo de OpenAI', 'sysman-suite' ); ?></label></th>
                        <td>
                            <input type="text" id="sysman_ia_openai_modelo" name="sysman_ia_openai_modelo" class="regular-text"
                                   value="<?php echo esc_attr( Config::modelo( 'openai' ) ); ?>" placeholder="<?php echo esc_attr( Config::MODELO_OPENAI_DEFECTO ); ?>">
                            <p class="description"><?php esc_html_e( 'Identificador del modelo tal como lo publica OpenAI.', 'sysman-suite' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sysman_ia_cache_horas"><?php esc_html_e( 'Reutilizar cada texto', 'sysman-suite' ); ?></label></th>
                        <td>
                            <input type="number" id="sysman_ia_cache_horas" name="sysman_ia_cache_horas" min="1" max="720" class="small-text"
                                   value="<?php echo esc_attr( Config::horas_cache() ); ?>"> <?php esc_html_e( 'horas', 'sysman-suite' ); ?>
                            &nbsp;&nbsp;
                            <label for="sysman_ia_limite_hora"><?php esc_html_e( 'Máximo de textos nuevos por hora:', 'sysman-suite' ); ?></label>
                            <input type="number" id="sysman_ia_limite_hora" name="sysman_ia_limite_hora" min="0" max="1000" class="small-text"
                                   value="<?php echo esc_attr( Config::limite_hora() ); ?>">
                            <p class="description"><?php esc_html_e( 'El tope protege el costo: los visitantes no pueden provocar llamadas ilimitadas al proveedor. Con 0 no se redacta ningún texto nuevo.', 'sysman-suite' ); ?></p>
                        </td>
                    </tr>
                </table>
                <p>
                    <button type="button" class="button" id="sysman-ia-probar"><?php esc_html_e( 'Probar conexión', 'sysman-suite' ); ?></button>
                    <span id="sysman-ia-probar-resultado" aria-live="polite"></span>
                </p>
            </div>
        </div>

        <!-- ElevenLabs -->
        <div class="sysman-card">
            <div class="sysman-card-header">
                <h2>
                    <span class="dashicons dashicons-microphone" aria-hidden="true"></span>
                    <?php esc_html_e( 'Asistente de voz para la ciudadanía (ElevenLabs)', 'sysman-suite' ); ?>
                </h2>
            </div>
            <div class="sysman-card-body">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="sysman_elevenlabs_agent_id"><?php esc_html_e( 'ID del agente', 'sysman-suite' ); ?></label></th>
                        <td>
                            <input type="text" id="sysman_elevenlabs_agent_id" name="sysman_elevenlabs_agent_id" class="regular-text"
                                   value="<?php echo esc_attr( Config::agente_id() ); ?>" spellcheck="false">
                            <p class="description"><?php esc_html_e( 'En ElevenLabs → Agents → su agente → Widget.', 'sysman-suite' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Agente privado', 'sysman-suite' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="sysman_elevenlabs_privado" value="1" <?php checked( (bool) get_option( 'sysman_elevenlabs_privado', false ) ); ?>>
                                <?php esc_html_e( 'El agente exige autenticación: el sitio pide una URL firmada a ElevenLabs en cada visita (la clave nunca llega al navegador).', 'sysman-suite' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'Recomendado: así nadie puede usar su agente —y su saldo— desde otro sitio.', 'sysman-suite' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sysman_elevenlabs_api_key"><?php esc_html_e( 'Clave de API de ElevenLabs', 'sysman-suite' ); ?></label></th>
                        <td><?php $campo_clave( 'elevenlabs', 'sysman_elevenlabs_api_key', 'sysman_elevenlabs_api_key' ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sysman_ia_url_privacidad"><?php esc_html_e( 'Política de tratamiento de datos', 'sysman-suite' ); ?></label></th>
                        <td>
                            <input type="url" id="sysman_ia_url_privacidad" name="sysman_ia_url_privacidad" class="large-text"
                                   value="<?php echo esc_attr( Config::url_privacidad() ); ?>" placeholder="https://">
                            <p class="description"><?php esc_html_e( 'Se enlaza en el aviso que acompaña al asistente (Ley 1581 de 2012).', 'sysman-suite' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sysman_elevenlabs_widget_js"><?php esc_html_e( 'Script del widget', 'sysman-suite' ); ?></label></th>
                        <td>
                            <input type="url" id="sysman_elevenlabs_widget_js" name="sysman_elevenlabs_widget_js" class="large-text"
                                   value="<?php echo esc_attr( Config::widget_js() ); ?>">
                            <p class="description"><?php esc_html_e( 'Cámbielo solo si ElevenLabs publica otra dirección o si quiere fijar una versión.', 'sysman-suite' ); ?></p>
                        </td>
                    </tr>
                </table>

                <h3><?php esc_html_e( 'Uso', 'sysman-suite' ); ?></h3>
                <p><code>[sysman_asistente]</code> — <?php esc_html_e( 'Atributos opcionales: variante (compact, expanded, full), invitacion, iniciar, terminar, escuchando, hablando, color1, color2, avatar, contexto="no", aviso="no".', 'sysman-suite' ); ?></p>

                <h3><?php esc_html_e( 'Configuración del agente en ElevenLabs', 'sysman-suite' ); ?></h3>
                <ol>
                    <li>
                        <?php esc_html_e( 'Prompt del sistema sugerido (las variables entre llaves las envía el sitio en cada conversación):', 'sysman-suite' ); ?>
                        <textarea class="large-text code" rows="9" readonly onclick="this.select()">Eres el asistente de transparencia presupuestal de la {{entidad}} (Colombia). Respondes en español, con frases cortas y cordiales, a preguntas de la ciudadanía sobre el presupuesto de gastos e ingresos.
Corte de los datos: {{periodo}}.
Resumen de gastos: {{resumen_gastos}}
Resumen de ingresos: {{resumen_ingresos}}
Para cifras de una dependencia, del porcentaje de ejecución o del recaudo, usa la herramienta consultar_presupuesto. Di las cifras tal como te llegan. Si un dato no está en el resumen ni en la herramienta, dilo y sugiere consultar el portal; nunca inventes cifras. No pidas ni registres datos personales. Si preguntan por trámites, quejas o temas que no son presupuestales, orienta hacia los canales oficiales de atención de la Gobernación.</textarea>
                    </li>
                    <li>
                        <?php esc_html_e( 'Variables dinámicas a declarar en el agente: entidad, periodo, resumen_gastos, resumen_ingresos.', 'sysman-suite' ); ?>
                    </li>
                    <li>
                        <?php esc_html_e( 'Herramienta de servidor (Tools → Add tool → Webhook):', 'sysman-suite' ); ?>
                        <table class="widefat striped" style="max-width:900px;margin-top:8px;">
                            <tbody>
                                <tr><th><?php esc_html_e( 'Nombre', 'sysman-suite' ); ?></th><td><code>consultar_presupuesto</code></td></tr>
                                <tr><th><?php esc_html_e( 'Método', 'sysman-suite' ); ?></th><td><code>POST</code></td></tr>
                                <tr><th>URL</th><td><input type="text" class="large-text code" readonly value="<?php echo esc_attr( $url_consulta ); ?>" onclick="this.select()"></td></tr>
                                <tr>
                                    <th><?php esc_html_e( 'Cabecera', 'sysman-suite' ); ?></th>
                                    <td>
                                        <code>X-Sysman-Secreto</code>:
                                        <input type="password" class="regular-text code" id="sysman-ia-secreto" readonly value="<?php echo esc_attr( $secreto ); ?>" onclick="this.type='text';this.select()">
                                        <p class="description"><?php esc_html_e( 'Guárdela en ElevenLabs como secreto. Clic para mostrarla.', 'sysman-suite' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e( 'Parámetros del cuerpo', 'sysman-suite' ); ?></th>
                                    <td>
                                        <code>tema</code> (<?php esc_html_e( 'obligatorio', 'sysman-suite' ); ?>):
                                        <?php foreach ( $temas as $k => $label ) : ?>
                                            <br><code><?php echo esc_html( $k ); ?></code> — <?php echo esc_html( $label ); ?>
                                        <?php endforeach; ?>
                                        <br><code>nombre</code> (<?php esc_html_e( 'opcional', 'sysman-suite' ); ?>): <?php esc_html_e( 'nombre de la dependencia, tal como lo diga la persona.', 'sysman-suite' ); ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </li>
                </ol>
            </div>
        </div>

        <?php submit_button( __( 'Guardar ajustes', 'sysman-suite' ) ); ?>
    </form>
</div>
<script>
(function () {
    var btn = document.getElementById('sysman-ia-probar');
    var out = document.getElementById('sysman-ia-probar-resultado');
    if (!btn) { return; }
    btn.addEventListener('click', function () {
        btn.disabled = true;
        out.textContent = <?php echo wp_json_encode( __( 'Probando…', 'sysman-suite' ) ); ?>;
        var datos = new FormData();
        datos.append('action', 'sysman_ia_probar');
        datos.append('nonce', <?php echo wp_json_encode( $nonce ); ?>);
        fetch(<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, { method: 'POST', body: datos, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (r) {
                out.textContent = r.success
                    ? '✔ ' + r.data.modelo + ': ' + r.data.texto
                    : '✖ ' + ((r.data && r.data.message) || 'Error');
            })
            .catch(function () { out.textContent = '✖ Error de red'; })
            .finally(function () { btn.disabled = false; });
    });
})();
</script>
