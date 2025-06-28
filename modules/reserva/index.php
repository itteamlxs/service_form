<?php
// Carga conexión y entorno (db.php ya carga env.php y loadEnv)
require_once __DIR__ . '/../../core/db.php';

// Carga Composer autoload para PHPMailer y demás dependencias
require_once __DIR__ . '/../../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$errores = [];
$exito = false;

// Cargar categorías para el formulario
$stmt = $pdo->prepare("SELECT id, nombre FROM categorias_servicio ORDER BY nombre");
$stmt->execute();
$categorias = $stmt->fetchAll();

$idTutorias = null;
foreach ($categorias as $cat) {
    if (stripos($cat['nombre'], 'tutorías') !== false) {
        $idTutorias = $cat['id'];
        break;
    }
}

// Carpeta para subir PDFs de temas de tutoría
$uploadDir = __DIR__ . '/../../uploads/temas/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $edad = (int) ($_POST['edad'] ?? 0);
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $categoria_id = (int) ($_POST['categoria'] ?? 0);
    $subservicio_id = (int) ($_POST['subservicio'] ?? 0);
    $modalidad = $_POST['modalidad'] ?? '';
    $fecha = $_POST['fecha'] ?? '';
    $hora = $_POST['hora'] ?? '';
    $observaciones = strip_tags($_POST['observaciones'] ?? '');
    $archivoTema = null;

    // Validaciones
    if ($nombre === '' || strlen($nombre) > 100) $errores[] = "Nombre inválido.";
    if ($edad < 10 || $edad > 100) $errores[] = "Edad fuera de rango.";
    if (!$email) $errores[] = "Email inválido.";
    if (!$categoria_id) $errores[] = "Categoría inválida.";
    if (!$subservicio_id) $errores[] = "Subservicio inválido.";
    if (!in_array($modalidad, ['presencial', 'en línea'])) $errores[] = "Modalidad inválida.";
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) $errores[] = "Fecha inválida.";
    if (!preg_match('/^\d{2}:\d{2}$/', $hora)) $errores[] = "Hora inválida.";

    // Validar archivo solo si es tutorías
    if ($categoria_id === $idTutorias) {
        if (!isset($_FILES['archivo_tema']) || $_FILES['archivo_tema']['error'] === UPLOAD_ERR_NO_FILE) {
            $errores[] = "Debe subir un archivo PDF con el tema de la tutoría.";
        } else {
            $file = $_FILES['archivo_tema'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errores[] = "Error al subir archivo.";
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file['tmp_name']);
                if ($mime !== 'application/pdf') {
                    $errores[] = "El archivo debe ser formato PDF.";
                }
                if ($file['size'] > 2 * 1024 * 1024) {
                    $errores[] = "El archivo no puede superar los 2MB.";
                }
            }
        }
    }

    // Evitar solapamientos en reservas
    if (empty($errores)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservas WHERE subservicio_id = ? AND fecha = ? AND hora = ?");
        $stmt->execute([$subservicio_id, $fecha, $hora]);
        if ($stmt->fetchColumn() > 0) {
            $errores[] = "Ya existe una reserva para ese servicio en esa fecha y hora.";
        }
    }

    if (empty($errores)) {
        try {
            $pdo->beginTransaction();

            if ($categoria_id === $idTutorias) {
                $ext = '.pdf';
                $basename = bin2hex(random_bytes(8));
                $filename = $basename . $ext;
                $filepath = $uploadDir . $filename;

                if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                    throw new Exception("No se pudo guardar el archivo.");
                }
                $archivoTema = $filename;
            }

            $stmt = $pdo->prepare("INSERT INTO clientes (nombre, edad, email) VALUES (?, ?, ?)");
            $stmt->execute([$nombre, $edad, $email]);
            $cliente_id = $pdo->lastInsertId();

            $stmt = $pdo->prepare("INSERT INTO reservas (cliente_id, subservicio_id, modalidad, fecha, hora, observaciones, archivo_tema) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$cliente_id, $subservicio_id, $modalidad, $fecha, $hora, $observaciones, $archivoTema]);

            $pdo->commit();

            // Enviar email cliente
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = getenv('SMTP_HOST');
            $mail->SMTPAuth = true;
            $mail->Username = getenv('SMTP_USER');
            $mail->Password = getenv('SMTP_PASS');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = getenv('SMTP_PORT') ?: 587;

            $mail->setFrom(getenv('SMTP_FROM') ?: getenv('SMTP_USER'), getenv('SMTP_FROM_NAME') ?: 'Reserva Cursos');
            $mail->addAddress($email, $nombre);
            $mail->Subject = "Confirmación de reserva";
            $mail->Body = "Hola $nombre,\n\nGracias por reservar el servicio.\nFecha: $fecha\nHora: $hora\nModalidad: $modalidad\nObservaciones: $observaciones\n\nSaludos.";
            $mail->send();

            // Enviar email admin
            $mailAdmin = new PHPMailer(true);
            $mailAdmin->isSMTP();
            $mailAdmin->Host = getenv('SMTP_HOST');
            $mailAdmin->SMTPAuth = true;
            $mailAdmin->Username = getenv('SMTP_USER');
            $mailAdmin->Password = getenv('SMTP_PASS');
            $mailAdmin->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mailAdmin->Port = getenv('SMTP_PORT') ?: 587;

            $mailAdmin->setFrom(getenv('SMTP_FROM') ?: getenv('SMTP_USER'), getenv('SMTP_FROM_NAME') ?: 'Reserva Cursos');
            $mailAdmin->addAddress(getenv('ADMIN_EMAIL'));
            $mailAdmin->Subject = "Nueva reserva recibida";
            $bodyAdmin = "Nueva reserva:\nCliente: $nombre\nEmail: $email\nFecha: $fecha\nHora: $hora\nModalidad: $modalidad\nObservaciones: $observaciones\n";
            if ($archivoTema) {
                $bodyAdmin .= "Archivo tema: $archivoTema\n";
            }
            $mailAdmin->Body = $bodyAdmin;
            $mailAdmin->send();

            $exito = true;

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("[".date('Y-m-d H:i:s')."] Error reserva: " . $e->getMessage() . "\n", 3, __DIR__ . "/../../logs/db_errors.log");
            $errores[] = "Error al procesar la reserva. Intenta más tarde.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Reserva de Cursos</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body class="bg-light">
  <div class="container py-4">
    <h2 class="mb-4 text-center">Reserva de Curso con Confirmación por Email</h2>

    <?php if (!empty($errores)): ?>
      <div class="alert alert-danger"><ul class="mb-0">
        <?php foreach ($errores as $err): ?>
          <li><?= htmlspecialchars($err) ?></li>
        <?php endforeach; ?>
      </ul></div>
    <?php elseif ($exito): ?>
      <div class="alert alert-success">¡Reserva registrada y correo enviado con éxito!</div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="border p-4 bg-white shadow rounded" id="formReserva">

      <div class="row mb-3">
        <div class="col-md-6">
          <label class="form-label">Nombre completo</label>
          <input type="text" name="nombre" class="form-control" required />
        </div>
        <div class="col-md-3">
          <label class="form-label">Edad</label>
          <input type="number" name="edad" class="form-control" min="10" max="100" required />
        </div>
        <div class="col-md-3">
          <label class="form-label">Correo</label>
          <input type="email" name="email" class="form-control" required />
        </div>
      </div>

      <div class="row mb-3">
        <div class="col-md-6">
          <label class="form-label">Categoría</label>
          <select name="categoria" id="categoria" class="form-select" required>
            <option value="">Selecciona una</option>
            <?php foreach ($categorias as $cat): ?>
              <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Subservicio</label>
          <select name="subservicio" id="subservicio" class="form-select" required>
            <option value="">Selecciona un subservicio</option>
          </select>
        </div>
      </div>

      <div class="row mb-3">
        <div class="col-md-4">
          <label class="form-label">Modalidad</label>
          <select name="modalidad" class="form-select" required>
            <option value="">Selecciona</option>
            <option value="presencial">Presencial</option>
            <option value="en línea">En línea</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Fecha</label>
          <input type="date" name="fecha" class="form-control" required />
        </div>
        <div class="col-md-4">
          <label class="form-label">Hora</label>
          <input type="time" name="hora" class="form-control" required />
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label">Observaciones</label>
        <textarea name="observaciones" class="form-control" rows="3"></textarea>
      </div>

      <!-- Archivo PDF para tutorías -->
      <div class="mb-3" id="divArchivoTema" style="display:none;">
        <label class="form-label">Sube el PDF del tema de la tutoría</label>
        <input type="file" name="archivo_tema" id="archivo_tema" accept="application/pdf" class="form-control" />
        <div class="form-text">Máximo 2MB. Solo PDF.</div>
      </div>

      <div class="text-end">
        <button type="submit" class="btn btn-primary">Reservar</button>
      </div>
    </form>
  </div>

<script>
  document.getElementById('categoria').addEventListener('change', function () {
    const categoriaId = this.value;
    const subservicioSelect = document.getElementById('subservicio');
    const divArchivoTema = document.getElementById('divArchivoTema');
    subservicioSelect.innerHTML = '<option>Cargando...</option>';

    const tutoriasId = <?= json_encode($idTutorias) ?>;
    if (categoriaId === tutoriasId) {
      divArchivoTema.style.display = 'block';
      document.getElementById('archivo_tema').required = true;
    } else {
      divArchivoTema.style.display = 'none';
      document.getElementById('archivo_tema').required = false;
    }

    fetch('cargar_subservicios.php?categoria_id=' + categoriaId)
      .then(res => res.json())
      .then(data => {
        subservicioSelect.innerHTML = '<option value="">Selecciona un subservicio</option>';
        data.forEach(item => {
          const opt = document.createElement('option');
          opt.value = item.id;
          opt.textContent = item.nombre;
          subservicioSelect.appendChild(opt);
        });
      })
      .catch(() => {
        subservicioSelect.innerHTML = '<option>Error al cargar</option>';
      });
  });
</script>
</body>
</html>
