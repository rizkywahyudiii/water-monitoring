import mysql.connector
import os
from dotenv import load_dotenv

# Load Env
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
ENV_PATH = os.path.join(BASE_DIR, '../.env')
load_dotenv(ENV_PATH)

DB_CONFIG = {
    "host": os.getenv('DB_HOST', '127.0.0.1'),
    "user": os.getenv('DB_USERNAME', 'root'),
    "password": os.getenv('DB_PASSWORD', ''),
    "database": os.getenv('DB_DATABASE', 'water_monitoring'),
    "port": int(os.getenv('DB_PORT', 3306)),
}

def test_insert():
    print(f"Testing koneksi ke: {DB_CONFIG['database']}...")
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        cursor = conn.cursor()

        # Data Dummy
        mae = 0.5
        rmse = 0.8
        r2 = 0.95
        samples = 100
        time_sec = 1.5

        query = """
            INSERT INTO model_evaluation
            (mae, rmse, r2_score, training_samples, training_time, created_at, updated_at)
            VALUES (%s, %s, %s, %s, %s, NOW(), NOW())
        """

        cursor.execute(query, (mae, rmse, r2, samples, time_sec))
        conn.commit()
        print("✅ BERHASIL! Cek tabel model_evaluation sekarang.")
        print(f"ID Data terakhir: {cursor.lastrowid}")

        cursor.close()
        conn.close()
    except Exception as e:
        print(f"❌ GAGAL: {e}")

if __name__ == "__main__":
    test_insert()
