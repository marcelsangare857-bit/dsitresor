
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

function e($valeur) {
    return htmlspecialchars((string)$valeur, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$erreur = '';

$numero_inventaire = '';
$nom = '';
$categorie = '';
$marque = '';
$modele = '';
$numero_serie = '';
$id_site = '';
$date_acquisition = '';
$etat = 'En service';
$observations = '';

try {
    $stmt = $pdo->query(
        "SELECT id, nom FROM sites ORDER BY nom ASC"
    );
    $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $ex) {
    $sites = [];
    $erreur = "Impossible de charger la liste des sites.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !hash_equals(
            $_SESSION['csrf'],
            (string)($_POST['csrf'] ?? '')
        )
    ) {
        http_response_code(403);
        exit('Jeton de sécurité invalide. Rechargez le formulaire.');
    }

    $numero_inventaire = trim(
        (string)($_POST['numero_inventaire'] ?? '')
    );
    $nom = trim((string)($_POST['nom'] ?? ''));
    $categorie = trim((string)($_POST['categorie'] ?? ''));
    $marque = trim((string)($_POST['marque'] ?? ''));
    $modele = trim((string)($_POST['modele'] ?? ''));
    $numero_serie = trim((string)($_POST['numero_serie'] ?? ''));
    $id_site = trim((string)($_POST['id_site'] ?? ''));
    $date_acquisition = trim(
        (string)($_POST['date_acquisition'] ?? '')
    );
    $etat = trim((string)($_POST['etat'] ?? 'En service'));
    $observations = trim(
        (string)($_POST['observations'] ?? '')
    );

    $etatsAutorises = [
        'En service',
        'En panne',
        'En maintenance',
        'Reforme'
    ];

    if (
        $numero_inventaire === '' ||
        $nom === '' ||
        $categorie === ''
    ) {
        $erreur = "Le numéro d'inventaire, le nom et la catégorie sont obligatoires.";

    } elseif (!in_array($etat, $etatsAutorises, true)) {
        $erreur = "L'état sélectionné est invalide.";

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
        $erreur = "La date d'acquisition est invalide.";

    } else {
        try {
            $siteId = null;

            if ($id_site !== '') {
                $siteId = filter_var(
                    $id_site,
                    FILTER_VALIDATE_INT
                );

                if (!$siteId || $siteId < 1) {
                    throw new InvalidArgumentException(
                        "Le site sélectionné est invalide."
                    );
                }

                $verifSite = $pdo->prepare(
                    "SELECT id FROM sites WHERE id = ?"
                );
                $verifSite->execute([$siteId]);

                if (!$verifSite->fetchColumn()) {
                    throw new InvalidArgumentException(
                        "Le site sélectionné n'existe pas."
                    );
                }
            }

            $sql = "INSERT INTO equipements (
                        numero_inventaire,
                        nom,
                        categorie,
                        marque,
                        modele,
                        numero_serie,
                        id_site,
                        date_acquisition,
                        etat,
                        observations
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

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
                $observations !== '' ? $observations : null
            ]);

            header('Location: liste.php?message=ajoute');
            exit;

        } catch (InvalidArgumentException $ex) {
            $erreur = $ex->getMessage();

        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {
                $erreur = "Ce numéro d'inventaire existe déjà ou une contrainte de la base de données a été violée.";
            } else {
                $erreur = "Erreur lors de l'enregistrement de l'équipement.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ajouter un équipement - DSI Trésor Mali</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f2f5f9;
            margin: 0;
            padding: 20px;
            color: #263445;
        }

        .conteneur {
            max-width: 850px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 3px 12px #00000012;
        }

        h1 { color: #123b66; }

        .grille {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
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
            font: inherit;
            box-sizing: border-box;
        }

        textarea { min-height: 90px; }

        .bouton {
            display: inline-block;
            border: 0;
            padding: 11px 16px;
            border-radius: 5px;
            color: white;
            text-decoration: none;
            cursor: pointer;
        }

        .vert { background: #218838; }
        .gris { background: #566573; }

        .actions {
            display: flex;
            gap: 10px;
            margin-top: 22px;
            flex-wrap: wrap;
        }

        .erreur {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 16px;
        }

        @media (max-width: 600px) {
            .grille { grid-template-columns: 1fr; }
            .large { grid-column: auto; }
            .conteneur { padding: 15px; }
        }
    </style>
</head>

<body>
<div class="conteneur">

    <h1>Ajouter un équipement</h1>
    <p>DSI — Trésor Mali</p>

    <?php if ($erreur !== ''): ?>
        <div class="erreur"><?= e($erreur) ?></div>
    <?php endif; ?>

    <form method="POST" action="ajouter.php">

        <input
            type="hidden"
            name="csrf"
            value="<?= e($_SESSION['csrf']) ?>"
        >

        <div class="grille">

            <div class="champ">
                <label for="numero_inventaire">
                    Numéro d'inventaire *
                </label>
                <input
                    id="numero_inventaire"
                    type="text"
                    name="numero_inventaire"
                    maxlength="100"
                    required
                    value="<?= e($numero_inventaire) ?>"
                    placeholder="Ex. DSI-ORD-0001"
                >
            </div>

            <div class="champ">
                <label for="nom">Nom de l'équipement *</label>
                <input
                    id="nom"
                    type="text"
                    name="nom"
                    maxlength="150"
                    required
                    value="<?= e($nom) ?>"
                    placeholder="Ex. Ordinateur du bureau"
                >
            </div>

            <div class="champ">
                <label for="categorie">Catégorie *</label>
                <select id="categorie" name="categorie" required>
                    <option value="">-- Sélectionner --</option>
                    <?php
                    $categories = [
                        'Ordinateur',
                        'Imprimante',
                        'Serveur',
                        'Switch',
                        'Routeur',
                        'Onduleur',
                        'Scanner',
                        'Téléphone',
                        'Autre'
                    ];
                    ?>
                    <?php foreach ($categories as $cat): ?>
                        <option
                            value="<?= e($cat) ?>"
                            <?= $categorie === $cat ? 'selected' : '' ?>
                        >
                            <?= e($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="champ">
                <label for="marque">Marque</label>
                <input
                    id="marque"
                    type="text"
                    name="marque"
                    maxlength="100"
                    value="<?= e($marque) ?>"
                    placeholder="Ex. HP, Dell, Cisco"
                >
            </div>

            <div class="champ">
                <label for="modele">Modèle</label>
                <input
                    id="modele"
                    type="text"
                    name="modele"
                    maxlength="100"
                    value="<?= e($modele) ?>"
                >
            </div>

            <div class="champ">
                <label for="numero_serie">Numéro de série</label>
                <input
                    id="numero_serie"
                    type="text"
                    name="numero_serie"
                    maxlength="150"
                    value="<?= e($numero_serie) ?>"
                >
            </div>

            <div class="champ">
                <label for="id_site">Site d'affectation</label>
                <select id="id_site" name="id_site">
                    <option value="">-- Aucun site --</option>
                    <?php foreach ($sites as $site): ?>
                        <option
                            value="<?= (int)$site['id'] ?>"
                            <?= (string)$id_site === (string)$site['id'] ? 'selected' : '' ?>
                        >
                            <?= e($site['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="champ">
                <label for="date_acquisition">
                    Date d'acquisition
                </label>
                <input
                    id="date_acquisition"
                    type="date"
                    name="date_acquisition"
                    value="<?= e($date_acquisition) ?>"
                >
            </div>

            <div class="champ">
                <label for="etat">État de l'équipement</label>
                <select id="etat" name="etat">
                    <?php foreach ([
                        'En service',
                        'En panne',
                        'En maintenance',
                        'Reforme'
                    ] as $optionEtat): ?>
                        <option
                            value="<?= e($optionEtat) ?>"
                            <?= $etat === $optionEtat ? 'selected' : '' ?>
                        >
                            <?= e($optionEtat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="champ large">
                <label for="observations">Observations</label>
                <textarea
                    id="observations"
                    name="observations"
                    placeholder="État général, caractéristiques ou remarques..."
                ><?= e($observations) ?></textarea>
            </div>

        </div>

        <div class="actions">
            <button type="submit" class="bouton vert">
                Enregistrer l'équipement
            </button>

            <a href="liste.php" class="bouton gris">
                Retour à la liste
            </a>
        </div>

    </form>

</div>
</body>
</html>