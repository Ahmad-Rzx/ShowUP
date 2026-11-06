/**
 * Asks for confirmation before a destructive form is submitted.
 * The form adds data-confirm="..." with the question to show.
 */
(function () {
  'use strict';

  var forms = document.querySelectorAll('form[data-confirm]');

  for (var i = 0; i < forms.length; i++) {
    forms[i].addEventListener('submit', function (event) {
      var message = this.getAttribute('data-confirm');

      if (message && !window.confirm(message)) {
        event.preventDefault();
      }
    });
  }
})();
