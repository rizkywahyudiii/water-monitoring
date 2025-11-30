"""
ML Auto Service v5.0 - Hybrid System (Real-time Trend & Pattern Recognition)
Path: water-monitoring/python_scripts/ml_auto_service_v5.py

Deskripsi:
1. Menggunakan Linear Regression (Rolling Window) untuk menghitung sisa waktu real-time.
   - Mengatasi masalah noise/riak air dari sensor ultrasonik.
2. Menggunakan Random Forest (Background) untuk mempelajari kebiasaan jam pemakaian.
"""

import os
import time
import sys
import joblib
import mysql.connector
import numpy as np
import pandas as pd
from datetime import datetime, timedelta
from dotenv import load_dotenv

# Machine Learning Libraries
from sklearn.linear_model import LinearRegression
from sklearn.ensemble import RandomForestRegressor
from sklearn.model_selection import train_test_split
from sklearn.metrics import mean_absolute_error, r2_score

# ===================== 1. CONFIGURATION & ENV =====================

# Setup Path (Menyesuaikan lokasi script agar bisa baca .env laravel)
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
ENV_PATH = os.path.join(BASE_DIR, '../.env') # Asumsi folder script ada di dalam project laravel
STORAGE_PATH = os.path.join(BASE_DIR, '../storage/app/models')

# Load .env
if os.path.exists(ENV_PATH):
    load_dotenv(ENV_PATH)
else:
    print(f"⚠️ Warning: .env file not found at {ENV_PATH}")

# Pastikan folder model ada
os.makedirs(STORAGE_PATH, exist_ok=True)
MODEL_PATH_RF = os.path.join(STORAGE_PATH, "water_pattern_rf.joblib")

# Database Config (Ambil dari .env)
DB_CONFIG = {
    "host": os.getenv('DB_HOST', '127.0.0.1'),
    "user": os.getenv('DB_USERNAME', 'root'),
    "password": os.getenv('DB_PASSWORD', ''),
    "database": os.getenv('DB_DATABASE', 'water_monitoring'),
    "port": int(os.getenv('DB_PORT', 3306)),
    "charset": "utf8mb4", # Penting untuk koneksi stabil
}

# Settings
CHECK_INTERVAL = 5      # Cek database setiap 5 detik
WINDOW_SIZE = 20        # Ambil 20 data terakhir untuk hitung trend (Linear Reg)
RETRAIN_THRESHOLD = 100 # Retrain model Random Forest setiap 100 data baru

# ===================== 2. DATABASE HELPER FUNCTIONS =====================

def get_db():
    """Membuat koneksi ke database dengan error handling."""
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        return conn
    except mysql.connector.Error as err:
        print(f"❌ DB Connection Error: {err}")
        return None

def fetch_latest_sensor(conn):
    """Mengambil 1 data sensor terakhir."""
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT * FROM sensor_data ORDER BY id DESC LIMIT 1")
    row = cursor.fetchone()
    cursor.close()
    return row

def fetch_data_window(conn, limit=20):
    """Mengambil X data terakhir untuk perhitungan Linear Regression Real-time."""
    cursor = conn.cursor(dictionary=True)
    # Kita butuh created_at untuk sumbu X (waktu) dan water_level untuk sumbu Y
    query = "SELECT id, water_level, created_at FROM sensor_data ORDER BY id DESC LIMIT %s"
    cursor.execute(query, (limit,))
    rows = cursor.fetchall()
    cursor.close()
    return rows

def fetch_training_data(conn):
    """Mengambil data historis untuk training Random Forest (Pola Pemakaian)."""
    # Kita aggregate per jam agar data lebih bersih untuk ML
    query = """
        SELECT
            HOUR(created_at) as hour_of_day,
            AVG(water_level) as avg_level
            -- Disini bisa ditambahkan logika rata-rata depletion rate jika kolomnya sudah bersih
        FROM sensor_data
        GROUP BY hour_of_day, DATE(created_at)
        ORDER BY created_at DESC LIMIT 5000
    """
    try:
        df = pd.read_sql(query, conn)
        return df
    except Exception as e:
        print(f"⚠️ Gagal fetch training data: {e}")
        return pd.DataFrame()

def save_prediction(conn, sensor_row, hours, rate, method, status_text):
    """Menyimpan hasil perhitungan ke tabel predictions."""
    try:
        cursor = conn.cursor()
        query = """
        INSERT INTO predictions (
            sensor_data_id, predicted_hours, predicted_method,
            current_level, predicted_rate, time_remaining,
            created_at, updated_at
        ) VALUES (%s, %s, %s, %s, %s, %s, NOW(), NOW())
        """
        vals = (sensor_row["id"], hours, method, sensor_row["water_level"], rate, status_text)
        cursor.execute(query, vals)
        conn.commit()
        cursor.close()
    except Exception as e:
        print(f"❌ Gagal simpan prediksi: {e}")

# ===================== 3. CORE LOGIC: LINEAR REGRESSION (REAL-TIME) =====================

def calculate_trend_linear_reg(rows):
    """
    Inti dari sistem v5.0:
    Menghitung kemiringan (slope) dari grafik air menggunakan Linear Regression.
    Return: (rate_per_hour, r_squared_score)
    """
    if len(rows) < 5:
        return 0, 0 # Data belum cukup

    df = pd.DataFrame(rows)
    df['timestamp'] = pd.to_datetime(df['created_at'])

    # Konversi waktu ke detik relative (agar bisa dihitung matematika)
    # Titik 0 adalah data paling lama di window ini
    start_time = df['timestamp'].min()
    df['seconds'] = (df['timestamp'] - start_time).dt.total_seconds()

    X = df[['seconds']].values # Input: Waktu
    y = df['water_level'].values # Target: Level Air

    # Fit Linear Regression
    model = LinearRegression()
    model.fit(X, y)

    slope_per_sec = model.coef_[0]
    r2 = model.score(X, y) # Seberapa lurus garisnya (1.0 = lurus sempurna)

    # Konversi ke per jam (dikali 3600 detik)
    # Slope negatif = Air berkurang
    # Slope positif = Air bertambah
    rate_per_hour = slope_per_sec * 3600

    return rate_per_hour, r2

# ===================== 4. CORE LOGIC: RANDOM FOREST (BACKGROUND PATTERN) =====================

def train_background_model(conn):
    """Melatih model Random Forest untuk mempelajari pola jam."""
    print("   🔄 Background Training: Random Forest...")
    df = fetch_training_data(conn)

    if df.empty or len(df) < 50:
        print("   ⚠️ Data belum cukup untuk training pola.")
        return None

    # Disini kita bisa buat model sederhana: Input Jam -> Output Level Rata2
    # (Ini contoh simplifikasi, bisa dikembangkan lebih kompleks)
    X = df[['hour_of_day']]
    y = df['avg_level']

    model = RandomForestRegressor(n_estimators=50, max_depth=5, random_state=42)
    model.fit(X, y)

    # Save model
    joblib.dump(model, MODEL_PATH_RF)
    print("   ✅ Model Random Forest Updated!")
    return model

# ===================== 5. UTILITIES =====================

def format_time_text(hours):
    """Mengubah float jam menjadi teks yang enak dibaca."""
    if hours >= 100 or hours < 0: return "> 2 Hari"
    if hours < 0.05: return "Selesai / Penuh"

    h = int(hours)
    m = int((hours - h) * 60)

    if h > 0:
        return f"{h} jam {m} menit"
    return f"{m} menit"

# ===================== 6. MAIN LOOP =====================

def main():
    print("==============================================")
    print("🤖 WATER MONITORING AI SERVICE v5.0 (HYBRID)")
    print(f"🎯 Database: {DB_CONFIG['database']} @ {DB_CONFIG['host']}")
    print("==============================================")

    last_processed_id = None
    data_counter_since_retrain = 0

    while True:
        conn = get_db()
        if not conn:
            time.sleep(10)
            continue

        try:
            # 1. Cek Data Terbaru
            latest = fetch_latest_sensor(conn)

            if not latest:
                print("💤 Belum ada data sensor...", end='\r')
                conn.close(); time.sleep(CHECK_INTERVAL); continue

            if latest["id"] == last_processed_id:
                # Tidak ada data baru, skip
                conn.close(); time.sleep(CHECK_INTERVAL); continue

            # 2. Ambil Window Data untuk Analisis Trend
            window_data = fetch_data_window(conn, limit=WINDOW_SIZE)

            # 3. Hitung Trend Real-time (Linear Regression)
            rate_per_hour, r2_score = calculate_trend_linear_reg(window_data)

            current_level = float(latest["water_level"])

            # Variabel Output
            pred_hours = 0
            final_rate = 0
            method = "IDLE"
            status_msg = "Stabil"

            # 4. Logika Penentuan Status
            # Threshold: Jika perubahan kurang dari 1% per jam, dianggap Noise/Stabil
            NOISE_THRESHOLD = 1.0

            if rate_per_hour < -NOISE_THRESHOLD:
                # --- KASUS: AIR BERKURANG (DRAINING) ---
                drain_rate = abs(rate_per_hour)

                # Hindari pembagian nol
                if drain_rate > 0.1:
                    pred_hours = current_level / drain_rate

                status_msg = f"Habis dlm {format_time_text(pred_hours)}"
                method = f"LinReg (Trend: -{drain_rate:.1f}%/h)"
                final_rate = drain_rate
                print(f"🔻 TURUN: Lvl {current_level}% | Rate: -{drain_rate:.1f}%/jam | {status_msg}")

            elif rate_per_hour > NOISE_THRESHOLD:
                # --- KASUS: AIR BERTAMBAH (FILLING) ---
                fill_rate = abs(rate_per_hour)
                remaining_space = 100 - current_level

                if fill_rate > 0.1:
                    pred_hours = remaining_space / fill_rate

                status_msg = f"Penuh dlm {format_time_text(pred_hours)}"
                method = f"Pump Detect (+{fill_rate:.1f}%/h)"
                final_rate = fill_rate
                print(f"🔼 NAIK: Lvl {current_level}% | Rate: +{fill_rate:.1f}%/jam | {status_msg}")

            else:
                # --- KASUS: STABIL ---
                status_msg = "Stabil"
                method = "Stabil"
                print(f"💤 STABIL: Lvl {current_level}% (Noise: {rate_per_hour:.2f})")

            # 5. Simpan Hasil ke DB
            save_prediction(conn, latest, pred_hours, final_rate, method, status_msg)

            last_processed_id = latest["id"]
            data_counter_since_retrain += 1

            # 6. Cek Perlu Retrain Random Forest? (Opsional)
            if data_counter_since_retrain >= RETRAIN_THRESHOLD:
                train_background_model(conn)
                data_counter_since_retrain = 0

        except KeyboardInterrupt:
            print("\n🛑 Stopping Service...")
            sys.exit()
        except Exception as e:
            print(f"\n❌ Error in Loop: {e}")
            # Jangan exit, coba lanjut di loop berikutnya

        finally:
            if conn.is_connected():
                conn.close()

        time.sleep(CHECK_INTERVAL)

if __name__ == "__main__":
    main()
