<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isbn     = trim($_POST['isbn'] ?? '');
    $title    = trim($_POST['title'] ?? '');
    $author   = trim($_POST['author'] ?? '');
    $genre    = trim($_POST['genre'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 0);

    if ($isbn === '' || $title === '' || $author === '' || $quantity < 0) {
        $error = 'ISBN, judul, penulis wajib diisi dan stok tidak boleh negatif.';
    } else {
        $sql = 'INSERT INTO Books (ISBN, Title, Author, Genre, Quantity)
                VALUES (:isbn, :title, :author, :genre, :quantity)';
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':isbn'     => $isbn,
                ':title'    => $title,
                ':author'   => $author,
                ':genre'    => $genre !== '' ? $genre : null,
                ':quantity' => $quantity,
            ]);
            header('Location: list.php');
            exit;
        } catch (PDOException $e) {
            $error = 'Gagal menyimpan buku (ISBN mungkin sudah terdaftar).';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Buku - Pustaka Digital</title>
    <style>
        body { font-family: sans-serif; max-width: 600px; margin: 2rem auto; padding: 0 1rem; }
        label { display: block; margin-top: 0.75rem; }
        input { width: 100%; padding: 0.4rem; box-sizing: border-box; }
        button { margin-top: 1rem; padding: 0.5rem 1rem; }
        .err { color: #b00020; }
    </style>
</head>
<body>
    <h1>Tambah Buku Baru</h1>
    <?php if ($error !== ''): ?>
        <p class="err"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <form method="post">
        <label>ISBN <input name="isbn" required></label>
        <label>Judul <input name="title" required></label>
        <label>Penulis <input name="author" required></label>
        <label>Genre <input name="genre"></label>
        <label>Jumlah Stok <input type="number" name="quantity" min="0" value="1" required></label>
        <button type="submit">Simpan</button>
        <a href="list.php">Batal</a>
    </form>
</body>
</html>
