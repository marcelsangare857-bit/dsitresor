
<?php
session_start();

require_once __DIR__ . '/../config/connexion.php';

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    exit("Identifiant invalide.");
}

$stmt = $pdo->prepare(
    "SELECT id, code_inventaire, designation
     FROM equipements
     WHERE id = :id"
);
$stmt->execute(['id' => $id]);
$equipement = $stmt->fetch();

if (!$equipement) {
    exit("Équipement introuvable.");
}

$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['confirmation'] ?? '') !== 'oui') {
        header('Location: liste.php');
        exit;
    }

    try {
        $delete = $pdo->prepare(
            "DELETE FROM equipements WHERE id = :id"
        );
        $delete->execute(['id' => $id]);

        header('Location: liste.php?suppression=ok');
        exit;

    } catch (PDOException $e) {
        error_log($e->getMessage());
        $erreur = "Suppression impossible. Cet équipement peut être lié à un incident ou à une autre donnée.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Supprimer un équipement</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            padding: 30px;
            color: #1e293b;
        }
        main {
            max-width: 600px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
        }
        .alerte {
            background: #fee2e2;
            color: #991b1b;
            padding: 15px;
            border-radius: 6px;
        }
        button, a {
            display: inline-block;
            padding: 11px 15px;
            margin: 8px 5px 0 0;
            border: 0;
            border-radius: 5px;
            text-decoration: none;
            cursor: pointer;
        }
        button {
            background: #b91c1c;
            color: white;
        }
        a {
            background: #64748b;
            color: white;
        }
        .erreur { color: #b91c1c; }
    </style>
</head>
<body>
<main>
    <h1>Confirmer la suppression</h1>

    <p>Souhaites-tu vraiment supprimer cet équipement ?</p>

    <div class="alerte">
        <strong><?= h($equipement['code_inventaire']) ?></strong><br>
        <?= h($equipement['designation']) ?>
    </div>

    <?php if ($erreur !== ''): ?>
        <p class="erreur"><?= h($erreur) ?></p>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="confirmation" value="oui">
        <button type="submit">Confirmer la suppression</button>
        <a href="liste.php">Annuler</a>
    </form>
</main>
</body>
</html>