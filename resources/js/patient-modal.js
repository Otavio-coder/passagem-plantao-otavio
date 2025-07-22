document.addEventListener('DOMContentLoaded', function () {
    // --- Real-time clock for modal (if needed) ---
    let clockInterval = null;

    function updateRealTimeClock() {
        const currentTime = new Date().toLocaleTimeString('pt-BR', {
            hour: '2-digit',
            minute: '2-digit'
        });
        document.querySelectorAll('#current-time-display, #input-time').forEach(el => {
            el.textContent = currentTime;
        });
    }

    function startClockInterval() {
        if (clockInterval) clearInterval(clockInterval);
        updateRealTimeClock();
        clockInterval = setInterval(updateRealTimeClock, 1000);
    }

    // --- Scroll messages container to bottom ---
    function scrollMessagesContainer() {
        const messagesContainer = document.getElementById('messages-container');
        if (messagesContainer) {
            requestAnimationFrame(() => {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
                setTimeout(() => {
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                }, 100);
            });
        }
    }

    // --- Auto-resize textarea and character counter ---
    function handleTextareaInput(e) {
        if (e.target.matches('textarea[wire\\:model\\.defer="newChatMessage"]')) {
            e.target.style.height = 'auto';
            e.target.style.height = Math.min(e.target.scrollHeight, 80) + 'px';
            const charCount = e.target.value.length;
            const counter = e.target.closest('form').querySelector('[class*="bg-gray-100"]');
            if (counter) {
                counter.textContent = charCount + '/1000';
                // Change color based on char count
                if (charCount > 900) {
                    counter.className = counter.className.replace('bg-gray-100 text-gray-600', 'bg-red-100 text-red-600');
                } else if (charCount > 800) {
                    counter.className = counter.className.replace('bg-gray-100 text-gray-600', 'bg-yellow-100 text-yellow-600');
                } else {
                    counter.className = counter.className.replace(/bg-(red|yellow)-100 text-(red|yellow)-600/, 'bg-gray-100 text-gray-600');
                }
            }
        }
    }

    // --- Prevent empty chat message submission ---
    function handleFormSubmit(e) {
        if (e.target.matches('form[wire\\:submit\\.prevent="sendChatMessage"]')) {
            const textarea = e.target.querySelector('textarea');
            if (!textarea.value.trim()) {
                e.preventDefault();
            }
        }
    }

    // --- Modal-specific Livewire/Alpine listeners ---
    document.addEventListener('livewire:component.updated', function () {
        startClockInterval();
        scrollMessagesContainer();

        // Re-bind chat listeners if needed
        const modal = document.querySelector('.relative[data-patient-id][data-shift]');
        if (modal && window.bindChatEchoListeners) {
            const patientId = modal.getAttribute('data-patient-id');
            const shift = modal.getAttribute('data-shift');
            if (patientId && shift) {
                window.bindChatEchoListeners(patientId, shift);
            }
        }
    });

    // --- Input and submit listeners for chat ---
    document.addEventListener('input', handleTextareaInput);
    document.addEventListener('submit', handleFormSubmit);

    // --- Initial run on DOM ready ---
    startClockInterval();
    scrollMessagesContainer();
});