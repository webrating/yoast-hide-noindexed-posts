<?php
/*
Plugin Name: Hide Noindex
Description: Adds a column to the WordPress post and page editors showing content without the noindex metatag and hides noindex posts/pages. Works exclusively with Yoast SEO.
Version: 1.4
Author: Mathias Ahlgren
*/

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class HideNoindexYoast {
    private static $instance;
    private $is_yoast_active = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_init', array($this, 'admin_init'));
    }

    public function admin_init() {
        if (!$this->is_yoast_active()) {
            return;
        }

        $post_types = array('post', 'page');
        foreach ($post_types as $post_type) {
            add_filter("manage_{$post_type}s_columns", array($this, 'add_noindex_column'));
            add_action("manage_{$post_type}s_custom_column", array($this, 'populate_noindex_column'), 10, 2);
            add_filter("manage_edit-{$post_type}_sortable_columns", array($this, 'noindex_column_sortable'));
        }

        add_action('pre_get_posts', array($this, 'noindex_column_orderby'));
        add_action('pre_get_posts', array($this, 'hide_noindex_posts'));
        add_action('admin_head', array($this, 'noindex_column_style'));
    }

    private function is_yoast_active() {
        if ($this->is_yoast_active === null) {
            $this->is_yoast_active = defined('WPSEO_VERSION');
        }
        return $this->is_yoast_active;
    }

    public function add_noindex_column($columns) {
        $columns['noindex_status'] = 'Index Status';
        return $columns;
    }

    public function populate_noindex_column($column, $post_id) {
        if ($column === 'noindex_status') {
            $cache_key = 'noindex_status_' . $post_id;
            $status = get_transient($cache_key);
            
            if ($status === false) {
                $noindex = get_post_meta($post_id, '_yoast_wpseo_meta-robots-noindex', true) === '1';
                $status = $noindex ? 'no_index' : 'indexed';
                set_transient($cache_key, $status, HOUR_IN_SECONDS);
            }
            
            echo $status === 'no_index' ? '<span style="color: red;">No Index</span>' : '<span style="color: green;">Indexed</span>';
        }
    }

    public function noindex_column_style() {
        echo '<style>.column-noindex_status { width: 10%; }</style>';
    }

    public function noindex_column_sortable($columns) {
        $columns['noindex_status'] = 'noindex_status';
        return $columns;
    }

    public function noindex_column_orderby($query) {
        if (!is_admin()) return;

        $orderby = $query->get('orderby');
        if ('noindex_status' == $orderby) {
            $query->set('meta_key', '_yoast_wpseo_meta-robots-noindex');
            $query->set('orderby', 'meta_value_num');
        }
    }

    public function hide_noindex_posts($query) {
        if (!is_admin() || !$query->is_main_query()) return;

        $screen = get_current_screen();
        if (!$screen || !in_array($screen->base, ['edit'])) return;

        // Apply the filter even when searching
        $meta_query = array(
            'relation' => 'OR',
            array(
                'key' => '_yoast_wpseo_meta-robots-noindex',
                'value' => '1',
                'compare' => '!='
            ),
            array(
                'key' => '_yoast_wpseo_meta-robots-noindex',
                'compare' => 'NOT EXISTS'
            )
        );

        // Merge with existing meta query if it exists
        $existing_meta_query = $query->get('meta_query');
        if (!empty($existing_meta_query)) {
            $meta_query = array(
                'relation' => 'AND',
                $existing_meta_query,
                $meta_query
            );
        }

        $query->set('meta_query', $meta_query);
    }
}

// Initialize the plugin
function hide_noindex_yoast_init() {
    return HideNoindexYoast::get_instance();
}

add_action('plugins_loaded', 'hide_noindex_yoast_init');