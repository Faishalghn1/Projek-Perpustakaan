<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin.php';

$action = requestString($_POST, 'action');
$errors = [];
$message = '';
$selectedIsbn = requestString($_POST, 'isbn');
$selectedMemberId = requestString($_POST, 'member_id');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!isValidCsrfToken($_POST)) {
        http_response_code(400);
        $message = 'Token formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } elseif (!in_array($action, ['add', 'cancel'], true)) {
        http_response_code(400);
        $message = 'Aksi antrean tidak valid.';
    } elseif ($action === 'add') {
        $memberId = filter_var($selectedMemberId, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($memberId === false) {
            $errors['member_id'] = 'Pilih anggota yang valid.';
        }
        if ($selectedIsbn === '' || textLength($selectedIsbn) > 20) {
            $errors['isbn'] = 'Pilih judul buku yang valid.';
        }

        if (!$errors) {
            $connection = null;
            try {
                $connection = getDatabaseConnection();
                $connection->beginTransaction();
                $bookStatement = $connection->prepare(
                    'SELECT ISBN, Quantity FROM Books WHERE ISBN = :isbn FOR UPDATE'
                );
                $bookStatement->execute(['isbn' => $selectedIsbn]);
                $book = $bookStatement->fetch();
                $memberStatement = $connection->prepare(
                    'SELECT MemberID FROM Members WHERE MemberID = :member_id'
                );
                $memberStatement->execute(['member_id' => $memberId]);
                $member = $memberStatement->fetch();

                if (!$book) {
                    $errors['isbn'] = 'Buku tidak ditemukan.';
                } elseif (!$member) {
                    $errors['member_id'] = 'Anggota tidak ditemukan.';
                } elseif ((int) $book['Quantity'] > 0) {
                    $errors['isbn'] = 'Antrean hanya tersedia saat semua eksemplar buku sedang dipinjam atau direservasi.';
                } else {
                    $activeCopyStatement = $connection->prepare(
                        "SELECT
                           EXISTS(SELECT 1 FROM Loans WHERE ISBN = :loan_isbn AND ReturnDate IS NULL)
                           OR EXISTS(SELECT 1 FROM Reservations WHERE ISBN = :reservation_isbn AND Status = 'ready')
                         AS HasActiveCopy"
                    );
                    $activeCopyStatement->execute([
                        'loan_isbn' => $selectedIsbn,
                        'reservation_isbn' => $selectedIsbn,
                    ]);
                    if (!(bool) $activeCopyStatement->fetchColumn()) {
                        $errors['isbn'] = 'Antrean hanya dapat dibuat untuk buku yang sedang dipinjam atau menunggu pengambilan reservasi.';
                    } else {
                        $duplicateStatement = $connection->prepare(
                            "SELECT COUNT(*)
                             FROM Reservations
                             WHERE MemberID = :member_id AND ISBN = :isbn
                               AND Status IN ('waiting', 'ready')"
                        );
                        $duplicateStatement->execute([
                            'member_id' => $memberId,
                            'isbn' => $selectedIsbn,
                        ]);
                        if ((int) $duplicateStatement->fetchColumn() > 0) {
                            $errors['member_id'] = 'Anggota sudah berada dalam antrean aktif untuk judul ini.';
                        } else {
                            $insertStatement = $connection->prepare(
                                "INSERT INTO Reservations (MemberID, ISBN, Status)
                                 VALUES (:member_id, :isbn, 'waiting')"
                            );
                            $insertStatement->execute([
                                'member_id' => $memberId,
                                'isbn' => $selectedIsbn,
                            ]);
                        }
                    }
                }

                if ($errors) {
                    $connection->rollBack();
                    http_response_code(422);
                } else {
                    $connection->commit();
                    $message = 'Anggota berhasil dimasukkan ke antrean reservasi.';
                    $selectedIsbn = '';
                    $selectedMemberId = '';
                }
            } catch (RuntimeException $exception) {
                if ($connection instanceof PDO && $connection->inTransaction()) {
                    $connection->rollBack();
                }
                error_log('Reservation creation failed: ' . $exception->getMessage());
                renderPageError('Reservasi tidak dapat disimpan saat ini.', 503);
                exit;
            }
        }
    } elseif ($action === 'cancel') {
        $reservationId = filter_var(requestString($_POST, 'reservation_id'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($reservationId === false) {
            $errors['reservation_id'] = 'Nomor reservasi tidak valid.';
        } else {
            $connection = null;
            try {
                $connection = getDatabaseConnection();
                $connection->beginTransaction();
                $identityStatement = $connection->prepare(
                    "SELECT ISBN FROM Reservations
                     WHERE ReservationID = :reservation_id AND Status IN ('waiting', 'ready')"
                );
                $identityStatement->execute(['reservation_id' => $reservationId]);
                $identity = $identityStatement->fetch();

                if (!$identity) {
                    $errors['reservation_id'] = 'Reservasi tidak ditemukan atau sudah diproses.';
                } else {
                    $bookStatement = $connection->prepare(
                        'SELECT ISBN FROM Books WHERE ISBN = :isbn FOR UPDATE'
                    );
                    $bookStatement->execute(['isbn' => $identity['ISBN']]);
                    $bookStatement->fetch();
                    $reservationStatement = $connection->prepare(
                        "SELECT ReservationID, ISBN, Status FROM Reservations
                         WHERE ReservationID = :reservation_id
                           AND Status IN ('waiting', 'ready')
                         FOR UPDATE"
                    );
                    $reservationStatement->execute(['reservation_id' => $reservationId]);
                    $reservation = $reservationStatement->fetch();

                    if (!$reservation) {
                        $errors['reservation_id'] = 'Reservasi sudah berubah dan tidak dapat dibatalkan.';
                    } else {
                        $cancelStatement = $connection->prepare(
                            "UPDATE Reservations SET Status = 'cancelled'
                             WHERE ReservationID = :reservation_id
                               AND Status IN ('waiting', 'ready')"
                        );
                        $cancelStatement->execute(['reservation_id' => $reservationId]);
                        if ($cancelStatement->rowCount() !== 1) {
                            throw new RuntimeException('Reservation state changed while cancelling it.');
                        }

                        if ($reservation['Status'] === 'ready') {
                            $releaseStatement = $connection->prepare(
                                'UPDATE Books SET Quantity = Quantity + 1 WHERE ISBN = :isbn'
                            );
                            $releaseStatement->execute(['isbn' => $reservation['ISBN']]);
                            $nextStatement = $connection->prepare(
                                "SELECT ReservationID
                                 FROM Reservations
                                 WHERE ISBN = :isbn AND Status = 'waiting'
                                 ORDER BY ReservedAt, ReservationID
                                 LIMIT 1
                                 FOR UPDATE"
                            );
                            $nextStatement->execute(['isbn' => $reservation['ISBN']]);
                            $next = $nextStatement->fetch();
                            if ($next) {
                                $readyStatement = $connection->prepare(
                                    "UPDATE Reservations SET Status = 'ready', ReadyAt = CURRENT_TIMESTAMP
                                     WHERE ReservationID = :reservation_id AND Status = 'waiting'"
                                );
                                $readyStatement->execute(['reservation_id' => $next['ReservationID']]);
                                $reserveStatement = $connection->prepare(
                                    'UPDATE Books SET Quantity = Quantity - 1 WHERE ISBN = :isbn AND Quantity > 0'
                                );
                                $reserveStatement->execute(['isbn' => $reservation['ISBN']]);
                                if ($readyStatement->rowCount() !== 1 || $reserveStatement->rowCount() !== 1) {
                                    throw new RuntimeException('Next reservation changed while assigning the released copy.');
                                }
                            }
                        }
                    }
                }

                if ($errors) {
                    $connection->rollBack();
                    http_response_code(422);
                } else {
                    $connection->commit();
                    $message = 'Reservasi berhasil dibatalkan.';
                }
            } catch (RuntimeException $exception) {
                if ($connection instanceof PDO && $connection->inTransaction()) {
                    $connection->rollBack();
                }
                error_log('Reservation cancellation failed: ' . $exception->getMessage());
                renderPageError('Reservasi tidak dapat dibatalkan saat ini.', 503);
                exit;
            }
        }
    }
}

try {
    $connection = getDatabaseConnection();
    $members = $connection->query('SELECT MemberID, Name FROM Members ORDER BY Name')->fetchAll();
    $queueableBooks = $connection->query(
        "SELECT Books.ISBN, Books.Title
         FROM Books
         WHERE Books.Quantity = 0
           AND (
             EXISTS (SELECT 1 FROM Loans WHERE Loans.ISBN = Books.ISBN AND Loans.ReturnDate IS NULL)
             OR EXISTS (
               SELECT 1 FROM Reservations
               WHERE Reservations.ISBN = Books.ISBN AND Reservations.Status = 'ready'
             )
           )
         ORDER BY Books.Title"
    )->fetchAll();
    $reservations = $connection->query(
        "SELECT Reservations.ReservationID, Reservations.MemberID, Reservations.ISBN,
                Reservations.ReservedAt, Reservations.ReadyAt, Reservations.Status,
                Books.Title, Members.Name
         FROM Reservations
         INNER JOIN Books ON Books.ISBN = Reservations.ISBN
         INNER JOIN Members ON Members.MemberID = Reservations.MemberID
         WHERE Reservations.Status IN ('waiting', 'ready')
         ORDER BY Reservations.ISBN, Reservations.ReservedAt, Reservations.ReservationID"
    )->fetchAll();
} catch (RuntimeException $exception) {
    error_log('Reservation queue database error: ' . $exception->getMessage());
    renderPageError('Antrean reservasi tidak dapat dimuat saat ini.', 503);
    exit;
}

$queuePositions = [];
foreach ($reservations as $reservation) {
    $isbn = (string) $reservation['ISBN'];
    if ($reservation['Status'] === 'waiting') {
        $queuePositions[$isbn] = ($queuePositions[$isbn] ?? 0) + 1;
    }
}

$token = csrfToken();
renderHeader('Antrean reservasi', 'queue');
?>
<main class="page-main" id="main-content">
    <div class="page-heading"><p class="eyebrow">RESERVASI BUKU</p><h1>Antrean <em>reservasi</em></h1><p>Anggota masuk antrean saat semua eksemplar sedang dipinjam. Saat buku kembali, sistem otomatis menahan eksemplar untuk anggota terlama.</p></div>
    <?php if ($message !== ''): ?><p class="form-feedback" role="status"><?= e($message) ?></p><?php endif; ?>
    <?php if ($errors): ?><div class="form-errors" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <section class="dashboard-panel">
        <div class="dashboard-panel-heading"><div><p class="eyebrow">DAFTARKAN ANGGOTA</p><h2>Tambah ke antrean</h2></div></div>
        <?php if ($queueableBooks && $members): ?>
            <form class="admin-form" method="post" action="/loans/queue.php">
                <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                <input type="hidden" name="action" value="add">
                <label class="form-field" for="member_id">Anggota<select id="member_id" name="member_id" required><option value="">Pilih anggota</option><?php foreach ($members as $member): ?><option value="<?= (int) $member['MemberID'] ?>" <?= $selectedMemberId === (string) $member['MemberID'] ? 'selected' : '' ?>><?= e((string) $member['Name']) ?></option><?php endforeach; ?></select><?php renderFieldError($errors, 'member_id'); ?></label>
                <label class="form-field" for="isbn">Buku<select id="isbn" name="isbn" required><option value="">Pilih buku yang sedang dipinjam</option><?php foreach ($queueableBooks as $book): ?><option value="<?= e((string) $book['ISBN']) ?>" <?= $selectedIsbn === (string) $book['ISBN'] ? 'selected' : '' ?>><?= e((string) $book['Title']) ?> (<?= e((string) $book['ISBN']) ?>)</option><?php endforeach; ?></select><?php renderFieldError($errors, 'isbn'); ?></label>
                <div class="form-actions"><button class="button button-dark" type="submit">Masukkan antrean</button><a class="text-link" href="/loans/return.php">Kembali ke transaksi</a></div>
            </form>
        <?php else: ?>
            <div class="dashboard-empty">Tidak ada judul yang sedang kehabisan stok untuk dimasukkan ke antrean.</div>
        <?php endif; ?>
    </section>

    <section class="dashboard-panel queue-list-panel">
        <div class="dashboard-panel-heading"><div><p class="eyebrow">URUTAN FIFO</p><h2>Antrean aktif</h2></div></div>
        <?php if ($reservations): ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Urutan</th><th>Buku</th><th>Anggota</th><th>Status</th><th>Waktu reservasi</th><th>Aksi</th></tr></thead>
                    <tbody>
                    <?php foreach ($reservations as $reservation): ?>
                        <tr>
                            <td><?= $reservation['Status'] === 'ready' ? 'Siap' : '#' . (int) $queuePositions[(string) $reservation['ISBN']] ?></td>
                            <td><?= e((string) $reservation['Title']) ?></td>
                            <td><?= e((string) $reservation['Name']) ?></td>
                            <td><?= $reservation['Status'] === 'ready' ? 'Siap diambil' : 'Menunggu' ?></td>
                            <td><?= e((string) $reservation['ReservedAt']) ?></td>
                            <td>
                                <form method="post" action="/loans/queue.php" onsubmit="return confirm('Batalkan reservasi ini?')">
                                    <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="reservation_id" value="<?= (int) $reservation['ReservationID'] ?>">
                                    <button class="text-button" type="submit">Batalkan</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="dashboard-empty">Belum ada antrean reservasi.</div>
        <?php endif; ?>
    </section>
</main>
<?php renderFooter(); ?>
