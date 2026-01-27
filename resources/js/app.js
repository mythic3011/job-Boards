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
    
    // Livewire initialization handler
    $(document).on('livewire:init', () => {
        console.log('Livewire initialized!', window.Livewire.all().length, 'components');
        window.Livewire.all().forEach((comp, i) => console.log(`Component ${i}:`, comp.name, comp.id));
    });
    
    // Fallback check after 3 seconds
    setTimeout(() => console.log('Livewire available:', !!window.Livewire, window.Livewire?.version || 'unknown'), 3000);
});