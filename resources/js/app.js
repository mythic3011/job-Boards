// jQuery-based app initialization with minimal code
import './bootstrap';
import './components/toast';
import './components/dropdown';
import './install';
import Alpine from 'alpinejs';

// Initialize everything with jQuery
$(() => {
    // Start Alpine.js
    window.Alpine = Alpine;
    Alpine.start();

    // UX: clear server-side validation styling as user edits (login/register/auth forms)
    $(document).on('input change', 'input.border-red-300, textarea.border-red-300, select.border-red-300', function () {
        const $field = $(this);
        $field.removeClass('border-red-300');
        const $wrapper = $field.closest('div');
        const $error = $wrapper.nextAll('p.text-red-600').first();
        if ($error.length) {
            $error.fadeOut(150);
        }
    });

    // Livewire initialization handler
    $(document).on('livewire:init', () => {
        console.log('Livewire initialized!', window.Livewire.all().length, 'components');
        window.Livewire.all().forEach((comp, i) => console.log(`Component ${i}:`, comp.name, comp.id));
    });
    
    // Fallback check after 3 seconds
    setTimeout(() => console.log('Livewire available:', !!window.Livewire, window.Livewire?.version || 'unknown'), 3000);
});