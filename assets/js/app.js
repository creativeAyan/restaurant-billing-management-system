// ============================================================
// RESTAURANT BILLING SYSTEM - MAIN JS
// ============================================================

// Clock
function updateClock() {
    const now = new Date();
    const timeEl = document.getElementById('topbarTime');
    const dateEl = document.getElementById('topbarDate');
    if (timeEl) {
        timeEl.textContent = now.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }
    if (dateEl) {
        dateEl.textContent = now.toLocaleDateString('en-IN', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
    }
}
updateClock();
setInterval(updateClock, 1000);

// Sidebar toggle
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('open');
}

// Quantity control for order items
function changeQty(itemId, delta) {
    const qtyEl = document.getElementById('qty_' + itemId);
    if (!qtyEl) return;
    let qty = parseInt(qtyEl.textContent) + delta;
    if (qty < 0) qty = 0;
    qtyEl.textContent = qty;
    updateOrderSummary();
}

// Order summary calculation
function updateOrderSummary() {
    let subtotal = 0;
    document.querySelectorAll('.order-item-row').forEach(row => {
        const qty = parseInt(row.querySelector('.qty-num')?.textContent || 0);
        const price = parseFloat(row.dataset.price || 0);
        const total = qty * price;
        const totalEl = row.querySelector('.item-total');
        if (totalEl) totalEl.textContent = formatCurrency(total);
        subtotal += total;
    });
    const taxRate = parseFloat(document.getElementById('taxRate')?.value || 5) / 100;
    const serviceRate = parseFloat(document.getElementById('serviceRate')?.value || 5) / 100;
    const discount = parseFloat(document.getElementById('discountAmount')?.value || 0);
    const deliveryCharge = parseFloat(document.getElementById('deliveryCharge')?.value || 0);

    const tax = subtotal * taxRate;
    const service = subtotal * serviceRate;
    const total = subtotal + tax + service - discount + deliveryCharge;

    const setValue = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = formatCurrency(val); };
    setValue('billSubtotal', subtotal);
    setValue('billTax', tax);
    setValue('billService', service);
    setValue('billDiscount', discount);
    setValue('billDelivery', deliveryCharge);
    setValue('billTotal', total);

    const hiddenTotal = document.getElementById('hiddenTotal');
    if (hiddenTotal) hiddenTotal.value = total.toFixed(2);
}

function formatCurrency(amount) {
    return '₹' + amount.toFixed(2);
}

// Menu item selection for new order
function toggleMenuItem(itemId, name, price) {
    const card = document.getElementById('menu_card_' + itemId);
    const existing = document.getElementById('order_item_' + itemId);

    if (existing) {
        // Increase quantity
        const qtyEl = existing.querySelector('.qty-num');
        qtyEl.textContent = parseInt(qtyEl.textContent) + 1;
    } else {
        // Add new item row
        const container = document.getElementById('orderItemsContainer');
        const row = document.createElement('div');
        row.className = 'order-item-row';
        row.id = 'order_item_' + itemId;
        row.dataset.price = price;
        row.innerHTML = `
            <div class="item-name">${name}</div>
            <div class="item-price">₹${parseFloat(price).toFixed(2)}</div>
            <div class="qty-control">
                <button type="button" class="qty-btn" onclick="removeOrDecrement(${itemId})">-</button>
                <span class="qty-num" id="qty_${itemId}">1</span>
                <button type="button" class="qty-btn" onclick="incrementItem(${itemId})">+</button>
            </div>
            <div class="item-total">₹${parseFloat(price).toFixed(2)}</div>
        `;
        container.appendChild(row);
        if (card) {
            card.classList.add('selected');
            const badge = card.querySelector('.item-badge');
            if (badge) { badge.style.display = 'flex'; badge.textContent = '1'; }
        }
    }
    updateOrderSummary();
    syncHiddenItems();
}

function removeOrDecrement(itemId) {
    const row = document.getElementById('order_item_' + itemId);
    if (!row) return;
    const qtyEl = row.querySelector('.qty-num');
    let qty = parseInt(qtyEl.textContent) - 1;
    if (qty <= 0) {
        row.remove();
        const card = document.getElementById('menu_card_' + itemId);
        if (card) { card.classList.remove('selected'); }
    } else {
        qtyEl.textContent = qty;
        const card = document.getElementById('menu_card_' + itemId);
        if (card) { const b = card.querySelector('.item-badge'); if (b) b.textContent = qty; }
    }
    updateOrderSummary();
    syncHiddenItems();
}

function incrementItem(itemId) {
    const row = document.getElementById('order_item_' + itemId);
    if (!row) return;
    const qtyEl = row.querySelector('.qty-num');
    const qty = parseInt(qtyEl.textContent) + 1;
    qtyEl.textContent = qty;
    const card = document.getElementById('menu_card_' + itemId);
    if (card) { const b = card.querySelector('.item-badge'); if (b) b.textContent = qty; }
    updateOrderSummary();
    syncHiddenItems();
}

function syncHiddenItems() {
    const items = [];
    document.querySelectorAll('.order-item-row').forEach(row => {
        const id = row.id.replace('order_item_', '');
        const qty = parseInt(row.querySelector('.qty-num').textContent);
        items.push({ id, qty, price: row.dataset.price });
    });
    const hiddenInput = document.getElementById('hiddenItems');
    if (hiddenInput) hiddenInput.value = JSON.stringify(items);
}

// Filter menu by category
function filterCategory(catId) {
    document.querySelectorAll('.menu-category-section').forEach(section => {
        if (catId === 'all' || section.dataset.cat == catId) {
            section.style.display = '';
        } else {
            section.style.display = 'none';
        }
    });
    document.querySelectorAll('.cat-filter-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.cat == catId || (catId === 'all' && btn.dataset.cat === 'all'));
    });
}

// Search menu items
function searchMenu(query) {
    const q = query.toLowerCase();
    document.querySelectorAll('.menu-item-card').forEach(card => {
        const name = card.dataset.name?.toLowerCase() || '';
        card.style.display = name.includes(q) ? '' : 'none';
    });
}

// Print bill
function printBill() {
    window.print();
}

// Confirm delete
function confirmDelete(url, message) {
    if (confirm(message || 'Are you sure you want to delete this?')) {
        window.location.href = url;
    }
}

// Change table status via AJAX
function updateTableStatus(tableId, status) {
    fetch(`?action=update_status&table_id=${tableId}&status=${status}`)
        .then(r => r.json())
        .then(d => { if (d.success) location.reload(); });
}

// Auto-calculate change (fallback for pages that don't have their own handler)
document.addEventListener('DOMContentLoaded', function () {
    const paidInput = document.getElementById('paidAmountInput');
    const totalEl   = document.getElementById('hiddenTotal');
    // Only attach if the page hasn't already handled it
    if (paidInput && totalEl && !paidInput.dataset.bound) {
        paidInput.dataset.bound = '1';
        paidInput.addEventListener('input', function () {
            const paid   = parseFloat(this.value) || 0;
            const total  = parseFloat(totalEl.value) || 0;
            const change = paid - total;
            const changeEl = document.getElementById('changeDisplay');
            if (changeEl) {
                changeEl.textContent = '₹' + Math.max(0, change).toFixed(2);
                changeEl.style.color = change >= 0 ? '#2d7d4f' : '#c0392b';
            }
        });
    }
});
