<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin.php';

$isbn = requestString($_GET, 'isbn');
$book = bookInput($_POST);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $isbn = $book['isbn'];
}
$errors = [];
$message = '';

try {
    $connection = getDatabaseConnection();
    $statement = $connection->prepare('SELECT ISBN, Title, Author, Genre, Quantity FROM Books WHERE ISBN = :isbn');
    $statement->execute(['isbn' => $isbn]);
    $existingBook = $statement->fetch();
} catch (RuntimeException $exception) {
    error_log('Book edit lookup failed: ' . $exception->getMessage());
    renderPageError('Data buku tidak dapat dimuat saat ini.', 503);
    exit;
}

if (!$existingBook) {
    renderPageError('Buku tidak ditemukan.', 404);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    $book = [
        'isbn' => (string) $existingBook['ISBN'],
        'title' => (string) $existingBook['Title'],
        'author' => (string) $existingBook['Author'],
        'genre' => (string) ($existingBook['Genre'] ?? ''),
        'quantity' => (string) $existingBook['Quantity'],
    ];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!isValidCsrfToken($_POST)) {
        http_response_code(400);
        $message = 'Token formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } else {
        $errors = validateBookInput($book);
        if (!$errors) {
            try {
                $statement = $connection->prepare(
                    'UPDATE Books SET Title = :title, Author = :author, Genre = :genre, Quantity = :quantity
                     WHERE ISBN = :isbn'
                );
                $statement->execute([
                    'title' => $book['title'],
                    'author' => $book['author'],
                    'genre' => $book['genre'] !== '' ? $book['genre'] : null,
                    'quantity' => (int) $book['quantity'],
                    'isbn' => $isbn,
                ]);
                header('Location: /books/list.php');
                exit;
            } catch (RuntimeException $exception) {
                error_log('Book update failed: ' . $exception->getMessage());
                renderPageError('Perubahan buku tidak dapat disimpan saat ini.', 503);
                exit;
            }
        }
    }
}

$token = csrfToken();
renderHeader('Ubah buku', 'books');
?>
<main class="page-main" id="main-content">
    <div class="page-heading"><p class="eyebrow">KOLEKSI PERPUSTAKAAN</p><h1>Ubah <em>buku</em></h1></div>
    <?php if ($message !== ''): ?><p class="form-errors" role="alert"><?= e($message) ?></p><?php endif; ?>
    <form class="admin-form" method="post" action="/books/edit.php?isbn=<?= rawurlencode($isbn) ?>">
        <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
        <input type="hidden" name="isbn" value="<?= e($isbn) ?>">
        <p class="form-field"><span>ISBN</span><strong><?= e($isbn) ?></strong></p>
        <label class="form-field" for="title">Judul<input id="title" name="title" maxlength="255" required value="<?= e($book['title']) ?>"><?php renderFieldError($errors, 'title'); ?></label>
        <label class="form-field" for="author">Penulis<input id="author" name="author" maxlength="255" required value="<?= e($book['author']) ?>"><?php renderFieldError($errors, 'author'); ?></label>
        <label class="form-field" for="genre">Genre<input id="genre" name="genre" maxlength="100" value="<?= e($book['genre']) ?>"><?php renderFieldError($errors, 'genre'); ?></label>
        <label class="form-field" for="quantity">Jumlah eksemplar<input id="quantity" name="quantity" type="number" min="0" step="1" required value="<?= e($book['quantity']) ?>"><?php renderFieldError($errors, 'quantity'); ?></label>
        <div class="form-actions"><button class="button button-dark" type="submit">Simpan perubahan</button><a class="text-link" href="/books/list.php">Batal</a></div>
    </form>
</main>
<?php renderFooter(); ?>
