/**
 * Image Preview Module
 *
 * Provides instant image preview functionality for file uploads.
 * Works with file inputs that have a data-preview attribute.
 *
 * Usage:
 *   <input type="file" id="avatar" data-preview="avatar-preview">
 *   <img id="avatar-preview" src="" alt="">
 */

(function() {
    'use strict';

    /**
     * Initialize image preview for all file inputs with data-preview attribute
     */
    function initImagePreviews() {
        const fileInputs = document.querySelectorAll('input[type="file"][data-preview]');

        fileInputs.forEach(input => {
            input.addEventListener('change', handleFileSelect);
        });
    }

    /**
     * Handle file selection and show preview
     */
    function handleFileSelect(event) {
        const input = event.target;
        const previewId = input.getAttribute('data-preview');

        if (!previewId) {
            return;
        }

        const previewElement = document.getElementById(previewId);
        if (!previewElement) {
            console.warn(`Preview element with ID "${previewId}" not found`);
            return;
        }

        const file = input.files && input.files[0];

        if (!file) {
            // No file selected - could be a cancellation
            return;
        }

        // Validate file type
        if (!file.type.startsWith('image/')) {
            alert('Please select an image file (JPEG, PNG, GIF, or WebP).');
            input.value = '';
            return;
        }

        // Validate file size (10MB max)
        const maxSize = 10 * 1024 * 1024;
        if (file.size > maxSize) {
            alert('Image file must be less than 10MB.');
            input.value = '';
            return;
        }

        // Read and display the image
        const reader = new FileReader();

        reader.onload = function(e) {
            const imageUrl = e.target.result;

            if (previewElement.tagName === 'IMG') {
                previewElement.src = imageUrl;
                previewElement.style.display = 'block';
            } else if (previewElement.tagName === 'DIV') {
                // For avatar placeholders, replace with img
                const img = document.createElement('img');
                img.id = previewId;
                img.src = imageUrl;
                img.alt = 'Image preview';
                img.className = previewElement.className;
                previewElement.parentNode.replaceChild(img, previewElement);
            }
        };

        reader.onerror = function() {
            alert('Error reading file. Please try again.');
            input.value = '';
        };

        reader.readAsDataURL(file);
    }

    /**
     * Initialize on DOM ready
     */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initImagePreviews);
    } else {
        initImagePreviews();
    }

})();
