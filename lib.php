<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');
ini_set('session.use_strict_mode', '1');
session_name(getenv('LIBRARY_SESSION_NAME') ?: 'perpustakaan_terpadu');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
if (!@session_start()) {
    http_response_code(503);
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><title>Layanan belum tersedia | Perpustakaan</title><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="assets/style.css"></head><body><main class="container"><h1>Layanan belum tersedia</h1><p>Sesi belum dapat dimulai. Hubungi pengelola untuk memeriksa layanan perpustakaan.</p></main></body></html>';
    exit;
}
$config = require __DIR__ . '/config.php';

function e($value): string { return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function textInput(array $source, string $key): string { return isset($source[$key]) && is_string($source[$key]) ? trim($source[$key]) : ''; }
function url(string $page, array $params = []): string { return 'index.php?' . http_build_query(['page' => $page] + $params); }
function redirect(string $page): void { header('Location: ' . url($page), true, 303); exit; }
function db(): mysqli {
    static $connection;
    global $config;
    if (!$connection) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $connection = new mysqli($config['host'], $config['user'], $config['password'], $config['database'], $config['port']);
        $connection->set_charset('utf8mb4');
    }
    return $connection;
}
function query(string $sql, array $params = []): mysqli_stmt {
    $stmt = db()->prepare($sql);
    if ($params) $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    $stmt->execute();
    return $stmt;
}
function rows(string $sql, array $params = []): array { return query($sql, $params)->get_result()->fetch_all(MYSQLI_ASSOC); }
function one(string $sql, array $params = []): ?array { return rows($sql, $params)[0] ?? null; }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrfField(): void { echo '<input type="hidden" name="csrf" value="' . e(csrf()) . '">'; }
function loggedIn(): bool { return !empty($_SESSION['staff_id']); }
function staffOnly(): void { if (!loggedIn()) redirect('login'); }
function flash(string $message, string $kind = 'success'): void { $_SESSION['flash'] = [$message, $kind]; }
function requiredText(string $key, int $max): string {
    $value = textInput($_POST, $key);
    if ($value === '' || mb_strlen($value, 'UTF-8') > $max) throw new DomainException('Periksa kolom wajib dan panjang isian.');
    return $value;
}
function libraryDate(string $value): DateTimeImmutable {
    foreach (['Y-m-d', 'd/m/Y'] as $format) {
        $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
        if ($date && $date->format($format) === $value) return $date;
    }
    throw new DomainException('Tanggal peminjaman tidak valid. Hubungi pengelola.');
}
function fine(string $due, ?string $returned = null): int {
    global $config;
    $start = libraryDate($due);
    $end = libraryDate($returned ?: date('Y-m-d'));
    return max(0, (int) $start->diff($end)->format('%r%a')) * $config['fine_per_day'];
}
function money($amount): string { return 'Rp ' . number_format((int) $amount, 0, ',', '.'); }
function locked(callable $work): void {
    $lock = 'library:' . substr(hash('sha256', (string) db()->query('SELECT DATABASE()')->fetch_row()[0]), 0, 40);
    if ((int) one('SELECT GET_LOCK(?, 10) AS acquired', [$lock])['acquired'] !== 1) throw new DomainException('Data sedang diproses petugas lain. Silakan coba lagi.');
    try {
        db()->begin_transaction();
        try { $work(); db()->commit(); }
        catch (Throwable $error) { db()->rollback(); throw $error; }
    } finally { query('SELECT RELEASE_LOCK(?)', [$lock]); }
}
function newId(string $table, string $column): string {
    $allowed = ['buku' => 'id_buku', 'anggota' => 'id_anggota', 'peminjaman' => 'id_peminjaman'];
    if (($allowed[$table] ?? '') !== $column) throw new LogicException('Invalid ID source');
    do { $id = (string) random_int(1000000, 9999999); } while (one("SELECT $column FROM $table WHERE $column = ?", [$id]));
    return $id;
}
function uniqueRecord(string $table, string $id, string $value): array {
    $allowed = ['buku' => 'id_buku', 'anggota' => 'id_anggota', 'peminjaman' => 'id_peminjaman', 'rak' => 'id_rak'];
    if (($allowed[$table] ?? '') !== $id) throw new LogicException('Invalid record source');
    $records = rows("SELECT * FROM $table WHERE $id = ? FOR UPDATE", [$value]);
    if (count($records) !== 1) throw new DomainException('Data tidak ditemukan atau ID tidak unik. Hubungi pengelola.');
    return $records[0];
}
function actionForm(string $action, string $id, string $label, string $style = 'secondary', bool $confirm = false): void {
    echo '<form method="post" action="' . e(url('action')) . '" class="inline-form"' . ($confirm ? ' data-confirm="' . e($label . '?') . '"' : '') . '>';
    csrfField();
    echo '<input type="hidden" name="action" value="' . e($action) . '"><input type="hidden" name="id" value="' . e($id) . '"><button type="submit" class="btn btn-' . e($style) . '">' . e($label) . '</button></form>';
}
function processAction(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); return; }
    $token = textInput($_POST, 'csrf');
    if ($token === '' || !hash_equals(csrf(), $token)) { http_response_code(403); echo 'Form kedaluwarsa. Muat ulang halaman sebelum mencoba lagi.'; return; }
    $action = textInput($_POST, 'action');
    $destinations = ['login'=>'login', 'logout'=>'catalogue', 'borrow'=>'borrow', 'return'=>'loans', 'pay'=>'history', 'delete_book'=>'books', 'delete_member'=>'members', 'add_book'=>'add_book', 'add_member'=>'add_member'];
    if (!isset($destinations[$action])) { http_response_code(400); echo 'Aksi tidak dikenal.'; return; }
    $destination = $destinations[$action];
    if ($action !== 'login') staffOnly();
    try {
        if ($action === 'login') {
            if ((int) ($_SESSION['login_wait_until'] ?? 0) > time()) throw new DomainException('Terlalu banyak percobaan. Tunggu satu menit.');
            $identifier = requiredText('username', 24);
            $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
            $staff = rows('SELECT id_petugas, nama_petugas, password FROM petugas WHERE id_petugas = ? OR nama_petugas = ?', [$identifier, $identifier]);
            $hash = count($staff) === 1 ? (string) $staff[0]['password'] : '';
            $valid = preg_match('/^[a-f0-9]{64}$/i', $hash) ? hash_equals(strtolower($hash), hash('sha256', $password)) : password_verify($password, $hash);
            if (!$valid || $password === '') {
                $_SESSION['login_attempts'] = (int) ($_SESSION['login_attempts'] ?? 0) + 1;
                if ($_SESSION['login_attempts'] >= 5) { $_SESSION['login_wait_until'] = time() + 60; $_SESSION['login_attempts'] = 0; }
                throw new DomainException('ID, username, atau kata sandi salah.');
            }
            session_regenerate_id(true);
            $_SESSION = ['staff_id' => $staff[0]['id_petugas'], 'staff_name' => $staff[0]['nama_petugas'], 'csrf' => bin2hex(random_bytes(32))];
            redirect('loans');
        }
        if ($action === 'logout') {
            $_SESSION = [];
            session_regenerate_id(true);
            redirect('catalogue');
        }
        locked(function () use ($action, &$destination): void {
            if (count(rows('SELECT id_petugas FROM petugas WHERE id_petugas = ?', [$_SESSION['staff_id']])) !== 1) throw new DomainException('Akun petugas tidak tersedia. Silakan keluar dan masuk kembali.');
            if ($action === 'borrow') {
                $book = uniqueRecord('buku', 'id_buku', requiredText('id_buku', 7));
                $member = uniqueRecord('anggota', 'id_anggota', requiredText('id_anggota', 10));
                if (!$book['judul_buku'] || !$member['nama_anggota']) throw new DomainException('Buku atau anggota sudah tidak aktif.');
                if (one('SELECT id_peminjaman FROM peminjaman WHERE id_buku = ? AND tanggal_dikembalikan IS NULL', [$book['id_buku']])) throw new DomainException('Buku baru saja dipinjam. Pilih buku lain.');
                global $config;
                query("INSERT INTO peminjaman (id_peminjaman,tanggal_pinjam,tanggal_kembali,tanggal_dikembalikan,jumlah_denda,status_pembayaran,tanggal_denda,id_buku,id_anggota,id_petugas) VALUES (?,?,?,NULL,'0','Belum Lunas',NULL,?,?,?)", [newId('peminjaman','id_peminjaman'), date('Y-m-d'), date('Y-m-d', strtotime('+' . $config['loan_days'] . ' days')), $book['id_buku'], $member['id_anggota'], $_SESSION['staff_id']]);
                $destination = 'loans';
            } elseif ($action === 'return' || $action === 'pay') {
                $loan = uniqueRecord('peminjaman', 'id_peminjaman', requiredText('id', 13));
                if ($action === 'return') {
                    if ($loan['tanggal_dikembalikan'] !== null) throw new DomainException('Buku sudah dikembalikan.');
                    $amount = fine((string) $loan['tanggal_kembali']);
                    if ($loan['status_pembayaran'] === 'Lunas' && $amount > (int) $loan['jumlah_denda']) throw new DomainException('Catatan pembayaran lama perlu diperiksa pengelola sebelum pengembalian.');
                    $status = $amount === 0 ? 'Tidak ada Denda' : ($loan['status_pembayaran'] === 'Lunas' ? 'Lunas' : 'Belum Lunas');
                    query('UPDATE peminjaman SET tanggal_dikembalikan=?, jumlah_denda=?, status_pembayaran=? WHERE id_peminjaman=?', [date('Y-m-d'), (string) $amount, $status, $loan['id_peminjaman']]);
                } else {
                    if ($loan['tanggal_dikembalikan'] === null) throw new DomainException('Kembalikan buku sebelum melunasi denda agar jumlahnya tetap.');
                    if ($loan['status_pembayaran'] === 'Lunas' || (int) $loan['jumlah_denda'] <= 0) throw new DomainException('Tidak ada denda yang perlu dibayar.');
                    query("UPDATE peminjaman SET status_pembayaran='Lunas', tanggal_denda=? WHERE id_peminjaman=?", [date('Y-m-d'), $loan['id_peminjaman']]);
                }
            } elseif ($action === 'delete_book' || $action === 'delete_member') {
                $isBook = $action === 'delete_book';
                $table = $isBook ? 'buku' : 'anggota';
                $id = $isBook ? 'id_buku' : 'id_anggota';
                $record = uniqueRecord($table, $id, requiredText('id', $isBook ? 7 : 10));
                if (one("SELECT id_peminjaman FROM peminjaman WHERE $id = ? AND (tanggal_dikembalikan IS NULL OR (CAST(jumlah_denda AS UNSIGNED) > 0 AND COALESCE(status_pembayaran,'') <> 'Lunas'))", [$record[$id]])) throw new DomainException('Masih ada peminjaman aktif atau denda belum lunas. Selesaikan terlebih dahulu.');
                $field = $isBook ? 'judul_buku' : 'nama_anggota';
                query("UPDATE $table SET $field = NULL WHERE $id = ?", [$record[$id]]);
            } elseif ($action === 'add_member') {
                $name = requiredText('nama_anggota', 37); $address = requiredText('alamat_anggota', 114); $phone = requiredText('no_telp_anggota', 19);
                if (!preg_match('/^[+0-9 ()-]{5,19}$/', $phone)) throw new DomainException('Periksa nomor telepon.');
                query('INSERT INTO anggota (id_anggota,nama_anggota,alamat_anggota,no_telp_anggota) VALUES (?,?,?,?)', [newId('anggota','id_anggota'), $name, $address, $phone]);
                $destination = 'members';
            } elseif ($action === 'add_book') {
                $title = requiredText('judul_buku',70); $author = requiredText('pengarang',59); $publisher = requiredText('penerbit',25); $isbn = requiredText('isbn',17); $year = requiredText('tahun_terbit',12); $shelf = requiredText('id_rak',6);
                if (!ctype_digit($year) || (int) $year < 1 || (int) $year > 9999) throw new DomainException('Tahun terbit harus antara 1 dan 9999.');
                uniqueRecord('rak','id_rak',$shelf);
                query('INSERT INTO buku (id_buku,judul_buku,pengarang,penerbit,isbn,tahun_terbit,id_rak) VALUES (?,?,?,?,?,?,?)', [newId('buku','id_buku'),$title,$author,$publisher,$isbn,$year,$shelf]);
                $destination = 'books';
            }
        });
        unset($_SESSION['old']);
        flash('Perubahan berhasil disimpan.');
    } catch (DomainException $error) {
        flash($error->getMessage(), 'error');
        if (in_array($action, ['add_book','add_member','borrow'], true)) $_SESSION['old'] = array_filter($_POST, fn($key) => !in_array($key, ['csrf','password'], true), ARRAY_FILTER_USE_KEY);
    } catch (Throwable $error) {
        error_log((string) $error);
        flash('Perubahan belum tersimpan. Periksa koneksi database lalu coba lagi.', 'error');
    }
    redirect($destination);
}
