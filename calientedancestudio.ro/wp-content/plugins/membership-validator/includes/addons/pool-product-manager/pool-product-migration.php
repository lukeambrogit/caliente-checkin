<?php
/**
 * Pool Product Manager - Database Migration
 * 
 * Convertește datele din pluginul original (_mv_pack_*) la noul format (_oc_pool_*)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Migrează datele din pluginul original la noul format
 */
function oc_pool_migrate_database() {
    global $wpdb;
    
    // Definește mapping-ul pentru conversion
    $meta_mapping = [
        '_mv_pack_enabled' => '_oc_pool_enabled',
        '_mv_pack_price' => '_oc_pool_price',
        '_mv_pack_pool_id' => '_oc_pool_pool_id',
        '_mv_pack_min_selections' => '_oc_pool_min_selections',
        '_mv_pack_max_selections' => '_oc_pool_max_selections',
        '_mv_pack_ui_style' => '_oc_pool_ui_style',
        '_mv_pack_allow_duplicates' => '_oc_pool_allow_duplicates',
        '_mv_pack_helper_text' => '_oc_pool_helper_text',
        '_mv_pack_selected_variations' => '_oc_pool_selected_variations'
    ];
    
    $migrated_count = 0;
    $updated_count = 0;
    
    foreach ( $meta_mapping as $old_key => $new_key ) {
        // Găsește toate produsele care au meta keys vechi
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
            $old_key
        ) );
        
        foreach ( $results as $row ) {
            $post_id = $row->post_id;
            $meta_value = $row->meta_value;
            
            // Verifică dacă noul meta key deja există
            $existing_new = get_post_meta( $post_id, $new_key, true );
            
            if ( ! $existing_new ) {
                // Copiază valoarea la noul key
                update_post_meta( $post_id, $new_key, maybe_unserialize( $meta_value ) );
                $migrated_count++;
            } else {
                $updated_count++;
            }
        }
    }
    
    // Setează flag că migrația s-a făcut
    update_option( 'oc_pool_migration_completed', current_time( 'mysql' ) );
    
    return [
        'migrated' => $migrated_count,
        'updated' => $updated_count,
        'mapping' => $meta_mapping
    ];
}

/**
 * Verifică dacă migrația s-a făcut deja
 */
function oc_pool_is_migrated() {
    return get_option( 'oc_pool_migration_completed', false );
}

/**
 * Afișează informații despre migrație în admin
 */
add_action( 'admin_notices', 'oc_pool_migration_notice' );
function oc_pool_migration_notice() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    
    $screen = get_current_screen();
    if ( ! $screen || $screen->id !== 'edit-product' ) return;
    
    // Verifică dacă există date vechi și migrația nu s-a făcut
    global $wpdb;
    $old_data_exists = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key LIKE '_mv_pack_%'" );
    $migration_done = oc_pool_is_migrated();
    
    if ( $old_data_exists && ! $migration_done ) {
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>' . esc_html__( 'Pool Product Manager:', OC_TEXT_DOMAIN ) . '</strong> ';
        echo sprintf( esc_html__( 'Găsite %d date din pluginul vechi.', OC_TEXT_DOMAIN ), (int) $old_data_exists ) . ' ';
        echo '<a href="' . esc_url( wp_nonce_url( add_query_arg( 'oc_pool_migrate', '1' ), 'oc_pool_migrate' ) ) . '" class="button">' . esc_html__( 'Migrează acum', OC_TEXT_DOMAIN ) . '</a></p>';
        echo '</div>';
    } elseif ( $migration_done ) {
        $migration_time = get_option( 'oc_pool_migration_completed' );
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>' . esc_html__( 'Pool Product Manager:', OC_TEXT_DOMAIN ) . '</strong> ' . sprintf( esc_html__( 'Migrația completată la %s', OC_TEXT_DOMAIN ), esc_html( (string) $migration_time ) ) . '</p>';
        echo '</div>';
    }
}

/**
 * Handler pentru migrația manuală
 */
add_action( 'admin_init', 'oc_pool_handle_migration_request' );
function oc_pool_handle_migration_request() {
    if ( ! isset( $_GET['oc_pool_migrate'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'oc_pool_migrate' ) ) {
        return;
    }
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Nu ai permisiunile necesare.' );
    }
    
    $result = oc_pool_migrate_database();
    
    $message = sprintf(
        'Migrația completată! %d înregistrări migrate, %d deja existente.',
        $result['migrated'],
        $result['updated']
    );
    
    wp_redirect( add_query_arg( [
        'page' => 'edit.php?post_type=product',
        'oc_pool_migrated' => base64_encode( $message )
    ], admin_url() ) );
    exit;
}

/**
 * Migrația automată la activare (dacă nu s-a făcut deja)
 */
add_action( 'init', 'oc_pool_auto_migrate', 5 );
function oc_pool_auto_migrate() {
    // Doar dacă nu s-a făcut deja
    if ( oc_pool_is_migrated() ) return;
    
    // Doar dacă există date vechi
    global $wpdb;
    $old_data_exists = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key LIKE '_mv_pack_%' LIMIT 1" );
    
    if ( $old_data_exists ) {
        // Execută migrația automată
        oc_pool_migrate_database();
    }
}
