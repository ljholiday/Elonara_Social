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

})();
