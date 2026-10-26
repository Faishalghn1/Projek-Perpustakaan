<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin.php';

$rawMemberId = requestString($_GET, 'member_id');
$memberId = filter_var($rawMemberId, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$errors = [];
$message = '';

if ($memberId === false) {
    renderPageError('Nomor anggota tidak valid.', 400);
    exit;
}

try {
    $connection = getDatabaseConnection();
    $statement = $connection->prepare(
        'SELECT MemberID, Name, Email, Phone FROM Members WHERE MemberID = :member_id'
    );
    $statement->execute(['member_id' => $memberId]);
    $record = $statement->fetch();
} catch (RuntimeException $exception) {
    error_log('Member edit lookup failed: ' . $exception->getMessage());
    renderPageError('Data anggota tidak dapat dimuat saat ini.', 503);
    exit;
}

if (!$record) {
    renderPageError('Anggota tidak ditemukan.', 404);
    exit;
}

$member = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    ? memberInput($_POST)
    : [
        'name' => (string) $record['Name'],
        'email' => (string) ($record['Email'] ?? ''),
        'phone' => (string) ($record['Phone'] ?? ''),
    ];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!isValidCsrfToken($_POST)) {
        http_response_code(400);
        $message = 'Token formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } else {
        $errors = validateMemberInput($member);
        if (!$errors) {
            try {
                $statement = $connection->prepare(
                    'UPDATE Members SET Name = :name, Email = :email, Phone = :phone
                     WHERE MemberID = :member_id'
                );
                $statement->execute([
                    'name' => $member['name'],
                    'email' => $member['email'] !== '' ? $member['email'] : null,
                    'phone' => $member['phone'] !== '' ? $member['phone'] : null,
                    'member_id' => $memberId,
                ]);
                header('Location: /members/list.php');
                exit;
            } catch (RuntimeException $exception) {
                error_log('Member update failed: ' . $exception->getMessage());
                if ($exception->getCode() === '23000') {
                    $errors['email'] = 'Email tersebut sudah digunakan anggota lain.';
                } else {
                    renderPageError('Perubahan anggota tidak dapat disimpan saat ini.', 503);
                    exit;
                }
            }
        }
    }
}

$token = csrfToken();
renderHeader('Ubah anggota', 'members');
?>
<main class="page-main" id="main-content">
    <div class="page-heading"><p class="eyebrow">PENGELOLAAN ANGGOTA · #<?= (int) $memberId ?></p><h1>Ubah data <em>anggota</em></h1><p>Perbarui informasi anggota perpustakaan.</p></div>
    <?php if ($message !== ''): ?><p class="form-errors" role="alert"><?= e($message) ?></p><?php endif; ?>
    <form class="admin-form" method="post" action="/members/edit.php?member_id=<?= (int) $memberId ?>">
        <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
        <label class="form-field" for="name">Nama lengkap<input id="name" name="name" maxlength="255" autocomplete="name" required value="<?= e($member['name']) ?>"><?php renderFieldError($errors, 'name'); ?></label>
        <label class="form-field" for="email">Email<input id="email" name="email" type="email" maxlength="255" autocomplete="email" value="<?= e($member['email']) ?>"><?php renderFieldError($errors, 'email'); ?></label>
        <label class="form-field" for="phone">Nomor telepon<input id="phone" name="phone" type="tel" maxlength="20" autocomplete="tel" value="<?= e($member['phone']) ?>"><?php renderFieldError($errors, 'phone'); ?></label>
        <div class="form-actions"><button class="button button-dark" type="submit">Simpan perubahan</button><a class="text-link" href="/members/list.php">Batal</a></div>
    </form>
</main>
<?php renderFooter(); ?>
