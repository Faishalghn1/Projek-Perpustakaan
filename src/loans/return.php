<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin.php';

$action = requestString($_POST, 'action');
$loanId = requestString($_POST, 'loan_id');
$errors = [];
$message = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!isValidCsrfToken($_POST)) {
        http_response_code(400);
        $message = 'Token formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } elseif (!in_array($action, ['return', 'renew'], true)) {
        http_response_code(400);
        $message = 'Jenis transaksi tidak valid.';
    } else {
        $validatedLoanId = filter_var($loanId, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($validatedLoanId === false) {
            $errors['loan_id'] = 'Pilih transaksi peminjaman yang valid.';
        } else {
            $connection = null;
            try {
                $connection = getDatabaseConnection();
                $connection->beginTransaction();

                $identityStatement = $connection->prepare(
                    'SELECT MemberID FROM Loans WHERE LoanID = :loan_id'
                );
                $identityStatement->execute(['loan_id' => $validatedLoanId]);
                $loanIdentity = $identityStatement->fetch();
                if (!$loanIdentity) {
                    $errors['loan_id'] = 'Transaksi peminjaman tidak ditemukan.';
                } else {
                    $memberLock = $connection->prepare(
                        'SELECT MemberID FROM Members WHERE MemberID = :member_id FOR UPDATE'
                    );
                    $memberLock->execute(['member_id' => $loanIdentity['MemberID']]);
                    $loanStatement = $connection->prepare(
                        'SELECT LoanID, MemberID, ISBN, DueDate, RenewalCount
                         FROM Loans
                         WHERE LoanID = :loan_id AND ReturnDate IS NULL
                         FOR UPDATE'
                    );
                    $loanStatement->execute(['loan_id' => $validatedLoanId]);
                    $loan = $loanStatement->fetch();

                    if (!$loan) {
                        $errors['loan_id'] = 'Peminjaman tidak ditemukan atau sudah dikembalikan.';
                    } elseif ($action === 'renew') {
                        if ((int) $loan['RenewalCount'] >= 1) {
                            $errors['loan_id'] = 'Pinjaman ini sudah menggunakan satu kali perpanjangan.';
                        } elseif ((string) $loan['DueDate'] < date('Y-m-d')) {
                            $errors['loan_id'] = 'Pinjaman yang sudah terlambat tidak dapat diperpanjang.';
                        } else {
                            $queueStatement = $connection->prepare(
                                "SELECT COUNT(*)
                                 FROM Reservations
                                 WHERE ISBN = :isbn AND Status IN ('waiting', 'ready')"
                            );
                            $queueStatement->execute(['isbn' => $loan['ISBN']]);
                            if ((int) $queueStatement->fetchColumn() > 0) {
                                $errors['loan_id'] = 'Pinjaman tidak dapat diperpanjang karena judul ini memiliki antrean reservasi.';
                            } else {
                                $renewalStatement = $connection->prepare(
                                    'UPDATE Loans
                                     SET DueDate = DATE_ADD(DueDate, INTERVAL ' . LOAN_PERIOD_DAYS . ' DAY),
                                         RenewalCount = RenewalCount + 1
                                     WHERE LoanID = :loan_id
                                       AND ReturnDate IS NULL
                                       AND RenewalCount = 0
                                       AND DueDate >= CURRENT_DATE'
                                );
                                $renewalStatement->execute(['loan_id' => $validatedLoanId]);
                                if ($renewalStatement->rowCount() !== 1) {
                                    throw new RuntimeException('Loan changed while processing its renewal.');
                                }
                            }
                        }
                    } else {
                        $fineStatement = $connection->prepare(
                            'SELECT GREATEST(DATEDIFF(CURRENT_DATE, DueDate), 0) * '
                                . DAILY_LATE_FINE . ' AS FineAmount
                             FROM Loans WHERE LoanID = :loan_id'
                        );
                        $fineStatement->execute(['loan_id' => $validatedLoanId]);
                        $fineAmount = (int) $fineStatement->fetchColumn();
                        $returnStatement = $connection->prepare(
                            'UPDATE Loans
                             SET ReturnDate = CURRENT_DATE, FineAmount = :fine_amount
                             WHERE LoanID = :loan_id AND ReturnDate IS NULL'
                        );
                        $returnStatement->execute([
                            'fine_amount' => $fineAmount,
                            'loan_id' => $validatedLoanId,
                        ]);
                        if ($returnStatement->rowCount() !== 1) {
                            throw new RuntimeException('Loan return state changed while processing the return.');
                        }

                        $bookStatement = $connection->prepare(
                            'UPDATE Books SET Quantity = Quantity + 1 WHERE ISBN = :isbn'
                        );
                        $bookStatement->execute(['isbn' => $loan['ISBN']]);
                        if ($bookStatement->rowCount() !== 1) {
                            throw new RuntimeException('The book associated with the loan no longer exists.');
                        }

                        $queueStatement = $connection->prepare(
                            "SELECT ReservationID, MemberID
                             FROM Reservations
                             WHERE ISBN = :isbn AND Status = 'waiting'
                             ORDER BY ReservedAt, ReservationID
                             LIMIT 1
                             FOR UPDATE"
                        );
                        $queueStatement->execute(['isbn' => $loan['ISBN']]);
                        $nextReservation = $queueStatement->fetch();
                        if ($nextReservation) {
                            $readyStatement = $connection->prepare(
                                "UPDATE Reservations
                                 SET Status = 'ready', ReadyAt = CURRENT_TIMESTAMP
                                 WHERE ReservationID = :reservation_id AND Status = 'waiting'"
                            );
                            $readyStatement->execute(['reservation_id' => $nextReservation['ReservationID']]);
                            $reserveCopy = $connection->prepare(
                                'UPDATE Books SET Quantity = Quantity - 1 WHERE ISBN = :isbn AND Quantity > 0'
                            );
                            $reserveCopy->execute(['isbn' => $loan['ISBN']]);
                            if ($readyStatement->rowCount() !== 1 || $reserveCopy->rowCount() !== 1) {
                                throw new RuntimeException('Reservation queue changed while a returned copy was assigned.');
                            }
                        }
                    }
                }

                if ($errors) {
                    $connection->rollBack();
                    http_response_code(422);
                } else {
                    $connection->commit();
                    if ($action === 'renew') {
                        $message = 'Perpanjangan berhasil. Jatuh tempo diperbarui '
                            . LOAN_PERIOD_DAYS . ' hari; pinjaman hanya dapat diperpanjang satu kali.';
                    } else {
                        $message = 'Pengembalian berhasil dicatat. Denda: Rp'
                            . number_format($fineAmount, 0, ',', '.') . '.';
                        if (isset($nextReservation) && $nextReservation) {
                            $message .= ' Buku dialokasikan kepada anggota berikutnya dalam antrean.';
                        }
                    }
                }
            } catch (RuntimeException $exception) {
                if ($connection instanceof PDO && $connection->inTransaction()) {
                    $connection->rollBack();
                }
                error_log('Loan return or renewal failed: ' . $exception->getMessage());
                renderPageError('Transaksi pengembalian atau perpanjangan tidak dapat disimpan saat ini.', 503);
                exit;
            }
        }
    }
}

try {
    $loans = getDatabaseConnection()->query(
        "SELECT Loans.LoanID, Loans.MemberID, Loans.ISBN, Loans.LoanDate, Loans.DueDate,
                Loans.RenewalCount, Books.Title, Members.Name,
                GREATEST(DATEDIFF(CURRENT_DATE, Loans.DueDate), 0) AS LateDays,
                EXISTS (
                    SELECT 1 FROM Reservations
                    WHERE Reservations.ISBN = Loans.ISBN
                      AND Reservations.Status IN ('waiting', 'ready')
                ) AS HasQueue
         FROM Loans
         INNER JOIN Books ON Books.ISBN = Loans.ISBN
         INNER JOIN Members ON Members.MemberID = Loans.MemberID
         WHERE Loans.ReturnDate IS NULL
         ORDER BY Loans.DueDate, Loans.LoanDate, Loans.LoanID"
    )->fetchAll();
    $recentReturns = getDatabaseConnection()->query(
        'SELECT Loans.LoanID, Loans.LoanDate, Loans.DueDate, Loans.ReturnDate,
                Loans.FineAmount, Books.Title, Members.Name
         FROM Loans
         INNER JOIN Books ON Books.ISBN = Loans.ISBN
         INNER JOIN Members ON Members.MemberID = Loans.MemberID
         WHERE Loans.ReturnDate IS NOT NULL AND Loans.FineAmount > 0
         ORDER BY Loans.ReturnDate DESC, Loans.LoanID DESC
         LIMIT 20'
    )->fetchAll();
} catch (RuntimeException $exception) {
    error_log('Active loan list database error: ' . $exception->getMessage());
    renderPageError('Daftar peminjaman tidak dapat dimuat. Pastikan skema kebijakan peminjaman sudah diterapkan.', 503);
    exit;
}

$token = csrfToken();
renderHeader('Transaksi peminjaman', 'return');
?>
<main class="page-main" id="main-content">
    <div class="page-heading"><p class="eyebrow">TRANSAKSI PERPUSTAKAAN</p><h1>Peminjaman & <em>pengembalian</em></h1><p>Masa pinjam <?= LOAN_PERIOD_DAYS ?> hari. Denda keterlambatan Rp<?= number_format(DAILY_LATE_FINE, 0, ',', '.') ?> per hari. Perpanjangan satu kali selama belum terlambat dan tidak ada antrean reservasi.</p></div>
    <?php if ($message !== ''): ?><p class="form-feedback" role="status"><?= e($message) ?></p><?php endif; ?>
    <?php if ($errors): ?><div class="form-errors" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <p><a class="button button-outline" href="/loans/queue.php">Kelola antrean reservasi</a></p>
    <?php if ($loans): ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>No.</th><th>Anggota</th><th>Buku</th><th>Tanggal pinjam</th><th>Jatuh tempo</th><th>Denda berjalan</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($loans as $loan): ?>
                    <?php $estimatedFine = (int) $loan['LateDays'] * DAILY_LATE_FINE; ?>
                    <tr>
                        <td><?= (int) $loan['LoanID'] ?></td>
                        <td><?= e((string) $loan['Name']) ?></td>
                        <td><?= e((string) $loan['Title']) ?></td>
                        <td><?= e((string) $loan['LoanDate']) ?></td>
                        <td><?= e((string) $loan['DueDate']) ?><?= (int) $loan['LateDays'] > 0 ? ' · terlambat ' . (int) $loan['LateDays'] . ' hari' : '' ?></td>
                        <td>Rp<?= number_format($estimatedFine, 0, ',', '.') ?></td>
                        <td class="table-actions">
                            <?php if ((int) $loan['RenewalCount'] === 0 && (int) $loan['LateDays'] === 0 && !(bool) $loan['HasQueue']): ?>
                                <form method="post" action="/loans/return.php" onsubmit="return confirm('Perpanjang jatuh tempo selama 14 hari?')">
                                    <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                                    <input type="hidden" name="action" value="renew">
                                    <input type="hidden" name="loan_id" value="<?= (int) $loan['LoanID'] ?>">
                                    <button class="text-button" type="submit">Perpanjang</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="/loans/return.php">
                                <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                                <input type="hidden" name="action" value="return">
                                <input type="hidden" name="loan_id" value="<?= (int) $loan['LoanID'] ?>">
                                <button class="button button-outline" type="submit">Catat pengembalian</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state"><h2>Tidak ada peminjaman aktif</h2><p>Semua buku sudah dikembalikan.</p></div>
    <?php endif; ?>

    <?php if ($recentReturns): ?>
        <section class="dashboard-panel return-history-panel">
            <div class="dashboard-panel-heading"><div><p class="eyebrow">RIWAYAT DENDA</p><h2>Denda yang tercatat</h2></div></div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Anggota</th><th>Buku</th><th>Jatuh tempo</th><th>Dikembalikan</th><th>Denda</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentReturns as $returned): ?>
                        <tr><td><?= e((string) $returned['Name']) ?></td><td><?= e((string) $returned['Title']) ?></td><td><?= e((string) $returned['DueDate']) ?></td><td><?= e((string) $returned['ReturnDate']) ?></td><td>Rp<?= number_format((int) $returned['FineAmount'], 0, ',', '.') ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</main>
<?php renderFooter(); ?>
