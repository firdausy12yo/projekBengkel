<?php
require_once __DIR__ . '/koneksi.php';

$daftarNotif = [];
$idPelanggan = (int) ($_SESSION['id_pelanggan'] ?? 0);

if ($idPelanggan > 0) {

    $stmt = $koneksi->prepare(
        "SELECT id_notifikasi, id_booking, kategori,
                judul, pesan, sudah_dibaca, dibuat_pada
         FROM notifikasi
         WHERE id_pelanggan = ?
         AND dihapus = 0
         ORDER BY id_notifikasi DESC
         LIMIT 100"
    );

    $stmt->bind_param('i', $idPelanggan);
    $stmt->execute();

    $hasil = $stmt->get_result();

    while ($baris = $hasil->fetch_assoc()) {
        $daftarNotif[] = $baris;
    }

    $stmt->close();
}

$csrfNotif = htmlspecialchars(
    $_SESSION['csrf_notifikasi'] ?? '',
    ENT_QUOTES,
    'UTF-8'
);
?>

<div class="page-header">
    <i class="fa-solid fa-bell header-icon"></i>
    <div>
        <h2>Notifikasi</h2>
        <p>Informasi terbaru tentang aktivitas booking dan servis.</p>
    </div>
</div>

<div class="psn-toolbar">
    <div class="psn-filters">
        <button type="button" class="psn-filter active"
            onclick="filterNotif('semua', this)">
            Semua
        </button>

        <button type="button" class="psn-filter"
            onclick="filterNotif('belum', this)">
            Belum Dibaca
        </button>
    </div>

    <form action="proses_notifikasi.php" method="POST">
        <input type="hidden" name="csrf_token"
            value="<?= $csrfNotif ?>">
        <input type="hidden" name="aksi" value="baca_semua">

        <button class="psn-read-all" type="submit"
            <?= $jumlahNotifBaru === 0 ? 'disabled' : '' ?>>
            <i class="fa-solid fa-check-double"></i>
            Tandai Semua Dibaca
        </button>
    </form>
</div>

<div class="psn-list">

    <?php if (empty($daftarNotif)): ?>

        <div class="psn-empty">
            <i class="fa-regular fa-bell-slash"></i>
            <h3>Belum Ada Notifikasi</h3>
            <p>Informasi booking akan muncul di sini.</p>
        </div>

    <?php else: ?>

        <?php foreach ($daftarNotif as $notif): ?>

            <?php
            $belumDibaca = (int)$notif['sudah_dibaca'] === 0;
            $ikon = $notif['kategori'] === 'servis'
                ? 'fa-screwdriver-wrench'
                : 'fa-calendar-check';
            ?>

            <article
                class="psn-card <?= $belumDibaca ? 'is-unread' : '' ?>"
                data-unread="<?= $belumDibaca ? '1' : '0' ?>">

                <div class="psn-icon">
                    <i class="fa-solid <?= $ikon ?>"></i>
                </div>

                <div class="psn-body">

                    <div class="psn-title-row">
                        <h3>
                            <?= htmlspecialchars(
                                $notif['judul'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </h3>

                        <?php if ($belumDibaca): ?>
                            <span class="psn-new">Baru</span>
                        <?php endif; ?>
                    </div>

                    <p>
                        <?= htmlspecialchars(
                            $notif['pesan'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </p>

                    <small>
                        <i class="fa-regular fa-clock"></i>
                        <?= date(
                            'd/m/Y H:i',
                            strtotime($notif['dibuat_pada'])
                        ) ?>
                    </small>

                    <div class="psn-actions">

                        <?php if ($notif['id_booking'] !== null): ?>
                            <form action="proses_notifikasi.php"
                                method="POST">
                                <input type="hidden" name="csrf_token"
                                    value="<?= $csrfNotif ?>">
                                <input type="hidden" name="aksi"
                                    value="lihat">
                                <input type="hidden" name="id_notifikasi"
                                    value="<?= (int)$notif['id_notifikasi'] ?>">

                                <button class="psn-btn primary" type="submit">
                                    <i class="fa-solid fa-eye"></i>
                                    Lihat Pesanan
                                </button>
                            </form>
                        <?php endif; ?>

                        <?php if ($belumDibaca): ?>
                            <form action="proses_notifikasi.php"
                                method="POST">
                                <input type="hidden" name="csrf_token"
                                    value="<?= $csrfNotif ?>">
                                <input type="hidden" name="aksi"
                                    value="baca">
                                <input type="hidden" name="id_notifikasi"
                                    value="<?= (int)$notif['id_notifikasi'] ?>">

                                <button class="psn-btn" type="submit">
                                    <i class="fa-solid fa-check"></i>
                                    Tandai Dibaca
                                </button>
                            </form>
                        <?php endif; ?>

                        <form action="proses_notifikasi.php" method="POST">
                            <input type="hidden" name="csrf_token"
                                value="<?= $csrfNotif ?>">
                            <input type="hidden" name="aksi"
                                value="hapus">
                            <input type="hidden" name="id_notifikasi"
                                value="<?= (int)$notif['id_notifikasi'] ?>">

                            <button class="psn-btn danger" type="submit"
                                onclick="return confirm('Hapus notifikasi ini?')">
                                <i class="fa-solid fa-trash"></i>
                                Hapus
                            </button>
                        </form>

                    </div>
                </div>
            </article>

        <?php endforeach; ?>

    <?php endif; ?>

    <div id="psn-filter-empty" class="psn-empty" hidden>
        <i class="fa-solid fa-check-circle"></i>
        <p>Tidak ada notifikasi pada kategori ini.</p>
    </div>

</div>