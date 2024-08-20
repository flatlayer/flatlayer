# FlatLayer CMS Image Management

This guide provides detailed instructions for managing images in your FlatLayer CMS project. It covers image processing, optimization, and usage.

## Prerequisites

Before setting up image handling in FlatLayer CMS, ensure you have the following:

- PHP 8.2 or higher
- Composer
- Laravel 11.x
- GD PHP extension (typically pre-installed on most PHP installations)

## Image Processing Setup

FlatLayer CMS uses the Intervention Image library with the GD driver for image processing. This library is already included in the project dependencies.

1. Verify GD installation:

   ```bash
   php -m | grep gd
   ```

   If GD is not listed, you'll need to install it. On most systems, you can use:

   ```bash
   sudo apt-get install php-gd
   ```

   Restart your web server after installation.

2. The image processing is automatically configured to use the GD driver in the `ImageTransformationService`.

## Image Optimization

FlatLayer CMS uses the Intervention Image library's built-in optimization features when processing images.

The optimization is automatically applied when processing images through the `ImageTransformationService`.

## Image Transformations

FlatLayer CMS provides an API endpoint for image transformations. You can resize, change the format, and adjust the quality of images on-the-fly.

### Transformation API

To transform an image, use the following endpoint:

```
GET /image/{id}.{extension}
```

Parameters:
- `id`: The ID of the image in the database
- `extension`: The desired output format (jpg, png, webp, gif)

Query parameters:
- `w`: Width (optional)
- `h`: Height (optional)
- `q`: Quality (1-100, optional)

Example:
```
GET /image/123.webp?w=800&h=600&q=80
```

This will retrieve image with ID 123, convert it to WebP format, resize it to 800x600 pixels, and set the quality to 80%.

## CDN Integration

FlatLayer CMS no longer implements its own caching system for images. Instead, it's recommended to use a Content Delivery Network (CDN) for caching and serving images. Popular CDN options include:

- Cloudflare
- Amazon CloudFront
- Fastly
- Akamai

When setting up your CDN, configure it to cache responses from your image transformation endpoint. This will provide efficient delivery of transformed images to your users.

## Best Practices

1. **Use appropriate image formats**: WebP is generally the best choice for web images due to its superior compression. Use JPEG for photographs and PNG for images with transparency.

2. **Responsive images**: Use the `srcset` attribute in your HTML to provide multiple image sizes for different device resolutions.

3. **Lazy loading**: Implement lazy loading for images that are not immediately visible on page load to improve initial page load times.

4. **Optimize original uploads**: While FlatLayer CMS optimizes images during transformation, it's still beneficial to upload pre-optimized images to reduce storage and processing requirements.

## Conclusion

With these guidelines, your FlatLayer CMS should be fully set up for efficient image processing and optimization. By leveraging a CDN for caching and delivery, you can ensure fast loading times for your images across different geographical locations.
