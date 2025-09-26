<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CAI_Schema {

    public function __construct(){
        add_action('wp_head', [$this,'article_schema']);
    }

    public function article_schema(){
        $opt = get_option('cai_settings', []);
        if (empty($opt['auto_schema'])) return;
        if (!is_singular('post')) return;
        global $post;
        $data = [
            "@context"=>"https://schema.org",
            "@type"=>"Article",
            "headline"=> get_the_title($post),
            "datePublished"=> get_the_date(DATE_ATOM, $post),
            "dateModified"=> get_the_modified_date(DATE_ATOM, $post),
            "author"=> [
                "@type"=>"Person",
                "name"=> get_the_author_meta('display_name', $post->post_author)
            ],
            "mainEntityOfPage"=> get_permalink($post)
        ];
        echo '<script type="application/ld+json">'.wp_json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).'</script>';

        // Optional: Output FAQPage JSON-LD stored by generator
        $faq_json = get_post_meta($post->ID, '_cai_faq_json', true);
        if ($faq_json){
            echo '<script type="application/ld+json">'. $faq_json .'</script>';
        }
    }
}
