# Image Transformation API

## Overview

The Image Transformation API provides dynamic image processing capabilities through a single RESTful endpoint. It supports resizing, format conversion, and quality optimization for images stored in the system.

## Endpoint

```http
GET /images/{id}.{extension}
```

### Parameters

#### Path Parameters
- `id`: The numeric ID of the image (required)
- `extension`: The desired output format (required)
    - Supported values: `jpg`, `jpeg`, `png`, `webp`, `gif`

#### Query Parameters
- `w`: Width in pixels (optional, max: 8192)
- `h`: Height in pixels (optional, max: 8192)
- `q`: Quality percentage (optional, 1-100, default: 80)
- `fm`: Format override (optional, overrides extension)

### Example Requests

Basic resize operation:
```http
GET /images/123.jpg?w=800&h=600
```

High quality transformation:
```http
GET /images/123.webp?w=1200&h=800&q=95
```

Format conversion:
```http
GET /images/123.webp?w=1000
```

### Responses

#### Success Response
```http
Status: 200 OK
Content-Type: image/jpeg (or appropriate mime type)
Cache-Control: public, max-age=31536000
```

The response body contains the transformed image data.

#### Error Responses

Invalid dimensions:
```http
Status: 400 Bad Request
Content-Type: application/json

{
    "error": "Requested width (9000px) would exceed the maximum allowed width (8192px)"
}
```

Image not found:
```http
Status: 404 Not Found
Content-Type: application/json

{
    "error": "Image not found"
}
```

Processing error:
```http
Status: 500 Internal Server Error
Content-Type: application/json

{
    "error": "An error occurred while processing the image"
}
```

### Image Metadata

To retrieve image metadata without processing:

```http
GET /images/{id}/metadata
```

Response:
```json
{
    "width": 1920,
    "height": 1080,
    "mime_type": "image/jpeg",
    "size": 1048576,
    "filename": "example.jpg",
    "thumbhash": "j4aFhYaX..."
}
```

## Transformation Rules

### Resizing Behavior

1. **Width and Height Specified**
    - Image is resized to exactly match the specified dimensions
    - Aspect ratio is preserved using cover mode
    - Excess image area is cropped from center

2. **Width Only**
    - Image is scaled to the specified width
    - Height is automatically calculated to maintain aspect ratio

3. **Height Only**
    - Image is scaled to the specified height
    - Width is automatically calculated to maintain aspect ratio

### Format Support

#### JPEG
- Best for photographs
- Quality parameter highly effective
- No alpha channel support

#### PNG
- Supports transparency
- Quality parameter ignored
- Larger file sizes than JPEG/WebP

#### WebP
- Modern format with superior compression
- Supports transparency
- Quality parameter supported
- Best overall choice for web delivery

#### GIF
- Limited to 256 colors
- Quality parameter ignored
- Supports simple animations (preserved during resizing)

### Dimension Limits

- Maximum width: 8192 pixels
- Maximum height: 8192 pixels
- Both dimensions must be positive integers
- Either width or height must be specified

## Error Handling

### Common Error Cases

1. **Dimension Errors**
   ```http
   Status: 400 Bad Request
   ```
    - Width/height exceeds maximum allowed
    - Negative dimensions
    - Non-integer dimensions

2. **Format Errors**
   ```http
   Status: 400 Bad Request
   ```
    - Unsupported output format
    - Invalid format conversion requested

3. **Quality Errors**
   ```http
   Status: 400 Bad Request
   ```
    - Quality value out of range (1-100)
    - Non-integer quality value

4. **Resource Errors**
   ```http
   Status: 404 Not Found
   ```
    - Image ID not found
    - Original file missing

5. **Processing Errors**
   ```http
   Status: 500 Internal Server Error
   ```
    - Image corruption
    - Memory exhaustion
    - Storage issues

### Validation Rules

```php
[
    'w' => 'sometimes|integer|min:1|max:8192',
    'h' => 'sometimes|integer|min:1|max:8192',
    'q' => 'sometimes|integer|between:1,100',
    'fm' => 'sometimes|in:jpg,jpeg,png,webp,gif'
]
```

## Performance Considerations

### Response Headers

All successful responses include:
```http
Cache-Control: public, max-age=31536000
ETag: "..."
Content-Length: ...
Content-Type: image/...
```

### Best Practices

1. **Dimension Selection**
    - Request only needed dimensions
    - Consider device DPI/scaling
    - Use appropriate quality values

2. **Format Selection**
    - Use WebP where supported
    - Provide JPEG fallback
    - Match format to content type

3. **Cache Optimization**
    - Consistent transformation parameters
    - Leverage browser caching
    - Use CDN when possible

4. **Resource Management**
    - Limit concurrent transformations
    - Monitor resource usage
    - Clean up unused cache files

## Examples

### Basic Transformations

Resize to specific dimensions:
```http
GET /images/123.jpg?w=800&h=600
```

High-quality WebP conversion:
```http
GET /images/123.webp?w=1200&q=90
```

Scale by width only:
```http
GET /images/123.jpg?w=1000
```

### Common Use Cases

Thumbnail generation:
```http
GET /images/123.jpg?w=150&h=150
```

Responsive image source:
```http
GET /images/123.webp?w=1200&q=80
```

Preview generation:
```http
GET /images/123.jpg?w=400&q=60
```

This endpoint provides a flexible and powerful way to transform images dynamically while maintaining performance through effective caching and optimization strategies.
