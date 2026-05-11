CREATE DATABASE IF NOT EXISTS smart_intruder;
USE smart_intruder;

CREATE TABLE IF NOT EXISTS intruder_log (
    serial_no INT NOT NULL AUTO_INCREMENT,
    time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    mode VARCHAR(30) NOT NULL DEFAULT 'AT_HOME',
    detection INT NOT NULL DEFAULT 0,
    PRIMARY KEY (serial_no)
);

CREATE TABLE IF NOT EXISTS system_state (
    id INT NOT NULL,
    mode VARCHAR(30) NOT NULL DEFAULT 'AT_HOME',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS device_heartbeat (
    device_name VARCHAR(50) NOT NULL,
    last_seen TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (device_name)
);

INSERT INTO system_state (id, mode)
VALUES (1, 'AT_HOME')
ON DUPLICATE KEY UPDATE mode = mode;

INSERT INTO device_heartbeat (device_name, last_seen)
VALUES
('handheld_esp32', NULL),
('python_processor', NULL),
('esp32_cam', NULL)
ON DUPLICATE KEY UPDATE device_name = VALUES(device_name);
