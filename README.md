# Smart Water Monitoring System (IoT + ML)

**Implementasi IoT dan Machine Learning untuk Monitoring dan Prediksi Kondisi Air Tangki**

Sistem ini memantau ketinggian dan kekeruhan air secara real-time menggunakan ESP32, serta memprediksi estimasi waktu air habis menggunakan algoritma *Linear Regression* dan klasifikasi kualitas air dengan *Random Forest*.

### 🎓 Informasi Akademik
* **Mata Kuliah:** Internet of Things
* **Dosen Pengampu:** Dedi Kiswanto, S.Kom., M.Kom.
* **Kampus:** Universitas Negeri Medan (UNIMED)

### 👥 Anggota Kelompok 6
1. **Rizky Wahyudi** (4233250024)
2. **Selfi Audy Priscilia** (4233250001)
3. **Windy Aulia** (4233250021)

---

## 📊 Hasil Penelitian (Key Highlights)

Berdasarkan pengujian sistem dan model Machine Learning, berikut adalah pencapaian utama proyek ini:

- **Akurasi Prediksi (Linear Regression):** Model prediksi waktu habis mencapai skor **R² 0.9928** dengan rata-rata error (**MAE**) hanya **1.06 jam**.
- **Perbandingan Model:** Meskipun *Random Forest* sedikit lebih akurat (MAE 0.60 jam), *Linear Regression* dipilih karena **23x lebih cepat** (0.009 detik) dalam pemrosesan data.
- **Akurasi Sensor:** Sensor ultrasonik HC-SR04 yang dikalibrasi memiliki tingkat error rata-rata sangat kecil, yaitu **0.86%**.
- **Performa Sistem:** Sistem berhasil berjalan stabil pada Cloud VPS dengan latensi pengiriman data ESP32 sekitar **2-5 detik**.

---

## 🚀 Installation & Setup

Ikuti langkah-langkah berikut untuk menjalankan project di local environment:

### 1. Clone Repository
```bash
git clone https://github.com/username/project-iot-water.git
cd project-iot-water

composer install
cp .env.example .env
php artisan key:generate

php artisan migrate

npm install
npm run build

# Buat virtual environment
python -m venv .venv

# Activate (Windows)
.venv\\Scripts\\activate
# Activate (Mac/Linux)
source .venv/bin/activate

# Install library ML (Pandas, Scikit-learn, MySQL-connector)
pip install -r requirements.txt
```

---

## ▶️ Running the App
Untuk menjalankan seluruh sistem, buka **3 terminal** berbeda dan jalankan perintah berikut:

### **Terminal 1 — Laravel Server**
```bash
php artisan serve
```

### **Terminal 2 — Vite Hot Reload**
```bash
npm run dev
```

### **Terminal 3 — Python ML Automation**
```bash
# Pastikan virtual environment (.venv) sudah aktif
cd scripts_python
python ml_auto_service_v3.py
