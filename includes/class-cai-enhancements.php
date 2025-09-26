<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CAI_Enhancements {

    public function __construct(){
        add_shortcode('cai_toc', [$this,'toc']);
        add_shortcode('cai_reading_time', [$this,'reading_time']);
        add_action('wp_head', [$this,'head_meta'], 5);
        add_action('wp_head', [$this,'website_schema'], 20);
        add_shortcode('cai_author_box', [$this,'author_box']);
        add_shortcode('cai_share', [$this,'share']);
        add_shortcode('cai_updated', [$this,'updated']);
        add_shortcode('cai_cluster', [$this,'cluster_name']);
        add_shortcode('cai_cluster_posts', [$this,'cluster_posts']);
    

    public function author_box(){
        if (!is_singular()) return '';
        global $post;
        $author_id = $post->post_author;
        $name = get_the_author_meta('display_name', $author_id);
        $bio  = get_the_author_meta('description', $author_id);
        $avatar = get_avatar_url($author_id, ['size'=>96]);
        $html = '<div class="cai-author-box">';
        $html .= '<img class="cai-author-avatar" src="'.esc_url($avatar).'" alt="'.esc_attr($name).'" />';
        $html .= '<div class="cai-author-meta"><div class="cai-author-name">'.esc_html($name).'</div>';
        if ($bio) $html .= '<div class="cai-author-bio">'.esc_html($bio).'</div>';
        $html .= '</div></div>';
        return $html;
    }

    public function share($atts=[]){
        if (!is_singular()) return '';
        $url = get_permalink();
        $title = get_the_title();
        $html = '<div class="cai-share"><button class="cai-btn" data-copy="'.esc_attr($url).'">'.esc_html__('העתק קישור','content-architect-ai').'</button> ';
        $html .= '<a class="cai-btn" target="_blank" rel="noopener" href="https://www.facebook.com/sharer/sharer.php?u='.rawurlencode($url).'">'.esc_html__('שיתוף בפייסבוק','content-architect-ai').'</a> ';
        $html .= '<a class="cai-btn" target="_blank" rel="noopener" href="https://www.linkedin.com/shareArticle?mini=true&url='.rawurlencode($url).'&title='.rawurlencode($title).'">'.esc_html__('שיתוף בלינקדאין','content-architect-ai').'</a></div>';
        return $html;
    }

    public function updated(){
        if (!is_singular()) return '';
        return '<span class="cai-updated">'.esc_html__('עודכן: ','content-architect-ai').get_the_modified_date().'</span>';
    }

    public function cluster_name(){
        if (!is_singular()) return '';
        $terms = wp_get_post_terms(get_the_ID(), 'topic_cluster');
        if (empty($terms)) return '';
        return '<span class="cai-cluster">'.esc_html($terms[0]->name).'</span>';
    }

    public function cluster_posts($atts=[]){
        $atts = shortcode_atts(['limit'=>6], $atts);
        $post_id = get_the_ID();
        $terms = wp_get_post_terms($post_id, 'topic_cluster', ['fields'=>'ids']);
        if (empty($terms)) return '';
        $q = new WP_Query([
            'post_type' => 'post',
            'post__not_in' => [$post_id],
            'tax_query' => [[
                'taxonomy'=>'topic_cluster',
                'field'=>'term_id',
                'terms'=>$terms
            ]],
            'posts_per_page' => intval($atts['limit'])
        ]);
        if (!$q->have_posts()) return '';
        $html = '<div class="cai-cluster-posts"><ul>';
        while ($q->have_posts()){ $q->the_post();
            $html .= '<li><a href="'.get_permalink().'">'.get_the_title().'</a></li>';
        }
        $html .= '</ul></div>';
        wp_reset_postdata();
        return $html;
    }

}

    // Simple Table of Contents from the_content
    public function toc($atts = []){
        if (!is_singular()) return '';
        global $post;
        $html = $post->post_content;
        if (!$html) return '';

        // find headings
        if (!preg_match_all('/<h([2-4])[^>]*>(.*?)<\/h\1>/is', $html, $m, PREG_SET_ORDER)) return '';
        $out = '<div class="cai-toc"><h3>'.esc_html__('תוכן עניינים','content-architect-ai').'</h3><ol>';
        $i = 0;
        foreach ($m as $h){
            $i++;
            $text = wp_strip_all_tags($h[2]);
            $id = sanitize_title($text) . '-' . $i;
            // add anchor id into content if missing (lazy replace)
            $html = preg_replace('/'.preg_quote($h[0],'/').'/', '<h'.$h[1].' id="'.$id.'">'.$h[2].'</h'.$h[1].'>',
                                 $html, 1);
            $out .= '<li><a href="#'.$id.'">'.esc_html($text).'</a></li>';
        }
        $out .= '</ol></div>';

        // replace content with injected anchors
        remove_filter('the_content', [$this,'auto_append_toc'], 1);
        add_filter('the_content', function($content) use ($html){ return $html; }, 1);
        return $out;
    }

    public function reading_time($atts = []){
        if (!is_singular()) return '';
        global $post;
        $wpm = 200;
        $words = str_word_count( wp_strip_all_tags( $post->post_content ) );
        $min = max(1, ceil($words / $wpm));
        return '<span class="cai-reading-time">'.sprintf(esc_html__('%d דקות קריאה','content-architect-ai'), $min).'</span>';
    }

    public function head_meta(){
        if (!is_singular()) return;
        global $post;
        $title = get_post_meta($post->ID, '_cai_meta_title', true) ?: get_the_title($post);
        $desc  = get_post_meta($post->ID, '_cai_meta_desc', true) ?: wp_trim_words( wp_strip_all_tags($post->post_content), 40);
        $url = get_permalink($post);
        echo '<meta property="og:type" content="article" />'."\n";
        echo '<meta property="og:title" content="'.esc_attr($title).'" />'."\n";
        echo '<meta property="og:description" content="'.esc_attr($desc).'" />'."\n";
        echo '<meta property="og:url" content="'.esc_url($url).'" />'."\n";
        echo '<meta name="twitter:card" content="summary_large_image" />'."\n";
        echo '<meta name="twitter:title" content="'.esc_attr($title).'" />'."\n";
        echo '<meta name="twitter:description" content="'.esc_attr($desc).'" />'."\n";
    }

    public function website_schema(){
        $opt = get_option('cai_settings', []);
        $org = [
            "@context"=>"https://schema.org",
            "@type"=>"Organization",
            "name" => $opt['org_name'] ?? get_bloginfo('name'),
        ];
        if (!empty($opt['org_logo'])) $org["logo"] = esc_url_raw($opt['org_logo']);

        $site = [
            "@context"=>"https://schema.org",
            "@type"=>"WebSite",
            "name"=> get_bloginfo('name'),
            "url"=> home_url('/'),
            "potentialAction" => [
              "@type" => "SearchAction",
              "target" => home_url('/?s={search_term_string}'),
              "query-input" => "required name=search_term_string"
            ]
        ];
        echo '<script type="application/ld+json">'.wp_json_encode($org, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).'</script>';
        echo '<script type="application/ld+json">'.wp_json_encode($site, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).'</script>';
    }
}
