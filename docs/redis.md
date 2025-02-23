# Redis Implementation

## Overview
Redis is used for caching in this project to improve performance, especially with Swoole.

## Configuration
- Host: redis
- Port: 6379
- No password authentication
- Persistence: enabled with volume mount

## Usage Examples

### Cache Remember
```php
$data = Cache::remember('key', 3600, function () {
    return expensive_operation();
});