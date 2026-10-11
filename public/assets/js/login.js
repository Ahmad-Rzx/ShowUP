(function () {
  'use strict';

  var form = document.getElementById('login-form');
  if (!form) {
    return;
  }

  var fields = ['email', 'password'];

  function setError(name, message) {
    var input = form.elements[name];
    var errorBox = document.getElementById(name + '-error');

    if (!input || !errorBox) {
      return;
    }

    if (message) {
      input.setAttribute('aria-invalid', 'true');
      input.setAttribute('aria-describedby', name + '-error');
      errorBox.textContent = message;
    } else {
      input.removeAttribute('aria-invalid');
      errorBox.textContent = '';
    }
  }

  function validateEmail(value) {
    var trimmed = value.trim();
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(trimmed) && trimmed.length <= 120;
  }

  function validateField(name) {
    var input = form.elements[name];
    var value = input.value;
    var message = '';

    if (name === 'email') {
      if (value.trim() === '') {
        message = 'Email is required.';
      } else if (!validateEmail(value)) {
        message = 'Enter a valid email address.';
      }
    }

    if (name === 'password') {
      if (value === '') {
        message = 'Password is required.';
      }
    }

    setError(name, message);
    return message === '';
  }

  fields.forEach(function (name) {
    var input = form.elements[name];

    if (!input) {
      return;
    }

    input.addEventListener('blur', function () {
      validateField(name);
    });

    input.addEventListener('input', function () {
      if (input.getAttribute('aria-invalid') === 'true') {
        validateField(name);
      }
    });
  });

  var toggleButtons = form.querySelectorAll('[data-toggle-password]');
  toggleButtons.forEach(function (button) {
    button.addEventListener('click', function () {
      var targetId = button.getAttribute('data-target');
      var input = document.getElementById(targetId);

      if (!input) {
        return;
      }

      var showing = input.type === 'text';
      input.type = showing ? 'password' : 'text';
      button.textContent = showing ? 'Show' : 'Hide';
      button.setAttribute('aria-pressed', showing ? 'false' : 'true');
      input.focus();
    });
  });

  form.addEventListener('submit', function (event) {
    var allValid = true;

    fields.forEach(function (name) {
      if (!validateField(name)) {
        allValid = false;
      }
    });

    if (!allValid) {
      event.preventDefault();
      var firstInvalid = form.querySelector('[aria-invalid="true"]');
      if (firstInvalid) {
        firstInvalid.focus();
      }
    }
  });
})();
