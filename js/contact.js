// Van der Volpi — contact form submit handling (vanilla JS)
//
// Loaded on the Contact page only. Depends on consent.js being loaded first
// (for vdvGetConsent / vdvSetConsent / vdvLoadRecaptcha).

document.addEventListener("DOMContentLoaded", function () {
  var form = document.getElementById("contact-form");
  if (!form) return;

  var statusEl = document.getElementById("form-status");
  var consentGate = document.getElementById("consent-gate");
  var consentAcceptBtn = document.getElementById("consent-gate-accept");
  var submitBtn = form.querySelector("button[type=submit]");

  function setStatus(message, tone) {
    if (!statusEl) return;
    statusEl.textContent = message;
    statusEl.className = "form-status" + (tone ? " form-status-" + tone : "");
  }

  function sendMessage(token) {
    var data = new URLSearchParams();
    data.set("name", form.elements["name"].value);
    data.set("email", form.elements["email"].value);
    data.set("message", form.elements["message"].value);
    data.set("company", form.elements["company"].value); // honeypot, always blank for real visitors
    data.set("recaptcha_token", token || "");

    return fetch("contact-submit.php", {
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
          .execute(siteKey, { action: "contact" })
          .then(resolve)
          .catch(function () {
            resolve("");
          });
      });
    });
  }

  form.addEventListener("submit", function (event) {
    event.preventDefault();

    if (window.vdvGetConsent && window.vdvGetConsent() !== "accepted") {
      if (consentGate) consentGate.hidden = false;
      return;
    }

    if (submitBtn) submitBtn.disabled = true;
    setStatus("Sending your message...", "");

    getRecaptchaToken()
      .then(sendMessage)
      .then(function (result) {
        if (result && result.ok) {
          setStatus("Thanks, your message is on its way.", "success");
          form.reset();
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
        if (submitBtn) submitBtn.disabled = false;
      });
  });

  if (consentAcceptBtn) {
    consentAcceptBtn.addEventListener("click", function () {
      if (window.vdvSetConsent) window.vdvSetConsent("accepted");
      if (window.vdvLoadRecaptcha) window.vdvLoadRecaptcha();
      if (consentGate) consentGate.hidden = true;
      setStatus("You can now send your message.", "");
    });
  }
});
