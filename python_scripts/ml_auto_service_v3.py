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

# Hyperparameters & Constants
CHECK_INTERVAL = 5          # Detik antar loop
WINDOW_SIZE = 60            # Ambil 60 data terakhir (Sesuai request 40-80)
ROLLING_WINDOW = 5          # Rolling mean window (Sesuai request 5-7)
OUTLIER_THRESHOLD = 5.0     # Abaikan jika lonjakan > 5%
RETRAIN_THRESHOLD = 10     # Retrain setiap 100 data baru

# Ensure storage exists
os.makedirs(STORAGE_PATH, exist_ok=True)
MODEL_FILE = os.path.join(STORAGE_PATH, "hybrid_rf_model.joblib")

# ================= DATABASE FUNCTIONS =================

def get_db():
    """Membuat koneksi database yang robust."""
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

def fetch_window_data(conn, limit=WINDOW_SIZE):
    """Mengambil N data terakhir untuk perhitungan Trend Real-time."""
    cursor = conn.cursor(dictionary=True)
    # Kita butuh created_at (waktu) dan water_level (nilai)
    query = """
        SELECT id, water_level, created_at
        FROM sensor_data
        ORDER BY id DESC LIMIT %s
    """
    cursor.execute(query, (limit,))
    rows = cursor.fetchall()
    cursor.close()
    # Return reversed (dari lama ke baru) agar rolling window benar
    return rows[::-1]

def fetch_training_data(conn, limit=5000):
    """Mengambil data lengkap untuk Training Random Forest."""
    # Fitur: hour, minute, water_level, distance, turbidity, depletion_rate
    query = """
        SELECT
            HOUR(created_at) as hour,
            MINUTE(created_at) as minute,
            water_level,
            distance,
            turbidity,
            depletion_rate
        FROM sensor_data
        WHERE depletion_rate IS NOT NULL
        ORDER BY id DESC LIMIT %s
    """
    try:
        df = pd.read_sql(query, conn, params=(limit,))
        return df
    except Exception as e:
        print(f"⚠️ Error fetch training: {e}")
        return pd.DataFrame()

def save_prediction(conn, sensor_id, current_level, p_hours, p_time_str, method, slope, r2):
    """Menyimpan hasil prediksi ke tabel predictions."""
    try:
        cursor = conn.cursor()
        # Note: Pastikan tabel predictions punya kolom slope dan r_squared,
        # jika tidak, hapus kolom tersebut dari query ini.
        query = """
            INSERT INTO predictions (
                sensor_data_id, current_level, predicted_hours,
                time_remaining, predicted_method, predicted_rate,
                r_squared, created_at, updated_at
            ) VALUES (%s, %s, %s, %s, %s, %s, %s, NOW(), NOW())
        """
        # predicted_rate kita isi dengan slope (trend)
        cursor.execute(query, (sensor_id, current_level, p_hours, p_time_str, method, slope, r2))
        conn.commit()
        cursor.close()

        # Output JSON format ke console (untuk log)
        log_data = {
            "predicted_hours": float(p_hours),
            "time_remaining": p_time_str,
            "method": method,
            "slope": float(slope),
            "r2": float(r2)
        }
        print(f"✅ Saved: {json.dumps(log_data)}")

    except Exception as e:
        print(f"❌ Save Pred Error: {e}")

def save_model_evaluation(conn, mae, rmse, r2, n_samples, t_time):
    """Menyimpan metrik evaluasi model ke tabel model_evaluation."""
    try:
        cursor = conn.cursor()
        query = """
            INSERT INTO model_evaluation (
                mae, rmse, r2_score, training_samples, training_time,
                created_at, updated_at
            ) VALUES (%s, %s, %s, %s, %s, NOW(), NOW())
        """
        cursor.execute(query, (mae, rmse, r2, n_samples, t_time))
        conn.commit()
        cursor.close()
        print(f"📈 Evaluation Saved: R²={r2:.4f}, RMSE={rmse:.4f}")
    except Exception as e:
        print(f"❌ Save Eval Error: {e}")

# ================= CORE LOGIC =================

def calculate_trend_realtime(rows):
    """
    Logic Inti: Linear Regression pada Window Data + Noise Filtering.
    """
    if len(rows) < 10: return 0, 0, 0 # Data kurang

    df = pd.DataFrame(rows)
    df['timestamp'] = pd.to_datetime(df['created_at'])

    # 1. Noise Filtering (Rolling Mean)
    # Menghaluskan grafik agar riak air tidak dianggap perubahan
    df['level_smooth'] = df['water_level'].rolling(window=ROLLING_WINDOW, min_periods=1).mean()

    # 2. Outlier Check (Opsional: Bandingkan data terakhir dgn smooth)
    last_raw = df['water_level'].iloc[-1]
    last_smooth = df['level_smooth'].iloc[-1]

    # Jika lonjakan > 5%, kita percayai smooth value saja (ignore raw outlier)
    if abs(last_raw - last_smooth) > OUTLIER_THRESHOLD:
        current_val_for_calc = last_smooth
    else:
        current_val_for_calc = last_smooth # Default pakai smooth biar aman

    # 3. Trend Calculation (Linear Regression)
    # Konversi waktu ke detik relatif
    start_time = df['timestamp'].iloc[0]
    df['seconds'] = (df['timestamp'] - start_time).dt.total_seconds()

    X = df[['seconds']].values
    y = df['level_smooth'].values # Pakai data yang sudah di-smooth

    model = LinearRegression()
    model.fit(X, y)

    slope_per_sec = model.coef_[0]
    r2 = model.score(X, y)

    # Konversi ke % per jam
    slope_per_hour = slope_per_sec * 3600

    return slope_per_hour, r2, current_val_for_calc

def format_time_prediction(hours):
    """Format float jam ke string '{jam} jam {menit} menit'."""
    if hours < 0: return "Hitungan Error"
    if hours > 48: return "> 2 Hari"

    h = int(hours)
    m = int((hours - h) * 60)

    text = ""
    if h > 0: text += f"{h} jam "
    text += f"{m} menit"
    return text.strip()

def retrain_random_forest(conn):
    """Background Process: Retrain Random Forest & Save Evaluation."""
    print("🔄 Starting Retraining Process...")
    start_time = time.time()

    df = fetch_training_data(conn)

    if len(df) < 50:
        print("⚠️ Not enough data to train RF.")
        return

    # Features & Target
    # Skenario: Kita ingin memprediksi 'depletion_rate' berdasarkan kondisi saat ini
    # untuk mengetahui apakah kondisi sekarang normal atau anomali.
    feature_cols = ['hour', 'minute', 'water_level', 'distance', 'turbidity']
    target_col = 'depletion_rate'

    # Drop rows with NaN
    df = df.dropna(subset=feature_cols + [target_col])

    X = df[feature_cols]
    y = df[target_col]

    X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42)

    # Train Model
    rf_model = RandomForestRegressor(n_estimators=100, max_depth=10, random_state=42)
    rf_model.fit(X_train, y_train)

    # Evaluate
    y_pred = rf_model.predict(X_test)
    mae = mean_absolute_error(y_test, y_pred)
    rmse = np.sqrt(mean_squared_error(y_test, y_pred))
    r2 = r2_score(y_test, y_pred)

    train_time = time.time() - start_time

    # Save Evaluation
    save_model_evaluation(conn, mae, rmse, r2, len(df), train_time)

    # Save Model File
    joblib.dump(rf_model, MODEL_FILE)
    print("✅ Model Retrained & Saved.")

# ================= MAIN LOOP =================

def main():
    print("🤖 Smart Water Monitor - Hybrid Architecture Started")
    print(f"🎯 Noise Filter: Window {ROLLING_WINDOW}, Outlier > {OUTLIER_THRESHOLD}%")

    last_processed_id = None
    data_counter = 0

    while True:
        conn = get_db()
        if not conn:
            time.sleep(10)
            continue

        try:
            # 1. Fetch Data
            latest = fetch_latest_sensor(conn)
            if not latest:
                conn.close(); time.sleep(CHECK_INTERVAL); continue

            if latest['id'] == last_processed_id:
                conn.close(); time.sleep(CHECK_INTERVAL); continue

            # 2. Fetch Window Data & Calculate Trend
            window_rows = fetch_window_data(conn, limit=WINDOW_SIZE)
            slope_hour, r2, smoothed_level = calculate_trend_realtime(window_rows)

            # Gunakan level asli untuk tampilan, tapi logic trend pakai smoothed
            current_level = float(latest['water_level'])

            # 3. Prediction Logic
            pred_hours = 0.0
            time_str = "Stabil"
            method = "Stabil"

            # Threshold R2 0.2 memastikan kita tidak memprediksi saat data terlalu acak
            if slope_hour < -1.0 and r2 > 0.2:
                # KASUS: AIR BERKURANG (DRAINING)
                drain_rate = abs(slope_hour)
                if drain_rate > 0.1:
                    pred_hours = current_level / drain_rate
                    time_str = format_time_prediction(pred_hours) + " (Habis)"
                    method = f"Draining (-{drain_rate:.1f}%/h)"

            elif slope_hour > 1.0 and r2 > 0.2:
                # KASUS: PENGISIAN (FILLING)
                fill_rate = abs(slope_hour)
                remaining = 100 - current_level
                if fill_rate > 0.1:
                    pred_hours = remaining / fill_rate
                    time_str = format_time_prediction(pred_hours) + " (Penuh)"
                    method = f"Filling (+{fill_rate:.1f}%/h)"

            else:
                # KASUS: STABIL / NOISE
                time_str = "Stabil"
                method = "Stabil"
                pred_hours = 0.0

            # 4. Save Prediction
            save_prediction(conn, latest['id'], current_level, pred_hours, time_str, method, slope_hour, r2)

            # 5. Check Retraining Logic
            last_processed_id = latest['id']
            data_counter += 1

            if data_counter >= RETRAIN_THRESHOLD:
                retrain_random_forest(conn)
                data_counter = 0 # Reset counter

        except KeyboardInterrupt:
            print("\n🛑 Stopping Service...")
            sys.exit()
        except Exception as e:
            print(f"\n❌ Error Main Loop: {e}")
        finally:
            if conn.is_connected():
                conn.close()

        time.sleep(CHECK_INTERVAL)

if __name__ == "__main__":
    main()
