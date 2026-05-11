# Smart Intruder System

![Python](https://img.shields.io/badge/Python-3776AB?style=for-the-badge&logo=python&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-005C84?style=for-the-badge&logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)
![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=for-the-badge&logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=for-the-badge&logo=css3&logoColor=white)
![Status](https://img.shields.io/badge/Status-Completed-success?style=for-the-badge)

An IoT-based intruder detection and monitoring system built with **ESP32-CAM**, **Python face recognition**, **PHP REST-style APIs**, **MySQL**, and a responsive web dashboard.

---

## Overview

**Smart Intruder System** is a completed IoT and computer-vision security project designed to detect unknown individuals from a live ESP32-CAM video stream.

The system captures a live camera feed, processes it using Python-based face recognition, compares detected faces with a known-face dataset, counts unknown faces as intruders, stores detection records in MySQL, and displays real-time monitoring data through a browser-based dashboard.

The dashboard provides system status, live camera monitoring, mode selection, intruder count, alert status, and historical detection logs.

---

## Key Features

- Real-time video monitoring using ESP32-CAM
- Face detection using MTCNN
- Face embedding generation using FaceNet
- Known and unknown face recognition
- Cosine similarity-based face matching
- Intruder count calculation
- At Home / Not At Home security mode selection
- Live processed camera feed
- MySQL-based detection history
- PHP API backend for data exchange
- Device heartbeat tracking
- Browser notification support
- Responsive HTML, CSS, and JavaScript dashboard

---

## System Architecture

```text
ESP32-CAM
   │
   │ Live video stream
   ▼
Python Processor
OpenCV + MTCNN + FaceNet
   │
   ├── Recognizes known faces
   ├── Counts unknown faces as intruders
   ├── Sends detection data to PHP API
   └── Sends heartbeat status
        │
        ▼
PHP API Backend
        │
        ▼
MySQL Database
        │
        ▼
Web Dashboard
HTML + CSS + JavaScript
```

---

## How It Works

1. The ESP32-CAM provides a live video stream.
2. Python reads the stream using OpenCV.
3. MTCNN detects faces from each video frame.
4. FaceNet converts detected faces into numerical embeddings.
5. The system compares live embeddings with stored known-face embeddings.
6. If the similarity score is above the threshold, the face is marked as known.
7. If the similarity score is below the threshold, the face is marked as unknown.
8. Unknown faces are counted as intruders.
9. Detection data is sent to the PHP API and stored in MySQL.
10. The dashboard fetches live status, detection count, mode, and history from the backend.

---

## Technology Stack

| Layer | Technologies |
|---|---|
| Hardware | ESP32-CAM, ESP32 |
| Computer Vision | Python, OpenCV, MTCNN, Keras FaceNet |
| Data Processing | NumPy, Scikit-learn, TensorFlow |
| Backend | PHP |
| Database | MySQL |
| Frontend | HTML, CSS, JavaScript |
| Local Server | XAMPP |

---

## Project Structure

Recommended project organization for a local XAMPP setup:

```text
C:\xampp\htdocs\smart_intruder_system\face_detect
│
├── .venv
├── detect.py
├── insert_counting.py
├── main.py
└── face_data
    ├── Person_1
    │   ├── 1.jpg
    │   └── 2.jpg
    └── Person_2
        ├── 1.jpg
        └── 2.jpg
```

```text
C:\xampp\htdocs\smart_intruder_system\php
│
├── create_tables.sql
├── db_connect.php
├── fetch_latest.php
├── get_history.php
├── get_mode.php
├── get_system_status.php
├── insert_data.php
├── set_mode.php
└── update_heartbeat.php
```

```text
C:\xampp\htdocs\smart_intruder_system\smart_intruder_dashboard
│
├── index.html
├── script.js
└── style.css
```

---

## Prerequisites

Before running the project, make sure the following are installed and configured:

- Python 3.x
- XAMPP
- Apache
- MySQL
- Git
- ESP32-CAM module
- Web browser
- Known-face image dataset
- Working ESP32-CAM stream URL

---

## Installation

### 1. Clone the Repository

```bash
git clone https://github.com/mehedi77k/Intruder_System.git
```

### 2. Set Up the Python Environment

Create and activate a virtual environment:

```bash
cd C:\xampp\htdocs\smart_intruder_system\face_detect
python -m venv .venv
.venv\Scripts\activate
```

Install the required packages:

```bash
pip install opencv-python numpy mtcnn keras-facenet scikit-learn tensorflow requests flask
```

### 3. Set Up the PHP API

Copy the backend API files into:

```text
C:\xampp\htdocs\smart_intruder_system\php
```

Start **Apache** and **MySQL** from the XAMPP Control Panel.

### 4. Set Up the Dashboard

Copy the dashboard files into:

```text
C:\xampp\htdocs\smart_intruder_system\smart_intruder_dashboard
```

The dashboard will be available at:

```text
http://localhost/smart_intruder_system/smart_intruder_dashboard
```

---

## Database Setup

Open phpMyAdmin:

```text
http://localhost/phpmyadmin
```

Create the database:

```sql
CREATE DATABASE smart_intruder;
```

Select the database:

```sql
USE smart_intruder;
```

Create the required tables:

```sql
CREATE TABLE intruder_log (
    serial_no INT AUTO_INCREMENT PRIMARY KEY,
    time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    mode VARCHAR(50) DEFAULT 'AT_HOME',
    detection INT DEFAULT 0
);

CREATE TABLE system_state (
    id INT PRIMARY KEY,
    mode VARCHAR(50) DEFAULT 'AT_HOME',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE device_heartbeat (
    device_name VARCHAR(100) PRIMARY KEY,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO system_state (id, mode)
VALUES (1, 'AT_HOME');
```

---

## Configuration

### Database Connection

Update `db_connect.php` with your local MySQL credentials:

```php
<?php
$host = "localhost";
$user = "root";
$password = "";
$database = "smart_intruder";

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    header("Content-Type: application/json");
    http_response_code(500);
    die(json_encode([
        "status" => "error",
        "message" => "Connection failed: " . $conn->connect_error
    ]));
}

$conn->set_charset("utf8mb4");
?>
```

### Python Configuration

Update the stream URL, dataset path, similarity threshold, and API URLs in the Python file you are running.

Example:

```python
ESP32_STREAM_URL = "http://YOUR_ESP32_CAM_IP:81/stream"
DATASET_PATH = r"C:\face_project\face_data"
SIMILARITY_THRESHOLD = 0.6

API_INSERT_URL = "http://localhost/C:/xampp/htdocs/smart_intruder_system/php/insert_data.php"
API_HEARTBEAT_URL = "http://localhost/C:/xampp/htdocs/smart_intruder_system/php/update_heartbeat.php?device=python_processor"
```

### Dashboard API Path

In `script.js`, confirm that the API base path matches your backend folder:

```javascript
const API_BASE = "http://localhost/C:/xampp/htdocs/smart_intruder_system/php";
```

---

## Face Dataset Format

Place known-face images inside separate folders named after each person:

```text
face_data
│
├── Mehedi
│   ├── 1.jpg
│   ├── 2.jpg
│   └── 3.jpg
│
├── Person_2
│   ├── 1.jpg
│   └── 2.jpg
│
└── Person_3
    ├── 1.jpg
    └── 2.jpg
```

Each folder name is used as the identity label for that person.

For better recognition accuracy:

- Use clear front-facing images.
- Use multiple images per person.
- Avoid blurry or low-light images.
- Keep the face visible and unobstructed.

---

## Running the Project

### 1. Start XAMPP

Start both services:

- Apache
- MySQL

### 2. Start the Python Processor

```bash
cd C:/xampp/htdocs/smart_intruder_system/face_detect
.venv\Scripts\activate
python insert_counting.py
```

You may also run standalone detection scripts if needed:

```bash
python detect.py
```

or

```bash
python main.py
```

### 3. Open the Dashboard

Open the following URL in a browser:

```text
http://localhost/C:/xampp/htdocs/smart_intruder_system/smart_intruder_dashboard/
```

---

## API Endpoints

| Endpoint | Method | Description |
|---|---:|---|
| `/fetch_latest.php` | GET | Returns the latest detection record |
| `/get_history.php?page=1&limit=10` | GET | Returns paginated detection history |
| `/get_mode.php` | GET | Returns the current system mode |
| `/set_mode.php` | POST | Updates the system mode |
| `/get_system_status.php` | GET | Returns system online/offline status |
| `/insert_data.php` | POST | Stores detection data |
| `/update_heartbeat.php?device=python_processor` | GET | Updates device heartbeat status |

Example mode update:

```text
mode=AT_HOME
```

or

```text
mode=NOT_AT_HOME
```

Example detection insert:

```text
detection=1
mode=NOT_AT_HOME
```

---

## Detection Logic

The system uses cosine similarity to compare live face embeddings with known-face embeddings.

```text
If similarity >= threshold:
    Face is recognized as a known person

If similarity < threshold:
    Face is classified as unknown
```

Default threshold:

```text
0.6
```

A lower threshold may recognize faces more easily but can increase false positives.  
A higher threshold may be stricter but can increase false negatives.

---

## Dashboard Modules

The dashboard includes:

- System online/offline status
- Live camera feed
- Current security mode
- Intruder count
- Alert / safe status
- Detection history table
- Auto-refreshing live data
- Browser notification support

---

## Testing Checklist

Use this checklist before demonstration or deployment:

- [ ] ESP32-CAM is powered on
- [ ] ESP32-CAM stream opens in a browser
- [ ] Python virtual environment is activated
- [ ] Python dependencies are installed
- [ ] Dataset path is correct
- [ ] Known-face folders are correctly named
- [ ] Python detects faces from the stream
- [ ] Known faces are recognized correctly
- [ ] Unknown faces are counted as intruders
- [ ] MySQL database is created
- [ ] Required database tables exist
- [ ] PHP API files are inside the correct XAMPP folder
- [ ] `db_connect.php` uses the correct database credentials
- [ ] Detection data is inserted into `intruder_log`
- [ ] Dashboard loads successfully
- [ ] Dashboard data refreshes automatically
- [ ] Mode selection works
- [ ] System status updates correctly
- [ ] Browser notifications work as expected

---

## Troubleshooting

### ESP32-CAM Stream Does Not Open

Check that:

- The ESP32-CAM is powered on.
- The ESP32-CAM is connected to Wi-Fi.
- The stream URL is correct.
- The computer and ESP32-CAM are on the same network.
- The stream opens directly in a browser.

Example:

```python
ESP32_STREAM_URL = "http://192.168.0.105:81/stream"
```

### Face Is Not Detected

Try the following:

- Improve lighting.
- Use clearer training images.
- Add more images per known person.
- Confirm the dataset folder path.
- Make sure the face is visible and front-facing.
- Adjust the similarity threshold carefully.

### PHP API Is Not Working

Check that:

- Apache is running.
- MySQL is running.
- API files are inside `C:\xampp\htdocs\smart_intruder_system\php`.
- `db_connect.php` has the correct database credentials.
- The API URL opens in a browser.

### Dashboard Is Not Loading Data

Check that:

- `script.js` points to the correct API base URL.
- Apache is running.
- MySQL is running.
- The PHP API endpoints return valid JSON.
- The database contains detection records.

### Dashboard Shows Offline

Check that:

- The Python processor is running.
- Heartbeat API requests are being sent.
- ESP32-CAM is online.
- `get_system_status.php` returns valid device status data.

---

## Known Limitations

- Recognition accuracy depends on image quality and lighting conditions.
- ESP32-CAM streaming must remain stable for reliable monitoring.
- The system is designed primarily for local-network use.
- Database and API configuration must be updated manually during setup.
- Face recognition performance may vary depending on camera angle, lighting, and dataset quality.

---

## Privacy and Ethical Use

This project involves camera monitoring and face recognition. Use it responsibly and only in authorized environments.

Recommended practices:

- Inform people when monitoring is active.
- Use the system only in locations where you have permission.
- Store face images and detection records securely.
- Do not use the system for unauthorized surveillance.
- Follow applicable local privacy and data protection rules.

---

## Possible Enhancements

Although the core project is complete, the following improvements can be added in future versions:

- Admin login system
- Email or SMS alerts
- Telegram bot notifications
- Unknown-face image capture
- Buzzer or siren integration
- Mobile dashboard
- Cloud database support
- Detection history export as CSV or PDF
- Analytics dashboard with charts
- Role-based access control

---

## Developer

**Mehedi Hasan**

- GitHub: [@mehedi77k](https://github.com/mehedi77k)
- Repository: [Intruder_System](https://github.com/mehedi77k/Smart_Intruder_System)

---

## Project Status

```text
Status: Completed
Project Type: IoT Security and Monitoring System
Main Language: Python
Backend: PHP + MySQL
Frontend: HTML + CSS + JavaScript
Camera Module: ESP32-CAM
```

---

## Support

For issues, suggestions, or improvements, open an issue in the GitHub repository:

```text
https://github.com/mehedi77k/Smart_Intruder_System/issues
```
