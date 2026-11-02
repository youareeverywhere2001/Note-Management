<?php
require_once __DIR__ . '/api.php';

const AI_MAX_CHARS = 20000;   

function ai_rate_limit(): void {
    $now = time();
    $calls = array_values(array_filter($_SESSION['ai_calls'] ?? [], fn($t) => $t > $now - 60));
    if (count($calls) >= 8) json_error('Bạn gửi yêu cầu AI quá nhanh. Hãy thử lại sau ít phút.', 429);
    $calls[] = $now;
    $_SESSION['ai_calls'] = $calls;
}

function ai_load_note(int $noteId, int $uid): array {
    $n = load_note_access($noteId, $uid, 'read');
    require_note_open($n);
    if (trim((string)$n['content']) === '') json_error('Ghi chú đang trống nên AI không có gì để xử lý.', 422);
    return $n;
}
function ai_note_text(array $n): string {
    return mb_substr((string)$n['content'], 0, AI_MAX_CHARS);
}

function gemini_call(string $system, string $user, bool $json = false): string {
    if (!function_exists('curl_init')) json_error('Máy chủ PHP chưa bật extension cURL.', 500);
    $key = env('GEMINI_API_KEY');
    if (!$key) json_error('Tính năng AI chưa được cấu hình (thiếu GEMINI_API_KEY trên máy chủ).', 503);
    $model = env('GEMINI_MODEL', 'gemini-3.8-flash');
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';

    $body = [
        'systemInstruction' => ['parts' => [['text' => $system]]],
        'contents' => [['role' => 'user', 'parts' => [['text' => $user]]]],
    ];
    if ($json) $body['generationConfig'] = ['responseMimeType' => 'application/json'];
    $payload = json_encode($body, JSON_UNESCAPED_UNICODE);

    session_write_close();  
    $resp = false; $code = 0; $err = '';
    for ($i = 0; $i < 3; $i++) {   
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
            CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 45,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $key],   // khóa nằm trong header, không nằm trên URL
            CURLOPT_POSTFIELDS => $payload,
        ]);
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($resp !== false && $code !== 503) break;
        if ($i < 2) sleep(2);
    }
    if ($resp === false) { error_log('Gemini cURL: ' . $err); json_error('Không kết nối được dịch vụ AI. Hãy thử lại sau.', 502); }
    if ($code === 429) json_error('Dịch vụ AI đã hết hạn mức sử dụng. Vui lòng thử lại sau.', 429);
    if ($code === 503) json_error('Dịch vụ AI đang quá tải. Vui lòng thử lại sau.', 503);
    if ($code !== 200) { error_log('Gemini HTTP ' . $code . ': ' . substr((string)$resp, 0, 500)); json_error('Dịch vụ AI trả về lỗi (mã ' . $code . ').', 502); }

    $r = json_decode((string)$resp, true);
    $text = $r['candidates'][0]['content']['parts'][0]['text'] ?? null;
    if (!is_string($text) || trim($text) === '') json_error('AI không trả về nội dung (có thể bị chặn bởi bộ lọc). Hãy thử lại.', 502);
    return trim($text);
}
