<?php

return [
    'base_url' => env('LLM_BASE_URL', 'https://api.tokito.xyz/v1'),
    'api_key' => env('LLM_API_KEY'),
    'model' => env('LLM_MODEL', 'auto'),
    'timeout' => (int) env('LLM_TIMEOUT', 60),
    'max_output_tokens' => (int) env('LLM_MAX_OUTPUT_TOKENS', 1500),
];
