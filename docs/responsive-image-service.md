# ResponsiveImageService

The `ResponsiveImageService` is a PHP class designed to generate responsive image tags with optimized `srcset` and `sizes` attributes. It's tailored for use with Laravel and a custom Media model.

## Features

- Generates responsive `<img>` tags with appropriate `srcset` and `sizes` attributes
- Supports viewport width (vw), pixel (px), and calculated values (e.g., `calc(100vw - 64px)`)
- Uses predefined breakpoints aligned with common responsive design practices
- Optimizes image sizes for different viewport widths
- Supports both fluid and fixed sizing modes
- Allows specifying a display size that impacts the generated image sizes
- Applies default image transformations via the `$defaultTransforms` constructor parameter

## Usage

### Basic Usage

```php
$service = new ResponsiveImageService(['q' => 80]);
$media = Media::find(1); 
$sizes = ['100vw', 'md:75vw', 'lg:50vw'];
$imgTag = $service->generateImgTag($media, $sizes, ['class' => 'my-image'], true);
```

### Parameters

- `$media`: An instance of the custom Media model that represents the image
- `$sizes`: An array of size definitions for different breakpoints
- `$attributes` (optional): Additional HTML attributes for the img tag
- `$isFluid` (optional): Whether to use fluid sizing (default: true)
- `$displaySize` (optional): The intended display size of the image (width and height)

### Size Definitions

Size definitions can be in the following formats:

- `100vw`: Viewport width percentage
- `500px`: Fixed pixel width
- `calc(100vw - 64px)`: Calculated width
- `md:75vw`: Breakpoint-specific width

Breakpoints:
- `sm`: 640px
- `md`: 768px
- `lg`: 1024px
- `xl`: 1280px
- `2xl`: 1536px

## How It Works

1. **Size Parsing**: The service parses the provided size definitions, handling different formats and breakpoints.

2. **Srcset Generation**:
    - In fluid mode, generates a range of image sizes from the original size down to a minimum size.
    - In fixed mode, generates the base size and a 2x size if possible.
    - If a display size is provided, it is used as the base size and aspect ratio is maintained.

3. **Sizes Attribute**: Creates a `sizes` attribute that correctly represents the parsed sizes, including calculated values.

4. **Image Tag Generation**:
    - Combines the generated `src`, `srcset`, `sizes`, and other attributes into a complete `<img>` tag.
    - Applies the `$defaultTransforms` to the `src` URL.

## Customization

The service can be customized via constructor parameters:

- `$defaultTransforms`: An array of default image transformations to apply (e.g., quality)

It also has some internal constants that control its behavior:

- `DECREMENT`: The factor by which to decrease image sizes in fluid mode (default: 0.9)
- `MIN_SIZE`: The minimum image width to generate (default: 100)
- `MAX_SIZE`: The maximum image width to generate (default: 8192)

## Note

This service is designed to work with a specific Media model and Laravel naming conventions. Ensure your setup is compatible or adjust the service as needed.
