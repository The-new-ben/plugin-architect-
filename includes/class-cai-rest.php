<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CAI_REST {

    public function __construct(){
        add_action('rest_api_init', [$this,'routes']);
        add_action('wp_ajax_cai_generate_meta', [$this,'ajax_generate_meta']);
        add_action('wp_ajax_cai_reindex', [$this,'ajax_reindex']);
        add_action('wp_ajax_cai_test_ai', [$this,'ajax_test_ai']);
    }

    public function routes(){
        register_rest_route('cai/v1', '/reindex', [
            'methods' => 'POST',
            'permission_callback' => function(){ return current_user_can('manage_options'); },
            'callback' => function($req){ return $this->reindex_all(); }
        ]);
    }

    public function reindex_all(){
        $q = new WP_Query([
            'post_type'=>['post','page'],
            'post_status'=>'publish',
            'posts_per_page'=>-1,
            'fields'=>'ids'
        ]);
        $count = 0;
        foreach ($q->posts as $pid){
            CAI_Clustering::index_post($pid);
            CAI_Clustering::assign_cluster($pid);
            $count++;
        }
        return ['indexed'=>$count];
    }

    public function ajax_generate_meta(){
        check_ajax_referer('cai-admin','nonce');
if (!current_user_can('edit_posts')) wp_send_json_error('forbidden', 403);
        $pid = absint($_POST['post_id'] ?? 0);
        if (!$pid) wp_send_json_error('missing id', 400);
        $post = get_post($pid);
        $prompt = 'Create an SEO title (<=60 chars) and a meta description (<=155 chars) in Hebrew for the following article. Return JSON with keys title, description. Title: "'.get_the_title($pid).'" Content: ' . wp_strip_all_tags($post->post_content);
        $json = CAI_AI::chat($prompt, 'You are a world-class SEO copywriter. Return JSON only.', 200);
        $data = json_decode($json, true);
        if (is_array($data)){
            update_post_meta($pid, '_cai_meta_title', sanitize_text_field($data['title'] ?? ''));
            update_post_meta($pid, '_cai_meta_desc', sanitize_textarea_field($data['description'] ?? ''));
            wp_send_json_success($data);
        } else {
            wp_send_json_error('bad ai response');
        }
    }

    public function ajax_reindex(){
        check_ajax_referer('cai-admin','nonce');
if (!current_user_can('manage_options')) wp_send_json_error('forbidden', 403);
        $res = $this->reindex_all();
        wp_send_json_success($res);
    }
}


    public function ajax_test_ai(){
        check_ajax_referer('cai-admin','nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('forbidden', 403);
        $res = CAI_AI::test();
        if (is_wp_error($res)) wp_send_json_error($res->get_error_message());
        wp_send_json_success(['ok'=>$res]);
    }
