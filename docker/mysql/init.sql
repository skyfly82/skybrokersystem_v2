CREATE DATABASE IF NOT EXISTS skybrokersystem;
CREATE USER IF NOT EXISTS 'skybroker'@'%' IDENTIFIED BY 'Krasnoludek1!';
GRANT ALL PRIVILEGES ON skybrokersystem.* TO 'skybroker'@'%';
FLUSH PRIVILEGES;