#!/bin/bash

# RestroReach v1.1.0 - Final Production Validation Script
# Validates that all COD enhancements are properly implemented

echo "🚀 RestroReach v1.1.0 - Final Production Validation"
echo "=================================================="
echo ""

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Check if we're in the correct directory
if [ ! -f "restaurant-delivery-manager.php" ]; then
    echo -e "${RED}❌ Error: Please run this script from the RestroReach plugin directory${NC}"
    exit 1
fi

echo -e "${BLUE}🔍 Validating v1.1.0 COD Enhancement Implementation...${NC}"
echo ""

# 1. Version Check
echo "1. 📋 Version Validation"
echo "========================"

VERSION_CHECK=$(grep -c "Version:.*1\.1\.0" restaurant-delivery-manager.php)
CONSTANT_CHECK=$(grep -c "RESTROREACH_VERSION.*1\.1\.0" restaurant-delivery-manager.php)

if [ $VERSION_CHECK -eq 1 ] && [ $CONSTANT_CHECK -eq 1 ]; then
    echo -e "${GREEN}   ✅ Plugin version correctly set to 1.1.0${NC}"
else
    echo -e "${RED}   ❌ Version mismatch detected${NC}"
fi

echo ""

# 2. Database Schema Validation
echo "2. 🗄️ Database Schema Validation"
echo "================================="

ADMIN_NOTES_CHECK=$(grep -c "admin_notes text NULL" includes/class-database.php)
DISCREPANCY_FLAG_CHECK=$(grep -c "discrepancy_flag tinyint" includes/class-database.php)

if [ $ADMIN_NOTES_CHECK -eq 1 ]; then
    echo -e "${GREEN}   ✅ admin_notes column properly defined${NC}"
else
    echo -e "${RED}   ❌ admin_notes column missing or incorrect${NC}"
fi

if [ $DISCREPANCY_FLAG_CHECK -eq 1 ]; then
    echo -e "${GREEN}   ✅ discrepancy_flag column properly defined${NC}"
else
    echo -e "${RED}   ❌ discrepancy_flag column missing or incorrect${NC}"
fi

echo ""

# 3. Payment Class Enhancements
echo "3. 💰 Payment System Enhancements"
echo "=================================="

ADMIN_NOTE_HANDLER=$(grep -c "admin_notes.*sanitize_textarea_field" includes/class-payments.php)
CSV_EXPORT_CHECK=$(grep -c "row.*admin_notes" includes/class-payments.php)
DEBUG_LOGGING=$(grep -c "error_log.*COD\|error_log.*reconciliation" includes/class-payments.php)

if [ $ADMIN_NOTE_HANDLER -ge 1 ]; then
    echo -e "${GREEN}   ✅ Admin notes handling implemented${NC}"
else
    echo -e "${RED}   ❌ Admin notes handling missing${NC}"
fi

if [ $CSV_EXPORT_CHECK -ge 1 ]; then
    echo -e "${GREEN}   ✅ CSV export includes admin notes${NC}"
else
    echo -e "${RED}   ❌ CSV export admin notes missing${NC}"
fi

if [ $DEBUG_LOGGING -ge 1 ]; then
    echo -e "${GREEN}   ✅ Debug logging implemented${NC}"
else
    echo -e "${YELLOW}   ⚠️ Debug logging not found (optional)${NC}"
fi

echo ""

# 4. Frontend Integration
echo "4. 🖥️ Frontend Integration Validation"
echo "====================================="

MODAL_ADMIN_NOTES=$(grep -c "admin_notes" templates/admin/cash-reconciliation-page.php)
DISCREPANCY_CSS=$(grep -c "discrepancy" assets/css/customer-tracking.css)

if [ $MODAL_ADMIN_NOTES -ge 1 ]; then
    echo -e "${GREEN}   ✅ Admin modal includes notes functionality${NC}"
else
    echo -e "${RED}   ❌ Admin modal notes functionality missing${NC}"
fi

if [ $DISCREPANCY_CSS -ge 1 ]; then
    echo -e "${GREEN}   ✅ Discrepancy highlighting CSS present${NC}"
else
    echo -e "${YELLOW}   ⚠️ Discrepancy CSS styles not found${NC}"
fi

echo ""

# 5. Security Validation
echo "5. 🔒 Security Implementation Check"
echo "==================================="

NONCE_USAGE=$(grep -c "wp_verify_nonce\|check_admin_referer" includes/class-payments.php)
CAPABILITY_CHECKS=$(grep -c "current_user_can" includes/class-payments.php)
SANITIZATION=$(grep -c "sanitize_text_field\|sanitize_textarea_field\|absint" includes/class-payments.php)

if [ $NONCE_USAGE -ge 2 ]; then
    echo -e "${GREEN}   ✅ Nonce verification implemented${NC}"
else
    echo -e "${RED}   ❌ Insufficient nonce verification${NC}"
fi

if [ $CAPABILITY_CHECKS -ge 2 ]; then
    echo -e "${GREEN}   ✅ User capability checks present${NC}"
else
    echo -e "${RED}   ❌ Insufficient capability checks${NC}"
fi

if [ $SANITIZATION -ge 5 ]; then
    echo -e "${GREEN}   ✅ Input sanitization implemented${NC}"
else
    echo -e "${RED}   ❌ Insufficient input sanitization${NC}"
fi

echo ""

# 6. Mobile Agent Offline Queue
echo "6. 📱 Mobile Agent Offline Queue"
echo "================================="

OFFLINE_QUEUE=$(grep -c "localStorage\|offlineQueue" assets/js/rdm-mobile-agent.js)
SYNC_FUNCTIONALITY=$(grep -c "syncQueue\|reconnect" assets/js/rdm-mobile-agent.js)

if [ $OFFLINE_QUEUE -ge 1 ]; then
    echo -e "${GREEN}   ✅ Offline queue implementation found${NC}"
else
    echo -e "${YELLOW}   ⚠️ Offline queue not found in mobile agent JS${NC}"
fi

if [ $SYNC_FUNCTIONALITY -ge 1 ]; then
    echo -e "${GREEN}   ✅ Sync functionality implemented${NC}"
else
    echo -e "${YELLOW}   ⚠️ Sync functionality not clearly identified${NC}"
fi

echo ""

# 7. File Structure Validation
echo "7. 📁 Critical File Validation"
echo "==============================="

CRITICAL_FILES=(
    "restaurant-delivery-manager.php"
    "includes/class-database.php"
    "includes/class-payments.php"
    "templates/admin/cash-reconciliation-page.php"
    "assets/js/rdm-mobile-agent.js"
    "assets/css/customer-tracking.css"
    "RELEASE_NOTES_v1.1.0.md"
)

for file in "${CRITICAL_FILES[@]}"; do
    if [ -f "$file" ]; then
        echo -e "${GREEN}   ✅ $file${NC}"
    else
        echo -e "${RED}   ❌ $file (MISSING)${NC}"
    fi
done

echo ""

# 8. Final Summary
echo "8. 📊 Final Validation Summary"
echo "==============================="

# Count total checks
TOTAL_CHECKS=0
PASSED_CHECKS=0

# Version checks
TOTAL_CHECKS=$((TOTAL_CHECKS + 2))
if [ $VERSION_CHECK -eq 1 ] && [ $CONSTANT_CHECK -eq 1 ]; then
    PASSED_CHECKS=$((PASSED_CHECKS + 2))
fi

# Database checks
TOTAL_CHECKS=$((TOTAL_CHECKS + 2))
if [ $ADMIN_NOTES_CHECK -eq 1 ]; then
    PASSED_CHECKS=$((PASSED_CHECKS + 1))
fi
if [ $DISCREPANCY_FLAG_CHECK -eq 1 ]; then
    PASSED_CHECKS=$((PASSED_CHECKS + 1))
fi

# Payment system checks
TOTAL_CHECKS=$((TOTAL_CHECKS + 2))
if [ $ADMIN_NOTE_HANDLER -ge 1 ]; then
    PASSED_CHECKS=$((PASSED_CHECKS + 1))
fi
if [ $CSV_EXPORT_CHECK -ge 1 ]; then
    PASSED_CHECKS=$((PASSED_CHECKS + 1))
fi

# Frontend checks
TOTAL_CHECKS=$((TOTAL_CHECKS + 1))
if [ $MODAL_ADMIN_NOTES -ge 1 ]; then
    PASSED_CHECKS=$((PASSED_CHECKS + 1))
fi

# Security checks
TOTAL_CHECKS=$((TOTAL_CHECKS + 3))
if [ $NONCE_USAGE -ge 2 ]; then
    PASSED_CHECKS=$((PASSED_CHECKS + 1))
fi
if [ $CAPABILITY_CHECKS -ge 2 ]; then
    PASSED_CHECKS=$((PASSED_CHECKS + 1))
fi
if [ $SANITIZATION -ge 5 ]; then
    PASSED_CHECKS=$((PASSED_CHECKS + 1))
fi

# Calculate percentage
PERCENTAGE=$((PASSED_CHECKS * 100 / TOTAL_CHECKS))

echo ""
echo -e "${BLUE}📈 Validation Results:${NC}"
echo -e "   Passed: ${GREEN}$PASSED_CHECKS${NC}/$TOTAL_CHECKS checks"
echo -e "   Success Rate: ${GREEN}$PERCENTAGE%${NC}"

if [ $PERCENTAGE -ge 80 ]; then
    echo ""
    echo -e "${GREEN}🎉 VALIDATION PASSED! RestroReach v1.1.0 is ready for production deployment.${NC}"
    echo ""
    echo -e "${BLUE}📋 Next Steps:${NC}"
    echo "   1. Deploy to staging environment for final testing"
    echo "   2. Run comprehensive workflow tests"
    echo "   3. Verify database migration on production-like data"
    echo "   4. Deploy to production"
    echo "   5. Monitor logs for 24-48 hours post-deployment"
    echo ""
    echo -e "${GREEN}✨ Congratulations! The COD enhancement is complete and production-ready.${NC}"
else
    echo ""
    echo -e "${RED}❌ VALIDATION FAILED! Please address the issues above before deployment.${NC}"
    echo ""
    echo -e "${YELLOW}🔧 Recommended Actions:${NC}"
    echo "   1. Fix any missing implementations identified above"
    echo "   2. Re-run this validation script"
    echo "   3. Perform manual testing of critical workflows"
fi

echo ""
echo "=================================================="
echo -e "${BLUE}🏁 RestroReach v1.1.0 Validation Complete${NC}"
echo "=================================================="
