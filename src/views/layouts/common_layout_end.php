<?php
// common_layout_end.php
// This file contains the closing HTML tags and the JavaScript for dynamic content loading.
?>
    </main>

    <!-- SweetAlert2 -->
    <!-- SweetAlert2 Loaded in Header -->
    <!-- Chart.js -->
    <script src="../../public/js/chart.umd.min.js"></script>


    <script>
        // Store the main content area element
        const mainContentArea = document.getElementById('main-content-area');

        // Function to load page content dynamically
        async function loadPage(url, pushState = true) {
            try {
                // Update active state on sidebar links
                document.querySelectorAll('.sidebar-link').forEach(link => link.classList.remove('active'));
                const currentLink = document.querySelector(`.sidebar-link[href="${url.split('?')[0]}"]`);
                if (currentLink) {
                    currentLink.classList.add('active');
                } else {
                    // Handle cases like edit/view invoice to keep 'Manage Invoices' active
                    if (url.includes('edit_invoice.php') || url.includes('view_invoice.php')) {
                        const manageLink = document.querySelector('.sidebar-link[href="manage_invoices.php"]');
                        if (manageLink) manageLink.classList.add('active');
                    }
                }

                const response = await fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    cache: 'no-store'
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const html = await response.text();
                mainContentArea.innerHTML = html;

                // Execute any <script> tags in the loaded content
                // This is the key to letting each page handle its own JavaScript
                mainContentArea.querySelectorAll("script").forEach(script => {
                    const newScript = document.createElement("script");
                    // Copy attributes
                    for (const attr of script.attributes) {
                        newScript.setAttribute(attr.name, attr.value);
                    }
                    // Copy inner content
                    newScript.textContent = script.textContent;
                    // Append to the head to execute it
                    document.head.appendChild(newScript).remove();
                });

                // Special handling for dashboard - trigger chart reinitialization
                if (url.includes('dashboard.php')) {
                    setTimeout(() => {
                        console.log('Dispatching dashboardLoaded event');
                        // Dispatch custom event that dashboard script listens to
                        window.dispatchEvent(new CustomEvent('dashboardLoaded'));
                    }, 200);
                }

                if (pushState) {
                    history.pushState({ path: url }, '', url);
                }

                // Update document title from the loaded content's <title> tag
                const newTitle = mainContentArea.querySelector('title')?.textContent || 'Invoice App';
                document.title = newTitle;

            } catch (error) {
                console.error('Error loading page:', error);
                mainContentArea.innerHTML = '<div class="text-red-500 text-center p-8">Failed to load content. Please try again.</div>';
            }
        }



        // Handle browser's back/forward buttons
        window.addEventListener('popstate', (event) => {
            if (event.state && event.state.path) {
                loadPage(event.state.path, false);
            }
        });

        // Attach click listeners to all sidebar links for SPA navigation
        document.addEventListener('click', function(event) {
            const link = event.target.closest('a.sidebar-link');
            if (link) {
                const url = link.getAttribute('href');
                
                // Special handling for logout - Let SweetAlert handler attached to the link take care of it
                if (url.includes('logout.php')) {
                    return;
                }
                
                // For all other links, use AJAX
                event.preventDefault();
                if (url && url !== '#') {
                    loadPage(url);
                }
            }
        });

        // Auto-dismiss notifications after 5 seconds
        function autoDismissNotifications() {
            const notifications = document.querySelectorAll('[data-auto-dismiss="true"]');
            notifications.forEach(notification => {
                setTimeout(() => {
                    notification.style.transition = 'opacity 0.5s ease-out';
                    notification.style.opacity = '0';
                    setTimeout(() => {
                        notification.remove();
                    }, 500);
                }, 5000);
            });
        }

        // Run auto-dismiss on page load
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', autoDismissNotifications);
        } else {
            autoDismissNotifications();
        }

        // Set the active link on initial page load
        document.addEventListener('DOMContentLoaded', function() {
            const currentPath = window.location.pathname.substring(window.location.pathname.lastIndexOf('/') + 1);
            const activeLink = document.querySelector(`.sidebar-link[href="${currentPath}"]`);
            if (activeLink) {
                activeLink.classList.add('active');
            }
        });

    </script>
</body>
</html>