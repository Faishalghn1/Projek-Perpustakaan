<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin.php';

$message = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!isValidCsrfToken($_POST)) {
        http_response_code(400);
        $message = 'Token formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } elseif (requestString($_POST, 'action') === 'delete') {
        $isbn = requestString($_POST, 'isbn');
        try {
            $statement = getDatabaseConnection()->prepare('DELETE FROM Books WHERE ISBN = :isbn');
            $statement->execute(['isbn' => $isbn]);
            $message = $statement->rowCount() === 1
                ? 'Buku berhasil dihapus.'
                : 'Buku tersebut tidak ditemukan.';
        } catch (RuntimeException $exception) {
            error_log('Book deletion failed: ' . $exception->getMessage());
            if ($exception->getCode() === '23000') {
                http_response_code(409);
                $message = 'Buku tidak dapat dihapus karena masih tercatat pada transaksi peminjaman.';
            } else {
                renderPageError('Data buku tidak dapat dihapus saat ini.', 503);
                exit;
            }
        }
    }
}

try {
    $books = getDatabaseConnection()->query(
        'SELECT ISBN, Title, Author, Genre, Quantity FROM Books ORDER BY Title'
    )->fetchAll();
} catch (RuntimeException $exception) {
    error_log('Book list database error: ' . $exception->getMessage());
    renderPageError('Daftar buku tidak dapat dimuat. Pastikan layanan database sudah berjalan.', 503);
    exit;
}

$token = csrfToken();
renderHeader('Daftar buku', 'books');
?>
<main class="page-main" id="main-content">
    <div class="page-heading">
        <p class="eyebrow">PENGELOLAAN KOLEKSI</p>
        <h1>Daftar <em>buku</em></h1>
        <p>Kelola koleksi buku perpustakaan dan jumlah eksemplarnya.</p>
    </div>
    <?php if ($message !== ''): ?><p class="form-feedback" role="status"><?= e($message) ?></p><?php endif; ?>
    <p><a class="button button-dark" href="/books/create.php">Tambah buku <span aria-hidden="true">+</span></a></p>
    <?php if ($books): ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>ISBN</th><th>Judul</th><th>Penulis</th><th>Genre</th><th>Stok</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($books as $book): ?>
                    <tr>
                        <td><?= e((string) $book['ISBN']) ?></td>
                        <td><?= e((string) $book['Title']) ?></td>
                        <td><?= e((string) $book['Author']) ?></td>
                        <td><?= e((string) ($book['Genre'] ?? '')) ?></td>
                        <td><?= (int) $book['Quantity'] ?></td>
                        <td class="table-actions">
                            <a class="text-link" href="/books/edit.php?isbn=<?= rawurlencode((string) $book['ISBN']) ?>">Ubah</a>
                            <form method="post" action="/books/list.php" onsubmit="return confirm('Hapus buku ini?')">
                                <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="isbn" value="<?= e((string) $book['ISBN']) ?>">
                                <button class="text-button" type="submit">Hapus</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state"><h2>Belum ada buku</h2><p>Tambahkan buku pertama ke koleksi perpustakaan.</p></div>
    <?php endif; ?>
</main>
<?php renderFooter(); ?>
