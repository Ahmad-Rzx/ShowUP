/**
 * Shows the fields that belong to the selected event mode.
 * The server repeats this choice, so the page still works without JavaScript.
 */
(function () {
  'use strict';

  var form = document.getElementById('event-form');
  if (!form) {
    return;
  }

  var modeInputs = form.querySelectorAll('input[name="event_mode"]');
  var physicalFields = document.getElementById('physical-fields');
  var onlineFields = document.getElementById('online-fields');

  if (!physicalFields || !onlineFields) {
    return;
  }

  function showGroup(group, isActive) {
    group.hidden = !isActive;

    // Disabled fields are not submitted, so the request only contains the active mode.
    var fields = group.querySelectorAll('input, select, textarea');
    for (var i = 0; i < fields.length; i++) {
      fields[i].disabled = !isActive;
    }
  }

  function updateMode() {
    var checked = form.querySelector('input[name="event_mode"]:checked');
    var mode = checked ? checked.value : 'physical';

    showGroup(physicalFields, mode === 'physical');
    showGroup(onlineFields, mode === 'online');
  }

  for (var i = 0; i < modeInputs.length; i++) {
    modeInputs[i].addEventListener('change', updateMode);
  }

  updateMode();
})();
