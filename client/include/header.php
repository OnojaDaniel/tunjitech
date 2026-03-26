<?php

?>


<!DOCTYPE html>
<html lang="en" data-theme="light">


<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tunjitech Consulting - Security Alert!</title>
    <link rel="icon" type="image/png" href="../assets/images/tunjitech-favicon.png" sizes="16x16">
    <!-- remix icon font css  -->
    <link rel="stylesheet" href="../assets/css/remixicon.css">
    <!-- BootStrap css -->
    <link rel="stylesheet" href="../assets/css/lib/bootstrap.min.css">
    <!-- Apex Chart css -->
    <link rel="stylesheet" href="../assets/css/lib/apexcharts.css">
    <!-- Data Table css -->
    <link rel="stylesheet" href="../assets/css/lib/dataTables.min.css">
    <!-- Text Editor css -->
    <link rel="stylesheet" href="../assets/css/lib/editor-katex.min.css">
    <link rel="stylesheet" href="../assets/css/lib/editor.atom-one-dark.min.css">
    <link rel="stylesheet" href="../assets/css/lib/editor.quill.snow.css">
    <!-- Date picker css -->
    <link rel="stylesheet" href="../assets/css/lib/flatpickr.min.css">
    <!-- Calendar css -->
    <link rel="stylesheet" href="../assets/css/lib/full-calendar.css">
    <!-- Vector Map css -->
    <link rel="stylesheet" href="../assets/css/lib/jquery-jvectormap-2.0.5.css">
    <!-- Popup css -->
    <link rel="stylesheet" href="../assets/css/lib/magnific-popup.css">
    <!-- Slick Slider css -->
    <link rel="stylesheet" href="../assets/css/lib/slick.css">
    <!-- prism css -->
    <link rel="stylesheet" href="../assets/css/lib/prism.css">
    <!-- file upload css -->
    <link rel="stylesheet" href="../assets/css/lib/file-upload.css">

    <link rel="stylesheet" href="../assets/css/lib/audioplayer.css">
    <!-- main css -->
    <link rel="stylesheet" href="../assets/css/style.css">
<!--    fontawesome-->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Add Leaflet CSS and JS CDN links before the closing </head> tag -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
          crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
            crossorigin=""></script>
</head>
<body>

<aside class="sidebar">
    <button type="button" class="sidebar-close-btn">
        <iconify-icon icon="radix-icons:cross-2"></iconify-icon>
    </button>
    <div>
        <a href="index.html" class="sidebar-logo">
            <img src="../assets/images/tunjitech-logo.png" alt="site logo" class="light-logo">
            <img src="../assets/images/tunjitech-white-logo.png" alt="site logo" class="dark-logo">
            <img src="../assets/images/logo-icon.png" alt="site logo" class="logo-icon">
        </a>
    </div>
    <div class="sidebar-menu-area">
        <ul class="sidebar-menu" id="sidebar-menu">
            <li class="dropdown">
                <a href="dashboard.php">
                    <iconify-icon icon="solar:home-smile-angle-outline" class="menu-icon"></iconify-icon>
                    <span>Dashboard</span>
                </a>

            </li>
            <li class="sidebar-menu-group-title">Application</li>
            <li>
                <a href="alerts.php">
                    <iconify-icon icon="mdi:alert-decagram" class="menu-icon"></iconify-icon>
                    <span> Alerts </span>
                </a>
            </li>
            <li>
                <a  href="change_password.php">
                    <iconify-icon icon="material-symbols:vpn-key-rounded" class="menu-icon"></iconify-icon>
                    <span>Change Password</span>
                </a>
            </li>
            <li>
                <a href="analytics.php">
                    <iconify-icon icon="dashicons:chart-bar" class="menu-icon"></iconify-icon>
                    <span>Analytics</span>
                </a>
            </li>
            <?php if (isClient() && $_SESSION['user_type'] == 'client_company'): ?>
                <li>
                    <a class="nav-link" href="users.php">
                        <iconify-icon icon="tabler:users-group" class="menu-icon"></iconify-icon>
                        <span>User Management</span>
                    </a>
                </li>
            <?php elseif (isClientUser() && isset($_SESSION['client_user_role']) && $_SESSION['client_user_role'] == 'admin'): ?>
                <li>
                    <a class="nav-link" href="users.php">
                        <iconify-icon icon="tabler:users-group" class="menu-icon"></iconify-icon>
                        <span>User Management</span>
                    </a>
                </li>
            <?php endif; ?>
               <li>
                <a href="terms.php">
                    <iconify-icon icon="fa6-solid:handshake" class="menu-icon"></iconify-icon>
                    <span>Terms & Conditions</span>
                </a>
            </li>
            <li>
                <a href="../logout.php">
                    <iconify-icon icon="lucide:power" class="menu-icon"></iconify-icon>
                    <span>logout</span>
                </a>
            </li>

           
        </ul>
    </div>
</aside>
<main class="dashboard-main">
    <div class="navbar-header">
        <div class="row align-items-center justify-content-between">
            <div class="col-auto">
                <div class="d-flex flex-wrap align-items-center gap-4">
                    <button type="button" class="sidebar-toggle">
                        <iconify-icon icon="heroicons:bars-3-solid" class="icon text-2xl non-active"></iconify-icon>
                        <iconify-icon icon="iconoir:arrow-right" class="icon text-2xl active"></iconify-icon>
                    </button>
                    <button type="button" class="sidebar-mobile-toggle">
                        <iconify-icon icon="heroicons:bars-3-solid" class="icon"></iconify-icon>
                    </button>
                    <form class="navbar-search">
                        <input type="text" name="search" placeholder="Search">
                        <iconify-icon icon="ion:search-outline" class="icon"></iconify-icon>
                    </form>
                </div>
            </div>
            <div class="col-auto">
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <button type="button" data-theme-toggle class="w-40-px h-40-px bg-neutral-200 rounded-circle d-flex justify-content-center align-items-center"></button>
                    

                    <div class="dropdown">
                        <button class="d-flex justify-content-center align-items-center rounded-circle" type="button" data-bs-toggle="dropdown">
                             <script src="https://cdn.lordicon.com/lordicon.js"></script>
                            <lord-icon
                                    src="https://cdn.lordicon.com/kdduutaw.json"
                                    trigger="hover"
                                    stroke="bold"
                                    state="hover-looking-around"
                                    colors="primary:#30c9e8,secondary:#9cf4df"
                                    style="width:250px;height:250px" class="w-40-px h-40-px object-fit-cover rounded-circle">
                            </lord-icon>
                        </button>
                        <div class="dropdown-menu to-top dropdown-menu-sm">

                            <ul class="to-top-list">
                                <li>
                                    <a class="dropdown-item text-black px-0 py-8 hover-bg-transparent hover-text-danger d-flex align-items-center gap-3" href="../logout.php">
                                        <iconify-icon icon="lucide:power" class="icon text-xl"></iconify-icon> Log Out
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div><!-- Profile dropdown end -->
                </div>
            </div>
        </div>
    </div>
