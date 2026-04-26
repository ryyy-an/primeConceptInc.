/**
 * Reports and Analytics Dashboard Logic
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize data from the secure container attribute
    const container = document.getElementById('reports-container');
    if (!container) return;

    const rawData = container.getAttribute('data-reports');
    if (!rawData) return;

    try {
        const pageData = JSON.parse(rawData);
        const stockHealth = pageData.stockHealth;
        const salesTrend = pageData.salesTrend;

        // 1. Health Chart (Doughnut)
        const healthCanvas = document.getElementById('healthChart');
        if (healthCanvas) {
            const healthCtx = healthCanvas.getContext('2d');
            new Chart(healthCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Healthy', 'Low', 'Out'],
                    datasets: [{
                        data: [
                            stockHealth.healthy,
                            stockHealth.low_stock,
                            stockHealth.out_of_stock
                        ],
                        backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    cutout: '70%',
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }

        // 2. Sales Chart (Line)
        const salesCanvas = document.getElementById('salesChart');
        if (salesCanvas) {
            const salesCtx = salesCanvas.getContext('2d');
            window.salesChart = new Chart(salesCtx, {
                type: 'line',
                data: {
                    labels: salesTrend.map(item => item.month_name),
                    datasets: [{
                        label: 'Revenue',
                        data: salesTrend.map(item => item.revenue),
                        borderColor: '#dc2626',
                        backgroundColor: 'rgba(220, 38, 38, 0.1)',
                        fill: true,
                        tension: 0.4,
                        borderWidth: 3,
                        pointRadius: 4,
                        pointBackgroundColor: '#dc2626'
                    }]
                },
                options: {
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: '#f3f4f6'
                            },
                            ticks: {
                                callback: value => '₱' + value.toLocaleString()
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    },
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }
    } catch (e) {
        console.error('[Admin Reports] Failed to parse page data:', e);
    }

    // --- EVENT DELEGATION ---
    initReportsEventDelegation();

    // Initial Load for Transactions
    fetchOrdersReport();
});

function initReportsEventDelegation() {
    // 1. Click Listeners
    document.body.addEventListener('click', (e) => {
        // Reset Filters
        if (e.target.closest('#resetAdminFiltersBtn')) {
            resetAdminFilters();
            return;
        }

        // Close Transaction Modal
        if (e.target.closest('#closeTxnModalBtn')) {
            closeTxnModal();
            return;
        }

        // View Order Details
        const viewBtn = e.target.closest('.view-details-btn');
        if (viewBtn) {
            const orderId = viewBtn.getAttribute('data-order-id');
            if (orderId) viewOrderDetails(orderId);
            return;
        }

        // Expand/Collapse Order Table
        if (e.target.closest('.expand-table-btn')) { expandOrderTable(); return; }
        if (e.target.closest('.show-less-btn')) { showLessOrders(); return; }

        // Pagination
        const pageBtn = e.target.closest('.go-to-page-btn');
        if (pageBtn) {
            const pageNum = pageBtn.getAttribute('data-page');
            if (pageNum) goToOrderPage(parseInt(pageNum));
            return;
        }
    });

    // 2. Change Listeners (Filters)
    document.body.addEventListener('change', (e) => {
        // Top Products Filter
        if (e.target.id === 'topProductsFilter') {
            fetchTopProducts();
            return;
        }

        // Transactions Date/Client Filter
        if (['reportStartDate', 'reportEndDate', 'combinedTxnFilter'].includes(e.target.id)) {
            fetchOrdersReport();
            return;
        }
    });
}

// --- ORDERS REPORT PROGRESSIVE LOGIC ---
let allOrdersData = [];
let displayLimit = 3;
let paginationThreshold = 5;
let currentPage = 1;

window.changeOrderStatusFilter = function(status) {
    fetchOrdersReport();
};

function resetAdminFilters() {
    const startInput = document.getElementById('reportStartDate');
    const endInput = document.getElementById('reportEndDate');
    const filterInput = document.getElementById('combinedTxnFilter');

    if(startInput) startInput.value = '';
    if(endInput) endInput.value = '';
    if(filterInput) filterInput.value = 'All';
    fetchOrdersReport();
}

function fetchOrdersReport() {
    const content = document.getElementById('ordersReportContent');
    const start = document.getElementById('reportStartDate')?.value || '';
    const end = document.getElementById('reportEndDate')?.value || '';
    const client = document.getElementById('combinedTxnFilter')?.value || 'All';

    if (!content) return;

    content.innerHTML = `<tr><td colspan="8" class="py-20 text-center"><div class="flex flex-col items-center gap-2"><div class="size-8 border-4 border-gray-100 border-t-red-600 rounded-full animate-spin"></div><p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Loading transactions...</p></div></td></tr>`;

    fetch(`../include/inc.admin/admin.ctrl.php?action=get_report_sales&client=${client}&start=${start}&end=${end}`)
        .then(res => res.json())
        .then(response => {
            if (response.success) {
                allOrdersData = response.data;
                renderOrdersTable();
            }
        });

    updateSalesTrendChart();
}

function renderOrdersTable() {
    const content = document.getElementById('ordersReportContent');
    const footer = document.getElementById('orderTableFooter');

    if (!content) return;

    if (allOrdersData.length === 0) {
        content.innerHTML = `<tr><td colspan="8" class="py-20 text-center"><p class="text-[11px] font-black text-gray-300 uppercase tracking-widest">No transaction records found</p></td></tr>`;
        if (footer) footer.innerHTML = '';
        return;
    }

    // Determine display range
    let dataToShow = [];
    let total = allOrdersData.length;

    if (total > paginationThreshold && displayLimit === paginationThreshold) {
        let start = (currentPage - 1) * paginationThreshold;
        let end = start + paginationThreshold;
        dataToShow = allOrdersData.slice(start, end);
    } else {
        dataToShow = allOrdersData.slice(0, displayLimit);
    }

    // Render Table
    content.innerHTML = dataToShow.map(row => {
        const transStatus = (row.trans_status || '').toUpperCase();
        const statusColors = {
            'SUCCESS': 'bg-green-50 text-green-600 border-green-200',
            'ONGOING': 'bg-orange-50 text-orange-600 border-orange-200',
            'PENDING': 'bg-yellow-50 text-yellow-600 border-yellow-200',
            'FAILED': 'bg-red-50 text-red-600 border-red-200',
        };
        const statusCls = statusColors[transStatus] || 'bg-gray-50 text-gray-600 border-gray-200';

        return `
            <tr class="hover:bg-red-50/30 transition group">
                <td class="px-8 py-5">
                    <p class="font-mono text-[11px] text-gray-400 font-bold tracking-tighter leading-none mb-1">#TXN-${row.trans_id.toString().padStart(5, '0')}</p>
                    <p class="text-[9px] font-black text-gray-900 border-l-2 border-red-500 pl-2 uppercase tracking-widest" title="OR Number">${row.or_number || 'NO-REF'}</p>
                </td>
                <td class="px-8 py-5 text-sm font-bold text-gray-700 leading-tight">
                    ${new Date(row.transaction_date).toLocaleDateString()}<br>
                    <span class="text-[9px] text-gray-400 font-medium font-mono">${new Date(row.transaction_date).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>
                </td>
                <td class="px-8 py-5 text-sm font-black text-gray-900 leading-tight">
                    ${row.customer_name}
                </td>
                <td class="px-8 py-5 text-center">
                    <span class="px-2 py-0.5 rounded text-[8px] font-black uppercase tracking-widest whitespace-nowrap ${row.client_type === 'Government' ? 'bg-indigo-50 text-indigo-600' : 'bg-slate-50 text-slate-500'}">
                        ${row.client_type || 'Private'}
                    </span>
                </td>
                <td class="px-8 py-5 text-center">
                    <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-widest whitespace-nowrap ${row.plan === 'Installment' ? 'bg-orange-50 text-orange-600 border border-orange-100' : 'bg-blue-50 text-blue-600 border border-blue-100'}">
                        ${row.plan === 'Installment' ? 'Installment' : 'Full Paid'}
                    </span>
                </td>
                <td class="px-8 py-5 text-right font-black text-gray-900">₱${parseFloat(row.amount_paid).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                <td class="px-8 py-5 text-center">
                    <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest border ${statusCls}">${row.trans_status}</span>
                </td>
                <td class="px-8 py-5 text-center">
                    <button data-order-id="${row.order_id}" class="view-details-btn size-8 rounded-lg bg-gray-50 flex items-center justify-center text-gray-400 hover:bg-red-600 hover:text-white transition shadow-sm border border-gray-100" title="View Parent Order Details">
                        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </td>
            </tr>
        `;
    }).join('');

    // Footer Logic
    if (footer) {
        if (total > 3 && displayLimit === 3) {
            footer.innerHTML = `
                <button class="expand-table-btn flex items-center gap-2 text-[10px] font-black text-gray-900 uppercase tracking-widest hover:text-red-600 transition group">
                    Show All (${total})
                    <svg class="size-4 group-hover:translate-y-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M19 9l-7 7-7-7" stroke-width="3" />
                    </svg>
                </button>`;
        } else if (displayLimit === paginationThreshold) {
            const totalPages = Math.ceil(total / paginationThreshold);
            let pagesHtml = '';
            for (let i = 1; i <= totalPages; i++) {
                pagesHtml += `<button data-page="${i}" class="go-to-page-btn size-8 rounded-lg text-xs font-black transition ${currentPage === i ? 'bg-red-600 text-white shadow-lg' : 'text-gray-400 hover:bg-gray-100'}">${i}</button>`;
            }
            footer.innerHTML = `
                <div class="flex flex-col items-center gap-4">
                    <button class="show-less-btn text-[10px] font-black text-gray-400 uppercase tracking-widest hover:text-red-600 transition group flex items-center gap-2">
                        <svg class="size-4 group-hover:-translate-y-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 15l7-7 7 7" stroke-width="3" /></svg>
                        Show Less
                    </button>
                    <div class="flex items-center gap-2">${pagesHtml}</div>
                </div>`;
        } else {
            footer.innerHTML = '';
        }
    }
}

function viewOrderDetails(orderId) {
    const modal = document.getElementById('txnDetailModal');
    const content = document.getElementById('modalItemsContent');
    const summaryHeader = document.getElementById('modalSummaryHeader');
    const financialHeader = document.getElementById('modalFinancialHeader');
    const scheduleContent = document.getElementById('modalScheduleContent');
    const scheduleWrapper = document.getElementById('modalScheduleWrapper');
    const title = document.getElementById('modalTxnId');
    const totalDisplay = document.getElementById('modalTotalAmount');

    if (!modal) return;

    title.innerText = `Order #ORD-${orderId.toString().padStart(5, '0')}`;
    summaryHeader.innerHTML = '<div class="col-span-full py-4 animate-pulse bg-gray-50 rounded-xl"></div>';
    financialHeader.innerHTML = '<div class="col-span-full py-4 animate-pulse bg-gray-50 rounded-xl"></div>';
    content.innerHTML = `<tr><td colspan="4" class="py-10 text-center"><div class="animate-spin size-6 border-4 border-gray-100 border-t-red-600 rounded-full mx-auto mb-2"></div><p class="text-[10px] font-black text-gray-300 uppercase tracking-widest">Fetching data...</p></td></tr>`;

    scheduleWrapper.classList.add('hidden');
    modal.classList.remove('hidden');

    fetch(`../include/inc.admin/admin.ctrl.php?action=get_order_details&order_id=${orderId}`)
        .then(res => res.json())
        .then(response => {
            if (response.success && response.summary) {
                const s = response.summary;

                // 1. Summary Badges
                summaryHeader.innerHTML = `
                    <div class="p-3 bg-gray-50 rounded-xl border border-gray-100">
                        <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Customer</p>
                        <p class="text-xs font-black text-gray-900">${s.customer_name}</p>
                    </div>
                    <div class="p-3 bg-gray-50 rounded-xl border border-gray-100">
                        <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Contact</p>
                        <p class="text-xs font-black text-gray-900">${s.contact_no || 'N/A'}</p>
                    </div>
                    <div class="p-3 bg-gray-50 rounded-xl border border-gray-100">
                        <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">OR Number</p>
                        <p class="text-xs font-black text-red-600 uppercase">${s.or_number || 'N/A'}</p>
                    </div>
                    <div class="p-3 bg-gray-50 rounded-xl border border-gray-100">
                        <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Payment Mode</p>
                        <p class="text-xs font-black text-gray-900 uppercase">${s.payment_mode || 'N/A'}</p>
                    </div>
                    <div class="p-3 bg-gray-50 rounded-xl border border-gray-100">
                        <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Txn Date</p>
                        <p class="text-xs font-black text-gray-900 uppercase">${new Date(s.transaction_date).toLocaleDateString()}</p>
                    </div>
                `;

                // 2. Financial Overview
                const totalPaid = (response.schedule || []).reduce((acc, row) => acc + (row.status === 'Paid' ? parseFloat(row.amount_paid) : 0), 0) || parseFloat(s.total) - parseFloat(s.balance);

                financialHeader.innerHTML = `
                    <div>
                        <p class="text-[9px] font-black text-red-400 uppercase tracking-widest mb-1">Principal</p>
                        <p class="text-lg font-black text-red-600 leading-none">₱${parseFloat(s.total).toLocaleString()}</p>
                    </div>
                    <div>
                        <p class="text-[9px] font-black text-red-400 uppercase tracking-widest mb-1">Interest</p>
                        <p class="text-lg font-black text-red-600 leading-none">${s.interest_rate || 0}%</p>
                    </div>
                    <div>
                        <p class="text-[9px] font-black text-red-400 uppercase tracking-widest mb-1">Total Payable</p>
                        <p class="text-lg font-black text-red-600 leading-none">₱${parseFloat(s.total_with_interest || s.total).toLocaleString()}</p>
                    </div>
                    <div class="border-l border-red-200 pl-6">
                        <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Total Paid</p>
                        <p class="text-lg font-black text-green-600 leading-none">₱${totalPaid.toLocaleString()}</p>
                    </div>
                    <div>
                        <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Balance</p>
                        <p class="text-lg font-black ${parseFloat(s.balance) > 0 ? 'text-red-500' : 'text-green-500'} leading-none">₱${parseFloat(s.balance).toLocaleString()}</p>
                    </div>
                `;

                // 3. Items
                let itemsHtml = '';
                response.items.forEach(item => {
                    const subtotal = item.quantity * item.price;
                    itemsHtml += `
                        <tr class="text-[11px] font-bold">
                            <td class="px-5 py-4">
                                <p class="text-gray-900 uppercase tracking-tighter">${item.prod_name}</p>
                                <p class="text-[10px] text-gray-400 leading-none mt-1 uppercase">${item.variant}</p>
                            </td>
                            <td class="py-4 text-center text-gray-600">${item.quantity}</td>
                            <td class="py-4 text-right text-gray-400">₱${parseFloat(item.price).toLocaleString()}</td>
                            <td class="px-5 py-4 text-right text-gray-900 font-black">₱${subtotal.toLocaleString()}</td>
                        </tr>
                    `;
                });
                content.innerHTML = itemsHtml;
                totalDisplay.innerText = `₱${parseFloat(s.total_with_interest || s.total).toLocaleString(undefined, {minimumFractionDigits: 2})}`;

                // 4. Schedule (if Installment)
                if (s.payment_type === 'Installment' && response.schedule && response.schedule.length > 0) {
                    scheduleWrapper.classList.remove('hidden');
                    let scheduleHtml = '';
                    response.schedule.forEach(row => {
                        const statusCls = row.status === 'Paid' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700';
                        scheduleHtml += `
                            <tr class="text-[10px] font-bold">
                                <td class="px-5 py-3">
                                    <span class="px-2 py-0.5 rounded-full uppercase tracking-widest text-[8px] font-black ${statusCls}">${row.status}</span>
                                </td>
                                <td class="py-3 text-gray-600">${row.due_date ? new Date(row.due_date).toLocaleDateString() : 'N/A'}</td>
                                <td class="py-3 text-right text-gray-900">₱${parseFloat(row.amount_paid).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                                <td class="px-5 py-3 text-right text-gray-400 uppercase tracking-tighter">${row.remarks || '---'}</td>
                            </tr>
                        `;
                    });
                    scheduleContent.innerHTML = scheduleHtml;
                }
            }
        });
}

function closeTxnModal() {
    const modal = document.getElementById('txnDetailModal');
    if (modal) modal.classList.add('hidden');
}

function expandOrderTable() {
    displayLimit = paginationThreshold;
    renderOrdersTable();
}

function showLessOrders() {
    displayLimit = 3;
    currentPage = 1;
    renderOrdersTable();
}

function goToOrderPage(page) {
    currentPage = page;
    renderOrdersTable();
}

function updateSalesTrendChart() {
    const startInput = document.getElementById('reportStartDate');
    const endInput = document.getElementById('reportEndDate');
    
    if(!startInput || !endInput) return;
    
    const start = startInput.value;
    const end = endInput.value;

    fetch(`../include/inc.admin/admin.ctrl.php?action=get_revenue_trend&start=${start}&end=${end}`)
        .then(res => res.json())
        .then(res => {
            if (res.success && window.salesChart) {
                window.salesChart.data.labels = res.labels;
                window.salesChart.data.datasets[0].data = res.data;
                window.salesChart.update();
            }
        });
}

function fetchTopProducts() {
    const filterInput = document.getElementById('topProductsFilter');
    const container = document.getElementById('topProductsContainer');

    if(!filterInput || !container) return;

    const period = filterInput.value;

    // Loading state
    container.innerHTML = `<div class="col-span-3 py-10 text-center text-gray-400 text-xs italic">Updating...</div>`;

    fetch(`../include/inc.admin/admin.ctrl.php?action=get_top_products&period=${period}`)
        .then(res => res.json())
        .then(res => {
            if (res.success && res.data) {
                if (res.data.length === 0) {
                    container.innerHTML = `<div class="col-span-3 py-10 text-center text-gray-400 text-xs italic uppercase">No data for this period</div>`;
                    return;
                }
                container.innerHTML = res.data.map(tp => {
                    const img = tp.variant_image || tp.default_image || 'default-placeholder.png';
                    const path = "../../public/assets/img/furnitures/" + encodeURIComponent(img.trim());
                    return `
                        <div class="group animate-in zoom-in duration-300">
                            <div class="w-16 h-16 sm:w-20 sm:h-20 mx-auto bg-gray-50 rounded-lg mb-3 flex items-center justify-center overflow-hidden border border-gray-100 group-hover:border-red-300 transition shadow-sm">
                                <img src="${path}" alt="${tp.name}" loading="lazy" class="object-contain h-full w-full">
                            </div>
                            <p class="text-[10px] font-bold text-gray-700 truncate px-1">${tp.name}</p>
                            <p class="text-xs font-black text-blue-600 mt-1">${parseInt(tp.total_sold)} <span class="text-[9px] text-gray-400 uppercase">Sold</span></p>
                        </div>
                    `;
                }).join('');
            }
        });
}
