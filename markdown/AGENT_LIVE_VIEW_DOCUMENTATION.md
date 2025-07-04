# Agent Live View Feature Documentation

## Overview
The Agent Live View feature allows restaurant managers and administrators to view the real-time location of individual delivery agents on a map interface within the WordPress admin area.

## Features
- **Single Agent Tracking**: View one agent's location at a time with detailed information
- **Real-time Location Display**: Shows the most recent GPS coordinates from the agent's mobile device
- **Agent Information Card**: Displays agent details, battery level, accuracy, and last update timestamp
- **Interactive Map**: Google Maps integration with custom markers and info windows
- **Agent Selection**: Dropdown to easily switch between different delivery agents
- **Security**: Proper capability checks and data validation
- **Error Handling**: Graceful handling of missing location data or invalid agents

## Access Requirements
- **Capability**: `rdm_manage_agents`
- **User Roles**: Restaurant managers and administrators
- **Menu Location**: RestroReach → Agent Live View

## Technical Implementation

### 1. Database Integration
```php
// Fetch latest agent location
$location_data = RDM_GPS_Tracking::get_latest_agent_location($agent_id);
```

The system queries the `{$wpdb->prefix}rr_location_tracking` table to retrieve:
- Latitude and longitude coordinates
- GPS accuracy in meters
- Battery level percentage
- Timestamp of last update

### 2. Admin Interface
- **File**: `includes/class-rdm-admin-interface.php`
- **Method**: `render_agent_live_view_page()`
- **URL**: `wp-admin/admin.php?page=restroreach-agent-live-view&agent_id=X`

### 3. JavaScript Integration
- **File**: `assets/js/rdm-admin-maps.js`
- **Function**: `rdmInitAgentLiveViewMap()`
- **Map Element**: `#rdm-agent-live-map-canvas`

### 4. Google Maps Integration
- **API Libraries**: `places,geometry,directions`
- **Callback**: `rdmInitAdminMaps`
- **Custom Markers**: Battery-level color coding
- **Info Windows**: Agent details with formatted data

## User Interface Elements

### Agent Selection Dropdown
```html
<select id="agent-selector" onchange="rdmSelectAgent(this.value)">
    <option value="">-- Select Agent --</option>
    <option value="123">John Doe (john@example.com)</option>
</select>
```

### Agent Information Card
- Agent name and email
- Last location update timestamp
- GPS accuracy (if available)
- Battery level with color coding
- Online/offline status indicator

### Map Display
- **Default Center**: Configurable (defaults to NYC: 40.7128, -74.0060)
- **Zoom Level**: 15 for agent location, 10 for default view
- **Marker Icons**: Custom colored circles based on battery level
  - Green (>50%): Good battery
  - Yellow (20-50%): Medium battery
  - Red (<20%): Low battery
- **Accuracy Circle**: Visual representation of GPS accuracy when available

### Location Details Table
| Field | Description |
|-------|-------------|
| Latitude | GPS coordinate (6 decimal places) |
| Longitude | GPS coordinate (6 decimal places) |
| Timestamp | Raw timestamp from database |

## Security Features

### Capability Checks
```php
if (!current_user_can('rdm_manage_agents')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}
```

### Input Validation
```php
$agent_id = isset($_GET['agent_id']) ? absint($_GET['agent_id']) : 0;
```

### User Verification
```php
$agent_user = get_userdata($agent_id);
if (!$agent_user || !user_can($agent_user, 'delivery_agent')) {
    // Handle invalid agent
}
```

## Error Handling

### No Agent Selected
- Displays agent selection form
- Shows informational message
- Provides dropdown to select an agent

### Invalid Agent ID
- Validates agent exists
- Confirms user has delivery agent capability
- Shows error message with selection form

### No Location Data
- Displays agent information without map marker
- Shows "No location data available" message
- Renders default map view

### Missing Google Maps API
- Graceful degradation
- Error logging for administrators
- User-friendly error messages

## CSS Styling

### Responsive Design
- Mobile-optimized layout
- Flexible map container
- Responsive table design
- Touch-friendly interface elements

### Visual Indicators
- Color-coded battery levels
- Status badges (online/offline)
- Loading states with animations
- Error and success message styling

## Usage Examples

### Viewing an Agent's Location
1. Navigate to RestroReach → Agent Live View
2. Select an agent from the dropdown (or use direct URL with agent_id)
3. View the agent's location on the map
4. Check agent details in the information card
5. Review location accuracy and battery status

### Direct URL Access
```
wp-admin/admin.php?page=restroreach-agent-live-view&agent_id=123
```

### Switching Between Agents
- Use the dropdown selector
- JavaScript automatically redirects with new agent_id
- Page refreshes with updated location data

## Testing and Validation

### Test Script
Run the validation test:
```bash
php tests/test-agent-live-view.php
```

### Manual Testing Checklist
1. ✅ Admin menu item appears for authorized users
2. ✅ Agent selection dropdown populates with delivery agents
3. ✅ Map displays agent location when data available
4. ✅ Info window shows agent details
5. ✅ Error handling for missing location data
6. ✅ Security checks prevent unauthorized access
7. ✅ Responsive design works on mobile devices
8. ✅ CSS styling renders correctly

## Configuration Requirements

### Google Maps API
- Valid API key configured in plugin settings
- Required APIs enabled:
  - Maps JavaScript API
  - Places API
  - Geometry API
  - Directions API

### WordPress Capabilities
- Custom capabilities properly registered
- User roles assigned correctly
- Delivery agents have GPS tracking enabled

## Performance Considerations

### Database Optimization
- Indexed queries on agent_id and timestamp
- Efficient LIMIT 1 queries for latest location
- Proper use of prepared statements

### Asset Loading
- Scripts only loaded on relevant admin pages
- Google Maps API loaded with callback
- CSS minification in production

### Caching
- Location data freshness (30-60 second intervals)
- Transient API for expensive operations
- Browser caching for static assets

## Future Enhancements

### Planned Features
1. **Real-time Updates**: WebSocket or polling for live location updates
2. **Multi-agent View**: Display multiple agents on the same map
3. **Historical Tracking**: View agent's route history
4. **Geofencing**: Alert when agents enter/exit delivery zones
5. **Route Optimization**: Suggest optimal delivery routes
6. **Battery Alerts**: Notifications for low battery levels

### Integration Opportunities
- SMS notifications for agent status changes
- Email reports for manager dashboards
- Mobile push notifications
- Third-party mapping services

## Troubleshooting

### Common Issues
1. **Blank Map**: Check Google Maps API key and enabled APIs
2. **No Agents**: Verify delivery agent user roles and capabilities
3. **Location Not Updating**: Check GPS tracking service and mobile app
4. **Permission Errors**: Verify user capabilities and role assignments

### Debug Information
- Enable WordPress debug logging
- Check browser console for JavaScript errors
- Verify AJAX responses in network tab
- Review server error logs

## API Reference

### PHP Methods
```php
// Get latest agent location
RDM_GPS_Tracking::get_latest_agent_location(int $agent_user_id): ?array

// Render agent live view page
RDM_Admin_Interface::render_agent_live_view_page(): void

// Enqueue Google Maps for admin
RDM_Google_Maps::enqueue_admin_maps_script(): void
```

### JavaScript Functions
```javascript
// Initialize agent live view map
rdmInitAgentLiveViewMap()

// Create agent marker icon
rdmGetAgentLiveViewMarkerIcon(batteryLevel)

// Create info window content
rdmCreateAgentInfoWindowContent(agentData)

// Handle agent selection
rdmSelectAgent(agentId)
```

This comprehensive documentation provides all necessary information for understanding, implementing, testing, and maintaining the Agent Live View feature.
