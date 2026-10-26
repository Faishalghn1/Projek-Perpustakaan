<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin.php';

$book = bookInput($_POST);
if ($book['quantity'] === '') {
    $book['quantity'] = '0';
}
$errors = [];
$message = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!isValidCsrfToken($_POST)) {
        http_response_code(400);
        $message = 'Token formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } else {
        $errors = validateBookInput($book);
        if (!$errors) {
            try {
                $statement = getDatabaseConnection()->prepare(
                    'INSERT INTO Books (ISBN, Title, Author, Genre, Quantity)
                     VALUES (:isbn, :title, :author, :genre, :quantity)'
                );
                $statement->execute([
                    'isbn' => $book['isbn'],
                    'title' => $book['title'],
                    'author' => $book['author'],
                    'genre' => $book['genre'] !== '' ? $book['genre'] : null,
                    'quantity' => (int) $book['quantity'],
                ]);
                header('Location: /books/list.php');
                exit;
            } catch (RuntimeException $exception) {
                error_log('Book creation failed: ' . $exception->getMessage());
                if ($exception->getCode() === '23000') {
                    $errors['isbn'] = 'ISBN tersebut sudah terdaftar.';
                } else {
                    renderPageError('Buku tidak dapat disimpan saat ini.', 503);
                    exit;
                }
            }
        }
    }
}

$token = csrfToken();
renderHeader('Tambah buku', 'books');
?>
<main class="page-main" id="main-content">
    <div class="page-heading"><p class="eyebrow">KOLEKSI PERPUSTAKAAN</p><h1>Tambah <em>buku</em></h1></div>
    <?php if ($message !== ''): ?><p class="form-errors" role="alert"><?= e($message) ?></p><?php endif; ?>
    <form class="admin-form" method="post" action="/books/create.php">
        <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
        <label class="form-field" for="isbn">ISBN<input id="isbn" name="isbn" maxlength="20" required value="<?= e($book['isbn']) ?>"><?php renderFieldError($errors, 'isbn'); ?></label>
        <label class="form-field" for="title">Judul<input id="title" name="title" maxlength="255" required value="<?= e($book['title']) ?>"><?php renderFieldError($errors, 'title'); ?></label>
        <label class="form-field" for="author">Penulis<input id="author" name="author" maxlength="255" required value="<?= e($book['author']) ?>"><?php renderFieldError($errors, 'author'); ?></label>
        <label class="form-field" for="genre">Genre<input id="genre" name="genre" maxlength="100" value="<?= e($book['genre']) ?>"><?php renderFieldError($errors, 'genre'); ?></label>
        <label class="form-field" for="quantity">Jumlah eksemplar<input id="quantity" name="quantity" type="number" min="0" step="1" required value="<?= e($book['quantity']) ?>"><?php renderFieldError($errors, 'quantity'); ?></label>
        <div class="form-actions"><button class="button button-dark" type="submit">Simpan buku</button><a class="text-link" href="/books/list.php">Batal</a></div>
    </form>
</main>
<?php renderFooter(); ?>
