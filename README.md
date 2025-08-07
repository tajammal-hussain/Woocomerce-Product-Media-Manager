# WooCommerce Product Media Manager

A comprehensive WordPress plugin that enhances WooCommerce products with advanced media management capabilities, including automatic watermarking, image galleries, and custom product variations.


<img width="1412" height="843" alt="image" src="https://github.com/user-attachments/assets/99a2b36a-f6bc-4f43-a259-5e5a8294df55" />
<img width="1830" height="881" alt="image" src="https://github.com/user-attachments/assets/4b6a3735-377e-44a0-88b7-b0464a393a40" />


<img width="1897" height="852" alt="image" src="https://github.com/user-attachments/assets/f7cc5077-b41e-4408-981a-77efc46210c8" />

## 🚀 Features

### 📸 **Advanced Media Management**
- **Bulk Image Upload**: Drag-and-drop interface for uploading multiple images at once
- **Media Library Integration**: Import existing images from WordPress media library
- **Image Organization**: Sort and reorder product images with drag-and-drop functionality
- **SKU Management**: Assign custom SKUs to individual images for inventory tracking
- **Image Preview**: Thumbnail previews for both original and watermarked images

### 🎨 **Automatic Watermarking System**
- **Text Watermarks**: Add custom text watermarks with configurable fonts, colors, and positioning
- **Image Watermarks**: Use custom images as watermarks with transparency support
- **Flexible Positioning**: 5 position options (top-left, top-right, bottom-left, bottom-right, center)
- **Opacity Control**: Adjustable watermark transparency (0-100%)
- **Quality Settings**: Configurable output quality for optimal file sizes
- **Real-time Preview**: See watermarked results immediately after generation

### 🛒 **WooCommerce Integration**
- **Cart Display**: Watermarked images shown in cart with SKU information
- **Order Management**: Original images stored in order records for clean documentation
- **Product Variations**: Each watermarked image creates unique product variations
- **Checkout Process**: Seamless integration with WooCommerce checkout flow

### 🎯 **Frontend Gallery System**
- **Masonry Layout**: Beautiful responsive gallery using Isotope.js
- **Infinite Scroll**: Load more images automatically as users scroll
- **Product Switching**: Switch between different products within the same category
- **Lightbox Integration**: Fancybox support for image viewing
- **Category-based Galleries**: Display galleries filtered by product categories

### ⚙️ **Admin Dashboard**
- **Settings Panel**: Comprehensive watermark configuration options
- **Media Management**: Intuitive interface for managing product media
- **Bulk Operations**: Generate watermarks for multiple images at once
- **Order Tracking**: View selected images in order details

## 📋 Requirements

- **WordPress**: 5.0 or higher
- **WooCommerce**: 5.0 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.6 or higher
- **Memory Limit**: 256MB minimum (512MB recommended)

## 🛠️ Installation

### Method 1: WordPress Admin (Recommended)
1. Download the plugin ZIP file
2. Go to **WordPress Admin → Plugins → Add New**
3. Click **Upload Plugin** and select the ZIP file
4. Click **Install Now** and then **Activate**

### Method 2: Manual Installation
1. Extract the plugin files to `/wp-content/plugins/woocommerce-product-media-manager/`
2. Go to **WordPress Admin → Plugins**
3. Find "WooCommerce Product Media Manager" and click **Activate**

## 🔧 Configuration

### Initial Setup
1. **Activate Plugin**: The plugin will automatically create necessary database tables
2. **Configure Watermarks**: Go to **WooCommerce → Product Media Manager → Settings**
3. **Set Default Options**: Configure watermark text, position, opacity, and quality

### Watermark Settings
- **Enable/Disable**: Toggle watermarking functionality
- **Watermark Type**: Choose between text or image watermarks
- **Text Settings**: Configure font, size, color, and background
- **Image Settings**: Upload custom watermark images with scale control
- **Position**: Select from 5 positioning options
- **Opacity**: Adjust transparency (0-100%)
- **Quality**: Set output quality (1-100)

## 📖 Usage Guide

### For Administrators

#### Managing Product Media
1. **Edit Product**: Go to any WooCommerce product
2. **Media Manager Tab**: Find the "Product Media Manager" tab
3. **Upload Images**: Use drag-and-drop or media library import
4. **Generate Watermarks**: Click "Generate Watermark" for each image
5. **Set SKUs**: Assign custom SKUs to track inventory
6. **Save Changes**: Update the product to apply changes

#### Bulk Operations
1. **Select Multiple Images**: Use checkboxes to select multiple images
2. **Bulk Watermark**: Generate watermarks for all selected images
3. **Bulk SKU Update**: Update SKUs for multiple images at once

#### Gallery Management
1. **Create Shortcodes**: Use `[wc_pmm_simple_gallery category="your-category"]`
2. **Customize Display**: Modify CSS for custom styling
3. **Infinite Scroll**: Automatically loads more images as users scroll

### For Customers

#### Shopping Experience
1. **Browse Gallery**: View watermarked images in product galleries
2. **Select Images**: Click on images to add specific variations to cart
3. **Cart Review**: See selected watermarked images in cart
4. **Checkout**: Complete purchase with selected image variations
5. **Order Confirmation**: Receive order with original image references

#### Gallery Features
- **Responsive Design**: Works on all devices and screen sizes
- **Masonry Layout**: Beautiful grid layout with automatic positioning
- **Infinite Scroll**: Seamlessly loads more images
- **Product Switching**: Switch between different products in same category
- **Lightbox Viewing**: Click images for full-size viewing

## 🎨 Customization

### CSS Customization
```css
/* Custom gallery styling */
.wc-pmm-simple-gallery {
    margin: 20px 0;
}

.wc-pmm-product-link {
    color: #333;
    font-weight: bold;
}

.wc-pmm-watermarked-image {
    border-radius: 8px;
    transition: transform 0.3s ease;
}

.wc-pmm-watermarked-image:hover {
    transform: scale(1.05);
}
```

### JavaScript Customization
```javascript
// Custom Isotope configuration
jQuery(document).ready(function($) {
    $('.isotope-grid').isotope({
        itemSelector: '.gallery-col',
        layoutMode: 'masonry',
        masonry: {
            columnWidth: '.gallery-col',
            gutter: 15
        }
    });
});
```

### PHP Hooks and Filters
```php
// Custom watermark settings
add_filter('wc_pmm_watermark_settings', function($settings) {
    $settings['watermark_text'] = 'Your Custom Text';
    $settings['watermark_position'] = 'bottom-right';
    return $settings;
});

// Custom gallery display
add_action('wc_pmm_before_gallery', function($category_slug) {
    echo '<div class="custom-gallery-header">';
    echo '<h2>Custom Gallery for ' . esc_html($category_slug) . '</h2>';
    echo '</div>';
});
```

## 🔌 API Reference

### Shortcodes
- `[wc_pmm_simple_gallery category="category-slug"]` - Display gallery for specific category

### Database Tables
- `wp_wc_pmm_watermark_settings` - Watermark configuration
- `wp_postmeta` - Product media data (key: `_wc_pmm_product_media`)

### AJAX Endpoints
- `wc_pmm_upload_image` - Upload new images
- `wc_pmm_generate_watermark` - Generate watermarks
- `wc_pmm_update_image_sku` - Update image SKUs
- `wc_pmm_add_to_cart_with_image` - Add image variations to cart

## 🚀 Performance Optimization

### Recommended Settings
- **Image Quality**: 85-90% for optimal balance
- **Watermark Opacity**: 60-80% for visibility
- **Batch Processing**: Process images in batches of 10-20
- **Caching**: Enable WordPress object caching

### Server Requirements
- **PHP Memory**: 512MB minimum
- **Upload Limit**: 64MB minimum
- **Execution Time**: 300 seconds for bulk operations
- **Image Processing**: GD or Imagick extension required

## 🔒 Security Features

- **Nonce Verification**: All AJAX requests include security nonces
- **Permission Checks**: Role-based access control
- **File Validation**: Strict file type and size validation
- **SQL Injection Protection**: Prepared statements for all database queries
- **XSS Prevention**: Output escaping on all user data

## 🐛 Troubleshooting

### Common Issues

#### Watermarks Not Generating
- **Check PHP Extensions**: Ensure GD or Imagick is installed
- **Memory Limit**: Increase PHP memory limit to 512MB
- **File Permissions**: Ensure upload directory is writable

#### Gallery Not Loading
- **JavaScript Errors**: Check browser console for errors
- **Isotope Library**: Ensure Isotope.js is properly loaded
- **CSS Conflicts**: Check for theme CSS conflicts

#### Cart Issues
- **WooCommerce Version**: Ensure WooCommerce 5.0+ is installed
- **Session Data**: Clear browser cache and cookies
- **Database Integrity**: Check for corrupted order meta data

### Debug Mode
Enable WordPress debug mode to see detailed error messages:
```php
// Add to wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## 📈 Changelog

### Version 1.0.0
- Initial release
- Basic watermarking functionality
- WooCommerce integration
- Frontend gallery system
- Admin dashboard
- Infinite scroll
- Cart and order management

## 🤝 Support

### Getting Help
- **Documentation**: Check this README for detailed instructions
- **WordPress Support**: Post questions in WordPress.org forums
- **GitHub Issues**: Report bugs and feature requests on GitHub

### Contributing
1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## 📄 License

This plugin is licensed under the GPL v2 or later.

## 🙏 Credits

- **Isotope.js**: For masonry layout functionality
- **Fancybox**: For lightbox image viewing
- **WordPress**: For the amazing platform
- **WooCommerce**: For e-commerce functionality

## 🔮 Roadmap

### Planned Features
- **Video Support**: Watermark video files
- **Batch Processing**: Process multiple products at once
- **API Integration**: REST API for external applications
- **Advanced Analytics**: Track image usage and performance
- **Mobile App**: Native mobile application
- **Cloud Storage**: Integration with cloud storage services

### Future Enhancements
- **AI Watermarking**: AI-powered watermark placement
- **Template System**: Pre-built watermark templates
- **Multi-language**: Internationalization support
- **Advanced Filters**: More image filtering options
- **Export/Import**: Bulk data export and import

---

**Made with ❤️ for the WordPress community** 
