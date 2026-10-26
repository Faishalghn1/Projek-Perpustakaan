<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/view.php';
require_once __DIR__ . '/config/db.php';

try {
    $connection = getDatabaseConnection();
    $counts = [
        'titles' => (int) $connection->query('SELECT COUNT(*) FROM Books')->fetchColumn(),
        'copies' => (int) $connection->query('SELECT COALESCE(SUM(Quantity), 0) FROM Books')->fetchColumn(),
        'members' => (int) $connection->query('SELECT COUNT(*) FROM Members')->fetchColumn(),
        'loans' => (int) $connection->query('SELECT COUNT(*) FROM Loans WHERE ReturnDate IS NULL')->fetchColumn(),
    ];
    $recentLoans = $connection->query(
        'SELECT Loans.LoanID, Loans.LoanDate, Books.Title, Members.Name
         FROM Loans
         INNER JOIN Books ON Books.ISBN = Loans.ISBN
         INNER JOIN Members ON Members.MemberID = Loans.MemberID
         WHERE Loans.ReturnDate IS NULL
         ORDER BY Loans.LoanDate DESC, Loans.LoanID DESC
         LIMIT 5'
    )->fetchAll();
    $lowStockBooks = $connection->query(
        'SELECT ISBN, Title, Quantity FROM Books ORDER BY Quantity ASC, Title ASC LIMIT 5'
    )->fetchAll();
} catch (RuntimeException $exception) {
    error_log('Dashboard database error: ' . $exception->getMessage());
    renderPageError('Data ringkasan tidak dapat dimuat. Pastikan layanan database sudah berjalan.', 503);
    exit;
}

renderHeader('Ringkasan pengelola', 'dashboard');
?>
<main class="page-main dashboard-main" id="main-content">
    <section class="dashboard-welcome">
        <div>
            <p class="eyebrow">PANEL PENGELOLA PERPUSTAKAAN</p>
            <h1>Ringkasan <em>operasional</em></h1>
            <p>Pantau koleksi, anggota, dan transaksi aktif dari satu tempat.</p>
        </div>
        <a class="button button-dark" href="/loans/borrow.php">Catat peminjaman <span aria-hidden="true">+</span></a>
    </section>

    <section class="dashboard-stats" aria-label="Statistik perpustakaan">
        <a class="stat-card" href="/books/list.php"><span>Total judul buku</span><strong><?= $counts['titles'] ?></strong><small>Kelola koleksi <span aria-hidden="true">→</span></small></a>
        <a class="stat-card" href="/books/list.php"><span>Total eksemplar</span><strong><?= $counts['copies'] ?></strong><small>Jumlah stok tercatat</small></a>
        <a class="stat-card" href="/members/list.php"><span>Anggota terdaftar</span><strong><?= $counts['members'] ?></strong><small>Kelola anggota <span aria-hidden="true">→</span></small></a>
        <a class="stat-card stat-card-accent" href="/loans/return.php"><span>Peminjaman aktif</span><strong><?= $counts['loans'] ?></strong><small>Proses pengembalian <span aria-hidden="true">→</span></small></a>
    </section>

    <section class="dashboard-quick-actions" aria-label="Aksi cepat">
        <a href="/books/create.php"><span aria-hidden="true">＋</span><span><strong>Tambah buku</strong><small>Daftarkan koleksi baru</small></span></a>
        <a href="/members/create.php"><span aria-hidden="true">＋</span><span><strong>Daftarkan anggota</strong><small>Buat data anggota</small></span></a>
        <a href="/loans/borrow.php"><span aria-hidden="true">↗</span><span><strong>Pinjamkan buku</strong><small>Catat transaksi pinjam</small></span></a>
        <a href="/loans/return.php"><span aria-hidden="true">↩</span><span><strong>Proses pengembalian</strong><small>Perbarui transaksi aktif</small></span></a>
    </section>

    <div class="dashboard-columns">
        <section class="dashboard-panel">
            <div class="dashboard-panel-heading"><div><p class="eyebrow">PERLU DITINDAKLANJUTI</p><h2>Peminjaman aktif</h2></div><a class="text-link" href="/loans/return.php">Lihat semua <span aria-hidden="true">→</span></a></div>
            <?php if ($recentLoans): ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Anggota</th><th>Buku</th><th>Tanggal pinjam</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentLoans as $loan): ?>
                            <tr><td><?= e((string) $loan['Name']) ?></td><td><?= e((string) $loan['Title']) ?></td><td><?= e((string) $loan['LoanDate']) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="dashboard-empty">Tidak ada peminjaman aktif saat ini.</div>
            <?php endif; ?>
        </section>

        <section class="dashboard-panel">
            <div class="dashboard-panel-heading"><div><p class="eyebrow">PEMANTAUAN STOK</p><h2>Stok buku terendah</h2></div><a class="text-link" href="/books/list.php">Kelola buku <span aria-hidden="true">→</span></a></div>
            <?php if ($lowStockBooks): ?>
                <ul class="stock-watch">
                    <?php foreach ($lowStockBooks as $book): ?>
                        <li><span><strong><?= e((string) $book['Title']) ?></strong><small><?= e((string) $book['ISBN']) ?></small></span><b class="<?= (int) $book['Quantity'] === 0 ? 'stock-empty' : '' ?>"><?= (int) $book['Quantity'] ?> <small>tersedia</small></b></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="dashboard-empty">Belum ada data buku. <a href="/books/create.php">Tambah buku pertama</a>.</div>
            <?php endif; ?>
        </section>
    </div>
</main>
<?php renderFooter(); ?>
