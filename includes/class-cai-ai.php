<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CAI_AI {

    public static function chat($prompt, $system='You are an expert SEO and information architect.', $max_tokens=400, $expect_json=false){
        $opt = get_option('cai_settings', []);
        $api_key = cai_get_openai_api_key();
        $model   = $opt['chat_model'] ?? 'gpt-4o-mini';
        if (empty($api_key)){
 codex/handle-errors-in-cai_ai-integration
            return new WP_Error('cai_missing_api_key', __('Missing OpenAI API key.', 'content-architect-ai'));

            return new WP_Error('missing_key', 'OpenAI API key missing');
 main
        }

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

        if ($expect_json){
            if (is_array($expect_json)){
                $body['response_format'] = $expect_json;
            } else {
                $body['response_format'] = ['type' => 'json_object'];
            }
        }

        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 45,
            'body' => wp_json_encode($body),
        ]);

        if (is_wp_error($response)){
 codex/handle-errors-in-cai_ai-integration
            return new WP_Error(
                'cai_http_error',
                __('Failed to contact OpenAI.', 'content-architect-ai'),
                [
                    'error_message' => $response->get_error_message(),
                ]
            );
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw_body = wp_remote_retrieve_body($response);
        $body = json_decode($raw_body, true);

        if ($code >= 200 && $code < 300 && !empty($body['choices'][0]['message']['content'])){
            return $body['choices'][0]['message']['content'];
        }

        $body_snippet = '';
        if (is_string($raw_body)){
            if (function_exists('mb_substr')){
                $body_snippet = mb_substr($raw_body, 0, 400);
                if (function_exists('mb_strlen') && mb_strlen($raw_body) > 400){
                    $body_snippet .= '…';
                } elseif (strlen($raw_body) > 400){
                    $body_snippet .= '…';
                }
            } else {
                $body_snippet = substr($raw_body, 0, 400);
                if (strlen($raw_body) > 400){
                    $body_snippet .= '…';
                }
            }
        }

        return new WP_Error(
            'cai_bad_response',
            __('Unexpected response from OpenAI.', 'content-architect-ai'),
            [
                'status_code' => $code,
                'body' => $body_snippet,
            ]
        );

            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $decoded = json_decode(wp_remote_retrieve_body($response), true);
        if ($code < 200 || $code >= 300){
            $message = !empty($decoded['error']['message']) ? $decoded['error']['message'] : 'Unexpected response from OpenAI';
            return new WP_Error('api_http_error', $message);
        }

        if (empty($decoded['choices'][0]['message']['content'])){
            return new WP_Error('api_missing_content', 'Empty response from OpenAI');
        }

        $content = $decoded['choices'][0]['message']['content'];
        if (!is_string($content)){
            return new WP_Error('api_invalid_content', 'Invalid content type returned from OpenAI');
        }

        $content = trim($content);
        if ($expect_json){
            $content = self::normalize_json_response($content);
        }

        return $content;
    }

    public static function normalize_json_response($text){
        if (!is_string($text)) return '';

        $normalized = trim($text);

        if (preg_match('/^```(?:json)?\s*([\s\S]*?)```/i', $normalized, $matches)){
            $normalized = $matches[1];
        }

        $normalized = trim($normalized);

        if (stripos($normalized, 'json') === 0){
            $candidate = ltrim(substr($normalized, 4));
            if ($candidate !== '' && ($candidate[0] === '{' || $candidate[0] === '[')){
                $normalized = $candidate;
            }
        }

        $normalized = trim($normalized);

        $firstBrace = strpos($normalized, '{');
        $firstBracket = strpos($normalized, '[');
        $start = false;
        if ($firstBrace !== false && $firstBracket !== false){
            $start = min($firstBrace, $firstBracket);
        } elseif ($firstBrace !== false){
            $start = $firstBrace;
        } elseif ($firstBracket !== false){
            $start = $firstBracket;
        }
        if ($start !== false && $start > 0){
            $normalized = substr($normalized, $start);
        }

        $endBrace = strrpos($normalized, '}');
        $endBracket = strrpos($normalized, ']');
        $end = false;
        if ($endBrace !== false && $endBracket !== false){
            $end = max($endBrace, $endBracket);
        } elseif ($endBrace !== false){
            $end = $endBrace;
        } elseif ($endBracket !== false){
            $end = $endBracket;
        }
        if ($end !== false){
            $normalized = substr($normalized, 0, $end + 1);
        }

        return trim($normalized);
    }

    public static function parse_json_response($text){
        $normalized = self::normalize_json_response($text);
        if ($normalized === ''){
            return new WP_Error('empty_json', 'Empty AI JSON response');
        }

        $data = json_decode($normalized, true);
        if (json_last_error() !== JSON_ERROR_NONE){
            return new WP_Error('json_decode_error', 'Failed to decode AI JSON: ' . json_last_error_msg(), ['raw'=>$normalized]);
        }

        return [
            'data' => $data,
            'raw'  => $normalized,
        ];
 main
    }

    public static function embedding($text){
        $opt = get_option('cai_settings', []);
        $api_key = cai_get_openai_api_key();
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

    public static function test(){
        $opt = get_option('cai_settings', []);
        $api_key = cai_get_openai_api_key();
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
}
