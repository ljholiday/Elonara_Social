/**
 * Event form enhancements
 * - Sets default time to 6pm (18:00) when date picker is first used
 * - Handles multi-day event logic
 */

(function() {
  'use strict';

  // Set default time to 6pm when event_date field is first interacted with
  const eventDateInput = document.getElementById('event_date');
  if (eventDateInput) {
    let hasSetDefault = false;

    eventDateInput.addEventListener('focus', function() {
      if (!hasSetDefault && !this.value) {
        // Get today's date at 6pm
        const now = new Date();
        now.setHours(18, 0, 0, 0);

        // Format for datetime-local input: YYYY-MM-DDTHH:MM
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');

        this.value = `${year}-${month}-${day}T${hours}:${minutes}`;
        hasSetDefault = true;
      }
    });
  }

  // Set default end time to 8pm when end_date field is first interacted with
  const endDateInput = document.getElementById('end_date');
  if (endDateInput && eventDateInput) {
    let hasSetEndDefault = false;

    endDateInput.addEventListener('focus', function() {
      if (!hasSetEndDefault && !this.value) {
        // If start date is set, use that date at 8pm
        // Otherwise use today at 8pm
        let baseDate;

        if (eventDateInput.value) {
          baseDate = new Date(eventDateInput.value);
        } else {
          baseDate = new Date();
        }

        baseDate.setHours(20, 0, 0, 0);

        // Format for datetime-local input
        const year = baseDate.getFullYear();
        const month = String(baseDate.getMonth() + 1).padStart(2, '0');
        const day = String(baseDate.getDate()).padStart(2, '0');
        const hours = String(baseDate.getHours()).padStart(2, '0');
        const minutes = String(baseDate.getMinutes()).padStart(2, '0');

        this.value = `${year}-${month}-${day}T${hours}:${minutes}`;
        hasSetEndDefault = true;
      }
    });
  }

  const recurrenceTypeSelect = document.getElementById('recurrence_type');
  const recurrenceSections = document.querySelectorAll('[data-recurrence-section]');
  const recurrenceIntervalSuffix = document.getElementById('recurrence_interval_suffix');
  const recurrenceDayCheckboxes = document.querySelectorAll('input[name="recurrence_days[]"]');
  const monthlyTypeInputs = document.querySelectorAll('input[name="monthly_type"]');
  const monthlyModeSections = document.querySelectorAll('[data-monthly-mode]');
  const monthlyDayInput = document.getElementById('monthly_day_number');
  const monthlyWeekSelect = document.getElementById('monthly_week');
  const monthlyWeekdaySelect = document.getElementById('monthly_weekday');
  const weekdayKeys = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

  function getRecurrenceType() {
    if (!recurrenceTypeSelect) {
      return 'none';
    }
    return recurrenceTypeSelect.value || 'none';
  }

  function updateIntervalVisibility(type) {
    recurrenceSections.forEach(function(section) {
      var shouldShow = false;
      var sectionKey = section.getAttribute('data-recurrence-section');
      if (sectionKey === 'interval') {
        shouldShow = type !== 'none';
      } else if (sectionKey === 'weekly') {
        shouldShow = type === 'weekly';
      } else if (sectionKey === 'monthly') {
        shouldShow = type === 'monthly';
      }
      if (shouldShow) {
        section.style.display = '';
        section.classList.remove('is-hidden');
      } else {
        section.style.display = 'none';
        section.classList.add('is-hidden');
      }
    });
  }

  function updateIntervalSuffix(type) {
    if (!recurrenceIntervalSuffix) {
      return;
    }
    var suffix = 'day(s)';
    if (type === 'weekly') {
      suffix = 'week(s)';
    } else if (type === 'monthly') {
      suffix = 'month(s)';
    }
    recurrenceIntervalSuffix.textContent = suffix;
  }

  function getMonthlyType() {
    var selected = null;
    monthlyTypeInputs.forEach(function(input) {
      if (input.checked) {
        selected = input.value;
      }
    });
    return selected || 'date';
  }

  function updateMonthlyModeSections(type) {
    monthlyModeSections.forEach(function(section) {
      var mode = section.getAttribute('data-monthly-mode');
      var shouldShow = type === 'monthly' && getMonthlyType() === mode;
      if (shouldShow) {
        section.style.display = '';
        section.classList.remove('is-hidden');
      } else {
        section.style.display = 'none';
        section.classList.add('is-hidden');
      }
    });
  }

  function ensureWeeklyDefaults() {
    if (!eventDateInput || getRecurrenceType() !== 'weekly' || recurrenceDayCheckboxes.length === 0) {
      return;
    }
    var hasChecked = Array.prototype.some.call(recurrenceDayCheckboxes, function(checkbox) {
      return checkbox.checked;
    });
    if (hasChecked) {
      return;
    }
    var baseDate = new Date(eventDateInput.value);
    if (Number.isNaN(baseDate.getTime())) {
      return;
    }
    var dayKey = weekdayKeys[baseDate.getDay()];
    recurrenceDayCheckboxes.forEach(function(checkbox) {
      if (checkbox.value === dayKey) {
        checkbox.checked = true;
      }
    });
  }

  function syncMonthlyDefaults() {
    if (!eventDateInput || getRecurrenceType() !== 'monthly') {
      return;
    }
    var baseDate = new Date(eventDateInput.value);
    if (Number.isNaN(baseDate.getTime())) {
      return;
    }
    var monthlyType = getMonthlyType();
    if (monthlyType === 'date' && monthlyDayInput) {
      if (monthlyDayInput.value === '') {
        monthlyDayInput.value = String(baseDate.getDate());
      }
    } else if (monthlyType === 'weekday') {
      if (monthlyWeekdaySelect && monthlyWeekdaySelect.value === '') {
        monthlyWeekdaySelect.value = weekdayKeys[baseDate.getDay()];
      }
      if (monthlyWeekSelect && monthlyWeekSelect.value === '') {
        var weekIndex = Math.ceil(baseDate.getDate() / 7);
        if (weekIndex >= 1 && weekIndex <= 4) {
          var weekLabels = ['first', 'second', 'third', 'fourth'];
          monthlyWeekSelect.value = weekLabels[weekIndex - 1];
        } else {
          monthlyWeekSelect.value = 'last';
        }
      }
    }
  }

  function updateRecurrenceUI() {
    var type = getRecurrenceType();
    updateIntervalVisibility(type);
    updateIntervalSuffix(type);
    updateMonthlyModeSections(type);
    ensureWeeklyDefaults();
    syncMonthlyDefaults();
  }

  if (recurrenceTypeSelect) {
    recurrenceTypeSelect.addEventListener('change', updateRecurrenceUI);
    updateRecurrenceUI();
  }

  if (monthlyTypeInputs.length > 0) {
    monthlyTypeInputs.forEach(function(input) {
      input.addEventListener('change', function() {
        updateMonthlyModeSections(getRecurrenceType());
        syncMonthlyDefaults();
      });
    });
  }

  if (eventDateInput) {
    eventDateInput.addEventListener('change', function() {
      ensureWeeklyDefaults();
      syncMonthlyDefaults();
    });
  }

})();
