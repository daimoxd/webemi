-- ============================================================
-- Base de datos: generadorPerfilesDB
-- Proyecto: Generador de Perfiles UI Reactiva
-- ============================================================

-- 1. Crear la base de datos y seleccionarla
CREATE DATABASE IF NOT EXISTS generadorPerfilesDB
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE generadorPerfilesDB;

-- 2. Crear la tabla de usuarios
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_completo VARCHAR(150) NOT NULL,
    fecha_nacimiento DATE NOT NULL,
    nivel_educativo VARCHAR(50) NOT NULL,
    telefono VARCHAR(15) NOT NULL,
    horas_programando DECIMAL(4,2) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    nickname VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
