/**
 * Settings & User Management Scripts
 */

/**
 * UI TOGGLE: Settings Sidebar
 * Handles visibility of different settings sections
 */
window.showSettingSection = function (sectionId, linkId) {
  const userSec = document.getElementById("userManagementSection");
  const reportsBackupSec = document.getElementById("reportsBackupSection");

  const activeClasses = [
    "bg-red-600",
    "text-white",
    "shadow-lg",
    "shadow-red-100",
    "active-setting-link",
  ];
  const inactiveClasses = [
    "text-gray-400",
    "hover:bg-gray-50",
    "hover:text-gray-900",
    "bg-transparent",
    "border-transparent",
    "hover:border-gray-100",
  ];

  // Hide all sections
  [userSec, reportsBackupSec].forEach((s) => s && s.classList.add("hidden"));

  // Deactivate all links
  const userLink = document.getElementById("userManagementLink");
  const reportsBackupLink = document.getElementById("reportsBackupLink");
  [userLink, reportsBackupLink].forEach((l) => {
    if (l) {
      l.classList.remove(...activeClasses);
      l.classList.add(...inactiveClasses);
    }
  });

  // Activate target
  if (sectionId === "userManagement" || sectionId === "userManagementSection") {
    if (userSec) userSec.classList.remove("hidden");
    if (userLink) {
      userLink.classList.remove(...inactiveClasses);
      userLink.classList.add(...activeClasses);
    }
    localStorage.setItem("settings_active_section", "userManagementSection");
    localStorage.setItem("settings_active_link", "userManagementLink");
  } else if (
    sectionId === "reportsBackup" ||
    sectionId === "reportsBackupSection"
  ) {
    if (reportsBackupSec) reportsBackupSec.classList.remove("hidden");
    if (reportsBackupLink) {
      reportsBackupLink.classList.remove(...inactiveClasses);
      reportsBackupLink.classList.add(...activeClasses);
    }
    localStorage.setItem("settings_active_section", "reportsBackupSection");
    localStorage.setItem("settings_active_link", "reportsBackupLink");
  }

  // Update URL Sync
  const url = new URL(window.location);
  url.searchParams.set("section", sectionId);
  window.history.replaceState({}, "", url);
};

/**
 * Fetch and display system diagnostics
 */
function loadDiagnostics() {
  const diagList = document.getElementById("diag_table_list");
  if (diagList)
    diagList.innerHTML =
      '<p class="text-center py-10 text-gray-400 animate-pulse">Fetching system data...</p>';

  fetch("../include/inc.admin/admin.ctrl.php?action=get_diagnostics")
    .then((res) => res.json())
    .then((res) => {
      if (res.success) {
        const data = res.data;
        const dbName = document.getElementById("diag_db_name");
        const dbVer = document.getElementById("diag_db_version");
        const status = document.getElementById("diag_status");

        if (dbName) dbName.innerText = data.db_name;
        if (dbVer) dbVer.innerText = data.db_version;
        if (status) status.innerText = data.status;

        if (diagList) {
          diagList.innerHTML = data.tables
            .map(
              (t) => `
                        <div class="flex justify-between items-center p-4 bg-gray-50 hover:bg-red-50/30 rounded-2xl border border-gray-100 transition-colors group">
                            <div class="flex items-center gap-3">
                                <div class="size-8 bg-white border border-gray-100 rounded-lg flex items-center justify-center text-gray-400 group-hover:text-red-500 transition-colors">
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" /></svg>
                                </div>
                                <span class="font-bold text-gray-700">${t}</span>
                            </div>
                            <span class="px-3 py-1 bg-white border border-gray-200 rounded-lg text-[10px] font-black uppercase tracking-tight shadow-sm">${data.counts[t]} rows</span>
                        </div>
                    `,
            )
            .join("");
        }
      }
    })
    .catch((err) => console.error("Diagnostics error:", err));
}

/**
 * Confirm and trigger database migration
 */
window.confirmMigration = function () {
  if (
    confirm(
      "WARNING: This will RESET the entire database and re-seed it with default data. All current orders, products, and user changes will be LOST. Continue?",
    )
  ) {
    const form = document.createElement("form");
    form.method = "POST";
    form.action = "../include/inc.admin/admin.ctrl.php";

    const actionInput = document.createElement("input");
    actionInput.type = "hidden";
    actionInput.name = "action";
    actionInput.value = "run_migration";

    form.appendChild(actionInput);
    document.body.appendChild(form);
    form.submit();
  }
};

// Registration Form Elements
const submitBtn = document.getElementById("submitBtn");
const usernameInput = document.getElementById("regUsername");
const nameInput = document.getElementById("regFullName");
const passwordInput = document.getElementById("passwordInput");
const confirmPasswordInput = document.getElementById("confirmPasswordInput");

let isUsernameValid = false;
let isNameValid = false;
let isPasswordValid = false;
let isMatchValid = false;

/**
 * MASTER FORM VALIDATION
 * Enables/Disables the Create Account button
 */
function validateForm() {
  if (!submitBtn) return;
  
  if (isUsernameValid && isNameValid && isPasswordValid && isMatchValid) {
    submitBtn.disabled = false;
    submitBtn.classList.remove("opacity-50", "cursor-not-allowed");
    submitBtn.classList.add("shadow-lg", "shadow-red-100", "hover:bg-gray-900");
  } else {
    submitBtn.disabled = true;
    submitBtn.classList.add("opacity-50", "cursor-not-allowed");
    submitBtn.classList.remove("shadow-lg", "shadow-red-100", "hover:bg-gray-900");
  }
}

/**
 * MODAL TOGGLE UTILITY
 */
window.toggleModal = function (modalId) {
  const modal = document.getElementById(modalId);
  if (!modal) return;
  const container = modal.querySelector("div");

  const isHidden = modal.classList.contains("opacity-0") || modal.classList.contains("pointer-events-none");

  if (isHidden) {
    // Opening
    modal.classList.remove("opacity-0", "pointer-events-none");
    modal.classList.add("opacity-100", "pointer-events-auto");
    if (container) {
      container.classList.remove("scale-95");
      container.classList.add("scale-100");
    }
  } else {
    // Closing
    modal.classList.remove("opacity-100", "pointer-events-auto");
    modal.classList.add("opacity-0", "pointer-events-none");
    if (container) {
      container.classList.remove("scale-100");
      container.classList.add("scale-95");
    }
  }
};

/**
 * MODAL CLOSE WITH UNSAVED CHANGES CHECK
 */
window.formHasUnsavedChanges = false;
let pendingModalToClose = null;
let pendingFormToReset = null;

window.closeModalWithCheck = function(modalId, formId) {
    if (window.formHasUnsavedChanges) {
        pendingModalToClose = modalId;
        pendingFormToReset = formId;
        toggleModal('discardModal');
    } else {
        closeModalForce(modalId, formId);
    }
}

window.closeModalForce = function(modalId, formId) {
    try {
        if (formId) {
            const form = document.getElementById(formId);
            if (form) form.reset();
        }
    } catch (e) {
        console.error("Reset error intercepted:", e);
    } finally {
        window.formHasUnsavedChanges = false;
        toggleModal(modalId);
    }
}

window.closeModal = function(modalId) {
    toggleModal(modalId);
}

window.confirmDiscardAction = function() {
    toggleModal('discardModal');
    if (pendingModalToClose) {
        closeModalForce(pendingModalToClose, pendingFormToReset);
    }
}

/**
 * PASSWORD VISIBILITY TOGGLE (Registration)
 */
window.toggleVisibility = function (inputId, iconId) {
  const input = document.getElementById(inputId);
  const icon = document.getElementById(iconId);
  if (input.type === "password") {
    input.type = "text";
    icon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />`;
  } else {
    input.type = "password";
    icon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /> <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />`;
  }
};

/**
 * PASSWORD VISIBILITY TOGGLE (Password Reset)
 */
window.toggleNewPassword = function () {
  const input = document.getElementById("new_password");
  const type = input.getAttribute("type") === "password" ? "text" : "password";
  input.setAttribute("type", type);
};

/**
 * PASSWORD MATCH VALIDATION
 */
function checkMatch() {
  if (!passwordInput || !confirmPasswordInput) return;
  const pass = passwordInput.value;
  const confirm = confirmPasswordInput.value;
  const status = document.getElementById("matchStatus");

  if (confirm.length > 0) {
    if (pass === confirm) {
      status.innerText = "✓ Matched";
      status.className = "text-[10px] font-bold text-green-500 uppercase";
      isMatchValid = true;
    } else {
      status.innerText = "✕ Not Matched";
      status.className = "text-[10px] font-bold text-red-500 uppercase";
      isMatchValid = false;
    }
  } else {
    status.innerText = "Not Matched";
    status.className = "text-[10px] font-bold text-slate-400 uppercase";
    isMatchValid = false;
  }
  validateForm();
}

/**
 * DELETE & RESET MODAL CONTROLS
 */
window.confirmDelete = function (userId) {
  const input = document.getElementById("delete_user_id_input");
  if (input) input.value = userId;
  toggleModal("deleteModal");
};

window.openResetModal = function (userId, fullName, username) {
  const idInput = document.getElementById("reset_user_id_input");
  const nameLabel = document.getElementById("reset_user_display_name");
  const usernameInput = document.getElementById("reset_username_input");

  if (idInput) idInput.value = userId;
  if (nameLabel) nameLabel.innerText = fullName;
  if (usernameInput) {
    usernameInput.value = username;
    usernameInput.readOnly = true;
    usernameInput.classList.add("cursor-not-allowed", "text-gray-500", "bg-gray-50");
    usernameInput.classList.remove("text-gray-900", "bg-white");
  }

  const passInput = document.querySelector(
    '#resetModal input[name="new_password"]',
  );
  const confirmInput = document.getElementById("confirm_password");
  if (passInput) passInput.value = "";
  if (confirmInput) confirmInput.value = "";

  // Reset Edit Button if exists
  const editBtn = document.getElementById("editUsernameBtn");
  if (editBtn) editBtn.classList.remove("bg-amber-600", "text-white");

  toggleModal("resetModal");
};

/**
 * ONLINE STATUS POLLING
 */
function fetchOnlineStatus() {
  fetch("../include/inc.admin/admin.ctrl.php?action=get_user_status")
    .then((res) => res.json())
    .then((users) => {
      users.forEach((user) => {
        const dot = document.getElementById(`status-dot-${user.id}`);
        const text = document.getElementById(`status-text-${user.id}`);

        if (dot && text) {
          const container = dot.parentElement;
          if (user.is_online == 1) {
            if (container) container.className = "inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-green-50/50 border border-green-100";
            dot.className = "size-1.5 rounded-full bg-green-500 animate-pulse";
            text.className = "text-[9px] font-black text-green-600 uppercase";
            text.innerText = "Live";
          } else {
            if (container) container.className = "inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-gray-50/50 border border-gray-100";
            dot.className = "size-1.5 rounded-full bg-gray-300";
            text.className = "text-[9px] font-black text-gray-400 uppercase";
            text.innerText = "Off";
          }
        }
      });
    })
    .catch((err) => console.error("Poll error:", err));
}

// Event Listeners initialization
document.addEventListener("DOMContentLoaded", () => {
  // Password match listeners
  if (confirmPasswordInput)
    confirmPasswordInput.addEventListener("input", checkMatch);
  if (passwordInput) passwordInput.addEventListener("input", checkMatch);
  
  // Tab Persistence - Robust Restoration Logic
  const urlParams = new URLSearchParams(window.location.search);
  const urlSection = urlParams.get('section');
  const savedSection = localStorage.getItem('settings_active_section');
  const savedLink = localStorage.getItem('settings_active_link');
  
  if (urlSection) {
      if (urlSection === 'reportsBackup' || urlSection === 'reportsBackupSection') {
          window.showSettingSection('reportsBackup', 'reportsBackupLink');
      } else {
          window.showSettingSection('userManagement', 'userManagementLink');
      }
  } else if (savedSection && savedLink) {
      window.showSettingSection(savedSection, savedLink);
  } else {
      window.showSettingSection('userManagement', 'userManagementLink');
  }

  // Full Name Validation
  if (nameInput) {
    nameInput.addEventListener("input", () => {
      nameInput.value = nameInput.value.replace(/[0-9]/g, "");
      const val = nameInput.value.trim();
      const status = document.getElementById("nameStatus");
      if (val.length >= 3) {
        status.innerText = "✓ Valid";
        status.className = "text-[10px] font-bold text-green-500 uppercase";
        isNameValid = true;
      } else {
        status.innerText = "Invalid Name";
        status.className = "text-[10px] font-bold text-red-500 uppercase";
        isNameValid = false;
      }
      validateForm();
    });
  }

  // Username Validation
  if (usernameInput) {
    usernameInput.addEventListener("input", () => {
      const username = usernameInput.value.trim();
      const status = document.getElementById("userStatus");
      if (username.length < 4) {
        status.innerText = "Too Short";
        status.className = "text-[10px] text-red-400 font-bold uppercase";
        isUsernameValid = false;
        validateForm();
        return;
      }
      status.innerText = "Checking...";
      const formData = new FormData();
      formData.append("username", username);
      fetch("../include/inc.admin/check_user.php", {
        method: "POST",
        body: formData,
      })
        .then((res) => res.text())
        .then((data) => {
          const response = data.trim();
          if (response === "taken") {
            status.innerText = "✕ Taken";
            status.className = "text-[10px] font-bold text-red-600 uppercase";
            isUsernameValid = false;
          } else {
            status.innerText = "✓ Available";
            status.className = "text-[10px] font-bold text-green-500 uppercase";
            isUsernameValid = true;
          }
          validateForm();
        })
        .catch((err) => {
          console.error("Connection Error:", err);
          status.innerText = "Error";
        });
    });
  }

  // Password Strength
  if (passwordInput) {
    passwordInput.addEventListener("input", function () {
      const password = this.value;
      const bar = document.getElementById("strengthBar");
      const text = document.getElementById("strengthText");
      let strength = 0;
      if (password.length >= 6) strength++;
      if (password.length >= 10) strength++;
      if (/[A-Z]/.test(password)) strength++;
      if (/[0-9]/.test(password)) strength++;
      if (/[^A-Za-z0-9]/.test(password)) strength++;
      switch (strength) {
        case 0:
          bar.className = "w-0";
          text.innerText = "Enter a password";
          text.className = "text-[10px] font-medium text-slate-400 uppercase";
          isPasswordValid = false;
          break;
        case 1:
        case 2:
          bar.className = "w-1/3 bg-red-500";
          text.innerText = "Weak";
          text.className = "text-[10px] font-bold text-red-500 uppercase";
          isPasswordValid = false;
          break;
        case 3:
        case 4:
          bar.className = "w-2/3 bg-yellow-500";
          text.innerText = "Moderate";
          text.className = "text-[10px] font-bold text-yellow-500 uppercase";
          isPasswordValid = true;
          break;
        case 5:
          bar.className = "w-full bg-green-500";
          text.innerText = "Strong";
          text.className = "text-[10px] font-bold text-green-500 uppercase";
          isPasswordValid = true;
          break;
      }
      validateForm();
    });
  }

  // Reset form states when form is cleared
  const regForm = document.getElementById("regForm");
  if (regForm) {
    regForm.addEventListener("reset", () => {
      // Reset validation flags
      isUsernameValid = false;
      isNameValid = false;
      isPasswordValid = false;
      isMatchValid = false;

      // Reset UI Statuses
      const statuses = ["userStatus", "nameStatus", "matchStatus"];
      statuses.forEach((id) => {
        const el = document.getElementById(id);
        if (el) {
          el.innerText =
            id === "userStatus"
              ? "Required"
              : id === "nameStatus"
                ? "Min 3 chars"
                : "Not Matched";
          el.className = "text-[10px] font-bold uppercase text-slate-400";
        }
      });

      // Reset Strength Bar
      const bar = document.getElementById("strengthBar");
      const stText = document.getElementById("strengthText");
      if (bar) bar.className = "w-0";
      if (stText) {
        stText.innerText = "Enter a password";
        stText.className =
          "text-[10px] font-medium uppercase tracking-wider text-slate-400";
      }

      // Sync button
      validateForm();
    });
  }

  // Start Online Polling
  setInterval(fetchOnlineStatus, 5000);
});
