<?php
session_start();
require_once __DIR__ . '/../config/connexion.php';

if (
    !isset($_SESSION['id_utilisateur']) &&
    !isset($_SESSION['utilisateur_id']) &&
    !isset($_SESSION['id'])
) {
    header('Location: ../login.php');
    exit;
}

$role = $_SESSION['role']
    ?? $_SESSION['utilisateur']['role']
    ?? '';

if (!in_array($role, ['administrateur', 'responsable_dsi'], true)) {
    http_response_code(403);
    exit("Accès refusé : vous n'êtes pas autorisé à modifier les équipements.");
}

function e($valeur) {
    return htmlspecialchars((string)$valeur, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    exit("Identifiant d'équipement invalide.");
}

$erreur = '';

try {
    $stmt = $pdo->prepare(
        'SELECT * FROM equipements WHERE id = ?'
    );
    $stmt->execute([$id]);
    $equipement = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$equipement) {
        exit("Équipement introuvable.");
    }

    $stmtSites = $pdo->query(
        'SELECT id, nom FROM sites ORDER BY nom ASC'
    );
    $sites = $stmtSites->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $ex) {
    exit("Erreur lors du chargement des données.");
}

$numero_inventaire = $equipement['numero_inventaire'];
$nom = $equipement['nom'];
$categorie = $equipement['categorie'];
$marque = $equipement['marque'] ?? '';
$modele = $equipement['modele'] ?? '';
$numero_serie = $equipement['numero_serie'] ?? '';
$id_site = $equipement['id_site'] === null
    ? ''
    : (string)$equipement['id_site'];
$date_acquisition = $equipement['date_acquisition'] ?? '';
$etat = $equipement['etat'];
$observations = $equipement['observations'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals(
        $_SESSION['csrf'],
        (string)($_POST['csrf'] ?? '')
    )) {
        http_response_code(403);
        exit("Jeton de sécurité invalide. Rechargez la page.");
    }

    $numero_inventaire = trim((string)($_POST['numero_inventaire'] ?? ''));
    $nom = trim((string)($_POST['nom'] ?? ''));
    $categorie = trim((string)($_POST['categorie'] ?? ''));
    $marque = trim((string)($_POST['marque'] ?? ''));
    $modele = trim((string)($_POST['modele'] ?? ''));
    $numero_serie = trim((string)($_POST['numero_serie'] ?? ''));
    $id_site = trim((string)($_POST['id_site'] ?? ''));
    $date_acquisition = trim((string)($_POST['date_acquisition'] ?? ''));
    $etat = trim((string)($_POST['etat'] ?? ''));
    $observations = trim((string)($_POST['observations'] ?? ''));

    $etatsAutorises = [
        'En service',
        'En panne',
        'En maintenance',
        'Reforme'
    ];

    if ($numero_inventaire === '' || $nom === '' || $categorie === '') {
        $erreur = "Le numéro d'inventaire, le nom et la catégorie sont obligatoires.";

    } elseif (!in_array($etat, $etatsAutorises, true)) {
        $erreur = "État de l'équipement invalide.";

    } elseif (
        $date_acquisition !== '' &&
        (
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_acquisition) ||
            !checkdate(
                (int)substr($date_acquisition, 5, 2),
                (int)substr($date_acquisition, 8, 2),
                (int)substr($date_acquisition, 0, 4)
            )
        )
    ) {
        $erreur = "Date d'acquisition invalide.";

    } else {
        try {
            $siteId = null;

            if ($id_site !== '') {
                $siteId = filter_var($id_site, FILTER_VALIDATE_INT);

                if (!$siteId || $siteId < 1) {
                    throw new InvalidArgumentException("Site invalide.");
                }

                $check = $pdo->prepare(
                    'SELECT id FROM sites WHERE id = ?'
                );
                $check->execute([$siteId]);

                if (!$check->fetchColumn()) {
                    throw new InvalidArgumentException(
                        "Le site sélectionné n'existe pas."
                    );
                }
            }

            $sql = "UPDATE equipements SET
                        numero_inventaire = ?,
                        nom = ?,
                        categorie = ?,
                        marque = ?,
                        modele = ?,
                        numero_serie = ?,
                        id_site = ?,
                        date_acquisition = ?,
                        etat = ?,
                        observations = ?
                    WHERE id = ?";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $numero_inventaire,
                $nom,
                $categorie,
                $marque !== '' ? $marque : null,
                $modele !== '' ? $modele : null,
                $numero_serie !== '' ? $numero_serie : null,
                $siteId,
                $date_acquisition !== '' ? $date_acquisition : null,
                $etat,
                $observations !== '' ? $observations : null,
                $id
            ]);

            header('Location: liste.php?message=modifie');
            exit;

        } catch (InvalidArgumentException $ex) {
            $erreur = $ex->getMessage();

        } catch (PDOException $ex) {
            $erreur = $ex->getCode() === '23000'
                ? "Ce numéro d'inventaire est déjà utilisé."
                : "Erreur lors de la modification de l'équipement.";
        }
    }
}

$categories = [
    'Ordinateur', 'Imprimante', 'Serveur', 'Switch',
    'Routeur', 'Onduleur', 'Scanner', 'Téléphone', 'Autre'
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Modifier un équipement - DSI Trésor Mali</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f2f5f9;
            padding: 20px;
            color: #263445;
        }
        .conteneur {
            max-width: 850px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
        }
        h1 { color: #123b66; }
        .grille {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 15px;
        }
        .champ {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }
        .large { grid-column: 1 / -1; }
        input, select, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
            font: inherit;
        }
        textarea { min-height: 90px; }
        .bouton {
            display: inline-block;
            padding: 11px 15px;
            border: 0;
            border-radius: 5px;
            color: white;
            text-decoration: none;
            cursor: pointer;
        }
        .vert { background: #218838; }
        .gris { background: #566573; }
        .erreur {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 5px;
        }
        .actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        @media (max-width: 600px) {
            .grille { grid-template-columns: 1fr; }
            .large { grid-column: auto; }
        }
    </style>
</head>
<body>
<div class="conteneur">

    <h1>Modifier un équipement</h1>

    <?php if ($erreur !== ''): ?>
        <div class="erreur"><?= e($erreur) ?></div>
    <?php endif; ?>

    <form method="POST" action="modifier.php?id=<?= (int)$id ?>">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">

        <div class="grille">

            <div class="champ">
                <label for="numero_inventaire">Numéro d'inventaire *</label>
                <input id="numero_inventaire" name="numero_inventaire"
                    maxlength="100" required
                    value="<?= e($numero_inventaire) ?>">
            </div>

            <div class="champ">
                <label for="nom">Nom de l'équipement *</label>
                <input id="nom" name="nom" maxlength="150" required
                    value="<?= e($nom) ?>">
            </div>

            <div class="champ">
                <label for="categorie">Catégorie *</label>
                <select id="categorie" name="categorie" required>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= e($cat) ?>"
                            <?= $categorie === $cat ? 'selected' : '' ?>>
                            <?= e($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="champ">
                <label for="marque">Marque</label>
                <input id="marque" name="marque" maxlength="100"
                    value="<?= e($marque) ?>">
            </div>

            <div class="champ">
                <label for="modele">Modèle</label>
                <input id="modele" name="modele" maxlength="100"
                    value="<?= e($modele) ?>">
            </div>

            <div class="champ">
                <label for="numero_serie">Numéro de série</label>
                <input id="numero_serie" name="numero_serie" maxlength="150"
                    value="<?= e($numero_serie) ?>">
            </div>

            <div class="champ">
                <label for="id_site">Site d'affectation</label>
                <select id="id_site" name="id_site">
                    <option value="">-- Aucun site --</option>
                    <?php foreach ($sites as $site): ?>
                        <option value="<?= (int)$site['id'] ?>"
                            <?= $id_site === (string)$site['id'] ? 'selected' : '' ?>>
                            <?= e($site['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="champ">
                <label for="date_acquisition">Date d'acquisition</label>
                <input type="date" id="date_acquisition"
                    name="date_acquisition"
                    value="<?= e($date_acquisition) ?>">
            </div>

            <div class="champ">
                <label for="etat">État</label>
                <select id="etat" name="etat">
                    <?php foreach ([
                        'En service', 'En panne',
                        'En maintenance', 'Reforme'
                    ] as $optionEtat): ?>
                        <option value="<?= e($optionEtat) ?>"
                            <?= $etat === $optionEtat ? 'selected' : '' ?>>
                            <?= e($optionEtat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="champ large">
                <label for="observations">Observations</label>
                <textarea id="observations" name="observations"><?= e($observations) ?></textarea>
            </div>

        </div>

        <div class="actions">
            <button type="submit" class="bouton vert">
                Enregistrer les modifications
            </button>
            <a href="liste.php" class="bouton gris">Retour à la liste</a>
        </div>
    </form>
</div>
</body>
</html>