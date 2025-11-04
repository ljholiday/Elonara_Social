/**
 * Block functionality for Elonara Social
 * Handles blocking and unblocking users
 */

/**
 * Block a user
 * @param {number} userId - ID of the user to block
 * @param {string} userName - Display name of the user to block
 */
async function blockUser(userId, userName) {
  if (!userId || userId <= 0) {
    console.error('Invalid user ID');
    return;
  }

  // Show confirmation dialog
  const confirmed = confirm(
    `Are you sure you want to block ${userName}?\n\n` +
    `They will not be able to:\n` +
    `• See your posts and replies\n` +
    `• Send you messages\n` +
    `• Interact with your content`
  );

  if (!confirmed) {
    return;
  }

  try {
    // Get nonce from meta tag
    const nonceElement = document.querySelector('meta[name="csrf-token"]');
    const nonce = nonceElement ? nonceElement.content : '';

    const response = await fetch('/api/block', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        blocked_user_id: userId,
        nonce: nonce
      })
    });

    const data = await response.json();

    if (response.ok) {
      alert(`${userName} has been blocked successfully.`);

      // Reload the page to update the UI
      window.location.reload();
    } else {
      alert(`Failed to block user: ${data.error || 'Unknown error'}`);
    }
  } catch (error) {
    console.error('Error blocking user:', error);
    alert('An error occurred while blocking the user. Please try again.');
  }
}

/**
 * Unblock a user
 * @param {number} userId - ID of the user to unblock
 * @param {string} userName - Display name of the user to unblock
 */
async function unblockUser(userId, userName) {
  if (!userId || userId <= 0) {
    console.error('Invalid user ID');
    return;
  }

  // Show confirmation dialog
  const confirmed = confirm(
    `Are you sure you want to unblock ${userName}?\n\n` +
    `They will be able to see and interact with your content again.`
  );

  if (!confirmed) {
    return;
  }

  try {
    // Get nonce from meta tag
    const nonceElement = document.querySelector('meta[name="csrf-token"]');
    const nonce = nonceElement ? nonceElement.content : '';

    const response = await fetch('/api/unblock', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        blocked_user_id: userId,
        nonce: nonce
      })
    });

    const data = await response.json();

    if (response.ok) {
      alert(`${userName} has been unblocked successfully.`);

      // Reload the page to update the UI
      window.location.reload();
    } else {
      alert(`Failed to unblock user: ${data.error || 'Unknown error'}`);
    }
  } catch (error) {
    console.error('Error unblocking user:', error);
    alert('An error occurred while unblocking the user. Please try again.');
  }
}
