import os
import cv2
import numpy as np
import time
import threading
import requests

from flask import Flask, Response, jsonify
from flask_cors import CORS
from mtcnn import MTCNN
from keras_facenet import FaceNet
from sklearn.metrics.pairwise import cosine_similarity


ESP32_STREAM_URL = "http://172.31.230.108:81/stream"
DATASET_PATH = r"C:\xampp\htdocs\smart_intruder_system\face_detect\face_data"

SIMILARITY_THRESHOLD = 0.6

API_BASE_URL = "http://localhost/smart_intruder_system/smart_intruder_dashboard/"
API_URL = f"{API_BASE_URL}/insert_data.php"
HEARTBEAT_URL = f"{API_BASE_URL}/update_heartbeat.php"
STATUS_URL = f"{API_BASE_URL}/get_system_status.php"

SEND_INTERVAL = 1
HEARTBEAT_INTERVAL = 10
MODE_REFRESH_INTERVAL = 2
RECONNECT_DELAY = 2

PYTHON_DEVICE_NAME = "python_processor"
CAMERA_DEVICE_NAME = "esp32_cam"

PYTHON_STREAM_HOST = "0.0.0.0"
PYTHON_STREAM_PORT = 5000
JPEG_QUALITY = 80

app = Flask(__name__)
CORS(app)

frame_lock = threading.Lock()

state = {
    "output_frame": None,
    "stream_ok": False,
    "unknown_count": 0,
    "known_detected": False,
    "current_mode": "AT_HOME",
    "last_frame_ts": 0
}
if not os.path.exists(DATASET_PATH):
    print("Dataset path not found:", DATASET_PATH)
    exit()

def normalize_mode(mode_value):
    value = str(mode_value or "").strip().upper()

    if value in ["AT_HOME", "AT HOME", "HOME"]:
        return "AT_HOME"

    if value in ["NOT_AT_HOME", "NOT AT HOME", "AWAY", "NOTHOME"]:
        return "NOT_AT_HOME"

    return "AT_HOME"
def format_mode(mode_value):
    return "Not At Home" if normalize_mode(mode_value) == "NOT_AT_HOME" else "At Home"

def update_output_frame(frame):
    with frame_lock:
        state["output_frame"] = frame.copy()

def make_placeholder_frame(title, subtitle=""):
    frame = np.zeros((720, 1280, 3), dtype=np.uint8)
    frame[:] = (23, 31, 43)

    cv2.putText(
        frame,
        "Smart Intruder System",
        (60, 110),
        cv2.FONT_HERSHEY_SIMPLEX,
        1.6,
        (255, 255, 255),
        3
    )

    cv2.putText(
        frame,
        title,
        (60, 250),
        cv2.FONT_HERSHEY_SIMPLEX,
        1.1,
        (84, 160, 255),
        3
    )

    if subtitle:
        cv2.putText(
            frame,
            subtitle,
            (60, 320),
            cv2.FONT_HERSHEY_SIMPLEX,
            0.9,
            (210, 220, 235),
            2
        )

    cv2.rectangle(frame, (45, 45), (1235, 675), (70, 90, 120), 2)
    return frame


def send_heartbeat(device_name):
    try:
        response = requests.get(
            HEARTBEAT_URL,
            params={"device": device_name},
            timeout=3
        )
        print(f"Heartbeat [{device_name}] -> {response.text}")
    except Exception as e:
        print(f"Heartbeat Error [{device_name}]: {e}")
def get_current_mode_from_server(fallback_mode="AT_HOME"):
    try:
        response = requests.get(STATUS_URL, params={"t": int(time.time())}, timeout=3)
        data = response.json()
        current_mode = data.get("current_mode")
        latest = data.get("latest") or {}
        if not current_mode:
            current_mode = latest.get("mode")
        return normalize_mode(current_mode or fallback_mode)
    except Exception as e:
        print("Mode fetch error:", e)
        return normalize_mode(fallback_mode)
def send_detection_to_database(detection_value, mode_value):
    try:
        payload = {
            "mode": normalize_mode(mode_value),
            "detection": int(max(0, detection_value))
        }
        response = requests.get(API_URL, params=payload, timeout=3)

        print("Sent to DB ->", payload)
        print("Server Response:", response.text)

    except Exception as e:
        print("API Error:", e)
def open_stream(stream_url):
    print("Opening ESP32 stream...")
    cap_obj = cv2.VideoCapture(stream_url)

    if not cap_obj.isOpened():
        print("Failed to open ESP32 stream")
        return None

    try:
        cap_obj.set(cv2.CAP_PROP_BUFFERSIZE, 1)
    except Exception:
        pass

    print("Stream opened successfully!")
    return cap_obj
print("Loading models...")
detector = MTCNN()
embedder = FaceNet()

print("Loading database...")
database = {}

for person_name in os.listdir(DATASET_PATH):
    person_folder = os.path.join(DATASET_PATH, person_name)
    if not os.path.isdir(person_folder):
        continue
    embeddings = []
    for img_name in os.listdir(person_folder):
        img_path = os.path.join(person_folder, img_name)
        img = cv2.imread(img_path)
        if img is None:
            continue
        try:
            rgb = cv2.cvtColor(img, cv2.COLOR_BGR2RGB)
            faces = detector.detect_faces(rgb)
        except Exception as e:
            print(f"[{person_name}] Detection error in dataset image:", e)
            continue
        if len(faces) == 0:
            continue
        x, y, w, h = faces[0]["box"]
        x = max(0, x)
        y = max(0, y)
        x2 = min(rgb.shape[1], x + w)
        y2 = min(rgb.shape[0], y + h)

        if x2 <= x or y2 <= y:
            continue
        face = rgb[y:y2, x:x2]

        if face is None or face.size == 0:
            continue
        fh, fw = face.shape[:2]
        if fh < 50 or fw < 50:
            continue
        try:
            face = cv2.resize(face, (160, 160))
            face = np.expand_dims(face.astype("float32"), axis=0)
            embedding = embedder.embeddings(face)[0]
            embeddings.append(embedding)
        except Exception as e:
            print(f"[{person_name}] Embedding error:", e)
            continue
    if len(embeddings) > 0:
        database[person_name] = np.mean(embeddings, axis=0)
print("Database Ready:", list(database.keys()))
if len(database) == 0:
    print("Warning: No valid face embeddings found in dataset!")
    exit()
def recognize_face(face_embedding):
    best_match = "Unknown"
    highest_similarity = -1
    for name, db_embedding in database.items():
        similarity = cosine_similarity([face_embedding], [db_embedding])[0][0]
        if similarity > highest_similarity:
            highest_similarity = similarity
            best_match = name
    if highest_similarity < SIMILARITY_THRESHOLD:
        best_match = "Unknown"
    return best_match, highest_similarity
def generate_frames():
    while True:
        with frame_lock:
            frame = None if state["output_frame"] is None else state["output_frame"].copy()

        if frame is None:
            frame = make_placeholder_frame(
                "Waiting for first frame...",
                "Python processor is starting"
            )
        ok, buffer = cv2.imencode(
            ".jpg",
            frame,
            [int(cv2.IMWRITE_JPEG_QUALITY), JPEG_QUALITY]
        )
        if not ok:
            time.sleep(0.05)
            continue
        frame_bytes = buffer.tobytes()
        yield (
            b"--frame\r\n"
            b"Content-Type: image/jpeg\r\n\r\n" +
            frame_bytes +
            b"\r\n"
        )
        time.sleep(0.03)
@app.route("/")
def home():
    return "Smart Intruder Python video server is running"
@app.route("/health")
def health():
    return jsonify({
        "status": "ok",
        "stream_ok": bool(state["stream_ok"]),
        "unknown_count": int(state["unknown_count"]),
        "known_detected": bool(state["known_detected"]),
        "current_mode": state["current_mode"],
        "last_frame_ts": state["last_frame_ts"]
    })
@app.route("/video_feed")
def video_feed():
    return Response(
        generate_frames(),
        mimetype="multipart/x-mixed-replace; boundary=frame"
    )
def processing_loop():
    cap = None
    last_sent_time = 0
    last_python_heartbeat_time = 0
    last_camera_heartbeat_time = 0
    last_mode_refresh_time = 0
    current_mode = "AT_HOME"

    update_output_frame(
        make_placeholder_frame(
            "Python processor started",
            "Trying to connect to ESP32 camera..."
        )
    )
    while True:
        now = time.time()
        if (now - last_mode_refresh_time) >= MODE_REFRESH_INTERVAL:
            current_mode = get_current_mode_from_server(current_mode)
            state["current_mode"] = current_mode
            last_mode_refresh_time = now
        if (now - last_python_heartbeat_time) >= HEARTBEAT_INTERVAL:
            send_heartbeat(PYTHON_DEVICE_NAME)
            last_python_heartbeat_time = now
        if cap is None or not cap.isOpened():
            state["stream_ok"] = False
            update_output_frame(
                make_placeholder_frame(
                    "Camera stream unavailable",
                    "Trying to reconnect to ESP32 stream..."
                )
            )
            cap = open_stream(ESP32_STREAM_URL)
            if cap is None:
                time.sleep(RECONNECT_DELAY)
                continue
        ret, frame = cap.read()

        if not ret or frame is None:
            print("Frame not received... Reconnecting stream.")
            state["stream_ok"] = False
            update_output_frame(
                make_placeholder_frame(
                    "Frame not received",
                    "Reconnecting to ESP32 stream..."
                )
            )
            try:
                cap.release()
            except Exception:
                pass
            cap = None
            time.sleep(RECONNECT_DELAY)
            continue
        state["stream_ok"] = True
        state["last_frame_ts"] = now
        if (now - last_camera_heartbeat_time) >= HEARTBEAT_INTERVAL:
            send_heartbeat(CAMERA_DEVICE_NAME)
            last_camera_heartbeat_time = now
        unknown_count = 0
        known_detected = False
        try:
            rgb = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
            faces = detector.detect_faces(rgb)
        except Exception as e:
            print("Face detection error:", e)
            update_output_frame(
                make_placeholder_frame(
                    "Face detection error",
                    "Processor is still running..."
                )
            )
            continue

        for face_data in faces:
            x, y, w, h = face_data["box"]

            x = max(0, x)
            y = max(0, y)
            x2 = min(frame.shape[1], x + w)
            y2 = min(frame.shape[0], y + h)

            if x2 <= x or y2 <= y:
                continue

            face = rgb[y:y2, x:x2]

            if face is None or face.size == 0:
                continue

            fh, fw = face.shape[:2]
            if fh < 50 or fw < 50:
                continue

            try:
                face = cv2.resize(face, (160, 160))
                face = np.expand_dims(face.astype("float32"), axis=0)
                embedding = embedder.embeddings(face)[0]
            except Exception as e:
                print("Embedding error:", e)
                continue

            best_match, highest_similarity = recognize_face(embedding)

            if best_match == "Unknown":
                unknown_count += 1
            else:
                known_detected = True

            color = (0, 255, 0) if best_match != "Unknown" else (0, 0, 255)
            display_similarity = max(highest_similarity, 0)

            cv2.rectangle(frame, (x, y), (x2, y2), color, 2)
            cv2.putText(
                frame,
                f"{best_match} ({display_similarity:.2f})",
                (x, max(30, y - 10)),
                cv2.FONT_HERSHEY_SIMPLEX,
                0.72,
                color,
                2
            )

        cv2.putText(
            frame,
            f"Unknown Count: {unknown_count}",
            (20, 40),
            cv2.FONT_HERSHEY_SIMPLEX,
            1.0,
            (0, 0, 255),
            2
        )

        cv2.putText(
            frame,
            f"Mode: {format_mode(current_mode)}",
            (20, 80),
            cv2.FONT_HERSHEY_SIMPLEX,
            0.9,
            (255, 220, 80),
            2
        )

        state["unknown_count"] = unknown_count
        state["known_detected"] = known_detected
        state["current_mode"] = current_mode
        if (now - last_sent_time) >= SEND_INTERVAL:
            detection_value = unknown_count
            send_detection_to_database(detection_value, current_mode)
            last_sent_time = now

        update_output_frame(frame)
if __name__ == "__main__":
    worker = threading.Thread(target=processing_loop, daemon=True)
    worker.start()

    print(f"Python video server running at http://127.0.0.1:{PYTHON_STREAM_PORT}")
    app.run(
        host=PYTHON_STREAM_HOST,
        port=PYTHON_STREAM_PORT,
        debug=False,
        threaded=True,
        use_reloader=False
    )