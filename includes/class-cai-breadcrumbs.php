<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CAI_Breadcrumbs {

    public function __construct(){
        add_shortcode('cai_breadcrumbs', [$this,'shortcode']);
        add_action('wp_head', [$this,'jsonld']);
    }

    public function build(){
        $items = [];
        $items[] = ['name'=> get_bloginfo('name'), 'url'=> home_url('/')];
        if (is_singular()){
            $post_id = get_the_ID();
            $terms = wp_get_post_terms($post_id, 'topic_cluster');
            if (!empty($terms)){
                $term = $terms[0];
                $items[] = ['name'=>$term->name, 'url'=> get_term_link($term)];
            }
            $items[] = ['name'=> get_the_title($post_id), 'url'=> get_permalink($post_id)];
        } elseif (is_tax('topic_cluster')){
            $term = get_queried_object();
            $items[] = ['name'=>$term->name, 'url'=> get_term_link($term)];
        } elseif (is_archive()){
            $items[] = ['name'=> post_type_archive_title('', false), 'url'=> ''];
        }
        return $items;
    }

    public function shortcode($atts){
        $items = $this->build();
        if (empty($items)) return '';
        $html = '<nav class="cai-breadcrumbs" aria-label="Breadcrumb"><ol>';
        foreach ($items as $i=>$item){
            $last = $i === count($items)-1;
            $html .= '<li>'.($last ? esc_html($item['name']) : '<a href="'.esc_url($item['url']).'">'.esc_html($item['name']).'</a>').'</li>';
        }
        $html .= '</ol></nav>';
        return $html;
    }

    public function jsonld(){
        $opt = get_option('cai_settings', []);
        if (empty($opt['auto_breadcrumbs_jsonld'])) return;
        $items = $this->build();
        if (empty($items)) return;
        $list = [];
        foreach ($items as $idx=>$it){
            $list[] = [
                "@type" => "ListItem",
                "position" => $idx+1,
                "name" => wp_strip_all_tags($it['name']),
                "item" => esc_url_raw($it['url'])
            ];
        }
        echo '<script type="application/ld+json">'.wp_json_encode([
            "@context"=>"https://schema.org",
            "@type"=>"BreadcrumbList",
            "itemListElement"=>$list
        ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).'</script>';
    }
}
