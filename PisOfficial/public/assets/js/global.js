console.log("✅ global.js loaded");

// Initialize Global Session Data from DOM
(function() {
    const sessionEl = document.getElementById('session-data');
    if (sessionEl) {
        try {
            const data = JSON.parse(sessionEl.getAttribute('data-session'));
            window.SESSION_USER_ID = data.userId;
            window.CART_ENDPOINT = data.cartEndpoint;
        } catch (e) {
            console.error('Failed to parse session data', e);
        }
    }
})();

window.showCustomAlert = function (message) {
  const modal = document.getElementById("customAlertModal");
  const msgDiv = document.getElementById("customAlertMessage");
  if (modal && msgDiv) {
    msgDiv.innerText = message;
    openModal("customAlertModal");
  } else {
    alert(message); // fallback
  }
};

window.closeAlertAndResetSpinners = function () {
  window.closeModal("customAlertModal");

  // Reset all stock adjustment inputs inside the stockModal
  const spinners = document.querySelectorAll(".stock-adj-input");
  spinners.forEach((input) => {
    input.value = 0;
  });

  // Re-sync the live component balances if we are in the warehouse tab
  if (typeof window.syncInventory === "function") {
    window.syncInventory();
  }
};

window.updateCartBadgeCount = async function () {
    try {
        const url = (typeof window.CART_ENDPOINT !== 'undefined' ? window.CART_ENDPOINT : '../include/global.ctrl.php') + "?action=get_cart_count";
        const res = await fetch(url);
        const data = await res.json();
        
        if (data.success) {
            const badges = document.querySelectorAll('.cart-badge');
            badges.forEach(b => {
                b.innerText = data.count;
                if (data.count > 0) {
                    b.classList.remove('hidden');
                    b.classList.add('flex');
                } else {
                    b.classList.remove('flex');
                    b.classList.add('hidden');
                }
            });
        }
    } catch (err) {
        console.error("Failed to update badge", err);
    }
};

window.showCustomConfirm = function (
  message,
  onConfirm,
  btnText = "Confirm",
  btnColorClass = "bg-red-500",
  title = "Confirmation Required",
) {
  const modal = document.getElementById("customConfirmModal");
  const titleDiv = document.getElementById("customConfirmTitle");
  const msgDiv = document.getElementById("customConfirmMessage");
  const btn = document.getElementById("customConfirmBtn");

  if (modal && msgDiv && btn) {
    if (titleDiv) titleDiv.innerText = title;
    msgDiv.innerText = message;
    btn.innerText = btnText;

    // Set the button classes
    btn.className = `w-full inline-flex justify-center rounded-xl border border-transparent shadow-sm px-4 py-3.5 text-sm font-bold text-white hover:bg-gray-900 active:scale-95 transition-all duration-300 ${btnColorClass}`;

    // Clear old listeners by cloning
    const newBtn = btn.cloneNode(true);
    btn.parentNode.replaceChild(newBtn, btn);

    // Re-bind to the new element
    newBtn.addEventListener(
      "click",
      function handleConfirm() {
        window.closeModal("customConfirmModal");
        if (typeof onConfirm === "function") onConfirm();
      },
      { once: true },
    );

    window.openModal("customConfirmModal");
  } else {
    // Fallback to native confirm if DOM elements are missing
    if (confirm(message)) {
      if (typeof onConfirm === "function") onConfirm();
    }
  }
};

window.showCustomSuccess = function (message, onClose) {
  if (typeof window.showToast === "function") {
    window.showToast(message, "success");
    if (onClose) {
      // Slight delay so the user can read the toast before reload/action
      setTimeout(onClose, 1200);
    }
  } else {
    // Fallback if toast script isn't loaded
    alert(message);
    if (onClose) onClose();
  }
};

// =========================
// Shared modal state
// =========================
const modalState = {
  product: null,
  stockMap: null,
  selectedVariant: null,
  selectedLocation: null,
  qty: 1,
};

// =========================
// Tab switching
// =========================
function showTab(index) {
  const tabs = [0, 1];
  tabs.forEach((i) => {
    const content = document.getElementById(`tabContent${i}`);
    const btn = document.getElementById(`tabBtn${i}`);
    
    if (content) content.classList.toggle("hidden", i !== index);
    if (btn) {
      btn.classList.toggle("bg-white", i === index);
      btn.classList.toggle("border", i === index);
      btn.classList.toggle("border-red-300", i === index);
      btn.classList.toggle("text-red-600", i === index);
      btn.classList.toggle("font-semibold", i === index);
      
      // Secondary state visibility for inactive tabs
      if (i !== index) {
          btn.classList.remove("font-semibold", "text-red-600");
          btn.classList.add("font-medium", "text-gray-700");
      }
    }
  });
}
window.showTab = showTab;

// =========================
// Shared modal state & logic
// =========================
window.formHasUnsavedChanges = false;
window.openModal = function (id) {
  const modal = document.getElementById(id);
  if (!modal) {
    console.warn(`Modal with id "${id}" not found.`);
    return;
  }

  // Ensure modal is prepared for transition
  modal.classList.remove("hidden"); // Backup in case hidden class is used
  
  // Trigger reflow for transition
  void modal.offsetWidth;

  modal.classList.remove("opacity-0", "pointer-events-none");
  modal.classList.add("opacity-100", "pointer-events-auto");

  // Show content box
  const content = modal.querySelector(".modal-box");
  if (content) {
    content.classList.remove("scale-95", "opacity-0");
    content.classList.add("scale-100", "opacity-100");
  }

  // Handle specific cases (like form reset) inside specific modals if needed
};

/**
 * Toggles an invisible overlay that blocks all mouse interactions
 * Useful for preventing double-clicks during async processing.
 */
window.toggleInteractionBlocker = function (show) {
  const overlay = document.getElementById("actionOverlay");
  if (!overlay) return;
  if (show) {
    overlay.classList.remove("hidden");
  } else {
    overlay.classList.add("hidden");
  }
};

window.closeModal = function (id) {
  const modal = document.getElementById(id);
  if (!modal) return;
  modal.classList.add("opacity-0", "pointer-events-none");
  modal.classList.remove("opacity-100", "pointer-events-auto");

  const box = modal.querySelector(".modal-box");
  if (box) {
    box.classList.add("scale-95", "opacity-0");
    box.classList.remove("scale-100", "opacity-100");
  }
};

window.closeModalWithCheck = function (modalId, formId) {
  if (window.formHasUnsavedChanges) {
    const discardModal = document.getElementById("discardModal");
    if (discardModal) {
      const confirmBtn = document.getElementById("confirmDiscardBtn");
      if (confirmBtn) {
        const newBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newBtn, confirmBtn);
        newBtn.addEventListener("click", () => {
          window.closeModal("discardModal");
          window.formHasUnsavedChanges = false;
          window.closeModal(modalId);
        });
      }
      window.openModal("discardModal");
    } else {
      if (confirm("You have unsaved changes. Discard them?")) {
        window.formHasUnsavedChanges = false;
        window.closeModal(modalId);
      }
    }
  } else {
    window.closeModal(modalId);
  }
};

// Add this for your quantity buttons
function changeQty(val) {
  const input = document.getElementById("cartQty");
  if (!input) return;
  let current = parseInt(input.value);
  
  // If we have strict stock checking active
  if (modalState.selectedVariant && modalState.selectedLocation) {
      const stocks = modalState.stockMap[modalState.selectedVariant];
      const maxStock = modalState.selectedLocation === "SR" ? stocks.sr : stocks.wh;
      if (val > 0 && current >= maxStock) {
        showCustomAlert("Cannot exceed available stocks.");
        return;
      }
  }

  if (current + val >= 1) {
    input.value = current + val;
  }
}
window.changeQty = changeQty;

// ==========================================
// POS & INVENTORY SHARED SCRIPTS
// ==========================================

/** * FILTERING LOGIC
 */
let activePosFilter = "all";

function toggleFilterMenu(event) {
  if (event) event.stopPropagation();
  const menu = document.getElementById("filterMenu");
  if (menu) menu.classList.toggle("hidden");
}
window.toggleFilterMenu = toggleFilterMenu;

function selectFilter(value) {
  activePosFilter = value;

  // Trigger Inventory-specific filter if function exists
  if (typeof filterInventory === "function") {
    filterInventory(value);
  }

  // Trigger POS-specific filter
  applyPosFilters();

  const menu = document.getElementById("filterMenu");
  if (menu) menu.classList.add("hidden");
}
window.selectFilter = selectFilter;

function applyPosFilters() {
  const searchEl = document.getElementById("searchInput");
  const query = searchEl ? searchEl.value.toLowerCase().trim() : "";
  const cards = document.querySelectorAll(".product-card");
  let anyVisible = false;

  cards.forEach((card) => {
    const name = (card.getAttribute("data-name") || "").toLowerCase();
    const code = (card.getAttribute("data-code") || "").toLowerCase();
    const wh = parseInt(card.getAttribute("data-wh")) || 0;
    const sr = parseInt(card.getAttribute("data-sr")) || 0;

    let visible = true;

    if (query && !name.includes(query) && !code.includes(query)) {
      visible = false;
    }

    if (activePosFilter === "warehouse" && wh <= 0) {
      visible = false;
    } else if (activePosFilter === "showroom" && sr <= 0) {
      visible = false;
    }

    if (visible) {
      card.classList.remove("hidden");
      anyVisible = true;
    } else {
      card.classList.add("hidden");
    }
  });

  const noResults = document.getElementById("noResults") || document.getElementById("posNoResults");
  if (noResults) {
    if (anyVisible) {
      noResults.classList.add("hidden");
    } else {
      noResults.classList.remove("hidden");
    }
  }
}
window.applyPosFilters = applyPosFilters;

/**
 * LOGOUT MODAL
 */
window.toggleLogoutModal = function (show) {
  if (show) {
    window.openModal("logout-modal");
  } else {
    window.closeModal("logout-modal");
  }
};

/**
 * Handle Logout with 1s loading state
 */
window.handleLogout = async function () {
  const btn = document.getElementById("confirmLogoutBtn");
  if (!btn || btn.disabled) return;

  // Check if spinner style exists, if not inject it
  if (!document.getElementById("spinner-style")) {
    const style = document.createElement("style");
    style.id = "spinner-style";
    style.innerHTML = `
            @keyframes spin-pure { to { transform: rotate(360deg); } }
            .pure-spinner {
                width: 14px;
                height: 14px;
                border: 2px solid rgba(255,255,255,0.3);
                border-top-color: #fff;
                border-radius: 50%;
                animation: spin-pure 0.8s linear infinite;
            }
        `;
    document.head.appendChild(style);
  }

  // Start Loading
  btn.disabled = true;
  btn.classList.add("opacity-80", "cursor-not-allowed", "bg-red-800");
  btn.innerHTML = `
        <div class="flex items-center justify-center gap-2">
            <div class="pure-spinner"></div>
            <span class="tracking-widest uppercase text-[10px]">Logging out...</span>
        </div>
    `;

  await new Promise((r) => setTimeout(r, 1200));
  window.location.href = "../auth/logout.auth.php";
}

/**
 * POS DYNAMIC MODAL & CART
 */
function openProductModal(encodedProduct) {
  try {
    const product = JSON.parse(
      decodeURIComponent(encodedProduct.replace(/\+/g, " ")),
    );
    modalState.product = product;

    document.getElementById("modalProductCode").innerText = product.code;
    document.getElementById("modalName").innerText = product.name;
    document.getElementById("modalPrice").innerText =
      "₱" +
      parseFloat(product.price).toLocaleString(undefined, {
        minimumFractionDigits: 2,
      });
    document.getElementById("overallStock").innerText = product.overall;
    document.getElementById("modalMainImg").src = product.image;

    const variantList = document.getElementById("variant-list");
    variantList.innerHTML = "";
    modalState.stockMap = {};

    product.variants.forEach((v, index) => {
      modalState.stockMap[v.id] = { sr: v.sr, wh: v.wh };
      const isChecked = index === 0 ? "checked" : "";
      if (index === 0) modalState.selectedVariant = v.id;

      const html = `
        <label class="cursor-pointer group">
            <input type="radio" name="variant" value="${v.id}"
                data-desc="Location: ${v.loc}"
                class="hidden peer" ${isChecked} data-update-variant-details>
            <div class="p-2.5 border-2 border-gray-100 rounded-2xl peer-checked:border-blue-600 peer-checked:bg-blue-50/30 transition-all flex items-center justify-between shadow-sm hover:border-gray-200">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-white rounded-xl border border-gray-100 p-1.5 shrink-0 shadow-sm">
                        <img src="${product.image}" class="object-contain w-full h-full">
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[11px] font-black text-gray-800 uppercase leading-none tracking-tight">${v.name}</span>
                        <span class="text-[8px] font-bold text-gray-400 uppercase mt-1">Variant</span>
                    </div>
                </div>
                <div class="text-right border-l border-gray-100 pl-3 shrink-0">
                    <span class="block text-[11px] font-black text-gray-900 leading-none">${v.sr + v.wh}</span>
                    <span class="text-[7px] font-black text-blue-500 uppercase tracking-tighter">Stocks</span>
                </div>
            </div>
        </label>
      `;
      variantList.insertAdjacentHTML("beforeend", html);
    });

    const srRadio = document.querySelector('input[name="source"][value="SR"]');
    if (srRadio) srRadio.checked = true;
    modalState.selectedLocation = "SR";

    if (product.variants[0]) updateVariantStocks(product.variants[0].id);

    document.querySelectorAll('input[name="source"]').forEach((radio) => {
        // We handle this via delegation in DOMContentLoaded
    });

    document.getElementById("cartQty").value = 1;
    openModal("addToCartModal");
  } catch (err) {
    console.error("Failed to parse product data", err);
  }
}
window.openProductModal = openProductModal;

function updateVariantDetails(el) {
  const variantId = el.value;
  modalState.selectedVariant = parseInt(variantId);
  const descEl = document.getElementById("variantDesc");
  if (descEl) descEl.innerText = el.getAttribute("data-desc") || "No description.";
  updateVariantStocks(variantId);
  const qtyInput = document.getElementById("cartQty");
  if (qtyInput) qtyInput.value = 1;
}
window.updateVariantDetails = updateVariantDetails;

function updateVariantStocks(variantId) {
  const s = modalState.stockMap[variantId];
  if (!s) return;
  const srRadio = document.querySelector('input[name="source"][value="SR"]');
  const whRadio = document.querySelector('input[name="source"][value="WH"]');
  
  if (srRadio) {
      const srLabel = srRadio.nextElementSibling.querySelector(".text-orange-300, .text-orange-500");
      if (srLabel) srLabel.innerText = s.sr + " Stock";
  }
  if (whRadio) {
      const whLabel = whRadio.nextElementSibling.querySelector(".text-blue-300, .text-blue-500");
      if (whLabel) whLabel.innerText = s.wh + " Stock";
  }
}
window.updateVariantStocks = updateVariantStocks;

// ==========================================
// GLOBAL EVENT DELEGATION
// ==========================================
document.addEventListener('click', (e) => {
    const target = e.target;

    // Logout Trigger (Header)
    if (target.closest('.logout-trigger') || target.closest('[data-open-modal="logout-modal"]')) {
        e.preventDefault();
        window.toggleLogoutModal(true);
        return;
    }

    // Modal Operations (Open)
    const openBtn = target.closest('[data-open-modal]') || target.closest('[data-modal-open]');
    if (openBtn) {
        const modalId = openBtn.getAttribute('data-open-modal') || openBtn.getAttribute('data-modal-open');
        window.openModal(modalId);
        return;
    }

    // Modal Operations (Close)
    const closeBtn = target.closest('[data-close-modal]') || 
                     target.closest('[data-modal-close]') || 
                     target.closest('[data-modal-close-req]') || 
                     target.closest('[data-modal-close-check]');
    if (closeBtn) {
        const modalId = closeBtn.getAttribute('data-close-modal') || 
                        closeBtn.getAttribute('data-modal-close') || 
                        closeBtn.getAttribute('data-modal-close-req') || 
                        closeBtn.getAttribute('data-modal-close-check');
        window.closeModal(modalId);
        return;
    }

    // Logout "Stay" (Close Modal) - Backup for class-based
    if (target.closest('.logout-close')) {
        e.preventDefault();
        window.toggleLogoutModal(false);
        return;
    }

    // Logout "Confirm" (Action)
    if (target.closest('#confirmLogoutBtn') || target.closest('[data-action="confirm-logout"]')) {
        e.preventDefault();
        window.handleLogout();
        return;
    }

    // Filter Menu
    if (target.closest('[data-toggle-filter-menu]')) {
        toggleFilterMenu(e);
        return;
    }
    const filterBtn = target.closest('[data-select-filter]');
    if (filterBtn) {
        selectFilter(filterBtn.getAttribute('data-select-filter'));
        return;
    }

    // Quantity Controls
    const qtyBtn = target.closest('[data-change-qty]');
    if (qtyBtn) {
        changeQty(parseInt(qtyBtn.getAttribute('data-change-qty')));
        return;
    }
});

document.addEventListener('change', (e) => {
    const target = e.target;

    // Source selection in product modal
    if (target.name === 'source' && ['SR', 'WH'].includes(target.value)) {
        modalState.selectedLocation = target.value;
        const qtyInput = document.getElementById("cartQty");
        if (qtyInput) qtyInput.value = 1;
        return;
    }

    // Variant Selection
    if (target.name === 'variant' && target.hasAttribute('data-update-variant-details')) {
        updateVariantDetails(target);
        return;
    }
});

document.addEventListener("DOMContentLoaded", () => {
  const searchInput = document.getElementById("searchInput");
  if (searchInput) searchInput.addEventListener("input", applyPosFilters);

  // Close filter menu when clicking outside
  window.addEventListener("click", function (event) {
    const menu = document.getElementById("filterMenu");
    if (menu && !event.target.closest(".inline-block") && !event.target.closest("#filterMenu") && !event.target.closest('[data-toggle-filter-menu]')) {
      menu.classList.add("hidden");
    }
  });
});
