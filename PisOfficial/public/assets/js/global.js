console.log("✅ script.js loaded");

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
    btn.className = `flex-1 py-4 rounded-2xl font-black text-white hover:bg-gray-900 shadow-lg active:scale-95 transition-all duration-300 uppercase text-[10px] tracking-[0.2em] ${btnColorClass}`;

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
    document
      .getElementById(`tabContent${i}`)
      .classList.toggle("hidden", i !== index);
    document
      .getElementById(`tabBtn${i}`)
      .classList.toggle("bg-white", i === index);
    document
      .getElementById(`tabBtn${i}`)
      .classList.toggle("border", i === index);
    document
      .getElementById(`tabBtn${i}`)
      .classList.toggle("border-red-300", i === index);
    document
      .getElementById(`tabBtn${i}`)
      .classList.toggle("text-red-600", i === index);
    document
      .getElementById(`tabBtn${i}`)
      .classList.toggle("font-semibold", i === index);
  });
}

// =========================
// Shared modal state & logic
// =========================
window.formHasUnsavedChanges = false;
window.openModal = function (id) {
  const modal = document.getElementById(id);
  if (!modal) return;
  modal.classList.remove("opacity-0", "pointer-events-none");
  modal.classList.add("opacity-100", "pointer-events-auto");

  const box = modal.querySelector(".modal-box");
  if (box) {
    box.classList.remove("scale-95", "opacity-0");
    box.classList.add("scale-100", "opacity-100");
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
  let current = parseInt(input.value);
  if (current + val >= 1) {
    input.value = current + val;
  }
}

// ==========================================
// POS & INVENTORY SHARED SCRIPTS
// ==========================================

/** * FILTERING LOGIC
 * Merged from both branches to handle POS cards and Inventory tables
 */
let activePosFilter = "all";

function toggleFilterMenu(event) {
  event.stopPropagation();
  const menu = document.getElementById("filterMenu");
  if (menu) menu.classList.toggle("hidden");
}

function selectFilter(value) {
  activePosFilter = value;

  // Trigger Inventory-specific filter if function exists (devRyan)
  if (typeof filterInventory === "function") {
    filterInventory(value);
  }

  // Trigger POS-specific filter (main)
  applyPosFilters();

  const menu = document.getElementById("filterMenu");
  if (menu) menu.classList.add("hidden");
}

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

  const noResults = document.getElementById("noResults");
  if (noResults) {
    if (anyVisible) {
      noResults.classList.add("hidden");
    } else {
      noResults.classList.remove("hidden");
    }
  }
}

// Close menu when clicking outside
window.addEventListener("click", function (event) {
  const menu = document.getElementById("filterMenu");
  if (
    menu &&
    !event.target.closest(".inline-block") &&
    !event.target.closest("#filterMenu")
  ) {
    menu.classList.add("hidden");
  }
});

/**
 * LOGOUT MODAL (devRyan)
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

  // Wait for 1.2s for dramatic effect
  await new Promise((r) => setTimeout(r, 1200));

  // Redirect to logout endpoint
  window.location.href = "../auth/logout.auth.php";
}

/**
 * POS DYNAMIC MODAL & CART (main)
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
                class="hidden peer" ${isChecked} onchange="updateVariantDetails(this)">
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

    document.querySelector('input[name="source"][value="SR"]').checked = true;
    modalState.selectedLocation = "SR";

    if (product.variants[0]) updateVariantStocks(product.variants[0].id);

    document.querySelectorAll('input[name="source"]').forEach((radio) => {
      radio.addEventListener("change", (e) => {
        modalState.selectedLocation = e.target.value;
        document.getElementById("cartQty").value = 1;
      });
    });

    document.getElementById("cartQty").value = 1;
    openModal("addToCartModal");
  } catch (err) {
    console.error("Failed to parse product data", err);
  }
}

function updateVariantDetails(el) {
  const variantId = el.value;
  modalState.selectedVariant = parseInt(variantId);
  document.getElementById("variantDesc").innerText =
    el.getAttribute("data-desc") || "No description.";
  updateVariantStocks(variantId);
  document.getElementById("cartQty").value = 1;
}

function updateVariantStocks(variantId) {
  const s = modalState.stockMap[variantId];
  if (!s) return;
  const srLabel = document
    .querySelector('input[name="source"][value="SR"]')
    .nextElementSibling.querySelector(".text-orange-300, .text-orange-500");
  const whLabel = document
    .querySelector('input[name="source"][value="WH"]')
    .nextElementSibling.querySelector(".text-blue-300, .text-blue-500");
  if (srLabel) srLabel.innerText = s.sr + " Stock";
  if (whLabel) whLabel.innerText = s.wh + " Stock";
}

window.changeQty = function (val) {
  const input = document.getElementById("cartQty");
  let current = parseInt(input.value);
  if (!modalState.selectedVariant || !modalState.selectedLocation) {
    if (current + val >= 1) input.value = current + val;
    return;
  }
  const stocks = modalState.stockMap[modalState.selectedVariant];
  const maxStock = modalState.selectedLocation === "SR" ? stocks.sr : stocks.wh;
  if (val > 0 && current >= maxStock) {
    showCustomAlert("Cannot exceed available stocks.");
    return;
  }
  if (current + val >= 1) input.value = current + val;
};

async function handleAddToCart() {
  const btn = document.querySelector(
    '#addToCartModal button[onclick="handleAddToCart()"]',
  );
  const originalText = btn ? btn.innerText : "Add to Cart";

  const variantId = modalState.selectedVariant;
  const qty = parseInt(document.getElementById("cartQty").value);
  const source = modalState.selectedLocation;
  const stocks = modalState.stockMap[variantId];
  const maxStock = source === "SR" ? stocks.sr : stocks.wh;

  if (qty > maxStock)
    return showCustomAlert(
      "Cannot add more than available stock (" + maxStock + ").",
    );

  // Step 1: Start 'Adding...' state (High Visibility)
  if (btn) {
    btn.disabled = true;
    btn.classList.add("opacity-80", "cursor-not-allowed", "bg-gray-800");

    // Injecting a local style for the spinner (Pure CSS Border Spinner)
    if (!document.getElementById("spinner-style")) {
      const style = document.createElement("style");
      style.id = "spinner-style";
      style.innerHTML = `
              @keyframes spin-pure {
                  to { transform: rotate(360deg); }
              }
              .pure-spinner {
                  width: 18px;
                  height: 18px;
                  border: 3px solid rgba(255,255,255,0.3);
                  border-top-color: #fff;
                  border-radius: 50%;
                  animation: spin-pure 0.8s linear infinite;
              }
          `;
      document.head.appendChild(style);
    }

    btn.innerHTML = `
        <div class="flex items-center justify-center gap-3">
            <div class="pure-spinner"></div>
            <span class="tracking-widest uppercase text-[11px]">Processing...</span>
        </div>`;
  }

  const startTime = Date.now();
  const url =
    (typeof CART_ENDPOINT !== "undefined"
      ? CART_ENDPOINT
      : "../include/inc.admin/admin.ctrl.php") + "?action=add_to_cart";
  const formData = new FormData();
  formData.append("variant_id", variantId);
  formData.append("qty", qty);
  formData.append("source", source);

  try {
    const res = await fetch(url, { method: "POST", body: formData });
    const data = await res.json();

    // Ensure at least 1 second has passed
    const elapsed = Date.now() - startTime;
    if (elapsed < 1000) await new Promise((r) => setTimeout(r, 1000 - elapsed));

    if (data.success) {
      closeModal("addToCartModal");

      // Show Toast
      if (typeof window.showToast === "function") {
        const productName = modalState.product
          ? modalState.product.name
          : "Product";
        window.showToast(
          `${productName} successfully added to the cart`,
          "success",
        );
      } else if (typeof showCustomSuccess === "function") {
        showCustomSuccess("Product added to cart successfully!");
      } else {
        showCustomSuccess("Product added to cart successfully!");
      }

      if (typeof populateOrderSummary === "function") populateOrderSummary();
      if (typeof updateCartBadgeCount === "function") updateCartBadgeCount();
    } else {
      showCustomAlert(data.message || "Failed to add to cart.");
    }
  } catch (err) {
    console.error(err);
    showCustomAlert("A network error occurred.");
  } finally {
    // Re-enable button & Reset appearance
    if (btn) {
      btn.disabled = false;
      btn.classList.remove("opacity-80", "cursor-not-allowed", "bg-gray-800");
      btn.innerText = originalText;
    }
  }
}

document.addEventListener("DOMContentLoaded", () => {
  const searchInput = document.getElementById("searchInput");
  if (searchInput) searchInput.addEventListener("input", applyPosFilters);
});

window.removeCartItem = function (cartId) {
  showCustomConfirm(
    "Are you sure you want to remove this item from your cart?",
    () => {
      executeRemoveCartItem(cartId);
    },
  );
};

async function executeRemoveCartItem(cartId) {
  const url = "../include/global.ctrl.php";
  const formData = new FormData();
  formData.append("action", "delete_cart_item");
  formData.append("cart_id", cartId);

  try {
    const res = await fetch(url, { method: "POST", body: formData });
    const data = await res.json();
    if (data.success) {
      if (typeof updateCartBadgeCount === "function") updateCartBadgeCount();
      if (typeof refreshAndShowTab === "function") {
        refreshAndShowTab(1);
      } else {
        window.location.href = window.location.pathname + "?tab=1";
      }
    } else {
      showCustomAlert(data.message || "Failed to remove item.");
    }
  } catch (err) {
    console.error(err);
  }
}

/**
 * Dynamic Cart Badge Update
 */
window.updateCartBadgeCount = async function () {
  const badges = document.querySelectorAll(".cart-badge");
  if (badges.length === 0) return;

  try {
    const res = await fetch("../include/global.ctrl.php?action=get_cart_items");
    const data = await res.json();
    if (data.success) {
      const count = data.items ? data.items.length : 0;
      badges.forEach((badge) => {
        badge.innerText = count;
        if (count > 0) {
          badge.classList.remove("hidden");
          badge.classList.add("flex");
        } else {
          badge.classList.remove("flex");
          badge.classList.add("hidden");
        }
      });
    }
  } catch (err) {
    console.error("Badge update error:", err);
  }
};

// Initial sync on load
document.addEventListener("DOMContentLoaded", () => {
  window.updateCartBadgeCount();
});

window.updateCartItem = async function (cartId, qty, maxStock = 99999) {
  if (qty < 1) {
    window.removeCartItem(cartId);
    return;
  }

  if (qty > maxStock) {
    showCustomAlert("Cannot exceed available stocks (" + maxStock + ").");
    return;
  }

  // Proceed with the update
  const url = "../include/global.ctrl.php";
  const formData = new FormData();
  formData.append("action", "update_cart_qty");
  formData.append("cart_id", cartId);
  formData.append("qty", qty);

  try {
    const res = await fetch(url, { method: "POST", body: formData });
    const data = await res.json();
    if (data.success) {
      if (typeof updateCartBadgeCount === "function") updateCartBadgeCount();
      if (typeof refreshAndShowTab === "function") {
        refreshAndShowTab(1);
      } else {
        window.location.href = window.location.pathname + "?tab=1";
      }
    } else {
      showCustomAlert(data.message || "Failed to update item quantity.");
    }
  } catch (err) {
    console.error(err);
  }
};
