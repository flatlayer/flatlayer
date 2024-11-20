# Content Preprocessing

## Overview

Flatlayer CMS processes Markdown files with YAML front matter to create structured content entries. The preprocessing system handles front matter parsing, content extraction, image processing, and relationship management.

## File Structure

### Basic File Format
```markdown
---
title: "My First Post"
type: post
published_at: 2024-01-01
tags: [tutorial, javascript]
images:
  featured: featured.jpg
  gallery: [image1.jpg, image2.jpg]
meta:
  author: John Doe
  category: tutorials
  difficulty: beginner
---

# My First Post

Content goes here...

![Image description](images/inline-image.jpg)
```

### Directory Structure
```
content/
├── posts/
│   ├── my-first-post.md
│   ├── images/
│   │   ├── featured.jpg
│   │   ├── image1.jpg
│   │   └── image2.jpg
│   └── index.md
└── docs/
    ├── getting-started/
    │   ├── index.md
    │   ├── installation.md
    │   └── images/
    │       └── setup-screen.jpg
    └── images/
        └── shared-image.jpg
```

## Front Matter

### Required Fields
```yaml
---
type: post|doc|page  # Content type (required)
title: String        # Content title (required if no H1 in content)
slug: String         # URL slug (optional, defaults to filename)
---
```

### Optional Fields
```yaml
---
# Publication
published_at: 2024-01-01 12:00:00  # Publication date/time
published_at: true                 # Publish immediately
published_at: null                 # Draft status

# Categorization
tags: [tag1, tag2]                # Array of tags
tags: tag1, tag2                  # Comma-separated tags

# Meta Information
meta:
  author: John Doe
  category: Tutorials
  difficulty: beginner
  estimated_time: 15
  prerequisites: [git, php]
  version: 1.0.0
  
# Custom Fields
meta:
  custom_field: value
  nested:
    field: value
---
```

### Image Definitions

#### Single Image
```yaml
---
images:
  featured: path/to/image.jpg
---
```

#### Multiple Images
```yaml
---
images:
  gallery: 
    - path/to/image1.jpg
    - path/to/image2.jpg
---
```

#### Image with Metadata
```yaml
---
images:
  featured: 
    src: path/to/image.jpg
    alt: Image description
    caption: Image caption
resources:
  - name: diagram
    src: path/to/diagram.jpg
    alt: Architecture diagram
---
```

## Image Processing

### Image Paths

Paths can be specified in several ways:

1. **Absolute from content root**
```yaml
images:
  featured: /images/shared/hero.jpg
```

2. **Relative to content file**
```yaml
images:
  featured: ./images/local-hero.jpg
  gallery: 
    - ../shared/image1.jpg
    - ./images/image2.jpg
```

3. **Collection-specific**
```yaml
images:
  featured: featured.jpg     # Searches in local directory
  gallery: [one.jpg, two.jpg]
  thumbnails: thumbs/*.jpg   # Supports patterns
```

### Inline Images

Images in Markdown content are automatically processed:

```markdown
![Alt text](image.jpg)

![Alt text](./images/local.jpg)

![Alt text](../shared/image.jpg)
```

The system will:
1. Resolve relative paths
2. Create image records
3. Replace markdown with responsive components
4. Generate thumbnails/previews
5. Calculate image hashes

### Image Collections

Images can be organized into named collections:

```yaml
---
images:
  # Featured image
  featured: hero.jpg
  
  # Gallery images
  gallery: 
    - gallery/one.jpg
    - gallery/two.jpg
    
  # Thumbnails
  thumbnails:
    - thumbs/small1.jpg
    - thumbs/small2.jpg
---
```

Each collection is processed independently and can have different handling rules.

## Directory Organization

### Recommended Structure

```
content-type/
├── index.md                  # Section index
├── entry-name.md            # Content file
├── images/                  # Local images
│   ├── featured/           # Featured images
│   ├── gallery/            # Gallery images
│   └── inline/             # Content images
├── subsection/             # Nested content
│   ├── index.md           # Subsection index
│   ├── page.md            # Subsection content
│   └── images/            # Subsection images
└── _shared/               # Shared resources
    └── images/            # Shared images
```

### Path Resolution

The system follows this resolution order:
1. Absolute paths from content root
2. Relative to content file
3. Local images directory
4. Parent images directory
5. Shared images directory

## Content Processing

### Markdown Processing

The system processes Markdown files in several stages:

1. **Front Matter Extraction**
```php
$document = $frontMatter->parse($content);
$data = $document->getData();
$markdownContent = $document->getContent();
```

2. **Title Extraction**
```php
[$title, $content] = $this->extractTitleFromContent($markdownContent);
$title = $data['title'] ?? $title;
```

3. **Image Processing**
```php
$content = $this->processMarkdownImages($model, $content, $relativePath);
```

4. **Tag Processing**
```php
if ($tags = $data['tags'] ?? null) {
    $model->syncTags($this->normalizeTagValue($tags));
}
```

### Special Files

1. **Index Files**
    - `index.md` represents directory content
    - Slug is parent directory name
    - Can contain section metadata

2. **Ordered Content**
    - Support numeric prefixes: `01-introduction.md`
    - Maintains order in listings
    - Prefix is removed from slug

## Data Normalization

### Tag Normalization
```php
protected function normalizeTagValue(mixed $value): array
{
    if (is_string($value)) {
        return array_filter(array_map('trim', explode(',', $value)));
    }
    
    if (is_array($value)) {
        return array_values(array_filter(array_map('trim', $value)));
    }
    
    return [];
}
```

### Date Normalization
```php
protected function normalizeDateValue(mixed $value, string $format = 'Y-m-d H:i:s'): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    if ($value === true) {
        return now()->format($format);
    }

    if (is_string($value)) {
        return Carbon::parse($value)->format($format);
    }
    
    return null;
}
```

### Meta Normalization
```php
protected function normalizeMetaData(array $meta): array
{
    // Remove null/empty values
    $meta = array_filter($meta, fn($value) => 
        $value !== null && $value !== ''
    );

    // Sort keys
    ksort($meta);

    return $meta;
}
```

## Error Handling

Common preprocessing errors:

1. **Invalid Front Matter**
```http
Status: 400 Bad Request
{
    "error": "Invalid YAML front matter: line 2, column 3"
}
```

2. **Missing Required Fields**
```http
Status: 400 Bad Request
{
    "error": "Missing required field: type"
}
```

3. **Invalid Image Path**
```http
Status: 400 Bad Request
{
    "error": "Invalid image path: ../outside/content.jpg"
}
```

4. **File Not Found**
```http
Status: 404 Not Found
{
    "error": "File not found: missing-file.md"
}
```

## Best Practices

1. **Content Organization**
    - Use clear directory structures
    - Keep related content together
    - Use index files for sections
    - Maintain consistent naming

2. **Image Management**
    - Organize images by purpose
    - Use descriptive filenames
    - Keep images close to content
    - Share common images appropriately

3. **Front Matter**
    - Include all required fields
    - Use consistent date formats
    - Organize meta fields logically
    - Document custom fields

4. **File Naming**
    - Use lowercase names
    - Use hyphens for spaces
    - Be descriptive but concise
    - Include ordering if needed

By following these guidelines and understanding the preprocessing system, you can create well-organized content that processes efficiently and reliably.
