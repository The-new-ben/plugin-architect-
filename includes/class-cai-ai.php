<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CAI_AI {

    public static function chat($prompt, $system='You are an expert SEO and information architect.', $max_tokens=400){
        $opt = get_option('cai_settings', []);
        $api_key = defined('CAI_OPENAI_API_KEY') && CAI_OPENAI_API_KEY ? CAI_OPENAI_API_KEY : ($opt['openai_api_key'] ?? '');
        $model   = $opt['chat_model'] ?? 'gpt-4o-mini';
        if (empty($api_key)) return '';

        $endpoint = 'https://api.openai.com/v1/chat/completions';
        $body = [
            'model' => $model,
            'messages' => [
                ['role'=>'system', 'content'=>$system],
                ['role'=>'user', 'content'=>$prompt],
            ],
            'temperature' => 0.2,
            'max_tokens' => $max_tokens
        ];

        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 45,
            'body' => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) return '';
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if ($code >= 200 && $code < 300 && !empty($body['choices'][0]['message']['content'])){
            return $body['choices'][0]['message']['content'];
        }
        return '';
    }

    public static function embedding($text){
        $opt = get_option('cai_settings', []);
        $api_key = defined('CAI_OPENAI_API_KEY') && CAI_OPENAI_API_KEY ? CAI_OPENAI_API_KEY : ($opt['openai_api_key'] ?? '');
        $model   = $opt['embedding_model'] ?? 'text-embedding-3-small';
        if (empty($api_key)) return [];

        $endpoint = 'https://api.openai.com/v1/embeddings';
        $body = [
            'model' => $model,
            'input' => $text,
        ];
        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 45,
            'body' => wp_json_encode($body),
        ]);
        if (is_wp_error($response)) return [];
        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if ($code >= 200 && $code < 300 && !empty($data['data'][0]['embedding'])){
            return $data['data'][0]['embedding'];
        }
        return [];
    }

    public static function cosine_similarity($a, $b){
        if (empty($a) || empty($b) || count($a) !== count($b)) return 0.0;
        $dot = 0.0; $na = 0.0; $nb = 0.0;
        $n = count($a);
        for ($i=0; $i<$n; $i++){
            $dot += $a[$i]*$b[$i];
            $na  += $a[$i]*$a[$i];
            $nb  += $b[$i]*$b[$i];
        }
        if ($na == 0 || $nb == 0) return 0.0;
        return $dot / (sqrt($na)*sqrt($nb));
    }
}


    public static function test(){
        $opt = get_option('cai_settings', []);
        $api_key = defined('CAI_OPENAI_API_KEY') && CAI_OPENAI_API_KEY ? CAI_OPENAI_API_KEY : ($opt['openai_api_key'] ?? '');
        $model   = $opt['chat_model'] ?? 'gpt-4o-mini';
        if (empty($api_key)) return new WP_Error('missing_key','OpenAI API key missing');

        $endpoint = 'https://api.openai.com/v1/chat/completions';
        $body = [
            'model' => $model,
            'messages' => [
                ['role'=>'system','content'=>'You are a health check probe.'],
                ['role'=>'user','content'=>'Reply with OK']
            ],
            'temperature' => 0,
            'max_tokens' => 5
        ];
        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 30,
            'body' => wp_json_encode($body),
        ]);
        if (is_wp_error($response)) return $response;
        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if ($code>=200 && $code<300 && isset($data['choices'][0]['message']['content'])){
            return trim($data['choices'][0]['message']['content']);
        }
        return new WP_Error('api_error', 'Bad response from OpenAI');
    }
