# 🧪 RestroReach Testing Guide

## Quick Testing Checklist

### 1. ⚡ Automated Tests
```bash
# Run production validation
./validate-production.sh

# Run workflow tests  
php tests/workflow-test-suite.php

# Run performance tests
node tests/performance-test.js
```

### 2. 🛒 Order Workflow Testing
1. **Create test order** in WooCommerce
2. **Assign to agent** via admin interface
3. **Update status**: Processing → Preparing → Ready → Out for Delivery → Delivered
4. **Verify**: Status changes sync across all interfaces

### 3. 📱 Mobile Agent Testing
1. **Login**: Visit `/rdm-agent-login` on mobile
2. **Test GPS**: Grant location permission, verify tracking
3. **Order management**: Accept/reject orders, update status
4. **Offline test**: Disable internet, verify cached functionality

### 4. 👥 User Role Testing
- **Restaurant Manager**: Can manage orders, assign agents
- **Delivery Agent**: Mobile access only, order updates
- **Customer**: Order tracking access only

### 5. 💰 Payment Testing
1. **Place COD order**
2. **Agent collects payment**
3. **Verify reconciliation** in admin dashboard

### 6. 📧 Notification Testing
1. **Place order** → Check customer email
2. **Change status** → Verify notification sent
3. **Mark delivered** → Check completion email

### 7. 🎯 Customer Tracking
1. **Create page** with `[rdm_order_tracking]` shortcode
2. **Enter order details** (ID + email)
3. **Verify**: Real-time tracking, map display, status updates

### 8. 📊 Admin Dashboard
1. **Check analytics** display correctly
2. **Test live agent view** with GPS tracking
3. **Verify order management** interface functions

## Test Scenarios

### Critical Workflows
- [ ] Complete order from checkout to delivery
- [ ] Agent assignment and GPS tracking
- [ ] Customer order tracking interface
- [ ] Payment collection and reconciliation
- [ ] Email notifications work
- [ ] Mobile interface functions
- [ ] User permissions enforced

### Compatibility & Integration
- [ ] WooCommerce integration doesn't break existing functionality
- [ ] Google Maps API works under various conditions
- [ ] Database operations work with high order volumes
- [ ] Plugin activation/deactivation doesn't cause errors
- [ ] Compatible with common WordPress themes
- [ ] WooCommerce HPOS enabled/disabled compatibility
- [ ] No conflicts with popular plugins

### Performance Targets
- Page load: < 3 seconds
- GPS updates: 45-second intervals
- Mobile responsiveness: All breakpoints
- Battery usage: Optimized for mobile

### Security Verification
- Input sanitization active
- Output escaping implemented  
- CSRF protection enabled
- User capability checks enforced

## Mobile Testing Steps

1. **Device Setup**: Use actual mobile device
2. **GPS Permission**: Grant location access
3. **Touch Testing**: Verify 44px minimum touch targets
4. **Offline Testing**: Test cached functionality
5. **Battery Testing**: Monitor power consumption
6. **Performance**: Check smooth scrolling and interactions

## Quick Test Commands

```bash
# Check system status
curl -I https://yoursite.com/wp-admin/admin-ajax.php

# Test GPS endpoint
curl -X POST https://yoursite.com/wp-admin/admin-ajax.php \
  -d "action=rdm_update_agent_location&latitude=40.7128&longitude=-74.0060"

# Verify tracking page
curl https://yoursite.com/order-tracking/
```

## Pass/Fail Criteria

### ✅ PASS Requirements
- All 8 critical workflows function
- No security vulnerabilities
- Mobile interface responsive
- Performance meets targets
- User roles properly enforced

### ❌ FAIL Conditions
- Order workflow broken
- GPS tracking not working
- Security issues found
- Mobile interface unusable
- Performance below targets

## Testing Results Template

```
RestroReach Test Results - [Date]
================================

1. Order Workflow: ✅ PASS / ❌ FAIL
2. Agent Assignment: ✅ PASS / ❌ FAIL  
3. Customer Tracking: ✅ PASS / ❌ FAIL
4. Payment System: ✅ PASS / ❌ FAIL
5. Admin Dashboard: ✅ PASS / ❌ FAIL
6. Notifications: ✅ PASS / ❌ FAIL
7. Mobile Interface: ✅ PASS / ❌ FAIL
8. User Permissions: ✅ PASS / ❌ FAIL

Overall: PRODUCTION READY / NEEDS WORK

Issues Found: [List any problems]
Recommendations: [Next steps]
```

---

**💡 Testing Tips:**
- Use staging environment only
- Test on multiple devices
- Create test data for realistic scenarios
- Document all issues found
- Re-test after fixes applied 