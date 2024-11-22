# Hierarchy Endpoint

## Overview

The Hierarchy endpoint provides access to hierarchical content structures in Flatlayer CMS. It enables navigation through nested content, retrieval of section structures, and finding specific nodes within content hierarchies.

## Base Endpoints

```http
GET /hierarchy/{type}              # Get full hierarchy or section
GET /hierarchy/{type}/{path}       # Find specific node and its ancestry
```

## Hierarchy Listing

### Basic Usage

Retrieve the complete hierarchy for a content type:

```http
GET /hierarchy/doc
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
        "root": null,
        "depth": null,
        "total_nodes": 3
    }
}
```

### Query Parameters

#### Root Path

Limit hierarchy to a specific section:

```http
GET /hierarchy/doc?root=docs/getting-started
```

This returns only the subtree starting at the specified path.

#### Depth Control

Limit the depth of the returned hierarchy:

```http
GET /hierarchy/doc?depth=2
```

The `depth` parameter accepts values 1-10 and controls how many levels deep the hierarchy goes.

#### Field Selection

Control which fields are included in the response:

```http
GET /hierarchy/doc?fields=["title","slug","meta.nav_order"]
```

For child nodes, use `navigation_fields`:

```http
GET /hierarchy/doc?fields=["title","slug","meta"]&navigation_fields=["title","slug"]
```

#### Sorting

Sort nodes at each level:

```http
GET /hierarchy/doc?sort={"meta.nav_order":"asc","title":"asc"}
```

## Node Finding

### Basic Node Lookup

Find a specific node and its ancestry:

```http
GET /hierarchy/doc/docs/getting-started/installation
```

Response:
```json
{
    "data": {
        "id": 3,
        "title": "Installation",
        "slug": "docs/getting-started/installation",
        "meta": {
            "difficulty": "beginner"
        }
    },
    "meta": {
        "ancestry": [
            {
                "id": 1,
                "title": "Documentation",
                "slug": "docs",
                "children": []
            },
            {
                "id": 2,
                "title": "Getting Started",
                "slug": "docs/getting-started",
                "children": []
            }
        ],
        "depth": 2
    }
}
```

### Field Selection for Node Finding

Control fields in both the node and its ancestry:

```http
GET /hierarchy/doc/docs/getting-started/installation?fields=["title","meta"]&navigation_fields=["title","slug"]
```

## Error Handling

### Invalid Type
```http
Status: 404 Not Found

{
    "error": "No repository configured for type: invalid"
}
```

### Node Not Found
```http
Status: 404 Not Found

{
    "error": "Node not found"
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
GET /hierarchy/doc?fields=["title","slug","meta.nav_order"]&sort={"meta.nav_order":"asc"}
```

### Section Overview

Get content structure for a specific section:

```http
GET /hierarchy/doc?root=docs/tutorials&depth=2&fields=["title","excerpt"]
```

### Breadcrumb Generation

Find a node with its ancestry:

```http
GET /hierarchy/doc/docs/getting-started/installation?fields=["title","slug"]
```

### Dynamic Navigation

Get siblings and parent sections:

```http
GET /hierarchy/doc/docs/getting-started/installation?navigation_fields=["title","slug","meta.nav_order"]
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
    - Use root parameter to limit scope
    - Consider pagination for large sections

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

4. **Error Handling**
    - Handle missing nodes gracefully
    - Provide fallback navigation
    - Validate paths before requests

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
GET /hierarchy/doc?fields=["title","slug","content_structure"]
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
GET /hierarchy/doc?fields=["title","slug","flat_content_structure"]
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
GET /hierarchy/doc?fields=["title","slug","content_structure"]&root=docs/advanced&depth=2
```

This allows you to build navigation systems that show both:
- Document hierarchy (sections and pages)
- In-page sections (headings and subheadings)

Example navigation structure:

```javascript
// React component example
const Navigation = ({ data }) => {
  return (
    <nav>
      {data.map(section => (
        <div key={section.slug}>
          {/* Document-level navigation */}
          <Link href={section.slug}>{section.title}</Link>
          
          {/* In-page section navigation */}
          {section.content_structure?.map(heading => (
            <div key={heading.anchor}>
              <Link href={`${section.slug}#${heading.anchor}`}>
                {heading.title}
              </Link>
              
              {/* Second-level headings */}
              <div className="ml-4">
                {heading.children?.map(subheading => (
                  <Link
                    key={subheading.anchor}
                    href={`${section.slug}#${subheading.anchor}`}
                  >
                    {subheading.title}
                  </Link>
                ))}
              </div>
            </div>
          ))}
          
          {/* Child documents */}
          <div className="ml-4">
            {section.children?.map(child => (
              <Link key={child.slug} href={child.slug}>
                {child.title}
              </Link>
            ))}
          </div>
        </div>
      ))}
    </nav>
  );
};
```

### Use Cases

#### Full Documentation Navigation
```http
GET /hierarchy/doc?fields=["title","slug","content_structure","meta.nav_order"]&sort={"meta.nav_order":"asc"}
```

This provides everything needed for a complete documentation navigation system:
- Section hierarchy
- Page ordering
- In-page headings
- Deep linking anchors

#### Section Overview Pages
```http
GET /hierarchy/doc?root=docs/guides&fields=["title","slug","excerpt","flat_content_structure"]
```

Perfect for creating overview pages that show:
- All pages in a section
- Quick links to subsections
- Preview of each page's structure

### Performance Tips

1. **Selective Loading**
    - Only request content structure when needed
    - Use `flat_content_structure` for simpler navigation
    - Cache navigation data when possible

2. **Progressive Enhancement**
    - Load document hierarchy first
    - Load content structure on demand
    - Use lazy loading for deep navigation

3. **Field Selection**
    - Request minimal fields for initial load
    - Load content structure for active sections
    - Use flat structure for search interfaces

### Best Practices

1. **Navigation Design**
    - Keep navigation hierarchy shallow
    - Use clear heading structure
    - Maintain consistent heading levels
    - Consider mobile navigation

2. **Content Organization**
    - Use meaningful heading hierarchy
    - Keep related content together
    - Use descriptive anchor IDs
    - Maintain logical document flow

3. **Implementation**
    - Cache navigation data
    - Implement progressive loading
    - Handle missing content gracefully
    - Provide fallback navigation

## Examples

### Building a Navigation Menu

```http
GET /hierarchy/doc?fields=["title","slug","meta.nav_order"]&sort={"meta.nav_order":"asc"}&depth=2
```

This provides a two-level deep menu structure:

```json
{
    "data": [
        {
            "title": "Documentation",
            "slug": "docs",
            "meta": {
                "nav_order": 1
            },
            "children": [
                {
                    "title": "Getting Started",
                    "slug": "docs/getting-started",
                    "meta": {
                        "nav_order": 1
                    }
                },
                {
                    "title": "Advanced Topics",
                    "slug": "docs/advanced",
                    "meta": {
                        "nav_order": 2
                    }
                }
            ]
        }
    ]
}
```

### Generating Breadcrumbs

```http
GET /hierarchy/doc/docs/getting-started/installation?fields=["title","slug"]
```

Use the ancestry data to build breadcrumbs:

```json
{
    "data": {
        "title": "Installation",
        "slug": "docs/getting-started/installation"
    },
    "meta": {
        "ancestry": [
            {
                "title": "Documentation",
                "slug": "docs"
            },
            {
                "title": "Getting Started",
                "slug": "docs/getting-started"
            }
        ]
    }
}
```

### Section Landing Page

Get a complete view of a section with metadata:

```http
GET /hierarchy/doc?root=docs/tutorials&fields=["title","excerpt","meta.difficulty","meta.duration"]
```

This endpoint provides powerful tools for working with hierarchical content structures while maintaining flexibility through its parameter system and efficient data retrieval through careful field selection.

### Example: Full Documentation Browser

```http
GET /hierarchy/doc?fields=[
    "title",
    "slug",
    "excerpt", 
    "content_structure",
    "meta.nav_order"
]&depth=3
```

This provides all the data needed for a full documentation browser:

```javascript
// Type definitions
/**
 * @typedef {Object} HierarchyNode
 * @property {string} title
 * @property {string} slug 
 * @property {Array<HierarchyNode>} children
 */

/**
 * @typedef {Object} NavigationData
 * @property {Array<HierarchyNode>} data
 * @property {Object} meta
 */

// Utility function
export function findNode(nodes, path) {
  for (const node of nodes) {
    if (node.slug === path) return node;
    if (node.children) {
      const found = findNode(node.children, path);
      if (found) return found;
    }
  }
}
```

Component code:
```sveltehtml
<script>
  import { onMount } from "svelte";
  import { findNode } from "./doc-utils";
  
  /** @type {import('@flatlayer/sdk').FlatlayerClient} */
  export let client;
  
  let hierarchy;
  let currentNode;

  onMount(async () => {
    const response = await client.get('/hierarchy/doc');
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
