<script>
    document.addEventListener('DOMContentLoaded', function() {
        var credentialsSection = document.getElementById('credentials-section');
        var toggleButton = document.getElementById('toggle-credentials');
        var addressForm = document.getElementById('address-form');
        var credentialsForm = document.getElementById('credentials-form');
        var addBrokerForm = document.getElementById('add-broker-form');
        var deleteBrokerForms = document.querySelectorAll('.delete-broker-form');
        var addLinkForm = document.getElementById('add-link-form');
        var deleteLinkForms = document.querySelectorAll('.delete-link-form');
        var newsForm = document.getElementById('news-form');
        var statusUpdateForms = document.querySelectorAll('.status-update-form');
        var paymentStatusForms = document.querySelectorAll('.payment-status-form');
        var serverDecisionForms = document.querySelectorAll('.server-decision-form');

        var modal = document.getElementById('password-modal');
        var modalInput = document.getElementById('modal-password-input');
        var modalConfirmBtn = document.getElementById('modal-confirm-btn');
        var modalCancelBtn = document.getElementById('modal-cancel-btn');
        var modalTitle = document.getElementById('modal-title');
        var modalParagraph = document.getElementById('modal-paragraph');
        
        var currentForm = null;

        // ============== LIVE USER DATA UPDATES ==============
        
        // Store all user rows and their data
        var userRows = document.querySelectorAll('.user-row');
        var updateIntervals = {};
        
        // Function to fetch live data for a specific user
        async function fetchUserLiveData(userId, sourceTable, rowElement) {
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    credentials: 'same-origin',
                    body: 'user_id=' + encodeURIComponent(userId) + '&source_table=' + encodeURIComponent(sourceTable)
                });
                
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                
                const data = await response.json();
                
                if (data.success) {
                    // Add highlight effect
                    rowElement.classList.add('updating-row');
                    setTimeout(() => rowElement.classList.remove('updating-row'), 300);
                    
                    // Update broker balance
                    const brokerBalanceCell = rowElement.querySelector('.broker-balance-cell');
                    if (brokerBalanceCell && data.broker_balance) {
                        animateValue(brokerBalanceCell, brokerBalanceCell.innerText.replace('$', ''), data.broker_balance, '$');
                    }
                    
                    // Update profit/loss
                    const profitLossCell = rowElement.querySelector('.profit-loss-cell');
                    if (profitLossCell && data.profit_loss) {
                        const currentText = profitLossCell.innerText.replace(/[^0-9.-]/g, '');
                        animateValue(profitLossCell, currentText, data.profit_loss, data.profit_loss >= 0 ? '+' : '');
                        profitLossCell.className = 'profit-loss-cell ' + data.profit_loss_class;
                    }
                    
                    // Update current balance
                    const currentBalanceCell = rowElement.querySelector('.current-balance-cell');
                    if (currentBalanceCell && data.current_balance) {
                        animateValue(currentBalanceCell, currentBalanceCell.innerText.replace('$', ''), data.current_balance, '$');
                        currentBalanceCell.className = 'current-balance-cell ' + data.current_balance_class;
                    }
                    
                    // Update server share
                    const serverShareCell = rowElement.querySelector('.server-share-cell');
                    if (serverShareCell && data.server_share) {
                        if (data.server_share !== '-') {
                            animateValue(serverShareCell, serverShareCell.innerText.replace('$', ''), data.server_share, '$');
                        } else {
                            serverShareCell.innerText = '-';
                        }
                    }
                    
                    // Update user share
                    const userShareCell = rowElement.querySelector('.user-share-cell');
                    if (userShareCell && data.user_share) {
                        if (data.user_share !== '-') {
                            animateValue(userShareCell, userShareCell.innerText.replace('$', ''), data.user_share, '$');
                        } else {
                            userShareCell.innerText = '-';
                        }
                    }
                    
                    // Update expected payment
                    const expectedPaymentCell = rowElement.querySelector('.expected-payment-cell');
                    if (expectedPaymentCell && data.expected_payment) {
                        if (data.expected_payment !== '-') {
                            animateValue(expectedPaymentCell, expectedPaymentCell.innerText.replace('$', ''), data.expected_payment, '$');
                        } else {
                            expectedPaymentCell.innerText = '-';
                        }
                    }
                    
                    // Update unpaid age
                    const unpaidAgeCell = rowElement.querySelector('.unpaid-age-cell');
                    if (unpaidAgeCell && data.unpaid_age_ended_on) {
                        unpaidAgeCell.innerHTML = `
                            <div><strong>Ended:</strong> ${escapeHtml(data.unpaid_age_ended_on)}</div>
                            <div class="${data.unpaid_is_ended ? 'unpaid-age-ended' : 'unpaid-age-not-ended'}">
                                <strong>Age:</strong> ${escapeHtml(data.unpaid_age)}
                            </div>
                        `;
                    } else if (unpaidAgeCell && !data.should_show_in_revenue) {
                        unpaidAgeCell.innerHTML = '-';
                    }
                    
                    // Update status badge
                    const statusCell = rowElement.querySelector('.status-cell');
                    if (statusCell && data.display_status && data.display_status !== '-') {
                        const statusSpan = statusCell.querySelector('.status-badge');
                        if (statusSpan) {
                            let badgeClass = '';
                            if (data.display_status === 'payment-confirmed') {
                                badgeClass = 'status-badge-payment-confirmed';
                            } else if (data.display_status === 'payment-made') {
                                badgeClass = 'status-badge-payment-made';
                            } else if (data.display_status === 'unpaid-payment') {
                                badgeClass = 'status-badge-unpaid-payment';
                            } else {
                                badgeClass = 'loyalty-unpaid';
                            }
                            statusSpan.className = 'status-badge ' + badgeClass;
                            statusSpan.textContent = data.display_status;
                        }
                        
                        // Update eligible badge
                        const existingEligibleBadge = statusCell.querySelector('.eligible-badge');
                        if (existingEligibleBadge) {
                            existingEligibleBadge.remove();
                        }
                        
                        if (data.display_status === 'payment-confirmed') {
                            const badge = document.createElement('span');
                            badge.className = 'eligible-badge received-badge';
                            badge.textContent = 'confirmed';
                            statusCell.appendChild(badge);
                        } else if (data.display_status === 'payment-made') {
                            const badge = document.createElement('span');
                            badge.className = 'eligible-badge made-badge';
                            badge.textContent = 'made';
                            statusCell.appendChild(badge);
                        } else if (data.display_status === 'unpaid-payment') {
                            const badge = document.createElement('span');
                            badge.className = 'eligible-badge unpaid-badge';
                            badge.textContent = 'unpaid';
                            statusCell.appendChild(badge);
                        } else if (data.should_show_in_revenue && data.server_share !== '-') {
                            const badge = document.createElement('span');
                            badge.className = 'eligible-badge';
                            badge.textContent = 'eligible';
                            statusCell.appendChild(badge);
                        }
                    }
                    
                    // Update status update form disabled state based on should_show_in_revenue
                    const statusForm = rowElement.querySelector('.payment-status-form');
                    const statusSelect = statusForm?.querySelector('.payment-status-select');
                    const statusButton = statusForm?.querySelector('.update-status-btn');
                    
                    if (statusForm && !data.should_show_in_revenue) {
                        statusForm.classList.add('disabled-form');
                        if (statusSelect) statusSelect.disabled = true;
                        if (statusButton) statusButton.disabled = true;
                    } else if (statusForm && data.should_show_in_revenue) {
                        statusForm.classList.remove('disabled-form');
                        if (statusSelect) statusSelect.disabled = false;
                        if (statusButton) statusButton.disabled = false;
                    }
                    
                    // Update row data attributes for filtering
                    rowElement.dataset.displayStatus = data.display_status;
                    rowElement.dataset.isPaymentConfirmed = (data.display_status === 'payment-confirmed') ? 'true' : 'false';
                    rowElement.dataset.isPaymentMade = (data.display_status === 'payment-made') ? 'true' : 'false';
                    rowElement.dataset.isUnpaidPayment = (data.display_status === 'unpaid-payment') ? 'true' : 'false';
                    rowElement.dataset.isEligible = (data.server_share !== '-') ? 'true' : 'false';
                    rowElement.dataset.shouldShow = data.should_show_in_revenue ? 'true' : 'false';
                    
                    // Re-apply current filter
                    filterAndSearchUsers();
                    
                    // Update summary totals if needed (optional - could do a separate summary API call)
                    updateSummaryTotals();
                }
            } catch (error) {
                console.error(`Failed to fetch live data for user ${userId}:`, error);
            }
        }
        
        // Smooth number animation function
        function animateValue(element, start, end, prefix = '', suffix = '', duration = 300) {
            if (!element) return;
            
            start = parseFloat(start.toString().replace(/[^0-9.-]/g, '')) || 0;
            end = parseFloat(end.toString().replace(/[^0-9.-]/g, '')) || 0;
            
            if (start === end) return;
            
            const range = end - start;
            let current = start;
            let startTime = null;
            
            function step(timestamp) {
                if (!startTime) startTime = timestamp;
                const elapsed = timestamp - startTime;
                const progress = Math.min(1, elapsed / duration);
                current = start + (range * progress);
                element.innerText = prefix + current.toFixed(2) + suffix;
                
                if (progress < 1) {
                    requestAnimationFrame(step);
                } else {
                    element.innerText = prefix + end.toFixed(2) + suffix;
                }
            }
            
            requestAnimationFrame(step);
        }
        
        // Escape HTML helper
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Function to update summary totals (simplified - could fetch from API)
        // Update summary totals function - now calls the recalculation
        function updateSummaryTotals() {
            recalculateSummaryTotals();
        }
        
        // Function to recalculate summary totals from visible rows
        // Store original dashboard totals from PHP (these represent ALL users)
        var originalDashboardTotals = {
            brokerBalance: <?= $revenueSummary['total_broker_balance'] ?? 0 ?>,
            profitLoss: <?= $revenueSummary['total_profit'] ?? 0 ?>,
            currentBalance: <?= $revenueSummary['total_current_balance'] ?? 0 ?>,
            userShare: <?= $revenueSummary['total_user_share'] ?? 0 ?>,
            expectedPayments: <?= $revenueSummary['total_unpaid_payments'] ?? 0 ?>,
            paymentsMade: <?= $revenueSummary['total_payments_made'] ?? 0 ?>,
            paymentsConfirmed: <?= $revenueSummary['total_payments_received'] ?? 0 ?>,
            usersWithProfit: <?= $revenueSummary['users_with_profit'] ?? 0 ?>
        };

        function recalculateSummaryTotals() {
            // IMPORTANT: Dashboard totals should ALWAYS show ALL users, not just filtered ones
            // We only update the dashboard if we have fresh data from live updates
            // Otherwise, keep the original PHP totals
            
            var allRows = document.querySelectorAll('.user-row');
            var hasLiveData = false;
            
            var totals = {
                brokerBalance: 0,
                profitLoss: 0,
                currentBalance: 0,
                userShare: 0,
                expectedPayments: 0,
                paymentsMade: 0,
                paymentsConfirmed: 0,
                usersWithProfit: 0
            };
            
            // Calculate totals from ALL rows (not just visible/filtered ones)
            allRows.forEach(row => {
                // Get live values from cells if they exist
                var brokerBalanceCell = row.querySelector('.broker-balance-cell');
                var profitLossCell = row.querySelector('.profit-loss-cell');
                var currentBalanceCell = row.querySelector('.current-balance-cell');
                var userShareCell = row.querySelector('.user-share-cell');
                var expectedPaymentCell = row.querySelector('.expected-payment-cell');
                var displayStatus = row.dataset.displayStatus;
                
                // Check if this row has live data (not just placeholder)
                if (brokerBalanceCell && brokerBalanceCell.innerText !== '-' && brokerBalanceCell.innerText !== '$0.00') {
                    hasLiveData = true;
                }
                
                // Add broker balance (ALWAYS add regardless of should_show)
                if (brokerBalanceCell && brokerBalanceCell.innerText !== '-') {
                    var brokerValue = parseFloat(brokerBalanceCell.innerText.replace(/[^0-9.-]/g, '')) || 0;
                    totals.brokerBalance += brokerValue;
                }
                
                // Add profit/loss (ALWAYS add regardless of should_show)
                if (profitLossCell && profitLossCell.innerText !== '-') {
                    var profitValue = parseFloat(profitLossCell.innerText.replace(/[^0-9.-]/g, '')) || 0;
                    totals.profitLoss += profitValue;
                }
                
                // Add current balance (ALWAYS add regardless of should_show)
                if (currentBalanceCell && currentBalanceCell.innerText !== '-') {
                    var currentValue = parseFloat(currentBalanceCell.innerText.replace(/[^0-9.-]/g, '')) || 0;
                    totals.currentBalance += currentValue;
                }
                
                // Add user share (ALWAYS add regardless of should_show)
                if (userShareCell && userShareCell.innerText !== '-') {
                    var userShareValue = parseFloat(userShareCell.innerText.replace(/[^0-9.-]/g, '')) || 0;
                    totals.userShare += userShareValue;
                }
                
                // Add expected payment and track payment statuses
                if (expectedPaymentCell && expectedPaymentCell.innerText !== '-') {
                    var expectedValue = parseFloat(expectedPaymentCell.innerText.replace(/[^0-9.-]/g, '')) || 0;
                    
                    if (displayStatus === 'payment-confirmed') {
                        totals.paymentsConfirmed += expectedValue;
                    } else if (displayStatus === 'payment-made') {
                        totals.paymentsMade += expectedValue;
                    } else if (displayStatus === 'unpaid-payment') {
                        totals.expectedPayments += expectedValue;
                    }
                    
                    // Count users with profit (where expected payment > 0)
                    if (expectedValue > 0) {
                        totals.usersWithProfit++;
                    }
                }
            });
            
            // If we have live data, update the summary cards with calculated totals
            // Otherwise, keep the original PHP totals
            if (hasLiveData && (totals.brokerBalance > 0 || totals.profitLoss !== 0 || totals.currentBalance > 0)) {
                updateSummaryCard('total-broker-balance', totals.brokerBalance);
                updateSummaryCard('total-profit', totals.profitLoss);
                updateSummaryCard('total-current-balance', totals.currentBalance);
                updateSummaryCard('total-user-share', totals.userShare);
                updateSummaryCard('total-expected-payments', totals.expectedPayments);
                updateSummaryCard('total-payments-made', totals.paymentsMade);
                updateSummaryCard('total-payments-received', totals.paymentsConfirmed);
                updateSummaryCard('users-with-profit', totals.usersWithProfit);
                
                // Update profit color
                var profitElement = document.getElementById('total-profit');
                if (profitElement) {
                    profitElement.style.color = totals.profitLoss >= 0 ? 'var(--profit-color)' : 'var(--loss-color)';
                }
            } else {
                // Restore original dashboard totals if no live data
                updateSummaryCard('total-broker-balance', originalDashboardTotals.brokerBalance);
                updateSummaryCard('total-profit', originalDashboardTotals.profitLoss);
                updateSummaryCard('total-current-balance', originalDashboardTotals.currentBalance);
                updateSummaryCard('total-user-share', originalDashboardTotals.userShare);
                updateSummaryCard('total-expected-payments', originalDashboardTotals.expectedPayments);
                updateSummaryCard('total-payments-made', originalDashboardTotals.paymentsMade);
                updateSummaryCard('total-payments-received', originalDashboardTotals.paymentsConfirmed);
                updateSummaryCard('users-with-profit', originalDashboardTotals.usersWithProfit);
                
                // Update profit color
                var profitElement = document.getElementById('total-profit');
                if (profitElement) {
                    profitElement.style.color = originalDashboardTotals.profitLoss >= 0 ? 'var(--profit-color)' : 'var(--loss-color)';
                }
            }
        }

        // Alternative: Create a separate function for server-side summary refresh via AJAX
        // This can be called periodically to get fresh totals from the server
        function refreshDashboardTotalsFromServer() {
            // Optional: Make an AJAX call to get fresh totals from ALL users
            // This ensures dashboard always shows correct totals even after database updates
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                credentials: 'same-origin',
                body: 'action=get_dashboard_totals'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    originalDashboardTotals = data.totals;
                    updateSummaryCard('total-broker-balance', data.totals.brokerBalance);
                    updateSummaryCard('total-profit', data.totals.profitLoss);
                    updateSummaryCard('total-current-balance', data.totals.currentBalance);
                    updateSummaryCard('total-user-share', data.totals.userShare);
                    updateSummaryCard('total-expected-payments', data.totals.expectedPayments);
                    updateSummaryCard('total-payments-made', data.totals.paymentsMade);
                    updateSummaryCard('total-payments-received', data.totals.paymentsConfirmed);
                    updateSummaryCard('users-with-profit', data.totals.usersWithProfit);
                }
            })
            .catch(error => console.error('Failed to refresh dashboard totals:', error));
        }

        // Helper function to update individual summary card with animation
        function updateSummaryCard(elementId, newValue) {
            var element = document.getElementById(elementId);
            if (element) {
                var oldValue = parseFloat(element.getAttribute('data-value') || element.innerText.replace(/[^0-9.-]/g, '')) || 0;
                element.setAttribute('data-value', newValue);
                
                if (elementId === 'users-with-profit') {
                    animateValueSimple(element, oldValue, newValue, '', '', 300);
                } else {
                    animateValueSimple(element, oldValue, newValue, '$', '', 300);
                }
            }
        }

        // Simple animation function for summary cards
        function animateValueSimple(element, start, end, prefix = '', suffix = '', duration = 300) {
            if (start === end) return;
            
            const range = end - start;
            let current = start;
            let startTime = null;
            
            function step(timestamp) {
                if (!startTime) startTime = timestamp;
                const elapsed = timestamp - startTime;
                const progress = Math.min(1, elapsed / duration);
                current = start + (range * progress);
                
                if (element.id === 'users-with-profit') {
                    element.innerText = Math.round(current);
                } else {
                    element.innerText = prefix + current.toFixed(2) + suffix;
                }
                
                if (progress < 1) {
                    requestAnimationFrame(step);
                } else {
                    if (element.id === 'users-with-profit') {
                        element.innerText = Math.round(end);
                    } else {
                        element.innerText = prefix + end.toFixed(2) + suffix;
                    }
                }
            }
            
            requestAnimationFrame(step);
        }
        
        // Start live updates for all eligible user rows
        function startLiveUserUpdates(intervalSeconds = 10) {
            userRows.forEach(row => {
                const userId = row.dataset.userId;
                const sourceTable = row.dataset.sourceTable;
                
                if (userId && sourceTable && !updateIntervals[userId]) {
                    // Initial fetch
                    fetchUserLiveData(userId, sourceTable, row);
                    
                    // Set interval
                    updateIntervals[userId] = setInterval(() => {
                        fetchUserLiveData(userId, sourceTable, row);
                    }, intervalSeconds * 500);
                }
            });
        }
        
        // Stop live updates
        function stopLiveUserUpdates() {
            Object.values(updateIntervals).forEach(interval => {
                clearInterval(interval);
            });
            updateIntervals = {};
        }
        
        // Handle page visibility
        let isPageVisible = true;
        
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                isPageVisible = false;
                // Slow down updates when hidden (clear and restart with longer interval)
                stopLiveUserUpdates();
                startLiveUserUpdates(30);
            } else {
                isPageVisible = true;
                stopLiveUserUpdates();
                startLiveUserUpdates(10);
            }
        });

        // Server Decision Forms
        serverDecisionForms.forEach(function(form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                var select = this.querySelector('.server-decision-select');
                var userId = this.querySelector('input[name="user_id"]').value;
                var decision = select.value;
                
                if (decision === '') {
                    alert("Please select a server decision.");
                    return;
                }
                
                var decisionText = select.options[select.selectedIndex].text;
                showPasswordModal(form, 'Security Check: Update Server Decision', 
                    'Please enter your Admin Password to update server decision for User ID <strong>' + userId + '</strong> to <strong>' + decisionText + '</strong>.');
            });
        });

        // Payment Status Update Forms - Only allow if not disabled
        paymentStatusForms.forEach(function(form) {
            if (!form.classList.contains('disabled-form')) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    var select = this.querySelector('.payment-status-select');
                    if (select.disabled) {
                        alert("Status updates are disabled for this user because they are not eligible for profit split.");
                        return;
                    }
                    var userId = this.querySelector('input[name="user_id"]').value;
                    var newStatus = select.value;
                    
                    if (newStatus === '') {
                        alert("Please select a payment status.");
                        return;
                    }
                    
                    var statusText = select.options[select.selectedIndex].text;
                    showPasswordModal(form, 'Security Check: Update Payment Status', 
                        'Please enter your Admin Password to update payment status for User ID <strong>' + userId + '</strong> to <strong>' + statusText + '</strong>.');
                });
            }
        });

        // Status update forms for new users
        statusUpdateForms.forEach(function(form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                var userId = form.querySelector('input[name="user_id"]').value;
                var newStatus = form.querySelector('input[name="new_application_status"]').value;
                if (newStatus.trim() === '') {
                    alert("Please enter a new application status.");
                    return;
                }
                showPasswordModal(form, 'Security Check: Update Application Status', 
                    'Please enter your Admin Password to update status for User ID <strong>' + userId + '</strong> to <strong>' + newStatus + '</strong>.');
            });
        });

        // Enhanced Filter and Search functionality for user directory
        var filterButtons = document.querySelectorAll('.filter-btn');
        var searchInput = document.getElementById('user-search');
        var resetSearchBtn = document.getElementById('reset-search');
        var userCountSpan = document.getElementById('user-count');

        if (filterButtons.length > 0) {
            filterButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    filterButtons.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    filterAndSearchUsers();
                });
            });
        }

        if (searchInput) {
            searchInput.addEventListener('input', filterAndSearchUsers);
        }

        if (resetSearchBtn) {
            resetSearchBtn.addEventListener('click', function() {
                searchInput.value = '';
                filterAndSearchUsers();
            });
        }

        // Enhanced filter logic for All Users, Confirmed, Payment Made, Unpaid, and Eligible
        function filterAndSearchUsers() {
            var activeFilter = document.querySelector('.filter-btn.active').dataset.filter;
            var searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
            var visibleCount = 0;

            userRows.forEach(row => {
                var shouldShow = row.dataset.shouldShow === 'true';
                var isPaymentConfirmed = row.dataset.isPaymentConfirmed === 'true';
                var isPaymentMade = row.dataset.isPaymentMade === 'true';
                var isUnpaidPayment = row.dataset.isUnpaidPayment === 'true';
                var isEligible = row.dataset.isEligible === 'true';
                var id = row.dataset.id;
                var email = row.dataset.email;
                var fullname = row.dataset.fullname;
                
                var matchesFilter = false;
                
                if (activeFilter === 'all') {
                    matchesFilter = true;
                } else if (activeFilter === 'confirmed') {
                    matchesFilter = isPaymentConfirmed;
                } else if (activeFilter === 'payment-made') {
                    matchesFilter = isPaymentMade;
                } else if (activeFilter === 'unpaid') {
                    matchesFilter = isUnpaidPayment;
                } else if (activeFilter === 'eligible') {
                    matchesFilter = isEligible && shouldShow;
                }
                
                var matchesSearch = true;
                if (searchTerm !== '') {
                    matchesSearch = id.includes(searchTerm) || 
                                   email.includes(searchTerm) || 
                                   fullname.includes(searchTerm);
                }
                
                if (matchesFilter && matchesSearch) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            if (userCountSpan) {
                userCountSpan.textContent = visibleCount;
            }
            recalculateSummaryTotals();
        }

        // Toggle credentials section
        if (toggleButton && credentialsSection) {
            toggleButton.addEventListener('click', function() {
                credentialsSection.classList.toggle('active');
                toggleButton.textContent = credentialsSection.classList.contains('active') ? 'Hide Admin Credentials Editor' : '👤 Edit Admin Credentials';
            });
        }

        // Show password modal function
        function showPasswordModal(form, title, paragraph) {
            currentForm = form;
            modalTitle.textContent = title;
            modalParagraph.innerHTML = paragraph;
            modalInput.value = '';
            modal.classList.add('show'); 
            modalInput.focus();
            return false; 
        }
        
        // Address form handler
        if(addressForm) {
            addressForm.addEventListener('submit', function(e) {
                e.preventDefault();
                showPasswordModal(this, 'Security Check: Settings Update', 'Please enter your Admin Password to authorize updating server settings.');
            });
        }

        // Credentials form handler
        if(credentialsForm) {
            credentialsForm.addEventListener('submit', function(e) {
                e.preventDefault();
                showPasswordModal(this, 'Security Check: Credentials Update', 'Please enter your Admin Password to authorize changing admin credentials.');
            });
        }

        // News form handler
        if(newsForm) {
            newsForm.addEventListener('submit', function(e) {
                e.preventDefault();
                showPasswordModal(this, 'Security Check: Update News', 'Please enter your Admin Password to authorize updating the news announcement.');
            });
        }

        // Add broker form handler
        if(addBrokerForm) {
            addBrokerForm.addEventListener('submit', function(e) {
                e.preventDefault();
                var newBroker = this.querySelector('input[name="new_broker"]').value;
                if (newBroker.trim() === '') { 
                    alert("Broker name cannot be empty."); 
                    return; 
                }
                showPasswordModal(this, 'Security Check: Add Broker', 'Please enter your Admin Password to authorize adding broker <strong>' + newBroker + '</strong>.');
            });
        }

        // Delete broker forms handlers
        deleteBrokerForms.forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                var broker = this.querySelector('input[name="broker_value"]').value;
                showPasswordModal(this, 'Security Check: Delete Broker', 'Please enter your Admin Password to authorize deleting broker <strong>' + broker + '</strong>.');
            });
        });

        // Add link form handler
        if(addLinkForm) {
            addLinkForm.addEventListener('submit', function(e) {
                e.preventDefault();
                var newLink = this.querySelector('input[name="new_link"]').value;
                if (newLink.trim() === '') { 
                    alert("Link cannot be empty."); 
                    return; 
                }
                showPasswordModal(this, 'Security Check: Add Link', 'Please enter your Admin Password to authorize adding link <strong>' + newLink + '</strong>.');
            });
        }

        // Delete link forms handlers
        deleteLinkForms.forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                var link = this.querySelector('input[name="link_value"]').value;
                showPasswordModal(this, 'Security Check: Delete Link', 'Please enter your Admin Password to authorize deleting link <strong>' + link + '</strong>.');
            });
        });

        // Modal confirm button
        modalConfirmBtn.addEventListener('click', function() {
            var password = modalInput.value;
            if (password.length > 0) {
                var hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'admin_confirmation_password';
                hiddenInput.value = password;
                
                var loginIdInput = document.createElement('input');
                loginIdInput.type = 'hidden';
                loginIdInput.name = 'login_id';
                loginIdInput.value = '<?= htmlspecialchars($serverAccount['admin_login_id'] ?? 'admin') ?>';
                
                var oldInput = currentForm.querySelector('input[name="admin_confirmation_password"]');
                if (oldInput) oldInput.remove();
                var oldLoginIdInput = currentForm.querySelector('input[name="login_id"]');
                if (oldLoginIdInput) oldLoginIdInput.remove();
                
                currentForm.appendChild(hiddenInput);
                currentForm.appendChild(loginIdInput); 
                
                modal.classList.remove('show');
                currentForm.submit(); 
            } else {
                alert("Password cannot be empty.");
                modalInput.focus();
            }
        });

        // Modal cancel button
        modalCancelBtn.addEventListener('click', function() {
            modal.classList.remove('show');
            currentForm = null;
        });

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.classList.remove('show');
                currentForm = null;
            }
        }

        // Additional helper: Auto-hide flash messages after 5 seconds
        var messageElement = document.querySelector('.message');
        if (messageElement) {
            setTimeout(function() {
                messageElement.style.opacity = '0';
                setTimeout(function() {
                    if (messageElement.parentNode) {
                        messageElement.style.display = 'none';
                    }
                }, 500);
            }, 5000);
        }
        
        // Start live user updates
        <?php if ($currentView === 'paid_users' && !empty($allUsers)): ?>
        startLiveUserUpdates(10);
        
        // Clean up on page unload
        window.addEventListener('beforeunload', function() {
            stopLiveUserUpdates();
        });
        <?php endif; ?>
        setTimeout(function() {
            recalculateSummaryTotals();
        }, 500);
    });
    
</script>
<script>
    // ============================================
    // SESSION TIMEOUT - 1 MINUTE (No Warning)
    // ============================================
    (function() {
        // Timeout in milliseconds (1 minute = 60,000 ms)
        const SESSION_TIMEOUT = 60 * 1000;
        let inactivityTimer;
        let isLoggedOut = false;
        
        // Check if user is already logged out by checking for the login form
        function checkIfLoggedOut() {
            // If we see a login form, user is logged out - stop all timers
            if (document.querySelector('.login-container')) {
                isLoggedOut = true;
                if (inactivityTimer) {
                    clearTimeout(inactivityTimer);
                    inactivityTimer = null;
                }
                return true;
            }
            return false;
        }
        
        // Function to reset the inactivity timer
        function resetInactivityTimer() {
            // Don't reset if already logged out
            if (isLoggedOut) return;
            
            // Check if logged out again
            if (checkIfLoggedOut()) return;
            
            // Clear existing timer
            if (inactivityTimer) clearTimeout(inactivityTimer);
            
            // Send keep-alive ping to server
            fetch('serveraccount.php', {
                method: 'HEAD',
                cache: 'no-cache',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).catch(function(error) {
                // If fetch fails, check if we're logged out
                checkIfLoggedOut();
            });
            
            // Set new timer for logout
            inactivityTimer = setTimeout(forceLogout, SESSION_TIMEOUT);
        }
        
        // Function to force logout
        function forceLogout() {
            // Check if already logged out first
            if (checkIfLoggedOut()) return;
            
            // Clear timer
            if (inactivityTimer) {
                clearTimeout(inactivityTimer);
                inactivityTimer = null;
            }
            
            // Show message (optional - will disappear on redirect)
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message';
            messageDiv.innerHTML = '<span style="color:orange;">⏰ Session expired. Redirecting to login...</span>';
            const container = document.querySelector('.container');
            if (container) {
                const existingMessage = container.querySelector('.message');
                if (existingMessage) existingMessage.remove();
                container.insertBefore(messageDiv, container.firstChild);
            }
            
            // Redirect to logout after 1.5 seconds
            setTimeout(function() {
                // Check again if we're already on login page
                if (document.querySelector('.login-container')) {
                    isLoggedOut = true;
                    return;
                }
                window.location.href = 'serveraccount.php?logout=1';
            }, 1500);
        }
        
        // Check initial state
        checkIfLoggedOut();
        
        // Track user activity events
        const activityEvents = ['mousemove', 'mousedown', 'click', 'keypress', 'scroll', 'touchstart', 'keydown'];
        
        function handleUserActivity() {
            // Don't handle activity if already logged out
            if (isLoggedOut) return;
            
            // Check if we're now logged out
            if (checkIfLoggedOut()) return;
            
            resetInactivityTimer();
        }
        
        // Add event listeners for user activity
        activityEvents.forEach(function(event) {
            document.addEventListener(event, handleUserActivity);
        });
        
        // Also track when page becomes visible again
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                // Check if logged out when page becomes visible
                if (checkIfLoggedOut()) return;
                handleUserActivity();
            }
        });
        
        // Initialize the timer (only if not logged out)
        if (!isLoggedOut) {
            resetInactivityTimer();
        }
        
        // Periodic ping to keep session alive (every 30 seconds)
        const pingInterval = setInterval(function() {
            // Stop pinging if logged out
            if (isLoggedOut || document.querySelector('.login-container')) {
                isLoggedOut = true;
                clearInterval(pingInterval);
                return;
            }
            
            if (document.visibilityState === 'visible') {
                fetch('serveraccount.php', {
                    method: 'HEAD',
                    cache: 'no-cache',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }).catch(function(error) {
                    // If fetch fails, check if we're logged out
                    checkIfLoggedOut();
                });
            }
        }, 30000);
        
        // Clean up on page unload
        window.addEventListener('beforeunload', function() {
            if (inactivityTimer) {
                clearTimeout(inactivityTimer);
                inactivityTimer = null;
            }
            clearInterval(pingInterval);
        });
        
        // Also check for login form on any DOM changes (in case of dynamic content)
        const observer = new MutationObserver(function() {
            if (document.querySelector('.login-container')) {
                isLoggedOut = true;
                if (inactivityTimer) {
                    clearTimeout(inactivityTimer);
                    inactivityTimer = null;
                }
            }
        });
        observer.observe(document.body, { childList: true, subtree: true });
        
    })();
</script>
