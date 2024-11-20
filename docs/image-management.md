# FlatLayer CMS Image Management

## Overview

FlatLayer CMS provides a robust image management system that handles image processing, optimization, transformations, and delivery. The system is built around the `ImageService`, `ImageTransformationService`, and related components to provide flexible, efficient image handling.

## Prerequisites

Before setting up image handling in FlatLayer CMS, ensure you have:

- PHP 8.2 or higher
- Laravel 11.x
- GD PHP extension (`php-gd`)
- Composer
- Adequate storage configuration

## Core Components

### 1. ImageService

The `ImageService` handles image operations including:
- Adding images to models
- Syncing image collections
- Generating thumbhash previews
- Managing image metadata

```php
use App\Services\Media\MediaLibrary;

class ImageController extends Controller
{
    public function __construct(protected MediaLibrary $imageService) {}

    public function addImage(Entry $entry, Request $request)
    {
        $image = $this->imageService->addImageToModel(
            $entry, 
            $request->file('image')->path(),
            'featured'
        );
    }
}
```

### 2. ImageTransformationService

Handles image transformations including:
- Resizing
- Format conversion
- Quality optimization
- Response generation

```php
use App\Services\Media\ImageTransformer;

class ImageTransformController extends Controller
{
    public function __construct(
        protected ImageTransformer $imageService
    ) {}

    public function transform(ImageTransformRequest $request, int $id, string $extension)
    {
        $media = Image::findOrFail($id);
        $transform = $request->validated();
        $transform['fm'] = $extension;

        $transformedImage = $this->imageService->transformImage(
            $media->path, 
            $transform
        );

        return $this->imageService->createImageResponse(
            $transformedImage, 
            $extension
        );
    }
}
```

## Image Model

The `Image` model provides a robust interface for image management:

```php
class Image extends Model
{
    protected $fillable = [
        'entry_id',
        'collection',
        'filename',
        'path',
        'mime_type',
        'size',
        'dimensions',
        'custom_properties',
        'thumbhash'
    ];

    protected $casts = [
        'dimensions' => 'array',
        'custom_properties' => 'array',
    ];

    public function getWidth(): ?int
    {
        return $this->dimensions['width'] ?? null;
    }

    public function getHeight(): ?int
    {
        return $this->dimensions['height'] ?? null;
    }

    public function getAspectRatio(): ?float
    {
        if ($this->getWidth() && $this->getHeight()) {
            return $this->getWidth() / $this->getHeight();
        }
        return null;
    }
}
```

## Image Processing

### Configuration

The system uses Intervention Image with the GD driver. Configure in your `.env`:

```env
FLATLAYER_MEDIA_MAX_WIDTH=8192
FLATLAYER_MEDIA_MAX_HEIGHT=8192
FLATLAYER_MEDIA_USE_SIGNATURES=true
```

### Transformation API

The image transformation endpoint provides on-the-fly image processing:

```
GET /image/{id}.{extension}
```

Parameters:
- `id`: Image ID from database
- `extension`: Output format (jpg, png, webp, gif)

Query parameters:
- `w`: Width (Optional, max: 8192)
- `h`: Height (Optional, max: 8192)
- `q`: Quality (1-100, Optional, default: 80)
- `fm`: Format override (Optional)

Example requests:
```http
# Basic resize
GET /image/123.webp?w=800&h=600

# High-quality JPEG with specific dimensions
GET /image/123.jpg?w=1200&h=800&q=90

# WebP format with quality setting
GET /image/123.webp?w=1000&q=85
```

### Error Handling

The system includes comprehensive error handling:

```php
try {
    $transformedImage = $this->imageService->transformImage($path, $transforms);
} catch (ImageDimensionException $e) {
    return new JsonResponse(['error' => $e->getMessage()], 400);
} catch (\Exception $e) {
    return new JsonResponse(
        ['error' => 'An error occurred while processing the image'], 
        500
    );
}
```

## Image Collections

The `HasImages` trait provides collection management capabilities:

```php
class Entry extends Model
{
    use HasImages;

    public function addImage(string $path, string $collectionName = 'default'): Image
    {
        return app(ImageService::class)->addImageToModel(
            $this, 
            $path, 
            $collectionName
        );
    }

    public function syncImages(array $paths, string $collectionName = 'default'): void
    {
        app(ImageService::class)->syncImagesForEntry(
            $this, 
            $paths, 
            $collectionName
        );
    }
}
```

## Optimization

### Automatic Optimization

Images are automatically optimized during transformation:
- Proper format selection
- Quality optimization
- Dimension constraints
- Metadata stripping

```php
protected function applyTransformations(\Intervention\Image\Image $image, array $params): void
{
    $originalWidth = $image->width();
    $originalHeight = $image->height();

    ['requestedWidth' => $requestedWidth, 'requestedHeight' => $requestedHeight] = 
        $this->getRequestedDimensions($params);

    $this->validateDimensions(
        $originalWidth, 
        $originalHeight, 
        $requestedWidth, 
        $requestedHeight
    );

    if ($requestedWidth && $requestedHeight) {
        $image->cover($requestedWidth, $requestedHeight);
    } elseif ($requestedWidth) {
        $image->scale(width: $requestedWidth);
    } elseif ($requestedHeight) {
        $image->scale(height: $requestedHeight);
    }
}
```

### Format Support

The system supports multiple image formats:
- JPEG: For photographs
- PNG: For images with transparency
- WebP: Modern format with superior compression
- GIF: For simple animations

## Performance Optimization

### Response Optimization

Responses include appropriate cache headers:
```php
return new Response($imageData, 200, [
    'Content-Type' => $contentType,
    'Content-Length' => strlen($imageData),
    'Cache-Control' => 'public, max-age=31536000',
    'Etag' => md5($imageData),
]);
```

### CDN Integration

FlatLayer CMS is designed to work with CDNs. Recommended providers:
- Cloudflare
- Amazon CloudFront
- Fastly
- Akamai

Configure your CDN to:
- Cache transformation responses
- Respect Cache-Control headers
- Handle image format negotiation
- Optimize edge delivery

## Frontend Integration

While this documentation focuses on backend functionality, FlatLayer provides a frontend SDK with a `ResponsiveImage` component that integrates seamlessly with these image management features. The component handles:
- Responsive image loading
- Format selection
- Lazy loading
- Placeholder generation

Refer to the frontend SDK documentation for details on the `ResponsiveImage` component.

## Best Practices

1. **Image Format Selection**
    - Use WebP with JPEG fallback for photos
    - Use PNG for images requiring transparency
    - Enable format negotiation in your CDN

2. **Dimension Management**
    - Set appropriate max-width/height limits
    - Use responsive image specifications
    - Maintain aspect ratios during transformations

3. **Performance Optimization**
    - Implement lazy loading for below-fold images
    - Use appropriate quality settings
    - Leverage CDN caching
    - Pre-generate common image sizes

4. **Storage Management**
    - Regularly clean unused images
    - Monitor storage usage
    - Implement backup strategies

5. **Security Considerations**
    - Validate image uploads
    - Implement proper access controls
    - Use signed URLs when necessary

## Command Line Tools

FlatLayer provides a CLI command for clearing old image cache files:

```bash
# Clear image cache older than 30 days (default)
php artisan image:clear-cache

# Clear image cache older than a specific number of days
php artisan image:clear-cache 7
```

This command helps manage disk space by removing cached image transformations that haven't been accessed recently.

## Error Handling

Common error scenarios and their handling:

```php
// Image dimension validation
if ($outputDimensions['width'] > $maxWidth) {
    throw new ImageDimensionException(
        "Resulting width ({$outputDimensions['width']}px) would exceed the maximum allowed width ({$maxWidth}px)"
    );
}

// Format support
if (!in_array($format, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
    throw new InvalidArgumentException("Unsupported image format: {$format}");
}

// Processing errors
try {
    $image = $this->manager->read($imagePath);
} catch (\Exception $e) {
    throw new RuntimeException("Failed to process image: {$e->getMessage()}");
}
```

By following these guidelines and leveraging FlatLayer's image management capabilities effectively, you can build a robust and efficient image handling system for your application.

Let me know if you need any clarification or additional examples for specific use cases.
