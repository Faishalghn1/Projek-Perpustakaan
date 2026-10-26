<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin.php';

$selectedLoanOption = requestString($_POST, 'loan_option');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    $isbn = requestString($_GET, 'isbn');
    $selectedLoanOption = $isbn !== '' ? 'book:' . $isbn : '';
}
$memberId = requestString($_POST, 'member_id');
$errors = [];
$message = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!isValidCsrfToken($_POST)) {
        http_response_code(400);
        $message = 'Token formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } else {
        $validatedMemberId = filter_var($memberId, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($validatedMemberId === false) {
            $errors['member_id'] = 'Pilih anggota yang valid.';
        }
        if (!preg_match('/^(book:.{1,20}|reservation:[1-9][0-9]*)$/', $selectedLoanOption)) {
            $errors['loan_option'] = 'Pilih buku tersedia atau reservasi siap diambil.';
        }

        if (!$errors) {
            $connection = null;
            try {
                $connection = getDatabaseConnection();
                $connection->beginTransaction();

                $memberStatement = $connection->prepare(
                    'SELECT MemberID FROM Members WHERE MemberID = :member_id FOR UPDATE'
                );
                $memberStatement->execute(['member_id' => $validatedMemberId]);
                if (!$memberStatement->fetch()) {
                    $errors['member_id'] = 'Anggota tidak ditemukan.';
                }

                if (!$errors) {
                    $overdueStatement = $connection->prepare(
                        'SELECT Books.Title, Loans.DueDate,
                                DATEDIFF(CURRENT_DATE, Loans.DueDate) AS LateDays
                         FROM Loans
                         INNER JOIN Books ON Books.ISBN = Loans.ISBN
                         WHERE Loans.MemberID = :member_id
                           AND Loans.ReturnDate IS NULL
                           AND Loans.DueDate < CURRENT_DATE
                         FOR UPDATE'
                    );
                    $overdueStatement->execute(['member_id' => $validatedMemberId]);
                    $overdueLoans = $overdueStatement->fetchAll();
                    if ($overdueLoans) {
                        $overdueDetails = array_map(
                            static fn (array $loan): string => sprintf(
                                '%s terlambat %d hari (denda berjalan Rp%s).',
                                (string) $loan['Title'],
                                (int) $loan['LateDays'],
                                number_format((int) $loan['LateDays'] * DAILY_LATE_FINE, 0, ',', '.')
                            ),
                            $overdueLoans
                        );
                        $errors['member_id'] = 'Anggota diblokir sampai semua buku terlambat dikembalikan: '
                            . implode(' ', $overdueDetails);
                    }
                }

                if (!$errors) {
                    $countStatement = $connection->prepare(
                        'SELECT COUNT(*) FROM Loans WHERE MemberID = :member_id AND ReturnDate IS NULL'
                    );
                    $countStatement->execute(['member_id' => $validatedMemberId]);
                    if ((int) $countStatement->fetchColumn() >= MAX_ACTIVE_LOANS) {
                        $errors['member_id'] = 'Batas peminjaman adalah ' . MAX_ACTIVE_LOANS . ' buku aktif per anggota.';
                    }
                }

                if (!$errors && str_starts_with($selectedLoanOption, 'reservation:')) {
                    $reservationId = (int) substr($selectedLoanOption, strlen('reservation:'));
                    $reservationStatement = $connection->prepare(
                        "SELECT ReservationID, ISBN
                         FROM Reservations
                         WHERE ReservationID = :reservation_id
                           AND MemberID = :member_id
                           AND Status = 'ready'
                         FOR UPDATE"
                    );
                    $reservationStatement->execute([
                        'reservation_id' => $reservationId,
                        'member_id' => $validatedMemberId,
                    ]);
                    $reservation = $reservationStatement->fetch();
                    if (!$reservation) {
                        $errors['loan_option'] = 'Reservasi siap diambil tidak ditemukan untuk anggota ini.';
                    } else {
                        $insertLoan = $connection->prepare(
                            'INSERT INTO Loans (MemberID, ISBN, LoanDate, DueDate, ReturnDate)
                             VALUES (:member_id, :isbn, CURRENT_DATE, DATE_ADD(CURRENT_DATE, INTERVAL '
                                . LOAN_PERIOD_DAYS . ' DAY), NULL)'
                        );
                        $insertLoan->execute([
                            'member_id' => $validatedMemberId,
                            'isbn' => $reservation['ISBN'],
                        ]);
                        $completeReservation = $connection->prepare(
                            "UPDATE Reservations
                             SET Status = 'completed', CompletedAt = CURRENT_TIMESTAMP
                             WHERE ReservationID = :reservation_id AND Status = 'ready'"
                        );
                        $completeReservation->execute(['reservation_id' => $reservationId]);
                        if ($completeReservation->rowCount() !== 1) {
                            throw new RuntimeException('Reservation state changed during checkout.');
                        }
                    }
                } elseif (!$errors) {
                    $isbn = substr($selectedLoanOption, strlen('book:'));
                    $bookStatement = $connection->prepare(
                        'SELECT Quantity FROM Books WHERE ISBN = :isbn FOR UPDATE'
                    );
                    $bookStatement->execute(['isbn' => $isbn]);
                    $book = $bookStatement->fetch();
                    if (!$book || (int) $book['Quantity'] < 1) {
                        $errors['loan_option'] = 'Buku tidak ditemukan atau stoknya habis.';
                    } else {
                        $queueStatement = $connection->prepare(
                            "SELECT COUNT(*) FROM Reservations WHERE ISBN = :isbn AND Status = 'waiting'"
                        );
                        $queueStatement->execute(['isbn' => $isbn]);
                        if ((int) $queueStatement->fetchColumn() > 0) {
                            $errors['loan_option'] = 'Buku memiliki antrean. Proses reservasi terlama terlebih dahulu.';
                        } else {
                            $insertLoan = $connection->prepare(
                                'INSERT INTO Loans (MemberID, ISBN, LoanDate, DueDate, ReturnDate)
                                 VALUES (:member_id, :isbn, CURRENT_DATE, DATE_ADD(CURRENT_DATE, INTERVAL '
                                    . LOAN_PERIOD_DAYS . ' DAY), NULL)'
                            );
                            $insertLoan->execute([
                                'member_id' => $validatedMemberId,
                                'isbn' => $isbn,
                            ]);
                            $updateBook = $connection->prepare(
                                'UPDATE Books SET Quantity = Quantity - 1 WHERE ISBN = :isbn AND Quantity > 0'
                            );
                            $updateBook->execute(['isbn' => $isbn]);
                            if ($updateBook->rowCount() !== 1) {
                                throw new RuntimeException('Book stock changed while the loan was being recorded.');
                            }
                        }
                    }
                }

                if ($errors) {
                    $connection->rollBack();
                    http_response_code(422);
                } else {
                    $connection->commit();
                    $message = 'Peminjaman berhasil dicatat. Jatuh tempo ' . LOAN_PERIOD_DAYS
                        . ' hari sejak tanggal pinjam.';
                    $selectedLoanOption = '';
                    $memberId = '';
                }
            } catch (RuntimeException $exception) {
                if ($connection instanceof PDO && $connection->inTransaction()) {
                    $connection->rollBack();
                }
                error_log('Loan creation failed: ' . $exception->getMessage());
                renderPageError('Transaksi peminjaman tidak dapat disimpan saat ini.', 503);
                exit;
            }
        }
    }
}

try {
    $connection = getDatabaseConnection();
    $books = $connection->query(
        'SELECT ISBN, Title, Author, Quantity FROM Books WHERE Quantity > 0 ORDER BY Title'
    )->fetchAll();
    $members = $connection->query('SELECT MemberID, Name FROM Members ORDER BY Name')->fetchAll();
    $readyReservations = $connection->query(
        "SELECT Reservations.ReservationID, Reservations.MemberID, Reservations.ISBN,
                Books.Title, Members.Name
         FROM Reservations
         INNER JOIN Books ON Books.ISBN = Reservations.ISBN
         INNER JOIN Members ON Members.MemberID = Reservations.MemberID
         WHERE Reservations.Status = 'ready'
         ORDER BY Reservations.ReadyAt, Reservations.ReservationID"
    )->fetchAll();
} catch (RuntimeException $exception) {
    error_log('Loan form database error: ' . $exception->getMessage());
    renderPageError('Data peminjaman tidak dapat dimuat saat ini.', 503);
    exit;
}

$token = csrfToken();
renderHeader('Peminjaman buku', 'loan');
?>
<main class="page-main" id="main-content">
    <div class="page-heading"><p class="eyebrow">TRANSAKSI PERPUSTAKAAN</p><h1>Pinjam <em>buku</em></h1><p>Masa pinjam <?= LOAN_PERIOD_DAYS ?> hari. Maksimal <?= MAX_ACTIVE_LOANS ?> buku aktif per anggota; anggota dengan buku terlambat diblokir sampai pengembalian.</p></div>
    <?php if ($message !== ''): ?><p class="form-feedback" role="status"><?= e($message) ?></p><?php endif; ?>
    <?php if ($errors): ?><div class="form-errors" role="alert"><strong>Peminjaman belum dicatat.</strong><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form class="admin-form" method="post" action="/loans/borrow.php">
        <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
        <label class="form-field" for="member_id">Anggota<select id="member_id" name="member_id" required><option value="">Pilih anggota</option><?php foreach ($members as $member): ?><option value="<?= (int) $member['MemberID'] ?>" <?= $memberId === (string) $member['MemberID'] ? 'selected' : '' ?>><?= e((string) $member['Name']) ?></option><?php endforeach; ?></select><?php renderFieldError($errors, 'member_id'); ?></label>
        <label class="form-field" for="loan_option">Buku<select id="loan_option" name="loan_option" required><option value="">Pilih buku atau reservasi</option><?php foreach ($books as $book): ?><option value="book:<?= e((string) $book['ISBN']) ?>" <?= $selectedLoanOption === 'book:' . (string) $book['ISBN'] ? 'selected' : '' ?>><?= e((string) $book['Title']) ?> — <?= e((string) $book['Author']) ?> (stok <?= (int) $book['Quantity'] ?>)</option><?php endforeach; ?><?php if ($readyReservations): ?><optgroup label="Reservasi siap diambil"><?php foreach ($readyReservations as $reservation): ?><option value="reservation:<?= (int) $reservation['ReservationID'] ?>" <?= $selectedLoanOption === 'reservation:' . (int) $reservation['ReservationID'] ? 'selected' : '' ?>><?= e((string) $reservation['Title']) ?> — untuk <?= e((string) $reservation['Name']) ?></option><?php endforeach; ?></optgroup><?php endif; ?></select><?php renderFieldError($errors, 'loan_option'); ?></label>
        <div class="form-actions"><button class="button button-dark" type="submit">Catat peminjaman</button><a class="text-link" href="/loans/return.php">Pengembalian</a><a class="text-link" href="/loans/queue.php">Antrean reservasi</a></div>
    </form>
</main>
<?php renderFooter(); ?>
