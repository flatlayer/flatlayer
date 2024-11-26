# Hierarchy Endpoint

## Overview

The Hierarchy endpoint provides access to hierarchical content structures in Flatlayer CMS. It enables navigation through nested content and retrieval of section structures.

## Base Endpoints

```http
GET /entries/{type}/hierarchy              # Get full hierarchy
```

## Hierarchy Listing

### Basic Usage

Retrieve the complete hierarchy for a content type:

```http
GET /entries/doc/hierarchy
```

Response:
```json
{
    "data": [
        {
            "id": 1,
            "title": "Documentation",
            "slug": "docs",
            "meta": {
                "section": "root",
                "nav_order": 1
            },
            "children": [
                {
                    "id": 2,
                    "title": "Getting Started",
                    "slug": "docs/getting-started",
                    "meta": {
                        "section": "guide",
                        "nav_order": 2
                    },
                    "children": [
                        {
                            "id": 3,
                            "title": "Installation",
                            "slug": "docs/getting-started/installation",
                            "meta": {
                                "difficulty": "beginner"
                            }
                        }
                    ]
                }
            ]
        }
    ],
    "meta": {
        "type": "doc",
        "depth": null,
        "total_nodes": 3
    }
}
```

### Query Parameters

#### Depth Control

Limit the depth of the returned hierarchy:

```http
GET /entries/doc/hierarchy?depth=2
```

The `depth` parameter accepts values 1-10 and controls how many levels deep the hierarchy goes.

#### Field Selection

Control which fields are included in the response:

```http
GET /entries/doc/hierarchy?fields=["title","slug","meta.nav_order"]
```

For child nodes, use `navigation_fields`:

```http
GET /entries/doc/hierarchy?fields=["title","slug","meta"]&navigation_fields=["title","slug"]
```

#### Sorting

Sort nodes at each level:

```http
GET /entries/doc/hierarchy?sort={"meta.nav_order":"asc","title":"asc"}
```

## Error Handling

### Invalid Type
```http
Status: 404 Not Found

{
    "error": "No repository configured for type: invalid"
}
```

### Invalid Parameters
```http
Status: 400 Bad Request

{
    "error": "Depth must be between 1 and 10"
}
```

## Common Use Cases

### Navigation Menu Structure

Retrieve a complete navigation structure:

```http
GET /entries/doc/hierarchy?fields=["title","slug","meta.nav_order"]&sort={"meta.nav_order":"asc"}
```

### Documentation Navigation

Get content structure with document organization:

```http
GET /entries/doc/hierarchy?fields=["title","excerpt"]
```

## Performance Considerations

1. **Depth Control**
    - Use appropriate depth limits
    - Avoid retrieving unnecessary levels
    - Consider caching for static hierarchies

2. **Field Selection**
    - Request only needed fields
    - Use different fields for root vs. navigation
    - Minimize meta field requests

3. **Response Size**
    - Large hierarchies can be slow to generate
    - Consider pagination for large sections
    - Cache commonly requested hierarchies

## Best Practices

1. **Structure Organization**
    - Use consistent directory structures
    - Maintain logical content grouping
    - Use index files for sections
    - Keep hierarchies reasonably flat

2. **Navigation Order**
    - Use `nav_order` in meta for explicit ordering
    - Provide fallback ordering (e.g., by title)
    - Maintain consistent ordering schemes

3. **Field Usage**
    - Define standard field sets for different views
    - Cache commonly used hierarchies
    - Use appropriate fields for different contexts

4. **Content Organization**
    - Use meaningful heading hierarchy
    - Keep related content together
    - Use descriptive anchor IDs
    - Maintain logical document flow

## Navigation Ordering

The `nav_order` meta field controls how items are ordered in the hierarchy.

### Basic Usage

Specify in front matter:
```yaml
---
title: Getting Started
type: doc
meta:
  nav_order: 1
---
```

### How it Works

- Lower numbers appear first (ascending order)
- Missing `nav_order` falls back to title-based sorting
- Each hierarchy level sorts independently
- Parent ordering doesn't affect child ordering
- Index files inherit their parent directory's order

### Example Structure

```
docs/
├── getting-started/      # nav_order: 1
│   ├── installation.md   # nav_order: 1
│   └── configuration.md  # nav_order: 2
├── guides/               # nav_order: 2
│   └── basic-usage.md    # nav_order: 1
└── reference/            # no nav_order (sorts by title)
    ├── api.md
    └── cli.md
```

## Document Structure Integration

### Overview

The hierarchy endpoint can be combined with content structure attributes to create comprehensive navigation systems. While the hierarchy provides document-level organization, the content structure attributes (`content_structure` and `flat_content_structure`) expose heading-level navigation within each document.

### Content Structure Fields

Include content structure in your hierarchy requests:

```http
GET /entries/doc/hierarchy?fields=["title","slug","content_structure"]
```

Response:
```json
{
    "data": [
        {
            "title": "Advanced Topics",
            "slug": "docs/advanced",
            "content_structure": [
                {
                    "title": "Advanced Configuration",
                    "level": 1,
                    "anchor": "advanced-configuration",
                    "position": {
                        "line": 1,
                        "offset": 0
                    },
                    "children": [
                        {
                            "title": "Custom Providers",
                            "level": 2,
                            "anchor": "custom-providers",
                            "position": {
                                "line": 15,
                                "offset": 280
                            }
                        }
                    ]
                }
            ]
        }
    ]
}
```

### Flattened Structure

For simpler navigation, use the flat content structure:

```http
GET /entries/doc/hierarchy?fields=["title","slug","flat_content_structure"]
```

Response:
```json
{
    "data": [
        {
            "title": "Advanced Topics",
            "slug": "docs/advanced",
            "flat_content_structure": [
                {
                    "title": "Advanced Configuration",
                    "level": 1,
                    "anchor": "advanced-configuration",
                    "position": {
                        "line": 1,
                        "offset": 0
                    }
                },
                {
                    "title": "Custom Providers",
                    "level": 2,
                    "anchor": "custom-providers",
                    "parent_anchor": "advanced-configuration",
                    "position": {
                        "line": 15,
                        "offset": 280
                    }
                }
            ]
        }
    ]
}
```

### Building Multi-Level Navigation

Combine hierarchy and content structure to build comprehensive navigation:

```http
GET /entries/doc/hierarchy?fields=["title","slug","content_structure"]
```

This allows you to build navigation systems that show both:
- Document hierarchy (sections and pages)
- In-page sections (headings and subheadings)

Example navigation structure:

```sveltehtml
<script>
  import { onMount } from "svelte";
  import { findNode } from "./doc-utils";
  
  /** @type {import('@flatlayer/sdk').FlatlayerClient} */
  export let client;
  
  let hierarchy;
  let currentNode;

  onMount(async () => {
    const response = await client.get('/entries/doc/hierarchy');
    hierarchy = response;
  });
</script>

<div class="flex">
  <nav>
    {#if hierarchy}
      <TreeNavigation data={hierarchy.data} />
    {/if}
  </nav>
  
  <main>
    {#if currentNode}
      <h1>{currentNode.title}</h1>
      <slot />
    {/if}
  </main>
</div>
```

Usage:
```sveltehtml
<script>
  import DocBrowser from './DocBrowser.svelte';
  import { FlatlayerClient } from '@flatlayer/sdk';
  
  const client = new FlatlayerClient('https://api.example.com');
</script>

<DocBrowser {client} />
```

This provides a basic framework for building documentation navigation using the hierarchy endpoint. The component handles fetching and displaying the hierarchical structure while maintaining type safety and providing utility functions for node traversal.
