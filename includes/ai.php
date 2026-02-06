<?php
// includes/ai.php
// DeepSeek AI helper functions + PDF text extraction

require_once __DIR__ . '/config.php';

function ai_is_configured(): bool {
    global $DEEPSEEK_API_KEY;
    return !empty($DEEPSEEK_API_KEY);
}

function deepseek_chat(string $systemPrompt, string $userMessage, array $options = []): ?string {
    global $DEEPSEEK_API_KEY;
    if (empty($DEEPSEEK_API_KEY)) {
        return null;
    }

    $maxTokens   = $options['max_tokens'] ?? 1024;
    $temperature = $options['temperature'] ?? 0.7;
    $messages    = $options['messages'] ?? [];

    // Build message array
    $apiMessages = [['role' => 'system', 'content' => $systemPrompt]];
    foreach ($messages as $msg) {
        $apiMessages[] = ['role' => $msg['role'], 'content' => $msg['content']];
    }
    $apiMessages[] = ['role' => 'user', 'content' => $userMessage];

    $payload = [
        'model'       => 'deepseek-chat',
        'messages'    => $apiMessages,
        'max_tokens'  => $maxTokens,
        'temperature' => $temperature,
    ];

    if (!empty($options['response_format'])) {
        $payload['response_format'] = $options['response_format'];
    }

    $ch = curl_init('https://api.deepseek.com/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $DEEPSEEK_API_KEY,
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error) {
        if (function_exists('log_warning')) {
            log_warning('DeepSeek API curl error', ['error' => $error]);
        }
        return null;
    }

    if ($httpCode !== 200) {
        if (function_exists('log_warning')) {
            log_warning('DeepSeek API error', ['http_code' => $httpCode, 'response' => substr($response, 0, 500)]);
        }
        return null;
    }

    $data = json_decode($response, true);
    return $data['choices'][0]['message']['content'] ?? null;
}

function deepseek_chat_json(string $systemPrompt, string $userMessage, array $options = []): ?array {
    $options['response_format'] = ['type' => 'json_object'];
    $content = deepseek_chat($systemPrompt, $userMessage, $options);
    if ($content === null) {
        return null;
    }
    $parsed = json_decode($content, true);
    return is_array($parsed) ? $parsed : null;
}

function extract_pdf_text(string $filePath, int $maxChars = 15000): ?string {
    $autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
    if (!file_exists($autoloadPath)) {
        if (function_exists('log_warning')) {
            log_warning('Composer autoloader not found for PDF parsing');
        }
        return null;
    }
    require_once $autoloadPath;

    if (!class_exists('\\Smalot\\PdfParser\\Parser')) {
        if (function_exists('log_warning')) {
            log_warning('smalot/pdfparser not installed');
        }
        return null;
    }

    try {
        $parser = new \Smalot\PdfParser\Parser();
        $pdf    = $parser->parseFile($filePath);
        $text   = $pdf->getText();
        $text   = preg_replace('/\s+/', ' ', trim($text));
        if (mb_strlen($text) > $maxChars) {
            $text = mb_substr($text, 0, $maxChars) . '...';
        }
        return $text ?: null;
    } catch (\Exception $e) {
        if (function_exists('log_warning')) {
            log_warning('PDF text extraction failed', ['error' => $e->getMessage(), 'file' => $filePath]);
        }
        return null;
    }
}
