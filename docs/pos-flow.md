# Alur/Flow Aplikasi POS

## 1. Master Data
- **Supplier**: Manajemen data supplier, termasuk informasi hutang awal dan catatan hutang.
- **Customer**: Manajemen data customer, termasuk informasi piutang awal dan catatan piutang.
- **Produk**: Manajemen data produk/barang yang dijual dan dibeli.

## 2. Transaksi Inti
- **Order Pembelian (Purchase Order)**: Admin membuat daftar barang yang akan dipesan ke supplier sebelum pembelian aktual. Saat barang datang, admin mencocokkan barang yang diterima dengan order yang sudah dibuat untuk memastikan kesesuaian dan efisiensi proses pembelian.
- **Pembelian**: Pencatatan pembelian barang dari supplier, otomatis menambah hutang jika pembayaran belum lunas.
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
- **Dashboard Ringkasan**: Tampilan ringkas performa toko (penjualan, stok kritis, piutang, hutang, kas
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
- `PUT /api/purchase-orders/{id}` — Update order pembelian
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
      "total_price": 500000
    }
  ]
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
      "total_price": 500000
    }
  ]
}
```

---

Dokumentasi akan diperbarui setiap ada fitur baru yang selesai.
