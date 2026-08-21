/**
 * Mikrotik Manager - JavaScript Principal
 */

document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.3s';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
    
    // Command form handler
    const commandForm = document.getElementById('commandForm');
    if (commandForm) {
        commandForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const commandInput = document.getElementById('command');
            const resultDiv = document.getElementById('commandResult');
            const outputPre = document.getElementById('commandOutput');
            
            const command = commandInput.value.trim();
            if (!command) return;
            
            // Show loading
            resultDiv.style.display = 'block';
            outputPre.textContent = 'Executando comando...';
            
            try {
                const formData = new FormData();
                formData.append('command', command);
                
                const response = await fetch('/mikrotik/command', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.error) {
                    outputPre.textContent = 'Erro: ' + data.error;
                    outputPre.style.color = '#ef4444';
                } else {
                    outputPre.textContent = JSON.stringify(data.data, null, 2);
                    outputPre.style.color = '#22c55e';
                }
            } catch (error) {
                outputPre.textContent = 'Erro de conexão: ' + error.message;
                outputPre.style.color = '#ef4444';
            }
        });
    }
    
    // Confirm delete actions
    document.querySelectorAll('[data-confirm]').forEach(link => {
        link.addEventListener('click', function(e) {
            if (!confirm(this.dataset.confirm || 'Tem certeza?')) {
                e.preventDefault();
            }
        });
    });
    
    // Mobile menu toggle (if needed)
    const menuToggle = document.querySelector('.menu-toggle');
    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('open');
        });
    }
});

/**
 * Utility: Make API requests
 */
async function apiRequest(url, method = 'GET', data = null) {
    const options = {
        method,
        headers: {
            'Content-Type': 'application/json',
        }
    };
    
    if (data) {
        options.body = JSON.stringify(data);
    }
    
    const response = await fetch(url, options);
    return response.json();
}
