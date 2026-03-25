-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 25-03-2026 a las 00:32:20
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `inventariomeca`
--

DELIMITER $$
--
-- Procedimientos
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_actualizar_activo` (IN `p_id_activo` INT, IN `p_num_marbete` VARCHAR(25), IN `p_activo_desc` VARCHAR(150), IN `p_estado` VARCHAR(15), IN `p_id_lab` INT, IN `p_tipo_activo` VARCHAR(15), IN `p_cantidad` INT)   BEGIN
    DECLARE v_existe_activo INT DEFAULT 0;
    DECLARE v_existe_lab INT DEFAULT 0;
    DECLARE v_marbete_duplicado INT DEFAULT 0;
    DECLARE v_cantidad_final INT DEFAULT 1;

    SELECT COUNT(*) INTO v_existe_activo
    FROM activos
    WHERE ID_Activo = p_id_activo;

    IF v_existe_activo = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El activo que intentas editar no existe.';
    END IF;

    SELECT COUNT(*) INTO v_existe_lab
    FROM ubicaciones
    WHERE ID_Lab = p_id_lab;

    IF v_existe_lab = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La ubicación seleccionada no existe.';
    END IF;

    SELECT COUNT(*) INTO v_marbete_duplicado
    FROM activos
    WHERE Num_Marbete = p_num_marbete
      AND ID_Activo <> p_id_activo;

    IF v_marbete_duplicado > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Ya existe otro activo con ese número de marbete.';
    END IF;

    IF p_tipo_activo = 'Consumible' THEN
        SET v_cantidad_final = IFNULL(p_cantidad, 1);
        IF v_cantidad_final < 1 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'La cantidad para un consumible debe ser mayor a 0.';
        END IF;
    ELSE
        SET v_cantidad_final = 1;
    END IF;

    UPDATE activos
    SET
        Num_Marbete = p_num_marbete,
        Activo_Desc = p_activo_desc,
        Estado = p_estado,
        ID_Lab = p_id_lab,
        Tipo_Activo = p_tipo_activo,
        Cantidad = v_cantidad_final
    WHERE ID_Activo = p_id_activo;

    SELECT 'Activo actualizado correctamente.' AS mensaje;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_actualizar_alumno` (IN `p_matricula_original` INT, IN `p_matricula_nueva` INT, IN `p_nombre` VARCHAR(150), IN `p_carrera` VARCHAR(50), IN `p_grupo` VARCHAR(20), IN `p_contacto` VARCHAR(15))   BEGIN
    UPDATE alumnos
    SET
        Matricula_Alumno = p_matricula_nueva,
        Nombre_Alumno = TRIM(p_nombre),
        Carrera = TRIM(p_carrera),
        Grupo = TRIM(p_grupo),
        Contacto_Alumno = TRIM(p_contacto)
    WHERE Matricula_Alumno = p_matricula_original;

    IF ROW_COUNT() = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No se encontró el alumno a actualizar.';
    END IF;

    SELECT 'Alumno actualizado correctamente.' AS mensaje;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_actualizar_docente` (IN `p_matricula_original` INT, IN `p_matricula_nueva` INT, IN `p_nombre` VARCHAR(150), IN `p_contacto` VARCHAR(15))   BEGIN
    UPDATE docentes
    SET
        Matricula_Docente = p_matricula_nueva,
        Nombre_Docente = TRIM(p_nombre),
        Contacto_Docente = TRIM(p_contacto)
    WHERE Matricula_Docente = p_matricula_original;

    IF ROW_COUNT() = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No se encontró el docente a actualizar.';
    END IF;

    SELECT 'Docente actualizado correctamente.' AS mensaje;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_actualizar_estado_activo` (IN `p_id_activo` INT, IN `p_nuevo_estado` VARCHAR(15))   BEGIN
    DECLARE v_existe_activo INT DEFAULT 0;
    DECLARE v_estado_actual VARCHAR(15);
    DECLARE v_prestamos_activos INT DEFAULT 0;

    SELECT COUNT(*), Estado
      INTO v_existe_activo, v_estado_actual
    FROM activos
    WHERE ID_Activo = p_id_activo;

    IF v_existe_activo = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El activo no existe.';
    END IF;

    IF p_nuevo_estado NOT IN ('Activo', 'Prestado', 'Mantenimiento', 'Baja') THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El estado seleccionado no es válido.';
    END IF;

    SELECT COUNT(*) INTO v_prestamos_activos
    FROM prestamos
    WHERE ID_Activo = p_id_activo
      AND Estado_Prestamo = 'Activo';

    IF v_prestamos_activos > 0 AND p_nuevo_estado IN ('Activo', 'Mantenimiento', 'Baja') THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No puedes cambiar el estado porque el activo tiene un préstamo activo.';
    END IF;

    UPDATE activos
    SET Estado = p_nuevo_estado
    WHERE ID_Activo = p_id_activo;

    SELECT 'Estado del activo actualizado correctamente.' AS mensaje;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_actualizar_estado_alumno` (IN `p_matricula` INT, IN `p_estado` VARCHAR(10))   BEGIN
    UPDATE alumnos
    SET Estado = TRIM(p_estado)
    WHERE Matricula_Alumno = p_matricula;

    IF ROW_COUNT() = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No se encontró el alumno.';
    END IF;

    SELECT 'Estado del alumno actualizado correctamente.' AS mensaje;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_actualizar_estado_docente` (IN `p_matricula` INT, IN `p_estado` VARCHAR(10))   BEGIN
    UPDATE docentes
    SET Estado = TRIM(p_estado)
    WHERE Matricula_Docente = p_matricula;

    IF ROW_COUNT() = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No se encontró el docente.';
    END IF;

    SELECT 'Estado del docente actualizado correctamente.' AS mensaje;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_actualizar_password_usuario` (IN `p_matricula` INT, IN `p_pswd` VARCHAR(100))   BEGIN
    UPDATE usuarios
    SET Pswd_Uss = p_pswd
    WHERE Matricula_Uss = p_matricula;

    IF ROW_COUNT() = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No se encontró el usuario.';
    END IF;

    SELECT 'Contraseña actualizada correctamente.' AS mensaje;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_actualizar_ubicacion` (IN `p_id_lab` INT, IN `p_nombre_lab` VARCHAR(15))   BEGIN
    UPDATE ubicaciones
    SET Nombre_Lab = TRIM(p_nombre_lab)
    WHERE ID_Lab = p_id_lab;

    IF ROW_COUNT() = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No se encontró la ubicación a actualizar.';
    END IF;

    SELECT 'Ubicación actualizada correctamente.' AS mensaje;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_actualizar_usuario` (IN `p_matricula_original` INT, IN `p_matricula_nueva` INT, IN `p_nombre` VARCHAR(150), IN `p_rol` VARCHAR(30))   BEGIN
    IF p_rol NOT IN ('Administrador', 'Encargado') THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El rol no es válido.';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM usuarios
        WHERE Matricula_Uss = p_matricula_nueva
          AND Matricula_Uss <> p_matricula_original
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Ya existe otro usuario con esa matrícula.';
    END IF;

    UPDATE usuarios
    SET
        Matricula_Uss = p_matricula_nueva,
        Nombre_Uss = TRIM(p_nombre),
        Rol_Uss = TRIM(p_rol)
    WHERE Matricula_Uss = p_matricula_original;

    IF ROW_COUNT() = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No se encontró el usuario a actualizar.';
    END IF;

    SELECT 'Usuario actualizado correctamente.' AS mensaje;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_agregar_item_prestamo` (IN `p_grupo_prestamo_id` INT, IN `p_id_activo` INT, IN `p_cantidad_solicitada` INT, IN `p_fecha_limite` DATETIME)   BEGIN
    DECLARE v_matricula_alumno INT;
    DECLARE v_matricula_docente INT;
    DECLARE v_id_lab INT;
    DECLARE v_comentarios VARCHAR(150);
    DECLARE v_registrado_por INT;
    DECLARE v_tipo_activo VARCHAR(15);

    IF (SELECT COUNT(*) FROM prestamos_grupo WHERE Grupo_PrestamoID = p_grupo_prestamo_id) = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El grupo de préstamo no existe.';
    END IF;

    SELECT
        Matricula_Alumno,
        Matricula_Docente,
        ID_Lab,
        Comentarios,
        Registrado_Por
    INTO
        v_matricula_alumno,
        v_matricula_docente,
        v_id_lab,
        v_comentarios,
        v_registrado_por
    FROM prestamos_grupo
    WHERE Grupo_PrestamoID = p_grupo_prestamo_id;

    SELECT Tipo_Activo
    INTO v_tipo_activo
    FROM activos
    WHERE ID_Activo = p_id_activo;

    INSERT INTO prestamos (
        Grupo_PrestamoID,
        ID_Activo,
        Cantidad_Solicitada,
        Matricula_Alumno,
        Matricula_Docente,
        Fecha_Limite,
        ID_Lab,
        Comentarios,
        Estado_Prestamo,
        Registrado_Por
    )
    VALUES (
        p_grupo_prestamo_id,
        p_id_activo,
        IFNULL(p_cantidad_solicitada, 1),
        v_matricula_alumno,
        v_matricula_docente,
        CASE WHEN v_tipo_activo = 'Consumible' THEN NULL ELSE p_fecha_limite END,
        v_id_lab,
        v_comentarios,
        CASE WHEN v_tipo_activo = 'Consumible' THEN 'Entregado' ELSE 'Activo' END,
        v_registrado_por
    );

    SELECT LAST_INSERT_ID() AS ID_Prestamo, 'Ítem agregado correctamente.' AS mensaje;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_buscar_activo_por_marbete` (IN `p_num_marbete` VARCHAR(25))   BEGIN
    DECLARE v_busqueda VARCHAR(25);

    SET v_busqueda = TRIM(IFNULL(p_num_marbete, ''));

    IF v_busqueda = '' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Debes capturar un número de marbete.';
    END IF;

    SELECT
        a.ID_Activo,
        a.Num_Marbete,
        a.Activo_Desc,
        a.Estado,
        a.Tipo_Activo,
        a.Cantidad,
        a.ID_Lab,
        u.Nombre_Lab
    FROM activos a
    LEFT JOIN ubicaciones u
        ON u.ID_Lab = a.ID_Lab
    WHERE TRIM(a.Num_Marbete) = v_busqueda
    LIMIT 1;

    IF ROW_COUNT() = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No se encontró un activo con ese número de marbete.';
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_buscar_solicitante_por_matricula` (IN `p_tipo` VARCHAR(20), IN `p_matricula` INT)   BEGIN
    DECLARE v_tipo VARCHAR(20);

    SET v_tipo = TRIM(IFNULL(p_tipo, ''));

    IF p_matricula IS NULL OR p_matricula <= 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Debes capturar una matrícula válida.';
    END IF;

    IF v_tipo = 'Alumno' THEN
        SELECT
            'Alumno' AS Tipo,
            a.Matricula_Alumno AS Matricula,
            a.Nombre_Alumno AS Nombre,
            a.Carrera,
            a.Grupo,
            a.Contacto_Alumno AS Contacto,
            a.Estado
        FROM alumnos a
        WHERE a.Matricula_Alumno = p_matricula
        LIMIT 1;

        IF ROW_COUNT() = 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'No se encontró un alumno con esa matrícula.';
        END IF;

    ELSEIF v_tipo = 'Docente' THEN
        SELECT
            'Docente' AS Tipo,
            d.Matricula_Docente AS Matricula,
            d.Nombre_Docente AS Nombre,
            '' AS Carrera,
            '' AS Grupo,
            d.Contacto_Docente AS Contacto,
            d.Estado
        FROM docentes d
        WHERE d.Matricula_Docente = p_matricula
        LIMIT 1;

        IF ROW_COUNT() = 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'No se encontró un docente con esa matrícula.';
        END IF;

    ELSE
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El tipo de solicitante debe ser Alumno o Docente.';
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_crear_grupo_prestamo` (IN `p_matricula_alumno` INT, IN `p_matricula_docente` INT, IN `p_id_lab` INT, IN `p_comentarios` VARCHAR(150), IN `p_registrado_por` INT)   BEGIN
    IF (p_matricula_alumno IS NULL AND p_matricula_docente IS NULL)
       OR (p_matricula_alumno IS NOT NULL AND p_matricula_docente IS NOT NULL) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Debes indicar solo alumno o solo docente.';
    END IF;

    IF p_matricula_alumno IS NOT NULL THEN
        IF (SELECT COUNT(*) FROM alumnos WHERE Matricula_Alumno = p_matricula_alumno) = 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'La matrícula del alumno no existe.';
        END IF;
    END IF;

    IF p_matricula_docente IS NOT NULL THEN
        IF (SELECT COUNT(*) FROM docentes WHERE Matricula_Docente = p_matricula_docente) = 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'La matrícula del docente no existe.';
        END IF;
    END IF;

    IF (SELECT COUNT(*) FROM ubicaciones WHERE ID_Lab = p_id_lab) = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La ubicación destino no existe.';
    END IF;

    IF (SELECT COUNT(*) FROM usuarios WHERE Matricula_Uss = p_registrado_por) = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El usuario que registra no existe.';
    END IF;

    INSERT INTO prestamos_grupo (
        Matricula_Alumno,
        Matricula_Docente,
        ID_Lab,
        Comentarios,
        Registrado_Por
    ) VALUES (
        p_matricula_alumno,
        p_matricula_docente,
        p_id_lab,
        IFNULL(p_comentarios, ''),
        p_registrado_por
    );

    SELECT LAST_INSERT_ID() AS Grupo_PrestamoID, 'Grupo de préstamo creado correctamente.' AS mensaje;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_dashboard_resumen` ()   BEGIN
    SELECT
        (SELECT COUNT(*) FROM activos) AS total_activos,
        (SELECT COUNT(*) FROM activos WHERE Estado = 'Activo') AS activos_disponibles,
        (SELECT COUNT(*) FROM activos WHERE Estado = 'Prestado') AS activos_prestados,
        (SELECT COUNT(*) FROM activos WHERE Estado = 'Mantenimiento') AS activos_mantenimiento,
        (SELECT COUNT(*) FROM activos WHERE Estado = 'Baja') AS activos_baja,
        (SELECT COUNT(*) FROM activos WHERE ID_Lab = 1) AS activos_en_almacen,
        (SELECT COUNT(*) FROM prestamos WHERE Estado_Prestamo = 'Activo') AS prestamos_activos,
        (SELECT COUNT(*) FROM prestamos WHERE Estado_Prestamo = 'Devuelto') AS prestamos_devueltos,
        (SELECT COUNT(*) 
           FROM prestamos 
          WHERE Estado_Prestamo = 'Activo'
            AND Fecha_Limite < NOW()) AS prestamos_vencidos,
        (SELECT COUNT(*) 
           FROM prestamos 
          WHERE DATE(Fecha_Inicio) = CURDATE()) AS prestamos_hoy;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_eliminar_activo` (IN `p_id_activo` INT)   BEGIN
    DECLARE v_existe_activo INT DEFAULT 0;
    DECLARE v_total_prestamos INT DEFAULT 0;

    SELECT COUNT(*) INTO v_existe_activo
    FROM activos
    WHERE ID_Activo = p_id_activo;

    IF v_existe_activo = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El activo no existe.';
    END IF;

    SELECT COUNT(*) INTO v_total_prestamos
    FROM prestamos
    WHERE ID_Activo = p_id_activo;

    IF v_total_prestamos > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No se puede eliminar porque el activo ya tiene movimientos registrados.';
    END IF;

    DELETE FROM activos
    WHERE ID_Activo = p_id_activo;

    SELECT 'Activo eliminado correctamente.' AS mensaje;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_eliminar_alumno` (IN `p_matricula` INT)   BEGIN
    DELETE FROM alumnos
    WHERE Matricula_Alumno = p_matricula;

    IF ROW_COUNT() = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No se encontró el alumno a eliminar.';
    END IF;

    SELECT 'Alumno eliminado correctamente.' AS mensaje;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_eliminar_docente` (IN `p_matricula` INT)   BEGIN
    DELETE FROM docentes
    WHERE Matricula_Docente = p_matricula;

    IF ROW_COUNT() = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No se encontró el docente a eliminar.';
    END IF;

    SELECT 'Docente eliminado correctamente.' AS mensaje;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_eliminar_ubicacion` (IN `p_id_lab` INT)   BEGIN
    DELETE FROM ubicaciones
    WHERE ID_Lab = p_id_lab;

    IF ROW_COUNT() = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No se encontró la ubicación a eliminar.';
    END IF;

    SELECT 'Ubicación eliminada correctamente.' AS mensaje;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_eliminar_usuario` (IN `p_matricula` INT)   BEGIN
    DELETE FROM usuarios
    WHERE Matricula_Uss = p_matricula;

    IF ROW_COUNT() = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No se encontró el usuario a eliminar.';
    END IF;

    SELECT 'Usuario eliminado correctamente.' AS mensaje;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_historial_devoluciones` ()   BEGIN
    SELECT
        p.ID_Prestamo,
        p.Grupo_PrestamoID,
        p.Cantidad_Solicitada,
        p.Fecha_Inicio,
        p.Fecha_Limite,
        p.Fecha_Devolucion,
        a.Num_Marbete,
        a.Activo_Desc,
        a.Tipo_Activo,
        COALESCE(al.Nombre_Alumno, d.Nombre_Docente) AS SolicitanteNombre,
        ur.Nombre_Uss AS RecibidoPorNombre
    FROM prestamos p
    INNER JOIN activos a
        ON a.ID_Activo = p.ID_Activo
    LEFT JOIN alumnos al
        ON al.Matricula_Alumno = p.Matricula_Alumno
    LEFT JOIN docentes d
        ON d.Matricula_Docente = p.Matricula_Docente
    LEFT JOIN usuarios ur
        ON ur.Matricula_Uss = p.Recibido_Por
    WHERE p.Estado_Prestamo = 'Devuelto'
    ORDER BY p.Fecha_Devolucion DESC
    LIMIT 15;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_listar_activos` (IN `p_estado` VARCHAR(20), IN `p_busqueda` VARCHAR(100))   BEGIN
    DECLARE v_estado VARCHAR(20);
    DECLARE v_busqueda VARCHAR(100);

    SET v_estado = IFNULL(NULLIF(TRIM(p_estado), ''), 'Todos');
    SET v_busqueda = IFNULL(TRIM(p_busqueda), '');

    SELECT
        a.ID_Activo,
        a.Num_Marbete,
        a.Activo_Desc,
        a.Tipo_Activo,
        a.Cantidad,
        a.Estado,
        a.ID_Lab,
        u.Nombre_Lab,
        (
            SELECT COUNT(*)
            FROM prestamos p
            WHERE p.ID_Activo = a.ID_Activo
        ) AS TotalPrestamos
    FROM activos a
    LEFT JOIN ubicaciones u ON u.ID_Lab = a.ID_Lab
    WHERE
        (v_estado = 'Todos' OR a.Estado = v_estado)
        AND (
            v_busqueda = ''
            OR a.Num_Marbete LIKE CONCAT('%', v_busqueda, '%')
            OR a.Activo_Desc LIKE CONCAT('%', v_busqueda, '%')
        )
    ORDER BY a.ID_Activo DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_listar_prestamos_activos_devolucion` (IN `p_busqueda` VARCHAR(150), IN `p_fecha_inicio` DATE, IN `p_fecha_limite` DATE)   BEGIN
    SET p_busqueda = TRIM(IFNULL(p_busqueda, ''));

    SELECT
        p.ID_Prestamo,
        p.Grupo_PrestamoID,
        p.Cantidad_Solicitada,
        p.Fecha_Inicio,
        p.Fecha_Limite,
        p.Comentarios,
        p.Estado_Prestamo,
        a.Num_Marbete,
        a.Activo_Desc,
        a.Tipo_Activo,
        COALESCE(al.Nombre_Alumno, d.Nombre_Docente) AS SolicitanteNombre,
        COALESCE(
            CAST(p.Matricula_Alumno AS CHAR),
            CAST(p.Matricula_Docente AS CHAR)
        ) AS SolicitanteMatricula,
        u.Nombre_Lab,
        ur.Nombre_Uss AS RegistradoPorNombre
    FROM prestamos p
    INNER JOIN activos a
        ON a.ID_Activo = p.ID_Activo
    LEFT JOIN alumnos al
        ON al.Matricula_Alumno = p.Matricula_Alumno
    LEFT JOIN docentes d
        ON d.Matricula_Docente = p.Matricula_Docente
    LEFT JOIN ubicaciones u
        ON u.ID_Lab = p.ID_Lab
    LEFT JOIN usuarios ur
        ON ur.Matricula_Uss = p.Registrado_Por
    WHERE p.Estado_Prestamo = 'Activo'
      AND (
            p_busqueda = ''
            OR CAST(IFNULL(p.Grupo_PrestamoID, '') AS CHAR) LIKE CONCAT('%', p_busqueda, '%')
            OR a.Num_Marbete LIKE CONCAT('%', p_busqueda, '%')
            OR a.Activo_Desc LIKE CONCAT('%', p_busqueda, '%')
            OR COALESCE(al.Nombre_Alumno, d.Nombre_Docente) LIKE CONCAT('%', p_busqueda, '%')
            OR CAST(COALESCE(p.Matricula_Alumno, p.Matricula_Docente) AS CHAR) LIKE CONCAT('%', p_busqueda, '%')
      )
      AND (p_fecha_inicio IS NULL OR DATE(p.Fecha_Inicio) = p_fecha_inicio)
      AND (p_fecha_limite IS NULL OR DATE(p.Fecha_Limite) = p_fecha_limite)
    ORDER BY p.Fecha_Limite ASC, p.Grupo_PrestamoID ASC, p.ID_Prestamo ASC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_listar_solicitantes` (IN `p_tipo` VARCHAR(20), IN `p_busqueda` VARCHAR(150))   BEGIN
    SELECT *
    FROM (
        SELECT
            'Alumno' AS Tipo,
            a.Matricula_Alumno AS Matricula,
            a.Nombre_Alumno AS Nombre,
            a.Carrera AS Carrera,
            a.Grupo AS Grupo,
            a.Contacto_Alumno AS Contacto
        FROM alumnos a

        UNION ALL

        SELECT
            'Docente' AS Tipo,
            d.Matricula_Docente AS Matricula,
            d.Nombre_Docente AS Nombre,
            '' AS Carrera,
            '' AS Grupo,
            d.Contacto_Docente AS Contacto
        FROM docentes d
    ) s
    WHERE
        (p_tipo = 'Todos' OR s.Tipo = p_tipo)
        AND (
            p_busqueda IS NULL
            OR p_busqueda = ''
            OR CAST(s.Matricula AS CHAR) LIKE CONCAT('%', p_busqueda, '%')
            OR s.Nombre LIKE CONCAT('%', p_busqueda, '%')
            OR s.Carrera LIKE CONCAT('%', p_busqueda, '%')
            OR s.Grupo LIKE CONCAT('%', p_busqueda, '%')
            OR s.Contacto LIKE CONCAT('%', p_busqueda, '%')
        )
    ORDER BY s.Tipo ASC, s.Nombre ASC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_listar_ubicaciones` (IN `p_busqueda` VARCHAR(100))   BEGIN
    SELECT
        u.ID_Lab,
        u.Nombre_Lab,
        COUNT(a.ID_Activo) AS TotalActivos
    FROM ubicaciones u
    LEFT JOIN activos a
        ON a.ID_Lab = u.ID_Lab
    WHERE (
        p_busqueda IS NULL
        OR p_busqueda = ''
        OR u.Nombre_Lab LIKE CONCAT('%', p_busqueda, '%')
    )
    GROUP BY u.ID_Lab, u.Nombre_Lab
    ORDER BY u.Nombre_Lab ASC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_listar_usuarios` (IN `p_busqueda` VARCHAR(150))   BEGIN
    DECLARE v_busqueda VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

    SET v_busqueda = TRIM(IFNULL(p_busqueda, ''));

    SELECT
        Matricula_Uss,
        Nombre_Uss,
        Rol_Uss
    FROM usuarios
    WHERE
        v_busqueda = ''
        OR CAST(Matricula_Uss AS CHAR) COLLATE utf8mb4_general_ci LIKE CONCAT('%', v_busqueda, '%')
        OR Nombre_Uss COLLATE utf8mb4_general_ci LIKE CONCAT('%', v_busqueda, '%')
        OR Rol_Uss COLLATE utf8mb4_general_ci LIKE CONCAT('%', v_busqueda, '%')
    ORDER BY Nombre_Uss ASC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_login_usuario` (IN `p_matricula` INT)   BEGIN
    SELECT 
        Matricula_Uss,
        Nombre_Uss,
        Rol_Uss,
        Pswd_Uss
    FROM usuarios
    WHERE Matricula_Uss = p_matricula
    LIMIT 1;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_mover_activo` (IN `p_id_activo` INT, IN `p_id_lab_destino` INT, IN `p_nuevo_estado` VARCHAR(15))   BEGIN
    DECLARE v_existe_activo INT DEFAULT 0;
    DECLARE v_existe_lab INT DEFAULT 0;

    SELECT COUNT(*) INTO v_existe_activo
    FROM activos
    WHERE ID_Activo = p_id_activo;

    IF v_existe_activo = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El activo no existe.';
    END IF;

    SELECT COUNT(*) INTO v_existe_lab
    FROM ubicaciones
    WHERE ID_Lab = p_id_lab_destino;

    IF v_existe_lab = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La ubicación destino no existe.';
    END IF;

    UPDATE activos
    SET 
        ID_Lab = p_id_lab_destino,
        Estado = IFNULL(NULLIF(TRIM(p_nuevo_estado), ''), Estado)
    WHERE ID_Activo = p_id_activo;

    SELECT 'Activo movido correctamente.' AS mensaje;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_registrar_activo` (IN `p_num_marbete` VARCHAR(25), IN `p_activo_desc` VARCHAR(150), IN `p_estado` VARCHAR(15), IN `p_id_lab` INT, IN `p_tipo_activo` VARCHAR(15), IN `p_cantidad` INT)   BEGIN
    DECLARE v_count INT DEFAULT 0;
    DECLARE v_estado_final VARCHAR(15);
    DECLARE v_lab_final INT;
    DECLARE v_tipo_final VARCHAR(15);
    DECLARE v_cantidad_final INT;

    SELECT COUNT(*) INTO v_count
    FROM activos
    WHERE Num_Marbete = p_num_marbete;

    IF v_count > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Ya existe un activo con ese número de marbete.';
    END IF;

    SET v_estado_final = IFNULL(NULLIF(TRIM(p_estado), ''), 'Activo');
    SET v_lab_final = IFNULL(p_id_lab, 1);
    SET v_tipo_final = IFNULL(NULLIF(TRIM(p_tipo_activo), ''), 'No Consumible');
    SET v_cantidad_final = IFNULL(p_cantidad, 1);

    INSERT INTO activos (
        Num_Marbete,
        Activo_Desc,
        Estado,
        ID_Lab,
        Tipo_Activo,
        Cantidad
    )
    VALUES (
        p_num_marbete,
        p_activo_desc,
        v_estado_final,
        v_lab_final,
        v_tipo_final,
        v_cantidad_final
    );

    SELECT LAST_INSERT_ID() AS ID_Activo, 'Activo registrado correctamente.' AS mensaje;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_registrar_alumno` (IN `p_matricula` INT, IN `p_nombre` VARCHAR(150), IN `p_carrera` VARCHAR(50), IN `p_grupo` VARCHAR(20), IN `p_contacto` VARCHAR(15))   BEGIN
    INSERT INTO alumnos (
        Matricula_Alumno,
        Nombre_Alumno,
        Carrera,
        Grupo,
        Contacto_Alumno,
        Estado
    ) VALUES (
        p_matricula,
        TRIM(p_nombre),
        TRIM(p_carrera),
        TRIM(p_grupo),
        TRIM(p_contacto),
        'Activo'
    );

    SELECT 'Alumno registrado correctamente.' AS mensaje;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_registrar_devolucion` (IN `p_id_prestamo` INT, IN `p_recibido_por` INT)   BEGIN
    DECLARE v_existe_prestamo INT DEFAULT 0;
    DECLARE v_estado_prestamo VARCHAR(13);
    DECLARE v_existe_usuario INT DEFAULT 0;

    SELECT COUNT(*), Estado_Prestamo
      INTO v_existe_prestamo, v_estado_prestamo
    FROM prestamos
    WHERE ID_Prestamo = p_id_prestamo;

    IF v_existe_prestamo = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El préstamo no existe.';
    END IF;

    IF v_estado_prestamo <> 'Activo' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El préstamo ya fue devuelto o no está activo.';
    END IF;

    SELECT COUNT(*) INTO v_existe_usuario
    FROM usuarios
    WHERE Matricula_Uss = p_recibido_por;

    IF v_existe_usuario = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El usuario que recibe no existe.';
    END IF;

    UPDATE prestamos
    SET 
        Estado_Prestamo = 'Devuelto',
        `Fecha_Devolucion` = NOW(),
        Recibido_Por = p_recibido_por
    WHERE ID_Prestamo = p_id_prestamo;

    SELECT 'Devolución registrada correctamente.' AS mensaje;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_registrar_docente` (IN `p_matricula` INT, IN `p_nombre` VARCHAR(150), IN `p_contacto` VARCHAR(15))   BEGIN
    INSERT INTO docentes (
        Matricula_Docente,
        Nombre_Docente,
        Contacto_Docente,
        Estado
    ) VALUES (
        p_matricula,
        TRIM(p_nombre),
        TRIM(p_contacto),
        'Activo'
    );

    SELECT 'Docente registrado correctamente.' AS mensaje;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_registrar_prestamo` (IN `p_id_activo` INT, IN `p_matricula_alumno` INT, IN `p_matricula_docente` INT, IN `p_fecha_limite` DATETIME, IN `p_id_lab` INT, IN `p_comentarios` VARCHAR(150), IN `p_registrado_por` INT)   BEGIN
    DECLARE v_grupo_id INT;

    CALL sp_crear_grupo_prestamo(
        p_matricula_alumno,
        p_matricula_docente,
        p_id_lab,
        p_comentarios,
        p_registrado_por
    );

    SELECT MAX(Grupo_PrestamoID) INTO v_grupo_id
    FROM prestamos_grupo
    WHERE Registrado_Por = p_registrado_por;

    CALL sp_agregar_item_prestamo(
        v_grupo_id,
        p_id_activo,
        1,
        p_fecha_limite
    );

    SELECT v_grupo_id AS Grupo_PrestamoID, 'Préstamo registrado correctamente.' AS mensaje;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_registrar_ubicacion` (IN `p_nombre_lab` VARCHAR(15))   BEGIN
    INSERT INTO ubicaciones (Nombre_Lab)
    VALUES (TRIM(p_nombre_lab));

    SELECT 'Ubicación registrada correctamente.' AS mensaje;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_registrar_usuario` (IN `p_matricula` INT, IN `p_nombre` VARCHAR(150), IN `p_rol` VARCHAR(30), IN `p_pswd` VARCHAR(100))   BEGIN
    IF EXISTS (
        SELECT 1
        FROM usuarios
        WHERE Matricula_Uss = p_matricula
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Ya existe un usuario con esa matrícula.';
    END IF;

    IF p_rol NOT IN ('Administrador', 'Encargado') THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El rol no es válido.';
    END IF;

    INSERT INTO usuarios (
        Matricula_Uss,
        Nombre_Uss,
        Rol_Uss,
        Pswd_Uss
    ) VALUES (
        p_matricula,
        TRIM(p_nombre),
        TRIM(p_rol),
        p_pswd
    );

    SELECT 'Usuario registrado correctamente.' AS mensaje;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_reporte_prestamos` (IN `p_tipo_reporte` VARCHAR(30), IN `p_fecha_inicio` DATE, IN `p_fecha_fin` DATE)   BEGIN
    SELECT
        p.ID_Prestamo,
        p.Grupo_PrestamoID,
        a.Num_Marbete,
        a.Activo_Desc,
        a.Tipo_Activo,
        p.Cantidad_Solicitada,
        p.Estado_Prestamo,
        COALESCE(al.Nombre_Alumno, d.Nombre_Docente) AS SolicitanteNombre,
        COALESCE(al.Contacto_Alumno, d.Contacto_Docente) AS Contacto,
        COALESCE(al.Carrera, 'Docente') AS Carrera,
        COALESCE(al.Grupo, '—') AS GrupoAcademico,
        COALESCE(
            CAST(p.Matricula_Alumno AS CHAR),
            CAST(p.Matricula_Docente AS CHAR)
        ) AS SolicitanteMatricula,
        p.Fecha_Inicio,
        p.Fecha_Limite,
        p.Fecha_Devolucion,
        u.Nombre_Lab,
        ur.Nombre_Uss AS RegistradoPorNombre,
        uu.Nombre_Uss AS RecibidoPorNombre,
        p.Comentarios
    FROM prestamos p
    INNER JOIN activos a
        ON a.ID_Activo = p.ID_Activo
    LEFT JOIN alumnos al
        ON al.Matricula_Alumno = p.Matricula_Alumno
    LEFT JOIN docentes d
        ON d.Matricula_Docente = p.Matricula_Docente
    LEFT JOIN ubicaciones u
        ON u.ID_Lab = p.ID_Lab
    LEFT JOIN usuarios ur
        ON ur.Matricula_Uss = p.Registrado_Por
    LEFT JOIN usuarios uu
        ON uu.Matricula_Uss = p.Recibido_Por
    WHERE
        (
            p_tipo_reporte = 'prestamos'
            AND (p_fecha_inicio IS NULL OR DATE(p.Fecha_Inicio) >= p_fecha_inicio)
            AND (p_fecha_fin IS NULL OR DATE(p.Fecha_Inicio) <= p_fecha_fin)
        )
        OR
        (
            p_tipo_reporte = 'devueltos'
            AND p.Estado_Prestamo = 'Devuelto'
            AND (p_fecha_inicio IS NULL OR DATE(p.Fecha_Devolucion) >= p_fecha_inicio)
            AND (p_fecha_fin IS NULL OR DATE(p.Fecha_Devolucion) <= p_fecha_fin)
        )
        OR
        (
            p_tipo_reporte = 'activos'
            AND p.Estado_Prestamo = 'Activo'
            AND (p_fecha_inicio IS NULL OR DATE(p.Fecha_Limite) >= p_fecha_inicio)
            AND (p_fecha_fin IS NULL OR DATE(p.Fecha_Limite) <= p_fecha_fin)
        )
        OR
        (
            p_tipo_reporte = 'vencidos'
            AND p.Estado_Prestamo = 'Activo'
            AND p.Fecha_Limite IS NOT NULL
            AND p.Fecha_Limite < NOW()
            AND (p_fecha_inicio IS NULL OR DATE(p.Fecha_Limite) >= p_fecha_inicio)
            AND (p_fecha_fin IS NULL OR DATE(p.Fecha_Limite) <= p_fecha_fin)
        )
    ORDER BY p.Fecha_Inicio DESC, p.ID_Prestamo DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_resumen_devoluciones` ()   BEGIN
    SELECT
        SUM(CASE WHEN Estado_Prestamo = 'Activo' THEN 1 ELSE 0 END) AS pendientes,
        SUM(CASE WHEN Estado_Prestamo = 'Activo' AND Fecha_Limite < NOW() THEN 1 ELSE 0 END) AS vencidos,
        SUM(CASE WHEN Estado_Prestamo = 'Devuelto' AND DATE(Fecha_Devolucion) = CURDATE() THEN 1 ELSE 0 END) AS devueltos_hoy,
        COUNT(DISTINCT CASE WHEN Estado_Prestamo = 'Activo' THEN Grupo_PrestamoID END) AS grupos_abiertos
    FROM prestamos;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `activos`
--

CREATE TABLE `activos` (
  `ID_Activo` int(15) NOT NULL,
  `Num_Marbete` varchar(25) NOT NULL,
  `Activo_Desc` varchar(150) NOT NULL,
  `Estado` varchar(15) NOT NULL DEFAULT 'Activo',
  `ID_Lab` int(10) NOT NULL,
  `Tipo_Activo` varchar(15) NOT NULL DEFAULT 'No Consumible',
  `Cantidad` int(5) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `activos`
--

INSERT INTO `activos` (`ID_Activo`, `Num_Marbete`, `Activo_Desc`, `Estado`, `ID_Lab`, `Tipo_Activo`, `Cantidad`) VALUES
(1, '1.2.3.222', 'Laptop HP', 'Mantenimiento', 1, 'No Consumible', 1),
(3, '1.2.3.4', 'Tornillo 1/4\'\'', 'Activo', 1, 'Consumible', 9),
(4, '1.2.3.1', 'Cautin', 'Activo', 1, 'No Consumible', 1),
(5, '1.2.3.2', 'Scanner', 'Baja', 1, 'No Consumible', 1),
(6, '1.2.3.23', 'Voltimetro 1', 'Baja', 1, 'No Consumible', 1),
(7, '1.3.4.4', 'Multinetro', 'Prestado', 1, 'No Consumible', 1);

--
-- Disparadores `activos`
--
DELIMITER $$
CREATE TRIGGER `trg_activos_bi` BEFORE INSERT ON `activos` FOR EACH ROW BEGIN
    IF NEW.Estado NOT IN ('Activo', 'Prestado', 'Mantenimiento', 'Baja') THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Estado de activo no válido.';
    END IF;

    IF NEW.Tipo_Activo NOT IN ('Consumible', 'No Consumible') THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Tipo de activo no válido.';
    END IF;

    IF NEW.Cantidad < 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La cantidad no puede ser negativa.';
    END IF;

    IF NEW.Tipo_Activo = 'Consumible' AND NEW.Cantidad <= 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Un consumible debe tener cantidad mayor a 0.';
    END IF;

    IF NEW.Tipo_Activo = 'No Consumible' AND NEW.Cantidad <= 0 THEN
        SET NEW.Cantidad = 1;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_activos_bu` BEFORE UPDATE ON `activos` FOR EACH ROW BEGIN
    IF NEW.Estado NOT IN ('Activo', 'Prestado', 'Mantenimiento', 'Baja') THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Estado de activo no válido.';
    END IF;

    IF NEW.Tipo_Activo NOT IN ('Consumible', 'No Consumible') THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Tipo de activo no válido.';
    END IF;

    IF NEW.Cantidad < 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La cantidad no puede ser negativa.';
    END IF;

    IF NEW.Tipo_Activo = 'Consumible' AND NEW.Cantidad <= 0 THEN
        SET NEW.Estado = 'Baja';
    END IF;

    IF NEW.Tipo_Activo = 'No Consumible' AND NEW.Cantidad <= 0 THEN
        SET NEW.Cantidad = 1;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alumnos`
--

CREATE TABLE `alumnos` (
  `Matricula_Alumno` int(15) NOT NULL,
  `Nombre_Alumno` varchar(150) NOT NULL,
  `Carrera` varchar(50) NOT NULL,
  `Grupo` varchar(20) NOT NULL,
  `Contacto_Alumno` varchar(15) NOT NULL,
  `Estado` varchar(10) NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `alumnos`
--

INSERT INTO `alumnos` (`Matricula_Alumno`, `Nombre_Alumno`, `Carrera`, `Grupo`, `Contacto_Alumno`, `Estado`) VALUES
(24306023, 'Elisa Test', 'Desarrollo de Software', 'DSM5-1', '6313155012', 'Activo'),
(24306089, 'no se', 'Desarrollo', 'DSM6-1', '6311010101', 'Activo');

--
-- Disparadores `alumnos`
--
DELIMITER $$
CREATE TRIGGER `trg_alumnos_bd` BEFORE DELETE ON `alumnos` FOR EACH ROW BEGIN
    IF EXISTS (
        SELECT 1
        FROM prestamos
        WHERE Matricula_Alumno = OLD.Matricula_Alumno
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No se puede eliminar el alumno porque tiene préstamos relacionados.';
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_alumnos_bi` BEFORE INSERT ON `alumnos` FOR EACH ROW BEGIN
    SET NEW.Nombre_Alumno = TRIM(NEW.Nombre_Alumno);
    SET NEW.Carrera = TRIM(NEW.Carrera);
    SET NEW.Grupo = TRIM(NEW.Grupo);
    SET NEW.Contacto_Alumno = TRIM(NEW.Contacto_Alumno);
    SET NEW.Estado = TRIM(NEW.Estado);

    IF NEW.Matricula_Alumno IS NULL OR NEW.Matricula_Alumno <= 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La matrícula del alumno es obligatoria.';
    END IF;

    IF NEW.Nombre_Alumno IS NULL OR NEW.Nombre_Alumno = '' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El nombre del alumno es obligatorio.';
    END IF;

    IF NEW.Carrera IS NULL OR NEW.Carrera = '' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La carrera del alumno es obligatoria.';
    END IF;

    IF NEW.Grupo IS NULL OR NEW.Grupo = '' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El grupo del alumno es obligatorio.';
    END IF;

    IF NEW.Contacto_Alumno IS NULL OR NEW.Contacto_Alumno = '' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El contacto del alumno es obligatorio.';
    END IF;

    IF NEW.Estado NOT IN ('Activo', 'Baja') THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El estado del alumno debe ser Activo o Baja.';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM alumnos
        WHERE Matricula_Alumno = NEW.Matricula_Alumno
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Ya existe un alumno con esa matrícula.';
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_alumnos_bu` BEFORE UPDATE ON `alumnos` FOR EACH ROW BEGIN
    SET NEW.Nombre_Alumno = TRIM(NEW.Nombre_Alumno);
    SET NEW.Carrera = TRIM(NEW.Carrera);
    SET NEW.Grupo = TRIM(NEW.Grupo);
    SET NEW.Contacto_Alumno = TRIM(NEW.Contacto_Alumno);
    SET NEW.Estado = TRIM(NEW.Estado);

    IF NEW.Matricula_Alumno IS NULL OR NEW.Matricula_Alumno <= 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La matrícula del alumno es obligatoria.';
    END IF;

    IF NEW.Nombre_Alumno IS NULL OR NEW.Nombre_Alumno = '' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El nombre del alumno es obligatorio.';
    END IF;

    IF NEW.Carrera IS NULL OR NEW.Carrera = '' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La carrera del alumno es obligatoria.';
    END IF;

    IF NEW.Grupo IS NULL OR NEW.Grupo = '' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El grupo del alumno es obligatorio.';
    END IF;

    IF NEW.Contacto_Alumno IS NULL OR NEW.Contacto_Alumno = '' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El contacto del alumno es obligatorio.';
    END IF;

    IF NEW.Estado NOT IN ('Activo', 'Baja') THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El estado del alumno debe ser Activo o Baja.';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM alumnos
        WHERE Matricula_Alumno = NEW.Matricula_Alumno
          AND Matricula_Alumno <> OLD.Matricula_Alumno
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Ya existe otro alumno con esa matrícula.';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bitacora_prestamos`
--

CREATE TABLE `bitacora_prestamos` (
  `ID_Bitacora` int(15) NOT NULL,
  `ID_Prestamo` int(15) NOT NULL,
  `Bitacora_Accion` varchar(15) NOT NULL,
  `Bitacora_Desc` varchar(150) NOT NULL,
  `Fecha_Movimiento` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `Usuario_Movimiento` int(15) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `bitacora_prestamos`
--

INSERT INTO `bitacora_prestamos` (`ID_Bitacora`, `ID_Prestamo`, `Bitacora_Accion`, `Bitacora_Desc`, `Fecha_Movimiento`, `Usuario_Movimiento`) VALUES
(1, 1, 'Prestamo', 'Se registró préstamo del activo: Cautin', '2026-03-20 21:33:16.377481', 24306012),
(2, 1, 'Devolucion', 'Se registró devolución del activo: Cautin', '2026-03-20 21:33:39.092357', 24306012),
(3, 2, 'Prestamo', 'Se registró préstamo del activo: Voltimetro 1', '2026-03-20 21:53:29.197448', 24306012),
(4, 2, 'Devolucion', 'Se registró devolución del activo: Voltimetro 1', '2026-03-20 21:54:40.440210', 24306012),
(5, 3, 'Prestamo', 'Se registró préstamo del activo: Voltimetro 1', '2026-03-20 21:55:03.208926', 24306011),
(6, 4, 'Prestamo', 'Se registró préstamo del activo: Tornillo 1/4\'\'', '2026-03-20 22:04:58.348772', 24306012),
(7, 5, 'Prestamo', 'Se registró préstamo del activo: Tornillo 1/4\'\'', '2026-03-20 22:05:01.637793', 24306012),
(8, 4, 'Devolucion', 'Se registró devolución del activo: Tornillo 1/4\'\'', '2026-03-20 22:05:31.754633', 24306012),
(9, 5, 'Devolucion', 'Se registró devolución del activo: Tornillo 1/4\'\'', '2026-03-20 22:05:45.677228', 24306012),
(10, 3, 'Devolucion', 'Se registró devolución del activo: Voltimetro 1', '2026-03-20 22:05:49.510363', 24306012),
(11, 6, 'Prestamo', 'Se registró préstamo del activo: Voltimetro 1', '2026-03-20 22:06:56.092398', 24306012),
(12, 6, 'Devolucion', 'Se registró devolución del activo: Voltimetro 1', '2026-03-20 22:07:15.405524', 24306012),
(13, 7, 'Prestamo', 'Se registró entrega del activo: Tornillo 1/4\'\' | Cantidad: 5', '2026-03-20 22:52:07.438530', 24306012),
(14, 8, 'Prestamo', 'Se registró préstamo del activo: Voltimetro 1 | Cantidad: 1', '2026-03-20 22:52:07.454416', 24306012),
(15, 8, 'Devolucion', 'Se registró devolución del activo: Voltimetro 1', '2026-03-20 23:12:04.129912', 24306012),
(16, 9, 'Prestamo', 'Se registró préstamo del activo: Voltimetro 1 | Cantidad: 1', '2026-03-20 23:44:30.605256', 24306012),
(17, 10, 'Prestamo', 'Se registró préstamo del activo: Multinetro | Cantidad: 1', '2026-03-23 14:39:05.550495', 24306012),
(18, 9, 'Devolucion', 'Se registró devolución del activo: Voltimetro 1', '2026-03-23 14:40:34.944081', 24306012);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `docentes`
--

CREATE TABLE `docentes` (
  `Matricula_Docente` int(15) NOT NULL,
  `Nombre_Docente` varchar(150) NOT NULL,
  `Contacto_Docente` varchar(15) NOT NULL,
  `Estado` varchar(10) NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `docentes`
--

INSERT INTO `docentes` (`Matricula_Docente`, `Nombre_Docente`, `Contacto_Docente`, `Estado`) VALUES
(24306077, 'Indelfonso Rodriguez', '6311106040', 'Activo'),
(24306082, 'Gabriel Lopez', '6310101010', 'Activo');

--
-- Disparadores `docentes`
--
DELIMITER $$
CREATE TRIGGER `trg_docentes_bd` BEFORE DELETE ON `docentes` FOR EACH ROW BEGIN
    IF EXISTS (
        SELECT 1
        FROM prestamos
        WHERE Matricula_Docente = OLD.Matricula_Docente
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No se puede eliminar el docente porque tiene préstamos relacionados.';
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_docentes_bi` BEFORE INSERT ON `docentes` FOR EACH ROW BEGIN
    SET NEW.Nombre_Docente = TRIM(NEW.Nombre_Docente);
    SET NEW.Contacto_Docente = TRIM(NEW.Contacto_Docente);
    SET NEW.Estado = TRIM(NEW.Estado);

    IF NEW.Matricula_Docente IS NULL OR NEW.Matricula_Docente <= 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La matrícula del docente es obligatoria.';
    END IF;

    IF NEW.Nombre_Docente IS NULL OR NEW.Nombre_Docente = '' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El nombre del docente es obligatorio.';
    END IF;

    IF NEW.Contacto_Docente IS NULL OR NEW.Contacto_Docente = '' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El contacto del docente es obligatorio.';
    END IF;

    IF NEW.Estado NOT IN ('Activo', 'Baja') THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El estado del docente debe ser Activo o Baja.';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM docentes
        WHERE Matricula_Docente = NEW.Matricula_Docente
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Ya existe un docente con esa matrícula.';
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_docentes_bu` BEFORE UPDATE ON `docentes` FOR EACH ROW BEGIN
    SET NEW.Nombre_Docente = TRIM(NEW.Nombre_Docente);
    SET NEW.Contacto_Docente = TRIM(NEW.Contacto_Docente);
    SET NEW.Estado = TRIM(NEW.Estado);

    IF NEW.Matricula_Docente IS NULL OR NEW.Matricula_Docente <= 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La matrícula del docente es obligatoria.';
    END IF;

    IF NEW.Nombre_Docente IS NULL OR NEW.Nombre_Docente = '' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El nombre del docente es obligatorio.';
    END IF;

    IF NEW.Contacto_Docente IS NULL OR NEW.Contacto_Docente = '' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El contacto del docente es obligatorio.';
    END IF;

    IF NEW.Estado NOT IN ('Activo', 'Baja') THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El estado del docente debe ser Activo o Baja.';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM docentes
        WHERE Matricula_Docente = NEW.Matricula_Docente
          AND Matricula_Docente <> OLD.Matricula_Docente
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Ya existe otro docente con esa matrícula.';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `prestamos`
--

CREATE TABLE `prestamos` (
  `ID_Prestamo` int(15) NOT NULL,
  `Grupo_PrestamoID` int(15) DEFAULT NULL,
  `ID_Activo` int(15) NOT NULL,
  `Cantidad_Solicitada` int(5) NOT NULL DEFAULT 1,
  `Matricula_Alumno` int(15) DEFAULT NULL,
  `Matricula_Docente` int(15) DEFAULT NULL,
  `Fecha_Inicio` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `Fecha_Limite` datetime(6) DEFAULT NULL,
  `Fecha_Devolucion` datetime(6) DEFAULT NULL,
  `ID_Lab` int(10) NOT NULL,
  `Comentarios` varchar(150) NOT NULL,
  `Estado_Prestamo` varchar(13) NOT NULL DEFAULT 'Activo',
  `Registrado_Por` int(15) NOT NULL,
  `Recibido_Por` int(15) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `prestamos`
--

INSERT INTO `prestamos` (`ID_Prestamo`, `Grupo_PrestamoID`, `ID_Activo`, `Cantidad_Solicitada`, `Matricula_Alumno`, `Matricula_Docente`, `Fecha_Inicio`, `Fecha_Limite`, `Fecha_Devolucion`, `ID_Lab`, `Comentarios`, `Estado_Prestamo`, `Registrado_Por`, `Recibido_Por`) VALUES
(1, NULL, 4, 1, 24306023, NULL, '2026-03-20 21:33:16.377481', '2026-03-23 21:32:00.000000', '2026-03-20 21:33:39.000000', 2, 'Cautin para lab usdfuheidue no se akdxhekd e en buen estado', 'Devuelto', 24306012, 24306012),
(2, NULL, 6, 1, 24306023, NULL, '2026-03-20 21:53:29.197448', '2026-03-24 21:53:00.000000', '2026-03-20 21:54:40.000000', 2, 'testetstetststtstetstydfewydsw', 'Devuelto', 24306012, 24306012),
(3, NULL, 6, 1, 24306023, NULL, '2026-03-20 21:55:03.208926', '2026-03-25 21:53:00.000000', '2026-03-20 22:05:49.000000', 2, 'nss', 'Devuelto', 24306011, 24306012),
(4, NULL, 3, 1, NULL, 24306082, '2026-03-20 22:04:58.348772', '2026-03-21 22:04:00.000000', '2026-03-20 22:05:31.000000', 1, 'consumible test', 'Devuelto', 24306012, 24306012),
(5, NULL, 3, 1, NULL, 24306082, '2026-03-20 22:05:01.637793', '2026-03-21 22:04:00.000000', '2026-03-20 22:05:45.000000', 1, 'consumible test', 'Devuelto', 24306012, 24306012),
(6, NULL, 6, 1, NULL, 24306082, '2026-03-20 22:06:56.092398', '2026-03-24 22:06:00.000000', '2026-03-20 22:07:15.000000', 2, 'dhoeihdw4', 'Devuelto', 24306012, 24306012),
(7, 1, 3, 5, 24306023, NULL, '2026-03-20 22:52:07.438530', NULL, '2026-03-20 22:52:07.000000', 1, '', 'Entregado', 24306012, 24306012),
(8, 1, 6, 1, 24306023, NULL, '2026-03-20 22:52:07.454416', '2026-03-27 22:51:00.000000', '2026-03-20 23:12:04.000000', 1, '', 'Devuelto', 24306012, 24306012),
(9, 2, 6, 1, 24306023, NULL, '2026-03-20 23:44:30.605256', '2026-03-21 23:44:00.000000', '2026-03-23 14:40:34.000000', 2, '', 'Devuelto', 24306012, 24306012),
(10, 18, 7, 1, 24306023, NULL, '2026-03-23 14:39:05.550495', '2026-03-24 14:38:00.000000', NULL, 5, 'fhtrhyrhf', 'Activo', 24306012, NULL);

--
-- Disparadores `prestamos`
--
DELIMITER $$
CREATE TRIGGER `trg_prestamos_ai` AFTER INSERT ON `prestamos` FOR EACH ROW BEGIN
    DECLARE v_tipo_activo VARCHAR(15);
    DECLARE v_desc_activo VARCHAR(150);

    SELECT Tipo_Activo, Activo_Desc
      INTO v_tipo_activo, v_desc_activo
    FROM activos
    WHERE ID_Activo = NEW.ID_Activo;

    IF v_tipo_activo = 'No Consumible' THEN
        UPDATE activos
        SET Estado = 'Prestado'
        WHERE ID_Activo = NEW.ID_Activo;
    ELSE
        UPDATE activos
        SET Cantidad = Cantidad - NEW.Cantidad_Solicitada
        WHERE ID_Activo = NEW.ID_Activo;
    END IF;

    INSERT INTO bitacora_prestamos (
        ID_Prestamo,
        Bitacora_Accion,
        Bitacora_Desc,
        Usuario_Movimiento
    )
    VALUES (
        NEW.ID_Prestamo,
        'Prestamo',
        CONCAT(
            'Se registró ',
            CASE WHEN v_tipo_activo = 'Consumible' THEN 'entrega' ELSE 'préstamo' END,
            ' del activo: ',
            v_desc_activo,
            ' | Cantidad: ',
            NEW.Cantidad_Solicitada
        ),
        NEW.Registrado_Por
    );
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_prestamos_au` AFTER UPDATE ON `prestamos` FOR EACH ROW BEGIN
    DECLARE v_tipo_activo VARCHAR(15);
    DECLARE v_desc_activo VARCHAR(150);

    IF NEW.Estado_Prestamo = 'Devuelto' AND OLD.Estado_Prestamo <> 'Devuelto' THEN
        SELECT Tipo_Activo, Activo_Desc
          INTO v_tipo_activo, v_desc_activo
        FROM activos
        WHERE ID_Activo = NEW.ID_Activo;

        IF v_tipo_activo = 'No Consumible' THEN
            UPDATE activos
            SET Estado = 'Activo',
                ID_Lab = 1
            WHERE ID_Activo = NEW.ID_Activo;

            INSERT INTO bitacora_prestamos (
                ID_Prestamo,
                Bitacora_Accion,
                Bitacora_Desc,
                Usuario_Movimiento
            )
            VALUES (
                NEW.ID_Prestamo,
                'Devolucion',
                CONCAT('Se registró devolución del activo: ', v_desc_activo),
                NEW.Recibido_Por
            );
        END IF;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_prestamos_bi` BEFORE INSERT ON `prestamos` FOR EACH ROW BEGIN
    DECLARE v_estado_activo VARCHAR(15);
    DECLARE v_tipo_activo VARCHAR(15);
    DECLARE v_cantidad_disponible INT DEFAULT 0;
    DECLARE v_prestamos_activos INT DEFAULT 0;

    IF NEW.Cantidad_Solicitada IS NULL OR NEW.Cantidad_Solicitada <= 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La cantidad solicitada debe ser mayor a 0.';
    END IF;

    SELECT Estado, Tipo_Activo, Cantidad
      INTO v_estado_activo, v_tipo_activo, v_cantidad_disponible
    FROM activos
    WHERE ID_Activo = NEW.ID_Activo;

    IF v_estado_activo IN ('Baja', 'Mantenimiento') THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El activo no está disponible.';
    END IF;

    IF v_tipo_activo = 'No Consumible' THEN
        IF NEW.Cantidad_Solicitada <> 1 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Un activo no consumible solo puede prestarse en cantidad 1.';
        END IF;

        IF NEW.Fecha_Limite IS NULL OR NEW.Fecha_Limite <= NOW() THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'La fecha límite debe ser posterior a la actual.';
        END IF;

        SELECT COUNT(*) INTO v_prestamos_activos
        FROM prestamos
        WHERE ID_Activo = NEW.ID_Activo
          AND Estado_Prestamo = 'Activo';

        IF v_prestamos_activos > 0 OR v_estado_activo = 'Prestado' THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'El activo ya está prestado.';
        END IF;

        IF NEW.Estado_Prestamo NOT IN ('Activo', 'Devuelto') THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Estado no válido para un no consumible.';
        END IF;
    ELSE
        IF NEW.Cantidad_Solicitada > v_cantidad_disponible THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'No hay suficiente existencia del consumible.';
        END IF;

        SET NEW.Fecha_Limite = NULL;
        SET NEW.Fecha_Devolucion = NOW();
        SET NEW.Recibido_Por = NEW.Registrado_Por;
        SET NEW.Estado_Prestamo = 'Entregado';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `prestamos_grupo`
--

CREATE TABLE `prestamos_grupo` (
  `Grupo_PrestamoID` int(15) NOT NULL,
  `Matricula_Alumno` int(15) DEFAULT NULL,
  `Matricula_Docente` int(15) DEFAULT NULL,
  `Fecha_Creacion` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `ID_Lab` int(10) NOT NULL,
  `Comentarios` varchar(150) NOT NULL DEFAULT '',
  `Registrado_Por` int(15) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `prestamos_grupo`
--

INSERT INTO `prestamos_grupo` (`Grupo_PrestamoID`, `Matricula_Alumno`, `Matricula_Docente`, `Fecha_Creacion`, `ID_Lab`, `Comentarios`, `Registrado_Por`) VALUES
(1, 24306023, NULL, '2026-03-20 22:52:07.427853', 1, '', 24306012),
(2, 24306023, NULL, '2026-03-20 23:44:30.580742', 2, '', 24306012),
(18, 24306023, NULL, '2026-03-23 14:39:05.547479', 5, 'fhtrhyrhf', 24306012);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ubicaciones`
--

CREATE TABLE `ubicaciones` (
  `ID_Lab` int(10) NOT NULL,
  `Nombre_Lab` varchar(15) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ubicaciones`
--

INSERT INTO `ubicaciones` (`ID_Lab`, `Nombre_Lab`) VALUES
(1, 'Almacén'),
(2, 'Laboratorio 1'),
(5, 'Laboratorio 2');

--
-- Disparadores `ubicaciones`
--
DELIMITER $$
CREATE TRIGGER `trg_ubicaciones_bd` BEFORE DELETE ON `ubicaciones` FOR EACH ROW BEGIN
    IF EXISTS (
        SELECT 1
        FROM activos
        WHERE ID_Lab = OLD.ID_Lab
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No se puede eliminar la ubicación porque tiene activos asignados.';
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_ubicaciones_bi` BEFORE INSERT ON `ubicaciones` FOR EACH ROW BEGIN
    SET NEW.Nombre_Lab = TRIM(NEW.Nombre_Lab);

    IF NEW.Nombre_Lab IS NULL OR NEW.Nombre_Lab = '' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El nombre de la ubicación es obligatorio.';
    END IF;

    IF CHAR_LENGTH(NEW.Nombre_Lab) > 15 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El nombre de la ubicación no puede exceder 15 caracteres.';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM ubicaciones
        WHERE UPPER(TRIM(Nombre_Lab)) = UPPER(TRIM(NEW.Nombre_Lab))
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Ya existe una ubicación con ese nombre.';
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_ubicaciones_bu` BEFORE UPDATE ON `ubicaciones` FOR EACH ROW BEGIN
    SET NEW.Nombre_Lab = TRIM(NEW.Nombre_Lab);

    IF NEW.Nombre_Lab IS NULL OR NEW.Nombre_Lab = '' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El nombre de la ubicación es obligatorio.';
    END IF;

    IF CHAR_LENGTH(NEW.Nombre_Lab) > 15 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El nombre de la ubicación no puede exceder 15 caracteres.';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM ubicaciones
        WHERE UPPER(TRIM(Nombre_Lab)) = UPPER(TRIM(NEW.Nombre_Lab))
          AND ID_Lab <> OLD.ID_Lab
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Ya existe otra ubicación con ese nombre.';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `Matricula_Uss` int(15) NOT NULL,
  `Nombre_Uss` varchar(150) NOT NULL,
  `Rol_Uss` varchar(30) NOT NULL,
  `Pswd_Uss` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`Matricula_Uss`, `Nombre_Uss`, `Rol_Uss`, `Pswd_Uss`) VALUES
(1, 'Miriam Hernandez', 'Administrador', '$2y$10$sml2Q835drxdLkg6x6Gf6.gixhiHdZ.JDBpDjAyCkk1PGIK3iTC3K'),
(243060, 'Paulina Cervantes', 'Encargado', '$2y$10$vgstse6adK.1Ap5SW8RKd.ed.g4mlpuWdqZO5eF66ajC2crKZLxWq'),
(24306011, 'Cristopher Quintero', 'Administrador', '$2y$10$VUbZZfG/w2MFBcr1hGX4FOJV/q0hBSk35zKV3uGpJ9QpwgjavgGOC'),
(24306012, 'Elisa Haro', 'Administrador', '$2a$10$6m8xYM0RSXW4IXKkou6PXOIpNo3g6uR.LKGpWU6KRi6fOAzhEb1HG');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `activos`
--
ALTER TABLE `activos`
  ADD PRIMARY KEY (`ID_Activo`),
  ADD KEY `fk_id_lab_activos` (`ID_Lab`);

--
-- Indices de la tabla `alumnos`
--
ALTER TABLE `alumnos`
  ADD PRIMARY KEY (`Matricula_Alumno`),
  ADD KEY `Matricula_Alumno` (`Matricula_Alumno`),
  ADD KEY `Matricula_Alumno_2` (`Matricula_Alumno`);

--
-- Indices de la tabla `bitacora_prestamos`
--
ALTER TABLE `bitacora_prestamos`
  ADD PRIMARY KEY (`ID_Bitacora`),
  ADD KEY `fk_id_prestamo` (`ID_Prestamo`),
  ADD KEY `fk_usuario_movimiento` (`Usuario_Movimiento`);

--
-- Indices de la tabla `docentes`
--
ALTER TABLE `docentes`
  ADD PRIMARY KEY (`Matricula_Docente`);

--
-- Indices de la tabla `prestamos`
--
ALTER TABLE `prestamos`
  ADD PRIMARY KEY (`ID_Prestamo`),
  ADD KEY `fk_id_lab` (`ID_Lab`),
  ADD KEY `fk_id_activo` (`ID_Activo`),
  ADD KEY `fk_matricula_alumno` (`Matricula_Alumno`),
  ADD KEY `fk_matricula_docente` (`Matricula_Docente`),
  ADD KEY `fk_registrado_por` (`Registrado_Por`),
  ADD KEY `fk_recibido_por` (`Recibido_Por`),
  ADD KEY `fk_grupo_prestamo` (`Grupo_PrestamoID`);

--
-- Indices de la tabla `prestamos_grupo`
--
ALTER TABLE `prestamos_grupo`
  ADD PRIMARY KEY (`Grupo_PrestamoID`),
  ADD KEY `fk_gp_alumno` (`Matricula_Alumno`),
  ADD KEY `fk_gp_docente` (`Matricula_Docente`),
  ADD KEY `fk_gp_lab` (`ID_Lab`),
  ADD KEY `fk_gp_usuario` (`Registrado_Por`);

--
-- Indices de la tabla `ubicaciones`
--
ALTER TABLE `ubicaciones`
  ADD PRIMARY KEY (`ID_Lab`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`Matricula_Uss`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `activos`
--
ALTER TABLE `activos`
  MODIFY `ID_Activo` int(15) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `bitacora_prestamos`
--
ALTER TABLE `bitacora_prestamos`
  MODIFY `ID_Bitacora` int(15) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de la tabla `prestamos`
--
ALTER TABLE `prestamos`
  MODIFY `ID_Prestamo` int(15) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `prestamos_grupo`
--
ALTER TABLE `prestamos_grupo`
  MODIFY `Grupo_PrestamoID` int(15) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de la tabla `ubicaciones`
--
ALTER TABLE `ubicaciones`
  MODIFY `ID_Lab` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `activos`
--
ALTER TABLE `activos`
  ADD CONSTRAINT `fk_id_lab_activos` FOREIGN KEY (`ID_Lab`) REFERENCES `ubicaciones` (`ID_Lab`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `bitacora_prestamos`
--
ALTER TABLE `bitacora_prestamos`
  ADD CONSTRAINT `fk_id_prestamo` FOREIGN KEY (`ID_Prestamo`) REFERENCES `prestamos` (`ID_Prestamo`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_usuario_movimiento` FOREIGN KEY (`Usuario_Movimiento`) REFERENCES `usuarios` (`Matricula_Uss`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `prestamos`
--
ALTER TABLE `prestamos`
  ADD CONSTRAINT `fk_grupo_prestamo` FOREIGN KEY (`Grupo_PrestamoID`) REFERENCES `prestamos_grupo` (`Grupo_PrestamoID`),
  ADD CONSTRAINT `fk_id_activo` FOREIGN KEY (`ID_Activo`) REFERENCES `activos` (`ID_Activo`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_id_lab` FOREIGN KEY (`ID_Lab`) REFERENCES `ubicaciones` (`ID_Lab`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_matricula_alumno` FOREIGN KEY (`Matricula_Alumno`) REFERENCES `alumnos` (`Matricula_Alumno`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_matricula_docente` FOREIGN KEY (`Matricula_Docente`) REFERENCES `docentes` (`Matricula_Docente`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_recibido_por` FOREIGN KEY (`Recibido_Por`) REFERENCES `usuarios` (`Matricula_Uss`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_registrado_por` FOREIGN KEY (`Registrado_Por`) REFERENCES `usuarios` (`Matricula_Uss`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `prestamos_grupo`
--
ALTER TABLE `prestamos_grupo`
  ADD CONSTRAINT `fk_gp_alumno` FOREIGN KEY (`Matricula_Alumno`) REFERENCES `alumnos` (`Matricula_Alumno`),
  ADD CONSTRAINT `fk_gp_docente` FOREIGN KEY (`Matricula_Docente`) REFERENCES `docentes` (`Matricula_Docente`),
  ADD CONSTRAINT `fk_gp_lab` FOREIGN KEY (`ID_Lab`) REFERENCES `ubicaciones` (`ID_Lab`),
  ADD CONSTRAINT `fk_gp_usuario` FOREIGN KEY (`Registrado_Por`) REFERENCES `usuarios` (`Matricula_Uss`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
