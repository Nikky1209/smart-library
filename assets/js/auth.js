    document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const lockIcon = passwordInput.parentElement.querySelector('.fa-lock');
            
            if (passwordInput && lockIcon) {
                const toggle = document.createElement('i');
                toggle.className = 'fas fa-eye password-toggle';
                toggle.style.cssText = 'cursor: pointer; margin-left: 10px; color: var(--slate); position: absolute; right: 16px; top: 50%; transform: translateY(-50%);';
                
                toggle.addEventListener('click', function() {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    toggle.className = type === 'password' ? 'fas fa-eye password-toggle' : 'fas fa-eye-slash password-toggle';
                });
                
                passwordInput.parentElement.appendChild(toggle);
            }
        });

         document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const confirmInput = document.getElementById('confirm_password');
            const strengthIndicator = document.getElementById('passwordStrength');
            const strengthFill = strengthIndicator.querySelector('.strength-fill');
            const strengthText = document.getElementById('strengthText');
            const matchIndicator = document.getElementById('passwordMatch');
            
            // Password visibility toggle
            function addPasswordToggle(inputId) {
                const input = document.getElementById(inputId);
                const icon = input.parentElement.querySelector('.fa-lock');
                
                if (input && icon) {
                    const toggle = document.createElement('i');
                    toggle.className = 'fas fa-eye password-toggle';
                    toggle.style.cssText = 'cursor: pointer; position: absolute; right: 16px; top: 50%; transform: translateY(-50%); color: var(--slate);';
                    
                    toggle.addEventListener('click', function() {
                        const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                        input.setAttribute('type', type);
                        toggle.className = type === 'password' ? 'fas fa-eye password-toggle' : 'fas fa-eye-slash password-toggle';
                    });
                    
                    input.parentElement.appendChild(toggle);
                }
            }
            
            // Add toggles for both password fields
            addPasswordToggle('password');
            addPasswordToggle('confirm_password');
            
            // Password strength checker
            function checkPasswordStrength(password) {
                let strength = 0;
                
                if (password.length >= 6) strength++;
                if (password.length >= 8) strength++;
                if (/[A-Z]/.test(password)) strength++;
                if (/[0-9]/.test(password)) strength++;
                if (/[^A-Za-z0-9]/.test(password)) strength++;
                
                return strength;
            }
            
            // Update strength indicator
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                
                if (password.length === 0) {
                    strengthIndicator.style.display = 'none';
                    return;
                }
                
                strengthIndicator.style.display = 'block';
                const strength = checkPasswordStrength(password);
                
                let width = '0%';
                let color = '#dc3545';
                let text = 'Weak';
                
                if (strength <= 1) {
                    width = '20%';
                    color = '#dc3545';
                    text = 'Weak';
                } else if (strength <= 3) {
                    width = '60%';
                    color = '#ffc107';
                    text = 'Fair';
                } else {
                    width = '100%';
                    color = '#28a745';
                    text = 'Strong';
                }
                
                strengthFill.style.width = width;
                strengthFill.style.backgroundColor = color;
                strengthText.textContent = text;
            });
            
            // Password confirmation checker
            function checkPasswordMatch() {
                if (passwordInput.value && confirmInput.value) {
                    if (passwordInput.value !== confirmInput.value) {
                        matchIndicator.style.display = 'block';
                        matchIndicator.style.color = '#dc3545';
                        matchIndicator.innerHTML = '<i class="fas fa-times"></i> Passwords do not match';
                        confirmInput.style.borderColor = '#dc3545';
                    } else {
                        matchIndicator.style.display = 'block';
                        matchIndicator.style.color = '#28a745';
                        matchIndicator.innerHTML = '<i class="fas fa-check"></i> Passwords match';
                        confirmInput.style.borderColor = '#28a745';
                    }
                } else {
                    matchIndicator.style.display = 'none';
                    confirmInput.style.borderColor = '#e2e8f0';
                }
            }
            
            passwordInput.addEventListener('input', checkPasswordMatch);
            confirmInput.addEventListener('input', checkPasswordMatch);
            
            // Form validation
            document.getElementById('registrationForm').addEventListener('submit', function(e) {
                if (passwordInput.value !== confirmInput.value) {
                    e.preventDefault();
                    alert('Passwords do not match. Please check and try again.');
                    passwordInput.focus();
                    return false;
                }
                
                if (passwordInput.value.length < 6) {
                    e.preventDefault();
                    alert('Password must be at least 6 characters long.');
                    passwordInput.focus();
                    return false;
                }
                
                const termsCheckbox = this.querySelector('input[name="terms"]');
                if (!termsCheckbox.checked) {
                    e.preventDefault();
                    alert('You must agree to the Library Terms of Service.');
                    return false;
                }
            });
        });