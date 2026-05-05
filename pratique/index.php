<?php
require_once 'config.php';

// Récupérer tous les animaux
$animaux = $pdo->query("
    SELECT a.*, p.nom as proprietaire_nom, p.prenom 
    FROM animaux a 
    LEFT JOIN proprietaires p ON a.id_proprietaire = p.id 
    ORDER BY a.nom
")->fetchAll();

// Récupérer les maladies pour le filtre
$maladies = $pdo->query("SELECT * FROM maladies ORDER BY nom_maladie")->fetchAll();

// Filtrage des visites par maladie
$visites_filtrees = [];
$maladie_selectionnee = null;
if (isset($_POST['filtrer'])) {
    $maladie_selectionnee = $_POST['maladie_id'];
    $stmt = $pdo->prepare("
        SELECT v.*, a.nom as animal_nom, m.nom_maladie 
        FROM visites v
        JOIN animaux a ON v.id_animal = a.id
        JOIN maladies m ON v.id_maladie = m.id
        WHERE v.id_maladie = ?
        ORDER BY v.date_visite DESC
    ");
    $stmt->execute([$maladie_selectionnee]);
    $visites_filtrees = $stmt->fetchAll();
}

// Récupérer les visites
$visites = $pdo->query("
    SELECT v.*, a.nom as animal_nom, m.nom_maladie 
    FROM visites v
    JOIN animaux a ON v.id_animal = a.id
    LEFT JOIN maladies m ON v.id_maladie = m.id
    ORDER BY v.date_visite DESC
")->fetchAll();

$proprietaires = $pdo->query("SELECT * FROM proprietaires")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinique Vétérinaire</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f0f8f0; padding: 20px; }
        .container { max-width: 1300px; margin: 0 auto; }
        
        .header { background: #2e7d32; color: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .header h1 { margin-bottom: 10px; }
        .nav { display: flex; gap: 15px; flex-wrap: wrap; margin-top: 10px; }
        .nav a { color: white; text-decoration: none; padding: 8px 16px; background: #1b5e20; border-radius: 5px; }
        .nav a:hover { background: #0d3b0f; }
        
        .card { background: white; border-radius: 10px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .card h2 { color: #2e7d32; margin-bottom: 15px; border-bottom: 2px solid #2e7d32; padding-bottom: 10px; }
        
        .form-group { margin-bottom: 15px; }
        label { font-weight: bold; display: block; margin-bottom: 5px; }
        input, select, textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 5px; }
        
        button, .btn { background: #2e7d32; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-danger { background: #c62828; }
        .btn-warning { background: #f57c00; }
        .btn-small { padding: 5px 10px; font-size: 12px; margin: 2px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #2e7d32; color: white; }
        tr:hover { background: #f5f5f5; }
        
        .message { background: #c8e6c9; padding: 10px; border-radius: 5px; margin-bottom: 20px; color: #1b5e20; }
        .row { display: flex; gap: 20px; flex-wrap: wrap; }
        .col { flex: 1; min-width: 250px; }
        
        @media (max-width: 768px) {
            .col { flex: 100%; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🐾 Clinique Vétérinaire</h1>
        <div class="nav">
            <a href="#animaux">🐕 Animaux</a>
            <a href="#visites">📅 Visites</a>
            <a href="#filtrer">🔍 Filtrer</a>
            <a href="ajouter.php">➕ Ajouter</a>
        </div>
    </div>

    <?php if(isset($_SESSION['message'])): ?>
        <div class="message"><?= $_SESSION['message']; unset($_SESSION['message']); ?></div>
    <?php endif; ?>

    <!-- LISTE DES ANIMAUX -->
    <div class="card" id="animaux">
        <h2>🐕 Liste des animaux</h2>
        <table>
            <thead>
                <tr><th>Nom</th><th>Type</th><th>Âge</th><th>Couleur</th><th>Propriétaire</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach($animaux as $animal): ?>
                <tr>
                    <td><?= htmlspecialchars($animal['nom']) ?></td>
                    <td><?= htmlspecialchars($animal['type']) ?></td>
                    <td><?= $animal['age'] ?> ans</td>
                    <td><?= htmlspecialchars($animal['couleur']) ?></td>
                    <td><?= htmlspecialchars($animal['prenom'] . ' ' . $animal['proprietaire_nom']) ?></td>
                    <td>
                        <a href="modifier.php?id=<?= $animal['id'] ?>" class="btn btn-small btn-warning">✏️</a>
                        <a href="supprimer.php?id=<?= $animal['id'] ?>" class="btn btn-small btn-danger" onclick="return confirm('Supprimer ?')">🗑️</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- JOURNAL DES VISITES -->
    <div class="card" id="visites">
        <h2>📅 Journal des visites</h2>
        <table>
            <thead>
                <tr><th>Date</th><th>Animal</th><th>Symptômes</th><th>Maladie</th><th>Traitement</th></tr>
            </thead>
            <tbody>
                <?php foreach($visites as $v): ?>
                <tr>
                    <td><?= $v['date_visite'] ?></td>
                    <td><?= htmlspecialchars($v['animal_nom']) ?></td>
                    <td><?= htmlspecialchars($v['symptomes']) ?></td>
                    <td><?= htmlspecialchars($v['nom_maladie']) ?></td>
                    <td><?= htmlspecialchars($v['traitement']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- FILTRAGE PAR MALADIE -->
    <div class="card" id="filtrer">
        <h2>🔍 Filtrer les visites par maladie</h2>
        <form method="POST">
            <div class="row">
                <div class="col">
                    <select name="maladie_id" required style="width: 100%; padding: 10px;">
                        <option value="">-- Choisir une maladie --</option>
                        <?php foreach($maladies as $m): ?>
                            <option value="<?= $m['id'] ?>" <?= ($maladie_selectionnee == $m['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m['nom_maladie']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col">
                    <button type="submit" name="filtrer">🔍 Filtrer</button>
                </div>
            </div>
        </form>

        <?php if($visites_filtrees): ?>
            <h3 style="margin-top: 20px;">Résultats (<?= count($visites_filtrees) ?> visite(s))</h3>
            <table>
                <thead><tr><th>Date</th><th>Animal</th><th>Symptômes</th><th>Traitement</th></tr></thead>
                <tbody>
                    <?php foreach($visites_filtrees as $vf): ?>
                    <tr>
                        <td><?= $vf['date_visite'] ?></td>
                        <td><?= htmlspecialchars($vf['animal_nom']) ?></td>
                        <td><?= htmlspecialchars($vf['symptomes']) ?></td>
                        <td><?= htmlspecialchars($vf['traitement']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php elseif($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <p style="color: #c62828; margin-top: 15px;">⚠️ Aucune visite trouvée.</p>
        <?php endif; ?>
    </div>

    <!-- LISTES RAPIDES -->
    <div class="row">
        <div class="col">
            <div class="card">
                <h2>👥 Propriétaires</h2>
                <ul>
                    <?php foreach($proprietaires as $p): ?>
                        <li><?= htmlspecialchars($p['prenom'] . ' ' . $p['nom']) ?> - <?= $p['telephone'] ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <div class="col">
            <div class="card">
                <h2>🏥 Maladies</h2>
                <ul>
                    <?php foreach($maladies as $m): ?>
                        <li><strong><?= htmlspecialchars($m['nom_maladie']) ?></strong> : <?= htmlspecialchars($m['description']) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>
</body>
</html>
