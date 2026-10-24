document.addEventListener("DOMContentLoaded", function () {
  var termsModal = document.getElementById("clientTermsModal");
  var openTermsButton = document.getElementById("openClientTerms");
  var closeTermsButton = document.getElementById("closeClientTerms");

  function closeClientTerms() {
    if (!termsModal) {
      return;
    }

    if (openTermsButton) {
      openTermsButton.focus();
    }

    termsModal.classList.remove("is-open");
    termsModal.setAttribute("aria-hidden", "true");
    document.body.classList.remove("cd-terms-open");
  }

  if (termsModal && openTermsButton && closeTermsButton) {
    openTermsButton.addEventListener("click", function () {
      termsModal.classList.add("is-open");
      termsModal.setAttribute("aria-hidden", "false");
      document.body.classList.add("cd-terms-open");
      closeTermsButton.focus();
    });

    closeTermsButton.addEventListener("click", closeClientTerms);

    termsModal.addEventListener("click", function (event) {
      if (event.target === termsModal) {
        closeClientTerms();
      }
    });

    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape" && termsModal.classList.contains("is-open")) {
        closeClientTerms();
      }
    });
  }

  // Use the full barangay list, regardless of provider availability.
  var municipalitySelect = document.getElementById("municipality");
  var barangaySelect = document.getElementById("barangay");
  var barangaysByMunicipality = window.SIQUIJOR_BARANGAYS;

  if (municipalitySelect && barangaySelect && barangaysByMunicipality) {
    function populateBarangays(selectedBarangay) {
      var municipality = municipalitySelect.value;
      barangaySelect.innerHTML = '<option value="">Any barangay</option>';

      if (!municipality || !barangaysByMunicipality[municipality]) {
        barangaySelect.disabled = true;
        return;
      }

      barangaysByMunicipality[municipality].forEach(function (name) {
        barangaySelect.add(new Option(name, name));
      });
      barangaySelect.disabled = false;

      if (
        selectedBarangay &&
        barangaysByMunicipality[municipality].indexOf(selectedBarangay) !== -1
      ) {
        barangaySelect.value = selectedBarangay;
      }
    }

    populateBarangays(barangaySelect.dataset.selected || "");
    municipalitySelect.addEventListener("change", function () {
      populateBarangays("");
    });
  }

  var profileMunicipalitySelect = document.getElementById("profile_municipality");
  var profileBarangaySelect = document.getElementById("profile_barangay");

  if (
    profileMunicipalitySelect &&
    profileBarangaySelect &&
    barangaysByMunicipality
  ) {
    var savedMunicipality =
      profileMunicipalitySelect.dataset.selected || "";
    var savedBarangay = profileBarangaySelect.dataset.selected || "";
    var municipalityOptions = Object.keys(barangaysByMunicipality);

    if (
      savedMunicipality &&
      municipalityOptions.indexOf(savedMunicipality) !== -1
    ) {
      municipalityOptions.splice(
        municipalityOptions.indexOf(savedMunicipality),
        1,
      );
      profileMunicipalitySelect.add(
        new Option(savedMunicipality + " (current)", savedMunicipality),
      );
    }

    municipalityOptions.forEach(function (municipality) {
      profileMunicipalitySelect.add(new Option(municipality, municipality));
    });

    function populateProfileBarangays(selectedBarangay, showCurrentFirst) {
      var municipality = profileMunicipalitySelect.value;
      profileBarangaySelect.innerHTML =
        '<option value="">Select barangay</option>';

      if (!municipality || !barangaysByMunicipality[municipality]) {
        profileBarangaySelect.disabled = true;
        return;
      }

      if (
        showCurrentFirst &&
        selectedBarangay &&
        barangaysByMunicipality[municipality].indexOf(selectedBarangay) !== -1
      ) {
        profileBarangaySelect.add(
          new Option(selectedBarangay + " (current)", selectedBarangay),
        );
      }

      barangaysByMunicipality[municipality].forEach(function (name) {
        if (showCurrentFirst && name === selectedBarangay) {
          return;
        }
        profileBarangaySelect.add(new Option(name, name));
      });
      profileBarangaySelect.disabled = false;

      if (
        selectedBarangay &&
        barangaysByMunicipality[municipality].indexOf(selectedBarangay) !== -1
      ) {
        profileBarangaySelect.value = selectedBarangay;
      }
    }

    profileMunicipalitySelect.value =
      savedMunicipality;
    populateProfileBarangays(savedBarangay, true);
    profileMunicipalitySelect.addEventListener("change", function () {
      populateProfileBarangays("", false);
    });
  }

  // Confirm before destructive actions (e.g. cancelling a booking)
  document.querySelectorAll("form[data-confirm]").forEach(function (form) {
    form.addEventListener("submit", function (ev) {
      if (!window.confirm(form.dataset.confirm)) {
        ev.preventDefault();
      }
    });
  });

  // Auto-hide success messages (e.g. "All notifications marked as read.") after 3 seconds
document.querySelectorAll(".cd-alert.ok").forEach(function (alertEl) {
  setTimeout(function () {
    alertEl.style.opacity = "0";
    setTimeout(function () {
      alertEl.remove();
    }, 400);
  }, 3000);
});
  // Live cost estimate on the booking form (the server recalculates the real total)
  var form = document.getElementById("booking-form");
  if (!form) {
    return;
  }
  var errEl = document.getElementById("time-error");
  var rate = parseFloat(form.dataset.rate || "0");
  var typeSel = document.getElementById("booking_type");
  var dateEl = document.getElementById("booking_date");
  var startTime = document.getElementById("start_time");
  var endTime = document.getElementById("end_time");
  var hoursEl = document.getElementById("cost-hours");
  var totalEl = document.getElementById("cost-total");
  var hintEl = document.getElementById("rule-hint");

  if (!typeSel || !dateEl || !startTime || !endTime || !hoursEl || !totalEl) {
    return;
  }

  // Mirrors the PHP rules. The server recalculates and is the authority.
  var DEDUCT = { hourly: "none", full_day: "always", urgent: "at9" };
  var HINTS = {
    hourly:
      "Hourly: from tomorrow onward, 1 to 8 hours, no deduction. For same-day help, choose Urgent.",
    full_day:
      "Daily: from tomorrow onward, 9 hours with 1 hour deducted (8 billable hours). Choose a start time and the end time fills in.",
    urgent:
      "Urgent: today only, 1 to 9 hours, starting from now. A 9-hour window deducts 1 hour; shorter windows have no deduction.",
  };
  var lastType = typeSel.value;

  function pesos(n) {
    return (
      "₱" +
      n.toLocaleString("en-PH", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      })
    );
  }
  function toMin(v) {
    var p = (v || "").split(":");
    return p.length === 2 ? parseInt(p[0], 10) * 60 + parseInt(p[1], 10) : NaN;
  }
  function hhmm(m) {
    return (
      String(Math.floor(m / 60)).padStart(2, "0") +
      ":" +
      String(m % 60).padStart(2, "0")
    );
  }
  function isoDate(d) {
    return (
      d.getFullYear() +
      "-" +
      String(d.getMonth() + 1).padStart(2, "0") +
      "-" +
      String(d.getDate()).padStart(2, "0")
    );
  }
  function todayIso() {
    return isoDate(new Date());
  }
  function tomorrowIso() {
    var d = new Date();
    d.setDate(d.getDate() + 1);
    return isoDate(d);
  }

  function update() {
    var type = typeSel.value;
    var isDaily = type === "full_day";

    if (lastType === "full_day" && !isDaily) {
      endTime.value = "";
    }
    lastType = type;

        // Date limits
    if (type === "urgent") {
      dateEl.min = todayIso();
      dateEl.max = todayIso();
      dateEl.value = todayIso();
    } else {
      dateEl.min = tomorrowIso();
      dateEl.removeAttribute("max");
      if (dateEl.value && dateEl.value < tomorrowIso()) {
        dateEl.value = "";
      }
    }

    // Start-time limits
    startTime.removeAttribute("max");
    startTime.removeAttribute("min");
    if (isDaily) {
      startTime.max = "14:59"; // 9 hours must end before midnight
    }
    if (type === "urgent") {
      var now = new Date();
      startTime.min = hhmm(now.getHours() * 60 + now.getMinutes()); // no start before the current time
    }

        // Block times that have already passed, or an end that is not after the start
    var msg = "";
    if (type === "urgent" && startTime.value) {
      var n = new Date();
      if (toMin(startTime.value) < n.getHours() * 60 + n.getMinutes()) {
        startTime.value = "";
        msg = "That start time has already passed. Choose the current time or later.";
      }
    }
    if (!isDaily && startTime.value && endTime.value && toMin(endTime.value) <= toMin(startTime.value)) {
      endTime.value = "";
      msg = msg || "The end time must be after the start time.";
    }
    if (errEl) {
      errEl.textContent = msg;
      errEl.hidden = msg === "";
    }

    // Daily: end time is always start + 9 hours
    endTime.readOnly = isDaily;
    if (isDaily) {
      var sm = toMin(startTime.value);
      endTime.value = isFinite(sm) && sm <= 14 * 60 + 59 ? hhmm(sm + 9 * 60) : "";
    }

    // Billable hours and total
    var s = toMin(startTime.value);
    var e = toMin(endTime.value);
    var h = isFinite(s) && isFinite(e) && e > s ? (e - s) / 60 : 0;
    if (DEDUCT[type] === "always" && h > 0) h -= 1;
    if (DEDUCT[type] === "at9" && h === 9) h -= 1;
    h = Math.round(h * 100) / 100;

    hoursEl.textContent = h + (h === 1 ? " hour" : " hours");
    totalEl.textContent = pesos(rate * h);
    if (hintEl) hintEl.textContent = HINTS[type] || "";
  }

  ["input", "change"].forEach(function (ev) {
    form.addEventListener(ev, update);
  });
  update();

    form.addEventListener("submit", function (ev) {
    update();
    if (!dateEl.value || !startTime.value || !endTime.value) {
      ev.preventDefault();
    }
  });
});