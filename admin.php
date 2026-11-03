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
        rel="stylesheet"
        href="admin.css">

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


                                <tr>


                                    <td>

                                        <strong>

                                            #BK-<?= str_pad(
                                                    $row['id_booking'],
                                                    5,
                                                    '0',
                                                    STR_PAD_LEFT
                                                ) ?>

                                        </strong>

                                    </td>


                                    <td>

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


                                    <td>

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


                                    <td>

                                        <?= htmlspecialchars(
                                            $row['layanan'] ?? '-'
                                        ) ?>

                                    </td>


                                    <td>

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


                                    <td class="keluhan">

                                        <?= htmlspecialchars(
                                            $row['catatan_keluhan']
                                        ) ?>

                                    </td>


                                    <td>

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


                                    <td>


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


                    </tbody>


                </table>


            </div>


        </section>


    </main>


</body>

</html>