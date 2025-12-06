/**
 * SLiMS Modern Admin v2.1
 */
(function($) {
    $(document).ready(function() {
        // Add sidebar toggle button
        if (!$('#sidebarToggle').length) {
            $('#header').prepend(
                '<button id="sidebarToggle" type="button">' +
                '<svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">' +
                '<path d="M4 6h16M4 12h16M4 18h16"/></svg></button>'
            );
        }

        // Toggle sidebar
        $('#sidebarToggle').on('click', function() {
            if ($(window).width() <= 768) {
                $('#sidepan').toggleClass('show');
            } else {
                $('body').toggleClass('sidebar-collapsed');
                localStorage.setItem('sidebar', $('body').hasClass('sidebar-collapsed') ? 'collapsed' : '');
            }
        });

        // Restore sidebar state
        if (localStorage.getItem('sidebar') === 'collapsed') {
            $('body').addClass('sidebar-collapsed');
        }

        // Close mobile sidebar on outside click
        $(document).on('click', function(e) {
            if ($(window).width() <= 768 && !$(e.target).closest('#sidepan, #sidebarToggle').length) {
                $('#sidepan').removeClass('show');
            }
        });

        // Ctrl+S to save
        $(document).on('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                $('button[type="submit"], input[type="submit"], button[name="saveData"]').first().click();
            }
        });
    });
})(jQuery);
