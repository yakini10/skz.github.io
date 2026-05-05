<?php
require_once 'config.php';

$proprietaires = $pdo->query("SELECT * FROM proprietaires ORDER BY nom")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sql = "INSERT INTO animaux (nom, type, age, couleur, id_proprietaire) VALUES (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_POST['nom'], $_POST['type'], $_POST['age'], $_POST['couleur'], $_POST['id_proprietaire']]);
    $_SESSION['message'] = "Animal ajouté !";
    header('Location: index.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ajouter un animal</title>
    <style>
        body { font-family: Arial; background: #f0f8f0; padding: 20px; }
        .container { max-width: 500px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; }
        input, select { width: 100%; padding: 8px; margin: 5px 0 15px; border: 1px solid #ddd; border-radius: 5px; }
        button { background: #2e7d32; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
        .btn { background: #666; text-decoration: none; color: white; padding: 10px 20px; display: inline-block; border-radius: 5px; }
        h1 { color: #2e7d32; }
    </style>
</head>
<body>
<div class="container">
    <h1>➕ Ajouter un animal</h1>
    <form method="POST">
        <label>Nom *</label>
        <input type="text" name="nom" required>
        
        <label>Type * (chien, chat...)</label>
        <input type="text" name="type" required>
        
        <label>Âge (années)</label>
        <input type="number" name="age">
        
        <label>Couleur</label>
        <input type="text" name="couleur">
        
        <label>Propriétaire *</label>
        <select name="id_proprietaire" required>
            <option value="">-- Sélectionner --</option>
            <?php foreach($proprietaires as $p): ?>
                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['prenom'] . ' ' . $p['nom']) ?></option>
            <?php endforeach; ?>
        </select>
        
        <button type="submit">💾 Enregistrer</button>
        <a href="index.php" class="btn">Annuler</a>
    </form>
</div>
</body>
</html>
