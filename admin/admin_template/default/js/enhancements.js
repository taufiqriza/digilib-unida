/**
 * SLiMS Filament-Style Layout
 */
(function($) {
    $(document).ready(function() {
        // Add toggle button to header
        if ($('#sidebarToggle').length === 0) {
            $('#header').prepend('<button id="sidebarToggle" type="button" title="Toggle Sidebar"><i class="fa fa-bars"></i></button>');
        }

        // Toggle sidebar
        $(document).on('click', '#sidebarToggle', function(e) {
            e.preventDefault();
            if ($(window).width() <= 1024) {
                $('#sidepan').toggleClass('show');
            } else {
                $('body').toggleClass('sidebar-collapsed');
                localStorage.setItem('sidebarCollapsed', $('body').hasClass('sidebar-collapsed') ? '1' : '0');
            }
        });

        // Restore state
        if (localStorage.getItem('sidebarCollapsed') === '1' && $(window).width() > 1024) {
            $('body').addClass('sidebar-collapsed');
        }

        // Close sidebar on outside click (mobile)
        $(document).on('click', function(e) {
            if ($(window).width() <= 1024 && !$(e.target).closest('#sidepan, #sidebarToggle').length) {
                $('#sidepan').removeClass('show');
            }
        });
    });
})(jQuery);
