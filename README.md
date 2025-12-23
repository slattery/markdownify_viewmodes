# Markdownify View Modes

A Drupal module that adds granular control over view modes for entity rendering through the Markdownify converter.

## Overview

This module extends the [Markdownify](https://drupal.org/project/markdownify) contrib module to allow configuration of which view mode is used when rendering entities (nodes, taxonomy terms, etc.) in Markdown format.

### Features

- **Global entity-type defaults**: Set a default view mode for each entity type (e.g., 'teaser' for all nodes)
- **Per-bundle overrides**: Override the global default for specific content types or vocabularies
- **Hierarchical fallback**: Intelligent system to determine the correct view mode to use
- **View mode validation**: Ensures only valid, configured view modes are applied

## Requirements

- Drupal 10+ or 11+
- [Markdownify](https://drupal.org/project/markdownify) contrib module

## Installation

1. Install the module:
   ```bash
   drush en markdownify_viewmodes
   ```

2. Clear cache:
   ```bash
   drush cr
   ```

## Configuration

### Global Entity-Type Defaults

1. Navigate to **Administration > Configuration > Content > Markdownify**
2. Expand each entity type section
3. Select a "Default view mode" for that entity type
4. Save

### Per-Bundle Overrides

1. Go to the bundle edit form (e.g., **Structure > Content Types > Manage > Article**)
2. Open the **Markdownify** tab
3. Check "Override global view mode for Markdownify"
4. Select a view mode
5. Save

## View Mode Resolution Hierarchy

When rendering an entity through Markdownify, the module checks for the view mode in this order:

1. **Bundle override** (if enabled)
2. **Entity-type default** (from markdownify settings)
3. **'full'** (Drupal's default fallback)

Example: For an article node
- First checks if there's a bundle override for article nodes
- If not, checks if there's an entity-type default for all nodes
- If not, uses 'full' view mode

## API Usage

### ViewModeResolver Service

Get the resolved view mode for an entity:

```php
$resolver = \Drupal::service('markdownify_viewmodes.view_mode_resolver');
$view_mode = $resolver->getViewMode($entity);
```

Internal methods (less commonly needed):
```php
$resolver->getBundleViewMode($entity_type_id, $bundle);
$resolver->getEntityTypeDefaultViewMode($entity_type_id);
$resolver->validateViewMode($entity_type_id, $bundle, $view_mode);
```

### Hook: hook_markdownify_entity_build_alter()

This module implements this hook to rebuild entities with the configured view mode during Markdownify rendering. Cache dependencies are properly managed for invalidation on config changes.

## Configuration Schema

### Global Settings

Stored in `markdownify.settings` as third-party settings:

```yaml
third_party_settings:
  markdownify_viewmodes:
    entity_types:
      node: teaser
      taxonomy_term: full
```

### Bundle Settings

Stored on bundle entities (node types, vocabularies, etc.) as third-party settings:

```yaml
third_party_settings:
  markdownify_viewmodes:
    enable_override: true
    view_mode: default
```

## Performance

- View mode resolution is cached through entity view builder caching
- Config changes properly invalidate render cache via cache dependencies
- No database queries are added by this module
- Service instantiation is lazy (created only when accessed)

## License

Follows Drupal's default licensing.
