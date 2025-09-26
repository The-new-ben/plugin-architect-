<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CAI_Taxonomies {
    public static function register(){
        register_taxonomy('topic_cluster', ['post','page'], [
            'labels' => [
                'name' => __('אשכולי תוכן', 'content-architect-ai'),
                'singular_name' => __('אשכול תוכן', 'content-architect-ai'),
            ],
            'hierarchical' => true,
            'show_ui' => true,
            'show_in_rest' => true,
            'rewrite' => ['slug' => 'cluster'],
        ]);
    }
}
