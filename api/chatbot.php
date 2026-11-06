<?php
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/helpers/PermissionHelper.php';
require_once dirname(__DIR__) . '/includes/gso_assistant.php';

header('Content-Type: application/json');
PermissionHelper::require('view');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    jsonResponse([
        'success' => true,
        'ai_ready' => gsoAssistantLlmConfigured(),
        'provider' => lgueEnv('GEMINI_API_KEY') !== '' ? 'gemini' : (lgueEnv('OPENAI_API_KEY') !== '' ? 'openai' : 'guide'),
    ]);
}

if ($method !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed']);
}

$hits = $_SESSION['gso_chat_hits'] ?? [];
$now = time();
$hits = array_values(array_filter($hits, static fn ($t) => is_int($t) && $t > $now - 300));
if (count($hits) >= 20) {
    jsonResponse(['success' => false, 'message' => 'Too many assistant questions. Wait a few minutes.']);
}
$hits[] = $now;
$_SESSION['gso_chat_hits'] = $hits;

$raw = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($raw)) {
    $raw = $_POST;
}
$message = trim((string) ($raw['message'] ?? ''));
if ($message === '') {
    jsonResponse(['success' => false, 'message' => 'Type a question first.']);
}
if (mb_strlen($message) > 1500) {
    $message = mb_substr($message, 0, 1500);
}

$history = [];
foreach (array_slice((array) ($raw['history'] ?? []), -6) as $turn) {
    if (!is_array($turn)) {
        continue;
    }
    $role = ($turn['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
    $content = trim((string) ($turn['content'] ?? ''));
    if ($content === '') {
        continue;
    }
    $history[] = ['role' => $role, 'content' => mb_substr($content, 0, 800)];
}

try {
    $conn = getDBConnection();
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Database connection failed']);
}

try {
    $out = gsoAssistantAsk($conn, $message, $history);
    jsonResponse([
        'success' => true,
        'answer' => $out['answer'],
        'source' => $out['source'],
        'ai_ready' => $out['ai_ready'],
    ]);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => 'Assistant could not answer right now.']);
}
