<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_isbn'])) {
    $delIsbn = trim($_POST['delete_isbn']);
    try {
        $del = $pdo->prepare('DELETE FROM Books WHERE ISBN = :isbn');
        $del->execute([':isbn' => $delIsbn]);
        $msg = 'Buku berhasil dihapus.';
    } catch (PDOException $e) {
        $msg = 'Gagal menghapus buku karena masih terkait dengan riwayat peminjaman.';
    }
}

$stmt  = $pdo->query('SELECT ISBN, Title, Author, Genre, Quantity FROM Books ORDER BY Title');
$books = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar Buku - Pustaka Digital</title>
    <style>
        body { font-family: sans-serif; max-width: 900px; margin: 2rem auto; padding: 0 1rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { border: 1px solid #ccc; padding: 0.5rem; text-align: left; }
        th { background: #f0f0f0; }
    </style>
</head>
<body>
    <h1>Daftar Buku Perpustakaan</h1>
    <p><a href="../index.php">&larr; Katalog Publik</a> | <a href="create.php">+ Tambah Buku Baru</a></p>
    <?php if ($msg !== ''): ?>
        <p><strong><?= htmlspecialchars($msg) ?></strong></p>
    <?php endif; ?>
    <table>
        <thead>
            <tr>
                <th>ISBN</th><th>Judul</th><th>Penulis</th><th>Genre</th><th>Stok</th><th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($books as $b): ?>
            <tr>
                <td><?= htmlspecialchars($b['ISBN']) ?></td>
                <td><?= htmlspecialchars($b['Title']) ?></td>
                <td><?= htmlspecialchars($b['Author']) ?></td>
                <td><?= htmlspecialchars($b['Genre'] ?? '-') ?></td>
                <td><?= (int)$b['Quantity'] ?></td>
                <td>
                    <a href="edit.php?isbn=<?= urlencode($b['ISBN']) ?>">Ubah</a> |
                    <form method="post" style="display:inline;" onsubmit="return confirm('Hapus buku ini?');">
                        <input type="hidden" name="delete_isbn" value="<?= htmlspecialchars($b['ISBN']) ?>">
                        <button type="submit">Hapus</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
