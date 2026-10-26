<?php
declare(strict_types=1);

/// Escape teks untuk konteks HTML agar konten katalog tidak ditafsirkan sebagai markup.
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/// Hitung panjang teks dengan dukungan Unicode bila ekstensi mbstring tersedia.
function textLength(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

/// Ambil nilai request scalar sebagai teks; nilai array/object diabaikan.
function requestString(array $source, string $key): string
{
    $value = $source[$key] ?? '';
    return is_scalar($value) ? trim((string) $value) : '';
}

function csrfToken(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function isValidCsrfToken(array $source): bool
{
    $submittedToken = requestString($source, 'csrf_token');
    $knownToken = csrfToken();

    return $submittedToken !== '' && hash_equals($knownToken, $submittedToken);
}

function renderPageError(string $message, int $statusCode = 500): void
{
    http_response_code($statusCode);
    renderHeader('Terjadi kesalahan');
    ?>
    <main class="page-main" id="main-content">
        <section class="not-found">
            <p class="eyebrow">PUSTAKA DIGITAL</p>
            <h1>Permintaan belum dapat diproses.</h1>
            <p><?= e($message) ?></p>
            <a class="button button-dark" href="/index.php">Kembali ke katalog</a>
        </section>
    </main>
    <?php
    renderFooter();
}

/// Render dokumen awal, metadata, navigasi utama, dan tautan aset bersama.
function renderHeader(string $title, string $activePage = ''): void
{
    ?>
    <!doctype html>
    <html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#f5f3ed">
        <title><?= e($title) ?> | Pustaka Digital</title>
        <link rel="stylesheet" href="/assets/css/style.css">
        <script src="/assets/js/app.js" defer></script>
    </head>
    <body>
    <a class="skip-link" href="#main-content">Lewati ke konten</a>
    <header class="site-header">
        <div class="header-inner">
            <a class="brand" href="/index.php" aria-label="Pustaka Digital, panel pengelola">
                <span class="brand-mark" aria-hidden="true">PD</span>
                <span class="brand-name">pustaka<span>digital</span></span>
            </a>
            <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="site-nav" aria-label="Buka menu">
                <span></span><span></span>
            </button>
            <nav class="site-nav" id="site-nav" aria-label="Navigasi utama">
                <a class="<?= $activePage === 'dashboard' ? 'is-active' : '' ?>" href="/index.php">Ringkasan</a>
                <a class="<?= $activePage === 'books' ? 'is-active' : '' ?>" href="/books/list.php">Buku</a>
                <a class="<?= $activePage === 'members' ? 'is-active' : '' ?>" href="/members/list.php">Anggota</a>
                <a class="<?= $activePage === 'loan' || $activePage === 'return' ? 'is-active' : '' ?>" href="/loans/return.php">Transaksi</a>
                <a class="<?= $activePage === 'queue' ? 'is-active' : '' ?>" href="/loans/queue.php">Antrean</a>
            </nav>
        </div>
    </header>
    <?php
}

/// Render penutup halaman dan identitas proyek.
function renderFooter(): void
{
    ?>
    <footer class="site-footer">
        <div class="footer-inner">
            <a class="brand footer-brand" href="/index.php">
                <span class="brand-mark" aria-hidden="true">PD</span>
                <span class="brand-name">pustaka<span>digital</span></span>
            </a>
            <p>Panel operasional perpustakaan.</p>
            <span class="footer-note">Pengelolaan koleksi dan transaksi</span>
        </div>
    </footer>
    </body>
    </html>
    <?php
}

/// Render kartu buku beserta ketersediaan stok dan tautan detail/pinjaman.
function renderBookCard(array $book): void
{
    $available = (int) $book['stock'] > 0;
    $isbn = (string) $book['id'];
    ?>
    <article class="book-card">
        <a class="cover <?= e((string) ($book['cover'] ?? 'cover-sea')) ?>" href="/books/detail.php?isbn=<?= rawurlencode($isbn) ?>" aria-label="Lihat detail <?= e((string) $book['title']) ?>">
            <span class="cover-label">Pustaka<br>Digital</span>
            <span class="cover-title"><?= e((string) $book['title']) ?></span>
            <span class="cover-author"><?= e((string) $book['author']) ?></span>
        </a>
        <div class="book-card-body">
            <div class="book-meta"><span><?= e((string) $book['genre']) ?></span><span class="<?= $available ? 'status-available' : 'status-unavailable' ?>"><?= $available ? 'Tersedia' : 'Habis' ?></span></div>
            <h2><a href="/books/detail.php?isbn=<?= rawurlencode($isbn) ?>"><?= e((string) $book['title']) ?></a></h2>
            <p class="book-author"><?= e((string) $book['author']) ?></p>
            <div class="book-card-bottom">
                <span class="stock-count"><?= (int) $book['stock'] ?> <span>eksemplar</span></span>
                <?php if ($available): ?>
                    <a class="text-link" href="/loans/borrow.php?isbn=<?= rawurlencode($isbn) ?>">Pinjam <span aria-hidden="true">→</span></a>
                <?php else: ?>
                    <span class="text-link is-disabled">Tidak tersedia</span>
                <?php endif; ?>
            </div>
        </div>
    </article>
    <?php
}