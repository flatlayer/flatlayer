# ResponsiveImageService

The `ResponsiveImageService` is a PHP class designed to generate responsive image tags with optimized `srcset` and `sizes` attributes. It's tailored for use with Laravel and follows a mobile-first approach, similar to Tailwind CSS.

## Features

- Generates responsive `<img>` tags with appropriate `srcset` and `sizes` attributes
- Supports viewport width (vw) and pixel (px) units
- Allows for calculated values (e.g., `100vw - 64px`)
- Uses predefined breakpoints aligned with common responsive design practices
- Implements a mobile-first approach
- Optimizes image sizes for different viewport widths
- Prevents generation of unnecessary image sizes

## Usage

### Basic Usage

```php
$service = new ResponsiveImageService();
$media = Media::find(1);
$sizes = ['100vw', 'md:75vw', 'lg:50vw', 'xl:calc(33vw - 64px)'];
$imgTag = $service->generateImgTag($media, $sizes);
```

### Parameters

- `$media`: An instance of your Media model that represents the image
- `$sizes`: An array of size definitions for different breakpoints
- `$attributes` (optional): Additional HTML attributes for the img tag

### Size Definitions

Size definitions can be in the following formats:

- `100vw`: Viewport width percentage
- `500px`: Fixed pixel width
- `calc(100vw - 64px)`: Calculated width
- `md:75vw`: Breakpoint-specific width (using predefined breakpoints)

Breakpoints:
- `sm`: 640px
- `md`: 768px
- `lg`: 1024px
- `xl`: 1280px
- `2xl`: 1536px

## How It Works

1. **Size Parsing**: The service parses the provided size definitions, handling different formats and breakpoints.

2. **Srcset Generation**:
    - Starts from the smallest size (minimum 240px) and works up to the largest defined size.
    - Generates intermediate sizes between breakpoints using a 10% increment.
    - Ensures that unnecessary sizes are not generated.

3. **Sizes Attribute**: Creates a `sizes` attribute that correctly represents the parsed sizes, including calculated values.

4. **Image Tag Generation**: Combines all the generated attributes into a complete `<img>` tag.

## Customization

You can customize the service by modifying the following properties:

- `$breakpoints`: Adjust or add custom breakpoints
- `$minWidth`: Change the minimum width for generated images (default: 240px)
- `$increment`: Modify the increment factor for generating intermediate sizes (default: 0.1 or 10%)

## Note

This service is designed to work with a specific Media model setup. Ensure your Media model is compatible or adjust the service as needed for your specific implementation.
