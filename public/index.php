<?php
declare(strict_types=1);

$host = getenv('MYSQLHOST') ?: '127.0.0.1';
$port = getenv('MYSQLPORT') ?: '3306';
$db   = getenv('MYSQLDATABASE') ?: 'productos';
$user = getenv('MYSQLUSER') ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: '';

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS productos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(120) NOT NULL,
            precio DECIMAL(10,2) NOT NULL,
            stock INT NOT NULL DEFAULT 0,
            creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
} catch (PDOException $e) {
    http_response_code(500);
    exit('No se pudo conectar con la base de datos. Verifica la configuración.');
}

function h(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$error = '';
$editar = null;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $accion = $_POST['accion'] ?? '';

        if ($accion === 'guardar') {
            $id = (int)($_POST['id'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $precio = filter_var($_POST['precio'] ?? null, FILTER_VALIDATE_FLOAT);
            $stock = filter_var($_POST['stock'] ?? null, FILTER_VALIDATE_INT);

            if ($nombre === '' || mb_strlen($nombre) > 120 ||
                $precio === false || $precio < 0 ||
                $stock === false || $stock < 0) {
                throw new InvalidArgumentException('Revisa el nombre, el precio y el stock.');
            }

            if ($id > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE productos SET nombre=?, precio=?, stock=? WHERE id=?'
                );
                $stmt->execute([$nombre, $precio, $stock, $id]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO productos (nombre, precio, stock) VALUES (?, ?, ?)'
                );
                $stmt->execute([$nombre, $precio, $stock]);
            }
        } elseif ($accion === 'eliminar') {
            $stmt = $pdo->prepare('DELETE FROM productos WHERE id=?');
            $stmt->execute([(int)($_POST['id'] ?? 0)]);
        }

        header('Location: /');
        exit;
    }

    if (isset($_GET['editar'])) {
        $stmt = $pdo->prepare('SELECT * FROM productos WHERE id=?');
        $stmt->execute([(int)$_GET['editar']]);
        $editar = $stmt->fetch() ?: null;
    }

    $productos = $pdo->query(
        'SELECT * FROM productos ORDER BY id DESC'
    )->fetchAll();
} catch (Throwable $e) {
    $error = $e instanceof InvalidArgumentException
        ? $e->getMessage()
        : 'No se pudo completar la operación. Revisa los datos e inténtalo de nuevo.';
    $productos = $productos ?? [];
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Gestión de productos | Aplicación PHP</title>
<style>
body{font-family:Arial,sans-serif;max-width:950px;margin:35px auto;padding:0 16px;color:#222}
input,button{padding:9px;margin:4px 0}input{max-width:100%;box-sizing:border-box}
table{width:100%;border-collapse:collapse;margin-top:20px}
th,td{border:1px solid #ddd;padding:10px;text-align:left}
th{background:#f2f2f2}.error{color:#a00}.inline{display:inline}
</style>
</head>
<body>
<h1>Sistema de Gestión de Productos</h1>
<p>Aplicación A — PHP y MySQL</p>

<?php if ($error !== ''): ?>
<p class="error"><?= h($error) ?></p>
<?php endif; ?>

<h2><?= $editar ? 'Editar producto' : 'Registrar producto' ?></h2>
<form method="post">
<input type="hidden" name="accion" value="guardar">
<input type="hidden" name="id" value="<?= h($editar['id'] ?? 0) ?>">
<label>Nombre<br>
<input name="nombre" maxlength="120" required
value="<?= h($editar['nombre'] ?? '') ?>"></label><br>
<label>Precio<br>
<input type="number" name="precio" min="0" step="0.01" required
value="<?= h($editar['precio'] ?? '') ?>"></label><br>
<label>Stock<br>
<input type="number" name="stock" min="0" step="1" required
value="<?= h($editar['stock'] ?? 0) ?>"></label><br>
<button type="submit">Guardar</button>
<?php if ($editar): ?><a href="/">Cancelar edición</a><?php endif; ?>
</form>

<h2>Productos registrados</h2>
<table>
<thead><tr><th>ID</th><th>Nombre</th><th>Precio</th><th>Stock</th><th>Acciones</th></tr></thead>
<tbody>
<?php foreach ($productos as $p): ?>
<tr>
<td><?= h($p['id']) ?></td>
<td><?= h($p['nombre']) ?></td>
<td>$<?= h(number_format((float)$p['precio'], 2)) ?></td>
<td><?= h($p['stock']) ?></td>
<td>
<a href="/?editar=<?= h($p['id']) ?>">Editar</a>
<form class="inline" method="post"
onsubmit="return confirm('¿Eliminar este producto?')">
<input type="hidden" name="accion" value="eliminar">
<input type="hidden" name="id" value="<?= h($p['id']) ?>">
<button type="submit">Eliminar</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</body>
</html>
