<!-- partials/rate_order_modal.php -->
<!-- Rate Your Order Modal with Image Upload -->
<style>
/* Rate Order Modal Styles */
.rate-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 10000;
    animation: fadeIn 0.2s ease-in-out;
}

.rate-modal-overlay.show {
    display: flex;
}

.rate-modal {
    background: white;
    border-radius: 20px;
    padding: 32px;
    max-width: 550px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.3);
    animation: slideUp 0.3s ease-out;
    position: relative;
}

.rate-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
}

.rate-modal-title {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 24px;
    font-weight: 700;
    color: #1e293b;
}

.rate-modal-title-icon {
    font-size: 28px;
}

.rate-modal-close {
    background: none;
    border: none;
    font-size: 28px;
    color: #64748b;
    cursor: pointer;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    transition: all 0.2s;
}

.rate-modal-close:hover {
    background: #f1f5f9;
    color: #1e293b;
}

.rate-order-info {
    background: #f8fafc;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 24px;
    border: 1px solid #e2e8f0;
}

.rate-order-code {
    font-size: 14px;
    color: #64748b;
    margin-bottom: 4px;
}

.rate-order-code strong {
    color: #1e293b;
    font-weight: 600;
}

.rate-order-items {
    font-size: 13px;
    color: #475569;
    line-height: 1.6;
}

.rate-stars-section {
    margin-bottom: 24px;
    text-align: center;
}

.rate-stars-label {
    font-size: 14px;
    color: #64748b;
    margin-bottom: 12px;
    font-weight: 500;
}

.rate-stars {
    display: flex;
    justify-content: center;
    gap: 8px;
}

.rate-star {
    font-size: 48px;
    color: #cbd5e1;
    cursor: pointer;
    transition: all 0.2s;
    user-select: none;
}

.rate-star:hover,
.rate-star.selected {
    color: #fbbf24;
    transform: scale(1.1);
}

.rate-star.selected {
    animation: starPop 0.3s ease-out;
}

@keyframes starPop {
    0% { transform: scale(1); }
    50% { transform: scale(1.3); }
    100% { transform: scale(1.1); }
}

.rate-comment-section {
    margin-bottom: 24px;
}

.rate-comment-label {
    font-size: 14px;
    color: #475569;
    margin-bottom: 8px;
    font-weight: 500;
}

.rate-comment-textarea {
    width: 100%;
    padding: 12px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-family: inherit;
    font-size: 14px;
    resize: vertical;
    min-height: 100px;
    transition: border-color 0.2s;
}

.rate-comment-textarea:focus {
    outline: none;
    border-color: #3b82f6;
}

/* NEW: Image Upload Section */
.rate-image-section {
    margin-bottom: 24px;
}

.rate-image-label {
    font-size: 14px;
    color: #475569;
    margin-bottom: 8px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 6px;
}

.rate-image-label-optional {
    font-size: 12px;
    color: #94a3b8;
    font-weight: 400;
}

.rate-image-upload-area {
    border: 2px dashed #cbd5e1;
    border-radius: 12px;
    padding: 24px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    background: #f8fafc;
}

.rate-image-upload-area:hover {
    border-color: #3b82f6;
    background: #eff6ff;
}

.rate-image-upload-area.has-image {
    border-style: solid;
    border-color: #10b981;
    background: #f0fdf4;
    padding: 16px;
}

.rate-image-icon {
    font-size: 48px;
    color: #cbd5e1;
    margin-bottom: 8px;
}

.rate-image-text {
    font-size: 14px;
    color: #64748b;
}

.rate-image-text strong {
    color: #3b82f6;
    font-weight: 600;
}

.rate-image-subtext {
    font-size: 12px;
    color: #94a3b8;
    margin-top: 4px;
}

.rate-image-preview {
    display: none;
    position: relative;
}

.rate-image-preview.show {
    display: block;
}

.rate-image-preview-img {
    width: 100%;
    border-radius: 8px;
    margin-bottom: 8px;
}

.rate-image-name {
    font-size: 13px;
    color: #475569;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.rate-image-remove {
    background: #ef4444;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    cursor: pointer;
    transition: background 0.2s;
}

.rate-image-remove:hover {
    background: #dc2626;
}

.rate-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}

.rate-btn {
    padding: 12px 24px;
    border-radius: 10px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 15px;
}

.rate-btn-cancel {
    background: #f1f5f9;
    color: #475569;
}

.rate-btn-cancel:hover {
    background: #e2e8f0;
}

.rate-btn-submit {
    background: #3b82f6;
    color: white;
    display: flex;
    align-items: center;
    gap: 8px;
}

.rate-btn-submit:hover:not(:disabled) {
    background: #2563eb;
    transform: translateY(-1px);
}

.rate-btn-submit:disabled {
    background: #cbd5e1;
    cursor: not-allowed;
}

.rate-btn-loading {
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Hidden file input */
#rateImageInput {
    display: none;
}
</style>

<!-- Rate Order Modal Structure -->
<div id="rateModalOverlay" class="rate-modal-overlay">
    <div class="rate-modal">
        <div class="rate-modal-header">
            <div class="rate-modal-title">
                <span class="rate-modal-title-icon">📸</span>
                Rate Your Order
            </div>
            <button class="rate-modal-close" id="rateModalClose">×</button>
        </div>

        <div class="rate-order-info" id="rateOrderInfo">
            <div class="rate-order-code">
                <strong>Order:</strong> <span id="rateOrderCode">RT12345</span>
            </div>
            <div class="rate-order-items" id="rateOrderItems">
                Loading order details...
            </div>
        </div>

        <div class="rate-stars-section">
            <div class="rate-stars-label">How would you rate your experience?</div>
            <div class="rate-stars" id="rateStars">
                <span class="rate-star" data-rating="1">★</span>
                <span class="rate-star" data-rating="2">★</span>
                <span class="rate-star" data-rating="3">★</span>
                <span class="rate-star" data-rating="4">★</span>
                <span class="rate-star" data-rating="5">★</span>
            </div>
        </div>

        <div class="rate-comment-section">
            <label class="rate-comment-label">Your Feedback</label>
            <textarea 
                id="rateComment" 
                class="rate-comment-textarea" 
                placeholder="Share your experience with us... (optional)"
            ></textarea>
        </div>

        <!-- NEW: Image Upload Section -->
        <div class="rate-image-section">
            <label class="rate-image-label">
                Add Photo
                <span class="rate-image-label-optional">(optional)</span>
            </label>
            <div class="rate-image-upload-area" id="rateImageUploadArea">
                <div id="rateImageUploadPrompt">
                    <div class="rate-image-icon">📷</div>
                    <div class="rate-image-text">
                        <strong>Click to upload</strong> or drag and drop
                    </div>
                    <div class="rate-image-subtext">
                        PNG, JPG, JPEG up to 5MB
                    </div>
                </div>
                <div class="rate-image-preview" id="rateImagePreview">
                    <img src="" alt="Preview" class="rate-image-preview-img" id="rateImagePreviewImg">
                    <div class="rate-image-name" id="rateImageName">
                        <span>📎</span>
                        <span id="rateImageFileName">image.jpg</span>
                    </div>
                    <button type="button" class="rate-image-remove" id="rateImageRemove">
                        Remove Image
                    </button>
                </div>
            </div>
            <input 
                type="file" 
                id="rateImageInput" 
                accept="image/png,image/jpeg,image/jpg"
            >
        </div>

        <div class="rate-actions">
            <button class="rate-btn rate-btn-cancel" id="rateCancelBtn">Cancel</button>
            <button class="rate-btn rate-btn-submit" id="rateSubmitBtn" disabled>
                <span id="rateSubmitText">Submit Feedback</span>
                <span class="rate-btn-loading" id="rateSubmitLoading" style="display: none;"></span>
            </button>
        </div>
    </div>
</div>

<script>
// Rate Order Modal JavaScript with Image Upload
const RateOrderModal = {
    currentOrderId: null,
    currentRating: 0,
    selectedImage: null,

    init: function() {
        this.bindEvents();
    },

    bindEvents: function() {
        const self = this;

        // Close button
        document.getElementById('rateModalClose').onclick = () => this.close();
        document.getElementById('rateCancelBtn').onclick = () => this.close();

        // Overlay click to close
        document.getElementById('rateModalOverlay').onclick = function(e) {
            if (e.target === this) {
                self.close();
            }
        };

        // Star rating
        const stars = document.querySelectorAll('.rate-star');
        stars.forEach(star => {
            star.onclick = () => {
                const rating = parseInt(star.getAttribute('data-rating'));
                self.setRating(rating);
            };
        });

        // Image upload
        const uploadArea = document.getElementById('rateImageUploadArea');
        const imageInput = document.getElementById('rateImageInput');
        
        uploadArea.onclick = () => {
            if (!this.selectedImage) {
                imageInput.click();
            }
        };

        imageInput.onchange = (e) => {
            const file = e.target.files[0];
            if (file) {
                this.handleImageSelect(file);
            }
        };

        // Drag and drop
        uploadArea.ondragover = (e) => {
            e.preventDefault();
            uploadArea.style.borderColor = '#3b82f6';
            uploadArea.style.background = '#eff6ff';
        };

        uploadArea.ondragleave = (e) => {
            e.preventDefault();
            uploadArea.style.borderColor = '#cbd5e1';
            uploadArea.style.background = '#f8fafc';
        };

        uploadArea.ondrop = (e) => {
            e.preventDefault();
            uploadArea.style.borderColor = '#cbd5e1';
            uploadArea.style.background = '#f8fafc';
            
            const file = e.dataTransfer.files[0];
            if (file && file.type.startsWith('image/')) {
                this.handleImageSelect(file);
            }
        };

        // Remove image button
        document.getElementById('rateImageRemove').onclick = (e) => {
            e.stopPropagation();
            this.removeImage();
        };

        // Submit button
        document.getElementById('rateSubmitBtn').onclick = () => this.submit();
    },

    open: function(orderId, orderCode = '', orderItems = '') {
        this.currentOrderId = orderId;
        this.currentRating = 0;
        this.selectedImage = null;

        // Reset form
        document.getElementById('rateOrderCode').textContent = orderCode || `Order #${orderId}`;
        document.getElementById('rateOrderItems').textContent = orderItems || 'Your order';
        document.getElementById('rateComment').value = '';
        this.removeImage();
        this.setRating(0);

        // Show modal
        document.getElementById('rateModalOverlay').classList.add('show');
    },

    close: function() {
        document.getElementById('rateModalOverlay').classList.remove('show');
        this.reset();
    },

    reset: function() {
        this.currentOrderId = null;
        this.currentRating = 0;
        this.selectedImage = null;
        document.getElementById('rateComment').value = '';
        this.removeImage();
        this.setRating(0);
    },

    setRating: function(rating) {
        this.currentRating = rating;
        const stars = document.querySelectorAll('.rate-star');
        stars.forEach((star, index) => {
            if (index < rating) {
                star.classList.add('selected');
            } else {
                star.classList.remove('selected');
            }
        });

        // Enable submit button if rating is selected
        document.getElementById('rateSubmitBtn').disabled = (rating === 0);
    },

    handleImageSelect: function(file) {
        // Validate file type
        const validTypes = ['image/png', 'image/jpeg', 'image/jpg'];
        if (!validTypes.includes(file.type)) {
            showError('Please select a valid image file (PNG, JPG, or JPEG)');
            return;
        }

        // Validate file size (5MB max)
        const maxSize = 5 * 1024 * 1024; // 5MB in bytes
        if (file.size > maxSize) {
            showError('Image size must be less than 5MB');
            return;
        }

        this.selectedImage = file;

        // Show preview
        const reader = new FileReader();
        reader.onload = (e) => {
            document.getElementById('rateImagePreviewImg').src = e.target.result;
            document.getElementById('rateImageFileName').textContent = file.name;
            document.getElementById('rateImageUploadPrompt').style.display = 'none';
            document.getElementById('rateImagePreview').classList.add('show');
            document.getElementById('rateImageUploadArea').classList.add('has-image');
        };
        reader.readAsDataURL(file);
    },

    removeImage: function() {
        this.selectedImage = null;
        document.getElementById('rateImageInput').value = '';
        document.getElementById('rateImagePreviewImg').src = '';
        document.getElementById('rateImageUploadPrompt').style.display = 'block';
        document.getElementById('rateImagePreview').classList.remove('show');
        document.getElementById('rateImageUploadArea').classList.remove('has-image');
    },

    async submit: function() {
        if (this.currentRating === 0) {
            showError('Please select a rating');
            return;
        }

        const submitBtn = document.getElementById('rateSubmitBtn');
        const submitText = document.getElementById('rateSubmitText');
        const submitLoading = document.getElementById('rateSubmitLoading');

        // Disable button and show loading
        submitBtn.disabled = true;
        submitText.textContent = 'Submitting...';
        submitLoading.style.display = 'inline-block';

        try {
            const formData = new FormData();
            formData.append('order_id', this.currentOrderId);
            formData.append('rating', this.currentRating);
            formData.append('comment', document.getElementById('rateComment').value.trim());
            
            // Append image if selected
            if (this.selectedImage) {
                formData.append('image', this.selectedImage);
            }

            const response = await fetch('/RADS-TOOLING/backend/api/feedback/create.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.close();
                showSuccess(
                    result.message || 'Thank you for your feedback! It will be published after review.',
                    'Feedback Submitted'
                );
                
                // Reload page after 2 seconds
                setTimeout(() => {
                    location.reload();
                }, 2000);
            } else {
                showError(result.message || 'Failed to submit feedback. Please try again.');
            }
        } catch (error) {
            console.error('Submit error:', error);
            showError('An error occurred while submitting your feedback. Please try again.');
        } finally {
            submitBtn.disabled = false;
            submitText.textContent = 'Submit Feedback';
            submitLoading.style.display = 'none';
        }
    }
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    RateOrderModal.init();
});

// Global function to open rate modal
function openRateModal(orderId, orderCode = '', orderItems = '') {
    RateOrderModal.open(orderId, orderCode, orderItems);
}
</script>