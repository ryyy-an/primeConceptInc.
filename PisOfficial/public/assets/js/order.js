/**
 * PIS Consolidated Order Management System
 * Handles Admin POS Checkout, Showroom Order Requests, and Admin Review
 */

// Shared State
let baseOrderTotal = 0; // Used for Admin Order Review
let currentCheckoutTotal = 0; // Used for POS/Showroom Checkout
let originalRequesterName = ""; // Used to remember the current order's requester

// ==========================================
// 1. SHARED CHECKOUT UI LOGIC
// ==========================================

/**
 * Opens the Cart Table Modal with a scale-up animation
 */
function openProceedModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;

    const box = modal.querySelector(".modal-box");

    // Dynamic population for reviewCartModal
    if (id === "reviewCartModal") {
        populateOrderSummary();
    }

    modal.classList.remove("opacity-0", "pointer-events-none");
    if (box) {
        box.classList.remove("scale-95");
        box.classList.add("scale-100");
    }
}

/**
 * Closes the Cart Table with a scale-down animation
 */
function closeProceedModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;

    const box = modal.querySelector(".modal-box");

    modal.classList.add("opacity-0", "pointer-events-none");
    if (box) {
        box.classList.remove("scale-100");
        box.classList.add("scale-95");
    }
}

/**
 * Fetches cart items and renders them into the summary modal
 */
async function populateOrderSummary() {
    const tableBody = document.getElementById("summaryTableBody");
    const grandTotalEl = document.getElementById("summaryGrandTotal");
    const itemCountEl = document.getElementById("summaryItemCount");
    const amountPaidInput = document.getElementById("amountPaid");

    if (!tableBody || !grandTotalEl || !itemCountEl) return;

    // Reset view
    tableBody.innerHTML = `<tr><td colspan="4" class="px-6 py-10 text-center font-bold text-gray-400 opacity-50 italic">Loading order details...</td></tr>`;

    try {
        const url = "../include/global.ctrl.php?action=get_cart_items";
        const res = await fetch(url);
        const data = await res.json();

        if (data.success && data.items) {
            tableBody.innerHTML = "";
            let total = 0;
            let count = 0;

            if (data.items.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="4" class="px-6 py-10 text-center font-bold text-gray-400 opacity-50 italic uppercase">Your cart is empty.</td></tr>`;
            } else {
                data.items.forEach((item) => {
                    const subtotal = item.price * item.qty;
                    total += subtotal;
                    count += parseInt(item.qty);

                    const srcBadge = item.source === "SR" ? "bg-red-600" : "bg-gray-800";

                    const html = `
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-6 py-4">
                                <span class="${srcBadge} text-white text-[9px] px-2 py-1 rounded-md font-black shadow-sm uppercase italic">${item.source}</span>
                            </td>
                            <td class="px-6 py-4">
                                <h1 class="text-sm font-black text-gray-900 leading-tight">${item.name}</h1>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-tighter">${item.variant} &bull; ${item.source === "SR" ? "Showroom" : "Warehouse"}</p>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="text-sm font-black text-gray-600">${item.qty}x</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <span class="text-sm font-black text-gray-900">₱${subtotal.toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                            </td>
                        </tr>
                    `;
                    tableBody.insertAdjacentHTML("beforeend", html);
                });
            }

            currentCheckoutTotal = total; // Store for calculations
            itemCountEl.innerText = `${count} Items`;
            grandTotalEl.innerText = `₱${total.toLocaleString(undefined, { minimumFractionDigits: 2 })}`;

            // Set default amount to pay if empty (Admin POS only usually)
            if (amountPaidInput && amountPaidInput.value === "") {
                amountPaidInput.value = total;
            }

            // Trigger sync calculation if POS installment exists
            if (typeof calculateInstallment === "function") calculateInstallment();
        } else {
            tableBody.innerHTML = `<tr><td colspan="4" class="px-6 py-10 text-center font-bold text-red-500">Failed to load cart items.</td></tr>`;
        }
    } catch (err) {
        console.error(err);
        tableBody.innerHTML = `<tr><td colspan="4" class="px-6 py-10 text-center font-bold text-red-500">Error fetching data.</td></tr>`;
    }
}

/**
 * Handles UI changes and calculation logic for installments (Admin POS)
 */
function toggleInstallmentView(val) {
    const settings = document.getElementById("installmentSettings");
    const balanceAlert = document.getElementById("balanceAlert");
    const label = document.getElementById("amountLabel");
    const badge = document.getElementById("typeBadge");
    const amountPaid = document.getElementById("amountPaid");

    if (!settings) return; // Only for Admin POS

    if (val === "installment") {
        settings.classList.remove("hidden");
        balanceAlert?.classList.remove("hidden");
        if (label) label.innerText = "Initial / Downpayment";
        if (badge) {
            badge.innerText = "Installment Plan";
            badge.classList.replace("bg-blue-100", "bg-orange-100");
            badge.classList.replace("text-blue-600", "text-orange-600");
        }
    } else {
        settings.classList.add("hidden");
        balanceAlert?.classList.add("hidden");
        if (label) label.innerText = "Amount to Pay Now";
        if (badge) {
            badge.innerText = "Standard Sale";
            badge.classList.replace("bg-orange-100", "bg-blue-100");
            badge.classList.replace("text-orange-600", "text-blue-600");
        }

        if (amountPaid) amountPaid.value = currentCheckoutTotal;
    }
    calculateInstallment();
}

/**
 * Performs real-time math for interest and amortization (Admin POS)
 */
function calculateInstallment() {
    const transactionTypeEl = document.getElementById("transactionType");
    if (!transactionTypeEl) return;

    const isInstallment = transactionTypeEl.value === "installment";
    const interestRate = parseFloat(document.getElementById("interestRate")?.value || 0);
    const term = parseInt(document.getElementById("installmentTerm")?.value || 1);
    const amountPaidInput = document.getElementById("amountPaid");
    const amountPaid = parseFloat(amountPaidInput?.value || 0);

    // UI Elements
    const totalWithInterestEl = document.getElementById("totalWithInterest");
    const monthlyAmortEl = document.getElementById("monthlyAmort");
    const calcBalanceEl = document.getElementById("calcBalance");
    const grandTotalEl = document.getElementById("summaryGrandTotal");

    let finalTotal = currentCheckoutTotal;

    if (isInstallment) {
        // Flat Interest Calculation
        const interestAmount = currentCheckoutTotal * (interestRate / 100);
        finalTotal = currentCheckoutTotal + interestAmount;
    }

    const balance = Math.max(0, finalTotal - amountPaid);
    const amortization = term > 0 ? balance / term : 0;

    // Update UI
    if (totalWithInterestEl)
        totalWithInterestEl.innerText = `₱${finalTotal.toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
    if (monthlyAmortEl)
        monthlyAmortEl.innerText = `₱${amortization.toLocaleString(undefined, { minimumFractionDigits: 2 })} / mo`;
    if (calcBalanceEl)
        calcBalanceEl.innerText = `₱${balance.toLocaleString(undefined, { minimumFractionDigits: 2 })}`;

    // Summary total shows the contract total
    if (grandTotalEl)
        grandTotalEl.innerText = `₱${finalTotal.toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
}

// ==========================================
// 2. ADMIN ORDER REVIEW LOGIC
// ==========================================

/**
 * Opens the order review modal and fetches real-time data
 */
async function openViewModal(orderId) {
    const modal = document.getElementById('viewModal');
    const wrapper = document.getElementById('modalWrapper');
    const backdrop = document.getElementById('modalBackdrop');

    if (!modal) return;

    try {
        const response = await fetch(`../include/inc.admin/admin.ctrl.php?action=get_items&pr_no=${orderId}`);
        const res = await response.json();

        if (!res.success) {
            window.showCustomAlert?.(res.error || "Could not fetch details");
            return;
        }

        const data = res.details;
        const items = res.items;

        const modalIdEl = document.getElementById('modal-id');
        modalIdEl.innerText = `ORD-${orderId.toString().padStart(5, '0')}`;
        modalIdEl.dataset.orderId = orderId;

        document.getElementById('modal-by').innerText = data.requested_by || 'System';
        document.getElementById('modal-customer').innerText = data.customer_name || 'N/A';
        document.getElementById('modal-status').innerText = data.status.toUpperCase();
        document.getElementById('admin-notes').value = data.comments || '';
        document.getElementById('admin-discount').value = '';

        const tbody = document.getElementById('modal-items-body');
        tbody.innerHTML = '';
        baseOrderTotal = 0;

        items.forEach(item => {
            const subtotal = item.qty * item.unit_price;
            baseOrderTotal += subtotal;

            const stock = parseInt(item.current_stock || 0);
            const reqQty = parseInt(item.qty);
            const isAvailable = stock >= reqQty;
            const stockColor = isAvailable ? 'text-green-600' : 'text-red-600';
            const stockIcon = isAvailable ? '✅' : '🚨';

            tbody.innerHTML += `
                <tr class="hover:bg-slate-50/50">
                    <td class="px-6 py-4 text-center">
                        <div class="flex flex-col items-center">
                            <span class="text-sm font-black ${stockColor}">${stockIcon} ${stock}</span>
                            <span class="text-[8px] font-bold text-slate-400 uppercase tracking-tighter">${isAvailable ? 'In Stock' : 'Insufficient'}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="font-bold text-slate-800 text-[13px]">${item.name}</div>
                        <div class="text-[10px] text-slate-400 uppercase font-bold">${item.category || 'General'} &bull; ${item.variant}</div>
                    </td>
                    <td class="px-6 py-4 text-center font-mono text-slate-600 font-bold">${item.qty}</td>
                    <td class="px-6 py-4 text-right font-bold text-slate-900">₱${parseFloat(subtotal).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                </tr>`;
        });

        document.getElementById('item-count').innerText = items.length;
        calculateFinalTotal();

        const historySearch = document.getElementById('history-search');
        if (historySearch) {
            originalRequesterName = data.customer_name || '';
            historySearch.value = originalRequesterName;
            // Trigger history fetch for this specific customer
            loadGlobalHistoryLog(originalRequesterName);
        } else {
            loadGlobalHistoryLog();
        }

        // Status-based UI Locking
        const status = (data.status || '').toLowerCase();
        const isPending = status === 'for review' || status === 'pending';
        
        const actionFooter = document.getElementById('modal-action-footer');
        const notesInput = document.getElementById('admin-notes');
        const discountInput = document.getElementById('admin-discount');

        if (isPending) {
            if (actionFooter) actionFooter.classList.remove('hidden');
            if (notesInput) notesInput.disabled = false;
            if (discountInput) discountInput.disabled = false;
        } else {
            if (actionFooter) actionFooter.classList.add('hidden');
            if (notesInput) notesInput.disabled = true;
            if (discountInput) discountInput.disabled = true;
        }

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        setTimeout(() => {
            backdrop.classList.replace('opacity-0', 'opacity-100');
            wrapper.classList.replace('scale-95', 'scale-100');
            wrapper.classList.replace('opacity-0', 'opacity-100');
        }, 10);

    } catch (err) {
        console.error(err);
    }
}

/**
 * Handles Approval or Rejection submission
 */
async function handleAction(type) {
    const notes = document.getElementById('admin-notes');
    const requestId = document.getElementById('modal-id').dataset.orderId;
    const discountInput = document.getElementById('admin-discount');
    const typeElement = document.querySelector('input[name="discount_type"]:checked');
    const approveBtn = document.getElementById('approveBtn');
    const rejectBtn = document.getElementById('rejectBtn');

    if (type === 'reject' && !notes.value.trim()) {
        notes.classList.add('border-red-500', 'ring-2', 'ring-red-100', 'animate-shake');
        notes.placeholder = "REQUIRED: Why are you rejecting this?";
        setTimeout(() => notes.classList.remove('border-red-500', 'ring-2', 'ring-red-100', 'animate-shake'), 2500);
        return;
    }

    // Removing redundant confirm alert as requested

    // Set Loading State
    const originalApproveText = approveBtn ? approveBtn.innerText : 'Confirm & Approve';
    const originalRejectText = rejectBtn ? rejectBtn.innerText : 'Reject Order';
    
    if (approveBtn) {
        approveBtn.disabled = true;
        if (type === 'approve') approveBtn.innerText = 'APPROVING...';
    }
    if (rejectBtn) {
        rejectBtn.disabled = true;
        if (type === 'reject') rejectBtn.innerText = 'REJECTING...';
    }

    try {
        const discountVal = parseFloat(discountInput.value) || 0;
        const discountType = typeElement ? typeElement.value : 'currency';
        let absoluteDiscount = discountType === 'percentage' ? (baseOrderTotal * (discountVal / 100)) : discountVal;

        const formData = new FormData();
        formData.append('action', 'update_status');
        formData.append('pr_no', requestId);
        formData.append('status', type);
        formData.append('notes', notes.value.trim());
        formData.append('discount', absoluteDiscount);

        const response = await fetch('../include/inc.admin/admin.ctrl.php', { method: 'POST', body: formData });
        const res = await response.json();

        if (res.success) {
            closeReviewModal();
            setTimeout(() => {
                window.showToast?.(res.message || "Action processed", "success");
                setTimeout(() => location.reload(), 1000);
            }, 300);
        } else {
            window.showCustomAlert?.(res.message || "Failed");
            // Reset
            if (approveBtn) { approveBtn.disabled = false; approveBtn.innerText = originalApproveText; }
            if (rejectBtn) { rejectBtn.disabled = false; rejectBtn.innerText = originalRejectText; }
        }
    } catch (err) {
        console.error(err);
        if (approveBtn) { approveBtn.disabled = false; approveBtn.innerText = originalApproveText; }
        if (rejectBtn) { rejectBtn.disabled = false; rejectBtn.innerText = originalRejectText; }
    }
}

/**
 * Fetches global order history for the sidebar log
 * Can be filtered by search query
 */
async function loadGlobalHistoryLog(search = '') {
    const container = document.getElementById('history-items-container');
    if (!container) return;

    // Default to original requester if search is cleared
    const finalSearch = search.trim() === '' ? originalRequesterName : search;

    // Show loading state
    if (!search && !originalRequesterName) {
        container.innerHTML = `<div class="py-10 text-center flex flex-col items-center gap-3">
            <div class="size-10 border-2 border-red-100 border-t-red-600 rounded-full animate-spin"></div>
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Fetching History...</p>
        </div>`;
    }

    try {
        const response = await fetch(`../include/inc.admin/admin.ctrl.php?action=get_history_log&search=${encodeURIComponent(finalSearch)}`);
        const res = await response.json();

        if (res.success && res.history.length > 0) {
            container.innerHTML = '';
            res.history.forEach(h => {
                const date = new Date(h.transaction_date).toLocaleDateString(undefined, { month: '2-digit', day: '2-digit', year: 'numeric' });
                const status = (h.trans_status || 'Pending').toUpperCase();
                const amount = parseFloat(h.amount_paid || 0).toLocaleString(undefined, { minimumFractionDigits: 2 });
                
                // Color mapping for status
                const statusColors = {
                    'SUCCESS': 'bg-green-100 text-green-700 border-green-200',
                    'SUCCESSFUL': 'bg-green-100 text-green-700 border-green-200',
                    'APPROVED': 'bg-green-100 text-green-700 border-green-200',
                    'REJECTED': 'bg-red-100 text-red-700 border-red-200',
                    'FAILED': 'bg-red-100 text-red-700 border-red-200',
                    'PENDING': 'bg-yellow-100 text-yellow-700 border-yellow-200',
                    'FOR REVIEW': 'bg-orange-100 text-orange-700 border-orange-200'
                };
                const colorClass = statusColors[status] || 'bg-gray-100 text-gray-700 border-gray-200';

                const cardHtml = `
                    <div class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm mb-3 hover:border-red-100 transition-all group">
                        <div class="flex justify-between items-start mb-1">
                            <div>
                                <h4 class="text-[12px] font-black text-gray-900 uppercase leading-tight group-hover:text-red-600 transition-colors">${h.customer_name}</h4>
                                <p class="text-[10px] text-gray-500 font-medium">${h.contact_no || 'No Contact'}</p>
                            </div>
                            <span class="px-2 py-0.5 rounded-md text-[9px] font-bold uppercase tracking-wider bg-red-50 text-red-600 border border-red-100">
                                ${h.client_type}
                            </span>
                        </div>

                        ${h.client_type.toLowerCase().includes('government') && h.gov_branch ? `
                        <div class="flex items-center gap-1.5 mb-2">
                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-7h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                            <p class="text-[10px] font-bold text-gray-600 tracking-tight">${h.gov_branch}</p>
                        </div>` : ''}

                        <div class="flex justify-between items-end border-t border-gray-50 pt-3 mt-1">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-mono font-bold text-gray-600">${date}</span>
                                    <span class="px-2 py-0.5 rounded text-[8px] font-black uppercase border ${colorClass}">${status}</span>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="text-[13px] font-black text-gray-900 tracking-tighter">₱${amount}</span>
                            </div>
                        </div>
                    </div>`;
                container.insertAdjacentHTML('beforeend', cardHtml);
            });
        } else {
            container.innerHTML = `
                <div class="py-12 text-center flex flex-col items-center gap-3">
                    <div class="size-10 bg-gray-50 rounded-full flex items-center justify-center border border-gray-100">
                        <svg class="size-5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest px-4 leading-relaxed">No history items found</p>
                </div>`;
        }
    } catch (err) {
        console.error("History Log Error:", err);
    }
}

/**
 * Handle Autocomplete/Search for History Sidebar
 */
let historySearchTimeout = null;
function handleHistoryAutocomplete(input) {
    const query = input.value.trim();

    if (historySearchTimeout) clearTimeout(historySearchTimeout);
    
    // We update the UI in real-time as the user types
    historySearchTimeout = setTimeout(() => {
        loadGlobalHistoryLog(query);
    }, 300);
}

function selectHistoryCustomer(id, name) {
    const input = document.getElementById('history-search');
    const suggestions = document.getElementById('history-suggestions');
    if (input) input.value = name;
    if (suggestions) suggestions.classList.add('hidden');

    if (id > 0) {
        fetchCustomerOrderHistory(id, true);
    } else {
        fetchCustomerOrderHistory(name, false);
    }
}

/**
 * Recalculates total based on discount inputs in Review Modal
 */
function calculateFinalTotal() {
    const discountInput = document.getElementById('admin-discount');
    const totalDisplay = document.getElementById('modal-total');
    const typeElement = document.querySelector('input[name="discount_type"]:checked');

    if (!typeElement || !totalDisplay) return;
    const type = typeElement.value;

    let discountVal = parseFloat(discountInput.value) || 0;
    let finalTotal = type === 'percentage' ? (baseOrderTotal - (baseOrderTotal * (discountVal / 100))) : (baseOrderTotal - discountVal);

    totalDisplay.innerText = `₱${Math.max(0, finalTotal).toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
}

function toggleDiscountType() {
    const type = document.querySelector('input[name="discount_type"]:checked')?.value;
    const label = document.getElementById('discount-label');
    if (label) label.innerText = type === 'currency' ? 'Discount (₱)' : 'Discount (%)';
    calculateFinalTotal();
}

function closeReviewModal() {
    const wrapper = document.getElementById('modalWrapper');
    const backdrop = document.getElementById('modalBackdrop');
    if (!wrapper) return;

    backdrop.classList.replace('opacity-100', 'opacity-0');
    wrapper.classList.replace('scale-100', 'scale-95');
    wrapper.classList.replace('opacity-100', 'opacity-0');

    setTimeout(() => {
        document.getElementById('viewModal').classList.add('hidden');
    }, 300);
}

// ==========================================
// 3. ADMIN POS SPECIFIC LOGIC
// ==========================================

async function completeSale() {
    // Reset change tracking since we're submitting
    window.formHasUnsavedChanges = false;

    const btn = document.getElementById("completeSaleBtn");
    if (!btn || btn.disabled) return;

    const payload = {
        action: "complete_sale",
        clientName: document.getElementById("clientName")?.value.trim(),
        clientType: document.getElementById("clientType")?.value,
        govBranch: document.getElementById("govBranch")?.value.trim() || null,
        clientContact: document.getElementById("clientContact")?.value.trim(),
        adminDiscount: document.getElementById("adminDiscount")?.value || 0,
        shippingMode: document.getElementById("shippingMode")?.value,
        deliveryAddress: document.getElementById("deliveryAddress")?.value.trim(),
        transactionType: document.getElementById("transactionType")?.value,
        interestRate: document.getElementById("interestRate")?.value || 0,
        installmentTerm: document.getElementById("installmentTerm")?.value || 0,
        paymentMethod: document.getElementById("paymentMethod")?.value,
        paymentRef: document.getElementById("paymentRef")?.value.trim(),
        amountPaid: document.getElementById("amountPaid")?.value || 0,
        paymentRemarks: document.getElementById("paymentRemarks")?.value.trim(),
        totalAmount: currentCheckoutTotal || 0,
        totalWithInterest: parseFloat(document.getElementById("totalWithInterest")?.innerText.replace(/[^\d.]/g, "") || 0),
        balance: parseFloat(document.getElementById("calcBalance")?.innerText.replace(/[^\d.]/g, "") || 0),
    };

    if (!payload.clientName) {
        window.showCustomAlert?.("Customer name is required.");
        return;
    }
    // Validation: Name should not contain numbers
    if (/[0-9]/.test(payload.clientName)) {
        window.showCustomAlert?.("Customer name should not contain numbers.");
        return;
    }
    // Validation: Contact Number (PH Format: 09XXXXXXXXX)
    if (!payload.clientContact || !/^09\d{9}$/.test(payload.clientContact)) {
        window.showCustomAlert?.("Please enter a valid 11-digit contact number (starting with 09).");
        return;
    }

    if (payload.amountPaid <= 0 && payload.totalAmount > 0) {
        window.showCustomAlert?.("Enter a valid amount paid.");
        return;
    }

    window.showCustomConfirm?.("Finalize this transaction?", async () => {
        btn.disabled = true;
        btn.innerHTML = `<span class="animate-pulse">Processing...</span>`;
        const formData = new FormData();
        for (const key in payload) formData.append(key, payload[key]);

        try {
            const res = await fetch("../include/inc.admin/admin.ctrl.php", { method: "POST", body: formData });
            const result = await res.json();
            if (result.success) {
                // Close the main checkout modal first
                closeProceedModal('reviewCartModal');
                
                setTimeout(() => {
                    window.showCustomSuccess?.(`Order #${result.order_id} recorded.`, () => window.location.reload());
                }, 300);
            } else {
                window.showCustomAlert?.(result.message || "Failed.");
                btn.disabled = false;
                btn.innerText = "Complete Sale";
            }
        } catch (err) {
            console.error(err);
            btn.disabled = false;
            btn.innerText = "Complete Sale";
        }
    }, "Complete Sale", "bg-red-600");
}

/**
 * Handle POS Customer selection from Search
 */
function selectCustomer(c) {
    const suggestions = document.getElementById("customerSuggestions");
    const nameInput = document.getElementById("clientName");
    const contactInput = document.getElementById("clientContact");
    const typeSelect = document.getElementById("clientType");
    const govInput = document.getElementById("govBranch");
    const historyName = document.getElementById("historyClientName");
    const historyType = document.getElementById("historyClientType");
    const badge = document.getElementById("customerBadge");

    if (nameInput) nameInput.value = c.name;
    if (contactInput) contactInput.value = c.contact_no;
    if (typeSelect) {
        typeSelect.value = c.client_type;
        if (typeof toggleGovFields === "function") toggleGovFields(c.client_type);
    }
    if (govInput) govInput.value = c.gov_branch || "";
    if (historyName) historyName.innerText = c.name;
    if (historyType) historyType.innerText = c.client_type.toUpperCase() + " ACCOUNT";
    if (badge) {
        badge.innerText = "Existing Customer";
        badge.classList.replace("bg-blue-50", "bg-green-50");
        badge.classList.replace("text-blue-600", "text-green-600");
    }

    suggestions?.classList.add("hidden");
    fetchCustomerPOSHistory(c.id);
}

/**
 * Real-time Customer Search for Checkout Forms
 */
let customerSearchTimeout = null;
function handleCustomerSearch(query) {
    const suggestions = document.getElementById("customerSuggestions");
    if (!suggestions) return;

    if (customerSearchTimeout) clearTimeout(customerSearchTimeout);
    if (query.trim().length < 2) {
        suggestions.classList.add("hidden");
        return;
    }

    customerSearchTimeout = setTimeout(async () => {
        try {
            const res = await fetch(`../include/global.ctrl.php?action=search_customers&query=${encodeURIComponent(query)}`);
            const data = await res.json();
            if (data.success && data.customers.length > 0) {
                suggestions.innerHTML = "";
                data.customers.forEach((c) => {
                    const html = `
                        <div onclick='selectCustomer(${JSON.stringify(c).replace(/'/g, "&apos;")})' 
                             class="px-5 py-4 hover:bg-blue-50 cursor-pointer border-b border-gray-50 last:border-0 transition-colors group">
                            <div class="flex justify-between items-center">
                                <div>
                                    <p class="text-sm font-black text-gray-900 group-hover:text-blue-600 uppercase tracking-tight">${c.name}</p>
                                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">${c.client_type} &bull; ${c.contact_no || 'No Contact'}</p>
                                </div>
                                <svg class="w-4 h-4 text-gray-300 group-hover:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path d="M13 7l5 5m0 0l-5 5m5-5H6" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path>
                                </svg>
                            </div>
                        </div>`;
                    suggestions.insertAdjacentHTML("beforeend", html);
                });
                suggestions.classList.remove("hidden");
            } else {
                suggestions.classList.add("hidden");
            }
        } catch (err) {
            console.error("Search Error:", err);
            suggestions.classList.add("hidden");
        }
    }, 300);
}

async function fetchCustomerPOSHistory(customerId) {
    const list = document.getElementById("customerHistoryList");
    const count = document.getElementById("historyCount");
    if (!list) return;

    list.innerHTML = `<div class="text-center py-10 italic text-[10px] text-gray-400">Fetching history...</div>`;
    try {
        const res = await fetch(`../include/global.ctrl.php?action=get_customer_history&customer_id=${customerId}`);
        const data = await res.json();
        if (data.success && data.history) {
            renderCustomerPOSHistory(data.history);
            if (count) count.innerText = data.history.length;
        } else {
            list.innerHTML = `<div class="text-center py-10 text-[10px] font-bold text-gray-400 uppercase">No history found</div>`;
            if (count) count.innerText = 0;
        }
    } catch (err) {
        console.error(err);
    }
}

function renderCustomerPOSHistory(history) {
    const list = document.getElementById("customerHistoryList");
    if (!list) return;
    list.innerHTML = "";
    history.forEach((h) => {
        const isPaid = (h.status || "").toLowerCase() === "paid";
        const statusColor = isPaid ? "text-green-600 bg-green-50" : "text-orange-600 bg-orange-50";
        const date = new Date(h.created_at).toLocaleDateString();
        const card = `
            <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm border-l-4 ${isPaid ? "border-l-green-400" : "border-l-orange-400"}">
                <div class="flex justify-between items-start mb-2">
                    <span class="text-[9px] font-black text-gray-400 uppercase tracking-widest">Order #${h.id}</span>
                    <span class="text-[8px] font-black ${statusColor} px-1.5 py-0.5 rounded uppercase">${h.status || "Pending"}</span>
                </div>
                <div class="flex justify-between items-center mb-2">
                    <h5 class="text-sm font-black text-gray-900 leading-none">₱${parseFloat(h.total_amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}</h5>
                    <span class="text-[10px] font-bold text-gray-400">${date}</span>
                </div>
                ${h.or_number ? `<div class="pt-2 border-t border-gray-50 flex items-center justify-between"><span class="text-[8px] font-black text-gray-400 uppercase">OR Number</span><span class="text-[9px] font-black text-blue-600 bg-blue-50 px-2 py-0.5 rounded italic tracking-tighter">${h.or_number}</span></div>` : ``}
            </div>`;
        list.insertAdjacentHTML("beforeend", card);
    });
}

function toggleGovFields(val) {
    const govSection = document.getElementById('govDeptSection');
    if (govSection) val === 'government' ? govSection.classList.remove('hidden') : govSection.classList.add('hidden');
}

function toggleAddress(val) {
    const addressSection = document.getElementById('deliveryAddressSection');
    if (addressSection) val === 'delivery' ? addressSection.classList.remove('hidden') : addressSection.classList.add('hidden');
}

function updateAdminSidebar(val) {
    const sidebarName = document.getElementById('historyClientName');
    if (sidebarName) sidebarName.innerText = val.trim() !== "" ? val : "New Client";
}

// ==========================================
// 4. SHOWROOM ORDER REQUEST LOGIC
// ==========================================

async function submitOrderRequest() {
    const btn = document.getElementById("placeRequestBtn");
    const nameInput = document.getElementById("clientName");
    const clientName = nameInput?.value.trim() || "";

    if (!clientName) {
        window.showCustomAlert?.("Please enter a client name.");
        return;
    }

    if (btn) {
        btn.disabled = true;
        btn.innerHTML = `<span class="animate-pulse italic">Submitting Request...</span>`;
    }

    try {
        const formData = new FormData();
        formData.append("action", "place_request");
        formData.append("fname", clientName); // sr.ctrl expects fname/lname
        formData.append("lname", "");

        const res = await fetch("../include/inc.showroom/sr.ctrl.php", { method: "POST", body: formData });
        const data = await res.json();

        if (data.success) {
            // Close the checkout modal first
            closeProceedModal('reviewCartModal');
            
            setTimeout(() => {
                window.showCustomSuccess?.(`Order Request Submitted Successfully! PR Number: ${data.pr_no}`, () => {
                    window.location.reload(); // Refresh to clear cart
                });
            }, 300);
        } else {
            window.showCustomAlert?.(data.error || "Failed to submit request.");
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = `Place Order Request`;
            }
        }
    } catch (err) {
        console.error(err);
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = `Place Order Request`;
        }
    }
}

    // Handle initial tab if on home-page.php
    document.addEventListener("DOMContentLoaded", () => {
        if (window.location.pathname.includes("home-page.php")) {
            const urlParams = new URLSearchParams(window.location.search);
            const activeTab = urlParams.get('tab') || '0';
            if (typeof showTab === 'function') showTab(parseInt(activeTab));
        }
    });

/**
 * Monitor Checkout Form for Unsaved Changes
 */
document.addEventListener("DOMContentLoaded", () => {
    const checkoutFields = [
        "clientName", 
        "clientContact", 
        "adminDiscount", 
        "paymentRef", 
        "paymentRemarks", 
        "govBranch", 
        "deliveryAddress", 
        "amountPaid",
        "clientType",
        "shippingMode",
        "transactionType",
        "paymentMethod"
    ];
    
    checkoutFields.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            const trackChange = () => {
                window.formHasUnsavedChanges = true;
            };
            el.addEventListener("input", trackChange);
            el.addEventListener("change", trackChange);
        }
    });
});

/**
 * Showroom Home Page: Tab switching with silent cart refresh
 */
async function refreshAndShowTab(tabIndex) {
    if (typeof showTab !== 'function') return;

    // 1. Instantly switch the UI tabs
    showTab(tabIndex);
    window.history.replaceState({}, "", window.location.pathname + "?tab=" + tabIndex);

    // Update badge count
    if (typeof updateCartBadgeCount === 'function') updateCartBadgeCount();

    // 2. Refresh cart data if switching to tab 1
    if (tabIndex === 1) {
        try {
            const response = await fetch(window.location.pathname + "?tab=1");
            const html = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, "text/html");

            const newTab1 = doc.getElementById("tabContent1");
            const currentTab1 = document.getElementById("tabContent1");
            if (newTab1 && currentTab1) {
                currentTab1.innerHTML = newTab1.innerHTML;
            }
        } catch (e) {
            console.error("Failed to sync latest cart.", e);
        }
    }
}
