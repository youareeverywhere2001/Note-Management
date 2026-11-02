<?php
// GET: danh sách ghi chú. Tham số: q (tìm trong tiêu đề + nội dung), labels (id nhãn, cách nhau bằng dấu phẩy), sort (updated|created)
require __DIR__ . '/../../../src/api.php';
$u = api_user();
$uid = (int)$u['id'];
session_write_close(); // không giữ khóa session khi chỉ đọc

$q = trim((string)($_GET['q'] ?? ''));
$sortCol = ($_GET['sort'] ?? 'updated') === 'created' ? 'n.created_at' : 'n.updated_at';
$labelIds = array_values(array_filter(array_map('intval', explode(',', (string)($_GET['labels'] ?? ''))), fn($x) => $x > 0));

$sql = "SELECT n.id, n.title, n.is_pinned, n.created_at, n.updated_at,
               (n.password_hash IS NOT NULL) AS is_locked,
               IF(n.password_hash IS NULL, SUBSTRING(n.content, 1, 300), '') AS snippet,
               (SELECT GROUP_CONCAT(nl.label_id) FROM note_labels nl WHERE nl.note_id = n.id) AS label_ids,
               (SELECT COUNT(*) FROM attachments a WHERE a.note_id = n.id) AS attach_count,
               EXISTS (SELECT 1 FROM note_shares s WHERE s.note_id = n.id) AS is_shared
        FROM notes n WHERE n.user_id = ?";
$args = [$uid];

if ($q !== '') {
    // Escape ký tự đặc biệt của LIKE (\ % _). Ghi chú bị khóa chỉ được tìm theo tiêu đề, không lộ nội dung.
    $like = '%' . addcslashes($q, '\\%_') . '%';
    $sql .= ' AND (n.title LIKE ? OR (n.password_hash IS NULL AND n.content LIKE ?))';
    array_push($args, $like, $like);
}
if ($labelIds) {
    $in = implode(',', array_fill(0, count($labelIds), '?'));
    $sql .= " AND EXISTS (SELECT 1 FROM note_labels x WHERE x.note_id = n.id AND x.label_id IN ($in))";
    $args = array_merge($args, $labelIds);
}
// Ghim lên đầu (ghim sau xếp trước), còn lại theo thời gian mới nhất
$sql .= " ORDER BY n.is_pinned DESC, n.pinned_at DESC, $sortCol DESC, n.id DESC LIMIT 500";

$st = db()->prepare($sql);
$st->execute($args);
$notes = array_map(fn($r) => [
    'id'           => (int)$r['id'],
    'title'        => $r['title'],
    'snippet'      => $r['snippet'],
    'is_pinned'    => (bool)$r['is_pinned'],
    'is_locked'    => (bool)$r['is_locked'],
    'is_shared'    => (bool)$r['is_shared'],   // TV3: ghi chú này đang được chia sẻ cho người khác (bảng note_shares)
    'label_ids'    => $r['label_ids'] === null ? [] : array_map('intval', explode(',', $r['label_ids'])),
    'attach_count' => (int)$r['attach_count'],
    'created_at'   => $r['created_at'],
    'updated_at'   => $r['updated_at'],
], $st->fetchAll());
json_out(['notes' => $notes]);
