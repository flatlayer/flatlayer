# Document Organization Guide

A comprehensive guide to organizing your documentation hierarchy in Flatlayer CMS.

## Overview

Flatlayer CMS provides flexible ways to organize your documentation through a combination of:
- Directory structure
- Index files (`index.md`)
- Navigation ordering (`nav_order` in front matter)
- Metadata and tagging

## Directory Structure

The directory structure directly maps to your documentation's URL paths and hierarchy. Here's an example structure:

```
docs/
├── index.md                # /docs
├── getting-started/
│   ├── index.md           # /docs/getting-started
│   ├── installation.md    # /docs/getting-started/installation
│   └── configuration.md   # /docs/getting-started/configuration
├── guides/
│   ├── index.md           # /docs/guides
│   ├── basic/
│   │   ├── index.md       # /docs/guides/basic
│   │   └── first-steps.md # /docs/guides/basic/first-steps
│   └── advanced/
│       ├── index.md       # /docs/guides/advanced
│       └── security.md    # /docs/guides/advanced/security
└── api/
    ├── index.md           # /docs/api
    ├── authentication.md  # /docs/api/authentication
    └── endpoints.md       # /docs/api/endpoints
```

### Best Practices

1. Keep URLs meaningful and descriptive
2. Use consistent naming conventions
3. Limit nesting depth (recommended max: 3-4 levels)
4. Group related content in directories
5. Use hyphen-case for filenames (`getting-started.md`, not `getting_started.md`)

## Using Index Files

Index files (`index.md`) serve as section landing pages and help organize your documentation hierarchy.

### Purpose of Index Files

- Create section landing pages
- Provide overview content for each section
- Enable cleaner URLs (e.g., `/docs/guides` instead of `/docs/guides.html`)
- Define section-level metadata

### Example Index File

```markdown
---
title: Developer Guides
type: doc
meta:
  section: guides
  nav_order: 2
  description: Complete guides for developers
  icon: book-open
---

# Developer Guides

Welcome to our developer guides section. Here you'll find comprehensive
documentation for both beginners and advanced users.

## Available Guides

- [Basic Guides](basic)
- [Advanced Guides](advanced)
```

## Navigation Ordering

Flatlayer CMS uses the `nav_order` metadata field to control the order of navigation items.

### Using nav_order

Add `nav_order` to the front matter of your markdown files:

```yaml
---
title: Installation Guide
type: doc
meta:
  nav_order: 1  # Lower numbers appear first
  section: getting-started
---
```

### Ordering Rules

1. Items are sorted by `nav_order` first
2. Items without `nav_order` appear after items with `nav_order`
3. Items with the same `nav_order` are sorted alphabetically by title
4. Lower numbers appear before higher numbers

### Example Section Structure

```yaml
# index.md (nav_order: 1)
---
title: Documentation
type: doc
meta:
  nav_order: 1
---

# getting-started/index.md (nav_order: 2)
---
title: Getting Started
type: doc
meta:
  nav_order: 2
---

# guides/index.md (nav_order: 3)
---
title: Guides
type: doc
meta:
  nav_order: 3
---
```

## Advanced Organization

### Section Metadata

Use metadata to enhance section organization:

```yaml
---
title: Advanced Topics
type: doc
meta:
  nav_order: 4
  section: advanced
  icon: rocket
  requires_auth: true
  difficulty: advanced
  prerequisites: [basic-concepts, authentication]
---
```

### Visibility Control

Control section visibility using `published_at` of `null`:

```yaml
---
title: Internal Documentation
type: doc
meta:
  nav_order: 5
published_at: null
---
```

### Content Relationships

Create relationships between documents:

```yaml
---
title: Advanced Security
type: doc
meta:
  nav_order: 3
  related_docs: 
    - /docs/getting-started/security-basics
    - /docs/api/authentication
  prerequisites:
    - title: Basic Security
      path: /docs/getting-started/security-basics
    - title: Authentication
      path: /docs/api/authentication
---
```

## API Access

Access your hierarchy programmatically:

```http
GET /hierarchy/doc?root=docs&depth=2
```

Response:
```json
{
  "data": [
    {
      "title": "Documentation",
      "slug": "docs",
      "children": [
        {
          "title": "Getting Started",
          "slug": "docs/getting-started",
          "children": []
        },
        {
          "title": "Guides",
          "slug": "docs/guides",
          "children": []
        }
      ]
    }
  ]
}
```

## Best Practices Summary

1. **Directory Structure**
    - Use meaningful directory names
    - Keep URLs clean and logical
    - Group related content together

2. **Index Files**
    - Create index files for all major sections
    - Use them to provide overview content
    - Include relevant metadata

3. **Navigation Ordering**
    - Use consistent `nav_order` values
    - Consider using ranges (1-10, 10-20, etc.) for flexibility
    - Document your ordering scheme

4. **Metadata**
    - Be consistent with metadata fields
    - Use metadata for advanced features
    - Document custom metadata fields

5. **Content Organization**
    - Keep similar content together
    - Use consistent naming conventions
    - Consider future growth when planning structure

Following these guidelines will help create a well-organized, maintainable documentation structure that's easy for both users and contributors to navigate.
