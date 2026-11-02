<?php
// GET ?id= : chi tiết một ghi chú để mở trong trình soạn thảo
require __DIR__ . '/../../../src/api.php';
$u = api_user();
$n = load_note((int)($_GET['id'] ?? 0), (int)$u['id']);
require_note_open($n);
json_out(['note' => note_detail((int)$n['id'], (int)$u['id'])]);
