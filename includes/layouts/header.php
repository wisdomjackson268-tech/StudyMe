<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <?php 
    if (function_exists('render_seo_head')) {
        render_seo_head($seo_options ?? []);
    }
    ?>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

    <!-- Theme Stylesheets -->
    <link rel="stylesheet" href="<?= function_exists('asset') ? asset('css/variables.css') : 'assets/css/variables.css' ?>">

    <link rel="stylesheet" href="<?= function_exists('asset') ? asset('css/light.css') : 'assets/css/light.css' ?>">

    <link rel="stylesheet" href="<?= function_exists('asset') ? asset('css/dark.css') : 'assets/css/dark.css' ?>">

    <link rel="stylesheet" href="<?= function_exists('asset') ? asset('css/skeleton.css') : 'assets/css/skeleton.css' ?>">

    <link rel="stylesheet" href="<?= function_exists('asset') ? asset('css/style.css') : 'assets/css/style.css' ?>">

    <link rel="stylesheet" href="<?= function_exists('asset') ? asset('css/pages/hero.css') : 'assets/css/pages/hero.css' ?>">
    
    <link rel="stylesheet" href="<?= function_exists('asset') ? asset('css/pages/animations.css') : 'assets/css/pages/animations.css' ?>">

    <link rel="stylesheet" href="<?= function_exists('asset') ? asset('css/responsive.css') : 'assets/css/responsive.css' ?>">

</head>

<body class="light">
<?php include BASE_PATH . '/includes/components/loader.php'; ?>
<?php include BASE_PATH . '/includes/components/navbar.php'; ?>