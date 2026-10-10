
<?php
session_start();

require_once __DIR__ . '/../config/connexion.php';

// Vérifier la connexion de l'utilisateur
if (
    !isset($_SESSION['id_utilisateur']) &&
    !isset($_SESSION['utilisateur_id']) &&
    !isset($_SESSION['id'])
) {
    header('Location: ../login.php');
    exit;
}

// Récupérer le rôle de l'utilisateur
$role = $_SESSION['role']
    ?? $_SESSION['utilisateur']['role']
    ?? '';

$peutGerer = in_array(
    $role,
    ['administrateur', 'responsable_dsi'],
    true
);

// Fonction de protection HTML
function e($valeur)
{
    return htmlspecialchars(
        (string)$valeur,
        ENT_QUOTES,
        'UTF-8'
    );
}

// Créer un jeton CSRF
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$erreur = '';

// Messages de confirmation
$messages = [
    'ajoute'    => 'Le site a été ajouté avec succès.',
    'modifie'   => 'Le site a été modifié avec succès.',
    'desactive' => 'Le site a été désactivé.',
    'reactive'  => 'Le site a été réactivé.'
];

$message = $messages[
    (string)($_GET['message'] ?? '')
] ?? '';

// Activer ou désactiver un site
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['changer_statut_id'])
) {
    if (!$peutGerer) {
        http_response_code(403);
        exit('Accès refusé : vous ne pouvez pas gérer les sites.');
    }

    if (
        !hash_equals(
            $_SESSION['csrf'],
            (string)($_POST['csrf'] ?? '')
        )
    ) {
        http_response_code(403);
        exit('Jeton de sécurité invalide. Rechargez la page.');
    }

    $idSite = filter_var(
        $_POST['changer_statut_id'],
        FILTER_VALIDATE_INT
    );

    $nouvelEtat = (string)($_POST['nouvel_etat'] ?? '');

    if (
        !$idSite ||
        $idSite < 1 ||
        !in_array($nouvelEtat, ['0', '1'], true)
    ) {
        $erreur = 'Les informations transmises sont invalides.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'UPDATE sites SET actif = ? WHERE id = ?'
            );

            $stmt->execute([
                (int)$nouvelEtat,
                $idSite
            ]);

            header(
                'Location: liste.php?message=' .
                ($nouvelEtat === '1' ? 'reactive' : 'desactive')
            );
            exit;

        } catch (PDOException $ex) {
            $erreur = 'Impossible de modifier le statut du site.';
        }
    }
}

// Recherche des sites
$recherche = trim((string)($_GET['recherche'] ?? ''));

try {
    if ($recherche !== '') {
        $stmt = $pdo->prepare(
            "SELECT id, nom, localisation, type_site, actif
             FROM sites
             WHERE nom LIKE :recherche
                OR localisation LIKE :recherche
                OR type_site LIKE :recherche
             ORDER BY nom ASC"
        );

        $stmt->execute([
            'recherche' => '%' . $recherche . '%'
        ]);

        $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } else {
        $stmt = $pdo->query(
            "SELECT id, nom, localisation, type_site, actif
             FROM sites
             ORDER BY nom ASC"
        );

        $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $ex) {
    $sites = [];
    $erreur = 'Erreur lors du chargement des sites.';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Liste des sites - DSI Trésor Mali</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f2f5f9;
            margin: 0;
            padding: 20px;
            color: #263445;
        }

        .conteneur {
            max-width: 1200px;
            margin: 0 auto;
            background: #fff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.08);
        }

        h1 {
            color: #123b66;
            margin-top: 0;
        }

        .barre {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 20px;
        }

        input[type="search"] {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            min-width: 230px;
        }

        .bouton {
            display: inline-block;
            padding: 9px 13px;
            border: none;
            border-radius: 5px;
            color: white;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
            white-space: nowrap;
        }

        .bleu {
            background: #1769aa;
        }

        .vert {
            background: #218838;
        }

        .rouge {
            background: #c0392b;
        }

        .gris {
            background: #566573;
        }

        .bouton:hover {
            opacity: 0.85;
        }

        .message {
            padding: 12px;
            background: #d4edda;
            color: #155724;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .erreur {
            padding: 12px;
            background: #f8d7da;
            color: #721c24;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }

        th {
            background: #123b66;
            color: white;
        }

        tr:nth-child(even) {
            background: #f8fafc;
        }

        .statut {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }

        .actif {
            background: #d4edda;
            color: #155724;
        }

        .inactif {
            background: #f8d7da;
            color: #721c24;
        }

        .actions {
            display: flex;
            gap: 7px;
            flex-wrap: wrap;
            align-items: center;
        }

        .actions form {
            margin: 0;
        }

        .vide {
            text-align: center;
            padding: 25px;
            color: #666;
        }

        @media (max-width: 600px) {
            body {
                padding: 8px;
            }

            .conteneur {
                padding: 12px;
            }
        }
    </style>
</head>

<body>

<div class="conteneur">

    <h1>Gestion des sites</h1>

    <p>
        Application de gestion du parc informatique
        — DSI Trésor Mali
    </p>

    <?php if ($message !== ''): ?>
        <div class="message">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($erreur !== ''): ?>
        <div class="erreur">
            <?= e($erreur) ?>
        </div>
    <?php endif; ?>

    <div class="barre">

        <form method="GET" action="liste.php">
            <input
                type="search"
                name="recherche"
                placeholder="Rechercher un site..."
                value="<?= e($recherche) ?>"
            >

            <button type="submit" class="bouton bleu">
                Rechercher
            </button>

            <a href="liste.php" class="bouton gris">
                Réinitialiser
            </a>
        </form>

        <?php if ($peutGerer): ?>
            <a href="ajouter.php" class="bouton vert">
                + Ajouter un site
            </a>
        <?php endif; ?>

        <a href="../index.php" class="bouton gris">
            Retour au tableau de bord
        </a>

    </div>

    <div class="table-responsive">

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom du site</th>
                    <th>Localisation</th>
                    <th>Type de site</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>

            <?php if (count($sites) > 0): ?>

                <?php foreach ($sites as $site): ?>

                    <tr>
                        <td><?= (int)$site['id'] ?></td>

                        <td><?= e($site['nom']) ?></td>

                        <td>
                            <?= e($site['localisation'] ?? '') ?>
                        </td>

                        <td>
                            <?= e($site['type_site'] ?? '') ?>
                        </td>

                        <td>
                            <?php if ((int)$site['actif'] === 1): ?>
                                <span class="statut actif">
                                    Actif
                                </span>
                            <?php else: ?>
                                <span class="statut inactif">
                                    Inactif
                                </span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <div class="actions">

                                <?php if ($peutGerer): ?>

                                    <a
                                        href="modifier.php?id=<?= (int)$site['id'] ?>"
                                        class="bouton bleu"
                                    >
                                        Modifier
                                    </a>

                                    <form
                                        method="POST"
                                        action="liste.php"
                                        onsubmit="return confirm('Confirmer le changement de statut de ce site ?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf"
                                            value="<?= e($_SESSION['csrf']) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="changer_statut_id"
                                            value="<?= (int)$site['id'] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="nouvel_etat"
                                            value="<?= (int)$site['actif'] === 1 ? '0' : '1' ?>"
                                        >

                                        <?php if ((int)$site['actif'] === 1): ?>

                                            <button
                                                type="submit"
                                                class="bouton rouge"
                                            >
                                                Désactiver
                                            </button>

                                        <?php else: ?>

                                            <button
                                                type="submit"
                                                class="bouton vert"
                                            >
                                                Réactiver
                                            </button>

                                        <?php endif; ?>

                                    </form>

                                <?php else: ?>

                                    <span>Consultation uniquement</span>

                                <?php endif; ?>

                            </div>
                        </td>
                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>
                    <td colspan="6" class="vide">
                        Aucun site trouvé dans la base de données.
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>
        </table>

    </div>

    <p>
        Nombre de sites affichés :
        <strong><?= count($sites) ?></strong>
    </p>

</div>

</body>
</html>