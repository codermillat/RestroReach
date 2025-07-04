# Restaurant Delivery Manager - Future Enhancements
## Planned Features for Post-MVP Development

This document outlines planned feature enhancements for the Restaurant Delivery Manager plugin that are beyond the scope of the initial 2-week development sprint. These features have been identified as valuable additions but are scheduled for future implementation.

---

## 🗺️ CHECKOUT PAGE MAP ENHANCEMENTS

### 1. Checkout Map - Store Location & Delivery Eligibility Visualizer

**Feature Status:** ❌ NOT IMPLEMENTED - Future Enhancement

**User Story:** 
> "As a customer on the checkout page, I want to see a map displaying the restaurant's location and my entered delivery address, so I can visually confirm if my address is likely within their delivery range before placing an order."

#### Functional Requirements:

- Interactive Google Map displayed on the WooCommerce checkout page after customer enters shipping address
- Clear marker showing the configured restaurant/store location
- Dynamic marker showing the customer's currently entered shipping address
- Automatic panning and zooming to appropriately display both markers
- Visual aid complementing the existing shipping method availability determined by shipping zone and distance logic

#### Technical Considerations:

- Integration into WooCommerce checkout page template or via hooks (`woocommerce_after_checkout_billing_form` or similar)
- JavaScript initialization of Google Maps with responsive design
- Geocoding of customer's address if not already done by shipping calculator
- Use of existing `RDM_Google_Maps::get_restaurant_coordinates()` method for restaurant location
- Optimization for performance to minimize impact on checkout page load time
- Compatibility with multiple shipping methods
- Mobile-responsive design with appropriate controls

#### Implementation Approach:

```php
/**
 * Add checkout map after billing form
 */
add_action('woocommerce_after_checkout_billing_form', 'rdm_add_checkout_map');

function rdm_add_checkout_map($checkout) {
    // Check if Maps API is enabled
    if (!RDM_Google_Maps::is_enabled()) {
        return;
    }
    
    // Get restaurant coordinates
    $restaurant_coords = RDM_Google_Maps::get_restaurant_coordinates();
    
    if (!$restaurant_coords) {
        return;
    }
    
    // Output map container
    echo '<div class="rdm-checkout-map-container">';
    echo '<h3>' . esc_html__('Delivery Location', 'restaurant-delivery-manager') . '</h3>';
    echo '<div id="rdm-checkout-map" style="height: 300px; margin-bottom: 20px;"></div>';
    echo '</div>';
    
    // Enqueue maps script with customer address detection
    wp_enqueue_script('rdm-checkout-map');
}
```

---

### 2. Checkout Map - Visual Delivery Zone/Radius

**Feature Status:** ❌ NOT IMPLEMENTED - Future Enhancement

**User Story:** 
> "As a customer viewing the store and my location on the checkout map, I want to see a visual representation of the restaurant's maximum delivery radius or defined delivery zone, so I can better understand if I fall within their service area."

#### Functional Requirements:

- Extension of the Store Location & Delivery Eligibility Visualizer map
- Visual overlay representing the delivery service area as either:
  - Simple circle if using "Maximum Delivery Distance" as the primary constraint
  - Complex polygon if custom delivery zone shapes are implemented
- Clear visual indication of the delivery boundary
- Visually distinct styling for customer's marker when inside vs. outside the delivery zone
- Enhanced visual confirmation of delivery eligibility

#### Technical Considerations:

- Retrieval of "Maximum Delivery Distance" setting from the active shipping method
- Storage and retrieval system for polygon coordinates if using custom zone shapes
- JavaScript implementation using `google.maps.Circle` for radius-based zones
- JavaScript implementation using `google.maps.Polygon` for custom-shaped zones
- Color-coding for in-zone vs. out-of-zone customer markers
- Potential user feedback when customer is outside delivery zone
- Performance considerations when rendering complex polygons

#### Implementation Approach:

```javascript
// Example JavaScript for radius-based delivery zone visualization
function initCheckoutMap() {
    // Initialize map with restaurant and customer markers
    
    // Add delivery radius circle
    const deliveryRadius = parseFloat(rdmCheckoutMapData.maxDeliveryDistance) * 1000; // Convert km to meters
    
    const deliveryZone = new google.maps.Circle({
        strokeColor: '#FF6384',
        strokeOpacity: 0.8,
        strokeWeight: 2,
        fillColor: '#FF6384',
        fillOpacity: 0.1,
        map: map,
        center: restaurantPosition,
        radius: deliveryRadius
    });
    
    // Check if customer is in delivery zone
    const isInDeliveryZone = google.maps.geometry.spherical.computeDistanceBetween(
        customerPosition, 
        restaurantPosition
    ) <= deliveryRadius;
    
    // Update customer marker based on in-zone status
    customerMarker.setIcon({
        url: isInDeliveryZone ? 'in-zone-marker.png' : 'out-zone-marker.png',
        // Other marker options
    });
    
    // Optional: Display message if outside delivery zone
    if (!isInDeliveryZone) {
        document.getElementById('rdm-delivery-notice').innerHTML = 
            '<div class="woocommerce-info">Your location appears to be outside our standard delivery area. Additional fees may apply.</div>';
    }
}
```

## 📋 IMPLEMENTATION CONSIDERATIONS

### Development Priority

These checkout map enhancements are considered **medium priority** for post-MVP development, as they significantly enhance the customer experience but are not critical for the core delivery management functionality.

### API Usage Impact

Implementing these features will increase Google Maps API usage. Considerations:

- Each checkout page load will generate 1-3 additional API calls
- Geocoding customer addresses adds Geocoding API usage
- Using the `geometry` library for distance calculations adds to API complexity
- Caching strategies should be implemented to minimize API calls

### User Experience Benefits

- Reduces customer confusion about delivery availability
- Provides visual confirmation of delivery address correctness
- Potentially reduces failed/canceled orders due to delivery area misunderstandings
- Enhances the modern, professional feel of the checkout experience

### Integration with Existing Features

These enhancements would complement:
- Distance-based shipping calculations
- Delivery area management
- Google Maps integration foundation
- Address validation system

## 🔄 FUTURE ROADMAP PLACEMENT

These features should be considered for implementation after the completion of the initial MVP and critical post-MVP features, likely in the second phase of development following the initial 2-week sprint. 