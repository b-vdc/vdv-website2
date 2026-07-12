// Van der Volpi — appointment booker (vanilla JS)
//
// Loaded on the Contact page only. Depends on consent.js being loaded first
// (for vdvGetConsent / vdvSetConsent / vdvLoadRecaptcha). Availability comes
// from booking-slots.php; bookings go to booking-submit.php. All slot
// strings are wall-clock Brussels times produced by the server and are
// echoed back verbatim.

document.addEventListener("DOMContentLoaded", function () {
  var typeButtons = document.querySelectorAll("#booking-types .call-type");
  var booker = document.getElementById("booker");
  if (!typeButtons.length || !booker) return;

  var picker = document.getElementById("booking-picker");
  var daysEl = document.getElementById("booking-days");
  var timesEl = document.getElementById("booking-times");
  var form = document.getElementById("booking-form");
  var summaryEl = document.getElementById("booking-summary");
  var statusEl = document.getElementById("booking-status");
  var consentGate = document.getElementById("booking-consent-gate");
  var consentAcceptBtn = document.getElementById("booking-consent-accept");
  var successEl = document.getElementById("booking-success");
  var successSummaryEl = document.getElementById("booking-success-summary");
  var successMeetEl = document.getElementById("booking-success-meet");
  var submitBtn = form.querySelector("button[type=submit]");

  var state = {
    type: null,
    typeLabel: "",
    price: "",
    durationMinutes: 0,
    days: [],
    selectedDate: null,
    selectedDayLabel: "",
    selectedSlot: null,
    selectedTimeLabel: ""
  };

  function setStatus(message, tone) {
    statusEl.textContent = message;
    statusEl.className = "form-status" + (tone ? " form-status-" + tone : "");
  }

  function addRetryButton() {
    var retry = document.createElement("button");
    retry.type = "button";
    retry.className = "btn btn-outline booker-retry";
    retry.textContent = "Try again";
    retry.addEventListener("click", function () {
      fetchSlots(state.type);
    });
    statusEl.appendChild(retry);
  }

  function clearSelection() {
    state.selectedDate = null;
    state.selectedSlot = null;
    daysEl.innerHTML = "";
    timesEl.innerHTML = "";
    form.hidden = true;
  }

  function summaryText() {
    return state.typeLabel + " on " + state.selectedDayLabel + " at " + state.selectedTimeLabel +
      " (" + state.durationMinutes + " minutes, " + state.price + ")";
  }

  function fetchSlots(type, notice) {
    clearSelection();
    setStatus("Loading available times...", "");

    fetch("booking-slots.php?type=" + encodeURIComponent(type))
      .then(function (response) {
        return response.json().catch(function () {
          return { ok: false };
        });
      })
      .then(function (result) {
        if (!result || !result.ok) {
          setStatus(
            (result && result.error) || "Could not load availability right now. Please try again in a moment.",
            "error"
          );
          addRetryButton();
          return;
        }
        state.days = result.days || [];
        state.durationMinutes = result.durationMinutes;

        var hasSlots = state.days.some(function (day) { return day.slots.length > 0; });
        if (!hasSlots) {
          setStatus("No open times in the next four weeks. Email info@vandervolpi.com or call +32 474 055 052 and we'll find one.", "");
          return;
        }

        setStatus(notice || "", notice ? "error" : "");
        renderDays();
      })
      .catch(function () {
        setStatus("Could not load availability right now. Please try again in a moment.", "error");
        addRetryButton();
      });
  }

  function renderDays() {
    daysEl.innerHTML = "";
    var firstAvailable = null;

    state.days.forEach(function (day) {
      var btn = document.createElement("button");
      btn.type = "button";
      btn.className = "slot-chip day-chip";
      btn.textContent = day.label;
      btn.disabled = day.slots.length === 0;
      btn.setAttribute("aria-pressed", "false");
      btn.addEventListener("click", function () {
        selectDay(day, btn);
      });
      daysEl.appendChild(btn);
      if (!firstAvailable && day.slots.length > 0) {
        firstAvailable = { day: day, btn: btn };
      }
    });

    if (firstAvailable) {
      selectDay(firstAvailable.day, firstAvailable.btn);
    }
  }

  function selectDay(day, btn) {
    state.selectedDate = day.date;
    state.selectedDayLabel = day.label;
    state.selectedSlot = null;
    form.hidden = true;

    var chips = daysEl.querySelectorAll(".day-chip");
    for (var i = 0; i < chips.length; i++) {
      chips[i].classList.remove("is-selected");
      chips[i].setAttribute("aria-pressed", "false");
    }
    btn.classList.add("is-selected");
    btn.setAttribute("aria-pressed", "true");

    renderTimes(day);
  }

  function renderTimes(day) {
    timesEl.innerHTML = "";
    day.slots.forEach(function (slot) {
      var btn = document.createElement("button");
      btn.type = "button";
      btn.className = "slot-chip time-chip";
      btn.textContent = slot.label;
      btn.setAttribute("aria-pressed", "false");
      btn.addEventListener("click", function () {
        selectTime(slot, btn);
      });
      timesEl.appendChild(btn);
    });
  }

  function selectTime(slot, btn) {
    state.selectedSlot = slot.start;
    state.selectedTimeLabel = slot.label;

    var chips = timesEl.querySelectorAll(".time-chip");
    for (var i = 0; i < chips.length; i++) {
      chips[i].classList.remove("is-selected");
      chips[i].setAttribute("aria-pressed", "false");
    }
    btn.classList.add("is-selected");
    btn.setAttribute("aria-pressed", "true");

    summaryEl.textContent = summaryText();
    setStatus("", "");
    form.hidden = false;
    form.scrollIntoView({ behavior: "smooth", block: "nearest" });
  }

  function sendBooking(token) {
    var data = new URLSearchParams();
    data.set("name", form.elements["name"].value);
    data.set("email", form.elements["email"].value);
    data.set("phone", form.elements["phone"].value);
    data.set("message", form.elements["message"].value);
    data.set("type", state.type);
    data.set("slot", state.selectedSlot);
    data.set("recaptcha_token", token || "");

    return fetch("booking-submit.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: data.toString()
    }).then(function (response) {
      return response.json().catch(function () {
        return { ok: false };
      });
    });
  }

  function getRecaptchaToken() {
    var siteKey = window.VDV_RECAPTCHA_SITE_KEY;
    if (!window.grecaptcha || !siteKey) {
      // Not loaded (e.g. disabled for local testing) - the server falls back to its own check.
      return Promise.resolve("");
    }
    return new Promise(function (resolve) {
      window.grecaptcha.ready(function () {
        window.grecaptcha
          .execute(siteKey, { action: "book" })
          .then(resolve)
          .catch(function () {
            resolve("");
          });
      });
    });
  }

  function showSuccess(result) {
    picker.hidden = true;
    form.hidden = true;
    setStatus("", "");
    successSummaryEl.textContent = summaryText() + ".";
    if (result.meetLink) {
      successMeetEl.innerHTML = "";
      successMeetEl.appendChild(document.createTextNode("Google Meet link: "));
      var link = document.createElement("a");
      link.href = result.meetLink;
      link.textContent = result.meetLink;
      link.target = "_blank";
      link.rel = "noopener";
      successMeetEl.appendChild(link);
    }
    successEl.hidden = false;
  }

  typeButtons.forEach(function (btn) {
    btn.addEventListener("click", function () {
      typeButtons.forEach(function (other) {
        other.setAttribute("aria-pressed", "false");
      });
      btn.setAttribute("aria-pressed", "true");

      state.type = btn.getAttribute("data-type");
      state.typeLabel = btn.querySelector("h4").textContent;
      var priceText = btn.querySelector(".price").textContent;
      state.price = state.type === "legal" ? priceText : "free";

      booker.hidden = false;
      picker.hidden = false;
      successEl.hidden = true;
      consentGate.hidden = true;
      fetchSlots(state.type);
      booker.scrollIntoView({ behavior: "smooth", block: "nearest" });
    });
  });

  form.addEventListener("submit", function (event) {
    event.preventDefault();

    if (window.vdvGetConsent && window.vdvGetConsent() !== "accepted") {
      consentGate.hidden = false;
      return;
    }

    submitBtn.disabled = true;
    setStatus("Booking your call...", "");

    getRecaptchaToken()
      .then(sendBooking)
      .then(function (result) {
        if (result && result.ok) {
          showSuccess(result);
          form.reset();
        } else if (result && result.code === "slot_taken") {
          fetchSlots(state.type, (result.error || "That time was just taken. Pick another slot."));
        } else {
          setStatus(
            (result && result.error) || "Something went wrong. Please try again or email info@vandervolpi.com directly.",
            "error"
          );
        }
      })
      .catch(function () {
        setStatus("Something went wrong. Please try again or email info@vandervolpi.com directly.", "error");
      })
      .then(function () {
        submitBtn.disabled = false;
      });
  });

  if (consentAcceptBtn) {
    consentAcceptBtn.addEventListener("click", function () {
      if (window.vdvSetConsent) window.vdvSetConsent("accepted");
      if (window.vdvLoadRecaptcha) window.vdvLoadRecaptcha();
      consentGate.hidden = true;
      setStatus("You can now confirm your booking.", "");
    });
  }
});
