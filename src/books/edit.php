<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';

$isbn  = trim($_GET['isbn'] ?? '');
$error = '';

$stmt = $pdo->prepare('SELECT ISBN, Title, Author, Genre, Quantity FROM Books WHERE ISBN = :isbn');
$stmt->execute([':isbn' => $isbn]);
$book = $stmt->fetch();

if (!$book) {
    exit('Buku tidak ditemukan. <a href="list.php">Kembali</a>');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title    = trim($_POST['title'] ?? '');
    $author   = trim($_POST['author'] ?? '');
    $genre    = trim($_POST['genre'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 0);

    if ($title === '' || $author === '' || $quantity < 0) {
        $error = 'Judul, penulis wajib diisi dan stok tidak boleh negatif.';
    } else {
        $upd = $pdo->prepare('UPDATE Books SET Title = :t, Author = :a, Genre = :g, Quantity = :q WHERE ISBN = :isbn');
        $upd->execute([
            ':t'    => $title,
            ':a'    => $author,
            ':g'    => $genre !== '' ? $genre : null,
            ':q'    => $quantity,
            ':isbn' => $isbn,
        ]);
        header('Location: list.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Ubah Buku - Pustaka Digital</title>
    <style>
        body { font-family: sans-serif; max-width: 600px; margin: 2rem auto; padding: 0 1rem; }
        label { display: block; margin-top: 0.75rem; }
        input { width: 100%; padding: 0.4rem; box-sizing: border-box; }
        button { margin-top: 1rem; padding: 0.5rem 1rem; }
        .err { color: #b00020; }
    </style>
</head>
<body>
    <h1>Ubah Data Buku (<?= htmlspecialchars($book['ISBN']) ?>)</h1>
    <?php if ($error !== ''): ?>
        <p class="err"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <form method="post">
        <label>Judul <input name="title" value="<?= htmlspecialchars($book['Title']) ?>" required></label>
        <label>Penulis <input name="author" value="<?= htmlspecialchars($book['Author']) ?>" required></label>
        <label>Genre <input name="genre" value="<?= htmlspecialchars($book['Genre'] ?? '') ?>"></label>
        <label>Jumlah Stok <input type="number" name="quantity" min="0" value="<?= (int)$book['Quantity'] ?>" required></label>
        <button type="submit">Perbarui</button>
        <a href="list.php">Batal</a>
    </form>
</body>
</html>
