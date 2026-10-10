
<?php
session_start();

require_once __DIR__ . '/../config/connexion.php';

// Vérifier que l'utilisateur est connecté
$idDeclarant = $_SESSION['id_utilisateur']
    ?? $_SESSION['utilisateur_id']
    ?? $_SESSION['id_user']
    ?? $_SESSION['utilisateur']['id']
    ?? null;

if (!$idDeclarant || !is_numeric($idDeclarant)) {
    die("Session utilisateur introuvable. Déconnectez-vous puis reconnectez-vous.");
}

$idDeclarant = (int) $idDeclarant;

function h($valeur)
{
    return htmlspecialchars(
        (string) $valeur,
        ENT_QUOTES,
        'UTF-8'
    );
}

$erreur = '';

$priorites = [
    'basse',
    'normale',
    'haute',
    'critique'
];

// Charger les équipements existants
$stmtEquipements = $pdo->query(
    "SELECT id, code_inventaire, designation
     FROM equipements
     ORDER BY designation"
);

$equipements = $stmtEquipements->fetchAll();

// Valeurs initiales du formulaire
$numero = 'INC-' . date('Ymd-His');
$titre = '';
$description = '';
$priorite = 'normale';
$idEquipement = '';
$idSite = '';
$idTechnicien = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $numero = trim($_POST['numero_incident'] ?? '');
    $titre = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $priorite = $_POST['priorite'] ?? 'normale';

    $idEquipement = $_POST['id_equipement'] ?? '';
    $idSite = trim($_POST['id_site'] ?? '');
    $idTechnicien = trim($_POST['id_technicien'] ?? '');

    if ($numero === '' || $titre === '' || $description === '') {
        $erreur = 'Le numéro, le titre et la description sont obligatoires.';
    } elseif (!in_array($priorite, $priorites, true)) {
        $erreur = 'La priorité sélectionnée est invalide.';
    } elseif (
        ($idEquipement !== '' && !ctype_digit((string) $idEquipement))
        || ($idSite !== '' && !ctype_digit($idSite))
        || ($idTechnicien !== '' && !ctype_digit($idTechnicien))
    ) {
        $erreur = 'Un identifiant de site, équipement ou technicien est invalide.';
    } else {
        try {
            // Vérifier l'utilisateur déclarant
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM utilisateurs WHERE id = ?"
            );
            $stmt->execute([$idDeclarant]);

            if ((int) $stmt->fetchColumn() === 0) {
                throw new RuntimeException(
                    "L'utilisateur connecté n'existe pas dans la table utilisateurs."
                );
            }

            // Vérifier l'équipement sélectionné
            if ($idEquipement !== '') {
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM equipements WHERE id = ?"
                );
                $stmt->execute([(int) $idEquipement]);

                if ((int) $stmt->fetchColumn() === 0) {
                    throw new RuntimeException(
                        "L'équipement sélectionné n'existe pas."
                    );
                }
            }

            // Le statut initial autorisé dans votre table est « nouveau »
            $sql = "INSERT INTO incidents (
                        numero_incident,
                        titre,
                        description,
                        priorite,
                        statut,
                        id_site,
                        id_equipement,
                        id_declarant,
                        id_technicien
                    ) VALUES (?, ?, ?, ?, 'nouveau', ?, ?, ?, ?)";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                $numero,
                $titre,
                $description,
                $priorite,
                $idSite !== '' ? (int) $idSite : null,
                $idEquipement !== '' ? (int) $idEquipement : null,
                $idDeclarant,
                $idTechnicien !== '' ? (int) $idTechnicien : null
            ]);

            header('Location: liste.php?ajout=ok');
            exit;

        } catch (Throwable $e) {
            error_log('Ajout incident : ' . $e->getMessage());

            $erreur = 'Impossible d’enregistrer l’incident : '
                . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Déclarer un incident - DSI Trésor Mali</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f3f5f8;
            margin: 0;
            padding: 25px;
        }

        .conteneur {
            max-width: 750px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px #ddd;
        }

        h1 {
            color: #17365d;
        }

        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }

        input, textarea, select {
            box-sizing: border-box;
            width: 100%;
            padding: 10px;
            margin-top: 6px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        textarea {
            min-height: 120px;
        }

        button, .retour {
            display: inline-block;
            margin-top: 20px;
            padding: 11px 18px;
            border: none;
            border-radius: 5px;
            color: white;
            background: #176b3a;
            text-decoration: none;
            cursor: pointer;
        }

        .retour {
            background: #555;
        }

        .erreur {
            padding: 12px;
            color: #842029;
            background: #f8d7da;
            border-radius: 5px;
            overflow-wrap: anywhere;
        }
    </style>
</head>

<body>
<div class="conteneur">

    <h1>Déclarer un incident</h1>
    <p>DSI — Trésor Mali</p>

    <?php if ($erreur !== ''): ?>
        <div class="erreur">
            <?= h($erreur) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">

        <label for="numero_incident">Numéro de l'incident</label>
        <input
            type="text"
            id="numero_incident"
            name="numero_incident"
            value="<?= h($numero) ?>"
            maxlength="50"
            required
        >

        <label for="titre">Titre de l'incident</label>
        <input
            type="text"
            id="titre"
            name="titre"
            value="<?= h($titre) ?>"
            maxlength="200"
            required
        >

        <label for="description">Description</label>
        <textarea
            id="description"
            name="description"
            required
        ><?= h($description) ?></textarea>

        <label for="priorite">Priorité</label>
        <select id="priorite" name="priorite" required>
            <?php foreach ($priorites as $p): ?>
                <option
                    value="<?= h($p) ?>"
                    <?= $priorite === $p ? 'selected' : '' ?>
                >
                    <?= h(ucfirst($p)) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="id_equipement">Équipement concerné</label>
        <select id="id_equipement" name="id_equipement">
            <option value="">Aucun équipement sélectionné</option>

            <?php foreach ($equipements as $eq): ?>
                <option
                    value="<?= (int) $eq['id'] ?>"
                    <?= (string) $idEquipement === (string) $eq['id']
                        ? 'selected'
                        : '' ?>
                >
                    <?= h($eq['code_inventaire'] . ' - ' . $eq['designation']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="id_site">Identifiant du site (facultatif)</label>
        <input
            type="number"
            id="id_site"
            name="id_site"
            min="1"
            value="<?= h($idSite) ?>"
        >

        <label for="id_technicien">
            Identifiant du technicien (facultatif)
        </label>
        <input
            type="number"
            id="id_technicien"
            name="id_technicien"
            min="1"
            value="<?= h($idTechnicien) ?>"
        >

        <button type="submit">Enregistrer l'incident</button>
        <a class="retour" href="liste.php">Retour à la liste</a>

    </form>
</div>
</body>
</html>
