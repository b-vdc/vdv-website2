// Van der Volpi — cookie consent banner + reCAPTCHA loader (vanilla JS)
//
// The only non-essential thing this site loads is Google reCAPTCHA on the
// Contact page, so consent is a single accept/decline choice, stored in
// localStorage. The banner shows once; it can be reopened any time from the
// "Manage cookie preferences" button on the Cookie Policy page.

var VDV_CONSENT_KEY = "vdv-consent";
var VDV_RECAPTCHA_SITE_KEY = "6LcuFUotAAAAAKWtrUR8cGW_81Eu6BQLrr_-Bugp";
window.VDV_RECAPTCHA_SITE_KEY = VDV_RECAPTCHA_SITE_KEY;

function vdvGetConsent() {
  try {
    return window.localStorage.getItem(VDV_CONSENT_KEY);
  } catch (e) {
    return null;
  }
}

function vdvSetConsent(value) {
  try {
    window.localStorage.setItem(VDV_CONSENT_KEY, value);
  } catch (e) {
    // localStorage unavailable (private browsing, etc.) - consent just won't persist
  }
}

function vdvPageNeedsRecaptcha() {
  return !!(document.getElementById("contact-form") || document.getElementById("booking-form"));
}

function vdvLoadRecaptcha() {
  if (!vdvPageNeedsRecaptcha()) return;
  if (document.getElementById("vdv-recaptcha-script")) return;
  var script = document.createElement("script");
  script.id = "vdv-recaptcha-script";
  script.src = "https://www.google.com/recaptcha/api.js?render=" + VDV_RECAPTCHA_SITE_KEY;
  script.async = true;
  script.defer = true;
  document.head.appendChild(script);
}

function vdvRemoveCookieBanner() {
  var existing = document.getElementById("cookie-banner");
  if (existing) existing.parentNode.removeChild(existing);
}

function vdvOpenConsentBanner() {
  vdvRemoveCookieBanner();

  var banner = document.createElement("div");
  banner.id = "cookie-banner";
  banner.className = "cookie-banner";
  banner.setAttribute("role", "dialog");
  banner.setAttribute("aria-label", "Cookie preferences");

  var text = document.createElement("p");
  text.appendChild(document.createTextNode(
    "This site uses Google reCAPTCHA to keep the contact and booking forms spam free. It only runs once you say yes. See our "
  ));
  var link = document.createElement("a");
  link.href = "/cookie-policy";
  link.textContent = "cookie policy";
  text.appendChild(link);
  text.appendChild(document.createTextNode("."));

  var actions = document.createElement("div");
  actions.className = "cookie-banner-actions";

  var acceptBtn = document.createElement("button");
  acceptBtn.type = "button";
  acceptBtn.className = "btn btn-light";
  acceptBtn.textContent = "Accept";
  acceptBtn.addEventListener("click", function () {
    vdvSetConsent("accepted");
    vdvRemoveCookieBanner();
    vdvLoadRecaptcha();
  });

  var declineBtn = document.createElement("button");
  declineBtn.type = "button";
  declineBtn.className = "btn btn-outline-light";
  declineBtn.textContent = "Decline";
  declineBtn.addEventListener("click", function () {
    vdvSetConsent("declined");
    vdvRemoveCookieBanner();
  });

  actions.appendChild(acceptBtn);
  actions.appendChild(declineBtn);
  banner.appendChild(text);
  banner.appendChild(actions);
  document.body.appendChild(banner);
}

window.vdvGetConsent = vdvGetConsent;
window.vdvSetConsent = vdvSetConsent;
window.vdvLoadRecaptcha = vdvLoadRecaptcha;
window.vdvOpenConsentBanner = vdvOpenConsentBanner;

document.addEventListener("DOMContentLoaded", function () {
  var consent = vdvGetConsent();
  if (consent === "accepted") {
    vdvLoadRecaptcha();
  } else if (consent === null) {
    vdvOpenConsentBanner();
  }

  // Cookie Policy page: lets a visitor reopen the banner and change their mind.
  var manageBtn = document.getElementById("manage-cookie-prefs");
  if (manageBtn) {
    manageBtn.addEventListener("click", function () {
      vdvOpenConsentBanner();
    });
  }
});
