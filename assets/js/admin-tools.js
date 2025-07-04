/**
 * RestroReach Admin Tools JavaScript
 *
 * Handles database management tool interactions and AJAX requests
 */
(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        
        // Run database test
        $('#rr-run-database-test').on('click', function() {
            var $button = $(this);
            var $results = $('#rr-test-results');
            var $content = $results.find('.rr-results-content');
            
            $button.prop('disabled', true).text(rrAdminTools.strings.processing);
            
            $.ajax({
                url: rrAdminTools.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rdm_run_database_test',
                    nonce: rrAdminTools.nonce
                },
                success: function(response) {
                    if (response.success) {
                        displayTestResults($content, response.data);
                        $results.slideDown();
                    } else {
                        alert(response.data || rrAdminTools.strings.error);
                    }
                },
                error: function() {
                    alert(rrAdminTools.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false).text('Run Database Tests');
                }
            });
        });
        
        // Generate sample data
        $('#rr-generate-sample-data').on('click', function() {
            var $button = $(this);
            var $results = $('#rr-sample-data-results');
            var $content = $results.find('.rr-results-content');
            
            $button.prop('disabled', true).text(rrAdminTools.strings.processing);
            
            $.ajax({
                url: rrAdminTools.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rdm_generate_sample_data',
                    nonce: rrAdminTools.nonce
                },
                success: function(response) {
                    if (response.success) {
                        displaySampleDataResults($content, response.data);
                        $results.slideDown();
                    } else {
                        alert(response.data || rrAdminTools.strings.error);
                    }
                },
                error: function() {
                    alert(rrAdminTools.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false).text('Generate Sample Data');
                }
            });
        });
        
        // Reset tables
        $('#rr-reset-tables').on('click', function() {
            if (!confirm(rrAdminTools.strings.confirmReset)) {
                return;
            }
            
            var $button = $(this);
            $button.prop('disabled', true).text(rrAdminTools.strings.processing);
            
            $.ajax({
                url: rrAdminTools.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rdm_reset_tables',
                    nonce: rrAdminTools.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert(response.data || rrAdminTools.strings.error);
                    }
                },
                error: function() {
                    alert(rrAdminTools.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false).text('Reset All Tables');
                }
            });
        });
        
        // Repair database
        $('#rr-repair-database').on('click', function() {
            var $button = $(this);
            var $results = $('#rr-maintenance-results');
            var $content = $results.find('.rr-results-content');
            
            $button.prop('disabled', true).text(rrAdminTools.strings.processing);
            
            $.ajax({
                url: rrAdminTools.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rdm_repair_database',
                    nonce: rrAdminTools.nonce
                },
                success: function(response) {
                    if (response.success) {
                        displayRepairResults($content, response.data);
                        $results.slideDown();
                    } else {
                        alert(response.data || rrAdminTools.strings.error);
                    }
                },
                error: function() {
                    alert(rrAdminTools.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false).text('Repair Database');
                }
            });
        });
        
        // Run health check
        $('#rr-run-health-check').on('click', function() {
            var $button = $(this);
            var $results = $('#rr-health-check-results');
            var $content = $results.find('.rr-results-content');
            
            $button.prop('disabled', true).text(rrAdminTools.strings.processing);
            
            $.ajax({
                url: rrAdminTools.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rdm_run_health_check',
                    nonce: rrAdminTools.nonce
                },
                success: function(response) {
                    if (response.success) {
                        displayHealthCheckResults($content, response.data);
                        $results.slideDown();
                    } else {
                        alert(response.data || rrAdminTools.strings.error);
                    }
                },
                error: function() {
                    alert(rrAdminTools.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false).text('Run Health Check');
                }
            });
        });
        
        // Cleanup data
        $('#rr-cleanup-data').on('click', function() {
            if (!confirm(rrAdminTools.strings.confirmCleanup)) {
                return;
            }
            
            var $button = $(this);
            var days = $('#rr-cleanup-days').val();
            var $results = $('#rr-maintenance-results');
            var $content = $results.find('.rr-results-content');
            
            $button.prop('disabled', true).text(rrAdminTools.strings.processing);
            
            $.ajax({
                url: rrAdminTools.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rdm_cleanup_data',
                    nonce: rrAdminTools.nonce,
                    days: days
                },
                success: function(response) {
                    if (response.success) {
                        $content.html('<div class="rr-success-message">' + response.data.message + '</div>');
                        $results.slideDown();
                    } else {
                        alert(response.data || rrAdminTools.strings.error);
                    }
                },
                error: function() {
                    alert(rrAdminTools.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false).text('Clean Up Data');
                }
            });
        });
    });
    
    /**
     * Display test results
     */
    function displayTestResults($container, data) {
        var html = '<div class="rr-test-summary">';
        html += '<h4>Test Summary</h4>';
        html += '<p>Total Tests: ' + data.summary.total + '</p>';
        html += '<p>Passed: <span class="rr-success">' + data.summary.passed + '</span></p>';
        html += '<p>Failed: <span class="rr-error">' + data.summary.failed + '</span></p>';
        html += '<p>Warnings: <span class="rr-warning">' + data.summary.warnings + '</span></p>';
        html += '<p>Success Rate: ' + data.summary.success_rate + '%</p>';
        html += '</div>';
        
        html += '<div class="rr-test-details">';
        html += '<h4>Test Details</h4>';
        
        $.each(data.results, function(index, test) {
            html += '<div class="rr-test-item rr-test-' + test.status + '">';
            html += '<h5>' + test.name + '</h5>';
            html += '<p class="rr-test-message">' + test.message + '</p>';
            
            if (test.errors && test.errors.length > 0) {
                html += '<ul class="rr-test-errors">';
                $.each(test.errors, function(i, error) {
                    html += '<li>' + error + '</li>';
                });
                html += '</ul>';
            }
            
            if (test.data) {
                html += '<details>';
                html += '<summary>Additional Data</summary>';
                html += '<pre>' + JSON.stringify(test.data, null, 2) + '</pre>';
                html += '</details>';
            }
            
            html += '</div>';
        });
        
        html += '</div>';
        
        $container.html(html);
    }
    
    /**
     * Display sample data results
     */
    function displaySampleDataResults($container, data) {
        var html = '<div class="rr-success-message">';
        html += '<h4>Sample Data Created Successfully!</h4>';
        html += '<ul>';
        html += '<li>Users Created: ' + data.users_created + '</li>';
        html += '<li>Agents Created: ' + data.agents_created + '</li>';
        html += '<li>Delivery Areas Created: ' + data.areas_created + '</li>';
        html += '<li>Sample Orders Created: ' + data.orders_created + '</li>';
        html += '</ul>';
        html += '</div>';
        
        $container.html(html);
    }
    
    /**
     * Display repair results
     */
    function displayRepairResults($container, data) {
        var html = '<div class="rr-repair-results">';
        html += '<h4>Database Repair Results</h4>';
        
        if (data.tables_created) {
            html += '<p class="rr-success">✓ Missing tables have been created</p>';
        }
        
        if (data.orphaned_records_cleaned) {
            html += '<p class="rr-success">✓ Orphaned records cleaned:</p>';
            html += '<ul>';
            if (data.orphaned_records_cleaned.orphaned_locations !== undefined) {
                html += '<li>Locations: ' + data.orphaned_records_cleaned.orphaned_locations + '</li>';
            }
            if (data.orphaned_records_cleaned.orphaned_assignments !== undefined) {
                html += '<li>Assignments: ' + data.orphaned_records_cleaned.orphaned_assignments + '</li>';
            }
            if (data.orphaned_records_cleaned.orphaned_agents !== undefined) {
                html += '<li>Agents: ' + data.orphaned_records_cleaned.orphaned_agents + '</li>';
            }
            html += '</ul>';
        }
        
        if (data.indexes_rebuilt) {
            html += '<p class="rr-success">✓ Database indexes have been rebuilt</p>';
        }
        
        if (Object.keys(data).length === 0) {
            html += '<p>No repairs were needed. Database is healthy!</p>';
        }
        
        html += '</div>';
        
        $container.html(html);
    }
    
    /**
     * Display health check results
     */
    function displayHealthCheckResults($container, data) {
        var statusClass = 'rr-status-' + data.status;
        var statusText = data.status.charAt(0).toUpperCase() + data.status.slice(1);
        
        var html = '<div class="rr-health-check-summary ' + statusClass + '">';
        html += '<h4>Overall Status: ' + statusText + '</h4>';
        html += '<p>Checked at: ' + data.timestamp + '</p>';
        html += '</div>';
        
        html += '<div class="rr-health-check-details">';
        
        $.each(data.checks, function(checkName, check) {
            var checkClass = 'rr-check-' + check.status;
            html += '<div class="rr-check-item ' + checkClass + '">';
            html += '<h5>' + formatCheckName(checkName) + '</h5>';
            html += '<p>' + check.message + '</p>';
            
            if (check.issues && check.issues.length > 0) {
                html += '<ul>';
                $.each(check.issues, function(i, issue) {
                    html += '<li>' + issue + '</li>';
                });
                html += '</ul>';
            }
            
            if (check.missing_tables) {
                html += '<p>Missing tables: ' + check.missing_tables.join(', ') + '</p>';
            }
            
            if (check.missing_indexes) {
                html += '<p>Missing indexes: ' + check.missing_indexes.join(', ') + '</p>';
            }
            
            if (check.slow_queries) {
                html += '<p>Slow queries:</p>';
                html += '<ul>';
                $.each(check.slow_queries, function(i, query) {
                    html += '<li>' + query.query + ' (' + query.time + 's)</li>';
                });
                html += '</ul>';
            }
            
            if (check.table_sizes) {
                html += '<details>';
                html += '<summary>Table Sizes</summary>';
                html += '<table class="rr-table-sizes">';
                $.each(check.table_sizes, function(table, info) {
                    html += '<tr><td>' + table + '</td><td>' + info.size_mb + ' MB</td><td>' + info.rows + ' rows</td></tr>';
                });
                html += '</table>';
                html += '</details>';
            }
            
            html += '</div>';
        });
        
        html += '</div>';
        
        $container.html(html);
    }
    
    /**
     * Format check name for display
     */
    function formatCheckName(name) {
        return name.replace(/_/g, ' ').replace(/\b\w/g, function(l) {
            return l.toUpperCase();
        });
    }
    
})(jQuery); 