// jQuery-based dropdown with minimal code - fixed double-click issue and jQuery availability
function initDropdowns() {
    if (typeof $ === 'undefined' || !$.fn) {
        // jQuery not ready yet, try again in 50ms
        setTimeout(initDropdowns, 50);
        return;
    }
    
    // Initialize all dropdowns
    $('[data-dropdown]').each((i, el) => {
        const $dropdown = $(el);
        const $button = $dropdown.find('[data-dropdown-button]');
        const $menu = $dropdown.find('[data-dropdown-menu]');
        const $arrow = $dropdown.find('[data-dropdown-arrow]');
        
        if (!$button.length || !$menu.length) return;
        
        let isOpen = false;
        
        // Set ARIA attributes
        $button.attr({'aria-expanded': 'false', 'aria-haspopup': 'true'});
        if (!$menu.attr('id')) $menu.attr('id', 'dropdown-menu-' + Math.random().toString(36).substr(2, 9));
        $button.attr('aria-controls', $menu.attr('id'));
        
        // Toggle dropdown with double-click protection
        $button.off('click dblclick').on('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            
            isOpen = !isOpen;
            $button.attr('aria-expanded', isOpen);
            
            if (isOpen) {
                $menu.removeClass('opacity-0 scale-95 pointer-events-none').addClass('opacity-100 scale-100');
                $arrow.css('transform', 'rotate(180deg)');
                setTimeout(() => $menu.find('a, button').first().focus(), 100);
            } else {
                $menu.removeClass('opacity-100 scale-100').addClass('opacity-0 scale-95 pointer-events-none');
                $arrow.css('transform', 'rotate(0deg)');
            }
        }).on('dblclick', (e) => {
            // Prevent double-click from interfering
            e.preventDefault();
            e.stopPropagation();
        });
        
        // Close on outside click
        $(document).on('click', (e) => {
            if (isOpen && !$dropdown.is(e.target) && !$dropdown.has(e.target).length) {
                isOpen = false;
                $button.attr('aria-expanded', 'false');
                $menu.removeClass('opacity-100 scale-100').addClass('opacity-0 scale-95 pointer-events-none');
                $arrow.css('transform', 'rotate(0deg)');
            }
        });
        
        // Keyboard navigation
        $(document).on('keydown', (e) => {
            if (!isOpen) return;
            const items = $menu.find('a, button');
            const current = items.index($(document.activeElement));
            
            switch (e.key) {
                case 'Escape': 
                    isOpen = false; 
                    $button.attr('aria-expanded', 'false'); 
                    $menu.removeClass('opacity-100 scale-100').addClass('opacity-0 scale-95 pointer-events-none'); 
                    $arrow.css('transform', 'rotate(0deg)'); 
                    $button.focus(); 
                    break;
                case 'ArrowDown': 
                    if (current >= 0) { 
                        e.preventDefault(); 
                        items.eq(current < items.length - 1 ? current + 1 : 0).focus(); 
                    } 
                    break;
                case 'ArrowUp': 
                    if (current >= 0) { 
                        e.preventDefault(); 
                        items.eq(current > 0 ? current - 1 : items.length - 1).focus(); 
                    } 
                    break;
                case 'Tab': 
                    if (current >= 0) { 
                        isOpen = false; 
                        $button.attr('aria-expanded', 'false'); 
                        $menu.removeClass('opacity-100 scale-100').addClass('opacity-0 scale-95 pointer-events-none'); 
                    } 
                    break;
            }
        });
    });
}

// Start trying to initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDropdowns);
} else {
    initDropdowns();
}