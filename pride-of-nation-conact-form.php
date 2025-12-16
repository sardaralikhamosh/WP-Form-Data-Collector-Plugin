<div class="contact-form-container">    
    <form class="contact-form" id="contactForm" method="post">
        <!-- First Row: Two Columns -->
        <div class="form-row">
            <!-- First Column: Name, Email, Phone -->
            <div class="form-fields-column">
                <input type="text" id="name" name="name" class="form-control" placeholder="Your Name" required>
                <input type="email" id="email" name="email" class="form-control" placeholder="Your Email" required>
                <input type="tel" id="phone" name="phone" class="form-control" placeholder="Your Phone">
            </div>
            
            <!-- Second Column: Message Textarea -->
            <div class="message-column">
                <textarea id="message" name="message" class="form-control" placeholder="Additional Message"></textarea>
            </div>
        </div>
        
        <!-- Second Row: Centered Button -->
        <div class="form-row">
            <button type="submit" class="submit-button" id="submitBtn">REGISTER NOW</button>
        </div>
    </form>
    
    <div class="success-message" id="successMessage">
        Thank you for your message! We've received it and will get back to you soon.
    </div>
    
    <div class="error-message" id="errorMessage">
        There was an error submitting your form. Please try again or contact us directly at hussain@prideofthenation.co.uk
    </div>
</div>

<script type="text/javascript">
// This will be replaced by WordPress
var ajaxurl = '/wp-admin/admin-ajax.php';

document.addEventListener('DOMContentLoaded', function() {
    const contactForm = document.getElementById('contactForm');
    
    // Try to get the correct ajaxurl from WordPress if available
    if (typeof wpAjax !== 'undefined' && wpAjax.ajaxurl) {
        ajaxurl = wpAjax.ajaxurl;
    } else if (typeof ajaxurl === 'undefined') {
        // Fallback to the standard WordPress AJAX URL
        ajaxurl = '/wp-admin/admin-ajax.php';
    }
    
    if (contactForm) {
        contactForm.addEventListener('submit', function(event) {
            event.preventDefault();
            
            // Get form elements
            const submitBtn = document.getElementById('submitBtn');
            const successMessage = document.getElementById('successMessage');
            const errorMessage = document.getElementById('errorMessage');
            
            // Hide previous messages
            successMessage.style.display = 'none';
            errorMessage.style.display = 'none';
            
            // Disable submit button to prevent multiple submissions
            submitBtn.disabled = true;
            submitBtn.textContent = 'Sending...';
            
            // Collect form data
            const formData = new FormData(this);
            
            // Add AJAX action and to_email
            formData.append('action', 'submit_contact_form');
            formData.append('to_email', 'hussain@prideofthenation.co.uk');
            formData.append('subject', 'Prideofthenation Form Submission');
            
            console.log('Sending AJAX request to:', ajaxurl); // For debugging
            
            // Send AJAX request to WordPress admin-ajax.php
            fetch(ajaxurl, {
                method: 'POST',
                body: formData,
            })
            .then(response => {
                console.log('Response status:', response.status); // For debugging
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data); // For debugging
                
                if (data.success) {
                    // Show success message
                    successMessage.style.display = 'block';
                    
                    // Reset form
                    contactForm.reset();
                    
                    // Scroll to success message
                    successMessage.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                } else {
                    // Show error message
                    errorMessage.style.display = 'block';
                    if (data.data) {
                        errorMessage.textContent = data.data;
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error); // For debugging
                // Show error message
                errorMessage.style.display = 'block';
                errorMessage.textContent = 'Network error. Please check your connection and try again. Error: ' + error.message;
            })
            .finally(() => {
                // Re-enable submit button
                submitBtn.disabled = false;
                submitBtn.textContent = 'REGISTER NOW';
            });
        });
        
        // Keep your existing form validation code here
        const formControls = document.querySelectorAll('.form-control');
        formControls.forEach(control => {
            control.addEventListener('blur', function() {
                if (this.hasAttribute('required') && this.value.trim() === '') {
                    this.style.border = '1px solid #e74c3c';
                } else {
                    this.style.border = 'none';
                }
                
                // Email validation
                if (this.type === 'email' && this.value.trim() !== '') {
                    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailPattern.test(this.value)) {
                        this.style.border = '1px solid #e74c3c';
                    } else {
                        this.style.border = 'none';
                    }
                }
            });
            
            // Add border on focus for better UX
            control.addEventListener('focus', function() {
                this.style.border = '1px solid #C9C1C1';
            });
            
            control.addEventListener('blur', function() {
                if (!this.style.borderColor || this.style.borderColor === 'rgb(231, 76, 60)') {
                    return;
                }
                this.style.border = 'none';
            });
        });
    }
});
</script>