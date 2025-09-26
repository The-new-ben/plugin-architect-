<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CAI_Internal_Links {

    public function __construct(){
        add_shortcode('cai_related', [$this,'shortcode']);
        add_filter('the_content', [$this,'auto_append'], 20);
    }

    public function shortcode($atts){
        $atts = shortcode_atts(['limit'=>4], $atts);
        $post_id = get_the_ID();
        $terms = wp_get_post_terms($post_id, 'topic_cluster', ['fields'=>'ids']);
        if (empty($terms)) return '';
        $q = new WP_Query([
            'post_type' => get_post_type($post_id),
            'post__not_in' => [$post_id],
            'tax_query' => [[
                'taxonomy'=>'topic_cluster',
                'field'=>'term_id',
                'terms'=>$terms
            ]],
            'posts_per_page' => intval($atts['limit'])
        ]);
        if (!$q->have_posts()) return '';
        $html = '<div class="cai-related"><h3>'.__('עוד בנושא', 'content-architect-ai').'</h3><ul>';
        while ($q->have_posts()){ $q->the_post();
            $html .= '<li><a href="'.get_permalink().'">'.get_the_title().'</a></li>';
        }
        $html .= '</ul></div>';
        wp_reset_postdata();
        return $html;
    }

    public function auto_append($content){
        $opt = get_option('cai_settings', []);
        if (!is_singular() || empty($opt['auto_internal_links'])) return $content;
        return $content . $this->shortcode([]);
    }
}
