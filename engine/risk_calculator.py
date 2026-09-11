"""
risk_calculator.py
--------------------
Har target ke simulation_logs se clicks, credential submissions,
aur reports count karta hai, Risk Score formula lagata hai, aur
targets.current_risk_score column update karta hai.

Risk Score = (2 x clicks) + (5 x credential_submitted) - (3 x reported)
"""

import mysql.connector
import pandas as pd
from mysql.connector import Error

DB_CONFIG = {
    "host": "localhost",
    "user": "cybershield_user",
    "password": "kali",   # apna password
    "database": "cybershield"
}


def get_connection():
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        return conn
    except Error as e:
        print(f"[ERROR] Connection fail hua: {e}")
        return None


def calculate_and_update_risk_scores():
    conn = get_connection()
    if conn is None:
        return

    # Har target ke liye clicks, credential submissions, reports sum karo
    query = """
        SELECT
            target_id,
            SUM(is_clicked) AS total_clicks,
            SUM(is_credential_submitted) AS total_credentials,
            SUM(is_report_phishing) AS total_reported
        FROM simulation_logs
        GROUP BY target_id
    """

    df = pd.read_sql(query, conn)

    if df.empty:
        print("[WARN] simulation_logs me abhi koi data nahi hai.")
        conn.close()
        return

    # Risk score formula lagao
    df["risk_score"] = (
        (2 * df["total_clicks"])
        + (5 * df["total_credentials"])
        - (3 * df["total_reported"])
    )

    # Negative score ko 0 pe clamp karo (score kabhi negative nahi hona chahiye)
    df["risk_score"] = df["risk_score"].clip(lower=0)

    cursor = conn.cursor()
    for _, row in df.iterrows():
        cursor.execute(
            "UPDATE targets SET current_risk_score = %s WHERE target_id = %s",
            (int(row["risk_score"]), int(row["target_id"]))
        )
        print(f"[UPDATED] target_id {int(row['target_id'])} -> risk_score {int(row['risk_score'])}")

    conn.commit()
    cursor.close()
    conn.close()
    print("[DONE] Saare risk scores update ho gaye.")


if __name__ == "__main__":
    calculate_and_update_risk_scores()
