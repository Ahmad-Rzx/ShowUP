/**
 * Signup form: friendly client-side checks. The server repeats every check
 * (signup.php), so this file is for convenience only, never for security.
 */
(function () {
  'use strict';

  var form = document.getElementById('signup-form');
  if (!form) { return; }

  var charLength = function (s) { return Array.from(s).length; };
  var byteLength = function (s) { return new TextEncoder().encode(s).length; };

  // One function per field. Each returns an error message, or '' when the value is fine.
  var rules = {
    full_name: function (v) {
      var n = charLength(v.trim());
      return (n < 2 || n > 100) ? 'Enter your full name (2 to 100 characters).' : '';
    },
    email: function (v) {
      var s = v.trim();
      var ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(s) && s.length <= 120;
      return ok ? '' : 'Enter a valid email address.';
    },
    city: function (v) {
      return charLength(v.trim()) > 80 ? 'City must be 80 characters or fewer.' : '';
    },
    password: function (v) {
      if (v.length < 8) { return 'Password must be at least 8 characters.'; }
      if (byteLength(v) > 72) { return 'Password can be at most 72 characters.'; }
      if (!/[A-Za-z]/.test(v) || !/\d/.test(v)) {
        return 'Password must contain at least one letter and one number.';
      }
      return '';
    },
    confirm_password: function (v) {
      return v !== form.elements.password.value ? 'Passwords do not match.' : '';
    }
  };

  function showError(name, message) {
    var input = form.elements[name];
    var box = document.getElementById(name + '-error');
    box.textContent = message;
    if (message) { input.setAttribute('aria-invalid', 'true'); }
    else { input.removeAttribute('aria-invalid'); }
  }

  function check(name) {
    var message = rules[name](form.elements[name].value);
    showError(name, message);
    return message === '';
  }

  Object.keys(rules).forEach(function (name) {
    var input = form.elements[name];
    // Check when the user leaves a field...
    input.addEventListener('blur', function () { check(name); });
    // ...and re-check while typing, but only once an error is already showing
    input.addEventListener('input', function () {
      if (input.getAttribute('aria-invalid') === 'true') { check(name); }
    });
  });

  // Keep "confirm password" in sync when the first password changes
  form.elements.password.addEventListener('input', function () {
    if (form.elements.confirm_password.value !== '') { check('confirm_password'); }
  });

  // Show / hide password buttons
  Array.prototype.forEach.call(form.querySelectorAll('.toggle-pw'), function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(btn.getAttribute('data-target'));
      var showing = input.type === 'text';
      input.type = showing ? 'password' : 'text';
      btn.textContent = showing ? 'Show' : 'Hide';
      btn.setAttribute('aria-pressed', showing ? 'false' : 'true');
    });
  });

  // On submit: stop if anything is wrong, otherwise stop double-clicks
  form.addEventListener('submit', function (event) {
    var firstBad = null;
    Object.keys(rules).forEach(function (name) {
      if (!check(name) && firstBad === null) { firstBad = name; }
    });
    if (firstBad !== null) {
      event.preventDefault();
      form.elements[firstBad].focus();
      return;
    }
    var button = document.getElementById('signup-submit');
    button.disabled = true;
    button.textContent = 'Creating account...';
  });
})();
