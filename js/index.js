const tg      = window.Telegram.WebApp;
const initData = tg.initData || '';
const apiBase  = 'api';
let activeTab  = 'new';

tg.expand();
tg.ready();

// ---- Ma'lumot yuklash ----
async function load() {
    try {
        const qs  = initData ? `initData=${encodeURIComponent(initData)}` : 'user_id=284914591';
        const res = await fetch(`${apiBase}/get-orders.php?${qs}`);
        const data = await res.json();

        if (!res.ok) { showToast(data.error || 'Xatolik', 'error'); return; }

        document.getElementById('driver-name').textContent = '🚗 ' + data.driver.full_name;

        renderTab('new',    data.orders.new);
        renderTab('road',   data.orders.road);
        renderTab('closed', data.orders.closed);

        document.getElementById('skeleton').style.display = 'none';
        switchTab(activeTab);
    } catch (e) {
        document.getElementById('skeleton').innerHTML =
            '<div class="empty"><div class="empty-icon">⚠️</div><div class="empty-text">Server bilan aloqa yo\'q</div></div>';
    }
}

function renderTab(tab, orders) {
    const cnt = document.getElementById('cnt-' + tab);
    cnt.textContent = orders.length > 0 ? orders.length : '';

    const el = document.getElementById('tab-' + tab);

    if (!orders.length) {
        el.innerHTML = '<div class="empty"><div class="empty-icon">📭</div><div class="empty-text">Buyurtma yo\'q</div></div>';
        return;
    }

    const qs = initData ? `&initData=${encodeURIComponent(initData)}` : '&user_id=284914591';
    el.innerHTML = '<div class="order-list">' +
        orders.map(o => `
            <a class="order-card" href="show.php?id=${o.id}${qs}&tab=${tab}">
                <div class="order-card-left">
                    <div class="order-number">#${esc(o.order_number)}</div>
                    <div class="shop-name">${esc(o.shop_name)}</div>
                    <div class="delivery-date">📅 ${fmtDate(o.delivery_date)}${o.delivery_time ? ' ' + o.delivery_time.slice(0,5) : ''}</div>
                </div>
                <div class="order-card-right">›</div>
            </a>
        `).join('') +
    '</div>';
}

function switchTab(tab) {
    activeTab = tab;
    document.querySelectorAll('.tab-btn').forEach(b => {
        b.classList.toggle('active', b.dataset.tab === tab);
    });
    document.querySelectorAll('.tab-pane').forEach(p => {
        p.style.display = p.id === 'tab-' + tab ? 'block' : 'none';
    });
}

function fmtDate(d) {
    if (!d) return '—';
    const [y, m, day] = d.split('-');
    return `${day}.${m}.${y}`;
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

// ---- Tab parametrini URL dan olish ----
const urlTab = new URLSearchParams(location.search).get('tab');
if (urlTab && ['new', 'road', 'closed'].includes(urlTab)) activeTab = urlTab;

load();
