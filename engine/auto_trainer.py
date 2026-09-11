"""
auto_trainer.py
----------------
Jo targets ne email open kiya ya link click kiya lekin training
complete nahi ki — unhe automatically lms_training me enroll karta hai.
Cron se daily chalao.
"""

import mysql.connector
from mysql.connector import Error

DB_CONFIG = {
    "host":     "localhost",
    "user":     "cybershield_user",
    "password": "kali",
    "database": "cybershield"
}

def get_connection():
    try:
        return mysql.connector.connect(**DB_CONFIG)
    except Error as e:
        print(f"[ERROR] {e}")
        return None

def auto_enroll_training():
    conn = get_connection()
    if conn is None:
        return

    cursor = conn.cursor()

    # Jo targets ne click kiya ya credential submit kiya
    # lekin unka lms_training record nahi hai
    cursor.execute("""
        SELECT DISTINCT sl.target_id, sl.campaign_id
        FROM simulation_logs sl
        WHERE (sl.is_clicked = 1 OR sl.is_credential_submitted = 1)
        AND NOT EXISTS (
            SELECT 1 FROM lms_training lt
            WHERE lt.target_id = sl.target_id
            AND lt.campaign_id = sl.campaign_id
        )
    """)
    rows = cursor.fetchall()

    enrolled = 0
    for target_id, campaign_id in rows:
        cursor.execute(
            "INSERT INTO lms_training (target_id, campaign_id, status) VALUES (%s, %s, 'not_started')",
            (target_id, campaign_id)
        )
        enrolled += 1
        print(f"[ENROLLED] target_id={target_id} campaign_id={campaign_id}")

    conn.commit()
    cursor.close()
    conn.close()
    print(f"[DONE] {enrolled} targets auto-enrolled in training.")

if __name__ == "__main__":
    auto_enroll_training()

