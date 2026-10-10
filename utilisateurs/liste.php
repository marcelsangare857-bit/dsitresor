

<?php
session_start();

require_once __DIR__ . '/../config/connexion.php';

// ==================================================
// 1. VERIFIER LA CONNEXION
// ==================================================
$idConnecte = $_SESSION['id_utilisateur']
    ?? $_SESSION['utilisateur_id']
    ?? $_SESSION['id']
    ?? $_SESSION['utilisateur']['id']
    ?? null;

if (!$idConnecte) {
    header('Location: ../login.php');
    exit;
}

// ==================================================
// 2. VERIFIER LE ROLE ADMINISTRATEUR
// ==================================================
$roleConnecte = $_SESSION['role']
    ?? $_SESSION['utilisateur']['role']
    ?? '';

if ($roleConnecte !== 'administrateur') {
    http_response_code(403);
    exit('Accès refusé : cette page est réservée à l’administrateur.');
}

// ==================================================
// 3. CREER UN JETON DE SECURITE
// ==================================================
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// ==================================================
// 4. FONCTION DE PROTECTION HTML
// ==================================================
function e($valeur)
{
    return htmlspecialchars(
        (string) $valeur,
        ENT_QUOTES,
        'UTF-8'
    );
}

// ==================================================
// 5. RECUPERER LES UTILISATEURS
// ==================================================
try {
    $sql = "SELECT id, nom, prenom, email, role, actif, date_creation
            FROM utilisateurs
            ORDER BY id DESC";

    $stmt = $pdo->query($sql);
    $utilisateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $ex) {
    $utilisateurs = [];
    $erreur = "Impossible de charger les utilisateurs.";
}

// ==================================================
// 6. MESSAGES
// ==================================================
$message = '';

if (isset($_GET['message'])) {
    switch ($_GET['message']) {
        case 'ajoute':
            $message = 'Utilisateur ajouté avec succès.';
            break;

        case 'modifie':
            $message = 'Utilisateur modifié avec succès.';
            break;

        case 'statut':
            $message = 'Statut du compte mis à jour.';
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Gestion des utilisateurs - DSI Trésor Mali</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
        }

        header {
            background: #123b66;
            color: white;
            padding: 20px 30px;
        }

        header h1 {
            margin: 0 0 8px;
        }

        header p {
            margin: 0;
            color: #dbeafe;
        }

        main {
            max-width: 1200px;
            margin: 30px auto;
            padding: 25px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.07);
        }

        .actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 25px;
        }

        .btn {
            display: inline-block;
            padding: 9px 12px;
            border: none;
            border-radius: 5px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            font-size: 13px;
            cursor: pointer;
        }

        .btn:hover {
            opacity: 0.88;
        }

        .btn-retour {
            background: #475569;
        }

        .btn-modifier {
            background: #0f766e;
        }

        .btn-statut {
            background: #b45309;
        }

        .message {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 5px;
            background: #dcfce7;
            color: #166534;
        }

        .erreur {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 5px;
            background: #fee2e2;
            color: #991b1b;
        }

        .table-conteneur {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        th {
            background: #eaf0f7;
            color: #123b66;
        }

        tr:hover td {
            background: #f8fafc;
        }

        .badge {
            display: inline-block;
            padding: 5px 8px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: bold;
        }

        .actif {
            background: #dcfce7;
            color: #166534;
        }

        .inactif {
            background: #fee2e2;
            color: #991b1b;
        }

        form {
            display: inline-block;
            margin: 3px 0;
        }

        .compteur {
            margin-bottom: 15px;
            color: #64748b;
        }

        footer {
            text-align: center;
            padding: 20px;
            color: #64748b;
            font-size: 13px;
        }

        @media (max-width: 600px) {
            main {
                margin: 15px;
                padding: 15px;
            }

            header {
                padding: 20px;
            }
        }
    </style>
</head>

<body>

<header>
    <h1>DSI Trésor Mali</h1>
    <p>Gestion des utilisateurs et des rôles</p>
</header>

<main>

    <div class="actions">
        <a class="btn btn-retour" href="../index.php">
            ← Tableau de bord
        </a>

        <a class="btn" href="ajouter.php">
            + Ajouter un utilisateur
        </a>
    </div>

    <h2>Liste des utilisateurs</h2>

    <?php if ($message !== ''): ?>
        <div class="message">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <?php if (isset($erreur)): ?>
        <div class="erreur">
            <?= e($erreur) ?>
        </div>
    <?php else: ?>

        <p class="compteur">
            Nombre d'utilisateurs :
            <strong><?= count($utilisateurs) ?></strong>
        </p>

        <div class="table-conteneur">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nom complet</th>
                        <th>E-mail</th>
                        <th>Rôle</th>
                        <th>Statut</th>
                        <th>Date de création</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (empty($utilisateurs)): ?>
                    <tr>
                        <td colspan="7">
                            Aucun utilisateur enregistré.
                        </td>
                    </tr>
                <?php else: ?>

                    <?php foreach ($utilisateurs as $u): ?>
                        <tr>
                            <td><?= (int) $u['id'] ?></td>

                            <td>
                                <?= e($u['prenom'] . ' ' . $u['nom']) ?>
                            </td>

                            <td><?= e($u['email']) ?></td>

                            <td><?= e($u['role']) ?></td>

                            <td>
                                <?php if ((int) $u['actif'] === 1): ?>
                                    <span class="badge actif">Actif</span>
                                <?php else: ?>
                                    <span class="badge inactif">Désactivé</span>
                                <?php endif; ?>
                            </td>

                            <td><?= e($u['date_creation']) ?></td>

                            <td>
                                <a
                                    class="btn btn-modifier"
                                    href="modifier.php?id=<?= (int) $u['id'] ?>"
                                >
                                    Modifier
                                </a>

                                <?php if ((int) $u['id'] !== (int) $idConnecte): ?>
                                    <form
                                        method="post"
                                        action="statut.php"
                                        onsubmit="return confirm('Confirmer le changement de statut de cet utilisateur ?');"
                                    >
                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int) $u['id'] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="csrf"
                                            value="<?= e($_SESSION['csrf']) ?>"
                                        >

                                        <button
                                            class="btn btn-statut"
                                            type="submit"
                                        >
                                            <?= (int) $u['actif'] === 1
                                                ? 'Désactiver'
                                                : 'Activer' ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

</main>

<footer>
    DSI Trésor Mali &copy; <?= date('Y') ?>
</footer>

</body>
</html>