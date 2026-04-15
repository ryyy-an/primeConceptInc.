/**
 * Showroom Order Request Management
 * Handles modal interactions, validations, and transaction finalization.
 */

// --- MODAL UTILITIES ---

function openModal(modalId, boxId) {
    const modal = document.getElementById(modalId);
    const box = document.getElementById(boxId);
    if (!modal || !box) return;

    document.body.style.overflow = 'hidden';
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    setTimeout(() => {
        box.classList.remove('scale-95', 'opacity-0');
    }, 10);
}

function closeModal(modalId, boxId) {
    const modal = document.getElementById(modalId);
    const box = document.getElementById(boxId);
    if (!modal || !box) return;

    document.body.style.overflow = '';
    box.classList.add('scale-95', 'opacity-0');
    setTimeout(() => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }, 300);
}

// --- REQUEST DETAILS MODAL ---

async function openRequestInfoModal(req) {
    document.body.style.overflow = 'hidden';
    const modal = document.getElementById('requestInfoModal');
    const box = document.getElementById('requestInfoBox');

    if (!modal || !box) return;

    // Set basic info
    document.getElementById('modal-id').textContent = req.pr_no;
    document.getElementById('modal-by').textContent = req.full_name || 'N/A';
    document.getElementById('modal-customer').textContent = req.customer_name || req.temp_customer_name || 'N/A';
    document.getElementById('modal-date').textContent = new Date(req.date).toLocaleDateString();

    // Status Badge
    const status = (req.status || 'pending').toLowerCase();
    let statusClass = 'bg-yellow-50 text-yellow-600 border-yellow-100 ring-yellow-50';

    if (status === 'approved') {
        statusClass = 'bg-green-50 text-green-600 border-green-100 ring-green-50';
    } else if (status === 'rejected') {
        statusClass = 'bg-red-50 text-red-600 border-red-100 ring-red-50';
    } else if (status === 'cancelled') {
        statusClass = 'bg-orange-50 text-orange-600 border-orange-100 ring-orange-50';
    } else if (status === 'success') {
        statusClass = 'bg-blue-50 text-blue-600 border-blue-100 ring-blue-50';
    } else {
        statusClass = 'bg-gray-50 text-gray-400 border-gray-100 ring-gray-100';
    }

    const badge = document.getElementById('modal-status-badge');
    badge.className = `text-[9px] font-black px-2 py-0.5 rounded-md uppercase border tracking-wider ${statusClass}`;
    badge.textContent = req.status;

    // UI Reorganization based on status
    const isForReview = status === 'for review';
    const isCancelled = status === 'cancelled';
    const isRejected = status === 'rejected';
    const isSuccess = status === 'success';
    const isApproved = status === 'approved';

    // Group all non-checkout/read-only statuses
    const isReadOnlyView = isForReview || isCancelled || isRejected || isSuccess;

    const rightHeader = document.getElementById('modal-right-header-text');
    const rightSubHeader = document.getElementById('modal-right-sub-header');
    const clientSection = document.getElementById('modal-client-section');
    const shippingSection = document.getElementById('modal-shipping-section');
    const paymentSection = document.getElementById('modal-payment-section');
    const totalSection = document.getElementById('modal-grand-total-section');
    const completeBtn = document.getElementById('showroomCompleteSaleBtn');

    if (isReadOnlyView) {
        // Change Right Column to "Order Summary" mode
        rightHeader.textContent = "Order Summary";
        if (rightSubHeader) rightSubHeader.classList.add('hidden');
        if (clientSection) clientSection.classList.add('hidden');
        if (shippingSection) shippingSection.classList.add('hidden');
        if (paymentSection) paymentSection.classList.add('hidden');
        if (totalSection) totalSection.classList.add('hidden');

        if (isForReview) {
            // Show "Cancel Request" instead of "Complete Sale"
            completeBtn.classList.remove('hidden');
            completeBtn.disabled = false;
            completeBtn.classList.remove('bg-blue-600', 'hover:bg-blue-700', 'shadow-blue-100', 'opacity-50', 'cursor-not-allowed');
            completeBtn.classList.add('bg-red-600', 'hover:bg-red-700', 'shadow-red-100');
            completeBtn.innerHTML = `<span>Cancel Request</span>`;
            completeBtn.onclick = () => cancelRequest(req.pr_no);
        } else {
            // Cancelled: Hide the button entirely
            completeBtn.classList.add('hidden');
        }
    } else {
        // Restore "Finalize Transaction" mode
        rightHeader.textContent = "Finalize Transaction";
        if (rightSubHeader) rightSubHeader.classList.remove('hidden');
        if (clientSection) clientSection.classList.remove('hidden');
        if (shippingSection) shippingSection.classList.remove('hidden');
        if (paymentSection) paymentSection.classList.remove('hidden');
        if (totalSection) totalSection.classList.remove('hidden');

        // Reset button to "Complete Sale" defaults
        completeBtn.classList.remove('hidden');
        completeBtn.classList.remove('bg-blue-600', 'hover:bg-blue-700', 'shadow-blue-100');
        completeBtn.classList.add('bg-red-600', 'hover:bg-red-700', 'shadow-red-100');
        completeBtn.onclick = handleShowroomCompleteSale;

        if (!isApproved) {
            completeBtn.disabled = true;
            completeBtn.classList.add('opacity-50', 'cursor-not-allowed');
            completeBtn.innerHTML = `<span>Awaiting Admin Approval</span>`;
        } else {
            completeBtn.disabled = false;
            completeBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            completeBtn.innerHTML = `<span>Complete Sale</span>`;
        }
    }

    // Cancel Button visibility (Left Column - may keep it or hide if redundant)
    // The user wanted the footer button to be the Cancel button for 'For Review'.
    const cancelContainer = document.getElementById('modal-cancel-container');
    if (status === 'pending') { // Only keep it here for Pending if not For Review
        cancelContainer.innerHTML = `
            <button onclick="cancelRequest(${req.pr_no})" 
                class="w-full py-4 bg-white border-2 border-red-50 text-red-600 font-bold rounded-2xl hover:bg-red-50 transition-all active:scale-95 uppercase text-[11px] tracking-widest shadow-sm">
                Cancel Request
            </button>
        `;
    } else {
        cancelContainer.innerHTML = '';
    }

    // Discount & Remarks
    document.getElementById('modal-discount-amount').textContent = `₱ ${parseFloat(req.discount || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
    document.getElementById('modal-remarks-text').textContent = req.comment || 'No remarks provided yet.';

    // Trigger summary detail population
    populateRequestSummary(req);

    // Show Modal
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    setTimeout(() => {
        box.classList.remove('scale-95', 'opacity-0');
    }, 10);
}

function closeRequestInfoModal() {
    closeModal('requestInfoModal', 'requestInfoBox');
}

// --- CANCELLATION LOGIC ---

function cancelRequest(prNo) {
    document.getElementById('cancel-pr-no-display').textContent = `#${prNo}`;
    document.getElementById('cancel-pr-no-input').value = prNo;
    openModal('cancelConfirmModal', 'cancelConfirmBox');
}

function closeCancelConfirmModal() {
    closeModal('cancelConfirmModal', 'cancelConfirmBox');
}

function confirmCancelExecution() {
    const prNo = document.getElementById('cancel-pr-no-input').value;
    const btn = document.getElementById('confirmCancelBtn');
    
    if (!prNo) return;
    
    btn.disabled = true;
    btn.innerHTML = `<span class="animate-pulse italic">Cancelling...</span>`;

    fetch('../include/inc.showroom/sr.ctrl.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `action=cancel_request&pr_no=${prNo}`
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                closeCancelConfirmModal();
                setTimeout(() => {
                    if (typeof showToast === 'function') showToast('Request cancelled successfully!', 'success');
                    setTimeout(() => location.reload(), 1500);
                }, 300);
            } else {
                if (typeof showToast === 'function') showToast(res.error || 'Failed to cancel request.', 'error');
                btn.disabled = false;
                btn.innerText = "Yes, Cancel Order";
            }
        })
        .catch(err => {
            console.error(err);
            btn.disabled = false;
            btn.innerText = "Yes, Cancel Order";
        });
}

// --- SUMMARY POPULATION ---

async function populateRequestSummary(req) {
    const tableBody = document.getElementById("summaryTableBody");
    const grandTotalEl = document.getElementById("summaryGrandTotal");
    const itemCountEl = document.getElementById("summaryItemCount");
    const amountPaidInput = document.getElementById("amountPaid");
    const hiddenOrderId = document.getElementById("hidden-order-id");

    if (!tableBody || !grandTotalEl || !itemCountEl) return;

    tableBody.innerHTML = `<tr><td colspan="4" class="px-6 py-10 text-center font-bold text-gray-400 opacity-50 italic">Loading order details...</td></tr>`;

    try {
        const response = await fetch(`../include/inc.showroom/sr.ctrl.php?action=get_items&pr_no=${req.pr_no}`);
        const data = await response.json();

        if (data.success && data.items) {
            tableBody.innerHTML = "";
            let total = 0;
            let count = 0;

            if (data.items.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="4" class="px-6 py-10 text-center font-bold text-gray-400 opacity-50 italic uppercase">No items found for this request.</td></tr>`;
            } else {
                data.items.forEach((item) => {
                    const qty = parseInt(item.quantity);
                    const price = parseFloat(item.price);
                    const subtotal = price * qty;
                    total += subtotal;
                    count += qty;

                    const srcBadge = item.location === "SR" ? "bg-red-600" : "bg-gray-800";

                    const html = `
                <tr class="hover:bg-gray-50/50 transition-colors">
                    <td class="px-6 py-4">
                        <span class="${srcBadge} text-white text-[9px] px-2 py-1 rounded-md font-black shadow-sm uppercase italic">${item.location}</span>
                    </td>
                    <td class="px-6 py-4">
                        <h1 class="text-sm font-black text-gray-900 leading-tight">${item.prod_name}</h1>
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-tighter">${item.variant} &bull; ${item.location === "SR" ? "Showroom" : "Warehouse"}</p>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="text-sm font-black text-gray-600">${qty}x</span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <span class="text-sm font-black text-gray-900">₱${subtotal.toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                    </td>
                </tr>
            `;
                    tableBody.insertAdjacentHTML("beforeend", html);
                });
            }

            window.currentCheckoutTotal = total;
            if (hiddenOrderId) hiddenOrderId.innerText = req.id;
            itemCountEl.innerText = `${count} Items`;
            grandTotalEl.innerText = `₱${total.toLocaleString(undefined, { minimumFractionDigits: 2 })}`;

            const info = data.details;
            if (document.getElementById("clientName")) document.getElementById("clientName").value = info.customer_name || "";
            if (document.getElementById("clientContact")) document.getElementById("clientContact").value = info.contact_no || "";
            if (document.getElementById("adminDiscount")) document.getElementById("adminDiscount").value = info.admin_discount || 0;
            if (document.getElementById("shippingMode")) document.getElementById("shippingMode").value = info.shipping_type || "pickup";
            if (document.getElementById("deliveryAddress")) document.getElementById("deliveryAddress").value = info.delivery_address || "";

            if (typeof toggleAddress === "function") toggleAddress(info.shipping_type || "pickup");
            if (amountPaidInput) amountPaidInput.value = total.toFixed(2);
            
            const totalWithInterestHidden = document.getElementById("totalWithInterest");
            if (totalWithInterestHidden) totalWithInterestHidden.value = total.toFixed(2);
        } else {
            tableBody.innerHTML = `<tr><td colspan="4" class="px-6 py-10 text-center font-bold text-red-500">Failed to load request items.</td></tr>`;
        }
    } catch (err) {
        console.error(err);
        tableBody.innerHTML = `<tr><td colspan="4" class="px-6 py-10 text-center font-bold text-red-500">Error fetching data.</td></tr>`;
    }
}

// --- TRANSACTION FINALIZATION ---

/**
 * Shows the custom validation modal with dynamic message
 */
function showValidationError(title, message) {
    document.getElementById('validation-title').textContent = title;
    document.getElementById('validation-message').textContent = message;
    openModal('validationErrorModal', 'validationErrorBox');
}

function closeValidationErrorModal() {
    closeModal('validationErrorModal', 'validationErrorBox');
}

/**
 * Shows the custom finalize confirmation modal
 */
function showFinalizeConfirm() {
    openModal('finalizeConfirmModal', 'finalizeConfirmBox');
}

function closeFinalizeConfirmModal() {
    closeModal('finalizeConfirmModal', 'finalizeConfirmBox');
}

async function handleShowroomCompleteSale() {
    const btn = document.getElementById("showroomCompleteSaleBtn");
    const orderId = document.getElementById("hidden-order-id")?.innerText;

    if (!btn || btn.disabled || !orderId) return;

    const payload = {
        customer_name: document.getElementById("clientName")?.value.trim(),
        contact_no: document.getElementById("clientContact")?.value.trim(),
    };

    if (!payload.customer_name) {
        showValidationError('Required Field', 'Customer name is required before completing the sale.');
        return;
    }

    if (/[0-9]/.test(payload.customer_name)) {
        showValidationError('Invalid Name', 'Customer name should not contain numbers.');
        return;
    }

    if (!payload.contact_no || !/^09\d{9}$/.test(payload.contact_no)) {
        showValidationError('Invalid Contact', 'Please enter a valid 11-digit contact number starting with 09.');
        return;
    }

    // If validation passes, show the custom confirmation modal
    showFinalizeConfirm();
}

async function executeFinalizeTransaction() {
    const btn = document.getElementById("showroomCompleteSaleBtn");
    const confirmBtn = document.getElementById("confirmFinalizeBtn");
    const orderId = document.getElementById("hidden-order-id")?.innerText;

    if (!orderId) return;

    const payload = {
        action: "finalize_order",
        order_id: orderId,
        customer_name: document.getElementById("clientName")?.value.trim(),
        clientType: 'Private / Individual',
        govBranch: null,
        contact_no: document.getElementById("clientContact")?.value.trim(),
        adminDiscount: document.getElementById("adminDiscount")?.value || 0,
        order_type: document.getElementById("shippingMode")?.value,
        address: document.getElementById("deliveryAddress")?.value.trim(),
        transactionType: 'full',
        interestRate: 0,
        installmentTerm: 1,
        paymentMethod: document.getElementById("paymentMethod")?.value,
        paymentRef: document.getElementById("paymentRef")?.value.trim(),
        amountPaid: document.getElementById("amountPaid")?.value || 0,
        paymentRemarks: document.getElementById("paymentRemarks")?.value.trim(),
        totalWithInterest: document.getElementById("amountPaid")?.value || 0,
        balance: 0,
    };

    closeFinalizeConfirmModal();
    btn.disabled = true;
    btn.innerHTML = `<span class="animate-pulse">Processing...</span>`;

    const formData = new FormData();
    for (const key in payload) formData.append(key, payload[key]);

    try {
        const res = await fetch("../include/inc.showroom/sr.ctrl.php", {
            method: "POST",
            body: formData
        });
        const result = await res.json();
        if (result.success) {
            if (typeof showToast === 'function') {
                showToast('Transaction completed successfully!', 'success');
                setTimeout(() => window.location.reload(), 1500);
            } else {
                window.location.reload();
            }
        } else {
            if (typeof showToast === 'function') showToast(result.message || "Could not finalize.", 'error');
            btn.disabled = false;
            btn.innerText = "Complete Sale";
        }
    } catch (err) {
        console.error(err);
        btn.disabled = false;
        btn.innerText = "Complete Sale";
    }
}

// --- GLOBAL INITIALIZATION ---

document.addEventListener('DOMContentLoaded', () => {
    if (typeof updateCartBadgeCount === 'function') updateCartBadgeCount();
});
