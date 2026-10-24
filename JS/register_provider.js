(function () {
  "use strict";

  var form = document.getElementById("providerForm");

  if (!form) {
    return;
  }

  // Use our own validation messages instead of browser popups.
  form.noValidate = true;

  var statusBox = document.getElementById("status");
  var submitBtn = form.querySelector('button[type="submit"]');

  var clearanceBox = document.getElementById("clearanceBox");
  var clearanceType = document.getElementById("clearance_type");
  var clearanceFile = document.getElementById("clearance_file");

  var serviceBoxes = form.querySelectorAll('input[name="services[]"]');
  var servicesError = document.getElementById("servicesError");

  // Terms and Agreement
  var termsModal = document.getElementById("termsModal");
  var openTerms = document.getElementById("openTerms");
  var openTermsError = document.getElementById("openTermsError");
  var closeTerms = document.getElementById("closeTerms");
  var agreeTerms = document.getElementById("agreeTerms");
  var termsError = document.getElementById("termsError");

  // Password fields
  var passwordInput = document.getElementById("password");
  var confirmPasswordInput = document.getElementById("confirm_password");
  var passwordMatchMsg = document.getElementById("passwordMatchMsg");

  // Email validation
  var emailInput = document.getElementById("email");
  var emailMsg = document.getElementById("emailMsg");
  var emailTaken = false;
  var lastChecked = "";
  var checkUrl = "../views/check_email.php";

  var MAX_BYTES = 5 * 1024 * 1024;
  var ALLOWED_EXT = ["jpg", "jpeg", "png", "pdf"];

  /* ---------- status messages ---------- */

  function showStatus(message, type) {
    if (!statusBox) {
      return;
    }

    statusBox.textContent = message;
    statusBox.className = "status show " + type;

    statusBox.scrollIntoView({
      behavior: "smooth",
      block: "center",
    });
  }

  function clearStatus() {
    if (statusBox) {
      statusBox.textContent = "";
      statusBox.className = "status";
    }
  }

  /* ---------- inline field errors (all shown at once) ---------- */

  function errorBoxFor(field, create) {
    var box = document.getElementById("err_" + field.id);

    if (!box && create) {
      box = document.createElement("small");
      box.id = "err_" + field.id;
      box.className = "field-msg error";
      var anchor = field.closest(".pw-wrap") || field;
      anchor.insertAdjacentElement("afterend", box);
    }

    return box;
  }

  function setError(field, message, inline) {
    field.classList.add("is-invalid");

    if (inline !== false && field.id) {
      errorBoxFor(field, true).textContent = message;
    }
  }

  function clearError(field) {
    field.classList.remove("is-invalid");

    if (field.id) {
      var box = errorBoxFor(field, false);
      if (box) {
        box.textContent = "";
      }
    }
  }

  function getFieldLabel(field) {
    var label = field.id
      ? form.querySelector('label[for="' + field.id + '"]')
      : null;

    return label
      ? label.textContent
          .replace(/\(optional\)/i, "")
          .replace(/\s+/g, " ")
          .trim()
      : field.getAttribute("aria-label") || "This field";
  }

  var requiredControls = form.querySelectorAll(
    "input[required], select[required], textarea[required]",
  );

  for (var r = 0; r < requiredControls.length; r++) {
    (function (field) {
      if (field === agreeTerms) {
        return;
      }

      field.addEventListener("input", function () {
        if (field.checkValidity()) {
          clearError(field);
        }
      });

      field.addEventListener("change", function () {
        if (field.checkValidity()) {
          clearError(field);
        }
      });
    })(requiredControls[r]);
  }

  /* ---------- complete form validation (collects ALL errors) ---------- */

  function validate() {
    var firstBad = null;
    var extras = [];

    function add(field, message, inline) {
      setError(field, message, inline);

      if (inline === false) {
        extras.push(message);
      }

      if (!firstBad) {
        firstBad = field;
      }
    }

    // Reset previous errors
    servicesError.textContent = "";
    form.querySelectorAll(".is-invalid").forEach(clearError);

    // 1. Every required field at once
    var required = form.querySelectorAll(
      "input[required], select[required], textarea[required]",
    );

    for (var i = 0; i < required.length; i++) {
      var field = required[i];

      if (
        field.disabled ||
        field === agreeTerms ||
        field === barangaySelect ||
        field.name === "services[]" ||
        (clearanceBox &&
          clearanceBox.contains(field) &&
          clearanceBox.classList.contains("is-hidden"))
      ) {
        continue;
      }

      var label = getFieldLabel(field);

      if (field.validity.valueMissing) {
        add(field, label + " is required.", field.id ? true : false);
      } else if (!field.checkValidity()) {
        add(field, "Please enter a valid " + label.toLowerCase() + ".");
      }
    }

    // 2. Location: barangay depends on municipality
    if (!locationInput.value) {
      add(barangaySelect, "Barangay is required.");
    }

    // 3. Email
    if (emailTaken) {
      add(emailInput, "This email is already registered.");
    }

    // 4. Passwords
    if (
      passwordInput.value &&
      passwordInput.value.length < 8 &&
      !passwordInput.classList.contains("is-invalid")
    ) {
      add(passwordInput, "Password must be at least 8 characters.");
    }

    if (
      confirmPasswordInput.value &&
      passwordInput.value !== confirmPasswordInput.value
    ) {
      add(confirmPasswordInput, "Passwords do not match.");
    }

    // 5. Services and rates
    var picked = 0;

    for (var s = 0; s < serviceBoxes.length; s++) {
      if (!serviceBoxes[s].checked) {
        continue;
      }

      picked++;

      var rate = rateInputFor(serviceBoxes[s]);
      var rateValue = rate ? Number(rate.value) : NaN;

      if (rate && rate.value.trim() !== "" && !(rateValue > 0)) {
        add(
          rate,
          "Enter a rate greater than 0 for every service you offer.",
          false,
        );
      }
    }

    if (picked === 0) {
      servicesError.textContent =
        "Please choose at least one service you offer.";

      if (!firstBad) {
        firstBad = serviceBoxes[0];
      }
    }

    // 6. Files
    var govId = document.getElementById("government_id");
    var msg = fileProblem(govId, "Government ID");

    if (msg) {
      add(govId, msg);
    }

    msg = fileProblem(clearanceFile, "Clearance file");

    if (msg) {
      add(clearanceFile, msg);
    }

    // 7. Terms
    if (!agreeTerms.checked) {
      termsError.hidden = false;
      agreeTerms.classList.add("is-invalid");

      if (!firstBad) {
        firstBad = agreeTerms;
      }
    } else {
      termsError.hidden = true;
    }

    if (firstBad) {
      showStatus(
        "Please complete the required fields highlighted below." +
          (extras.length ? " " + extras.join(" ") : ""),
        "error",
      );

      try {
        firstBad.focus({ preventScroll: true });
      } catch (error) {
        firstBad.focus();
      }

      return false;
    }

    return true;
  }

  /* ---------- Terms and Agreement popup ---------- */

  function showTerms() {
    if (!termsModal) {
      showStatus("The Terms and Agreement popup could not be found.", "error");
      return;
    }

    termsModal.hidden = false;
    document.body.classList.add("modal-open");
  }

  function hideTerms() {
    if (!termsModal) {
      return;
    }

    termsModal.hidden = true;
    document.body.classList.remove("modal-open");
  }

  if (openTerms) {
    openTerms.addEventListener("click", function (event) {
      event.preventDefault();
      showTerms();
    });
  }

  if (openTermsError) {
    openTermsError.addEventListener("click", function (event) {
      event.preventDefault();
      showTerms();
    });
  }

  if (closeTerms) {
    closeTerms.addEventListener("click", hideTerms);
  }

  if (termsModal) {
    termsModal.addEventListener("click", function (event) {
      if (event.target === termsModal) {
        hideTerms();
      }
    });
  }

  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape" && termsModal && !termsModal.hidden) {
      hideTerms();
    }
  });

  if (agreeTerms) {
    agreeTerms.addEventListener("change", function () {
      if (agreeTerms.checked) {
        agreeTerms.classList.remove("is-invalid");

        if (termsError) {
          termsError.hidden = true;
        }

        clearStatus();
      }
    });
  }

  /* ---------- location: Province (fixed) → Municipality → Barangay ---------- */

  var municipalitySelect = document.getElementById("municipality");
  var barangaySelect = document.getElementById("barangay");
  var locationInput = document.getElementById("location");
  var PROVINCE = "Siquijor";

  function resetBarangays() {
    barangaySelect.innerHTML =
      '<option value="">Select municipality first</option>';
    barangaySelect.disabled = true;
    barangaySelect.classList.remove("is-invalid");
    locationInput.value = "";
  }

  Object.keys(window.SIQUIJOR_BARANGAYS).forEach(function (municipality) {
    municipalitySelect.add(new Option(municipality, municipality));
  });

  municipalitySelect.addEventListener("change", function () {
    var municipality = municipalitySelect.value;
    resetBarangays();

    if (!municipality) {
      return;
    }

    barangaySelect.innerHTML = '<option value="">Select barangay</option>';
    window.SIQUIJOR_BARANGAYS[municipality].forEach(function (name) {
      barangaySelect.add(new Option(name, name));
    });
    barangaySelect.disabled = false;
  });

  barangaySelect.addEventListener("change", function () {
    locationInput.value = barangaySelect.value
      ? barangaySelect.value + ", " + municipalitySelect.value + ", " + PROVINCE
      : "";
  });

  form.addEventListener("reset", function () {
    setTimeout(resetBarangays, 0);
  });

  /* ---------- services, rates, and clearance ---------- */

  function rateInputFor(box) {
    return form.querySelector('input[name="rate[' + box.value + ']"]');
  }

  function toggleClearance(show) {
    if (!clearanceBox || !clearanceType || !clearanceFile) {
      return;
    }

    if (show) {
      clearanceBox.classList.remove("is-hidden");
    } else {
      clearanceBox.classList.add("is-hidden");
      clearanceType.value = "";
      clearanceFile.value = "";

      clearanceType.classList.remove("is-invalid");
      clearanceFile.classList.remove("is-invalid");
    }

    clearanceType.required = show;
    clearanceFile.required = show;
  }

  if (clearanceType) {
    clearanceType.addEventListener("change", function () {
      if (clearanceType.value) {
        clearError(clearanceType);
      }
    });
  }

  if (clearanceFile) {
    clearanceFile.addEventListener("change", function () {
      if (clearanceFile.files && clearanceFile.files.length) {
        clearError(clearanceFile);
      }
    });
  }

  function syncServices() {
    var needsClearance = false;

    for (var i = 0; i < serviceBoxes.length; i++) {
      var box = serviceBoxes[i];
      var rate = rateInputFor(box);

      if (rate) {
        rate.disabled = !box.checked;
        rate.required = box.checked;

        if (!box.checked) {
          rate.value = "";
          rate.classList.remove("is-invalid");
        }
      }

      if (box.checked && box.getAttribute("data-needs-clearance") === "true") {
        needsClearance = true;
      }
    }

    toggleClearance(needsClearance);
  }

  for (var s = 0; s < serviceBoxes.length; s++) {
    serviceBoxes[s].addEventListener("change", function () {
      this.classList.remove("is-invalid");
      servicesError.textContent = "";
      syncServices();
      clearStatus();
    });
  }

  syncServices();

  /* ---------- show / hide password ---------- */

  function resetPasswordToggles() {
    var buttons = form.querySelectorAll(".pw-toggle");

    for (var i = 0; i < buttons.length; i++) {
      var target = document.getElementById(
        buttons[i].getAttribute("data-target"),
      );

      if (target) {
        target.type = "password";
      }

      buttons[i].classList.remove("is-shown");
      buttons[i].setAttribute("aria-pressed", "false");
      buttons[i].setAttribute("aria-label", "Show password");
    }
  }

  var pwButtons = form.querySelectorAll(".pw-toggle");

  for (var p = 0; p < pwButtons.length; p++) {
    pwButtons[p].addEventListener("click", function () {
      var input = document.getElementById(this.getAttribute("data-target"));

      if (!input) {
        return;
      }

      var show = input.type === "password";

      input.type = show ? "text" : "password";

      this.classList.toggle("is-shown", show);
      this.setAttribute("aria-pressed", show ? "true" : "false");
      this.setAttribute("aria-label", show ? "Hide password" : "Show password");

      input.focus();
    });
  }

  /* ---------- password confirmation ---------- */

  function checkPasswordMatch() {
    if (!passwordInput || !confirmPasswordInput) {
      return true;
    }

    if (confirmPasswordInput.value === "") {
      confirmPasswordInput.setCustomValidity("");

      if (passwordMatchMsg) {
        passwordMatchMsg.textContent = "";
        passwordMatchMsg.className = "field-msg";
      }

      return true;
    }

    var matches = passwordInput.value === confirmPasswordInput.value;

    if (!matches) {
      confirmPasswordInput.setCustomValidity("Passwords do not match.");

      if (passwordMatchMsg) {
        passwordMatchMsg.textContent = "Passwords do not match.";
        passwordMatchMsg.className = "field-msg error";
      }

      return false;
    }

    confirmPasswordInput.setCustomValidity("");

    if (passwordMatchMsg) {
      passwordMatchMsg.textContent = "Passwords match.";
      passwordMatchMsg.className = "field-msg success";
    }

    return true;
  }

  if (passwordInput && confirmPasswordInput) {
    passwordInput.addEventListener("input", checkPasswordMatch);
    confirmPasswordInput.addEventListener("input", checkPasswordMatch);
  }

  /* ---------- duplicate-email check ---------- */

  function clearEmailState() {
    emailTaken = false;

    if (emailMsg) {
      emailMsg.textContent = "";
      emailMsg.className = "field-msg";
    }

    if (emailInput) {
      emailInput.classList.remove("is-invalid");
    }
  }

  function checkEmail() {
    if (!emailInput) {
      return Promise.resolve();
    }

    var value = emailInput.value.trim().toLowerCase();

    if (value === "" || !emailInput.checkValidity() || value === lastChecked) {
      return Promise.resolve();
    }

    lastChecked = value;

    var body = new FormData();
    body.append("email", value);

    return fetch(checkUrl, {
      method: "POST",
      body: body,
      credentials: "same-origin",
    })
      .then(function (response) {
        if (!response.ok) {
          throw new Error("Email check failed.");
        }

        return response.json();
      })
      .then(function (data) {
        // Ignore an outdated response.
        if (emailInput.value.trim().toLowerCase() !== value) {
          return;
        }

        if (data && data.success === true && data.available === false) {
          emailTaken = true;
          emailInput.classList.add("is-invalid");

          if (emailMsg) {
            emailMsg.textContent =
              data.message ||
              "This email is already registered. Please use another email.";

            emailMsg.className = "field-msg error";
          }
        } else {
          clearEmailState();
        }
      })
      .catch(function () {
        // The server must still check duplicate emails during registration.
      });
  }

  if (emailInput) {
    emailInput.addEventListener("blur", checkEmail);

    emailInput.addEventListener("input", function () {
      lastChecked = "";
      clearEmailState();
      clearError(emailInput);
    });
  }

  /* ---------- file validation ---------- */

  function fileProblem(input, label) {
    if (!input) {
      return label + " field could not be found.";
    }

    var file = input.files && input.files[0];

    if (!file) {
      return "";
    }

    var ext = file.name.split(".").pop().toLowerCase();

    if (ALLOWED_EXT.indexOf(ext) === -1) {
      return label + " must be a JPG, JPEG, PNG, or PDF file.";
    }

    if (file.size > MAX_BYTES) {
      return label + " must be 5 MB or smaller.";
    }

    return "";
  }

  /* ---------- form submission ---------- */

  form.addEventListener("submit", function (event) {
    event.preventDefault();
    clearStatus();

    if (!validate()) {
      return;
    }

    if (!submitBtn) {
      showStatus("The submit button could not be found.", "error");
      return;
    }

    // Check email again before submitting if it has not been checked.
    var currentEmail = emailInput ? emailInput.value.trim().toLowerCase() : "";

    var emailCheck = Promise.resolve();

    if (emailInput && currentEmail && currentEmail !== lastChecked) {
      emailCheck = checkEmail();
    }

    submitBtn.disabled = true;

    var originalText = submitBtn.textContent;
    submitBtn.textContent = "Checking information...";

    emailCheck
      .then(function () {
        if (emailTaken) {
          throw new Error(
            "This email is already registered. Use another email or log in.",
          );
        }

        // Recheck the form after the email check.
        if (!validate()) {
          throw new Error("Please correct the highlighted fields.");
        }

        submitBtn.textContent = "Creating account...";

        return fetch(form.getAttribute("action"), {
          method: "POST",
          body: new FormData(form),
          credentials: "same-origin",
        });
      })
      .then(function (response) {
        if (!response) {
          return null;
        }

        return response.text();
      })
      .then(function (text) {
        if (text === null) {
          return;
        }

        var data;

        try {
          data = JSON.parse(text);
        } catch (error) {
          throw new Error("Unexpected server response.");
        }

        if (data.success) {
          showStatus(data.message || "Registration successful.", "success");

          form.reset();
          resetPasswordToggles();
          clearEmailState();
          lastChecked = "";
          syncServices();

          setTimeout(function () {
            window.location.href = data.redirect || "../index.html";
          }, 2000);
        } else {
          showStatus(
            data.message || "Registration failed. Please try again.",
            "error",
          );

          submitBtn.disabled = false;
          submitBtn.textContent = originalText;
        }
      })
      .catch(function (error) {
        showStatus(
          error.message || "Could not reach the server. Please try again.",
          "error",
        );

        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
      });
  });
})();
