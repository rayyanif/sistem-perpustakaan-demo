# Sistem Perpustakaan Demo

**English** · [Bahasa Indonesia](README.id.md)

A PHP and MySQL/MariaDB library app with a public catalogue and staff workflows for books, members, loans, returns, and fines.

## GitHub Pages demo

The [catalogue demo](https://rayyanif.github.io/sistem-perpustakaan-demo/) uses four fictional book records. You can try search and availability filters in your browser. To use staff sign-in or record transactions, run the PHP app with your own database.

## Run the full app with XAMPP

1. Download or clone this repository into `C:\xampp\htdocs\perpustakaan_terpadu`.
2. In the XAMPP Control Panel, start **Apache** and **MySQL**.
3. Open [phpMyAdmin](http://localhost/phpmyadmin/), choose **Import**, and import [`schema.sql`](schema.sql). It creates `database_perpustakaan` and its five empty tables. Import into a fresh database for a clean trial.
4. If your database settings differ from the XAMPP defaults, set `LIBRARY_DB_HOST`, `LIBRARY_DB_PORT`, `LIBRARY_DB_NAME`, `LIBRARY_DB_USER`, and `LIBRARY_DB_PASSWORD` for the PHP server, or update your local `config.php`.
5. Open the [PHP catalogue](http://localhost/perpustakaan_terpadu/index.php). Add your own records or use the setup below to start testing as a staff member.

### Create a local staff account and shelf

Generate a hash for a password you choose. Run this in PowerShell.

```powershell
& 'C:\xampp\php\php.exe' -r "echo password_hash(readline('Local test password: '), PASSWORD_DEFAULT), PHP_EOL;"
```

In phpMyAdmin, open the **SQL** tab for `database_perpustakaan`. Replace `PASTE_GENERATED_HASH` with the output from PowerShell, then run these statements once in the fresh database.

```sql
INSERT INTO petugas (id_petugas, nama_petugas, password)
VALUES ('DEMO01', 'Petugas Demo', 'PASTE_GENERATED_HASH');

INSERT INTO rak (id_rak, lokasi_rak)
VALUES ('RAK01', 'Rak A-01');
```

Sign in as `DEMO01` using the password you chose. Add a book and a member, then use **Catat peminjaman** to try a loan.

The sample books in the static demo are separate from your SQL database. Keep local credentials and any exports containing real member, loan, or staff records out of the repository.
