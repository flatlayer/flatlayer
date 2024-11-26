#!/usr/bin/env python3

import glob
from pathlib import Path
import argparse
import logging
from typing import Dict, List

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

# Complete URL mappings to catch all cases
URL_MAPPINGS: Dict[str, str] = {
    # Fix incorrect entries paths in hierarchy
    '/entries/{type}/entries/': '/entries/{type}/hierarchy/',
    '/entries/doc/entries/': '/entries/doc/hierarchy/',
    '/entries/post/entries/': '/entries/post/hierarchy/',
    '/entries/{type}/entries/{path}': '/entries/{type}/hierarchy/{path}',
    '/entries/doc/entries/getting-started': '/entries/doc/hierarchy/getting-started',
    '/entries/doc/entries/doc/getting-started': '/entries/doc/hierarchy/getting-started',

    # Fix remaining image URLs
    'GET /image/123.webp': 'GET /images/123.webp',
    'GET /image/123': 'GET /images/123',

    # Base entry endpoints
    '/entry/post': '/entries/post/list',
    '/entry/doc': '/entries/doc/list',
    '/entry/{type}': '/entries/{type}/list',
    '/entry/batch/post': '/entries/post/batch',
    '/entry/batch/doc': '/entries/doc/batch',
    '/entry/batch/{type}': '/entries/{type}/batch',
    '/entry/{type}/{path}': '/entries/{type}/show/{path}',

    # Fix duplicate 'entries' in paths
    '/entries/doc/entries/doc/hierarchy/': '/entries/doc/hierarchy/',
    '/entries/doc/entries/docs/hierarchy/': '/entries/doc/hierarchy/',
    '/entries/doc/entries/doc/hierarchys/': '/entries/doc/hierarchy/',
    '/entries/{type}/entries/{type}/hierarchy/': '/entries/{type}/hierarchy/',
    '/entries/post/entries/post/hierarchy/': '/entries/post/hierarchy/',

    # Fix incorrect hierarchy pluralization
    '/entries/doc/hierarchys/': '/entries/doc/hierarchy/',
    '/entries/{type}/hierarchys/': '/entries/{type}/hierarchy/',

    # Old hierarchy endpoints
    '/hierarchy/doc': '/entries/doc/hierarchy',
    '/hierarchy/post': '/entries/post/hierarchy',
    '/hierarchy/{type}': '/entries/{type}/hierarchy',
    '/hierarchy/doc/': '/entries/doc/hierarchy/',
    '/hierarchy/post/': '/entries/post/hierarchy/',
    '/hierarchy/{type}/': '/entries/{type}/hierarchy/',
    '/hierarchy/doc/docs/getting-started': '/entries/doc/hierarchy/docs/getting-started',
    '/hierarchy/{type}/{path}': '/entries/{type}/hierarchy/{path}',

    # Image endpoints (with and without trailing slashes)
    '/image/123.jpg': '/images/123.jpg',
    '/image/123.jpeg': '/images/123.jpeg',
    '/image/123.png': '/images/123.png',
    '/image/123.webp': '/images/123.webp',
    '/image/123.gif': '/images/123.gif',
    '/image/{id}.jpg': '/images/{id}.jpg',
    '/image/{id}.jpeg': '/images/{id}.jpeg',
    '/image/{id}.png': '/images/{id}.png',
    '/image/{id}.webp': '/images/{id}.webp',
    '/image/{id}.gif': '/images/{id}.gif',
    '/image/123/': '/images/123/',
    '/image/{id}/': '/images/{id}/',
    '/image/123/metadata': '/images/123/metadata',
    '/image/{id}/metadata': '/images/{id}/metadata',

    # Webhook endpoints
    '/webhook/doc': '/webhooks/doc',
    '/webhook/post': '/webhooks/post',
    '/webhook/{type}': '/webhooks/{type}',
    '/webhook/doc/': '/webhooks/doc/',
    '/webhook/post/': '/webhooks/post/',
    '/webhook/{type}/': '/webhooks/{type}/',

    # HTTP method prefixed endpoints
    'GET /entry/': 'GET /entries/',
    'POST /entry/': 'POST /entries/',
    'PUT /entry/': 'PUT /entries/',
    'DELETE /entry/': 'DELETE /entries/',
    'GET /image/': 'GET /images/',
    'POST /image/': 'POST /images/',
    'PUT /image/': 'PUT /images/',
    'DELETE /image/': 'DELETE /images/',
    'GET /webhook/': 'GET /webhooks/',
    'POST /webhook/': 'POST /webhooks/',
    'PUT /webhook/': 'PUT /webhooks/',
    'DELETE /webhook/': 'DELETE /webhooks/',
    'GET /hierarchy/': 'GET /entries/',
    'POST /hierarchy/': 'POST /entries/',

    # Fix any remaining base endpoints
    '/entry/': '/entries/',
    '/image/': '/images/',
    '/webhook/': '/webhooks/',
    '/hierarchy/': '/entries/',

    # OpenAPI path references
    '"path": "/entry/': '"path": "/entries/',
    '"path": "/image/': '"path": "/images/',
    '"path": "/webhook/': '"path": "/webhooks/',
    '"path": "/hierarchy/': '"path": "/entries/',
}

def update_content(content: str, mappings: Dict[str, str]) -> str:
    """Update content using direct string replacements."""
    updated_content = content

    # Sort mappings by length (longest first) to handle more specific URLs before general ones
    sorted_mappings = sorted(mappings.items(), key=lambda x: len(x[0]), reverse=True)

    for old_url, new_url in sorted_mappings:
        # Handle both normal text and code blocks
        updated_content = updated_content.replace(old_url, new_url)

        # Handle backtick-wrapped URLs
        updated_content = updated_content.replace(f'`{old_url}`', f'`{new_url}`')

        # Handle URLs in JSON examples
        updated_content = updated_content.replace(f'"{old_url}"', f'"{new_url}"')

        # Handle URLs in markdown links
        updated_content = updated_content.replace(f']({old_url}', f']({new_url}')

    return updated_content

def process_file(filepath: Path, args: argparse.Namespace) -> None:
    """Process a single file and update its content if needed."""
    try:
        if args.verbose:
            logger.info(f"Processing {filepath}")

        with open(filepath, 'r', encoding='utf-8') as f:
            content = f.read()

        updated_content = update_content(content, URL_MAPPINGS)

        if content != updated_content:
            if args.verbose:
                logger.info(f"Changes found in {filepath}")

            if not args.dry_run:
                with open(filepath, 'w', encoding='utf-8') as f:
                    f.write(updated_content)
                logger.info(f"Updated {filepath}")
            else:
                logger.info(f"Would update {filepath} (dry run)")

                if args.verbose:
                    for old_url, new_url in URL_MAPPINGS.items():
                        if old_url in content:
                            logger.info(f"  Would replace: {old_url} → {new_url}")
    except Exception as e:
        logger.error(f"Error processing {filepath}: {str(e)}")

def main():
    parser = argparse.ArgumentParser(
        description='Update API endpoint URLs in documentation to match new route structure'
    )
    parser.add_argument('--dry-run', action='store_true',
                      help='Show what would be changed without making changes')
    parser.add_argument('--verbose', action='store_true',
                      help='Show detailed information about changes')
    parser.add_argument('--path', type=str, default=".",
                      help='Path to documentation directory (default: current directory)')
    args = parser.parse_args()

    base_path = Path(args.path)
    files: List[Path] = []
    files.extend(base_path.glob("**/*.md"))
    files.extend(base_path.glob("**/*.yaml"))
    files.extend(base_path.glob("**/*.yml"))

    for filepath in files:
        if filepath.is_file():
            process_file(filepath, args)

if __name__ == "__main__":
    main()
