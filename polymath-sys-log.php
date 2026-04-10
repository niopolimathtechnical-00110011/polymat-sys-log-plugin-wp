<?php
/**
 * Plugin Name: PolyMath® Sistema de Log
 * Description: Registra IP, geolocalização, páginas acessadas, navegadores, dispositivos, erros 404 e tentativas de login inválidas. Busca avançada com suporte a múltiplos termos (;).
 * Version: 1.1
 * Author: Enio Alves Borges
 * GIT: https://github.com/nio00110011
 */

if (!defined('ABSPATH')) {
    exit;
}

// ========================
// 1. CRIAÇÃO DA TABELA
// ========================
register_activation_hook(__FILE__, 'msl_criar_tabela');
function msl_criar_tabela() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'meu_sistema_log';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id INT NOT NULL AUTO_INCREMENT,
        event_type VARCHAR(50) NOT NULL,
        ip VARCHAR(45) NOT NULL,
        user_agent TEXT,
        browser VARCHAR(100),
        device VARCHAR(100),
        referrer TEXT,
        request_uri TEXT,
        username VARCHAR(100),
        geolocation_country VARCHAR(100),
        geolocation_city VARCHAR(100),
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_timestamp (timestamp),
        INDEX idx_event_type (event_type),
        INDEX idx_ip (ip)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// ========================
// 2. FUNÇÕES AUXILIARES
// ========================
function msl_get_real_ip() {
    $ip = $_SERVER['REMOTE_ADDR'];
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

function msl_get_browser_and_device($user_agent) {
    $browser = 'Desconhecido';
    $device = 'Desconhecido';

    if (stripos($user_agent, 'Tor') !== false) {
        $browser = 'Tor (anonimizador)';
    } elseif (stripos($user_agent, 'Brave') !== false) {
        $browser = 'Brave (anonimizador)';
    } elseif (stripos($user_agent, 'Edg') !== false) {
        $browser = 'Edge';
    } elseif (stripos($user_agent, 'OPR') !== false || stripos($user_agent, 'Opera') !== false) {
        $browser = 'Opera';
    } elseif (stripos($user_agent, 'Chrome') !== false && stripos($user_agent, 'Safari') !== false) {
        $browser = 'Chrome';
    } elseif (stripos($user_agent, 'Safari') !== false && stripos($user_agent, 'Version') !== false) {
        $browser = 'Safari';
    } elseif (stripos($user_agent, 'Firefox') !== false) {
        $browser = 'Firefox';
    } elseif (stripos($user_agent, 'MSIE') !== false || stripos($user_agent, 'Trident') !== false) {
        $browser = 'Internet Explorer';
    }

    if (stripos($user_agent, 'iPhone') !== false) {
        $device = 'iPhone';
    } elseif (stripos($user_agent, 'iPad') !== false) {
        $device = 'iPad';
    } elseif (stripos($user_agent, 'Android') !== false) {
        $device = 'Android';
    } elseif (stripos($user_agent, 'Windows NT') !== false) {
        $device = 'Windows PC';
    } elseif (stripos($user_agent, 'Mac OS X') !== false) {
        $device = 'Mac';
    } elseif (stripos($user_agent, 'Linux') !== false) {
        $device = 'Linux';
    }

    return ['browser' => $browser, 'device' => $device];
}

function msl_get_geolocation($ip) {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return ['country' => '', 'city' => ''];
    }

    $response = wp_remote_get("http://ip-api.com/json/{$ip}?fields=status,country,city", ['timeout' => 2]);
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return ['country' => '', 'city' => ''];
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (isset($body['status']) && $body['status'] === 'success') {
        return [
            'country' => sanitize_text_field($body['country']),
            'city'    => sanitize_text_field($body['city'])
        ];
    }
    return ['country' => '', 'city' => ''];
}

// ========================
// 3. REGISTRO DOS LOGS
// ========================
function msl_salvar_log($event_type, $username = '') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'meu_sistema_log';

    $ip = msl_get_real_ip();
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';

    $browser_device = msl_get_browser_and_device($user_agent);
    $geo = msl_get_geolocation($ip);

    $data = [
        'event_type'          => $event_type,
        'ip'                  => $ip,
        'user_agent'          => $user_agent,
        'browser'             => $browser_device['browser'],
        'device'              => $browser_device['device'],
        'referrer'            => $referrer,
        'request_uri'         => $request_uri,
        'username'            => $username,
        'geolocation_country' => $geo['country'],
        'geolocation_city'    => $geo['city'],
        'timestamp'           => current_time('mysql')
    ];

    $wpdb->insert($table_name, $data);
}

add_action('wp', 'msl_log_page_access');
function msl_log_page_access() {
    if (is_admin()) {
        return;
    }
    if (is_404()) {
        msl_salvar_log('erro_404');
    } else {
        msl_salvar_log('acesso_pagina');
    }
}

add_action('wp_login_failed', 'msl_log_failed_login');
function msl_log_failed_login($username) {
    msl_salvar_log('login_falhou', $username);
}

// ========================
// 4. CRON JOB PARA LIMPEZA
// ========================
add_action('wp', 'msl_agendar_limpeza');
function msl_agendar_limpeza() {
    if (!wp_next_scheduled('msl_limpeza_diaria')) {
        wp_schedule_event(time(), 'daily', 'msl_limpeza_diaria');
    }
}

add_action('msl_limpeza_diaria', 'msl_executar_limpeza');
function msl_executar_limpeza() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'meu_sistema_log';
    $dias_reter = get_option('msl_dias_reter', 30);
    $data_limite = date('Y-m-d H:i:s', strtotime("-$dias_reter days"));
    $wpdb->query($wpdb->prepare("DELETE FROM $table_name WHERE timestamp < %s", $data_limite));
}

// ========================
// 5. PAINEL ADMINISTRATIVO (COM BUSCA AVANÇADA)
// ========================
add_action('admin_menu', 'msl_adicionar_menu');
function msl_adicionar_menu() {
    add_submenu_page(
        'tools.php',
        'PolyMath® Sistema de Log',
        'Logs de Acesso',
        'manage_options',
        'meu-sistema-log',
        'msl_pagina_logs_manual'
    );
}

function msl_pagina_logs_manual() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'meu_sistema_log';
    
    $per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;
    
    $event_filter = isset($_GET['event_filter']) ? sanitize_text_field($_GET['event_filter']) : '';
    $search = isset($_GET['s']) ? trim($_GET['s']) : '';
    
    $where = '1=1';
    $params = [];
    
    if (!empty($event_filter)) {
        $where .= ' AND event_type = %s';
        $params[] = $event_filter;
    }
    
    // Busca avançada com múltiplos termos (separados por ;)
    if (!empty($search)) {
        $terms = array_map('trim', explode(';', $search));
        $terms = array_filter($terms, function($term) { return $term !== ''; });
        
        if (!empty($terms)) {
            $term_conditions = [];
            foreach ($terms as $term) {
                $like = '%' . $wpdb->esc_like($term) . '%';
                // Monta a condição para este termo sem usar prepare agora
                $term_conditions[] = "(CAST(id AS CHAR) LIKE %s OR " .
                    "DATE_FORMAT(timestamp, '%%d/%%m/%%Y %%H:%%i:%%s') LIKE %s OR " .
                    "event_type LIKE %s OR " .
                    "ip LIKE %s OR " .
                    "CONCAT(geolocation_city, ', ', geolocation_country) LIKE %s OR " .
                    "browser LIKE %s OR " .
                    "device LIKE %s OR " .
                    "request_uri LIKE %s OR " .
                    "username LIKE %s)";
                // Adiciona 9 placeholders (os %s serão preenchidos depois)
                for ($i = 0; $i < 9; $i++) {
                    $params[] = $like;
                }
            }
            // Todos os termos devem ser satisfeitos (AND)
            $where .= ' AND (' . implode(' AND ', $term_conditions) . ')';
        }
    }
    
    // Contagem total
    $sql_total = "SELECT COUNT(*) FROM $table_name WHERE $where";
    if (!empty($params)) {
        $total_items = $wpdb->get_var($wpdb->prepare($sql_total, $params));
    } else {
        $total_items = $wpdb->get_var($sql_total);
    }
    
    // Consulta principal
    $sql = "SELECT * FROM $table_name WHERE $where ORDER BY timestamp DESC LIMIT %d OFFSET %d";
    $params[] = $per_page;
    $params[] = $offset;
    $logs = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
    
    // Exibição (mantida igual)
    ?>
    <div class="wrap">
        <h1>PolyMath® Sistema de Log</h1>
        
        <form method="get" style="margin-bottom: 20px;">
            <input type="hidden" name="page" value="meu-sistema-log">
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <select name="event_filter">
                    <option value="">Todos os eventos</option>
                    <option value="acesso_pagina" <?php selected($event_filter, 'acesso_pagina'); ?>>Acesso página</option>
                    <option value="erro_404" <?php selected($event_filter, 'erro_404'); ?>>Erro 404</option>
                    <option value="login_falhou" <?php selected($event_filter, 'login_falhou'); ?>>Login falhou</option>
                </select>
                <input type="text" name="s" placeholder="Buscar por qualquer campo (use ; para múltiplos termos)" value="<?php echo esc_attr($search); ?>" style="min-width: 300px;">
                <button type="submit" class="button">Filtrar</button>
                <a href="?page=meu-sistema-log" class="button">Limpar filtros</a>
            </div>
            <p class="description">Exemplo: <code>09/04/20 ; acesso pagina ; 35.242.132.122</code> – retorna logs que contenham todos esses trechos em qualquer coluna.</p>
        </form>
        
        <?php if (empty($logs)): ?>
            <p>Nenhum log encontrado.</p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Data/Hora</th>
                        <th>Tipo</th>
                        <th>IP</th>
                        <th>Localização</th>
                        <th>Navegador</th>
                        <th>Dispositivo</th>
                        <th>Página</th>
                        <th>Usuário</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo intval($log['id']); ?></td>
                            <td><?php echo get_date_from_gmt($log['timestamp'], 'd/m/Y H:i:s'); ?></td>
                            <td><?php echo esc_html(str_replace('_', ' ', $log['event_type'])); ?></td>
                            <td><?php echo esc_html($log['ip']); ?></td>
                            <td><?php echo esc_html($log['geolocation_city'] . ', ' . $log['geolocation_country']); ?></td>
                            <td><?php echo esc_html($log['browser']); ?></td>
                            <td><?php echo esc_html($log['device']); ?></td>
                            <td><code><?php echo esc_html(substr($log['request_uri'], 0, 80)); ?></code></td>
                            <td><?php echo esc_html($log['username']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php
            $total_pages = ceil($total_items / $per_page);
            if ($total_pages > 1):
            ?>
            <div class="tablenav">
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php echo $total_items; ?> itens</span>
                    <span class="pagination-links">
                        <?php
                        $base_url = add_query_arg(['page' => 'meu-sistema-log', 'event_filter' => $event_filter, 's' => $search], admin_url('tools.php'));
                        if ($current_page > 1) {
                            echo '<a class="prev-page button" href="' . esc_url(add_query_arg('paged', $current_page - 1, $base_url)) . '">‹</a>';
                        } else {
                            echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>';
                        }
                        echo '<span class="paging-input">Página ' . $current_page . ' de ' . $total_pages . '</span>';
                        if ($current_page < $total_pages) {
                            echo '<a class="next-page button" href="' . esc_url(add_query_arg('paged', $current_page + 1, $base_url)) . '">›</a>';
                        } else {
                            echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>';
                        }
                        ?>
                    </span>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <hr>
        <h2>Configurações de Retenção</h2>
        <form method="post" action="options.php">
            <?php settings_fields('msl_config_group'); ?>
            <label>Manter logs por (dias):</label>
            <input type="number" name="msl_dias_reter" value="<?php echo esc_attr(get_option('msl_dias_reter', 30)); ?>" min="1" max="365">
            <?php submit_button('Salvar'); ?>
        </form>
    </div>
    <?php
}

// ========================
// 6. CONFIGURAÇÕES E LIMPEZA
// ========================
add_action('admin_init', 'msl_registrar_configuracoes');
function msl_registrar_configuracoes() {
    register_setting('msl_config_group', 'msl_dias_reter', 'absint');
}

register_deactivation_hook(__FILE__, 'msl_desativar_cron');
function msl_desativar_cron() {
    $timestamp = wp_next_scheduled('msl_limpeza_diaria');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'msl_limpeza_diaria');
    }
}
