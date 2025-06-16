# Sales API Documentation

Endpoint: `POST /api/sales`

## Request Body

| Field             | Type     | Required | Description                                  |
|-------------------|----------|----------|----------------------------------------------|
| customer_id       | integer  | Yes      | Customer ID (must exist in customers table)  |
| items             | array    | Yes      | List of items (see below)                    |
| items[].product_id| integer  | Yes      | Product ID (must exist in products table)    |
| items[].qty       | integer  | Yes      | Quantity of product                          |
| items[].price     | number   | Yes      | Price per unit                               |
| paid              | number   | Yes      | Amount paid                                  |
| notes             | string   | No       | Additional notes                             |
| payment_method    | string   | No       | Payment method (e.g., cash, transfer)        |
| discount          | number   | No       | Discount amount                              |
| tax               | number   | No       | Tax amount                                   |
| reference         | string   | No       | Reference code                               |
| cashier_id        | integer  | No       | Cashier user ID                              |

### Example JSON

```json
{
  "customer_id": 1,
  "items": [
    {"product_id": 2, "qty": 3, "price": 10000},
    {"product_id": 5, "qty": 1, "price": 5000}
  ],
  "paid": 25000,
  "notes": "Catatan tambahan",
  "payment_method": "cash",
  "discount": 2000,
  "tax": 1000,
  "reference": "INV-20240611-001",
  "cashier_id": 4
}
```

## Response

- **201 Created**
- Returns the created sales data with items.

```json
{
  "sales": {
    "id": 10,
    "customer_id": 1,
    "total": 25000,
    "paid": 25000,
    "status": "completed",
    "notes": "Catatan tambahan",
    "unique_code": "PJ-20240611-abc123",
    "payment_method": "cash",
    "discount": 2000,
    "tax": 1000,
    "reference": "INV-20240611-001",
    "cashier_id": 4,
    "received": true,
    "total_received": 25000,
    "items": [
      {"product_id": 2, "qty": 3, "price": 10000, "subtotal": 30000},
      {"product_id": 5, "qty": 1, "price": 5000, "subtotal": 5000}
    ]
  }
}
```

## Notes
- If `paid` is less than `total`, the sale will be marked as not fully received (`received: false`).
- All monetary values are in integer (no decimal) for this API.
- Make sure to send all required fields as specified above.

## Penjelasan Field `reference`

Field `reference` digunakan untuk menyimpan kode referensi eksternal atau internal yang berkaitan dengan transaksi penjualan. Contohnya bisa berupa nomor invoice dari sistem lain, kode pembayaran, atau referensi lain yang memudahkan integrasi dan pelacakan antar sistem.

### Contoh Penggunaan

Saat melakukan request pembuatan penjualan, Anda dapat mengisi field `reference` seperti berikut:

```json
default
{
  "customer_id": 1,
  "unique_code": "INV-20240611-001",
  "total": 150000,
  "paid": 150000,
  "bayar": 200000,
  "kembali": 50000,
  "status": "paid",
  "notes": "Pembelian tunai",
  "payment_method": "cash",
  "discount": 5000,
  "tax": 10000,
  "reference": "TRX-EXTERNAL-12345",
  "cashier_id": 2,
  "received": true,
  "total_received": 150000,
  "items": [
    {
      "product_id": 10,
      "quantity": 2,
      "price": 50000
    },
    {
      "product_id": 12,
      "quantity": 1,
      "price": 50000
    }
  ]
}
```

Field ini akan disimpan di database dan dapat digunakan untuk pelacakan atau integrasi dengan sistem eksternal.
