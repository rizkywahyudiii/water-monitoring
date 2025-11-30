"""
ML Auto Service v5.1 - Hybrid System + Model Evaluation Storage
Path: water-monitoring/python_scripts/ml_auto_service_v5.py

Updates:
- v5.1: Menambahkan penyimpanan metrik evaluasi (MAE, RMSE, R2) ke tabel model_evaluation.
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
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score

# ===================== 1. CONFIGURATION & ENV =====================

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
ENV_PATH = os.path.join(BASE_DIR, '../.env')
STORAGE_PATH = os.path.join(BASE_DIR, '../storage/app/models')

if os.path.exists(ENV_PATH):
    load_dotenv(ENV_PATH)

os.makedirs(STORAGE_PATH, exist_ok=True)
MODEL_PATH_RF = os.path.join(STORAGE_PATH, "water_pattern_rf.joblib")

DB_CONFIG = {
    "host": os.getenv('DB_HOST', '127.0.0.1'),
    "user": os.getenv('DB_USERNAME'),
    "password": os.getenv('DB_PASSWORD'),
    "database": os.getenv('DB_DATABASE'),
    "port": int(os.getenv('DB_PORT', 3306)),
    "charset": "utf8mb4",
}

# Settings
CHECK_INTERVAL = 5
WINDOW_SIZE = 20
RETRAIN_THRESHOLD = 100

# ===================== 2. DATABASE HELPER FUNCTIONS =====================

def get_db():
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        return conn
    except mysql.connector.Error as err:
        print(f"❌ DB Connection Error: {err}")
        return None

def fetch_latest_sensor(conn):
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT * FROM sensor_data ORDER BY id DESC LIMIT 1")
    row = cursor.fetchone()
    cursor.close()
    return row

def fetch_data_window(conn, limit=20):
    cursor = conn.cursor(dictionary=True)
    query = "SELECT id, water_level, created_at FROM sensor_data ORDER BY id DESC LIMIT %s"
    cursor.execute(query, (limit,))
    rows = cursor.fetchall()
    cursor.close()
    return rows

def fetch_training_data(conn):
    query = """
        SELECT
            HOUR(created_at) as hour_of_day,
            AVG(water_level) as avg_level
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

# --- [BARU] Fungsi Simpan Evaluasi Model ---
def save_model_evaluation(conn, mae, rmse, r2, samples, time_sec):
    try:
        cursor = conn.cursor()
        query = """
            INSERT INTO model_evaluation
            (mae, rmse, r2_score, training_samples, training_time, created_at, updated_at)
            VALUES (%s, %s, %s, %s, %s, NOW(), NOW())
        """
        cursor.execute(query, (mae, rmse, r2, samples, time_sec))
        conn.commit()
        cursor.close()
        print(f"   📈 Evaluasi tersimpan: R2={r2:.4f}, RMSE={rmse:.4f}")
    except Exception as e:
        print(f"❌ Gagal simpan evaluasi: {e}")

# ===================== 3. CORE LOGIC: LINEAR REGRESSION (REAL-TIME) =====================

def calculate_trend_linear_reg(rows):
    if len(rows) < 5: return 0, 0

    df = pd.DataFrame(rows)
    df['timestamp'] = pd.to_datetime(df['created_at'])
    start_time = df['timestamp'].min()
    df['seconds'] = (df['timestamp'] - start_time).dt.total_seconds()

    X = df[['seconds']].values
    y = df['water_level'].values

    model = LinearRegression()
    model.fit(X, y)

    slope_per_sec = model.coef_[0]
    r2 = model.score(X, y)

    rate_per_hour = slope_per_sec * 3600
    return rate_per_hour, r2

# ===================== 4. CORE LOGIC: RANDOM FOREST (BACKGROUND PATTERN) =====================

def train_background_model(conn):
    print("   🔄 Background Training: Random Forest...")
    start_time = time.time() # Mulai timer

    df = fetch_training_data(conn)

    if df.empty or len(df) < 50:
        print("   ⚠️ Data belum cukup untuk training pola.")
        return None

    # Persiapan Data
    X = df[['hour_of_day']]
    y = df['avg_level']

    # Split Data untuk Validasi
    X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42)

    # Training
    model = RandomForestRegressor(n_estimators=100, max_depth=10, random_state=42)
    model.fit(X_train, y_train)

    # Evaluasi Model
    y_pred = model.predict(X_test)

    # Hitung Metrik
    mae = mean_absolute_error(y_test, y_pred)
    rmse = np.sqrt(mean_squared_error(y_test, y_pred)) # Akar kuadrat dari MSE
    r2 = r2_score(y_test, y_pred)
    training_duration = time.time() - start_time

    # Simpan ke Database (BARU)
    save_model_evaluation(conn, mae, rmse, r2, len(df), training_duration)

    # Save file model
    joblib.dump(model, MODEL_PATH_RF)
    print("   ✅ Model Random Forest Updated!")
    return model

# ===================== 5. UTILITIES =====================

def format_time_text(hours):
    if hours >= 100 or hours < 0: return "> 2 Hari"
    if hours < 0.05: return "Selesai / Penuh"

    h = int(hours)
    m = int((hours - h) * 60)

    if h > 0: return f"{h} jam {m} menit"
    return f"{m} menit"

# ===================== 6. MAIN LOOP =====================

def main():
    print("==============================================")
    print("🤖 WATER MONITORING AI SERVICE v5.1 (Evaluasi Aktif)")
    print(f"🎯 Database: {DB_CONFIG['database']} @ {DB_CONFIG['host']}")
    print("==============================================")

    last_processed_id = None
    data_counter_since_retrain = 0

    while True:
        conn = get_db()
        if not conn: time.sleep(10); continue

        try:
            latest = fetch_latest_sensor(conn)

            if not latest:
                print("💤 Belum ada data sensor...", end='\r')
                conn.close(); time.sleep(CHECK_INTERVAL); continue

            if latest["id"] == last_processed_id:
                conn.close(); time.sleep(CHECK_INTERVAL); continue

            # --- REALTIME LOGIC ---
            window_data = fetch_data_window(conn, limit=WINDOW_SIZE)
            rate_per_hour, r2_score = calculate_trend_linear_reg(window_data)
            current_level = float(latest["water_level"])

            pred_hours = 0
            final_rate = 0
            method = "IDLE"
            status_msg = "Stabil"
            NOISE_THRESHOLD = 1.0

            if rate_per_hour < -NOISE_THRESHOLD:
                drain_rate = abs(rate_per_hour)
                if drain_rate > 0.1: pred_hours = current_level / drain_rate
                status_msg = f"Habis dlm {format_time_text(pred_hours)}"
                method = f"LinReg (-{drain_rate:.1f}%/h)"
                final_rate = drain_rate
                print(f"🔻 TURUN: Lvl {current_level}% | Rate: -{drain_rate:.1f}%/jam | {status_msg}")

            elif rate_per_hour > NOISE_THRESHOLD:
                fill_rate = abs(rate_per_hour)
                remaining_space = 100 - current_level
                if fill_rate > 0.1: pred_hours = remaining_space / fill_rate
                status_msg = f"Penuh dlm {format_time_text(pred_hours)}"
                method = f"Pump Detect (+{fill_rate:.1f}%/h)"
                final_rate = fill_rate
                print(f"🔼 NAIK: Lvl {current_level}% | Rate: +{fill_rate:.1f}%/jam | {status_msg}")

            else:
                status_msg = "Stabil"
                method = "Stabil"
                print(f"💤 STABIL: Lvl {current_level}% (Noise: {rate_per_hour:.2f})")

            save_prediction(conn, latest, pred_hours, final_rate, method, status_msg)

            last_processed_id = latest["id"]
            data_counter_since_retrain += 1

            # --- RETRAINING LOGIC ---
            if data_counter_since_retrain >= RETRAIN_THRESHOLD:
                # Sekarang fungsi ini sudah menyimpan metrik ke DB
                train_background_model(conn)
                data_counter_since_retrain = 0

        except KeyboardInterrupt:
            print("\n🛑 Stopping Service...")
            sys.exit()
        except Exception as e:
            print(f"\n❌ Error in Loop: {e}")

        finally:
            if conn.is_connected():
                conn.close()

        time.sleep(CHECK_INTERVAL)

if __name__ == "__main__":
    main()
