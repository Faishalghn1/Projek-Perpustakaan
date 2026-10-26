<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin.php';

$member = memberInput($_POST);
$errors = [];
$message = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!isValidCsrfToken($_POST)) {
        http_response_code(400);
        $message = 'Token formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } else {
        $errors = validateMemberInput($member);
        if (!$errors) {
            try {
                $statement = getDatabaseConnection()->prepare(
                    'INSERT INTO Members (Name, Email, Phone) VALUES (:name, :email, :phone)'
                );
                $statement->execute([
                    'name' => $member['name'],
                    'email' => $member['email'] !== '' ? $member['email'] : null,
                    'phone' => $member['phone'] !== '' ? $member['phone'] : null,
                ]);
                header('Location: /members/list.php');
                exit;
            } catch (RuntimeException $exception) {
                error_log('Member registration failed: ' . $exception->getMessage());
                if ($exception->getCode() === '23000') {
                    $errors['email'] = 'Email tersebut sudah terdaftar.';
                } else {
                    renderPageError('Data anggota tidak dapat disimpan saat ini.', 503);
                    exit;
                }
            }
        }
    }
}

$token = csrfToken();
renderHeader('Registrasi anggota', 'members');
?>
<main class="page-main" id="main-content">
    <div class="page-heading"><p class="eyebrow">PENGELOLAAN ANGGOTA</p><h1>Registrasi <em>anggota</em></h1></div>
    <?php if ($message !== ''): ?><p class="form-errors" role="alert"><?= e($message) ?></p><?php endif; ?>
    <form class="admin-form" method="post" action="/members/create.php">
        <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
        <label class="form-field" for="name">Nama lengkap<input id="name" name="name" maxlength="255" autocomplete="name" required value="<?= e($member['name']) ?>"><?php renderFieldError($errors, 'name'); ?></label>
        <label class="form-field" for="email">Email<input id="email" name="email" type="email" maxlength="255" autocomplete="email" value="<?= e($member['email']) ?>"><?php renderFieldError($errors, 'email'); ?></label>
        <label class="form-field" for="phone">Nomor telepon<input id="phone" name="phone" type="tel" maxlength="20" autocomplete="tel" value="<?= e($member['phone']) ?>"><?php renderFieldError($errors, 'phone'); ?></label>
        <div class="form-actions"><button class="button button-dark" type="submit">Simpan anggota</button><a class="text-link" href="/members/list.php">Batal</a></div>
    </form>
</main>
<?php renderFooter(); ?>
