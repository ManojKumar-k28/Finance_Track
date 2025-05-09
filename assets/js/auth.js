/**
 * FinanceTrack - Authentication Page JavaScript
 * Handles login and registration functionality
 */

document.addEventListener('DOMContentLoaded', function() {
  // Password strength meter
  const passwordInput = document.getElementById('password');
  const passwordStrength = document.getElementById('password-strength');
  const confirmPasswordInput = document.getElementById('confirm_password');
  
  if (passwordInput && passwordStrength) {
    passwordInput.addEventListener('input', updatePasswordStrength);
  }
  
  if (passwordInput && confirmPasswordInput) {
    confirmPasswordInput.addEventListener('input', checkPasswordMatch);
    passwordInput.addEventListener('input', checkPasswordMatch);
  }
  
  /**
   * Update password strength indicator
   */
  function updatePasswordStrength() {
    const password = passwordInput.value;
    let strength = 0;
    let status = '';
    
    // Check password criteria
    if (password.length >= 8) strength += 1;
    if (password.match(/[a-z]/)) strength += 1;
    if (password.match(/[A-Z]/)) strength += 1;
    if (password.match(/[0-9]/)) strength += 1;
    if (password.match(/[^a-zA-Z0-9]/)) strength += 1;
    
    // Set indicator color and width based on strength
    let color, width;
    switch (strength) {
      case 0:
      case 1:
        color = 'var(--danger-500)';
        width = '20%';
        status = 'Weak';
        break;
      case 2:
      case 3:
        color = 'var(--warning-500)';
        width = '60%';
        status = 'Moderate';
        break;
      case 4:
      case 5:
        color = 'var(--success-500)';
        width = '100%';
        status = 'Strong';
        break;
    }
    
    // Update indicator
    passwordStrength.innerHTML = `<div style="width: ${width}; height: 100%; background-color: ${color}; border-radius: 9999px;"></div>`;
    passwordStrength.setAttribute('data-status', status);
  }
  
  /**
   * Check if passwords match
   */
  function checkPasswordMatch() {
    if (!confirmPasswordInput.value) return;
    
    const password = passwordInput.value;
    const confirmPassword = confirmPasswordInput.value;
    
    if (password === confirmPassword) {
      confirmPasswordInput.style.borderColor = 'var(--success-500)';
      confirmPasswordInput.setCustomValidity('');
    } else {
      confirmPasswordInput.style.borderColor = 'var(--danger-500)';
      confirmPasswordInput.setCustomValidity('Passwords do not match');
    }
  }
  
  // Add subtle animations to auth form
  animateAuthForm();
  
  /**
   * Add animations to auth form elements
   */
  function animateAuthForm() {
    const formContainer = document.querySelector('.auth-form-container');
    const formHeader = document.querySelector('.auth-form-header');
    const formElements = document.querySelectorAll('.form-group, .form-options, button');
    
    if (!formContainer) return;
    
    // Header animation
    if (formHeader) {
      formHeader.style.opacity = '0';
      formHeader.style.transform = 'translateY(-20px)';
      formHeader.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
      
      setTimeout(() => {
        formHeader.style.opacity = '1';
        formHeader.style.transform = 'translateY(0)';
      }, 100);
    }
    
    // Form elements staggered animation
    formElements.forEach((element, index) => {
      element.style.opacity = '0';
      element.style.transform = 'translateY(20px)';
      element.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
      
      setTimeout(() => {
        element.style.opacity = '1';
        element.style.transform = 'translateY(0)';
      }, 200 + (index * 100));
    });
  }
});