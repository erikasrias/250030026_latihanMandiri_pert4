<?php
 //1. CLASS BUKU 
 class Buku
{
    private string $kode;
    private string $judul;
    private string $penulis;
    private int $stok;

    public function __construct(string $kode, string $judul, string $penulis, int $stok)
    {
        $this->kode    = $kode;
        $this->judul   = $judul;
        $this->penulis = $penulis;
        $this->setStok($stok);
    }

    public function getKode(): string    { return $this->kode; }
    public function getJudul(): string   { return $this->judul; }
    public function getPenulis(): string { return $this->penulis; }
    public function getStok(): int       { return $this->stok; }

    public function setStok(int $stok): void
    {
        if ($stok < 0) {
            throw new InvalidArgumentException("Stok tidak boleh negatif.");
        }
        $this->stok = $stok;
    }

    public function isTersedia(): bool
    {
        return $this->stok > 0;
    }

    public function kurangiStok(): void { $this->setStok($this->stok - 1); }
    public function tambahStok(): void  { $this->setStok($this->stok + 1); }

    public function info(): string
    {
        return "[{$this->kode}] {$this->judul} - {$this->penulis} (stok: {$this->stok})";
    }
}

 //2. CLASS ANGGOTA induk + turunan 
class Anggota
{
    private string $id;
    private string $nama;

    public function __construct(string $id, string $nama)
    {
        $this->id   = $id;
        $this->nama = $nama;
    }

    public function getId(): string   { return $this->id; }
    public function getNama(): string { return $this->nama; }

    public function setNama(string $nama): void
    {
        if (trim($nama) === '') {
            throw new InvalidArgumentException("Nama tidak boleh kosong.");
        }
        $this->nama = $nama;
    }

    public function getJenis(): string        { return "Umum"; }
    public function getBatasPinjam(): int     { return 2; }   // jumlah buku maks
    public function getLamaPinjam(): int      { return 7; }   // hari
    public function getDendaPerHari(): int    { return 1000; } // rupiah
}

class Mahasiswa extends Anggota
{
    private string $prodi;

    public function __construct(string $id, string $nama, string $prodi)
    {
        parent::__construct($id, $nama);
        $this->prodi = $prodi;
    }

    public function getProdi(): string { return $this->prodi; }

    public function getJenis(): string     { return "Mahasiswa ({$this->prodi})"; }
    public function getBatasPinjam(): int  { return 3; }
    public function getLamaPinjam(): int   { return 7; }
    public function getDendaPerHari(): int { return 1000; }
}

class Dosen extends Anggota
{
    public function getJenis(): string     { return "Dosen"; }
    public function getBatasPinjam(): int  { return 5; }
    public function getLamaPinjam(): int   { return 14; }
    public function getDendaPerHari(): int { return 500; }
}

 //3. CLASS PEMINJAMAN 
class Peminjaman
{
    private static int $counter = 0;

    private string $noTransaksi;
    private Anggota $anggota;
    private Buku $buku;
    private DateTime $tglPinjam;
    private DateTime $tglJatuhTempo;
    private ?DateTime $tglKembali = null;

    public function __construct(Anggota $anggota, Buku $buku, string $tglPinjam)
    {
        if (!$buku->isTersedia()) {
            throw new RuntimeException("Buku \"{$buku->getJudul()}\" sedang tidak tersedia.");
        }

        self::$counter++;
        $this->noTransaksi   = sprintf("TRX-%03d", self::$counter);
        $this->anggota       = $anggota;
        $this->buku          = $buku;
        $this->tglPinjam     = new DateTime($tglPinjam);
        $this->tglJatuhTempo = (clone $this->tglPinjam)
            ->modify("+{$anggota->getLamaPinjam()} days");

        $buku->kurangiStok();
    }

    public function getNoTransaksi(): string { return $this->noTransaksi; }
    public function getAnggota(): Anggota    { return $this->anggota; }
    public function getBuku(): Buku          { return $this->buku; }
    public function isSudahKembali(): bool   { return $this->tglKembali !== null; }

    public function kembalikan(string $tglKembali): int
    {
        if ($this->isSudahKembali()) {
            throw new RuntimeException("Buku sudah dikembalikan sebelumnya.");
        }
        $this->tglKembali = new DateTime($tglKembali);
        $this->buku->tambahStok();
        return $this->hitungDenda();
    }

    public function hitungDenda(): int
    {
        if ($this->tglKembali === null) {
            return 0;
        }
        $selisih = $this->tglJatuhTempo->diff($this->tglKembali);
        $telat   = $selisih->invert ? 0 : $selisih->days;
        return $telat * $this->anggota->getDendaPerHari(); // polimorfisme
    }

    public function info(): string
    {
        $status = $this->isSudahKembali()
            ? "Kembali " . $this->tglKembali->format('d-m-Y')
            : "Belum kembali";

        return sprintf(
            "%s | %s (%s) meminjam \"%s\" | Pinjam: %s | Tempo: %s | %s",
            $this->noTransaksi,
            $this->anggota->getNama(),
            $this->anggota->getJenis(),
            $this->buku->getJudul(),
            $this->tglPinjam->format('d-m-Y'),
            $this->tglJatuhTempo->format('d-m-Y'),
            $status
        );
    }
}

 //4. PROGRAM UTAMA (membuat objek & menjalankan method)
echo "<pre style='font-family:monospace;font-size:14px;line-height:1.6'>";

function garis(string $judul): void
{
    echo "\n=== {$judul} " . str_repeat("=", max(0, 55 - strlen($judul))) . "\n";
}

$buku1 = new Buku("B001", "Pemrograman PHP", "Andi Wijaya", 2);
$buku2 = new Buku("B002", "Basis Data", "Siti Rahma", 1);
$buku3 = new Buku("B003", "Algoritma & Struktur Data", "Budi Santoso", 3);

$anggota = [
    new Mahasiswa("M001", "Erika", "Sistem Informasi"),
    new Dosen("D001", "Pak Made"),
    new Anggota("U001", "Pengunjung Umum"),
];

garis("DATA BUKU");
foreach ([$buku1, $buku2, $buku3] as $b) {
    echo $b->info() . "\n";
}

garis("DATA ANGGOTA");
foreach ($anggota as $a) {
    printf(
        "%s - %-18s | %-26s | maks %d buku | %2d hari | denda Rp%s/hari\n",
        $a->getId(), $a->getNama(), $a->getJenis(),
        $a->getBatasPinjam(), $a->getLamaPinjam(),
        number_format($a->getDendaPerHari(), 0, ',', '.')
    );
}

garis("TRANSAKSI PEMINJAMAN");
$daftarPinjam = [];

try {
    $daftarPinjam[] = new Peminjaman($anggota[0], $buku1, "2026-10-01"); // Mahasiswa
    $daftarPinjam[] = new Peminjaman($anggota[1], $buku2, "2026-10-01"); // Dosen
    $daftarPinjam[] = new Peminjaman($anggota[2], $buku3, "2026-10-01"); // Umum

    $daftarPinjam[] = new Peminjaman($anggota[0], $buku2, "2026-10-02");
} catch (RuntimeException $e) {
    echo "[GAGAL] " . $e->getMessage() . "\n";
}

foreach ($daftarPinjam as $p) {
    echo $p->info() . "\n";
}

garis("PENGEMBALIAN & DENDA");
$tglKembali = ["2026-10-11", "2026-10-18", "2026-10-11"];

foreach ($daftarPinjam as $i => $p) {
    $denda = $p->kembalikan($tglKembali[$i]);
    printf(
        "%s | %-18s | denda: Rp%s\n",
        $p->getNoTransaksi(),
        $p->getAnggota()->getNama(),
        number_format($denda, 0, ',', '.')
    );
}

garis("STOK BUKU SETELAH PENGEMBALIAN");
foreach ([$buku1, $buku2, $buku3] as $b) {
    echo $b->info() . "\n";
}

echo "</pre>";