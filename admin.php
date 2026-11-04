<?php

session_start();
include 'koneksi.php';


/* ========================================
   PROTEKSI HALAMAN ADMIN
======================================== */

if (
    !isset($_SESSION['id_admin']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    header(
        "Location: index.php?error=Akses+ditolak!"
    );
    exit();
}


$nama_admin = htmlspecialchars(
    $_SESSION['nama_lengkap']
);


/* ========================================
   STATISTIK
======================================== */

$sqlStatistik = "
SELECT
    COUNT(*) AS total,
    SUM(status_booking = 'Menunggu') AS menunggu,
    SUM(status_booking = 'Diterima') AS diterima,
    SUM(status_booking = 'Diproses') AS diproses,
    SUM(status_booking = 'Selesai') AS selesai,
    SUM(status_booking = 'Ditolak') AS ditolak
FROM booking
";

$statistik = $koneksi
    ->query($sqlStatistik)
    ->fetch_assoc();


/* ========================================
   AMBIL SEMUA PESANAN
======================================== */

$sql = "
SELECT
    b.id_booking,
    b.tanggal_booking,
    b.jam_booking,
    b.status_booking,
    b.catatan_keluhan,
    b.catatan_admin,
    b.dibuat_pada,

    p.nama_lengkap,
    p.username,

    k.merk,
    k.model,
    k.no_plat,

    GROUP_CONCAT(
        DISTINCT l.nama_layanan
        SEPARATOR ', '
    ) AS layanan

FROM booking b

JOIN pelanggan p
    ON b.id_pelanggan = p.id_pelanggan

JOIN kendaraan k
    ON b.id_kendaraan = k.id_kendaraan

LEFT JOIN detail_booking db
    ON b.id_booking = db.id_booking

LEFT JOIN layanan l
    ON db.id_layanan = l.id_layanan

GROUP BY b.id_booking

ORDER BY
    CASE b.status_booking
        WHEN 'Menunggu' THEN 1
        WHEN 'Diterima' THEN 2
        WHEN 'Diproses' THEN 3
        WHEN 'Selesai' THEN 4
        WHEN 'Ditolak' THEN 5
        ELSE 6
    END,
    b.dibuat_pada DESC
";

$pesanan = $koneksi->query($sql);


function grupStatus($status)
{
    return in_array($status, ['Ditolak', 'Dibatalkan'], true)
        ? 'batal'
        : strtolower($status);
}

function classStatus($status)
{
    switch ($status) {

        case 'Menunggu':
            return 'status-menunggu';

        case 'Diterima':
            return 'status-diterima';

        case 'Diproses':
            return 'status-diproses';

        case 'Selesai':
            return 'status-selesai';

        case 'Ditolak':
            return 'status-ditolak';

        case 'Dibatalkan':
            return 'status-ditolak';

        default:
            return '';
    }
}

?>

<!DOCTYPE html>

<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Admin PitStop.id
    </title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com">

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    <link
        rel="stylesheet"
        href="admin.css?v=<?= filemtime(__DIR__ . '/admin.css') ?>">

</head>


<body>


    <!-- ================================
     NAVBAR ADMIN
================================ -->

    <nav class="admin-navbar">

        <div class="admin-logo">

            <i class="fa-solid fa-car-side"></i>

            PitStop<span>.id</span>

            <small>
                ADMIN
            </small>

        </div>


        <div class="admin-user">

            <span>

                <i class="fa-solid fa-user-shield"></i>

                <?= $nama_admin ?>

            </span>

            <a
                href="logout.php"
                class="logout">

                <i class="fa-solid fa-right-from-bracket"></i>

                Logout

            </a>

        </div>

    </nav>



    <main class="admin-container">


        <div class="admin-header">

            <div>

                <h1>
                    Dashboard Admin
                </h1>

                <p>
                    Kelola booking dan aktivitas
                    bengkel PitStop.id.
                </p>

            </div>

        </div>



        <!-- ================================
         STATISTIK
    ================================= -->

        <section class="stats-grid">


            <div class="stat-card">

                <i class="fa-solid fa-list"></i>

                <div>

                    <span>Total Booking</span>

                    <strong>
                        <?= (int)$statistik['total'] ?>
                    </strong>

                </div>

            </div>


            <div class="stat-card warning">

                <i class="fa-solid fa-clock"></i>

                <div>

                    <span>Menunggu</span>

                    <strong>
                        <?= (int)$statistik['menunggu'] ?>
                    </strong>

                </div>

            </div>


            <div class="stat-card success">

                <i class="fa-solid fa-check"></i>

                <div>

                    <span>Diterima</span>

                    <strong>
                        <?= (int)$statistik['diterima'] ?>
                    </strong>

                </div>

            </div>


            <div class="stat-card process">

                <i class="fa-solid fa-gears"></i>

                <div>

                    <span>Diproses</span>

                    <strong>
                        <?= (int)$statistik['diproses'] ?>
                    </strong>

                </div>

            </div>


            <div class="stat-card finished">

                <i class="fa-solid fa-circle-check"></i>

                <div>

                    <span>Selesai</span>

                    <strong>
                        <?= (int)$statistik['selesai'] ?>
                    </strong>

                </div>

            </div>


        </section>



        <!-- ================================
         DAFTAR BOOKING
    ================================= -->

        <section class="orders-section">


            <div class="section-header">

                <div>

                    <h2>
                        Pesanan Masuk
                    </h2>

                    <p>
                        Kelola seluruh booking pelanggan.
                    </p>

                </div>

            </div>



            <div class="orders-toolbar">

                <div class="filter-tabs" id="filter-tabs" role="tablist" aria-label="Filter status booking">
                    <button type="button" class="filter-tab active" data-filter="semua" role="tab" aria-selected="true">Semua <span class="count">0</span></button>
                    <button type="button" class="filter-tab" data-filter="menunggu" role="tab" aria-selected="false">Menunggu <span class="count">0</span></button>
                    <button type="button" class="filter-tab" data-filter="diterima" role="tab" aria-selected="false">Diterima <span class="count">0</span></button>
                    <button type="button" class="filter-tab" data-filter="diproses" role="tab" aria-selected="false">Diproses <span class="count">0</span></button>
                    <button type="button" class="filter-tab" data-filter="selesai" role="tab" aria-selected="false">Selesai <span class="count">0</span></button>
                    <button type="button" class="filter-tab" data-filter="batal" role="tab" aria-selected="false">Ditolak / Batal <span class="count">0</span></button>
                </div>

                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input
                        type="search"
                        id="search-order"
                        placeholder="Cari nama, plat, layanan, ID..."
                        aria-label="Cari booking">
                </div>

            </div>

            <div class="table-wrapper">


                <table>


                    <thead>

                        <tr>

                            <th>ID</th>

                            <th>Pelanggan</th>

                            <th>Kendaraan</th>

                            <th>Layanan</th>

                            <th>Jadwal</th>

                            <th>Keluhan</th>

                            <th>Status</th>

                            <th>Aksi</th>

                        </tr>

                    </thead>


                    <tbody>


                        <?php if ($pesanan->num_rows > 0): ?>


                            <?php while ($row = $pesanan->fetch_assoc()): ?>


                                <tr
                                    class="order-row"
                                    data-status="<?= grupStatus($row['status_booking']) ?>"
                                    data-search="<?= htmlspecialchars(strtolower(
                                                        'bk-' . str_pad($row['id_booking'], 5, '0', STR_PAD_LEFT) . ' ' .
                                                            $row['nama_lengkap'] . ' ' . $row['username'] . ' ' .
                                                            $row['merk'] . ' ' . $row['model'] . ' ' . $row['no_plat'] . ' ' .
                                                            ($row['layanan'] ?? '')
                                                    ), ENT_QUOTES, 'UTF-8') ?>">


                                    <td data-label="ID">

                                        <strong>

                                            #BK-<?= str_pad(
                                                    $row['id_booking'],
                                                    5,
                                                    '0',
                                                    STR_PAD_LEFT
                                                ) ?>

                                        </strong>

                                    </td>


                                    <td data-label="Pelanggan">

                                        <strong>

                                            <?= htmlspecialchars(
                                                $row['nama_lengkap']
                                            ) ?>

                                        </strong>

                                        <small>

                                            @<?= htmlspecialchars(
                                                    $row['username']
                                                ) ?>

                                        </small>

                                    </td>


                                    <td data-label="Kendaraan">

                                        <?= htmlspecialchars(
                                            $row['merk']
                                        ) ?>

                                        <?= htmlspecialchars(
                                            $row['model']
                                        ) ?>

                                        <small>

                                            <?= htmlspecialchars(
                                                $row['no_plat']
                                            ) ?>

                                        </small>

                                    </td>


                                    <td data-label="Layanan">

                                        <?= htmlspecialchars(
                                            $row['layanan'] ?? '-'
                                        ) ?>

                                    </td>


                                    <td data-label="Jadwal">

                                        <?= date(
                                            'd M Y',
                                            strtotime(
                                                $row['tanggal_booking']
                                            )
                                        ) ?>

                                        <small>

                                            <?= sprintf(
                                                '%02d:00',
                                                (int)$row['jam_booking']
                                            ) ?>

                                        </small>

                                    </td>


                                    <td class="keluhan" data-label="Keluhan">

                                        <?= htmlspecialchars(
                                            $row['catatan_keluhan']
                                        ) ?>

                                    </td>


                                    <td data-label="Status">

                                        <span class="
                                    status
                                    <?= classStatus(
                                        $row['status_booking']
                                    ) ?>
                                ">

                                            <?= htmlspecialchars(
                                                $row['status_booking']
                                            ) ?>

                                        </span>


                                        <?php if (
                                            !empty($row['catatan_admin'])
                                        ): ?>

                                            <small class="admin-note">

                                                <?= htmlspecialchars(
                                                    $row['catatan_admin']
                                                ) ?>

                                            </small>

                                        <?php endif; ?>

                                    </td>


                                    <td data-label="Aksi">


                                        <!-- MENUNGGU -->

                                        <?php if (
                                            $row['status_booking']
                                            === 'Menunggu'
                                        ): ?>


                                            <form
                                                action="proses_admin_booking.php"
                                                method="POST"
                                                class="action-form">

                                                <input
                                                    type="hidden"
                                                    name="id_booking"
                                                    value="<?= $row['id_booking'] ?>">


                                                <input
                                                    type="text"
                                                    name="catatan_admin"
                                                    placeholder="Alasan jika ditolak...">


                                                <div class="action-buttons">


                                                    <button
                                                        type="submit"
                                                        name="aksi"
                                                        value="terima"
                                                        class="btn-accept">

                                                        <i class="fa-solid fa-check"></i>

                                                        Terima

                                                    </button>


                                                    <button
                                                        type="submit"
                                                        name="aksi"
                                                        value="tolak"
                                                        class="btn-reject">

                                                        <i class="fa-solid fa-xmark"></i>

                                                        Tolak

                                                    </button>


                                                </div>


                                            </form>



                                            <!-- DITERIMA -->

                                        <?php elseif (
                                            $row['status_booking']
                                            === 'Diterima'
                                        ): ?>


                                            <form
                                                action="proses_admin_booking.php"
                                                method="POST">

                                                <input
                                                    type="hidden"
                                                    name="id_booking"
                                                    value="<?= $row['id_booking'] ?>">

                                                <button
                                                    name="aksi"
                                                    value="proses"
                                                    class="btn-process">

                                                    <i class="fa-solid fa-gears"></i>

                                                    Mulai Servis

                                                </button>

                                            </form>



                                            <!-- DIPROSES -->

                                        <?php elseif (
                                            $row['status_booking']
                                            === 'Diproses'
                                        ): ?>


                                            <form
                                                action="proses_admin_booking.php"
                                                method="POST">

                                                <input
                                                    type="hidden"
                                                    name="id_booking"
                                                    value="<?= $row['id_booking'] ?>">

                                                <button
                                                    name="aksi"
                                                    value="selesai"
                                                    class="btn-finish">

                                                    <i class="fa-solid fa-circle-check"></i>

                                                    Selesai

                                                </button>

                                            </form>



                                        <?php else: ?>


                                            <span class="no-action">

                                                Tidak ada aksi

                                            </span>


                                        <?php endif; ?>


                                    </td>


                                </tr>


                            <?php endwhile; ?>


                        <?php else: ?>


                            <tr>

                                <td
                                    colspan="8"
                                    class="empty">

                                    Belum ada booking.

                                </td>

                            </tr>


                        <?php endif; ?>


                    <tr id="filter-empty" hidden>
                            <td colspan="8" class="empty">
                                Tidak ada booking yang cocok dengan filter.
                            </td>
                        </tr>

                    </tbody>


                </table>


            </div>

            <div class="pagination" id="pagination" hidden>
                <span id="page-info"></span>
                <div class="pagination-buttons" id="page-buttons"></div>
            </div>


        </section>


    </main>

    <script>
        (function() {
            const PER_HALAMAN = 10;
            const baris = Array.from(document.querySelectorAll('tr.order-row'));
            const tabs = Array.from(document.querySelectorAll('.filter-tab'));
            const cari = document.getElementById('search-order');
            const kosong = document.getElementById('filter-empty');
            const pager = document.getElementById('pagination');
            const info = document.getElementById('page-info');
            const tombol = document.getElementById('page-buttons');

            let filter = 'semua';
            let halaman = 1;

            // Jumlah per status di setiap tab
            tabs.forEach(tab => {
                const f = tab.dataset.filter;
                const n = f === 'semua' ?
                    baris.length :
                    baris.filter(r => r.dataset.status === f).length;
                tab.querySelector('.count').textContent = n;
            });

            function cocok(r) {
                const q = cari.value.trim().toLowerCase();
                return (filter === 'semua' || r.dataset.status === filter) &&
                    (q === '' || r.dataset.search.includes(q));
            }

            function buatTombol(teks, tujuan, aktif, mati, label) {
                const b = document.createElement('button');
                b.type = 'button';
                b.textContent = teks;
                b.setAttribute('aria-label', label);
                if (aktif) b.classList.add('active');
                b.disabled = !!mati;
                b.addEventListener('click', () => {
                    halaman = tujuan;
                    render();
                });
                tombol.appendChild(b);
            }

            function render() {
                const hasil = baris.filter(cocok);
                const totalHal = Math.max(1, Math.ceil(hasil.length / PER_HALAMAN));
                halaman = Math.min(halaman, totalHal);
                const mulai = (halaman - 1) * PER_HALAMAN;

                baris.forEach(r => r.hidden = true);
                hasil.slice(mulai, mulai + PER_HALAMAN).forEach(r => r.hidden = false);

                if (kosong) kosong.hidden = hasil.length > 0 || baris.length === 0;

                pager.hidden = hasil.length <= PER_HALAMAN;
                info.textContent = 'Menampilkan ' + (hasil.length ? mulai + 1 : 0) + '-' +
                    Math.min(mulai + PER_HALAMAN, hasil.length) + ' dari ' + hasil.length + ' booking';

                tombol.replaceChildren();
                buatTombol('‹', halaman - 1, false, halaman === 1, 'Halaman sebelumnya');
                for (let p = 1; p <= totalHal; p++) {
                    buatTombol(String(p), p, p === halaman, false, 'Halaman ' + p);
                }
                buatTombol('›', halaman + 1, false, halaman === totalHal, 'Halaman berikutnya');
            }

            tabs.forEach(tab => tab.addEventListener('click', () => {
                tabs.forEach(t => {
                    t.classList.remove('active');
                    t.setAttribute('aria-selected', 'false');
                });
                tab.classList.add('active');
                tab.setAttribute('aria-selected', 'true');
                filter = tab.dataset.filter;
                halaman = 1;
                render();
            }));

            cari.addEventListener('input', () => {
                halaman = 1;
                render();
            });

            render();
        })();
    </script>



</body>

</html>