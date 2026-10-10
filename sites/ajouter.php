
<?php
session_start();

require_once __DIR__ . '/../config/connexion.php';

// Vérifier la connexion
$idConnecte = $_SESSION['id_utilisateur']
    ?? $_SESSION['utilisateur_id']
    ?? $_SESSION['id']
    ?? null;

if (!$idConnecte) {
    header('Location: ../login.php');
    exit;
}

// Vérifier le rôle
$role = $_SESSION['role'] ?? 'consultation';

if (isset($_SESSION['utilisateur']) && is_array($_SESSION['utilisateur'])) {
    $role = $_SESSION['utilisateur']['role'] ?? $role;
}

if (!in_array($role, ['administrateur', 'responsable_dsi'], true)) {
    http_response_code(403);
    exit('Accès refusé : vous ne pouvez pas ajouter un site.');
}

// Jeton de sécurité
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function e($valeur): string
{
    return htmlspecialchars((string)$valeur, ENT_QUOTES, 'UTF-8');
}

$nom = '';
$localisation = '';
$typeSite = '';
$actif = 1;
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier le jeton
    if (!hash_equals(
        $_SESSION['csrf'],
        (string)($_POST['csrf'] ?? '')
    )) {
        http_response_code(403);
        exit('Jeton de sécurité invalide. Rechargez la page.');
    }

    $nom = trim($_POST['nom'] ?? '');
    $localisation = trim($_POST['localisation'] ?? '');
    $typeSite = trim($_POST['type_site'] ?? '');
    $actif = isset($_POST['actif']) && $_POST['actif'] === '1' ? 1 : 0;

    if ($nom === '') {
        $erreur = 'Le nom du site est obligatoire.';
    } elseif (mb_strlen($nom) > 150) {
        $erreur = 'Le nom du site ne doit pas dépasser 150 caractères.';
    } elseif (mb_strlen($localisation) > 150) {
        $erreur = 'La localisation ne doit pas dépasser 150 caractères.';
    } elseif (mb_strlen($typeSite) > 100) {
        $erreur = 'Le type de site ne doit pas dépasser 100 caractères.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO sites
                    (nom, localisation, type_site, actif)
                VALUES
                    (?, ?, ?, ?)
            ");

            $stmt->execute([
                $nom,
                $localisation !== '' ? $localisation : null,
                $typeSite !== '' ? $typeSite : null,
                $actif
            ]);

            header('Location: liste.php?message=ajoute');
            exit;

        } catch (PDOException $ex) {
            $erreur = 'Erreur lors de l’enregistrement du site. '
                . 'Vérifiez la base de données.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un site - DSI Trésor Mali</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
        }

        header {
            background: #123b64;
            color: white;
            padding: 22px 30px;
        }

        header h1 { margin: 0 0 7px; }
        header p { margin: 0; color: #dbeafe; }

        .conteneur {
            max-width: 750px;
            margin: 30px auto;
            padding: 0 18px;
        }

        .navigation { margin-bottom: 20px; }

        .navigation a {
            color: #1d4ed8;
            text-decoration: none;
        }

        .carte {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,.06);
        }

        .champ { margin-bottom: 20px; }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
        }

        input[type="text"], select {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 15px;
        }

        .case {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .case label { margin: 0; }

        .boutons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 25px;
        }

        .bouton {
            padding: 11px 16px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            cursor: pointer;
            font-size: 15px;
        }

        .enregistrer { background: #15803d; color: white; }
        .annuler { background: #64748b; color: white; }

        .erreur {
            padding: 13px;
            background: #fee2e2;
            color: #991b1b;
            border-radius: 6px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<header>
    <h1>Ajouter un site</h1>
    <p>DSI — Trésor Mali</p>
</header>

<main class="conteneur">
    <nav class="navigation">
        <a href="liste.php">← Retour à la liste des sites</a>
    </nav>

    <section class="carte">
        <h2>Informations du site</h2>

        <?php if ($erreur !== ''): ?>
            <div class="erreur"><?= e($erreur) ?></div>
        <?php endif; ?>

        <form method="POST" action="ajouter.php">
            <input
                type="hidden"
                name="csrf"
                value="<?= e($_SESSION['csrf']) ?>"
            >

            <div class="champ">
                <label for="nom">Nom du site *</label>
                <input
                    type="text"
                    id="nom"
                    name="nom"
                    maxlength="150"
                    value="<?= e($nom) ?>"
                    required
                >
            </div>

            <div class="champ">
                <label for="localisation">Localisation</label>
                <input
                    type="text"
                    id="localisation"
                    name="localisation"
                    maxlength="150"
                    value="<?= e($localisation) ?>"
                    placeholder="Ex. Bamako, Kayes, Gao"
                >
            </div>

            <div class="champ">
                <label for="type_site">Type de site</label>
                <input
                    type="text"
                    id="type_site"
                    name="type_site"
                    maxlength="100"
                    value="<?= e($typeSite) ?>"
                    placeholder="Ex. Direction, Trésorerie régionale"
                >
            </div>

            <div class="champ">
                <label for="actif">État du site</label>
                <select id="actif" name="actif">
                    <option value="1" <?= $actif === 1 ? 'selected' : '' ?>>
                        Actif
                    </option>
                    <option value="0" <?= $actif === 0 ? 'selected' : '' ?>>
                        Inactif
                    </option>
                </select>
            </div>

            <div class="boutons">
                <button type="submit" class="bouton enregistrer">
                    Enregistrer le site
                </button>

                <a href="liste.php" class="bouton annuler">
                    Annuler
                </a>
            </div>
        </form>
    </section>
</main>

</body>
</html>