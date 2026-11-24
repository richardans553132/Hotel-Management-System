<?php
/**
 * Shared Header Template
 * 
 * Sets up SEO, responsive viewport, Bootstrap 5.3 CSS, Bootstrap Icons,
 * Google Fonts, and the custom premium theme stylesheet.
 */
require_once __DIR__ . '/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Hotel Booking System - Premium Administration Control Panel. Built with PHP, MySQL, and Bootstrap 5.3.">
    <meta name="author" content="Antigravity Admin Team">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . " | Hotel Booking System" : "Hotel Booking System - Administration Panel"; ?></title>
    
    <!-- Bootstrap 5.3 CSS (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Custom CSS Stylesheet -->
    <link href="/hotel/assets/css/style.css?v=1.1" rel="stylesheet">
</head>
<body>
