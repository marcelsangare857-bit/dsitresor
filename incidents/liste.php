
<?php
session_start();
require_once __DIR__ . '/../config/connexion.php';

function h($valeur) {
    return htmlspecialchars((string)$valeur, ENT_QUOTES, 'UTF-8');
}

$sql = "SELECT i.*, e.code_inventaire, e.designation
        FROM incidents i
        LEFT JOIN equipements e ON i.id_equipement = e.id
        ORDER BY i.date_declaration DESC";

$stmt = $pdo->query($sql);
$incidents = $stmt->fetchAll();

$messages = [
    'ajout' => 'Incident déclaré avec succès.',
    'modification' => 'Incident modifié avec succès.',
    'suppression' => 'Incident supprimé avec succès.'
];

$message = '';
foreach ($messages as $cle => $texte) {
    if (isset($_GET[$cle]) && $_GET[$cle] === 'ok') {
        $message = $texte;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestion des incidents - DSI Trésor Mali</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f3f5f9;
            margin: 0;
            padding: 25px;
        }
        .conteneur {
            max-width: 1200px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
        }
        h1 { color: #17365d; }
        .bouton {
            display: inline-block;
            background: #176b3a;
            color: white;
            padding: 10px 14px;
            text-decoration: none;
            border-radius: 5px;
            margin: 5px 0 15px;
        }
        .retour { background: #526273; }
        .message {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 5px;
        }
        .table-wrapper { overflow-x: auto; }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        th { background: #17365d; color: white; }
        tr:nth-child(even) { background: #f7f9fc; }
        .statut { font-weight: bold; }
        .vide { padding: 20px; text-align: center; }
    </style>
</head>
<body>
<div class="conteneur">

    <h1>Gestion des incidents informatiques</h1>
    <p>Direction des systèmes d'information — Trésor Mali</p>

    <?php if ($message !== ''): ?>
        <div class="message"><?= h($message) ?></div>
    <?php endif; ?>

    <a class="bouton" href="ajouter.php">+ Déclarer un incident</a>
    <a class="bouton retour" href="../index.php">Retour au tableau de bord</a>

    <div class="table-wrapper">
    <table>
        <thead>
        <tr>
            <th>N° incident</th>
            <th>Titre</th>
            <th>Catégorie</th>
            <th>Équipement</th>
            <th>Priorité</th>
            <th>Statut</th>
            <th>Date de déclaration</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php if (count($incidents) > 0): ?>
            <?php foreach ($incidents as $incident): ?>
            <tr>
                <td><?= h($incident['numero_incident']) ?></td>
                <td><?= h($incident['titre']) ?></td>
                <td><?= h($incident['categorie'] ?? '') ?></td>
                <td>
                    <?php if ($incident['code_inventaire']): ?>
                        <?= h($incident['code_inventaire']) ?>
                        — <?= h($incident['designation']) ?>
                    <?php else: ?>
                        Non renseigné
                    <?php endif; ?>
                </td>
                <td><?= h($incident['priorite']) ?></td>
                <td class="statut"><?= h($incident['statut']) ?></td>
                <td><?= h($incident['date_declaration']) ?></td>
                <td>
                    <a href="modifier.php?id=<?= (int)$incident['id'] ?>">
                        Modifier
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="8" class="vide">
                    Aucun incident enregistré pour le moment.
                </td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>

</div>
</body>
</html>