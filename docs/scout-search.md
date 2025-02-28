# Product Search API Documentation

## Overview
The product search functionality is implemented using Laravel Scout with Meilisearch as the search engine. It provides powerful search and filtering capabilities for the product catalog.

7️⃣ Kesimpulan
✅ Laravel Scout + Meilisearch → Pencarian cepat dan fleksibel.
✅ Redis Cache → Mengurangi query berulang ke Meilisearch.
✅ Queue dengan Redis → Data selalu terindeks tanpa memperlambat user.
✅ Debounce di Vue → Menghindari terlalu banyak request API.

Ini adalah kombinasi terbaik untuk pencarian cepat di Laravel! 🚀

## Endpoint
```

## Search Features

### Available Features
- Full-text search on product name and barcode
- Category and unit filtering
- Stock status filtering
- Sorting by multiple fields
- Pagination
- Text highlighting in search results

### Example Requests with Features

```bash
# Full-text search
GET /api/products/search?q=milk chocolate

# Category and unit filtering
GET /api/products/search?category_id=1&satuan_id=2

# Stock status filtering
GET /api/products/search?status=low-stock

# Sorting
GET /api/products/search?sort_by=name&sort_dir=asc

# Pagination
GET /api/products/search?per_page=20&page=2

# Combined features
GET /api/products/search?q=milk&category_id=1&status=low-stock&sort_by=stock&sort_dir=desc&per_page=20
```

## Production Setup

### 1. Initial Setup
```bash
# Install dependencies
composer require laravel/scout meilisearch/meilisearch-php http-interop/http-factory-guzzle

# Publish Scout configuration
php artisan vendor:publish --provider="Laravel\Scout\ScoutServiceProvider"

