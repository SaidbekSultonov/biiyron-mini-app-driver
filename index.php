<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Haydovchi — Buyurtmalar</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/app.css">
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
</head>
<body>

<div class="header">
    <div class="header-title" id="driver-name">🚗 Buyurtmalar</div>
</div>

<div class="tabs">
    <div class="tab-btn active" data-tab="new" onclick="switchTab('new')">
        Yangi<br><span id="cnt-new"></span>
    </div>
    <div class="tab-btn" data-tab="road" onclick="switchTab('road')">
        Yo'lda<br><span id="cnt-road"></span>
    </div>
    <div class="tab-btn" data-tab="closed" onclick="switchTab('closed')">
        Yopilgan<br><span id="cnt-closed"></span>
    </div>
</div>

<!-- Skeleton -->
<div id="skeleton">
    <div class="skeleton-card skeleton" style="margin-top:10px"></div>
    <div class="skeleton-card skeleton" style="margin-top:8px"></div>
    <div class="skeleton-card skeleton" style="margin-top:8px"></div>
</div>

<div id="tab-new"    class="tab-pane" style="display:none"></div>
<div id="tab-road"   class="tab-pane" style="display:none"></div>
<div id="tab-closed" class="tab-pane" style="display:none"></div>

<div class="toast" id="toast"></div>

<script src="js/index.js"></script>
</body>
</html>
