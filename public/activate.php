<?php
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/layout.php';
$token = $_GET['token'] ?? '';
page_header('Kích hoạt tài khoản', (bool)current_user());
?>
<div class="card auth-card"><div class="card-body p-4 text-center">
<h1 class="h4 mb-3">Kích hoạt tài khoản</h1>
<div id="activation-result" class="alert alert-info">Đang xác thực liên kết...</div>
<a class="btn btn-primary" href="login.php">Đăng nhập</a>
</div></div>
<script>
fetch('api/auth/activate.php?token=<?= e(rawurlencode($token)) ?>')
 .then(r => r.json())
 .then(data => {
   const box = document.getElementById('activation-result');
   box.className = 'alert ' + (data.success ? 'alert-success' : 'alert-danger');
   box.textContent = data.message;
 })
 .catch(() => { document.getElementById('activation-result').textContent = 'Không thể kết nối máy chủ.'; });
</script>
<?php page_footer();
