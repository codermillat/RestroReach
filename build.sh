#!/bin/bash

# RestroReach Asset Minification Build Script
# Production-ready asset optimization for WordPress plugin

echo "🚀 RestroReach Build System - Starting Asset Optimization..."

# Create minified directory if it doesn't exist
mkdir -p assets/css/min
mkdir -p assets/js/min

# Function to minify CSS using PHP (fallback if no npm)
minify_css_php() {
    local input_file="$1"
    local output_file="$2"
    
    php -r "
    \$css = file_get_contents('$input_file');
    // Remove comments
    \$css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', \$css);
    // Remove whitespace
    \$css = str_replace(array(\"\r\n\", \"\r\", \"\n\", \"\t\", '  ', '    ', '    '), '', \$css);
    // Remove remaining whitespace around delimiters
    \$css = preg_replace('/\s*{\s*/', '{', \$css);
    \$css = preg_replace('/;\s*/', ';', \$css);
    \$css = preg_replace('/\s*}\s*/', '}', \$css);
    file_put_contents('$output_file', \$css);
    "
}

# Function to minify JavaScript using PHP (basic minification)
minify_js_php() {
    local input_file="$1"
    local output_file="$2"
    
    php -r "
    \$js = file_get_contents('$input_file');
    // Remove single line comments (but preserve URLs)
    \$js = preg_replace('/(?<!:)\/\/.*$/m', '', \$js);
    // Remove multi-line comments
    \$js = preg_replace('/\/\*[\s\S]*?\*\//', '', \$js);
    // Remove extra whitespace
    \$js = preg_replace('/\s+/', ' ', \$js);
    // Remove whitespace around operators
    \$js = preg_replace('/\s*([{}();,:])\s*/', '\$1', \$js);
    file_put_contents('$output_file', trim(\$js));
    "
}

echo "📦 Minifying CSS files..."

# Minify CSS files
minify_css_php "assets/css/rdm-mobile-agent.css" "assets/css/rdm-mobile-agent.min.css"
minify_css_php "assets/css/rdm-google-maps.css" "assets/css/rdm-google-maps.min.css"
minify_css_php "assets/css/rdm-customer-tracking.css" "assets/css/rdm-customer-tracking.min.css"
minify_css_php "assets/css/rdm-analytics.css" "assets/css/rdm-analytics.min.css"
minify_css_php "assets/css/rdm-payments.css" "assets/css/rdm-payments.min.css"
minify_css_php "assets/css/rdm-admin-orders.css" "assets/css/rdm-admin-orders.min.css"
minify_css_php "assets/css/rdm-notifications.css" "assets/css/rdm-notifications.min.css"
minify_css_php "admin/css/admin-dashboard.css" "admin/css/admin-dashboard.min.css"

echo "⚡ Minifying JavaScript files..."

# Minify JavaScript files
minify_js_php "assets/js/rdm-mobile-agent.js" "assets/js/rdm-mobile-agent.min.js"
minify_js_php "assets/js/rdm-google-maps.js" "assets/js/rdm-google-maps.min.js"
minify_js_php "assets/js/rdm-customer-tracking.js" "assets/js/rdm-customer-tracking.min.js"
minify_js_php "assets/js/rdm-analytics.js" "assets/js/rdm-analytics.min.js"
minify_js_php "assets/js/rdm-payments.js" "assets/js/rdm-payments.min.js"
minify_js_php "assets/js/rdm-admin-orders.js" "assets/js/rdm-admin-orders.min.js"
minify_js_php "admin/js/admin-dashboard.js" "admin/js/admin-dashboard.min.js"

echo "📊 Build Statistics:"
echo "----------------------------------------"

# Calculate compression statistics
for file in assets/css/*.css admin/css/*.css; do
    if [[ ! "$file" == *".min.css" ]] && [[ -f "$file" ]]; then
        original_size=$(wc -c < "$file")
        minified_file="${file%.css}.min.css"
        if [[ -f "$minified_file" ]]; then
            minified_size=$(wc -c < "$minified_file")
            savings=$((original_size - minified_size))
            percentage=$((savings * 100 / original_size))
            echo "CSS: $(basename "$file") - Saved ${percentage}% (${savings} bytes)"
        fi
    fi
done

for file in assets/js/*.js admin/js/*.js; do
    if [[ ! "$file" == *".min.js" ]] && [[ -f "$file" ]]; then
        original_size=$(wc -c < "$file")
        minified_file="${file%.js}.min.js"
        if [[ -f "$minified_file" ]]; then
            minified_size=$(wc -c < "$minified_file")
            savings=$((original_size - minified_size))
            percentage=$((savings * 100 / original_size))
            echo "JS:  $(basename "$file") - Saved ${percentage}% (${savings} bytes)"
        fi
    fi
done

echo "----------------------------------------"
echo "✅ Asset minification completed successfully!"
echo "💡 To use advanced minification with better compression:"
echo "   npm install && npm run build"
echo ""
echo "🔧 Production mode enabled in WordPress will now use .min files" 