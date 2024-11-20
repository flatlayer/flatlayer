# Storage System

## Overview

Flatlayer CMS implements a flexible storage abstraction layer that handles content files, media assets, and image transformations. The system is built around Laravel's filesystem abstraction and supports multiple storage drivers including local filesystem and S3-compatible storage.

## Storage Configuration

### Repository Configuration

Content repositories are configured using environment variables following this pattern:

```env
CONTENT_REPOSITORY_{TYPE}_PATH=/path/to/content
CONTENT_REPOSITORY_{TYPE}_DRIVER=local|s3
```

Example configurations:

```env
# Local filesystem repository
CONTENT_REPOSITORY_DOCS_PATH=/var/www/content/docs
CONTENT_REPOSITORY_DOCS_DRIVER=local

# S3-based repository
CONTENT_REPOSITORY_BLOG_PATH=content/blog
CONTENT_REPOSITORY_BLOG_DRIVER=s3
CONTENT_REPOSITORY_BLOG_KEY=your-aws-key
CONTENT_REPOSITORY_BLOG_SECRET=your-aws-secret
CONTENT_REPOSITORY_BLOG_REGION=us-west-2
CONTENT_REPOSITORY_BLOG_BUCKET=your-bucket
```

### Available Configuration Options

For all repositories:
```env
CONTENT_REPOSITORY_{TYPE}_PATH=            # Required: Base path
CONTENT_REPOSITORY_{TYPE}_DRIVER=          # Optional: Storage driver (default: local)
```

Additional S3 options:
```env
CONTENT_REPOSITORY_{TYPE}_KEY=             # AWS access key ID
CONTENT_REPOSITORY_{TYPE}_SECRET=          # AWS secret access key
CONTENT_REPOSITORY_{TYPE}_REGION=          # AWS region
CONTENT_REPOSITORY_{TYPE}_BUCKET=          # S3 bucket name
CONTENT_REPOSITORY_{TYPE}_URL=             # Optional: Custom S3 URL
CONTENT_REPOSITORY_{TYPE}_ENDPOINT=        # Optional: Custom endpoint
CONTENT_REPOSITORY_{TYPE}_USE_PATH_STYLE=  # Optional: Use path-style endpoint
```

## Disk Resolution

The `StorageResolver` class handles disk resolution:

```php
use App\Services\Storage\StorageResolver;

class ContentController
{
    public function __construct(protected StorageResolver $resolver) {}

    public function handle(string $type)
    {
        // Resolve disk from repository configuration
        $disk = $this->resolver->resolve(null, $type);

        // Resolve specific disk
        $disk = $this->resolver->resolve('content.blog', $type);

        // Resolve from array configuration
        $disk = $this->resolver->resolve([
            'driver' => 'local',
            'root' => storage_path('app/content'),
        ], $type);
    }
}
```

### Resolution Priority

1. Direct Filesystem instance
2. String disk identifier
3. Array configuration
4. Repository configuration

## Path Handling

All paths are normalized using the `Path` class:

```php
use App\Support\Path;

// Convert file path to slug
$slug = Path::toSlug('docs/getting-started/index.md'); // -> docs/getting-started

// Get parent path
$parent = Path::parent('docs/getting-started/installation'); // -> docs/getting-started

// Get ancestors
$ancestors = Path::ancestors('docs/getting-started/installation');
// -> ['docs', 'docs/getting-started']

// Get siblings
$siblings = Path::siblings('docs/getting-started/installation', $allPaths);
```

### Path Security

The system implements several security measures:

1. Path Normalization
```php
// Converts backslashes to forward slashes
// Collapses multiple slashes
// Removes leading/trailing slashes
$normalized = $this->normalizePath($path);
```

2. Traversal Prevention
```php
// Blocks path traversal attempts
if (preg_match('#(?:^|/)\.\.(?:/|$)|^\.\.?/?$#', $path)) {
    throw new RuntimeException('Path traversal not allowed');
}
```

3. Character Validation
```php
if (preg_match('/[\x00-\x1F\x7F]/', $value)) {
    throw new InvalidArgumentException('Invalid characters in path');
}
```

## Media Storage

### Media Library

The `MediaLibrary` class handles media file operations:

```php
use App\Services\Media\MediaLibrary;

class ImageController
{
    public function __construct(protected MediaLibrary $library) {}

    public function store(Entry $entry, UploadedFile $file)
    {
        // Add image to entry
        $image = $this->library->addImageToModel(
            $entry,
            $file->path(),
            'featured'
        );

        // Sync multiple images
        $this->library->syncImagesForEntry(
            $entry,
            ['image1.jpg', 'image2.jpg'],
            'gallery'
        );
    }
}
```

### Path Resolution

Media paths are resolved relative to content files:

```php
// Resolve absolute path
$path = $this->resolveMediaPath('images/photo.jpg', 'content/post.md');

// Resolve relative path
$path = $this->resolveMediaPath('../images/photo.jpg', 'content/posts/post.md');
```

### File Information

The system tracks comprehensive file metadata:

```php
$fileInfo = $this->getFileInfo($path);

/*
[
    'size' => 1234567,
    'mime_type' => 'image/jpeg',
    'dimensions' => [
        'width' => 1920,
        'height' => 1080
    ],
    'thumbhash' => 'abc123...'
]
*/
```

## Cache Management

### Image Cache

Transformed images are cached with appropriate headers:

```http
Cache-Control: public, max-age=31536000
ETag: "..."
Content-Length: ...
Content-Type: image/...
```

### Cache Cleanup

The system includes a command to clean old cache files:

```bash
# Clear cache files older than 30 days
php artisan image:clear-cache

# Clear cache files older than 7 days
php artisan image:clear-cache 7
```

## Error Handling

The storage system implements comprehensive error handling:

```php
try {
    $content = $this->storage->get($path);
} catch (RuntimeException $e) {
    // Handle missing file
} catch (\Exception $e) {
    // Handle general errors
}
```

Common error cases:
1. File not found
2. Permission denied
3. Invalid path
4. Storage connection failure
5. Quota exceeded

## Performance Considerations

1. **Disk Access**
    - Use appropriate chunk sizes for large files
    - Implement streaming for large downloads
    - Cache frequently accessed files

2. **Path Operations**
    - Cache resolved paths
    - Use efficient path matching algorithms
    - Minimize filesystem operations

3. **Media Processing**
    - Process images asynchronously when possible
    - Cache transformed images
    - Use appropriate image quality settings

## Best Practices

1. **Repository Organization**
   ```
   content/
   ├── posts/
   │   ├── images/
   │   └── *.md
   ├── docs/
   │   ├── images/
   │   └── **/*.md
   └── pages/
       ├── images/
       └── *.md
   ```

2. **File Naming**
    - Use lowercase filenames
    - Use hyphens for spaces
    - Keep filenames descriptive but concise
    - Maintain consistent extensions

3. **Media Organization**
    - Keep images close to content
    - Use meaningful collection names
    - Implement consistent naming patterns
    - Maintain appropriate directory structure

4. **Storage Selection**
   - Use Git repositories for primary storage
   - Use local disk for syncing
   - Consider CDN integration for media
   - Implement appropriate backup strategies

By following these guidelines and understanding the storage system's capabilities, you can effectively manage content and media assets while maintaining good performance and security.
