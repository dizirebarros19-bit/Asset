from flask import Flask, request, jsonify
import mysql.connector
import time
from flask_cors import CORS
from datetime import datetime, date

app = Flask(__name__)
CORS(app)

# -----------------------------
# GLOBAL STORAGE
# -----------------------------
ANOMALY_QUEUE = []
LAST_ANOMALY_TIME = 0
COOLDOWN = 60 

# -----------------------------
# DATABASE CONNECTION
# -----------------------------
def get_db_connection():
    return mysql.connector.connect(
        host="localhost",
        user="root",
        password="",
        database="inventory_system"
    )

# -----------------------------
# HELPER: ANALYZE ASSET (REFINED NATURAL LANGUAGE)
# -----------------------------
def analyze_asset(asset_id, d_count, age_days, components_list, days_since_report):
    comp_str = ", ".join(components_list)
    
    # 1. Determine Severity
    if d_count >= 8:
        status = "Catastrophic"
        base_judgment = f"This asset is likely a total loss with {d_count} failed components ({comp_str})."
    elif d_count >= 4:
        status = "Critical"
        base_judgment = f"System integrity is compromised due to {d_count} major issues."
    else:
        status = "Warning"
        base_judgment = f"This unit is showing signs of wear with {d_count} reported issues."

    # 2. Contextual Age & Duration Strings
    age_text = f"in service for {age_days} days" if age_days > 0 else "brand new (deployed today)"
    
    # 3. Intelligent "Thoughts" Generation
    # Case A: Brand New Asset with Issues (Likely DOA/Defect)
    if age_days <= 14 and d_count >= 1:
        status = "Critical"
        thoughts = f"Immediate attention required: This unit is {age_text} but already reports {d_count} issue(s). This may be a manufacturer defect."
    
    # Case B: Stagnant Repair (Over 90 days)
    elif days_since_report >= 90:
        status = "Critical"
        thoughts = f"ALERT: Repair is stagnant. {base_judgment} It has been damaged for {days_since_report} days despite only being {age_text}."
    
    # Case C: Recent Issue (Damaged for 0-1 days)
    elif days_since_report <= 1:
        thoughts = f"{base_judgment} This is a new report for an asset that has been {age_text}. Monitor for further degradation."
    
    # Case D: Persistent but not stagnant
    elif days_since_report > 30:
        thoughts = f"{base_judgment} Notably, it has remained damaged for {days_since_report} days while being {age_text}."
    
    # Case E: Standard
    else:
        thoughts = f"{base_judgment} The asset has been {age_text}."

    return thoughts, status

# -----------------------------
# HELPER: CHECK IF ASSET EXISTS
# -----------------------------
def asset_exists(asset_id):
    conn = None
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute("SELECT 1 FROM assets WHERE asset_id = %s", (asset_id,))
        exists = cursor.fetchone() is not None
        return exists
    except Exception:
        return False
    finally:
        if conn: conn.close()

# -----------------------------
# REFRESH ANOMALY QUEUE
# -----------------------------
def refresh_anomaly_queue():
    global ANOMALY_QUEUE
    conn = None
    try:
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        
        cursor.execute("SELECT id AS db_id, asset_id, date_acquired, item_condition FROM assets")
        rows = cursor.fetchall()
        
        temp_queue = []
        now = datetime.now()

        for row in rows:
            raw_acquired = row['date_acquired']
            age_days = 0
            
            if raw_acquired:
                if isinstance(raw_acquired, date) and not isinstance(raw_acquired, datetime):
                    raw_acquired = datetime.combine(raw_acquired, datetime.min.time())
                if isinstance(raw_acquired, datetime):
                    age_days = (now - raw_acquired).days

            cursor.execute("""
                SELECT component, reported_at FROM reported_items 
                WHERE asset_id = %s AND status = 'Damaged'
            """, (row['asset_id'],))
            comp_rows = cursor.fetchall()

            all_components = []
            days_since_report = 0
            
            if comp_rows:
                report_dates = []
                for r in comp_rows:
                    rep_at = r['reported_at']
                    if rep_at:
                        if isinstance(rep_at, date) and not isinstance(rep_at, datetime):
                            rep_at = datetime.combine(rep_at, datetime.min.time())
                        report_dates.append(rep_at)
                
                if report_dates:
                    oldest_report = min(report_dates)
                    days_since_report = (now - oldest_report).days

                for r in comp_rows:
                    comps = [c.strip() for c in r['component'].split(',') if c.strip()]
                    all_components.extend(comps)
            
            d_count = len(all_components)

            if d_count > 0:
                thoughts, severity_status = analyze_asset(
                    row['asset_id'], d_count, age_days, all_components, days_since_report
                )
                
                row.update({
                    'thoughts': thoughts,
                    'd_count': d_count,
                    'severity': severity_status,
                    'age_days': age_days,
                    'days_since_report': days_since_report,
                    'damaged_components': all_components
                })
                temp_queue.append(row)

        ANOMALY_QUEUE = temp_queue

    except Exception as e:
        print(f"Error: {e}")
    finally:
        if conn: conn.close()

# -----------------------------
# ENDPOINTS
# -----------------------------
@app.route('/scan', methods=['GET'])
def scan_assets():
    global LAST_ANOMALY_TIME, ANOMALY_QUEUE
    type_ = request.args.get('type', 'greeting')
    response = {"messages": [], "critical": False, "anomaly": None}
    current_time = int(time.time())

    if type_ == 'standby':
        if not ANOMALY_QUEUE:
            refresh_anomaly_queue()

        ANOMALY_QUEUE = [a for a in ANOMALY_QUEUE if asset_exists(a['asset_id'])]

        if ANOMALY_QUEUE and (current_time - LAST_ANOMALY_TIME >= COOLDOWN):
            anomaly = ANOMALY_QUEUE.pop(0)
            LAST_ANOMALY_TIME = current_time

            response['critical'] = True
            response['anomaly'] = {
                "id": anomaly['db_id'],
                "asset_id": anomaly['asset_id'],
                "damage_count": anomaly['d_count'],
                "severity": anomaly['severity'],
                "days_stagnant": anomaly['days_since_report'],
                "summary": f"Velyn Alert: {anomaly['asset_id']} needs review.",
                "thoughts": anomaly['thoughts']
            }
    else:
        response['messages'].append("Velyn AI is online. Asset monitoring active.")

    return jsonify(response)

@app.route('/all_anomalies', methods=['GET'])
def all_anomalies():
    refresh_anomaly_queue()
    result = []
    for a in ANOMALY_QUEUE:
        if asset_exists(a['asset_id']):
            d_acquired = a.get('date_acquired')
            formatted_date = d_acquired.strftime("%Y-%m-%d") if isinstance(d_acquired, (date, datetime)) else str(d_acquired)
            
            result.append({
                "db_id": a['db_id'],
                "asset_id": a['asset_id'],
                "damage_count": a.get('d_count', 0),
                "severity": a.get('severity', "Warning"),
                "thoughts": a.get('thoughts', ""),
                "days_stagnant": a.get('days_since_report', 0),
                "age_days": a.get('age_days', 0),
                "status": a.get('item_condition', "Unknown"),
                "date_acquired": formatted_date,
                "components": a.get('damaged_components', [])
            })

    return jsonify({"anomalies": result, "count": len(result)})

if __name__ == '__main__':
    refresh_anomaly_queue()
    app.run(port=5000, debug=True)