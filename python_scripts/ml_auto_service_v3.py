"""
ML Auto Service v5.0 - Hybrid Linear Regression & Random Forest
Path: water-monitoring/python_scripts/ml_auto_service_v5.py
"""

import os
import time
import joblib
import mysql.connector
import numpy as np
import pandas as pd
from sklearn.linear_model import LinearRegression
from sklearn.ensemble import RandomForestRegressor
from sklearn.model_selection import train_test_split
from sklearn.metrics import r2_score
from dotenv import load_dotenv
from datetime import datetime, timedelta

# ... (BAGIAN CONFIG & DB SAMA SEPERTI SEBELUMNYA) ...
# Ganti nama model file
MODEL_PATH = os.path.join(STORAGE_PATH, "water_pattern_rf.joblib")

# ===================== CORE LOGIC BARU =====================

def calculate_realtime_trend(conn, sensor_id, window_limit=15):
    """
    Menghitung laju air (Rate) menggunakan Linear Regression
    pada X menit data terakhir untuk menghilangkan noise riak air.
    """
    cursor = conn.cursor(dictionary=True)
    # Ambil data X menit terakhir
    cursor.execute("""
        SELECT water_level, created_at
        FROM sensor_data
        ORDER BY id DESC LIMIT %s
    """, (window_limit,))
    rows = cursor.fetchall()
    cursor.close()

    if len(rows) < 5: return 0, "Not enough data"

    # Konversi ke DataFrame
    df = pd.DataFrame(rows)
    df['timestamp'] = pd.to_datetime(df['created_at'])

    # Ubah waktu menjadi "menit yang lalu" (Numeric untuk regresi)
    # Kita pakai detik (timestamp) agar presisi
    latest_time = df['timestamp'].max()
    df['seconds_rel'] = (df['timestamp'] - latest_time).dt.total_seconds()

    # X = Waktu (detik), y = Level Air
    X = df[['seconds_rel']].values
    y = df['water_level'].values

    # Fit Linear Regression (Mencari Slope/Kemiringan)
    reg = LinearRegression().fit(X, y)
    slope_per_sec = reg.coef_[0]

    # Konversi slope per detik ke slope per jam
    slope_per_hour = slope_per_sec * 3600

    # slope negatif = air berkurang, positif = air nambah
    return slope_per_hour, len(rows)

def prepare_dataset_for_pattern(conn):
    """
    Mengambil data historis untuk mempelajari pola jam.
    Fitur: [Jam, Hari] -> Target: [Rata-rata Rate/Jam]
    """
    # Query ini merata-rata rate per jam agar lebih halus
    query = """
        SELECT
            HOUR(created_at) as hour_of_day,
            AVG(depletion_rate) as avg_rate
        FROM sensor_data
        WHERE depletion_rate > 0.5  -- Hanya ambil saat air benar2 dipakai
        GROUP BY hour_of_day, DATE(created_at)
    """
    df = pd.read_sql(query, conn)
    if df.empty: return None, None

    X = df[['hour_of_day']]
    y = df['avg_rate']
    return X, y

def train_pattern_model(X, y):
    # Ganti ke Random Forest (Lebih stabil untuk data ghaib/sedikit)
    model = RandomForestRegressor(n_estimators=100, max_depth=10, random_state=42)
    X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2)
    model.fit(X_train, y_train)

    score = model.score(X_test, y_test)
    return model, score

# ===================== MAIN LOOP =====================
def main():
    print("🤖 Hybrid Water Monitor (Linear Reg + Random Forest)")

    # Load Model Pola (Jika ada)
    pattern_model = None
    if os.path.exists(MODEL_PATH):
        pattern_model = joblib.load(MODEL_PATH)

    last_processed_id = None

    while True:
        conn = get_db()
        if not conn: time.sleep(5); continue

        try:
            latest = fetch_latest_sensor(conn)
            if not latest or latest["id"] == last_processed_id:
                conn.close(); time.sleep(CHECK_INTERVAL); continue

            current_level = float(latest["water_level"])

            # --- 1. HITUNG REALTIME RATE (LINEAR REGRESSION) ---
            # Ini jauh lebih akurat daripada hitungan PHP
            real_rate_per_hour, sample_count = calculate_realtime_trend(conn, latest["id"], window_limit=20)

            pred_hours = 0
            status_text = "Stabil"
            method = "IDLE"
            final_rate_display = 0

            # Logic Prediksi
            # Ambang batas 1% per jam dianggap noise/stabil
            if real_rate_per_hour < -1.0:
                # SEDANG DIPAKAI (Slope Negatif)
                drain_rate = abs(real_rate_per_hour)
                pred_hours = current_level / drain_rate
                status_text = format_time(pred_hours, "Habis dlm")
                method = f"LinearReg (n={sample_count})"
                final_rate_display = drain_rate

            elif real_rate_per_hour > 1.0:
                # SEDANG DIISI (Slope Positif)
                fill_rate = abs(real_rate_per_hour)
                pred_hours = (100 - current_level) / fill_rate
                status_text = format_time(pred_hours, "Penuh dlm")
                method = "Pump Detect"
                final_rate_display = fill_rate

            else:
                # STABIL / IDLE
                # Di sini kita bisa pakai ML untuk iseng prediksi "Biasanya jam segini kepakai gak?"
                # Tapi untuk status dashboard, lebih baik tampilkan "Stabil"
                status_text = "Stabil"
                method = "Stabil"

            print(f"📊 Lvl: {current_level}% | Trend: {real_rate_per_hour:.2f}%/jam | Status: {status_text}")

            save_prediction(conn, latest, pred_hours, final_rate_display, method, status_text)

            # --- 2. BACKGROUND RETRAINING (OPSIONAL) ---
            # Lakukan retraining model Random Forest setiap 100 data baru
            # ... (Logic retraining mirip v3 tapi pakai Random Forest) ...

            last_processed_id = latest["id"]

        except Exception as e:
            print(f"Error: {e}")

        conn.close()
        time.sleep(CHECK_INTERVAL)

if __name__ == "__main__":
    main()
