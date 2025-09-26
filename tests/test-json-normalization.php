<?php
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

require_once ABSPATH . 'includes/class-cai-ai.php';

function assert_decodes(string $label, string $input, array $expected): void {
    $normalized = CAI_AI::normalize_json_response($input);
    $decoded = json_decode($normalized, true);
    if ($decoded !== $expected) {
        throw new RuntimeException(sprintf(
            '%s failed: expected %s got %s',
            $label,
            json_encode($expected, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ));
    }
}

function assert_decodable(string $label, string $input): void {
    $normalized = CAI_AI::normalize_json_response($input);
    $decoded = json_decode($normalized, true);
    if (!is_array($decoded) && !is_object($decoded)) {
        throw new RuntimeException(sprintf('%s failed to decode JSON: %s', $label, json_last_error_msg()));
    }
}

try {
    assert_decodes('code fences', "```json\n{\"title\":\"שלום\"}\n```", ['title' => 'שלום']);
    assert_decodes('json prefix', "json\n{\"clusters\":[1,2,3]}", ['clusters' => [1,2,3]]);
    assert_decodes('leading explanation', "Here is your JSON:\n{\"ok\":true}\nThanks!", ['ok' => true]);
    assert_decodes('trailing fence text', "```JSON\n[{\"title\":\"A\"}]\n```\nExtra", [['title' => 'A']]);
    assert_decodable('whitespace robustness', "    ```json\n{\n  \"value\": 42\n}\n```    ");
    echo "All normalization tests passed\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
