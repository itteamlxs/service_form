CREATE DATABASE IF NOT EXISTS reservas_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE reservas_db;

-- 1. Tabla de clientes (no requiere registro previo, solo captura)
CREATE TABLE clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    edad INT NOT NULL CHECK (edad >= 10 AND edad <= 100),
    email VARCHAR(100) NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Tabla de categorías de servicio (servicio técnico, creatividad, etc.)
CREATE TABLE categorias_servicio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE
);

-- 3. Tabla de subservicios específicos (relacionados a la categoría)
CREATE TABLE subservicios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categoria_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    modalidad ENUM('presencial', 'en línea', 'ambos') NOT NULL DEFAULT 'ambos',
    FOREIGN KEY (categoria_id) REFERENCES categorias_servicio(id) ON DELETE CASCADE
);

-- 4. Tabla de precios por subservicio
CREATE TABLE precios_servicio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subservicio_id INT NOT NULL,
    tipo_cobro ENUM('hora', 'proyecto') NOT NULL,
    precio DECIMAL(10,2) NOT NULL CHECK (precio >= 0),
    activo TINYINT(1) DEFAULT 1,
    FOREIGN KEY (subservicio_id) REFERENCES subservicios(id) ON DELETE CASCADE
);

-- 5. Tabla de reservas
CREATE TABLE reservas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    subservicio_id INT NOT NULL,
    modalidad ENUM('presencial', 'en línea') NOT NULL,
    fecha DATE NOT NULL,
    hora TIME NOT NULL,
    estado ENUM('pendiente', 'confirmado', 'cancelado', 'resuelto') DEFAULT 'pendiente',
    observaciones TEXT,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    FOREIGN KEY (subservicio_id) REFERENCES subservicios(id) ON DELETE CASCADE
);

-- 6. Tabla de administradores (si hay panel de control)
CREATE TABLE administradores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 7. Tabla de logs / auditoría básica de acciones de admin
CREATE TABLE log_admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    accion TEXT NOT NULL,
    modulo_afectado VARCHAR(100),
    ip_origen VARCHAR(45),
    user_agent TEXT,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES administradores(id) ON DELETE CASCADE
);
