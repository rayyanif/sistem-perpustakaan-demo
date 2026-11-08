<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");
header('Cache-Control: no-store');
$page = textInput($_GET, 'page') ?: 'catalogue';
$titles = ['catalogue'=>'Katalog buku', 'login'=>'Masuk petugas', 'loans'=>'Peminjaman aktif', 'history'=>'Riwayat dan denda', 'books'=>'Koleksi buku', 'members'=>'Anggota perpustakaan', 'borrow'=>'Catat peminjaman', 'add_book'=>'Tambah buku', 'add_member'=>'Tambah anggota'];
if ($page === 'action') { processAction(); exit; }
if (!isset($titles[$page])) { http_response_code(404); $page = 'missing'; $titles[$page] = 'Halaman tidak ditemukan'; }
if (!in_array($page, ['catalogue','login','missing'], true)) staffOnly();
$title = $titles[$page];
$search = mb_substr(textInput($_GET, 'search'), 0, 150);
$searchLabels = [
    'catalogue' => 'Cari buku di katalog',
    'loans' => 'Cari peminjaman aktif',
    'history' => 'Cari riwayat peminjaman',
    'books' => 'Cari dalam koleksi buku',
    'members' => 'Cari anggota',
];
$searchLabel = $searchLabels[$page] ?? null;
$toolbarAction = ['loans' => 'borrow', 'books' => 'add_book', 'members' => 'add_member'][$page] ?? null;
$availabilityLabels = ['' => 'Semua', 'available' => 'Tersedia', 'borrowed' => 'Dipinjam'];
$availability = $page === 'catalogue' ? textInput($_GET, 'availability') : '';
if (!isset($availabilityLabels[$availability])) $availability = '';
$old = $_SESSION['old'] ?? []; unset($_SESSION['old']);
function oldValue(string $name): string { global $old; return e($old[$name] ?? ''); }
function tableStart(array $labels, string $title): void {
    echo '<p class="table-hint">Geser tabel untuk melihat seluruh rincian.</p><div class="table-scroll" role="region" tabindex="0" aria-label="' . e($title) . '"><table><thead><tr>';
    foreach ($labels as $label) echo '<th scope="col">' . e($label) . '</th>';
    echo '</tr></thead><tbody>';
}
function emptyView(string $heading, string $description): void { echo '<div class="empty-state"><h2>' . e($heading) . '</h2><p>' . e($description) . '</p></div>'; }
?>
<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e($title) ?> | Perpustakaan</title><link rel="preload" href="assets/plus-jakarta-sans-latin-400.woff2" as="font" type="font/woff2" crossorigin><link rel="stylesheet" href="assets/style.css?v=20261108-shared-toolbar"><script src="assets/app.js?v=20261108-shared-toolbar" defer></script></head>
<body class="page-<?= e($page) ?>">
<a class="skip-link" href="#main-content">Langsung ke konten</a>
<header class="site-header<?= $searchLabel !== null ? ' site-header-searchable' : '' ?>"><div class="header-inner">
<a class="wordmark" href="<?= e(url('catalogue')) ?>">
    <span class="header-label">Perpustakaan</span>
    <span class="wordmark-caption"><span class="header-label">Katalog dan layanan peminjaman</span></span>
</a>
<button type="button" class="menu-toggle" aria-controls="main-navigation" aria-expanded="false" aria-label="Menu" hidden>
    <span class="menu-lines" aria-hidden="true"></span>
</button>
<nav class="main-navigation" id="main-navigation" aria-label="Navigasi utama"><ul class="nav-links">
<?php $links = loggedIn() ? ['catalogue'=>'Katalog','loans'=>'Peminjaman','history'=>'Riwayat & denda','books'=>'Buku','members'=>'Anggota'] : ['catalogue'=>'Katalog buku','login'=>'Masuk petugas']; foreach ($links as $key=>$label): ?>
<li><a href="<?= e(url($key)) ?>"<?= $page === $key ? ' aria-current="page"' : '' ?>><span class="header-label"><?= e($label) ?></span></a></li>
<?php endforeach; ?></ul>
<?php if (loggedIn()) actionForm('logout', '', 'Keluar'); ?>
</nav>
<?php if ($searchLabel !== null): ?>
<div class="page-toolbar">
    <form class="toolbar-search" method="get" action="index.php" role="search">
        <input type="hidden" name="page" value="<?= e($page) ?>">
        <?php if ($availability !== ''): ?><input type="hidden" name="availability" value="<?= e($availability) ?>"><?php endif; ?>
        <button class="toolbar-search-button" type="submit" aria-label="Cari">Cari</button>
        <input type="search" name="search" maxlength="150" aria-label="<?= e($searchLabel) ?>" placeholder="<?= e($searchLabel) ?>…" value="<?= e($search) ?>">
    </form>
    <?php if ($page === 'catalogue'): ?>
    <nav class="catalogue-filters" aria-label="Ketersediaan buku">
        <?php foreach ($availabilityLabels as $filter => $label):
            $filterParams = $search === '' ? [] : ['search' => $search];
            if ($filter !== '') $filterParams['availability'] = $filter;
        ?>
        <a class="catalogue-filter" href="<?= e(url('catalogue', $filterParams)) ?>"<?= $filter === $availability ? ' aria-current="true"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <?php elseif ($toolbarAction !== null): ?>
    <a class="btn btn-primary toolbar-action" href="<?= e(url($toolbarAction)) ?>"><?= e($titles[$toolbarAction]) ?></a>
    <?php endif; ?>
    <?php if ($search !== ''): ?><a class="toolbar-reset" href="<?= e(url($page, $availability === '' ? [] : ['availability' => $availability])) ?>">Reset pencarian</a><?php endif; ?>
</div>
<?php endif; ?>
</div></header>
<main class="container" id="main-content" tabindex="-1">
<div class="page-heading"><div><h1><?= e($title) ?></h1><p class="page-description"><?= e(['catalogue'=>'Cari judul, pengarang, penerbit, atau ISBN dan periksa ketersediaannya.', 'loans'=>'Catat pengembalian. Denda dihitung per hari kalender setelah batas kembali.', 'history'=>'Lihat buku yang sudah kembali dan lunasi denda yang masih tercatat.', 'books'=>'Kelola informasi buku dan tempatnya di rak.', 'members'=>'Kelola anggota yang menggunakan layanan peminjaman.', 'borrow'=>'Pilih buku tersedia dan anggota terdaftar.', 'login'=>'Gunakan akun petugas perpustakaan yang sudah terdaftar.', 'add_book'=>'Lengkapi informasi terbit dan pilih rak yang terdaftar.', 'add_member'=>'Catat identitas dan kontak untuk layanan peminjaman.'][$page] ?? 'Kembali ke katalog untuk melanjutkan.') ?></p></div>
</div>
<?php if (isset($_SESSION['flash'])): [$message,$kind] = $_SESSION['flash']; unset($_SESSION['flash']); ?><div role="status" class="notice notice-<?= e($kind) ?>"><?= e($message) ?></div><?php endif; ?>
<?php try { ?>
<?php if ($page === 'catalogue' || $page === 'books'):
    $params=[]; $where='WHERE b.judul_buku IS NOT NULL';
    if ($search !== '') { $where .= ' AND (b.judul_buku LIKE ? OR b.pengarang LIKE ? OR b.penerbit LIKE ? OR b.isbn LIKE ?)'; $params=array_fill(0,4,'%'.$search.'%'); }
    $activeLoan = 'EXISTS(SELECT 1 FROM peminjaman p WHERE p.id_buku=b.id_buku AND p.tanggal_dikembalikan IS NULL)';
    $where .= match ($availability) {
        'available' => " AND NOT $activeLoan",
        'borrowed' => " AND $activeLoan",
        default => '',
    };
    $books=rows("SELECT b.*, r.lokasi_rak, $activeLoan AS borrowed FROM buku b LEFT JOIN rak r ON r.id_rak=b.id_rak $where ORDER BY b.judul_buku",$params);
    $totals=one("SELECT COUNT(*) AS total, COALESCE(SUM(NOT $activeLoan),0) AS available FROM buku b WHERE b.judul_buku IS NOT NULL");
?>
<section class="card" aria-label="Koleksi buku"><div class="register-header"><h2>Daftar buku</h2><p class="result-count"><?= e($totals['total']) ?> buku dalam koleksi · <?= e($totals['available']) ?> tersedia · <?= count($books) ?> hasil</p></div>
<?php if (!$books) {
    if ($availability !== '') emptyView('Buku tidak ditemukan', 'Ubah filter ketersediaan atau coba kata kunci lain.');
    else emptyView($search !== '' ? 'Buku tidak ditemukan' : 'Koleksi belum diisi', 'Coba kata kunci lain atau hubungi petugas untuk informasi koleksi.');
} else { tableStart($page === 'books' ? ['Judul','Pengarang','Penerbit','ISBN','Tahun','Rak','Status','Aksi'] : ['Judul','Pengarang','Penerbit','ISBN','Tahun','Rak','Status'],'Koleksi buku'); foreach ($books as $book): ?>
<tr><td class="primary-cell"><?= e($book['judul_buku']) ?></td><td><?= e($book['pengarang']) ?></td><td><?= e($book['penerbit']) ?></td><td><?= e($book['isbn']) ?></td><td><?= e($book['tahun_terbit']) ?></td><td><?= e($book['lokasi_rak'] ?: 'Belum ditentukan') ?></td><td><span class="status<?= $book['borrowed'] ? ' status-due' : '' ?>"><?= $book['borrowed'] ? 'Dipinjam' : 'Tersedia' ?></span></td><?php if ($page === 'books'): ?><td><?php actionForm('delete_book',(string)$book['id_buku'],'Hapus buku','delete',true); ?></td><?php endif; ?></tr>
<?php endforeach; echo '</tbody></table></div>'; } ?></section>
<?php elseif ($page === 'members'):
    $members=rows('SELECT * FROM anggota WHERE nama_anggota IS NOT NULL' . ($search !== '' ? ' AND (nama_anggota LIKE ? OR alamat_anggota LIKE ? OR no_telp_anggota LIKE ?)' : '') . ' ORDER BY nama_anggota', $search !== '' ? array_fill(0,3,'%'.$search.'%') : []);
?>
<section class="card" aria-label="Daftar anggota"><div class="register-header"><h2>Anggota terdaftar</h2><p class="result-count"><?= count($members) ?> hasil</p></div><?php if (!$members) emptyView('Anggota tidak ditemukan','Tambahkan anggota atau coba kata kunci lain.'); else { tableStart(['ID','Nama','Alamat','Telepon','Aksi'],'Daftar anggota'); foreach ($members as $member): ?>
<tr><td><?= e($member['id_anggota']) ?></td><td class="primary-cell"><?= e($member['nama_anggota']) ?></td><td><?= e($member['alamat_anggota']) ?></td><td><?= e($member['no_telp_anggota']) ?></td><td><?php actionForm('delete_member',(string)$member['id_anggota'],'Hapus anggota','delete',true); ?></td></tr>
<?php endforeach; echo '</tbody></table></div>'; } ?></section>
<?php elseif ($page === 'loans' || $page === 'history'):
    $where=$page === 'loans' ? 'p.tanggal_dikembalikan IS NULL' : 'p.tanggal_dikembalikan IS NOT NULL'; $params=[];
    if ($search !== '') { $where .= ' AND (b.judul_buku LIKE ? OR a.nama_anggota LIKE ? OR p.id_peminjaman LIKE ?)'; $params=array_fill(0,3,'%'.$search.'%'); }
    $loans=rows("SELECT p.*,b.judul_buku,a.nama_anggota FROM peminjaman p LEFT JOIN buku b ON b.id_buku=p.id_buku LEFT JOIN anggota a ON a.id_anggota=p.id_anggota WHERE $where ORDER BY p.id_peminjaman DESC",$params);
?>
<section class="card" aria-label="Daftar peminjaman"><div class="register-header"><h2><?= e($title) ?></h2><p class="result-count"><?= count($loans) ?> hasil</p></div><?php if (!$loans) emptyView('Belum ada catatan','Peminjaman yang sesuai akan muncul di daftar ini.'); else { tableStart(['Anggota / ID','Buku','Dipinjam','Batas kembali','Dikembalikan','Denda','Pembayaran','Aksi'],'Daftar peminjaman'); foreach ($loans as $loan):
    $invalidDate=false; try { $amount=$page === 'loans' ? fine((string)$loan['tanggal_kembali']) : (int)$loan['jumlah_denda']; } catch (DomainException $error) { $invalidDate=true; $amount=0; }
?>
<tr><td class="primary-cell"><?= e($loan['nama_anggota'] ?: 'Anggota diarsipkan') ?><span class="cell-note"><?= e($loan['id_peminjaman']) ?></span></td><td class="primary-cell"><?= e($loan['judul_buku'] ?: 'Buku diarsipkan') ?></td><td><?= e($loan['tanggal_pinjam']) ?></td><td><?= e($loan['tanggal_kembali']) ?></td><td><?= e($loan['tanggal_dikembalikan'] ?: 'Belum kembali') ?></td><td><?= $invalidDate ? 'Tanggal perlu diperiksa' : e(money($amount)) ?></td><td><span class="status<?= $amount > 0 && $loan['status_pembayaran'] !== 'Lunas' ? ' status-due' : '' ?>"><?= e($amount === 0 && !$invalidDate ? 'Tidak ada denda' : $loan['status_pembayaran']) ?></span></td><td><?php if ($page === 'loans' && !$invalidDate) actionForm('return',(string)$loan['id_peminjaman'],'Kembalikan buku','secondary',true); elseif ($page === 'history' && $amount > 0 && $loan['status_pembayaran'] !== 'Lunas') actionForm('pay',(string)$loan['id_peminjaman'],'Lunasi denda','primary',true); else echo '<span class="cell-note">Tidak ada aksi</span>'; ?></td></tr>
<?php endforeach; echo '</tbody></table></div>'; } ?></section>
<?php elseif ($page === 'login'): ?>
<div class="form-layout"><form class="card form-card" method="post" action="<?= e(url('action')) ?>"><?php csrfField(); ?><input type="hidden" name="action" value="login"><div class="form-grid"><div class="form-group form-group-wide"><label for="username">ID atau username</label><input id="username" name="username" autocomplete="username" autocapitalize="none" spellcheck="false" maxlength="24" aria-describedby="username-help" required><p class="field-help" id="username-help">Masukkan ID petugas atau nama petugas yang terdaftar.</p></div><div class="form-group form-group-wide"><label for="password">Kata sandi</label><input id="password" name="password" type="password" autocomplete="current-password" required></div></div><div class="form-actions"><button type="submit" class="btn btn-primary">Masuk petugas</button><a href="<?= e(url('catalogue')) ?>" class="btn btn-secondary">Kembali ke katalog</a></div></form><aside class="form-aside"><h2>Layanan petugas</h2><p>Pencatatan anggota, peminjaman, pengembalian, dan pembayaran denda tersedia setelah masuk.</p></aside></div>
<?php elseif ($page === 'borrow'):
    $books=rows('SELECT b.* FROM buku b WHERE b.judul_buku IS NOT NULL AND NOT EXISTS(SELECT 1 FROM peminjaman p WHERE p.id_buku=b.id_buku AND p.tanggal_dikembalikan IS NULL) ORDER BY b.judul_buku');
    $members=rows('SELECT * FROM anggota WHERE nama_anggota IS NOT NULL ORDER BY nama_anggota');
?>
<div class="form-layout"><section class="card form-card" aria-label="Form peminjaman"><?php if (!$books || !$members) { emptyView('Peminjaman belum dapat dicatat','Pastikan ada buku tersedia dan anggota terdaftar.'); } else { ?>
<form method="post" action="<?= e(url('action')) ?>"><?php csrfField(); ?><input type="hidden" name="action" value="borrow"><div class="form-grid"><div class="form-group form-group-wide"><label for="id_buku">Buku tersedia</label><select id="id_buku" name="id_buku" required><option value="">Pilih buku</option><?php foreach ($books as $book): ?><option value="<?= e($book['id_buku']) ?>"<?= ($old['id_buku'] ?? '') === $book['id_buku'] ? ' selected' : '' ?>><?= e($book['judul_buku'].' / '.$book['pengarang'].' / '.$book['id_buku']) ?></option><?php endforeach; ?></select></div><div class="form-group form-group-wide"><label for="id_anggota">Anggota peminjam</label><select id="id_anggota" name="id_anggota" required><option value="">Pilih anggota</option><?php foreach ($members as $member): ?><option value="<?= e($member['id_anggota']) ?>"<?= ($old['id_anggota'] ?? '') === $member['id_anggota'] ? ' selected' : '' ?>><?= e($member['nama_anggota'].' / '.$member['id_anggota']) ?></option><?php endforeach; ?></select></div></div><div class="form-actions"><button class="btn btn-primary" type="submit">Simpan peminjaman</button><a class="btn btn-secondary" href="<?= e(url('loans')) ?>">Batal</a></div></form><?php } ?></section><aside class="form-aside"><h2>Ketentuan peminjaman</h2><p>Batas kembali <?= e($config['loan_days']) ?> hari. Denda <?= e(money($config['fine_per_day'])) ?> per hari keterlambatan.</p><p>Pembayaran dilakukan setelah buku dikembalikan agar jumlah denda tidak berubah.</p></aside></div>
<?php elseif ($page === 'add_member' || $page === 'add_book'):
    $shelves=$page === 'add_book' ? rows('SELECT * FROM rak ORDER BY lokasi_rak') : [];
    $fields=$page === 'add_member' ? ['nama_anggota'=>['Nama lengkap',37,'text'],'alamat_anggota'=>['Alamat',114,'textarea'],'no_telp_anggota'=>['Nomor telepon',19,'tel']] : ['judul_buku'=>['Judul buku',70,'text'],'pengarang'=>['Pengarang',59,'text'],'penerbit'=>['Penerbit',25,'text'],'isbn'=>['ISBN',17,'text'],'tahun_terbit'=>['Tahun terbit',4,'number']];
?>
<div class="form-layout"><form class="card form-card" method="post" action="<?= e(url('action')) ?>"><?php csrfField(); ?><input type="hidden" name="action" value="<?= e($page) ?>"><div class="form-grid">
<?php foreach ($fields as $name=>[$label,$length,$type]): ?><div class="form-group<?= in_array($name,['judul_buku','nama_anggota','alamat_anggota'],true) ? ' form-group-wide' : '' ?>"><label for="<?= e($name) ?>"><?= e($label) ?></label><?php if ($type === 'textarea'): ?><textarea id="<?= e($name) ?>" name="<?= e($name) ?>" maxlength="<?= $length ?>" required><?= oldValue($name) ?></textarea><?php else: ?><input id="<?= e($name) ?>" name="<?= e($name) ?>" type="<?= e($type) ?>" value="<?= oldValue($name) ?>"<?= $type === 'number' ? ' min="1" max="9999"' : ' maxlength="'.$length.'"' ?> required><?php endif; ?></div><?php endforeach; ?>
<?php if ($page === 'add_book'): ?><div class="form-group"><label for="id_rak">Rak</label><select id="id_rak" name="id_rak" required><option value="">Pilih rak</option><?php foreach ($shelves as $shelf): ?><option value="<?= e($shelf['id_rak']) ?>"<?= ($old['id_rak'] ?? '') === $shelf['id_rak'] ? ' selected' : '' ?>><?= e($shelf['lokasi_rak'].' / '.$shelf['id_rak']) ?></option><?php endforeach; ?></select><?php if (!$shelves): ?><p class="field-help">Belum ada rak. Hubungi pengelola untuk mendaftarkan rak terlebih dahulu.</p><?php endif; ?></div><?php endif; ?>
</div><div class="form-actions"><button class="btn btn-primary" type="submit"<?= $page === 'add_book' && !$shelves ? ' disabled' : '' ?>>Simpan <?= $page === 'add_book' ? 'buku' : 'anggota' ?></button><a class="btn btn-secondary" href="<?= e(url($page === 'add_book' ? 'books' : 'members')) ?>">Batal</a></div></form><aside class="form-aside"><h2>Sebelum disimpan</h2><p>Semua kolom wajib diisi. Periksa kembali identitas dan informasi yang dicatat.</p></aside></div>
<?php else: emptyView('Halaman tidak ditemukan','Gunakan navigasi untuk kembali ke katalog.'); endif; ?>
<?php } catch (Throwable $error) { error_log((string)$error); http_response_code(503); ?><section class="card" role="alert"><div class="empty-state"><h2>Data belum dapat dimuat</h2><p>Koneksi atau data perpustakaan belum tersedia. Coba lagi setelah pengelola memeriksa layanan database.</p><a class="btn btn-secondary" href="<?= e(url($page)) ?>">Muat ulang</a></div></section><?php } ?>
</main>
<footer class="site-footer"><span>Perpustakaan</span><span><?= loggedIn() ? 'Petugas: '.e($_SESSION['staff_name']) : 'Katalog publik' ?></span></footer>
<p class="submit-status" role="status" hidden></p>
<dialog class="confirm-dialog" aria-labelledby="confirm-title"><h2 id="confirm-title">Konfirmasi perubahan</h2><p>Periksa pilihan sebelum melanjutkan.</p><div class="form-actions"><button class="btn btn-primary" type="button" data-confirm>Ya, lanjutkan</button><button class="btn btn-secondary" type="button" data-cancel>Batal</button></div></dialog>
</body></html>
