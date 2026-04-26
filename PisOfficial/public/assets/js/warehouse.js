/**
 * Warehouse Module - General Utilities
 * Shared JS logic for the warehouse dashboard, inventory, and reports.
 */

document.addEventListener('DOMContentLoaded', () => {
    console.log('[Warehouse] Module Initialized');
    initInventory();
    initWarehouseEventDelegation();
});

/**
 * Initialize Inventory UI Logic
 */
function initInventory() {
    const container = document.getElementById('inventory-container');
    if (!container) return;

    // 1. Search Functionality
    const searchInput = document.getElementById('searchInput');
    const cards = document.querySelectorAll('.product-card');
    const noResults = document.getElementById('noResults');

    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const term = e.target.value.toLowerCase().trim();
            let hasVisible = false;

            cards.forEach(card => {
                const name = card.getAttribute('data-name')?.toLowerCase() || '';
                const code = card.getAttribute('data-code')?.toLowerCase() || '';
                
                if (name.includes(term) || code.includes(term)) {
                    card.classList.remove('hidden');
                    hasVisible = true;
                } else {
                    card.classList.add('hidden');
                }
            });

            if (noResults) {
                noResults.classList.toggle('hidden', hasVisible);
            }
        });
    }

    // Global Exposure for internal use if needed, but delegation preferred
    window.resetSearch = function() {
        if (searchInput) {
            searchInput.value = '';
            cards.forEach(card => card.classList.remove('hidden'));
            if (noResults) noResults.classList.add('hidden');
        }
    };

    window.openViewModal = function(code) {
        const formData = new FormData();
        formData.append('action', 'get_product_details');
        formData.append('code', code);

        // UI Feedback (Loading state if needed)
        console.log('[Warehouse] Fetching details for:', code);

        fetch('../include/inc.admin/admin.ctrl.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const p = data.product;
                    const viewCodeHeader = document.getElementById('viewCodeHeader');
                    const viewName = document.getElementById('viewName');
                    const viewCategory = document.getElementById('viewCategory');
                    const viewPrice = document.getElementById('viewPrice');
                    const viewDescription = document.getElementById('viewDescription');
                    const viewImagePreview = document.getElementById('viewImagePreview');

                    if(viewCodeHeader) viewCodeHeader.innerText = p.code;
                    if(viewName) viewName.innerText = p.name;
                    if(viewCategory) viewCategory.innerText = p.category || 'N/A';
                    if(viewPrice) viewPrice.innerText = '₱' + parseFloat(p.price).toLocaleString(undefined, { minimumFractionDigits: 2 });
                    if(viewDescription) viewDescription.innerText = p.description || 'No description available.';

                    const img = p.default_image || 'default.png';
                    if(viewImagePreview) viewImagePreview.src = `../../public/assets/img/furnitures/${img}`;

                    // Variants
                    const container = document.getElementById('viewVariantsContainer');
                    if(container) {
                        container.innerHTML = '';
                        if (p.variants && p.variants.length > 0) {
                            p.variants.forEach(v => {
                                const div = document.createElement('div');
                                div.className = 'flex items-center gap-4 p-4 bg-gray-50 rounded-2xl border border-gray-100 shadow-sm';
                                div.innerHTML = `
                                    <div class="w-12 h-12 bg-white rounded-xl border border-gray-200 flex items-center justify-center shrink-0">
                                        <img src="../../public/assets/img/furnitures/${v.variant_image || 'default.png'}" loading="lazy" class="object-cover w-full h-full rounded-xl" onerror="this.src='../../public/assets/img/favIcon.png'">
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-0.5 leading-none">Variant Name</p>
                                        <p class="font-bold text-gray-800 text-sm">${v.variant}</p>
                                    </div>
                                    <div class="text-right flex gap-3 border-l border-gray-100 pl-3">
                                        <div>
                                            <p class="text-[9px] font-black text-blue-500 uppercase leading-none mb-1">WH</p>
                                            <p class="font-black text-blue-600 text-sm tracking-tighter">${v.v_wh || 0}</p>
                                        </div>
                                    </div>
                                `;
                                container.appendChild(div);
                            });
                        } else {
                            container.innerHTML = '<p class="text-sm text-gray-400 italic">No variants available.</p>';
                        }
                    }

                    // Components
                    const compContainer = document.getElementById('viewComponentsContainer');
                    if(compContainer) {
                        compContainer.innerHTML = '';
                        if (p.components && p.components.length > 0) {
                            p.components.forEach(c => {
                                const div = document.createElement('div');
                                div.className = 'flex justify-between items-center p-3 bg-gray-50 rounded-xl border border-gray-100 shadow-sm transition-all hover:bg-white';
                                div.innerHTML = `
                                    <div>
                                        <p class="text-xs font-bold text-gray-800">${c.component_name}</p>
                                        <p class="text-[9px] text-gray-400 font-bold uppercase tracking-tighter mt-0.5">Loc: ${c.location || 'N/A'}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-[9px] font-bold text-gray-500 uppercase tracking-widest mb-0.5">Need</p>
                                        <p class="text-sm font-black text-gray-900">${c.qty_needed}</p>
                                    </div>
                                `;
                                compContainer.appendChild(div);
                            });
                        } else {
                            compContainer.innerHTML = '<p class="text-sm text-gray-400 italic">No component recipe established.</p>';
                        }
                    }

                    openWhModal('viewModal');
                }
            })
            .catch(err => console.error('[Warehouse] View Modal Error:', err));
    };

    window.openWhModal = function(id) {
        const modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.remove("opacity-0", "pointer-events-none", "hidden");
        modal.classList.add("opacity-100", "pointer-events-auto", "flex");
    };

    window.closeWhModal = function() {
        const modal = document.getElementById('viewModal');
        if (!modal) return;
        modal.classList.add("opacity-0", "pointer-events-none", "hidden");
        modal.classList.remove("opacity-100", "pointer-events-auto", "flex");
    };
}

/**
 * Centralized Event Delegation for Warehouse Module
 */
function initWarehouseEventDelegation() {
    document.body.addEventListener('click', (e) => {
        // 1. Open View Product Modal
        const card = e.target.closest('[data-open-view-modal]');
        if (card) {
            const code = card.getAttribute('data-open-view-modal');
            if (typeof window.openViewModal === 'function') {
                window.openViewModal(code);
            }
            return;
        }

        // 2. Reset Search
        if (e.target.closest('[data-reset-search]')) {
            if (typeof window.resetSearch === 'function') {
                window.resetSearch();
            }
            return;
        }

        // 3. Close Modal
        if (e.target.closest('[data-close-wh-modal]')) {
            if (typeof window.closeWhModal === 'function') {
                window.closeWhModal();
            }
            return;
        }
    });

    // Handle clicking outside modal to close
    document.body.addEventListener('click', (e) => {
        if (e.target.classList.contains('bg-black/50') && e.target.parentElement.id === 'viewModal') {
            if (typeof window.closeWhModal === 'function') {
                window.closeWhModal();
            }
        }
    });
}
