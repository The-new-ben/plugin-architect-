<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CAI_Clustering {

    public static function index_post($post_id){
        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') return;
        $text = wp_strip_all_tags($post->post_title . "\n" . $post->post_content);
        $vec  = CAI_AI::embedding(mb_substr($text,0,7000));
        if (!empty($vec)){
            update_post_meta($post_id, '_cai_embedding', wp_json_encode($vec));
        }
    }

    public static function assign_cluster($post_id){
        $vec_json = get_post_meta($post_id, '_cai_embedding', true);
        if (empty($vec_json)) return;
        $vec = json_decode($vec_json, true);
        if (empty($vec)) return;

        // find best matching existing cluster term based on centroid (average of embeddings in term)
        $terms = get_terms(['taxonomy'=>'topic_cluster','hide_empty'=>false]);
        $best_score = 0; $best_term_id = 0;
        foreach ($terms as $term){
            $ids = get_objects_in_term($term->term_id, 'topic_cluster');
            $centroid = self::centroid($ids);
            $score = CAI_AI::cosine_similarity($vec, $centroid);
            if ($score > $best_score){
                $best_score = $score;
                $best_term_id = $term->term_id;
            }
        }

        if ($best_score > 0.80 && $best_term_id){
            wp_set_object_terms($post_id, intval($best_term_id), 'topic_cluster', false);
        } else {
            // ask AI to propose a cluster name
            $name = CAI_AI::chat('Suggest a 2-4 word topic cluster name in Hebrew for this content: '.get_the_title($post_id));
            $name = sanitize_text_field($name ?: 'אשכול חדש');
            $term = wp_insert_term($name, 'topic_cluster');
            if (!is_wp_error($term)){
                wp_set_object_terms($post_id, intval($term['term_id']), 'topic_cluster', false);
            }
        }
    }

    protected static function centroid($post_ids){
        $sum = []; $count = 0;
        foreach ($post_ids as $pid){
            $vec = json_decode(get_post_meta($pid, '_cai_embedding', true), true);
            if (empty($vec)) continue;
            if (empty($sum)) $sum = array_fill(0, count($vec), 0.0);
            for ($i=0; $i<count($vec); $i++){ $sum[$i] += $vec[$i]; }
            $count++;
        }
        if ($count === 0) return [];
        for ($i=0; $i<count($sum); $i++){ $sum[$i] = $sum[$i]/$count; }
        return $sum;
    }
}
