"""
ML Auto Service v3 - LightGBM Regressor (Laravel Integration)
Path: water-monitoring/python_scripts/ml_auto_service_v3.py

Fitur:
- Load Config Database dari .env Laravel
- Training otomatis LightGBM
- Prediksi waktu habis (hours & text)
- Auto-retrain jika ada data baru yang cukup
"""

import os
import time
import sys
from datetime import datetime

# Library Data Science & ML
import joblib
import lightgbm as lgb
import mysql.connector
import numpy as np
import pandas as pd
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import StandardScaler

# Library untuk baca .env Laravel
from dotenv import load_dotenv

# ===================== KONFIGURASI PATH & ENV =====================
# Mendapatkan path absolut folder script ini berada
BASE_DIR = os.path.dirname(os.path.abspath(__file__))

# Path ke file .env Laravel (naik satu level dari folder python_scripts)
ENV_PATH = os.path.join(BASE_DIR, '../.env')

# Load environment variables
if os.path.exists(ENV_PATH):
    load_dotenv(ENV_PATH)
    print(f"✅ Config loaded from: {ENV_PATH}")
else:
    print("⚠️ Warning: .env file not found. Using default settings.")

# Konfigurasi Database (Baca dari .env)
DB_CONFIG = {
    "host": os.getenv('DB_HOST', '127.0.0.1'),
    "user": os.getenv('DB_USERNAME', 'root'),
    "password": os.getenv('DB_PASSWORD', ''),
    "database": os.getenv('DB_DATABASE', 'water_monitoring_v3'),
    "port": int(os.getenv('DB_PORT', 3306)),
    "charset": "utf8mb4",
}

# Path penyimpanan Model (di folder storage Laravel)
STORAGE_PATH = os.path.join(BASE_DIR, '../storage/app/models')
os.makedirs(STORAGE_PATH, exist_ok=True)

MODEL_PATH = os.path.join(STORAGE_PATH, "water_depletion_lgbm.joblib")
SCALER_PATH = os.path.join(STORAGE_PATH, "scaler.joblib")

# ===================== PARAMETER ML =====================
TRAINING_LIMIT = 500              # Ambil 500 data terakhir untuk training
MIN_SAMPLES = 30                  # Minimal data valid untuk mulai training
CHECK_INTERVAL = 5                # Cek data baru tiap 5 detik
RETRAIN_THRESHOLD = 50            # Retrain ulang tiap 50 data baru
PREDICT_METHOD = "LightGBM"
ROLLING_PERCENTILE = 0.95         # Untuk membuang outlier ekstrem

# Parameter Hyperparameter LightGBM
LGBM_PARAMS = {
    'objective': 'regression',
    'metric': 'rmse',
    'boosting_type': 'gbdt',
    'num_leaves': 31,
    'learning_rate': 0.05,
    'feature_fraction': 0.8,
    'bagging_fraction': 0.8,
    'bagging_freq': 5,
    'verbose': -1,
    'min_child_samples': 20
}

# ===================== FUNGSI DATABASE =====================
def get_db():
    """Membuat koneksi ke database MySQL"""
    try:
        return mysql.connector.connect(**DB_CONFIG)
    except mysql.connector.Error as err:
        print(f"❌ DB Connection Error: {err}")
        return None

def fetch_latest_sensor(conn):
    """Mengambil 1 data sensor terakhir"""
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT * FROM sensor_data ORDER BY id DESC LIMIT 1")
    row = cursor.fetchone()
    cursor.close()
    return row

def fetch_training_data(conn, limit=TRAINING_LIMIT):
    """Mengambil dataset untuk training"""
    query = """
        SELECT turbidity, distance, water_level, depletion_rate, timestamp
        FROM sensor_data
        ORDER BY id DESC
        LIMIT %s
    """
    df = pd.read_sql(query, conn, params=(limit,))
    return df

def save_model_evaluation(conn, mae, rmse, r2, samples, training_time):
    """Menyimpan log performa model ke tabel model_evaluation"""
    cursor = conn.cursor()
    cursor.execute(
        """
        INSERT INTO model_evaluation (mae, rmse, r2_score, training_samples, training_time, created_at, updated_at)
        VALUES (%s, %s, %s, %s, %s, NOW(), NOW())
        """,
        (mae, rmse, r2, samples, training_time),
    )
    conn.commit()
    cursor.close()

def save_prediction(conn, sensor_row, predicted_hours, predicted_rate):
    """Menyimpan hasil prediksi ke tabel predictions"""
    # Format teks waktu (Human Readable)
    if predicted_hours < 1:
        time_rem = f"{int(predicted_hours * 60)} menit"
    else:
        hours = int(predicted_hours)
        mins = int((predicted_hours - hours) * 60)
        time_rem = f"{hours} jam {mins} menit"

    cursor = conn.cursor()
    cursor.execute(
        """
        INSERT INTO predictions (
            sensor_data_id, predicted_hours, predicted_method,
            current_level, predicted_rate, time_remaining,
            created_at, updated_at
        )
        VALUES (%s, %s, %s, %s, %s, %s, NOW(), NOW())
        """,
        (
            sensor_row["id"],
            predicted_hours,
            PREDICT_METHOD,
            sensor_row["water_level"],
            predicted_rate,
            time_rem
        ),
    )
    conn.commit()
    cursor.close()

def count_new_data(conn, last_processed_id):
    """Menghitung jumlah data baru sejak ID terakhir"""
    if last_processed_id is None:
        return 0

    cursor = conn.cursor()
    cursor.execute("SELECT COUNT(*) as cnt FROM sensor_data WHERE id > %s", (last_processed_id,))
    result = cursor.fetchone()
    cursor.close()
    return result[0] if result else 0

# ===================== FUNGSI ML (DATA PREP & TRAINING) =====================
def prepare_dataset(df: pd.DataFrame):
    """Membersihkan dan memfilter data untuk training"""
    if df.empty: return None

    required = ["turbidity", "distance", "water_level", "depletion_rate"]
    # Pastikan kolom ada
    if not all(col in df.columns for col in required): return None

    # Konversi ke numeric & drop NaN
    df = df[required].copy()
    for col in required:
        df[col] = pd.to_numeric(df[col], errors='coerce')

    df = df.dropna().drop_duplicates()

    # Filter Logic:
    # Kita hanya belajar dari kondisi di mana air berkurang (depletion_rate > 0)
    # Jika air nambah (ngisi) atau diam, itu bukan pola yang kita prediksi untuk 'habisnya kapan'
    df = df[
        (df["depletion_rate"] > 0) &
        (df["turbidity"] > 0) &
        (df["distance"] > 0) &
        (df["water_level"] >= 0)
    ]

    # Cek jumlah data minimal
    if len(df) < MIN_SAMPLES: return None

    # Winsorize (Buang nilai ekstrem rate yang gak masuk akal)
    upper = df["depletion_rate"].quantile(ROLLING_PERCENTILE)
    if not np.isnan(upper) and upper > 0:
        df["depletion_rate"] = df["depletion_rate"].clip(upper=upper)

    # X (Fitur), y (Target)
    X = df[["turbidity", "distance", "water_level"]]
    y = df["depletion_rate"]

    return X, y

def train_lightgbm_model(X, y):
    """Melatih model LightGBM"""
    X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42)

    # Scaling
    scaler = StandardScaler()
    X_train_scaled = scaler.fit_transform(X_train)
    X_test_scaled = scaler.transform(X_test)

    start_time = time.time()

    # Create Dataset for LightGBM
    train_data = lgb.Dataset(X_train_scaled, label=y_train)
    valid_data = lgb.Dataset(X_test_scaled, label=y_test, reference=train_data)

    # Train
    model = lgb.train(
        LGBM_PARAMS,
        train_data,
        num_boost_round=200,
        valid_sets=[valid_data],
        callbacks=[lgb.early_stopping(stopping_rounds=20, verbose=False)]
    )

    training_time = time.time() - start_time

    # Evaluate
    y_pred = model.predict(X_test_scaled, num_iteration=model.best_iteration)
    mae = mean_absolute_error(y_test, y_pred)
    mse = mean_squared_error(y_test, y_pred)
    rmse = float(np.sqrt(mse))
    r2 = r2_score(y_test, y_pred)

    return model, scaler, mae, rmse, r2, len(y), training_time

def load_model():
    """Load model dari disk"""
    if os.path.exists(MODEL_PATH) and os.path.exists(SCALER_PATH):
        try:
            model = joblib.load(MODEL_PATH)
            scaler = joblib.load(SCALER_PATH)
            return model, scaler
        except Exception as e:
            print(f"⚠️ Gagal load model: {e}")
            return None, None
    return None, None

def save_model(model, scaler):
    """Simpan model ke disk"""
    try:
        joblib.dump(model, MODEL_PATH)
        joblib.dump(scaler, SCALER_PATH)
        return True
    except Exception as e:
        print(f"❌ Gagal simpan model: {e}")
        return False

def predict_hours(model, scaler, row):
    """Melakukan prediksi waktu"""
    # Siapkan fitur (sesuai urutan training)
    features = np.array([[
        float(row["turbidity"]),
        float(row["distance"]),
        float(row["water_level"])
    ]])

    # Scale & Predict
    features_scaled = scaler.transform(features)
    predicted_rate = float(model.predict(features_scaled, num_iteration=model.best_iteration)[0])

    # Validasi Rate
    if predicted_rate <= 0.0001:
        return None, predicted_rate # Rate negatif/nol artinya air tidak berkurang

    hours = float(row["water_level"]) / predicted_rate
    return hours, predicted_rate

# ===================== MAIN LOOP =====================
def main():
    print("="*60)
    print("🤖 ML Auto Service v3 (LightGBM) - Started")
    print(f"📂 DB Target: {DB_CONFIG['database']} @ {DB_CONFIG['host']}")
    print(f"💾 Model Path: {MODEL_PATH}")
    print("="*60)

    current_model, current_scaler = load_model()
    if current_model:
        print("✅ Model existing ditemukan dan dimuat.")
    else:
        print("ℹ️ Belum ada model. Menunggu data cukup untuk training pertama...")

    last_processed_id = None
    last_retrain_id = None

    while True:
        conn = None
        try:
            conn = get_db()
            if conn is None:
                print("❌ DB Connection Failed. Retrying in 5s...")
                time.sleep(5)
                continue

            # 1. Cek Data Terbaru
            latest = fetch_latest_sensor(conn)

            if not latest:
                print("💤 Belum ada data sensor...", end='\r')
                conn.close()
                time.sleep(CHECK_INTERVAL)
                continue

            # Skip jika data ID sama dengan yang terakhir diproses
            if last_processed_id is not None and latest["id"] == last_processed_id:
                conn.close()
                time.sleep(CHECK_INTERVAL)
                continue

            print(f"\n[{datetime.now().strftime('%H:%M:%S')}] 📥 Data ID {latest['id']} | Lvl {latest['water_level']}% | Rate {latest['depletion_rate']:.2f}")

            # 2. Logika Retraining (Apakah perlu training ulang?)
            # Hitung data baru sejak training terakhir
            new_data_count = count_new_data(conn, last_retrain_id)

            # Syarat training: Model belum ada ATAU data baru sudah menumpuk > Threshold
            should_train = (current_model is None) or (new_data_count >= RETRAIN_THRESHOLD)

            if should_train:
                df = fetch_training_data(conn)
                dataset = prepare_dataset(df)

                if dataset:
                    X, y = dataset
                    print(f"   🔄 Training Model... (Data latih: {len(y)} baris)")

                    new_model, new_scaler, mae, rmse, r2, samples, t_time = train_lightgbm_model(X, y)

                    # Update model yang sedang berjalan
                    current_model = new_model
                    current_scaler = new_scaler
                    last_retrain_id = latest["id"]

                    # Simpan ke file & DB
                    save_model(current_model, current_scaler)
                    save_model_evaluation(conn, mae, rmse, r2, samples, t_time)
                    print(f"   ✅ Model Updated! RMSE: {rmse:.4f} | R²: {r2:.4f}")
                else:
                    print(f"   ⚠️ Data valid belum cukup untuk training (< {MIN_SAMPLES} sampel)")

            # 3. Logika Prediksi (Kalau model sudah ada)
            if current_model and current_scaler:
                hours, rate = predict_hours(current_model, current_scaler, latest)

                if hours is not None:
                    save_prediction(conn, latest, hours, rate)
                    print(f"   🔮 Prediksi: Habis dalam {hours:.1f} jam (Rate: {rate:.2f}%/jam)")
                else:
                    print("   ℹ️ Air sedang diam/mengisi, tidak ada prediksi depletion.")

            # Update pointer
            last_processed_id = latest["id"]
            conn.close()
            time.sleep(CHECK_INTERVAL)

        except KeyboardInterrupt:
            print("\n🛑 Service Stopped by User")
            if conn: conn.close()
            break
        except Exception as e:
            print(f"❌ Error Unhandled: {e}")
            if conn: conn.close()
            time.sleep(CHECK_INTERVAL)

if __name__ == "__main__":
    main()
