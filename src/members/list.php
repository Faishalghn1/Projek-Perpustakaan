<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin.php';

$message = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!isValidCsrfToken($_POST)) {
        http_response_code(400);
        $message = 'Token formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } elseif (requestString($_POST, 'action') === 'delete') {
        $memberId = filter_var(requestString($_POST, 'member_id'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($memberId === false) {
            http_response_code(400);
            $message = 'Nomor anggota tidak valid.';
        } else {
            try {
                $statement = getDatabaseConnection()->prepare('DELETE FROM Members WHERE MemberID = :member_id');
                $statement->execute(['member_id' => $memberId]);
                $message = $statement->rowCount() === 1
                    ? 'Anggota berhasil dihapus.'
                    : 'Anggota tersebut tidak ditemukan.';
            } catch (RuntimeException $exception) {
                error_log('Member deletion failed: ' . $exception->getMessage());
                if ($exception->getCode() === '23000') {
                    http_response_code(409);
                    $message = 'Anggota tidak dapat dihapus karena masih memiliki catatan peminjaman.';
                } else {
                    renderPageError('Data anggota tidak dapat dihapus saat ini.', 503);
                    exit;
                }
            }
        }
    }
}

try {
    $members = getDatabaseConnection()->query(
        'SELECT MemberID, Name, Email, Phone FROM Members ORDER BY Name'
    )->fetchAll();
} catch (RuntimeException $exception) {
    error_log('Member list database error: ' . $exception->getMessage());
    renderPageError('Daftar anggota tidak dapat dimuat. Pastikan layanan database sudah berjalan.', 503);
    exit;
}

$token = csrfToken();
renderHeader('Daftar anggota', 'members');
?>
<main class="page-main" id="main-content">
    <div class="page-heading"><p class="eyebrow">PENGELOLAAN ANGGOTA</p><h1>Daftar <em>anggota</em></h1><p>Daftar anggota terdaftar di perpustakaan.</p></div>
    <?php if ($message !== ''): ?><p class="form-feedback" role="status"><?= e($message) ?></p><?php endif; ?>
    <p><a class="button button-dark" href="/members/create.php">Daftarkan anggota <span aria-hidden="true">+</span></a></p>
    <?php if ($members): ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>ID</th><th>Nama</th><th>Email</th><th>Telepon</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($members as $member): ?>
                    <tr>
                        <td><?= (int) $member['MemberID'] ?></td>
                        <td><?= e((string) $member['Name']) ?></td>
                        <td><?= e((string) ($member['Email'] ?? '')) ?></td>
                        <td><?= e((string) ($member['Phone'] ?? '')) ?></td>
                        <td class="table-actions">
                            <a class="text-link" href="/members/edit.php?member_id=<?= (int) $member['MemberID'] ?>">Ubah</a>
                            <form method="post" action="/members/list.php" onsubmit="return confirm('Hapus anggota ini?')">
                                <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="member_id" value="<?= (int) $member['MemberID'] ?>">
                                <button class="text-button" type="submit">Hapus</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state"><h2>Belum ada anggota</h2><p>Daftarkan anggota pertama perpustakaan.</p></div>
    <?php endif; ?>
</main>
<?php renderFooter(); ?>
