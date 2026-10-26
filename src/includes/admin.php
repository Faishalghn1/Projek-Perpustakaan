<?php
declare(strict_types=1);

require_once __DIR__ . '/view.php';
require_once __DIR__ . '/../config/db.php';

const LOAN_PERIOD_DAYS = 14;
const MAX_ACTIVE_LOANS = 3;
const DAILY_LATE_FINE = 5000;

function bookInput(array $source): array
{
    return [
        'isbn' => requestString($source, 'isbn'),
        'title' => requestString($source, 'title'),
        'author' => requestString($source, 'author'),
        'genre' => requestString($source, 'genre'),
        'quantity' => requestString($source, 'quantity'),
    ];
}

function validateBookInput(array $book): array
{
    $errors = [];
    if ($book['isbn'] === '' || textLength($book['isbn']) > 20) {
        $errors['isbn'] = 'ISBN wajib diisi dan maksimal 20 karakter.';
    }
    if ($book['title'] === '' || textLength($book['title']) > 255) {
        $errors['title'] = 'Judul wajib diisi dan maksimal 255 karakter.';
    }
    if ($book['author'] === '' || textLength($book['author']) > 255) {
        $errors['author'] = 'Penulis wajib diisi dan maksimal 255 karakter.';
    }
    if (textLength($book['genre']) > 100) {
        $errors['genre'] = 'Genre maksimal 100 karakter.';
    }
    $quantity = filter_var($book['quantity'], FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 0, 'max_range' => 2147483647],
    ]);
    if ($quantity === false) {
        $errors['quantity'] = 'Jumlah harus berupa bilangan bulat dari 0 sampai 2147483647.';
    }

    return $errors;
}

function memberInput(array $source): array
{
    return [
        'name' => requestString($source, 'name'),
        'email' => requestString($source, 'email'),
        'phone' => requestString($source, 'phone'),
    ];
}

function validateMemberInput(array $member): array
{
    $errors = [];
    if ($member['name'] === '' || textLength($member['name']) > 255) {
        $errors['name'] = 'Nama wajib diisi dan maksimal 255 karakter.';
    }
    if ($member['email'] !== ''
        && (!filter_var($member['email'], FILTER_VALIDATE_EMAIL) || textLength($member['email']) > 255)) {
        $errors['email'] = 'Masukkan email valid dengan maksimal 255 karakter.';
    }
    if (textLength($member['phone']) > 20) {
        $errors['phone'] = 'Nomor telepon maksimal 20 karakter.';
    }

    return $errors;
}

function renderFieldError(array $errors, string $field): void
{
    if (isset($errors[$field])) {
        echo '<span class="field-error">' . e($errors[$field]) . '</span>';
    }
}
