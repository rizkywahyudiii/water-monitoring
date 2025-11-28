"""
ML Auto Service v4.0 - Time Aware Prediction
Path: water-monitoring/python_scripts/ml_auto_service_v3.py

Update v4.0:
- Menambahkan fitur 'HOUR' (Jam) ke dalam training model.
- Akurasi (R2) diharapkan naik drastis karena pola pemakaian air bergantung waktu.
"""

import os
import time
from datetime import datetime
import joblib
import lightgbm as lgb
import mysql.connector
import numpy as np
import pandas as pd
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import StandardScaler
from dotenv import load_dotenv

# ===================== CONFIG & ENV =====================
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
ENV_PATH = os.path.join(BASE_DIR, '../.env')
if os.path.exists(ENV_PATH): load_dotenv(ENV_PATH)

DB_CONFIG = {
    "host": os.getenv('DB_HOST', '127.0.0.1'),
    "user": os.getenv('DB_USERNAME', 'root'),
    "password": os.getenv('DB_PASSWORD', ''),
    "database": os.getenv('DB_DATABASE', 'water_monitoring'),
    "port": int(os.getenv('DB_PORT', 3306)),
    "charset": "utf8mb4",
}

STORAGE_PATH = os.path.join(BASE_DIR, '../storage/app/models')
os.makedirs(STORAGE_PATH, exist_ok=True)
MODEL_PATH = os.path.join(STORAGE_PATH, "water_depletion_lgbm.joblib")
SCALER_PATH = os.path.join(STORAGE_PATH, "scaler.joblib")

TRAINING_LIMIT = 1000             # Naikkan limit training biar lebih pintar
MIN_SAMPLES = 50
CHECK_INTERVAL = 5
RETRAIN_THRESHOLD = 50
ROLLING_PERCENTILE = 0.95

LGBM_PARAMS = {
    'objective': 'regression', 'metric': 'rmse', 'boosting_type': 'gbdt',
    'num_leaves': 31, 'learning_rate': 0.05, 'feature_fraction': 0.9,
    'bagging_fraction': 0.8, 'bagging_freq': 5, 'verbose': -1, 'min_child_samples': 20
}

# ===================== DATABASE FUNCTIONS =====================
def get_db():
    try: return mysql.connector.connect(**DB_CONFIG)
    except Exception as e: print(f"❌ DB Error: {e}"); return None

def fetch_latest_sensor(conn):
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT * FROM sensor_data ORDER BY id DESC LIMIT 1")
    row = cursor.fetchone()
    cursor.close()
    return row

def fetch_training_data(conn, limit=TRAINING_LIMIT):
    # UPDATE: Ambil juga jam (HOUR) dari timestamp
    query = """
        SELECT
            turbidity,
            distance,
            water_level,
            depletion_rate,
            HOUR(created_at) as hour_of_day
        FROM sensor_data
        WHERE depletion_rate > 0
        ORDER BY id DESC LIMIT %s
    """
    df = pd.read_sql(query, conn, params=(limit,))
    return df

def save_prediction(conn, sensor_row, hours, rate, method, status_text):
    cursor = conn.cursor()
    cursor.execute(
        """
        INSERT INTO predictions (
            sensor_data_id, predicted_hours, predicted_method,
            current_level, predicted_rate, time_remaining,
            created_at, updated_at
        ) VALUES (%s, %s, %s, %s, %s, %s, NOW(), NOW())
        """,
        (sensor_row["id"], hours, method, sensor_row["water_level"], rate, status_text)
    )
    conn.commit()
    cursor.close()

def save_model_evaluation(conn, mae, rmse, r2, samples, time_sec):
    cursor = conn.cursor()
    cursor.execute(
        "INSERT INTO model_evaluation (mae, rmse, r2_score, training_samples, training_time, created_at, updated_at) VALUES (%s, %s, %s, %s, %s, NOW(), NOW())",
        (mae, rmse, r2, samples, time_sec)
    )
    conn.commit()
    cursor.close()

def count_new_data(conn, last_id):
    if last_id is None: return 0
    cursor = conn.cursor()
    cursor.execute("SELECT COUNT(*) FROM sensor_data WHERE id > %s AND depletion_rate > 0", (last_id,))
    res = cursor.fetchone()
    cursor.close()
    return res[0] if res else 0

# ===================== ML & LOGIC =====================
def prepare_dataset(df):
    if df.empty or len(df) < MIN_SAMPLES: return None
    df = df.dropna().drop_duplicates()

    # Winsorize Outlier
    upper = df["depletion_rate"].quantile(ROLLING_PERCENTILE)
    if upper > 0: df["depletion_rate"] = df["depletion_rate"].clip(upper=upper)

    # UPDATE: Input Feature tambah 'hour_of_day'
    # Turbidity sebenarnya kurang relevan utk rate, tapi kita keep aja
    X = df[["turbidity", "distance", "water_level", "hour_of_day"]]
    y = df["depletion_rate"]
    return X, y

def train_model(X, y):
    X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42)
    scaler = StandardScaler()
    X_train_s = scaler.fit_transform(X_train)
    X_test_s = scaler.transform(X_test)

    start = time.time()
    train_ds = lgb.Dataset(X_train_s, label=y_train)
    valid_ds = lgb.Dataset(X_test_s, label=y_test, reference=train_ds)

    model = lgb.train(LGBM_PARAMS, train_ds, num_boost_round=300, # Naikkan round
                      valid_sets=[valid_ds],
                      callbacks=[lgb.early_stopping(stopping_rounds=30, verbose=False)])

    y_pred = model.predict(X_test_s, num_iteration=model.best_iteration)
    mae = mean_absolute_error(y_test, y_pred) # Hitung MAE
    rmse = float(np.sqrt(mean_squared_error(y_test, y_pred)))
    r2 = r2_score(y_test, y_pred)

    return model, scaler, mae, rmse, r2, len(y), time.time() - start # Return MAE juga

def load_model_disk():
    if os.path.exists(MODEL_PATH) and os.path.exists(SCALER_PATH):
        try: return joblib.load(MODEL_PATH), joblib.load(SCALER_PATH)
        except: pass
    return None, None

def format_time(hours, prefix=""):
    if hours < 0.02: return "Selesai"
    h = int(hours)
    m = int((hours - h) * 60)
    text = ""
    if h > 0: text += f"{h}j "
    text += f"{m}m"
    return f"{prefix} {text}".strip()

# ===================== MAIN PROCESS =====================
def main():
    print("🤖 Smart Water Monitor v4.0 (Time Aware)")
    print(f"📂 DB Target: {DB_CONFIG['database']}")

    current_model, current_scaler = load_model_disk()
    last_processed_id = None
    last_retrain_id = None

    while True:
        conn = get_db()
        if not conn: time.sleep(5); continue

        try:
            latest = fetch_latest_sensor(conn)
            if not latest:
                conn.close(); time.sleep(CHECK_INTERVAL); continue

            if last_processed_id == latest["id"]:
                conn.close(); time.sleep(CHECK_INTERVAL); continue

            # Data Mentah
            raw_rate = float(latest["depletion_rate"] or 0)
            current_level = float(latest["water_level"])

            # Ambil JAM sekarang untuk fitur prediksi
            current_hour = datetime.now().hour

            pred_hours = 0
            pred_rate = 0
            method = "IDLE"
            status_text = "Stabil"

            # === LOGIKA PREDIKSI ===

            # 1. KASUS MENGISI (POMPA)
            if raw_rate < -0.5:
                fill_rate = abs(raw_rate)
                remaining_percent = 100 - current_level
                if fill_rate > 0:
                    pred_hours = remaining_percent / fill_rate
                    status_text = format_time(pred_hours, "Penuh dlm")
                    method = "PUMP_REFILL"
                    pred_rate = fill_rate
                print(f"🌊 MENGISI: Lvl {current_level}% | Rate +{fill_rate:.1f}% | {status_text}")

            # 2. KASUS MENGURAS (KERAN)
            elif raw_rate > 0.5:
                if current_model and current_scaler:
                    # UPDATE: Input array harus ada 4 fitur sekarang
                    # [turbidity, distance, water_level, hour_of_day]
                    feats = np.array([[
                        float(latest["turbidity"]),
                        float(latest["distance"]),
                        current_level,
                        float(current_hour) # Fitur Jam Penting!
                    ]])

                    ml_rate = float(current_model.predict(current_scaler.transform(feats))[0])

                    # Safety check biar gak negatif/nol
                    if ml_rate < 0.1: ml_rate = 0.1

                    pred_hours = current_level / ml_rate
                    status_text = format_time(pred_hours, "Habis dlm")
                    method = "LightGBM"
                    pred_rate = ml_rate
                    print(f"🚰 MEMAKAI: Lvl {current_level}% | ML Rate -{ml_rate:.1f}% | {status_text}")
                else:
                    status_text = "Menunggu AI..."

            # 3. KASUS STABIL
            else:
                print(f"💤 STABIL: Level {current_level}% (Hour: {current_hour})")
                status_text = "Stabil"

            save_prediction(conn, latest, pred_hours, pred_rate, method, status_text)

            # === LOGIKA RETRAINING ===
            new_count = count_new_data(conn, last_retrain_id)
            # Train lebih sering di awal
            if (current_model is None or new_count >= RETRAIN_THRESHOLD):
                df = fetch_training_data(conn)
                dataset = prepare_dataset(df)
                if dataset:
                    print(f"   🔄 Retraining dengan {len(dataset[0])} data (termasuk fitur Jam)...")
                    mod, sc, mae, rmse, r2, n, t = train_model(*dataset)

                    current_model, current_scaler = mod, sc
                    joblib.dump(mod, MODEL_PATH); joblib.dump(sc, SCALER_PATH)
                    save_model_evaluation(conn, mae, rmse, r2, n, t)

                    print(f"   ✅ Model Updated! R² Score: {r2:.4f} (Target > 0.8)")
                    last_retrain_id = latest["id"]

            last_processed_id = latest["id"]

        except Exception as e:
            print(f"❌ Error: {e}")

        conn.close()
        time.sleep(CHECK_INTERVAL)

if __name__ == "__main__":
    main()
