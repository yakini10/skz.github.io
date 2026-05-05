<?php
session_start();

require_once 'config.php';
$db = getDB();

// СОЗДАНИЕ ТАБЛИЦЫ ADMIN
$db->exec("
    CREATE TABLE IF NOT EXISTS admin (
        id INT PRIMARY KEY AUTO_INCREMENT,
        login VARCHAR(50) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

// Создание администратора по умолчанию
$stmt = $db->query("SELECT COUNT(*) FROM admin");
if ($stmt->fetchColumn() == 0) {
    $hash = password_hash('123', PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO admin (login, password_hash) VALUES (?, ?)");
    $stmt->execute(['admin', $hash]);
}

// AUTHENTIFICATION PAR SESSION 
$admin_logged_in = !empty($_SESSION['admin_logged_in']);

// Déconnexion
if (isset($_GET['logout_admin'])) {
    session_destroy();
    header('Location: admin.php');
    exit();
}

// Traitement du formulaire de connexion
if (empty($_SERVER['PHP_AUTH_USER']) 
    empty($_SERVER['PHP_AUTH_PW']) 
    $_SERVER['PHP_AUTH_USER'] != 'admin' ||
    md5($_SERVER['PHP_AUTH_PW']) != md5('123')) {
  header('HTTP/1.1 401 Unanthorized');
  header('WWW-Authenticate: Basic realm="My site"');
  print('<h1>401 Требуется авторизация</h1>');
  exit();
}

// Formulaire de connexion si non authentifié

    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <title>Вход в администрирование</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
                padding: 20px;
            }
            .login-form {
                background: white;
                padding: 40px;
                border-radius: 20px;
                width: 350px;
                text-align: center;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            }
            h1 { color: #333; margin-bottom: 25px; font-size: 24px; }
            .form-group { margin-bottom: 20px; text-align: left; }
            label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; }
            input {
                width: 100%;
                padding: 12px;
                border: 2px solid #e0e0e0;
                border-radius: 8px;
                font-size: 14px;
            }
            input:focus { outline: none; border-color: #667eea; }
            button {
                width: 100%;
                padding: 12px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                border-radius: 8px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
            }
            button:hover { transform: translateY(-2px); }
            .error { color: #f44336; margin-bottom: 15px; font-size: 14px; }
            .info { margin-top: 20px; font-size: 12px; color: #888; }
            .info a { color: #667eea; text-decoration: none; }
        </style>
    </head>
    <body>
        <div class="login-form">
            <h1> Администрирование</h1>
            <?php if (isset($auth_error)): ?>
                <div class="error"><?= htmlspecialchars($auth_error) ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label>Логин</label>
                    <input type="text" name="login" placeholder="admin" required autofocus>
                </div>
                <div class="form-group">
                    <label>Пароль</label>
                    <input type="password" name="password" placeholder="***" required>
                </div>
                <button type="submit" name="admin_login">Войти</button>
            </form>
            <div class="info">
<p>Логин по умолчанию: <strong>admin</strong> / <strong>123</strong></p>
                <p><a href="index.php">← На главную</a></p>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();



// ОБРАБОТКА ДЕЙСТВИЙ
$action = $_GET['action'] ?? 'list';
$message = null;
$error = null;

// УДАЛЕНИЕ
if ($action === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    try {
        $db->beginTransaction();
        
        $stmt = $db->prepare("DELETE FROM application_languages WHERE application_id = ?");
        $stmt->execute([$id]);
        
        $stmt = $db->prepare("DELETE FROM users WHERE application_id = ?");
        $stmt->execute([$id]);
        
        $stmt = $db->prepare("DELETE FROM application WHERE id = ?");
        $stmt->execute([$id]);
        
        $db->commit();
        $message = "Данные успешно удалены.";
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Ошибка при удалении.";
    }
}

// РЕДАКТИРОВАНИЕ
$edit_data = null;
$edit_languages = [];
if ($action === 'edit' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $db->beginTransaction();
            
            $stmt = $db->prepare("UPDATE application SET 
                fio = ?, phone = ?, email = ?, birth_date = ?, 
                gender = ?, biography = ?, contract_accepted = ? 
                WHERE id = ?");
            
            $stmt->execute([
                $_POST['fio'],
                $_POST['phone'],
                $_POST['email'],
                $_POST['birth_date'],
                $_POST['gender'],
                $_POST['biography'],
                isset($_POST['contract_accepted']) ? 1 : 0,
                $id
            ]);
            
            $stmt = $db->prepare("DELETE FROM application_languages WHERE application_id = ?");
            $stmt->execute([$id]);
            
            if (!empty($_POST['languages'])) {
                foreach ($_POST['languages'] as $lang_id) {
                    $stmt = $db->prepare("INSERT INTO application_languages VALUES (?, ?)");
                    $stmt->execute([$id, $lang_id]);
                }
            }
            
            $db->commit();
            $message = "Данные успешно изменены.";
            header('Location: admin.php');
            exit();
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Ошибка при изменении.";
        }
    }
    
    $stmt = $db->prepare("SELECT * FROM application WHERE id = ?");
    $stmt->execute([$id]);
    $edit_data = $stmt->fetch();
    
    if (!$edit_data) {
        $error = "Данные не найдены";
        $action = 'list';
    } else {
        $stmt = $db->prepare("SELECT language_id FROM application_languages WHERE application_id = ?");
        $stmt->execute([$id]);
        $edit_languages = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

// ОТОБРАЖЕНИЕ ДАННЫХ
$stmt = $db->query("
    SELECT a.*, u.login 
    FROM application a 
    LEFT JOIN users u ON a.id = u.application_id 
    ORDER BY a.id DESC
");
$applications = $stmt->fetchAll();

// СТАТИСТИКА
$stmt = $db->query("
    SELECT l.id, l.name, COUNT(al.application_id) as nb
    FROM languages l
    LEFT JOIN application_languages al ON l.id = al.language_id
    GROUP BY l.id
    ORDER BY nb DESC
");
$statistiques = $stmt->fetchAll();
$langs_list = [
    1 => 'Pascal', 2 => 'C', 3 => 'C++', 4 => 'JavaScript',
    5 => 'PHP', 6 => 'Python', 7 => 'Java', 8 => 'Haskell',
    9 => 'Clojure', 10 => 'Prolog', 11 => 'Scala', 12 => 'Go'
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Администрирование - Управление заявками</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 1300px; margin: 0 auto; }
        .header {
            background: white;
            padding: 20px 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .header h1 { color: #667eea; font-size: 24px; }
        .header a {
            background: #667eea;
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 8px;
            transition: 0.3s;
        }
        .header a:hover { background: #764ba2; }
        .stats {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 25px;
        }
        .stats h2 { color: #333; margin-bottom: 15px; border-left: 4px solid #667eea; padding-left: 15px; font-size: 18px; }
        .stats-grid { display: flex; flex-wrap: wrap; gap: 12px; }
        .stat-item {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            text-align: center;
            min-width: 90px;
        }
        .stat-item .lang { font-size: 13px; opacity: 0.9; }
        .stat-item .count { font-size: 28px; font-weight: bold; }
        .message { background: #d4edda; color: #155724; padding: 12px 20px; border-radius: 10px; margin-bottom: 20px; }
        .error { background: #f8d7da; color: #721c24; padding: 12px 20px; border-radius: 10px; margin-bottom: 20px; }
        .table-wrapper { background: white; border-radius: 15px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #667eea; color: white; padding: 12px 10px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #eee; }
        tr:hover { background: #f5f5f5; }
        .btn { display: inline-block; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 12px; margin: 0 2px; }
        .btn-edit { background: #ffc107; color: #333; }
        .btn-delete { background: #dc3545; color: white; }
        .footer-note { text-align: center; margin-top: 20px; color: white; font-size: 12px; }
        .btn-cancel { background: #6c757d; padding: 12px 25px; border-radius: 8px; color: white; text-decoration: none; margin-left: 10px; }
        .form-edit { background: white; padding: 30px; border-radius: 15px; max-width: 650px; margin: 0 auto; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 5px; color: #555; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; }
        button { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 25px; border: none; border-radius: 8px; cursor: pointer; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Администрирование - Управление заявками</h1>
        <div>
            <a href="index.php"> Форма регистрации</a>
            <a href="login.php" style="background: #6c757d;"> Вход пользователя</a>
            <a href="admin.php?logout_admin=1" style="background: #dc3545;"> Выйти (админ)</a>
        </div>
    </div>
<?php if ($message): ?>
        <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <div class="stats">
        <h2>Статистика: Предпочитаемые языки программирования</h2>
        <div class="stats-grid">
            <?php foreach ($statistiques as $stat): ?>
                <div class="stat-item">
                    <div class="lang"><?= htmlspecialchars($stat['name']) ?></div>
                    <div class="count"><?= $stat['nb'] ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <?php if ($action === 'edit' && $edit_data): ?>
        <div class="form-edit">
            <h2>Редактирование данных (ID: <?= $id ?>)</h2>
            <form method="POST">
                <div class="form-group">
                    <label>ФИО *</label>
                    <input type="text" name="fio" value="<?= htmlspecialchars($edit_data['fio']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Телефон *</label>
                    <input type="tel" name="phone" value="<?= htmlspecialchars($edit_data['phone']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($edit_data['email']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Дата рождения</label>
                    <input type="date" name="birth_date" value="<?= htmlspecialchars($edit_data['birth_date']) ?>">
                </div>
                <div class="form-group">
                    <label>Пол</label>
                    <select name="gender">
                        <option value="male" <?= $edit_data['gender'] == 'male' ? 'selected' : '' ?>>Мужчина</option>
                        <option value="female" <?= $edit_data['gender'] == 'female' ? 'selected' : '' ?>>Женщина</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Любимый язык программирования</label>
                    <select name="languages[]" multiple size="6">
                        <?php foreach ($langs_list as $id_lang => $name): ?>
                            <option value="<?= $id_lang ?>" <?= in_array($id_lang, $edit_languages) ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Биография</label>
                    <textarea name="biography" rows="4"><?= htmlspecialchars($edit_data['biography']) ?></textarea>
                </div>
                <div class="form-group">
                    <label><input type="checkbox" name="contract_accepted" value="1" <?= $edit_data['contract_accepted'] ? 'checked' : '' ?>> С контрактом ознакомлен(а)</label>
                </div>
                <button type="submit"> Сохранить</button>
                <a href="admin.php" class="btn-cancel"> Отмена</a>
            </form>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>ID</th><th>ФИО</th><th>Email</th><th>Телефон</th><th>Дата рождения</th><th>Пол</th><th>Логин</th><th>Действия</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($applications)): ?>
                        <tr><td colspan="8" style="text-align:center; padding:40px;">Нет ни одной заявки</td></tr>
                    <?php else: ?>
                        <?php foreach ($applications as $app): ?>
                            <tr>
                                <td><?= $app['id'] ?></td>
                                <td><?= htmlspecialchars($app['fio']) ?></td>
                              <td><?= htmlspecialchars($app['email']) ?></td>
                                <td><?= htmlspecialchars($app['phone']) ?></td>
                                <td><?= htmlspecialchars($app['birth_date']) ?></td>
                                <td><?= $app['gender'] == 'male' ? 'Мужской' : 'Женский' ?></td>
                                <td><?= htmlspecialchars($app['login'] ?? '—') ?></td>
                                <td>
                                    <a href="admin.php?action=edit&id=<?= $app['id'] ?>" class="btn btn-edit"> Редактировать</a>
                                    <a href="admin.php?action=delete&id=<?= $app['id'] ?>" class="btn btn-delete" onclick="return confirm('Удалить эту заявку навсегда?')"> Удалить</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="footer-note">
            Всего: <?= count($applications) ?> заявка(и) Вы вошли как администратор
        </div>
    <?php endif; ?>
</div>
</body>
</html>
  
