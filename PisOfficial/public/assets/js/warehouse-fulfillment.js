/**
 * Warehouse Module - Order Fulfillment
 * Handles modal interactions, item staging, and fulfillment AJAX calls.
 */

// Global state for fulfillment logic
let currentOrder = null;
let selectedProductIndex = -1;

// Elements (Initialized on DOM load or when first accessed)
let modal, modalSidebar, modalDetailPane, detailPaneFooter, productDetailContent, noSelectionState;
let markReadyBtn, cancelReadyBtn, finalFulfillBtn, activeFulfillBtn, progressHeader;

document.addEventListener('DOMContentLoaded', () => {
    modal = document.getElementById('fulfillmentModal');
    modalSidebar = document.getElementById('modalSidebar');
    modalDetailPane = document.getElementById('modalDetailPane');
    detailPaneFooter = document.getElementById('detailPaneFooter');
    productDetailContent = document.getElementById('productDetailContent');
    noSelectionState = document.getElementById('noSelectionState');
    markReadyBtn = document.getElementById('markReadyBtn');
    cancelReadyBtn = document.getElementById('cancelReadyBtn');
    finalFulfillBtn = document.getElementById('finalFulfillBtn');
    activeFulfillBtn = document.getElementById('activeFulfillBtn');
    progressHeader = document.getElementById('fulfillmentProgressHeader');

    // Close modal on outside click
    window.onclick = (e) => {
        if (e.target === modal) closeFulfillmentModal();
    };

    // Close button
    const closeBtn = document.getElementById('closeModal');
    if (closeBtn) closeBtn.onclick = closeFulfillmentModal;

    // Attach Action Handlers
    if (markReadyBtn) markReadyBtn.onclick = () => updateItemStatus('ready');
    if (cancelReadyBtn) cancelReadyBtn.onclick = () => updateItemStatus('pending');
    if (activeFulfillBtn) activeFulfillBtn.onclick = fulfillOrderHandler;
});

/**
 * Opens the fulfillment modal and fetches order details.
 */
async function openOrderFulfillmentModal(event, orderId) {
    if (event) event.stopPropagation();
    if (!modal) return;

    // 1. Immediate UI Setup: Show modal backdrop & loading state
    document.body.style.overflow = 'hidden';
    modal.classList.remove('hidden', 'opacity-0', 'pointer-events-none');
    modal.classList.add('flex', 'opacity-100');

    // Reset Sub-states
    selectedProductIndex = -1;
    currentOrder = null;
    document.getElementById('modalOrderHeadline').innerText = `Order #${orderId}`;
    noSelectionState.classList.remove('hidden');
    productDetailContent.classList.add('hidden');
    detailPaneFooter.classList.add('hidden');

    // Show skeleton loading in sidebar
    modalSidebar.innerHTML = `
        <div class="p-8 text-center space-y-4 animate-pulse">
            <div class="h-10 bg-gray-100 rounded-xl w-full"></div>
            <div class="h-10 bg-gray-100 rounded-xl w-3/4 mx-auto"></div>
            <div class="h-10 bg-gray-100 rounded-xl w-full"></div>
            <p class="text-[10px] font-black text-gray-300 uppercase tracking-widest mt-4">Syncing Archive...</p>
        </div>
    `;

    try {
        const formData = new FormData();
        formData.append('action', 'get_order_details');
        formData.append('order_id', orderId);

        const response = await fetch('../include/inc.warehouse/wh.ctrl.php', {
            method: 'POST',
            body: formData
        });

        if (!response.ok) throw new Error(`Server responded with ${response.status}`);

        const data = await response.json();

        if (data.success) {
            currentOrder = data.order;
            document.getElementById('modalOrderHeadline').innerText = `Order #${currentOrder.order_id}`;
            renderSidebar();
            updateFulfillmentProgress();

            if (currentOrder.products && currentOrder.products.length > 0) {
                selectProduct(0);
            } else {
                modalSidebar.innerHTML = '<p class="p-6 text-sm text-gray-400">No products found in this order.</p>';
            }
        } else if (data.message === 'Order not found.' && typeof window.mockOrders !== 'undefined') {
            // MOCK FALLBACK
            const mockMatch = window.mockOrders.find(o => o.order_id == orderId);

            if (mockMatch) {
                currentOrder = JSON.parse(JSON.stringify(mockMatch)); // Deep clone
                document.getElementById('modalOrderHeadline').innerText = `Order #${currentOrder.order_id} (Demo Mode)`;
                renderSidebar();
                updateFulfillmentProgress();
                if (currentOrder.products && currentOrder.products.length > 0) selectProduct(0);
                if (typeof showToast === 'function') showToast('Viewing Mock Order (No DB connection)', 'info');
            } else {
                showErrorInSidebar(data.message);
            }
        } else {
            showErrorInSidebar(data.message || 'The requested order could not be retrieved.');
            if (typeof showToast === 'function') showToast(data.message || 'Failed to load order', 'error');
        }
    } catch (err) {
        console.error("Fulfillment Modal Error:", err);
        showErrorInSidebar('Network error. Could not connect to the server.');
        if (typeof showToast === 'function') showToast('Network or Server error', 'error');
    }
}

function showErrorInSidebar(message) {
    modalSidebar.innerHTML = `
        <div class="p-6 text-center">
            <p class="text-sm text-red-500 font-bold mb-2">Error Loading Order</p>
            <p class="text-xs text-gray-500">${message}</p>
            <button onclick="closeFulfillmentModal()" class="mt-4 text-[10px] font-black uppercase text-gray-400 hover:text-black">Dismiss</button>
        </div>
    `;
}

function closeFulfillmentModal() {
    modal.classList.remove('opacity-100');
    modal.classList.add('pointer-events-none');
    document.body.style.overflow = '';
    setTimeout(() => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }, 300);
}

function renderSidebar() {
    modalSidebar.innerHTML = '';
    currentOrder.products.forEach((product, index) => {
        const isSelected = index === selectedProductIndex;
        const isReady = product.wh_item_status === 'ready';

        const sidebarItem = document.createElement('button');
        sidebarItem.className = `w-full text-left p-4 rounded-xl transition-all duration-300 flex items-center gap-4 ${isSelected ? 'bg-white shadow-md border-transparent' : 'bg-transparent border border-transparent hover:bg-white/40'}`;

        sidebarItem.innerHTML = `
            <div class="w-14 h-14 bg-gray-100 rounded-lg flex-shrink-0 overflow-hidden relative border border-gray-100 shadow-sm">
                <img src="../../public/assets/img/furnitures/${encodeURIComponent(product.img?.trim() || 'default-placeholder.png')}" class="w-full h-full object-cover" onerror="this.onerror=null; this.src='../../public/assets/img/primeLogo.ico'">
            </div>
            <div class="flex-1 min-w-0">
                <h4 class="text-sm font-bold text-gray-900 truncate leading-tight mb-0.5">${product.prod_name}</h4>
                <div class="flex items-center gap-2">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-tight">${product.prod_code}</p>
                    <span class="text-[9px] font-black text-red-600 bg-red-50 px-1.5 rounded-md border border-red-100">x${product.qty_to_pick}</span>
                </div>
            </div>
            ${isReady ? `
                <div class="bg-green-500 text-white rounded-full p-1 border-2 border-white shadow-sm flex items-center justify-center -ml-2 self-start mt-2 relative z-10">
                    <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                </div>
            ` : ''}
        `;

        sidebarItem.onclick = () => selectProduct(index);
        modalSidebar.appendChild(sidebarItem);
    });
}

function selectProduct(index) {
    selectedProductIndex = index;
    const product = currentOrder.products[index];
    const isReady = product.wh_item_status === 'ready';

    noSelectionState.classList.add('hidden');
    productDetailContent.classList.remove('hidden');
    detailPaneFooter.classList.remove('hidden');

    // Populate Fields
    document.getElementById('detailImg').src = `../../public/assets/img/furnitures/${encodeURIComponent(product.img?.trim() || 'default-placeholder.png')}`;
    document.getElementById('detailCode').innerText = product.prod_code;
    document.getElementById('detailName').innerText = product.prod_name;
    document.getElementById('detailQty').innerText = product.qty_to_pick;
    document.getElementById('detailStock').innerText = product.available_stock;

    if (product.variant_name && product.variant_name !== 'Standard') {
        document.getElementById('detailName').innerText += ` (${product.variant_name})`;
    }

    const descEl = document.getElementById('detailDescription');
    descEl.innerText = product.description || `Premium quality ${product.prod_name} build. Requires careful handling and verification of all components before staging.`;

    const locContainer = document.getElementById('detailLocations');
    locContainer.innerHTML = '';
    product.components.forEach(comp => {
        locContainer.innerHTML += `
            <div class="flex items-center gap-3 bg-[#f8fafc] p-4 rounded-xl border-none group hover:bg-blue-50 transition-all duration-300">
                <div class="text-gray-400 group-hover:text-blue-600 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                </div>
                <div class="flex-1">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest leading-none mb-1">${comp.component_name}</p>
                    <p class="text-lg font-black text-gray-900 leading-none tracking-tight">${comp.location || 'N/A'}</p>
                </div>
            </div>
        `;
    });

    if (isReady) {
        markReadyBtn.classList.add('hidden');
        cancelReadyBtn.classList.remove('hidden');
    } else {
        markReadyBtn.classList.remove('hidden');
        cancelReadyBtn.classList.add('hidden');
    }

    renderSidebar();
    updateFulfillmentProgress();
    syncGridState();
}

function syncGridState() {
    if (!currentOrder) return;
    const orderId = currentOrder.order_id;
    const pillContainer = document.getElementById(`status-pill-${orderId}`);

    if (!pillContainer) return;

    const isModified = currentOrder.products.some(p => p.wh_item_status === 'ready');

    if (isModified) {
        pillContainer.innerHTML = `
            <div class="bg-amber-100 text-amber-700 px-2 py-0.5 rounded-md border border-amber-200">
                <span class="text-[8px] font-black uppercase tracking-wider">In Progress</span>
            </div>
        `;
    } else {
        pillContainer.innerHTML = `
            <div class="bg-blue-50 text-blue-600 px-2 py-0.5 rounded-md border border-blue-100">
                <span class="text-[8px] font-black uppercase tracking-wider">New</span>
            </div>
        `;
    }
}

async function updateItemStatus(newStatus) {
    if (selectedProductIndex === -1) return;
    const product = currentOrder.products[selectedProductIndex];

    try {
        const formData = new FormData();
        formData.append('action', 'mark_item_ready');
        formData.append('item_id', product.item_id);
        formData.append('status', newStatus);

        const response = await fetch('../include/inc.warehouse/wh.ctrl.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();
        if (data.success) {
            product.wh_item_status = newStatus;
            if (typeof showToast === 'function') {
                const msg = newStatus === 'ready' ? 'Product marked as ready' : 'Staging cancelled';
                showToast(msg, 'success');
            }
            selectProduct(selectedProductIndex);
        } else {
            if (typeof showToast === 'function') showToast(data.message || 'Verification failed', 'error');
        }
    } catch (err) {
        console.error(err);
    }
}

function updateFulfillmentProgress() {
    const total = currentOrder.products.length;
    const readyItems = currentOrder.products.filter(p => p.wh_item_status === 'ready').length;
    const isAllReady = total > 0 && readyItems === total;

    progressHeader.innerText = `${readyItems} of ${total} products ready`;

    if (isAllReady) {
        finalFulfillBtn.classList.add('hidden');
        activeFulfillBtn.classList.remove('hidden');
    } else {
        finalFulfillBtn.classList.remove('hidden');
        activeFulfillBtn.classList.add('hidden');
    }
}

async function fulfillOrderHandler() {
    if (!currentOrder) return;
    try {
        const formData = new FormData();
        formData.append('action', 'fulfill_order');
        formData.append('order_id', currentOrder.order_id);

        const response = await fetch('../include/inc.warehouse/wh.ctrl.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        if (data.success) {
            if (typeof showToast === 'function') {
                showToast('Order fulfilled successfully', 'success');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                window.location.reload();
            }
        } else {
            if (typeof showToast === 'function') showToast(data.message || 'Fulfillment failed', 'error');
        }
    } catch (err) {
        console.error(err);
    }
}

async function resetAllOrderItems() {
    if (!currentOrder) return;

    const checkbox = document.getElementById('unmarkAllCheckbox');
    if (!checkbox || !checkbox.checked) return;

    try {
        const formData = new FormData();
        formData.append('action', 'reset_all_ready');
        formData.append('order_id', currentOrder.order_id);

        const response = await fetch('../include/inc.warehouse/wh.ctrl.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();
        if (data.success) {
            currentOrder.products.forEach(p => p.wh_item_status = 'pending');
            if (typeof showToast === 'function') showToast('Order progress reset', 'success');
            selectProduct(selectedProductIndex);
            checkbox.checked = false;
        } else {
            if (typeof showToast === 'function') showToast(data.message || 'Reset failed', 'error');
            checkbox.checked = false;
        }
    } catch (err) {
        console.error(err);
        checkbox.checked = false;
    }
}
