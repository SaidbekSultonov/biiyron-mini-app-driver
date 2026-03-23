const tg       = window.Telegram.WebApp;
const params   = new URLSearchParams(location.search);
const orderId  = parseInt(params.get('id') || '0');
const initData = tg.initData || params.get('initData') || '';
const backTab  = params.get('tab') || 'new';
const apiBase  = 'api';

tg.expand();
tg.ready();
tg.BackButton.show();
tg.BackButton.onClick(() => goBack());

// ---- Back link ----
const backHref = `index.php?tab=${backTab}` + (initData ? `&initData=${encodeURIComponent(initData)}` : '&user_id=284914591');
document.getElementById('back-link').href = backHref;

let order         = null;
let selectedSrcId = null;   // modal da tanlangan source item id
let pendingItemId = null;   // qaysi item uchun modal ochilgan
let pendingProdId = null;   // product_id (faqat plus uchun)
let modalMode     = 'plus'; // 'plus' | 'minus'

// ===================== LOAD =====================
async function loadOrder() {
    if (!orderId) { showContent('<div class="empty"><div class="empty-icon">⚠️</div><div class="empty-text">ID yo\'q</div></div>'); return; }
    try {
        const qs  = initData ? `initData=${encodeURIComponent(initData)}` : 'user_id=284914591';
        const res = await fetch(`${apiBase}/get-order.php?id=${orderId}&${qs}`);
        const d   = await res.json();
        if (!res.ok) { showContent(`<div class="empty"><div class="empty-text">❌ ${d.error}</div></div>`); return; }
        order = d.order;
        render();
    } catch (e) {
        showContent('<div class="empty"><div class="empty-text">Server bilan aloqa yo\'q</div></div>');
    }
}

// ===================== RENDER =====================
function render() {
    document.getElementById('page-title').textContent = '#' + order.order_number;
    document.getElementById('skeleton').style.display  = 'none';

    const s = order.status;

    let html = '';
    // --- Info blok ---
    html += `<div class="info-section" style="margin-top:12px">
        <div class="info-section-title">Buyurtma ma'lumotlari</div>
        <div class="info-row"><span class="info-label">Holat</span>
            <span class="status-badge ${s===2?'badge-new':s===3?'badge-sent':'badge-closed'}">
                ${s===2?'Yangi':s===3?'Yo\'lda':'Yopilgan'}
            </span>
        </div>
        <div class="info-row"><span class="info-label">Do'kon</span><span class="info-value">${esc(order.shop_name)}</span></div>
        <div class="info-row"><span class="info-label">Manzil</span><span class="info-value">${esc(order.shop_address||'—')}${order.district_name?', '+esc(order.district_name):''}</span></div>
        <div class="info-row"><span class="info-label">Mijoz</span><span class="info-value">${esc(order.client_name)}</span></div>
        <div class="info-row"><span class="info-label">Yetkazish</span><span class="info-value">${fmtDate(order.delivery_date)}${order.delivery_time?' '+order.delivery_time.slice(0,5):''}</span></div>
        <div class="info-row"><span class="info-label">Yetkazish narxi</span><span class="info-value">${fmtMoney(order.delivery_price ?? 0)} so'm</span></div>
        ${order.comment?`<div class="info-row"><span class="info-label">Izoh</span><span class="info-value">${esc(order.comment)}</span></div>`:''}
    </div>`;

    // --- Mahsulotlar ---
    html += `<div class="products-section">
        <div class="products-title">Mahsulotlar (${order.items.length})</div>`;

    order.items.forEach(item => {
        const qtyDisplay = item.product_type === 2
            ? item.quantity.toFixed(1) + ' kg'
            : item.quantity.toFixed(0) + ' dona';
        const isZero     = item.quantity <= 0;
        const weightText = item.product_weight > 0 ? fmtWeight(item.product_weight) : '';
        const rejected   = order.is_rejected;

        const controls = (s === 3)
            ? `<div class="product-controls">
                <button class="btn-ctrl btn-minus" data-id="${item.item_id}" onclick="onMinus(${item.item_id})"
                    ${(rejected||isZero)?'disabled':''}>−</button>
                <span class="item-qty${isZero?' zero':''}" id="qty-${item.item_id}">${qtyDisplay}</span>
                <button class="btn-ctrl btn-plus"  data-id="${item.item_id}" onclick="onPlus(${item.item_id},${item.product_id})"
                    ${rejected?'disabled':''}>+</button>
               </div>`
            : `<div class="item-qty" style="font-size:13px;color:#666">${qtyDisplay}</div>`;

        html += `<div class="product-item${rejected?' rejected-item':''}" id="pitem-${item.item_id}">
            <img class="product-img" src="${item.image_url||'css/no-image.png'}"
                 onerror="this.src='css/no-image.png'" alt="">
            <div class="product-info">
                <div class="product-name">${esc(item.name)}</div>
                <div class="product-price">${fmtMoney(item.price)} so'm</div>
                ${weightText?`<div class="product-weight">${weightText}</div>`:''}
            </div>
            ${controls}
        </div>`;
    });

    html += '</div>';

    // --- Status 4: o'zgargan mahsulotlar ---
    if (s === 4 && order.change_logs && order.change_logs.length > 0) {
        html += `<div class="info-section" style="margin-top:12px">
            <div class="info-section-title">O'zgargan mahsulotlar</div>`;
        order.change_logs.forEach(cl => {
            const orig = Number.isInteger(cl.qty_original) ? cl.qty_original : cl.qty_original.toFixed(1);
            const fin  = Number.isInteger(cl.qty_final)    ? cl.qty_final    : cl.qty_final.toFixed(1);
            html += `<div class="info-row">
                <span class="info-label" style="flex:1">${esc(cl.product_name)}</span>
                <span class="info-value" style="color:#ff4757">${orig} → ${fin}</span>
            </div>`;
        });
        html += `</div>`;
    }

    // --- Status 3 uchun qo'shimcha ---
    if (s === 3) {
        // Izoh
        html += `<div class="comment-section">
            <div class="comment-label" id="comment-label">Izoh</div>
            <textarea class="comment-textarea" id="comment-textarea"
                placeholder="Yetkazish haqida izoh..."
                onblur="saveComment()">${esc(order.driver_comment||'')}</textarea>
        </div>`;

        // Bottom section
        html += `<div class="bottom-section">
            <label class="checkbox-row">
                <input type="checkbox" id="chk-fee" ${order.delivery_fee_paid?'checked':''}
                    onchange="onFeePaidChange(this.checked)">
                <span class="checkbox-label">✅ Yetkazib berish haqi berildi</span>
            </label>
            <div class="checkbox-divider"></div>
            <label class="checkbox-row rejected-row">
                <input type="checkbox" id="chk-rejected" ${order.is_rejected?'checked':''}
                    onchange="onRejectedChange(this.checked)">
                <span class="checkbox-label" style="color:#ff4757">❌ Buyurtma rad qilindi</span>
            </label>
            <button class="btn-close-order" id="btn-close" onclick="closeOrder()">
                Buyurtmani yopish
            </button>
        </div>`;
    }

    // --- Status 2 uchun tugma ---
    if (s === 2) {
        html += `<button class="btn-start-delivery" id="btn-start" onclick="startDelivery()">
            🚗 Yo'lga chiqdim
        </button>`;
    }

    showContent(html);
    checkCommentRequired();
}

function showContent(html) {
    const el = document.getElementById('page-content');
    el.innerHTML = html;
    el.style.display = 'block';
}

// ===================== STATUS 2 → 3 =====================
async function startDelivery() {
    const btn = document.getElementById('btn-start');
    btn.disabled = true;
    btn.textContent = 'Saqlanmoqda...';

    const res  = await post('start-delivery.php', { order_id: orderId });
    const data = await res.json();

    if (data.success) {
        showToast('✅ Yo\'lga chiqdingiz!', 'success');
        setTimeout(() => goBack(), 1000);
    } else {
        showToast(data.error || 'Xatolik', 'error');
        btn.disabled = false;
        btn.textContent = '🚗 Yo\'lga chiqdim';
    }
}

// ===================== PLUS — modal =====================
async function onPlus(itemId, productId) {
    pendingItemId = itemId;
    pendingProdId = productId;
    selectedSrcId = null;
    modalMode     = 'plus';
    openPlusModal(productId);
}

async function openPlusModal(productId) {
    const item = order.items.find(i => i.item_id == pendingItemId);
    showModal('Qaysi buyurtmadan olish?', item ? item.name : '');

    const qs  = initData ? `&initData=${encodeURIComponent(initData)}` : '&user_id=284914591';
    const res = await fetch(`${apiBase}/get-source-orders.php?product_id=${productId}&exclude_order_id=${orderId}${qs}`);
    const d   = await res.json();

    if (!d.sources || d.sources.length === 0) {
        document.getElementById('modal-body').innerHTML =
            `<div class="modal-no-source">⚠️ Siz bu mahsulotdan qo'sha olmaysiz<br><small style="color:#aaa">Boshqa buyurtmalarda mavjud emas</small></div>`;
        return;
    }

    document.getElementById('modal-body').innerHTML = d.sources.map(src => {
        const qtyStr = src.product_type === 2
            ? src.quantity.toFixed(1) + ' kg'
            : src.quantity.toFixed(0) + ' dona';
        return `<div class="modal-source-item" data-item-id="${src.item_id}" onclick="selectSource(${src.item_id}, this)">
            <div>
                <div class="modal-source-num">#${esc(src.order_number)}</div>
                <div class="modal-source-shop">${esc(src.shop_name)}</div>
            </div>
            <div class="modal-source-qty">${qtyStr}</div>
        </div>`;
    }).join('');
}

// ===================== MINUS =====================
async function onMinus(itemId) {
    const item = order.items.find(i => i.item_id == itemId);
    if (!item || item.quantity <= 0) return;

    // Avval bu item uchun transfer manbalari borligini tekshirish
    const qs  = initData ? `&initData=${encodeURIComponent(initData)}` : '&user_id=284914591';
    const res = await fetch(`${apiBase}/get-minus-sources.php?item_id=${itemId}${qs}`);
    const d   = await res.json();

    if (d.sources && d.sources.length > 0) {
        // Transfer manbalari bor — qaysi buyurtmaga qaytarishni tanlash kerak
        pendingItemId = itemId;
        selectedSrcId = null;
        modalMode     = 'minus';
        showModal('Qaysi buyurtmaga qaytarish?', item.name);
        document.getElementById('modal-body').innerHTML = d.sources.map(src =>
            `<div class="modal-source-item" data-item-id="${src.source_order_item_id}"
                  onclick="selectSource(${src.source_order_item_id}, this)">
                <div>
                    <div class="modal-source-num">#${esc(src.order_number)}</div>
                    <div class="modal-source-shop">${esc(src.shop_name)}</div>
                </div>
                <div class="modal-source-qty">${src.transfer_count} ta</div>
            </div>`
        ).join('');
        return;
    }

    // Transfer yo'q — to'g'ridan-to'g'ri kamaytirish
    await directDecrease(itemId);
}

async function directDecrease(itemId) {
    const res  = await post('decrease-product.php', { item_id: itemId });
    const data = await res.json();

    if (!data.success) { showToast(data.error || 'Xatolik', 'error'); return; }

    const item = order.items.find(i => i.item_id == itemId);
    if (item) item.quantity = data.new_qty;
    updateQtyDisplay(itemId, data.new_qty);

    if (data.need_comment) {
        const lbl = document.getElementById('comment-label');
        const ta  = document.getElementById('comment-textarea');
        if (lbl) { lbl.textContent = 'Izoh (majburiy — mahsulot 0 ga tushdi)'; lbl.classList.add('required-hint'); }
        if (ta)  { ta.classList.add('error'); ta.focus(); }
    }

    checkCommentRequired();
}

// ===================== MODAL SHARED =====================
function showModal(title, subtitle) {
    document.getElementById('modal-title').textContent    = title;
    document.getElementById('modal-subtitle').textContent = subtitle || '';
    document.getElementById('modal-body').innerHTML       = '<div class="modal-no-source">Yuklanmoqda...</div>';
    document.getElementById('btn-modal-confirm').disabled = true;
    document.getElementById('modal').style.display        = 'flex';
    selectedSrcId = null;
}

function selectSource(itemId, el) {
    document.querySelectorAll('.modal-source-item').forEach(r => r.classList.remove('selected'));
    el.classList.add('selected');
    selectedSrcId = itemId;
    document.getElementById('btn-modal-confirm').disabled = false;
}

async function confirmTransfer() {
    if (!selectedSrcId || !pendingItemId) return;
    if (modalMode === 'minus') {
        await confirmReverseTransfer();
    } else {
        await confirmPlusTransfer();
    }
}

async function confirmPlusTransfer() {
    const btn = document.getElementById('btn-modal-confirm');
    btn.disabled = true; btn.textContent = '...';

    const res  = await post('transfer-product.php', { to_item_id: pendingItemId, from_item_id: selectedSrcId });
    const data = await res.json();
    btn.textContent = 'Tasdiqlash';

    if (!data.success) {
        showToast(data.error || 'Xatolik', 'error');
        btn.disabled = false;
        return;
    }

    const item = order.items.find(i => i.item_id == pendingItemId);
    if (item) item.quantity = data.new_to_qty;
    updateQtyDisplay(pendingItemId, data.new_to_qty);
    closeModal();
    showToast('✅ Ko\'paytirildi', 'success');

    if (data.new_from_qty <= 0) {
        setTimeout(() => openPlusModal(pendingProdId), 400);
    }
}

async function confirmReverseTransfer() {
    const btn = document.getElementById('btn-modal-confirm');
    btn.disabled = true; btn.textContent = '...';

    const res  = await post('reverse-transfer.php', { to_item_id: pendingItemId, source_item_id: selectedSrcId });
    const data = await res.json();
    btn.textContent = 'Tasdiqlash';

    if (!data.success) {
        showToast(data.error || 'Xatolik', 'error');
        btn.disabled = false;
        return;
    }

    const item = order.items.find(i => i.item_id == pendingItemId);
    if (item) item.quantity = data.new_to_qty;
    updateQtyDisplay(pendingItemId, data.new_to_qty);
    closeModal();
    showToast('↩️ Qaytarildi', 'success');

    if (data.new_to_qty <= 0) {
        const lbl = document.getElementById('comment-label');
        const ta  = document.getElementById('comment-textarea');
        if (lbl) { lbl.textContent = 'Izoh (majburiy — mahsulot 0 ga tushdi)'; lbl.classList.add('required-hint'); }
        if (ta)  { ta.classList.add('error'); ta.focus(); }
    }
    checkCommentRequired();
}

function closeModal() {
    document.getElementById('modal').style.display = 'none';
    selectedSrcId = null;
}

function modalBgClick(e) {
    if (e.target.id === 'modal') closeModal();
}

function updateQtyDisplay(itemId, qty) {
    const el = document.getElementById('qty-' + itemId);
    if (!el) return;
    const item = order.items.find(i => i.item_id == itemId);
    if (!item) return;
    const isZero = qty <= 0;
    el.textContent = item.product_type === 2
        ? qty.toFixed(1) + ' kg'
        : Math.max(0, Math.round(qty)) + ' dona';
    el.className = 'item-qty' + (isZero ? ' zero' : '');

    // Minus tugmasini 0 da o'chirib qo'yamiz
    const btnMinus = document.querySelector(`.btn-ctrl.btn-minus[data-id="${itemId}"]`);
    if (btnMinus) btnMinus.disabled = isZero;
}

// ===================== COMMENT =====================
function checkCommentRequired() {
    if (!order || order.status !== 3) return;
    const hasZero = order.items.some(i => i.quantity <= 0);
    const lbl     = document.getElementById('comment-label');
    const ta      = document.getElementById('comment-textarea');
    if (!lbl || !ta) return;
    if (hasZero && !ta.value.trim()) {
        lbl.textContent = 'Izoh (majburiy — mahsulot 0 ga tushdi)';
        lbl.classList.add('required-hint');
        ta.classList.add('error');
    } else {
        lbl.textContent = 'Izoh';
        lbl.classList.remove('required-hint');
        ta.classList.remove('error');
    }
}

let commentTimer = null;
function saveComment() {
    clearTimeout(commentTimer);
    const ta = document.getElementById('comment-textarea');
    if (!ta) return;
    const val = ta.value.trim();
    commentTimer = setTimeout(() => {
        post('save-comment.php', { order_id: orderId, comment: val });
        checkCommentRequired();
    }, 500);
}

// ===================== CHECKBOXES =====================
async function onFeePaidChange(checked) {
    const res  = await post('toggle-fee-paid.php', { order_id: orderId, value: checked ? 1 : 0 });
    const data = await res.json();
    if (!data.success) {
        showToast(data.error || 'Xatolik', 'error');
        document.getElementById('chk-fee').checked = !checked;
    } else {
        order.delivery_fee_paid = checked ? 1 : 0;
        showToast(checked ? '✅ Yetkazib berish haqi belgilandi' : 'Olib tashlandi', 'success');
    }
}

async function onRejectedChange(checked) {
    const chk = document.getElementById('chk-rejected');
    chk.disabled = true;

    const res  = await post('toggle-rejected.php', { order_id: orderId, value: checked ? 1 : 0 });
    const data = await res.json();
    chk.disabled = false;

    if (!data.success) {
        showToast(data.error || 'Xatolik', 'error');
        chk.checked = !checked;
        return;
    }

    order.is_rejected = checked ? 1 : 0;

    if (checked) {
        // Barcha o'zgartirishlar qaytarildi — sahifani yangilash
        showToast('Barcha o\'zgarishlar qaytarildi', 'error');
        await reloadOrder();
    } else {
        showToast('Rad qilish olib tashlandi', 'success');
        await reloadOrder();
    }
}

async function reloadOrder() {
    const qs  = initData ? `initData=${encodeURIComponent(initData)}` : 'user_id=284914591';
    const res = await fetch(`${apiBase}/get-order.php?id=${orderId}&${qs}`);
    const d   = await res.json();
    if (d.order) { order = d.order; render(); }
}

// ===================== CLOSE ORDER =====================
async function closeOrder() {
    // Izoh majburiy tekshiruv
    const ta = document.getElementById('comment-textarea');
    if (order.items.some(i => i.quantity <= 0) && ta && !ta.value.trim()) {
        checkCommentRequired();
        ta.focus();
        showToast('Izoh kiriting (mahsulot 0 ga tushgan)', 'error');
        return;
    }

    // Avval izohni saqlaymiz
    if (ta && ta.value.trim()) {
        await post('save-comment.php', { order_id: orderId, comment: ta.value.trim() });
    }

    const btn  = document.getElementById('btn-close');
    btn.disabled = true;
    btn.textContent = 'Saqlanmoqda...';

    const res  = await post('close-order.php', { order_id: orderId });
    const data = await res.json();

    if (data.success) {
        showToast('✅ Buyurtma yopildi!', 'success');
        setTimeout(() => goBack('new'), 1000);
    } else {
        showToast(data.error || 'Xatolik', 'error');
        btn.disabled = false;
        btn.textContent = 'Buyurtmani yopish';
    }
}

// ===================== HELPERS =====================
function tgAlert(msg, cb) {
    if (tg.version && parseFloat(tg.version) >= 6.1) {
        tg.showAlert(msg, cb);
    } else {
        alert(msg);
        if (cb) cb();
    }
}

function goBack(tab) {
    const t = tab || backTab;
    const qs = initData ? `&initData=${encodeURIComponent(initData)}` : '&user_id=284914591';
    location.href = `index.php?tab=${t}${qs}`;
}

async function post(endpoint, body) {
    if (initData) body.initData = initData;
    else          body.user_id  = 284914591;

    return fetch(`${apiBase}/${endpoint}`, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify(body),
    });
}

function fmtDate(d) {
    if (!d) return '—';
    const [y, m, day] = d.split('-');
    return `${day}.${m}.${y}`;
}

function fmtMoney(n) {
    return Number(n).toLocaleString('ru-RU');
}

function fmtWeight(g) {
    return g >= 1000 ? (g / 1000).toFixed(2) + ' kg' : g + ' g';
}

function esc(s) {
    const d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
}

function showToast(msg, type = '') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className   = 'toast' + (type ? ' ' + type : '') + ' show';
    setTimeout(() => { t.className = 'toast'; }, 3000);
}

loadOrder();
