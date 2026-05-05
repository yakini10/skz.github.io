<?php
require_once 'config.php';

$id = $_GET['id'];
$animal = $pdo->prepare("SELECT * FROM animaux WHERE id = ?");
$animal->execute([$id]);
$animal = $animal->fetch();

if (!$animal) {
    header('Location: index.php');
    exit();
}

$proprietaires = $pdo->query("SELECT * FROM proprietaires ORDER BY nom")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sql = "UPDATE animaux SET nom=?, type=?, age=?, couleur=?, id_proprietaire=? WHERE id=?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_POST['nom'], $_POST['type'], $_POST['age'], $_POST['couleur'], $_POST['id_proprietaire'], $id]);
    $_SESSION['message'] = "Animal modifié !";
    header('Location: index.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Modifier un animal</title>
    <style>
        body { font-family: Arial; background: #f0f8f0; padding: 20px; }
        .container { max-width: 500px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; }
        input, select { width: 100%; padding: 8px; margin: 5px 0 15px; border: 1px solid #ddd; border-radius: 5px; }
        button { background: #f57c00; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
        .btn { background: #666; text-decoration: none; color: white; padding: 10px 20px; display: inline-block; border-radius: 5px; }
        h1 { color: #f57c00; }
    </style>
</head>
<body>
<div class="container">
    <h1>✏️ Modifier l'animal</h1>
    <form method="POST">
        <label>Nom</label>
        <input type="text" name="nom" value="<?= htmlspecialchars($animal['nom']) ?>" required>
        
        <label>Type</label>
        <input type="text" name="type" value="<?= htmlspecialchars($animal['type']) ?>" required>
        
        <label>Âge</label>
        <input type="number" name="age" value="<?= $animal['age'] ?>">
        
        <label>Couleur</label>
        <input type="text" name="couleur" value="<?= htmlspecialchars($animal['couleur']) ?>">
        
        <label>Propriétaire</label>
        <select name="id_proprietaire" required>
            <?php foreach($proprietaires as $p): ?>
                <option value="<?= $p['id'] ?>" <?= $p['id'] == $animal['id_proprietaire'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($p['prenom'] . ' ' . $p['nom']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <button type="submit">💾 Mettre à jour</button>
        <a href="index.php" class="btn">Annuler</a>
    </form>
</div>
</body>
</html>
