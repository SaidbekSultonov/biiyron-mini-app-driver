<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Buyurtma</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/app.css">
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
</head>
<body class="show-body">

<div class="header" id="page-header">
    <a class="back-btn" href="index.php" id="back-link">&#8592;</a>
    <div class="header-title" id="page-title">Yuklanmoqda...</div>
</div>

<!-- Skeleton -->
<div id="skeleton">
    <div class="skeleton-card skeleton" style="margin-top:12px; height:160px"></div>
    <div class="skeleton-card skeleton" style="margin-top:8px; height:90px"></div>
    <div class="skeleton-card skeleton" style="margin-top:8px; height:90px"></div>
</div>

<div id="page-content" style="display:none"></div>

<!-- Modal (plus tugma) -->
<div class="modal-overlay" id="modal" style="display:none" onclick="modalBgClick(event)">
    <div class="modal-sheet">
        <div class="modal-header">
            <div class="modal-title" id="modal-title">Qaysi buyurtmadan olish?</div>
            <div class="modal-subtitle" id="modal-subtitle"></div>
        </div>
        <div class="modal-body" id="modal-body"></div>
        <div class="modal-footer">
            <button class="btn-modal-cancel" onclick="closeModal()">Bekor</button>
            <button class="btn-modal-confirm" id="btn-modal-confirm" disabled onclick="confirmTransfer()">Tasdiqlash</button>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script src="js/show.js?v=<?php echo time(); ?>"></script>
</body>
</html>
