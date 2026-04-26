/**
 * Admin Dashboard Logic
 */

document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('dashboard-container');
    if (!container) return;

    const rawData = container.getAttribute('data-dashboard');
    if (!rawData) return;

    const pageData = JSON.parse(rawData);
    
    // --- Event Listeners ---
    const collectionModal = document.getElementById('collectionModal');
    if (collectionModal) {
        collectionModal.addEventListener('click', (e) => {
            if (e.target.closest('[data-close-collection]')) toggleCollectionModal(false);
        });
    }

    const receivablesTable = document.getElementById('receivablesContent');
    if (receivablesTable) {
        receivablesTable.addEventListener('click', (e) => {
            const btn = e.target.closest('.open-collection-btn');
            if (btn) {
                const data = JSON.parse(btn.getAttribute('data-order'));
                openCollectionModal(data);
            }
        });
    }

    const collForm = document.getElementById('modal-collection-form');
    if (collForm) {
        collForm.addEventListener('input', (e) => {
            setCollectionFormDirty();
            if (e.target.id === 'collAmount') handleAmountInput(e.target.value);
        });
        collForm.addEventListener('change', (e) => {
            if (e.target.id === 'collMethod') setCollectionFormDirty();
        });
    }

    const submitCollBtn = document.getElementById('submitCollectionBtn');
    if (submitCollBtn) submitCollBtn.addEventListener('click', submitCollection);

    // Initial Load
    renderLowStockTable();
    renderRecentOrdersTable();
    fetchReceivables();
    initCharts();
});

// --- RECENT ORDERS PROGRESSIVE LOGIC ---
let recentOrdersDisplayLimit = 3;
let recentOrdersPage = 1;
const recentOrdersThreshold = 5;

function renderRecentOrdersTable() {
    const container = document.getElementById('dashboard-container');
    const rawData = container?.getAttribute('data-dashboard');
    if (!rawData) return;
    const pageData = JSON.parse(rawData);
    const data = pageData.recentOrders || [];

    const content = document.getElementById('recentOrdersContent');
    const footer = document.getElementById('recentOrdersTableFooter');

    if (!content) return;

    if (data.length === 0) {
        content.innerHTML = `<tr><td colspan="4" class="py-10 text-center text-gray-400 italic font-medium">No recent orders</td></tr>`;
        if (footer) footer.innerHTML = '';
        return;
    }

    let dataToShow = [];
    let total = data.length;

    if (total > recentOrdersThreshold && recentOrdersDisplayLimit === recentOrdersThreshold) {
        let start = (recentOrdersPage - 1) * recentOrdersThreshold;
        let end = start + recentOrdersThreshold;
        dataToShow = data.slice(start, end);
    } else {
        dataToShow = data.slice(0, recentOrdersDisplayLimit);
    }

    content.innerHTML = dataToShow.map(order => {
        const status = order.status.toLowerCase();
        const statusClass = status === 'completed' ? 'bg-green-100 text-green-600' : (status === 'rejected' ? 'bg-red-100 text-red-600' : 'bg-blue-100 text-blue-600');
        const dateObj = new Date(order.created_at);
        const formattedDate = dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });

        return `
            <tr class="hover:bg-gray-50/50 transition-colors">
                <td class="px-6 py-4 font-bold text-gray-900 leading-none">#ORD-${order.id.toString().padStart(5, '0')}</td>
                <td class="px-6 py-4 text-gray-400 font-mono text-[11px] font-medium">${formattedDate}</td>
                <td class="px-6 py-4 text-right font-black text-gray-900 leading-none">₱${parseFloat(order.total).toLocaleString()}</td>
                <td class="px-6 py-4 text-center">
                    <span class="px-3 py-1 ${statusClass} rounded-full text-[10px] font-bold italic uppercase tracking-tighter">
                        ${order.status}
                    </span>
                </td>
            </tr>
        `;
    }).join('');

    if (footer) {
        if (total > 3 && recentOrdersDisplayLimit === 3) {
            footer.innerHTML = `
                <button class="expand-orders-btn flex items-center gap-2 text-[10px] font-bold text-gray-400 uppercase tracking-widest hover:text-red-600 transition group">
                    Show More (${total})
                    <svg class="size-4 group-hover:translate-y-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7" stroke-width="3" /></svg>
                </button>
            `;
        } else if (total > recentOrdersThreshold) {
            let totalPages = Math.ceil(total / recentOrdersThreshold);
            let pagesHtml = `<button class="collapse-orders-btn text-[10px] font-black text-gray-300 uppercase tracking-tighter hover:text-red-600 transition mr-2">Show Less</button>`;
            for (let i = 1; i <= totalPages; i++) {
                pagesHtml += `<button data-page="${i}" class="go-to-orders-page-btn size-7 rounded-lg text-xs font-black transition ${recentOrdersPage === i ? 'bg-red-600 text-white shadow-lg' : 'text-gray-400 hover:bg-gray-100'}">${i}</button>`;
            }
            footer.innerHTML = `<div class="flex items-center gap-2">${pagesHtml}</div>`;
        } else if (recentOrdersDisplayLimit > 3) {
            footer.innerHTML = `
                <button class="collapse-orders-btn flex items-center gap-2 text-[10px] font-bold text-gray-400 uppercase tracking-widest hover:text-red-600 transition group">
                    Show Less
                    <svg class="size-4 group-hover:-translate-y-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 15l7-7 7 7" stroke-width="3" /></svg>
                </button>
            `;
        } else {
            footer.innerHTML = '';
        }
    }
}

function expandRecentOrdersTable() {
    recentOrdersDisplayLimit = recentOrdersThreshold;
    renderRecentOrdersTable();
}

function collapseRecentOrdersTable() {
    recentOrdersDisplayLimit = 3;
    recentOrdersPage = 1;
    renderRecentOrdersTable();
}

function goToRecentOrdersPage(p) {
    recentOrdersPage = p;
    renderRecentOrdersTable();
}

// --- LOW STOCK PROGRESSIVE LOGIC ---
let lowStockDisplayLimit = 3;
let lowStockPage = 1;
const lowStockPageThreshold = 5;

function renderLowStockTable() {
    const container = document.getElementById('dashboard-container');
    const rawData = container?.getAttribute('data-dashboard');
    if (!rawData) return;
    const pageData = JSON.parse(rawData);
    const data = pageData.lowStockItems || [];

    const content = document.getElementById('lowStockAlertsContent');
    const footer = document.getElementById('lowStockTableFooter');

    if (!content) return;

    if (data.length === 0) {
        content.innerHTML = `<tr><td colspan="4" class="py-10 text-center text-gray-400 italic font-medium">No low stock alerts</td></tr>`;
        if (footer) footer.innerHTML = '';
        return;
    }

    let dataToShow = [];
    let total = data.length;

    if (total > lowStockPageThreshold && lowStockDisplayLimit === lowStockPageThreshold) {
        let start = (lowStockPage - 1) * lowStockPageThreshold;
        let end = start + lowStockPageThreshold;
        dataToShow = data.slice(start, end);
    } else {
        dataToShow = data.slice(0, lowStockDisplayLimit);
    }

    content.innerHTML = dataToShow.map(item => {
        const threshold = parseInt(item.min_buildable_qty);
        const srQty = parseInt(item.sr_qty);
        const whQty = parseInt(item.wh_qty);
        let location = '';
        let displayQty = '';
        if (whQty <= threshold && srQty <= threshold) {
            location = 'Both';
            displayQty = `WH: ${whQty} | SR: ${srQty}`;
        } else if (whQty <= threshold) {
            location = 'Warehouse';
            displayQty = whQty;
        } else {
            location = 'Showroom';
            displayQty = srQty;
        }

        return `
            <tr class="hover:bg-gray-50/50 transition-colors">
                <td class="px-6 py-4 font-bold text-gray-800">${item.prod_name}</td>
                <td class="px-6 py-4 text-gray-400 font-medium">${item.variant}</td>
                <td class="px-6 py-4 text-center">
                    <span class="px-2 py-0.5 bg-gray-100 text-gray-500 rounded text-[9px] font-black uppercase tracking-widest border border-gray-200">
                        ${location}
                    </span>
                </td>
                <td class="px-6 py-4 text-right text-red-600 font-black">${displayQty}</td>
            </tr>
        `;
    }).join('');

    if (footer) {
        if (total > 3 && lowStockDisplayLimit === 3) {
            footer.innerHTML = `
                <button class="expand-low-stock-btn flex items-center gap-2 text-[10px] font-bold text-gray-400 uppercase tracking-widest hover:text-red-600 transition group">
                    Show More (${total})
                    <svg class="size-4 group-hover:translate-y-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7" stroke-width="3" /></svg>
                </button>
            `;
        } else if (total > lowStockPageThreshold) {
            let totalPages = Math.ceil(total / lowStockPageThreshold);
            let pagesHtml = `<button class="collapse-low-stock-btn text-[10px] font-black text-gray-300 uppercase tracking-tighter hover:text-red-600 transition mr-2">Show Less</button>`;
            for (let i = 1; i <= totalPages; i++) {
                pagesHtml += `<button data-page="${i}" class="go-to-low-stock-page-btn size-7 rounded-lg text-xs font-black transition ${lowStockPage === i ? 'bg-red-600 text-white shadow-lg' : 'text-gray-400 hover:bg-gray-100'}">${i}</button>`;
            }
            footer.innerHTML = `<div class="flex items-center gap-2">${pagesHtml}</div>`;
        } else if (lowStockDisplayLimit > 3) {
            footer.innerHTML = `
                <button class="collapse-low-stock-btn flex items-center gap-2 text-[10px] font-bold text-gray-400 uppercase tracking-widest hover:text-red-600 transition group">
                    Show Less
                    <svg class="size-4 group-hover:-translate-y-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 15l7-7 7 7" stroke-width="3" /></svg>
                </button>
            `;
        } else {
            footer.innerHTML = '';
        }
    }
}

function expandLowStockTable() {
    lowStockDisplayLimit = lowStockPageThreshold;
    renderLowStockTable();
}

function collapseLowStockTable() {
    lowStockDisplayLimit = 3;
    lowStockPage = 1;
    renderLowStockTable();
}

function goToLowStockPage(p) {
    lowStockPage = p;
    renderLowStockTable();
}

function fetchReceivables() {
    const content = document.getElementById('receivablesContent');
    const countDisplay = document.getElementById('receivableCount');
    const totalDisplay = document.getElementById('receivableTotal');

    if (!content) return;

    fetch(`../include/inc.admin/admin.ctrl.php?action=get_receivables`)
        .then(response => response.json())
        .then(response => {
            if (response.success) {
                if (countDisplay) countDisplay.innerText = `${response.stats.count} pending accounts`;
                if (totalDisplay) totalDisplay.innerText = `₱${response.stats.total.toLocaleString()}`;

                if (response.data.length === 0) {
                    content.innerHTML = `<tr><td colspan="7" class="py-20 text-center"><p class="text-[10px] font-black text-gray-300 uppercase tracking-widest">No pending collections found</p></td></tr>`;
                    return;
                }

                content.innerHTML = response.data.map(row => {
                    const dataObj = JSON.stringify({id: row.id, name: row.client_name, balance: row.balance, or: row.or_number}).replace(/'/g, "&apos;");
                    return `
                        <tr class="hover:bg-orange-50/20 transition group">
                            <td class="px-8 py-5 font-bold text-gray-900 leading-none">
                                <p class="text-[10px] text-gray-400 font-mono mb-1 tracking-tighter">#ORD-${row.id.toString().padStart(5, '0')}</p>
                                <p class="text-[9px] font-black text-orange-600 uppercase border-l-2 border-orange-500 pl-2 leading-none">${row.or_number || 'NO-REF'}</p>
                            </td>
                            <td class="px-8 py-5">
                                <div class="font-black text-gray-800 uppercase tracking-tighter leading-tight">${row.client_name}</div>
                                <p class="text-[10px] text-gray-400 font-medium">${row.branch || 'Independent Branch'}</p>
                            </td>
                            <td class="px-8 py-5 text-center">
                                <span class="px-2 py-0.5 rounded text-[8px] font-black uppercase tracking-widest bg-blue-50 text-blue-600">
                                    ${row.client_type}
                                </span>
                            </td>
                            <td class="px-8 py-5 text-center text-[10px] font-bold text-gray-400 uppercase tracking-tighter leading-tight">
                                ${new Date(row.created_at).toLocaleDateString()}
                            </td>
                            <td class="px-8 py-5 text-right font-black text-gray-900 leading-none">
                                <p class="text-sm font-black">₱${parseFloat(row.balance).toLocaleString(undefined, {minimumFractionDigits: 2})}</p>
                                <p class="text-[9px] text-gray-300 font-medium mt-1">OF ₱${parseFloat(row.total).toLocaleString()}</p>
                            </td>
                            <td class="px-8 py-5 text-center">
                                <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest border bg-orange-50 text-orange-600 border-orange-100">
                                    ${row.status}
                                </span>
                            </td>
                            <td class="px-8 py-5 text-center">
                                <button data-order='${dataObj}' 
                                    class="open-collection-btn size-8 rounded-lg bg-white border border-gray-100 flex items-center justify-center text-gray-400 hover:bg-orange-600 hover:text-white hover:border-orange-700 transition-all shadow-sm">
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2" /></svg>
                                </button>
                            </td>
                        </tr>
                    `;
                }).join('');
            }
        });
}

let isCollectionFormDirty = false;
const setCollectionFormDirty = () => isCollectionFormDirty = true;

function toggleCollectionModal(show) {
    const modal = document.getElementById('collectionModal');
    if (!modal) return;

    if (!show && isCollectionFormDirty) {
        if (!confirm("You have unsaved changes in this collection form. Are you sure you want to close and discard them?")) {
            return;
        }
    }

    modal.classList.toggle('hidden', !show);
    if (!show) {
        const amountInput = document.getElementById('collAmount');
        const refInput = document.getElementById('collRef');
        const remarksInput = document.getElementById('collRemarks');
        const methodInput = document.getElementById('collMethod');

        if(amountInput) amountInput.value = '';
        if(refInput) refInput.value = '';
        if(remarksInput) remarksInput.value = '';
        if(methodInput) methodInput.value = 'Cash';
        isCollectionFormDirty = false;
    }
}

function handleAmountInput(value) {
    const val = parseFloat(value || 0);
    document.getElementById('currentCapturingDisplay').innerText = `₱ ${val.toLocaleString(undefined, {minimumFractionDigits: 2})}`;
}

async function openCollectionModal(data) {
    const collOrderId = document.getElementById('collOrderId');
    if (collOrderId) collOrderId.value = data.id;

    // Reset fields to loading state
    const modalId = document.getElementById('modal-id');
    const modalCustomer = document.getElementById('modal-customer');
    const modalContact = document.getElementById('modal-contact');
    const modalDate = document.getElementById('modal-date');
    const modalPrincipal = document.getElementById('modal-principal');
    const modalInterest = document.getElementById('modal-interest');
    const modalPaid = document.getElementById('modal-paid');
    const modalBalance = document.getElementById('modal-balance');
    const footerTotal = document.getElementById('collFooterTotal');
    const termIndicator = document.getElementById('termIndicator');
    const nextDueLabel = document.getElementById('collNextDue');
    const currentCapturing = document.getElementById('currentCapturingDisplay');

    if (modalId) modalId.innerText = data.or || "REF-SYNC...";
    if (modalCustomer) modalCustomer.innerText = "Loading...";
    if (modalContact) modalContact.innerText = "---";
    if (modalDate) modalDate.innerText = "---";
    if (modalPrincipal) modalPrincipal.innerText = "₱ 0.00";
    if (modalInterest) modalInterest.innerText = "0%";
    if (modalPaid) modalPaid.innerText = "₱ 0.00";
    if (modalBalance) modalBalance.innerText = "₱ 0.00";
    if (footerTotal) footerTotal.innerText = "₱ 0.00";
    if (termIndicator) termIndicator.innerText = "Checking...";
    if (nextDueLabel) nextDueLabel.innerText = "Syncing...";
    if (currentCapturing) currentCapturing.innerText = "₱ 0.00";

    const tbody = document.getElementById('collTrackerBody');
    if(tbody) tbody.innerHTML = `<tr><td colspan="4" class="py-10 text-center"><div class="animate-spin size-6 border-4 border-gray-100 border-t-orange-600 rounded-full mx-auto"></div></td></tr>`;

    toggleCollectionModal(true);

    try {
        const res = await fetch(`../include/inc.admin/admin.ctrl.php?action=get_order_details&order_id=${data.id}`);
        const response = await res.json();

        if (response.success && response.summary) {
            const s = response.summary;
            if (modalCustomer) modalCustomer.innerText = s.customer_name;
            if (modalContact) modalContact.innerText = s.contact_no || 'N/A';
            if (modalId) modalId.innerText = s.or_number ? `#${s.or_number}` : 'NO-REF';
            if (modalDate) modalDate.innerText = new Date(s.created_at).toLocaleDateString('en-US', {
                month: 'long', day: 'numeric', year: 'numeric'
            });

            if (modalPrincipal) modalPrincipal.innerText = `₱ ${parseFloat(s.principal_amount || 0).toLocaleString(undefined, {minimumFractionDigits: 2})}`;
            if (modalInterest) modalInterest.innerText = `${s.interest_rate}%`;
            if (footerTotal) footerTotal.innerText = `₱ ${parseFloat(s.total_with_interest || 0).toLocaleString(undefined, {minimumFractionDigits: 2})}`;
        }

        if (response.success && response.schedule && tbody) {
            let totalPaidSum = 0;
            let nextDue = null;
            let nextTermLabel = "Fully Paid";

            tbody.innerHTML = response.schedule.map((term, index) => {
                const isPaid = term.status === 'Paid';
                if (isPaid) totalPaidSum += parseFloat(term.amount_paid || 0);
                if (!isPaid && !nextDue) {
                    nextDue = term;
                    nextTermLabel = term.remarks || `Term ${index + 1}`;
                }
                const statusClass = isPaid ? 'bg-green-100/50 text-green-600 border-green-100' : 'bg-orange-50 text-orange-600 border-orange-100';
                return `
                    <tr class="hover:bg-gray-50/50 transition border-b border-gray-50 last:border-0 group">
                        <td class="px-6 py-4">
                            <div class="font-extrabold text-gray-900 uppercase tracking-tighter text-[12px]">${term.remarks || `Term ${index + 1}`}</div>
                            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mt-0.5 whitespace-nowrap">${isPaid ? 'Payment Confirmed' : 'Scheduled'}</p>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <p class="text-[11px] font-black text-gray-500 uppercase tracking-tighter leading-none">${term.due_date ? new Date(term.due_date).toLocaleDateString() : 'N/A'}</p>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase tracking-widest border ${statusClass}">
                                ${term.status}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <p class="text-[12px] font-extrabold text-gray-950 leading-none">₱${parseFloat(term.amount_paid || 0).toLocaleString(undefined, {minimumFractionDigits: 2})}</p>
                        </td>
                    </tr>
                `;
            }).join('');

            if (modalPaid) modalPaid.innerText = `₱ ${totalPaidSum.toLocaleString(undefined, {minimumFractionDigits: 2})}`;
            const payable = parseFloat(response.summary.total_with_interest || 0);
            const currentBalance = payable - totalPaidSum;
            if (modalBalance) modalBalance.innerText = `₱ ${currentBalance.toLocaleString(undefined, {minimumFractionDigits: 2})}`;
            if (nextDueLabel) nextDueLabel.innerText = nextDue ? `Next: ${nextTermLabel}` : "Cleared";
            if (termIndicator) termIndicator.innerText = nextTermLabel;

            const amntInp = document.getElementById('collAmount');
            if (nextDue) {
                if (amntInp) amntInp.value = parseFloat(nextDue.amount_paid || 0).toFixed(2);
                if (currentCapturing) currentCapturing.innerText = `₱ ${parseFloat(nextDue.amount_paid || 0).toLocaleString(undefined, {minimumFractionDigits: 2})}`;
            } else {
                if (amntInp) amntInp.value = '';
                if (currentCapturing) currentCapturing.innerText = "₱ 0.00";
            }
            isCollectionFormDirty = false;
        } else if(tbody) {
            tbody.innerHTML = `<tr><td colspan="4" class="py-10 text-center text-red-500 text-xs font-bold uppercase tracking-widest">Failed to load schedule</td></tr>`;
        }
    } catch (e) {
        console.error("Error loading collection details:", e);
        if(tbody) tbody.innerHTML = `<tr><td colspan="4" class="py-10 text-center text-red-500 text-xs font-bold uppercase tracking-widest">Sync Error</td></tr>`;
    }
}

function submitCollection() {
    const orderId = document.getElementById('collOrderId')?.value;
    const amount = document.getElementById('collAmount')?.value;
    const ref = document.getElementById('collRef')?.value;
    const method = document.getElementById('collMethod')?.value;
    const remarks = document.getElementById('collRemarks')?.value;

    if (!amount || parseFloat(amount) <= 0) {
        alert('Please enter a valid amount');
        return;
    }

    const formData = new FormData();
    formData.append('order_id', orderId);
    formData.append('amount', amount);
    formData.append('reference', ref);
    formData.append('payment_method', method);
    formData.append('remarks', remarks);

    fetch(`../include/inc.admin/admin.ctrl.php?action=record_collection`, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(response => {
        if (response.success) {
            isCollectionFormDirty = false;
            toggleCollectionModal(false);
            if (typeof showToast === 'function') {
                showToast('Collection finalized successfully!', 'success');
            }
            fetchReceivables();
            renderRecentOrdersTable();
        } else {
            alert(response.error || 'Failed to record payment');
        }
    });
}

/**
 * --- DASHBOARD CHARTS ---
 */
function initCharts() {
    if (typeof Chart === 'undefined') {
        console.error("Chart.js not loaded.");
        return;
    }

    const container = document.getElementById('dashboard-container');
    const rawData = container?.getAttribute('data-dashboard');
    if (!rawData) return;

    let pageData;
    try {
        pageData = JSON.parse(rawData);
    } catch (e) {
        console.error("Failed to parse dashboard data", e);
        return;
    }

    // 1. Sales Trend Chart
    const salesTrendData = pageData.salesTrend || [];
    const salesCtx = document.getElementById('salesTrendChart');
    if (salesCtx && salesTrendData.length > 0) {
        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: salesTrendData.map(d => d.month),
                datasets: [{
                    label: 'Revenue',
                    data: salesTrendData.map(d => parseFloat(d.total_sales)),
                    borderColor: '#ef4444', // red-500
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#ef4444',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) label += ': ';
                                if (context.parsed.y !== null) {
                                    label += new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(context.parsed.y);
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { 
                            drawBorder: false,
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            callback: value => '₱' + value.toLocaleString(),
                            font: { size: 11, weight: '600' }
                        }
                    },
                    x: {
                        grid: { display: false },
                        ticks: {
                            font: { size: 11, weight: '600' }
                        }
                    }
                }
            }
        });
    }

    // 2. Inventory by Location Chart
    const inventoryData = pageData.inventoryStats || [];
    const invCtx = document.getElementById('inventoryChart');
    if (invCtx && inventoryData.length > 0) {
        new Chart(invCtx, {
            type: 'bar',
            data: {
                labels: inventoryData.map(d => d.category),
                datasets: [
                    {
                        label: 'Warehouse',
                        data: inventoryData.map(d => parseInt(d.wh_qty)),
                        backgroundColor: '#111827', // gray-900 (blackish)
                        borderRadius: 6,
                        categoryPercentage: 0.6,
                        barPercentage: 0.8
                    },
                    {
                        label: 'Showroom',
                        data: inventoryData.map(d => parseInt(d.sr_qty)),
                        backgroundColor: '#9ca3af', // gray-400
                        borderRadius: 6,
                        categoryPercentage: 0.6,
                        barPercentage: 0.8
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(17, 24, 39, 0.9)',
                        titleFont: { family: 'Outfit', size: 13, weight: '800' },
                        bodyFont: { family: 'Inter', size: 12 },
                        padding: 12,
                        cornerRadius: 10,
                        displayColors: true
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100, // Fixed baseline at 100 as requested
                        grid: { 
                            drawBorder: false,
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            stepSize: 20,
                            font: { size: 11, weight: '600' }
                        }
                    },
                    x: {
                        grid: { display: false },
                        ticks: {
                            font: { size: 11, weight: '700' },
                            color: '#374151'
                        }
                    }
                }
            }
        });
    }
}
