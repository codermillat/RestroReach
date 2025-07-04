#!/bin/bash

# RestroReach Production Validation Suite
# Comprehensive pre-deployment validation system

echo "🚀 RestroReach Production Validation Suite"
echo "=========================================="
echo "Comprehensive security, performance, and quality checks"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Results tracking
TOTAL_CHECKS=0
PASSED_CHECKS=0
FAILED_CHECKS=0
WARNINGS=0

# Function to run check
run_check() {
    local check_name="$1"
    local command="$2"
    local critical="$3"
    
    echo -n "⏳ $check_name... "
    TOTAL_CHECKS=$((TOTAL_CHECKS + 1))
    
    if eval "$command" >/dev/null 2>&1; then
        echo -e "${GREEN}✅ PASS${NC}"
        PASSED_CHECKS=$((PASSED_CHECKS + 1))
        return 0
    else
        if [ "$critical" = "true" ]; then
            echo -e "${RED}❌ FAIL (CRITICAL)${NC}"
            FAILED_CHECKS=$((FAILED_CHECKS + 1))
        else
            echo -e "${YELLOW}⚠️ WARNING${NC}"
            WARNINGS=$((WARNINGS + 1))
        fi
        return 1
    fi
}

# Function to display section header
section_header() {
    echo ""
    echo -e "${BLUE}📋 $1${NC}"
    echo "─────────────────────────────────────────"
}

# 1. BASIC FILE STRUCTURE VALIDATION
section_header "FILE STRUCTURE VALIDATION"

run_check "Main plugin file exists" "test -f restaurant-delivery-manager.php" true
run_check "Includes directory exists" "test -d includes" true
run_check "Assets directory exists" "test -d assets" true
run_check "Templates directory exists" "test -d templates" true
run_check "README.md exists" "test -f README.md" false
run_check "License file exists" "test -f LICENSE.md" false

# 2. CORE CLASS FILES VALIDATION
section_header "CORE CLASS FILES VALIDATION"

core_classes=(
    "includes/class-database.php"
    "includes/class-rdm-admin-interface.php"
    "includes/class-rdm-google-maps.php"
    "includes/class-payments.php"
    "includes/class-woocommerce-integration.php"
    "includes/class-analytics.php"
    "includes/class-notifications.php"
    "includes/class-customer-tracking.php"
    "includes/class-user-roles.php"
    "includes/class-rdm-mobile-frontend.php"
    "includes/class-rdm-api-endpoints.php"
    "includes/class-rdm-gps-tracking.php"
)

for class_file in "${core_classes[@]}"; do
    run_check "Core class: $(basename "$class_file")" "test -f '$class_file'" true
done

# 3. ASSET FILES VALIDATION
section_header "ASSET FILES VALIDATION"

# Check for critical assets
run_check "Mobile agent CSS" "test -f assets/css/rdm-mobile-agent.css" true
run_check "Mobile agent JS" "test -f assets/js/rdm-mobile-agent.js" true
run_check "Google Maps CSS" "test -f assets/css/rdm-google-maps.css" true
run_check "Google Maps JS" "test -f assets/js/rdm-google-maps.js" true
run_check "Customer tracking CSS" "test -f assets/css/rdm-customer-tracking.css" true
run_check "Customer tracking JS" "test -f assets/js/rdm-customer-tracking.js" true

# Check for minified assets (production optimization)
run_check "Mobile agent CSS minified" "test -f assets/css/rdm-mobile-agent.min.css" false
run_check "Mobile agent JS minified" "test -f assets/js/rdm-mobile-agent.min.js" false
run_check "Google Maps JS minified" "test -f assets/js/rdm-google-maps.min.js" false

# 4. TEMPLATE FILES VALIDATION
section_header "TEMPLATE FILES VALIDATION"

run_check "Customer tracking template" "test -f templates/customer-tracking.php" true
run_check "Mobile agent dashboard" "test -f templates/mobile-agent/dashboard.php" true
run_check "Admin order management" "test -f templates/admin/order-management-page.php" true

# 5. SECURITY VALIDATION
section_header "SECURITY VALIDATION"

# Check for ABSPATH protection
run_check "ABSPATH protection in main file" "grep -q \"defined('ABSPATH')\" restaurant-delivery-manager.php" true

# Check core files have ABSPATH protection
abspath_files_checked=0
abspath_files_protected=0

for file in includes/*.php; do
    if [ -f "$file" ]; then
        abspath_files_checked=$((abspath_files_checked + 1))
        if grep -q "defined('ABSPATH')" "$file"; then
            abspath_files_protected=$((abspath_files_protected + 1))
        fi
    fi
done

if [ $abspath_files_protected -eq $abspath_files_checked ]; then
    run_check "All PHP files have ABSPATH protection" "true" true
else
    run_check "All PHP files have ABSPATH protection" "false" true
    echo "   📊 Protected: $abspath_files_protected/$abspath_files_checked files"
fi

# Check for proper nonce usage
run_check "Nonce verification present" "grep -r 'wp_verify_nonce' includes/" true
run_check "Sanitization functions used" "grep -r 'sanitize_' includes/" true
run_check "Output escaping present" "grep -r 'esc_html\|esc_attr' templates/" true

# 6. PERFORMANCE VALIDATION
section_header "PERFORMANCE VALIDATION"

# Check for efficient database queries
run_check "Prepared statements used" "grep -r 'wpdb->prepare' includes/" true
run_check "No direct wpdb->query without prepare" "! grep -r 'wpdb->query(' includes/ | grep -v prepare" false

# Check GPS tracking optimization
run_check "Battery optimization in GPS tracking" "grep -q 'enableHighAccuracy.*false' assets/js/rdm-mobile-agent.js" false
run_check "Location update intervals optimized" "grep -q '45000\|45 seconds' assets/js/rdm-mobile-agent.js" false

# 7. WORDPRESS STANDARDS VALIDATION
section_header "WORDPRESS STANDARDS VALIDATION"

# Check for WordPress coding standards
run_check "WordPress hooks used properly" "grep -r 'add_action\|add_filter' includes/" true
run_check "Translation functions used" "grep -r '__\|_e\|esc_html__' includes/ templates/" false
run_check "Capability checks present" "grep -r 'current_user_can' includes/" true

# Check plugin headers
run_check "Plugin header present" "grep -q 'Plugin Name:' restaurant-delivery-manager.php" true
run_check "Version defined" "grep -q 'Version:' restaurant-delivery-manager.php" true
run_check "Text domain defined" "grep -q 'Text Domain:' restaurant-delivery-manager.php" true

# 8. FUNCTIONALITY VALIDATION
section_header "FUNCTIONALITY VALIDATION"

# Check for AJAX endpoints
ajax_endpoints_found=$(grep -r 'wp_ajax_' includes/ | wc -l)
if [ $ajax_endpoints_found -gt 10 ]; then
    run_check "AJAX endpoints implemented" "true" true
    echo "   📊 Found: $ajax_endpoints_found AJAX endpoints"
else
    run_check "AJAX endpoints implemented" "false" true
fi

# Check for database table creation
run_check "Database table creation code" "grep -r 'dbDelta' includes/" true

# Check for user role management
run_check "Custom user roles defined" "grep -q 'add_role\|add_cap' includes/" false

# 9. MOBILE OPTIMIZATION VALIDATION
section_header "MOBILE OPTIMIZATION VALIDATION"

# Check for responsive design
run_check "CSS media queries present" "grep -r '@media' assets/css/" false
run_check "Touch-friendly interfaces" "grep -q 'touch\|mobile' assets/css/rdm-mobile-agent.css" false

# Check for PWA features
run_check "Service worker referenced" "grep -q 'serviceWorker\|sw.js' assets/js/" false

# 10. INTEGRATION VALIDATION
section_header "INTEGRATION VALIDATION"

# WooCommerce integration
run_check "WooCommerce integration class" "test -f includes/class-woocommerce-integration.php" true
run_check "WooCommerce hooks used" "grep -q 'woocommerce_' includes/class-woocommerce-integration.php" true

# Google Maps integration (check for Google Maps usage)
run_check "Google Maps API integration" "grep -q 'google.maps' assets/js/rdm-google-maps.js" true

# 11. BUILD SYSTEM VALIDATION
section_header "BUILD SYSTEM VALIDATION"

run_check "Package.json exists" "test -f package.json" false
run_check "Build script exists" "test -f build.sh" false
run_check "Build script executable" "test -x build.sh" false

# 12. RUN AUTOMATED SECURITY SCAN
section_header "AUTOMATED SECURITY SCAN"

if command -v node >/dev/null 2>&1 && test -f tests/security-scan.js; then
    echo "⏳ Running automated security scan..."
    if node tests/security-scan.js >/dev/null 2>&1; then
        echo -e "${GREEN}✅ Security scan passed${NC}"
        PASSED_CHECKS=$((PASSED_CHECKS + 1))
    else
        echo -e "${YELLOW}⚠️ Security scan found issues (check security-scan-*.json)${NC}"
        WARNINGS=$((WARNINGS + 1))
    fi
    TOTAL_CHECKS=$((TOTAL_CHECKS + 1))
else
    echo -e "${YELLOW}⚠️ Node.js not available or security scan missing${NC}"
    WARNINGS=$((WARNINGS + 1))
    TOTAL_CHECKS=$((TOTAL_CHECKS + 1))
fi

# 13. FILE SIZE ANALYSIS
section_header "FILE SIZE ANALYSIS"

total_size=$(du -sh . 2>/dev/null | cut -f1)
echo "📊 Total plugin size: $total_size"

# Check individual component sizes
echo "📊 Component sizes:"
du -sh includes/ assets/ templates/ admin/ 2>/dev/null | while read size dir; do
    echo "   $dir: $size"
done

# 14. FINAL RESULTS
echo ""
echo "🏁 VALIDATION RESULTS"
echo "====================="
echo "Total Checks: $TOTAL_CHECKS"
echo -e "Passed: ${GREEN}$PASSED_CHECKS${NC}"
echo -e "Failed: ${RED}$FAILED_CHECKS${NC}"
echo -e "Warnings: ${YELLOW}$WARNINGS${NC}"

# Calculate success rate
success_rate=$((PASSED_CHECKS * 100 / TOTAL_CHECKS))
echo "Success Rate: $success_rate%"

# Final assessment
echo ""
echo "🎯 FINAL ASSESSMENT:"
if [ $FAILED_CHECKS -eq 0 ] && [ $success_rate -ge 90 ]; then
    echo -e "${GREEN}✅ PRODUCTION READY${NC}"
    echo "   Plugin meets all critical requirements for production deployment"
    exit_code=0
elif [ $FAILED_CHECKS -eq 0 ] && [ $success_rate -ge 80 ]; then
    echo -e "${YELLOW}⚠️ PRODUCTION READY WITH WARNINGS${NC}"
    echo "   Plugin is functional but has minor issues to address"
    exit_code=0
elif [ $FAILED_CHECKS -le 2 ] && [ $success_rate -ge 70 ]; then
    echo -e "${YELLOW}⚠️ NEEDS MINOR FIXES${NC}"
    echo "   Address the failed checks before production deployment"
    exit_code=1
else
    echo -e "${RED}❌ NOT PRODUCTION READY${NC}"
    echo "   Critical issues must be resolved before deployment"
    exit_code=2
fi

echo ""
echo "💡 Next Steps:"
if [ $FAILED_CHECKS -gt 0 ]; then
    echo "   1. Fix all CRITICAL failures above"
fi
if [ $WARNINGS -gt 0 ]; then
    echo "   2. Review and address warnings"
fi
echo "   3. Run './build.sh' to generate optimized assets"
echo "   4. Test in staging environment"
echo "   5. Deploy to production"

exit $exit_code 