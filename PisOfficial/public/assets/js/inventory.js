// Make this a global variable so the selectFilter function can access it
let currentFilter = "all";

document.addEventListener("DOMContentLoaded", function () {
  const searchInput = document.getElementById("searchInput");
  const cards = document.querySelectorAll(".card-style");
  const noResults = document.getElementById("noResults");

  // This is the main function that handles filtering
  window.filterInventory = function () {
    const searchTerm = searchInput.value.toLowerCase().trim();
    let visibleCount = 0;

    cards.forEach((card) => {
      const productName = card.querySelector("h1").textContent.toLowerCase();
      const productCode = card.querySelector("h2").textContent.toLowerCase();
      const productDesc = card.querySelector("p")
        ? card.querySelector("p").textContent.toLowerCase()
        : "";

      const whEl = card.querySelector(".loc-wh");
      const srEl = card.querySelector(".loc-sr");

      const whStock = whEl ? (parseInt(whEl.textContent.replace(/[^\d]/g, "")) || 0) : 0;
      const srStock = srEl ? (parseInt(srEl.textContent.replace(/[^\d]/g, "")) || 0) : 0;

      // Search Logic
      const matchesSearch =
        productName.includes(searchTerm) ||
        productCode.includes(searchTerm) ||
        productDesc.includes(searchTerm);

      // Location Filter Logic (Using the global currentFilter)
      let matchesLocation = true;
      if (currentFilter === "warehouse") {
        matchesLocation = whStock > 0;
      } else if (currentFilter === "showroom") {
        matchesLocation = srStock > 0;
      }

      // Execution
      if (matchesSearch && matchesLocation) {
        card.style.display = "flex";
        visibleCount++;
      } else {
        card.style.display = "none";
      }
    });

    // Show/Hide No Results
    if (visibleCount === 0) {
      if (noResults) {
          noResults.classList.remove("hidden");
          noResults.classList.add("flex");
      }
    } else {
      if (noResults) {
          noResults.classList.add("hidden");
          noResults.classList.remove("flex");
      }
    }
  };

  // Listen to search input
  if (searchInput) searchInput.addEventListener("input", filterInventory);

  // Add change tracker for forms
  const forms = document.querySelectorAll('form');
  forms.forEach(form => {
      form.addEventListener("input", () => {
          window.formHasUnsavedChanges = true;
      });
      form.addEventListener("change", () => {
          window.formHasUnsavedChanges = true;
      });
  });

  // --- Centralized Event Delegation ---
  document.body.addEventListener('click', (e) => {
      // Toggle Filter Menu
      if (e.target.closest('[data-toggle-filter-menu]')) {
          e.stopPropagation();
          const menu = document.getElementById('filterMenu');
          if (menu) menu.classList.toggle('hidden');
          return;
      }

      // Select Filter
      const filterBtn = e.target.closest('[data-select-filter]');
      if (filterBtn) {
          const val = filterBtn.getAttribute('data-select-filter');
          window.selectFilter(val);
          return;
      }

      // Reset Filters
      if (e.target.closest('[data-reset-filters]')) {
          window.resetFilters();
          return;
      }

      // Open Modal
      const openModalBtn = e.target.closest('[data-open-modal]');
      if (openModalBtn) {
          const target = openModalBtn.getAttribute('data-open-modal');
          if (typeof window.openModal === 'function') window.openModal(target);
          return;
      }

      // Close Modal (general)
      const closeBtn = e.target.closest('[data-close-modal]');
      if (closeBtn) {
          window.closeModal(closeBtn.getAttribute('data-close-modal'));
          return;
      }

      // Close Modal with Check
      const closeCheckBtn = e.target.closest('[data-close-modal-check]');
      if (closeCheckBtn) {
          const modalId = closeCheckBtn.getAttribute('data-close-modal-check');
          const formId = closeCheckBtn.getAttribute('data-form-id');
          if (typeof window.closeModalWithCheck === 'function') window.closeModalWithCheck(modalId, formId);
          return;
      }

      // Open Stock Modal
      const stockBtn = e.target.closest('[data-open-stock-modal]');
      if (stockBtn) {
          const code = stockBtn.getAttribute('data-open-stock-modal');
          if (typeof window.openStockModal === 'function') window.openStockModal(code);
          return;
      }

      // Open Edit Modal
      const editBtn = e.target.closest('[data-open-edit-modal]');
      if (editBtn) {
          const code = editBtn.getAttribute('data-open-edit-modal');
          if (typeof window.openEditModal === 'function') window.openEditModal(code);
          return;
      }

      // Open Delete Modal
      const deleteBtn = e.target.closest('[data-open-delete-modal]');
      if (deleteBtn) {
          const code = deleteBtn.getAttribute('data-open-delete-modal');
          const name = deleteBtn.getAttribute('data-product-name');
          if (typeof window.openDeleteModal === 'function') window.openDeleteModal(code, name);
          return;
      }

      // Add Component Row (New/Edit)
      if (e.target.closest('[data-add-comp-row]')) {
          window.addComponentRow();
          return;
      }
      if (e.target.closest('[data-add-edit-comp-row]')) {
          window.addEditComponentRow();
          return;
      }

      // Add Variant Row (New/Edit)
      if (e.target.closest('[data-add-variant-row]')) {
          window.addVariantRow();
          return;
      }
      if (e.target.closest('[data-add-edit-variant-row]')) {
          window.addEditVariantRow();
          return;
      }

      // Handle Edit Save
      if (e.target.closest('[data-save-edit]')) {
          window.handleEditSave();
          return;
      }
      
      // Remove Row
      const removeRowBtn = e.target.closest('[data-remove-row]');
      if (removeRowBtn) {
          const type = removeRowBtn.getAttribute('data-remove-row');
          const name = removeRowBtn.getAttribute('data-row-name') || '';
          window.confirmRemoveRow(removeRowBtn, type, name);
          return;
      }

      // Close menu when clicking outside
      if (!e.target.closest('.inline-block')) {
          const menu = document.getElementById("filterMenu");
          if (menu) menu.classList.add("hidden");
      }
  });

  document.body.addEventListener('change', (e) => {
      // Image Preview (Main)
      if (e.target.matches('[data-preview-image]')) {
          const targetId = e.target.getAttribute('data-preview-image');
          window.previewImage(e.target, targetId);
          return;
      }

      // Variant Image Preview (New)
      if (e.target.matches('[data-preview-variant-image]')) {
          const index = e.target.getAttribute('data-preview-variant-image');
          window.previewVariantImage(e.target, index);
          return;
      }

      // Variant Image Preview (Edit)
      if (e.target.matches('[data-preview-edit-variant-image]')) {
          const index = e.target.getAttribute('data-preview-edit-variant-image');
          window.previewEditVariantImage(e.target, index);
          return;
      }

      // Sale Toggle
      if (e.target.matches('[data-sale-toggle]')) {
          window.handleSaleToggle(e.target);
          return;
      }
  });
});

window.selectFilter = function(type) {
  // 1. Close the menu
  const menu = document.getElementById('filterMenu');
  if (menu) menu.classList.add('hidden');

  // 2. Update global filter
  currentFilter = type;

  // 3. Update all cards quantity and tags visibility
  const overallQtyElements = document.querySelectorAll('.overall-qty');
  overallQtyElements.forEach(el => {
      const whRaw = parseInt(el.getAttribute('data-wh')) || 0;
      const srRaw = parseInt(el.getAttribute('data-sr')) || 0;
      const cardRoot = el.closest('.card-style');
      if (!cardRoot) return;

      if (type === 'warehouse') {
          el.textContent = whRaw;
          cardRoot.querySelectorAll('.loc-wh').forEach(wh => wh.style.display = 'inline-flex');
          cardRoot.querySelectorAll('.loc-sr').forEach(sr => sr.style.display = 'none');
      } else if (type === 'showroom') {
          el.textContent = srRaw;
          cardRoot.querySelectorAll('.loc-wh').forEach(wh => wh.style.display = 'none');
          cardRoot.querySelectorAll('.loc-sr').forEach(sr => sr.style.display = 'inline-flex');
      } else {
          el.textContent = whRaw + srRaw;
          cardRoot.querySelectorAll('.loc-wh').forEach(wh => wh.style.display = 'inline-flex');
          cardRoot.querySelectorAll('.loc-sr').forEach(sr => sr.style.display = 'inline-flex');
      }
  });

  // 4. Call card-level filter
  if (typeof filterInventory === "function") {
    filterInventory();
  }
};


window.resetFilters = function () {
  const searchInput = document.getElementById("searchInput");
  if (searchInput) searchInput.value = "";
  currentFilter = "all";
  if (typeof filterInventory === "function") {
    filterInventory();
  }
};

// --- Add Product JS Logic ---

window.previewImage = function (input, previewId) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function (e) {
      const preview = document.getElementById(previewId);
      if (preview) {
        preview.src = e.target.result;
        preview.classList.remove("hidden");

        // Hide placeholder icon if present
        if (previewId === "mainImagePreview") {
          const icon = document.getElementById("placeholderIcon");
          if (icon) icon.classList.add("hidden");
        }
      }
    };
    reader.readAsDataURL(input.files[0]);
  }
};

window.previewVariantImage = function (input, index) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function (e) {
      const preview = document.getElementById("v-prev-" + index);
      const svg = document.getElementById("v-svg-" + index);
      if (preview) {
        preview.src = e.target.result;
        preview.classList.remove("hidden");
      }
      if (svg) {
        svg.classList.add("hidden");
      }
    };
    reader.readAsDataURL(input.files[0]);
  }
};

// --- Confirmation utility for row removal ---
window.confirmRemoveRow = function(btn, type, name = '') {
    const message = name 
        ? `Are you sure you want to remove the ${type} "${name}"?` 
        : `Are you sure you want to remove this ${type}?`;
    
    if (typeof window.showCustomConfirm === "function") {
        const title = type === 'variant' ? 'Remove Variant' : 'Remove Component';
        window.showCustomConfirm(message, () => {
            const row = btn.closest('.group') || btn.parentElement;
            if (row) row.remove();
            window.formHasUnsavedChanges = true;
        }, "Remove", "bg-red-500", title);
    } else {
        if (confirm(message)) {
            const row = btn.closest('.group') || btn.parentElement;
            if (row) row.remove();
            window.formHasUnsavedChanges = true;
        }
    }
};

window.addComponentRow = function (containerId = "componentsContainer") {
  const container = document.getElementById(containerId);
  if (!container) return;

  window.formHasUnsavedChanges = true;

  const html = `
        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-2xl group border border-transparent hover:border-gray-200 transition-all">
            <div class="flex-1">
                <input type="text" name="comp_names[]" list="compSuggestion" placeholder="Search or type part..." class="w-full bg-transparent font-bold text-gray-800 outline-none text-sm" required>
                <div class="flex items-center gap-2 mt-2 border border-gray-200 rounded-lg px-3 py-1 bg-white w-full">
                    <span class="text-[10px] font-black text-blue-500 uppercase tracking-widest shrink-0">LOC:</span>
                    <input type="text" name="comp_locs[]" placeholder="Aisle 0-0" class="w-full bg-transparent outline-none text-[11px] font-bold uppercase text-gray-700" value="" required>
                </div>
            </div>
            <div class="flex items-center gap-2 px-2 border-l border-gray-200">
                <span class="text-[10px] font-bold text-gray-400 uppercase">Qty</span>
                <input type="number" name="comp_qtys[]" placeholder="0" class="w-10 bg-white border border-gray-200 rounded-lg text-center font-bold text-sm py-1 outline-none" required>
            </div>
            <button type="button" data-remove-row="component" class="text-gray-300 hover:text-red-500 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
            </button>
        </div>
    `;
  container.insertAdjacentHTML("beforeend", html);
};

let variantCounter = 1;
window.addVariantRow = function (containerId = "variantsContainer") {
  const container = document.getElementById(containerId);
  if (!container) return;

  window.formHasUnsavedChanges = true;

  const index = variantCounter++;
  const html = `
        <div class="flex items-center gap-4 p-3 bg-gray-50 rounded-2xl group border border-transparent hover:border-gray-200 transition-all">
            <div class="w-14 h-14 bg-white rounded-xl border border-gray-200 flex items-center justify-center shrink-0 relative">
                <img id="v-prev-${index}" class="hidden object-cover w-full h-full rounded-xl">
                <svg id="v-svg-${index}" class="w-4 h-4 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
                <label class="absolute inset-0 bg-black/40 opacity-0 hover:opacity-100 flex items-center justify-center cursor-pointer rounded-xl transition-all">
                    <input type="file" name="variant_imgs[]" data-preview-variant-image="${index}" accept=".jpg,.jpeg,.png" class="hidden" required>
                    <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M12 4v16m8-8H4" stroke-width="2" stroke-linecap="round"></path>
                    </svg>
                </label>
            </div>
            <div class="flex-1">
                <p class="text-[9px] font-black text-gray-400 uppercase mb-0.5 tracking-widest">Variant Name</p>
                <input type="text" name="variant_names[]" placeholder="e.g. Matte Black" class="w-full bg-transparent font-bold text-gray-800 outline-none text-sm" required>
            </div>
            <div class="w-20 border-l border-gray-200 pl-3">
                <p class="text-[9px] font-black text-red-500 uppercase mb-0.5 tracking-widest">Low Stock</p>
                <input type="number" name="variant_low_stocks[]" placeholder="10" class="w-full bg-transparent font-bold text-red-600 outline-none text-sm" required>
            </div>
            <button type="button" data-remove-row="variant" class="text-gray-300 hover:text-red-500 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
            </button>
        </div>
    `;
  container.insertAdjacentHTML("beforeend", html);
};

window.saveNewProduct = async function () {
  const form = document.getElementById("addProductForm");
  if (!form) return;

  if (!form.reportValidity()) {
    return;
  }

  const formData = new FormData(form);
  formData.append("action", "add_product");

  try {
    const response = await fetch("../include/inc.admin/admin.ctrl.php", {
      method: "POST",
      body: formData,
    });

    let result = await response.json();

    if (result && result.success) {
      window.formHasUnsavedChanges = false;
      if (typeof window.closeModal === "function") {
          window.closeModal("addProductModal");
      }
      
      setTimeout(() => {
          if (typeof window.showCustomSuccess === "function") {
            window.showCustomSuccess("Product successfully added!", () => {
              window.location.reload();
            });
          }
      }, 300);
    } else {
      if (typeof window.showCustomAlert === "function") {
        window.showCustomAlert("Error: " + (result.message || "Failed to add product."));
      }
    }
  } catch (error) {
    console.error("Error adding product:", error);
  }
};

// --- Delete Product JS Logic ---

let productToDeleteCode = null;

window.openDeleteModal = function (code, name) {
  productToDeleteCode = code;
  const nameEl = document.getElementById("deleteProductName");
  if (nameEl) {
    nameEl.textContent = name;
  }

  if (typeof window.openModal === "function") window.openModal("deleteModal");
};

window.closeDeleteModal = function () {
  productToDeleteCode = null;
  if (typeof window.closeModal === "function") window.closeModal("deleteModal");
};

window.confirmDeleteProduct = async function () {
  if (!productToDeleteCode) return;

  const formData = new FormData();
  formData.append("action", "delete_product");
  formData.append("code", productToDeleteCode);

  try {
    const response = await fetch("../include/inc.admin/admin.ctrl.php", {
      method: "POST",
      body: formData,
    });

    let result = await response.json();

    if (result && result.success) {
      closeDeleteModal();
      setTimeout(() => {
          if (typeof window.showCustomSuccess === "function") {
            window.showCustomSuccess("Product deleted successfully!", () => {
              window.location.reload();
            });
          }
      }, 300);
    } else {
      if (typeof window.showCustomAlert === "function") {
        window.showCustomAlert("Error: " + (result.message || "Failed to delete product."));
      }
    }
  } catch (error) {
    console.error("Error deleting product:", error);
  }
};

// --- Edit Product JS Logic ---

window.openEditModal = async function(code) {
  const formData = new FormData();
  formData.append("action", "get_product_details");
  formData.append("code", code);

  window.formHasUnsavedChanges = false; // Reset before loading

  try {
    const response = await fetch("../include/inc.admin/admin.ctrl.php", {
      method: "POST",
      body: formData,
    });
    const result = await response.json();
    if (result && result.success) {
      const p = result.product;
      
      document.getElementById('editProdId').value = p.prod_id || '';
      document.getElementById('editOldCode').value = p.code;
      const headerCode = document.getElementById('editCodeHeader');
      if (headerCode) headerCode.textContent = p.code;
      
      document.getElementById('editName').value = p.name || '';
      document.getElementById('editCode').value = p.code || '';
      document.getElementById('editPrice').value = p.price || 0;
      document.getElementById('editCategory').value = p.category || '';
      document.getElementById('editDescription').value = p.description || '';
      
      const saleToggle = document.getElementById('editSaleToggle');
      const discountInput = document.getElementById('editDiscountInput');
      if (Number(p.is_on_sale) === 1) {
          if (saleToggle) saleToggle.checked = true;
          if (discountInput) {
            discountInput.disabled = false;
            discountInput.value = p.discount || 0;
          }
      } else {
          if (saleToggle) saleToggle.checked = false;
          if (discountInput) {
            discountInput.disabled = true;
            discountInput.value = 0;
          }
      }

      const editImagePreview = document.getElementById('editImagePreview');
      if (editImagePreview) {
        editImagePreview.src = `../../public/assets/img/furnitures/${p.default_image}`;
      }
      
      const compContainer = document.getElementById('editComponentsContainer');
      if (compContainer) {
        compContainer.innerHTML = '';
        if (p.components && p.components.length > 0) {
            p.components.forEach(comp => {
                addEditComponentRow(comp.pc_id, comp.component_name, comp.location || '', comp.qty_needed);
            });
        }
      }

      const varContainer = document.getElementById('editVariantsContainer');
      if (varContainer) {
        varContainer.innerHTML = '';
        if (p.variants && p.variants.length > 0) {
            p.variants.forEach((v, index) => {
                addEditVariantRow(v.variant_id, v.variant, v.variant_image, v.min_buildable_qty, index);
            });
        }
      }

      if (typeof window.openModal === "function") window.openModal('editModal');

      // Reset immediately after population is completely injected
      setTimeout(() => { window.formHasUnsavedChanges = false; }, 50);
    } else {
        if (typeof window.showCustomAlert === "function") {
            window.showCustomAlert("Error: " + (result.message || "Failed to fetch details."));
        }
    }
  } catch (error) {
    console.error(error);
  }
};

window.handleSaleToggle = async function(checkbox) {
    toggleEditSaleDiscount(checkbox);

    const code = document.getElementById('editOldCode')?.value;
    if (!code) return; // Do nothing if product isn't fully loaded

    const newStatus = checkbox.checked ? 1 : 0;
    const formData = new FormData();
    formData.append("action", "toggle_sale");
    formData.append("code", code);
    formData.append("is_on_sale", newStatus);

    try {
        const response = await fetch("../include/inc.admin/admin.ctrl.php", {
            method: "POST",
            body: formData,
        });

        let result = await response.json();

        if (result && result.success) {
            if (typeof window.showCustomSuccess === "function") {
                window.showCustomSuccess("Sale status updated!");
            }
        } else {
            checkbox.checked = !checkbox.checked; // revert
            toggleEditSaleDiscount(checkbox);
            if (typeof window.showCustomAlert === "function") {
                window.showCustomAlert("Error: " + (result.message || "Unknown error"));
            }
        }
    } catch (e) {
        checkbox.checked = !checkbox.checked; // revert
        toggleEditSaleDiscount(checkbox);
        console.error(e);
    }
};

window.toggleEditSaleDiscount = function(checkbox) {
    const input = document.getElementById('editDiscountInput');
    if (input) {
      input.disabled = !checkbox.checked;
      if (!checkbox.checked) input.value = '0';
    }
};

window.addEditComponentRow = function(id = '', name = '', loc = '', qty = '') {
    const container = document.getElementById('editComponentsContainer');
    if(!container) return;
    
    const isExisting = name !== '';
    const disabledAttr = isExisting ? 'disabled' : '';
    const penColor = isExisting ? 'text-gray-300' : 'text-blue-500';

    if (!isExisting) window.formHasUnsavedChanges = true;

    const html = `
        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-2xl group border border-transparent hover:border-gray-200 transition-all">
            <div class="flex-1">
                <input type="hidden" name="pc_ids[]" value="${id}">
                <input type="hidden" name="existing_comp_names[]" value="${name}">
                <input type="text" name="comp_names[]" value="${name}" list="compSuggestion" placeholder="Search or type part..." class="w-full bg-transparent font-bold text-gray-800 outline-none text-sm disabled:opacity-50" ${disabledAttr} required>
                <div class="flex items-center gap-2 mt-2 border border-gray-200 rounded-lg px-3 py-1 bg-white w-full ${isExisting ? 'opacity-50' : ''}">
                    <span class="text-[10px] font-black text-blue-500 uppercase tracking-widest shrink-0">LOC:</span>
                    <input type="text" name="comp_locs[]" value="${loc}" placeholder="Aisle 0-0" class="w-full bg-transparent outline-none text-[11px] font-bold uppercase text-gray-700" ${disabledAttr} required>
                </div>
            </div>
            <div class="flex items-center gap-2 px-2 border-l border-gray-200">
                <span class="text-[10px] font-bold text-gray-400 uppercase">Qty</span>
                <input type="number" name="comp_qtys[]" value="${qty}" placeholder="0" class="w-10 bg-white border border-gray-200 rounded-lg text-center font-bold text-sm py-1 outline-none disabled:bg-gray-100" ${disabledAttr} required>
            </div>
            <div class="flex items-center gap-2 border-l border-gray-200 pl-2">
                <button type="button" onclick="toggleEditRow(this)" class="${penColor} hover:text-blue-500 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                </button>
                <button type="button" data-remove-row="component" class="text-gray-300 hover:text-red-500 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                </button>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
};

let editVariantCounter = 100;
window.addEditVariantRow = function(id = '', name = '', image = 'default.png', lowStock = '10', overrideIndex = null) {
  const container = document.getElementById("editVariantsContainer");
  if (!container) return;

  const index = overrideIndex !== null ? overrideIndex : editVariantCounter++;
  const imgSrc = image ? `../../public/assets/img/furnitures/${image}` : '';
  const imgClass = image ? "object-cover w-full h-full rounded-xl" : "hidden object-cover w-full h-full rounded-xl";
  const svgClass = image ? "hidden w-4 h-4 text-gray-200" : "w-4 h-4 text-gray-200";

  const isExisting = name !== '';
  const disabledAttr = isExisting ? 'disabled' : '';
  const penColor = isExisting ? 'text-gray-300' : 'text-blue-500'; 

  if (!isExisting) window.formHasUnsavedChanges = true;

  const html = `
        <div class="flex items-center gap-4 p-3 bg-gray-50 rounded-2xl group border border-transparent hover:border-gray-200 transition-all">
            <input type="hidden" name="variant_ids[]" value="${id}">
            <input type="hidden" name="existing_variant_imgs[]" value="${image}">
            <div class="w-14 h-14 bg-white rounded-xl border border-gray-200 flex items-center justify-center shrink-0 relative">
                <img id="v-edit-prev-${index}" src="${imgSrc}" loading="lazy" class="${imgClass}" onerror="this.onerror=null; this.src='../../public/assets/img/furnitures/default.png';">
                <svg id="v-edit-svg-${index}" class="${svgClass}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
                <label class="absolute inset-0 bg-black/40 opacity-0 hover:opacity-100 flex items-center justify-center cursor-pointer rounded-xl transition-all">
                    <input type="file" name="variant_imgs[]" data-preview-edit-variant-image="${index}" accept=".jpg,.jpeg,.png" class="hidden" ${id === '' ? 'required' : ''}>
                    <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M12 4v16m8-8H4" stroke-width="2" stroke-linecap="round"></path>
                    </svg>
                </label>
            </div>
            <div class="flex-1" >
                <p class="text-[9px] font-black text-gray-400 uppercase mb-0.5 tracking-widest">Variant Name</p>
                <input type="text" name="variant_names[]" value="${name}" placeholder="e.g. Matte Black" class="w-full bg-transparent font-bold text-gray-800 outline-none text-sm disabled:opacity-50" ${disabledAttr} required>
            </div>
            <div class="w-20 border-l border-gray-200 pl-3">
                <p class="text-[9px] font-black text-red-500 uppercase mb-0.5 tracking-widest">Low Stock</p>
                <input type="number" name="variant_low_stocks[]" value="${lowStock}" placeholder="10" class="w-full bg-transparent font-bold text-red-600 outline-none text-sm disabled:opacity-50" ${disabledAttr} required>
            </div>
            <div class="flex items-center gap-2 border-l border-gray-200 pl-2">
                <button type="button" onclick="toggleEditRow(this)" class="${penColor} hover:text-green-500 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                </button>
                <button type="button" data-remove-row="variant" class="text-gray-300 hover:text-red-500 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                </button>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
};

window.previewEditVariantImage = function(input, index) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function (e) {
      const preview = document.getElementById("v-edit-prev-" + index);
      const svg = document.getElementById("v-edit-svg-" + index);
      if (preview) {
        preview.src = e.target.result;
        preview.classList.remove("hidden");
      }
      if (svg) {
        svg.classList.add("hidden");
      }
    };
    reader.readAsDataURL(input.files[0]);
  }
};

window.handleEditSave = function () {
  const form = document.getElementById("editProductForm");
  if (!form) return;

  const disabledElements = form.querySelectorAll(':disabled');
  disabledElements.forEach(el => el.disabled = false);

  if (!form.reportValidity()) {
      disabledElements.forEach(el => el.disabled = true);
      return;
  }

  disabledElements.forEach(el => el.disabled = true);

  if (!window.formHasUnsavedChanges) {
    if (typeof window.openModal === "function") {
      window.openModal('editConfirmModal');
    } 
  } else {
    window.executeEditSave();
  }
};

window.executeEditSave = async function () {
  const form = document.getElementById("editProductForm");
  if (!form) return;

  const disabledElements = form.querySelectorAll(':disabled');
  disabledElements.forEach(el => el.disabled = false);

  const formData = new FormData(form);
  formData.append("action", "update_product");

  disabledElements.forEach(el => el.disabled = true);

  if (typeof window.closeModal === "function") {
    window.closeModal('editConfirmModal');
  }

  try {
    const response = await fetch("../include/inc.admin/admin.ctrl.php", {
      method: "POST",
      body: formData,
    });
    let result = await response.json();
    
    if (result && result.success) {
        if (typeof window.closeModal === "function") {
            window.closeModal('editConfirmModal');
            window.closeModal('editModal');
        }

        if (typeof window.showCustomSuccess === "function") {
            window.showCustomSuccess("Product updated successfully", () => {
                window.location.reload();
            });
        }
    } else {
        if (typeof window.showCustomAlert === "function") {
            window.showCustomAlert("Error: " + (result.message || "Unknown error"));
        }
    }
  } catch (error) {
    console.error("Error updating product:", error);
  }
};

// --- Sale Status Logic ---

window.toggleSaleDatabaseCheckbox = async function (productCode, checkbox) {
  const newStatus = checkbox.checked ? 1 : 0;
  const inputField = document.getElementById("discountInput");

  const formData = new FormData();
  formData.append("action", "toggle_sale");
  formData.append("code", productCode);
  formData.append("is_on_sale", newStatus);

  try {
    const response = await fetch("../include/inc.admin/admin.ctrl.php", {
      method: "POST",
      body: formData,
    });

    let result = await response.json();

    if (result && result.success) {
      if (typeof window.showCustomSuccess === "function") {
        window.showCustomSuccess("Sale status updated!");
      }
      if (inputField) {
        inputField.disabled = !checkbox.checked;
        if (!checkbox.checked) inputField.value = 0;
      }
    } else {
      checkbox.checked = !checkbox.checked;
      if (typeof window.showCustomAlert === "function") {
        window.showCustomAlert("Error: " + (result.message || "Unknown error"));
      }
    }
  } catch (e) {
    checkbox.checked = !checkbox.checked;
    console.error(e);
  }
};

window.toggleSaleDiscount = function (checkbox) {
  const input = document.getElementById("discountInput");
  if (input) {
    input.disabled = !checkbox.checked;
    if (!checkbox.checked) input.value = 0;
  }
};

// --- Stock Adjustment Modal & Logic ---

window.toggleEditRow = function (button) {
  const row = button.closest(".group");
  if (!row) return;

  const inputs = row.querySelectorAll('input:not([type="hidden"]):not([type="file"])');
  if (inputs.length === 0) return;

  const isCurrentlyDisabled = inputs[0].disabled;
  inputs.forEach((input) => {
    input.disabled = !isCurrentlyDisabled;
  });

  if (isCurrentlyDisabled) {
    inputs[0].focus();
    button.classList.add("text-blue-500");
    button.classList.remove("text-gray-300");
  } else {
    button.classList.remove("text-blue-500");
    button.classList.add("text-gray-300");
  }
};

window.openStockModal = async function(code) {
  const formData = new FormData();
  formData.append("action", "get_product_details");
  formData.append("code", code);

  try {
    const response = await fetch("../include/inc.admin/admin.ctrl.php", {
      method: "POST",
      body: formData,
    });
    const result = await response.json();
    if (result && result.success) {
      window.populateStockModal(result.product);
      if (typeof window.openModal === "function") window.openModal('stockModal');
    } else {
      if (typeof window.showCustomAlert === "function") {
          window.showCustomAlert("Error: " + (result.message || "Failed to fetch stock details."));
      }
    }
  } catch (error) {
    console.error(error);
  }
};

window.populateStockModal = function(p) {
    const modalName = document.getElementById('stockModalName');
    const modalCode = document.getElementById('stockModalCode');
    if (modalName) modalName.textContent = p.name;
    if (modalCode) modalCode.textContent = p.code;

    const tabsContainer = document.getElementById('stockTabsContainer');
    if (tabsContainer) {
        tabsContainer.innerHTML = `
            <button onclick="switchTab(this, 'Warehouse', 'blue')"
                class="tab-btn w-full p-4 rounded-2xl bg-white border border-blue-600 shadow-sm flex flex-col items-start gap-1 transition-all border-l-4 group hover:bg-gray-50/50">
                <span class="tab-label text-[10px] font-black uppercase text-blue-600 group-hover:text-blue-600 transition-colors">Warehouse</span>
                <span class="tab-count text-lg font-black text-gray-900 group-hover:text-gray-900 leading-tight transition-colors">${p.total_wh || 0}<small class="text-[10px] block font-bold">UNITS</small></span>
            </button>
            <button onclick="switchTab(this, 'Showroom', 'red')"
                class="tab-btn w-full p-4 rounded-2xl bg-transparent border border-transparent flex flex-col items-start gap-1 transition-all group hover:bg-gray-50/50">
                <span class="tab-label text-[10px] font-black uppercase text-gray-400 group-hover:text-red-600 transition-colors">Showroom</span>
                <span class="tab-count text-lg font-black text-gray-300 group-hover:text-gray-900 leading-tight transition-colors">${p.total_sr || 0}<small class="text-[10px] block font-bold">UNITS</small></span>
            </button>
        `;
    }

    const whContent = document.getElementById('warehouseContent');
    if (whContent) {
        let whHtml = `
            <div class="flex items-center gap-3 border-l-2 border-blue-500 pl-3 py-1">
                <div>
                    <h4 class="text-[11px] font-black uppercase tracking-widest text-gray-900">Production Inventory</h4>
                    <p class="text-[9px] text-gray-400 font-bold uppercase tracking-tighter">Adjusting variants will deduct from components</p>
                </div>
            </div>
            <div class="space-y-4">
        `;

        if (p.variants && p.variants.length > 0) {
            p.variants.forEach((v) => {
                whHtml += `
                    <div class="bg-white border border-gray-100 rounded-3xl p-4 shadow-sm variant-card" data-variant-id="${v.variant_id}">
                        <div class="flex items-center mb-4">
                            <div class="relative shrink-0">
                                <div class="w-14 h-14 bg-gray-50 rounded-2xl border border-gray-100 p-2 flex items-center justify-center">
                                    <img src="../../public/assets/img/furnitures/${v.variant_image || 'default.png'}" loading="lazy" class="object-contain w-full h-full" onerror="this.src='../../public/assets/img/furnitures/default.png'">
                                </div>
                                <span class="absolute -top-1 -right-1 bg-blue-600 text-white text-[8px] font-black px-2 py-0.5 rounded-md uppercase">VAR</span>
                            </div>
                            <div class="flex-1 min-w-0 ml-4">
                                <h4 class="text-sm font-black text-gray-800 uppercase tracking-tight truncate">${v.variant}</h4>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-[9px] font-bold text-gray-400 uppercase">Stock:</span>
                                    <span class="text-[11px] font-black text-blue-600 bg-blue-50 px-2.5 py-1 rounded-lg">${v.v_wh} PCS</span>
                                </div>
                            </div>
                            <div class="flex flex-col items-center min-w-[150px]">
                                <label class="text-[8px] font-black text-blue-500 uppercase tracking-widest mb-1.5 leading-none">Assemble New</label>
                                <div class="flex items-center bg-white border border-gray-200 rounded-xl p-0.5 w-full">
                                    <button onclick="window.stepDownValue('wh-v-${v.variant_id}')"
                                        class="w-8 h-8 hover:bg-gray-100 text-gray-400 rounded-lg transition-all active:scale-90 flex items-center justify-center shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path d="M18 12H6" stroke-width="2.5" stroke-linecap="round" />
                                        </svg>
                                    </button>
                                    <div class="flex-1 px-1">
                                        <input type="number" id="wh-v-${v.variant_id}" 
                                            data-type="WH_VAR" data-id="${v.variant_id}" data-current="${v.v_wh || 0}" value="0"
                                            oninput="window.syncInventory()"
                                            class="stock-adj-input variant-input max-w-full bg-transparent font-black text-gray-900 outline-none text-center text-sm">
                                    </div>
                                    <button onclick="window.stepUpValue('wh-v-${v.variant_id}')"
                                        class="w-8 h-8 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-all active:scale-90 flex items-center justify-center shadow-sm shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path d="M12 6v12m6-6H6" stroke-width="2.5" stroke-linecap="round" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50/80 rounded-2xl p-3 grid grid-cols-2 gap-3">
                            <label class="col-span-2 text-[8px] font-black text-gray-400 uppercase tracking-widest mb-1">Recipe Linkages (${v.variant})</label>
                            ${p.components.map(c => `
                                <div class="flex items-center justify-between p-2.5 bg-white rounded-xl border border-gray-100 shadow-sm" data-comp-id="${c.pc_id}">
                                    <div class="min-w-0">
                                        <h6 class="text-[10px] font-black text-gray-700 uppercase truncate">${c.component_name}</h6>
                                        <p class="text-[8px] font-bold text-gray-400 mt-1 uppercase">Needed: ${c.qty_needed} per unit</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-[7px] font-black text-blue-500 uppercase leading-none mb-1">Total Parts</p>
                                        <span class="live-balance font-black text-xs text-blue-600" 
                                              data-comp-id="${c.pc_id}" 
                                              data-v-stock="${v.v_wh}" 
                                              data-ratio="${c.qty_needed}">${(parseInt(v.v_wh) || 0) * (parseInt(c.qty_needed) || 0)}</span>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
            });
        }
        whHtml += `</div>`;
        whContent.innerHTML = whHtml;
    }

    const srContent = document.getElementById('showroomContent');
    if (srContent) {
        let srHtml = `
            <div class="flex items-center gap-3 border-l-2 border-red-500 pl-3 py-1">
                <div>
                    <h4 class="text-[11px] font-black uppercase tracking-widest text-gray-900">Showroom Sync</h4>
                    <p class="text-[9px] text-gray-400 font-bold uppercase tracking-tighter">Adding to showroom will deduct from warehouse</p>
                </div>
            </div>
            <div class="space-y-3 mt-4">
        `;

        if (p.variants && p.variants.length > 0) {
            p.variants.forEach(v => {
                srHtml += `
                    <div class="p-4 bg-white border border-gray-100 rounded-3xl flex items-center gap-5 shadow-sm">
                        <div class="w-16 h-16 bg-gray-50 rounded-2xl border border-gray-100 p-2 shrink-0 flex items-center justify-center">
                            <img src="../../public/assets/img/furnitures/${v.variant_image || 'default.png'}" loading="lazy" class="object-contain w-full h-full" onerror="this.src='../../public/assets/img/furnitures/default.png'">
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1.5">
                                <span class="text-[9px] font-black bg-red-50 text-red-600 px-2 py-0.5 rounded-md uppercase">FLOOR</span>
                            </div>
                            <h4 class="text-sm font-black text-gray-800 uppercase mt-1.5 truncate">${v.variant}</h4>
                            <div class="flex items-center gap-3 mt-1.5">
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-tighter">Floor: <span class="text-gray-900">${v.v_sr}</span></p>
                                <div class="w-px h-3 bg-gray-200"></div>
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-tighter">WH Stock: <span class="text-gray-900">${v.v_wh}</span></p>
                            </div>
                        </div>
                        <div class="flex flex-col items-center">
                            <label class="text-[8px] font-black text-red-500 uppercase tracking-widest mb-1.5 leading-none">Move to Floor</label>
                            <div class="flex items-center bg-white border border-gray-200 rounded-xl p-0.5 gap-0.5">
                                <button onclick="window.stepDownValue('sr-v-${v.variant_id}')"
                                    class="w-8 h-8 hover:bg-gray-100 text-gray-400 rounded-lg transition-all active:scale-90 flex items-center justify-center">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path d="M19 12H5" stroke-width="2.5" stroke-linecap="round" />
                                    </svg>
                                </button>
                                <input type="number" id="sr-v-${v.variant_id}" 
                                    data-type="SR_VAR" data-id="${v.variant_id}" 
                                    data-current="${v.v_sr || 0}" 
                                    data-wh-current="${v.v_wh || 0}"
                                    value="0"
                                    oninput="window.syncInventory()"
                                    class="stock-adj-input w-10 bg-transparent font-black text-gray-900 outline-none text-center text-sm">
                                <button onclick="window.stepUpValue('sr-v-${v.variant_id}')"
                                    class="w-8 h-8 bg-gray-900 hover:bg-black text-white rounded-lg transition-all active:scale-90 flex items-center justify-center shadow-md">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 6v12m6-6H6" stroke-width="2.5" stroke-linecap="round" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });
        }
        srHtml += `</div>`.trim();
        srContent.innerHTML = srHtml;
    }
    
    window.syncInventory();
};

window.syncInventory = function () {
  const variantInputs = document.querySelectorAll('.variant-input');
  variantInputs.forEach(input => {
      const adjustment = parseInt(input.value) || 0;
      const card = input.closest('.variant-card');
      if (!card) return;
      
      const recipeLabels = card.querySelectorAll('.live-balance');
      recipeLabels.forEach(el => {
          const vStock = parseInt(el.getAttribute('data-v-stock')) || 0;
          const ratio = parseInt(el.getAttribute('data-ratio')) || 0;
          const newTotalUsed = Math.max(0, (vStock + adjustment) * ratio);
          el.textContent = newTotalUsed;

          if (adjustment > 0) {
              el.classList.add('text-green-600');
              el.classList.remove('text-blue-600', 'text-red-600', 'text-gray-900');
          } else if (adjustment < 0) {
              el.classList.add('text-red-600');
              el.classList.remove('text-blue-600', 'text-green-600', 'text-gray-900');
          } else {
              el.classList.add('text-gray-900');
              el.classList.remove('text-blue-600', 'text-green-600', 'text-red-600');
          }
      });
  });
};

window.switchTab = function (btn, tabName, color) {
  const buttons = document.querySelectorAll(".tab-btn");
  buttons.forEach((b) => {
    b.classList.remove("bg-white", `border-${color}-600`, "border-blue-600", "border-red-600", "border-l-4", "shadow-sm");
    b.classList.add("bg-transparent", "border-transparent");
    
    const label = b.querySelector(".tab-label");
    if (label) {
      label.classList.remove("text-blue-600", "text-red-600");
      label.classList.add("text-gray-400");
    }

    const count = b.querySelector(".tab-count");
    if (count) {
      count.classList.remove("text-gray-900");
      count.classList.add("text-gray-300");
    }
  });

  btn.classList.remove("bg-transparent", "border-transparent");
  btn.classList.add("bg-white", `border-${color}-600`, "border-l-4", "shadow-sm");
  
  const activeLabel = btn.querySelector(".tab-label");
  if (activeLabel) {
    activeLabel.classList.remove("text-gray-400");
    activeLabel.classList.add(`text-${color}-600`);
  }

  const activeCount = btn.querySelector(".tab-count");
  if (activeCount) {
    activeCount.classList.remove("text-gray-300");
    activeCount.classList.add("text-gray-900");
  }

  const whContent = document.getElementById("warehouseContent");
  const srContent = document.getElementById("showroomContent");
  
  if (tabName === 'Warehouse') {
      if (whContent) whContent.classList.remove("hidden");
      if (srContent) srContent.classList.add("hidden");
  } else {
      if (whContent) whContent.classList.add("hidden");
      if (srContent) srContent.classList.remove("hidden");
  }
};

window.stepUpValue = function (id) {
  const input = document.getElementById(id);
  if (input) {
    input.stepUp();
    if (input.classList.contains('stock-adj-input')) window.syncInventory();
  }
};

window.stepDownValue = function (id) {
  const input = document.getElementById(id);
  if (input) {
    input.stepDown();
    if (input.classList.contains('stock-adj-input')) window.syncInventory();
  }
};

window.handleStockUpdate = function() {
    const inputs = document.querySelectorAll('.stock-adj-input');
    const adjustments = [];
    let hasReduction = false;
    const variantStats = {};

    for (const input of inputs) {
        const diff = parseInt(input.value) || 0;
        if (diff !== 0) {
            const variantId = input.getAttribute('data-id');
            const type = input.getAttribute('data-type');
            
            if (!variantStats[variantId]) {
                const card = input.closest('.variant-card') || document.querySelector(`[data-variant-id="${variantId}"]`);
                variantStats[variantId] = {
                    name: card ? card.querySelector('h4').textContent : 'Unknown',
                    wh_base: 0,
                    wh_adj: 0,
                    sr_adj: 0
                }
            }
            
            if (type === 'WH_VAR') {
                variantStats[variantId].wh_base = parseInt(input.getAttribute('data-current')) || 0;
                variantStats[variantId].wh_adj = diff;
            } else {
                variantStats[variantId].wh_base = parseInt(input.getAttribute('data-wh-current')) || 0;
                variantStats[variantId].sr_adj = diff;
            }
        }
    }

    for (const vid in variantStats) {
        const stats = variantStats[vid];
        const projectedWH = stats.wh_base + stats.wh_adj - stats.sr_adj;
        if (projectedWH < 0) {
            const msg = `Insufficient Warehouse stock! Your transfer of ${stats.sr_adj} units exceeds the total available Warehouse stock (${stats.wh_base + stats.wh_adj}) for ${stats.name}.`;
            if (typeof window.showCustomAlert === "function") window.showCustomAlert(msg);
            else alert(msg);
            return;
        }
    }

    for (const input of inputs) {
        const diff = parseInt(input.value) || 0;
        const current = parseInt(input.getAttribute('data-current')) || 0;
        const type = input.getAttribute('data-type');

        if (diff !== 0) {
            if (current + diff < 0 && type !== 'WH_VAR' && type !== 'SR_VAR') {
                const msg = `Invalid Adjustment! Current stock is ${current}, you cannot subtract ${Math.abs(diff)}. The result would be negative.`;
                if (typeof window.showCustomAlert === "function") window.showCustomAlert(msg);
                else alert(msg);
                return;
            }
            if (diff < 0) hasReduction = true;
            adjustments.push({ type: type, id: input.getAttribute('data-id'), diff: diff });
        }
    }

    if (adjustments.length === 0) {
        if (typeof window.showCustomAlert === "function") window.showCustomAlert("No adjustments made.");
        else alert("No adjustments made.");
        return;
    }

    window.pendingStockAdjustments = adjustments;
    if (hasReduction && typeof window.openModal === 'function') {
        window.openModal('reductionConfirmModal');
    } else {
        window.executeStockUpdate();
    }
};

window.executeStockUpdate = async function() {
    const adjustments = window.pendingStockAdjustments;
    if (!adjustments || adjustments.length === 0) return;

    const btn = document.getElementById('stockUpdateBtn');
    const originalText = btn ? btn.innerHTML : "Update Stocks";

    if (btn) {
        btn.disabled = true;
        btn.innerHTML = `<svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-white inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Updating...`;
    }

    const formData = new FormData();
    formData.append("action", "update_stocks");
    formData.append("adjustments", JSON.stringify(adjustments));

    try {
        const response = await fetch("../include/inc.admin/admin.ctrl.php", {
            method: "POST",
            body: formData,
        });
        const result = await response.json();
    if (result && result.success) {
        if (typeof window.closeModal === "function") {
            window.closeModal('reductionConfirmModal');
            window.closeModal('stockModal');
        }

        setTimeout(() => {
            if (typeof window.showCustomSuccess === "function") {
                window.showCustomSuccess("Stocks updated successfully!", () => {
                    window.location.reload();
                });
            } else {
                window.location.reload();
            }
        }, 300);
    } else {
             if (typeof window.showCustomAlert === "function") {
                window.showCustomAlert("Error: " + (result.message || "Failed to update stocks."));
            }
        }
    } catch (error) {
        console.error(error);
    } finally {
        window.pendingStockAdjustments = null;
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }
};

window.toggleFilterMenu = function(e) {
  if (e) e.stopPropagation();
  const menu = document.getElementById("filterMenu");
  if (menu) menu.classList.toggle("hidden");
};

