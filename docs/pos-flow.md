# Alur/Flow Aplikasi POS

## 1. Master Data
- **Supplier**: Manajemen data supplier, termasuk informasi hutang awal dan catatan hutang.
- **Customer**: Manajemen data customer, termasuk informasi piutang awal dan catatan piutang.
- **Produk**: Manajemen data produk/barang yang dijual dan dibeli.

## 2. Transaksi Inti
- **Order Pembelian (Purchase Order)**: Admin membuat daftar barang yang akan dipesan ke supplier sebelum pembelian aktual. Saat barang datang, admin mencocokkan barang yang diterima dengan order yang sudah dibuat untuk memastikan kesesuaian dan efisiensi proses pembelian.
  
  **Status pada Purchase Order (PO):**
  - **draft**: PO baru dibuat, belum dikonfirmasi untuk dipesan ke supplier.
  - **ordered**: PO sudah dikonfirmasi dan dikirim ke supplier, menunggu kedatangan barang.
  - **received**: Seluruh barang pada PO sudah diterima.
  - **cancelled**: PO dibatalkan sebelum seluruh barang diterima.
  
  **Status Item pada Purchase Order:**
  - **added**: Item baru ditambahkan ke dalam daftar PO, namun belum dikonfirmasi untuk dipesan.
  - **ordered**: Item sudah dikonfirmasi untuk dipesan ke supplier dan menunggu kedatangan barang.
  - **active**: Item sudah diterima sebagian/seluruhnya, siap diproses ke tahap pembelian aktual.
  - Perubahan status item ini menentukan apakah item bisa diproses lebih lanjut dalam transaksi pembelian. Hanya item dengan status "ordered" atau "active" yang dapat diproses pada tahap pembelian.
  
- **Pembelian Aktual (Purchase)**: Pencatatan pembelian barang dari supplier berdasarkan Purchase Order (PO) yang sudah dibuat sebelumnya. Hanya item dari purchaseOrderItems dengan status sesuai (misal: 'ordered' atau 'active') yang dapat diproses. Proses ini mencatat barang yang benar-benar diterima dari supplier berdasarkan PO, sehingga stok dan hutang terupdate secara akurat. Transaksi pembelian memiliki relasi ke PO dan item-itemnya, serta validasi agar hanya item yang sudah diorder yang bisa diproses pembeliannya. Status item akan berubah menjadi "active" setelah barang diterima, dan tidak dapat diproses ulang jika sudah selesai.

- **Penjualan**: Pencatatan penjualan barang ke customer, otomatis menambah piutang jika pembayaran belum lunas.
- **Pembayaran Hutang**: Pencatatan pembayaran hutang ke supplier.
- **Pembayaran Piutang**: Pencatatan penerimaan pembayaran dari customer.

## 3. Pengelolaan Hutang & Piutang Otomatis
- **Saldo Hutang Supplier**: Update otomatis setiap ada transaksi pembelian/pembayaran.
- **Saldo Piutang Customer**: Update otomatis setiap ada transaksi penjualan/pembayaran.

## 4. Laporan
- **Laporan Stok Barang**: Mutasi stok, stok minimum, stok opname, dan riwayat pergerakan barang.
- **Laporan Penjualan & Pembelian**: Rekap transaksi harian/bulanan.
- **Rekap Harian Kasir**: Ringkasan penjualan, penerimaan kas, pengeluaran kas harian per kasir.
- **Laporan Hutang**: Daftar hutang supplier dan riwayat pembayaran.
- **Laporan Piutang**: Daftar piutang customer dan riwayat pembayaran.
- **Laporan Keuangan Sederhana**: Laporan arus kas, laba rugi, dan posisi keuangan toko.

## 5. Pengelolaan User, Hak Akses & Audit
- **User Management**: Pengelolaan user (kasir, admin, owner) dan pengaturan hak akses fitur.
- **Audit Log**: Riwayat aktivitas user untuk keamanan dan monitoring.
- **Role & Permission Management**: Manajemen peran dan izin pengguna untuk mengontrol akses fitur.
- **Dashboard Ringkasan**: Tampilan ringkas performa toko (penjualan, stok kritis, piutang, hutang, kas)
## 6. Pengaturan Toko
- **Pengaturan Umum**: Informasi toko, alamat, kontak, dan logo.
- **Pengaturan Akun**: Pengaturan akun toko seperti username, password, dan level akses.
- **Pengaturan Keuangan**: Pengaturan akun bank, metode pembayaran, dan laporan keuangan.

---

# Dokumentasi Fitur Selesai

## Supplier
### Endpoint
- `POST /api/suppliers` — Menambah supplier baru beserta hutang awal
- `GET /api/suppliers/{id}` — Detail supplier beserta saldo hutang

#### Contoh Request Tambah Supplier
```json
{
  "name": "PT Sumber Makmur",
  "address": "Jl. Raya No.1",
  "phone": "08123456789",
  "email": "supplier@email.com",
  "description": "Supplier utama",
  "initial_amount": 1000000,
  "debt_notes": "Hutang awal tahun"
}
```

#### Contoh Response
```json
{
  "id": 1,
  "name": "PT Sumber Makmur",
  "address": "Jl. Raya No.1",
  "phone": "08123456789",
  "email": "supplier@email.com",
  "description": "Supplier utama",
  "debt": {
    "initial_amount": 1000000,
    "current_amount": 1000000,
    "notes": "Hutang awal tahun"
  }
}
```

## Customer
### Endpoint
- `POST /api/customers` — Menambah customer baru beserta piutang awal
- `GET /api/customers/{id}` — Detail customer beserta saldo piutang

#### Contoh Request Tambah Customer
```json
{
  "name": "CV Maju Jaya",
  "address": "Jl. Melati No.2",
  "phone": "082233445566",
  "email": "customer@email.com",
  "description": "Customer loyal",
  "initial_amount": 500000,
  "receivable_notes": "Piutang awal tahun"
}
```

#### Contoh Response
```json
{
  "id": 1,
  "name": "CV Maju Jaya",
  "address": "Jl. Melati No.2",
  "phone": "082233445566",
  "email": "customer@email.com",
  "description": "Customer loyal",
  "receivable": {
    "initial_amount": 500000,
    "current_amount": 500000,
    "notes": "Piutang awal tahun"
  }
}
```

## Purchase Order
### Endpoint
- `GET /api/purchase-orders` — List semua order pembelian
- `POST /api/purchase-orders` — Membuat order pembelian baru
- `GET /api/purchase-orders/{id}` — Detail order pembelian
- `PUT /api/purchase-orders/items/{id}/status` — Update status item order pembelian
- `DELETE /api/purchase-orders/{id}` — Hapus order pembelian

#### Contoh Request Tambah Purchase Order
```json
{
  "supplier_id": 1,
  "order_number": "PO-2024-0001",
  "order_date": "2024-03-03",
  "notes": "Order barang stok awal",
  "status": "draft",
  "items": [
    {
      "product_id": 1,
      "quantity": 10,
      "unit_price": 50000,
      "total_price": 500000,
      "item_status": "active"
    }
  ]
}
```

#### Contoh Request Update Status Item Purchase Order
```json
{
  "status": "cancelled"
}
```

#### Contoh Response
```json
{
  "id": 1,
  "purchase_order_id": 1,
  "product_id": 1,
  "quantity": 10,
  "unit_price": 50000,
  "total_price": 500000,
  "item_status": "cancelled"
}
```

#### Contoh Response
```json
{
  "id": 1,
  "supplier_id": 1,
  "order_number": "PO-2024-0001",
  "order_date": "2024-03-03",
  "notes": "Order barang stok awal",
  "status": "draft",
  "created_at": "2024-03-03T10:00:00.000000Z",
  "updated_at": "2024-03-03T10:00:00.000000Z",
  "supplier": {
    "id": 1,
    "name": "PT Sumber Makmur"
  },
  "items": [
    {
      "product_id": 1,
      "quantity": 10,
      "unit_price": 50000,
      "total_price": 500000,
      "item_status": "active"
    }
  ]
}
```

---

Dokumentasi akan diperbarui setiap ada fitur baru yang selesai.

## Purchase
### Endpoint
- `GET /api/purchases` — List semua transaksi pembelian
- `POST /api/purchases` — Membuat transaksi pembelian baru
- `GET /api/purchases/{id}` — Detail transaksi pembelian
- `DELETE /api/purchases/{id}` — Hapus transaksi pembelian

#### Parameter Pencarian (GET /api/purchases)
| Nama      | Tipe   | Keterangan                                 |
|-----------|--------|--------------------------------------------|
| supplier  | string | (opsional) Nama supplier, pencarian fuzzy  |
| status    | string | (opsional) Status purchase order           |

Contoh Request:
```
GET /api/purchases?supplier=makmur
```

Contoh Response:
```json
{
  "current_page": 1,
  "data": [
    {
      "id": 1,
      "purchase_order_id": 1,
      "supplier_id": 1,
      "purchase_date": "2024-06-08",
      "total": 300000,
      "paid": 0,
      "debt": 300000,
      "note": null,
      "created_at": "2024-06-08T10:00:00.000000Z",
      "updated_at": "2024-06-08T10:00:00.000000Z",
      "supplier_name": "PT Sumber Makmur",
      "status_order": "ordered",
      "supplier": {
        "id": 1,
        "name": "PT Sumber Makmur"
      },
      "purchase_order": {
        "id": 1,
        "order_number": "PO-2024-0001"
      },
      "items": [
        {
          "id": 10,
          "purchase_id": 1,
          "product_id": 5,
          "qty": 20,
          "price": 15000,
          "subtotal": 300000,
          "product": {
            "id": 5,
            "name": "Beras Premium"
          }
        }
      ]
    }
  ],
  "next_page_url": null,
  "prev_page_url": null,
  "per_page": 15,
  "total": 1
}
```

#### Contoh Request Tambah Pembelian
POST /api/purchases
{
  "purchase_order_id": 1,
  "purchase_date": "2024-06-08",
  "items": [
    {
      "purchase_order_item_id": 10,
      "product_id": 5,
      "qty": 20,
      "price": 15000
    }
  ],
  "note": "Barang diterima sesuai PO."
}
