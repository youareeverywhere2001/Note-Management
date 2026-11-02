<?php
// TV3: Trang ghi chú được chia sẻ với tôi.
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/layout.php';

$u = require_login();

$st = db()->prepare("SELECT s.id AS share_id, s.permission, n.id, n.title, n.updated_at,
        (n.password_hash IS NOT NULL) AS is_locked,
        IF(n.password_hash IS NULL, SUBSTRING(n.content, 1, 160), '') AS snippet,
        o.display_name AS owner_name, o.email AS owner_email, o.avatar AS owner_avatar
    FROM note_shares s
    JOIN notes n ON n.id = s.note_id
    JOIN users o ON o.id = n.user_id
    WHERE s.shared_with_user_id = ?
    ORDER BY n.updated_at DESC, s.id DESC");

$st->execute([$u['id']]);
$rows = $st->fetchAll();

page_header('Được chia sẻ', true, ['assets/notes.css']);
?>

<div class="shared-page">

    <div class="shared-header">
        <div>
            <h1 class="shared-title">Được chia sẻ với tôi</h1>
            <p class="shared-subtitle">
                Những ghi chú mà người khác đã chia sẻ với bạn.
            </p>
        </div>
    </div>

    <?php if (!$rows): ?>

        <div class="shared-empty">
            <div class="shared-empty-title">Chưa có ghi chú được chia sẻ</div>
            <p>Khi có người chia sẻ ghi chú với bạn, chúng sẽ xuất hiện ở đây.</p>
        </div>

    <?php else: ?>

        <div class="shared-count">
            <?= count($rows) ?> ghi chú được chia sẻ
        </div>

        <div class="shared-list" id="shared-list">

            <?php foreach ($rows as $r):
                $owner = [
                    'display_name' => $r['owner_name'],
                    'email' => $r['owner_email'],
                    'avatar' => $r['owner_avatar']
                ];
            ?>

                <article class="shared-card" data-share="<?= (int)$r['share_id'] ?>">

                    <div class="shared-card-top">

                        <div class="shared-card-content">
                            <h2 class="shared-card-title">
                                <a href="collaborate.php?id=<?= (int)$r['id'] ?>">
                                    <?= $r['title'] !== '' ? e($r['title']) : 'Không có tiêu đề' ?>
                                </a>
                            </h2>

                            <p class="shared-card-snippet">
                                <?php if ($r['is_locked']): ?>
                                    Ghi chú được bảo vệ bằng mật khẩu
                                <?php else: ?>
                                    <?= e($r['snippet']) ?>
                                <?php endif; ?>
                            </p>
                        </div>

                        <span class="shared-permission <?= $r['permission'] === 'edit' ? 'is-edit' : 'is-view' ?>">
                            <?= $r['permission'] === 'edit' ? 'Được sửa' : 'Chỉ xem' ?>
                        </span>

                    </div>

                    <div class="shared-card-divider"></div>

                    <div class="shared-card-bottom">

                        <div class="shared-owner">
                            <?= avatar_html($owner, 28) ?>

                            <div>
                                <div class="shared-owner-name">
                                    <?= e($r['owner_name'] ?: $r['owner_email']) ?>
                                </div>
                                <div class="shared-owner-email">
                                    <?= e($r['owner_email']) ?>
                                </div>
                            </div>
                        </div>

                        <div class="shared-card-actions">
                            <span class="shared-date">
                                <time data-utc="<?= e($r['updated_at']) ?>">
                                    <?= e($r['updated_at']) ?>
                                </time>
                            </span>

                            <button
                                type="button"
                                class="shared-leave-btn"
                                data-leave="<?= (int)$r['share_id'] ?>">
                                Rời khỏi
                            </button>
                        </div>

                    </div>

                </article>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>

<?php page_footer(['assets/api.js']); ?>

<script>
document.querySelectorAll('time[data-utc]').forEach(t => {
    const d = new Date(t.dataset.utc.replace(' ', 'T') + 'Z');

    if (!isNaN(d)) {
        t.textContent = d.toLocaleString('vi-VN');
    }
});

document.addEventListener('click', async e => {
    const b = e.target.closest('[data-leave]');
    if (!b) return;

    if (!confirm('Rời khỏi ghi chú được chia sẻ này? Bạn sẽ không còn truy cập được cho tới khi chủ sở hữu chia sẻ lại.')) return;

    try {
        await api('shares/delete.php', {
            method: 'POST',
            body: { share_id: +b.dataset.leave }
        });

        b.closest('article').remove();

        const list = document.getElementById('shared-list');

        if (list && !list.querySelector('article')) {
            list.outerHTML = `
                <div class="shared-empty">
                    <div class="shared-empty-title">Chưa có ghi chú được chia sẻ</div>
                    <p>Khi có người chia sẻ ghi chú với bạn, chúng sẽ xuất hiện ở đây.</p>
                </div>
            `;
        }
    } catch (err) {
        alert(err.message);
    }
});
</script>
