<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CAI_Cannibalization {

    public function __construct(){
        add_action('admin_init', [$this,'admin_page_data']);
    }

    public static function check_post($post_id){
        $kw = get_post_meta($post_id, '_cai_target_keyword', true);
        if (!$kw) return;
        $args = [
            'post_type' => get_post_type($post_id),
            'post__not_in' => [$post_id],
            'meta_query' => [[
                'key' => '_cai_target_keyword',
                'value' => $kw,
                'compare' => '=',
            ]],
            'posts_per_page' => 5
        ];
        $q = new WP_Query($args);
        if ($q->have_posts()){
            update_post_meta($post_id, '_cai_cannibal_matches', wp_list_pluck($q->posts, 'ID'));
        } else {
            delete_post_meta($post_id, '_cai_cannibal_matches');
        }
        wp_reset_postdata();
    }

    public function admin_page_data(){
        if (!current_user_can('manage_options')) return;
        if (!isset($_GET['page']) || $_GET['page'] !== 'cai-cannibal') return;
        $args = [
            'post_type' => ['post','page'],
            'posts_per_page' => 50,
            'meta_key' => '_cai_target_keyword',
            'meta_compare' => 'EXISTS'
        ];
        $q = new WP_Query($args);
        echo '<div class="wrap"><table class="widefat"><thead><tr><th>'.__('מילת מפתח', 'content-architect-ai').'</th><th>'.__('תכנים מתנגשים', 'content-architect-ai').'</th></tr></thead><tbody>';
        $map = [];
        foreach ($q->posts as $p){
            $kw = get_post_meta($p->ID, '_cai_target_keyword', true);
            if (!$kw) continue;
            $map[$kw] = $map[$kw] ?? [];
            $map[$kw][] = $p;
        }
        foreach ($map as $kw=>$posts){
            if (count($posts) <= 1) continue;
            echo '<tr><td>'.esc_html($kw).'</td><td>';
            foreach ($posts as $p){
                echo '<a href="'.get_edit_post_link($p->ID).'">'.esc_html(get_the_title($p)).'</a> | ';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
}
