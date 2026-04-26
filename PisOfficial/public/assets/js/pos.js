/**
 * POS Page Logic
 */

document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('pos-container');
    if (!container) return;

    const rawData = container.getAttribute('data-pos');
    if (rawData) {
        try {
            const pageData = JSON.parse(rawData);
            const activeTab = pageData.activeTab;
            if (typeof window.showTab === 'function') {
                window.showTab(activeTab);
            }
        } catch (e) {
            console.error('Error parsing POS data-pos:', e);
        }
    }


    // Event Delegation for All POS Interactions
    container.addEventListener('click', function(e) {
        // 1. Tab Switching
        const tabBtn = e.target.closest('[data-tab]');
        if (tabBtn) {
            const index = parseInt(tabBtn.getAttribute('data-tab'));
            refreshAndShowTab(index);
            return;
        }

        // 2. Add to Cart (Catalog)
        const btn = e.target.closest('.add-to-cart-btn');
        if (btn) {
            const productData = btn.getAttribute('data-product');
            if (productData && typeof openProductModal === 'function') {
                openProductModal(encodeURIComponent(productData));
            }
            return;
        }

        // 3. Filter Menu Toggle
        const filterBtn = e.target.closest('[data-filter-toggle]');
        if (filterBtn) {
            e.stopPropagation();
            const menu = document.getElementById("filterMenu");
            if (menu) menu.classList.toggle("hidden");
            return;
        }

        // 4. Select Filter
        const filterItem = e.target.closest('[data-filter]');
        if (filterItem) {
            const val = filterItem.getAttribute('data-filter');
            if (typeof selectFilter === 'function') {
                selectFilter(val);
            }
            return;
        }

        // 5. Modal Close (General)
        const closeBtn = e.target.closest('[data-modal-close]');
        if (closeBtn) {
            const modalId = closeBtn.getAttribute('data-modal-close');
            if (typeof closeModal === 'function') {
                closeModal(modalId);
            }
            return;
        }

        // 6. Modal Close with Check
        const closeCheckBtn = e.target.closest('[data-modal-close-check]');
        if (closeCheckBtn) {
            const modalId = closeCheckBtn.getAttribute('data-modal-close-check');
            const formId = closeCheckBtn.getAttribute('data-form-id');
            if (typeof closeModalWithCheck === 'function') {
                closeModalWithCheck(modalId, formId);
            }
            return;
        }

        // 7. Quantity Adjustment (Add to Cart Modal)
        const qtyAdj = e.target.closest('[data-qty-adj]');
        if (qtyAdj) {
            const val = parseInt(qtyAdj.getAttribute('data-qty-adj'));
            if (typeof changeQty === 'function') {
                changeQty(val);
            }
            return;
        }

        // 8. Add to Cart Submit
        const subCart = e.target.closest('[data-cart-submit]');
        if (subCart) {
            if (typeof handleAddToCart === 'function') {
                handleAddToCart();
            }
            return;
        }

        // 9. Update Cart Item Qty (Cart Tab)
        const cartQtyAdj = e.target.closest('[data-cart-qty-adj]');
        if (cartQtyAdj) {
            const cartId = cartQtyAdj.getAttribute('data-cart-id');
            const newQty = parseInt(cartQtyAdj.getAttribute('data-new-qty'));
            const maxStock = parseInt(cartQtyAdj.getAttribute('data-max-stock'));
            if (typeof updateCartItem === 'function') {
                updateCartItem(cartId, newQty, maxStock);
            }
            return;
        }

        // 10. Remove Cart Item
        const removeBtn = e.target.closest('[data-cart-remove]');
        if (removeBtn) {
            const cartId = removeBtn.getAttribute('data-cart-remove');
            if (typeof removeCartItem === 'function') {
                removeCartItem(cartId);
            }
            return;
        }

        // 11. Open Checkout Modal
        const processBtn = e.target.closest('[data-open-checkout]');
        if (processBtn) {
            const modalId = processBtn.getAttribute('data-open-checkout');
            if (typeof openProceedModal === 'function') {
                openProceedModal(modalId);
            }
            return;
        }

        // 12. Complete Sale
        const completeBtn = e.target.closest('[data-complete-sale]');
        if (completeBtn) {
            if (typeof completeSale === 'function') {
                completeSale();
            }
            return;
        }
    });

    // Handle Input/Change Events for Checkout Form
    container.addEventListener('input', function(e) {
        if (e.target.id === 'clientName') {
            if (typeof handleCustomerSearch === 'function') {
                handleCustomerSearch(e.target.value);
            }
        }
        if (e.target.id === 'interestRate' || e.target.id === 'amountPaid') {
            if (typeof calculateInstallment === 'function') {
                calculateInstallment();
            }
        }
    });

    container.addEventListener('change', function(e) {
        if (e.target.id === 'clientType') {
            if (typeof toggleGovFields === 'function') {
                toggleGovFields(e.target.value);
            }
        }
        if (e.target.id === 'shippingMode') {
            if (typeof toggleAddress === 'function') {
                toggleAddress(e.target.value);
            }
        }
        if (e.target.id === 'transactionType') {
            if (typeof toggleInstallmentView === 'function') {
                toggleInstallmentView(e.target.value);
            }
        }
        if (e.target.name === 'variant') {
            if (typeof updateVariantDetails === 'function') {
                updateVariantDetails(e.target);
            }
        }
    });
});

/**
 * Handle tab switching with server sync
 * @param {number} tabIndex 
 */
async function refreshAndShowTab(tabIndex) {
    // 1. Instantly switch the UI tabs for a seamless feel
    if (typeof showTab === 'function') {
        showTab(tabIndex);
    }
    window.history.replaceState({}, '', window.location.pathname + '?tab=' + tabIndex);

    // Update badge too
    if (typeof updateCartBadgeCount === 'function') {
        updateCartBadgeCount();
    }

    // 2. If we are switching to the cart, silently fetch the freshest server cart data 
    if (tabIndex === 1) {
        try {
            const response = await fetch(window.location.pathname + '?tab=1');
            const html = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');

            // Replace the cart container content silently
            const newTab1 = doc.getElementById('tabContent1');
            const currentTab1 = document.getElementById('tabContent1');
            if (newTab1 && currentTab1) {
                currentTab1.innerHTML = newTab1.innerHTML;
            }
        } catch (e) {
            console.error('Failed to sync latest cart.', e);
        }
    }
}

// Ensure globally accessible if needed for inline onclick (though we should move those to listeners too)
window.refreshAndShowTab = refreshAndShowTab;
