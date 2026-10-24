console.log("register_client.js v2 loaded");

("use strict");

document.addEventListener("DOMContentLoaded", function () {
  console.log("DOM ready, form =", document.getElementById("registerForm"));

  const form = document.getElementById("registerForm");
  const statusBox = document.getElementById("status");
  const submitBtn = document.getElementById("submitBtn");
  const agreeTerms = document.getElementById("agreeTerms");
  const termsError = document.getElementById("termsError");

  const emailInput = document.getElementById("email");
  const passwordInput = document.getElementById("password");
  const confirmPasswordInput = document.getElementById("confirm_password");
  const passwordLength = document.getElementById("passwordLength");
  const passwordLettersNumbers = document.getElementById(
    "passwordLettersNumbers",
  );

  // ===== Province (fixed) → Municipality → Barangay (filtered) =====
  const municipalitySelect = document.getElementById("municipality");
  const barangaySelect = document.getElementById("barangay");
  const addressInput = document.getElementById("address");
  const PROVINCE = "Siquijor";

  function resetBarangays() {
    barangaySelect.innerHTML =
      '<option value="">Select municipality first</option>';
    barangaySelect.disabled = true;
    addressInput.value = "";
  }

  Object.keys(window.SIQUIJOR_BARANGAYS).forEach(function (municipality) {
    municipalitySelect.add(new Option(municipality, municipality));
  });

  municipalitySelect.addEventListener("change", function () {
    const municipality = municipalitySelect.value;
    resetBarangays();
    setFieldValid(barangaySelect, "");

    if (!municipality) return;

    setFieldValid(municipalitySelect, "");
    barangaySelect.innerHTML = '<option value="">Select barangay</option>';
    window.SIQUIJOR_BARANGAYS[municipality].forEach(function (name) {
      barangaySelect.add(new Option(name, name));
    });
    barangaySelect.disabled = false;
    clearStatus();
  });

  barangaySelect.addEventListener("change", function () {
    addressInput.value = barangaySelect.value
      ? barangaySelect.value + ", " + municipalitySelect.value + ", " + PROVINCE
      : "";
    if (barangaySelect.value) setFieldValid(barangaySelect, "");
    clearStatus();
  });

  form.addEventListener("reset", function () {
    setTimeout(resetBarangays, 0);
  });

  let emailAvailable = null;
  let checkingEmail = false;
  let emailRequestNumber = 0;

  // ========================================
  // STATUS MESSAGE
  // ========================================

  function showStatus(message, type) {
    if (!statusBox) return;

    statusBox.textContent = message;
    statusBox.className = "alert alert-" + type;
    statusBox.setAttribute("role", "alert");
  }

  function clearStatus() {
    if (!statusBox) return;

    statusBox.textContent = "";
    statusBox.className = "alert d-none";
  }

  // ========================================
  // FIELD VALIDATION HELPERS
  // ========================================

  function getFeedback(field) {
    const container = field.closest(".col-md-4, .col-md-6, .col-12");

    return container ? container.querySelector(".invalid-feedback") : null;
  }

  function setFieldError(field, message) {
    field.classList.remove("is-valid");
    field.classList.add("is-invalid");
    field.setCustomValidity(message);

    const feedback = getFeedback(field);
    if (feedback) {
      feedback.textContent = message;
      feedback.classList.remove("text-success");
      feedback.classList.add("d-block");
    }

    return false;
  }

  function setFieldValid(field, message = "") {
    field.classList.remove("is-invalid");
    field.setCustomValidity("");

    const feedback = getFeedback(field);
    if (feedback) {
      feedback.textContent = message;
      feedback.classList.remove("text-success", "d-block");

      if (message) {
        feedback.classList.add("text-success", "d-block");
      }
    }

    if (message) {
      field.classList.add("is-valid");
    } else {
      field.classList.remove("is-valid");
    }

    return true;
  }

  // Accepts 09XXXXXXXXX, 9XXXXXXXXX, 639XXXXXXXXX, +639XXXXXXXXX (spaces and dashes allowed).
  // Returns 09XXXXXXXXX, or null when the number is not valid.
  function normalizeMobile(raw) {
    const n = raw.replace(/[^\d+]/g, "");
    let m;
    if (
      (m = n.match(/^\+639(\d{9})$/)) ||
      (m = n.match(/^639(\d{9})$/)) ||
      (m = n.match(/^09(\d{9})$/)) ||
      (m = n.match(/^9(\d{9})$/))
    ) {
      return "09" + m[1];
    }
    return null;
  }

  function validateRequired(field, label) {
    if (!field.value.trim()) {
      return setFieldError(field, label + " is required.");
    }

    return setFieldValid(field, "");
  }

  // ========================================
  // MANUAL TYPING ONLY FOR PHONE NUMBERS
  // Set ALLOW_PASTE to true if you want to allow pasting.
  // ========================================

  const ALLOW_PASTE = false;

  ["mobile_number"].forEach(function (id) {
    const field = document.getElementById(id);
    if (!field) return;

    // 1) Browsers do not autofill readonly fields; unlock when the user focuses it
    field.setAttribute("readonly", "readonly");
    function unlock() {
      field.removeAttribute("readonly");
    }
    field.addEventListener("focus", unlock);
    field.addEventListener("pointerdown", unlock);

    // 2) Block paste and drag-drop (optional)
    function reject(event) {
      event.preventDefault();
      setFieldError(field, "Please type this number manually.");
    }

    if (!ALLOW_PASTE) {
      field.addEventListener("paste", reject);
      field.addEventListener("drop", reject);
    }

    // 3) Safety net: autofill and paste add many characters in a single input event.
    //    Typing adds one, so a big jump means the text did not come from the keyboard.
    let previousLength = field.value.length;

    field.addEventListener("input", function (event) {
      const jump = field.value.length - previousLength;

      if (
        !ALLOW_PASTE &&
        jump > 1 &&
        !event.isComposing &&
        event.inputType !== "insertCompositionText"
      ) {
        field.value = "";
        previousLength = 0;
        setFieldError(field, "Please type this number manually.");
        return;
      }

      previousLength = field.value.length;
    });
  });

  // One phone check used by the submit validation AND the blur handler,
  // so the message is always the same. Logs the exact value when it rejects.
  function checkPhone(field, label) {
    if (!field.value.trim()) {
      return validateRequired(field, label);
    }

    if (!normalizeMobile(field.value)) {
      console.warn(
        "Phone rejected:",
        field.id,
        "value =",
        JSON.stringify(field.value),
        "length =",
        field.value.length,
      );
      return setFieldError(
        field,
        label + " must be a valid Philippine number, e.g. 09123456789.",
      );
    }

    return setFieldValid(field, "");
  }

  // ========================================
  // VALIDATE EMAIL FORMAT
  // ========================================

  function validateEmailFormat() {
    emailInput.setCustomValidity(""); // clear any old error so validity is re-checked fresh

    const email = emailInput.value.trim();

    if (!email) {
      emailAvailable = null;
      return setFieldError(emailInput, "Email address is required.");
    }

    if (!emailInput.validity.valid) {
      emailAvailable = null;
      return setFieldError(emailInput, "Please enter a valid email address.");
    }

    return setFieldValid(emailInput, "");
  }

  // ========================================
  // CHECK EMAIL AGAINST THE DATABASE
  // Returns true = available, false = taken or invalid, null = could not check
  // ========================================

  async function checkEmailAvailability() {
    if (!validateEmailFormat()) return false;

    const email = emailInput.value.trim().toLowerCase();
    const requestNumber = ++emailRequestNumber;

    checkingEmail = true;
    emailAvailable = null;

    emailInput.classList.remove("is-valid", "is-invalid");

    const feedback = getFeedback(emailInput);
    if (feedback) feedback.textContent = "Checking email...";

    try {
      const response = await fetch("../views/check_email.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: new URLSearchParams({ email: email }),
      });

      const result = await response.json();

      // Ignore a response for an older email value.
      if (requestNumber !== emailRequestNumber) return null;

      if (!response.ok || !result.success) {
        throw new Error(
          result.message || "Unable to check email availability.",
        );
      }

      emailAvailable = result.available;

      if (emailAvailable) {
        setFieldValid(emailInput, result.message);
        return true;
      }

      setFieldError(emailInput, result.message);
      return false;
    } catch (error) {
      if (requestNumber !== emailRequestNumber) return null;

      console.warn("Email check unavailable:", error);
      emailAvailable = null;
      setFieldValid(emailInput, "");
      return null; // the server checks again during registration
    } finally {
      if (requestNumber === emailRequestNumber) {
        checkingEmail = false;
      }
    }
  }

  if (emailInput) {
    emailInput.addEventListener("input", function () {
      emailRequestNumber++;
      checkingEmail = false;
      emailAvailable = null;
      setFieldValid(emailInput, "");
      clearStatus();
    });

    emailInput.addEventListener("blur", checkEmailAvailability);
  }

  // ========================================
  // SHOW / HIDE PASSWORD EYE BUTTONS
  // ========================================

  document.querySelectorAll(".pw-toggle").forEach(function (button) {
    button.addEventListener("click", function () {
      const input = document.getElementById(button.dataset.target);
      if (!input) return;

      const showPassword = input.type === "password";
      input.type = showPassword ? "text" : "password";

      button.querySelector(".eye-on").classList.toggle("d-none", !showPassword);
      button.querySelector(".eye-off").classList.toggle("d-none", showPassword);

      button.setAttribute("aria-pressed", String(showPassword));
      button.setAttribute(
        "aria-label",
        showPassword ? "Hide password" : "Show password",
      );
    });
  });

  // ========================================
  // VALIDATE PASSWORD
  // ========================================

  function markRule(element, ok, showFail) {
    if (!element) return;
    element.classList.remove("text-muted", "text-success", "text-danger");
    element.classList.add(
      ok ? "text-success" : showFail ? "text-danger" : "text-muted",
    );
  }

  function validatePassword() {
    const pw = passwordInput.value;
    const lengthOk = pw.length >= 8;
    const mixOk = /[A-Za-z]/.test(pw) && /\d/.test(pw);

    markRule(passwordLength, lengthOk, true);
    markRule(passwordLettersNumbers, mixOk, true);

    if (!pw) return setFieldError(passwordInput, "Password is required.");
    if (!lengthOk || !mixOk) {
      return setFieldError(
        passwordInput,
        "Password does not meet the requirements.",
      );
    }
    if (pw.length > 72) {
      return setFieldError(
        passwordInput,
        "Password must be 72 characters or fewer.",
      );
    }
    return setFieldValid(passwordInput, "");
  }

  passwordInput.addEventListener("input", function () {
    const pw = passwordInput.value;
    markRule(passwordLength, pw.length >= 8, false);
    markRule(
      passwordLettersNumbers,
      /[A-Za-z]/.test(pw) && /\d/.test(pw),
      false,
    );
    if (passwordInput.classList.contains("is-invalid"))
      setFieldValid(passwordInput, "");
    if (confirmPasswordInput.value) validateConfirmPassword();
    clearStatus();
  });

  function validateConfirmPassword() {
    if (!confirmPasswordInput.value) {
      return setFieldError(
        confirmPasswordInput,
        "Please confirm your password.",
      );
    }

    if (confirmPasswordInput.value !== passwordInput.value) {
      return setFieldError(confirmPasswordInput, "Passwords do not match.");
    }

    return setFieldValid(confirmPasswordInput, "");
  }

  confirmPasswordInput.addEventListener("input", function () {
    validateConfirmPassword();
    clearStatus();
  });

  // ========================================
  // LIVE FEEDBACK FOR THE OTHER FIELDS
  // (these must live HERE, not inside the submit handler)
  // ========================================

  // Clear a field's error as soon as the user edits it
  form.querySelectorAll("input").forEach(function (input) {
    if (
      input === emailInput ||
      input === passwordInput ||
      input === confirmPasswordInput
    )
      return;

    input.addEventListener("input", function () {
      if (input.classList.contains("is-invalid")) setFieldValid(input, "");
      clearStatus();
    });
  });

  // Re-check phone numbers when the user leaves the field
  [["mobile_number", "Mobile number"]].forEach(function (item) {
    const field = document.getElementById(item[0]);
    if (!field) return;

    field.addEventListener("blur", function () {
      if (field.value.trim()) checkPhone(field, item[1]);
    });
  });

  // ========================================
  // VALIDATE THE REGISTRATION FORM
  // ========================================

  function validateForm() {
    let valid = true;

    // Require agreement to the Terms and Agreement
    const termsContainer = agreeTerms.closest(".terms-agreement");

    if (!agreeTerms.checked) {
      termsContainer.classList.add("is-invalid");
      termsError.textContent =
        "You must agree to the Terms and Conditions before creating an account.";
      valid = false;
    } else {
      termsContainer.classList.remove("is-invalid");
      termsError.textContent = "";
    }

    const requiredFields = [
      ["first_name", "First name"],
      ["last_name", "Last name"],
      ["municipality", "Municipality"],
      ["barangay", "Barangay"],
      ["address", "Home address"],
    ];

    requiredFields.forEach(function (item) {
      const field = document.getElementById(item[0]);

      if (field && !validateRequired(field, item[1])) {
        valid = false;
      }
    });

    // Mobile number must be a valid Philippine mobile number.
    [["mobile_number", "Mobile number"]].forEach(function (item) {
      const field = document.getElementById(item[0]);

      if (field && !checkPhone(field, item[1])) {
        valid = false;
      }
    });

    if (!validateEmailFormat()) valid = false;
    if (!validatePassword()) valid = false;
    if (!validateConfirmPassword()) valid = false;

    return valid;
  }

  agreeTerms.addEventListener("change", function () {
    if (agreeTerms.checked) {
      agreeTerms.closest(".terms-agreement").classList.remove("is-invalid");
      termsError.textContent = "";
    }
  });

  // ========================================
  // SUBMIT REGISTRATION TO PHP
  // ========================================

  form.addEventListener("submit", async function (event) {
    event.preventDefault();
    clearStatus();

    if (!validateForm()) {
      showStatus("Please correct the highlighted fields.", "danger");

      const firstInvalid = form.querySelector(".is-invalid");
      if (firstInvalid) firstInvalid.focus();

      return;
    }

    // Check the email again, but only stop if the server says it is taken.
    // If the check could not run (null), the PHP checks again during registration.
    const available = await checkEmailAvailability();

    if (available === false) {
      showStatus(
        "That email cannot be used. Please enter a different email address.",
        "danger",
      );
      emailInput.focus();
      return;
    }

    // Send clean values without changing what the user sees
    const formData = new FormData(form);
    formData.set("email", emailInput.value.trim().toLowerCase());
    formData.set(
      "mobile_number",
      normalizeMobile(form.elements["mobile_number"].value),
    );

    submitBtn.disabled = true;
    submitBtn.textContent = "Creating account...";
    let redirecting = false;

    try {
      const response = await fetch("../views/register_client.php", {
        method: "POST",
        body: formData,
      });

      // Read as text first so a PHP warning doesn't hide the real problem
      const rawText = await response.text();
      let result;

      try {
        result = JSON.parse(rawText);
      } catch (parseError) {
        console.error("Server replied with:", rawText);
        showStatus(
          "The server sent an unexpected reply. Press F12 and read the Console.",
          "danger",
        );
        return;
      }

      if (!response.ok || !result.success) {
        showStatus(
          result.message || "Registration failed. Please try again.",
          "danger",
        );

        if (result.field === "email") {
          emailAvailable = false;
        }

        // Show the error under the field the server named
        const badField = result.field ? form.elements[result.field] : null;
        if (badField) {
          setFieldError(badField, result.message);
          badField.focus();
        }

        return;
      }

      redirecting = true;

      showStatus(result.message || "Account created successfully!", "success");
      submitBtn.textContent = "Account created";

      form.reset();
      emailAvailable = null;

      form.querySelectorAll(".is-valid, .is-invalid").forEach(function (field) {
        field.classList.remove("is-valid", "is-invalid");
        field.setCustomValidity("");
      });

      // Redirect to the login page after successful registration.
      setTimeout(function () {
        window.location.href = "../index.html?registered=1";
      }, 1500);
    } catch (error) {
      console.error("Registration request failed:", error);
      showStatus(
        "Unable to connect to the server. Make sure XAMPP Apache and MySQL are running.",
        "danger",
      );
    } finally {
      // Keep the button disabled while redirecting so it cannot be submitted twice
      if (!redirecting) {
        submitBtn.disabled = false;
        submitBtn.textContent = "Create account";
      }
    }
  });

  /* TERMS MODAL */
  const termsModalElement = document.getElementById("termsModal");
  const termsOpenLink = document.getElementById("openTermsModal");
  const termsCloseButton = document.getElementById("closeTermsModal");

  if (termsModalElement && termsOpenLink && termsCloseButton) {
    function showTermsModal() {
      termsModalElement.classList.add("show");
      termsModalElement.setAttribute("aria-hidden", "false");
      document.body.classList.add("terms-modal-open");
      termsCloseButton.focus();

      document.addEventListener("keydown", function (e) {
        if (
          e.key === "Escape" &&
          termsModalElement.classList.contains("show")
        ) {
          hideTermsModal();
        }
      });
    }

    function hideTermsModal() {
      // Move focus out BEFORE hiding the modal
      termsOpenLink.focus();
      termsModalElement.classList.remove("show");
      termsModalElement.setAttribute("aria-hidden", "true");
      document.body.classList.remove("terms-modal-open");

      termsModalElement.addEventListener("click", function (e) {
        if (e.target === termsModalElement) hideTermsModal();
      });
    }

    termsOpenLink.addEventListener("click", function (event) {
      event.preventDefault();
      showTermsModal();
    });

    // Close only using the X button
    termsCloseButton.addEventListener("click", hideTermsModal);
  }
});
