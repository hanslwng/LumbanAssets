/**
 * LGU Property Inventory Management System
 * Main JavaScript File
 */

// Chatbot functionality (PLACEHOLDER)
// TODO: Integrate chatbot API here (OpenAI or custom NLP)

document.addEventListener('DOMContentLoaded', function() {
    
    // Chatbot toggle
    const chatbotButton = document.getElementById('chatbot-button');
    const chatbotPanel = document.getElementById('chatbot-panel');
    const chatbotClose = document.getElementById('chatbot-close');
    const chatbotSend = document.getElementById('chatbot-send');
    const chatbotInput = document.getElementById('chatbot-input');
    const chatbotBody = document.getElementById('chatbot-body');
    
    if (chatbotButton) {
        chatbotButton.addEventListener('click', function() {
            chatbotPanel.classList.toggle('active');
        });
    }
    
    if (chatbotClose) {
        chatbotClose.addEventListener('click', function() {
            chatbotPanel.classList.remove('active');
        });
    }
    
    if (chatbotSend) {
        chatbotSend.addEventListener('click', function() {
            sendChatMessage();
        });
    }
    
    if (chatbotInput) {
        chatbotInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                sendChatMessage();
            }
        });
    }
    
    function sendChatMessage() {
        const message = chatbotInput.value.trim();
        if (message === '') return;
        
        // Display user message
        appendChatMessage('user', message);
        chatbotInput.value = '';
        
        // Get bot response (placeholder)
        setTimeout(function() {
            const response = chatbotResponse(message);
            appendChatMessage('bot', response);
        }, 500);
    }
    
    function appendChatMessage(sender, message) {
        const messageDiv = document.createElement('div');
        messageDiv.style.marginBottom = '10px';
        messageDiv.style.padding = '10px';
        messageDiv.style.borderRadius = '4px';
        
        if (sender === 'user') {
            messageDiv.style.backgroundColor = '#B22222';
            messageDiv.style.color = 'white';
            messageDiv.style.textAlign = 'right';
        } else {
            messageDiv.style.backgroundColor = '#F5F5F5';
            messageDiv.style.color = '#333';
        }
        
        messageDiv.textContent = message;
        chatbotBody.appendChild(messageDiv);
        chatbotBody.scrollTop = chatbotBody.scrollHeight;
    }
    
    // Chatbot response function (PLACEHOLDER)
    // TODO: Connect to API here
    function chatbotResponse(input) {
        // Placeholder responses
        const responses = [
            "Chatbot not yet configured. This feature will be available soon.",
            "I'm a placeholder chatbot. Integration with AI API is pending.",
            "This chatbot will help you navigate the system once configured."
        ];
        
        return responses[Math.floor(Math.random() * responses.length)];
    }
    
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.remove();
            }, 500);
        }, 5000);
    });
    
    // Confirm delete actions
    const deleteButtons = document.querySelectorAll('[data-confirm]');
    deleteButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm');
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
    
    // Form validation
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(function(field) {
                if (field.value.trim() === '') {
                    isValid = false;
                    field.style.borderColor = '#DC3545';
                } else {
                    field.style.borderColor = '#DDDDDD';
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields');
            }
        });
    });
    
    // Print functionality
    const printButtons = document.querySelectorAll('[data-print]');
    printButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            window.print();
        });
    });
    
    // Dynamic item selection for issuance
    const itemSelect = document.getElementById('item_id');
    if (itemSelect) {
        itemSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const availableQty = selectedOption.getAttribute('data-available');
            const classification = selectedOption.getAttribute('data-classification');
            
            // Update available quantity display
            const qtyDisplay = document.getElementById('available-qty-display');
            if (qtyDisplay) {
                qtyDisplay.textContent = 'Available: ' + availableQty;
            }
            
            // Update form type hint
            const formTypeHint = document.getElementById('form-type-hint');
            if (formTypeHint) {
                if (classification === 'PPE') {
                    formTypeHint.textContent = 'This item requires PAR (Property Acknowledgement Receipt)';
                } else {
                    formTypeHint.textContent = 'This item requires ICS (Inventory Custodian Slip)';
                }
            }
        });
    }
    
    // Dynamic action selection for returns
    const actionSelect = document.getElementById('action_taken');
    const transferOfficeGroup = document.getElementById('transfer-office-group');
    
    if (actionSelect && transferOfficeGroup) {
        actionSelect.addEventListener('change', function() {
            if (this.value === 'Transfer to Another Office') {
                transferOfficeGroup.style.display = 'block';
            } else {
                transferOfficeGroup.style.display = 'none';
            }
        });
    }
    
    // Condition status validation for returns
    const conditionSelect = document.getElementById('condition_status');
    if (conditionSelect && actionSelect) {
        conditionSelect.addEventListener('change', function() {
            if (this.value === 'Damaged' && actionSelect.value === 'Transfer to Another Office') {
                alert('Warning: Damaged/Unserviceable items cannot be transferred. Please select a different action.');
                actionSelect.value = '';
            }
        });
    }
});

// Helper function to format currency
function formatCurrency(amount) {
    return '₱' + parseFloat(amount).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// Helper function to format date
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}
