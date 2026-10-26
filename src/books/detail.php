<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/view.php';
require_once __DIR__ . '/../config/db.php';

$isbn = requestString($_GET, 'isbn');
if ($isbn === '') {
    http_response_code(400);
    renderPageError('ISBN buku tidak valid.', 400);
    exit;
}

try {
    $statement = getDatabaseConnection()->prepare(
        'SELECT ISBN, Title, Author, Genre, Quantity FROM Books WHERE ISBN = :isbn'
    );
    $statement->execute(['isbn' => $isbn]);
    $book = $statement->fetch();
} catch (RuntimeException $exception) {
    error_log('Book detail database error: ' . $exception->getMessage());
    renderPageError('Detail buku tidak dapat dimuat saat ini.', 503);
    exit;
}

if (!$book) {
    http_response_code(404);
    renderPageError('Buku tidak ditemukan.', 404);
    exit;
}

renderHeader((string) $book['Title'], 'books');
?>
<main class="page-main" id="main-content">
    <nav class="breadcrumbs" aria-label="Breadcrumb"><a href="/index.php">Katalog</a><span aria-hidden="true">/</span><a href="/books/list.php">Semua buku</a><span aria-hidden="true">/</span><span><?= e((string) $book['Title']) ?></span></nav>
    <div class="detail-layout">
        <div class="detail-cover cover cover-sea" aria-label="Sampul ilustratif <?= e((string) $book['Title']) ?>">
            <span class="cover-label">Pustaka<br>Digital</span><span class="cover-title"><?= e((string) $book['Title']) ?></span><span class="cover-author"><?= e((string) $book['Author']) ?></span>
        </div>
        <article class="detail-copy">
            <p class="eyebrow"><?= e((string) ($book['Genre'] ?? 'Umum')) ?> <span class="detail-separator">·</span> <?= e((string) $book['ISBN']) ?></p>
            <h1><?= e((string) $book['Title']) ?></h1>
            <p class="detail-author">oleh <strong><?= e((string) $book['Author']) ?></strong></p>
            <dl class="book-facts"><div><dt>Genre</dt><dd><?= e((string) ($book['Genre'] ?? 'Umum')) ?></dd></div><div><dt>ISBN</dt><dd><?= e((string) $book['ISBN']) ?></dd></div><div><dt>Stok</dt><dd><?= (int) $book['Quantity'] ?> eksemplar</dd></div></dl>
            <?php if ((int) $book['Quantity'] > 0): ?>
                <a class="button button-dark" href="/loans/borrow.php?isbn=<?= rawurlencode((string) $book['ISBN']) ?>">Catat peminjaman <span aria-hidden="true">↗</span></a>
            <?php else: ?>
                <p class="availability unavailable"><span class="notice-dot"></span> Tidak tersedia untuk dipinjam saat ini.</p>
            <?php endif; ?>
        </article>
    </div>
</main>
<?php renderFooter(); ?>
