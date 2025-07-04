# Restaurant Delivery Manager - Testing Guide

This guide provides step-by-step instructions for manually testing the Restaurant Delivery Manager plugin after installation. It covers the core implemented features and ensures everything is working as expected.

## Prerequisites

- WordPress 6.0+ installation
- WooCommerce 8.0+ installed and activated
- Restaurant Delivery Manager Professional plugin installed and activated
- Google Maps API key with necessary APIs enabled (Maps JavaScript, Places, Geocoding, Directions)
- At least one user with the "Delivery Agent" role
- Sample products created in WooCommerce

## Initial Setup Verification

### Plugin Activation Test

1. Navigate to WordPress Admin → Plugins
2. Locate "Restaurant Delivery Manager Professional"
3. Click "Activate" if not already activated
4. **Expected Result:** Plugin activates without errors and a new "RestroReach" menu appears in the admin sidebar

### Database Tables Verification

1. Install a database browser plugin like "Database Browser" or use phpMyAdmin
2. Check for the following tables:
   - `{prefix}rr_location_tracking`
   - `{prefix}order_assignments`
   - `{prefix}delivery_agents`
3. **Expected Result:** All tables exist with proper structure

### User Roles Verification

1. Go to WordPress Admin → Users → Add New
2. Check the "Role" dropdown
3. **Expected Result:** "Restaurant Manager" and "Delivery Agent" roles are available

## Google Maps API Configuration

### API Key Configuration Test

1. Navigate to RestroReach → Settings
2. Locate the "Google Maps API Settings" section
3. Enter your Google Maps API key
4. Click "Save Changes"
5. **Expected Result:** Settings are saved without errors

### API Key Validation Test

1. Navigate to RestroReach → Settings
2. Locate the "Google Maps API Settings" section
3. Click "Test API Key"
4. **Expected Result:** A success message appears if the API key is valid, or an error message with details if it's invalid

### Test Address Validation

1. Navigate to RestroReach → Settings
2. Locate the "Test Address" section
3. Enter a valid address (e.g., "1600 Amphitheatre Parkway, Mountain View, CA")
4. Click "Validate Address"
5. **Expected Result:** Address successfully geocodes and shows coordinates and formatted address

## Agent Management

### Create Delivery Agent

1. Go to WordPress Admin → Users → Add New
2. Fill in the required information
3. Select "Delivery Agent" as the role
4. Click "Add New User"
5. **Expected Result:** New user is created with Delivery Agent role

### View Agent List

1. Navigate to RestroReach → Agents
2. **Expected Result:** List of delivery agents is displayed with their status and key information

## Agent Live View Testing

### Access Agent Live View Page

1. Navigate to RestroReach → Agent Live View
2. **Expected Result:** Agent Live View page loads with an agent selector dropdown

### Select an Agent to View

1. On the Agent Live View page, use the dropdown to select a delivery agent
2. **Expected Result:** The page reloads with the selected agent's information

### View Agent Location (Simulated if no real data)

1. Select an agent with location data
   - If no real location data exists, manually insert a test record using the Database Browser plugin or follow the debug simulation method described at the bottom of this guide
2. **Expected Result:** A map displays with the agent's position marked and an information card showing agent details

### Agent Location Information

1. On the Agent Live View page with an agent selected
2. Observe the information card beside the map
3. **Expected Result:** Card shows agent name, last update time, battery level, and GPS accuracy information

## WooCommerce Integration Testing

### Custom Order Statuses

1. Go to WooCommerce → Settings → Order Status
2. **Expected Result:** Custom order statuses are present: "Preparing", "Ready for Pickup", "Out for Delivery", "Delivered"

### Create Test Order

1. Go to WooCommerce → Orders → Add New
2. Create a test order with sample products
3. Select a customer and add delivery address
4. Set order status to "Processing"
5. Save the order
6. **Expected Result:** Order is created successfully with the selected status

### Order Status Transitions

1. Go to WooCommerce → Orders
2. Edit the test order you created
3. Change status to "Preparing"
4. Save the order
5. **Expected Result:** Order status updates successfully

### Order Assignment

1. Go to WooCommerce → Orders
2. Edit the test order
3. Look for the "Assign Delivery Agent" section
4. Select a delivery agent from the dropdown
5. Click "Assign Agent"
6. **Expected Result:** Agent is assigned to the order and recorded in the order notes

## GPS Tracking Test

### Manual GPS Simulation

1. Navigate to RestroReach → Tools
2. Locate the "GPS Simulation" section (if implemented)
3. Enter the agent ID, latitude, longitude, accuracy, and battery level
4. Click "Simulate Location Update"
5. **Expected Result:** A success message appears, and the agent's location is updated

### Location Database Verification

1. After simulating a location update, navigate to RestroReach → Agent Live View
2. Select the agent you updated
3. **Expected Result:** The map shows the location you simulated with the correct details

## Route Visualization (If Implemented)

### Order Route Visualization

1. Go to WooCommerce → Orders
2. Edit an order with status "Out for Delivery" and an assigned agent
3. Locate the "Delivery Route" section
4. **Expected Result:** A map shows the route from the restaurant to the customer's address

## Admin Dashboard Testing

### Dashboard Access

1. Navigate to RestroReach → Dashboard
2. **Expected Result:** Dashboard loads with order summary, agent status, and key metrics

### Orders Overview

1. Navigate to RestroReach → Orders
2. **Expected Result:** List of orders is displayed with filtering options and status indicators

### Dashboard Refresh

1. On the RestroReach → Dashboard page
2. Wait 30-60 seconds or click the refresh button if available
3. **Expected Result:** Dashboard updates with latest data without full page reload

## Mobile Interface Testing (If Implemented)

### Mobile Interface Access

1. Log in as a delivery agent user
2. Navigate to the site frontend
3. **Expected Result:** Agent is redirected to the mobile interface or shown a link to access it

## Error Handling Tests

### Missing API Key Test

1. Navigate to RestroReach → Settings
2. Clear the Google Maps API key field
3. Save changes
4. Navigate to RestroReach → Agent Live View
5. **Expected Result:** A message indicating the API key is missing is displayed instead of the map

### Invalid Agent Selection

1. Navigate to RestroReach → Agent Live View
2. Manually modify the URL to include an invalid agent ID
   - Example: `wp-admin/admin.php?page=restroreach-agent-live-view&agent_id=999999`
3. **Expected Result:** An error message is displayed indicating the agent is invalid or not found

## Database Tools (If Implemented)

### Location Data Cleanup

1. Navigate to RestroReach → Tools
2. Locate the "Database Maintenance" section
3. Click "Clean Old Location Data"
4. **Expected Result:** A success message appears indicating how many records were cleaned up

## Debug Testing Methods

### Simulating GPS Data Manually via Database

If the GPS simulation tool is not implemented, you can manually insert location data:

1. Access your WordPress database via phpMyAdmin or similar tool
2. Navigate to the `{prefix}rr_location_tracking` table
3. Insert a new record with the following structure:
   ```sql
   INSERT INTO {prefix}rr_location_tracking 
   (agent_id, latitude, longitude, accuracy, battery_level, created_at) 
   VALUES 
   ({AGENT_USER_ID}, 40.7128, -74.0060, 10, 85, NOW());
   ```
4. Replace `{AGENT_USER_ID}` with the ID of your test delivery agent
5. This simulates a location in New York City with 10-meter accuracy and 85% battery

### Simulating API Key Validation

If you don't have a valid Google Maps API key for testing:

1. You can use the plugin with limited functionality by focusing on non-map features
2. In admin views that require maps, you'll see error messages which you can verify are displayed correctly
3. Alternative: Use a testing API key with limited quota for development purposes

## Testing Notes

- This guide covers testing the core implemented features based on the current development status
- Some features mentioned in the project specifications may not be implemented yet
- When testing GPS functionality, remember that real-world testing on mobile devices will provide the most accurate results
- For WooCommerce integration, ensure you have test products with proper shipping zones set up 