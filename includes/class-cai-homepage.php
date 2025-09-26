<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CAI_Homepage {

    public function __construct(){
        add_shortcode('cai_dynamic_home', [$this,'shortcode']);
    }

    public function shortcode($atts){
        $opt = get_option('cai_settings', []);
        $sections = isset($opt['home_sections']) ? intval($opt['home_sections']) : 3;
        $per = isset($opt['posts_per_cluster']) ? intval($opt['posts_per_cluster']) : 4;

        $terms = get_terms([
            'taxonomy'=>'topic_cluster',
            'hide_empty'=>true,
            'number'=>$sections,
            'orderby'=>'count',
            'order'=>'DESC'
        ]);
        if (empty($terms)) return '<p>'.__('אין עדיין אשכולי תוכן.', 'content-architect-ai').'</p>';

        $out = '<div class="cai-home">';
        foreach ($terms as $term){
            $out .= '<section class="cai-home-section">';
            $out .= '<h2><a href="'.esc_url(get_term_link($term)).'">'.esc_html($term->name).'</a></h2>';
            $q = new WP_Query([
                'post_type'=>['post'],
                'tax_query'=>[[
                    'taxonomy'=>'topic_cluster',
                    'field'=>'term_id',
                    'terms'=>[$term->term_id]
                ]],
                'posts_per_page'=>$per
            ]);
            if ($q->have_posts()){
                $out .= '<ul class="cai-home-posts">';
                while ($q->have_posts()){ $q->the_post();
                    $out .= '<li><a href="'.get_permalink().'">'.get_the_title().'</a></li>';
                }
                $out .= '</ul>';
            }
            wp_reset_postdata();
            $out .= '</section>';
        }
        $out .= '</div>';
        return $out;
    }
}
